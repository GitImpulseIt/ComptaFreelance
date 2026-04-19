#!/usr/bin/env bash
#
# Dump compressé de la base ComptaV2 (dev local) vers database/backup/.
# Garde les 3 dumps les plus récents.
# Compatible Git Bash sous Windows (Docker Desktop).
#
# Usage : ./database/backup.sh
#
# Restauration d'un dump :
#   gunzip -c database/backup/comptav2_YYYY-MM-DD_HH-MM-SS.sql.gz \
#     | docker run -i --rm -e PGPASSWORD=changeme \
#         --entrypoint psql alpine/psql \
#         -h host.docker.internal -U comptav2 -d comptav2
#

set -euo pipefail

# --- Configuration (en dur) ---
PG_HOST="host.docker.internal"
PG_PORT="5432"
PG_USER="comptav2"
PG_PASSWORD="changeme"
PG_DB="comptav2"
DOCKER_IMAGE="alpine/psql"
KEEP=3

# --- Résolution des chemins ---
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="${SCRIPT_DIR}/backup"
mkdir -p "${BACKUP_DIR}"

TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"
DUMP_FILE="${BACKUP_DIR}/${PG_DB}_${TIMESTAMP}.sql.gz"

echo "Dump de ${PG_DB}@${PG_HOST}:${PG_PORT} vers ${DUMP_FILE}"

# pg_dump écrit le SQL sur stdout, gzip compresse côté hôte.
# Pas de volume Docker nécessaire → pas de souci de path Windows.
docker run --rm \
    -e PGPASSWORD="${PG_PASSWORD}" \
    --entrypoint pg_dump \
    "${DOCKER_IMAGE}" \
    -h "${PG_HOST}" \
    -p "${PG_PORT}" \
    -U "${PG_USER}" \
    -d "${PG_DB}" \
    --no-owner \
    --no-acl \
    | gzip -9 > "${DUMP_FILE}"

echo "OK : $(du -h "${DUMP_FILE}" | cut -f1) → ${DUMP_FILE}"

# --- Rotation : ne garde que les ${KEEP} dumps les plus récents ---
mapfile -t OLD < <(ls -1t "${BACKUP_DIR}/${PG_DB}_"*.sql.gz 2>/dev/null | tail -n +$((KEEP + 1)))
if [[ ${#OLD[@]} -gt 0 ]]; then
    echo "Rotation : suppression de ${#OLD[@]} ancien(s) dump(s)"
    for f in "${OLD[@]}"; do
        rm -f "${f}"
        echo "  - ${f}"
    done
fi
