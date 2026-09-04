{{--
    Tata letak halaman galat.

    Halaman bawaan Laravel menyebut nama kerangka kerjanya dan, saat
    APP_DEBUG hidup, menampilkan jejak lengkap beserta jalur berkas server.
    Halaman ini tidak menyebut apa pun soal teknologi maupun jalur -- hanya
    kodenya dan apa yang bisa dilakukan pembaca.

    Berdiri sendiri tanpa berkas luar, jadi tetap tampil benar meski server
    tidak punya akses internet dan meski yang gagal justru pelayan berkas
    statisnya.

    Permintaan ke /api/* TIDAK sampai ke sini: exception handler membalasnya
    dengan envelope JSON. Halaman ini hanya untuk orang yang membuka alamat
    servernya lewat peramban.
--}}
@php
    $kode = isset($exception) && method_exists($exception, 'getStatusCode')
        ? $exception->getStatusCode()
        : ($kode ?? 500);

    // Judul dan saran per kode. Yang tidak terdaftar memakai cadangan sesuai
    // golongannya, bukan pesan kosong.
    $salinan = [
        400 => ['Permintaan Tidak Dipahami', 'Alamat atau data yang dikirim tidak sesuai bentuk yang diharapkan.'],
        401 => ['Perlu Masuk Dulu', 'Halaman ini butuh sesi yang masih berlaku. Silakan masuk kembali dari aplikasi.'],
        403 => ['Tidak Punya Akses', 'Akun Anda tidak diizinkan membuka bagian ini. Hubungi superadmin bila memang perlu.'],
        404 => ['Halaman Tidak Ada', 'Alamat yang Anda buka tidak terdaftar di server ini. Periksa kembali tautannya.'],
        405 => ['Cara Akses Salah', 'Alamat ini ada, tetapi tidak menerima jenis permintaan tersebut.'],
        413 => ['Berkas Terlalu Besar', 'Ukuran maksimal 8 MB. Perkecil berkasnya lalu coba lagi.'],
        419 => ['Sesi Kedaluwarsa', 'Halaman terlalu lama terbuka. Muat ulang lalu ulangi.'],
        429 => ['Terlalu Banyak Permintaan', 'Anda mencoba terlalu sering. Tunggu sebentar sebelum mencoba lagi.'],
        500 => ['Ada Yang Salah Di Server', 'Kesalahan ini sudah dicatat. Coba lagi beberapa saat lagi.'],
        503 => ['Sedang Perawatan', 'Server sementara tidak melayani permintaan. Sebentar lagi kembali normal.'],
    ];

    [$judul, $pesan] = $salinan[$kode] ?? (
        $kode >= 500
            ? ['Ada Yang Salah Di Server', 'Kesalahan ini sudah dicatat. Coba lagi beberapa saat lagi.']
            : ['Permintaan Tidak Bisa Dilayani', 'Periksa kembali alamat yang Anda buka.']
    );

    $merah = $kode >= 500;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $kode }} — {{ $judul }}</title>

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='42' fill='%23f87171'/%3E%3C/svg%3E">

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --latar: #0b1020;
            --latar-kartu: rgba(255, 255, 255, 0.04);
            --garis: rgba(255, 255, 255, 0.09);
            --teks: #e8ecf6;
            --teks-redup: #8b96b0;
            --tanda: #fbbf24;
            --tanda-berat: #f87171;
        }

        @media (prefers-color-scheme: light) {
            :root {
                --latar: #f5f7fb;
                --latar-kartu: rgba(15, 23, 42, 0.03);
                --garis: rgba(15, 23, 42, 0.1);
                --teks: #0f172a;
                --teks-redup: #5b6478;
                --tanda: #b45309;
                --tanda-berat: #b91c1c;
            }
        }

        html, body { height: 100%; }

        body {
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background: var(--latar);
            color: var(--teks);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI",
                         Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background: radial-gradient(
                60ch 40ch at 50% -15%,
                color-mix(in srgb, var(--warna-tanda) 20%, transparent),
                transparent 70%
            );
        }

        main {
            position: relative;
            width: 100%;
            max-width: 460px;
            text-align: center;
            animation: masuk 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes masuk {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: none; }
        }

        .kode {
            margin: 0;
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo,
                         Consolas, "Liberation Mono", monospace;
            font-size: clamp(3.5rem, 18vw, 6rem);
            font-weight: 700;
            line-height: 1;
            letter-spacing: -0.03em;
            color: var(--warna-tanda);
        }

        h1 {
            margin: 14px 0 0;
            font-size: clamp(1.05rem, 4.5vw, 1.4rem);
            font-weight: 650;
            letter-spacing: 0.01em;
        }

        p {
            margin: 14px auto 0;
            max-width: 38ch;
            font-size: 0.9rem;
            line-height: 1.65;
            color: var(--teks-redup);
        }

        .panel {
            margin-top: 30px;
            padding: 14px 18px;
            border: 1px solid var(--garis);
            border-radius: 12px;
            background: var(--latar-kartu);
            font-size: 0.78rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--teks-redup);
        }

        @media (prefers-reduced-motion: reduce) {
            main { animation: none; }
        }
    </style>
</head>
<body style="--warna-tanda: {{ $merah ? 'var(--tanda-berat)' : 'var(--tanda)' }}">
    <main>
        <p class="kode">{{ $kode }}</p>
        <h1>{{ $judul }}</h1>
        <p>{{ $pesan }}</p>

        <div class="panel">{{ config('app.name') }}</div>
    </main>
</body>
</html>
