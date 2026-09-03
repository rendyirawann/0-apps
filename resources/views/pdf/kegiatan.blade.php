@extends('pdf.layout')

@section('title', 'Laporan Transaksi Pekerjaan')

@section('content')

    <h1 class="judul">Laporan Transaksi Pekerjaan</h1>
    <p class="sub-judul">{{ $kegiatan->nama }}@if ($kegiatan->kode) &middot; {{ $kegiatan->kode }} @endif</p>

    {{-- ---------------- Identitas ---------------- --}}
    <table class="ident">
        <tr>
            <td class="label">Nama Kegiatan</td><td class="sep">:</td>
            <td><strong>{{ $kegiatan->nama }}</strong></td>
            <td class="label">Status</td><td class="sep">:</td>
            <td>{{ $kegiatan->status->label() }}</td>
        </tr>
        <tr>
            <td class="label">Kode</td><td class="sep">:</td>
            <td>{{ $kegiatan->kode ?: '-' }}</td>
            <td class="label">Sumber Dana</td><td class="sep">:</td>
            <td>{{ $kegiatan->sumber_dana ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Lokasi</td><td class="sep">:</td>
            <td>{{ $kegiatan->lokasi ?: '-' }}</td>
            <td class="label">Pelaksanaan</td><td class="sep">:</td>
            <td>
                {{ $kegiatan->tanggal_mulai?->translatedFormat('d M Y') ?: '-' }}
                s.d.
                {{ $kegiatan->tanggal_selesai?->translatedFormat('d M Y') ?: '-' }}
            </td>
        </tr>
        @if ($kegiatan->keterangan)
            <tr>
                <td class="label">Keterangan</td><td class="sep">:</td>
                <td colspan="4">{{ $kegiatan->keterangan }}</td>
            </tr>
        @endif
    </table>

    {{-- ---------------- Rincian transaksi ---------------- --}}
    <table class="data">
        <thead>
            <tr>
                <th style="width:4%" class="ctr">No</th>
                <th style="width:46%">Uraian</th>
                <th style="width:11%" class="ctr">Persen</th>
                <th style="width:20%" class="num">Nilai (Rp)</th>
                <th style="width:19%">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($breakdown as $i => $row)
                <tr class="{{ in_array($row['tipe'], ['hasil', 'dasar'], true) ? $row['tipe'] : '' }}">
                    <td class="ctr">{{ $i + 1 }}</td>
                    <td>{{ $row['label'] }}</td>
                    <td class="ctr">{{ $row['persen'] ?: '-' }}</td>
                    <td class="num {{ $row['nilai'] < 0 ? 'rugi' : '' }}">{{ $row['nilai_formatted'] }}</td>
                    <td class="catatan">{{ $row['catatan'] ?: '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($hasil->is_rugi)
        <p class="rugi" style="font-size:9px;font-weight:bold">
            Perhatian: profit kotor kegiatan ini bernilai negatif (rugi
            {{ \App\Support\Rupiah::format(abs($hasil->profit_kotor)) }}).
            Biaya pelaksanaan real sudah melampaui sisa netto setelah beban lain.
        </p>
    @endif

    <p class="catatan">
        Dasar perhitungan: PPN dan PPh dihitung dari Pagu; seluruh persentase lain dihitung dari
        Netto (Pagu &minus; PPN &minus; PPh) sebesar {{ \App\Support\Rupiah::format($hasil->netto) }}.
        Total persentase beban {{ \App\Support\Rupiah::persen($hasil->total_persen_beban) }},
        sisa untuk profit kotor {{ \App\Support\Rupiah::persen($hasil->sisa_persen) }}.
        Semua nilai dibulatkan ke rupiah.
        @if ($hasil->sisa_pembulatan !== 0)
            Sisa pembulatan pembagian owner sebesar
            {{ \App\Support\Rupiah::format($hasil->sisa_pembulatan) }} tidak dibagikan dan
            masuk kas perusahaan.
        @endif
    </p>

    {{-- ---------------- Pembagian hasil ---------------- --}}
    <table class="data">
        <thead>
            <tr>
                <th colspan="3">Pembagian Hasil</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width:50%">Profit Kotor</td>
                <td class="ctr" style="width:15%">{{ \App\Support\Rupiah::persen($hasil->persen_profit_kotor, 2) }} dari netto</td>
                <td class="num {{ $hasil->profit_kotor < 0 ? 'rugi' : '' }}">{{ \App\Support\Rupiah::format($hasil->profit_kotor) }}</td>
            </tr>
            <tr>
                <td>Bagi Hasil Investor</td>
                <td class="ctr">{{ \App\Support\Rupiah::persen($kegiatan->rate_investor) }}</td>
                <td class="num">{{ \App\Support\Rupiah::format($hasil->bagi_hasil_investor) }}</td>
            </tr>
            <tr class="hasil">
                <td>Profit Bersih</td>
                <td class="ctr">&mdash;</td>
                <td class="num">{{ \App\Support\Rupiah::format($hasil->profit_bersih) }}</td>
            </tr>
            @for ($i = 1; $i <= $hasil->jml_owner; $i++)
                <tr>
                    <td>Hasil Bersih &mdash; Owner {{ $i }}</td>
                    <td class="ctr">1/{{ $hasil->jml_owner }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($hasil->hasil_bersih_per_owner) }}</td>
                </tr>
            @endfor
            @if ($hasil->sisa_pembulatan !== 0)
                <tr>
                    <td>Sisa pembulatan (kas perusahaan)</td>
                    <td class="ctr">&mdash;</td>
                    <td class="num">{{ \App\Support\Rupiah::format($hasil->sisa_pembulatan) }}</td>
                </tr>
            @endif
            <tr class="total">
                <td>Total Dibagikan</td>
                <td class="ctr">{{ $hasil->jml_owner }} owner</td>
                <td class="num">{{ \App\Support\Rupiah::format($hasil->hasil_bersih_per_owner * $hasil->jml_owner) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ---------------- Arus kas ---------------- --}}
    @if ($kas->isNotEmpty())
        <h1 class="judul" style="margin-top:16px">Catatan Arus Kas</h1>
        <p class="sub-judul">{{ $kas->count() }} transaksi</p>

        <table class="data">
            <thead>
                <tr>
                    <th style="width:4%" class="ctr">No</th>
                    <th style="width:11%" class="ctr">Tanggal</th>
                    <th style="width:16%">Kategori</th>
                    <th style="width:29%">Uraian</th>
                    <th style="width:10%" class="ctr">Metode</th>
                    <th style="width:15%" class="num">Masuk</th>
                    <th style="width:15%" class="num">Keluar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kas as $i => $t)
                    <tr>
                        <td class="ctr">{{ $i + 1 }}</td>
                        <td class="ctr">{{ $t->tanggal->translatedFormat('d/m/Y') }}</td>
                        <td>{{ $t->kategori->label() }}</td>
                        <td>
                            {{ $t->uraian }}
                            @if ($t->no_bukti)
                                <span class="catatan">({{ $t->no_bukti }})</span>
                            @endif
                        </td>
                        <td class="ctr">{{ ucfirst($t->metode) }}</td>
                        <td class="num">{{ $t->jenis === \App\Enums\JenisKas::Masuk ? \App\Support\Rupiah::format($t->nominal, false) : '' }}</td>
                        <td class="num">{{ $t->jenis === \App\Enums\JenisKas::Keluar ? \App\Support\Rupiah::format($t->nominal, false) : '' }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="5">TOTAL</td>
                    <td class="num">{{ \App\Support\Rupiah::format($ringkasanKas['masuk'], false) }}</td>
                    <td class="num">{{ \App\Support\Rupiah::format($ringkasanKas['keluar'], false) }}</td>
                </tr>
                <tr class="hasil">
                    <td colspan="5">SALDO KAS</td>
                    <td class="num" colspan="2">{{ \App\Support\Rupiah::format($ringkasanKas['saldo']) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- ---------------- Tanda tangan ---------------- --}}
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
