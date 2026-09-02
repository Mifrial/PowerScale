# План 3 — CRUD одной таблицы с PHP-классом

**Статус:** готов к реализации, 2026-09-01. Канон — [`smarttable.md`](smarttable.md). Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Поля — [`smarttable-plan-02-fields.md`](smarttable-plan-02-fields.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md) (KISS/SOLID/DRY/слои).

Цель: DDL из `ColumnMeta`, `open`, строка, транзакция. Schema и Query Builder только во внутренних адаптерах.

## Todo

- [x] **gateway-api** — порт `ISmartTableGateway`; handle `IOpenedTable` в `Service/` + `Interface/Service/`; один PDO; `open` через Reflection, без `catch (Error)`.
- [x] **layers** — тонкий handle; stateless `TableSchema` и `TableRows`; `RowAssembler` без SQL. Mapper/SQLSTATE-класс — не заранее, только если quality или второй вызывающий.
- [x] **ddl-rows** — без сравнения типов; не add `id`; SELECT по карте; JSON как PHP-значение.
- [x] **exceptions-tests-gates** — листья Schema/Row/Transaction; MySQL или skip; rollback только DML; cs/quality/phpunit.

## Не входит

`getList` (план 4, тот же фасад), working `multiple`, `reference`, `forceUpdateTable`, `deleteTable`, index/unique кроме PK, словарь, кэш, журнал наката репозитория (не SmartTable), Versioned, события CRUD, fluent `query()`, ALTER TYPE / VARCHAR length.

## Слои

`Table/` — только definition (имя + карта). **Не** handle: иначе `Table/` зависит от `Service/` (schema/rows).

| Тип | Папка | Делает | Не делает |
|---|---|---|---|
| `SmartTableDefinition` | `Table/` | имя, `getMap()` | SQL, open |
| Поле | `Field/` | cast/extract/hydrate, `ColumnMeta` | Schema, QB |
| `RowAssembler` | `Service/` | ключи, cast→extract, hydrate | SQL |
| `TableSchema` | `Service/` | create/updateTable, Blueprint из meta | insert/select, cast |
| `TableRows` | `Service/` | insert/update/delete/select/exists/`lastInsertId` | Blueprint, cast |
| `OpenedTable` | `Service/` | делегирует schema/rows, держит definition | SQL, цикл по полям |
| `SmartTableGateway` | `Service/` | `open`, транзакция | DDL, строка |

Отдельный mapper / переводчик SQLSTATE **не** заводить с первого коммита. Вынести, если quality упрётся или появится копипаста 42S02/42S22.

Фасад `IOpenedTable` для соседа. Класс — тонкий. План 4: `getList` на фасаде, фильтр — новый тип.

Контракты зеркалят класс: `Interface/Service/ISmartTableGateway.php`, `Interface/Service/IOpenedTable.php`. `IOpenedTable` **не** порт контейнера (только возврат `open`).

`TableSchema` / `TableRows` stateless: в методы передают `SmartTableDefinition`.

Порт не называется `ISmartTable`. Контейнер не сервис.

## Сборка (один коннект)

Кложура порта: `($serviceLocator, $moduleContainer)`.

- `IDatabaseConnection` — существующая фабрика, один адаптер.
- `ISmartTableGateway` — `$moduleContainer->get(IDatabaseConnection::class)`. Один `instanceof IlluminateDatabaseConnection`; иначе `DbConfigInvalidException` (сломанный override, не новый лист). Schema/rows/gateway получают **этот** объект. Второй `new` адаптера запрещён.

`IIlluminateConnection` в `Interface/` и в карте портов **нет**.

Gateway / schema / rows / assembler / mapper локатор не читают.

## Контракт

```
ISmartTableGateway::open(string $definitionClass): IOpenedTable
ISmartTableGateway::transaction(Closure $work): mixed
```

`open` до `new`: класс есть; `is_subclass_of(..., SmartTableDefinition::class)`; `ReflectionClass` — не abstract, конструктор без обязательных параметров. Иначе `MapInvalidException`. **Не** `catch (Error)`: слишком широко (и против «не заворачивать чужой throwable пачкой»). Handle не кэшировать.

`transaction`: `transactionLevel() > 0` → `TRANSACTION_OPEN`, без savepoint. Успех → commit. Throwable → rollback, тот же throwable. Сбой commit/rollback → `TransactionFailedException` + `previous`.

MySQL DDL = неявный commit. Не эмулировать rollback схемы. Тест отката — только `add`.

## DDL

Сначала `instanceof IdField` (`type()` у него `int`). Signed `INT` + `AUTO_INCREMENT` + `PRIMARY KEY`. Не `increments()`.

| ColumnMeta | Blueprint |
|---|---|
| VARCHAR + length (length обязателен) | `string($name, $length)` |
| TEXT | `text` |
| LONGTEXT | `longText` |
| INT | `integer` |
| TINYINT + length 1 | `tinyInteger` |
| JSON | `json` |

Неизвестный `sqlType` или VARCHAR без length → `MapInvalidException`.

`required` → `NOT NULL`. SQL DEFAULT нет. Index/unique с флагов нет.

`createTable`: есть таблица → `TABLE_EXISTS`. `updateTable`: нет таблицы → `TABLE_MISSING`. Нет колонки (не `id`) → add. Лишние колонки БД не трогать. Типы не сравнивать.

Нет колонки `id` на существующей таблице → `SCHEMA_MISMATCH`, не `ADD id`.

Чужой SQL на DDL → `DDL_FAILED`, пароль не в `message`. `hasTable` закрывает exists/missing; SQLSTATE на DDL не плодить заранее.

## Строка

Assembler: неизвестный ключ → `MAP_INVALID`. Add без ключа → `cast(..., false)`. `id` на add не `null` → `MAP_INVALID`. Update: пустой массив или ключ `id` → `MAP_INVALID`; только переданные ключи; required только для них.

Не `affected rows`. Нет строки на update/delete: exists по `id` → `ROW_NOT_FOUND`. Гонка exists/update в v1 допустима. `getById` без строки → `null`.

SELECT колонки из карты, не `*`.

На DML: 42S02 → `TABLE_MISSING`; 42S22 → `SCHEMA_MISMATCH`; иначе → `ROW_WRITE_FAILED`. Пока единственный потребитель — `TableRows`: `private` рядом с insert/select.

JSON: `extract` отдаёт PHP array/скаляр. Illuminate insert массив не кодирует (в SQL уходит `"Array"`), поэтому `TableRows` перед DML делает `json_encode` колонок `type() === json`. Во `JsonField::extract` encode нет.

`lastInsertId` — строка PDO → `(int)`; `<= 0` → `ROW_WRITE_FAILED`.

add → `int` id. get → гидратированный `array<string, mixed>`.

## Ошибки

- Schema: `TableExistsException`, `TableMissingException`, `SchemaMismatchException`, `DdlFailedException`;
- Row: `RowNotFoundException`, `RowWriteFailedException`;
- Transaction: `TransactionOpenException`, `TransactionFailedException`.

Коды: `TABLE_EXISTS`, `TABLE_MISSING`, `SCHEMA_MISMATCH`, `DDL_FAILED`, `ROW_NOT_FOUND`, `ROW_WRITE_FAILED`, `TRANSACTION_OPEN`, `TRANSACTION_FAILED`.

Поля/map плана 2 не подменять.

## Тесты

Только MySQL, skip как ping. Фикстура не `st_sample`. `dropIfExists` в teardown.

Сценарии: create→add→get (скалярные типы плана 2); частичный update; delete; get unknown `null`; update/delete неизвестного id → `ROW_NOT_FOUND`; add без required; unknown key; повторный create; updateTable (второе definition, то же имя, **nullable** поле); CRUD без таблицы; rollback только `add`; вложенный transaction.

Юнит: `open` не-definition / abstract / ctor с аргументом; неизвестный `sqlType` / VARCHAR без length; assembler unknown key / id на add / пустой update / id на update.

Ворота: `cs-check`, `quality`, `phpunit`. В `local.php` — `collation` и `timezone` с dist.

## Следующий заход

План 4 — [`smarttable-plan-04-getlist.md`](smarttable-plan-04-getlist.md). Multiple — план 5.
