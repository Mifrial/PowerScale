# План 5b — reference

**Статус:** сделано, 2026-09-01. Канон — [`smarttable.md`](smarttable.md) § типы и § Reference и удаление. Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Multiple — [`smarttable-plan-05-multiple.md`](smarttable-plan-05-multiple.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

**Дополнение:** `onDelete` + `cascade` и ширина целого — [`smarttable-plan-15-bigint.md`](smarttable-plan-15-bigint.md). Канон — актуальный [`smarttable.md`](smarttable.md), не «CASCADE нет» ниже как запрет навсегда.

Цель: тип `reference` — колонка `INT` (id другой Basic-таблицы), PHP `int|null`. Restrict по умолчанию. `SET NULL` только если поле не `required`. Сигнатуры `IOpenedTable` не расширять.

## Todo

- [x] **field** — `ReferenceField`: `type() === 'reference'`, `ColumnMeta INT` signed как `IdField`; cast/extract/hydrate целое ≥ 1 или null; цель — class-string definition; `onDelete` `restrict` | `setNull` | `none`. Невалидные пары и цель — **в конструкторе** (`MAP_INVALID`), не откладывать на `getMap`.
- [x] **ddl** — колонка через существующий INT; FK `ALTER` после CREATE/mfv; цель нет в БД → `TABLE_MISSING`; CASCADE нет; signed INT = `id`.
- [x] **write-filter** — без JOIN на родителя; translator: только MySQL `1451`/`1452` → `REFERENCE_CONSTRAINT`; binder: `reference` как `int` (включая `><` и IN); sort по колонке можно.
- [x] **tests-gates** — юнит ctor/карты; MySQL parent/child + self-ref две строки; skip как ping; cs/quality/phpunit; канон § Ошибки: ветка **row**.

## Не входит

Multiple+reference, индекс/unique из `FieldSettings` (план 6; индекс под FK — побочный InnoDB), `ON DELETE/UPDATE CASCADE`, `forceUpdateTable` / `deleteTable`, JOIN родителя в get/getList, hydrator объекта вместо int, цикл A↔B на карте **и взаимные FK двух таблиц** (этим порядком CREATE+FK сразу не поднять), Versioned, кэш, подпапки `Service/`. Не добавлять `reference` в allow-list multiple (`assertMultipleAllowed` уже откажет: типа нет в `string|int|bool|datetime`).

## Зачем не вместе с multiple и не с индексами

Multiple — sidecar той же таблицы. Reference — колонка + FK на **другую** (или self) таблицу. Индексы из определения — все типы, отдельный DDL.

## Слои

Плоский `Service/`. Дробить по задаче.

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `ReferenceField` | `Field/` | цель, `onDelete`, scalar int/null, `MAP_INVALID` в ctor | Blueprint FK |
| `SmartTableDefinition` | `Table/` | без новой ветки multiple+reference (уже закрыто типом) | CREATE TABLE |
| `ReferenceSchema` | `Service/` | `constraintName`, `getForeignKeys`, `foreign(..., $name)` restrict/setNull | insert, filter |
| `TableSchema` | `Service/` | колонка INT; после mfv — `ReferenceSchema` | значения |
| `DriverErrorTranslator` | `Service/` | `errorInfo[1]` ∈ {1451, 1452} → лист | unique `1062`, прочий 23000 |
| `ListFilterBinder` | `Service/` | `'reference'` рядом с `'int'` | JOIN |
| `TableRows` / `TableList` | `Service/` | без спецветки | SELECT существования родителя |
| `IOpenedTable` | `Interface/` | только `@throws` | новые методы |

`ReferenceSchema` stateless, `new` на том же адаптере, не порт. `OpenedTable` без нового dep.

Имя целевой физической таблицы: после `class_exists` + `is_subclass_of(SmartTableDefinition::class)` — `(new $class())->getName()`. Не SQL-строка в конструкторе соседа. Не локатор.

**Запрет:** при сборке своей карты **не** вызывать `getMap()` цели (self-ref → рекурсия). Только `getName()` / `tableName()`.

PHP Error на неизвестный class-string недопустим: нет класса / не definition → `MAP_INVALID` в ctor.

## Контракт поля

```php
new ReferenceField(
    'parent_id',
    FieldSettings::fromOptions(['required' => true]),
    ParentTable::class,
    'restrict',
);
```

Четвёртый аргумент по умолчанию `'restrict'`. `onDelete` не класть в `FieldSettings`.

| | |
|---|---|
| PHP in/out | `int` ≥ 1 или `null` (смысл id, не min/max IntField соседа) |
| БД | signed `INT` **как** `IdField` (`integer(..., false)`), не unsigned |
| `type()` | `reference` |
| default | scalar int/null как у int |

`onDelete`:

| Значение | FK | Удаление родителя |
|---|---|---|
| `restrict` (default) | да, `ON DELETE RESTRICT` + `ON UPDATE RESTRICT` | отказ, child на месте |
| `setNull` | да, `ON DELETE SET NULL` + `ON UPDATE RESTRICT` | child.`field` := NULL |
| `none` | нет | висячий id |

`MAP_INVALID` в **конструкторе** поля:

- `required` и `setNull`
- `required` и `none`
- `multiple() === true`
- цель не class-string / нет класса / не subclass definition
- `onDelete` не из тройки

Имя FK / `getName()` цели: шаблон `[a-z][a-z0-9_]*`, длина ≤ 64 — в `ReferenceSchema` (как mfv), не дублировать regex в поле, если имя цели уже проверяет `getName()`.

Канон: `none` на required нельзя; SET NULL на required нельзя.

Можно наследовать `IntField` и переопределить `type()` + min 1, либо отдельный класс с тем же hydrate строк PDO (`'3'`). Не оставлять `type() === 'int'`.

## Фасад

Сигнатуры `IOpenedTable` не менять. Дополнить `@throws`:

- `createTable` / `updateTable`: `TableMissingException` (нет **цели** FK);
- `add` / `update` / `delete`: `ReferenceConstraintException`.

## DDL

`createTable`: основная без multiple → mfv → FK для `onDelete !== none`.

Нет `hasTable(цель)` → `TABLE_MISSING` до `ALTER`. Self-reference: своя таблица уже создана, FK тем же шагом `ALTER`.

Колонка: тот же `blueprint->integer($name)` signed, что у прочих INT / как `id` без autoincrement. Иначе errno 3780.

`updateTable`: колонки (reference — обычный недостающий INT) → mfv → недостающие FK. Лишние FK не удалять. Колонка есть, constraint нет → всё равно создать FK. Смена `onDelete` в PHP при живом constraint FK не переписывает и не снимает (как лишние колонки до `forceUpdateTable`).

Имя constraint: `{table}_{field}_fk` (`ReferenceSchema::constraintName`). Ставить явно: `foreign($field, $constraintName)->references('id')->on($targetName)`.

Есть ли FK: Illuminate Schema `getForeignKeys($table)` (11.x), сравнение **имени**. Не парсить `SHOW CREATE TABLE`.

CASCADE ни delete, ни update. Implicit index InnoDB — не план 6.

FK не внутри первого `Schema::create` callback.

## Запись и фильтр

Существование родителя не SELECT в PHP. `none` — висячий id сознательно.

`delete()` parent + restrict: 1451, `writeAtomic` rollback (в т.ч. mfv родителя). Child не каскадить.

`update()` child на несуществующий id: 1452, тот же лист.

get/getList: колонка в основном SELECT, hydrate int/null. Не строка родителя.

Binder: `'reference'` вместе с `'int'` / `'datetime'` (`=`, `!=`, `<`, `>`, `<=`, `>=`, `><`). `%` → `MAP_INVALID`. `@` — уже отказ на не-multiple. List → IN, не equality multiple. `parent_id => []` — пустой IN, как `id`, `MAP_INVALID`.

Sort по колонке — обычный `orderBy`.

## Ошибки

Лист: `Exception/Row/ReferenceConstraintException`, код `REFERENCE_CONSTRAINT`. Не `Field/`. Конструктор: `?Throwable $previous = null`, как у `ROW_WRITE_FAILED`.

Канон [`smarttable.md`](smarttable.md) § Ошибки **в этом заходе** дополняется веткой row.

`DriverErrorTranslator`: по цепочке `PDOException::$errorInfo[1]` (int/numeric string). **Только** `1451` (delete/update родителя, есть ссылки) и `1452` (insert/update child, нет родителя) → `REFERENCE_CONSTRAINT`. SQLSTATE `23000` **без** этих кодов (в т.ч. unique `1062`) → по-прежнему `ROW_WRITE_FAILED`. Не разбирать текст сообщения. План 6 unique не ремапит 1451/1452.

## Тесты

Только MySQL, skip как ping. Teardown: child, потом parent.

Фикстуры: parent (id+title); child `parent_id` restrict required; optional setNull; optional none; ctor required+setNull / required+none / multiple — `MAP_INVALID`; self-ref **optional** restrict.

MySQL:

- create parent, create child; колонка signed INT; constraint `{child}_{field}_fk`.
- create child без parent → `TABLE_MISSING`.
- add child с живым id; get int.
- add / **update** child с несуществующим id → `REFERENCE_CONSTRAINT`.
- delete parent при живом child → `REFERENCE_CONSTRAINT`; child на месте.
- delete child, затем parent — ок.
- setNull: delete parent → child.`parent_id` null.
- none: delete parent → child.get прежний int, родителя нет.
- updateTable: dropForeign в тесте (Illuminate), колонка остаётся → `updateTable` создаёт constraint.
- getList `parent_id => [1, 2]` — IN; sort `parent_id` asc.
- self-ref: **две** строки A→B; delete B — отказ; delete A, затем B — ок. Не строить сценарий «строка ссылается на себя, потом delete» (InnoDB 1451 на self-loop). Optional self-loop: update на свой id допустим; delete — сначала null, потом delete.

Юнит: `type() === 'reference'`; ctor отказы; binder `%parent_id` без SQL.

Регресс: CRUD/getList без reference, mfv.

Ворота: `cs-check`, `quality`, `phpunit`.

## Документы захода

- этот файл (ссылки в [`TR.md`](TR.md) и [`smarttable-roadmap.md`](smarttable-roadmap.md) уже есть);
- [`smarttable.md`](smarttable.md) § Ошибки — ветка row, включая `REFERENCE_CONSTRAINT`.

## Следующий заход

План 6: index/unique из определения; unique `1062` не смешивать с 1451/1452. Multiple+reference — отдельный хвост. Кэш — план 8.
