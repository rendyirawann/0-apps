<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KegiatanRequest;
use App\Http\Resources\KegiatanResource;
use App\Models\Kegiatan;
use App\Services\RateDefaults;
use App\Services\TaksasiCalculator;
use App\Support\ApiResponse;
use App\Support\Izin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class KegiatanController extends Controller
{
    public function __construct(private readonly TaksasiCalculator $calculator) {}

    #[OA\Get(
        path: '/api/kegiatan',
        operationId: 'kegiatanIndex',
        description: 'Daftar kegiatan dengan pencarian, filter status, dan pengurutan. Respons memakai bentuk ringkas (tanpa breakdown) supaya hemat kuota di mobile.',
        summary: 'Daftar kegiatan',
        security: [['bearerAuth' => []]],
        tags: ['Kegiatan'],
        parameters: [
            new OA\Parameter(name: 'search', description: 'Cari di nama, kode, atau lokasi', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'berjalan', 'selesai', 'batal'])),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: '-created_at', enum: ['-created_at', 'created_at', 'nama', '-nama', '-pagu', 'pagu', '-profit_bersih', 'profit_bersih'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Kegiatan')),
                    new OA\Property(property: 'meta', type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Belum login'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $allowedSorts = ['created_at', 'nama', 'pagu', 'profit_bersih', 'tanggal_mulai'];
        $sort = (string) $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowedSorts, true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));

        $paginator = Kegiatan::query()
            ->search($request->query('search'))
            ->status($request->query('status'))
            ->orderBy($column, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::paginated(
            KegiatanResource::collection($paginator),
            'Daftar kegiatan.',
        );
    }

    #[OA\Post(
        path: '/api/kegiatan',
        operationId: 'kegiatanStore',
        description: 'Membuat kegiatan baru. Persentase boleh dikirim sebagian; yang tidak dikirim diisi dari nilai default (lihat GET /api/referensi/default-rates). Snapshot hasil hitung langsung disimpan.',
        summary: 'Tambah kegiatan',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nama', 'pagu'],
                properties: [
                    new OA\Property(property: 'nama', type: 'string', example: 'Pembangunan Drainase Blok A'),
                    new OA\Property(property: 'kode', type: 'string', nullable: true, example: 'KG-2026-001'),
                    new OA\Property(property: 'keterangan', type: 'string', nullable: true),
                    new OA\Property(property: 'lokasi', type: 'string', nullable: true, example: 'Kec. Cibitung'),
                    new OA\Property(property: 'sumber_dana', type: 'string', nullable: true, example: 'APBD 2026'),
                    new OA\Property(property: 'tanggal_mulai', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'tanggal_selesai', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'berjalan', 'selesai', 'batal'], example: 'draft'),
                    new OA\Property(property: 'pagu', type: 'integer', example: 400000000),
                    new OA\Property(property: 'pelaksanaan_real', description: 'Kosongkan agar diambil otomatis dari total kas kategori bahan + upah', type: 'integer', nullable: true),
                    new OA\Property(property: 'rate_ppn', type: 'number', format: 'float', example: 11),
                    new OA\Property(property: 'rate_pph', type: 'number', format: 'float', example: 1.75),
                    new OA\Property(property: 'rate_rencana', type: 'number', format: 'float', example: 60),
                    new OA\Property(property: 'rate_kewajiban', type: 'number', format: 'float', example: 12),
                    new OA\Property(property: 'rate_administrasi', type: 'number', format: 'float', example: 1),
                    new OA\Property(property: 'rate_perusahaan', type: 'number', format: 'float', example: 1.5),
                    new OA\Property(property: 'rate_investor', type: 'number', format: 'float', example: 50),
                    new OA\Property(property: 'jml_owner', type: 'integer', example: 3),
                ],
            ),
        ),
        tags: ['Kegiatan'],
        responses: [
            new OA\Response(response: 201, description: 'Kegiatan dibuat', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/KegiatanDetail'),
            ])),
            new OA\Response(response: 422, description: 'Validasi gagal, termasuk bila total persen beban melebihi 100%', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(KegiatanRequest $request): JsonResponse
    {
        $kegiatan = DB::transaction(function () use ($request): Kegiatan {
            // Rate yang tidak dikirim diambil dari default pengaturan.
            // array_replace, bukan array_merge: kunci di sini string, dan
            // yang dikirim klien harus menang atas default.
            $data = array_replace(
                RateDefaults::all(),
                array_filter(
                    $request->validated(),
                    static fn ($nilai) => $nilai !== null,
                ),
            );

            $kegiatan = new Kegiatan($data);
            $kegiatan->created_by = $request->user()?->id;
            $kegiatan->updated_by = $request->user()?->id;
            $kegiatan->save();

            $kegiatan->recalculate();

            return $kegiatan;
        });

        return ApiResponse::success(
            (new KegiatanResource($kegiatan->fresh()))->withTaksasi(),
            'Kegiatan berhasil dibuat.',
            201,
        );
    }

    #[OA\Get(
        path: '/api/kegiatan/{id}',
        operationId: 'kegiatanShow',
        description: 'Detail satu kegiatan lengkap dengan rate, seluruh breakdown taksasi (siap dirender jadi tabel), dan ringkasan kas.',
        summary: 'Detail kegiatan',
        security: [['bearerAuth' => []]],
        tags: ['Kegiatan'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/KegiatanDetail'),
            ])),
            new OA\Response(response: 404, description: 'Tidak ditemukan'),
        ],
    )]
    public function show(Kegiatan $kegiatan): JsonResponse
    {
        return ApiResponse::success(
            (new KegiatanResource($kegiatan))->withTaksasi(),
            'Detail kegiatan.',
        );
    }

    #[OA\Put(
        path: '/api/kegiatan/{id}',
        operationId: 'kegiatanUpdate',
        description: 'Memperbarui kegiatan. Kirim hanya field yang berubah. Snapshot hasil hitung otomatis diperbarui.',
        summary: 'Ubah kegiatan',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'nama', type: 'string'),
            new OA\Property(property: 'pagu', type: 'integer'),
            new OA\Property(property: 'pelaksanaan_real', type: 'integer', nullable: true),
            new OA\Property(property: 'status', type: 'string', enum: ['draft', 'berjalan', 'selesai', 'batal']),
            new OA\Property(property: 'rate_rencana', type: 'number', format: 'float'),
            new OA\Property(property: 'jml_owner', type: 'integer'),
        ])),
        tags: ['Kegiatan'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tersimpan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ],
    )]
    public function update(KegiatanRequest $request, Kegiatan $kegiatan): JsonResponse
    {
        DB::transaction(function () use ($request, $kegiatan): void {
            $data = $request->validated();

            // `pelaksanaan_real` dikirim eksplisit sebagai null = kembalikan ke
            // mode otomatis (ambil dari kas). Kalau tidak dikirim, nilai lama tetap.
            if ($request->exists('pelaksanaan_real') && $request->input('pelaksanaan_real') === null) {
                $data['pelaksanaan_real'] = null;
            }

            $kegiatan->fill($data);
            $kegiatan->updated_by = $request->user()?->id;
            $kegiatan->save();

            $kegiatan->recalculate();
        });

        return ApiResponse::success(
            (new KegiatanResource($kegiatan->fresh()))->withTaksasi(),
            'Kegiatan berhasil diperbarui.',
        );
    }

    #[OA\Delete(
        path: '/api/kegiatan/{id}',
        operationId: 'kegiatanDestroy',
        description: 'Menghapus kegiatan (soft delete). Catatan kas di dalamnya ikut tidak tampil, tetapi datanya masih tersimpan untuk audit.',
        summary: 'Hapus kegiatan',
        security: [['bearerAuth' => []]],
        tags: ['Kegiatan'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Terhapus')],
    )]
    public function destroy(Request $request, Kegiatan $kegiatan): JsonResponse
    {
        // Tidak memakai FormRequest, jadi izinnya harus diperiksa di sini --
        // tanpa ini petugas bisa menghapus kegiatan.
        if (! Izin::kelolaKegiatan($request->user())) {
            return ApiResponse::error(
                'Hanya superadmin yang boleh menghapus kegiatan.',
                403,
                code: 'FORBIDDEN',
            );
        }

        $kegiatan->delete();

        return ApiResponse::success(null, 'Kegiatan berhasil dihapus.');
    }

    #[OA\Post(
        path: '/api/kegiatan/preview',
        operationId: 'kegiatanPreview',
        description: 'Menghitung taksasi TANPA menyimpan. Dipakai form mobile untuk menampilkan angka secara langsung saat pagu / persentase diubah, sehingga rumus di aplikasi dan di server tidak pernah berbeda.',
        summary: 'Pratinjau perhitungan (tanpa simpan)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pagu'],
                properties: [
                    new OA\Property(property: 'pagu', type: 'integer', example: 400000000),
                    new OA\Property(property: 'pelaksanaan_real', type: 'integer', nullable: true),
                    new OA\Property(property: 'rate_ppn', type: 'number', format: 'float', example: 11),
                    new OA\Property(property: 'rate_pph', type: 'number', format: 'float', example: 1.75),
                    new OA\Property(property: 'rate_rencana', type: 'number', format: 'float', example: 60),
                    new OA\Property(property: 'rate_kewajiban', type: 'number', format: 'float', example: 12),
                    new OA\Property(property: 'rate_administrasi', type: 'number', format: 'float', example: 1),
                    new OA\Property(property: 'rate_perusahaan', type: 'number', format: 'float', example: 1.5),
                    new OA\Property(property: 'rate_investor', type: 'number', format: 'float', example: 50),
                    new OA\Property(property: 'jml_owner', type: 'integer', example: 3),
                ],
            ),
        ),
        tags: ['Kegiatan'],
        responses: [
            new OA\Response(response: 200, description: 'Hasil hitung', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/TaksasiHasil'),
            ])),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ],
    )]
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pagu' => ['required', 'numeric', 'min:0', 'max:999999999999999'],
            'pelaksanaan_real' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'rate_ppn' => ['nullable', 'numeric', 'between:0,100'],
            'rate_pph' => ['nullable', 'numeric', 'between:0,100'],
            'rate_rencana' => ['nullable', 'numeric', 'between:0,100'],
            'rate_kewajiban' => ['nullable', 'numeric', 'between:0,100'],
            'rate_administrasi' => ['nullable', 'numeric', 'between:0,100'],
            'rate_perusahaan' => ['nullable', 'numeric', 'between:0,100'],
            'rate_investor' => ['nullable', 'numeric', 'between:0,100'],
            'jml_owner' => ['nullable', 'integer', 'between:1,50'],
        ]);

        // Rate yang tidak dikirim diisi default, bukan 0 -- kalau dibiarkan 0
        // pratinjau akan menampilkan profit 100% dan menyesatkan.
        foreach (RateDefaults::all() as $key => $value) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                $data[$key] = $value;
            }
        }

        return ApiResponse::success(
            $this->calculator->hitung($data)->toArray(),
            'Pratinjau perhitungan.',
        );
    }
}
