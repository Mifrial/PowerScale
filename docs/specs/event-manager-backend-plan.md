# Backend EventManager — implementation plan

**Статус:** implementation/reconciliation plan, 2026-09-28.
**Scope:** только общий runtime EventManager backend.
**Не входит:** Mechanic Engine, Character build/validation, Rule Engine, Game
events, SSE, mail delivery и persistent event platform.

## 1. Executive summary

### Текущий статус

Синхронный process-local backend EventManager реализован в `Core/Event` и
покрыт unit/boot tests:

- `www/mifrial/modules/Core/Event/Service/EventManager.php:20–301`;
- `www/mifrial/modules/Core/Event/Interface/Service/IEventManager.php:11–48`;
- `www/mifrial/modules/Core/Event/tests/EventManagerTest.php:20–456`;
- `www/mifrial/modules/Core/Event/tests/EventModuleBootTest.php:20–66`;
- `www/mifrial/modules/Core/Event/module.config.php:1–15`.

Не реализованы как часть EventManager и остаются отдельными boundaries:

- Character/Game domain event producers and consumers;
- after-commit integration;
- durable event/outbox storage;
- SSE/realtime delivery;
- retries and persistent delivery idempotency.

Следовательно, Phase 1 и Phase 2 ниже описывают уже выполненный sync MVP и
его проверяемые границы, а не список ещё не созданных файлов. Будущая
интеграция Character/Game начинается только после отдельного решения о
transactional outbox и post-commit delivery.

Есть несколько event-like механизмов, но они не являются общей шиной:

- `Core/Mail`: собственный каталог `mail_event`, таблица `mail_job` и фасад
  `IMail::trigger()`;
- `Core/Agent`: расписание и in-memory handlers для CLI-тиков;
- `Kernel\Dispatcher`: dispatch HTTP/application actions по маршрутам;
- `Kernel\Http\SseEmitter`: транспортный формат `event:`;
- `Core/Logger`: технические записи, не события;
- пустой ключ `'events' => []` во всех текущих `module.config.php`.

### Оставшиеся компоненты

Для domain integration нужны:

1. typed Character/Game event payloads;
2. producer/consumer registration;
3. explicit post-operation failure policy;
4. after-commit or outbox boundary, если реакция должна быть надёжной;
5. SSE/realtime adapter;
6. queue contract для durable delivery, retry/failure/idempotency и
   transaction boundary.

### Минимальный scope v1

Рекомендуемый MVP:

- отдельный backend-модуль `Core/Event` (namespace `Mifrial\Core\Event`);
- `IEventManager`;
- `on`, `off`, `fire`;
- string event name;
- immutable `IEventPayload` with read-only access and separate payload classes
  of consumers;
- subscription token;
- synchronous listeners;
- deterministic priority и стабильный порядок внутри priority;
- исключение listener пробрасывается с сохранением исходного throwable;
- Core container, constructor DI, unit и boot integration tests.

`fire()` не должен автоматически делать запись в БД, лог, mail job или SSE.

### Что намеренно не входит

EventManager не отвечает за:

- HTTP action dispatch;
- `Core/Mail` event catalog, templates, delivery и mail jobs;
- `Core/Agent` scheduler;
- SSE transport или имена SSE-событий;
- Logger и persistent audit log;
- domain event storage, outbox, retries и idempotency без отдельного контракта;
- Character, Mechanic Engine, Rule Engine и Game Engine.

Пример `Roleplay\Character.Character::afterUpdate` допустим только как будущий потребитель общего
runtime delivery. Он не является частью этой реализации. Для той же причины
`runEvent` не принимается за целевую архитектуру backend EventManager.

## 2. Evidence inventory

Статусы:

- `IMPLEMENTED` — подтверждено кодом;
- `PARTIAL` — есть близкий механизм, но не заявленный контракт;
- `OPEN` — требование/решение отсутствует;
- `CODE_GAP` — документация требует компонент, которого нет в коде.

### EventManager и архитектурное требование

| Evidence | Подтверждение | Статус |
|---|---|---|
| `docs/tr/architecture.md`, раздел «Дополнения сервера» | Sync-контракт `fire`/`on`/`off` и listeners; async queue вынесена в deferred | `sync IMPLEMENTED; async DEFERRED` |
| `docs/tr/smarttable.md`, раздел «Не v1» | CRUD events → EventManager отмечены как `OPEN`, подключать осторожно | `OPEN` |
| `docs/tr/history/TR-legacy-2026-08.md`, около 2437–2553 | Старый исторический EventManager и `fire/on/off` | `OPEN`, не текущий код |
| `www/mifrial/modules/Core/Event/*` | `EventManager`, `IEventManager`, typed payload/result, container и tests | `IMPLEMENTED` |
| `www/mifrial/composer.json::require` | Runtime требует `illuminate/database`, но не `illuminate/events`; lock/transitive metadata не является реализацией EventManager | `IMPLEMENTED`, граница проверена |

### `module.config.php.events`

Ключ `'events' => []` присутствует в:

- `modules/Core/Kernel/module.config.php`;
- `modules/Core/Agent/module.config.php`;
- `modules/Core/Mail/module.config.php`;
- `modules/Core/Logger/module.config.php`;
- `modules/Core/SmartTable/module.config.php`;
- `modules/Core/Cache/module.config.php`;
- `modules/Core/Auth/module.config.php`;
- `modules/Core/User/module.config.php`;
- `modules/Messages/Chat/module.config.php`;
- `modules/Roleplay/Keyword/module.config.php`;
- `modules/Roleplay/Mechanic/module.config.php`;
- `modules/Roleplay/Rule/module.config.php`;
- `modules/Roleplay/RuleSpace/module.config.php`;
- `modules/Roleplay/Character/module.config.php`;
- `modules/Versioning/Space/module.config.php`;
- `modules/Core/Kernel/tests/fixtures/modules/Demo/LazyStub/module.config.php`.

`ModuleManager::loadModuleConfig()` (`modules/Core/Kernel/Service/ModuleManager.php`)
только проверяет, что конфиг — массив. `ModuleSetupCollector` (`.../Setup/
ModuleSetupCollector.php`) читает только `setup`. `ModuleContainerFactory`/
`ModuleContainerBinder` используют `container`, `locator` и `ports`.
`routes` собирается отдельным `ModuleRouteCatalog`.

Следовательно, текущий `events`:

- не имеет установленного формата;
- не валидируется;
- не попадает в registry;
- не вызывает runtime registration;
- не означает наличие EventManager.

Для `Core/Mail` это особенно важно: его реальные mail events находятся в
таблице `mail_event`, а не в `module.config.php.events`.

### Module/container setup

| Evidence | Подтверждение | Статус |
|---|---|---|
| `ApplicationFactory::assemble()` | Создаёт `ServiceLocator`, `ModuleManager`, binder; `loadCore()` или `loadAllFromDisk()` | `IMPLEMENTED` |
| `ApplicationFactory::registerLazyFromCatalog()` | Lazy catalog регистрирует слот контейнера по интерфейсу | `IMPLEMENTED` |
| `ModuleContainer::get()` | Lazy port factory, memoization, circular-port guard | `IMPLEMENTED` |
| `ServiceLocator::get()` | Lazy module container, circular-container guard | `IMPLEMENTED` |
| `ModuleContainerBinder::bindEager()` / `registerLazy()` | Container integration и freeze | `IMPLEMENTED` |
| `ModuleManager::getRoutes()` / `Dispatcher::dispatch()` | Application action dispatch, не event dispatch | `IMPLEMENTED`, отдельный контур |
| `config/modules.php` | Lazy entries для прикладных модулей; Core-модули грузятся через `loadCore()` | `IMPLEMENTED` |

### Queue, jobs и Agent

| Evidence | Подтверждение | Статус |
|---|---|---|
| `Core/Mail/Interface/Service/IMail.php::trigger()` | Ставит mail job по известному mail event code | `IMPLEMENTED`, специализированно |
| `Core/Mail/Service/MailService.php::trigger()` | `MailEventRepository` → `MailJobRepository::addPending()`; optional inline flush | `IMPLEMENTED`, специализированно |
| `Core/Mail/Repository/MailJobRepository.php` | Хранение pending/sent/failed mail job, `event_id` | `IMPLEMENTED`, mail-only |
| `Core/Mail/Service/MailFlushService.php` | Рендер, transport, failed state и logger для mail job | `IMPLEMENTED`, mail-only |
| `Core/Agent/Interface/Service/IAgents.php` | `ensureAgent`, `bindHandler`, `tick` | `IMPLEMENTED`, scheduler-only |
| `Core/Agent/Service/AgentService.php::tick()` | Due rows, in-memory handler, exception logging, `last_run_at` | `IMPLEMENTED`, не generic queue |
| `Core/Agent/module.config.php` | `ports` содержит `IAgents`, `routes` и `events` пусты | `IMPLEMENTED`, но event registration нет |
| `Core/Mail/module.config.php` | `agents => ['mail.flush' => MailFlushHandler::class]` | `IMPLEMENTED`, donor-specific |
| `Core/Agent/agent` table | Расписание, не arbitrary event/job payload | `IMPLEMENTED`, не queue contract |

Общей очереди, worker-а произвольных jobs, retry/idempotency/failure contract
для runtime events в репозитории не найдено. Mail queue нельзя расширять до
общей очереди молча: она связана с `mail_event`, templates и transport.

### Event-like механизмы, которые не следует смешивать

| Механизм | Evidence | Граница |
|---|---|---|
| Mail events | `Core/Mail/Table/MailEventTable.php`, `MailEventRepository`, `IMail::trigger()` | Каталог шаблонных mail-событий и jobs |
| Mail `event_id` | `Core/Mail/Repository/MailJobRepository.php`, tests `MailMysqlTest.php` | FK/идентификатор mail catalog row, не runtime event identity |
| Logger records | `Core/Logger`, `ILogger`, `TableLogWriter` | Persistent technical log, не delivery |
| Application actions | `Core/Kernel/Service/Dispatcher.php`, `IDispatcher`, `Application::dispatch()` | Запрос → action handler → `ActionResponse` |
| SSE names | `Core/Kernel/Http/SseEmitter.php::writeEvent()`; `Core/Kernel/tests/SseHttpTest.php` | Wire-format `event:` для SSE |

## 3. Scope and boundaries

### Рабочее определение

EventManager — in-process backend service для доставки runtime-события
подписанным listeners в текущем PHP process. Он знает event name, typed
payload и registry handlers. В sync v1 он не знает БД, HTTP, Mail, Logger,
SSE и доменные таблицы.

Async boundary появляется только после утверждения отдельного queue contract.
Тогда EventManager может передать событие queue adapter-у, но не должен
становиться владельцем persistent jobs, worker lifecycle или retry policy.

### Важное различие с примером Bitrix

Предлагаемый вызов:

```php
$eventManager->fire('Roleplay\Character.Character::afterUpdate', $payload);
```

не должен автоматически означать «вызвать все обработчики с arbitrary
массивом». В отличие от грубого Bitrix-подобного варианта payload должен быть
объектом конкретного контракта, а registration — выдавать token:

```php
$subscription = $eventManager->on(
    'Roleplay\Character.Character::afterUpdate',
    $listener,
    priority: 100,
);
$eventManager->off($subscription);
```

`Roleplay\Character` и `Game` в этом файле — только гипотетические будущие
потребители. Их update pipeline, session delivery и актуализация участников
не входят в план.

В этом примере `off()` не должен вызываться сразу после `on()`: это было бы
эквивалентно «зарегистрировать listener и немедленно отменить регистрацию».
Корректный lifecycle выглядит так:

```text
module boot/setup
  → subscription = eventManager->on(...)
  → listener работает весь lifetime процесса
module teardown/test cleanup
  → eventManager->off(subscription)
```

Для обычных PHP-модулей, загруженных на весь application/CLI process, `off()`
часто вообще не нужен: subscription живёт столько же, сколько контейнер и
EventManager. Он нужен для:

- временной подписки теста;
- plugin/module teardown;
- scoped listener-а, который нельзя оставить до конца процесса;
- замены listener-а без удаления чужих registrations.

`off()` принимает именно token, а не только event name/listener, чтобы один
модуль не снял чужую или дублирующую подписку. Поэтому владелец регистрации
должен сохранить token в своём setup/registration object. Если registration
делегируется общему module bootstrap, token хранит bootstrap; прикладному
listener-у получать его повторно не требуется.

Event name является ключом registry и глобален внутри одного экземпляра
EventManager. В проекте module reference технически хранится как `group/name`,
а PHP-классы имеют namespace `Mifrial\ModuleGroup\ModuleName` (например,
`Mifrial\Roleplay\Character`). Для event identity предлагается логический
module prefix без корневого `Mifrial`: `ModuleGroup\ModuleName` (например,
`Roleplay\Character`). Disk key и event identity нельзя смешивать.

Рекомендуемый формат event name:

```text
<ModuleGroup>\<ModuleName>.<Subject>::<LifecycleOperation>
```

`Subject` — конкретная сущность, таблица или resource owner, а не сам модуль.
Например:

```text
TestGroup\TestModule.Test::beforeCreate
TestGroup\TestModule.Test::beforeDelete
Roleplay\Character.Character::afterUpdate
```

Для v1 EventManager проверяет этот формат regex-правилом для
`ModuleGroup`, `ModuleName`, `Subject` и `LifecycleOperation`: каждый сегмент
начинается с латинской буквы и продолжается латинскими буквами, цифрами или
`_`. Альтернативный shorthand, пробелы и автоматическое добавление prefix не
разрешаются.

Producer владеет константой/публичным контрактом имени; consumer импортирует
этот контракт, а не дублирует свободную строку. EventManager сам не должен
разруливать collision одинаковых строк: одинаковый event name означает одну
логическую точку подписки. Collision prevention — ответственность naming
convention и integration tests producer-а.

Для `module.config.php['events']` используется только полный event code:

```php
'events' => [
    CharacterEventNames::AFTER_UPDATE => [
        [
            'handler' => GameCharacterUpdatedListener::class,
            'priority' => 100,
        ],
    ],
],
```

`CharacterEventNames::AFTER_UPDATE` содержит, например,
`Roleplay\Character.Character::afterUpdate`. Collector не добавляет prefix,
не различает local/external shorthand и не преобразует строку: он только
валидирует полный code и регистрирует handler. Это одна форма записи для
событий своего и чужого модуля. DB-driven subscriptions также хранят полный
event code.

### Проверка модели на двух сценариях

Эти сценарии показывают, что под одним словом «событие» скрываются два разных
контракта:

1. **guarded lifecycle event** — listener может запретить операцию до записи;
2. **notification event** — операция уже успешно совершена, и listeners должны
   узнать об этом.

Они могут быть сведены к одному `fire(): EventResult`, если сам `EventResult`
умеет выразить success/failure/errors/stopped. Различие остаётся в
lifecycle-фазе события: до записи result может запретить операцию, после
успешной операции result описывает доставку notification.

#### Сценарий 1: валидация записей Test перед create/delete

Предположим, модуль `TestGroup/TestModule` владеет таблицей `Test`, открытой
через SmartTable, а две проверки должны выполняться в строгом порядке.
Концептуальный flow:

```text
ST/Test service
  → собрать TestBeforeCreate/TestBeforeDelete payload
  → guarded lifecycle dispatch
      → validator A (priority 100)
      → validator B (priority 200)
  → только если valid: SmartTable add/delete в той же domain transaction
```

Event payload должен быть отдельным DTO, например
`TestRecordCreatePayload` или `TestRecordDeletePayload`, а не сырым массивом.
Он может содержать operation, table identity, proposed fields/record,
actor snapshot и expected version, но EventManager не должен сам читать
SmartTable или решать права.

Регистрация должна происходить кодом модуля/его provider-а при boot/setup:

```php
$eventManager->on(
    'TestGroup\TestModule.Test::beforeCreate',
    new TestCreateValidatorA(),
    priority: 100,
);
$eventManager->on(
    'TestGroup\TestModule.Test::beforeCreate',
    new TestCreateValidatorB(),
    priority: 200,
);
```

Это не означает, что администратор UI может сохранить PHP class-string или
произвольную функцию в БД. UI может включить заранее разрешённую policy
модуля, но загрузка и вызов PHP listener остаются серверной конфигурацией.

Здесь не нужен отдельный `IEventValidator`. Тот же `IEventManager::fire()`
может возвращать `EventResult` с `isSuccessful`, errors и признаком
остановки цепочки. Для `beforeCreate`/`beforeDelete` listener должен вернуть
успешный или ошибочный `EventResult`; `null` допустим как shorthand успешного
результата:

```php
$result = $eventManager->fire('TestGroup\TestModule.Test::beforeCreate', $payload);
if (!$result->isSuccessful()) {
    return $result->getErrors();
}
```

Пример listener-а:

```php
$eventResult = new EventResult();
$eventResult->addWarning(new EventIssue('NAME_NORMALIZED', 'Name was normalized'));
$eventResult->addError(new EventIssue('INVALID_NAME', 'Name is invalid', 'name'));
$eventResult->addPayload(new TestNormalizationPayload($normalizedName));

return $eventResult;
```

Здесь `EventResult` — не статический набор factory-методов и не массив
ошибок. Он является локальным mutable accumulator одного listener-а:

- `addWarning()` не делает результат неуспешным;
- `addError()` делает результат неуспешным;
- `addPayload()` добавляет typed output listener-а;
- `isSuccessful()` вычисляется из наличия errors, а не устанавливается
  отдельным public setter-ом.

EventManager собирает результаты listeners в общий итоговый `EventResult`.
Listener-ы не получают общий mutable accumulator друг друга: это позволяет
сохранить изоляцию и определить, какой listener создал warning/error/payload.

Единый `EventResult` имеет такую семантику:

- listener-ы вызываются по priority и FIFO;
- listener может вернуть `null` (успех без результата) или собственный
  `IEventResult`;
- `addError()` в возвращённом результате прекращает цепочку по fail-fast policy
  в любой lifecycle-фазе; для post-operation event это не
  откатывает уже завершённую operation;
- исключение listener-а не превращается в успешный result;
- `fire()` возвращает агрегированный `EventResult`;
- validation запускается до `add/delete`, а commit/rollback остаётся у
  владельца domain operation.

Адаптер может завернуть существующую функцию вида
`validate($eventPayload): bool` в пустой успешный или наполненный ошибкой
`EventResult`, но целевой результат должен содержать typed
`EventIssue(code, message, path)`. Boolean — только compatibility adapter.
`TestNormalizationPayload` в примере выше также должен реализовать
`IEventPayload`; обычный массив не может быть передан в `addPayload()`.

Важная граница: если validator A изменяет внешнее состояние, а validator B
падает, EventManager не сможет откатить этот side effect. Поэтому validators
должны быть pure/read-only checks либо работать только с transaction-owned
state. EventManager не становится transaction manager.

#### Сценарий 2: Game подписывается на обновление персонажа

Здесь нужен notification event, например:

```text
Character service
  → transaction: update + validation + outbox event row
  → commit
  → fire('Roleplay\Character.Character::afterUpdate', CharacterUpdatedPayload)
      → Game listener
          → найти активные session memberships
          → построить нужное обновление projection
          → передать его Game/SSE/projection boundary
```

Payload должен быть immutable snapshot факта обновления, например:

- `characterId`;
- новый `characterVersion`;
- идентификатор/контекст игры, если он уже известен владельцу события;
- actor или source operation;
- ограниченный список изменившихся полей, если он нужен listener-у.

EventManager не должен:

- сам искать участников сессии;
- сам рассылать данные каждому участнику;
- знать про SSE connections;
- превращать каждое событие в Chat message;
- гарантировать доставку после завершения PHP process.

`Game` — будущий listener и владелец session fan-out policy. Если обновление
должно быть видно клиентам, следующий слой — Game notification/projection и
отдельный transport (в текущем scope SSE исключён). Сам факт
`CharacterChanged` не должен создавать Chat spam: combat messages идут своим
combat-chat flow, а системное сообщение о значимом GM edit допускается только
по отдельной явной policy, при необходимости одной сгруппированной записью
на операцию. Сам `Core/Event` durable delivery не предоставляет; для
Character/Game integration используется отдельный outbox boundary.

`Roleplay\Character.Character::afterUpdate` следует трактовать как
post-commit notification в интеграции Character/Game: обязательная запись
outbox создаётся в той же transaction, что и mutation, а process-local
`fire()` вызывается только после успешного commit. EventManager сам не
предоставляет `afterCommit` hook. Если конкретный producer пока не имеет
outbox, его `fire()` является best-effort уведомлением и не должен вызываться
внутри незакоммиченной transaction для listeners, читающих persisted state.

Политика результата зависит от фазы события:

- ошибка `beforeCreate`/`beforeDelete` блокирует create/delete;
- ошибка post-update observer-а не должна делать уже завершённую operation
  «неуспешным» задним числом;
- для обязательной реакции Game нужен отдельный reliable delivery contract,
  а не молчаливое усиление `fire()`.

#### Вывод из сценариев

Event module потенциально закрывает оба сценария единым result-контрактом:

```text
IEventManager  — before/guarded или after/notification по имени/фазе события
EventResult    — success/errors/invoked/stopped, агрегат dispatch
IEventQueue    — future durable async delivery, отдельный контракт
```

Различие находится в lifecycle-событии (`before...` или `after...`) и месте
вызова, а не в дополнительном EventValidation типе. EventManager всё равно не
становится transaction manager или reliable queue.

### Расширяемость и будущая регистрация из интерфейса

`EventSubscription`, `EventIssue` и `EventResult` сами по себе не должны
становиться механизмом plugin inheritance. В публичной границе лучше
типизировать поведение интерфейсами:

```php
interface IEventResult
{
    public function addWarning(IEventIssue $issue): void;

    public function addError(IEventIssue $issue): void;

    public function addPayload(IEventPayload $payload): void;

    /**
     * @return array<int, IEventIssue> Предупреждения.
     */
    public function getWarnings(): array;

    /**
     * @return array<int, IEventIssue> Ошибки.
     */
    public function getErrors(): array;

    /**
     * @return array<int, IEventPayload> Payload-ы listeners.
     */
    public function getPayloads(): array;

    public function isSuccessful(): bool;

    public function isStopped(): bool;

    public function getInvokedCount(): int;

    /**
     * Добавляет результат одного listener-а в aggregate.
     */
    public function merge(IEventResult $result, bool $stop = false): void;
}

interface IEventIssue
{
    public function getCode(): string;

    public function getMessage(): string;

    public function getPath(): ?string;
}
```

`EventResult` и `EventIssue` — стандартные реализации этих интерфейсов, а не
единственные допустимые реализации. `EventManager` должен возвращать
`IEventResult`, а `IEventResult` должен принимать `IEventIssue` и агрегировать
через публичный contract, а не проверять `instanceof EventResult`. `merge()`
одновременно добавляет локальный result, учитывает один вызов listener-а и
принимает явный stop flag. Публичный result contract намеренно
не содержит event name и остаётся в пределах quality-limit публичных методов.
Listener result может быть собственной реализацией `IEventResult`; EventManager
не обязан знать её concrete class.

`EventSubscription` может остаться concrete opaque token: это идентификатор
жизненного цикла, а не extension point. Token должен содержать непрозрачный
subscription id и owner identity конкретного EventManager; он не
сериализуется и не переносится между application processes. Это process-local
API-инвариант, а не security boundary внутри собственного кода.
`off()` проверяет owner identity до удаления и не должен повреждать registry
при malformed same-owner token. Для listener-а также лучше иметь
Публичный listener contract — `IEventListener` с
`handle(IEventPayload $payload): ?IEventResult`; он подходит для DI и
декларативной регистрации.

Первый пример предполагает более сильную возможность: пользователь UI
создаёт `Test` через SmartTable, затем выбирает уже существующий в коде
handler и сохраняет подписку в БД. Это не должно означать сохранение
произвольного PHP class-string или closure из UI. Безопасная будущая схема:

```text
module code
  → publishes handler catalog: stable handler code → factory/class
database
  → stores event name + handler code + priority + active/config
boot
  → resolves only catalogued handler codes
  → calls EventManager::on(...)
```

Например, UI сохраняет `test.validate.name`, а не
`TestGroup\TestModule\TestEventHandlerClass`. Код модуля заранее объявляет
этот handler в catalog; Event bootstrap проверяет, что code разрешён, создаёт
объект через DI и получает subscription token. Две записи с priority `100` и
`200` дают требуемый строгий порядок. `off()` для таких persistent
subscriptions выполняется при reload/disable, а не обычным request listener-ом.

### Как избежать цикла SmartTable ↔ Event

Если SmartTable в будущем начнёт публиковать generic CRUD events, направление
зависимости должно быть только таким:

```text
Core/SmartTable → Core/Event
Core/EventRegistration (future adapter) → Core/SmartTable + Core/Event
```

`Core/Event` не должен импортировать SmartTable и читать таблицу подписок.
Иначе получится цикл:

```text
SmartTable → Event → SmartTable
```

Поэтому persistent DB-driven registration не входит в базовый Event module.
Нужен отдельный adapter/module, например `Core/EventRegistration`, который
владеет subscription repository через SmartTable и при boot связывает его с
in-memory EventManager. Альтернатива — registration orchestration в
composition root, если отдельный модуль преждевременен.

Для generated ST это означает:

1. ST публикует только стабильный generic event contract и typed payload;
2. ST не знает, какие handlers выбраны пользователем в БД;
3. EventRegistration читает subscription rows и разрешает catalogued handlers;
4. EventManager выполняет уже собранные listeners;
5. права на изменение subscriptions, активность и validation config остаются
   отдельным application/admin contract.

Это потенциальная Phase 5, не часть текущего MVP. В текущем коде нет
подтверждённого persistent Event subscription storage, handler catalog или
динамического module reload.

## 4. Proposed contract

### Выбор identity/payload

Выбирается вариант **A: string event name + typed `IEventPayload`**.

| Вариант | Оценка |
|---|---|
| A. string + typed `IEventPayload` | Рекомендуется: сохраняет простой lookup, read-only boundary и расширяемые typed payload contracts |
| B. string + array | Совместим с Mail, но теряет статическую проверку, допускает неявные ключи и не соответствует строгому PHP-коду |
| C. event object + отдельный listener interface | Возможно позже, но для v1 добавляет типы и dispatch protocol без потребителя |
| D. class-string event type | Сильная типизация identity, но требует registry/инстанцирования и преждевременно связывает event name с PHP-классом |

Рекомендация не означает, что EventManager должен содержать все payload
классы. Каждый потребитель владеет своим DTO/value object и передаёт его через
общий `IEventPayload` boundary. Для первого вертикального среза достаточно
тестового payload-класса.

### Предлагаемые типы

Рекомендуемая структура модуля:

```text
modules/Core/Event/
├── Container/EventContainer.php
├── Exception/EventException.php
├── Interface/Container/IEventContainer.php
├── Interface/Service/IEventManager.php
├── Interface/Service/IEventListener.php
├── Interface/Value/IEventPayload.php
├── Interface/Value/IEventIssue.php
├── Interface/Value/IEventResult.php
├── Value/EventSubscription.php
├── Value/EventIssue.php
├── Value/EventResult.php
├── Service/EventManager.php
└── Service/EventPortFactory.php
```

Минимальный read-only payload contract:

```php
interface IEventPayload
{
    public function has(string $key): bool;

    /**
     * @return mixed Значение ключа; отсутствие ключа — исключение контракта.
     */
    public function get(string $key): mixed;
}
```

`IEventPayload` — базовая граница EventManager, а не обещание, что каждый
consumer работает с неименованным словарём. Конкретный payload может добавить
typed getters:

```php
interface ICharacterUpdatedPayload extends IEventPayload
{
    public function getCharacterId(): int;

    public function getCharacterVersion(): int;
}
```

`EventManager` видит только `IEventPayload`, а producer и listener могут
зависеть от более узкого payload interface. Не добавлять `set`, `remove` или
возврат внутренней mutable map. `all(): array` также не делать обязательной
операцией: она превращает payload в ассоциативный мешок и обходит typed
getters. Если для persistence/config всё же понадобится сериализация, добавить
отдельный serializer/adapter, а не расширять runtime payload read API.

Read-only здесь означает логическую неизменяемость, которую нужно обеспечить
реализацией: разрешены scalar/null/enum, копируемые массивы и immutable
`IEventPayload` objects. Mutable domain objects через payload запрещены.
Одного PHP-интерфейса `has/get` для deep immutability недостаточно; это
отдельный implementation test и invariant concrete payload implementation.

`Core/Event` выбран вместо backend `Core/Engine`: в текущей архитектуре
`Core/Engine` — frontend namespace, а серверного модуля Engine нет. Выделенный
`Core/EventManager` также допустим как название каталога, но `Core/Event`
лучше соответствует PHP-правилу «не копировать имя модуля в имя порта» и
оставляет `IEventManager` понятным публичным портом.

Минимальный публичный контракт:

```php
interface IEventManager
{
    public function on(
        string $eventName,
        IEventListener $listener,
        int $priority = 0,
    ): EventSubscription;

    public function off(EventSubscription $subscription): void;

    public function fire(string $eventName, IEventPayload $payload): IEventResult;
}
```

Сигнатуры являются планом, а не внесённым кодом.

`EventResult` используется и для guarded lifecycle events, и для обычных
notification events. Отличается не тип результата, а фаза и смысл event name.

#### `on`

- Принимает непустое canonical event name, `IEventListener` и integer priority.
- Возвращает opaque `EventSubscription`, содержащий registry identity.
- Регистрация синхронная и процессная; listener не сохраняется в БД.
- Одинаковый listener регистрируется повторно как отдельная subscription.
- `eventName` с trim-пустым значением или leading/trailing whitespace даёт
  `EventException(EVENT_INVALID)`. Тип `IEventListener` проверяется PHP до
  входа в метод; EventManager не добавляет prefix и не переписывает event name.
- `EventSubscription` принадлежит EventManager; `off` другого manager-а должен
  быть отвергнут, чтобы token одного процесса не управлял registry другого.

#### `off`

- Удаляет ровно subscription, соответствующую token.
- Повторный `off` рекомендуется сделать идемпотентным no-op: это безопасно для
  cleanup в `finally` и не требует знания текущего состояния.
- Не принимает event name + listener: такой API удаляет дубликаты неоднозначно.

#### `fire`

- Проверяет canonical event name и `IEventPayload`; правила проверки имени
  совпадают с `on()` и не выполняют автоматического prefixing.
- Выполняет listeners синхронно в snapshot порядка текущего dispatch.
- Возвращает `IEventResult`; стандартная aggregate implementation — mutable
  `EventResult` с количеством вызванных listeners,
  success/failure status, errors и stopped flag.
- Listener может вернуть failure result; EventManager агрегирует его и
  прекращает dispatch по fail-fast policy.
- Отсутствие listeners не является ошибкой: возвращается успешный result с
  count `0`.
- Неизвестное имя без listeners не отличается от события без подписчиков:
  отдельного event catalog в v1 нет.
- `EventResult` не становится произвольным domain result: он описывает только
  dispatch и ошибки/warnings listeners.

#### Listener contract

Нормативный listener shape:

```php
interface IEventListener
{
    public function handle(IEventPayload $payload): ?IEventResult;
}
```

PHP не позволяет выразить в общей сигнатуре, что конкретный listener принимает
конкретный payload-класс. Поэтому перед первым вызовом нужен
runtime guard/обязательное convention-тестирование; listener, получивший
неподходящий payload, должен дать typed `EventException` на boundary либо
свой `TypeError` должен быть сохранён как previous throwable. Не следует
строить reflection-based schema registry без реального потребителя.

Нельзя реализовать `IEventListener` классом, который сужает параметр до
`ICharacterUpdatedPayload`: это нарушит совместимость сигнатуры PHP. Такой
handler принимает `IEventPayload`, проверяет `instanceof` конкретного
payload-интерфейса и передаёт его во внутренний typed method. Для
declarative catalog это обязательная adapter boundary, а не optional detail.

Для closure-based unit tests можно сделать тестовый
`CallableEventListenerAdapter`, но он не является альтернативным public API и
не должен попадать в module config или DB catalog.

### `EventResult`

`EventResult` имеет две роли:

1. mutable local accumulator, который возвращает один listener;
2. mutable aggregate, который EventManager заполняет результатами listeners и
   затем отдаёт вызывающему коду.

Минимальное содержимое:

- number of invoked listeners;
- `isSuccessful(): bool`, учитывающий errors и failure status custom result;
- typed `warnings` и `errors`;
- ordered result `payloads`;
- `isStopped(): bool`, фиксируемый aggregate через `merge(..., stop: true)`;
- при необходимости subscription identity у issue/payload source.

Ошибки и предупреждения не являются массивами без контракта. Их элемент —
`EventIssue` value object с `code`, `message` и optional `path`; разные уровни
добавляются методами `addWarning(IEventIssue)` и `addError(IEventIssue)`.

Входной payload события и output payload результата — разные вещи:

- `fire($eventName, $eventPayload)` получает immutable `IEventPayload`;
- `addPayload($listenerPayload)` добавляет output одного listener-а;
- aggregate возвращает упорядоченный список output payloads всех merged
  listener results, включая result listener-а, завершившего chain с errors;
  consumer использует эти payloads только при успешном aggregate;
- EventManager не пытается склеивать payloads разных PHP-классов в один
  объект и не трактует их как запись БД.

Для сохранения строгой типизации рекомендуется
`addPayload(IEventPayload $payload)`. Универсальный `array` не является
payload contract и не должен использоваться как мешок произвольных полей.
`EventResult` не содержит async job id, mutation diff или произвольный domain
result.


## 5. Listener semantics

### Подтверждено кодом

Ни одно из перечисленных listener semantics не подтверждено backend-кодом.
Они должны быть утверждены как часть контракта до реализации. Документ
`docs/tr/architecture.md` подтверждает только наличие требования sync
listeners.

### Recommended default для v1

1. **Порядок:** меньший `priority` выполняется раньше; если priority равен —
   порядок регистрации FIFO. Это самостоятельное правило EventManager, а не
   заимствованный контракт другого runtime.
2. **Duplicate registration:** разрешена; каждая регистрация имеет собственный
   token и выполняется отдельно.
3. **`off`:** удаляет только token; повторное удаление — no-op.
4. **Удаление во время `fire`:** текущий snapshot не меняется задним числом;
   listener, попавший в snapshot, всё ещё вызывается, если до него дошла
   очередь текущего dispatch. Следующие `fire` его не вызывают.
5. **Добавление во время `fire`:** новый listener не вызывается в текущем
   dispatch, начинает работать со следующего.
6. **Нет listeners:** no-op с `EventResult(invoked=0)`.
7. **Неизвестное событие:** no-op; event catalog не вводится без потребителя.
8. **Listener exception:** немедленно прерывает chain и пробрасывается; более
   поздние listeners не вызываются.
9. **Payload:** immutable по контракту. Listener не должен менять его
   содержимое; derived result возвращается отдельным domain service.
10. **Reentrant fire:** вложенный `fire()` другого event name разрешён; каждый
    вызов имеет собственный snapshot и result.
11. **Recursion:** если event name уже находится в текущем dispatch stack,
    EventManager немедленно бросает `EventException(EVENT_RECURSION)` с цепочкой
    вроде `A → B → A`. Это ловит прямую и косвенную рекурсию и не зависит от
    произвольного числа `32`.

### Закрытое решение

- Произвольный recursion limit `32` не вводить.
- Не допускать event cycle по уже активному event name.
- Бросать `EVENT_RECURSION` до вызова следующего listener-а и сохранять
  dispatch chain для диагностики.
- Разные неповторяющиеся вложенные events разрешать.
- Payload immutable; listener не изменяет входной payload.
- Защита от бесконечной цепочки уникальных динамических event names не является
  контрактом v1; при появлении dynamic event names это будет отдельное решение.

### OPEN, требующие решения

- Нужен ли listener `stopPropagation()`; для v1 — нет, потому что нет
  подтверждённого потребителя.
- Нужны ли wildcard event names; для v1 — нет.
- Нужен ли event catalog/registration validation; для v1 — нет.
- Нужны ли once-listeners; для v1 — нет.

## 6. Async boundary

### Факты

В backend есть очередь только внутри Mail:

```text
IMail::trigger
  → MailEventRepository (mail_event)
  → MailJobRepository (mail_job)
  → MailFlushService
  → Agent mail.flush
```

У неё подтверждены storage, worker-like flush handler, failed state и logging.
Но это:

- не очередь произвольных PHP jobs;
- не runtime EventManager;
- не общий retry/idempotency contract;
- не transaction-aware event delivery;
- не контракт для Game/Character/SSE.

`Core/Agent` не выполняет arbitrary queue jobs: `IAgentHandler::run()` живёт в
памяти CLI, а таблица `agent` хранит только расписание.

### Решение

Выбирается вариант **1: v1 только sync EventManager, async deferred**.

Второй по приоритету будущий вариант — **4: EventManager публикует runtime
event, отдельный adapter создаёт job**. EventManager не должен владеть
persistent queue. Варианты «EventManager сам владеет queue» и «переиспользовать
Mail job» отклоняются: первый смешивает delivery и persistence, второй
неправильно связывает общий runtime с Mail.

Для будущего async этапа сначала нужен отдельный контракт наподобие
`IEventQueue`/`IEventJobPublisher`, но точное имя и API не фиксируются MVP.
Нужно отдельно решить payload serialization, event version, worker ownership,
retry, duplicate delivery, failure state, idempotency и transaction boundary.

## 7. Module architecture

### Где живёт модуль

Рекомендуется отдельный `modules/Core/Event`:

- EventManager — общая инфраструктура Core, доступная будущим backend-модулям;
- он не находится в Kernel, потому что Kernel отвечает за bootstrap, modules,
  actions, request context и базовые infrastructure ports;
- он не находится в `Core/Engine`: это frontend boundary и в backend дереве
  отсутствует;
- он не находится в Mail/Agent: их event-like API специализированы.

Если команда требует исторического имени `Core\EventManager`, каталог можно
назвать `modules/Core/EventManager`, но сохранить тот же отдельный lifecycle,
container и exception family. Это косметическое решение и не меняет границу.

### Container/DI

Следовать существующей цепочке:

```text
locator → EventContainer → EventPortFactory → EventManager
```

Предлагаемые файлы:

- `Interface/Container/IEventContainer.php`;
- `Container/EventContainer.php`;
- `Interface/Service/IEventManager.php`;
- `Service/EventManager.php`;
- `Service/EventPortFactory.php`;
- `Exception/EventException.php`;
- `Value/EventSubscription.php`;
- `Value/EventResult.php`;
- `module.config.php`.

`module.config.php` должен объявить `container`, `locator`, `ports`, пустые
`routes` и `events`. Пустой `events` сохраняет установленную форму конфигов и
не означает, что registration protocol уже существует.

`EventPortFactory` получает только необходимые зависимости через composition
root/locator factory. Сам `EventManager` не получает `IServiceLocator` и не
вызывает его во время `on`/`off`/`fire`. Это прямо следует `DEC-070`, `DEC-072`
и правилам constructor injection.

### Загрузка

Рекомендуется Core eager loading, как у остальных `Core/*`. Вызов
`$serviceLocator->get(IEventContainer::class)->get(IEventManager::class)`
даёт lazy resolution порта внутри уже загруженного Core container. Отдельный
lazy entry в `config/modules.php` не нужен.

### Registration listeners

Для MVP регистрация происходит явно в composition/setup code владельца
потребителя, через полученный `IEventManager`. Автоматическое чтение
`module.config.php.events` не вводить до определения:

- формата listener class;
- момента регистрации;
- lifetime;
- dependency resolution;
- порядка модулей;
- поведения lazy modules;
- duplicate/invalid config errors.

После появления реального потребителя можно добавить декларативную карту, но
тогда `ModuleManager` должен только валидировать/передавать config, а
EventManager registration coordinator — создавать listeners через container.
Не заставлять runtime-сервисы обращаться к `ModuleManager` или locator.

### Почему не использовать текущее `events`

Пустой ключ во всех конфигах не доказывает общий контракт. Его смысл сейчас
не реализован. В v1 оставить его пустым совместимо с кодом и не создаёт
ложной обратной совместимости. Если поле будет активировано, требуется
отдельное решение и tests на invalid configuration.

### Circular dependencies

EventManager должен зависеть только от собственной in-memory registry. Не
внедрять в него Character/Game/Mail/Logger/Agent. Это предотвращает cycle
через Core container и делает bootstrap независимым от lazy прикладных
модулей. Listener может зависеть от своих портов; это ответственность factory
потребителя.

## 8. Error semantics

Создать `EventException extends MifrialException` с листьями только по мере
необходимости. Для MVP достаточно кодов:

- `EVENT_INVALID` — пустое/whitespace имя, foreign token или invalid
  registration config;
- `EVENT_RECURSION` — event name уже есть в текущем dispatch chain;
- `EVENT_LISTENER_FAILED` — foreign `Throwable`, обёрнутый с `previous`.

Recommended policy:

- invalid API input бросает `EventException`;
- listener `MifrialException` пробрасывается без замены;
- чужой `Throwable` на границе listener-а оборачивается в
  `EventException('EVENT_LISTENER_FAILED', ..., $previous)`, чтобы наружу
  выходил только разрешённый проектом exception family;
- исходный throwable сохраняется в `getPrevious()` вместе с его trace;
- не логировать автоматически: EventManager не владеет Logger и не должен
  создавать duplicate records;
- при listener exception нет `EventResult` и нет «частичного успеха»;
  уже выполненные listeners остаются side effect-ами sync process и rollback
  им не обещается.

Для post-operation notification producer обязан сам выбрать boundary policy.
В целевой Character/Game integration запись outbox уже находится в
закоммиченной transaction, поэтому ошибка local listener не делает mutation
неуспешной: retry выполняется через outbox. До появления outbox producer
может перехватить `EventException` после успешной operation и применить
best-effort policy. EventManager не является transaction manager и не
гарантирует доставку после завершения PHP process.

HTTP `Dispatcher` не должен переводить EventException в ActionResponse сам по
себе. Если action вызывает EventManager, action либо обрабатывает доменную
ошибку по своему контракту, либо ошибка доходит до общей internal boundary.

## 9. Transaction semantics

### Подтверждено

SmartTable имеет transaction exceptions:

- `TransactionOpenException`;
- `TransactionFailedException`;
- `docs/tr/smarttable.md` говорит, что при уже открытой TX `transaction()`
  выполняет работу внутри неё без собственного commit/rollback.

Но EventManager MVP не имеет transaction context, `afterCommit`, `afterRollback`,
outbox или callback registry. SmartTable `IOpenedRecords` сам по себе не
доказывает, что runtime event должен быть transaction-aware.

### Решение для MVP

- `fire` не знает о транзакции;
- EventManager не открывает, commit-ит и rollback-ит transaction;
- sync listener вызывается в том месте, где его вызвал сервис;
- если listener упал, DB rollback должен решаться владеющим domain service,
  не EventManager;
- async enqueue до commit запрещён как небезопасный unspecified behavior;
- `afterCommit`, `afterRollback` и transactional outbox — `OPEN/DEFERRED`;
- их не включать в v1 без отдельного contract decision.

Для Character/Game integration consumer boundary уже определена отдельно:
outbox row записывается до commit, а EventManager вызывается после commit как
fast path. Реализация outbox остаётся отдельным планом и не добавляется в
Core/Event. Этот plan не разрешает автоматически привязывать SmartTable CRUD
events к EventManager.

## 10. Implementation phases

### Phase 0 — решения контракта (зафиксировано)

Зафиксировано:

- `Core/Event` против буквального `Core/EventManager`;
- имя публичного порта `IEventManager`;
- `IEventPayload` read-only boundary and typed consumer payloads;
- subscription token;
- priority ordering;
- duplicate/off/mutation semantics;
- exception propagation;
- active-event cycle detection and dispatch chain;
- distinction between notification and guarded validation semantics;
- `EventResult` success/errors/stopped contract;
- fail-fast versus collected validation errors;
- отсутствие catalog/wildcard/once;
- sync-only MVP;
- отсутствие transaction hooks.

**Файлы:** решения перенесены в `docs/tr/architecture.md` и
`docs/tr/decisions.md` (`DEC-083`).
**Tests:** sync MVP уже имеет unit и boot/container tests.
**Готовность:** сигнатуры и sync-семантика закрыты; Character/Game outbox
integration не входит в эту фазу.

### Phase 1 — synchronous EventManager

**Статус: реализовано.** Реализация и тесты находятся в следующих файлах:

- `modules/Core/Event/Interface/Service/IEventManager.php`;
- `modules/Core/Event/Interface/Service/IEventListener.php`;
- `modules/Core/Event/Interface/Value/IEventPayload.php`;
- `modules/Core/Event/Interface/Value/IEventIssue.php`;
- `modules/Core/Event/Interface/Value/IEventResult.php`;
- `modules/Core/Event/Service/EventManager.php`;
- `modules/Core/Event/Value/EventSubscription.php`;
- `modules/Core/Event/Value/EventIssue.php`;
- `modules/Core/Event/Value/EventResult.php`;
- `modules/Core/Event/Exception/EventException.php`;
- при необходимости внутренний `ListenerRegistry` только если появится
  отдельная причина изменений.

**Поведение:** `on/off/fire`, snapshot dispatch, priority + FIFO,
`IEventPayload`, exception propagation, active-event cycle guard.

**Tests:** unit suite `modules/Core/Event/tests/` и testsuite `event` в
`www/mifrial/phpunit.xml.dist`.
**Готовность:** deterministic tests зелёные, нет locator/service dependency
в `EventManager`, все PHPDoc/quality rules соблюдены.
**Не входит:** config registration, async, transaction, logging, domain
consumers.

### Phase 2 — module/container integration

**Статус: реализовано.** Container/config/autoload wiring находится в
следующих файлах:

- `modules/Core/Event/Container/EventContainer.php`;
- `modules/Core/Event/Interface/Container/IEventContainer.php`;
- `modules/Core/Event/Service/EventPortFactory.php`;
- `modules/Core/Event/module.config.php`;
- `www/mifrial/composer.json`: PSR-4 entry for `Mifrial\Core\Event\`,
  test namespace and tests classmap exclusion;
- `www/mifrial/phpunit.xml.dist`: `event` testsuite.

**Tests:** boot through `ApplicationFactory`, container port resolution,
freeze behavior, circular guard, no locator call from service. Override tests
должны использовать прямой test harness `ModuleContainerFactory`/
`ModuleContainer`, потому что обычный `ApplicationFactory` freeze-ит контейнеры
до выдачи приложения. При будущих изменениях `composer.json` нужно повторно
выполнить `composer dump-autoload`; для текущего MVP wiring уже применён.
**Готовность:** `IEventManager` доступен через Core container в обычном boot и
не требует eager loading прикладных модулей. Изменение этих файлов не является
частью будущей Character/Game integration.
**Не входит:** listener consumers и активная обработка `module.config.php.events`.

### Phase 3 — configuration registration

**Статус:** deferred, не обязательна для MVP.

Если появится реальный consumer, определить отдельный flow:

- `events` — декларативные runtime registrations: полный `eventName` → список
  handler descriptors;
- descriptor содержит class-string handler или factory и, при необходимости,
  priority;
- collector валидирует полный event code, но не добавляет к нему module
  prefix и не преобразует его;
- payload compatibility validation;
- module load timing;
- registration order/priority;
- invalid config exception;
- cleanup/lifetime.

**Файлы:** `ModuleManager`/config validator только после утверждения
формата; module configs конкретных consumers; integration tests.
**Готовность:** boot регистрирует listeners ровно один раз и deterministic
порядком.
**Не входит:** автоматически трактовать все текущие `events => []` как
доказательство уже поддержанной регистрации.

### Phase 4 — async boundary

**Статус:** deferred.

До начала должен существовать утверждённый `IEventQueue` или эквивалентный
отдельный queue contract с worker, payload serialization, retry/failure,
idempotency и transaction semantics.

**Файлы:** отдельный Core queue module/adapter, EventManager adapter и tests —
конкретный список нельзя честно зафиксировать по текущему коду.
**Готовность:** доказано, что событие не теряется между commit и enqueue,
worker может выполнить payload, failure observable, duplicate policy
определена.
**Не входит:** расширение `mail_job`, SSE broker, event store, outbox без
отдельного решения.

### Phase 5 — DB-driven subscriptions (future)

Этот этап закрывает потенциальный UI-сценарий «создать ST и выбрать уже
существующий handler», но не входит в базовый EventManager.

**Новая граница:**

```text
Core/SmartTable → Core/Event
Core/EventRegistration → Core/SmartTable + Core/Event
```

`Core/EventRegistration` или composition-root adapter должен:

- хранить subscription rows через SmartTable;
- хранить `eventName`, stable `handlerCode`, `priority`, `active` и
  ограниченную configuration map;
- получать catalog `handlerCode → factory/class` от модулей;
- разрешать только catalogued handlers через DI;
- создавать/снимать runtime subscriptions при boot/reload/disable;
- проверять права администратора и invalid configuration.

UI никогда не сохраняет произвольный PHP class-string, namespace или closure.
Например, в БД хранится `test.validate.name`, а не
`TestGroup\TestModule\TestEventHandlerClass`.

Если SmartTable публикует generic CRUD events, он зависит только от публичного
`IEventManager` и typed payload contract. `Core/Event` не читает таблицу
subscriptions и не зависит от SmartTable. Это предотвращает цикл
`SmartTable → Event → SmartTable`.

**Готовность:** существующий кодовый handler можно выбрать из UI по stable
code, зарегистрировать в заданном priority и отключить без изменения PHP-кода.
**Не входит:** произвольное выполнение кода из БД, dynamic PHP eval,
transactional outbox и durable event delivery.

## 11. Test plan

### Unit

Обязательные тесты Phase 1:

- registration и token uniqueness;
- fire одного и нескольких listeners;
- listener, возвращающий `null`, как успешный shorthand;
- priority;
- FIFO при одинаковом priority;
- duplicate registration;
- off одного token и повторный idempotent off;
- отсутствие listeners;
- unknown event name без catalog;
- exception listener и остановка цепочки;
- сохранение исходного throwable;
- wrapping foreign `Throwable` into `EventException` with `previous`;
- listener failure result с errors и stopped flag;
- custom `IEventResult`, который сообщает failure без errors, не оставляет
  aggregate успешным;
- две проверки `beforeCreate`/`beforeDelete` в строгом priority порядке;
- remove во время fire;
- add во время fire;
- reentrant fire;
- direct `A → A` and indirect `A → B → A` cycle detection;
- nested non-cyclic events;
- `IEventPayload` implementation and typed consumer extension;
- invalid short/empty/whitespace name и foreign subscription token;
- warnings/errors, payload aggregation и корректный invoked count.

Не добавлять тесты mutable payload, wildcard, once или aggregated listener
results, пока они не станут контрактом.

### Integration

- boot `ApplicationFactory::boot()` с Core/Event;
- получение `IEventContainer` из locator;
- получение `IEventManager` из container;
- constructor injection фабрики;
- port memoization;
- override до resolve и freeze после bind;
- отсутствие необходимости загружать Roleplay/Character/Game;
- invalid container/port behavior;
- проверка, что `events => []` остальных модулей не регистрирует listeners;
- проверка, что action `Dispatcher::dispatch()` не вызывает EventManager
  автоматически.

### Async

Async tests **deferred**, потому что общий queue contract отсутствует. Mail
tests не следует переименовывать в EventManager tests: существующие
`Core/Mail/tests/MailMysqlTest.php` проверяют `mail_event`, `mail_job`, flush,
failure и inline mode именно Mail.

## 12. Risks and unresolved decisions

| Вопрос | Recommended default | Альтернативы/последствия | Нужно решение Андрея |
|---|---|---|---|
| Публичный port | `IEventManager` | `IEventBus` строже, но расходится с требованием; `IEvents` слишком расплывчато | Решено |
| Модуль | `Core/Event` | `Core/EventManager` исторически понятен, но дублирует имя порта | Решено |
| Identity | namespaced string constant: `ModuleGroup\ModuleName.Subject::LifecycleOperation` | class-string сильнее, но создаёт registry coupling | Решено |
| Payload | read-only `IEventPayload` with typed consumer extensions and immutable nested values | plain array weakens typing; a concrete event-object protocol adds unnecessary coupling | Решено |
| `EventResult` | один `IEventResult`: local result → `merge()` в aggregate; invocation/stopped state | shared accumulator связывает listeners и усложняет ownership | Решено |
| Guarded validation | тот же `EventResult` с errors и stopped | отдельный validation result был бы лишней типовой границей | Решено |
| Validation policy | fail-fast по priority | collect all errors требует отдельного aggregate mode и продолжает проверки после invalid | Решено |
| Listener failure policy | fail-fast для текущего dispatch в любой фазе; post-operation failure не откатывает operation | continue-on-error требует отдельной fan-out policy и наблюдаемости каждого listener-а | Решено |
| Post-operation notification in Core/Event MVP | best-effort; EventManager propagates, producer boundary handles; уже завершённая operation не откатывается | Character/Game mandatory delivery requires separate outbox/commit contract; swallowing in EventManager hides failure | Решено |
| Event name ownership | namespaced producer constant | короткие свободные строки создают collision между модулями | Решено |
| Subscription lifecycle | boot registration до конца process; token хранит owner | dynamic registration требует explicit cleanup через `off` | Решено |
| Public extensibility | `IEventResult`/`IEventIssue`/`IEventListener`; concrete Value types are defaults; `merge(..., stop)` сохраняет aggregate state | concrete return types make custom result/listener implementations harder | Решено |
| Typed listener payload | explicit adapter: base `IEventPayload` at Event boundary → subtype handler | narrowing `handle()` parameter violates PHP substitution; self-check in every handler duplicates glue | Решено |
| DB-driven registration | отдельный `Core/EventRegistration` adapter | прямой ST dependency в Event creates cycle; no-code UI flow remains deferred | Решено |
| Priority | меньший раньше, FIFO ties | больший раньше меняет порядок и требует миграции consumer contracts | Решено |
| Duplicates | разрешены, token per registration | deduplicate скрывает intent | Решено |
| `off` | token-only; повторный owned off no-op; foreign token → `EventException`; malformed same-owner token не повреждает registry | strict повторный off усложняет cleanup; silent foreign hides ownership bugs | Решено |
| Exceptions | `MifrialException` rethrow; foreign `Throwable` wrapped with previous | always wrapping loses original Mifrial error type; propagating foreign violates exception family | Решено |
| Recursion | active-event cycle detection, `EVENT_RECURSION`, chain diagnostics | hard depth limit only as future emergency guard for dynamic names | Нет для v1 |
| Listener lifecycle | process-local, explicit token | config auto-registration требует lifecycle protocol | Нет для MVP, да для Phase 3 |
| `events` config | полный producer-owned `eventName` → handler descriptors; не subscription rows | отдельный handler catalog для DB-driven registration; смешение config registrations и DB subscriptions | Решено |
| Async split | sync-only MVP; отдельный queue adapter deferred | EventManager-owned queue смешивает роли; Mail reuse неверен | Решено |
| Transaction in Core/Event MVP | transaction-agnostic; producer выбирает место вызова; afterCommit/outbox deferred | Event-owned TX смешивает delivery и storage semantics; Character/Game consumer boundary определяет отдельный outbox | Решено |
| Logging | не логировать автоматически; producer/application boundary logs | EventManager→Logger dependency creates duplicate records and Core coupling | Решено |
| Metrics | не добавлять | отдельный metrics port при потребителе | Нет для MVP |
| Backward compatibility | не имитировать Bitrix API | adapter возможен позже, если будет consumer | Да, если нужен Bitrix-like API |

Главный риск — принять историческую формулировку «очередь для async» за
готовую реализацию. Код подтверждает только Mail queue и Agent scheduler;
общего queue port нет.

## 13. Recommended MVP

Вертикальный срез:

1. отдельный `Core/Event` backend module;
2. публичный `IEventManager` для notification events;
3. `string eventName + IEventPayload`;
4. `on` → token, `off` → token, `fire` → mutable aggregate `EventResult`;
5. deterministic priority/FIFO order;
6. sync-only delivery;
7. typed `EventException`, listener exception не скрывается;
8. Core container и constructor DI;
9. unit + boot/container integration tests;
10. документация решения в `architecture.md`/`decisions.md`.

Для ST/Test тот же `EventResult` используется как guarded result в
`beforeCreate`/`beforeDelete`; отдельный validation type не нужен. В MVP нет
Character, Mechanic, Mail, SSE, Logger integration, persistent storage,
generic queue, retry, outbox, afterCommit и Game consumers.

Это достаточная основа для будущего потребителя, потому что фиксирует самую
важную общую часть — process-local delivery и жизненный цикл подписки — не
подменяя пока неизвестные доменные/transaction/async semantics. Когда появится
реальный consumer, его payload и listener factory проверят, достаточно ли
общего контракта, не расширяя EventManager заранее.

## 14. Final decision memo

### Рекомендованная архитектура

Выделенный backend-модуль `Core/Event` с единым `IEventManager`, in-memory
registry, immutable `IEventPayload` values with typed consumer extensions, token subscriptions и
priority/FIFO dispatch. `EventResult` поддерживает как обычный notification,
так и guarded `before...` result с errors/stopped.

`Core\Engine` для этого не подходит: в текущем каноне это frontend Engine, а
backend Core/Engine отсутствует. `Core\EventManager` допустим как буквальное
имя модуля, но `Core/Event` лучше соблюдает текущие PHP naming/DI boundaries.

### Файлы реализованного sync MVP

- `www/mifrial/modules/Core/Event/module.config.php`;
- `www/mifrial/modules/Core/Event/Container/EventContainer.php`;
- `www/mifrial/modules/Core/Event/Exception/EventException.php`;
- `www/mifrial/modules/Core/Event/Interface/Container/IEventContainer.php`;
- `www/mifrial/modules/Core/Event/Interface/Value/IEventPayload.php`;
- `www/mifrial/modules/Core/Event/Interface/Value/IEventIssue.php`;
- `www/mifrial/modules/Core/Event/Interface/Service/IEventListener.php`;
- `www/mifrial/modules/Core/Event/Interface/Service/IEventManager.php`;
- `www/mifrial/modules/Core/Event/Interface/Value/IEventResult.php`;
- `www/mifrial/modules/Core/Event/Service/EventManager.php`;
- `www/mifrial/modules/Core/Event/Service/EventPortFactory.php`;
- `www/mifrial/modules/Core/Event/Value/EventIssue.php`;
- `www/mifrial/modules/Core/Event/Value/EventResult.php`;
- `www/mifrial/modules/Core/Event/Value/EventSubscription.php`;
- `www/mifrial/modules/Core/Event/tests/EventManagerTest.php`;
- `www/mifrial/modules/Core/Event/tests/EventModuleBootTest.php`.
- `www/mifrial/composer.json` — PSR-4/autoload-dev entries модуля.

Canonical-документация для sync MVP уже обновлена:

- `docs/tr/architecture.md`;
- `docs/tr/decisions.md`;
`config/modules.php` для Core/Event менять не нужно, так как `loadCore()`
подключает все Core modules; это подтверждено boot integration test.

### Файлы, которые не нужно менять в первой реализации

- `Core/Kernel/Service/ModuleManager.php`;
- `Core/Kernel/Service/ModuleContainerBinder.php`;
- `Core/Kernel/Service/ServiceLocator.php`;
- `Core/Kernel/Service/Dispatcher.php`;
- `Core/Agent`;
- `Core/Mail`;
- `Core/Logger`;
- `Core/SmartTable`;
- `config/modules.php`;
- `Roleplay/Character`;
- `Roleplay/Mechanic`;
- `Messages/Chat`;
- SSE entrypoint/emitter.

Их изменение появится только при отдельном, подтверждённом integration
contract, а не из-за самого факта создания EventManager.

### Что добавить в документацию

Документационный gate для sync MVP пройден:

- canonical owner и namespace EventManager в `architecture.md`;
- решение по module name/port name;
- payload/priority/exception/recursion/transaction semantics в `decisions.md`;
- explicit statement, что `module.config.php.events` в MVP пуст, а в future
  Phase 3 является декларативной картой runtime registrations с полными
  producer-owned event codes; collector не добавляет prefix;
- explicit deferral of `IEventQueue` и владения outbox за пределами
  `Core/Event`;
- evidence status `IMPLEMENTED` подтверждён кодом и tests.

### Подтверждённые решения

1. модуль `Core/Event`;
2. public port `IEventManager`;
3. read-only `IEventPayload` с typed consumer extensions и immutable nested
   values;
4. namespaced string identity
   `ModuleGroup\ModuleName.Subject::LifecycleOperation`;
5. priority: меньший раньше, одинаковый priority — FIFO;
6. `IEventResult` local result → `merge()` в aggregate;
7. fail-fast после `addError()`;
8. `MifrialException` rethrow, foreign `Throwable` wrapped with `previous`;
9. active-event cycle detection вместо arbitrary recursion limit;
10. sync-only MVP, transaction-agnostic EventManager;
11. best-effort post-operation notification без гарантии afterCommit;
12. отдельный `Core/EventRegistration` для DB-driven subscriptions;
13. `module.config.php.events` как декларативные runtime registrations с
    полным event code, не subscription storage; handler catalog для DB-driven
    subscriptions — отдельный контракт.

### Неблокирующие future questions

- `stopPropagation`, wildcard и once-listeners;
- защита от бесконечной цепочки уникальных динамических event names;
- metrics port;
- Bitrix-compatible adapter, если появится реальный consumer.

### Итоговый статус готовности

К реализации **готов архитектурный scope sync MVP**, но production-level
async EventManager не готов и в MVP не входит.

Build blockers для sync MVP отсутствуют: Phase 1/2 уже реализованы и
описаны canonical-документами. Следующий отдельный scope — Character/Game
integration: ему нужны собственные outbox, SSE и transaction contracts.
Queue, DB-driven subscriptions и durable delivery не следует добавлять в
`Core/Event` без отдельного решения.
