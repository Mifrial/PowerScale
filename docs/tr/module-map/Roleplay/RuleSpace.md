# Roleplay/RuleSpace

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Roleplay/RuleSpace --zone=tr`.

- **Путь:** `modules/Roleplay/RuleSpace`
- **Неймспейс:** `Mifrial\Roleplay\RuleSpace`
- **Контейнер:** `Mifrial\Roleplay\RuleSpace\Container\RuleSpaceContainer`
- **Локатор:** `Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer`
- **Setup:** `Mifrial\Roleplay\RuleSpace\Setup\RuleSpaceModuleSetup`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces`
- `Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService`
- `Mifrial\Roleplay\RuleSpace\Action\GetSpaceListAction`
- `Mifrial\Roleplay\RuleSpace\Action\GetSpaceAction`
- `Mifrial\Roleplay\RuleSpace\Action\GetSpaceByCodeAction`
- `Mifrial\Roleplay\RuleSpace\Action\CreateSpaceAction`
- `Mifrial\Roleplay\RuleSpace\Action\UpdateSpaceAction`
- `Mifrial\Roleplay\RuleSpace\Action\DeactivateSpaceAction`
- `Mifrial\Roleplay\RuleSpace\Action\GetSpaceRevisionsAction`
- `Mifrial\Roleplay\RuleSpace\Action\GetSpaceRevisionAction`
- `Mifrial\Roleplay\RuleSpace\Action\CommitSpaceDraftAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `ruleSpace.getList` | `GetSpaceListAction` | да |
| `ruleSpace.get` | `GetSpaceAction` | да |
| `ruleSpace.getByCode` | `GetSpaceByCodeAction` | да |
| `ruleSpace.create` | `CreateSpaceAction` | да |
| `ruleSpace.update` | `UpdateSpaceAction` | да |
| `ruleSpace.deactivate` | `DeactivateSpaceAction` | да |
| `ruleSpace.getRevisions` | `GetSpaceRevisionsAction` | да |
| `ruleSpace.getRevision` | `GetSpaceRevisionAction` | да |
| `ruleSpace.commitDraft` | `CommitSpaceDraftAction` | да |

## Контракты `Interface/`

- `Interface/Container/IRuleSpaceContainer.php` — Публичный контейнер модуля Roleplay/RuleSpace.
- `Interface/Service/IRuleSpaces.php` — Оператор миров правил: sidecar и состав через IRules.

## Зависимости

- **Зависит от:** [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md), [Roleplay/Rule](Rule.md)
- **От него зависят:** [Roleplay/Character](Character.md), [Roleplay/Game](Game.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [rulespace-plan-01.md](../../rulespace-plan-01.md) | план | `~ЧАСТИЧНО~` | 2026-09-05 |
| [rulespace-plan-02.md](../../rulespace-plan-02.md) | план | `~ЧАСТИЧНО~` | 2026-09-05 |
| [rulespace-plan-03.md](../../rulespace-plan-03.md) | план | `~ЧАСТИЧНО~` | 2026-09-05 |
| [rulespace-plan-04.md](../../rulespace-plan-04.md) | план | `~ЧАСТИЧНО~` | 2026-09-05 |
| [rulespace-plan-05.md](../../rulespace-plan-05.md) | план | `~ЧАСТИЧНО~` | 2026-09-05 |
| [rulespace-plan-06.md](../../rulespace-plan-06.md) | план | `~ЧАСТИЧНО~` | 2026-09-05 |
| [rulespace-plan-10.md](../../rulespace-plan-10.md) | план | `~ЧАСТИЧНО~` | 2026-09-05 |
| [rulespace-plan-11-sections-vue.md](../../rulespace-plan-11-sections-vue.md) | план | `~ЧАСТИЧНО~` | 2026-09-08 |

