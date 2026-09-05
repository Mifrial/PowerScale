# План Logger 1 — узкий логер ошибок

**Статус:** сделано, 2026-09-04. Выбор адаптера в `module.config` **снят** — [`logger-plan-03.md`](logger-plan-03.md). Конвейер — [`architecture.md`](architecture.md). DEC-076 / [`decisions.md`](decisions.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Setup — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). Тик — [`agent-plan-01-tick.md`](agent-plan-01-tick.md).

Цель: модуль `Core/Logger` пишет технические записи в SmartTable и становится адаптером уже существующего `ILogger`. Не журнал безопасности, не экономика, не EventManager, не PSR-3 (восемь уровней syslog не копируем).

`ILogger` — порт Kernel. Соседи не импортируют Logger.

Канон уже говорит: журнал сервера — таблица ST, `error_log` — запасной путь; журнал аудита — не этот контур ([`architecture.md`](architecture.md) § SmartTable).

## Термины

| Термин | Смысл |
|---|---|
| Логер | Сток технических ошибок процесса. Контракт — `ILogger`. |
| `ProcessLogger` | Обёртка в Kernel: один экземпляр на процесс, внутри сменяемый адаптер. |
| `TableLogWriter` | Класс модуля Logger (порт контейнера): insert в `log`; `debug` и сбой insert — `ErrorLogLogger`. |
| Журнал безопасности | Входы, смена пароля, deactivate. **Не этот модуль.** |

## Решения

### 1. Граница

**Logger** владеет таблицей и табличным адаптером.

**Kernel** владеет `ILogger`, `ErrorLogLogger`, `ProcessLogger` и composition root.

**Сосед** пишет только `ILogger` с контейнера Kernel: `locator->get(IKernelContainer::class)->get(ILogger::class)`. Extra-порт Kernel, рядом с `IRuntimeConfig` / `IRequestContext`. Не `open` таблицы `log`, не `Logger/Service`.

**DAG:** Logger → SmartTable. Agent → `ILogger` (Kernel), не Logger. Auth/Mail ↛ Logger. **Kernel PHP не импортирует ни один прикладной модуль** (как уже «не импортирует Auth»). В том числе нет `use Mifrial\Core\Logger\…` в `ApplicationFactory`.

Подмена адаптера — composition root, не `agents`. Ключ **`logger` в site config** (class-string порта какого-то загруженного модуля) — [`logger-plan-03.md`](logger-plan-03.md). **Не** ключ `logger` в `module.config` (так было в этом файле до плана 3).

`ProcessLogger` **не** знает модули и ключ: только `ErrorLogLogger` + замыкание `(): ILogger` из `ApplicationFactory`. Порядок `assemble`: создать `ProcessLogger` **до** `createContainerBinder` (extra `ILogger` замыкает **тот же** объект); `loadCore` / `bindEager` как сейчас; замыкание **не** звать до первой записи в таблицу (контейнеры уже bound). В замыкании: конфиги загруженных, `hasContainer`, `get(port)`, `instanceof ILogger`. Нет ключа / нет контейнера / не `ILogger` / throwable — `ProcessLogger` оставляет `ErrorLogLogger`.

Контейнер как у Agent/Mail: `LoggerContainer`, `locator` = `ILoggerContainer`. Setup — class-string `LoggerModuleSetup`. Порт: `TableLogWriter::class`. На контейнер Logger **не** вешать `ILogger` как ключ порта (процессный `ILogger` живёт только extra Kernel). Сосед не берёт `ILoggerContainer`.

### 2. `ILogger`: уровни

Четыре метода, один контракт context:

- `error(string $message, array $context = []): void`
- `warning(...)`
- `info(...)`
- `debug(...)`

Ни один не бросает. Сбой записи не становится вторым INTERNAL и не зовёт `ILogger` снова (`TableLogWriter` → конкретный `ErrorLogLogger`, не `ProcessLogger`).

В **таблицу** попадают `error` / `warning` / `info`. **`debug` только в `error_log`**, в `log` нет (иначе зальёт диск на любом шумном цикле).

Нет `userId` / `module` в сигнатуре. Нет PSR-3 `emergency`/`notice`/… . Нет `LoggerException` / `LOG_*`, пока нечему бросать наружу.

Context: в json только скаляры и `null`; вложенное — ключ отбросить. Зарезервированные ключи адаптер **поднимает в колонки** и из json **убирает** (не дублировать). Остаток пустой → `context` null.

| Ключ context | Колонка | Кто кладёт |
|---|---|---|
| `class` | `exception_class` | Kernel INTERNAL; Agent при throwable |
| `errorCode` | `error_code` | если throwable `instanceof MifrialException` — `getErrorCode()`. **Не** `Throwable::getCode()` (int) |
| `userId` | `user_id` | Kernel INTERNAL из `IRequestContext::getActor()`; CLI/гость без актора — нет ключа |
| `source` | `source` | HTTP: код action; Agent: код агента |

Прочие ключи (`file`, `line`, `message` исключения) остаются в json. Trace в context по-прежнему не кладём.

Числового **module id** в ядре нет. Не выдумывать. Модуль восстанавливается из `source` (`user.create` → User, `mail.flush` → Agent/Mail). Отдельная колонка `module` не нужна.

`user_id` здесь — «кто был актором, когда упало», не audit «кто вошёл». **Без FK** на `user` (учётки ещё нет / удалили / CLI). IP и URI — не этот план.

Обогащение актора/`source` делает **вызывающий** (Kernel INTERNAL, Agent), не `TableLogWriter`. Адаптер не ходит в `IRequestContext`. `userId` в колонку только `int`; строка/другое — не поднимать и в json не оставлять.

`source` на HTTP: в `catch` `respondTo` читать `action` с query того же `IHttpRequest` (код действия **не** в scope — парсится внутри `dispatchFromRequest`). Пустая/не строка — ключа нет.

`Application::dispatch()` без HTTP этим планом в `ILogger` **не** оборачиваем. CLI setup/agent и сбой mail job — [`logger-plan-02.md`](logger-plan-02.md).

Доменный `ActionException` из `handle` ловит `Dispatcher` → `fail` клиенту, в логер не идёт. Прочий `MifrialException` из `handle`/контейнера — INTERNAL, код пишем.

### 2b. `getErrorCode` на `MifrialException`

Да: у любого исключения Mifrial есть строковый код. Конструктор+метод были скопированы в `KernelException`, `ActionException`, `SmartTableException`; у `ModuleManagerException` кода не было.

База: `__construct(string $errorCode, string $message, ?Throwable $previous = null)` и `getErrorCode(): string`.

`KernelException` / `ActionException` — пустые наследники (тип для `Dispatcher` / листьев). `SmartTableException` — без своего ctor (листья уже передают код).

`ModuleManagerException`: в `parent::__construct` базы передать код с абстрактного `moduleErrorCode()` листа. Коды: `MODULE_NOT_FOUND`, `INVALID_MODULE_CONFIG`. Сигнатуры `new ModuleNotFoundException($group, $name)` не ломать.

Листья ST уже зовут `parent::__construct('TABLE_MISSING', …)` — после удаления ctor у `SmartTableException` это уйдёт в базу; коды не менять. `error_code` maxLength 64: текущие коды (`FIELD_MULTIPLE_UNSUPPORTED` = 27) влезают.

### 3. Таблица

Имя: `log`. PK — `IdField::big()`.

| Поле | Заметки |
|---|---|
| `id` | bigint |
| `created_at` | datetime required, default now |
| `level` | string required: `error` / `warning` / `info` |
| `message` | text required |
| `source` | string nullable, maxLength 255 |
| `user_id` | int nullable, **без** reference |
| `exception_class` | string nullable, maxLength 255 |
| `error_code` | string nullable, maxLength 64 (`USER_INVALID`, не HTTP-статус) |
| `context` | json nullable |

Нет update/delete из адаптера. Индекс по `created_at` / `level` — не этот план (нет списка). Data-шагов нет.

Неизвестный `level` с метода не придёт. Строка с `debug` в БД не пишется.

`LoggerSchema::install()` для mysql-тестов.

### 4. Сборка и fallback

`ProcessLogger` реализует `ILogger`, стартует с `ErrorLogLogger`. **Один объект на процесс:** его же кладут в extra Kernel (`ILogger` → эта инстанция, не `new` на каждый `get`) и в конструктор `Application`.

**Ленивая** подмена inner — при **первой записи в таблицу** (`error`/`warning`/`info`), не в `boot()` и не на `debug()`. Замыкание тогда первый раз трогает контейнер Logger / ST. `ISmartTableGateway` из локатора уже открывает соединение: eager в `boot()` сломает `PingDispatchTest`.

Сборка inner **один раз на процесс**. Нет ключа `logger` / throwable в замыкании → дальше только `ErrorLogLogger`, ST не трогать. Inner собрался, insert упал (нет таблицы, `FIELD_INVALID` слишком длинный class, …) → этот вызов `ErrorLogLogger`, inner оставить. Успешный insert **не** дублирует `error_log`. `debug` всегда fallback, замыкание не вызывает.

`ProcessLogger` делегирует `error`/`warning`/`info` во inner после ensure; `debug` — сразу в fallback.

### 5. Кто пишет в v1

**HTTP INTERNAL** (`Application::respondTo`): `error`, message как сейчас `Unhandled kernel error`; context `class`, `message`, `file`, `line`; плюс `source` с query `action`; `errorCode` если `MifrialException`; `userId` если `getActor()` не null.

**Agent `tickRow`:** нет handler → `warning`, message `Agent handler is missing`, `source` = код агента. Исключение `run()` → `error`, message `Agent handler failed`, `source`, `class`, throwable `message`, `errorCode` если `MifrialException`. `last_run_at` не двигать; остальные агенты продолжить.

`info` в v1 никто не зовёт (метод есть). `debug` никто не зовёт.

`AgentPortFactory` берёт `ILogger` с Kernel. `AgentService` — зависимость конструктора. Поправить **все** `new AgentService(`: `AgentMysqlTest`, `MailMysqlTest` (сейчас без логера).

### 6. Автозагрузка и тесты

`composer.json` PSR-4 `Mifrial\Core\Logger\` + `exclude-from-classmap` tests; **autoload-dev** `Mifrial\Core\Logger\Tests\`. `phpunit.xml.dist` suite `logger`.

Mysql Logger: insert виден `getList` (level, source, колонки из context); нет таблицы → вызов не бросает; `debug` не создаёт строку. Kernel: `boot()` + ping без обязательного MySQL; extra `ILogger` **тот же** объект, что у `Application`; ключ `logger` как `request_bind` (`KernelGuardTest`: не ключ ports; дубль двух модулей). `getErrorCode()` на `ModuleNotFoundException` / `InvalidModuleConfigException`. `ModuleSetupCollectorTest` знает `Core/Logger`. `ErrorLogLogger` пишет уровень в префикс строки `error_log`.

## Этапы

### A — модуль и таблица

`modules/Core/Logger/`, autoload, suite, `LogTable`, schema, setup, collector.

### B — адаптер процесса

`ProcessLogger` + extra `ILogger`. Ключ `logger`. `getErrorCode` на базе. Ленивый `TableLogWriter`. Mysql insert / fallback.

### C — Agent и канон

Тик пишет missing handler / exception. architecture: Logger в абзаце PHP-инфраструктуры; хвост `error_log` до модуля Logger. Хвост agent-plan 1. `composer quality`.

## Todo

- [x] **A-module** — контейнер, `ILoggerContainer`, порт `TableLogWriter`, ключ `logger`, карта `log`, setup, autoload + autoload-dev + suite, mysql install/drop.
- [x] **B-adapter** — `getErrorCode` на `MifrialException`; `ILogger` четыре метода; `ProcessLogger`; extra Kernel; ленивый `TableLogWriter` по ключу `logger`; INTERNAL context (§5).
- [x] **C-agent** — лог тика; DAG/хвосты; quality.

## Не входит

Журнал безопасности / логин. PSR-3 целиком. Колонка `module` / числовой module id. FK на `user`. IP, URI. Писать `debug` в таблицу. HTTP `log.*`. Vue. EventManager. Dual-write в `error_log` при успешном insert. Ротация/purge. Индексы списка. Чтение списка кроме mysql-тестов. EconomyOperation. SMTP. SmartTable CRUD-события. Eager-open ST в `boot()`. Логировать доменный `ActionException` из `handle`. Оборачивать `Application::dispatch()` в `ILogger`. `use` Logger из Kernel. (Mail flush / CLI `bin/setup.php`+`bin/agent.php` — plan 2.)

## Документы захода

этот файл; [`architecture.md`](architecture.md); [`agent-plan-01-tick.md`](agent-plan-01-tick.md); [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md); [`php-coding-standards.md`](php-coding-standards.md); [`TR.md`](TR.md); [`decisions.md`](decisions.md) DEC-076.
