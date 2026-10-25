# Roleplay/Rule

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Roleplay/Rule --zone=tr`.

- **Путь:** `modules/Roleplay/Rule`
- **Неймспейс:** `Mifrial\Roleplay\Rule`
- **Контейнер:** `Mifrial\Roleplay\Rule\Container\RuleContainer`
- **Локатор:** `Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer`
- **Setup:** `Closure(IServiceLocator)`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Roleplay\Rule\Interface\Service\IRules`
- `Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

## Контракты `Interface/`

- `Interface/Container/IRuleContainer.php` — Публичный контейнер модуля Roleplay/Rule.
- `Interface/Service/IFormulaEvaluations.php` — Обход уже разобранных узлов формулы.
- `Interface/Service/IRules.php` — Фасад правил: identity по code, состав на часах.

## Зависимости

- **Зависит от:** [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Roleplay/Keyword](Keyword.md), [Roleplay/Mechanic](Mechanic.md), [Versioning/Space](../Versioning/Space.md)
- **От него зависят:** [Roleplay/Character](Character.md), [Roleplay/Game](Game.md), [Roleplay/RuleSpace](RuleSpace.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [rule-content-plan-04-knowledge.md](../../rule-content-plan-04-knowledge.md) | план | `~ПЛАН~` | 2026-09-13 |
| [rule-content-plan-08-strike-upgrades.md](../../rule-content-plan-08-strike-upgrades.md) | план | `~ПЛАН~` | 2026-09-15 |
| [rule-content-plan-09-melee-speed.md](../../rule-content-plan-09-melee-speed.md) | план | `~ПЛАН~` | 2026-09-16 |
| [rule-content-plan-11-melee-accuracy.md](../../rule-content-plan-11-melee-accuracy.md) | план | `~ПЛАН~` | 2026-09-20 |
| [rule-content-plan-12-dodge-benefit.md](../../rule-content-plan-12-dodge-benefit.md) | план | `~ПЛАН~` | 2026-09-19 |
| [rule-content-plan-13-melee-quantity.md](../../rule-content-plan-13-melee-quantity.md) | план | `~ПЛАН~` | 2026-09-20 |
| [rule-content-plan-14-future-ability-ideas.md](../../rule-content-plan-14-future-ability-ideas.md) | план | `~ПЛАН~` | — |
| [rule-content-plan-15-melee-tactics-design.md](../../rule-content-plan-15-melee-tactics-design.md) | план | `~ПЛАН~` | 2026-09-20 |
| [rule-roadmap.md](../../rule-roadmap.md) | план | `~ПЛАН~` | 2026-10-05 |
| [spell-review-01.md](../../spell-review-01.md) | канон | `~ПЛАН~` | 2026-09-07 |
| [spell-roadmap.md](../../spell-roadmap.md) | план | `~ПЛАН~` | 2026-09-07 |
| [rule-content-plan-07-languages.md](../../rule-content-plan-07-languages.md) | план | `~В РАБОТЕ~` | 2026-09-15 |
| [rule-content-plan-10-melee-power.md](../../rule-content-plan-10-melee-power.md) | план | `~В РАБОТЕ~` | 2026-10-25 |
| [rule-content-roadmap.md](../../rule-content-roadmap.md) | план | `~В РАБОТЕ~` | 2026-09-16 |
| [rule-plan-05.md](../../rule-plan-05.md) | план | `~В РАБОТЕ~` | 2026-10-05 |
| [spell-inventory-01.md](../../spell-inventory-01.md) | канон | `~В РАБОТЕ~` | 2026-09-07 |
| [spell-plan-02-slice.md](../../spell-plan-02-slice.md) | план | `~В РАБОТЕ~` | 2026-09-07 |
| [rule-plan-01.md](../../rule-plan-01.md) | план | `~ЧАСТИЧНО~` | 2026-09-05 |
| [rule-plan-03.md](../../rule-plan-03.md) | план | `~ЧАСТИЧНО~` | 2026-10-05 |
| [rule-plan-04.md](../../rule-plan-04.md) | план | `~ЧАСТИЧНО~` | 2026-10-05 |
| [rule-plan-07.md](../../rule-plan-07.md) | план | `~ОТМЕНЁН~` | 2026-10-06 |
| [rule-content-plan-03-senses.md](../../rule-content-plan-03-senses.md) | план | `~ОТЛОЖЕН~` | — |
| [rule-content-plan-01-editorial.md](../../rule-content-plan-01-editorial.md) | план | `~СДЕЛАНО~` | — |
| [rule-content-plan-02-concentration.md](../../rule-content-plan-02-concentration.md) | план | `~СДЕЛАНО~` | 2026-09-13 |
| [rule-content-plan-05-knowledge-game.md](../../rule-content-plan-05-knowledge-game.md) | план | `~СДЕЛАНО~` | 2026-09-13 |
| [rule-content-plan-06-wounds.md](../../rule-content-plan-06-wounds.md) | план | `~СДЕЛАНО~` | 2026-09-13 |
| [rule-plan-02-init-surface.md](../../rule-plan-02-init-surface.md) | план | `~СДЕЛАНО~` | 2026-09-07 |
| [rule-plan-06.md](../../rule-plan-06.md) | план | `~СДЕЛАНО~` | 2026-10-06 |
| [rule-plan-08.md](../../rule-plan-08.md) | план | `~СДЕЛАНО~` | 2026-10-06 |
| [rule-plan-09.md](../../rule-plan-09.md) | план | `~СДЕЛАНО~` | 2026-10-06 |
| [rule-plan-10.md](../../rule-plan-10.md) | план | `~СДЕЛАНО~` | 2026-10-07 |
| [spell-plan-01-inventory.md](../../spell-plan-01-inventory.md) | план | `~СДЕЛАНО~` | 2026-09-07 |
| [spell-plan-03-contract.md](../../spell-plan-03-contract.md) | план | `~СДЕЛАНО~` | 2026-09-08 |
| [spell-plan-04-import.md](../../spell-plan-04-import.md) | план | `~СДЕЛАНО~` | 2026-09-08 |
| [spell-plan-05-frontend.md](../../spell-plan-05-frontend.md) | план | `~СДЕЛАНО~` | 2026-09-08 |
| [spell-plan-06-cast-check.md](../../spell-plan-06-cast-check.md) | план | `~СДЕЛАНО~` | 2026-09-09 |
| [spell-plan-07-runtime-slice.md](../../spell-plan-07-runtime-slice.md) | план | `~СДЕЛАНО~` | 2026-09-10 |
| [spell-plan-08-runtime-expansion.md](../../spell-plan-08-runtime-expansion.md) | план | `~СДЕЛАНО~` | 2026-09-11 |
| [spell-plan-09-magic-runtime.md](../../spell-plan-09-magic-runtime.md) | план | `~СДЕЛАНО~` | 2026-09-11 |
| [rule-content-queue.md](../../rule-content-queue.md) | канон | ? | 2026-09-16 |
| [rule-system.md](../../rule-system.md) | канон | ? | 2026-08-30 |

