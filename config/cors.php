<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Dibutuhkan saat aplikasi Flutter dijalankan di browser (mode debug web):
    | halaman berjalan di http://localhost:<port-acak> sedangkan API di
    | http://127.0.0.1:8000, sehingga setiap permintaan bersifat lintas-origin.
    |
    | Aplikasi Android/iOS tidak terpengaruh berkas ini -- aturan CORS hanya
    | ditegakkan oleh browser.
    |
    */

    'paths' => ['api/*', 'docs', 'docs/*'],

    'allowed_methods' => ['*'],

    /*
    | Port Flutter web berubah-ubah setiap kali dijalankan, jadi origin tidak
    | bisa didaftarkan satu per satu.
    |
    | UNTUK PRODUKSI: ganti menjadi daftar domain yang benar-benar dipakai,
    | mis. ['https://app.perusahaan.co.id'].
    */
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    /*
    | Tanpa ini browser menyembunyikan Content-Disposition, sehingga aplikasi
    | tidak bisa membaca nama berkas PDF dari server dan terpaksa memakai nama
    | buatan sendiri.
    */
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    /*
    | Autentikasi memakai Bearer token, bukan cookie sesi, jadi kredensial
    | lintas-origin tidak perlu diizinkan.
    */
    'supports_credentials' => false,

];
