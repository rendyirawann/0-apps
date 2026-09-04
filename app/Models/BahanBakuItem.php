<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisMaster;
use App\Support\MasterDataOtomatis;
use Database\Factories\BahanBakuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu baris rincian bahan baku.
 *
 * Subtotal dihitung dan disimpan otomatis, tidak pernah dikirim klien, agar
 * qty x harga selalu konsisten dengan yang tersimpan.
 */
class BahanBakuItem extends Model
{
    /** @use HasFactory<BahanBakuItemFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'bahan_baku_items';

    protected $fillable = [
        'kegiatan_id', 'nama', 'satuan', 'qty', 'harga_satuan',
        'tanggal_beli', 'toko', 'keterangan', 'urutan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'harga_satuan' => 'integer',
            'subtotal' => 'integer',
            'tanggal_beli' => 'date',
            'urutan' => 'integer',
        ];
    }

    /**
     * Subtotal selalu diturunkan dari qty x harga_satuan, dibulatkan ke rupiah
     * mengikuti aturan yang sama dengan seluruh kolom uang lain.
     *
     * Perubahan apa pun ikut memperbarui snapshot transaksi kegiatan, karena
     * total item inilah yang menjadi angka "Bahan Baku".
     */
    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->subtotal = (int) round((float) $item->qty * (float) $item->harga_satuan);
        });

        // Satuan dan toko yang belum ada di daftar acuan didaftarkan sendiri,
        // supaya nilai yang diketik lewat pilihan "Lainnya" tersedia untuk
        // input berikutnya. Lihat MasterDataOtomatis untuk alasannya.
        static::saved(function (self $item): void {
            MasterDataOtomatis::daftarkan(JenisMaster::Satuan, $item->satuan);
            MasterDataOtomatis::daftarkan(JenisMaster::Toko, $item->toko);
        });

        $sync = function (self $item): void {
            $item->kegiatan?->recalculate();
        };

        static::saved($sync);
        static::deleted($sync);
        static::restored($sync);
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
