# План 7b — runtime-словарь

**Статус:** сделано, 2026-09-01. Канон — [`smarttable.md`](smarttable.md) § Назначение (класс и словарь — одно физическое имя). Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md) пункт 7. DDL — [`smarttable-plan-07-force-ddl.md`](smarttable-plan-07-force-ddl.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: словарь как **второй источник карты**, не второй вид хранения. Мета-таблицы — обычные `SmartTableDefinition` в PHP. Пользовательская runtime-таблица: строки словаря → `RuntimeDefinition` → те же `createTable` / `updateTable` / `forceUpdateTable` / `deleteTable` / строка. `ISmartTableGateway::open` по class-string **не** менять. Переименование нет. Права — после Auth (этот заход без ACL). Кэш — план 8.

`IOpenedTable` не расширять (уже 10 public у handle). Методы каталога — на **каталоге**, не на handle (`createTable` каталога ≠ `IOpenedTable::createTable`).

## Todo

- [x] **meta** — `MetaTableDefinition` → `st_meta_table` (`name` unique required, `label` optional string); `MetaFieldDefinition` → `st_meta_field` (`table_id` reference restrict required на meta_table, `name` required string, `type` required string, `settings` json). Поле `id` в словаре полей **не** хранить: `RuntimeDefinition` всегда добавляет `IdField`. Составной unique `(table_id, name)` — не v1: дубль имени поля в каталоге → `MAP_INVALID`. Порядок полей карты: `getList` meta-полей `sort: id ASC`.
- [x] **runtime-def** — `RuntimeDefinition extends SmartTableDefinition`, конструктор `(string $tableName, array $fields)`: `$fields` — уже собранные `BaseField[]` **без** `IdField`. Каталог: assembler → `new RuntimeDefinition`. `defineFields()`: первым `new IdField()`, затем `$fields`. Assembler `id` и `type=id` не эмитит (`MAP_INVALID`). `gateway->open(RuntimeDefinition::class)` → `MAP_INVALID` (обязательный ctor, как сейчас Reflection). Сборка — `FieldSpecAssembler` (Service/, `new`, не порт). Конструктор `ReferenceField(..., class-string)` **не** менять (`assertTargetDefinition` + `new $class` в `targetTableName`). Словарь — `ReferenceField::forTable(...)`: отдельный путь, физическое имя, **без** `class_exists` / `new $class`; `targetTableName()` отдаёт это имя. `onDelete` по умолчанию `restrict`.
- [x] **catalog** — порт `ITableCatalog` в `Interface/Service/` (как gateway; не `ISmartTable*`). Методы: `installMeta`, `openByName`, `createTable`, `addField`, `dropField`, `dropTable`. В карту портов контейнера. Не зависит от gateway. Schema/rows/list без состояния: достаточно одного адаптера соединения; общий helper фабрик/Harness (не третий публичный порт, не `===` экземпляров). Meta: `new OpenedTable(new MetaTableDefinition(), …)`. Имена `st_meta_table` / `st_meta_field` и невалидное физ. имя в `createTable` → `MAP_INVALID`. Пустой `fieldSpecs` (только `id`) допустим.
- [x] **ddl-flow** — `installMeta`: сначала `st_meta_table`, потом `st_meta_field`; нет → `createTable`, есть → `updateTable` (не ловить `TableExists` пачкой). Каталог `createTable`: см. конфликт имён и сироту. `addField`: нет строки словаря или нет физики → `TABLE_MISSING` **до** insert; дубль имени / `id` → `MAP_INVALID`; insert + `updateTable`. `dropField`: см. порядок (после удаления строки словаря — **новый** handle). `dropTable`: физики нет — только строки словаря; физика есть — `deleteTable` (входящий FK → `DDL_FAILED`, словарь не трогать), затем поля, затем строка таблицы. `openByName`: нет строки словаря → `TABLE_MISSING`; физики может не быть.
- [x] **tests-gates** — MySQL skip как ping; runtime `st_dict_*`; cs/quality/phpunit. Канон: runtime по имени через словарь.

## Не входит

Кэш. Накат схемы репозитория (не этот модуль). Генерация PHP-файлов. ACL. Админка. Переименование. ALTER TYPE. Составной unique. Регистрация всех PHP-классов таблиц в словаре. `open(class)` ищет словарь. LIKE по mfv. Подпапки `Service/`. Молчаливый ingest «физика есть, строки словаря нет». Hydrator JSON-поля в словаре (код в реестре).

## Зачем отдельный порт

Gateway — open class-string + transaction. Каталог — мета и runtime. Сосед с `open(UserTable::class)` словарь не тянет. Handle не знает, откуда карта. Каталог не зовёт `gateway->open(RuntimeDefinition::class)`.

## Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `MetaTableDefinition` / `MetaFieldDefinition` | `Table/` | meta map | SQL |
| `RuntimeDefinition` | `Table/` | имя + поля из конструктора | SQL, словарь |
| `FieldSpecAssembler` | `Service/` | спека → `BaseField` без `IdField` | OpenedTable, Schema, HydratorRegistry |
| `ITableCatalog` | `Interface/Service/` | install / openByName / create / addField / dropField / dropTable | Query Builder |
| `SmartTableCatalog` | `Service/` | строки meta + assembler + OpenedTable | HTTP, Schema Builder, сборка ColumnMeta |
| `MfvSchema` | `Service/` | `dropFieldStorage` одного sidecar | LIKE |
| `ReferenceField` | `Field/` | `forTable` (имя); ctor class-string как сейчас | каталог |
| `ISmartTableGateway` | `Interface/Service/` | как сейчас | `openByName` |

`SmartTableDefinition`: `tableName` / `defineFields` не делать public setter.

## dropField и multiple

7a не снимает mfv поля, которого уже нет в карте. Карта кэшируется в definition handle: `force` на старом handle поле ещё видит и колонку не снимет.

Порядок:

1. `openByName` (поле ещё в карте);
2. если `multiple` — `MfvSchema::dropFieldStorage($definition, $field)` (имя через `tableName`, `DdlFailedException`; не LIKE; каталог Schema Builder не зовёт);
3. удалить строку словаря;
4. снова `openByName` (карты без поля) → `forceUpdateTable` (колонка / индекс / FK).

`dropField('id')` или неизвестное имя → `MAP_INVALID`.

## Reference в словаре

В PHP-классе `new ReferenceField(..., UserTable::class)` без изменений (цель — instantiable definition без обязательного ctor). В спеке `target` = физическое имя → только `forTable`. `assertTargetsExist` — `hasTable` (таблица с тем же именем годится, PHP-класс цели не нужен). Self-ref: `target` = своё `name`; CREATE как у self-ref в PHP-классе. `RuntimeDefinition::class` в ctor reference PHP-класса нельзя.

## Спека поля

```
name: string
type: string | int | bool | datetime | text | html | json | reference
required, multiple, indexed, unique, label, default — в FieldSettings::fromOptions
maxLength — string (конструктор StringField, по умолчанию 255)
min, max — int
json — без hydrator (`null`); ключ `hydrator` в спеке → `MAP_INVALID`
target, onDelete — только reference (`forTable`; нет `target` → `MAP_INVALID`; onDelete по умолчанию restrict)
```

Нет `id` в спеке (`type` `id` → `MAP_INVALID`). Неизвестный `type` → `MAP_INVALID`. Дубли имён в одном массиве `fieldSpecs` → `MAP_INVALID` до insert. Флаги multiple/index — те же отказы `getMap`, что у карты PHP-класса. `fromOptions` лишние ключи молча отбрасывает: неизвестный ключ спеки ловит assembler (`MAP_INVALID`), не FieldSettings. `maxLength` только у `string`; `min`/`max` только у `int`; `target`/`onDelete` только у `reference` — иначе `MAP_INVALID`.

`settings` в `st_meta_field` — JSON всей спеки без `name`/`type` (roundtrip addField).

## Конфликт имён и сирота

Одно физическое имя. Нет строки словаря, физика есть (в т.ч. таблица с PHP-классом) → каталог `createTable` → `TableExistsException`. `open(Class)` словарь не читает. Две карты на одном имени не ищем сканом классов.

Строка meta есть, физики нет (CREATE упал после insert): повтор каталожного `createTable` — физический `createTable`, без второго insert (`name` unique). `openByName` в этом состоянии отдаёт handle; `IOpenedTable::createTable()` тоже поднимает стол.

DDL не в одной транзакции с insert meta.

## Фасад каталога

```
installMeta(): void
openByName(string $tableName): IOpenedTable
createTable(string $tableName, array $fieldSpecs): IOpenedTable
addField(string $tableName, array $fieldSpec): void
dropField(string $tableName, string $fieldName): void
dropTable(string $tableName): void
```

Не fluent. Не `dropColumn` на handle.

`installMeta`: `st_meta_table` затем `st_meta_field`; нет таблицы → `createTable`, есть → `updateTable`.

Нет meta (забыли install) → `TABLE_MISSING`.

## Тесты

Только MySQL, skip как ping. Teardown: `st_dict_*` (и их mfv), затем `st_meta_field`, `st_meta_table`.

- `installMeta` дважды идемпотентен.
- create + openByName + add/get строки.
- addField виден в get; dropField скаляра — колонки нет (force на **новом** handle).
- dropField multiple — нет `*_mfv_*`.
- string `maxLength` → VARCHAR этой длины.
- неизвестный `type` → `MAP_INVALID`.
- ключ `hydrator` в спеке json → `MAP_INVALID`.
- пустой `fieldSpecs` (только системный `id`).
- невалидное имя таблицы → `MAP_INVALID`.
- addField в неизвестную таблицу → `TABLE_MISSING`.
- dropTable: физика и mfv карты исчезают; openByName → `TABLE_MISSING`.
- дубль `st_meta_table.name` → `UNIQUE_CONSTRAINT`; дубль имени поля в каталоге → `MAP_INVALID` (составного unique нет).
- каталог `createTable` при живой таблице с PHP-классом с тем же именем → `TABLE_EXISTS`.
- `createTable` с именем `st_meta_table` / `st_meta_field` → `MAP_INVALID`.
- сирота meta без физики: повтор `createTable` каталога поднимает стол; `dropTable` без физики чистит только словарь.
- `gateway->open(RuntimeDefinition::class)` → `MAP_INVALID`.
- reference runtime→runtime restrict; runtime→живая таблица с PHP-классом по физ. имени.
- dropTable parent при живом child → `DDL_FAILED`, строка словаря parent на месте.

Ворота: `cs-check`, `quality`, `phpunit`.

## Документы захода

- этот файл; [`TR.md`](TR.md); пункт 7 [`smarttable-roadmap.md`](smarttable-roadmap.md);
- [`smarttable.md`](smarttable.md) — runtime по имени через словарь.

## Следующий заход

План 8: тегированный кэш TTL. Накат репозитория — не SmartTable (словарь сам себе DDL).
