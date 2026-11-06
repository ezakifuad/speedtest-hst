#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
command -v flock >/dev/null
exec 9>.deploy.lock
flock -n 9 || { echo 'Pemasangan lain sedang berjalan.' >&2; exit 1; }
docker compose version >/dev/null
if [ "${1:-}" = '--update' ]; then
 [ -d .git ] || { echo 'Jalankan dari checkout repository khusus Speedtest HST.' >&2; exit 1; }
 [ -z "$(git status --porcelain)" ] || { echo 'Ada perubahan lokal; selesaikan sebelum pembaruan.' >&2; exit 1; }
 bash scripts/backup.sh
 git pull --ff-only
elif [ "$#" -ne 0 ]; then
 echo 'Gunakan tanpa argumen untuk instalasi, atau --update untuk pull GitHub.' >&2
 exit 1
fi
bash scripts/init-env.sh
docker compose up -d --wait --wait-timeout 180 db
bash scripts/migrate.sh
# Docker gateway identifies the host reverse proxy. Trust only that peer.
db_container=$(docker compose ps -q db)
proxy_gateway=$(docker inspect --format '{{range .NetworkSettings.Networks}}{{.Gateway}}{{end}}' "$db_container")
[[ "$proxy_gateway" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'Gateway proxy perlu dikonfigurasi manual.' >&2; exit 1; }
if grep -q '^TRUSTED_PROXIES=' .env; then
 sed -i "s/^TRUSTED_PROXIES=.*/TRUSTED_PROXIES=$proxy_gateway/" .env
else
 echo "TRUSTED_PROXIES=$proxy_gateway" >> .env
fi
chmod 600 .env
docker compose up -d --build --wait --wait-timeout 180
echo 'Layanan sehat: http://127.0.0.1:8093'
echo 'Lanjutkan pengaturan Proxy Project dan SSL di aaPanel; verifikasi IP klien dari perangkat di luar jaringan.'
