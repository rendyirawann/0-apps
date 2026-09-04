<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashFlow;
use App\Models\Kegiatan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Upah yang dibayar sebagian (metode Hutang).
 *
 * Aturannya: yang menambah Biaya Pelaksanaan Real hanyalah bagian yang SUDAH
 * DIBAYAR. Sisanya tercatat sebagai terhutang dan ditampilkan terpisah, bukan
 * ikut dijumlahkan -- kalau ikut, kas terlihat sudah keluar padahal uangnya
 * masih ada.
 */
class UpahHutangTest extends TestCase
{
    use RefreshDatabase;

    private function petugas(): User
    {
        return User::factory()->create(['role' => User::PETUGAS, 'is_active' => true]);
    }

    private function catat(User $aktor, Kegiatan $kegiatan, array $data): TestResponse
    {
        return $this->actingAs($aktor)->postJson(
            "/api/kegiatan/{$kegiatan->id}/cash-flows",
            array_merge([
                'tanggal' => '2026-09-01',
                'kategori' => 'upah',
                'nominal' => 10_000_000,
                'uraian' => 'Upah tukang minggu ke-1',
            ], $data),
        );
    }

    #[Test]
    public function tanpa_metode_dianggap_lunas(): void
    {
        $kegiatan = Kegiatan::factory()->create();

        $response = $this->catat($this->petugas(), $kegiatan, []);

        $response->assertCreated()
            ->assertJsonPath('data.dibayar', 10_000_000)
            ->assertJsonPath('data.terhutang', 0)
            ->assertJsonPath('data.status_bayar', 'lunas');
    }

    #[Test]
    public function hutang_tanpa_pembayaran_belum_menambah_biaya_real(): void
    {
        $kegiatan = Kegiatan::factory()->create();

        $this->catat($this->petugas(), $kegiatan, ['metode' => 'hutang'])
            ->assertCreated()
            ->assertJsonPath('data.dibayar', 0)
            ->assertJsonPath('data.terhutang', 10_000_000)
            ->assertJsonPath('data.status_bayar', 'belum');

        // Inti aturannya: nilainya tercatat, tetapi biaya realnya masih nol.
        $this->assertSame(0, $kegiatan->fresh()->totalUpah());
        $this->assertSame(10_000_000, $kegiatan->fresh()->totalUpahTerhutang());
    }

    #[Test]
    public function hutang_sebagian_hanya_menghitung_yang_sudah_dibayar(): void
    {
        $kegiatan = Kegiatan::factory()->create();

        $this->catat($this->petugas(), $kegiatan, [
            'metode' => 'hutang',
            'status_bayar' => 'belum',
            'dibayar' => 4_000_000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.dibayar', 4_000_000)
            ->assertJsonPath('data.terhutang', 6_000_000);

        $segar = $kegiatan->fresh();

        $this->assertSame(4_000_000, $segar->totalUpah());
        $this->assertSame(6_000_000, $segar->totalUpahTerhutang());
    }

    #[Test]
    public function melunasi_menaikkan_biaya_real(): void
    {
        $kegiatan = Kegiatan::factory()->create();
        $aktor = $this->petugas();

        $id = $this->catat($aktor, $kegiatan, [
            'metode' => 'hutang',
            'status_bayar' => 'belum',
            'dibayar' => 4_000_000,
        ])->json('data.id');

        $this->assertSame(4_000_000, $kegiatan->fresh()->totalUpah());

        $this->actingAs($aktor)
            ->putJson("/api/cash-flows/{$id}", [
                'tanggal' => '2026-09-01',
                'kategori' => 'upah',
                'nominal' => 10_000_000,
                'uraian' => 'Upah tukang minggu ke-1',
                'metode' => 'kas',
                'status_bayar' => 'lunas',
            ])
            ->assertOk()
            ->assertJsonPath('data.terhutang', 0)
            ->assertJsonPath('data.status_bayar', 'lunas');

        $segar = $kegiatan->fresh();

        $this->assertSame(10_000_000, $segar->totalUpah());
        $this->assertSame(0, $segar->totalUpahTerhutang());
    }

    #[Test]
    public function dibayar_tidak_boleh_melebihi_nominal(): void
    {
        $kegiatan = Kegiatan::factory()->create();

        $this->catat($this->petugas(), $kegiatan, [
            'metode' => 'hutang',
            'status_bayar' => 'belum',
            'dibayar' => 12_000_000,
        ])->assertStatus(422)->assertJsonPath('errors.dibayar.0', function (array|string $pesan) {
            return str_contains(is_array($pesan) ? $pesan[0] : $pesan, 'melebihi');
        });
    }

    #[Test]
    public function belum_lunas_tetapi_terbayar_penuh_ditolak(): void
    {
        $kegiatan = Kegiatan::factory()->create();

        // Dua pernyataan yang bertentangan. Ditolak, bukan diam-diam diubah
        // jadi lunas, supaya pengguna tahu pilihannya tidak tersimpan.
        $this->catat($this->petugas(), $kegiatan, [
            'metode' => 'hutang',
            'status_bayar' => 'belum',
            'dibayar' => 10_000_000,
        ])->assertStatus(422);
    }

    #[Test]
    public function metode_di_luar_daftar_ditolak(): void
    {
        $kegiatan = Kegiatan::factory()->create();

        $this->catat($this->petugas(), $kegiatan, ['metode' => 'barter'])
            ->assertStatus(422);
    }

    #[Test]
    public function baris_lama_tetap_terhitung_penuh(): void
    {
        // Migrasi mengisi `dibayar` = `nominal` untuk baris yang sudah ada.
        // Kalau tidak, seluruh biaya pelaksanaan lama jatuh ke nol.
        $kegiatan = Kegiatan::factory()->create();

        CashFlow::factory()->create([
            'kegiatan_id' => $kegiatan->id,
            'jenis' => 'keluar',
            'kategori' => 'upah',
            'nominal' => 7_500_000,
            'dibayar' => 7_500_000,
        ]);

        $this->assertSame(7_500_000, $kegiatan->fresh()->totalUpah());
    }

    #[Test]
    public function rincian_memuat_total_terhutang(): void
    {
        $kegiatan = Kegiatan::factory()->create();
        $aktor = $this->petugas();

        $this->catat($aktor, $kegiatan, [
            'metode' => 'hutang',
            'status_bayar' => 'belum',
            'dibayar' => 1_000_000,
        ])->assertCreated();

        $this->actingAs($aktor)
            ->getJson("/api/kegiatan/{$kegiatan->id}/bahan-baku")
            ->assertOk()
            ->assertJsonPath('data.total_upah', 1_000_000)
            ->assertJsonPath('data.total_upah_terhutang', 9_000_000);
    }
}
