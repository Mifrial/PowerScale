# Архитектура PowerScale

**Статус:** текущий канон, 2026-08-30.

## Как читать

Источники имеют такой приоритет:

1. код и тесты;
2. `draft-front_1.2ds/frontend-rules.md`;
3. решения из [`decisions.md`](decisions.md);
4. исторический ТР и спеки.

`OPEN` означает неподтверждённый backend-контракт. `DEFERRED` означает сознательно отложенное решение. Этот документ не является планом реализации.

## Backend foundation contract

Backend modules are two-level `Namespace/ModuleName`. `ModuleManager` exposes `includeModule(group, module)` and `requireModule(...)`; only `Core/*` is loaded eagerly, while other modules are lazy. `module.config.php` declares `services`, `routes` and `events`.

`ServiceLocator` is the primary DI container. Backend services use stable dot-notation codes such as `Core.User.Service.User`, aliases by `::class` are allowed for IDE compatibility, and factories receive the parameter `$serviceLocator`. Frontend uses the generic `set/get/reset` locator exported from `Core/Engine`, while each module exposes `registerXApi(impl)` and `getXApi(): IXApi`.

The request pipeline is:

```text
Request → authentication → CSRF → action routing → controller → ActionResponse
```

`main.ts` registers mock or real APIs and initializes the CSRF API. `HttpClient` obtains the CSRF token through a callback and sends it on state-changing requests. `runAction` encodes the action name and returns a typed response/error envelope.

## SmartTable contract

SmartTable is the only backend data-access abstraction. `BaseField` defines name, label, required, multiple and default; `ReferenceField` describes foreign references; `SmartTableDefinition.getMap()` returns the table field map. Module hydrators register under `smarttable_fields`.

Repositories obtain a table through `Core.SmartTable.Service.open(tableName)`. The service supports `open/create`, CRUD (`add`, `update`, `delete`, `getList` with filter/sort/pagination/select), local `transaction()` and global `beginTransaction/commit/rollback`. `MigrationService` diffs definitions and generates migrations; `SmartTableMigration` provides `up/down`.

Backend logging is primarily database-backed through the log SmartTable; file fallback is limited to startup or database unavailability. Exact production backend implementation remains `OPEN`.

## Backend extras (REQUIREMENT, implementation OPEN)

Legacy §2/волна 1 описывает дополнительные backend-контракты, которых нет в текущем frontend evidence. Они сохранены как backend `REQUIREMENT`, не как frontend или backend `IMPLEMENTED`:

- EventManager: `fire`/`on`/`off`, синхронные слушатели, очередь для асинхронных;
- иерархия ошибок поверх ActionResponse;
- SmartTable QueryBuilder (`query()` для сложных случаев);
- тегированный кэш `getList` (TTL, tags).

Код frontend их не реализует. Drop vs keep — только явным решением; silent drop запрещён.

## Границы модулей

Frontend построен как Shell и прикладные модули:

```text
Core
├── Engine
└── UI
Messages
├── Chat
└── Notifications
Roleplay
├── Rule
├── Space
├── Character
└── Game
```

Core — фундамент. Прикладные модули зависят от Core. `Core/Engine` не зависит от `Core/UI`.

Текущие публичные границы:

- `Rule` — каталог правил; не импортирует `Space`;
- `Space` — владеет контекстом пространства и ревизией, зависит от публичного API `Rule`;
- `Chat` — самостоятельный host сообщений, не импортирует Roleplay;
- `Character` — зависит от Core, Rule, публичного Space и Chat;
- `Game` — зависит от Core, Character, Rule, Chat и публичного Space.

Межмодульный доступ во всём проекте идёт через `init.ts`, публичные DTO/интерфейсы, stores и plugin-регистраторы. Внутренние файлы чужого прикладного модуля напрямую не импортируются; это правило распространяется на существующие и будущие прикладные модули.

Для доменных сервисов действует typed DI-контракт: публичный фасад модуля отдаёт capability-oriented API, а сервис-потребитель получает нужные порты через constructor DI. ServiceLocator и module-container используются для регистрации, выбора real/mock реализации и сборки приложения в composition root (`main.ts`/bootstrap) и публичных `init.ts`; locator/container не передаются внутрь доменной логики и не вызываются там напрямую. Внутри module `init.ts` допускается несколько узких фасадов, но не единый универсальный контейнер с произвольным доступом ко всем сервисам. Правило обязательно для всех прикладных модулей проекта.

## CODE_GAP: фактические импорты Game

Текущее правило публичных границ нарушается в `Roleplay/Game`: Game напрямую импортирует внутренние реализации Rule (`Rule/Service/Instance/*`, `Rule/Service/Mechanic/*`, `Rule/Constant/*`, `Rule/Store/*`) и Character (`Character/Constant/*`). Это подтверждено текущим source evidence в `migration-claims.md`; код в этом документальном проходе не исправляется. Такие зависимости должны быть переведены на typed public facades и constructor DI (`DEC-064`). До отдельной реализации они имеют disposition `CODE_GAP`/`BACKLOG`, а не `IMPLEMENTED`.

## Frontend-слои

- `.vue` отвечает за UI и состояние отображения;
- доменная логика находится в `Service/`/`Value/`;
- транспортные API регистрируются через ServiceLocator;
- stores хранят состояние, но не заменяют доменные сервисы;
- DTO и string-literal enum находятся в типовых слоях;
- Chat, профиль и подобные host-модули принимают opaque plugin-контракты и не импортируют доноров.

Сервисы и моки должны иметь единый контракт. Глубокое клонирование выполняется через `structuredClone`, если среда это позволяет.

## Frontend module anatomy

Each module keeps `Interface/` for service contracts, `Dto/` for data and discriminated unions, `Enum/` for flat string-literal unions, `Service/` for domain classes, `Constant/` for registries, `Component/` for Vue components, `Mock/`, `Utils/`, `Store/`, `Page/` and `__tests__/`. The module root contains only public `init.ts` and `routes.ts`.

`Core/Engine` owns `HttpClient`, `CsrfApi`, `Engine`, `ActionResponse`, `useAbortable` and dimensional values. `Core/UI` owns SmartGrid, FilterBar and shared composables. `Core/User` owns profile, groups, permissions and admin registries. `Messages/Chat` owns generic chat/plugin infrastructure; Roleplay modules register domain plugins through public APIs.

All source imports use the `@/` alias. Composables live in the module-root `Composables/`; shared UI belongs to `Core/UI`; `Core/Engine` must not import `Core/UI`. Components do not contain domain calculations. SFC block order is `script setup` → `template` → `style scoped`.

## Backend

Границы backend разделяются так:

- **Frontend-confirmed:** формы DTO, интерфейсы API, mock adapters и request/error envelope, реально присутствующие в текущем frontend.
- **Backend requirement:** доменные инварианты, которым должны соответствовать серверные операции: авторизация, visibility filtering, optimistic versions, transactions, idempotency и journal.
- **Backend implementation `OPEN`:** физическая схема, endpoints, repositories, persistence, transaction implementation, SSE authorization/reconnect/retention и production logging.

Наличие frontend DTO/API не доказывает backend implementation. В частности, физическая схема таблиц экономических операций и точные endpoint остаются задачей backend-проектирования; доменные инварианты зафиксированы в [`game-system.md`](game-system.md).

## Связанные документы

- [`rule-system.md`](rule-system.md)
- [`character-system.md`](character-system.md)
- [`game-system.md`](game-system.md)
- [`chat-system.md`](chat-system.md)
- [`auth-system.md`](auth-system.md)
- [`data-model.md`](data-model.md)
- [`ui-system.md`](ui-system.md)
- [`decisions.md`](decisions.md)
- [`history.md`](history.md)
