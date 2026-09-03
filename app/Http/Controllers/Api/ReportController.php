<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\JenisKas;
use App\Enums\KategoriKas;
use App\Enums\StatusKegiatan;
use App\Http\Controllers\Controller;
use App\Http\Resources\KegiatanResource;
use App\Models\CashFlow;
use App\Models\Kegiatan;
use App\Support\ApiResponse;
use App\Support\Rupiah;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    #[OA\Get(
        path: '/api/laporan/ringkasan',
        operationId: 'laporanRingkasan',
        description: 'Angka untuk dashboard: jumlah kegiatan per status, akumulasi pagu & profit, posisi kas, tren kas bulanan, dan kegiatan dengan profit tertinggi. Semua bisa dibatasi periode.',
        summary: 'Ringkasan dashboard',
        security: [['bearerAuth' => []]],
        tags: ['Laporan'],
        parameters: [
            new OA\Parameter(name: 'dari', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sampai', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'bulan_tren', description: 'Jumlah bulan pada grafik tren kas', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 6, maximum: 24, minimum: 1)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function ringkasan(Request $request): JsonResponse
    {
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $kegiatanQuery = Kegiatan::query()
            ->when(filled($dari), fn ($q) => $q->whereDate('created_at', '>=', $dari))
            ->when(filled($sampai), fn ($q) => $q->whereDate('created_at', '<=', $sampai));

        $agg = (clone $kegiatanQuery)
            ->selectRaw('
                COUNT(*) AS jumlah,
                COALESCE(SUM(pagu), 0) AS total_pagu,
                COALESCE(SUM(ppn), 0) AS total_ppn,
                COALESCE(SUM(pph), 0) AS total_pph,
                COALESCE(SUM(netto), 0) AS total_netto,
                COALESCE(SUM(biaya_kewajiban), 0) AS total_kewajiban,
                COALESCE(SUM(biaya_administrasi), 0) AS total_administrasi,
                COALESCE(SUM(biaya_perusahaan), 0) AS total_perusahaan,
                COALESCE(SUM(profit_kotor), 0) AS total_profit_kotor,
                COALESCE(SUM(bagi_hasil_investor), 0) AS total_investor,
                COALESCE(SUM(profit_bersih), 0) AS total_profit_bersih,
                COALESCE(SUM(hasil_bersih_per_owner), 0) AS total_per_owner,
                COALESCE(SUM(sisa_pembulatan), 0) AS total_sisa_pembulatan
            ')
            ->toBase()
            ->first();

        $perStatus = (clone $kegiatanQuery)
            ->selectRaw('status, COUNT(*) AS jumlah, COALESCE(SUM(pagu), 0) AS total_pagu')
            ->groupBy('status')
            ->toBase()
            ->get()
            ->keyBy('status');

        $statusRows = array_map(function (StatusKegiatan $s) use ($perStatus): array {
            $row = $perStatus->get($s->value);

            return [
                'status' => $s->value,
                'label' => $s->label(),
                'jumlah' => (int) ($row->jumlah ?? 0),
                'total_pagu' => (int) round((float) ($row->total_pagu ?? 0)),
            ];
        }, StatusKegiatan::cases());

        $kasQuery = CashFlow::query()->periode($dari, $sampai);

        $kas = (clone $kasQuery)
            ->selectRaw('jenis, COALESCE(SUM(nominal), 0) AS total')
            ->groupBy('jenis')
            ->toBase()
            ->pluck('total', 'jenis');

        $masuk = (int) round((float) ($kas[JenisKas::Masuk->value] ?? 0));
        $keluar = (int) round((float) ($kas[JenisKas::Keluar->value] ?? 0));

        $topKegiatan = (clone $kegiatanQuery)
            ->orderByDesc('profit_bersih')
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'periode' => ['dari' => $dari, 'sampai' => $sampai],

            'kegiatan' => [
                'jumlah' => (int) $agg->jumlah,
                'per_status' => $statusRows,
            ],

            'akumulasi' => $this->withFormatted([
                'total_pagu' => (int) round((float) $agg->total_pagu),
                'total_ppn' => (int) round((float) $agg->total_ppn),
                'total_pph' => (int) round((float) $agg->total_pph),
                'total_netto' => (int) round((float) $agg->total_netto),
                'total_kewajiban' => (int) round((float) $agg->total_kewajiban),
                'total_administrasi' => (int) round((float) $agg->total_administrasi),
                'total_perusahaan' => (int) round((float) $agg->total_perusahaan),
                'total_profit_kotor' => (int) round((float) $agg->total_profit_kotor),
                'total_investor' => (int) round((float) $agg->total_investor),
                'total_profit_bersih' => (int) round((float) $agg->total_profit_bersih),
                'total_per_owner' => (int) round((float) $agg->total_per_owner),
                'total_sisa_pembulatan' => (int) round((float) $agg->total_sisa_pembulatan),
            ]),

            'kas' => $this->withFormatted([
                'masuk' => $masuk,
                'keluar' => $keluar,
                'saldo' => $masuk - $keluar,
            ]),

            'tren_kas' => $this->trenKas((int) $request->query('bulan_tren', 6)),

            'kas_per_kategori' => $this->kasPerKategori($dari, $sampai),

            'top_kegiatan' => KegiatanResource::collection($topKegiatan),
        ], 'Ringkasan laporan.');
    }

    #[OA\Get(
        path: '/api/laporan/rekap-kegiatan',
        operationId: 'laporanRekapKegiatan',
        description: 'Tabel rekap semua kegiatan dengan kolom seperti di Excel "Taksasi Pekerjaan", plus baris TOTAL. Dipakai layar Laporan di mobile.',
        summary: 'Rekap tabel kegiatan',
        security: [['bearerAuth' => []]],
        tags: ['Laporan'],
        parameters: [
            new OA\Parameter(name: 'dari', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sampai', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'berjalan', 'selesai', 'batal'])),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function rekapKegiatan(Request $request): JsonResponse
    {
        $rows = $this->rekapRows($request);

        return ApiResponse::success([
            'periode' => ['dari' => $request->query('dari'), 'sampai' => $request->query('sampai')],
            'baris' => $rows['baris'],
            'total' => $rows['total'],
        ], 'Rekap kegiatan.');
    }

    #[OA\Get(
        path: '/api/laporan/kegiatan/{id}/pdf',
        operationId: 'laporanKegiatanPdf',
        description: 'Mencetak laporan satu kegiatan ke PDF: identitas kegiatan, rincian taksasi baris-per-baris beserta persentasenya, dan daftar arus kas. Respons berupa berkas PDF (application/pdf).',
        summary: 'Cetak PDF taksasi kegiatan',
        security: [['bearerAuth' => []]],
        tags: ['Laporan'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'download', description: '1 = paksa unduh (attachment), 0 = tampilkan inline', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, enum: [0, 1])),
            new OA\Parameter(name: 'sertakan_kas', description: '1 = sertakan daftar arus kas', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, enum: [0, 1])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Berkas PDF',
                content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary')),
            ),
            new OA\Response(response: 404, description: 'Kegiatan tidak ditemukan'),
        ],
    )]
    public function kegiatanPdf(Request $request, Kegiatan $kegiatan): Response
    {
        $hasil = $kegiatan->hitung();
        $sertakanKas = $request->query('sertakan_kas', '1') !== '0';

        $kas = $sertakanKas
            ? $kegiatan->cashFlows()->orderBy('tanggal')->orderBy('id')->get()
            : collect();

        $pdf = Pdf::loadView('pdf.kegiatan', [
            'kegiatan' => $kegiatan,
            'hasil' => $hasil,
            'breakdown' => (new KegiatanResource($kegiatan))->withTaksasi()->resolve()['breakdown'],
            'kas' => $kas,
            'ringkasanKas' => $kegiatan->ringkasanKas(),
            'perusahaan' => config('taksasi.perusahaan'),
            'dicetakPada' => Carbon::now(),
            'dicetakOleh' => $request->user()?->name ?? '-',
        ])->setPaper('a4', 'portrait');

        $nama = sprintf(
            'Taksasi-%s-%s.pdf',
            str_replace([' ', '/'], '-', $kegiatan->kode ?: (string) $kegiatan->id),
            Carbon::now()->format('Ymd-Hi'),
        );

        return $request->query('download', '1') === '0'
            ? $pdf->stream($nama)
            : $pdf->download($nama);
    }

    #[OA\Get(
        path: '/api/laporan/rekap-kegiatan/pdf',
        operationId: 'laporanRekapPdf',
        description: 'Mencetak tabel rekap seluruh kegiatan ke PDF (orientasi landscape) beserta baris TOTAL.',
        summary: 'Cetak PDF rekap kegiatan',
        security: [['bearerAuth' => []]],
        tags: ['Laporan'],
        parameters: [
            new OA\Parameter(name: 'dari', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sampai', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'download', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, enum: [0, 1])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Berkas PDF',
                content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary')),
            ),
        ],
    )]
    public function rekapPdf(Request $request): Response
    {
        $rows = $this->rekapRows($request);

        $pdf = Pdf::loadView('pdf.rekap', [
            'baris' => $rows['baris'],
            'total' => $rows['total'],
            'periode' => ['dari' => $request->query('dari'), 'sampai' => $request->query('sampai')],
            'perusahaan' => config('taksasi.perusahaan'),
            'dicetakPada' => Carbon::now(),
            'dicetakOleh' => $request->user()?->name ?? '-',
        ])->setPaper('a4', 'landscape');

        $nama = 'Rekap-Taksasi-'.Carbon::now()->format('Ymd-Hi').'.pdf';

        return $request->query('download', '1') === '0'
            ? $pdf->stream($nama)
            : $pdf->download($nama);
    }

    #[OA\Get(
        path: '/api/laporan/arus-kas/pdf',
        operationId: 'laporanArusKasPdf',
        description: 'Mencetak buku kas ke PDF: daftar transaksi berurutan tanggal dengan kolom debet/kredit dan saldo berjalan. Bisa dibatasi per kegiatan dan per periode.',
        summary: 'Cetak PDF buku kas',
        security: [['bearerAuth' => []]],
        tags: ['Laporan'],
        parameters: [
            new OA\Parameter(name: 'kegiatan_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'dari', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sampai', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'download', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, enum: [0, 1])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Berkas PDF',
                content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary')),
            ),
        ],
    )]
    public function arusKasPdf(Request $request): Response
    {
        $kegiatan = $request->filled('kegiatan_id')
            ? Kegiatan::find($request->integer('kegiatan_id'))
            : null;

        $kas = CashFlow::query()
            ->with('kegiatan:id,nama,kode')
            ->when($kegiatan !== null, fn ($q) => $q->where('kegiatan_id', $kegiatan->id))
            ->periode($request->query('dari'), $request->query('sampai'))
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('pdf.arus-kas', [
            'kas' => $kas,
            'kegiatan' => $kegiatan,
            'periode' => ['dari' => $request->query('dari'), 'sampai' => $request->query('sampai')],
            'perusahaan' => config('taksasi.perusahaan'),
            'dicetakPada' => Carbon::now(),
            'dicetakOleh' => $request->user()?->name ?? '-',
        ])->setPaper('a4', 'landscape');

        $nama = 'Buku-Kas-'.Carbon::now()->format('Ymd-Hi').'.pdf';

        return $request->query('download', '1') === '0'
            ? $pdf->stream($nama)
            : $pdf->download($nama);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /**
     * Tambahkan pasangan `<key>_formatted` untuk setiap nilai rupiah,
     * supaya klien tidak perlu memformat ulang.
     *
     * @param  array<string, int>  $values
     * @return array<string, int|string>
     */
    private function withFormatted(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $out[$key] = $value;
            $out[$key.'_formatted'] = Rupiah::format($value);
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function trenKas(int $bulan): array
    {
        $bulan = min(24, max(1, $bulan));
        $mulai = Carbon::now()->startOfMonth()->subMonths($bulan - 1);

        $rows = CashFlow::query()
            ->whereDate('tanggal', '>=', $mulai->toDateString())
            ->selectRaw("to_char(tanggal, 'YYYY-MM') AS periode, jenis, COALESCE(SUM(nominal), 0) AS total")
            ->groupBy('periode', 'jenis')
            ->toBase()
            ->get()
            ->groupBy('periode');

        $out = [];

        for ($i = 0; $i < $bulan; $i++) {
            $cursor = $mulai->copy()->addMonths($i);
            $key = $cursor->format('Y-m');
            $group = $rows->get($key);

            $masuk = (int) round((float) ($group?->firstWhere('jenis', JenisKas::Masuk->value)->total ?? 0));
            $keluar = (int) round((float) ($group?->firstWhere('jenis', JenisKas::Keluar->value)->total ?? 0));

            $out[] = [
                'periode' => $key,
                'label' => $cursor->translatedFormat('M Y'),
                'masuk' => $masuk,
                'keluar' => $keluar,
                'saldo' => $masuk - $keluar,
            ];
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function kasPerKategori(?string $dari, ?string $sampai): array
    {
        return CashFlow::query()
            ->periode($dari, $sampai)
            ->selectRaw('jenis, kategori, COALESCE(SUM(nominal), 0) AS total')
            ->groupBy('jenis', 'kategori')
            ->toBase()
            ->get()
            ->map(function ($row): array {
                $kategori = KategoriKas::from($row->kategori);
                $total = (int) round((float) $row->total);

                return [
                    'kategori' => $kategori->value,
                    'kategori_label' => $kategori->label(),
                    'jenis' => $row->jenis,
                    'total' => $total,
                    'total_formatted' => Rupiah::format($total),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** @return array{baris: array<int, array<string, mixed>>, total: array<string, mixed>} */
    private function rekapRows(Request $request): array
    {
        $kegiatan = Kegiatan::query()
            ->status($request->query('status'))
            ->when(filled($request->query('dari')), fn ($q) => $q->whereDate('created_at', '>=', $request->query('dari')))
            ->when(filled($request->query('sampai')), fn ($q) => $q->whereDate('created_at', '<=', $request->query('sampai')))
            ->orderBy('created_at')
            ->get();

        $baris = [];
        $total = array_fill_keys([
            'pagu', 'ppn', 'pph', 'netto', 'rencana_pelaksanaan', 'biaya_kewajiban',
            'pelaksanaan_real', 'biaya_administrasi', 'biaya_perusahaan',
            'profit_kotor', 'bagi_hasil_investor', 'profit_bersih',
            'hasil_bersih_per_owner', 'sisa_pembulatan',
        ], 0);

        foreach ($kegiatan as $i => $k) {
            $h = $k->hitung();

            $row = [
                'no' => $i + 1,
                'id' => $k->id,
                'kode' => $k->kode,
                'nama' => $k->nama,
                'status' => $k->status->value,
                'status_label' => $k->status->label(),
                'jml_owner' => $h->jml_owner,
                'rates' => [
                    'ppn' => $k->rate_ppn,
                    'pph' => $k->rate_pph,
                    'rencana' => $k->rate_rencana,
                    'kewajiban' => $k->rate_kewajiban,
                    'administrasi' => $k->rate_administrasi,
                    'perusahaan' => $k->rate_perusahaan,
                    'investor' => $k->rate_investor,
                ],
            ];

            foreach (array_keys($total) as $key) {
                $row[$key] = $h->{$key};
                $row[$key.'_formatted'] = Rupiah::format($h->{$key});
                $total[$key] += $h->{$key};
            }

            $row['is_rugi'] = $h->is_rugi;
            $baris[] = $row;
        }

        $totalFormatted = ['jumlah_kegiatan' => $kegiatan->count()];

        foreach ($total as $key => $value) {
            $totalFormatted[$key] = $value;
            $totalFormatted[$key.'_formatted'] = Rupiah::format($value);
        }

        return ['baris' => $baris, 'total' => $totalFormatted];
    }
}
