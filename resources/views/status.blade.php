{{--
    Halaman akar server.

    Menggantikan halaman sambutan bawaan Laravel. Alasannya bukan sekadar
    tampilan: halaman bawaan itu memasang nama dan versi kerangka kerja,
    tautan ke dokumentasinya, serta gaya dari CDN luar. Di server API yang
    boleh dibuka siapa saja, itu memberi tahu penyerang persis teknologi apa
    yang dipakai -- dan tetap tampil rusak kalau servernya tanpa akses
    internet.

    Halaman ini berdiri sendiri: tidak ada berkas luar, tidak ada versi yang
    disebut, dan pemeriksaan kesehatannya benar-benar menembak /api/health
    sehingga bisa dipakai memastikan servernya hidup dari peramban.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }} — Server Live</title>

    {{-- SVG sebaris; tidak ada permintaan tambahan ke server. --}}
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='42' fill='%2334d399'/%3E%3C/svg%3E">

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --latar: #0b1020;
            --latar-kartu: rgba(255, 255, 255, 0.04);
            --garis: rgba(255, 255, 255, 0.09);
            --teks: #e8ecf6;
            --teks-redup: #8b96b0;
            --hidup: #34d399;
            --aksen: #6366f1;
        }

        /* Yang memilih tema terang tetap mendapat halaman yang terbaca,
           bukan teks putih di atas putih. */
        @media (prefers-color-scheme: light) {
            :root {
                --latar: #f5f7fb;
                --latar-kartu: rgba(15, 23, 42, 0.03);
                --garis: rgba(15, 23, 42, 0.1);
                --teks: #0f172a;
                --teks-redup: #5b6478;
                --hidup: #059669;
                --aksen: #4f46e5;
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

        /* Dua sorotan lembut, dibuat dengan gradien supaya tidak perlu gambar. */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(60ch 40ch at 15% -10%, color-mix(in srgb, var(--aksen) 22%, transparent), transparent 70%),
                radial-gradient(50ch 35ch at 90% 110%, color-mix(in srgb, var(--hidup) 16%, transparent), transparent 70%);
        }

        main {
            position: relative;
            width: 100%;
            max-width: 520px;
            text-align: center;
            animation: masuk 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes masuk {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: none; }
        }

        .nama {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--teks-redup);
            margin: 0 0 20px;
        }

        .titik-besar {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--hidup);
            display: inline-block;
            vertical-align: middle;
            margin-right: 14px;
            box-shadow: 0 0 0 0 color-mix(in srgb, var(--hidup) 60%, transparent);
            animation: denyut 2.4s ease-out infinite;
        }

        @keyframes denyut {
            0%   { box-shadow: 0 0 0 0 color-mix(in srgb, var(--hidup) 55%, transparent); }
            70%  { box-shadow: 0 0 0 18px transparent; }
            100% { box-shadow: 0 0 0 0 transparent; }
        }

        h1 {
            display: inline-block;
            margin: 0;
            font-size: clamp(1.5rem, 7vw, 2.6rem);
            font-weight: 700;
            letter-spacing: 0.06em;
            line-height: 1.1;
        }

        .keterangan {
            margin: 18px auto 0;
            max-width: 40ch;
            font-size: 0.9rem;
            line-height: 1.6;
            color: var(--teks-redup);
        }

        .panel {
            margin-top: 34px;
            border: 1px solid var(--garis);
            border-radius: 14px;
            background: var(--latar-kartu);
            overflow: hidden;
            text-align: left;
        }

        .baris {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 18px;
            font-size: 0.82rem;
        }

        .baris + .baris { border-top: 1px solid var(--garis); }

        .baris dt {
            margin: 0;
            color: var(--teks-redup);
            flex: 1;
        }

        .baris dd {
            margin: 0;
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo,
                         Consolas, "Liberation Mono", monospace;
            font-variant-numeric: tabular-nums;
            /* Alamat panjang dipotong di sini, bukan melebarkan panel dan
               membuat seluruh halaman bisa digeser ke samping. */
            overflow-wrap: anywhere;
            text-align: right;
        }

        dl { margin: 0; }

        .titik-kecil {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--teks-redup);
            flex: none;
        }

        .titik-kecil[data-keadaan="ok"]    { background: var(--hidup); }
        .titik-kecil[data-keadaan="gagal"] { background: #f87171; }

        footer {
            margin-top: 26px;
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--teks-redup);
        }

        @media (prefers-reduced-motion: reduce) {
            main { animation: none; }
            .titik-besar { animation: none; }
        }
    </style>
</head>
<body>
    <main>
        <p class="nama">{{ config('app.name') }}</p>

        <div>
            <span class="titik-besar" aria-hidden="true"></span>
            <h1>SERVER IS LIVE</h1>
        </div>

        <p class="keterangan">
            Ini alamat API, bukan halaman aplikasi. Buka lewat aplikasi
            Transaksi Pekerjaan di perangkat Anda.
        </p>

        <dl class="panel">
            <div class="baris">
                <span class="titik-kecil" data-keadaan="ok" aria-hidden="true"></span>
                <dt>Layanan</dt>
                <dd>Berjalan</dd>
            </div>
            <div class="baris">
                <span class="titik-kecil" id="titik-health" aria-hidden="true"></span>
                <dt>Pemeriksaan kesehatan</dt>
                <dd id="hasil-health">Memeriksa…</dd>
            </div>
            <div class="baris">
                <span class="titik-kecil" aria-hidden="true"></span>
                <dt>Waktu server</dt>
                <dd>{{ now()->timezone(config('app.timezone'))->format('d M Y H:i') }}</dd>
            </div>
        </dl>

        <footer>Akses tanpa izin tercatat</footer>
    </main>

    <script>
        // Pemeriksaan sungguhan, bukan hiasan. Alamatnya dibangun di sisi
        // server sehingga tetap benar saat dipasang di bawah subfolder.
        (function () {
            var alamat = @json(url('/api/health'));
            var titik = document.getElementById('titik-health');
            var hasil = document.getElementById('hasil-health');
            var mulai = performance.now();

            fetch(alamat, { headers: { Accept: 'application/json' }, cache: 'no-store' })
                .then(function (r) {
                    if (!r.ok) throw new Error(r.status);

                    return r.json();
                })
                .then(function (body) {
                    if (body && body.success === false) throw new Error('gagal');

                    titik.dataset.keadaan = 'ok';
                    hasil.textContent = Math.round(performance.now() - mulai) + ' ms';
                })
                .catch(function () {
                    titik.dataset.keadaan = 'gagal';
                    hasil.textContent = 'Tidak menjawab';
                });
        })();
    </script>
</body>
</html>
