<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Kegiatan;
use App\Services\TaksasiResult;
use App\Support\Izin;
use App\Support\Rupiah;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Kegiatan
 *
 * Mode ringkas dipakai untuk daftar; mode lengkap (withTaksasi) menambahkan
 * breakdown baris-per-baris yang siap dirender di layar detail & PDF.
 */
class KegiatanResource extends JsonResource
{
    protected bool $withTaksasi = false;

    public function withTaksasi(bool $value = true): static
    {
        $this->withTaksasi = $value;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'kode' => $this->kode,
            'nama' => $this->nama,
            'keterangan' => $this->keterangan,
            'lokasi' => $this->lokasi,
            'sumber_dana' => $this->sumber_dana,
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            'pagu' => (int) $this->pagu,
            'pagu_formatted' => Rupiah::format($this->pagu),

            'netto' => (int) $this->netto,
            'profit_kotor' => (int) $this->profit_kotor,
            'profit_bersih' => (int) $this->profit_bersih,
            'hasil_bersih_per_owner' => (int) $this->hasil_bersih_per_owner,
            'hasil_bersih_per_owner_formatted' => Rupiah::format($this->hasil_bersih_per_owner),
            'jml_owner' => (int) $this->jml_owner,
            'is_rugi' => (int) $this->profit_kotor < 0,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (! $this->withTaksasi) {
            return $data;
        }

        $hasil = $this->hitung();
        $kas = $this->ringkasanKas();

        return $data + [
            'rates' => [
                'rate_ppn' => $this->rate_ppn,
                'rate_pph' => $this->rate_pph,
                'rate_rencana' => $this->rate_rencana,
                'rate_kewajiban' => $this->rate_kewajiban,
                'rate_administrasi' => $this->rate_administrasi,
                'rate_perusahaan' => $this->rate_perusahaan,
                'rate_investor' => $this->rate_investor,
                'jml_owner' => (int) $this->jml_owner,
                'total_persen_beban' => $hasil->total_persen_beban,
                'sisa_persen' => $hasil->sisa_persen,
            ],

            'pelaksanaan_real_input' => $this->pelaksanaan_real !== null ? (int) $this->pelaksanaan_real : null,
            'pelaksanaan_real_sumber' => $this->sumberPelaksanaanReal(),

            // false = kegiatan baru yang persentasenya belum ditentukan.
            // Aplikasi memakainya untuk menampilkan bagian taksasi sebagai
            // belum diisi alih-alih menampilkan profit yang belum berarti.
            'rate_terisi' => $this->rateTerisi(),

            // Dua penyusun Biaya Pelaksanaan Real, dipisah agar layar detail
            // bisa menampilkan asal angkanya tanpa memanggil endpoint lain.
            'total_bahan_baku' => $this->totalBahanBaku(),
            'total_bahan_baku_formatted' => Rupiah::format($this->totalBahanBaku()),
            'total_upah' => $this->totalUpah(),
            'total_upah_formatted' => Rupiah::format($this->totalUpah()),
            'jumlah_item_bahan_baku' => $this->bahanBakuItems()->count(),
            'jumlah_lampiran' => $this->lampiran()->count(),

            'izin' => Izin::ringkasan($request->user()),

            'taksasi' => $hasil->toArray(),

            // Breakdown siap-render: satu entri = satu kolom di Excel.
            'breakdown' => $this->breakdown($hasil),

            'ringkasan_kas' => [
                'masuk' => $kas['masuk'],
                'masuk_formatted' => Rupiah::format($kas['masuk']),
                'keluar' => $kas['keluar'],
                'keluar_formatted' => Rupiah::format($kas['keluar']),
                'saldo' => $kas['saldo'],
                'saldo_formatted' => Rupiah::format($kas['saldo']),
                'jumlah_transaksi' => $this->cashFlows()->count(),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function breakdown(TaksasiResult $h): array
    {
        $row = fn (string $key, string $label, int $nilai, ?string $persen = null, string $tipe = 'beban', ?string $catatan = null) => [
            'key' => $key,
            'label' => $label,
            'persen' => $persen,
            'nilai' => $nilai,
            'nilai_formatted' => Rupiah::format($nilai),
            'tipe' => $tipe, // dasar | pengurang | beban | hasil
            'catatan' => $catatan,
        ];

        return [
            $row('pagu', 'Pagu', $h->pagu, '100%', 'dasar'),
            $row('ppn', 'PPN', $h->ppn, Rupiah::persen($this->rate_ppn), 'pengurang', 'Dihitung dari Pagu'),
            $row('pph', 'PPh', $h->pph, Rupiah::persen($this->rate_pph), 'pengurang', 'Dihitung dari Pagu'),
            $row('netto', 'Netto (Pagu − PPN − PPh)', $h->netto, null, 'dasar', 'Dasar semua persentase di bawah'),
            $row('rencana_pelaksanaan', 'Rencana Pelaksanaan (Taksasi Belanja)', $h->rencana_pelaksanaan, Rupiah::persen($this->rate_rencana), 'rencana', 'Plafon belanja, tidak mengurangi profit'),
            $row('biaya_kewajiban', 'Biaya Kewajiban', $h->biaya_kewajiban, Rupiah::persen($this->rate_kewajiban), 'beban'),
            $row('pelaksanaan_real', 'Biaya Pelaksanaan Real (Bahan + Upah)', $h->pelaksanaan_real, Rupiah::persen($h->persen_pelaksanaan_real, 2), 'beban', match ($this->sumberPelaksanaanReal()) {
                'manual' => 'Input manual',
                'kas' => 'Otomatis dari catatan kas (bahan + upah)',
                default => 'Belum ada realisasi — memakai proyeksi Rencana',
            }),
            $row('biaya_administrasi', 'Administrasi', $h->biaya_administrasi, Rupiah::persen($this->rate_administrasi), 'beban'),
            $row('biaya_perusahaan', 'Biaya Perusahaan', $h->biaya_perusahaan, Rupiah::persen($this->rate_perusahaan), 'beban'),
            $row('profit_kotor', 'Profit Kotor', $h->profit_kotor, Rupiah::persen($h->persen_profit_kotor, 2), 'hasil'),
            $row('bagi_hasil_investor', 'Bagi Hasil Investor', $h->bagi_hasil_investor, Rupiah::persen($this->rate_investor), 'pengurang'),
            $row('profit_bersih', 'Profit Bersih', $h->profit_bersih, null, 'hasil'),
            $row('hasil_bersih_per_owner', 'Hasil Bersih (per Owner)', $h->hasil_bersih_per_owner, null, 'hasil', sprintf('Dibagi %d owner%s', $h->jml_owner, $h->sisa_pembulatan !== 0 ? sprintf('; sisa pembulatan %s masuk kas perusahaan', Rupiah::format($h->sisa_pembulatan)) : '')),
        ];
    }
}
