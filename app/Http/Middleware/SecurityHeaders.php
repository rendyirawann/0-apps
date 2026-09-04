<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk seluruh respons.
 *
 * Dipasang di aplikasi, BUKAN di nginx. Kalau keduanya memasang header yang
 * sama, browser menerima dua nilai untuk X-Frame-Options -- dan menganggapnya
 * tidak sah, sehingga perlindungannya justru hilang. Satu sumber saja, dan
 * sumber itu ikut berpindah bersama kodenya ke server mana pun.
 *
 * Ketatnya sengaja dijaga di tingkat yang tidak merusak apa pun:
 *
 * - `'unsafe-inline'` tetap diizinkan untuk skrip dan gaya. Swagger UI
 *   menyisipkan gaya dari dalam JavaScript-nya saat berjalan; melarangnya
 *   membuat halaman dokumentasi tampil rusak tanpa satu pun pesan galat.
 *   Risikonya kecil di sini karena server ini tidak pernah merender masukan
 *   pengguna sebagai HTML -- seluruh jawabannya JSON, dan dua halaman HTML
 *   yang ada isinya tetap.
 * - Sisanya dikunci: tidak ada sumber luar, tidak bisa dibingkai, tidak ada
 *   plugin, dan form tidak bisa diarahkan ke domain lain.
 */
class SecurityHeaders
{
    /**
     * Ditulis sekali di sini supaya tidak ada dua daftar yang bisa berbeda.
     *
     * @var array<int, string>
     */
    private const CSP = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "form-action 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $h = $response->headers;

        $h->set('Content-Security-Policy', implode('; ', self::CSP));

        // Menutup celah "browser menebak jenis berkas": tanpa ini, unggahan
        // yang isinya HTML bisa dieksekusi sebagai HTML meski dikirim dengan
        // Content-Type gambar.
        $h->set('X-Content-Type-Options', 'nosniff');

        // frame-ancestors di CSP sudah menanganinya untuk browser modern;
        // baris ini untuk yang belum mengenal CSP.
        $h->set('X-Frame-Options', 'DENY');

        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Server ini tidak butuh satu pun kemampuan perangkat. Menyebutnya
        // kosong berarti halaman yang disisipkan pun tidak bisa memintanya.
        $h->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=(), usb=()');

        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Cross-Origin-Resource-Policy', 'same-origin');

        // HSTS HANYA saat permintaannya benar-benar lewat HTTPS.
        //
        // Mengirimnya di HTTP polos bukan sekadar sia-sia: pemasangan di
        // IP tanpa sertifikat akan terkunci: browser mengingat host itu wajib
        // HTTPS, lalu menolak membukanya lagi -- dan ingatan itu tidak bisa
        // dibatalkan dari sisi server.
        if ($request->secure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Nama kerangka kerja tidak perlu diumumkan. Bukan pengamanan
        // sungguhan, tetapi tidak ada gunanya mempermudah pemindai otomatis.
        $h->remove('X-Powered-By');
        $h->remove('Server');

        // X-Powered-By ditambahkan PHP di lapisan yang lebih bawah daripada
        // respons Laravel, jadi membuangnya dari bag di atas saja tidak cukup.
        // Yang benar-benar mematikannya adalah expose_php = Off di php.ini
        // (dipasang setup-server.sh); baris ini menutupnya juga di lingkungan
        // yang setelan itu belum berlaku, mis. komputer sendiri.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
