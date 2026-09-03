<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisKas;
use App\Enums\KategoriKas;
use Database\Factories\CashFlowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashFlow extends Model
{
    /** @use HasFactory<CashFlowFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kegiatan_id', 'tanggal', 'jenis', 'kategori', 'nominal',
        'uraian', 'keterangan', 'metode', 'no_bukti', 'lampiran_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jenis' => JenisKas::class,
            'kategori' => KategoriKas::class,
            'nominal' => 'integer',
        ];
    }

    /**
     * Setiap perubahan kas ikut memperbarui snapshot taksasi kegiatan,
     * karena "Biaya Pelaksanaan Real" bisa berasal dari total kas bahan+upah.
     */
    protected static function booted(): void
    {
        $sync = function (CashFlow $kas): void {
            $kas->kegiatan?->recalculate();
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

    /** Nominal bertanda: masuk positif, keluar negatif. */
    public function nominalBertanda(): int
    {
        return $this->jenis === JenisKas::Masuk
            ? (int) $this->nominal
            : -(int) $this->nominal;
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopePeriode(Builder $q, ?string $dari, ?string $sampai): Builder
    {
        return $q
            ->when(filled($dari), fn (Builder $b) => $b->whereDate('tanggal', '>=', $dari))
            ->when(filled($sampai), fn (Builder $b) => $b->whereDate('tanggal', '<=', $sampai));
    }

    public function scopeJenis(Builder $q, ?string $jenis): Builder
    {
        return blank($jenis) ? $q : $q->where('jenis', $jenis);
    }

    public function scopeKategori(Builder $q, ?string $kategori): Builder
    {
        return blank($kategori) ? $q : $q->where('kategori', $kategori);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }

        $term = '%'.strtolower($term).'%';

        return $q->where(function (Builder $b) use ($term) {
            $b->whereRaw('LOWER(uraian) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(no_bukti, \'\')) LIKE ?', [$term]);
        });
    }
}
