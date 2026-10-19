# Core/Mail

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Core/Mail --zone=tr`.

- **Путь:** `modules/Core/Mail`
- **Неймспейс:** `Mifrial\Core\Mail`
- **Контейнер:** `Mifrial\Core\Mail\Container\MailContainer`
- **Локатор:** `Mifrial\Core\Mail\Interface\Container\IMailContainer`
- **Setup:** `Closure(IServiceLocator)`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Core\Mail\Interface\Service\IMail`
- `Mifrial\Core\Mail\Service\MailFlushHandler`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

## Контракты `Interface/`

- `Interface/Container/IMailContainer.php` — Публичный контейнер модуля Mail.
- `Interface/Service/IMail.php` — Сосед: поставить событие в очередь.

## Зависимости

- **Зависит от:** [Core/Agent](Agent.md), [Core/Kernel](Kernel.md), [Core/SmartTable](SmartTable.md)
- **От него зависят:** [Core/Auth](Auth.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [mail-roadmap.md](../../mail-roadmap.md) | план | `~ПЛАН~` | 2026-09-03 |
| [mail-plan-01-queue.md](../../mail-plan-01-queue.md) | план | `~СДЕЛАНО~` | 2026-09-03 |

