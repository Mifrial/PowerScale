# SmartTable

**Статус:** канон требований к серверному доступу к данным, 2026-09-02. Basic закрыт (план 10). Owner [`architecture.md`](architecture.md). Стек — `DEC-078`. Решения v1 ниже — часть канона.

## Назначение

SmartTable — единственная точка доступа модулей к MySQL. Репозиторий не пишет SQL, не вызывает Query Builder/Schema и не использует Eloquent. Наружу — HL-подобный API.

Все таблицы в БД устроены одинаково. PHP-класс (написанный вручную или сгенерированный из словаря) — фасад определения, не второй вид хранения. `ISmartTableGateway::open(class)` возвращает сумку: **`schema()`** (`exists` / `createTable` / `updateTable` / `forceUpdateTable` / `deleteTable`) и **`records()`** (`add` / `addMany` / `update` / `delete` / `getById` / `getList` / `getUnique` / `getFirst` / `aggregate`). Прикладной репозиторий получает `records()` с фабрики, не зовёт `open`. Класс может опережать схему: `exists()` (есть ли физика) / `createTable` / `updateTable` (не удаляет лишние колонки в БД) / `forceUpdateTable` (снимает leftover колонок, индексов, FK и mfv, которых нет в классе) / `deleteTable`. `createTable` при уже существующей таблице — `TABLE_EXISTS`, не ветка install. Если класс ссылается на поле, которого нет в БД — исключение. Поля в БД, которых нет в классе, при обычном `updateTable` не трогаются. Имя таблицы одно: PHP-класс и запись словаря не делят два разных `users`. Таблица из словаря открывается по имени (`ITableCatalog::openByName`), не через `ISmartTableGateway::open` по class-string. Журнал наката схемы (up/down по репозиторию) — не API SmartTable; SmartTable даёт DDL одной таблицы.

Оболочка доступа — **Basic**. Версионность (пространства, ревизии, COW, срез состава) — модуль `Versioning/Space`, несколько Basic-карт, не второй вид `open()` ([`versioning-roadmap.md`](versioning-roadmap.md), `DEC-080`). Кода `Versioned*` в `Core/SmartTable` нет.

Админский UI — **после Auth**.

## Стек (`DEC-078`)

Внутри `Core/SmartTable`: `illuminate/database` (Connection, Query Builder, Schema/Blueprint). Запрещены Eloquent, `bootEloquent`, глобальный Capsule. Laravel-приложение не подключается.

Соединение — ключи `local.php` (`host`, `port`, `database`, `username`, `password`, `charset`), не PDO-DSN: Capsule так и работает. Драйвер кэша (`redis` / `file`) — тот же конфиг.

Тесты SmartTable — **только MySQL** (та же СУБД, что в проде). SQLite не используем.

## Basic: идентичность и системные поля

Обязательное системное поле у всех Basic: автоинкремент **`id`** (integer). Других обязательных системных полей нет. Где нужен soft-delete — обычное поле **`active`** (bool), не отдельный механизм. `created_at` экземпляра версии — колонка карты Versioning, не системное поле ST.

## Наши типы → колонка

Пользователь и `getMap()` видят **наш** тип, не MySQL.

| Тип | БД | PHP in/out |
|---|---|---|
| `string` | `VARCHAR(n)` | `string` |
| `text` | `TEXT` | `string` |
| `html` | `LONGTEXT` | `string` (сырая разметка; санитация — не v1 SmartTable, а потребитель/UI) |
| `int` | `INT` | `int`; опционально min/max в настройках поля, проверка на add/update |
| `bigint` | `BIGINT` | `int` (64-bit PHP); те же min/max, если заданы |
| `bool` | `TINYINT(1)` | `bool` |
| `datetime` | `INT` (unix timestamp) | объект DateTime ядра (PHP-зеркало Engine); авторазбор строки/int на входе **не** обязателен в v1 — лучше явный объект |
| `json` | `JSON` | массив/скаляр после hydrator или `array` |
| `reference` | `INT` (`id` целевой таблицы) | `int` / `null`; **единственный** hop в getList (план 14) |
| `linkset` | нет колонки; mfv `{table}_mfv_{field}` | `list<int>` связей с целевой таблицей; **не** hop ([`smarttable-plan-20-linkset.md`](smarttable-plan-20-linkset.md)) |

**Текст:** `string` + **`maxLength`** (по умолчанию 255, максимум **1024** → VARCHAR) и `text` без предела (TEXT). «Название» vs «статья» — пресеты UI, не отдельные типы хранения.

**HTML:** отдельный тип (не `text`), чтобы не смешать с поиском/санитацией. В v1 это длинная строка в LONGTEXT, не value-object.

Системный `id`: по умолчанию `IdField` → signed `INT` AUTO_INCREMENT. Широкий PK — тот же `IdField` (`IdField::big()`) → signed `BIGINT` (не unsigned `bigIncrements`). Целая колонка данных: `IntField` с флагом ширины (`type()` `int` | `bigint`), не классы-близнецы и не ключ `FieldSettings`. Ссылка `reference` ширину **не** задаёт. Пока родители с широким `id` не обещаны, колонка FK — signed `INT`. Вывод ширины FK из `id` цели — отдельный заход. `user.id` не меняем. Подробно — [`smarttable-plan-15-bigint.md`](smarttable-plan-15-bigint.md).

**Datetime:** в БД int unix **UTC**, наружу DateTime. Hydrate из БД всегда в объект. `default` на datetime: нет ключа / объект ядра / sentinel «сейчас» (на add без ключа → `DateTime::now()`). Не SQL `DEFAULT`. Явный `null` при required — ошибка, не «сейчас». Update без ключа дату не ставит. Подробно — [`smarttable-plan-13-datetime-now.md`](smarttable-plan-13-datetime-now.md).

## Обязательность

`required` в настройках поля ⇒ проверка на add/update. Для обычной колонки ещё `NOT NULL` в SQL. У `multiple` колонки на основной таблице нет: пустой list (`[]`) при `required` — `FIELD_REQUIRED`, без SQL NOT NULL.

## Multiple

Флаг `multiple`: значение всегда `list` (множество, порядок в API не контракт). Хранение — таблица `{table}_mfv_{field}` (multiple field values), не JSON в строке. Индекс `(owner_id, value)`. Удаление основной строки чистит mfv **явно** в `delete()`, без `ON DELETE CASCADE` на sidecar (не путать с `onDelete: cascade` у `reference`). Тип **`linkset`** (`LinkSetField`) — то же хранилище, но цель — чужая таблица; флаг `multiple` на нём не ставят ([`smarttable-plan-20-linkset.md`](smarttable-plan-20-linkset.md)).

## Reference и удаление

`onDelete` на поле `reference`: `restrict` (default), `setNull`, `none`, **`cascade`**. `ON DELETE SET NULL` не ставить, если поле `required`. `required` + `none` нельзя. **`required` + `cascade` можно** (NOT NULL + `ON DELETE CASCADE`). На одной карте `onDelete: cascade` и любое `multiple` нельзя: SQL CASCADE обходит PHP `delete()`, sidecar mfv не чистится. `none` — без физического FK, висячие id возможны; для required выключать FK нельзя.

Автокаскад **mfv / чужих таблиц без объявления** по-прежнему запрещён. `cascade` только если поле его задало.

Удаление **таблицы и поля** — в v1. Переименование физической таблицы/колонки — нет. Смена логического `NAME` SmartTable — опасно, не v1.

## getList

Пагинация списков: **limit + offset**, **total опционален**. `limit` getList — **1..10000** ([`smarttable-plan-18-slice-batch.md`](smarttable-plan-18-slice-batch.md)); HTTP-страницы по-прежнему шлют маленький limit. Агрегат групп — **1..500**, без изменений. Отдельного режима cursor у `getList` нет. Точечный unique-поиск — `records()->getUnique(ListQuery, ttl)` (внутри `limit` 2: нет строки → `null`, две+ → `MAP_INVALID`). `getFirst(ListQuery, ttl)` — первая строка или `null` (нужны filter или sort). Оба — оболочка над `getList`: те же filter/sort/select; `offset` и `countTotal` недопустимы. `addMany` — пачка insert до 10000 строк, sidecar mfv как у `add` ([`smarttable-plan-19-addmany-mfv.md`](smarttable-plan-19-addmany-mfv.md)).

Лента чата курсор в смысле «сообщения старше этого id» **нужна**, но это не OFFSET и не отдельный API SmartTable: `filter: id < :lastId`, sort по `id` DESC, `limit`. То же для «новее чем». Sync-cursor фронта (`lastSync`, DEC-063) — курсор канала, не пагинация таблицы. Когда OFFSET на огромных лентах станет узким местом — можно добавить keyset сахар; в v1 хватает `<` / `>` + limit.

Фильтр по multiple:

- **contains** (`@`, все указанные значения входят в множество строки, надмножество допускается):
  - в БД `field1 = [1, 4, 6, 7]`;
  - `@field1 => 1` — да; `@field1 => 2` — нет; `@field1 => [1, 4]` — да; `@field1 => [1, 2]` — нет;
  - `@field1 => []` — исключение;
  - «1 или 2» — два условия `@` и `LOGIC => OR` (не `@ => [1, 2]`).
- **равенство множеств** (`=` / без префикса): строка совпадает, если множество значений **в точности** равно поданному (порядок не важен, дубль в операнде — ошибка). `field1 => [1, 4]` — да только при `{1, 4}`; `{1, 4, 6}` — нет. Скаляр `field1 => 1` — то же, что `[1]`. `field1 => []` — строки с пустым множеством (нет рядов mfv).

Сортировка по multiple в v1: **исключение** (порядок списка значений не задан). Если понадобится позже — отдельное правило (например MIN), не молчаливый sort первой колонки mfv.

Прочие операторы фильтра v1: `=`, `!=`, `IN`, `%`, `<`, `>`, `<=`, `>=`, `><` (интервал), AND/OR. Без `<`/`>` лента чата на Basic не собирается.

Путь через `reference`: hop только по `reference` на `id` цели; **не** по `linkset` / `multiple`. Лист — любое поле достигнутой карты (в т.ч. multiple / `linkset`). Ключ с точками в filter/sort/select. FROM списка — только своя таблица (EXISTS / подзапрос / догрузка mfv, без JOIN). `select: null` — своя карта без путей. Своё поле `reference` в ряду остаётся `int`. Подробно — [`smarttable-plan-14-reference-path.md`](smarttable-plan-14-reference-path.md). Полный JOIN Bitrix по-прежнему не v1. `GROUP BY` / меры — не слот `getList` (ряд getList = строка карты).

## Агрегат

Пачка `COUNT` / `MAX` / `MIN` / `SUM` по **своей** таблице: `records()->aggregate(AggregateQuery, ttl)`. Ряд результата — ключи `GROUP BY` + агрегатные функции, не сущность карты. `countTotal` на getList — одно число на весь WHERE, не замена. FROM как у getList: своя таблица, JOIN в FROM нет. Функции — `new CountField('unread')` / `new MaxField('id', 'last_id')` в `Dto/`, не колонка карты, не `['fn' => …]`, не Bitrix `ExpressionField`. Порог с другой карты — `SubqueryValue` в операнде фильтра (`getList` и `aggregate`), не JOIN. Промах группы (0 строк) ST не дописывает — репозиторий. Код — [`smarttable-plan-17-aggregate.md`](smarttable-plan-17-aggregate.md). Chat 5 (unread/preview); User `memberCount` — [`user-plan-08-member-count-aggregate.md`](user-plan-08-member-count-aggregate.md).

## Кэш

Кэш **только если** у `getList` / `getById` / `getUnique` / `getFirst` / `aggregate` явно задан TTL. Без TTL запрос в БД. Инвалидация тегов таблицы/поля — **после успешного commit**. `get` живёт на теге `st:{table}:rows` (не на теге стола списков: `add` списки бьёт, чужие get — нет). Delete родителя дополнительно сбрасывает `st:{child}` и `st:{child}:rows` у столов с физическим FK `ON DELETE CASCADE` или `SET NULL` на этот PK (иначе SQL меняет детей в обход PHP). Restrict/`none` — нет. Окно commit→сброс допустимо. `SubqueryValue` в filter: ключ канонизирует объект; теги — стол и поля **той** карты (как путь). `CountField` / `MaxField` в aggregate: ключ канонизирует вид+поле+alias; теги — `group`/filter и аргумент Max/Min/Sum, не alias; `COUNT(*)` не равен `select: null`. `update` бьёт поле, не стол — одного тега чужого стола мало.

Драйвер: модуль **`Core/Cache`** (`ICacheStore`, [`cache-plan-01.md`](cache-plan-01.md), `DEC-081`). `redis` или `file` из `local.php`. TTL слота **1..2592000** с (30 суток), без «навсегда». Redis — основной, file — запасной. Теги списков ST (`st:…`) считает `TableCache`, не сосед. Дырявый **конфиг** кэша — ошибка всегда. Драйвер **отвалился** (I/O): на `debug` — исключение; без `debug` — чтение из БД, запись/сброс кэша пропускается, запрос не падает.

## Индексы

В определении поля: `indexed` и `unique` на **одной** колонке. Составной unique — `defineUniqueKeys()` на definition (класс и словарь, [`smarttable-plan-16-composite-unique.md`](smarttable-plan-16-composite-unique.md)): JSON `st_meta_table.unique_keys`, не флаг поля. Fulltext — хвост. `updateTable` не снимает лишние индексы: leftover UNIQUE продолжает отвергать дубли, пока нет `forceUpdateTable`.

## Языки

`label` закладывается как **ключ сообщения**, не как единственная человеческая строка. Каталог переводов — позже; fallback — имя поля.

## Ошибки

`SmartTableException` — абстрактная база с `getErrorCode()`. Листья, не одна конкретная мега-ошибка:

- соединение: `DatabaseException` → `DbConfigInvalidException`, `DbConnectFailedException`;
- карта: `MapInvalidException`;
- поле: `FieldException` → `FieldInvalidException`, `FieldRequiredException`, `FieldMultipleUnsupportedException`, `UnknownHydratorException`;
- строка: `RowNotFoundException`, `RowWriteFailedException`, `ReferenceConstraintException`, `UniqueConstraintException`;
- схема: `SchemaException` → `TableMissingException`, `TableExistsException`, `SchemaMismatchException`, `DdlFailedException`;
- кэш: `CacheException` → `CacheConfigInvalidException`, `CacheDriverFailedException`;
- транзакция: `TransactionFailedException` (`TRANSACTION_FAILED`). Если TX уже открыта — `transaction()` выполняет работу в ней (без своего commit/rollback).

## Не v1

- Fluent `query()`.
- Полный JOIN Bitrix и runtime-поля. Агрегат своей таблицы — план 17, не getList.
- Поле «файл» (модуль Files).
- Составные **неуникальные** индексы.
- Код `Versioned*` в SmartTable; переименование NAME/колонок. Версионность — [`versioning-plan-01.md`](versioning-plan-01.md).
- События CRUD → EventManager (нужны, подключать осторожно — OPEN).
- Права на словарь — после Auth.
- Админка Vue.
- Установка/обновление набора модулей (Kernel, [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md)).
- Авторазбор datetime из произвольной строки на add.
- decimal/float (деньги) — не v1, `int` до отдельного типа.

## Versioned

Не часть SmartTable. Контракт и нарезка — [`versioning-roadmap.md`](versioning-roadmap.md).

## Нарезка

[`smarttable-roadmap.md`](smarttable-roadmap.md). User закрыт до HTTP. План 15 (BIGINT, cascade) — блокер Auth 1. План 17 (агрегат) — блокер Chat 5. План 18 — блокер Versioning 1. Пункт 11 нарезки ST снят: [`versioning-roadmap.md`](versioning-roadmap.md).
