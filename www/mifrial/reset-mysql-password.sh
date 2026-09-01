#!/usr/bin/env bash
# Сброс пользователя MySQL powerscale без знания старого пароля.
# Ubuntu 8.0: root@localhost часто с паролем, sudo mysql без пароля не заходит.
# Админский вход — debian-sys-maint из /etc/mysql/debian.cnf.
set -euo pipefail

MIFRIAL="$(cd "$(dirname "$0")" && pwd)"
LOCAL_PHP="${MIFRIAL}/config/local.php"
DATABASE="${MIFRIAL_DB_NAME:-powerscale}"
DB_USER="${MIFRIAL_DB_USER:-powerscale}"
PASSWORD="${1:-}"
DEBIAN_CNF="${MYSQL_DEFAULTS_FILE:-/etc/mysql/debian.cnf}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Запустите: sudo $0 [новый-пароль]" >&2
  echo "Без аргумента пароль сгенерируется." >&2
  exit 1
fi

if [[ -z "${PASSWORD}" ]]; then
  PASSWORD="$(openssl rand -base64 18 | tr -d '/+=' | head -c 24)"
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

Проверьте:
  sudo mysql --defaults-file=/etc/mysql/debian.cnf -e 'SELECT USER();'

Если и это 1045 — у сервера сброшен debian-sys-maint. Тогда разово:

  sudo systemctl stop mysql
  sudo mkdir -p /var/run/mysqld
  sudo chown mysql:mysql /var/run/mysqld
  sudo mysqld --skip-grant-tables --skip-networking --pid-file=/tmp/mysql-recover.pid &
  sleep 2
  sudo mysql --protocol=socket -u root
  # в клиенте:
  # ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY 'временный';
  # FLUSH PRIVILEGES;
  # exit
  sudo kill "$(cat /tmp/mysql-recover.pid)"
  sudo systemctl start mysql

После этого снова: sudo этот-скрипт
EOF
  return 1
}

mysql_as_admin <<SQL
CREATE DATABASE IF NOT EXISTS \`${DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${PASSWORD}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${PASSWORD}';
GRANT ALL ON \`${DATABASE}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL ON \`${DATABASE}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

if [[ -f "${LOCAL_PHP}" ]]; then
  php -r '
    $path = $argv[1];
    $password = $argv[2];
    $config = require $path;
    if (!is_array($config)) {
        fwrite(STDERR, "local.php is not an array\n");
        exit(1);
    }
    $config["db"]["password"] = $password;
    $exported = var_export($config, true);
    $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $exported . ";\n";
    file_put_contents($path, $contents);
  ' "${LOCAL_PHP}" "${PASSWORD}"
  if [[ -n "${SUDO_USER:-}" ]]; then
    chown "${SUDO_USER}:${SUDO_USER}" "${LOCAL_PHP}"
  fi
fi

echo "MySQL: пользователь ${DB_USER} @localhost и @127.0.0.1, база ${DATABASE}."
echo "Пароль записан в ${LOCAL_PHP} (файл не в git)."
echo "Новый пароль: ${PASSWORD}"
