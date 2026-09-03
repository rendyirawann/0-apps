<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Menguji alur autentikasi lewat HTTP, termasuk siklus hidup token biometrik
 * yang tidak bisa diuji lewat unit test biasa.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $override = []): User
    {
        return User::factory()->create(array_replace([
            'name' => 'Bayu Apriansah',
            'email' => 'superadmin@taksasi.test',
            'password' => 'password123',
            'role' => User::SUPERADMIN,
            'is_active' => true,
        ], $override));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Percobaan login dibatasi per email+IP; bersihkan agar test tidak
        // saling mempengaruhi.
        RateLimiter::clear('login:superadmin@taksasi.test|127.0.0.1');
    }

    /**
     * Lupakan guard yang sudah teresolusi.
     *
     * Di dalam satu test, instance aplikasi dipakai ulang antar-permintaan dan
     * guard menyimpan user yang sudah diautentikasi. Tanpa ini, permintaan
     * setelah logout tetap dianggap terautentikasi sehingga pencabutan token
     * seolah-olah tidak bekerja.
     */
    private function lupakanGuard(): void
    {
        $this->app['auth']->forgetGuards();
    }

    // ------------------------------------------------------------------ login

    #[Test]
    public function login_berhasil_mengembalikan_token_dan_profil(): void
    {
        $this->user();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'superadmin@taksasi.test',
            'password' => 'password123',
            'device_name' => 'Samsung A54',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'superadmin@taksasi.test')
            ->assertJsonPath('data.user.role', 'superadmin')
            ->assertJsonPath('data.user.biometric_enabled', false)
            ->assertJsonStructure(['data' => ['access_token', 'expires_at', 'user']]);

        $this->assertNotEmpty($response->json('data.access_token'));
    }

    #[Test]
    public function password_salah_ditolak_dengan_kode_yang_jelas(): void
    {
        $this->user();

        $this->postJson('/api/auth/login', [
            'email' => 'superadmin@taksasi.test',
            'password' => 'salah',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    #[Test]
    public function akun_nonaktif_tidak_boleh_masuk(): void
    {
        $this->user(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'email' => 'superadmin@taksasi.test',
            'password' => 'password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    #[Test]
    public function login_dibatasi_setelah_lima_kali_gagal(): void
    {
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'superadmin@taksasi.test',
                'password' => 'salah',
            ])->assertStatus(401);
        }

        // Percobaan keenam ditolak walau passwordnya benar.
        $this->postJson('/api/auth/login', [
            'email' => 'superadmin@taksasi.test',
            'password' => 'password123',
        ])
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
    }

    #[Test]
    public function endpoint_terproteksi_menolak_tanpa_token(): void
    {
        $this->getJson('/api/kegiatan')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    // -------------------------------------------------------------- biometrik

    #[Test]
    public function biometrik_hanya_bisa_diaktifkan_dengan_password_benar(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson('/api/auth/biometric/enable', ['password' => 'salah'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_PASSWORD');

        $this->assertNull($user->fresh()->biometric_token_hash);
    }

    #[Test]
    public function token_biometrik_disimpan_sebagai_hash_bukan_teks_asli(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->postJson('/api/auth/biometric/enable', [
            'password' => 'password123',
            'device_name' => 'Samsung A54',
        ])->assertOk();

        $plain = $response->json('data.biometric_token');

        $this->assertNotEmpty($plain);
        $this->assertSame(64, strlen($plain));

        $tersimpan = $user->fresh()->biometric_token_hash;

        // Inti keamanannya: bocornya tabel users tidak cukup untuk login.
        $this->assertNotSame($plain, $tersimpan);
        $this->assertSame(hash('sha256', $plain), $tersimpan);
    }

    #[Test]
    public function login_biometrik_menukar_token_dengan_access_token_baru(): void
    {
        $user = $this->user();

        $plain = $this->actingAs($user)
            ->postJson('/api/auth/biometric/enable', ['password' => 'password123'])
            ->assertOk()
            ->json('data.biometric_token');

        $this->postJson('/api/auth/biometric/login', [
            'email' => 'superadmin@taksasi.test',
            'biometric_token' => $plain,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.biometric_enabled', true)
            ->assertJsonStructure(['data' => ['access_token']]);
    }

    #[Test]
    public function token_biometrik_palsu_ditolak(): void
    {
        $this->user();

        $this->postJson('/api/auth/biometric/login', [
            'email' => 'superadmin@taksasi.test',
            'biometric_token' => str_repeat('x', 64),
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'BIOMETRIC_INVALID');
    }

    #[Test]
    public function token_biometrik_kedaluwarsa_ditolak(): void
    {
        $user = $this->user();

        $plain = $this->actingAs($user)
            ->postJson('/api/auth/biometric/enable', ['password' => 'password123'])
            ->assertOk()
            ->json('data.biometric_token');

        // Mundurkan masa berlaku ke masa lalu.
        $user->forceFill(['biometric_expires_at' => now()->subDay()])->save();

        $this->postJson('/api/auth/biometric/login', [
            'email' => 'superadmin@taksasi.test',
            'biometric_token' => $plain,
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'BIOMETRIC_INVALID');
    }

    // --------------------------------------------------------------- password

    #[Test]
    public function ganti_password_mencabut_token_biometrik(): void
    {
        $user = $this->user();

        $plain = $this->actingAs($user)
            ->postJson('/api/auth/biometric/enable', ['password' => 'password123'])
            ->assertOk()
            ->json('data.biometric_token');

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'password123',
            'password' => 'BaruSekali9',
            'password_confirmation' => 'BaruSekali9',
        ])
            ->assertOk()
            ->assertJsonPath('data.biometric_reset', true);

        // Password baru berlaku, password lama tidak.
        $this->assertTrue(Hash::check('BaruSekali9', $user->fresh()->password));
        $this->assertNull($user->fresh()->biometric_token_hash);

        // Token biometrik lama sudah mati.
        $this->postJson('/api/auth/biometric/login', [
            'email' => 'superadmin@taksasi.test',
            'biometric_token' => $plain,
        ])->assertStatus(401);
    }

    #[Test]
    public function ganti_password_menolak_password_lama_yang_salah(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'ngawur',
            'password' => 'BaruSekali9',
            'password_confirmation' => 'BaruSekali9',
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_PASSWORD');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    #[Test]
    public function password_baru_wajib_memenuhi_syarat_kekuatan(): void
    {
        $user = $this->user();

        // tanpa angka
        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'password123',
            'password' => 'hanyahuruf',
            'password_confirmation' => 'hanyahuruf',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('password');

        // terlalu pendek
        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'password123',
            'password' => 'ab1',
            'password_confirmation' => 'ab1',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    #[Test]
    public function pesan_validasi_memakai_bahasa_indonesia(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Kolom Email wajib diisi.')
            ->assertJsonPath('errors.password.0', 'Kolom Password wajib diisi.');
    }

    // ----------------------------------------------------------------- logout

    #[Test]
    public function logout_mencabut_token_yang_dipakai_tetapi_bukan_biometrik(): void
    {
        $user = $this->user();

        // Sengaja TIDAK memakai actingAs(): guard sesi akan tetap
        // mengautentikasi permintaan berikutnya, sehingga pencabutan Bearer
        // token tidak benar-benar teruji.
        $token = $this->postJson('/api/auth/login', [
            'email' => 'superadmin@taksasi.test',
            'password' => 'password123',
        ])->assertOk()->json('data.access_token');

        $this->withToken($token)->postJson('/api/auth/biometric/enable', [
            'password' => 'password123',
        ])->assertOk();

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->lupakanGuard();

        // Access token mati...
        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401);

        // ...tetapi biometrik masih terdaftar, sehingga pengguna tetap bisa
        // masuk dengan sidik jari tanpa mengetik password.
        $this->assertNotNull($user->fresh()->biometric_token_hash);
    }

    #[Test]
    public function logout_perangkat_lain_menyisakan_token_yang_sedang_dipakai(): void
    {
        $this->user();

        $login = fn () => $this->postJson('/api/auth/login', [
            'email' => 'superadmin@taksasi.test',
            'password' => 'password123',
        ])->assertOk()->json('data.access_token');

        $tokenLama = $login();
        $tokenSekarang = $login();

        $this->withToken($tokenSekarang)->postJson('/api/auth/change-password', [
            'current_password' => 'password123',
            'password' => 'BaruSekali9',
            'password_confirmation' => 'BaruSekali9',
            'logout_other_devices' => true,
        ])->assertOk();

        $this->lupakanGuard();

        // Token perangkat lain dicabut, token yang sedang dipakai tetap hidup.
        $this->withToken($tokenLama)->getJson('/api/auth/me')->assertStatus(401);

        $this->lupakanGuard();

        $this->withToken($tokenSekarang)->getJson('/api/auth/me')->assertOk();
    }

    #[Test]
    public function me_mengembalikan_profil_pengguna_aktif(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'superadmin@taksasi.test')
            ->assertJsonPath('data.name', 'Bayu Apriansah');
    }

    #[Test]
    public function login_baru_mencabut_sesi_perangkat_lain(): void
    {
        $user = User::factory()->create([
            'email' => 'satu@taksasi.test',
            'password' => 'password123',
            'role' => User::PETUGAS,
            'is_active' => true,
        ]);

        $pertama = $this->postJson('/api/auth/login', [
            'email' => 'satu@taksasi.test',
            'password' => 'password123',
            'device_name' => 'HP lama',
        ])->assertOk()->json('data.access_token');

        // Token pertama masih berlaku sebelum ada login lain.
        $this->lupakanGuard();
        $this->withHeader('Authorization', "Bearer {$pertama}")
            ->getJson('/api/auth/me')
            ->assertOk();

        $kedua = $this->postJson('/api/auth/login', [
            'email' => 'satu@taksasi.test',
            'password' => 'password123',
            'device_name' => 'HP baru',
        ])->assertOk()->json('data.access_token');

        // Perangkat lama terlempar.
        $this->lupakanGuard();
        $this->withHeader('Authorization', "Bearer {$pertama}")
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        // Perangkat baru yang berlaku.
        $this->lupakanGuard();
        $this->withHeader('Authorization', "Bearer {$kedua}")
            ->getJson('/api/auth/me')
            ->assertOk();

        // Hanya satu token yang tersisa di database.
        $this->assertSame(1, $user->tokens()->count());
    }

    #[Test]
    public function login_sidik_jari_juga_menyisakan_satu_sesi(): void
    {
        $user = User::factory()->create([
            'email' => 'sidik@taksasi.test',
            'password' => 'password123',
            'role' => User::PETUGAS,
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'sidik@taksasi.test',
            'password' => 'password123',
        ])->assertOk();

        $this->lupakanGuard();

        $daftar = $this->actingAs($user)
            ->postJson('/api/auth/biometric/enable', [
                'password' => 'password123',
                'device_name' => 'HP saya',
            ])->assertOk();

        $tokenBiometrik = $daftar->json('data.biometric_token');

        $this->lupakanGuard();

        $this->postJson('/api/auth/biometric/login', [
            'email' => 'sidik@taksasi.test',
            'biometric_token' => $tokenBiometrik,
            'device_name' => 'HP saya',
        ])->assertOk();

        // Aturannya sama untuk jalur login apa pun.
        $this->assertSame(1, $user->tokens()->count());
    }
}
