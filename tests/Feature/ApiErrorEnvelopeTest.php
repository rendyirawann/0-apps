<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Kegiatan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bentuk respons error API.
 *
 * Berbeda dari test lain, sebagian test di sini SENGAJA tidak memakai
 * getJson(): helper itu mengirim Accept: application/json, dan justru header
 * itulah yang menyembunyikan bug redirect tamu. Endpoint terlindungi pernah
 * membalas 500 berisi jejak galat lengkap untuk siapa pun yang membuka URL-nya
 * dari browser, sementara seluruh test tetap hijau.
 */
class ApiErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tamu_tanpa_header_json_tetap_dijawab_401_bukan_500(): void
    {
        // Tanpa Accept: application/json -- persis seperti membuka URL API
        // dari address bar browser.
        $response = $this->get('/api/auth/me');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'code' => 'UNAUTHENTICATED',
        ]);
    }

    #[Test]
    public function pesan_401_tidak_membocorkan_path_server(): void
    {
        $isi = $this->get('/api/kegiatan')->getContent();

        $this->assertIsString($isi);
        $this->assertStringNotContainsString('vendor\\laravel', $isi);
        $this->assertStringNotContainsString('C:\\', $isi);
        $this->assertStringNotContainsString('RouteNotFoundException', $isi);
        $this->assertStringNotContainsString('Route [login] not defined', $isi);
    }

    /**
     * Seluruh endpoint terlindungi, bukan hanya satu contoh: perbaikannya
     * berlaku global, jadi pengamannya pun harus global.
     */
    #[Test]
    public function setiap_endpoint_terlindungi_menjawab_401_tanpa_header_json(): void
    {
        $jalur = [
            '/api/auth/me',
            '/api/kegiatan',
            '/api/kegiatan/1',
            '/api/kegiatan/1/bahan-baku',
            '/api/kegiatan/1/lampiran',
            '/api/lampiran/1/berkas',
            '/api/cash-flows',
            '/api/pengguna',
            '/api/aktivitas',
            '/api/aktivitas/saya',
            '/api/aktivitas/ringkasan',
            '/api/laporan/ringkasan',
            '/api/referensi',
            '/api/referensi/default-rates',
        ];

        foreach ($jalur as $path) {
            $this->get($path)->assertStatus(401);
        }
    }

    #[Test]
    public function endpoint_publik_tetap_bisa_diakses_tanpa_token(): void
    {
        $this->get('/api/health')->assertOk()->assertJson(['success' => true]);
    }

    #[Test]
    public function pengguna_yang_sudah_masuk_tidak_terpengaruh_perubahan_redirect(): void
    {
        $superadmin = User::factory()->create(['role' => User::SUPERADMIN]);

        $this->actingAs($superadmin)->get('/api/auth/me')->assertOk();
    }

    #[Test]
    public function endpoint_tak_dikenal_dijawab_404_dengan_envelope(): void
    {
        $this->getJson('/api/tidak-ada-endpoint-ini')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'code' => 'NOT_FOUND']);
    }

    #[Test]
    public function id_yang_tidak_ada_dijawab_404_model_not_found(): void
    {
        $petugas = User::factory()->create(['role' => User::PETUGAS]);

        $this->actingAs($petugas)
            ->getJson('/api/kegiatan/999999')
            ->assertStatus(404)
            ->assertJson(['code' => 'MODEL_NOT_FOUND']);
    }

    #[Test]
    public function metode_yang_salah_dijawab_405_dengan_envelope(): void
    {
        $superadmin = User::factory()->create(['role' => User::SUPERADMIN]);

        $kegiatan = Kegiatan::create([
            'nama' => 'A',
            'pagu' => 400_000_000,
            'status' => 'berjalan',
            'rate_ppn' => 11,
            'rate_pph' => 1.75,
            'rate_rencana' => 60,
            'rate_kewajiban' => 12,
            'rate_administrasi' => 1,
            'rate_perusahaan' => 1.5,
            'rate_investor' => 50,
            'jml_owner' => 3,
        ]);

        // /kegiatan/{id} tidak menerima POST.
        $this->actingAs($superadmin)
            ->postJson("/api/kegiatan/{$kegiatan->id}")
            ->assertStatus(405)
            ->assertJson(['code' => 'METHOD_NOT_ALLOWED']);
    }
}
