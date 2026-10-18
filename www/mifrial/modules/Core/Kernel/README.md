# Core\Kernel

Карта входа: как пользоваться ядром. Описывает «как», не «что устроено» —
устройство в каноне, см. ниже. Пути даны от корня репозитория.

**Роль:** composition root и HTTP-граница. Собирает контейнеры и маршруты
модулей из `module.config.php`, находит обработчик по имени action, биндит JSON
на параметры `handle()`, возвращает единый конверт `ActionResponse`. Содержит
общий HTTP-слой (cookie-контекст, CSRF, SSE), базовые исключения и CLI-прогон
setup (DDL-граф и реестр data-шагов).

**Канон:** [`architecture.md`](../../../../../docs/tr/architecture.md) — разделы
«Контракт фундамента сервера», «PHP: локатор, контейнер модуля, фабрика»
(`DEC-070`), «Quality markers» (`DEC-071`), «PHP-правила» (`DEC-072`).
Накат схемы — [`kernel-plan-01-setup.md`](../../../../../docs/tr/kernel-plan-01-setup.md).

**Сверено по коду:** 2026-10-18.

## Ключевые типы

| Тип | Путь | Зачем |
|---|---|---|
| `ApplicationFactory` | `Service/ApplicationFactory.php` | Точка сборки: `boot()` для HTTP/CLI, `bootSetup()` для setup-прогона |
| `Application` | `Service/Application.php` | `handle()` — HTTP-конвейер, `prepareHttp()`, `dispatch()`, `emitJson()` |
| `ServiceLocator` | `Service/ServiceLocator.php` | Каталог контейнеров; `get(IXContainer::class)` |
| `ModuleContainer` | `Container/ModuleContainer.php` | Контейнер портов одного модуля |
| `ModuleManager` | `Service/ModuleManager.php` | Загрузка модулей, `getContainer(group, name)` |
| `Dispatcher` | `Service/Dispatcher.php` | Вызов обработчика и упаковка результата |
| `IActionHandler` | `Interface/Action/IActionHandler.php` | Маркер обработчика; сигнатуру задаёт класс |
| `IActionInput` | `Interface/Action/IActionInput.php` | Маркер входного DTO для биндинга конструктора |
| `ActionResponse` | `Dto/ActionResponse.php` | Конверт `{success, data, error?}` |
| `ActionException` | `Exception/ActionException.php` | Ожидаемый доменный отказ с кодом |
| `MifrialException` | `Exception/MifrialException.php` | База со строковым `getErrorCode()` |
| `OptionalValue` + `OptionalString/Int/Bool/Array` | `Value/Optional/*` | Отличить отсутствие ключа от JSON `null` |
| `JsonObject` | `Value/JsonObject.php` | JSON-объект (не список) с доступом по членам |
| `JsonDocuments` | `Http/JsonDocuments.php` | `json_decode` с сохранением объект ↔ `JsonObject` |
| `DateTime` | `Value/DateTime.php` | Unix UTC и единственная точка `now()` |
| `IRequestContext` / `RequestActor` | `Http/RequestContext.php`, `Dto/RequestActor.php` | Cookie запроса/ответа и снимок актора |
| `IRuntimeConfig` + `DatabaseSettings`/`CacheSettings` | `Service/RuntimeConfig.php`, `Dto/*` | Снимок `local.php` без побочных эффектов |
| `IModuleSetup` / `ISetupStep` | `Interface/Service/IModuleSetup.php`, `Interface/Service/ISetupStep.php` | Карты и idempotent data-шаги для CLI setup |
| `ILogger` | `Interface/Service/ILogger.php` | Порт логгера; реализацию выбирает конфиг-ключ `logger` |

Конфиг-ключи `module.config.php`: `container`, `locator`, `ports`, `routes`
(`handler`, `csrf`), `setup`, `request_bind`, `events`.

## Как пользоваться

### Объявить порт и маршрут

```php
// www/mifrial/modules/<Group>/<Name>/module.config.php
'locator' => IXContainer::class,
'ports' => [
    IXService::class => static function (IServiceLocator $serviceLocator): IXService {
        return (new XPortFactory())->create($serviceLocator);
    },
    MyAction::class => static fn (IServiceLocator $s): MyAction => new MyAction(
        (new XPortFactory())->http($s),
    ),
],
'routes' => [
    'x.myAction' => ['handler' => MyAction::class, 'csrf' => true],
],
```

### Получить порт соседнего модуля

```php
$records = $serviceLocator
    ->get(ISmartTableContainer::class)   // ключ `locator` соседа
    ->get(ISmartTableGateway::class);    // ключ `ports` соседа
```

Цепочка: **локатор → контейнер модуля → фабрика → сервис**. Локатор знает
контейнеры, контейнер знает порты, фабрика — единственное место `new` и
получения чужого контейнера.

### Обработчик

```php
final class MyAction implements IActionHandler
{
    public function __construct(private readonly IXService $service) {}

    /** @return array<string, mixed> */
    public function handle(int $id, OptionalString $note): array
    {
        return $this->service->do($id, $note);
    }
}
```

Данные успеха возвращает `handle()`; Kernel сам оборачивает их в
`ActionResponse::ok()`. Доменная ошибка — `ActionException`; прочие `Throwable`
диспетчер не ловит.

### CLI setup

`php www/mifrial/bin/setup.php` — `bootSetup()` + `ModuleSetupFactory::run()`:
накат DDL по топопорядку FK и прогон непройденных data-шагов (реестр
`setup_step`).

## Инварианты

- `handle()` обязан быть `public` и реализовать `IActionHandler`, иначе
  `INVALID_HANDLER`.
- Payload — JSON-объект (`JsonObject` или ассоциативный массив); JSON-список и
  скаляры отвергаются (`INVALID_PARAMS`).
- Поддерживаемые типы параметров: `int`, `float`, `string`, `bool`, `array`,
  `mixed`, backed enum. `"1"` в `int` **не** приводится. `DateTime` и прочие
  объекты в `handle` не передаются — только через конструктор обработчика.
- Имя параметра `handle` — не ключ JSON. Лишний ключ → `Unknown parameter`;
  отсутствующий без Optional/default → `Missing parameter`; `null` только у
  nullable-параметра.
- Режим `IActionInput`: ровно один non-nullable параметр, DTO `final`;
  JSON биндится на **имена параметров конструктора**. Смесь DTO и скаляров
  запрещена.
- `OptionalValue` отличает отсутствие ключа (`absent`) от `null`:
  `OptionalString`/`OptionalInt` принимают `present(null)`, `OptionalBool`
  отвергает `null` (`OPTIONAL_JSON`).
- CSRF: кука `csrf-token` и заголовок `X-CSRF-Token` через `hash_equals`.
  Ключ `csrf` по умолчанию `true`; проверяется только в HTTP-пути
  (`Application::handle`), `dispatch()` и `prepareHttp()` — нет.
- HTTP: `200` успех; `403` — `CSRF`; `500` — `INTERNAL`; остальное (`UNKNOWN_ACTION`,
  `INVALID_JSON`, `INVALID_PARAMS`, `INVALID_HANDLER`) — `400`. `401` не
  используется.
- Прикладной код не зовёт `getModuleContainer(...)` и не держит строковые ключи.
  Сосед берётся через `$serviceLocator->get(IXContainer::class)`.
- `override` порта и `set` локатора — только до первого `get`/freeze, иначе
  `CONTAINER_FROZEN` / `PORT_RESOLVED` / `LOCATOR_FROZEN`.
- `IRuntimeConfig` / `IRequestContext` / `ILogger` живут **только** в контейнере
  Kernel и не протекают в обычные модульные контейнеры.
- Коды action уникальны глобально; `request_bind` обязан быть ключом из `ports`
  того же модуля, иначе `INVALID_MODULE_CONFIG`.
- Kernel не импортирует `Core/Auth` и `Core/Logger`: сессия приходит через
  `request_bind`/`RequestActor`, логгер — через class-string ключа `logger` в
  `local.php`.
- Совпадающий префикс путей: `Service/X.php` ↔ `Interface/Service/IX.php`;
  контейнер — в `Container/`, а не в `Service/`.

## Точки расширения

- **Новый action/маршрут:** класс `implements IActionHandler` + запись в `ports`
  + запись в `routes`. Образец — `www/mifrial/modules/Core/Auth/module.config.php`.
- **Новый порт:** запись в `ports` и (если порт нужен соседям) ключ `locator`.
- **Setup:** ключ `setup` (class-string `IModuleSetup` или `Closure(IServiceLocator)`)
  + `getTableClasses()`/`getDataSteps()`. Образец —
  `www/mifrial/modules/Core/SmartTable/Setup/SmartTableModuleSetup.php`.
- **Конфиг:** `www/mifrial/config/local.php` (имя переопределяет env
  `MIFRIAL_CONFIG`; в phpunit — `test.php`). Ключи: `debug`, `db`, `cache`,
  `auth`, `logger`.
- **Ленивые модули:** `www/mifrial/config/modules.php`, ключ `lazy`.

## Зависимости

- Импортирует: `Core/SmartTable` (setup-прогон DDL и реестр шагов) — это
  единственная внешняя модульная зависимость production-кода Kernel.
- Импортируется: почти всеми модулями (`Core/*`, `Messages/Chat`,
  `Roleplay/*`, `Versioning/Space`).
- Специально не знает: `Core/Auth`, `Core/Logger`, `Core/Event` (ключ `events`
  в конфиге Kernel не читает), `Core/Cache`, `Core/Mail`, `Core/Agent`.

## Тесты

Каталог `tests/`. Основные:

- `KernelGuardTest.php` — CSRF, HTTP-коды, debug/prod `INTERNAL`, циклы портов,
  дубли action, кривой `request_bind`.
- `ActionParameterBinderTest.php` — все правила биндинга и Optional.
- `OptionalValueTest.php` — семантика absent/present.
- `DispatcherActionResultTest.php` — упаковка результата и `ActionException`.
- `PingDispatchTest.php` — сквозной dispatch `mifrial.ping`.
- `LazyModuleLoadTest.php`, `LazyCatalogRoutesTest.php` — ленивые модули.
- `RuntimeConfigTest.php`, `RequestContextTest.php` — конфиг, cookie, актор.
- `TableSetupOrderTest.php`, `ModuleSetupMysqlTest.php` — топопорядок FK и
  идемпотентность шагов (нужен MySQL).
- `ModuleSetupCollectorTest.php`, `ModuleDiskLoadTest.php` — сбор setup-ов.

## Не описано / OPEN

- Ключ `events` объявлен во всех `module.config.php`, но кодом Kernel не
  читается — слушатели регистрируются программно через `Core/Event`.
- `ModuleSetupRunner::run()` не оборачивает DDL в транзакцию.
- `getErrorDetails()` у базового `MifrialException` всегда `[]`; непустые
  `details` требуют своей реализации.
- Публичные `emitJson()`/`emitHttpError()`/`getModuleManager()` есть на
  `Application`, но отсутствуют в `IApplication`.
- `ITransactionRunner` объявлен контрактом, но кодом Kernel не используется;
  реализация — `Core/SmartTable`.
