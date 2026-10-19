# Core/Kernel

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Core/Kernel --zone=tr`.

- **Путь:** `modules/Core/Kernel`
- **Неймспейс:** `Mifrial\Core\Kernel`
- **Контейнер:** `Mifrial\Core\Kernel\Container\KernelContainer`
- **Локатор:** `Mifrial\Core\Kernel\Interface\Container\IKernelContainer`
- **Setup:** `Mifrial\Core\Kernel\Setup\KernelModuleSetup`
- **Карта входа:** [README.md](../../../../www/mifrial/modules/Core/Kernel/README.md)
- **Канон:** раздел «Карта доверия: стабильные модули Core» в [contract-status.md](../../contract-status.md)

## Порты

- `Mifrial\Core\Kernel\Action\PingAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `mifrial.ping` | `PingAction` | нет |

## Контракты `Interface/`

- `Interface/Action/IActionHandler.php` — Маркер обработчика действия.
- `Interface/Action/IActionInput.php` — Маркер входного DTO action: JSON гидрирует конструктор, не имя параметра handle.
- `Interface/Container/IKernelContainer.php` — Публичный контейнер модуля Kernel.
- `Interface/Container/IModuleContainer.php` — Контракт контейнера портов модуля.
- `Interface/Http/IHttpRequest.php` — Контракт снимка HTTP-запроса.
- `Interface/Http/IRequestBinder.php` — Кладёт снимок актора в контекст запроса. Реализация — модуль сессии.
- `Interface/Http/IRequestContext.php` — Процессный снимок входящих cookie и очередь исходящих.
- `Interface/Service/IApplication.php` — Контракт фасада процесса: HTTP и внутренний dispatch.
- `Interface/Service/IDispatcher.php` — Контракт диспетчера действий.
- `Interface/Service/ILogger.php` — Порт записи журнала ядра.
- `Interface/Service/IModuleManager.php` — Контракт загрузки модулей и маршрутов.
- `Interface/Service/IModuleSetup.php` — Карты и data-шаги модуля для CLI setup. Не порт контейнера соседа.
- `Interface/Service/IRuntimeConfig.php` — Локальная конфигурация процесса для портов модулей.
- `Interface/Service/IServiceLocator.php` — Контракт каталога контейнеров модулей.
- `Interface/Service/ISetupStep.php` — Один data-шаг установки модуля. Идемпотентен при повторном CLI.
- `Interface/Service/ITransactionRunner.php` — Одна единица работы на соединении вызывающего.

## Зависимости

- **Зависит от:** [Core/SmartTable](SmartTable.md)
- **От него зависят:** [Core/Agent](Agent.md), [Core/Auth](Auth.md), [Core/Cache](Cache.md), [Core/Event](Event.md), [Core/Logger](Logger.md), [Core/Mail](Mail.md), [Core/SmartTable](SmartTable.md), [Core/User](User.md), [Messages/Chat](../Messages/Chat.md), [Roleplay/Character](../Roleplay/Character.md), [Roleplay/Game](../Roleplay/Game.md), [Roleplay/Keyword](../Roleplay/Keyword.md), [Roleplay/Mechanic](../Roleplay/Mechanic.md), [Roleplay/Rule](../Roleplay/Rule.md), [Roleplay/RuleSpace](../Roleplay/RuleSpace.md), [Versioning/Space](../Versioning/Space.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [kernel-plan-01-setup.md](../../kernel-plan-01-setup.md) | план | `~СДЕЛАНО~` | 2026-09-02 |
| [kernel-plan-02-action-input.md](../../kernel-plan-02-action-input.md) | план | `~СДЕЛАНО~` | 2026-09-03 |

