#!/usr/bin/env bash
# Поднимает отдельный MySQL только для тестов на 127.0.0.1:3307.
# Логин/пароль берёт из config/test.php, чтобы не дублировать секрет в репозитории.
set -euo pipefail

cd "$(dirname "$0")"
CONFIG="$(cd ../.. && pwd)/config/test.php"

if [ ! -f "$CONFIG" ]; then
  echo "Не найден $CONFIG" >&2
  echo "Скопируйте config/test.php.dist в config/test.php и заполните доступы." >&2
  exit 1
fi

MIFRIAL_TEST_DB_USER="$(php -r '$c = require $argv[1]; echo $c["db"]["username"];' "$CONFIG")"
MIFRIAL_TEST_DB_PASSWORD="$(php -r '$c = require $argv[1]; echo $c["db"]["password"];' "$CONFIG")"
export MIFRIAL_TEST_DB_USER MIFRIAL_TEST_DB_PASSWORD

IMAGE="${MIFRIAL_TEST_MYSQL_IMAGE:-mysql:8.0}"
if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
  FALLBACK="quay.io/bitrix24/percona-server:8.0.45-v1-rhel"
  if docker image inspect "$FALLBACK" >/dev/null 2>&1; then
    echo "Образ $IMAGE не найден, использую локальный $FALLBACK" >&2
    IMAGE="$FALLBACK"
  fi
fi
export MIFRIAL_TEST_MYSQL_IMAGE="$IMAGE"

docker compose up -d --wait
