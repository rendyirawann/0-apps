# Deploy ke server — rendy-irawan.my.id

Susunan yang dipakai: **nginx** menangani TLS dan berkas statis, **Laravel
Octane** menjadi server aplikasinya, **Redis** untuk cache/session/antrean,
**PostgreSQL** untuk data.

```
Internet ──► nginx :443 ──► Octane :8000 ──► PostgreSQL :5432
             (TLS, statis)   (4 worker)  └─► Redis :6379
```

nginx **tidak** menjalankan PHP di sini — tidak ada php-fpm sama sekali.
Octane sendiri sudah menjadi server HTTP; nginx hanya di depannya.

## Berkas di repo ini

| Berkas | Isi |
|---|---|
| `deploy/setup-server.sh` | Menyiapkan server dari nol. Dijalankan **sekali**. |
| `deploy/deploy.sh` | Deploy rilis baru. Dijalankan setiap kali. |
| `deploy/nginx/rendy-irawan.my.id.conf` | Konfigurasi nginx |
| `deploy/systemd/o-api-octane.service` | Proses Octane |
| `deploy/systemd/o-api-queue.service` | Pekerja antrean |
| `deploy/systemd/o-api-scheduler.service` | Penjadwal (opsional) |

---

## 1. DNS

Sudah benar pada panel Anda: `A` record untuk apex dan `www` menunjuk ke IP
server.

```
rendy-irawan.my.id       A   <IP-server>   3600
www.rendy-irawan.my.id   A   <IP-server>   3600
```

Konfigurasi nginx mengalihkan `www` ke apex, jadi **satu alamat kanonik**:
`https://rendy-irawan.my.id`. Ini penting — kalau kedua alamat sama-sama
melayani API, token Sanctum dan CORS punya dua asal berbeda.

Pastikan sudah menyebar sebelum menerbitkan sertifikat:

```bash
dig +short rendy-irawan.my.id
```

## 2. Sekali di awal

```bash
# di server, sebagai root
curl -sSL https://raw.githubusercontent.com/rendyirawann/0-apps/main/deploy/setup-server.sh -o setup.sh
DOMAIN=rendy-irawan.my.id EMAIL=rendy9008@gmail.com bash setup.sh
```

Skrip itu memasang PHP 8.3 + ekstensi, nginx, PostgreSQL, Redis, Composer,
certbot; membuat database beserta sandi acak; menyiapkan folder rilis; dan
memasang unit systemd serta konfigurasi nginx.

Sandi database dicetak di akhir — **catat**, dipakai di `.env`.

Lalu:

```bash
cd /var/www/o-api/repo
nano /var/www/o-api/shared/.env       # lihat daftar di bawah
composer install --no-dev --optimize-autoloader
php artisan key:generate --force

sudo -u www-data deploy/deploy.sh     # octane:install sudah termasuk di sini
php artisan db:seed --force           # akun superadmin
systemctl enable --now o-api-octane o-api-queue
```

Tidak ada `php artisan octane:install` manual di daftar ini — `deploy.sh`
menjalankannya pada setiap rilis. Alasannya ada di bagian
**Octane dipasang di server, bukan di komputer lokal**.

### Isi `.env` produksi

```dotenv
APP_NAME="Transaksi Pekerjaan API"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rendy-irawan.my.id
APP_KEY=                       # diisi php artisan key:generate

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432                   # 5432 di server; 5433 hanya di komputer lokal
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
L5_SWAGGER_CONST_HOST=https://rendy-irawan.my.id
L5_SWAGGER_GENERATE_ALWAYS=false
```

> `APP_DEBUG=false` bukan formalitas. Dengan `true`, respons galat memuat
> jejak lengkap beserta jalur berkas server.

> `L5_SWAGGER_GENERATE_ALWAYS=false` di produksi. Kalau `true`, spesifikasi
> dibangun ulang pada setiap permintaan ke dokumentasi — pemborosan yang
> terasa. `deploy.sh` sudah membangunnya sekali saat deploy.

## 3. Deploy berikutnya

```bash
sudo -u www-data /var/www/o-api/repo/deploy/deploy.sh
```

Satu perintah: ambil kode, pasang dependensi, migrasi, bangun cache,
alihkan symlink `current`, muat ulang Octane, restart antrean, sisakan 5
rilis terakhir, lalu periksa `/api/health`.

Mengembalikan versi:

```bash
ls -1t /var/www/o-api/releases | head -3
ln -sfn /var/www/o-api/releases/<sebelumnya> /var/www/o-api/current
php artisan octane:reload
```

---

## Hal-hal khusus proyek ini

### `client_max_body_size` wajib dinaikkan

Aplikasi menerima foto struk sampai **8 MB**
(`LampiranRequest::MAKS_KB = 8192`), sedangkan bawaan nginx hanya **1 MB**.
Tanpa `client_max_body_size 12M`, unggahan struk ditolak nginx dengan **413**
dan permintaannya **tidak pernah sampai ke Laravel** — jadi tidak ada pesan
galat yang berguna di aplikasi maupun di `laravel.log`. Batas PHP
(`upload_max_filesize`) juga dinaikkan oleh `setup-server.sh`; keduanya harus
naik, bukan salah satu.

### `storage/` harus di luar folder rilis

Foto struk pengguna tersimpan di `storage/app/lampiran`. `deploy.sh`
menautkannya ke `shared/storage`. Kalau `storage/` ikut di dalam folder
rilis, **seluruh bukti belanja hilang pada deploy berikutnya** — dan tidak ada
cara memulihkannya.

### Octane dipasang di server, bukan di komputer lokal

Ada dua bagian, dan keduanya berperilaku berbeda:

| Bagian | Di mana | Cara |
|---|---|---|
| Paket `laravel/octane` | **Di repo** (`composer.json`) | `composer install --no-dev` |
| Biner FrankenPHP | **Di server**, sistem-wide | `setup-server.sh` |
| `public/frankenphp-worker.php` | **Per rilis** | `deploy.sh` |

**Paketnya ada di repo, bukan dipasang di server.** Ini bukan pilihan gaya:
kalau `composer require laravel/octane` dijalankan di server di dalam folder
rilis, `composer.json` dan `composer.lock` yang berubah itu **ikut terhapus
pada deploy berikutnya** — karena `deploy.sh` mengambil ulang dari git. Octane
lalu hilang dan Octane-nya mati. Karena itu dependensinya dideklarasikan di
repo, dan server hanya memasang apa yang tidak bisa disimpan di git.

**Octane tidak dijalankan di komputer lokal.** Octane butuh FrankenPHP,
Swoole, atau RoadRunner — ketiganya Linux/macOS. Di Windows tidak ada yang
jalan. Pengembangan lokal tetap memakai `php artisan serve`, dan itu tidak
masalah: yang berbeda hanya cara aplikasi disajikan, bukan kodenya.

#### Mengapa binernya sistem-wide

Octane mencari binernya begitu:

```php
// vendor/laravel/octane/src/FrankenPhp/Concerns/FindsFrankenPhpBinary.php
(new ExecutableFinder())->find('frankenphp', null, [base_path()]);
```

Yaitu **PATH sistem, ditambah folder aplikasi**. Kalau dibiarkan diunduh oleh
`octane:install`, binernya (~150 MB) mendarat di `base_path()` — di dalam
folder rilis — dan ikut terhapus setiap deploy, jadi harus diunduh ulang
terus-menerus. Dengan memasangnya di `/usr/local/bin/frankenphp`, satu salinan
dipakai semua rilis dan `octane:install` menemukannya lewat PATH lalu tidak
mengunduh apa pun.

`setup-server.sh` memilih varian binernya dengan **logika yang sama seperti
Octane**: bila `getconf GNU_LIBC_VERSION` berhasil, sistemnya glibc
(Ubuntu/Debian) dan yang dipakai varian `-gnu`; bila gagal, sistemnya musl
(Alpine) dan yang dipakai build statis. Menyamakan logika ini penting — kalau
binernya beda dari yang dicari Octane, `octane:install` akan mengunduh lagi ke
folder rilis dan masalah "hilang setiap deploy" kembali.

#### Mengapa `octane:install` dijalankan setiap deploy

`octane:install` menulis `public/frankenphp-worker.php`, dan berkas itu ada di
dalam folder rilis. Rilis baru tidak akan memilikinya kalau perintah ini
dilewati, dan Octane gagal start. Perintahnya idempoten dan, dengan biner
sudah di PATH, tidak mengunduh apa pun — jadi aman dijalankan berulang.

Berkas `frankenphp`, `public/frankenphp-worker.php`, dan `Caddyfile`
dimasukkan `.gitignore`: semuanya dihasilkan di server, bukan disimpan di repo.

#### Setelah deploy wajib `octane:reload`

Octane menahan aplikasi di memori antar-permintaan. Mengganti berkas saja
tidak berpengaruh — worker lama masih memegang kode lama sampai dimuat ulang.
`deploy.sh` sudah memanggil `octane:reload`, yang membiarkan worker
menyelesaikan permintaan berjalan sebelum diganti, sehingga tidak ada
permintaan yang terputus.

Pekerja antrean lain ceritanya: prosesnya harus **di-restart**, bukan reload
(`queue:restart` menandai agar berhenti setelah pekerjaan berjalan selesai,
lalu systemd menghidupkannya kembali dengan kode baru).

#### Kode ini sudah diperiksa aman untuk Octane

Tiga hal yang biasanya membuat aplikasi rusak di Octane, dan keadaannya di
sini:

- **`env()` di luar `config/`** — tidak ada. Jadi `config:cache` tidak membuat
  nilainya menjadi null saat berjalan. (`grep -rn "env(" app/ routes/`)
- **State statis yang bertahan antar-permintaan** — tidak ada.
- **Cache di memori proses** — `Setting::all2()` memakai
  `Cache::rememberForever`, yaitu store Redis bersama, bukan memori worker.
  Akibatnya perubahan pengaturan langsung terlihat oleh **semua** worker, bukan
  hanya worker yang menanganinya.

`config:cache` sendiri **wajib** untuk Octane: tanpanya setiap worker membaca
`.env` pada setiap boot. `deploy.sh` membangunnya.

### Jumlah worker dibatasi 4

Satu worker Octane menahan satu koneksi PostgreSQL selama proses hidup.
Dengan `max_connections = 100` bawaan PostgreSQL, `--workers=auto` di server
ber-CPU banyak bisa menghabiskan kuota koneksi. Naikkan bertahap sambil
memantau `pg_stat_activity`.

### Cache dan antrean dipisah database Redis

`REDIS_DB=0` untuk antrean, `REDIS_CACHE_DB=1` untuk cache. Dengan begitu
`php artisan cache:clear` tidak ikut menghapus pekerjaan yang sedang menunggu,
dan `maxmemory-policy noeviction` melindungi antrean dari terbuang saat memori
penuh.

---

## Mengosongkan data kegiatan di server

Semua perintah dijalankan dari folder rilis aktif, sebagai `www-data`:

```bash
cd /var/www/o-api/current
```

### 1. Cadangkan dulu — ini tidak bisa dibatalkan

```bash
sudo -u postgres pg_dump o_taksasi | gzip > ~/o_taksasi-$(date +%Y%m%d-%H%M).sql.gz
tar czf ~/lampiran-$(date +%Y%m%d-%H%M).tar.gz -C /var/www/o-api/shared/storage/app lampiran
```

Cadangkan **keduanya**. Database menyimpan barisnya, disk menyimpan foto
struknya; memulihkan salah satu saja menghasilkan lampiran yang barisnya ada
tetapi berkasnya hilang, atau sebaliknya.

### 2. Bersihkan

```bash
sudo -u www-data php artisan kegiatan:bersihkan
```

Perintahnya menampilkan apa yang akan dihapus dan apa yang dipertahankan,
lalu meminta konfirmasi — di `APP_ENV=production` konfirmasinya selalu
ditanyakan. Tambahkan `--force` hanya bila dipanggil dari skrip.

| Dihapus | Dipertahankan |
|---|---|
| Kegiatan | Akun pengguna |
| Arus kas | Data master (satuan, toko, sumber dana) |
| Rincian bahan baku | Jejak aktivitas |
| Lampiran + **berkas fisiknya** | Pengaturan `.env` |

Pilihan tambahan:

```bash
# Ikut menghapus jejak aktivitas modul kegiatan/kas/bahan baku/lampiran.
# Jejak login tetap ada.
sudo -u www-data php artisan kegiatan:bersihkan --aktivitas

# Bersihkan lalu isi ulang 3 kegiatan contoh (untuk demo, bukan produksi).
sudo -u www-data php artisan kegiatan:bersihkan --contoh
```

Urutan id kegiatan kembali dari 1, jadi kegiatan pertama setelah dibersihkan
tidak bernomor lanjutan.

### Mulai benar-benar dari nol

Kalau akun pun ingin dibuat ulang:

```bash
sudo -u www-data php artisan migrate:fresh --force
sudo -u www-data php artisan db:seed --force
sudo -u www-data php artisan octane:reload
```

`migrate:fresh` menghapus **seluruh tabel**, termasuk akun — jadi password
superadmin kembali ke `password123` dan wajib segera diganti. Berkas lampiran
di `shared/storage` **tidak** ikut terhapus oleh perintah ini; bersihkan
manual bila memang dikehendaki:

```bash
sudo -u www-data find /var/www/o-api/shared/storage/app/lampiran -mindepth 1 -delete
```

`octane:reload` di akhir bukan formalitas: worker menahan aplikasi di memori,
termasuk cache pengaturan, sehingga tanpa reload sebagian data lama masih
terbaca sampai worker berganti sendiri.

### Isi awal tanpa data contoh

Untuk server yang datanya akan diisi sendiri dari aplikasi:

```bash
sudo -u www-data php artisan db:seed --class=MasterDataSeeder --force
sudo -u www-data php artisan db:seed --class=UserSeeder --force
```

`DatabaseSeeder` memanggil `KegiatanContohSeeder`, jadi `db:seed` tanpa
`--class` selalu ikut membawa 3 kegiatan contoh — berguna untuk demo, hanya
mengotori laporan di produksi.

> **Kenapa bukan migrasi?** Migrasi dijalankan otomatis oleh `deploy.sh` pada
> setiap rilis. Migrasi yang menghapus data akan mengosongkan kegiatan
> berulang kali setiap deploy — kehilangan data yang tidak bisa dipulihkan.
> Pembersihan data harus selalu berupa tindakan yang diminta secara sadar,
> karena itu bentuknya perintah artisan.

## Memeriksa setelah deploy

```bash
curl -s https://rendy-irawan.my.id/api/health          # {"success":true,...}
curl -s -o /dev/null -w '%{http_code}\n' \
     https://rendy-irawan.my.id/api/auth/me            # harus 401, bukan 500
```

`401` pada endpoint terlindungi adalah tanda benar. Kalau `500`, berarti
`bootstrap/app.php` tidak memuat pengalihan tamu — itu bug yang sudah
diperbaiki dan diuji, jadi periksa apakah `config:cache` sudah dibangun.

Uji unggahan struk 8 MB — inilah yang paling sering gagal karena batas nginx:

```bash
head -c 8000000 /dev/urandom > /tmp/uji.jpg
curl -s -o /dev/null -w '%{http_code}\n' \
  -X POST https://rendy-irawan.my.id/api/kegiatan/1/lampiran \
  -H "Authorization: Bearer <token>" -F "berkas=@/tmp/uji.jpg;type=image/jpeg"
# 201 atau 422 = batas ukuran lolos.  413 = client_max_body_size masih kecil.
```

## Log

```bash
journalctl -u o-api-octane -f
journalctl -u o-api-queue -f
tail -f /var/www/o-api/shared/storage/logs/laravel.log
tail -f /var/log/nginx/o-api.error.log
```

## Kalau Octane tidak mau jalan

Periksa berurutan — hampir semua kasus berhenti di salah satu dari empat ini:

```bash
# 1. Binernya ada dan bisa dijalankan?
which frankenphp && frankenphp version
#    Kosong -> ulangi bagian FrankenPHP di setup-server.sh.
#    Ada tapi "cannot execute" -> varian binernya salah (glibc vs musl).

# 2. Worker per rilis sudah ditulis?
ls -l /var/www/o-api/current/public/frankenphp-worker.php
#    Tidak ada -> deploy.sh belum menjalankan octane:install.

# 3. Portnya benar-benar diikat?
ss -lntp | grep 8000

# 4. Apa kata prosesnya?
journalctl -u o-api-octane -n 50 --no-pager
```

Gejala yang sering muncul:

| Gejala | Sebabnya biasanya |
|---|---|
| `502 Bad Gateway` dari nginx | Octane mati atau belum mengikat :8000. Lihat `journalctl`. |
| Perubahan kode tidak terasa | `octane:reload` belum jalan; worker masih memegang kode lama. |
| Nilai `.env` terbaca null | `config:cache` dibangun sebelum `.env` diisi. Jalankan ulang `php artisan config:cache`. |
| `413 Request Entity Too Large` saat unggah struk | `client_max_body_size` di nginx, bukan Octane. |
| Koneksi database habis | `--workers` terlalu tinggi; satu worker = satu koneksi PostgreSQL. |

Menjalankan Octane di latar depan untuk melihat galatnya langsung:

```bash
sudo systemctl stop o-api-octane
cd /var/www/o-api/current
sudo -u www-data php artisan octane:start --server=frankenphp     --host=127.0.0.1 --port=8000 --workers=1
```

## Kalau ingin memakai Swoole

FrankenPHP dipilih karena berupa biner tunggal tanpa PECL. Untuk Swoole:

```bash
apt-get install -y php8.3-dev
pecl install swoole            # jawab "no" untuk semua pertanyaan opsional
echo "extension=swoole.so" > /etc/php/8.3/cli/conf.d/99-swoole.ini
php artisan octane:install --server=swoole
```

Lalu ubah `--server=frankenphp` menjadi `--server=swoole` pada
`o-api-octane.service` dan `OCTANE_SERVER=swoole` di `.env`.
