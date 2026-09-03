<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\JenisKas;
use App\Enums\KategoriKas;
use App\Enums\StatusKegiatan;
use App\Http\Controllers\Controller;
use App\Http\Requests\SettingUpdateRequest;
use App\Models\Setting;
use App\Services\RateDefaults;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
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
            'default_rates' => RateDefaults::all(),
        ], 'Referensi aplikasi.');
    }

    #[OA\Get(
        path: '/api/referensi/default-rates',
        operationId: 'referensiDefaultRates',
        description: 'Nilai awal persentase untuk form kegiatan baru. Ini HANYA default: rate final disimpan per-kegiatan, jadi mengubah nilai di sini tidak mengubah angka kegiatan yang sudah tersimpan.',
        summary: 'Default persentase',
        security: [['bearerAuth' => []]],
        tags: ['Referensi'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'rate_ppn', type: 'number', format: 'float', example: 11),
                    new OA\Property(property: 'rate_pph', type: 'number', format: 'float', example: 1.75),
                    new OA\Property(property: 'rate_rencana', type: 'number', format: 'float', example: 60),
                    new OA\Property(property: 'rate_kewajiban', type: 'number', format: 'float', example: 12),
                    new OA\Property(property: 'rate_administrasi', type: 'number', format: 'float', example: 1),
                    new OA\Property(property: 'rate_perusahaan', type: 'number', format: 'float', example: 1.5),
                    new OA\Property(property: 'rate_investor', type: 'number', format: 'float', example: 50),
                    new OA\Property(property: 'jml_owner', type: 'integer', example: 3),
                ], type: 'object'),
            ])),
        ],
    )]
    public function defaultRates(): JsonResponse
    {
        return ApiResponse::success(RateDefaults::all(), 'Default persentase.');
    }

    #[OA\Put(
        path: '/api/referensi/default-rates',
        operationId: 'referensiDefaultRatesUpdate',
        description: 'Mengubah nilai default persentase untuk kegiatan yang AKAN dibuat. Kegiatan yang sudah tersimpan tidak terpengaruh, karena rate-nya disimpan per baris.',
        summary: 'Ubah default persentase',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'rate_ppn', type: 'number', format: 'float', example: 11),
            new OA\Property(property: 'rate_pph', type: 'number', format: 'float', example: 1.75),
            new OA\Property(property: 'rate_rencana', type: 'number', format: 'float', example: 60),
            new OA\Property(property: 'rate_kewajiban', type: 'number', format: 'float', example: 12),
            new OA\Property(property: 'rate_administrasi', type: 'number', format: 'float', example: 1),
            new OA\Property(property: 'rate_perusahaan', type: 'number', format: 'float', example: 1.5),
            new OA\Property(property: 'rate_investor', type: 'number', format: 'float', example: 50),
            new OA\Property(property: 'jml_owner', type: 'integer', example: 3),
        ])),
        tags: ['Referensi'],
        responses: [
            new OA\Response(response: 200, description: 'Tersimpan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ],
    )]
    public function updateDefaultRates(SettingUpdateRequest $request): JsonResponse
    {
        foreach ($request->validated() as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => (string) $value,
                    'type' => $key === 'jml_owner' ? 'int' : 'percent',
                    'label' => $key,
                    'group' => 'taksasi',
                ],
            );
        }

        Cache::forget(Setting::CACHE_KEY);

        return ApiResponse::success(RateDefaults::all(), 'Default persentase diperbarui.');
    }
}
