<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Pembatas laju permintaan.
 *
 * Dua hal yang dijaga di sini, dan keduanya berbeda sifatnya:
 *
 * 1. **Menebak sandi.** Endpoint login dibatasi per KOMBINASI email + IP,
 *    bukan per email saja. Kalau hanya per email, siapa pun yang tahu alamat
 *    surel seseorang bisa mengunci akun itu dari luar hanya dengan salah
 *    memasukkan sandi berulang kali -- perlindungannya berbalik menjadi
 *    senjata. Ditambah batas per IP supaya satu penyerang tidak bisa
 *    menyisir banyak email sekaligus dari satu tempat.
 *
 * 2. **Membanjiri API.** Batas umum yang longgar untuk pemakaian wajar,
 *    dihitung per akun bila sudah masuk dan per IP bila belum. Dihitung per
 *    akun karena beberapa petugas di satu kantor berbagi satu IP publik;
 *    membatasi per IP saja membuat mereka saling menghabiskan jatah.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->batasUmum();
        $this->batasLogin();
    }

    private function batasUmum(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // 120 per menit longgar untuk pemakaian sungguhan -- membuka satu
            // kegiatan memicu beberapa permintaan sekaligus -- tetapi tetap
            // memotong skrip yang menembak tanpa henti.
            return Limit::perMinute(120)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });
    }

    /**
     * Penjaga BANJIR untuk endpoint login, bukan penjaga tebak sandi.
     *
     * Yang menahan penebakan sandi adalah penguncian di AuthController: ia
     * menghitung KEGAGALAN saja (lima per email+IP) dan direset begitu
     * sandinya benar. Itu jauh lebih tepat daripada menghitung setiap
     * permintaan, jadi ia yang harus lebih dulu bekerja.
     *
     * Karena itu angka di sini sengaja dibuat jauh lebih longgar. Kalau
     * disamakan, pembatas kasar inilah yang lebih dulu berbunyi dan pesan
     * "coba lagi dalam sekian detik" yang berguna itu tidak pernah sampai ke
     * pengguna.
     */
    private function batasLogin(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));
            $kunci = $email.'|'.$request->ip();

            return [
                // Empat kali lipat batas penguncian di controller: hanya
                // tersentuh oleh skrip yang menembak tanpa henti.
                Limit::perMinute(20)->by($kunci),

                // Serangan lambat. Penguncian di controller memaafkan setelah
                // 60 detik, jadi penyerang yang mencoba empat kali per menit
                // sepanjang hari tidak akan pernah tersentuh olehnya.
                Limit::perMinutes(60, 60)->by($kunci),

                // Satu IP tidak bisa menyisir banyak email sekaligus.
                Limit::perMinute(30)->by('ip|'.$request->ip()),
            ];
        });
    }
}
