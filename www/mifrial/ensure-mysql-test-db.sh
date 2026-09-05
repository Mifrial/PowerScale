#!/usr/bin/env bash
# Создаёт powerscale_test и даёт GRANT пользователю powerscale.
# Пароль MySQL root не нужен: вход через debian-sys-maint.
# Пароль powerscale не меняет.
set -euo pipefail

DATABASE="${MIFRIAL_TEST_DB_NAME:-powerscale_test}"
DB_USER="${MIFRIAL_DB_USER:-powerscale}"
DEBIAN_CNF="${MYSQL_DEFAULTS_FILE:-/etc/mysql/debian.cnf}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Запустите: sudo $0" >&2
  exit 1
fi

mysql_as_admin() {
  if [[ -r "${DEBIAN_CNF}" ]] && mysql --defaults-file="${DEBIAN_CNF}" -e 'SELECT 1' >/dev/null 2>&1; then
    mysql --defaults-file="${DEBIAN_CNF}" "$@"
    return 0
  fi

  if mysql --protocol=socket -u root -e 'SELECT 1' >/dev/null 2>&1; then
    mysql --protocol=socket -u root "$@"
    return 0
  fi

  cat >&2 <<'EOF'
Не удалось войти в MySQL ни через /etc/mysql/debian.cnf, ни как root без пароля.

  sudo mysql --defaults-file=/etc/mysql/debian.cnf -e 'SELECT USER();'
EOF
  return 1
}

mysql_as_admin <<SQL
CREATE DATABASE IF NOT EXISTS \`${DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON \`${DATABASE}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL ON \`${DATABASE}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "MySQL: база ${DATABASE}, GRANT для ${DB_USER}@localhost и @127.0.0.1."
echo "Пароль в config/test.php — тот же, что у ${DB_USER} в config/local.php."
