# Deploy ke IP dengan subfolder

Untuk memasang API di alamat seperti `http://203.0.113.10/transaksi` — tanpa
domain, dan berbagi IP dengan aplikasi lain yang mungkin sudah ada di sana.

Untuk pemasangan di domain sendiri dengan TLS, pakai [DEPLOY.md](DEPLOY.md).
Susunan komponennya sama; yang berbeda hanya nginx dan tiga baris `.env`.

```
Klien ──► nginx :80 ──► /transaksi/*  ──► Octane :8000 ──► PostgreSQL
                        (prefiks dipotong)   (4 worker)  └─► Redis

                    ──► /*            ──► situs lain yang sudah ada
                                          (tidak tersentuh)
```

Ganti `203.0.113.10` dengan IP server dan `transaksi` dengan nama subfolder
yang diinginkan di seluruh dokumen ini.

---

## Subfolder ini tidak bisa terhapus — begini alasannya

Ini syaratnya, jadi ditaruh paling depan.

**`/transaksi` bukan direktori di disk.** Ia hanya pola alamat di konfigurasi
nginx. Tidak ada folder bernama `transaksi` yang dibuat di document root mana
pun, jadi tidak ada yang bisa dihapus — tidak oleh deploy, tidak oleh
`rm -rf` di web root, tidak oleh pembaruan aplikasi lain yang berbagi IP.

Aplikasinya sendiri hidup di `/var/www/o-api/`, terpisah:

```
/var/www/o-api/          <- aplikasi, DI LUAR web root situs mana pun
├── repo/
├── releases/20260904…/
├── shared/              <- .env dan storage/, bertahan antar rilis
└── current -> releases/20260904…

/var/www/html/           <- web root situs lain, TIDAK PERNAH DISENTUH
```

Selama deploy, hanya **satu** perintah yang menghapus sesuatu: pemangkasan
rilis lama di langkah 9 `deploy.sh`. Perintah itu diberi dua pagar:

1. Hanya folder yang namanya **persis 14 angka** (stempel waktu buatan skrip
   itu sendiri) yang boleh dihapus. Folder lain — cadangan manual, apa pun
   yang tidak sengaja tersimpan di sana — dilewati sambil dicetak namanya.
2. Rilis yang sedang ditunjuk `current` tidak pernah ikut terhapus.

Dan pemangkasan itu hanya berjalan di dalam `/var/www/o-api/releases/`,
tidak pernah di web root.

**Yang justru perlu dijaga adalah blok `location` di nginx.** Kalau blok itu
hilang dari konfigurasi, alamat `/transaksi` mati meskipun aplikasinya sehat.
Jadi:

- Simpan konfigurasinya di `/etc/nginx/sites-available/`, bukan hanya
  ditempel lewat panel yang bisa menimpa berkasnya.
- Kalau memakai panel (aaPanel, Plesk, cPanel) yang menulis ulang konfigurasi
  situs, letakkan blok `location` di berkas *include* milik panel tersebut,
  bukan di berkas situs yang bisa ditimpa.
- Setelah setiap perubahan pada konfigurasi situs lain, periksa ulang:

  ```bash
  curl -s -o /dev/null -w '%{http_code}\n' http://203.0.113.10/transaksi/api/health
  ```

Satu hal terakhir: **jangan pakai nama subfolder yang sudah dipakai direktori
nyata** di web root situs lain. Blok `location ^~` akan menang atas direktori
itu dan aplikasi lama kehilangan jalurnya. Kalau `/transaksi` sudah terpakai,
pilih nama lain.

---

## Yang berubah di kode

Rute Laravel **tidak** diberi prefiks. nginx sudah memotong `/transaksi`
sebelum meneruskan, jadi aplikasi tetap menerima `/api/health` apa adanya —
persis seperti saat dipasang di domain sendiri.

Yang perlu tahu soal subfolder hanyalah URL yang **dihasilkan** Laravel:
halaman Swagger memuat spesifikasi dan asetnya lewat `route()`, dan tanpa
prefiks alamat itu keluar dari subfolder. Halamannya lalu tampil kosong tanpa
pesan galat apa pun.

Itu ditangani [`app/Support/UrlSubfolder.php`](app/Support/UrlSubfolder.php),
dipanggil dari `AppServiceProvider::boot()`. Ia membaca `APP_URL`:

| `APP_URL` | Yang dilakukan |
|---|---|
| `http://203.0.113.10/transaksi` | `URL::forceRootUrl()` dengan prefiks |
| `https://rendy-irawan.my.id` | tidak melakukan apa pun |
| `http://127.0.0.1:8000` | tidak melakukan apa pun |

Jadi satu basis kode melayani kedua cara pasang, dan berpindah di antaranya
cukup dengan mengubah `APP_URL`. Perilakunya dikunci
[`tests/Feature/SubfolderUrlTest.php`](tests/Feature/SubfolderUrlTest.php).

---

## 1. Sekali di awal

```bash
# di server, sebagai root
curl -sSL https://raw.githubusercontent.com/rendyirawann/0-apps/main/deploy/setup-server.sh -o setup.sh
SUBFOLDER=transaksi bash setup.sh
```

`SUBFOLDER` itulah yang membedakannya dari pemasangan domain. Dengan variabel
tersebut skrip:

- **melewati certbot** — tidak ada sertifikat untuk sebuah IP;
- menulis `/etc/nginx/sites-available/o-api-subfolder.conf` dengan nama
  subfolder dan IP sudah terisi;
- **tidak mengaktifkannya** dan **tidak menghapus** `sites-enabled/default`.

Bagian terakhir itu disengaja. Berkas contohnya memakai `default_server`;
mengaktifkannya begitu saja di IP yang sudah melayani situs lain akan merebut
IP tersebut. Skrip berhenti di situ dan menyerahkan keputusannya ke Anda
(lihat bagian nginx di bawah).

IP dideteksi dari `hostname -I`. Kalau server punya beberapa antarmuka dan
yang terpilih salah, sebutkan sendiri:

```bash
SUBFOLDER=transaksi IP=203.0.113.10 bash setup.sh
```

Sandi database dicetak di akhir — catat, dipakai di `.env`.

### Isi `.env` produksi

Sama seperti DEPLOY.md kecuali tiga baris yang ditandai:

```dotenv
APP_NAME="Transaksi Pekerjaan API"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://203.0.113.10/transaksi     # <-- lengkap dengan subfolder
APP_KEY=                                  # diisi php artisan key:generate

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432                              # 5432 di server; 5433 hanya lokal
DB_DATABASE=o_taksasi
DB_USERNAME=o_taksasi
DB_PASSWORD=<dari setup-server.sh>

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

OCTANE_SERVER=frankenphp
L5_SWAGGER_CONST_HOST=http://203.0.113.10/transaksi   # <-- ikut subfolder
L5_SWAGGER_GENERATE_ALWAYS=false
SESSION_SECURE_COOKIE=false               # <-- tidak ada HTTPS di sini
```

> **`APP_URL` harus persis** — skema, IP, dan subfolder, tanpa garis miring di
> akhir. Nilai inilah yang dibaca `UrlSubfolder`. Salah satu huruf saja dan
> halaman Swagger memuat spesifikasi dari alamat yang tidak ada.

> **`L5_SWAGGER_CONST_HOST` harus ikut** karena nilainya masuk ke bagian
> `servers` di spesifikasi — alamat yang dituju tombol "Try it out". Kalau
> tidak diubah, tombol itu menembak `http://127.0.0.1:8000` dari browser
> pemakai, yang berarti komputer pemakai sendiri, bukan server.

> **`SESSION_SECURE_COOKIE=false`** karena bawaan Laravel menandai cookie sesi
> `Secure` di produksi. Di HTTP polos, cookie bertanda itu tidak pernah
> dikirim balik oleh browser, sehingga sesi Swagger UI tidak pernah nempel.
> Aplikasi Flutter tidak terpengaruh — ia memakai token Bearer, bukan cookie.

Lalu, sama seperti DEPLOY.md:

```bash
cd /var/www/o-api/repo
nano /var/www/o-api/shared/.env
composer install --no-dev --optimize-autoloader
php artisan key:generate --force

sudo -u www-data deploy/deploy.sh
php artisan db:seed --class=MasterDataSeeder --force
php artisan db:seed --class=UserSeeder --force
systemctl enable --now o-api-octane o-api-queue
```

> Dua seeder disebut satu per satu, bukan `db:seed` polos. `db:seed` tanpa
> `--class` ikut membuat tiga kegiatan contoh — berguna untuk demo, tetapi di
> server hanya mengotori laporan.

## 2. nginx

Berkas contohnya:
[`deploy/nginx/ip-subfolder.conf`](deploy/nginx/ip-subfolder.conf). Setelah
`setup-server.sh` berjalan, versi yang sudah terisi ada di
`/etc/nginx/sites-available/o-api-subfolder.conf`.

### a. IP ini belum dipakai apa pun

```bash
ln -s /etc/nginx/sites-available/o-api-subfolder.conf /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

### b. Sudah ada situs lain di IP ini

**Jangan membuat blok `server` kedua untuk IP yang sama.** nginx hanya memilih
satu sebagai `default_server`, dan situs yang kalah ikut mati.

Buka blok `server` milik situs yang sudah berjalan, lalu tempel bagian yang
diapit penanda `POTONGAN UNTUK SERVER YANG SUDAH ADA` dari
`o-api-subfolder.conf`. Blok `map` dan `upstream` di bagian atas berkas itu
juga harus ikut, tetapi diletakkan di luar `server` — misalnya di
`/etc/nginx/conf.d/o-api-upstream.conf`.

```bash
nginx -t && systemctl reload nginx
```

### Dua hal yang gampang terlewat

**`^~` pada `location ^~ /transaksi/` itu wajib.** Tanpanya, `location` regex
milik situs lama — misalnya `location ~* \.(css|js|png)$` — menang atas
prefiks ini dan mencoba melayani aset Swagger UI dari disk. Berkasnya tidak
ada di sana, jadi hasilnya 404 tanpa penjelasan. `^~` menghentikan pencarian
regex begitu prefiksnya cocok.

**Garis miring di akhir `proxy_pass` itulah yang memotong prefiks.**

```nginx
proxy_pass http://o_api_octane/;   # /transaksi/api/health -> /api/health
proxy_pass http://o_api_octane;    # /transaksi/api/health -> /transaksi/api/health
```

Tanpa garis miring itu, Laravel menerima `/transaksi/api/health` — alamat yang
tidak ada di tabel rutenya — dan setiap permintaan dijawab 404 "Endpoint tidak
ditemukan".

## 3. Deploy berikutnya

Sama persis dengan pemasangan domain:

```bash
sudo -u www-data /var/www/o-api/repo/deploy/deploy.sh
```

Tidak ada langkah tambahan untuk subfolder. `deploy.sh` menjalankan
`config:cache`, yang membekukan `APP_URL` beserta prefiksnya ke dalam cache
konfigurasi.

> Kalau `APP_URL` di `shared/.env` diubah tanpa deploy ulang, jalankan
> `php artisan config:cache && php artisan octane:reload`. Tanpa keduanya,
> Octane masih memakai nilai lama sampai prosesnya mati.

Health check di akhir `deploy.sh` menembak `127.0.0.1:8000/api/health`
langsung ke Octane, melewati nginx. Jadi hasil hijau di sana berarti
aplikasinya hidup — **belum tentu** subfoldernya tersambung. Itu diperiksa di
bagian berikut.

## 4. Alamat yang harus jalan

Jalankan setelah deploy pertama. Semuanya lewat nginx, bukan localhost.

```bash
IP=203.0.113.10
SUB=transaksi

# 1. Aplikasi hidup di balik subfolder
curl -s http://$IP/$SUB/api/health
# {"success":true,...}

# 2. Tanpa garis miring -> dialihkan, bukan 404
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' http://$IP/$SUB
# 301 http://203.0.113.10/transaksi/

# 3. Spesifikasi Swagger, dan servers-nya ikut berprefiks
curl -s http://$IP/$SUB/docs | head -c 400
# ..."servers":[{"url":"http://203.0.113.10/transaksi",...

# 4. Halaman Swagger UI
curl -s -o /dev/null -w '%{http_code}\n' http://$IP/$SUB/api/documentation
# 200

# 5. Aset Swagger UI -- inilah yang mati kalau `^~` lupa dipasang
curl -s -o /dev/null -w '%{http_code}\n' http://$IP/$SUB/docs/asset/swagger-ui.css
# 200

# 6. Health check bawaan Laravel
curl -s -o /dev/null -w '%{http_code}\n' http://$IP/$SUB/up
# 200

# 7. Login benar-benar bekerja
curl -s -X POST http://$IP/$SUB/api/auth/login \
     -H 'Content-Type: application/json' -H 'Accept: application/json' \
     -d '{"email":"superadmin@taksasi.test","password":"password123"}'
# {"success":true,"data":{"token":"..."}}

# 8. Endpoint terlindungi menjawab 401, bukan 500
curl -s -o /dev/null -w '%{http_code}\n' http://$IP/$SUB/api/auth/me
# 401

# 9. Batas unggahan struk: 12 MB di nginx, 8 MB di aplikasi
head -c 9000000 /dev/urandom > /tmp/besar.jpg
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://$IP/$SUB/api/lampiran \
     -F berkas=@/tmp/besar.jpg
# 401 atau 422 -> batas nginx lolos.  413 -> client_max_body_size masih kecil.
rm /tmp/besar.jpg

# 10. Situs lain di IP ini masih hidup
curl -s -o /dev/null -w '%{http_code}\n' http://$IP/
```

Nomor 3 dan 5 yang paling sering gagal, dan keduanya gagal diam-diam: halaman
Swagger tetap terbuka, hanya isinya kosong.

## 5. Aplikasi Flutter

APK-nya punya kolom **Alamat API** yang bisa diubah tanpa membangun ulang
(Pengaturan ▸ Alamat API, hanya untuk superadmin). Isi dengan:

```
http://203.0.113.10/transaksi
```

Tanpa `/api` di belakang — aplikasi menambahkannya sendiri. Kalau terlanjur
diketik, alamatnya dirapikan otomatis (`AppConfig.normalkan()` membuang garis
miring dan `/api` di akhir).

Tombol **Uji koneksi** di layar itu menembak `/api/health` melalui alamat yang
diketik, jadi bisa dipakai memastikan sebelum menyimpan.

HTTP polos sudah diizinkan di aplikasi Android — `network_security_config.xml`
membuka cleartext untuk semua host — jadi alamat IP tanpa HTTPS bisa dipakai
tanpa membangun ulang APK.

Untuk mengubah alamat **bawaannya**, yang terpakai sebelum ada yang disimpan:

```powershell
flutter build apk --release --dart-define=API_BASE_URL=http://203.0.113.10/transaksi
```

## 6. Tanpa TLS, ini yang diterima

Menaruh API di IP polos berarti **token dan sandi berjalan tanpa enkripsi**.
Siapa pun di jalur jaringan yang sama — Wi-Fi kantor, jaringan seluler, router
mana pun di antaranya — bisa membacanya dan memakai token itu untuk masuk
sebagai pengguna tersebut.

Untuk uji coba internal ini sepadan. Untuk data keuangan yang dipakai
sehari-hari, tidak.

Kalau nanti ada domain, pindahnya murah:

1. Arahkan `A` record ke IP server.
2. `certbot certonly --nginx -d domain.anda`
3. Ubah `.env`: `APP_URL` dan `L5_SWAGGER_CONST_HOST` ke `https://domain.anda`,
   hapus `SESSION_SECURE_COOKIE=false`.
4. `php artisan config:cache && php artisan octane:reload`

`UrlSubfolder` berhenti bekerja dengan sendirinya begitu `APP_URL` tidak lagi
punya subfolder — tidak ada kode yang perlu diubah. Blok `location` di nginx
boleh dibiarkan; alamat lama tetap jalan selama masa peralihan.

## 7. Mengosongkan data kegiatan

Sama dengan DEPLOY.md — perintahnya tidak dipengaruhi subfolder:

```bash
cd /var/www/o-api/current
php artisan kegiatan:bersihkan --aktivitas
```

Cadangkan dulu; ini tidak bisa dibatalkan. Rinciannya di bagian **Mengosongkan
data kegiatan di server** pada [DEPLOY.md](DEPLOY.md).

## 8. Kalau ada yang tidak beres

| Gejala | Sebab yang paling sering |
|---|---|
| Semua alamat 404 | `proxy_pass` tanpa garis miring di akhir |
| `/transaksi` 404 tapi `/transaksi/` jalan | blok `location = /transaksi` yang mengalihkan belum ada |
| Swagger terbuka tapi kosong | `APP_URL` belum ada subfoldernya, atau `config:cache` belum dijalankan ulang |
| Swagger tampil tanpa gaya | `^~` hilang; aset tertangkap `location` regex situs lain |
| "Try it out" menembak 127.0.0.1 | `L5_SWAGGER_CONST_HOST` belum diubah |
| Unggah struk 413 | `client_max_body_size` belum ada di dalam blok `location` |
| Situs lain ikut mati | ada blok `server` kedua untuk IP yang sama — gabungkan jadi satu |
| Perubahan `.env` tidak terasa | `php artisan config:cache && php artisan octane:reload` |

Log yang perlu dilihat:

```bash
journalctl -u o-api-octane -n 50
tail -n 50 /var/www/o-api/shared/storage/logs/laravel.log
tail -n 50 /var/log/nginx/o-api.error.log
```
