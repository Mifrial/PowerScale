#!/usr/bin/env bash
# Останавливает тестовый MySQL. Данные на tmpfs пропадают — это ожидаемо.
set -euo pipefail

cd "$(dirname "$0")"
docker compose down
