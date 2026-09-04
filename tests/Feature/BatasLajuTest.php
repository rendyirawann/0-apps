<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pembatas laju: menebak sandi dan membanjiri API.
 *
 * Ada DUA lapisan, dan urutannya penting:
 *
 * 1. Penguncian di AuthController -- menghitung KEGAGALAN saja, lima per
 *    email+IP, direset begitu sandinya benar. Inilah penjaga tebak sandi, dan
 *    inilah yang harus lebih dulu berbunyi karena pesannya paling berguna
 *    ("coba lagi dalam sekian detik").
 * 2. throttle:login di rutenya -- jauh lebih longgar, hanya untuk menahan
 *    banjir permintaan mentah.
 *
 * Yang diuji bukan cuma "ada batasnya", tetapi bahwa batas itu tidak berbalik
 * menjadi senjata: mengunci akun orang lain justru cara paling murah
 * melumpuhkan sistem yang perlindungannya salah kunci.
 */
class BatasLajuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Hitungannya tersimpan di cache, dan cache bisa terbawa antar test.
        RateLimiter::clear('login');
        RateLimiter::clear('login:korban@taksasi.test|127.0.0.1');
    }

    private function coba(string $email, string $sandi = 'salah-sekali'): TestResponse
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $sandi,
        ]);
    }

    #[Test]
    public function menebak_sandi_berulang_kali_diblokir(): void
    {
        User::factory()->create([
            'email' => 'korban@taksasi.test',
            'password' => 'sandi-yang-benar',
        ]);

        // Lima percobaan pertama ditolak sebagai kredensial salah, bukan 429.
        for ($i = 0; $i < 5; $i++) {
            $this->coba('korban@taksasi.test')->assertStatus(401);
        }

        // TOO_MANY_ATTEMPTS, bukan TOO_MANY_REQUESTS: yang berbunyi adalah
        // penguncian di controller, bukan penjaga banjir di rutenya. Kalau
        // yang muncul TOO_MANY_REQUESTS, berarti penjaga banjirnya terlalu
        // ketat dan menutupi lapisan yang lebih tepat.
        $this->coba('korban@taksasi.test')
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
    }

    #[Test]
    public function sandi_benar_setelah_terblokir_tetap_ditolak(): void
    {
        User::factory()->create([
            'email' => 'korban@taksasi.test',
            'password' => 'sandi-yang-benar',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->coba('korban@taksasi.test');
        }

        // Kalau blokirnya bisa dilewati sekadar dengan menebak benar, batasnya
        // tidak menahan apa pun: penyerang toh menebak sampai benar.
        $this->coba('korban@taksasi.test', 'sandi-yang-benar')->assertStatus(429);
    }

    #[Test]
    public function mengunci_satu_akun_tidak_mengunci_akun_lain(): void
    {
        User::factory()->create(['email' => 'satu@taksasi.test', 'password' => 'rahasia-satu']);
        User::factory()->create(['email' => 'dua@taksasi.test', 'password' => 'rahasia-dua']);

        for ($i = 0; $i < 6; $i++) {
            $this->coba('satu@taksasi.test');
        }

        // Kunci pembatasnya email + IP. Kalau hanya email, siapa pun yang tahu
        // alamat surel seseorang bisa mengunci akun itu dari luar.
        $this->coba('dua@taksasi.test', 'rahasia-dua')->assertOk();
    }

    #[Test]
    public function login_berhasil_tidak_terhalang_batas(): void
    {
        User::factory()->create(['email' => 'petugas@taksasi.test', 'password' => 'rahasia-ok']);

        $this->coba('petugas@taksasi.test', 'rahasia-ok')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    #[Test]
    public function jawaban_429_memberi_tahu_kapan_boleh_mencoba_lagi(): void
    {
        User::factory()->create(['email' => 'korban@taksasi.test', 'password' => 'rahasia']);

        for ($i = 0; $i < 6; $i++) {
            $response = $this->coba('korban@taksasi.test');
        }

        // Tanpa Retry-After, aplikasi hanya bisa menyuruh "coba lagi nanti"
        // tanpa tahu nanti itu kapan -- dan pengguna menekan tombolnya
        // berulang kali, yang justru memperpanjang blokirnya sendiri.
        $response->assertStatus(429)->assertHeader('Retry-After');
    }

    #[Test]
    public function batas_umum_terpasang_di_grup_api(): void
    {
        $aktor = User::factory()->create();

        // Header inilah yang membuktikan throttle:api benar-benar berjalan,
        // tanpa perlu menembak 120 kali.
        $this->actingAs($aktor)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '120');
    }

    #[Test]
    public function penjaga_banjir_login_lebih_longgar_dari_penguncian(): void
    {
        // Dibuktikan lewat header pembatasnya: 20 per menit, empat kali lipat
        // lima percobaan gagal yang dijaga controller. Kalau angkanya turun
        // sampai menyamai, pesan penguncian yang berguna itu tidak akan
        // pernah sampai ke pengguna.
        $this->postJson('/api/auth/login', ['email' => 'a@b.test', 'password' => 'x'])
            ->assertHeader('X-RateLimit-Limit', '20');
    }

    #[Test]
    public function health_check_tidak_ikut_terkunci_batas_login(): void
    {
        // deploy.sh menembak endpoint ini setiap rilis; kalau ikut batas login
        // yang ketat, deploy beruntun akan gagal sendiri.
        for ($i = 0; $i < 12; $i++) {
            $this->getJson('/api/health')->assertOk();
        }
    }
}
