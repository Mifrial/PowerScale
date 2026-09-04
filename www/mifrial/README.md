# Mifrial

Ядро внутри корня сайта [`www/`](../) (как `/bitrix` в `www`). Apache смотрит на `www/`, не на эту папку.

Правила разработки PHP находятся в
[`docs/tr/php-coding-standards.md`](../../docs/tr/php-coding-standards.md).

`init.php` — только bootstrap приложения. API имеет отдельную точку входа
[`API/action.php`](API/action.php); будущий SSE-транспорт будет отдельным
entrypoint рядом с ним.

`VITE_API_MODE=real` включает боевой `auth.login` (нужны `php bin/setup.php` и ключ `auth` в `local.php`).

```bash
cp config/local.php.dist config/local.php
composer install
sudo bash install-local.sh
curl -sS -X POST 'http://powerscale.test.ru/api/run?action=mifrial.ping' -H 'Content-Type: application/json'
```

Фронт: `http://powerscale.test.ru:3000`. Тесты: `vendor/bin/phpunit`. Интеграция SmartTable и User ждёт MySQL с теми же ключами, что `config/local.php` (либо `MIFRIAL_TEST_DB_*`).

Данные: SmartTable (`DEC-078`) на `illuminate/database` без Eloquent. User — таблицы `user`, `user_group`, `user_group_member`. Auth — `user_identity`, `auth_session` (`kind` user/guest, `user_id` у гостя пуст), httpOnly cookie `mifrial-session`. Нарезка —
[`docs/tr/smarttable-roadmap.md`](../../docs/tr/smarttable-roadmap.md).

Установка/обновление схемы всех модулей на диске (граф `reference`, data-шаги):

```bash
php bin/setup.php
```

Почта: `IMail::trigger`, очередь `mail_job`, тик агента `mail.flush`. SMTP в v1 нет — `LogMailTransport` пишет в `error_log`. Inline-flush — ключ `mail.flush_inline` в `local.php` (dist false).

Тик агентов (cron ОС раз в минуту). Ключ `agents` в `module.config` донора; строку расписания пишет его data-шаг:

```bash
php bin/agent.php
```

Проверка и исправление PHP-стиля:

```bash
composer cs-check
composer cs-fix
```
