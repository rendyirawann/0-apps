<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: <<<'DESC'
    API untuk aplikasi **Transaksi Pekerjaan** — pencatatan arus kas per kegiatan
    beserta perhitungan bagi hasil.

    ### Alur autentikasi
    1. `POST /api/auth/login` dengan email + password → dapat `access_token`.
    2. Kirim `Authorization: Bearer <access_token>` untuk semua endpoint lain.
    3. Opsional: `POST /api/auth/biometric/enable` (butuh password lagi) →
       dapat `biometric_token` yang disimpan aplikasi di Android Keystore.
       Login berikutnya cukup sidik jari lewat `POST /api/auth/biometric/login`.

    ### Aturan angka
    Semua nilai uang adalah **rupiah bulat** (integer, tanpa desimal).
    Persentase dikirim sebagai angka desimal biasa, mis. `1.75` untuk 1,75%.
    DESC,
    title: 'Transaksi Pekerjaan API',
    contact: new OA\Contact(email: 'bayuapriansah10@gmail.com'),
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Local')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'Masukkan access_token dari endpoint login (tanpa kata "Bearer").',
    name: 'Authorization',
    in: 'header',
    bearerFormat: 'JWT',
    scheme: 'bearer',
)]
#[OA\Tag(name: 'Auth', description: 'Login, logout, ganti password, sidik jari')]
#[OA\Tag(name: 'Kegiatan', description: 'CRUD kegiatan & perhitungan transaksi')]
#[OA\Tag(name: 'Cash Flow', description: 'Pencatatan kas masuk / keluar per kegiatan')]
#[OA\Tag(name: 'Laporan', description: 'Ringkasan, rekap, dan cetak PDF')]
#[OA\Tag(name: 'Referensi', description: 'Daftar enum & nilai default untuk form')]
abstract class Controller
{
    //
}
