# План 7a — forceUpdateTable и deleteTable

**Статус:** сделано, 2026-09-01. Канон — [`smarttable.md`](smarttable.md) § Назначение (класс опережает схему) и leftover у индексов/FK. Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md) пункт 7. Индексы — [`smarttable-plan-06-indexes.md`](smarttable-plan-06-indexes.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: деструктивный DDL на **одной** таблице, описанной PHP-классом. `updateTable` по-прежнему только добавляет. Словарь и runtime-создание таблиц — **не** этот заход (7b). Переименование колонок/физического имени — нет.

`IOpenedTable` **расширяется** двумя методами (новые, старые сигнатуры не менять).

## Слои (как сделано)

`TableSchema` — оркестратор: `createTable` / `updateTable` / `forceUpdateTable` / `deleteTable`. Чертёж колонок — `ColumnSchema`. Leftover индексов/FK/mfv — `IndexSchema` / `ReferenceSchema` / `MfvSchema`. Handle знает **один** DDL-объект, без пятого аргумента и без `SchemaForce`.

`OpenedTable` — 10 public (лимит не превышен). `IOpenedTable` — 9. `phpcs:ignore` не ставили.

## Todo

- [x] **facade** — `forceUpdateTable(): void`, `deleteTable(): void` на `IOpenedTable` / `OpenedTable`; делегируют в `TableSchema`. Нет своей таблицы → `TABLE_MISSING`. Force `@throws`: `TableMissingException`, `SchemaMismatchException` (нет `id`, как update), `MapInvalidException`, `DdlFailedException`. Delete `@throws`: `TableMissingException`, `DdlFailedException` (без проверки `id`).
- [x] **columns** — `ColumnSchema`: то, что сейчас private колонок в `TableSchema` (create main / add missing / define / sqlType), плюс drop leftover колонок (не `id`).
- [x] **force** — `TableSchema::forceUpdateTable`: как `updateTable` (колонки → индексы → mfv → FK), затем prune: extra mfv → extra FK этой таблицы → extra `_idx`/`_unq` → extra columns. Типы не сравнивать, ALTER TYPE нет.
- [x] **delete** — свои FK (в т.ч. leftover `_fk`, self-ref до DROP) → mfv **только полей текущей карты** → `drop` основной. Входящие FK детей не снимать: отказ драйвера → `DdlFailedException`. LIKE по `{table}_mfv_%` нет. Сирота sidecar после удаления поля из PHP без force остаётся.
- [x] **tests-gates** — MySQL skip как ping; `ForceDdlMysqlTest`; cs/quality/phpunit. Канон: force снимает leftover колонок, индексов, FK и mfv.

## Не входит

Словарь (мета-таблицы, `open` по имени без PHP-класса, API «создать поле»). Кэш. Журнал наката репозитория (не SmartTable). ALTER TYPE / смена VARCHAR length. Переименование. CASCADE drop соседей. Fulltext. Подпапки `Service/`. Админка. `dropColumn` с фасада. Снятие InnoDB-индекса с суффиксом `_fk` после `dropForeign`, если колонка в карте осталась (`onDelete === none`).

## Зачем не вместе со словарём

Словарь — второй источник **карты** (те же `getMap` / `createTable`). Drop leftover нужен таблице с PHP-классом уже сейчас (планы 5b/6 откладывали снятие на force). Runtime-таблица всё равно вызовет те же `forceUpdateTable` / `deleteTable`.

## Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `IOpenedTable` | `Interface/` | два метода | SQL |
| `OpenedTable` | `Service/` | делегирует в `TableSchema` | SQL, новый ctor-arg |
| `TableSchema` | `Service/` | create / update / force / delete | match sqlType, insert |
| `ColumnSchema` | `Service/` | колонки основной таблицы | индексы, FK, mfv, getList |
| `IndexSchema` | `Service/` | leftover `_idx`/`_unq`: `dropUnique` / `dropIndex` | FK, колонки |
| `ReferenceSchema` | `Service/` | leftover и все свои `_fk`: `dropForeign` | mfv |
| `MfvSchema` | `Service/` | leftover и drop sidecar по карте | колонки основной |

Хелперы stateless, `new` на адаптере внутри `TableSchema`, не порты. Шаблоны имён не копировать.

Порядок **forceUpdateTable**:

1. нет таблицы → `TABLE_MISSING`; нет `id` → `SchemaMismatchException`;
2. `assertTargetsExist` + `assertNames` как у `updateTable`;
3. add missing columns → missing indexes → missing mfv → missing FK;
4. prune: extra mfv → extra FK этой таблицы → extra `_idx`/`_unq` → extra columns.

Mfv до колонки: sidecar текущего не-multiple поля снять до DROP колонки с тем же именем (поле стало multiple или скаляр после multiple). Extra FK до extra column.

Unique сняли, `indexed` оставили: сначала может появиться `_idx` при живом `_unq`, потом prune снимет `_unq`. Обратно — симметрично. Оба флага: в карте только `_unq`, leftover `_idx` снять.

`onDelete` → `none`, поле на месте: колонка остаётся, снимается `_fk`.

Порядок **deleteTable**: FK этой таблицы из `getForeignKeys` с суффиксом `_fk` (включая leftover) → mfv полей **текущей** карты → `drop` основной. Тест: delete при живом multiple дропает известный sidecar; orphan mfv без force не ищем.

DDL MySQL = неявный commit. Откат force/delete в `transaction()` не тестировать.

## Фасад

```
forceUpdateTable(): void
deleteTable(): void
```

Не `dropColumn` по одному имени снаружи. Не fluent. Не `ReferenceConstraintException` / `UniqueConstraintException` (это DML). Отказ DROP из‑за чужого FK → `DDL_FAILED`. `TableExistsException` нет. Delete без колонки `id` всё равно дропает стол.

## Контракт prune

Лишняя **колонка**: имя в БД, нет в `getMap()` как обычное (не multiple) поле. Не `id`.

Лишний **индекс**: имя `{table}_{field}_idx` или `_unq`, и карта это имя не требует (поля нет или флаг снят). `PRIMARY` и имена `*_fk` здесь не дропать.

Лишний **FK**: имя `{table}_{field}_fk`, поля нет или `onDelete === 'none'`.

Лишний **mfv**: поле есть в **текущей** карте и `multiple() === false`, sidecar `{table}_mfv_{field}` существует — снять. Поля, которого уже нет в PHP, sidecar не ищем (LIKE нет): сирота до явного DROP вручную. Невалидное имя `{table}_mfv_{field}` (длина > 64) — skip, не `MAP_INVALID` (таким DDL sidecar не создавался).

Чужие KEY с другими именами не трогать. Не переименовывать. DROP колонки с данными — ок.

## Тесты

Только MySQL, skip как ping. Класс `ForceDdlMysqlTest`, свой teardown `dropIfExists`.

Reuse: `MiniTitleNoteTable` → `MiniTitleTable` на `st_crud_mini` (leftover колонка). Unique / index / FK / mfv — **новые** физ. имена `st_force_*`, не `st_idx_unique` / `st_ref_child` / `st_mfv_probe`.

- leftover `_unq`: дубль add проходит; leftover `_idx` снят;
- unique → indexed и indexed → unique;
- leftover mfv после снятия multiple с карты;
- leftover колонка+FK: restrict-поле убрали, parent жив;
- restrict → none, поле на месте, `_fk` нет;
- `deleteTable`: таблица и mfv карты исчезают; повтор → `TABLE_MISSING`;
- self-ref на `st_force_*` (не `SelfRefTable` / `st_ref_self`): `deleteTable` проходит (свои FK до DROP);
- child жив, delete **parent** table → `DDL_FAILED`, child на месте;
- `forceUpdateTable` / `deleteTable` без таблицы → `TABLE_MISSING`;
- `updateTable` leftover **не** снимает.

Ворота: `cs-check`, `quality`, `phpunit`.

## Документы захода

- этот файл; ссылка в [`TR.md`](TR.md) и пункт 7 [`smarttable-roadmap.md`](smarttable-roadmap.md);
- [`smarttable.md`](smarttable.md) — force снимает leftover колонок/индексов/FK/mfv.

## Следующий заход

**7b:** мета-таблицы словаря как обычные `SmartTableDefinition` в PHP; одно физическое имя с классом; API создать/удалить таблицу и поле через словарь + `createTable`/`force`/`delete`. `open` по class-string остаётся; runtime-definition — отдельный вход, не ломая Reflection `open`. Кэш — план 8.
