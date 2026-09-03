<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\JenisMaster;
use App\Models\MasterData;
use Illuminate\Database\Seeder;

/**
 * Isi awal daftar acuan: satuan, toko, sumber dana.
 *
 * Memakai updateOrCreate supaya bisa dijalankan ulang tanpa menggandakan isi
 * daftar, dan supaya nama yang sudah disunting superadmin tidak tertimpa.
 * Yang dinonaktifkan pun tetap nonaktif — hanya `urutan` dan `aktif` yang
 * ditulis ulang untuk nama yang memang berasal dari daftar bawaan ini.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (JenisMaster::cases() as $jenis) {
            foreach ($jenis->contoh() as $urutan => $nama) {
                MasterData::query()->updateOrCreate(
                    ['jenis' => $jenis->value, 'nama' => $nama],
                    ['urutan' => $urutan, 'aktif' => true],
                );
            }
        }

        $this->command?->info(sprintf(
            'Data master : %d pilihan (%s)',
            MasterData::query()->count(),
            implode(', ', array_map(fn (JenisMaster $j) => $j->label(), JenisMaster::cases())),
        ));
    }
}
