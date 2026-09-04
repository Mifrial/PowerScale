# План 16 — составной unique

**Статус:** сделано, 2026-09-04. Канон — [`smarttable.md`](smarttable.md). Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Одноколоночный unique — [`smarttable-plan-06-indexes.md`](smarttable-plan-06-indexes.md) (**не** ломать). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Force leftover — [`smarttable-plan-07-force-ddl.md`](smarttable-plan-07-force-ddl.md).

Цель: unique по **двум и более колонкам основной таблицы**. Один контракт для PHP-класса и словаря. Не флаг поля. Не составной неуникальный INDEX. Не fulltext.

Хвост User (`member_key`) — [`user-plan-07-member-unique.md`](user-plan-07-member-unique.md), **после** этого файла. Chat 1 — после User 07 (или хотя бы после 16, без `member_key` на `chat_member`).

## Решения

**Контракт на таблице**, не `FieldSettings::unique`. Одно поле по-прежнему план 6.

```php
protected function defineUniqueKeys(): array
{
    return [
        ['user_id', 'group_id'],
    ];
}
```

Базовый `SmartTableDefinition`: метод есть, default `[]`. `getUniqueKeys()` после `getMap()` → `array<int, array<int, string>>`.

**Имя DDL:** `{table}_{field1}_{field2}_unq` (поля в порядке кортежа; 3+ — все имена через `_`). Длина > 64 / не `^[a-z][a-z0-9_]*$` → `MAP_INVALID` до CREATE.

`IndexSchema::isManagedIndexName` уже матчит `{table}_…_(idx|unq)` — составное имя **попадает** как managed. Если его нет в `plannedIndexNames`, **force снимет только что созданный unique**. `plannedIndexes` сейчас `{field, suffix, unique}` и `createOne(field)` — одно поле. Нужен общий список плановых имён: одноколоночные + кортежи. `assertNames` / `createAll` / `createMissing` / `dropLeftover` читают его. Quality: не раздувать `createOne`; вынести имя кортежа и `unique($columns, $name)`.

**Столкновение имён индексов** → `MAP_INVALID`: поле `a_b` с `unique` даёт `{table}_a_b_unq`, кортеж `['a','b']` — то же имя. Сверять все planned names (поле `_unq`/`_idx` и кортежи).

**Валидация в `getMap()`**, не только в `getUniqueKeys()`. Иначе `createTable` может пройти с кривым `defineUniqueKeys`. Кэш `fieldMap` назначать **после** assert (если throw — кэш пуст). `getUniqueKeys()` зовёт `getMap()`, отдаёт `array<int, array<int, string>>`.

**Типы колонок ключа** — тот же allowlist, что unique на поле (план 6): `string` (maxLength ≤ 255), `int`, `bigint`, `bool`, `datetime`, `reference`. Остальное (`text`/`html`/`json`/`id`/multiple) → `MAP_INVALID`. Слишком длинный InnoDB-ключ (несколько VARCHAR) — `DDL_FAILED`, в PHP байты не считаем.

**MAP_INVALID кортежа:** не массив строк; пустой; один элемент (unique на поле); неизвестное имя; дубль имени в кортеже; два **одинаковых** кортежа (тот же порядок). `['a','b']` и `['b','a']` — два ключа, оба DDL. Поле с `unique` и участие в кортеже — два индекса, ок.

**NULL:** как одноколоночный: NULL в любой колонке ключа не даёт дубль в MySQL. Членство — все required.

**updateTable** — `createMissing` по имени. Лишнее снимает только force. Старый `{table}_member_key_unq` после удаления поля из карты — leftover force (User 7).

**1062** уже `UNIQUE_CONSTRAINT`. `TableRows` не трогать.

**Словарь — тот же контракт, не флаг поля.** Колонка `st_meta_table.unique_keys`: `JsonField`, `default` `[]` (как `settings`, **не** required). Форма `[["user_id","group_id"]]`. Не в `settings` поля. После `installMeta` у старых строк колонка может быть NULL → читать как `[]`, не `MAP_INVALID`.

`RuntimeDefinition($tableName, $fields, $uniqueKeys = [])` → `defineUniqueKeys()` отдаёт третий аргумент. `makeDefinition` / `runtimeDefinition` / `definitionByName` (hop) — одни и те же ключи. Кривой JSON / не список кортежей → тот же `MAP_INVALID`, что у класса. `getMap()` **до** insert в meta.

`ITableCatalog::createTable($name, $fieldSpecs, $uniqueKeys = [])` — третий аргумент **на интерфейсе** (optional). Сейчас 6 public, станет 7; `SmartTableCatalog` (+ ctor, `definitionByName`, `setUniqueKeys`) остаётся ≤10. Писать `unique_keys` в meta **до** физического CREATE. Повтор на сироте (meta есть, физики нет): как сейчас **игнор** `$fieldSpecs` / `$uniqueKeys`, карта из словаря (`createPhysicsFromDictionary`).

`setUniqueKeys(string $tableName, array $uniqueKeys): void` — `getMap()` на текущих полях + новых ключах **до** update строки (иначе словарь отравлен). Нет физики — только JSON (сирота). Есть физика — JSON + `updateTable` (`createMissing`). Снятие кортежа **не** DROP: leftover до force. Каталогу метод force **не** добавлять: `openByName()->schema()->forceUpdateTable()`, как PHP-класс. Не раздувать `IOpenedTable`.

`dropField`: имя в `unique_keys` → `MAP_INVALID` **до** delete строки. Сначала `setUniqueKeys` без поля; leftover индекса снимет force внутри текущего `dropField`.

`installMeta`: `updateTable` на `st_meta_table` добавляет колонку. `insertTable` пишет `unique_keys` (пусто → `[]`).

`MetaFieldDefinition`: `defineUniqueKeys() = [['table_id', 'name']]`. Имя индекса `st_meta_field_table_id_name_unq` (длина ок). Дубль имени при `add` → `UNIQUE_CONSTRAINT`, если обошли `assertNewFieldName` (`MAP_INVALID` на спеке оставить). `installMeta` `createMissing`.

**Не замена `pair_key`.** Нужны колонки на этой таблице. Private-пара — два члена, не две колонки `chat`.

## Todo

- [x] **map** — `defineUniqueKeys` default `[]`; assert в `getMap()` до кэша; `RuntimeDefinition` третий аргумент; `FieldMapTest` отказы.
- [x] **ddl** — обобщить план индексов; столкновение имён `{table}_a_b_unq`; `IndexMysqlTest`: две колонки, дубль add, NULL×2, updateTable createMissing.
- [x] **catalog** — колонка JSON; 3-й аргумент на порте; `setUniqueKeys` (validate до write); сирота игнор 3-го аргумента; `dropField` если поле в ключе; meta `(table_id, name)`; `DictionaryUniqueMysqlTest`.
- [x] **gates** — cs/quality/phpunit suite smarttable.

## Не входит

Составной INDEX без unique. Unique на mfv. ALTER порядка колонок ключа (имя другое = новый ключ, старый leftover на force). Регистрация PHP-классов в словаре. User `member_key` (план User 7). Chat.

## Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `UniqueKeyMap` | `Table/` | кортежи + имена индексов | Blueprint |
| `RuntimeDefinition` | `Table/` | те же кортежи из ctor | SQL |
| `MetaTableDefinition` | `Table/` | колонка `unique_keys` | runtime DDL |
| `IndexSchema` | `Service/` | DDL имени и колонок | insert |
| `ITableCatalog` | `Interface/Service/` | create 3-й аргумент, `setUniqueKeys` | handle |
| `TableRows` | `Service/` | без смены | — |

## Документы захода

этот файл; [`smarttable.md`](smarttable.md) § Индексы; roadmap; [`TR.md`](TR.md); план 6 и 7b «Не входит» → 16.

## Следующий заход

[`user-plan-07-member-unique.md`](user-plan-07-member-unique.md), затем Chat 1.
