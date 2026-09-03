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
php artisan octane:install --server=frankenphp
sudo -u www-data deploy/deploy.sh
php artisan db:seed --force           # akun superadmin
systemctl enable --now o-api-octane o-api-queue
```

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

### Octane menahan aplikasi di memori

Konsekuensinya, setelah deploy **wajib** `octane:reload`; mengganti berkas
saja tidak berpengaruh karena worker lama masih memegang kode lama.
`deploy.sh` sudah melakukannya.

Kode ini sudah diperiksa aman untuk Octane:

- **Tidak ada `env()` di luar `config/`** — jadi `config:cache` tidak membuat
  nilai berubah menjadi null saat berjalan.
- **Tidak ada state statis** yang menyimpan data antar-permintaan.
- `Setting::all2()` memakai `Cache::rememberForever`, yaitu store Redis
  bersama — bukan memori proses — sehingga perubahan pengaturan langsung
  terlihat oleh semua worker, bukan hanya worker yang menanganinya.

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
