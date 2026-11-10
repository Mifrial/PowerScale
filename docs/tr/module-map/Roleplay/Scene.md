# Roleplay/Scene

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Roleplay/Scene --zone=tr`.

- **Путь:** `modules/Roleplay/Scene`
- **Неймспейс:** `Mifrial\Roleplay\Scene`
- **Контейнер:** `Mifrial\Roleplay\Scene\Container\SceneContainer`
- **Локатор:** `Mifrial\Roleplay\Scene\Interface\Container\ISceneContainer`
- **Setup:** `Mifrial\Roleplay\Scene\Setup\SceneModuleSetup`
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

- **Зависит от:** [Core/File](../Core/File.md), [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md)
- **От него зависят:** —

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [scene-plan-01.md](../../scene-plan-01.md) | план | `~В РАБОТЕ~` | 2026-11-10 |
| [scene-renderer.md](../../scene-renderer.md) | канон | `~В РАБОТЕ~` | 2026-11-10 |
| [scene-system.md](../../scene-system.md) | канон | `~В РАБОТЕ~` | 2026-11-10 |

