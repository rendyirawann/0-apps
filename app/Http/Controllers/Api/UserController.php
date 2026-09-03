<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Izin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Pengelolaan akun petugas oleh superadmin.
 *
 * Akun superadmin sendiri tidak bisa dibuat, diubah perannya, atau dihapus
 * lewat endpoint ini -- hanya ada satu, ditetapkan lewat seeder.
 */
class UserController extends Controller
{
    #[OA\Get(
        path: '/api/pengguna',
        operationId: 'penggunaIndex',
        description: 'Daftar akun petugas. Hanya superadmin yang boleh mengaksesnya.',
        summary: 'Daftar petugas',
        security: [['bearerAuth' => []]],
        tags: ['Pengguna'],
        parameters: [
            new OA\Parameter(name: 'search', description: 'Cari nama atau email', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 403, description: 'Bukan superadmin'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        if (! Izin::kelolaPengguna($request->user())) {
            return $this->tolak();
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $cari = $request->query('search');

        $paginator = User::query()
            ->where('role', User::PETUGAS)
            ->when(filled($cari), function ($q) use ($cari) {
                $term = '%'.strtolower((string) $cari).'%';

                $q->where(function ($b) use ($term) {
                    $b->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::paginated(
            UserResource::collection($paginator),
            'Daftar petugas.',
        );
    }

    #[OA\Post(
        path: '/api/pengguna',
        operationId: 'penggunaStore',
        description: 'Membuat akun petugas baru. Perannya selalu "petugas" -- tidak bisa ditentukan lewat permintaan, sehingga tidak mungkin tercipta superadmin kedua karena salah kirim.',
        summary: 'Tambah petugas',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Sinta Pratiwi'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'sinta@taksasi.test'),
                    new OA\Property(property: 'password', description: 'Minimal 8 karakter, ada huruf & angka', type: 'string', format: 'password'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ],
            ),
        ),
        tags: ['Pengguna'],
        responses: [
            new OA\Response(response: 201, description: 'Petugas dibuat'),
            new OA\Response(response: 403, description: 'Bukan superadmin'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ],
    )]
    public function store(UserRequest $request): JsonResponse
    {
        $user = User::create($request->dataPetugas());

        return ApiResponse::success(
            // ->fresh() bukan sekadar kehati-hatian: is_active memakai nilai
            // bawaan dari kolom, yang tidak pernah ikut terisi pada instance
            // hasil create(). Tanpa ini, respons pembuatan akun mengirim
            // is_active null padahal di database sudah true, dan aplikasi
            // akan menampilkan petugas baru sebagai nonaktif.
            new UserResource($user->fresh()),
            'Akun petugas berhasil dibuat.',
            201,
        );
    }

    #[OA\Get(
        path: '/api/pengguna/{id}',
        operationId: 'penggunaShow',
        summary: 'Detail petugas',
        security: [['bearerAuth' => []]],
        tags: ['Pengguna'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function show(Request $request, User $user): JsonResponse
    {
        if (! Izin::kelolaPengguna($request->user())) {
            return $this->tolak();
        }

        if ($user->isSuperadmin()) {
            return $this->tolakSuperadmin();
        }

        return ApiResponse::success(new UserResource($user));
    }

    #[OA\Put(
        path: '/api/pengguna/{id}',
        operationId: 'penggunaUpdate',
        description: 'Mengubah data petugas. Kosongkan password bila tidak ingin menggantinya. Mengubah password di sini juga mencabut token sidik jari petugas tersebut.',
        summary: 'Ubah petugas',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'password', description: 'Opsional', type: 'string', nullable: true),
            new OA\Property(property: 'is_active', type: 'boolean'),
        ])),
        tags: ['Pengguna'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Tersimpan')],
    )]
    public function update(UserRequest $request, User $user): JsonResponse
    {
        if ($user->isSuperadmin()) {
            return $this->tolakSuperadmin();
        }

        $gantiPassword = $request->filled('password');

        $user->fill($request->dataPetugas());

        if ($gantiPassword) {
            $user->password_changed_at = now();
        }

        $user->save();

        // Token sidik jari terikat pada password lama; dicabut agar perangkat
        // petugas harus mendaftar ulang dengan password baru.
        if ($gantiPassword) {
            $user->revokeBiometricToken();
            $user->tokens()->delete();
        }

        return ApiResponse::success(
            new UserResource($user->fresh()),
            $gantiPassword
                ? 'Data petugas diperbarui. Sesi dan sidik jari petugas dicabut.'
                : 'Data petugas diperbarui.',
        );
    }

    #[OA\Delete(
        path: '/api/pengguna/{id}',
        operationId: 'penggunaDestroy',
        description: 'Menonaktifkan akun petugas dan mencabut seluruh sesinya. Akunnya tidak dihapus dari database agar jejak siapa yang menginput data lama tetap terbaca.',
        summary: 'Nonaktifkan petugas',
        security: [['bearerAuth' => []]],
        tags: ['Pengguna'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Dinonaktifkan')],
    )]
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (! Izin::kelolaPengguna($request->user())) {
            return $this->tolak();
        }

        if ($user->isSuperadmin()) {
            return $this->tolakSuperadmin();
        }

        $user->forceFill(['is_active' => false])->save();
        $user->revokeBiometricToken();
        $user->tokens()->delete();

        return ApiResponse::success(
            new UserResource($user->fresh()),
            'Akun petugas dinonaktifkan dan seluruh sesinya dicabut.',
        );
    }

    private function tolak(): JsonResponse
    {
        return ApiResponse::error(
            'Hanya superadmin yang boleh mengelola akun.',
            403,
            code: 'FORBIDDEN',
        );
    }

    private function tolakSuperadmin(): JsonResponse
    {
        return ApiResponse::error(
            'Akun superadmin tidak dapat diubah dari sini.',
            403,
            code: 'SUPERADMIN_TERKUNCI',
        );
    }
}
