<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\UrlSubfolder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * URL yang dihasilkan Laravel saat aplikasi dipasang di bawah subfolder.
 *
 * Halaman Swagger paling terasa bila ini salah: ia memuat spesifikasi dan
 * asetnya lewat `route()`, jadi tanpa prefiks alamatnya keluar dari subfolder
 * dan halamannya kosong tanpa pesan galat apa pun.
 */
class SubfolderUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        // Root URL dan skema bersifat global pada instans aplikasi; dikembalikan
        // supaya test lain tidak ikut terpengaruh.
        URL::forceRootUrl(null);
        URL::forceScheme(null);

        parent::tearDown();
    }

    #[Test]
    public function url_memuat_prefiks_subfolder(): void
    {
        $dipasang = UrlSubfolder::terapkan('http://203.0.113.10/transaksi');

        $this->assertTrue($dipasang);
        $this->assertSame('http://203.0.113.10/transaksi/api/health', url('/api/health'));
    }

    #[Test]
    public function alamat_spesifikasi_swagger_ikut_berprefiks(): void
    {
        UrlSubfolder::terapkan('http://203.0.113.10/transaksi');

        // Inilah yang dipakai halaman Swagger untuk memuat spesifikasinya.
        $this->assertStringStartsWith('http://203.0.113.10/transaksi/', url('/docs'));
        $this->assertStringStartsWith(
            'http://203.0.113.10/transaksi/',
            url('/api/documentation'),
        );
    }

    #[Test]
    public function subfolder_bersarang_juga_ditangani(): void
    {
        UrlSubfolder::terapkan('http://203.0.113.10/app/transaksi/');

        $this->assertSame(
            'http://203.0.113.10/app/transaksi/api/health',
            url('/api/health'),
        );
    }

    #[Test]
    public function tanpa_subfolder_tidak_melakukan_apa_pun(): void
    {
        foreach (['https://rendy-irawan.my.id', 'http://127.0.0.1:8000', ''] as $appUrl) {
            $this->assertFalse(
                UrlSubfolder::terapkan($appUrl),
                "APP_URL '{$appUrl}' tidak punya subfolder, jadi tidak boleh dipaksa",
            );
        }
    }

    #[Test]
    public function prefiks_dibaca_benar(): void
    {
        $this->assertSame('transaksi', UrlSubfolder::prefiks('http://1.2.3.4/transaksi'));
        $this->assertSame('transaksi', UrlSubfolder::prefiks('http://1.2.3.4/transaksi/'));
        $this->assertSame('a/b', UrlSubfolder::prefiks('http://1.2.3.4/a/b/'));
        $this->assertSame('', UrlSubfolder::prefiks('http://1.2.3.4'));
        $this->assertSame('', UrlSubfolder::prefiks('https://rendy-irawan.my.id/'));
        $this->assertSame('', UrlSubfolder::prefiks(null));
    }

    #[Test]
    public function skema_https_dipaksa_bila_app_url_https(): void
    {
        // Di belakang proxy yang menangani TLS, tautan tidak boleh berbalik
        // ke http.
        UrlSubfolder::terapkan('https://contoh.test/transaksi');

        $this->assertStringStartsWith('https://', url('/api/health'));
    }

    #[Test]
    public function definisi_rute_tidak_ikut_berprefiks(): void
    {
        UrlSubfolder::terapkan('http://203.0.113.10/transaksi');

        // nginx memotong prefiksnya sebelum meneruskan, jadi aplikasi tetap
        // MELAYANI '/api/health' apa adanya. Yang diperiksa di sini tabel
        // rutenya, bukan lewat klien test: klien ikut memakai root URL yang
        // dipaksa, sehingga permintaannya tertuju ke '/transaksi/api/health'
        // -- alamat yang memang hanya ada di nginx, bukan di aplikasi.
        $jalur = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($rute) => $rute->uri())
            ->all();

        $this->assertContains('api/health', $jalur);
        foreach ($jalur as $uri) {
            $this->assertStringStartsNotWith(
                'transaksi/',
                $uri,
                'rute tidak boleh ikut membawa prefiks subfolder',
            );
        }
    }

    #[Test]
    public function endpoint_tetap_hidup_tanpa_root_yang_dipaksa(): void
    {
        // Keadaan sebenarnya di server: Laravel menerima '/api/health' karena
        // prefiksnya sudah dipotong nginx.
        $this->getJson('/api/health')->assertOk()->assertJson(['success' => true]);
    }
}
