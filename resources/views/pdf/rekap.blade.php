@extends('pdf.layout')

@section('title', 'Rekap Transaksi Pekerjaan')

@section('content')

    <h1 class="judul">Rekapitulasi Transaksi Pekerjaan</h1>
    <p class="sub-judul">
        @if ($periode['dari'] || $periode['sampai'])
            Periode
            {{ $periode['dari'] ? \Illuminate\Support\Carbon::parse($periode['dari'])->translatedFormat('d M Y') : 'awal' }}
            s.d.
            {{ $periode['sampai'] ? \Illuminate\Support\Carbon::parse($periode['sampai'])->translatedFormat('d M Y') : 'sekarang' }}
        @else
            Seluruh periode
        @endif
        &middot; {{ $total['jumlah_kegiatan'] }} kegiatan
    </p>

    <table class="data" style="font-size:7.6px">
        <thead>
            <tr>
                <th style="width:3%" class="ctr">No</th>
                <th style="width:13%">Nama Kegiatan</th>
                <th style="width:7.5%" class="num">Pagu</th>
                <th style="width:6.5%" class="num">PPN</th>
                <th style="width:6%" class="num">PPh</th>
                <th style="width:7.5%" class="num">Netto</th>
                <th style="width:7.5%" class="num">Rencana</th>
                <th style="width:7%" class="num">Kewajiban</th>
                <th style="width:7.5%" class="num">Pelaks. Real</th>
                <th style="width:6%" class="num">Adm.</th>
                <th style="width:6%" class="num">B. Perush.</th>
                <th style="width:7.5%" class="num">Profit Kotor</th>
                <th style="width:7%" class="num">Investor</th>
                <th style="width:7%" class="num">Profit Bersih</th>
                <th style="width:7%" class="num">/Owner</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $row)
                <tr>
                    <td class="ctr">{{ $row['no'] }}</td>
                    <td>
                        {{ $row['nama'] }}
                        @if ($row['kode'])
                            <br><span class="catatan">{{ $row['kode'] }}</span>
                        @endif
                    </td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['pagu'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['ppn'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['pph'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['netto'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['rencana_pelaksanaan'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['biaya_kewajiban'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['pelaksanaan_real'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['biaya_administrasi'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['biaya_perusahaan'], false) }}</td>
                    <td class="num {{ $row['is_rugi'] ? 'rugi' : '' }}">{{ \App\Support\Rupiah::format($row['profit_kotor'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['bagi_hasil_investor'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['profit_bersih'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($row['hasil_bersih_per_owner'], false) }}</td>
                </tr>
            @empty
                <tr><td colspan="15" class="ctr">Belum ada data kegiatan pada periode ini.</td></tr>
            @endforelse

            @if (! empty($baris))
                <tr class="total">
                    <td colspan="2">TOTAL</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['pagu'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['ppn'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['pph'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['netto'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['rencana_pelaksanaan'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['biaya_kewajiban'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['pelaksanaan_real'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['biaya_administrasi'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['biaya_perusahaan'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['profit_kotor'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['bagi_hasil_investor'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['profit_bersih'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($total['hasil_bersih_per_owner'], false) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="catatan">
        Kolom &ldquo;/Owner&rdquo; adalah jatah SATU orang owner (Profit Bersih dibagi jumlah owner
        per kegiatan), sehingga penjumlahan kolom tersebut bukan total yang dibagikan.
        Nilai negatif pada Profit Kotor menandakan biaya pelaksanaan real melampaui sisa netto.
        Semua nilai dibulatkan ke rupiah.
        @if (($total['sisa_pembulatan'] ?? 0) !== 0)
            Akumulasi sisa pembulatan {{ \App\Support\Rupiah::format($total['sisa_pembulatan']) }}
            masuk kas perusahaan.
        @endif
    </p>

    <table class="ttd">
        <tr>
            <td style="width:33%">
                Dibuat oleh,
                <div class="ttd-garis">{{ $dicetakOleh }}</div>
            </td>
            <td style="width:34%"></td>
            <td style="width:33%">
                {{ $dicetakPada->translatedFormat('d F Y') }}<br>
                Disetujui oleh,
                <div class="ttd-garis">Owner / Direktur</div>
            </td>
        </tr>
    </table>

@endsection
