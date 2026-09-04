<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisKas;
use App\Enums\KategoriKas;
use App\Enums\MetodeBayar;
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

    /**
     * Nilai bawaan untuk instans BARU.
     *
     * Kolomnya memang punya default di database, tetapi default itu baru
     * berlaku saat baris ditulis -- objek hasil create() masih memegang null
     * sampai dibaca ulang. Tanpa baris ini, resource yang memanggil
     * $this->metode->label() pecah tepat pada respons 201.
     */
    protected $attributes = [
        'metode' => 'kas',
        'dibayar' => 0,
    ];

    protected $fillable = [
        'kegiatan_id', 'tanggal', 'jenis', 'kategori', 'nominal', 'dibayar',
        'uraian', 'keterangan', 'metode', 'no_bukti', 'lampiran_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jenis' => JenisKas::class,
            'kategori' => KategoriKas::class,
            'metode' => MetodeBayar::class,
            'nominal' => 'integer',
            'dibayar' => 'integer',
        ];
    }

    /**
     * Setiap perubahan kas ikut memperbarui snapshot transaksi kegiatan,
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
    // Pelunasan
    // ------------------------------------------------------------------

    /**
     * Sisa yang belum dibayar.
     *
     * Tidak pernah negatif: `dibayar` yang melebihi nominal berarti salah
     * input, dan menampilkan terhutang minus hanya membingungkan.
     */
    public function terhutang(): int
    {
        return max(0, (int) $this->nominal - (int) $this->dibayar);
    }

    /**
     * Diturunkan, bukan disimpan.
     *
     * Kalau status ikut jadi kolom, cepat atau lambat akan ada baris bertanda
     * "lunas" yang `dibayar`-nya masih nol. Dengan satu sumber angka, keadaan
     * itu tidak mungkin terjadi.
     */
    public function sudahLunas(): bool
    {
        return $this->terhutang() === 0;
    }

    public function statusBayar(): string
    {
        return $this->sudahLunas() ? 'lunas' : 'belum';
    }

    public function statusBayarLabel(): string
    {
        return $this->sudahLunas() ? 'Lunas' : 'Belum Lunas';
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
