#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [ -e .env ]; then
  echo '.env sudah ada; kredensial dipertahankan.'
  exit 0
fi
command -v openssl >/dev/null
umask 077
# No plaintext passwords are printed.
{
  echo "DB_PASSWORD=$(openssl rand -hex 32)"
  echo "DB_ROOT_PASSWORD=$(openssl rand -hex 32)"
  echo "ADMIN_PASSWORD=$(openssl rand -hex 24)"
  echo 'APP_HTTPS=1'
  echo 'TRUSTED_PROXIES='
} > .env
chmod 600 .env
echo '.env dibuat. Lihat ADMIN_PASSWORD langsung pada file server melalui aaPanel.'
