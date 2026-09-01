#!/usr/bin/env bash
set -euo pipefail
MIFRIAL="$(cd "$(dirname "$0")" && pwd)"
SITE="$(cd "$MIFRIAL/.." && pwd)"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Запустите с sudo: sudo $0" >&2
  exit 1
fi

mysql -e "CREATE DATABASE IF NOT EXISTS powerscale CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'powerscale'@'localhost' IDENTIFIED BY 'changeme';
CREATE USER IF NOT EXISTS 'powerscale'@'127.0.0.1' IDENTIFIED BY 'changeme';
ALTER USER 'powerscale'@'localhost' IDENTIFIED BY 'changeme';
ALTER USER 'powerscale'@'127.0.0.1' IDENTIFIED BY 'changeme';
GRANT ALL ON powerscale.* TO 'powerscale'@'localhost';
GRANT ALL ON powerscale.* TO 'powerscale'@'127.0.0.1';
FLUSH PRIVILEGES;"

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
systemctl reload apache2

echo "hosts + Apache DocumentRoot=${SITE} + MySQL user powerscale@localhost и @127.0.0.1."
echo "Пароль по умолчанию changeme — смените: sudo ${MIFRIAL}/reset-mysql-password.sh"
