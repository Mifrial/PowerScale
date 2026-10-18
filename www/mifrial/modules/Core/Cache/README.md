# Core\Cache

Карта входа: как пользоваться кэшем. Пути даны от корня репозитория.

**Роль:** байтовый кэш по ключу с TTL и тегами. Драйвер выбирается по конфигу:
файловый, Redis или «непригодный» store при дырявом конфиге. Значения не
типизированы — только строки, сериализацию делает потребитель.

**Канон:** [`cache-plan-01.md`](../../../../../docs/tr/cache-plan-01.md)
(`DEC-081`). Политику TTL и тегов конкретной таблицы держит потребитель —
для SmartTable это [`smarttable.md`](../../../../../docs/tr/smarttable.md)
§«Кэш».

**Сверено по коду:** 2026-10-18.

## Ключевые типы

| Тип | Путь | Зачем |
|---|---|---|
| `ICacheStore` | `Interface/Service/ICacheStore.php` | Публичный порт: `read`, `write`, `deleteKeys`, `flushTags`, `isUsable`; `MAX_TTL_SECONDS` |
| `FileCacheStore` | `Service/FileCacheStore.php` | Файловый драйвер (flock, sha256-пути, тег-индексы) |
| `RedisCacheStore` | `Service/RedisCacheStore.php` | Драйвер через `ext-redis` |
| `UnusableCacheStore` | `Service/UnusableCacheStore.php` | Заглушка при дырявом конфиге, `isUsable() === false` |
| `CacheStoreFactory` | `Service/CacheStoreFactory.php` | `open(CacheSettings)` — выбор драйвера |
| `CachePortFactory` | `Service/CachePortFactory.php` | Сборка порта из `IRuntimeConfig->cache()` |
| `CacheStoreGuard` | `Service/CacheStoreGuard.php` | Валидация ключей, TTL и тегов |
| `CacheEnvelope` | `Service/CacheEnvelope.php` | Конверт TTL вокруг payload |
| `FileCacheTagIndex` | `Service/FileCacheTagIndex.php` | Тег-индексы файлового драйвера |
| `ICacheContainer` / `CacheContainer` | `Interface/Container/ICacheContainer.php`, `Container/CacheContainer.php` | Локатор модуля |
| `CacheException` / `CacheInvalidException` / `CacheDriverFailedException` | `Exception/*.php` | Ошибки модуля |

Модуль без таблиц и без setup.

## Как пользоваться

```php
/** @var ICacheStore $cache */
$cache = $serviceLocator->get(ICacheContainer::class)->get(ICacheStore::class);

$cache->write('character:5:sheet', $payload, 3600, ['character', 'character:5']);
$cache->read('character:5:sheet');   // ?string
$cache->flushTags(['character:5']);  // сброс всех ключей тега
$cache->deleteKeys(['character:5:sheet']);
```

Объявление порта в `module.config.php` модуля:

```php
'ports' => [
    ICacheStore::class => static function (IServiceLocator $serviceLocator): ICacheStore {
        return (new CachePortFactory())->create($serviceLocator);
    },
],
```

Выбор драйвера (`CacheStoreFactory::open`):
`driver === 'file'` + непустой `path` → `FileCacheStore`;
`driver === 'redis'` + непустые host/port + `class_exists(Redis::class)` →
`RedisCacheStore`; иначе → `UnusableCacheStore`.

## Инварианты

- `TTL` — 1..`MAX_TTL_SECONDS` (2592000, 30 суток), без «навсегда». Вне
  диапазона → `CacheInvalidException`.
- Пустой ключ или пустой тег → `CacheInvalidException`.
- `isUsable()` — `false` ровно при пустом `path`, дырявом redis или неизвестном
  драйвере.
- `UnusableCacheStore`: `read`/`write` всегда бросают; пустые `deleteKeys`/
  `flushTags` — no-op, непустые — бросок.
- Штатные драйверы: промах и истёкший ключ → `read` вернул `null` (истёкший
  удаляется); пустые `deleteKeys`/`flushTags` — no-op.
- Кэш не типизирован: наружу и внутрь — только `string`. Типизацию и
  сериализацию делает потребитель.
- Дырявый **конфиг** — всегда ошибка (непригодный store). Отвал **драйвера**
  (I/O) — забота потребителя: SmartTable на `debug` бросает, без `debug` читает
  из БД и пропускает запись/сброс.
- Модуль не знает про таблицы, теги конкретных доменов и SmartTable-политики.

## Точки расширения

- **Новый драйвер:** ветка в `CacheStoreFactory::open` + реализация `ICacheStore`.
- **Конфиг:** `www/mifrial/config/local.php`, ключ `cache`
  (`driver`, `path`, host/port для redis). В phpunit — `config/test.php`.
- Реализация одна на процесс (`CachePortFactory`); внешнего hook для подмены
  нет.

## Зависимости

- Импортирует: `Core/Kernel` (`CacheSettings`, `KernelException`,
  `IModuleContainer`, `IServiceLocator`, `IKernelContainer`).
- Потребители: `Core/SmartTable`, `Versioning/Space` (`VersionedCatalog`,
  `RevisionSliceStore`).
- Состояние конфига: в `config/local.php` `cache.driver = file`, но `path` не
  задан → локально получается `UnusableCacheStore`; в `config/test.php` `path`
  задан → файловый store.

## Тесты

- `tests/CacheStoreFactoryTest.php` — пустой `path`, неизвестный драйвер,
  дырявый redis → непригодный store.
- `tests/FileCacheStoreTest.php` — `write`/`read` с TTL, `flushTags`, guard,
  изоляция тегов.
- `tests/RedisCacheStoreTest.php` — живой Redis (иначе self-skip): TTL и сброс
  тега.
- `tests/CachePortBootTest.php` — boot резолвит `ICacheStore` из `test.php`.

## Не описано / OPEN

- Не реализовано: версионирование значений, batch-операции, negative-cache,
  метрики, инвалидация по списку ключей-тегов (есть только `flushTags`).
