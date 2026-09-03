<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\JenisMaster;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterDataRequest;
use App\Http\Resources\MasterDataResource;
use App\Models\MasterData;
use App\Support\ApiResponse;
use App\Support\Izin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Daftar acuan yang dikelola superadmin: satuan, toko, sumber dana.
 *
 * Membaca terbuka untuk semua peran karena pilihannya dibutuhkan saat mengisi
 * form; menulis hanya superadmin.
 */
class MasterDataController extends Controller
{
    #[OA\Get(
        path: '/api/master',
        operationId: 'masterSemua',
        description: 'Seluruh daftar acuan sekaligus, dikelompokkan per jenis. Satu panggilan cukup untuk mengisi semua pilihan di form, jadi aplikasi tidak perlu memanggil per jenis.',
        summary: 'Semua daftar acuan',
        security: [['bearerAuth' => []]],
        tags: ['Data Master'],
        parameters: [
            new OA\Parameter(
                name: 'semua',
                description: 'true untuk ikut menyertakan yang dinonaktifkan (dipakai layar pengelolaan)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
            ),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): JsonResponse
    {
        // Yang dinonaktifkan hanya ditampilkan pada layar pengelolaan, dan
        // hanya kepada yang boleh mengelola -- di form input, pilihan mati
        // tidak boleh muncul.
        $ikutMati = $request->boolean('semua') && Izin::kelolaMaster($request->user());

        $baris = MasterData::query()
            ->when(! $ikutMati, fn ($q) => $q->aktif())
            ->terurut()
            ->get()
            ->groupBy(fn (MasterData $m) => $m->jenis->value);

        $data = [];

        foreach (JenisMaster::cases() as $jenis) {
            $data[$jenis->value] = MasterDataResource::collection(
                $baris->get($jenis->value, collect()),
            );
        }

        return ApiResponse::success([
            'jenis' => JenisMaster::opsi(),
            'data' => $data,
            'boleh_kelola' => Izin::kelolaMaster($request->user()),
        ], 'Daftar acuan.');
    }

    #[OA\Get(
        path: '/api/master/{jenis}',
        operationId: 'masterIndex',
        description: 'Isi satu daftar acuan. `jenis` salah satu dari: satuan, toko, sumber_dana.',
        summary: 'Isi satu daftar',
        security: [['bearerAuth' => []]],
        tags: ['Data Master'],
        parameters: [
            new OA\Parameter(name: 'jenis', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['satuan', 'toko', 'sumber_dana'])),
            new OA\Parameter(name: 'semua', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Jenis tidak dikenal'),
        ],
    )]
    public function perJenis(Request $request, string $jenis): JsonResponse
    {
        $terpilih = JenisMaster::tryFrom($jenis);

        if ($terpilih === null) {
            return ApiResponse::error(
                'Jenis daftar tidak dikenal. Pilihannya: '.implode(', ', JenisMaster::values()).'.',
                404,
                code: 'NOT_FOUND',
            );
        }

        $ikutMati = $request->boolean('semua') && Izin::kelolaMaster($request->user());

        $baris = MasterData::query()
            ->jenis($terpilih)
            ->when(! $ikutMati, fn ($q) => $q->aktif())
            ->terurut()
            ->get();

        return ApiResponse::success([
            'jenis' => $terpilih->value,
            'jenis_label' => $terpilih->label(),
            'keterangan' => $terpilih->keterangan(),
            'items' => MasterDataResource::collection($baris),
            'boleh_kelola' => Izin::kelolaMaster($request->user()),
        ], $terpilih->label().'.');
    }

    #[OA\Post(
        path: '/api/master/{jenis}',
        operationId: 'masterStore',
        description: 'Menambah satu pilihan ke daftar. Hanya superadmin.',
        summary: 'Tambah pilihan',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'nama', type: 'string', example: 'sak'),
            new OA\Property(property: 'keterangan', type: 'string', nullable: true),
            new OA\Property(property: 'urutan', type: 'integer', nullable: true),
            new OA\Property(property: 'aktif', type: 'boolean', nullable: true),
        ])),
        tags: ['Data Master'],
        parameters: [
            new OA\Parameter(name: 'jenis', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['satuan', 'toko', 'sumber_dana'])),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Tersimpan'),
            new OA\Response(response: 403, description: 'Bukan superadmin'),
            new OA\Response(response: 422, description: 'Nama sudah ada di daftar ini'),
        ],
    )]
    public function store(MasterDataRequest $request, string $jenis): JsonResponse
    {
        $terpilih = JenisMaster::tryFrom($jenis);

        if ($terpilih === null) {
            return ApiResponse::error('Jenis daftar tidak dikenal.', 404, code: 'NOT_FOUND');
        }

        $item = MasterData::create([
            ...$request->safe()->all(),
            'jenis' => $terpilih->value,
            'created_by' => $request->user()?->id,
        ]);

        return ApiResponse::success(
            new MasterDataResource($item->fresh()->load('creator')),
            $terpilih->label().' ditambahkan.',
            201,
        );
    }

    #[OA\Put(
        path: '/api/master/{id}',
        operationId: 'masterUpdate',
        description: 'Mengubah satu pilihan. Jenisnya tidak bisa dipindah.',
        summary: 'Ubah pilihan',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'nama', type: 'string'),
            new OA\Property(property: 'keterangan', type: 'string', nullable: true),
            new OA\Property(property: 'urutan', type: 'integer'),
            new OA\Property(property: 'aktif', type: 'boolean'),
        ])),
        tags: ['Data Master'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Tersimpan')],
    )]
    public function update(MasterDataRequest $request, MasterData $master): JsonResponse
    {
        $master->fill($request->safe()->all())->save();

        return ApiResponse::success(
            new MasterDataResource($master->fresh()->load('creator')),
            'Perubahan disimpan.',
        );
    }

    #[OA\Delete(
        path: '/api/master/{id}',
        operationId: 'masterDestroy',
        description: 'Menghapus satu pilihan dari daftar. Data lama yang sudah memakai namanya TIDAK berubah, karena bahan baku menyimpan teks satuan dan tokonya, bukan id.',
        summary: 'Hapus pilihan',
        security: [['bearerAuth' => []]],
        tags: ['Data Master'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Terhapus'),
            new OA\Response(response: 403, description: 'Bukan superadmin'),
        ],
    )]
    public function destroy(Request $request, MasterData $master): JsonResponse
    {
        if (! Izin::kelolaMaster($request->user())) {
            return ApiResponse::error(
                'Hanya superadmin yang boleh mengelola data master.',
                403,
                code: 'FORBIDDEN',
            );
        }

        $nama = $master->nama;
        $master->delete();

        return ApiResponse::success(
            ['nama' => $nama],
            '"'.$nama.'" dihapus dari daftar. Data lama yang memakainya tidak berubah.',
        );
    }
}
