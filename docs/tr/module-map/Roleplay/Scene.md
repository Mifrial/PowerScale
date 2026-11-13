# Roleplay/Scene

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Roleplay/Scene --zone=tr`.

- **Путь:** `modules/Roleplay/Scene`
- **Неймспейс:** `Mifrial\Roleplay\Scene`
- **Контейнер:** `Mifrial\Roleplay\Scene\Container\SceneContainer`
- **Локатор:** `Mifrial\Roleplay\Scene\Interface\Container\ISceneContainer`
- **Setup:** `Closure(IServiceLocator)`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Roleplay\Scene\Service\SceneCatalogHttp`
- `Mifrial\Roleplay\Scene\Service\SceneSaveHttp`
- `Mifrial\Roleplay\Scene\Action\FindScenePageAction`
- `Mifrial\Roleplay\Scene\Action\GetSceneAction`
- `Mifrial\Roleplay\Scene\Action\ArchiveSceneAction`
- `Mifrial\Roleplay\Scene\Action\SaveSceneAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `scene.findPage` | `FindScenePageAction` | да |
| `scene.get` | `GetSceneAction` | да |
| `scene.save` | `SaveSceneAction` | да |
| `scene.archive` | `ArchiveSceneAction` | да |

## Контракты `Interface/`

- `Interface/Container/ISceneContainer.php` — Публичный контейнер модуля Roleplay/Scene.

## Зависимости

- **Зависит от:** [Core/File](../Core/File.md), [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md), [Library/Asset](../Library/Asset.md)
- **От него зависят:** —

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [scene-plan-01.md](../../scene-plan-01.md) | план | `~В РАБОТЕ~` | 2026-11-12 |
| [scene-plan-02-water-shaders.md](../../scene-plan-02-water-shaders.md) | план | `~В РАБОТЕ~` | 2026-11-12 |
| [scene-plan-04-render-and-nav-fixes.md](../../scene-plan-04-render-and-nav-fixes.md) | план | `~В РАБОТЕ~` | 2026-11-13 |
| [scene-renderer.md](../../scene-renderer.md) | канон | `~В РАБОТЕ~` | 2026-11-12 |
| [scene-system.md](../../scene-system.md) | канон | `~В РАБОТЕ~` | 2026-11-10 |
| [scene-plan-05-bridge-deck.md](../../scene-plan-05-bridge-deck.md) | план | `~ЧАСТИЧНО~` | 2026-11-14 |
| [scene-plan-02-battleground.md](../../scene-plan-02-battleground.md) | план | `~СДЕЛАНО~` | 2026-11-13 |
| [scene-plan-03-decor-occlusion.md](../../scene-plan-03-decor-occlusion.md) | план | `~СДЕЛАНО~` | 2026-11-13 |

