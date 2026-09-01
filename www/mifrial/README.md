# Mifrial

Ядро внутри корня сайта [`www/`](../) (как `/bitrix` в `www`). Apache смотрит на `www/`, не на эту папку.

Правила разработки PHP находятся в
[`docs/tr/php-coding-standards.md`](../../docs/tr/php-coding-standards.md).

`init.php` — только bootstrap приложения. API имеет отдельную точку входа
[`API/action.php`](API/action.php); будущий SSE-транспорт будет отдельным
entrypoint рядом с ним.

`VITE_API_MODE=real` не включать, пока нет `auth.login`.

```bash
cp config/local.php.dist config/local.php
composer install
sudo bash install-local.sh
curl -sS -X POST 'http://powerscale.test.ru/api/run?action=mifrial.ping' -H 'Content-Type: application/json'
```

Фронт: `http://powerscale.test.ru:3000`. Тесты Kernel: `vendor/bin/phpunit`.

Проверка и исправление PHP-стиля:

```bash
composer cs-check
composer cs-fix
```
