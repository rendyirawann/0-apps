<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MasterData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MasterData */
class MasterDataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'jenis' => $this->jenis->value,
            'jenis_label' => $this->jenis->label(),
            'nama' => $this->nama,
            'keterangan' => $this->keterangan,
            'urutan' => (int) $this->urutan,
            'aktif' => (bool) $this->aktif,
            'dibuat_oleh' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
