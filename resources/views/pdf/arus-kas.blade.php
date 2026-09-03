@extends('pdf.layout')

@section('title', 'Buku Kas')

@section('content')

    @php
        $totalMasuk = 0;
        $totalKeluar = 0;
        $saldo = 0;
    @endphp

    <h1 class="judul">Buku Kas</h1>
    <p class="sub-judul">
        @if ($kegiatan)
            {{ $kegiatan->nama }}@if ($kegiatan->kode) &middot; {{ $kegiatan->kode }} @endif
        @else
            Semua Kegiatan
        @endif
        &middot;
        @if ($periode['dari'] || $periode['sampai'])
            {{ $periode['dari'] ? \Illuminate\Support\Carbon::parse($periode['dari'])->translatedFormat('d M Y') : 'awal' }}
            s.d.
            {{ $periode['sampai'] ? \Illuminate\Support\Carbon::parse($periode['sampai'])->translatedFormat('d M Y') : 'sekarang' }}
        @else
            seluruh periode
        @endif
        &middot; {{ $kas->count() }} transaksi
    </p>

    <table class="data">
        <thead>
            <tr>
                <th style="width:3.5%" class="ctr">No</th>
                <th style="width:8%" class="ctr">Tanggal</th>
                @unless ($kegiatan)
                    <th style="width:14%">Kegiatan</th>
                @endunless
                <th style="width:13%">Kategori</th>
                <th style="width:{{ $kegiatan ? '30' : '20' }}%">Uraian</th>
                <th style="width:7%" class="ctr">Metode</th>
                <th style="width:9%" class="ctr">No. Bukti</th>
                <th style="width:12%" class="num">Masuk</th>
                <th style="width:12%" class="num">Keluar</th>
                <th style="width:12%" class="num">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($kas as $i => $t)
                @php
                    $isMasuk = $t->jenis === \App\Enums\JenisKas::Masuk;
                    $nominal = (int) $t->nominal;
                    $isMasuk ? $totalMasuk += $nominal : $totalKeluar += $nominal;
                    $saldo += $isMasuk ? $nominal : -$nominal;
                @endphp
                <tr>
                    <td class="ctr">{{ $i + 1 }}</td>
                    <td class="ctr">{{ $t->tanggal->translatedFormat('d/m/Y') }}</td>
                    @unless ($kegiatan)
                        <td>{{ $t->kegiatan?->nama ?? '-' }}</td>
                    @endunless
                    <td>{{ $t->kategori->label() }}</td>
                    <td>{{ $t->uraian }}</td>
                    <td class="ctr">{{ ucfirst($t->metode) }}</td>
                    <td class="ctr">{{ $t->no_bukti ?: '-' }}</td>
                    <td class="num">{{ $isMasuk ? \App\Support\Rupiah::format($nominal, false) : '' }}</td>
                    <td class="num">{{ $isMasuk ? '' : \App\Support\Rupiah::format($nominal, false) }}</td>
                    <td class="num {{ $saldo < 0 ? 'rugi' : '' }}">{{ \App\Support\Rupiah::format($saldo, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $kegiatan ? '9' : '10' }}" class="ctr">Belum ada transaksi pada periode ini.</td></tr>
            @endforelse

            @if ($kas->isNotEmpty())
                <tr class="total">
                    <td colspan="{{ $kegiatan ? '6' : '7' }}">TOTAL</td>
                    <td class="num">{{ \App\Support\Rupiah::format($totalMasuk, false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($totalKeluar, false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($saldo, false) }}</td>
                </tr>
                <tr class="hasil">
                    <td colspan="{{ $kegiatan ? '8' : '9' }}">SALDO AKHIR</td>
                    <td class="num {{ $saldo < 0 ? 'rugi' : '' }}">{{ \App\Support\Rupiah::format($saldo) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="catatan">
        Kolom Saldo adalah saldo berjalan yang dihitung dari urutan tanggal transaksi dalam
        periode ini, belum termasuk saldo awal sebelum periode.
        Semua nilai dibulatkan ke rupiah.
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
                Diperiksa oleh,
                <div class="ttd-garis">Bendahara / Owner</div>
            </td>
        </tr>
    </table>

@endsection
