# Roleplay/Lore

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Roleplay/Lore --zone=tr`.

- **Путь:** `modules/Roleplay/Lore`
- **Неймспейс:** `Mifrial\Roleplay\Lore`
- **Контейнер:** `Mifrial\Roleplay\Lore\Container\LoreContainer`
- **Локатор:** `Mifrial\Roleplay\Lore\Interface\Container\ILoreContainer`
- **Setup:** `Mifrial\Roleplay\Lore\Setup\LoreModuleSetup`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Roleplay\Lore\Service\LoreTransferHttpService`
- `Mifrial\Roleplay\Lore\Service\LoreWorldHttpService`
- `Mifrial\Roleplay\Lore\Service\LoreArticleHttpService`
- `Mifrial\Roleplay\Lore\Action\FindLoreWorldPageAction`
- `Mifrial\Roleplay\Lore\Action\GetLoreWorldAction`
- `Mifrial\Roleplay\Lore\Action\SaveLoreWorldAction`
- `Mifrial\Roleplay\Lore\Action\ArchiveLoreWorldAction`
- `Mifrial\Roleplay\Lore\Action\SaveLoreTreeAction`
- `Mifrial\Roleplay\Lore\Action\FindLoreArticlePageAction`
- `Mifrial\Roleplay\Lore\Action\GetLoreArticleAction`
- `Mifrial\Roleplay\Lore\Action\SaveLoreArticleAction`
- `Mifrial\Roleplay\Lore\Action\ArchiveLoreArticleAction`
- `Mifrial\Roleplay\Lore\Action\ExportLoreWorldAction`
- `Mifrial\Roleplay\Lore\Action\ImportLoreWorldAction`
- `Mifrial\Roleplay\Lore\Action\FindLoreReaderCandidatesAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `lore.findWorldPage` | `FindLoreWorldPageAction` | да |
| `lore.getWorld` | `GetLoreWorldAction` | да |
| `lore.saveWorld` | `SaveLoreWorldAction` | да |
| `lore.archiveWorld` | `ArchiveLoreWorldAction` | да |
| `lore.saveTree` | `SaveLoreTreeAction` | да |
| `lore.findArticlePage` | `FindLoreArticlePageAction` | да |
| `lore.getArticle` | `GetLoreArticleAction` | да |
| `lore.saveArticle` | `SaveLoreArticleAction` | да |
| `lore.archiveArticle` | `ArchiveLoreArticleAction` | да |
| `lore.findReaderCandidates` | `FindLoreReaderCandidatesAction` | да |
| `lore.exportWorld` | `ExportLoreWorldAction` | да |
| `lore.importWorld` | `ImportLoreWorldAction` | да |

## Контракты `Interface/`

- `Interface/Container/ILoreContainer.php` — Публичный контейнер модуля Roleplay/Lore.

## Зависимости

- **Зависит от:** [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md), [Roleplay/Keyword](Keyword.md)
- **От него зависят:** —

## События

—

## Планы и документы

нет

