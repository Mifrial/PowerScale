# План Cache 1 — драйвер Core/Cache

**Статус:** план, 2026-09-05. Конвейер — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Сейчас store живёт в `SmartTable/Service/Cache`. Потребители: ST (`TableCache`), Versioning ([`versioning-plan-01.md`](versioning-plan-01.md)). `DEC-081`.

Цель: один драйвер redis/file из `local.php`. Политики сверху разные: ST — теги стола и сброс после записи; Space — ключ среза и TTL **месяц**, не «навсегда».

Не `TableCache` в Core. Не SQL. Не PSR-6.

## Термины

| Термин | Смысл |
|---|---|
| Store | Байты по ключу, TTL, опциональные теги. |
| Тег | Множество ключей; `flushTags` снимает их. ST вешает `st:…`. Space теги **не** использует. |
| Потолок TTL | **2592000 с (30 суток)**. Больше — ошибка конфига вызова. Нуля/«навсегда» нет. |

## Решения

### 1. Модуль

`www/mifrial/modules/Core/Cache`, `Mifrial\Core\Cache`. Eager `Core/*`. Таблиц нет, setup пустой.

Контейнер `CacheContainer` / `ICacheContainer`. Порт: `ICacheStore` (один). Сосед: `$locator->get(ICacheContainer::class)->get(ICacheStore::class)`. Не extra Kernel. ST/Versioning фабрики берут контейнер Cache из локатора.

`get(ICacheStore)` **не** бросает на дырявом `local.php`: объект с `isUsable()===false`, без connect/mkdir. Иначе ping/boot, который резолвит порт, падает. `read`/`write` на таком объекте — `CACHE_INVALID`. TableCache как сейчас не пишет, если `!isUsable()`.

`composer.json` PSR-4 `Mifrial\Core\Cache\` + Tests. `phpunit.xml.dist` suite `cache`. `loadCore` сканирует Cache **раньше** Kernel по алфавиту — порты ленивые, `IRuntimeConfig` резолвится при первом `get`, когда Kernel уже загружен. Не звать `get(ICacheStore)` из `module.config` include.

**DAG:** Cache → Kernel (`IRuntimeConfig` / `CacheSettings`). ST → Cache. Versioning → Cache + ST. Cache ↛ ST. Kernel ↛ Cache.

### 2. `ICacheStore`

Байты, не гидратация ST/`RevisionSlice`. Один экземпляр на процесс (порт контейнера): ST и Space **не** открывают второй клиент Redis на те же ключи.

- `read(string $key): ?string`
- `write(string $key, string $payload, int $ttlSeconds, array $tagNames = []): void`
- `deleteKeys(array $keys): void` — имя как сейчас у store, не `delete`
- `flushTags(array $tagNames): void`
- `isUsable(): bool` — то же, что нынешний `CacheStoreFactory::canOpen` (пустой path / дырявый redis). TableCache без этого не знает, когда skip invalidate.

`ttlSeconds` **1..2592000**. Иначе `CACHE_INVALID`. Пустой ключ / тег — отказ. `flushTags([])` / `deleteKeys([])` — no-op. `tagNames` list; `[]` — ключ не в множествах.

**TTL принадлежит store**, не «ещё раз» в payload ST. Сейчас ST: `CachePayload` (`expire\nserialize`) + Redis `SET` без EXPIRE — ключ в Redis живёт вечно, срок только при decode. После выноса:

- `write` исполняет TTL на обоих драйверах **одним** конвертом store (`expiresAt\n` + raw) **и** Redis `SET EX` (ключ уходит из Redis без read). `read` снимает конверт, наружу — те байты, что передал потребитель.
- ST `CachePayload`: только `serialize`/`unserialize` ListResult и ряда, **без** своей строки expire. Space — JSON DTO, не PHP serialize.
- `TableCacheTest` сейчас крутит expire через clock в `TableCache::now()`. После выноса clock нужен **file-store** (ctor теста), не интерфейс. Redis SETEX — стенные часы; expire-тест file, не redis.
- Старые файлы `var/cache/smarttable` — мусор (двойной/старый конверт). Не мигрировать.
- `write`: сначала ключ с TTL (Redis `SET EX`), потом SADD в теги. Наоборот — член тега без ключа при обрыве.
- Конверт store режет **первую** строку (`expiresAt`); остаток — payload с переводами строк (serialize).
- `SmartTableSupport` / `TableCache` сейчас `new` factory из `CacheSettings`. Станет ctor `ICacheStore`. Mysql/unit ST с temp path: `new FileCacheStore($temp)` (или clock) в тест, не обязательный полный boot. WeakMap «один TableCache на connection» остаётся; store один на процесс.

Теги redis: `stg:{tag}` → set ключей. Истёкший ключ может остаться в set до следующего `flushTags` — не чистить set по EXPIRE в v1.

Store **не** знает транзакцию MySQL. TableCache по-прежнему не читает/не пишет кэш, пока открыта TX, и копит сброс тегов до commit. Space пишет слайс **после** успешного `gateway.transaction()`.

Исключения — `Core/Cache/Exception`. TableCache **wrap** в нынешние ST `CACHE_*` + `CacheFailSoft` (debug / miss). Space: I/O store без debug → промах среза и БД, не 500; debug — исключение. Fail-soft **не** прятать внутрь store (Space и ST решают сами; у ST уже есть).

`CacheStoreFactory` в Cache: `CacheSettings` с Kernel `IRuntimeConfig` (**Cache → Kernel**, не наоборот). Дырявый конфиг — `isUsable() === false` / ошибка при `write`, не при boot ping.

Перенос: `FileCacheStore`, `RedisCacheStore`, `FileCacheTagIndex`, factory. Сигнатура `write` **меняется** (добавляется ttl). Тесты `TableCacheTest` / `RedisCacheStoreTest` / `CacheMysqlTest` — на новый порт; redis-тест как сейчас (скип если нет сокета), не обязательный redis в каждом CI.

### 2b. Дыры, которые план закрывает

| Дыра | Решение |
|---|---|
| Space json на file без expire | TTL в store, не в ST payload |
| Два Redis-клиента | Один `ICacheStore` |
| TableCache без canOpen | `isUsable()` |
| Кэш внутри TX | по-прежнему ST; store глупый |
| Clock expire ST | clock на FileCacheStore в тесте, не на `ICacheStore` |
| PHP serialize слайса Space | JSON; serialize только TableCache |
| get() при пустом path | объект, `isUsable()===false`, не throw |
| Autoload / phpunit | composer + suite `cache` |
| Support без локатора | store в ctor тестов |

### 3. Конфиг

Ключ `cache` в `local.php` / `test.php` без смены имён: `driver`, `path`, `redis.host/port`. Path больше не «только ST»: в dist можно `var/cache` (не `…/smarttable`); существующие `local.php` со старым path не ломать принудительно.

Префиксы ключей задаёт **потребитель**: ST как сейчас (`table:get:id`, hash списка). Space: `vs:{cluster}:{spaceId}:{revision}`. Store не добавляет префикс модуля сам (иначе flushTags сложнее). Не сталкивать ключи: ST не использует префикс `vs:`.

### 4. ST

`TableCache` собирается с `ICacheStore`, не `new CacheStoreFactory`. `CacheSettings` для debug/fail-soft можно оставить с runtime; open store — только модуль Cache. `assertTtl`: `null` или 1..2592000.

`CachePayload`: только serialize значения ST; expire — store. Тесты file в temp — ок после смены формата.

Семантика тегов ST **не** меняется: списки `st:{table}`, get `st:{table}:rows`. Space **не** вызывает `flushTags` на `st:`.

Тесты ST кэша: тот же mysql/file; factory через модуль или тот же path. Не ломать suite.

### 5. Space (не код этого PR, контракт)

Срез: `write(key, json, 2592000, [])`. Повторный getRevision — `read`. Нет never-expire. Нет процесса-map как прод-кэша (тест может остаться без store, если Cache в phpunit file). Промах TTL через месяц — снова два getList, снова write.

Не override тегов ST.

### 6. Канон

`DEC-081`: драйвер кэша — `Core/Cache`; ST и Versioning — политики. `DEC-078` «тегированный кэш в ST» = политика списков, не владение redis. [`smarttable.md`](smarttable.md) § Кэш: store из Core. [`architecture.md`](architecture.md) абзац Cache. [`TR.md`](TR.md). Versioning §5 — TTL 30д, не навсегда.

## Todo

- [x] **module** — Core/Cache, autoload, phpunit suite, store, контейнер, перенос file/redis из ST.
- [x] **ttl** — store исполняет 1..2592000 (Redis EX + file expire); ST payload без своей строки expire.
- [x] **st** — TableCache на `ICacheStore`; wrap ошибок; assertTtl max; phpunit smarttable cache.
- [x] **gates** — phpunit Cache (file; redis если в CI есть); cs/quality Cache+ST.
- [x] **canon** — этот файл; architecture; smarttable.md; TR; versioning-plan-01 §5; DEC-081.

## Не входит

Семантика getList. Override тегов с `IOpenedRecords`. PSR. GC file. Сжатие слайса. Versioning код (после ST 18 и этого файла). Менять драйвер в `local.php` пользователей.

## Документы захода

этот файл; [`smarttable.md`](smarttable.md); [`versioning-plan-01.md`](versioning-plan-01.md); [`php-coding-standards.md`](php-coding-standards.md).
