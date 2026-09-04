<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\JenisMaster;
use App\Models\Kegiatan;
use App\Models\MasterData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nilai yang diketik lewat pilihan "Lainnya" ikut masuk daftar acuan.
 *
 * Tanpa ini, setiap orang mengetik ulang dan lahirlah "TB Sumber Jaya",
 * "Tb sumber jaya", dan "sumber jaya" sebagai tiga toko berbeda di laporan.
 *
 * Ini juga satu-satunya jalan petugas menambah Data Master: mereka tidak bisa
 * mengubah atau menghapus isinya, hanya memperkenalkan nama baru dengan
 * benar-benar memakainya.
 */
class MasterDataOtomatisTest extends TestCase
{
    use RefreshDatabase;

    private function simpanItem(array $data = []): TestResponse
    {
        $kegiatan = Kegiatan::factory()->create();
        $aktor = User::factory()->create(['role' => User::PETUGAS, 'is_active' => true]);

        return $this->actingAs($aktor)->postJson(
            "/api/kegiatan/{$kegiatan->id}/bahan-baku",
            array_merge([
                'nama' => 'Besi beton 12mm',
                'qty' => 10,
                'harga_satuan' => 95_000,
            ], $data),
        );
    }

    #[Test]
    public function satuan_baru_masuk_daftar_acuan(): void
    {
        $this->simpanItem(['satuan' => 'dus'])->assertCreated();

        $this->assertDatabaseHas('master_data', [
            'jenis' => JenisMaster::Satuan->value,
            'nama' => 'dus',
        ]);
    }

    #[Test]
    public function toko_baru_masuk_daftar_acuan(): void
    {
        $this->simpanItem(['toko' => 'TB Rejeki Baru'])->assertCreated();

        $this->assertDatabaseHas('master_data', [
            'jenis' => JenisMaster::Toko->value,
            'nama' => 'TB Rejeki Baru',
        ]);
    }

    #[Test]
    public function beda_besar_kecil_huruf_tidak_menggandakan(): void
    {
        MasterData::query()->create([
            'jenis' => JenisMaster::Toko->value,
            'nama' => 'TB Sumber Jaya',
            'urutan' => 1,
            'aktif' => true,
        ]);

        $this->simpanItem(['toko' => 'tb sumber jaya'])->assertCreated();

        $this->assertSame(
            1,
            MasterData::query()->jenis(JenisMaster::Toko)->count(),
            '"tb sumber jaya" dan "TB Sumber Jaya" adalah toko yang sama',
        );
    }

    #[Test]
    public function nilai_yang_sudah_ada_tidak_digandakan(): void
    {
        $this->simpanItem(['satuan' => 'dus'])->assertCreated();
        $this->simpanItem(['satuan' => 'dus'])->assertCreated();

        $this->assertSame(
            1,
            MasterData::query()->jenis(JenisMaster::Satuan)->where('nama', 'dus')->count(),
        );
    }

    #[Test]
    public function nilai_kosong_tidak_mendaftarkan_apa_pun(): void
    {
        $sebelum = MasterData::query()->count();

        $this->simpanItem(['satuan' => null, 'toko' => null])->assertCreated();

        $this->assertSame($sebelum, MasterData::query()->count());
    }
}
