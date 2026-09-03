<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\KategoriKas;
use App\Models\User;

/**
 * Aturan hak akses, dipusatkan di satu berkas.
 *
 * Ada dua peran:
 *
 *   superadmin — akses penuh. Hanya SATU akun, dan dialah yang membuat akun
 *                petugas.
 *   petugas    — boleh MELIHAT seluruh data, tetapi hanya boleh MENGISI bagian
 *                Biaya Pelaksanaan (bahan baku + upah pekerja) dan Administrasi.
 *
 * Dipakai di dua tempat sekaligus:
 *   1. FormRequest / controller, sebagai penjaga sesungguhnya di server.
 *   2. Resource, untuk memberi tahu aplikasi tombol mana yang perlu ditampilkan.
 *
 * Sumbernya satu, sehingga tampilan aplikasi tidak mungkin menjanjikan aksi
 * yang nanti ditolak server.
 */
final class Izin
{
    /**
     * Kategori kas yang boleh disentuh petugas.
     *
     * Upah pekerja termasuk Biaya Pelaksanaan; administrasi disebut terpisah
     * pada sheet aslinya. Selain keduanya -- termin, kewajiban, pajak, bagi
     * hasil -- hanya superadmin.
     */
    public const KATEGORI_PETUGAS = [
        'upah',
        'administrasi',
    ];

    private function __construct() {}

    // ------------------------------------------------------------------
    // Kegiatan
    // ------------------------------------------------------------------

    /** Membuat, mengubah, menghapus kegiatan beserta persentasenya. */
    public static function kelolaKegiatan(?User $user): bool
    {
        return $user?->isSuperadmin() ?? false;
    }

    /** Mengubah nilai default persentase di Pengaturan. */
    public static function kelolaPengaturan(?User $user): bool
    {
        return $user?->isSuperadmin() ?? false;
    }

    /** Membuat dan mengubah akun petugas. */
    public static function kelolaPengguna(?User $user): bool
    {
        return $user?->isSuperadmin() ?? false;
    }

    /**
     * Melihat jejak aktivitas AKUN LAIN.
     *
     * Riwayat sendiri selalu boleh dilihat siapa pun lewat endpoint terpisah,
     * jadi izin ini khusus untuk melihat pekerjaan orang lain.
     */
    /**
     * Mengelola daftar acuan (satuan, toko, sumber dana).
     *
     * Hanya superadmin. Petugas tetap MEMBACA daftarnya -- pilihannya
     * dibutuhkan saat mengisi bahan baku -- tetapi tidak menambah isinya,
     * supaya daftar tidak lekas penuh varian ejaan yang sama.
     */
    public static function kelolaMaster(?User $user): bool
    {
        return $user?->isSuperadmin() ?? false;
    }

    public static function lihatAktivitasSemua(?User $user): bool
    {
        return $user?->isSuperadmin() ?? false;
    }

    // ------------------------------------------------------------------
    // Biaya Pelaksanaan
    // ------------------------------------------------------------------

    /** Mengisi rincian bahan baku per item dan lampiran struknya. */
    public static function kelolaBahanBaku(?User $user): bool
    {
        return $user !== null && ($user->isSuperadmin() || $user->isPetugas());
    }

    public static function kelolaLampiran(?User $user): bool
    {
        return self::kelolaBahanBaku($user);
    }

    // ------------------------------------------------------------------
    // Arus kas
    // ------------------------------------------------------------------

    /** Bolehkah pengguna mencatat/mengubah kas pada kategori tertentu. */
    public static function kelolaKas(?User $user, string|KategoriKas|null $kategori): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        if (! $user->isPetugas()) {
            return false;
        }

        $nilai = $kategori instanceof KategoriKas ? $kategori->value : $kategori;

        return $nilai !== null && in_array($nilai, self::KATEGORI_PETUGAS, true);
    }

    /**
     * Daftar kategori kas yang boleh diisi pengguna ini.
     *
     * @return array<int, string>
     */
    public static function kategoriKasYangBoleh(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->isSuperadmin()) {
            return array_map(
                fn (KategoriKas $k) => $k->value,
                KategoriKas::dapatDipilih(),
            );
        }

        return $user->isPetugas() ? self::KATEGORI_PETUGAS : [];
    }

    /**
     * Ringkasan izin untuk dikirim ke aplikasi.
     *
     * @return array<string, mixed>
     */
    public static function ringkasan(?User $user): array
    {
        return [
            'kelola_kegiatan' => self::kelolaKegiatan($user),
            'kelola_pengaturan' => self::kelolaPengaturan($user),
            'kelola_pengguna' => self::kelolaPengguna($user),
            'kelola_master' => self::kelolaMaster($user),
            'lihat_aktivitas_semua' => self::lihatAktivitasSemua($user),
            'kelola_bahan_baku' => self::kelolaBahanBaku($user),
            'kelola_lampiran' => self::kelolaLampiran($user),
            'kategori_kas' => self::kategoriKasYangBoleh($user),
        ];
    }
}
