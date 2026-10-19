# Core/Event

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Core/Event --zone=tr`.

- **Путь:** `modules/Core/Event`
- **Неймспейс:** `Mifrial\Core\Event`
- **Контейнер:** `Mifrial\Core\Event\Container\EventContainer`
- **Локатор:** `Mifrial\Core\Event\Interface\Container\IEventContainer`
- **Setup:** —
- **Карта входа:** [README.md](../../../../www/mifrial/modules/Core/Event/README.md)
- **Канон:** раздел «Карта доверия: стабильные модули Core» в [contract-status.md](../../contract-status.md)

## Порты

- `Mifrial\Core\Event\Interface\Service\IEventManager`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

## Контракты `Interface/`

- `Interface/Container/IEventContainer.php` — Публичный контейнер портов модуля Event.
- `Interface/Service/IEventListener.php` — Контракт синхронного listener runtime-события.
- `Interface/Service/IEventManager.php` — Публичный порт синхронной runtime-доставки событий.
- `Interface/Value/IEventIssue.php` — Контракт структурированной ошибки или warning события.
- `Interface/Value/IEventPayload.php` — Контракт неизменяемого payload runtime-события.
- `Interface/Value/IEventResult.php` — Контракт локального или агрегированного результата события.

## Зависимости

- **Зависит от:** [Core/Kernel](Kernel.md)
- **От него зависят:** [Roleplay/Game](../Roleplay/Game.md)

## События

—

## Планы и документы

нет

