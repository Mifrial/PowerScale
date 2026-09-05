# Mifrial

Ядро внутри корня сайта [`www/`](../) (как `/bitrix` в `www`). Apache смотрит на `www/`, не на эту папку.

Правила разработки PHP находятся в
[`docs/tr/php-coding-standards.md`](../../docs/tr/php-coding-standards.md).

`init.php` — только bootstrap приложения. HTTP-action — [`API/action.php`](API/action.php);
живой sync чата — [`API/chat-sync.php`](API/chat-sync.php) (`GET /api/chat/sync`, cookie, без CSRF).
Apache: оба PHP в allowlist `.htaccess`; на `/api/chat/sync` выключен gzip. `Timeout` vhost
не оставлять дефолтные 60 с — иначе поток оборвётся, несмотря на `: ping`.

`VITE_API_MODE=real` включает боевой `auth.login` (нужны `php bin/setup.php` и ключ `auth` в `local.php`).

`cp config/test.php.dist config/test.php` — phpunit (`MIFRIAL_CONFIG=test`). База `powerscale_test`, не сайт. Пароль MySQL root не нужен. Ключ `logger` в `local.php` / `test.php` — class-string порта табличного адаптера (`TableLogWriter`); без ключа процесс пишет в `error_log`.

```bash
sudo bash ensure-mysql-test-db.sh
```

В `config/test.php` тот же пользователь и пароль, что в `local.php`, другое имя базы.

```bash
cp config/local.php.dist config/local.php
cp config/test.php.dist config/test.php
composer install
sudo bash install-local.sh
sudo bash ensure-mysql-test-db.sh
curl -sS -X POST 'http://powerscale.test.ru/api/run?action=mifrial.ping' -H 'Content-Type: application/json'
```

Фронт: `http://powerscale.test.ru:3000`. Тесты: `vendor/bin/phpunit` (БД из `config/test.php`, не `local.php`). Переопределение: `MIFRIAL_TEST_DB_*`.

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
