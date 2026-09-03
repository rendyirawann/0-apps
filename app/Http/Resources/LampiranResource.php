<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lampiran;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lampiran */
class LampiranResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kegiatan_id' => $this->kegiatan_id,
            'konteks' => $this->konteks,

            'nama_asli' => $this->nama_asli,
            'mime' => $this->mime,
            'is_gambar' => $this->isGambar(),

            'ukuran' => (int) $this->ukuran,
            'ukuran_label' => $this->ukuranLabel(),

            'keterangan' => $this->keterangan,

            // Jalur relatif; berkasnya diambil lewat endpoint yang memeriksa
            // token, bukan URL publik. Jalur penyimpanan sengaja tidak dibocorkan.
            'url_berkas' => "/api/lampiran/{$this->id}/berkas",

            'diunggah_oleh' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
