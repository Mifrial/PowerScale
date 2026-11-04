#!/usr/bin/env bash
set -euo pipefail
MIFRIAL="$(cd "$(dirname "$0")" && pwd)"
SITE="$(cd "$MIFRIAL/.." && pwd)"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Запустите с sudo: sudo $0" >&2
  exit 1
fi

PHP_RUNTIME="8.3"
SURY_KEYRING="/usr/share/keyrings/sury-php.gpg"
SURY_SOURCES="/etc/apt/sources.list.d/sury-php.list"

# PHP 8.3 из sury: Apache и CLI должны быть одной версии и сборки.
bootstrap_php() {
  if ! command -v curl >/dev/null 2>&1; then
    echo "PHP ${PHP_RUNTIME}: нужен curl для ключа sury — установите пакет curl." >&2
    exit 1
  fi

  local codename
  codename="$(. /etc/os-release && printf '%s' "${VERSION_CODENAME:-}")"
  if [[ -z "${codename}" ]]; then
    echo "PHP ${PHP_RUNTIME}: не удалось определить кодовое имя дистрибутива." >&2
    exit 1
  fi

  if [[ ! -f "${SURY_KEYRING}" ]]; then
    curl -fsSL https://packages.sury.org/php/apt.gpg -o "${SURY_KEYRING}"
    chmod 0644 "${SURY_KEYRING}"
  fi

  if [[ ! -f "${SURY_SOURCES}" ]]; then
    printf 'deb [signed-by=%s] https://packages.sury.org/php/ %s main\n' \
      "${SURY_KEYRING}" "${codename}" > "${SURY_SOURCES}"
  fi

  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
    "libapache2-mod-php${PHP_RUNTIME}" \
    "php${PHP_RUNTIME}-cli" \
    "php${PHP_RUNTIME}-mysql" \
    "php${PHP_RUNTIME}-mbstring" \
    "php${PHP_RUNTIME}-xml" \
    "php${PHP_RUNTIME}-curl" \
    "php${PHP_RUNTIME}-imagick"

  # 8.2 остаётся установленным: откат — a2enmod php8.2 и a2dismod php8.3.
  a2dismod php8.2 >/dev/null 2>&1 || true
  a2enmod "php${PHP_RUNTIME}" >/dev/null

  local cli_major_minor
  cli_major_minor="$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' 2>/dev/null || true)"
  if [[ "${cli_major_minor}" != "${PHP_RUNTIME}" ]]; then
    echo "PHP: CLI ${cli_major_minor:-неизвестен}, ожидался ${PHP_RUNTIME}." >&2
    exit 1
  fi

  if ! php -m | grep -qx 'imagick'; then
    echo "PHP ${PHP_RUNTIME}: расширение imagick не загрузилось." >&2
    exit 1
  fi
}

MYSQL_ADMIN_CMD="${MYSQL_ADMIN_CMD:-mysql}"

app_mysql_available() {
  [[ -f "${MIFRIAL}/config/local.php" ]] || return 1
  local db_host db_port db_name db_user db_password
  mapfile -t db_settings < <(php -r '
    $config = require $argv[1];
    $db = $config["db"] ?? [];
    echo ($db["host"] ?? ""), "\n", ($db["port"] ?? ""), "\n", ($db["database"] ?? ""),
      "\n", ($db["username"] ?? ""), "\n", ($db["password"] ?? ""), "\n";
  ' "${MIFRIAL}/config/local.php" 2>/dev/null)
  [[ ${#db_settings[@]} -eq 5 ]] || return 1
  db_host="${db_settings[0]}"
  db_port="${db_settings[1]}"
  db_name="${db_settings[2]}"
  db_user="${db_settings[3]}"
  db_password="${db_settings[4]}"
  [[ -n "${db_host}" && -n "${db_name}" && -n "${db_user}" ]] || return 1
  mysql -h "${db_host}" -P "${db_port}" -u "${db_user}" -p"${db_password}" -e "USE \`${db_name}\`" >/dev/null 2>&1
}

bootstrap_mysql() {
  if app_mysql_available; then
    echo "MySQL: база уже доступна приложению — bootstrap пользователей пропущен."
    return 0
  fi

  if ! ${MYSQL_ADMIN_CMD} -e "SELECT 1" >/dev/null 2>&1; then
    echo "MySQL: нет доступа администратора (${MYSQL_ADMIN_CMD}); bootstrap пропущен." >&2
    echo "  Если базы/пользователя ещё нет, задайте команду админа, например:" >&2
    echo "  sudo MYSQL_ADMIN_CMD='mysql -uroot -p' $0" >&2
    return 0
  fi

  ${MYSQL_ADMIN_CMD} -e "CREATE DATABASE IF NOT EXISTS powerscale CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'powerscale'@'localhost' IDENTIFIED BY 'changeme';
CREATE USER IF NOT EXISTS 'powerscale'@'127.0.0.1' IDENTIFIED BY 'changeme';
ALTER USER 'powerscale'@'localhost' IDENTIFIED BY 'changeme';
ALTER USER 'powerscale'@'127.0.0.1' IDENTIFIED BY 'changeme';
GRANT ALL ON powerscale.* TO 'powerscale'@'localhost';
GRANT ALL ON powerscale.* TO 'powerscale'@'127.0.0.1';
FLUSH PRIVILEGES;"
}

bootstrap_php
bootstrap_mysql

if ! grep -q 'powerscale.test.ru' /etc/hosts; then
  echo '127.0.0.1 powerscale.test.ru' >> /etc/hosts
fi

cat > /etc/apache2/sites-available/powerscale.test.ru.conf <<EOF
<VirtualHost *:80>
	ServerName powerscale.test.ru
	DocumentRoot ${SITE}
	<Directory ${SITE}>
		Options FollowSymLinks
		AllowOverride All
		Require all granted
	</Directory>
	ErrorLog \${APACHE_LOG_DIR}/powerscale.test.ru-error.log
	CustomLog \${APACHE_LOG_DIR}/powerscale.test.ru-access.log combined
</VirtualHost>
EOF

a2enmod rewrite
a2ensite powerscale.test.ru

for php_config_dir in /etc/php/*/apache2/conf.d; do
  if [[ -d "${php_config_dir}" ]]; then
    cat > "${php_config_dir}/99-powerscale-upload.ini" <<'EOF'
upload_max_filesize=40M
post_max_size=48M
EOF
  fi
done

# Пользователь Apache берётся из envvars: ps отдаёт master-процесс (root).
APACHE_USER="$(bash -c 'source /etc/apache2/envvars 2>/dev/null; printf "%s" "${APACHE_RUN_USER:-www-data}"' 2>/dev/null)"
APACHE_USER="${APACHE_USER:-www-data}"
APACHE_GROUP="$(bash -c 'source /etc/apache2/envvars 2>/dev/null; printf "%s" "${APACHE_RUN_GROUP:-www-data}"' 2>/dev/null)"
APACHE_GROUP="${APACHE_GROUP:-www-data}"
install -d -o "${APACHE_USER}" -g "${APACHE_GROUP}" -m 0770 "${MIFRIAL}/var/file"

systemctl reload apache2

echo "PHP ${PHP_RUNTIME} (sury, imagick) + hosts + Apache DocumentRoot=${SITE} + MySQL user powerscale@localhost и @127.0.0.1."
echo "Пароль по умолчанию changeme — смените: sudo ${MIFRIAL}/reset-mysql-password.sh"
