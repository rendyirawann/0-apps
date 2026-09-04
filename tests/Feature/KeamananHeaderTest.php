<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Header keamanan dan halaman yang dilihat peramban.
 *
 * Bukan sekadar mengejar nilai pemindai: tiap header di sini menutup satu
 * cara nyata sebuah halaman disalahgunakan, dan tiap halaman di sini
 * menggantikan halaman bawaan Laravel yang membocorkan versi dan jalur
 * berkas server.
 */
class KeamananHeaderTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    public static function headerWajib(): array
    {
        return [
            ['Content-Security-Policy'],
            ['X-Content-Type-Options'],
            ['X-Frame-Options'],
            ['Referrer-Policy'],
            ['Permissions-Policy'],
            ['Cross-Origin-Opener-Policy'],
            ['Cross-Origin-Resource-Policy'],
        ];
    }

    #[Test]
    public function halaman_status_membawa_semua_header(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        foreach (self::headerWajib() as [$header]) {
            $response->assertHeader($header);
        }
    }

    #[Test]
    public function jawaban_api_juga_membawa_header(): void
    {
        // Header dipasang global, bukan hanya di rute peramban.
        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    #[Test]
    public function header_tidak_ganda(): void
    {
        // Nilai ganda pada X-Frame-Options dianggap tidak sah oleh browser dan
        // diabaikan seluruhnya -- perlindungannya justru hilang. Karena itu
        // header ini HANYA dipasang aplikasi, tidak juga di nginx.
        $response = $this->get('/');

        foreach (self::headerWajib() as [$header]) {
            $this->assertCount(
                1,
                $response->headers->all($header),
                "header {$header} terkirim lebih dari sekali",
            );
        }
    }

    #[Test]
    public function csp_mengunci_sumber_luar(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);

        // Tidak ada satu pun host luar yang diizinkan.
        $this->assertStringNotContainsString('http://', $csp);
        $this->assertStringNotContainsString('https://', $csp);
    }

    #[Test]
    public function hsts_hanya_saat_https(): void
    {
        // Di HTTP polos, HSTS akan mengunci pemasangan di IP tanpa sertifikat:
        // browser mengingat host itu wajib HTTPS dan menolak membukanya lagi,
        // dan ingatan itu tidak bisa dibatalkan dari sisi server.
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    #[Test]
    public function nama_kerangka_kerja_tidak_diumumkan(): void
    {
        $this->get('/')->assertHeaderMissing('X-Powered-By');
    }

    // ------------------------------------------------------------------
    // Halaman
    // ------------------------------------------------------------------

    #[Test]
    public function akar_menampilkan_server_live(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertSee('SERVER IS LIVE');
    }

    #[Test]
    public function akar_tidak_membocorkan_teknologi(): void
    {
        // Halaman sambutan bawaan menyebut nama kerangka kerjanya dan menaut
        // ke dokumentasinya. Itulah yang digantikan halaman ini.
        $isi = $this->get('/')->getContent();

        foreach (['Laravel', 'laravel.com', 'php.net', 'Vite'] as $jejak) {
            $this->assertStringNotContainsString($jejak, $isi);
        }
    }

    #[Test]
    public function galat_peramban_memakai_halaman_sendiri(): void
    {
        $response = $this->get('/alamat-yang-tidak-ada');

        $response->assertNotFound()
            ->assertSee('404')
            ->assertSee('Halaman Tidak Ada');

        $this->assertStringNotContainsString('Laravel', $response->getContent());
    }

    #[Test]
    public function galat_api_tetap_json(): void
    {
        // Halaman HTML di atas TIDAK boleh ikut dipakai untuk /api/*: aplikasi
        // Flutter hanya punya satu jalur pembacaan, yaitu envelope JSON.
        $this->getJson('/api/alamat-yang-tidak-ada')
            ->assertNotFound()
            ->assertJson(['success' => false, 'code' => 'NOT_FOUND']);
    }

    #[Test]
    public function halaman_status_tidak_butuh_login(): void
    {
        // Dipakai untuk memastikan server hidup dari peramban mana pun.
        $this->assertNull(auth()->user());

        $this->get('/')->assertOk();
    }

    #[Test]
    public function halaman_status_tetap_tampil_untuk_yang_sudah_login(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk();
    }
}
