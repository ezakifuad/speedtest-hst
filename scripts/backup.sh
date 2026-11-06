#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
mkdir -p backups
backup_path="backups/speedtest-$(date -u +%Y%m%dT%H%M%SZ)-$$.sql.gz"
trap 'rm -f "$backup_path.tmp"' EXIT
docker compose exec -T db sh -c 'exec mariadb-dump --single-transaction -u speedtest -p"$MARIADB_PASSWORD" speedtest' | gzip > "$backup_path.tmp"
mv "$backup_path.tmp" "$backup_path"
echo "Backup database: $backup_path"
