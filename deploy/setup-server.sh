#!/usr/bin/env bash
#
# Menyiapkan server dari nol untuk Transaksi Pekerjaan API.
# Diuji pada Ubuntu 24.04. Dijalankan SEKALI, sebagai root.
#
#   sudo bash setup-server.sh
#
# Setelah selesai, deploy berikutnya cukup: deploy/deploy.sh

set -euo pipefail

DOMAIN="${DOMAIN:-rendy-irawan.my.id}"
EMAIL="${EMAIL:-rendy9008@gmail.com}"
REPO="${REPO:-https://github.com/rendyirawann/0-apps.git}"
APP_DIR="${APP_DIR:-/var/www/o-api}"
DB_NAME="${DB_NAME:-o_taksasi}"
DB_USER="${DB_USER:-o_taksasi}"

info() { printf '\n\033[1;34m==>\033[0m %s\n' "$1"; }

[ "$(id -u)" -eq 0 ] || { echo "Jalankan sebagai root."; exit 1; }

# ------------------------------------------------------------------- 1. paket
info "Memasang paket dasar"
apt-get update
apt-get install -y software-properties-common curl git unzip ca-certificates

info "Memasang PHP 8.3 dan ekstensi"
add-apt-repository -y ppa:ondrej/php
apt-get update
# Ekstensi yang benar-benar dipakai aplikasi ini:
#   pgsql   -> PostgreSQL
#   mbstring, xml, bcmath, curl, zip -> Laravel + dompdf
#   gd      -> dompdf untuk gambar pada PDF
#   intl    -> pemformatan angka & tanggal Indonesia
apt-get install -y \
    php8.3-cli php8.3-common php8.3-pgsql php8.3-mbstring php8.3-xml \
    php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-redis

info "Memasang nginx, PostgreSQL, Redis"
apt-get install -y nginx postgresql redis-server

info "Memasang Composer"
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f /tmp/composer-setup.php

# ---------------------------------------------------------------- 2. batas PHP
# Foto struk sampai 8 MB. Bawaan PHP (upload_max_filesize 2M) menolaknya lebih
# dulu, jadi harus dinaikkan di sisi PHP juga — bukan hanya di nginx.
info "Menaikkan batas unggahan PHP"
cat > /etc/php/8.3/cli/conf.d/99-o-api.ini <<'INI'
upload_max_filesize = 12M
post_max_size = 14M
memory_limit = 512M
max_execution_time = 120
INI

# ------------------------------------------------------------------ 3. Redis
# Redis dipakai untuk cache, session, dan antrean.
#   maxmemory-policy: cache pengaturan boleh dibuang saat memori penuh,
#   TETAPI antrean tidak boleh. Karena itu keduanya dipisah per database
#   (lihat REDIS_CACHE_DB di .env) dan kebijakannya dibiarkan noeviction
#   agar pekerjaan antrean tidak pernah hilang diam-diam.
info "Mengatur Redis"
sed -i 's/^# *maxmemory .*/maxmemory 256mb/'                 /etc/redis/redis.conf
sed -i 's/^# *maxmemory-policy .*/maxmemory-policy noeviction/' /etc/redis/redis.conf
sed -i 's/^supervised .*/supervised systemd/'                /etc/redis/redis.conf
systemctl enable --now redis-server
systemctl restart redis-server

# --------------------------------------------------------------- 4. PostgreSQL
info "Membuat database dan pengguna PostgreSQL"
SANDI="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"

sudo -u postgres psql <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${DB_USER}') THEN
    CREATE ROLE ${DB_USER} LOGIN PASSWORD '${SANDI}';
  END IF;
END \$\$;
SQL

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" \
  | grep -q 1 || sudo -u postgres createdb -O "${DB_USER}" "${DB_NAME}"

echo "  DB_USERNAME=${DB_USER}"
echo "  DB_PASSWORD=${SANDI}   <-- CATAT, dipakai di .env"

# ------------------------------------------------------------------- 5. folder
info "Menyiapkan folder aplikasi"
mkdir -p "$APP_DIR"/{releases,shared/storage,repo} /var/log/o-api /var/www/certbot

git clone --branch main "$REPO" "$APP_DIR/repo" 2>/dev/null \
  || git -C "$APP_DIR/repo" fetch origin

# Struktur storage disiapkan manual karena folder rilis akan menautkan ke sini.
mkdir -p "$APP_DIR"/shared/storage/{app/private,app/public,app/lampiran,framework/{cache/data,sessions,testing,views},logs}

if [ ! -f "$APP_DIR/shared/.env" ]; then
    cp "$APP_DIR/repo/.env.example" "$APP_DIR/shared/.env"
    echo "  .env dibuat dari .env.example — ISI DULU sebelum deploy."
fi

chown -R www-data:www-data "$APP_DIR" /var/log/o-api
chmod -R 775 "$APP_DIR/shared/storage"

# ------------------------------------------------------------------ 6. systemd
info "Memasang unit systemd"
cp "$APP_DIR"/repo/deploy/systemd/*.service /etc/systemd/system/
systemctl daemon-reload

# ------------------------------------------------------------------- 7. nginx
info "Memasang konfigurasi nginx"
cp "$APP_DIR/repo/deploy/nginx/${DOMAIN}.conf" /etc/nginx/sites-available/
ln -sfn "/etc/nginx/sites-available/${DOMAIN}.conf" /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

info "Menerbitkan sertifikat TLS"
apt-get install -y certbot python3-certbot-nginx
# --nginx mengubah konfigurasi sendiri; di sini konfigurasinya sudah menunjuk
# ke jalur sertifikat, jadi dipakai mode webroot agar tidak saling menimpa.
certbot certonly --webroot -w /var/www/certbot \
    -d "$DOMAIN" -d "www.$DOMAIN" \
    --email "$EMAIL" --agree-tos --non-interactive

nginx -t && systemctl reload nginx

cat <<PESAN

Selesai. Langkah berikutnya, berurutan:

  1. Isi $APP_DIR/shared/.env
       APP_ENV=production
       APP_DEBUG=false
       APP_URL=https://$DOMAIN
       DB_PORT=5432                 <- di server, bukan 5433
       DB_USERNAME=$DB_USER
       DB_PASSWORD=<sandi di atas>
       CACHE_STORE=redis
       SESSION_DRIVER=redis
       QUEUE_CONNECTION=redis
       L5_SWAGGER_CONST_HOST=https://$DOMAIN

  2. cd $APP_DIR/repo && composer install --no-dev
     php artisan key:generate --force   (menulis ke shared/.env)

  3. php artisan octane:install --server=frankenphp

  4. sudo -u www-data $APP_DIR/repo/deploy/deploy.sh

  5. php artisan db:seed --force      (sekali, untuk akun superadmin)
     Lalu SEGERA ganti passwordnya lewat aplikasi.

  6. systemctl enable --now o-api-octane o-api-queue

PESAN
