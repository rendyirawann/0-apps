#!/usr/bin/env bash
#
# Deploy Transaksi Pekerjaan API.
#
# Dijalankan DI SERVER:
#   sudo -u www-data /var/www/o-api/repo/deploy/deploy.sh
#
# Memakai tata letak rilis bersimbol tautan, sehingga pergantian versi
# terjadi dalam satu operasi dan bisa dikembalikan seketika:
#
#   /var/www/o-api/
#   ├── repo/            klon git (bare working copy)
#   ├── releases/2026…/  satu folder per rilis
#   ├── shared/          BERTAHAN antar rilis
#   │   ├── .env
#   │   └── storage/     termasuk foto struk pengguna
#   └── current -> releases/2026…
#
# storage/ WAJIB berada di shared/. Kalau ikut di dalam folder rilis, seluruh
# foto struk yang diunggah pengguna akan hilang pada deploy berikutnya.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/o-api}"
REPO_DIR="$APP_DIR/repo"
RELEASES_DIR="$APP_DIR/releases"
SHARED_DIR="$APP_DIR/shared"
CURRENT_LINK="$APP_DIR/current"
BRANCH="${BRANCH:-main}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
PHP="${PHP:-/usr/bin/php}"

RELEASE="$RELEASES_DIR/$(date +%Y%m%d%H%M%S)"

info() { printf '\n\033[1;34m==>\033[0m %s\n' "$1"; }
gagal() { printf '\n\033[1;31mGAGAL:\033[0m %s\n' "$1" >&2; exit 1; }

[ -d "$REPO_DIR/.git" ] || gagal "Repo belum ada di $REPO_DIR. Jalankan deploy/setup-server.sh dulu."
[ -f "$SHARED_DIR/.env" ] || gagal ".env belum ada di $SHARED_DIR. Salin dari .env.example lalu isi."

# ---------------------------------------------------------------- 1. ambil kode
info "Mengambil kode terbaru ($BRANCH)"
git -C "$REPO_DIR" fetch --prune origin
git -C "$REPO_DIR" reset --hard "origin/$BRANCH"

SHA_PENDEK="$(git -C "$REPO_DIR" rev-parse --short HEAD)"
info "Rilis $RELEASE (commit $SHA_PENDEK)"

mkdir -p "$RELEASES_DIR"
cp -a "$REPO_DIR" "$RELEASE"
rm -rf "$RELEASE/.git"

# ------------------------------------------------- 2. tautkan yang harus tetap
info "Menautkan .env dan storage dari shared/"
rm -rf "$RELEASE/storage"
ln -s "$SHARED_DIR/storage" "$RELEASE/storage"
ln -s "$SHARED_DIR/.env" "$RELEASE/.env"

# --------------------------------------------------------------- 3. dependensi
info "Memasang dependensi (tanpa dev)"
cd "$RELEASE"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ------------------------------------------------------------------ 4. migrasi
# Dijalankan SEBELUM current dialihkan, supaya skema sudah siap ketika kode
# baru mulai melayani permintaan. --force karena tidak ada prompt di CI.
info "Menjalankan migrasi"
$PHP artisan migrate --force

# --------------------------------------------------------------------- 5. cache
# config:cache WAJIB untuk Octane: tanpanya, setiap worker membaca .env pada
# setiap boot dan env() di luar config/ akan mengembalikan null.
info "Membangun cache konfigurasi, rute, dan view"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

info "Membangun dokumentasi Swagger"
$PHP artisan l5-swagger:generate

# Tautan storage publik. Perlu ada, tetapi TIDAK menyentuh disk lampiran yang
# memang privat.
$PHP artisan storage:link || true

# ------------------------------------------------------------------ 6. alihkan
info "Mengalihkan current -> rilis baru"
ln -sfn "$RELEASE" "${CURRENT_LINK}.tmp"
mv -Tf "${CURRENT_LINK}.tmp" "$CURRENT_LINK"

# ------------------------------------------------------------- 7. muat ulang
# reload, BUKAN restart: worker lama menyelesaikan permintaan yang sedang
# berjalan lalu diganti, jadi tidak ada permintaan yang terputus.
info "Memuat ulang Octane"
$PHP artisan octane:reload || sudo systemctl restart o-api-octane

# Pekerja antrean harus di-restart, bukan reload: prosesnya memegang kode lama
# sampai mati.
info "Me-restart pekerja antrean"
$PHP artisan queue:restart

# ------------------------------------------------------------ 8. bersih-bersih
info "Menyisakan $KEEP_RELEASES rilis terakhir"
cd "$RELEASES_DIR"
ls -1dt */ 2>/dev/null | tail -n "+$((KEEP_RELEASES + 1))" | xargs -r rm -rf

# ------------------------------------------------------------------ 9. periksa
info "Memeriksa kesehatan"
sleep 2

KODE="$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/api/health || echo 000)"

if [ "$KODE" != "200" ]; then
    gagal "Health check membalas $KODE. Rilis sudah aktif — periksa log:
  journalctl -u o-api-octane -n 50
  tail -n 50 $SHARED_DIR/storage/logs/laravel.log
Untuk mengembalikan: ln -sfn <rilis-sebelumnya> $CURRENT_LINK && php artisan octane:reload"
fi

printf '\n\033[1;32mSelesai.\033[0m Rilis %s (commit %s) aktif.\n' "$(basename "$RELEASE")" "$SHA_PENDEK"
