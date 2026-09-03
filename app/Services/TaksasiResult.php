<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Hasil perhitungan satu baris "Taksasi Pekerjaan".
 * Semua nilai uang adalah rupiah bulat (int), tanpa desimal.
 */
final readonly class TaksasiResult
{
    public function __construct(
        public int $pagu,
        public int $ppn,
        public int $pph,
        public int $netto,
        public int $rencana_pelaksanaan,
        public int $biaya_kewajiban,
        public int $pelaksanaan_real,
        public int $biaya_administrasi,
        public int $biaya_perusahaan,
        public int $profit_kotor,
        public int $bagi_hasil_investor,
        public int $profit_bersih,
        public int $hasil_bersih_per_owner,
        public int $sisa_pembulatan,
        public int $jml_owner,
        // --- turunan untuk tampilan / analisa ---
        public float $persen_pelaksanaan_real,
        public float $persen_profit_kotor,
        public int $selisih_rencana_real,
        public float $total_persen_beban,
        public float $sisa_persen,
        public bool $is_rugi,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'pagu' => $this->pagu,
            'ppn' => $this->ppn,
            'pph' => $this->pph,
            'netto' => $this->netto,
            'rencana_pelaksanaan' => $this->rencana_pelaksanaan,
            'biaya_kewajiban' => $this->biaya_kewajiban,
            'pelaksanaan_real' => $this->pelaksanaan_real,
            'biaya_administrasi' => $this->biaya_administrasi,
            'biaya_perusahaan' => $this->biaya_perusahaan,
            'profit_kotor' => $this->profit_kotor,
            'bagi_hasil_investor' => $this->bagi_hasil_investor,
            'profit_bersih' => $this->profit_bersih,
            'hasil_bersih_per_owner' => $this->hasil_bersih_per_owner,
            'sisa_pembulatan' => $this->sisa_pembulatan,
            'jml_owner' => $this->jml_owner,
            'persen_pelaksanaan_real' => $this->persen_pelaksanaan_real,
            'persen_profit_kotor' => $this->persen_profit_kotor,
            'selisih_rencana_real' => $this->selisih_rencana_real,
            'total_persen_beban' => $this->total_persen_beban,
            'sisa_persen' => $this->sisa_persen,
            'is_rugi' => $this->is_rugi,
        ];
    }

    /** Kolom yang dipersistensi ke tabel `kegiatan`. @return array<string, mixed> */
    public function toColumns(): array
    {
        return [
            'ppn' => $this->ppn,
            'pph' => $this->pph,
            'netto' => $this->netto,
            'rencana_pelaksanaan' => $this->rencana_pelaksanaan,
            'biaya_kewajiban' => $this->biaya_kewajiban,
            'biaya_administrasi' => $this->biaya_administrasi,
            'biaya_perusahaan' => $this->biaya_perusahaan,
            'profit_kotor' => $this->profit_kotor,
            'bagi_hasil_investor' => $this->bagi_hasil_investor,
            'profit_bersih' => $this->profit_bersih,
            'hasil_bersih_per_owner' => $this->hasil_bersih_per_owner,
            'sisa_pembulatan' => $this->sisa_pembulatan,
        ];
    }
}
