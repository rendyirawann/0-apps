<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat setiap permintaan API yang mengubah data.
 *
 * Sengaja dipasang sebagai middleware, bukan dipanggil satu per satu di tiap
 * controller: dengan begini endpoint baru otomatis ikut tercatat tanpa perlu
 * diingat penulisnya, dan tidak ada jalur yang lolos.
 *
 * Yang TIDAK dicatat:
 *   - permintaan GET (tidak mengubah apa pun, jumlahnya membanjiri jejak)
 *   - nilai password, token, dan isi berkas unggahan
 */
class CatatAktivitas
{
    /** Kunci yang isinya tidak boleh ikut tersimpan. */
    private const RAHASIA = [
        'password',
        'password_confirmation',
        'current_password',
        'biometric_token',
        'access_token',
        'token',
    ];

    private const ABAIKAN_METODE = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        $mulai = microtime(true);

        $response = $next($request);

        if (! in_array($request->method(), self::ABAIKAN_METODE, true)) {
            // Pencatatan tidak boleh menggagalkan permintaan yang sudah sukses.
            try {
                $this->catat($request, $response, $mulai);
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat aktivitas: '.$e->getMessage());
            }
        }

        return $response;
    }

    private function catat(Request $request, Response $response, float $mulai): void
    {
        $user = $request->user() ?? $this->pelakuLogin($request);
        $status = $response->getStatusCode();
        $routeName = $request->route()?->getName();

        [$modul, $aksi] = $this->kenali($request, $routeName);

        ActivityLog::create([
            'user_id' => $user?->id,
            'user_nama' => $user?->name,
            'user_role' => $user?->role,

            'aksi' => $aksi,
            'modul' => $modul,

            'subjek_tipe' => $this->subjekTipe($modul),
            'subjek_id' => $this->subjekId($request),
            'subjek_label' => $this->subjekLabel($request),

            'metode' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route_name' => $routeName,
            'status' => $status,
            'berhasil' => $status >= 200 && $status < 300,

            'payload' => $this->payloadAman($request),

            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 300),
            'durasi_ms' => (int) round((microtime(true) - $mulai) * 1000),
        ]);
    }

    /**
     * Siapa yang melakukan login.
     *
     * Pada endpoint login, pengguna belum terautentikasi saat middleware
     * berjalan sehingga $request->user() masih null. Tanpa ini, baris jejak
     * "Masuk dengan password" tidak punya pemilik -- padahal justru baris
     * itu yang paling dibutuhkan saat menelusuri siapa mengakses apa.
     */
    private function pelakuLogin(Request $request): ?User
    {
        if (! $request->is('api/auth/login', 'api/auth/biometric/login')) {
            return null;
        }

        $email = $request->input('email');

        return $email === null
            ? null
            : User::query()->where('email', $email)->first();
    }

    /**
     * Menerjemahkan nama route menjadi modul + kalimat aksi berbahasa Indonesia.
     *
     * @return array{0:string, 1:string}
     */
    private function kenali(Request $request, ?string $routeName): array
    {
        $peta = [
            'api.auth.login' => ['auth', 'Masuk dengan password'],
            'api.auth.biometric.login' => ['auth', 'Masuk dengan sidik jari'],
            'api.auth.logout' => ['auth', 'Keluar'],
            'api.auth.change-password' => ['auth', 'Mengganti password'],
            'api.auth.profil.update' => ['auth', 'Memperbarui profil'],
            'api.auth.biometric.enable' => ['auth', 'Mengaktifkan login sidik jari'],
            'api.auth.biometric.disable' => ['auth', 'Menonaktifkan login sidik jari'],

            'api.kegiatan.store' => ['kegiatan', 'Menambah kegiatan'],
            'api.kegiatan.update' => ['kegiatan', 'Mengubah kegiatan'],
            'api.kegiatan.destroy' => ['kegiatan', 'Menghapus kegiatan'],
            'api.kegiatan.preview' => ['kegiatan', 'Pratinjau perhitungan'],

            'api.bahan-baku.store' => ['bahan_baku', 'Menambah item bahan baku'],
            'api.bahan-baku.update' => ['bahan_baku', 'Mengubah item bahan baku'],
            'api.bahan-baku.destroy' => ['bahan_baku', 'Menghapus item bahan baku'],

            'api.lampiran.store' => ['lampiran', 'Mengunggah lampiran bukti'],
            'api.lampiran.destroy' => ['lampiran', 'Menghapus lampiran'],

            'api.kegiatan.cash-flows.store' => ['kas', 'Mencatat arus kas'],
            'api.cash-flows.update' => ['kas', 'Mengubah catatan kas'],
            'api.cash-flows.destroy' => ['kas', 'Menghapus catatan kas'],

            'api.pengguna.store' => ['pengguna', 'Membuat akun petugas'],
            'api.pengguna.update' => ['pengguna', 'Mengubah akun petugas'],
            'api.pengguna.destroy' => ['pengguna', 'Menonaktifkan akun petugas'],

            'api.referensi.rates.update' => ['pengaturan', 'Mengubah default persentase'],
        ];

        if ($routeName !== null && isset($peta[$routeName])) {
            return $peta[$routeName];
        }

        // Endpoint baru yang belum dipetakan tetap tercatat, memakai metode dan
        // jalurnya — lebih baik tercatat apa adanya daripada hilang.
        return ['lain', $request->method().' '.$request->path()];
    }

    private function subjekTipe(string $modul): ?string
    {
        return match ($modul) {
            'kegiatan' => 'Kegiatan',
            'bahan_baku' => 'BahanBakuItem',
            'kas' => 'CashFlow',
            'lampiran' => 'Lampiran',
            'pengguna' => 'User',
            default => null,
        };
    }

    /** Ambil id sasaran dari parameter route, apa pun namanya. */
    private function subjekId(Request $request): ?int
    {
        foreach (['kegiatan', 'bahanBaku', 'cashFlow', 'lampiran', 'user'] as $nama) {
            $param = $request->route($nama);

            if ($param === null) {
                continue;
            }

            if (is_object($param) && isset($param->id)) {
                return (int) $param->id;
            }

            if (is_numeric($param)) {
                return (int) $param;
            }
        }

        return null;
    }

    /** Label singkat agar jejak terbaca tanpa perlu membuka data aslinya. */
    private function subjekLabel(Request $request): ?string
    {
        foreach (['nama', 'uraian', 'name'] as $kunci) {
            if ($request->filled($kunci)) {
                return substr((string) $request->input($kunci), 0, 200);
            }
        }

        $kegiatan = $request->route('kegiatan');

        if (is_object($kegiatan) && isset($kegiatan->nama)) {
            return substr((string) $kegiatan->nama, 0, 200);
        }

        return null;
    }

    /**
     * Isi permintaan tanpa data rahasia dan tanpa berkas.
     *
     * @return array<string, mixed>|null
     */
    private function payloadAman(Request $request): ?array
    {
        $data = $request->except(self::RAHASIA);

        // Berkas unggahan diganti ringkasannya saja; isinya tidak masuk akal
        // disimpan di tabel jejak.
        foreach ($request->allFiles() as $kunci => $berkas) {
            $satu = is_array($berkas) ? ($berkas[0] ?? null) : $berkas;

            $data[$kunci] = $satu === null ? '[berkas]' : sprintf(
                '[berkas: %s, %s KB]',
                $satu->getClientOriginalName(),
                number_format($satu->getSize() / 1024, 0, ',', '.'),
            );
        }

        if ($data === []) {
            return null;
        }

        // Jaga agar satu baris jejak tidak membengkak tak terkendali.
        $json = json_encode($data);

        if ($json !== false && strlen($json) > 8000) {
            return ['_ringkas' => 'Payload terlalu besar untuk disimpan utuh.',
                '_kunci' => array_keys($data)];
        }

        return $data;
    }
}
