#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
query() {
 docker compose exec -T db sh -c 'exec mariadb -N -B -u speedtest -p"$MARIADB_PASSWORD" speedtest' <<< "$1"
}
table_count=$(query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='results';")
if [ "$table_count" = 0 ]; then
 docker compose exec -T db sh -c 'exec mariadb -u speedtest -p"$MARIADB_PASSWORD" speedtest' < database/001_schema.sql
fi
column_count=$(query "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='results' AND column_name='owner_hash';")
if [ "$column_count" = 0 ]; then
 docker compose exec -T db sh -c 'exec mariadb -u speedtest -p"$MARIADB_PASSWORD" speedtest' < database/002_result_details.sql
 echo 'Skema detail hasil diterapkan.'
else
 echo 'Skema database sudah sesuai; data lama dipertahankan.'
fi
