<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Mesin hitung "Taksasi Pekerjaan".
 *
 * ==================================================================
 * URUTAN RUMUS (mengikuti kolom C..N di Excel)
 * ==================================================================
 *   ppn        = round(pagu  * rate_ppn        / 100)      <- lurus dari PAGU
 *   pph        = round(pagu  * rate_pph        / 100)      <- lurus dari PAGU
 *   netto      = pagu - ppn - pph
 *   rencana    = round(netto * rate_rencana    / 100)
 *   kewajiban  = round(netto * rate_kewajiban  / 100)
 *   administr. = round(netto * rate_administrasi / 100)
 *   perusahaan = round(netto * rate_perusahaan / 100)
 *   real       = input manual, atau SUM(kas keluar: bahan + upah)
 *
 *   profit_kotor  = netto - kewajiban - real - administrasi - perusahaan
 *   investor      = round(profit_kotor * rate_investor / 100)
 *   profit_bersih = profit_kotor - investor
 *   per_owner     = intdiv(profit_bersih, jml_owner)
 *   sisa          = profit_bersih - (per_owner * jml_owner)
 *
 * ==================================================================
 * ATURAN PEMBULATAN  (pembulatan ke rupiah, tanpa desimal)
 * ==================================================================
 * 1. Tiap kolom dibulatkan SAAT dihitung; kolom turunan memakai angka
 *    yang sudah dibulatkan. Bukan "hitung eksak lalu bulatkan di akhir",
 *    supaya kolom-kolom di laporan selalu balance saat dijumlah manual.
 *
 * 2. `netto`, `profit_kotor`, dan `profit_bersih` adalah hasil PENGURANGAN,
 *    bukan round() ulang. Kalau profit_bersih ikut di-round() dari persen,
 *    saat profit_kotor bernilai ganjil bisa terjadi
 *    investor + profit_bersih != profit_kotor (selisih 1 rupiah).
 *
 * 3. Pembagian ke owner memakai intdiv() (memotong ke arah NOL), bukan floor().
 *    floor() salah untuk kondisi rugi:
 *      floor(-100 / 3) = -34  ->  3 x -34 = -102  (rugi jadi LEBIH besar)
 *      intdiv(-100, 3) = -33  ->  3 x -33 =  -99  (sisa -1, benar)
 *    Sisa 0..(jml_owner-1) rupiah tidak dibagikan -> masuk kas perusahaan,
 *    dilaporkan lewat field `sisa_pembulatan`.
 */
final class TaksasiCalculator
{
    /**
     * @param  array{
     *     pagu?: int|float|string,
     *     pelaksanaan_real?: int|float|string|null,
     *     rate_ppn?: int|float|string,
     *     rate_pph?: int|float|string,
     *     rate_rencana?: int|float|string,
     *     rate_kewajiban?: int|float|string,
     *     rate_administrasi?: int|float|string,
     *     rate_perusahaan?: int|float|string,
     *     rate_investor?: int|float|string,
     *     jml_owner?: int|string,
     *  }  $input
     */
    public function hitung(array $input): TaksasiResult
    {
        $pagu = self::rp($input['pagu'] ?? 0);

        $rPpn = self::pct($input['rate_ppn'] ?? 0);
        $rPph = self::pct($input['rate_pph'] ?? 0);
        $rRencana = self::pct($input['rate_rencana'] ?? 0);
        $rKewajiban = self::pct($input['rate_kewajiban'] ?? 0);
        $rAdministrasi = self::pct($input['rate_administrasi'] ?? 0);
        $rPerusahaan = self::pct($input['rate_perusahaan'] ?? 0);
        $rInvestor = self::pct($input['rate_investor'] ?? 0);

        $jmlOwner = max(1, (int) ($input['jml_owner'] ?? 1));

        // ---- pajak: basis PAGU bruto (=C6*11%, =C6*1,75%) ----
        $ppn = self::rp($pagu * $rPpn / 100);
        $pph = self::rp($pagu * $rPph / 100);

        // ---- netto: basis semua persentase berikutnya ----
        $netto = $pagu - $ppn - $pph;

        $rencana = self::rp($netto * $rRencana / 100);
        $kewajiban = self::rp($netto * $rKewajiban / 100);
        $administrasi = self::rp($netto * $rAdministrasi / 100);
        $perusahaan = self::rp($netto * $rPerusahaan / 100);

        // Biaya Pelaksanaan Real: kalau tidak diisi, pakai rencana sebagai
        // proyeksi supaya kolom profit tetap terisi saat kegiatan masih draft.
        $real = isset($input['pelaksanaan_real']) && $input['pelaksanaan_real'] !== null
            ? self::rp($input['pelaksanaan_real'])
            : $rencana;

        // ---- profit ----
        $profitKotor = $netto - $kewajiban - $real - $administrasi - $perusahaan;
        $investor = self::rp($profitKotor * $rInvestor / 100);
        $profitBersih = $profitKotor - $investor;      // selisih, bukan round() ulang

        $perOwner = intdiv($profitBersih, $jmlOwner);  // memotong ke arah nol
        $sisa = $profitBersih - ($perOwner * $jmlOwner);

        // ---- turunan untuk tampilan ----
        $totalPersenBeban = $rRencana + $rKewajiban + $rAdministrasi + $rPerusahaan;

        return new TaksasiResult(
            pagu: $pagu,
            ppn: $ppn,
            pph: $pph,
            netto: $netto,
            rencana_pelaksanaan: $rencana,
            biaya_kewajiban: $kewajiban,
            pelaksanaan_real: $real,
            biaya_administrasi: $administrasi,
            biaya_perusahaan: $perusahaan,
            profit_kotor: $profitKotor,
            bagi_hasil_investor: $investor,
            profit_bersih: $profitBersih,
            hasil_bersih_per_owner: $perOwner,
            sisa_pembulatan: $sisa,
            jml_owner: $jmlOwner,
            persen_pelaksanaan_real: self::persen($real, $netto),
            persen_profit_kotor: self::persen($profitKotor, $netto),
            selisih_rencana_real: $rencana - $real,
            total_persen_beban: round($totalPersenBeban, 3),
            sisa_persen: round(100 - $totalPersenBeban, 3),
            is_rugi: $profitKotor < 0,
        );
    }

    /** Pembulatan ke rupiah bulat. round() PHP = half away from zero. */
    private static function rp(int|float|string $value): int
    {
        return (int) round((float) $value);
    }

    private static function pct(int|float|string $value): float
    {
        return (float) $value;
    }

    private static function persen(int $bagian, int $dari): float
    {
        return $dari === 0 ? 0.0 : round($bagian / $dari * 100, 2);
    }
}
