# Roleplay/Map

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Roleplay/Map --zone=tr`.

- **Путь:** `modules/Roleplay/Map`
- **Неймспейс:** `Mifrial\Roleplay\Map`
- **Контейнер:** `Mifrial\Roleplay\Map\Container\MapContainer`
- **Локатор:** `Mifrial\Roleplay\Map\Interface\Container\IMapContainer`
- **Setup:** `Mifrial\Roleplay\Map\Setup\MapModuleSetup`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Roleplay\Map\Service\MapCatalogHttp`
- `Mifrial\Roleplay\Map\Service\MapSaveHttp`
- `Mifrial\Roleplay\Map\Service\MapRouteHttp`
- `Mifrial\Roleplay\Map\Action\FindMapPageAction`
- `Mifrial\Roleplay\Map\Action\GetMapAction`
- `Mifrial\Roleplay\Map\Action\ArchiveMapAction`
- `Mifrial\Roleplay\Map\Action\SaveMapAction`
- `Mifrial\Roleplay\Map\Action\RouteMapAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `map.findPage` | `FindMapPageAction` | да |
| `map.get` | `GetMapAction` | да |
| `map.save` | `SaveMapAction` | да |
| `map.archive` | `ArchiveMapAction` | да |
| `map.route` | `RouteMapAction` | да |

## Контракты `Interface/`

- `Interface/Container/IMapContainer.php` — Публичный контейнер модуля Roleplay/Map.

## Зависимости

- **Зависит от:** [Core/File](../Core/File.md), [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md)
- **От него зависят:** —

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [map-plan-01.md](../../map-plan-01.md) | план | `~В РАБОТЕ~` | 2026-11-07 |
| [map-plan-02.md](../../map-plan-02.md) | план | `~В РАБОТЕ~` | 2026-11-07 |
| [spatial-roadmap.md](../../spatial-roadmap.md) | план | `~В РАБОТЕ~` | 2026-11-06 |

