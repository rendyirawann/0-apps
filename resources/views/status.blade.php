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
            padding: clamp(16px, 5vw, 24px);
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

            /* min-width 0 WAJIB pada anak grid. Tanpanya nilainya `auto`,
               yang berarti kotak ini menolak menyempit di bawah lebar
               isinya -- dan di layar ponsel judul beserta panelnya terdorong
               keluar tepi sehingga terpotong. */
            min-width: 0;
            width: 100%;
            max-width: 520px;
            text-align: center;
            animation: masuk 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes masuk {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: none; }
        }

        /* Baris judul dibuat flex supaya titik dan tulisannya bisa turun
           baris bersama di layar sempit, bukan terkunci pada satu baris
           yang lebih lebar daripada layarnya. */
        .judul {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .titik-besar {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--hidup);
            flex: none;
            box-shadow: 0 0 0 0 color-mix(in srgb, var(--hidup) 60%, transparent);
            animation: denyut 2.4s ease-out infinite;
        }

        @keyframes denyut {
            0%   { box-shadow: 0 0 0 0 color-mix(in srgb, var(--hidup) 55%, transparent); }
            70%  { box-shadow: 0 0 0 18px transparent; }
            100% { box-shadow: 0 0 0 0 transparent; }
        }

        h1 {
            margin: 0;
            min-width: 0;

            /* Batas bawahnya diturunkan agar utuh di layar 320px -- kalimat
               yang terpotong lebih buruk daripada huruf yang mengecil. */
            font-size: clamp(1.15rem, 6vw, 2.6rem);
            font-weight: 700;
            letter-spacing: 0.05em;
            line-height: 1.15;
        }

        /* ---------------------------------------------------------------
           Dinosaurus piksel

           Digambar sendiri dari kotak-kotak SVG, bukan memakai gambar
           Chrome: asetnya milik orang lain, dan sebuah <rect> jauh lebih
           ringan daripada berkas gambar yang harus diunduh terpisah --
           halaman ini memang dibuat agar tidak butuh satu pun berkas luar.

           Warnanya memakai --teks, jadi ikut menyesuaikan tema terang dan
           gelap tanpa gambar kedua.
           --------------------------------------------------------------- */
        .dino {
            display: block;
            margin: 0 auto 20px;
            padding: 0;
            border: 0;
            background: none;
            line-height: 0;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .dino:focus-visible {
            outline: 2px solid var(--aksen);
            outline-offset: 8px;
            border-radius: 8px;
        }

        .dino svg {
            width: 88px;
            height: auto;
            display: block;
        }

        .dino .badan { fill: var(--teks); }

        /* Mata sewarna latar, bukan putih: di tema terang, putih di atas
           kepala gelap tampak benar, tetapi di tema gelap ia menyala. */
        .dino .mata { fill: var(--latar); }

        /* Dua pose kaki bergantian. steps(1) membuat pergantiannya patah
           seperti animasi piksel, bukan memudar seperti gambar biasa. */
        .kaki-1,
        .kaki-2 { animation: langkah 0.34s steps(1) infinite; }

        /* Setengah putaran di belakang pose pertama, sehingga pada setiap
           saat tepat satu pose yang terlihat -- tidak pernah dua, tidak
           pernah kosong. */
        .kaki-2 { animation-delay: 0.17s; }

        @keyframes langkah {
            0%, 49.9%  { opacity: 1; }
            50%, 100%  { opacity: 0; }
        }

        /* Garis tanah yang bergeser: inilah yang membuat dinonya terbaca
           sebagai berlari, bukan sekadar menggerakkan kaki di tempat. */
        .tanah {
            stroke: var(--teks-redup);
            stroke-width: 1;
            animation: geser 0.55s linear infinite;
        }

        @keyframes geser {
            to { stroke-dashoffset: -8; }
        }

        .dino.melompat svg {
            animation: lompat 0.55s cubic-bezier(0.25, 0.1, 0.3, 1);
        }

        /* Kakinya berhenti selagi melayang -- berlari di udara justru
           merusak ilusinya. */
        .dino.melompat .kaki-1,
        .dino.melompat .kaki-2 { animation-play-state: paused; }

        @keyframes lompat {
            0%, 100% { transform: translateY(0); }
            40%      { transform: translateY(-26px); }
        }

        .panel {
            margin-top: 30px;
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

            /* Gerak berulang tanpa henti memicu rasa mual pada sebagian
               orang. Dinonya tetap ada, hanya berhenti pada satu pose --
               pose kedua disembunyikan supaya kakinya tidak bertumpuk. */
            .kaki-1,
            .kaki-2,
            .tanah { animation: none; }

            .kaki-2 { opacity: 0; }

            .dino.melompat svg { animation: none; }
        }
    </style>
</head>
<body>
    <main>
        {{-- Tombol, bukan sekadar gambar: yang bisa diketuk harus bisa
             dicapai lewat papan ketik juga, dan <button> memberi itu tanpa
             satu baris pun kode tambahan. --}}
        <button type="button" class="dino" id="dino" aria-label="Dinosaurus piksel berlari. Ketuk untuk melompat.">
            <svg viewBox="0 0 30 30" shape-rendering="crispEdges" aria-hidden="true" focusable="false">
                <g class="badan">
                    {{-- Kepala, moncong, dan rahang bawah --}}
                    <rect x="18" y="1"  width="9" height="6"/>
                    <rect x="27" y="3"  width="2" height="3"/>
                    <rect x="18" y="7"  width="8" height="2"/>

                    {{-- Leher: cukup tinggi supaya kepalanya tidak menempel
                         ke badan dan siluetnya terbaca sebagai dinosaurus,
                         bukan sekadar gumpalan. --}}
                    <rect x="15" y="6"  width="4" height="7"/>

                    <rect x="7"  y="12" width="11" height="7"/>

                    {{-- Ekor, meruncing ke belakang lewat dua kotak yang
                         makin kecil dan makin tinggi. --}}
                    <rect x="3"  y="10" width="6" height="5"/>
                    <rect x="0"  y="8"  width="5" height="4"/>

                    {{-- Tangan kecil khas T-rex --}}
                    <rect x="16" y="14" width="4" height="2"/>
                </g>

                <rect class="mata" x="24" y="3" width="2" height="2"/>

                {{-- Dua pose kaki. Yang menapak selalu punya telapak; yang
                     terangkat hanya potongan pendek. --}}
                <g class="kaki-1">
                    <rect class="badan" x="8"  y="19" width="3" height="6"/>
                    <rect class="badan" x="8"  y="25" width="5" height="2"/>
                    <rect class="badan" x="14" y="19" width="3" height="4"/>
                </g>
                <g class="kaki-2">
                    <rect class="badan" x="14" y="19" width="3" height="6"/>
                    <rect class="badan" x="14" y="25" width="5" height="2"/>
                    <rect class="badan" x="8"  y="19" width="3" height="4"/>
                </g>

                <line class="tanah" x1="0" y1="28" x2="30" y2="28" stroke-dasharray="4 4"/>
            </svg>
        </button>

        <div class="judul">
            <span class="titik-besar" aria-hidden="true"></span>
            <h1>SERVER IS LIVE</h1>
        </div>

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
        // Dino melompat saat diketuk atau saat spasi ditekan.
        (function () {
            var dino = document.getElementById('dino');
            var DURASI = 550;

            function lompat() {
                // Lompatan baru diabaikan selagi yang lama berjalan. Tanpa
                // ini, mengetuk cepat berkali-kali membuat animasinya
                // dimulai ulang di tengah udara dan dinonya berkedut.
                if (dino.classList.contains('melompat')) return;

                dino.classList.add('melompat');
                setTimeout(function () {
                    dino.classList.remove('melompat');
                }, DURASI);
            }

            dino.addEventListener('click', lompat);

            document.addEventListener('keydown', function (e) {
                if (e.code !== 'Space' && e.key !== ' ') return;

                // Saat tombolnya sedang terfokus, peramban sudah mengubah
                // spasi menjadi klik. Menanganinya lagi di sini berarti dua
                // lompatan untuk satu tekanan.
                if (document.activeElement === dino) return;

                e.preventDefault();
                lompat();
            });
        })();

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
