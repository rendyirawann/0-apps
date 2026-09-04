<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisKas;
use App\Enums\KategoriKas;
use App\Enums\StatusKegiatan;
use App\Services\TaksasiCalculator;
use App\Services\TaksasiResult;
use Database\Factories\KegiatanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kegiatan extends Model
{
    /** @use HasFactory<KegiatanFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'kegiatan';

    protected $fillable = [
        'kode', 'nama', 'keterangan', 'lokasi', 'sumber_dana',
        'tanggal_mulai', 'tanggal_selesai', 'status',
        'pagu', 'pelaksanaan_real',
        'rate_ppn', 'rate_pph', 'rate_rencana', 'rate_kewajiban',
        'rate_administrasi', 'rate_perusahaan', 'rate_investor', 'jml_owner',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusKegiatan::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',

            // uang -> rupiah bulat
            'pagu' => 'integer',
            'pelaksanaan_real' => 'integer',
            'ppn' => 'integer',
            'pph' => 'integer',
            'netto' => 'integer',
            'rencana_pelaksanaan' => 'integer',
            'biaya_kewajiban' => 'integer',
            'biaya_administrasi' => 'integer',
            'biaya_perusahaan' => 'integer',
            'profit_kotor' => 'integer',
            'bagi_hasil_investor' => 'integer',
            'profit_bersih' => 'integer',
            'hasil_bersih_per_owner' => 'integer',
            'sisa_pembulatan' => 'integer',

            // persen
            'rate_ppn' => 'float',
            'rate_pph' => 'float',
            'rate_rencana' => 'float',
            'rate_kewajiban' => 'float',
            'rate_administrasi' => 'float',
            'rate_perusahaan' => 'float',
            'rate_investor' => 'float',
            'jml_owner' => 'integer',
        ];
    }

    // ------------------------------------------------------------------
    // Relasi
    // ------------------------------------------------------------------

    public function cashFlows(): HasMany
    {
        return $this->hasMany(CashFlow::class);
    }

    /** Rincian bahan baku per item -- sumber angka "Bahan Baku". */
    public function bahanBakuItems(): HasMany
    {
        return $this->hasMany(BahanBakuItem::class)->orderBy('urutan')->orderBy('id');
    }

    /** Lampiran bukti (foto struk belanja, dokumen pendukung). */
    public function lampiran(): HasMany
    {
        return $this->hasMany(Lampiran::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ------------------------------------------------------------------
    // Perhitungan
    // ------------------------------------------------------------------

    /**
     * Total bahan baku, dijumlahkan dari rincian per item.
     *
     * toBase(): query agregat tidak perlu melewati cast Eloquent.
     */
    public function totalBahanBaku(): int
    {
        return (int) round((float) $this->bahanBakuItems()
            ->toBase()
            ->sum('subtotal'));
    }

    /** Total upah pekerja dari catatan kas. */
    /**
     * Upah yang sudah BENAR-BENAR dibayar.
     *
     * Dijumlahkan dari `dibayar`, bukan `nominal`. Upah yang dicatat sebagai
     * hutang belum mengeluarkan uang, jadi belum boleh menaikkan Biaya
     * Pelaksanaan Real -- kalau ikut dihitung, profit terlihat lebih kecil
     * daripada keadaan sebenarnya di kas.
     *
     * Sisanya ada di totalUpahTerhutang().
     */
    public function totalUpah(): int
    {
        return (int) round((float) $this->cashFlows()
            ->where('jenis', JenisKas::Keluar->value)
            ->whereIn('kategori', KategoriKas::pelaksanaanReal())
            ->toBase()
            ->sum('dibayar'));
    }

    /** Nilai upah yang sudah dicatat tetapi belum dibayar. */
    public function totalUpahTerhutang(): int
    {
        return (int) round((float) $this->cashFlows()
            ->where('jenis', JenisKas::Keluar->value)
            ->whereIn('kategori', KategoriKas::pelaksanaanReal())
            ->toBase()
            ->selectRaw('COALESCE(SUM(GREATEST(nominal - dibayar, 0)), 0) AS sisa')
            ->value('sisa'));
    }

    /**
     * Realisasi Biaya Pelaksanaan = bahan baku (rincian item) + upah (kas).
     */
    /**
     * Apakah persentase kegiatan ini sudah pernah ditentukan.
     *
     * Kegiatan baru dibuat hanya dengan nama dan pagu, sehingga seluruh rate
     * masih nol. Dengan rate nol, rumusnya menghasilkan profit = pagu -- angka
     * yang benar secara aritmetika tetapi tidak berarti apa-apa. Penanda ini
     * dikirim ke aplikasi supaya bagian transaksi ditampilkan sebagai "belum
     * diisi", bukan sebagai hasil perhitungan.
     */
    public function rateTerisi(): bool
    {
        foreach ([
            $this->rate_ppn,
            $this->rate_pph,
            $this->rate_rencana,
            $this->rate_kewajiban,
            $this->rate_administrasi,
            $this->rate_perusahaan,
            $this->rate_investor,
        ] as $rate) {
            if ((float) $rate > 0) {
                return true;
            }
        }

        return false;
    }

    public function realisasiPelaksanaan(): int
    {
        return $this->totalBahanBaku() + $this->totalUpah();
    }

    /**
     * Nilai "Biaya Pelaksanaan Real" yang efektif dipakai di rumus profit.
     * Prioritas: kolom manual -> realisasi (item + upah) -> rencana (proyeksi).
     */
    public function pelaksanaanRealEfektif(): int
    {
        if ($this->pelaksanaan_real !== null) {
            return (int) $this->pelaksanaan_real;
        }

        $realisasi = $this->realisasiPelaksanaan();

        return $realisasi > 0 ? $realisasi : (int) $this->rencana_pelaksanaan;
    }

    public function sumberPelaksanaanReal(): string
    {
        if ($this->pelaksanaan_real !== null) {
            return 'manual';
        }

        // 'realisasi' juga saat totalnya masih nol: nol itu keadaan yang
        // sebenarnya, bukan mode lain.
        return 'realisasi';
    }

    public function hitung(): TaksasiResult
    {
        // Tanpa nilai manual, yang dipakai adalah realisasi apa adanya --
        // termasuk nol. Tidak ada lagi proyeksi dari Rencana.
        $real = $this->pelaksanaan_real ?? $this->realisasiPelaksanaan();

        return app(TaksasiCalculator::class)->hitung([
            'pagu' => $this->pagu,
            'pelaksanaan_real' => $real,
            'rate_ppn' => $this->rate_ppn,
            'rate_pph' => $this->rate_pph,
            'rate_rencana' => $this->rate_rencana,
            'rate_kewajiban' => $this->rate_kewajiban,
            'rate_administrasi' => $this->rate_administrasi,
            'rate_perusahaan' => $this->rate_perusahaan,
            'rate_investor' => $this->rate_investor,
            'jml_owner' => $this->jml_owner,
        ]);
    }

    /**
     * Hitung ulang lalu simpan snapshot kolom hasil.
     *
     * forceFill, BUKAN fill: kolom hasil (netto, profit_kotor, dst) sengaja
     * tidak dimasukkan ke $fillable supaya tidak bisa dikirim klien. Dengan
     * fill(), Eloquent menolaknya diam-diam sehingga snapshot tidak pernah
     * tersimpan dan daftar kegiatan menampilkan nol.
     */
    public function recalculate(bool $save = true): TaksasiResult
    {
        $hasil = $this->hitung();
        $this->forceFill($hasil->toColumns());

        if ($save) {
            $this->saveQuietly();
        }

        return $hasil;
    }

    // ------------------------------------------------------------------
    // Ringkasan kas
    // ------------------------------------------------------------------

    /** @return array{masuk:int, keluar:int, saldo:int} */
    public function ringkasanKas(): array
    {
        // toBase(): query agregat tidak boleh lewat cast Eloquent, kalau tidak
        // kolom `jenis` jadi objek enum dan gagal dipakai sebagai kunci array.
        $rows = $this->cashFlows()
            ->selectRaw('jenis, COALESCE(SUM(nominal), 0) AS total')
            ->groupBy('jenis')
            ->toBase()
            ->pluck('total', 'jenis');

        $masuk = (int) round((float) ($rows[JenisKas::Masuk->value] ?? 0));
        $keluar = (int) round((float) ($rows[JenisKas::Keluar->value] ?? 0));

        return ['masuk' => $masuk, 'keluar' => $keluar, 'saldo' => $masuk - $keluar];
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = '%'.strtolower($term).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->whereRaw('LOWER(nama) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(kode, \'\')) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(lokasi, \'\')) LIKE ?', [$term]);
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }
}
