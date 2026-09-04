# Архитектура PowerScale

**Статус:** текущий канон, 2026-08-30.

## Как читать

Источники имеют такой приоритет:

1. код и тесты;
2. локальные правила стека:
   - PHP: [`php-coding-standards.md`](php-coding-standards.md), ссылка на него также есть в корневом `AGENTS.md`;
   - Vue/TypeScript: `draft-front_1.2ds/frontend-rules.md`;
3. решения из [`decisions.md`](decisions.md);
4. исторический ТР и спеки.

`OPEN` — неподтверждённый серверный контракт. `DEFERRED` — сознательно отложенное решение. Этот документ не план реализации.

Правила разработки бэкенда и фронтенда не смешиваются в одном файле.
Файл правил соответствующего стека должен быть доступен без загрузки всего
ТР и использоваться как единый источник для повседневной разработки.
PHP дополнительно фиксирует KISS, SOLID, DRY и разделение слоёв в
[`php-coding-standards.md`](php-coding-standards.md).

## Контракт фундамента сервера

Публичный корень сайта — `www/`. `www/index.html` является entrypoint
клиентского приложения, если фронтенд собирается как SPA. `mifrial/init.php`
является чистым bootstrap приложения и не обрабатывает HTTP-запросы.
API-запросы входят через `mifrial/API/action.php`; отдельный SSE-транспорт
будет иметь собственный entrypoint рядом с ним. Внутренние файлы `mifrial`
закрыты от прямого доступа Apache, кроме разрешённых API entrypoint’ов.

Модули сервера двухуровневые: `Namespace/ModuleName`. PHP-неймспейс Kernel —
`Mifrial\Core\Kernel`. `ModuleManager`: `includeModule` возвращает `false`, если модуля нет; `requireModule` бросает `ModuleManagerException`. Сразу грузится только `Core/*` через `requireModule`, остальные — лениво. `module.config.php` объявляет `container`, `locator`, `ports`, `routes` и `events`. Дубль кода action или кривой `handler` — исключение при загрузке модуля.

Исключения `ModuleManager` наследуют общего родителя с `getModuleKey()` (`group/name`). Интерфейс PHP зеркалит путь класса: `Service/Application.php` → `Interface/Service/IApplication.php`, `Container/KernelContainer.php` → `Interface/Container/IKernelContainer.php`. Контейнер модуля — не сервис: он лежит в `Container/`, а не в `Service/`. Префикс `I` у интерфейса допустим; однобуквенные и сокращённые имена параметров (`$l`, `$c`) запрещены.

На фронте — общий локатор `set`/`get`/`reset` из `Core/Engine`; каждый модуль отдаёт `registerXApi(impl)` и `getXApi(): IXApi` (`DEC-064`).

### PHP: локатор, контейнер модуля, фабрика (`DEC-070`)

Цепочка: **локатор → контейнер модуля → фабрика → сервис.**

- **Kernel / Application** — composition root: `ApplicationFactory::boot` создаёт локатор и `ModuleManager` через `new`. Entrypoint (`API/action.php`) получает `Application` и вызывает `handle`. Модули `Application` не получают.
- **ModuleManager** — загрузка модулей с диска. Не держит локатор и не регистрирует сервисы. `getContainer($group, $name)` зовут только `boot`/диспетчер; сосед пишет `$serviceLocator->get(IXxxContainer::class)`.
- **Локатор** — каталог контейнеров. Для ещё не загруженного модуля в каталоге лежит слот (фабрика), не экземпляр. Первый `get` делает `requireModule`, собирает контейнер и запоминает его. Слоты вешает `boot` из `config/modules.php` (`lazy`) и из `locator` уже загруженных модулей.
- **Контейнер модуля Kernel (`IKernelContainer`)** — порты модуля Kernel (ping). Не диспетчер и не менеджер модулей.
- **Фабрика** — единственное место `new` и `get` чужого контейнера из локатора. Сервис в рантайме не зовёт локатор.
- **Подмена:** `override` до первого `get` порта, затем freeze контейнера и локатора (`set` снаружи запрещён; во время разрешения ленивого слота допустимы алиасы `locator` из конфига модуля).
- **OPEN:** публикация `IDispatcher` / `IModuleManager` для чужих модулей — когда появится потребитель.

Мешок строк на каждый репозиторий и `ServiceLocator::get('Core.Kernel.Ping')` — не канон. Прикладной код не вызывает `getModuleContainer('Core', 'User')`.

### Quality markers для PHP (`DEC-071`)

Форматтер автоматически исправляет только безопасные формальные изменения. Отдельный quality-анализатор обязан сигнализировать об архитектурных и complexity-проблемах ошибкой; эти ошибки не маскируются глобальными исключениями.

Начальные маркеры:

- больше трёх `return`;
- больше 30 строк в методе;
- Cognitive Complexity больше 8;
- Cyclomatic Complexity больше 8;
- вложенность больше трёх уровней;
- больше трёх логических связей в одном условии.

Маркер является поводом пересмотреть функцию, а не автоматическим запретом на конкретную конструкцию. Агент сначала пытается исправить очевидную проблему: декомпозировать метод, вынести фабрику или валидатор, упростить условие. Если после анализа функция обоснованно должна остаться сложной, агент сообщает причину и запрашивает разрешение на локальное точечное исключение. Глобальные отключения правил запрещены.

### PHP-правила (`DEC-072`)

Единый нормативный документ для PHP находится в
[`php-coding-standards.md`](php-coding-standards.md). Корневой `AGENTS.md`
ссылается на тот же документ, чтобы правила были доступны агенту и
разработчику при работе с бэкендом. Здесь не дублируются детали PHPDoc
типов и методов, порядка методов и class quality markers.

Прочитанная строка модуля — Record с геттерами смысла (`getId`, `isBypass`), не ассоциативный мешок. New/Patch — карта присутствующих ключей для insert/update SmartTable. JSON HTTP — отдельный маппинг (`DEC-079`).

На фронте JSDoc обязателен у объявления `class`; у функций вне `.vue` —
по возможности, не гейт (`DEC-077`). PHPDoc методов и типов — отдельное
правило бэкенда.

Конвейер запроса:

```text
запрос → маршрутизация action → CSRF (если не csrf:false) → request_bind (актор сессии, если модуль заявил) → биндинг handle → данные или ActionException → ActionResponse
```

`runAction` на фронте передаёт JSON-объект. Диспетчер сопоставляет ключи объекта либо с именами параметров `handle` (скаляры, `array`, backed enum), либо с именами параметров конструктора единственного `IActionInput`. Имя параметра `handle(UpdateUserInput $input)` не является ключом JSON: тело остаётся плоским `{id, name, …}`. Типы скаляров — `int`, `float`, `string`, `bool`, `array`, backed enum. Лишние ключи, отсутствие обязательного поля и несовпадение типа дают `INVALID_PARAMS`. `null` допустим только у `?T`. Поля `OptionalValue` отличают отсутствие ключа (`absent`) от JSON `null` (у `OptionalString` — `present(null)`; у `OptionalBool`/`OptionalArray` — `INVALID_PARAMS`). Сервисы и Kernel `DateTime` в `handle` не передаются — только через конструктор обработчика. Общий `handle(mixed $payload)` не является контрактом.

`handle` возвращает данные успеха (`array` / скаляр / `null`). Диспетчер оборачивает их в `ActionResponse::ok`. Доменная ошибка — `ActionException` с машиночитаемым кодом; диспетчер собирает `fail`. Класс Action не знает про `ActionResponse`. Непойманный throwable на HTTP-границе — `INTERNAL` (при `debug` в `local.php` — класс, message и trace в JSON) и запись в `ILogger` (`error_log` до модуля Logger). CSRF: кука `csrf-token` и заголовок `X-CSRF-Token`. HTTP: 200 успех, 400 доменные/`INVALID_*`/`UNKNOWN_ACTION`, 403 CSRF, 500 INTERNAL; не 401.

Cookie ответа: порт `IRequestContext` (extra Kernel, как `IRuntimeConfig`). `Application::handle` сбрасывает контекст и копирует входящие cookie; модули кладут исходящие в очередь, не вызывая `setcookie`. `ResponseEmitter` шлёт `Set-Cookie` до JSON. `dispatch()` без HTTP входящие cookie не подставляет. Актор сессии — тот же контекст; модуль с ключом `request_bind` кладёт снимок до dispatch ([`user-plan-03-http.md`](user-plan-03-http.md)). Kernel не импортирует Auth.

`main.ts` регистрирует mock или боевые API и инициализирует CSRF. `HttpClient` берёт CSRF-токен через callback и шлёт его на запросы, меняющие состояние. `runAction` кодирует имя действия и возвращает типизированный конверт ответа или ошибки.

## Контракт SmartTable (`DEC-078`)

Полный канон: [`smarttable.md`](smarttable.md). Нарезка: [`smarttable-roadmap.md`](smarttable-roadmap.md).

SmartTable — единственная серверная абстракция доступа к данным. Репозиторий не вызывает SQL, Eloquent и Query/Schema Illuminate. Внутри `Core/SmartTable` — `illuminate/database` (Connection, Query Builder, Schema), без фреймворка Laravel. `multiple` хранится доп. таблицей значений. Тегированный кэш `getList` — требование v1. Runtime-создание таблиц/полей с нашими типами — требование v1. Админский UI — после Auth. Versioned-оболочка — контракт заранее, код — план 11.

Фабрика прикладного модуля открывает таблицу через контейнер SmartTable: PHP-класс — `ISmartTableGateway::open` (class-string), таблица из словаря — `ITableCatalog::openByName`. Не строковый ключ локатора. `open` отдаёт сумку `IOpenedTable`: `schema()` — DDL (`createTable` / `updateTable` / `forceUpdateTable` / `deleteTable`), `records()` — строки (`add` / `getList` / `getUnique` / …). Репозиторий получает `records()`, класс схемы — `schema()`; сосед прикладного модуля не зовёт `open`. Гидраторы — конфиг модуля SmartTable. Установка/обновление набора модулей (граф DDL, CLI) — Kernel, не SmartTable ([`kernel-plan-01-setup.md`](kernel-plan-01-setup.md)). Журнал аудита — не этот контур.

Журнал сервера — таблица SmartTable; файл/`error_log` — запасной путь. Basic принят: прикладные модули ходят только через порты SmartTable.

## Дополнения сервера (требование, реализация OPEN)

Наследие §2 / волна 1 описывает серверные контракты, которых нет в текущем фронтовом evidence. Они сохранены как серверное требование, не как реализованное на фронте или на сервере:

- EventManager: `fire`/`on`/`off`, синхронные слушатели, очередь для асинхронных;
- детальная иерархия доменных ошибок поверх `ActionException` (базовый контракт — DEC-075);
- SmartTable fluent `query()` для сложных случаев (не v1).

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
- User → Engine, UI (не Auth; хост плагинов прав, профиля, админки и секций `/users/:id/edit`)
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

PHP-инфраструктура (не фронтовое дерево): **`Core/Agent`** — расписание тиков, CLI `bin/agent.php`, обработчики в памяти (не PHP в БД). **`Core/Mail`** — каталог событий, шаблоны, очередь jobs; агент `mail.flush`. Рёбра: Agent → SmartTable; Mail → SmartTable + Agent (`IAgents`); Auth → User и (Auth 3) Mail (`IMail`). Kernel не шлёт почту. Vue Engine агентов не настраивает. Нарезка — [`mail-roadmap.md`](mail-roadmap.md). План сброса через очередь — [`auth-plan-03-mail-reset.md`](auth-plan-03-mail-reset.md).

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
