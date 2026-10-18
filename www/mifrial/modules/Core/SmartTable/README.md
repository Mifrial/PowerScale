# Core\SmartTable

Карта входа: как пользоваться серверным доступом к данным. Описывает «как», не
«что устроено» — устройство в каноне, см. ниже. Пути даны от корня репозитория.

**Роль:** единственная абстракция доступа модулей к MySQL. Даёт декларативное
описание таблицы PHP-классом (поля, id, индексы, unique, reference, linkset,
multiple, datetime), компилирует DTO-запросы в SQL через Illuminate Query/Schema
Builder и прячет их внутри `Service/Query/*` и `Service/Schema/*`. Прикладной
репозиторий SQL не пишет.

**Канон:** [`smarttable.md`](../../../../../docs/tr/smarttable.md) (`DEC-078`);
нарезка — [`smarttable-roadmap.md`](../../../../../docs/tr/smarttable-roadmap.md).
Стек `DEC-078`: внутри `illuminate/database`, без Eloquent, Capsule и Laravel.

**Сверено по коду:** 2026-10-18.

## Ключевые типы

| Тип | Путь | Зачем |
|---|---|---|
| `ISmartTableGateway` | `Interface/Service/ISmartTableGateway.php` | `open(class)`, `transaction()`, `isTransactionOpen()` |
| `IOpenedTable` | `Interface/Service/IOpenedTable.php` | Сумка: `schema()`, `records()`, `conditionalRecords()` |
| `IOpenedSchema` | `Interface/Service/IOpenedSchema.php` | DDL одной карты |
| `IOpenedRecords` | `Interface/Service/IOpenedRecords.php` | CRUD, `getList`/`getUnique`/`getFirst`, `aggregate` |
| `IConditionalOpenedRecords` | `Interface/Service/IConditionalOpenedRecords.php` | `updateConditional()`, `getCurrentById()` (CAS) |
| `ITableCatalog` | `Interface/Service/ITableCatalog.php` | `openByName(физическое имя)`, словарь метатаблиц |
| `IDatabaseConnection` | `Interface/Service/IDatabaseConnection.php` | Порт соединения (`illuminateConnection()`) |
| `SmartTableDefinition` | `Table/SmartTableDefinition.php` | База определения: `tableName()`, `defineFields()`, `defineUniqueKeys()` |
| `IdField` / `IntField` / `StringField` / `TextField` / `HtmlField` | `Field/*.php` | Скалярные типы; `IdField::big()`, `IntField` с шириной |
| `DateTimeField` / `BoolField` / `JsonField` | `Field/*.php` | Остальные типы |
| `ReferenceField` / `LinkSetField` | `Field/*.php` | Ссылка на id цели; набор ссылок 1:N |
| `MultipleList` | `Field/MultipleList.php` | Представление multiple-значения |
| `FieldSettings` | `Dto/FieldSettings.php` | `required`, `unique`, `indexed`, `default`, ширина строки |
| `DateTimeNow` | `Value/DateTimeNow.php` | Sentinel «поставить `DateTime::now()`» |
| `ListQuery` | `Dto/ListQuery.php` | filter, sort, limit, offset, countTotal, select |
| `FilterGroup` / `FilterCondition` | `Dto/FilterGroup.php`, `Dto/FilterCondition.php` | Дерево фильтра |
| `AggregateQuery` + `CountField`/`SumField`/`MinField`/`MaxField`/`SubqueryValue` | `Dto/*.php` | `GROUP BY`-агрегат |
| `ListResult` / `AggregateResult` / `CacheHit` | `Dto/*.php` | Результат и признак попадания в кэш |
| `ConditionalCas` | `Dto/ConditionalCas.php` | Поле CAS и ожидаемое значение |
| `IFieldHydrator` / `HydratorRegistry` | `Interface/Field/IFieldHydrator.php`, `Service/HydratorRegistry.php` | Кастомная гидратация значения поля |

## Как пользоваться

### 1. Объявить таблицу

```php
final class ChatTable extends SmartTableDefinition
{
    protected function tableName(): string { return 'chat'; }

    protected function defineFields(): array
    {
        return [
            IdField::big(),
            new StringField('type', FieldSettings::fromOptions(['required' => true])),
            new DateTimeField('created_at', FieldSettings::fromOptions([
                'required' => true,
                'default' => DateTimeNow::instance(),
            ])),
            new ReferenceField(
                'user_id',
                FieldSettings::fromOptions(['required' => false]),
                UserTable::class,
                'cascade',
            ),
        ];
    }

    protected function defineUniqueKeys(): array
    {
        return [['user_id', 'type']];
    }
}
```

Reference и linkset — это **поля** внутри `defineFields()`, отдельных методов
`references()`/`linksets()` нет. Образцы:
`modules/Messages/Chat/Table/ChatTable.php`,
`modules/Core/Auth/Table/AuthSessionTable.php`.

### 2. Открыть таблицу

```php
$records = $gateway->open(LogTable::class)->records();   // по классу карты
$schema  = $gateway->open(LogTable::class)->schema();    // тот же карты DDL
$records = $catalog->openByName('log')->records();       // из словаря
```

`records()` и `schema()` — разные порты. Репозиторий получает `records()` с
фабрики модуля, класс схемы — `schema()`. Сосед прикладного модуля `open` не
зовёт. Реальный образец:
`modules/Core/Logger/Service/LoggerPortFactory.php` (`logRepository()`).

### 3. Читать список

```php
$result = $records->getList(new ListQuery(
    new FilterGroup('AND', [
        new FilterCondition('game_id', '=', $gameId),
    ]),
    ['id' => 'ASC'],   // sort
    500,               // limit
    $offset,           // offset
    false,             // countTotal
    null,              // select: null — вся карта
), $cacheTtl);         // null — всегда БД

foreach ($result->rows() as $row) { /* ... */ }
$result->total();      // если countTotal = true
```

Порядок параметров `ListQuery`: `filter, sort, limit, offset, countTotal, select`.

### 4. Писать и транзакции

```php
$records->add(['type' => 'private']);          // int id
$records->addMany([$rowA, $rowB]);             // list<int> id
$records->update($id, ['type' => 'group']);
$records->delete($id);

$gateway->transaction(function () use ($records): void {
    // если TX уже открыта, работа идёт в ней без своего commit/rollback
});
```

### 5. CAS

```php
$conditionals = $gateway->open(GameTable::class)->conditionalRecords();
$ok = $conditionals->updateConditional($rowId, new ConditionalCas('cas', $expected), $values);
```

### 6. Агрегат

```php
$result = $records->aggregate(new AggregateQuery(
    $filter, $groupFields, [new CountField('unread')], 500,
));
```

## Инварианты

- **Никакого SQL в репозиториях.** Query/Schema Builder живёт только в
  `Service/Query/*` и `Service/Schema/*`.
- `getList` limit — **1..10000** (`ListQuery::MAX_LIMIT`); `addMany` — до 10000
  строк; агрегат групп — **1..500**. Курсора у `getList` нет: «старше/новее»
  делается фильтром `id <`/`id >` + sort + limit.
- `getUnique(ListQuery, ttl)` — ровно одна строка (внутри limit 2); две и более
  → `MAP_INVALID`. `getFirst(ListQuery, ttl)` — первая строка; нужен filter или
  sort. Оба не принимают `offset` и `countTotal`.
- `multiple` допустим для string/int/bigint/bool/datetime (string ≤255), хранится
  в sidecar `{table}_mfv_{field}` с индексом `(owner_id, value)`. Фильтры только
  `@` (contains) и `=` (равенство множеств). Сортировка по multiple —
  исключение. `addMany` с multiple запрещён (`MAP_INVALID`).
- `linkset` — то же хранилище, но цель чужая; **не** даёт hop.
- Hop в `getList` — только по `reference` на `id` цели. Ключ с точками в
  filter/sort/select; лист — любое поле достигнутой карты. FROM — только своя
  таблица, без JOIN.
- `onDelete` у `reference`: `restrict` (по умолчанию), `setNull`, `none`,
  `cascade`. `required` + `none` нельзя; `setNull` при `required` нельзя;
  `required` + `cascade` можно. `cascade` на карте несовместим с любым
  `multiple`. Своё поле `reference` в ряду остаётся `int`.
- `defineUniqueKeys()` — составной unique, каждый кортеж ≥2 полей; допустим для
  string/int/bigint/bool/datetime/reference (не для json/text/html/mfv/id).
- Кэш — **только** если TTL задан явно у `getList`/`getById`/`getUnique`/
  `getFirst`/`aggregate`. TTL — 1..2592000 (30 суток), значения из
  `ICacheStore::MAX_TTL_SECONDS`. Теги: `st:{table}`, `st:{table}:{field}`,
  `st:{table}:rows`. Инвалидация — после успешного commit.
- `datetime` хранится как unix UTC (`INT`). `default => DateTimeNow::instance()`
  подставляет `DateTime::now()` только на `add` без ключа; `update` без ключа
  дату не трогает. Явный `null` при `required` — ошибка, не «сейчас».
- `updateTable()` добавляет недостающее, но не удаляет leftover; снять лишнее —
  `forceUpdateTable()` (снимает колонки, индексы, FK и mfv). `createTable()` при
  существующей таблице → `TABLE_EXISTS`, а не ветка установки.
- SmartTable **не** накатывает DDL чужих модулей: это Kernel
  (`ModuleSetupRunner` по `IModuleSetup::getTableClasses()`).
- CAS: `ConditionalCas(casField, expected)` — `casField` скалярный `IntField` и
  не может лежать в `values`; 0 изменённых строк → `false`, больше одной →
  `RowWriteFailedException`. `getCurrentById()` берёт `lockForUpdate()` и не
  кэшируется.
- Репозиторий не бросает исключения SmartTable наружу молча: например Logger
  маппит `MapInvalidException` в `LoggerInvalidException`.

## Точки расширения

- **Новый тип поля:** класс в `Field/` + при необходимости ветки в
  `Service/Schema/*` и `Service/Query/RowAssembler`.
- **Свой класс-поле без правки ядра:** наследование от базового поля (образец —
  `tests/Fixture/CustomColumnField.php`).
- **Гидратор:** `IFieldHydrator` + `HydratorRegistry`. Ключ
  `smarttable_hydrators` в `module.config.php` пока зарезервирован, сканера нет.
- **Своя таблица:** наследник `SmartTableDefinition` в модуле + регистрация
  карты в его setup.
- **Кэш-политики:** `Service/Cache/*` (`TableCache`, `ListCacheKey`,
  `DependentRowCacheTags`).

## Зависимости

- Импортирует: `Core/Kernel` (`DateTime`, `DatabaseSettings`, `IModuleSetup`,
  `ITransactionRunner`), `Core/Cache` (`ICacheStore`).
- Импортируется: `Core/*` (75 файлов), `Roleplay/*` (156), `Versioning/Space`
  (15), `Messages/Chat` (14).
- Специально не делает: накат DDL чужих модулей, журнал аудита, версионность
  строк (`Versioning/Space`, `DEC-080`).

## Тесты

Каталог `tests/`, ~35 классов + `tests/Fixture/*` (~60 определений).
Harness — `tests/GatewayHarness.php`.

- Юнит без MySQL: `ListQueryTest.php`, `AggregateQueryTest.php`,
  `ListQueryCompilerTest.php`, `FieldPathWalkerTest.php`, `ListCacheKeyTest.php`,
  `TableCacheTest.php`, `RowAssemblerTest.php`, `HydratorRegistryTest.php`,
  `DateTimeNowTest.php`, `TableSchemaColumnTest.php`.
- MySQL-интеграционные (`*MysqlTest.php`, self-skip без БД): `CrudMysqlTest.php`,
  `AddManyMysqlTest.php`, `GetListMysqlTest.php`, `IndexMysqlTest.php`,
  `ForceDdlMysqlTest.php`, `ReferenceMysqlTest.php`, `ReferencePathMysqlTest.php`,
  `MultipleMysqlTest.php`, `LinkSetMysqlTest.php`, `ConditionalUpdateMysqlTest.php`,
  `AggregateMysqlTest.php`, `CacheMysqlTest.php`, `BigIntMysqlTest.php`,
  `DictionaryMysqlTest.php`.

## Не описано / OPEN

- Ключ `smarttable_hydrators` — бронь, сканера гидраторов нет.
- События CRUD → EventManager не подключены (канон помечает как `OPEN`).
- Fulltext, составные неуникальные индексы, decimal/float, fluent `query()`,
  полный JOIN — не v1.
