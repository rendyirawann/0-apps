<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LampiranRequest;
use App\Http\Resources\LampiranResource;
use App\Models\Kegiatan;
use App\Models\Lampiran;
use App\Support\ApiResponse;
use App\Support\Izin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LampiranController extends Controller
{
    #[OA\Get(
        path: '/api/kegiatan/{id}/lampiran',
        operationId: 'lampiranIndex',
        description: 'Daftar bukti yang terlampir pada satu kegiatan -- umumnya foto struk belanja bahan baku.',
        summary: 'Daftar lampiran kegiatan',
        security: [['bearerAuth' => []]],
        tags: ['Lampiran'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request, Kegiatan $kegiatan): JsonResponse
    {
        $daftar = $kegiatan->lampiran()
            ->with('uploader')
            ->when($request->filled('konteks'), fn ($q) => $q->where('konteks', $request->query('konteks')))
            ->get();

        return ApiResponse::success([
            'kegiatan_id' => $kegiatan->id,
            'items' => LampiranResource::collection($daftar),
            'jumlah' => $daftar->count(),
            'boleh_ubah' => Izin::kelolaLampiran($request->user()),
        ], 'Daftar lampiran.');
    }

    #[OA\Post(
        path: '/api/kegiatan/{id}/lampiran',
        operationId: 'lampiranStore',
        description: 'Mengunggah bukti belanja. Dikirim sebagai multipart/form-data, sehingga bisa berasal dari pemilih berkas maupun langsung dari kamera perangkat. Berkas yang sama persis (hash identik) ditolak agar tidak terunggah dua kali.',
        summary: 'Unggah lampiran / foto struk',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['berkas'],
                    properties: [
                        new OA\Property(property: 'berkas', description: 'JPG, PNG, WEBP, HEIC, atau PDF. Maksimal 8 MB.', type: 'string', format: 'binary'),
                        new OA\Property(property: 'konteks', type: 'string', enum: ['biaya_pelaksanaan', 'administrasi', 'lain'], example: 'biaya_pelaksanaan'),
                        new OA\Property(property: 'keterangan', type: 'string', nullable: true),
                    ],
                ),
            ),
        ),
        tags: ['Lampiran'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Terunggah'),
            new OA\Response(response: 409, description: 'Berkas identik sudah pernah diunggah'),
            new OA\Response(response: 422, description: 'Berkas tidak valid'),
        ],
    )]
    public function store(LampiranRequest $request, Kegiatan $kegiatan): JsonResponse
    {
        $berkas = $request->file('berkas');
        $hash = hash_file('sha256', $berkas->getRealPath());

        // Foto struk mudah terkirim dua kali (jaringan lambat, tombol ditekan
        // ulang). Berkas dengan isi identik pada kegiatan yang sama ditolak.
        $sudahAda = $kegiatan->lampiran()->where('hash', $hash)->first();

        if ($sudahAda !== null) {
            return ApiResponse::error(
                'Berkas yang sama sudah pernah diunggah pada kegiatan ini.',
                409,
                code: 'LAMPIRAN_DUPLIKAT',
            );
        }

        $path = $berkas->store((string) $kegiatan->id, Lampiran::DISK);

        $lampiran = $kegiatan->lampiran()->create([
            'konteks' => $request->input('konteks', 'biaya_pelaksanaan'),
            'path' => $path,
            'nama_asli' => $berkas->getClientOriginalName(),
            'mime' => $berkas->getClientMimeType(),
            'ukuran' => $berkas->getSize(),
            'hash' => $hash,
            'keterangan' => $request->input('keterangan'),
            'uploaded_by' => $request->user()?->id,
        ]);

        return ApiResponse::success(
            new LampiranResource($lampiran->load('uploader')),
            'Lampiran berhasil diunggah.',
            201,
        );
    }

    #[OA\Get(
        path: '/api/lampiran/{id}/berkas',
        operationId: 'lampiranBerkas',
        description: 'Mengunduh isi berkas lampiran. Berkas disimpan di disk privat, jadi hanya bisa diambil lewat endpoint ini yang memeriksa token -- bukan lewat URL publik yang bisa ditebak.',
        summary: 'Ambil berkas lampiran',
        security: [['bearerAuth' => []]],
        tags: ['Lampiran'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Isi berkas', content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 404, description: 'Berkas tidak ada di penyimpanan'),
        ],
    )]
    public function berkas(Lampiran $lampiran): StreamedResponse|JsonResponse
    {
        if (! $lampiran->adaBerkasnya()) {
            return ApiResponse::error(
                'Berkas tidak ditemukan di penyimpanan.',
                404,
                code: 'BERKAS_HILANG',
            );
        }

        return Storage::disk(Lampiran::DISK)->response(
            $lampiran->path,
            $lampiran->nama_asli,
            ['Content-Type' => $lampiran->mime],
        );
    }

    #[OA\Delete(
        path: '/api/lampiran/{id}',
        operationId: 'lampiranDestroy',
        description: 'Menghapus lampiran. Berkas fisiknya baru benar-benar dibuang saat penghapusan permanen, sehingga penghapusan tidak sengaja masih bisa dipulihkan.',
        summary: 'Hapus lampiran',
        security: [['bearerAuth' => []]],
        tags: ['Lampiran'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Terhapus')],
    )]
    public function destroy(Request $request, Lampiran $lampiran): JsonResponse
    {
        if (! Izin::kelolaLampiran($request->user())) {
            return ApiResponse::error('Anda tidak punya hak untuk tindakan ini.', 403, code: 'FORBIDDEN');
        }

        $lampiran->delete();

        return ApiResponse::success(null, 'Lampiran dihapus.');
    }
}
