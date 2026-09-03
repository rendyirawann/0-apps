<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BahanBakuRequest;
use App\Http\Resources\BahanBakuItemResource;
use App\Models\BahanBakuItem;
use App\Models\Kegiatan;
use App\Support\ApiResponse;
use App\Support\Izin;
use App\Support\Rupiah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BahanBakuController extends Controller
{
    #[OA\Get(
        path: '/api/kegiatan/{id}/bahan-baku',
        operationId: 'bahanBakuIndex',
        description: 'Rincian bahan baku per item beserta totalnya. Total inilah yang menjadi porsi "Bahan Baku" pada Biaya Pelaksanaan Real -- angkanya tidak pernah diketik manual.',
        summary: 'Rincian bahan baku satu kegiatan',
        security: [['bearerAuth' => []]],
        tags: ['Bahan Baku'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/BahanBakuItem')),
                    new OA\Property(property: 'total_bahan_baku', type: 'integer', example: 142400000),
                    new OA\Property(property: 'total_upah', type: 'integer', example: 67000000),
                    new OA\Property(property: 'total_pelaksanaan', type: 'integer', example: 209400000),
                    new OA\Property(property: 'boleh_ubah', type: 'boolean'),
                ], type: 'object'),
            ])),
        ],
    )]
    public function index(Request $request, Kegiatan $kegiatan): JsonResponse
    {
        return ApiResponse::success(
            $this->ringkasan($kegiatan, $request),
            'Rincian bahan baku.',
        );
    }

    #[OA\Post(
        path: '/api/kegiatan/{id}/bahan-baku',
        operationId: 'bahanBakuStore',
        description: 'Menambah satu baris bahan baku. Subtotal dihitung server dari qty x harga_satuan, dan total keseluruhan langsung memperbarui Biaya Pelaksanaan Real beserta seluruh angka profit.',
        summary: 'Tambah item bahan baku',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nama', 'qty', 'harga_satuan'],
                properties: [
                    new OA\Property(property: 'nama', type: 'string', example: 'Besi beton 12mm'),
                    new OA\Property(property: 'satuan', type: 'string', example: 'batang'),
                    new OA\Property(property: 'qty', type: 'number', format: 'float', example: 50),
                    new OA\Property(property: 'harga_satuan', type: 'integer', example: 145000),
                    new OA\Property(property: 'tanggal_beli', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'no_struk', type: 'string', nullable: true),
                    new OA\Property(property: 'toko', type: 'string', nullable: true),
                    new OA\Property(property: 'keterangan', type: 'string', nullable: true),
                ],
            ),
        ),
        tags: ['Bahan Baku'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Item ditambahkan'),
            new OA\Response(response: 403, description: 'Tidak punya hak'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ],
    )]
    public function store(BahanBakuRequest $request, Kegiatan $kegiatan): JsonResponse
    {
        $item = new BahanBakuItem($request->validated());
        $item->kegiatan_id = $kegiatan->id;
        $item->created_by = $request->user()?->id;

        if (! $request->filled('urutan')) {
            $item->urutan = (int) $kegiatan->bahanBakuItems()->max('urutan') + 1;
        }

        $item->save();

        return ApiResponse::success(
            $this->ringkasan($kegiatan->fresh(), $request) + ['item' => new BahanBakuItemResource($item->load('creator'))],
            'Item bahan baku ditambahkan.',
            201,
        );
    }

    #[OA\Put(
        path: '/api/bahan-baku/{id}',
        operationId: 'bahanBakuUpdate',
        description: 'Mengubah satu baris bahan baku. Subtotal dan seluruh angka taksasi dihitung ulang.',
        summary: 'Ubah item bahan baku',
        security: [['bearerAuth' => []]],
        tags: ['Bahan Baku'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Tersimpan')],
    )]
    public function update(BahanBakuRequest $request, BahanBakuItem $bahanBaku): JsonResponse
    {
        $bahanBaku->fill($request->validated())->save();

        return ApiResponse::success(
            $this->ringkasan($bahanBaku->kegiatan->fresh(), $request)
                + ['item' => new BahanBakuItemResource($bahanBaku->fresh()->load('creator'))],
            'Item bahan baku diperbarui.',
        );
    }

    #[OA\Delete(
        path: '/api/bahan-baku/{id}',
        operationId: 'bahanBakuDestroy',
        description: 'Menghapus satu baris bahan baku (soft delete). Total dan angka profit dihitung ulang.',
        summary: 'Hapus item bahan baku',
        security: [['bearerAuth' => []]],
        tags: ['Bahan Baku'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Terhapus')],
    )]
    public function destroy(Request $request, BahanBakuItem $bahanBaku): JsonResponse
    {
        if (! Izin::kelolaBahanBaku($request->user())) {
            return ApiResponse::error('Anda tidak punya hak untuk tindakan ini.', 403, code: 'FORBIDDEN');
        }

        $kegiatan = $bahanBaku->kegiatan;
        $bahanBaku->delete();

        return ApiResponse::success(
            $this->ringkasan($kegiatan->fresh(), $request),
            'Item bahan baku dihapus.',
        );
    }

    /**
     * Ringkasan yang selalu ikut dikembalikan setiap kali daftar berubah,
     * supaya aplikasi tidak perlu memanggil ulang detail kegiatan.
     *
     * @return array<string, mixed>
     */
    private function ringkasan(Kegiatan $kegiatan, Request $request): array
    {
        $bahan = $kegiatan->totalBahanBaku();
        $upah = $kegiatan->totalUpah();

        return [
            'kegiatan_id' => $kegiatan->id,
            'items' => BahanBakuItemResource::collection($kegiatan->bahanBakuItems()->with('creator')->get()),
            'jumlah_item' => $kegiatan->bahanBakuItems()->count(),

            'total_bahan_baku' => $bahan,
            'total_bahan_baku_formatted' => Rupiah::format($bahan),
            'total_upah' => $upah,
            'total_upah_formatted' => Rupiah::format($upah),
            'total_pelaksanaan' => $bahan + $upah,
            'total_pelaksanaan_formatted' => Rupiah::format($bahan + $upah),

            'rencana_pelaksanaan' => (int) $kegiatan->rencana_pelaksanaan,
            'pelaksanaan_real_sumber' => $kegiatan->sumberPelaksanaanReal(),

            'boleh_ubah' => Izin::kelolaBahanBaku($request->user()),
        ];
    }
}
