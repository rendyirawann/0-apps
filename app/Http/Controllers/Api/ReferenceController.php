<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\JenisKas;
use App\Enums\KategoriKas;
use App\Enums\StatusKegiatan;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ReferenceController extends Controller
{
    #[OA\Get(
        path: '/api/referensi',
        operationId: 'referensiAll',
        description: 'Semua daftar pilihan yang dibutuhkan form mobile dalam satu panggilan: status kegiatan, jenis kas, kategori kas (beserta jenis induknya), metode pembayaran, dan nilai default persentase. Panggil sekali saat aplikasi start lalu cache di sisi klien.',
        summary: 'Semua referensi untuk form',
        security: [['bearerAuth' => []]],
        tags: ['Referensi'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'status_kegiatan', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'jenis_kas', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'kategori_kas', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'metode', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'default_rates', type: 'object'),
                ], type: 'object'),
            ])),
        ],
    )]
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'status_kegiatan' => array_map(
                fn (StatusKegiatan $s) => ['value' => $s->value, 'label' => $s->label()],
                StatusKegiatan::cases(),
            ),
            'jenis_kas' => array_map(
                fn (JenisKas $j) => ['value' => $j->value, 'label' => $j->label()],
                JenisKas::cases(),
            ),
            'kategori_kas' => KategoriKas::options(),
            'kategori_pelaksanaan_real' => KategoriKas::pelaksanaanReal(),
            'metode' => [
                ['value' => 'kas', 'label' => 'Kas / Tunai'],
                ['value' => 'transfer', 'label' => 'Transfer Bank'],
            ],
        ], 'Referensi aplikasi.');
    }

    /*
     * defaultRates() dan updateDefaultRates() DICABUT.
     *
     * Persentase kini selalu diisi per kegiatan oleh penggunanya sendiri, jadi
     * tidak ada lagi yang memakai nilai bawaan -- membiarkan endpointnya hanya
     * mengundang seseorang menyambungkannya kembali dan membuat kegiatan baru
     * kembali terisi angka yang belum ditentukan siapa pun.
     */
}
