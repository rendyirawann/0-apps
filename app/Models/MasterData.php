<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisMaster;
use Database\Factories\MasterDataFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu pilihan pada daftar acuan (satuan, toko, sumber dana).
 *
 * Dinonaktifkan alih-alih dihapus bila sudah dipakai data lama: kolom `toko`
 * dan `satuan` pada bahan baku menyimpan teksnya, bukan id-nya, sehingga data
 * lama tetap utuh -- tetapi pilihannya tidak perlu lagi muncul saat input baru.
 */
class MasterData extends Model
{
    /** @use HasFactory<MasterDataFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'master_data';

    protected $fillable = ['jenis', 'nama', 'keterangan', 'urutan', 'aktif', 'created_by'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisMaster::class,
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<self> $query */
    public function scopeJenis(Builder $query, JenisMaster|string $jenis): void
    {
        $query->where('jenis', $jenis instanceof JenisMaster ? $jenis->value : $jenis);
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): void
    {
        $query->where('aktif', true);
    }

    /**
     * Urutan tampil: `urutan` dulu, lalu nama.
     *
     * @param  Builder<self>  $query
     */
    public function scopeTerurut(Builder $query): void
    {
        $query->orderBy('urutan')->orderBy('nama');
    }
}
