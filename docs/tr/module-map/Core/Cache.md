# Core/Cache

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Core/Cache --zone=tr`.

- **Путь:** `modules/Core/Cache`
- **Неймспейс:** `Mifrial\Core\Cache`
- **Контейнер:** `Mifrial\Core\Cache\Container\CacheContainer`
- **Локатор:** `Mifrial\Core\Cache\Interface\Container\ICacheContainer`
- **Setup:** —
- **Карта входа:** [README.md](../../../../www/mifrial/modules/Core/Cache/README.md)
- **Канон:** раздел «Карта доверия: стабильные модули Core» в [contract-status.md](../../contract-status.md)

## Порты

- `Mifrial\Core\Cache\Interface\Service\ICacheStore`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

## Контракты `Interface/`

- `Interface/Container/ICacheContainer.php` — Публичный контейнер модуля Cache.
- `Interface/Service/ICacheStore.php` — Байты по ключу с TTL и опциональными тегами.

## Зависимости

- **Зависит от:** [Core/Kernel](Kernel.md)
- **От него зависят:** [Core/SmartTable](SmartTable.md), [Versioning/Space](../Versioning/Space.md)

## События

—

## Планы и документы

нет

