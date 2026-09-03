<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BiometricEnableRequest;
use App\Http\Requests\BiometricLoginRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ProfilRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /** Batas percobaan login per email+IP. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    #[OA\Post(
        path: '/api/auth/login',
        operationId: 'authLogin',
        description: 'Menukar email + password dengan access token. Token dipakai di header Authorization: Bearer <token>.',
        summary: 'Login dengan email & password',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'owner@taksasi.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'device_name', description: 'Nama perangkat, tampil di daftar sesi', type: 'string', example: 'Samsung A54'),
                ],
            ),
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login berhasil',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Login berhasil.'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'access_token', type: 'string', example: '3|abcdefghijklmnop'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Email atau password salah'),
            new OA\Response(response: 403, description: 'Akun dinonaktifkan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
            new OA\Response(response: 429, description: 'Terlalu banyak percobaan login'),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login:'.Str::lower((string) $request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return ApiResponse::error(
                sprintf('Terlalu banyak percobaan login. Coba lagi dalam %d detik.', RateLimiter::availableIn($key)),
                429,
                code: 'TOO_MANY_ATTEMPTS',
            );
        }

        $user = User::query()->where('email', $request->input('email'))->first();

        if (! $user || ! $user->checkPassword((string) $request->input('password'))) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return ApiResponse::error('Email atau password salah.', 401, code: 'INVALID_CREDENTIALS');
        }

        if (! $user->is_active) {
            return ApiResponse::error('Akun Anda dinonaktifkan. Hubungi administrator.', 403, code: 'ACCOUNT_DISABLED');
        }

        RateLimiter::clear($key);

        return ApiResponse::success(
            $this->tokenPayload($user, (string) $request->input('device_name', 'mobile')),
            'Login berhasil.',
        );
    }

    #[OA\Post(
        path: '/api/auth/biometric/enable',
        operationId: 'authBiometricEnable',
        description: 'Mendaftarkan login sidik jari untuk perangkat ini. Password diminta ulang karena ini aksi sensitif. Respons berisi biometric_token yang HANYA dikembalikan sekali: simpan di Android Keystore / iOS Keychain (flutter_secure_storage), lalu buka dengan sidik jari (local_auth) untuk login berikutnya. Server hanya menyimpan hash SHA-256-nya.',
        summary: 'Aktifkan login sidik jari',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', description: 'Password akun saat ini', type: 'string', format: 'password'),
                    new OA\Property(property: 'device_name', type: 'string', example: 'Samsung A54'),
                ],
            ),
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sidik jari aktif',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'biometric_token', type: 'string'),
                        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Password salah'),
        ],
    )]
    public function enableBiometric(BiometricEnableRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->checkPassword((string) $request->input('password'))) {
            return ApiResponse::error('Password salah.', 401, code: 'INVALID_PASSWORD');
        }

        $device = $request->input('device_name');
        $plain = $user->issueBiometricToken($device !== null ? (string) $device : null);

        return ApiResponse::success([
            'biometric_token' => $plain,
            'expires_at' => $user->biometric_expires_at?->toIso8601String(),
            'device_name' => $user->biometric_device_name,
        ], 'Login sidik jari berhasil diaktifkan.');
    }

    #[OA\Post(
        path: '/api/auth/biometric/login',
        operationId: 'authBiometricLogin',
        description: 'Menukar biometric_token (yang dibuka dengan sidik jari di perangkat) dengan access token baru. Token biometrik tidak berubah, jadi bisa dipakai berulang sampai kedaluwarsa atau password diganti.',
        summary: 'Login dengan sidik jari',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'biometric_token'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'biometric_token', type: 'string'),
                    new OA\Property(property: 'device_name', type: 'string'),
                ],
            ),
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Login berhasil'),
            new OA\Response(response: 401, description: 'Token biometrik tidak valid / kedaluwarsa, minta login password lagi'),
        ],
    )]
    public function biometricLogin(BiometricLoginRequest $request): JsonResponse
    {
        $key = 'biometric:'.Str::lower((string) $request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return ApiResponse::error(
                sprintf('Terlalu banyak percobaan. Coba lagi dalam %d detik.', RateLimiter::availableIn($key)),
                429,
                code: 'TOO_MANY_ATTEMPTS',
            );
        }

        $user = User::query()->where('email', $request->input('email'))->first();

        if (! $user || ! $user->biometricTokenIsValid((string) $request->input('biometric_token'))) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return ApiResponse::error(
                'Sesi sidik jari tidak valid atau sudah kedaluwarsa. Silakan login dengan password.',
                401,
                code: 'BIOMETRIC_INVALID',
            );
        }

        if (! $user->is_active) {
            return ApiResponse::error('Akun Anda dinonaktifkan. Hubungi administrator.', 403, code: 'ACCOUNT_DISABLED');
        }

        RateLimiter::clear($key);

        return ApiResponse::success(
            $this->tokenPayload($user, (string) $request->input('device_name', 'mobile-biometric')),
            'Login sidik jari berhasil.',
        );
    }

    #[OA\Delete(
        path: '/api/auth/biometric',
        operationId: 'authBiometricDisable',
        description: 'Mencabut token biometrik. Login sidik jari di perangkat mana pun tidak akan berlaku lagi.',
        summary: 'Matikan login sidik jari',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [new OA\Response(response: 200, description: 'Berhasil dicabut')],
    )]
    public function disableBiometric(Request $request): JsonResponse
    {
        $request->user()->revokeBiometricToken();

        return ApiResponse::success(null, 'Login sidik jari dimatikan.');
    }

    #[OA\Get(
        path: '/api/auth/me',
        operationId: 'authMe',
        description: 'Profil pengguna yang sedang login. Dipakai aplikasi saat start-up untuk memastikan token masih valid.',
        summary: 'Profil pengguna aktif',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 401, description: 'Token tidak valid'),
        ],
    )]
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    #[OA\Put(
        path: '/api/auth/profil',
        operationId: 'authProfilUpdate',
        description: 'Melengkapi atau memperbarui data akun sendiri. Peran dan status aktif TIDAK bisa diubah dari sini -- keduanya urusan superadmin, agar petugas tidak bisa menaikkan perannya sendiri.',
        summary: 'Ubah profil sendiri',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Sinta Pratiwi'),
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'phone', type: 'string', nullable: true, example: '081234567890'),
            new OA\Property(property: 'jabatan', type: 'string', nullable: true, example: 'Pelaksana Lapangan'),
            new OA\Property(property: 'alamat', type: 'string', nullable: true),
        ])),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Profil tersimpan', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ],
    )]
    public function updateProfil(ProfilRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return ApiResponse::success(
            new UserResource($user->fresh()),
            'Profil berhasil diperbarui.',
        );
    }

    #[OA\Post(
        path: '/api/auth/change-password',
        operationId: 'authChangePassword',
        description: 'Mengganti password. Untuk keamanan, token biometrik otomatis dicabut (perangkat harus mendaftar sidik jari lagi dengan password baru). Kirim logout_other_devices: true untuk sekaligus mencabut token di perangkat lain; token yang sedang dipakai tetap aktif.',
        summary: 'Ganti password',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password', description: 'Minimal 8 karakter, harus ada huruf & angka', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                    new OA\Property(property: 'logout_other_devices', type: 'boolean', example: true),
                ],
            ),
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Password diganti'),
            new OA\Response(response: 401, description: 'Password saat ini salah'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ],
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->checkPassword((string) $request->input('current_password'))) {
            return ApiResponse::error('Password saat ini salah.', 401, code: 'INVALID_PASSWORD');
        }

        $user->forceFill([
            'password' => $request->input('password'),
            'password_changed_at' => now(),
        ])->save();

        // Sidik jari dicabut: token lama terikat ke password lama.
        $user->revokeBiometricToken();

        if ($request->boolean('logout_other_devices')) {
            $current = $request->user()->currentAccessToken();
            $currentId = $current instanceof PersonalAccessToken ? $current->getKey() : null;

            $user->tokens()
                ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
                ->delete();
        }

        return ApiResponse::success([
            'biometric_reset' => true,
        ], 'Password berhasil diganti. Login sidik jari perlu diaktifkan ulang.');
    }

    #[OA\Post(
        path: '/api/auth/logout',
        operationId: 'authLogout',
        description: 'Mencabut access token yang sedang dipakai. Token biometrik TIDAK dicabut, sehingga login sidik jari tetap bisa dipakai.',
        summary: 'Logout',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [new OA\Response(response: 200, description: 'Logout berhasil')],
    )]
    public function logout(Request $request): JsonResponse
    {
        // currentAccessToken() mengembalikan TransientToken (tanpa delete())
        // bila pengguna terautentikasi lewat sesi, bukan Bearer token.
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return ApiResponse::success(null, 'Logout berhasil.');
    }

    /** @return array<string, mixed> */
    private function tokenPayload(User $user, string $deviceName): array
    {
        $ttl = config('taksasi.token_ttl_minutes');
        $expiresAt = $ttl !== null && $ttl !== '' ? now()->addMinutes((int) $ttl) : null;

        /*
         * SATU SESI PER AKUN.
         *
         * Seluruh token lama dicabut sebelum yang baru diterbitkan, sehingga
         * masuk di perangkat lain otomatis mengeluarkan perangkat sebelumnya.
         * Alasannya bukan teknis melainkan akuntabilitas: setiap tindakan di
         * sini tercatat atas nama satu akun, dan satu akun yang aktif di
         * beberapa perangkat sekaligus membuat jejak aktivitasnya tidak lagi
         * bisa dipertanggungjawabkan ke satu orang.
         *
         * Token sidik jari TIDAK ikut dicabut: ia kredensial milik perangkat,
         * bukan sesi. Perangkat yang terlempar tetap bisa masuk kembali dengan
         * sidik jarinya -- dan saat itu perangkat yang lain yang keluar.
         *
         * Diletakkan di sini, bukan di masing-masing method login, supaya
         * jalur login apa pun yang ditambahkan nanti ikut terkena aturannya.
         */
        $user->tokens()->delete();

        $token = $user->createToken($deviceName !== '' ? $deviceName : 'mobile', ['*'], $expiresAt);

        $user->forceFill(['last_login_at' => now()])->save();

        return [
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => new UserResource($user->refresh()),
        ];
    }
}
