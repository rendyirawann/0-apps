<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Isi awal aplikasi.
 *
 * Dipecah per bagian supaya masing-masing bisa dijalankan sendiri:
 *
 *   php artisan db:seed                                      semuanya
 *   php artisan db:seed --class=MasterDataSeeder             hanya daftar acuan
 *   php artisan db:seed --class=UserSeeder                   hanya akun
 *   php artisan db:seed --class=KegiatanContohSeeder         hanya kegiatan contoh
 *
 * Pemisahannya bukan sekadar kerapian. `db:seed` tanpa `--class` selalu ikut
 * membawa tiga kegiatan contoh — berguna untuk demo, tetapi di server hanya
 * mengotori laporan. Di sana yang dijalankan cukup MasterDataSeeder dan
 * UserSeeder, lalu datanya diisi sendiri dari aplikasi.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MasterDataSeeder::class,
            UserSeeder::class,
            KegiatanContohSeeder::class,
        ]);
    }
}
