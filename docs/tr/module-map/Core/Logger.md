# Core/Logger

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Core/Logger --zone=tr`.

- **Путь:** `modules/Core/Logger`
- **Неймспейс:** `Mifrial\Core\Logger`
- **Контейнер:** `Mifrial\Core\Logger\Container\LoggerContainer`
- **Локатор:** `Mifrial\Core\Logger\Interface\Container\ILoggerContainer`
- **Setup:** `Mifrial\Core\Logger\Setup\LoggerModuleSetup`
- **Карта входа:** [README.md](../../../../www/mifrial/modules/Core/Logger/README.md)
- **Канон:** раздел «Карта доверия: стабильные модули Core» в [contract-status.md](../../contract-status.md)

## Порты

- `Mifrial\Core\Logger\Service\TableLogWriter`
- `Mifrial\Core\Logger\Service\LoggerHttpService`
- `Mifrial\Core\Logger\Action\FindLogPageAction`
- `Mifrial\Core\Logger\Action\GetLogAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `logger.findPage` | `FindLogPageAction` | да |
| `logger.get` | `GetLogAction` | да |

## Контракты `Interface/`

- `Interface/Container/ILoggerContainer.php` — Публичный контейнер модуля Logger.

## Зависимости

- **Зависит от:** [Core/Kernel](Kernel.md), [Core/SmartTable](SmartTable.md), [Core/User](User.md)
- **От него зависят:** —

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [logger-plan-01.md](../../logger-plan-01.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [logger-plan-02.md](../../logger-plan-02.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [logger-plan-03.md](../../logger-plan-03.md) | план | `~СДЕЛАНО~` | 2026-09-05 |
| [logger-plan-04-view.md](../../logger-plan-04-view.md) | план | `~СДЕЛАНО~` | 2026-09-10 |
| [logger-plan-05-vue-datetime.md](../../logger-plan-05-vue-datetime.md) | план | `~СДЕЛАНО~` | 2026-09-10 |

