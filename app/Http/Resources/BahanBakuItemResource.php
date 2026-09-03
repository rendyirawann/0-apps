<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BahanBakuItem;
use App\Support\Rupiah;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BahanBakuItem */
class BahanBakuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kegiatan_id' => $this->kegiatan_id,
            'nama' => $this->nama,
            'satuan' => $this->satuan,

            'qty' => $this->qty,
            'qty_formatted' => rtrim(rtrim(number_format((float) $this->qty, 3, ',', '.'), '0'), ','),

            'harga_satuan' => (int) $this->harga_satuan,
            'harga_satuan_formatted' => Rupiah::format($this->harga_satuan),

            'subtotal' => (int) $this->subtotal,
            'subtotal_formatted' => Rupiah::format($this->subtotal),

            'tanggal_beli' => $this->tanggal_beli?->toDateString(),
            'no_struk' => $this->no_struk,
            'toko' => $this->toko,
            'keterangan' => $this->keterangan,
            'urutan' => (int) $this->urutan,

            'dibuat_oleh' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
