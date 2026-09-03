<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LampiranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Berkas bukti (foto struk belanja atau dokumen pendukung).
 *
 * Berkasnya disimpan di disk privat, TIDAK di folder publik: isinya bukti
 * keuangan, jadi hanya bisa diambil lewat endpoint yang memeriksa token.
 */
class Lampiran extends Model
{
    /** @use HasFactory<LampiranFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'lampiran';

    public const DISK = 'lampiran';

    protected $fillable = [
        'kegiatan_id', 'konteks', 'path', 'nama_asli',
        'mime', 'ukuran', 'hash', 'keterangan', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['ukuran' => 'integer'];
    }

    /** Berkas fisiknya ikut terhapus saat lampiran dihapus permanen. */
    protected static function booted(): void
    {
        static::forceDeleted(function (self $lampiran): void {
            Storage::disk(self::DISK)->delete($lampiran->path);
        });
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isGambar(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function ukuranLabel(): string
    {
        $kb = $this->ukuran / 1024;

        return $kb >= 1024
            ? number_format($kb / 1024, 1, ',', '.').' MB'
            : number_format($kb, 0, ',', '.').' KB';
    }

    public function adaBerkasnya(): bool
    {
        return Storage::disk(self::DISK)->exists($this->path);
    }
}
