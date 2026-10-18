# Core\Event

Карта входа: как пользоваться шиной runtime-событий. Пути даны от корня
репозитория.

**Роль:** синхронная process-local доставка событий. Подписка слушателей на
каноническое имя события, синхронный вызов одного события всеми слушателями в
детерминированном порядке и агрегация результата. Межпроцессной, отложенной и
асинхронной доставки нет.

**Канон:** [`architecture.md`](../../../../../docs/tr/architecture.md) — раздел
«EventManager (sync MVP реализован, async DEFERRED)».
План (сверять с кодом) —
[`event-manager-backend-plan.md`](../../../../../docs/specs/event-manager-backend-plan.md).

**Сверено по коду:** 2026-10-18.

## Ключевые типы

| Тип | Путь | Зачем |
|---|---|---|
| `IEventManager` | `Interface/Service/IEventManager.php` | Публичный порт: `on()`, `off()`, `fire()` |
| `EventManager` | `Service/EventManager.php` | Реализация порта |
| `EventPortFactory` | `Service/EventPortFactory.php` | Сборка порта без зависимостей от соседей |
| `IEventListener` | `Interface/Service/IEventListener.php` | Слушатель: `handle(IEventPayload): ?IEventResult` |
| `IEventPayload` | `Interface/Value/IEventPayload.php` | Неизменяемый payload |
| `IEventResult` / `EventResult` | `Interface/Value/IEventResult.php`, `Value/EventResult.php` | Агрегированный результат, `merge()` |
| `IEventIssue` / `EventIssue` | `Interface/Value/IEventIssue.php`, `Value/EventIssue.php` | Проблема в результате |
| `EventSubscription` | `Value/EventSubscription.php` | Непрозрачный token подписки |
| `EventException` | `Exception/EventException.php` | `EVENT_INVALID`, `EVENT_RECURSION`, `EVENT_LISTENER_FAILED` |
| `IEventContainer` / `EventContainer` | `Interface/Container/IEventContainer.php`, `Container/EventContainer.php` | Локатор модуля |

Модуль без таблиц и без setup.

## Как пользоваться

```php
/** @var IEventManager $events */
$events = $serviceLocator->get(IEventContainer::class)->get(IEventManager::class);

$subscription = $events->on('Roleplay\Game.Delivery::Recorded', $listener, 10);
$result = $events->fire('Roleplay\Game.Delivery::Recorded', $payload);
$events->off($subscription);
```

Объявление порта в `module.config.php` модуля:

```php
'ports' => [
    IEventManager::class => static fn (): IEventManager => (new EventPortFactory())->create(),
],
```

Слушатель:

```php
final class DeliveryListener implements IEventListener
{
    public function handle(IEventPayload $payload): ?IEventResult
    {
        // ... вернуть IEventResult или null
    }
}
```

## Инварианты

- Формат имени события:
  `ModuleGroup\ModuleName.Subject::LifecycleOperation`
  (пример `Roleplay\Game.Delivery::Recorded`). Несовпадение, пустое имя и
  внешние пробелы → `EVENT_INVALID`.
- Порядок: по `priority` (меньше — раньше), при равенстве — FIFO по порядку
  регистрации.
- `fire()` работает по снапшоту слушателей, снятому на входе: `on`/`off` во
  время доставки не меняют текущий обход.
- `EventSubscription` привязан к конкретному `EventManager`; чужой token →
  `EVENT_INVALID`, повторный `off` того же token — no-op, сериализация token
  запрещена.
- Повторный вход в уже активное имя в текущем стеке → `EVENT_RECURSION` с
  цепочкой.
- Слушатель, вернувший результат с `isSuccessful() === false`, **останавливает**
  цепочку; остальные слушатели не вызываются, результат мёржится.
- `Throwable` слушателя оборачивается в `EVENT_LISTENER_FAILED`;
  `MifrialException` (ошибка проекта) пробрасывается без обёртки.
- `fire()` возвращает агрегированный `EventResult` даже без слушателей.
- Модуль не знает SmartTable, Mail, Agent, Logger, HTTP и SSE.
- Асинхронная доставка, `afterCommit`, outbox и подписки из БД — `DEFERRED`, не
  часть sync MVP.

## Точки расширения

- Новый слушатель: `implements IEventListener` + `on()`. Подписки регистрируются
  **программно**, не через конфиг.
- Свои реализации `IEventPayload` / `IEventResult` / `IEventIssue` допустимы.
- Ключ `events` в `module.config.php` — инертная декларация: рантайм его не
  читает и не валидирует.

## Зависимости

- Импортирует: только `Core/Kernel` (`MifrialException`, `IModuleContainer`,
  `ModuleContainer`).
- Потребитель: `Roleplay/Game` — слушатель `GameDeliveryListener`; порт
  используют `GameDeliverySignal`, `GameEconomy`, `GameStrikes`, `GameTurns`,
  `GameWoundActions`, `GameManualSheetActions` и их порт-фабрики.

## Тесты

- `tests/EventManagerTest.php` — `on`/`off`/`fire`, priority и FIFO, снапшот,
  рекурсия, остановка по неуспешному результату, обёртка `Throwable`, формат
  имени.
- `tests/EventModuleBootTest.php` — загрузка через Core-контейнер, локатор,
  содержимое `module.config.php`.

## Не описано / OPEN

- Не реализовано: async/отложенная доставка, `afterCommit`, outbox,
  персистентные подписки, межпроцессная шина.
- Обработка сбоя доставки — на стороне потребителя (`GameDeliverySignal` глушит
  исключения), не в модуле Event.
