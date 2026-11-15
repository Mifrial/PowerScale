# Library/Asset

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Library/Asset --zone=tr`.

- **Путь:** `modules/Library/Asset`
- **Неймспейс:** `Mifrial\Library\Asset`
- **Контейнер:** `Mifrial\Library\Asset\Container\AssetContainer`
- **Локатор:** `Mifrial\Library\Asset\Interface\Container\IAssetContainer`
- **Setup:** `Mifrial\Library\Asset\Setup\AssetModuleSetup`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Library\Asset\Interface\Service\IAssetCollections`
- `Mifrial\Library\Asset\Service\AssetHttpService`
- `Mifrial\Library\Asset\Action\FindAssetPageAction`
- `Mifrial\Library\Asset\Action\CreateAssetAction`
- `Mifrial\Library\Asset\Action\UpdateAssetAction`
- `Mifrial\Library\Asset\Action\ArchiveAssetAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `asset.findPage` | `FindAssetPageAction` | да |
| `asset.create` | `CreateAssetAction` | да |
| `asset.update` | `UpdateAssetAction` | да |
| `asset.archive` | `ArchiveAssetAction` | да |

## Контракты `Interface/`

- `Interface/Container/IAssetContainer.php` — Публичный контейнер модуля Library/Asset.
- `Interface/Service/IAssetCollections.php` — Публичный порт коллекций для модулей-потребителей.

## Зависимости

- **Зависит от:** [Core/File](../Core/File.md), [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md)
- **От него зависят:** [Roleplay/Scene](../Roleplay/Scene.md)

## События

—

## Планы и документы

нет

