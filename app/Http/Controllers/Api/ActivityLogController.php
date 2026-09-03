<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Izin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Jejak aktivitas.
 *
 * Dua pintu yang sengaja dipisah:
 *   /api/aktivitas/saya  — riwayat milik sendiri, boleh diakses siapa pun.
 *   /api/aktivitas       — riwayat SELURUH pengguna, khusus superadmin.
 *
 * Pemisahan ini membuat kesalahan konfigurasi tidak berujung petugas ikut
 * melihat aktivitas orang lain: endpoint "saya" memang tidak punya cara untuk
 * menampilkan milik orang lain.
 */
class ActivityLogController extends Controller
{
    #[OA\Get(
        path: '/api/aktivitas/saya',
        operationId: 'aktivitasSaya',
        description: 'Riwayat aktivitas pengguna yang sedang login. Semua peran boleh mengaksesnya, dan hanya berisi jejaknya sendiri.',
        summary: 'Riwayat aktivitas sendiri',
        security: [['bearerAuth' => []]],
        tags: ['Aktivitas'],
        parameters: [
            new OA\Parameter(name: 'modul', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['auth', 'kegiatan', 'bahan_baku', 'kas', 'lampiran', 'pengguna', 'pengaturan', 'lain'])),
            new OA\Parameter(name: 'dari', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sampai', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'hanya_gagal', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 30)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function saya(Request $request): JsonResponse
    {
        return $this->daftar($request, $request->user()?->id);
    }

    #[OA\Get(
        path: '/api/aktivitas',
        operationId: 'aktivitasSemua',
        description: 'Riwayat aktivitas seluruh pengguna. Khusus superadmin. Bisa disaring per pengguna lewat parameter user_id untuk menelusuri pekerjaan satu petugas.',
        summary: 'Riwayat aktivitas semua akun',
        security: [['bearerAuth' => []]],
        tags: ['Aktivitas'],
        parameters: [
            new OA\Parameter(name: 'user_id', description: 'Saring untuk satu pengguna', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'modul', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'dari', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sampai', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', description: 'Cari pada aksi, subjek, atau nama pengguna', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hanya_gagal', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 30)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 403, description: 'Bukan superadmin'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        if (! Izin::lihatAktivitasSemua($request->user())) {
            return ApiResponse::error(
                'Hanya superadmin yang boleh melihat aktivitas akun lain.',
                403,
                code: 'FORBIDDEN',
            );
        }

        return $this->daftar($request, $request->integer('user_id') ?: null);
    }

    #[OA\Get(
        path: '/api/aktivitas/ringkasan',
        operationId: 'aktivitasRingkasan',
        description: 'Angka ringkas untuk layar Log Aktivitas: jumlah aktivitas per modul, per pengguna, dan jumlah yang gagal. Khusus superadmin.',
        summary: 'Ringkasan aktivitas',
        security: [['bearerAuth' => []]],
        tags: ['Aktivitas'],
        parameters: [
            new OA\Parameter(name: 'dari', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sampai', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function ringkasan(Request $request): JsonResponse
    {
        if (! Izin::lihatAktivitasSemua($request->user())) {
            return ApiResponse::error(
                'Hanya superadmin yang boleh melihat ringkasan ini.',
                403,
                code: 'FORBIDDEN',
            );
        }

        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $perModul = ActivityLog::query()
            ->periode($dari, $sampai)
            ->selectRaw('modul, COUNT(*) AS jumlah')
            ->groupBy('modul')
            ->orderByDesc('jumlah')
            ->toBase()
            ->get()
            ->map(fn ($r) => [
                'modul' => (string) $r->modul,
                'label' => ActivityLogResource::labelModul((string) $r->modul),
                'jumlah' => (int) $r->jumlah,
            ])
            ->all();

        $perPengguna = ActivityLog::query()
            ->periode($dari, $sampai)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, MAX(user_nama) AS nama, MAX(user_role) AS role, COUNT(*) AS jumlah, MAX(created_at) AS terakhir')
            ->groupBy('user_id')
            ->orderByDesc('jumlah')
            ->toBase()
            ->get()
            ->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'nama' => (string) $r->nama,
                'role' => (string) $r->role,
                'jumlah' => (int) $r->jumlah,
                'terakhir' => $r->terakhir,
            ])
            ->all();

        $total = ActivityLog::query()->periode($dari, $sampai)->count();
        $gagal = ActivityLog::query()->periode($dari, $sampai)->where('berhasil', false)->count();

        return ApiResponse::success([
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'total' => $total,
            'gagal' => $gagal,
            'per_modul' => $perModul,
            'per_pengguna' => $perPengguna,
            'daftar_modul' => ActivityLogResource::daftarModul(),
            'daftar_pengguna' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'nama' => $u->name,
                    'role' => $u->role,
                    'role_label' => $u->roleLabel(),
                ])
                ->all(),
        ], 'Ringkasan aktivitas.');
    }

    private function daftar(Request $request, ?int $userId): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));

        $paginator = ActivityLog::query()
            ->untukUser($userId)
            ->modul($request->query('modul'))
            ->periode($request->query('dari'), $request->query('sampai'))
            ->search($request->query('search'))
            ->hanyaGagal($request->boolean('hanya_gagal'))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::paginated(
            ActivityLogResource::collection($paginator),
            'Riwayat aktivitas.',
        );
    }
}
