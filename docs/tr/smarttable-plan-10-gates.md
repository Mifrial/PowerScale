# План 10 — ворота Basic

**Статус:** сделано, 2026-09-02. Канон — [`smarttable.md`](smarttable.md) целиком, кроме § Versioned (только контракт). Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md) пункт 10. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: закрыть Basic. **Новых фич нет.** Сверка канона с кодом и тестами; дыры канона чинить точечно; запрещённое (Versioned, UI, fluent `query()`, журнал наката) в дереве не появляется. После ворот прикладные модули могут открывать таблицы через порты SmartTable.

## Todo

- [x] **canon-map** — пройти [`smarttable.md`](smarttable.md) по разделам (стек, id/`active`, типы, required, multiple, reference, getList, кэш, индексы, label, ошибки). На каждый пункт: файл теста или явный «не v1 / хвост». Дыра канона без теста → тест или правка канона в этом заходе, не «потом».
- [x] **no-forbidden** — в `modules/Core/SmartTable` нет `Versioned*`, Eloquent/`bootEloquent`/Capsule, SQLite-тестов, fluent `query()`, HTTP/Vue. `IOpenedTable` 9 public. Публичные порты контейнера: `IDatabaseConnection`, `ISmartTableGateway`, `ITableCatalog`, `HydratorRegistry` — без `ISchemaMigrator`.
- [x] **docs-align** — [`architecture.md`](architecture.md): class-string через `ISmartTableGateway::open`, runtime — `ITableCatalog::openByName`. [`smarttable.md`](smarttable.md) § Ошибки: транзакция. Статус канона: Basic закрыт. README без Versioned/админки.
- [x] **known-tails** — см. ниже.
- [x] **gates** — `cs-check`, `quality`, `phpunit` 134 (1 skip redis). Дыра: `CACHE_CONFIG_INVALID` не ассертился — `TableCacheTest` (пустой path + неизвестный driver).

## Отчёт ворот

| Раздел канона | Тест / документ |
|---|---|
| Illuminate, не Eloquent, `local.php`, MySQL | MysqlPing, ConnectionConfig, composer |
| только `id`; `active` обычный bool | FieldMap, CrudMysql, GetList |
| типы, DateTime UTC, json hydrator | FieldMap, CrudMysql, HydratorRegistry |
| required / multiple `[]` | CrudMysql, MultipleMysql |
| mfv, delete без CASCADE | MultipleMysql, ForceDdl (drop mapped mfv) |
| reference restrict / setNull / none | ReferenceMysql |
| getList; `@` / `=` multiple; sort multiple отказ | GetListMysql, MultipleMysql, ListQueryCompiler |
| TTL; теги после commit; file/redis; CONFIG vs I/O | TableCache, CacheMysql, RedisCacheStore (skip) |
| index/unique; leftover до force | IndexMysql, ForceDdlMysql |
| словарь `openByName`; одно физ. имя | DictionaryMysql |
| листья ошибок | Exception/ + § Ошибки (транзакция дописана) |
| label ключ / fallback имя | FieldMapTest |

## Хвосты (не v1 / не чинили)

- multiple+reference
- fulltext; составной unique; ALTER TYPE
- события CRUD → EventManager (OPEN)
- hydrator JSON в словаре
- `canOpen()` redis без `ext-redis` vs `get`+TTL: invalidate молчит, чтение с TTL бросает `CACHE_CONFIG_INVALID`; слотов нет
- журнал наката — не SmartTable (план 9)

## Не входит

План 11 Versioned. Админка. Auth/ACL словаря. Модуль журнала наката. User как код этого захода.

## Следующий заход

User / Auth / Журнал **или** план 11 Versioned. Модуль наката схемы — когда понадобится.
