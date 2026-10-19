# Core/Agent

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Core/Agent --zone=tr`.

- **Путь:** `modules/Core/Agent`
- **Неймспейс:** `Mifrial\Core\Agent`
- **Контейнер:** `Mifrial\Core\Agent\Container\AgentContainer`
- **Локатор:** `Mifrial\Core\Agent\Interface\Container\IAgentContainer`
- **Setup:** `Mifrial\Core\Agent\Setup\AgentModuleSetup`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Core\Agent\Interface\Service\IAgents`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

## Контракты `Interface/`

- `Interface/Container/IAgentContainer.php` — Публичный контейнер модуля Agent.
- `Interface/Service/IAgentHandler.php` — Обработчик тика агента. Класс донора, не PHP в БД.
- `Interface/Service/IAgents.php` — Расписание агентов и тик CLI.

## Зависимости

- **Зависит от:** [Core/Kernel](Kernel.md), [Core/SmartTable](SmartTable.md)
- **От него зависят:** [Core/Mail](Mail.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [agent-plan-01-tick.md](../../agent-plan-01-tick.md) | план | `~СДЕЛАНО~` | 2026-09-03 |

