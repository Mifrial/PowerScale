# План 8 — тегированный кэш

**Статус:** сделано, 2026-09-02. Канон — [`smarttable.md`](smarttable.md) § Кэш. Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md) пункт 8. Список — [`smarttable-plan-04-getlist.md`](smarttable-plan-04-getlist.md). Транзакция — план 3. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: `getById` / `getList` кэшируются **только** при явном TTL. Без TTL — всегда БД. Инвалидация **после успешного commit** (автокоммит записи — сразу после успеха). Внутри открытой транзакции не читать и не писать кэш. Подпапки `Service/` нет. `IOpenedTable` не расширять новыми методами (10 public у handle): TTL — опциональный 2-й аргумент `getById` и `getList`.

Illuminate Cache / Laravel / Eloquent / `predis` не подключать. Redis — основной драйвер; file — запасной и драйвер тестов.

## Todo

- [x] **ttl-api** — `getById(int $rowId, ?int $cacheTtl = null)`; `getList(ListQuery $listQuery, ?int $cacheTtl = null)`. Нет аргумента / `null` — без кэша. TTL ≤ 0 → `MAP_INVALID`. **Не** 7-й параметр ctor `ListQuery` (уже 6 = потолок quality) и **не** ключ `fromOptions`. Вызов: `$table->getList(ListQuery::fromOptions([...]), 60)`. `OpenedTable` без SQL.
- [x] **tags** — сброс **OR**: `getList` умирает, если пересечение тегов непусто. Каналы (один на стол / на **имя** поля): `st:{table}`, `st:{table}:{field}`. `get` + TTL: тег `st:{table}:rows` (не `st:{table}`: add не бьёт чужие get). Delete родителя: ещё `st:{child}` + `st:{child}:rows` для FK CASCADE/SET NULL. Поля запроса: рекурсия filter + ключи sort + select (`null` → вся карта). Ключ `{table}:get:{id}`; `null` кэшировать. Сброс только после **успеха** записи.
- [x] **tx** — `transactionLevel() > 0`: чтение с TTL в БД, не `set`; запись копит теги/`DEL`. После успешного `commit` в gateway — `flushPending`. Rollback / исключение в `$work` — `clearPending`. Вне tx — сразу. После DDL, если уровень уже 0 (неявный commit MySQL) — `flushPending` всего pending. `CACHE_DRIVER_FAILED` после SQL commit **не** `TRANSACTION_FAILED`. Окно commit→сброс допустимо.
- [x] **drivers** — Kernel `CacheSettings` + `IRuntimeConfig::cache()`; `cacheDriver()` делегирует. Нет `cache` в тестах Kernel → driver `file`, path `''`; ping store не открывает. Dist в этом заходе с `path`. Пустой path / неизвестный driver / redis без host|port → `CACHE_CONFIG_INVALID` всегда. File: `flock` на индекс тега. Redis: `ext-redis`, skip если нет класса/сокета. Один `TableCache` на объект соединения (`WeakMap`), не на экземпляр Support. Не порт соседа.
- [x] **fail-soft** — I/O драйвера: `debug() === true` → `CACHE_DRIVER_FAILED`; `debug() === false` → чтение miss в БД, set/invalidate no-op. Логгер не заводим.
- [x] **tests-gates** — юнит file (hit/miss/expire через подставленный `now()`, OR тега, `DEL` get); debug on/off на I/O; MySQL skip как ping: hit TTL; update поля бьёт нужный getList и этот get; add бьёт списки; `null` get кэшируется; rollback не сбрасывает; без TTL всегда БД; CRUD без TTL при path `''` не бросает. cs/quality/phpunit.

## Не входит

План 9. Versioned. ACL. Подпапки `Service/`. `illuminate/cache` / `predis`. Теги чужих модулей. Инвалидация извне. Stampede. Кэш DDL-результатов / `open` / `openByName`. Cursor. AND по тегам. Полевые теги на каждом `get`. TTL в ключе. `cacheTtl` в `ListQuery`. Пароль Redis.

## Зачем не на handle новым методом

Лимит 10 public. TTL — параметр чтения. Инвалидация не на фасаде соседа.

## Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `CacheSettings` | Kernel `Dto/` | driver/path/redis | I/O |
| `IRuntimeConfig` | Kernel | `cache()`; `cacheDriver()` как сейчас | SmartTable |
| `ListQuery` | `Dto/` | filter/sort/page/select | TTL |
| `TableCache` | `Service/` | get/set, теги getList, `DEL` get, pending | Query Builder, hydrate |
| `FileCacheStore` / `RedisCacheStore` | `Service/` | байты + индекс тегов getList | карта |
| `OpenedTable` | `Service/` | TTL → cache; успех записи → remember | SQL |
| `SmartTableGateway` | `Service/` | flush/clear pending после commit/rollback | ключи |
| `IOpenedTable` | `Interface/Service/` | 2-й TTL у `get`/`getList` | новый метод |

`OpenedTable` 5-й аргумент `TableCache` (лимит 6). Gateway и catalog получают **тот же** экземпляр с WeakMap соединения. Catalog/`openByName` передают его в `OpenedTable`. DDL каталога **без** handle (`createTable` / `dropTable` / `installMeta` → `TableSchema` напрямую) после успеха тоже тег стола. `addField` / `dropField` уже через handle. `TableRows`/`TableList` кэш не знают.

Store **ленивый**: обычный CRUD без TTL при path `''` не открывает store, invalidate — no-op. Первый `get`/`getList` с TTL при дырявом конфиге → `CACHE_CONFIG_INVALID`.

Ключ `get`: `{table}:get:{id}`. Ключ `getList`: стол + стабильный json (filter/sort/limit/offset/countTotal/select). TTL только expire. Payload: `serialize`; `unserialize` только `ListResult` и `DateTime` ядра (`get` — массив с возможными `DateTime`).

Ошибки (листья `SmartTableException`, не Database / не Kernel):

- `CACHE_CONFIG_INVALID` — всегда, и на деве и в проде;
- `CACHE_DRIVER_FAILED` — только при `debug()`; иначе fail-soft (см. todo).

## Теги (OR)

| Операция | На записи | Сброс |
|---|---|---|
| `getList` + TTL | стол + поля **этого** запроса | — |
| `get` + TTL | ключ `{table}:get:{id}` | — |
| `add` успех | — | тег стола |
| `delete` успех | — | тег стола + `DEL` этого get |
| `update` успех | — | `DEL` этого get + теги изменённых полей, не стол |
| DDL успех | — | тег стола |

Полевой канал — ящик ключей `getList` на имя поля, не ячейка и не все столбцы на `get`.

`select === null` = вся карта: любой update поля бьёт такой список.

## Конфиг

```
cache.driver = file | redis   обязателен в dist
cache.path   = каталог file   обязателен в dist (__DIR__ . '/../var/cache/smarttable')
cache.redis.host / port       обязательны при driver=redis
```

В `local.php.dist` **в этом заходе** дописать `path`. Ничто не мешает: Kernel без ключа `cache` по-прежнему `file` + `''` (ping/юнит RuntimeConfig). Сайт с dist уже имеет каталог. `var/cache/` в gitignore, если ещё нет.

## Тесты

- file: expire через фейковые часы; flush тега бьёт только свои ключи;
- get/getList без TTL не пишут store; add без TTL при path `''` не бросает;
- debug true: I/O store → исключение; debug false: I/O → miss/no-op;
- MySQL: `getList($query, 60)`; update `title` / add / delete / null-get / rollback / commit как todo;
- redis skip без сокета.

Ворота: `cs-check`, `quality`, `phpunit`.

## Документы захода

- этот файл; [`TR.md`](TR.md); пункт 8 roadmap; канон § Ошибки после кода; `local.php.dist`.

## Следующий заход

План 10: ворота Basic.
