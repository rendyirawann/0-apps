<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ActivityLog */
class ActivityLogResource extends JsonResource
{
    /** @return array<string, string> */
    public static function daftarModul(): array
    {
        return [
            'auth' => 'Akun & Sesi',
            'kegiatan' => 'Kegiatan',
            'bahan_baku' => 'Bahan Baku',
            'kas' => 'Arus Kas',
            'lampiran' => 'Lampiran',
            'pengguna' => 'Manajemen Akun',
            'pengaturan' => 'Pengaturan',
            'lain' => 'Lain-lain',
        ];
    }

    public static function labelModul(string $modul): string
    {
        return self::daftarModul()[$modul] ?? ucfirst(str_replace('_', ' ', $modul));
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'user_id' => $this->user_id,
            // Nama & peran diambil dari salinan saat kejadian, bukan dari tabel
            // users, agar jejak lama tetap benar walau akunnya berubah.
            'user_nama' => $this->user_nama,
            'user_role' => $this->user_role,

            'aksi' => $this->aksi,
            'modul' => $this->modul,
            'modul_label' => self::labelModul((string) $this->modul),

            'subjek_tipe' => $this->subjek_tipe,
            'subjek_id' => $this->subjek_id,
            'subjek_label' => $this->subjek_label,

            'metode' => $this->metode,
            'path' => $this->path,
            'status' => (int) $this->status,
            'berhasil' => (bool) $this->berhasil,

            'payload' => $this->payload,

            'ip' => $this->ip,
            'durasi_ms' => $this->durasi_ms,

            'created_at' => $this->created_at?->toIso8601String(),
            'waktu' => $this->created_at?->translatedFormat('d M Y, H:i:s'),
        ];
    }
}
