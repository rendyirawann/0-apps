<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TaksasiCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Mengunci aturan perhitungan & pembulatan "Taksasi Pekerjaan".
 * Test ini murni unit (tanpa database) sehingga cepat dijalankan.
 */
class TaksasiCalculatorTest extends TestCase
{
    private TaksasiCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new TaksasiCalculator;
    }

    /** @return array<string, mixed> */
    private function rates(array $override = []): array
    {
        return array_replace([
            'rate_ppn' => 11,
            'rate_pph' => 1.75,
            'rate_rencana' => 60,
            'rate_kewajiban' => 12,
            'rate_administrasi' => 1,
            'rate_perusahaan' => 1.5,
            'rate_investor' => 50,
            'jml_owner' => 3,

            // Pada sheet aslinya kolom Biaya Pelaksanaan Real memang terisi,
            // sebesar Rencana. Dikirim eksplisit di sini supaya verifikasi
            // terhadap Excel tidak bergantung pada nilai pengganti apa pun --
            // kalkulator memakai NOL bila kolom ini kosong.
            'pelaksanaan_real' => 209_400_000,
        ], $override);
    }

    #[Test]
    public function cocok_dengan_contoh_excel(): void
    {
        $h = $this->calc->hitung($this->rates(['pagu' => 400_000_000]));

        // Angka-angka ini diambil langsung dari sheet "Taksasi Pekerjaan".
        $this->assertSame(44_000_000, $h->ppn, 'PPN 11% dari pagu');
        $this->assertSame(7_000_000, $h->pph, 'PPh 1,75% dari pagu');
        $this->assertSame(349_000_000, $h->netto);
        $this->assertSame(209_400_000, $h->rencana_pelaksanaan);
        $this->assertSame(41_880_000, $h->biaya_kewajiban);
        $this->assertSame(3_490_000, $h->biaya_administrasi);
        $this->assertSame(5_235_000, $h->biaya_perusahaan);
        $this->assertSame(88_995_000, $h->profit_kotor);
        $this->assertSame(44_497_500, $h->bagi_hasil_investor);
        $this->assertSame(44_497_500, $h->profit_bersih);
        $this->assertSame(14_832_500, $h->hasil_bersih_per_owner);
        $this->assertSame(0, $h->sisa_pembulatan);
    }

    #[Test]
    public function pajak_dihitung_dari_pagu_bukan_dari_netto(): void
    {
        $h = $this->calc->hitung($this->rates(['pagu' => 400_000_000]));

        // Kalau basisnya keliru (mis. 11/111 dari pagu) PPN akan 39.639.640.
        $this->assertSame(44_000_000, $h->ppn);
        $this->assertSame((int) round(400_000_000 * 0.0175), $h->pph);
    }

    #[Test]
    public function persentase_beban_dihitung_dari_netto(): void
    {
        $h = $this->calc->hitung($this->rates(['pagu' => 400_000_000]));

        $this->assertSame((int) round($h->netto * 0.60), $h->rencana_pelaksanaan);
        $this->assertSame((int) round($h->netto * 0.12), $h->biaya_kewajiban);
        $this->assertSame((int) round($h->netto * 0.01), $h->biaya_administrasi);
        $this->assertSame((int) round($h->netto * 0.015), $h->biaya_perusahaan);
    }

    #[Test]
    public function investor_ditambah_profit_bersih_selalu_sama_dengan_profit_kotor(): void
    {
        // Pagu ganjil sengaja dipilih agar pembagian 50% menghasilkan pecahan.
        foreach ([400_000_001, 333_333_333, 123_456_789, 999_999_999, 1] as $pagu) {
            $h = $this->calc->hitung($this->rates(['pagu' => $pagu]));

            $this->assertSame(
                $h->profit_kotor,
                $h->bagi_hasil_investor + $h->profit_bersih,
                "profit_kotor harus utuh terbagi pada pagu {$pagu}",
            );
        }
    }

    #[Test]
    public function pembagian_owner_tidak_pernah_melebihi_profit_bersih(): void
    {
        foreach ([400_000_001, 333_333_333, 123_456_789, 7, 0] as $pagu) {
            foreach ([1, 2, 3, 5, 7] as $owner) {
                $h = $this->calc->hitung($this->rates(['pagu' => $pagu, 'jml_owner' => $owner]));

                $this->assertSame(
                    $h->profit_bersih,
                    $h->hasil_bersih_per_owner * $owner + $h->sisa_pembulatan,
                    "pagu {$pagu}, {$owner} owner",
                );

                $this->assertLessThan(
                    $owner,
                    abs($h->sisa_pembulatan),
                    'sisa pembulatan harus lebih kecil dari jumlah owner',
                );
            }
        }
    }

    #[Test]
    public function saat_rugi_pembagian_tidak_memperbesar_kerugian(): void
    {
        // Pelaksanaan real jauh di atas netto -> profit kotor minus.
        $h = $this->calc->hitung($this->rates([
            'pagu' => 400_000_000,
            'pelaksanaan_real' => 330_000_000,
        ]));

        $this->assertTrue($h->is_rugi);
        $this->assertLessThan(0, $h->profit_kotor);

        // Inti aturan intdiv(): total yang dibebankan ke owner tidak boleh
        // lebih besar (lebih minus) daripada profit bersih.
        $totalDibagikan = $h->hasil_bersih_per_owner * $h->jml_owner;

        $this->assertGreaterThanOrEqual(
            $h->profit_bersih,
            $totalDibagikan,
            'floor() akan gagal di sini: kerugian yang dibagikan jadi lebih besar dari kerugian nyata',
        );

        $this->assertSame(
            $h->profit_bersih,
            $totalDibagikan + $h->sisa_pembulatan,
        );
    }

    #[Test]
    public function intdiv_berbeda_dari_floor_untuk_angka_negatif(): void
    {
        // Dokumentasi hidup atas keputusan desain: -100 / 3
        $this->assertSame(-33, intdiv(-100, 3));
        $this->assertSame(-34, (int) floor(-100 / 3));

        // intdiv: 3 x -33 = -99  (sisa -1, kerugian tetap -100)
        // floor : 3 x -34 = -102 (kerugian membengkak 2 rupiah)
        $this->assertSame(-99, intdiv(-100, 3) * 3);
        $this->assertSame(-102, (int) floor(-100 / 3) * 3);
    }

    #[Test]
    public function pelaksanaan_real_kosong_berarti_nol_bukan_proyeksi(): void
    {
        $tanpaReal = $this->calc->hitung($this->rates([
            'pagu' => 400_000_000,
            'pelaksanaan_real' => null,
        ]));

        // Kosong berarti belum ada belanja yang dicatat, bukan "sebesar
        // rencana". Menebaknya dari Rencana membuat kegiatan baru terbaca
        // seolah belanjanya sudah berjalan.
        $this->assertSame(0, $tanpaReal->pelaksanaan_real);

        // Seluruh nilai Rencana masih utuh sebagai pembanding.
        $this->assertSame(209_400_000, $tanpaReal->rencana_pelaksanaan);
        $this->assertSame(209_400_000, $tanpaReal->selisih_rencana_real);

        // Karena belum ada belanja, profit kotor persis netto dikurangi
        // kewajiban, administrasi, dan biaya perusahaan saja.
        $this->assertSame(
            $tanpaReal->netto
                - $tanpaReal->biaya_kewajiban
                - $tanpaReal->biaya_administrasi
                - $tanpaReal->biaya_perusahaan,
            $tanpaReal->profit_kotor,
        );
    }

    #[Test]
    public function selisih_negatif_menandakan_over_budget(): void
    {
        $h = $this->calc->hitung($this->rates([
            'pagu' => 400_000_000,
            'pelaksanaan_real' => 219_400_000, // 10 juta di atas rencana
        ]));

        $this->assertSame(-10_000_000, $h->selisih_rencana_real);
        $this->assertSame(78_995_000, $h->profit_kotor, 'profit turun tepat sebesar kelebihan belanja');
    }

    #[Test]
    public function total_persen_beban_dan_sisa_persen_konsisten(): void
    {
        $h = $this->calc->hitung($this->rates(['pagu' => 400_000_000]));

        $this->assertSame(74.5, $h->total_persen_beban);
        $this->assertSame(25.5, $h->sisa_persen);
        $this->assertSame(25.5, $h->persen_profit_kotor);
    }

    #[Test]
    public function pagu_nol_tidak_membagi_dengan_nol(): void
    {
        $h = $this->calc->hitung($this->rates([
            'pagu' => 0,
            'pelaksanaan_real' => 0,
        ]));

        $this->assertSame(0, $h->netto);
        $this->assertSame(0, $h->profit_kotor);
        $this->assertSame(0.0, $h->persen_profit_kotor);
        $this->assertSame(0.0, $h->persen_pelaksanaan_real);
        $this->assertFalse($h->is_rugi);
    }

    #[Test]
    public function jumlah_owner_nol_diperlakukan_sebagai_satu(): void
    {
        $h = $this->calc->hitung($this->rates(['pagu' => 400_000_000, 'jml_owner' => 0]));

        $this->assertSame(1, $h->jml_owner);
        $this->assertSame($h->profit_bersih, $h->hasil_bersih_per_owner);
    }

    #[Test]
    #[DataProvider('rateBerbedaProvider')]
    public function menghormati_rate_per_kegiatan(array $input, int $harapanProfitKotor, int $harapanPerOwner): void
    {
        $h = $this->calc->hitung($input);

        $this->assertSame($harapanProfitKotor, $h->profit_kotor);
        $this->assertSame($harapanPerOwner, $h->hasil_bersih_per_owner);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: int, 2: int}> */
    public static function rateBerbedaProvider(): array
    {
        return [
            // PPh 2,65%, rencana 65%, 2 owner (kegiatan KG-2026-002 pada seeder)
            'pph 2,65% dan 2 owner' => [
                [
                    'pagu' => 275_000_000,
                    'pelaksanaan_real' => 172_000_000,
                    'rate_ppn' => 11,
                    'rate_pph' => 2.65,
                    'rate_rencana' => 65,
                    'rate_kewajiban' => 10,
                    'rate_administrasi' => 1.5,
                    'rate_perusahaan' => 2,
                    'rate_investor' => 40,
                    'jml_owner' => 2,
                ],
                33_405_062,
                10_021_518,
            ],

            // Tanpa investor: seluruh profit kotor jadi profit bersih.
            'tanpa investor (0%)' => [
                [
                    'pagu' => 400_000_000,
                    // 60% dari netto 349 juta -- sama dengan Rencana.
                    'pelaksanaan_real' => 209_400_000,
                    'rate_ppn' => 11,
                    'rate_pph' => 1.75,
                    'rate_rencana' => 60,
                    'rate_kewajiban' => 12,
                    'rate_administrasi' => 1,
                    'rate_perusahaan' => 1.5,
                    'rate_investor' => 0,
                    'jml_owner' => 3,
                ],
                88_995_000,
                29_665_000,
            ],

            // Bebas pajak (mis. nilai kontrak di bawah batas PPN).
            'tanpa pajak' => [
                [
                    'pagu' => 100_000_000,
                    // Tanpa pajak, netto = pagu, jadi 60% Rencana = 60 juta.
                    'pelaksanaan_real' => 60_000_000,
                    'rate_ppn' => 0,
                    'rate_pph' => 0,
                    'rate_rencana' => 60,
                    'rate_kewajiban' => 12,
                    'rate_administrasi' => 1,
                    'rate_perusahaan' => 1.5,
                    'rate_investor' => 50,
                    'jml_owner' => 3,
                ],
                25_500_000,
                4_250_000,
            ],
        ];
    }
}
