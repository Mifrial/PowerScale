#!/usr/bin/env bash
# Полный прогон бэкенда и фронтенда. Запускать на границе задачи/сессии,
# не после каждого коммита. Логи и JUnit — в /tmp/opencode/test-runs.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${TEST_RUN_OUT:-/tmp/opencode/test-runs}"
mkdir -p "$OUT"

echo "== backend =="
cd "$ROOT/www/mifrial"
/usr/bin/time -f 'backend WALL %e s' php vendor/bin/phpunit --no-coverage \
  --log-junit "$OUT/backend-junit.xml" > "$OUT/backend.log" 2>&1
backend=$?

echo "== frontend =="
cd "$ROOT/draft-front_1.2ds"
/usr/bin/time -f 'frontend WALL %e s' npx vitest run \
  --reporter=default --reporter=junit \
  --outputFile.junit="$OUT/frontend-junit.xml" > "$OUT/frontend.log" 2>&1
frontend=$?

echo "--- backend"
tail -n 3 "$OUT/backend.log" || true
echo "--- frontend"
grep -E 'Test Files|Tests  ' "$OUT/frontend.log" | tail -2 || true
echo "backend=$backend frontend=$frontend (logs: $OUT)"

if [ "$backend" -ne 0 ] || [ "$frontend" -ne 0 ]; then
  exit 1
fi
