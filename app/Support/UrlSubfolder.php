<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Menjaga prefiks subfolder pada URL yang dihasilkan Laravel.
 *
 * Saat aplikasi dipasang di bawah subfolder -- mis.
 * `APP_URL=http://76.13.23.58/transaksi` -- nginx meneruskan permintaan ke
 * Octane SETELAH memotong prefiksnya, sehingga aplikasi hanya melihat
 * `/api/health`. Itu justru yang diinginkan: definisi rute tidak perlu tahu
 * di subfolder mana ia dipasang, dan pemasangan di domain sendiri tetap sama.
 *
 * Konsekuensinya, URL yang DIHASILKAN Laravel kehilangan prefiksnya. Halaman
 * Swagger paling terasa: ia memuat spesifikasi dan asetnya lewat `route()`,
 * jadi tanpa penyesuaian ini alamatnya menunjuk ke `http://76.13.23.58/docs`
 * -- di luar subfolder -- dan halamannya kosong tanpa pesan galat apa pun.
 *
 * Dipisah dari AppServiceProvider supaya bisa diuji: provider hanya di-boot
 * sekali per proses, sehingga logikanya tidak bisa dipanggil ulang dengan
 * APP_URL berbeda dari dalam test.
 */
final class UrlSubfolder
{
    /** Mengembalikan true bila prefiks memang dipasang. */
    public static function terapkan(?string $appUrl): bool
    {
        $appUrl = trim((string) $appUrl);

        if ($appUrl === '') {
            return false;
        }

        if (self::prefiks($appUrl) === '') {
            return false;
        }

        URL::forceRootUrl($appUrl);

        // Skema ikut dipaksa supaya tautan tidak berbalik ke http saat
        // aplikasi berada di belakang proxy yang menangani TLS.
        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        return true;
    }

    /** Bagian jalur dari APP_URL, tanpa garis miring pembuka/penutup. */
    public static function prefiks(?string $appUrl): string
    {
        $path = parse_url(trim((string) $appUrl), PHP_URL_PATH);

        return trim((string) $path, '/');
    }
}
