<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\JenisMaster;
use App\Models\MasterData;
use Illuminate\Support\Facades\Auth;

/**
 * Mendaftarkan nilai yang diketik pengguna ke daftar acuan.
 *
 * Form bahan baku memilih satuan dan toko dari dropdown Data Master, dengan
 * satu pilihan "Lainnya" untuk mengetik nilai yang belum ada. Nilai itu
 * didaftarkan di sini supaya orang berikutnya tinggal memilihnya -- kalau
 * tidak, setiap orang mengetik ulang dan lahirlah "TB Sumber Jaya",
 * "Tb sumber jaya", dan "sumber jaya" sebagai tiga toko berbeda di laporan.
 *
 * Dijalankan di SERVER, bukan dengan panggilan terpisah dari aplikasi. Kalau
 * aplikasi yang mendaftarkan, nilainya bisa tersimpan di rincian tetapi gagal
 * masuk daftar acuan saat jaringan putus di antara dua permintaan.
 *
 * Ini juga satu-satunya jalan petugas menambah Data Master: mereka tidak bisa
 * mengubah atau menghapus isinya, hanya memperkenalkan nama baru dengan
 * benar-benar memakainya.
 */
final class MasterDataOtomatis
{
    /** Batas panjang mengikuti kolom `master_data.nama`. */
    private const MAKS_NAMA = 100;

    public static function daftarkan(JenisMaster $jenis, ?string $nama): void
    {
        $nama = trim((string) $nama);

        if ($nama === '' || mb_strlen($nama) > self::MAKS_NAMA) {
            return;
        }

        // Pencocokan tanpa peduli besar-kecil huruf: "sak" dan "Sak" adalah
        // satuan yang sama, dan menambahkan keduanya justru merusak tujuannya.
        $sudahAda = MasterData::query()
            ->jenis($jenis)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])
            ->exists();

        if ($sudahAda) {
            return;
        }

        MasterData::query()->create([
            'jenis' => $jenis->value,
            'nama' => $nama,
            'keterangan' => 'Ditambahkan otomatis dari input rincian.',
            // Ditaruh di belakang daftar: pilihan yang dikurasi superadmin
            // tetap muncul lebih dulu.
            'urutan' => 900,
            'aktif' => true,
            'created_by' => Auth::id(),
        ]);
    }
}
