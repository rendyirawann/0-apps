<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\MetodeBayar;
use App\Models\CashFlow;
use App\Support\Rupiah;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CashFlow */
class CashFlowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Baris lama bisa saja tersimpan sebelum kolom metode ada.
        $metode = $this->metode ?? MetodeBayar::Kas;

        return [
            'id' => $this->id,
            'kegiatan_id' => $this->kegiatan_id,
            'tanggal' => $this->tanggal?->toDateString(),
            'jenis' => $this->jenis->value,
            'jenis_label' => $this->jenis->label(),
            'kategori' => $this->kategori->value,
            'kategori_label' => $this->kategori->label(),
            'nominal' => (int) $this->nominal,
            'nominal_formatted' => Rupiah::format($this->nominal),
            'nominal_bertanda' => $this->nominalBertanda(),
            'uraian' => $this->uraian,
            'keterangan' => $this->keterangan,

            'metode' => $metode->value,
            'metode_label' => $metode->label(),

            // Yang benar-benar sudah keluar. Inilah angka yang menyumbang ke
            // Biaya Pelaksanaan Real -- bukan `nominal`.
            'dibayar' => (int) $this->dibayar,
            'dibayar_formatted' => Rupiah::format($this->dibayar),

            'terhutang' => $this->terhutang(),
            'terhutang_formatted' => Rupiah::format($this->terhutang()),

            'status_bayar' => $this->statusBayar(),
            'status_bayar_label' => $this->statusBayarLabel(),

            'no_bukti' => $this->no_bukti,
            'created_at' => $this->created_at?->toIso8601String(),
            'kegiatan' => $this->whenLoaded('kegiatan', fn () => [
                'id' => $this->kegiatan->id,
                'nama' => $this->kegiatan->nama,
                'kode' => $this->kegiatan->kode,
            ]),
        ];
    }
}
