<?php

declare(strict_types=1);

use App\Http\Middleware\CatatAktivitas;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Dipasang ke seluruh grup api agar setiap endpoint yang mengubah data
        // otomatis tercatat di jejak aktivitas -- termasuk endpoint yang dibuat
        // nanti, tanpa perlu diingat penulisnya.
        $middleware->appendToGroup('api', CatatAktivitas::class);

        /*
        |------------------------------------------------------------------
        | Tamu di rute API tidak dialihkan ke mana pun
        |------------------------------------------------------------------
        | Bawaan Laravel mengarahkan tamu ke route('login'), yang tidak ada
        | di aplikasi khusus API ini. Akibatnya Authenticate::unauthenticated()
        | melempar RouteNotFoundException DI DALAM middleware -- sebelum
        | renderer AuthenticationException di bawah sempat berjalan -- sehingga
        | endpoint terlindungi membalas 500 berisi jejak galat alih-alih 401.
        |
        | Hal itu hanya muncul saat permintaan TIDAK membawa
        | Accept: application/json, karena unauthenticated() melewati redirect
        | bila klien meminta JSON. Aplikasi Flutter selalu mengirim header itu,
        | jadi gejalanya tidak terlihat dari aplikasi -- tetapi tetap salah,
        | dan membocorkan path server ke siapa pun yang membuka URL API.
        |
        | Dengan mengembalikan null untuk rute api, exception-nya tetap berupa
        | AuthenticationException dan dijawab 401 dengan envelope yang sama
        | seperti error lainnya.
        */
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
        |------------------------------------------------------------------
        | Envelope error yang seragam
        |------------------------------------------------------------------
        | Semua error API memakai bentuk { success, message, code, errors? }
        | sehingga klien Flutter cukup punya SATU jalur parsing, tidak perlu
        | membedakan format bawaan Laravel per jenis error.
        */

        $isApi = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                $e->validator->errors()->first() ?: 'Data yang dikirim tidak valid.',
                422,
                $e->errors(),
                'VALIDATION_ERROR',
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                'Sesi Anda sudah berakhir. Silakan login kembali.',
                401,
                code: 'UNAUTHENTICATED',
            );
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                'Anda tidak punya hak akses untuk tindakan ini.',
                403,
                code: 'FORBIDDEN',
            );
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error('Data tidak ditemukan.', 404, code: 'NOT_FOUND');
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            // Route-model binding yang gagal dibungkus jadi NotFoundHttpException.
            // Dibedakan supaya aplikasi bisa membalas "data sudah dihapus" alih-alih
            // "URL salah" -- dua kondisi yang penanganannya berbeda di UI.
            $isModel = $e->getPrevious() instanceof ModelNotFoundException;

            return ApiResponse::error(
                $isModel ? 'Data tidak ditemukan atau sudah dihapus.' : 'Endpoint tidak ditemukan.',
                404,
                code: $isModel ? 'MODEL_NOT_FOUND' : 'NOT_FOUND',
            );
        });

        /*
        |------------------------------------------------------------------
        | Unggahan yang lebih besar dari batas PHP
        |------------------------------------------------------------------
        | Kalau berkasnya melampaui post_max_size, PHP membuang body-nya
        | SEBELUM validasi sempat berjalan. Tanpa penanganan ini, jawabannya
        | jatuh ke halaman galat HTML -- dan aplikasi yang mengharap JSON
        | gagal membacanya, sehingga indikator unggahnya menggantung tanpa
        | pesan apa pun. Persis gejala "mengunggah terus, tidak masuk".
        |
        | 413 dengan envelope yang sama membuat aplikasi bisa menampilkan
        | alasannya dan melepas tombolnya kembali.
        */
        $exceptions->render(function (PostTooLargeException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                'Berkas terlalu besar. Ukuran maksimal 8 MB.',
                413,
                code: 'PAYLOAD_TOO_LARGE',
            );
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error('Metode HTTP tidak diizinkan untuk endpoint ini.', 405, code: 'METHOD_NOT_ALLOWED');
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                'Terlalu banyak permintaan. Silakan tunggu sebentar.',
                429,
                code: 'TOO_MANY_REQUESTS',
            );
        });

        // Error tak terduga: sembunyikan detail saat APP_DEBUG=false.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request) || $e instanceof HttpExceptionInterface) {
                return null;
            }

            if (config('app.debug')) {
                return null; // biarkan Laravel menampilkan trace saat debugging
            }

            report($e);

            return ApiResponse::error(
                'Terjadi kesalahan pada server. Silakan coba lagi.',
                500,
                code: 'SERVER_ERROR',
            );
        });
    })->create();
