<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\JenisKas;
use App\Enums\KategoriKas;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlowRequest;
use App\Http\Resources\CashFlowResource;
use App\Models\CashFlow;
use App\Models\Kegiatan;
use App\Support\ApiResponse;
use App\Support\Rupiah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CashFlowController extends Controller
{
    #[OA\Get(
        path: '/api/kegiatan/{id}/cash-flows',
        operationId: 'cashFlowIndexByKegiatan',
        description: 'Daftar arus kas milik satu kegiatan, terbaru di atas. Bisa difilter per periode, jenis, dan kategori.',
        summary: 'Arus kas satu kegiatan',
        security: [['bearerAuth' => []]],
        tags: ['Cash Flow'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'dari', description: 'Tanggal awal (YYYY-MM-DD)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sampai', description: 'Tanggal akhir (YYYY-MM-DD)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'jenis', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['masuk', 'keluar'])),
            new OA\Parameter(name: 'kategori', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CashFlow')),
                new OA\Property(property: 'meta', type: 'object'),
            ])),
        ],
    )]
    public function index(Request $request, Kegiatan $kegiatan): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $paginator = $kegiatan->cashFlows()
            ->periode($request->query('dari'), $request->query('sampai'))
            ->jenis($request->query('jenis'))
            ->kategori($request->query('kategori'))
            ->search($request->query('search'))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::paginated(
            CashFlowResource::collection($paginator),
            'Arus kas kegiatan.',
        );
    }

    #[OA\Get(
        path: '/api/cash-flows',
        operationId: 'cashFlowIndexAll',
        description: 'Daftar arus kas lintas kegiatan. Berguna untuk layar "Semua Transaksi" dan rekap harian.',
        summary: 'Arus kas semua kegiatan',
        security: [['bearerAuth' => []]],
        tags: ['Cash Flow'],
        parameters: [
            new OA\Parameter(name: 'kegiatan_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'dari', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sampai', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'jenis', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['masuk', 'keluar'])),
            new OA\Parameter(name: 'kategori', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function all(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $paginator = CashFlow::query()
            ->with('kegiatan:id,nama,kode')
            ->when($request->filled('kegiatan_id'), fn ($q) => $q->where('kegiatan_id', $request->integer('kegiatan_id')))
            ->periode($request->query('dari'), $request->query('sampai'))
            ->jenis($request->query('jenis'))
            ->kategori($request->query('kategori'))
            ->search($request->query('search'))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::paginated(
            CashFlowResource::collection($paginator),
            'Arus kas.',
        );
    }

    #[OA\Post(
        path: '/api/kegiatan/{id}/cash-flows',
        operationId: 'cashFlowStore',
        description: 'Mencatat kas masuk / keluar. Field `jenis` boleh dikosongkan karena bisa diturunkan dari kategori. Jika kategori `bahan` atau `upah` dan kegiatan memakai mode otomatis, kolom "Biaya Pelaksanaan Real" beserta seluruh angka profit langsung dihitung ulang.',
        summary: 'Tambah catatan kas',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tanggal', 'kategori', 'nominal', 'uraian'],
                properties: [
                    new OA\Property(property: 'tanggal', type: 'string', format: 'date', example: '2026-09-03'),
                    new OA\Property(property: 'kategori', type: 'string', enum: ['termin', 'modal_investor', 'lain_masuk', 'bahan', 'upah', 'kewajiban', 'administrasi', 'biaya_perusahaan', 'ppn', 'pph', 'bagi_hasil_investor', 'profit_owner', 'lain_keluar'], example: 'bahan'),
                    new OA\Property(property: 'jenis', description: 'Opsional; diturunkan dari kategori bila kosong', type: 'string', enum: ['masuk', 'keluar']),
                    new OA\Property(property: 'nominal', type: 'integer', example: 15000000),
                    new OA\Property(property: 'uraian', type: 'string', example: 'Pembelian besi beton 12mm'),
                    new OA\Property(property: 'keterangan', type: 'string', nullable: true),
                    new OA\Property(property: 'metode', type: 'string', enum: ['kas', 'transfer'], example: 'transfer'),
                    new OA\Property(property: 'no_bukti', type: 'string', nullable: true, example: 'INV/2026/0912'),
                ],
            ),
        ),
        tags: ['Cash Flow'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Tercatat'),
            new OA\Response(response: 422, description: 'Validasi gagal, mis. kategori tidak cocok dengan jenis'),
        ],
    )]
    public function store(CashFlowRequest $request, Kegiatan $kegiatan): JsonResponse
    {
        $kas = new CashFlow($request->validated());
        $kas->kegiatan_id = $kegiatan->id;
        $kas->created_by = $request->user()?->id;
        $kas->save();

        return ApiResponse::success(
            new CashFlowResource($kas),
            'Catatan kas berhasil ditambahkan.',
            201,
        );
    }

    #[OA\Get(
        path: '/api/cash-flows/{id}',
        operationId: 'cashFlowShow',
        summary: 'Detail catatan kas',
        security: [['bearerAuth' => []]],
        tags: ['Cash Flow'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function show(CashFlow $cashFlow): JsonResponse
    {
        return ApiResponse::success(new CashFlowResource($cashFlow->load('kegiatan:id,nama,kode')));
    }

    #[OA\Put(
        path: '/api/cash-flows/{id}',
        operationId: 'cashFlowUpdate',
        description: 'Mengubah catatan kas. Angka transaksi kegiatan terkait ikut dihitung ulang.',
        summary: 'Ubah catatan kas',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'tanggal', type: 'string', format: 'date'),
            new OA\Property(property: 'kategori', type: 'string'),
            new OA\Property(property: 'nominal', type: 'integer'),
            new OA\Property(property: 'uraian', type: 'string'),
        ])),
        tags: ['Cash Flow'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Tersimpan')],
    )]
    public function update(CashFlowRequest $request, CashFlow $cashFlow): JsonResponse
    {
        $cashFlow->fill($request->validated())->save();

        return ApiResponse::success(new CashFlowResource($cashFlow->fresh()), 'Catatan kas diperbarui.');
    }

    #[OA\Delete(
        path: '/api/cash-flows/{id}',
        operationId: 'cashFlowDestroy',
        description: 'Menghapus catatan kas (soft delete). Angka transaksi kegiatan terkait dihitung ulang.',
        summary: 'Hapus catatan kas',
        security: [['bearerAuth' => []]],
        tags: ['Cash Flow'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Terhapus')],
    )]
    public function destroy(CashFlow $cashFlow): JsonResponse
    {
        $cashFlow->delete();

        return ApiResponse::success(null, 'Catatan kas dihapus.');
    }

    #[OA\Get(
        path: '/api/kegiatan/{id}/cash-flows/rekap',
        operationId: 'cashFlowRekap',
        description: 'Rekapitulasi kas satu kegiatan: total masuk, total keluar, saldo, dan rincian per kategori. Dipakai untuk grafik di layar detail kegiatan.',
        summary: 'Rekap kas per kategori',
        security: [['bearerAuth' => []]],
        tags: ['Cash Flow'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'dari', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sampai', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function rekap(Request $request, Kegiatan $kegiatan): JsonResponse
    {
        $rows = $kegiatan->cashFlows()
            ->periode($request->query('dari'), $request->query('sampai'))
            ->selectRaw('jenis, kategori, COALESCE(SUM(nominal), 0) AS total, COUNT(*) AS jumlah')
            ->groupBy('jenis', 'kategori')
            ->toBase()
            ->get();

        $perKategori = $rows->map(function ($row): array {
            $kategori = KategoriKas::from($row->kategori);
            $total = (int) round((float) $row->total);

            return [
                'kategori' => $kategori->value,
                'kategori_label' => $kategori->label(),
                'jenis' => $row->jenis,
                'total' => $total,
                'total_formatted' => Rupiah::format($total),
                'jumlah_transaksi' => (int) $row->jumlah,
            ];
        })->sortByDesc('total')->values();

        $masuk = (int) $perKategori->where('jenis', JenisKas::Masuk->value)->sum('total');
        $keluar = (int) $perKategori->where('jenis', JenisKas::Keluar->value)->sum('total');

        $bahanUpah = (int) $perKategori
            ->whereIn('kategori', KategoriKas::pelaksanaanReal())
            ->sum('total');

        return ApiResponse::success([
            'total_masuk' => $masuk,
            'total_masuk_formatted' => Rupiah::format($masuk),
            'total_keluar' => $keluar,
            'total_keluar_formatted' => Rupiah::format($keluar),
            'saldo' => $masuk - $keluar,
            'saldo_formatted' => Rupiah::format($masuk - $keluar),
            'total_bahan_upah' => $bahanUpah,
            'total_bahan_upah_formatted' => Rupiah::format($bahanUpah),
            'per_kategori' => $perKategori,
        ], 'Rekap kas kegiatan.');
    }
}
