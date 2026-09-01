# План 1 — соединение SmartTable

**Статус:** план реализации, 2026-09-01. Канон требований — [`smarttable.md`](smarttable.md). Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Кнопка Build в Cursor — у файла [`.cursor/plans`](../../.cursor/plans) / пользовательского plan, не у этого markdown. Чеклист здесь — чтобы план был исполняемым и без UI.

## Todo

- [ ] **kernel-config-port** — `LocalConfigLoader`, `DatabaseSettings`, `IRuntimeConfig` в Kernel. Extra-порты — `Closure` в конструктор `ModuleContainerFactory`, **только** в `KernelContainer` (не во все модули). `ApplicationFactory` собирает binder явно. Extra побеждает одноимённый ключ из `module.config`.
- [ ] **kernel-config-tests** — юнит лоадера на temp-файле. Ping без MySQL. `getConfig` и `IRuntimeConfig` — один массив. `LazyModuleLoadTest` без extra-портов зелёный.
- [ ] **smarttable-connection** — модуль: контейнер, `SmartTableException` с кодом, ленивый MySQL, `ping(): void` без SQL/PDO в интерфейсе, UTC, default port 3306, пароль не в `message`.
- [ ] **smarttable-tests-gates** — suite PHPUnit; юнит `dsn`/дырявый `db`; интеграция ping или skip; autoload + phpcs exclude tests; `cs-check` / `quality` / `phpunit`; одна строка в README.

## Цель

Ленивый MySQL из `config/local.php` без Capsule/Eloquent и без CRUD. `boot` и `mifrial.ping` не открывают сокет. `SELECT 1` только внутри модуля/теста.

## Не входит

Поля, `getMap`, Schema, `open`, CRUD, getList, Redis, SSL, reconnect, пул, table prefix, второй коннект, Docker/Apache из теста.

## Контракт

1. Ключи: `db.host`, `db.port`, `db.database`, `db.username`, `db.password`, `db.charset`. Лишний `dsn` или не те типы — `SmartTableException` (`DB_CONFIG_INVALID`) при **connect**, не при `boot`. Нет `db` — boot живой.
2. Соединение ленивое. Повтор `get` порта — тот же адаптер; повтор connect — тот же PDO.
3. Нет Capsule, `setAsGlobal`, `bootEloquent`, Eloquent, фасада `DB`, Illuminate Application.
4. Снаружи модуля не видны PDO, Query Builder, `Illuminate\Database\Connection`. На `ISmartTableContainer` нет SQL-методов. Адаптер — внутренний порт того же контейнера (тесты модуля / будущие сервисы SmartTable), не API соседа.
5. Интеграция: только MySQL; skip если нечем коннектиться; не SQLite. `MIFRIAL_TEST_DB_*` только bootstrap PHPUnit.
6. `open($table)` не объявляем.
7. `loadCore()` создаст контейнер SmartTable. Порт соединения не резолвить, пока его не `get`.
8. На connect: `time_zone = '+00:00'`; пустые port/charset → `3306` / `utf8mb4` / `utf8mb4_unicode_ci`; prefix `''`.
9. Kernel не зависит от SmartTable. Публичный адаптер — только `ping(): void`.
10. Extra-порты мержить **только** в KernelContainer. Влить `IRuntimeConfig` в каждый модуль нельзя (`LazyModuleLoadTest` и граница). `IKernelContainer` новый метод не получает — порт через `get(IRuntimeConfig::class)`.
11. В тексте исключения нет пароля.

Порядок `boot`: load config → extra Kernel-порты → loadCore → bindEager → lazy catalog → freeze locator → `new Application(..., $config)`.

## Исключения

`SmartTableException extends MifrialException` с `getErrorCode()` как у `KernelException`. Коды: `DB_CONFIG_INVALID`, `DB_CONNECT_FAILED`. Чужой throwable на connect → SmartTableException, `previous` сохранить.

## Ворота

`composer cs-check`, `composer quality`, `vendor/bin/phpunit` из `www/mifrial`. Нет классов полей/CRUD. `local.php` не в git.

## Следующий заход

План 2 — типы и `getMap`. Тот же внутренний адаптер, не новый коннект.
