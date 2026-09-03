{{-- Layout dasar seluruh PDF. DomPDF hanya mendukung CSS 2.1: pakai table
     untuk layout, jangan flexbox/grid. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Laporan')</title>
    <style>
        @page { margin: 22mm 14mm 20mm 14mm; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5px;
            color: #1a1d23;
            margin: 0;
            line-height: 1.45;
        }

        /* ---------- Kop surat ---------- */
        .kop { width: 100%; border-bottom: 2px solid #1E3A5F; padding-bottom: 8px; margin-bottom: 14px; }
        .kop td { vertical-align: top; }
        .kop .nama-pt { font-size: 15px; font-weight: bold; color: #1E3A5F; letter-spacing: .3px; }
        .kop .alamat { font-size: 8.5px; color: #4b5563; margin-top: 2px; }
        .kop .doc-meta { text-align: right; font-size: 8.5px; color: #4b5563; }

        h1.judul {
            font-size: 13px;
            text-align: center;
            margin: 0 0 2px;
            text-transform: uppercase;
            letter-spacing: .8px;
        }
        p.sub-judul { font-size: 9px; text-align: center; color: #6b7280; margin: 0 0 14px; }

        /* ---------- Tabel umum ---------- */
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.data th, table.data td { border: 0.6px solid #cbd5e1; padding: 4px 6px; }
        table.data thead th {
            background: #1E3A5F;
            color: #fff;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: .4px;
            font-weight: bold;
        }
        table.data tbody tr:nth-child(even) td { background: #f8fafc; }
        table.data .num { text-align: right; white-space: nowrap; }
        table.data .ctr { text-align: center; }

        /* ---------- Identitas (2 kolom) ---------- */
        table.ident { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.ident td { padding: 3px 6px; vertical-align: top; font-size: 9px; }
        table.ident td.label { width: 20%; color: #6b7280; }
        table.ident td.sep { width: 2%; color: #6b7280; }

        /* ---------- Baris hasil / total ---------- */
        tr.total td { background: #1E3A5F !important; color: #fff; font-weight: bold; }
        tr.hasil td { background: #E9EFF7 !important; font-weight: bold; }
        tr.dasar td { background: #e2e8f0 !important; font-weight: bold; }
        .rugi { color: #b91c1c; }

        .catatan { font-size: 8px; color: #6b7280; font-style: italic; }

        /* ---------- Tanda tangan ---------- */
        table.ttd { width: 100%; margin-top: 22px; }
        table.ttd td { text-align: center; font-size: 9px; vertical-align: bottom; padding: 0 10px; }
        .ttd-garis { margin-top: 52px; border-top: 0.6px solid #1a1d23; padding-top: 3px; }

        /* ---------- Footer ---------- */
        .footer {
            position: fixed;
            bottom: -14mm; left: 0; right: 0;
            font-size: 7.5px;
            color: #9ca3af;
            border-top: 0.5px solid #e5e7eb;
            padding-top: 3px;
        }
        .footer td { padding: 0; }
    </style>
</head>
<body>

<table class="kop">
    <tr>
        <td>
            <div class="nama-pt">{{ $perusahaan['nama'] }}</div>
            <div class="alamat">
                {{ $perusahaan['alamat'] }}<br>
                Telp: {{ $perusahaan['telepon'] }} &nbsp;&middot;&nbsp; Email: {{ $perusahaan['email'] }}
            </div>
        </td>
        <td class="doc-meta">
            Dicetak: {{ $dicetakPada->translatedFormat('d F Y, H:i') }}<br>
            Oleh: {{ $dicetakOleh }}
        </td>
    </tr>
</table>

@yield('content')

<table class="footer">
    <tr>
        <td>{{ $perusahaan['nama'] }} &middot; @yield('title', 'Laporan')</td>
        <td style="text-align:right">Dokumen dibuat otomatis oleh sistem</td>
    </tr>
</table>

</body>
</html>
