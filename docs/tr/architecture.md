# Архитектура PowerScale

**Статус:** текущий канон, 2026-08-30.

## Как читать

Источники имеют такой приоритет:

1. код и тесты;
2. `draft-front_1.2ds/frontend-rules.md`;
3. решения из [`decisions.md`](decisions.md);
4. исторический ТР и спеки.

`OPEN` — неподтверждённый серверный контракт. `DEFERRED` — сознательно отложенное решение. Этот документ не план реализации.

## Контракт фундамента сервера

Модули сервера двухуровневые: `Namespace/ModuleName`. `ModuleManager` даёт `includeModule(group, module)` и `requireModule(...)`; сразу грузится только `Core/*`, остальные — лениво. `module.config.php` объявляет `services`, `routes` и `events`.

Основной контейнер внедрения — `ServiceLocator`. Сервисы сервера имеют стабильные коды через точку, например `Core.User.Service.User`; алиасы по `::class` допустимы для IDE; фабрики получают `$serviceLocator`. На фронте — общий локатор `set`/`get`/`reset` из `Core/Engine`; каждый модуль отдаёт `registerXApi(impl)` и `getXApi(): IXApi`.

Конвейер запроса:

```text
запрос → аутентификация → CSRF → маршрутизация action → контроллер → ActionResponse
```

`main.ts` регистрирует mock или боевые API и инициализирует CSRF. `HttpClient` берёт CSRF-токен через callback и шлёт его на запросы, меняющие состояние. `runAction` кодирует имя действия и возвращает типизированный конверт ответа или ошибки.

## Контракт SmartTable

SmartTable — единственная серверная абстракция доступа к данным. `BaseField` задаёт имя, подпись, required, multiple и default; `ReferenceField` — внешние ссылки; `SmartTableDefinition.getMap()` возвращает карту полей. Гидраторы модулей регистрируются как `smarttable_fields`.

Репозиторий открывает таблицу через `Core.SmartTable.Service.open(tableName)`. Сервис: `open`/`create`, CRUD (`add`, `update`, `delete`, `getList` с фильтром, сортировкой, пагинацией, select), локальная `transaction()` и глобальные `beginTransaction`/`commit`/`rollback`. `MigrationService` сравнивает определения и генерирует миграции; `SmartTableMigration` даёт `up`/`down`.

Журнал сервера в основном в SmartTable логов; запись в файл — запасной путь на старте или если база недоступна. Точная боевая реализация сервера — `OPEN`.

## Дополнения сервера (требование, реализация OPEN)

Наследие §2 / волна 1 описывает серверные контракты, которых нет в текущем фронтовом evidence. Они сохранены как серверное требование, не как реализованное на фронте или на сервере:

- EventManager: `fire`/`on`/`off`, синхронные слушатели, очередь для асинхронных;
- иерархия ошибок поверх ActionResponse;
- SmartTable QueryBuilder (`query()` для сложных случаев);
- тегированный кэш `getList` (TTL, теги).

Код фронта их не реализует. Оставить или выкинуть — только явным решением; молча выкидывать нельзя.

## Границы модулей

Frontend построен как оболочка (shell) и прикладные модули:

```text
Core
├── Engine
├── UI
├── Auth
└── User
Messages
├── Chat
└── Notifications
Roleplay
├── Home
├── Rule
├── Space
├── Character
└── Game
```

### Поверхность

Чужой модуль может импортировать только `init.ts` (фасады `get*` / `register*` и узкий композабл-фасад) и типы из `Dto/` / `Interface/` / `Enum/`.

Чужой модуль не импортирует: `Service/`, `Component/`, `Constant/`, `Store/`, `Page/`, `Composables/`, `Utils/`. Из `init` нельзя реэкспортировать `useXxxStore()`.

Исключения: `Core/Engine` и `Core/UI` открыты целиком; Engine не импортирует UI. Auth и User — как прикладные модули. Между модулями можно импортировать только mock-фикстуры. `src/shell/`, `src/router/`, `main.ts` — корень сборки; прикладные модули не импортируют shell.

Типы не сваливать в баррель `init`. Файлов `index.ts` с реэкспортом всего модуля нет.

Pinia только внутри своего модуля, не в ServiceLocator. В конструктор сервиса передаётся порт; стор читает адаптер того же модуля. Страницы и свой стор модуля могут вызывать `getXxxApi()` из публичного `init` разрешённого ребра. Классы в `Service/` не обращаются к локатору и не вызывают `getXxxApi` (`DEC-064`, `DEC-065`). Регистрация `register*` в `init` остаётся. Внедрение через конструктор не требуется у Engine и UI. Home не имеет доменных классов в `Service/` — constructor DI там не применяется.

Auth зависит от User: после входа и выхода Auth вызывает фасад User. User не импортирует Auth. Выход из сессии — в shell. «Кто я» в прикладных модулях — текущий пользователь из User.

Хост плагинов (Chat, реестры User) не импортирует доноров; донор вызывает `register*` хоста.

### Разрешённые рёбра

«Публично» = `init` / `Dto` / `Interface` / `Enum`, не `Store` и не `Component`.

- Engine — никого вне себя
- UI → Engine
- Auth → Engine, UI, User (публично)
- User → Engine, UI (не Auth; хост плагинов прав, профиля и админки)
- Chat → Engine, UI, User (публично). Не Roleplay, не Notifications, не Auth
- Notifications → Engine, UI, User (публично). Не Chat, не Roleplay, не Auth
- Home → Engine, UI, User, Notifications (публично). Не Auth
- Rule → Engine, UI, User (плагин), Chat (плагин). Не Space/Character/Game. Не Notifications. `spaceId` и ревизия — проп, роут или ключ inject; Space только `provide`
- Space → Engine, UI, User, Rule (публично). Не Character/Game. Не Notifications
- Character → Engine, UI, User, Rule, Space, Chat (плагин), Notifications (публично, отправка). Не Game
- Game → Engine, UI, User, Rule, Space, Character, Chat (плагин), Notifications (публично, отправка)

### Долг кода (CODE_GAP, не реализация)

Это нарушение канона в текущем дереве, не норма.

Поверхность модулей и locator закрыты этапами 6b–8. Линтер `powerscale/no-foreign-module-internals` держит чужие внутренности. Текущего долга по этому канону нет.

## Слои фронтенда

- `.vue` — UI и состояние отображения;
- доменная логика — в `Service/` и `Value/`;
- транспортные API регистрируются через ServiceLocator;
- сторы хранят состояние и не заменяют доменные сервисы;
- DTO и string-literal enum — в типовых слоях;
- хосты вроде Chat и профиля принимают непрозрачные контракты плагинов и не импортируют доноров.

Сервисы и моки — один контракт. Глубокое клонирование — `structuredClone`, если среда это позволяет.

## Анатомия модуля фронтенда

В каждом модуле: `Interface/` — контракты сервисов; `Dto/` — данные и дискриминированные объединения; `Enum/` — плоские string-literal union; `Service/` — доменные классы; `Constant/` — справочники; `Component/` — Vue; `Mock/`, `Utils/`, `Store/`, `Page/`, `__tests__/`. В корне модуля из файлов — только публичные `init.ts` и `routes.ts`.

`Core/Engine` владеет `HttpClient`, `CsrfApi`, `Engine`, `ActionResponse`, `useAbortable` и размерными значениями. `Core/UI` — SmartGrid, FilterBar и общие композаблы. `Core/User` — профиль, группы, права и реестры админки. `Messages/Chat` — общая инфраструктура чата и плагинов; модули Roleplay регистрируют доменные плагины через публичные API.

Импорты исходников — только через алиас `@/`. Композаблы — в корневой `Composables/` модуля; общий UI — в `Core/UI`; `Core/Engine` не импортирует `Core/UI`. В компонентах нет доменных расчётов. Порядок блоков SFC: `script setup` → `template` → `style scoped`.

## Сервер

Границы сервера такие:

- **Подтверждено фронтом:** формы DTO, интерфейсы API, mock-адаптеры и конверт запроса/ошибки, которые есть в текущем фронте.
- **Требование к серверу:** доменные инварианты серверных операций: авторизация, фильтрация видимости, оптимистичные версии, транзакции, идемпотентность, журнал.
- **Реализация сервера `OPEN`:** физическая схема, endpoints, репозитории, хранение, транзакции, авторизация/reconnect/retention SSE и боевое логирование.

Наличие DTO и API на фронте не доказывает реализацию на сервере. Физическая схема таблиц экономических операций и точные endpoint — задача проектирования сервера; доменные инварианты экономики — в [`game-system.md`](game-system.md), сцены — в [`battleground-system.md`](battleground-system.md). PHP battleground не начинать до серверного Core.

Battleground живёт в `Roleplay/Game` (библиотека шаблонов — UI того же модуля). Новых DAG-рёбер в Core/Chat нет. SSE сцены — существующая `OPEN` граница realtime.

## Связанные документы

- [`rule-system.md`](rule-system.md)
- [`character-system.md`](character-system.md)
- [`game-system.md`](game-system.md)
- [`battleground-system.md`](battleground-system.md)
- [`chat-system.md`](chat-system.md)
- [`auth-system.md`](auth-system.md)
- [`data-model.md`](data-model.md)
- [`ui-system.md`](ui-system.md)
- [`decisions.md`](decisions.md)
- [`history.md`](history.md)
