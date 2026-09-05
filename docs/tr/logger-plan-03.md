# План Logger 3 — class-string логера в конфиге сайта

**Статус:** сделано, 2026-09-05. v1 — [`logger-plan-01.md`](logger-plan-01.md). Куда писать — [`logger-plan-02.md`](logger-plan-02.md). Конвейер — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: сайт выбирает табличный адаптер в `config/local.php` (и `test.php`). Модуль Logger только публикует порт. Kernel не импортирует Logger и не читает ключ `logger` из `module.config`. Соседи по-прежнему `IKernelContainer::get(ILogger::class)`.

Повод: `php bin/setup.php` падает `UNKNOWN_PORT` на `ILogger` (Agent/Mail), затем `CliFailureReporter` маскирует исходную ошибку тем же портом. Тот же `get(ILogger)` на `bootSetup` — `ModuleSetupCollectorTest` (Mail setup → `IAgents`). `PingDispatchTest` уже ждёт extra `ILogger`; `ApplicationFactory` его не вешает.

## Термины

| Термин | Смысл |
|---|---|
| Site config | `config/{local\|test}.php`, не `module.config.php`. |
| Порт адаптера | ключ `ports` модуля, class-string класса, который `instanceof ILogger` (v1 — `TableLogWriter`). |
| Процессный логер | extra Kernel `ILogger`: один `ProcessLogger` на процесс. |

## Решения

### 1. Выбор — сайт, не модуль

`local.php.dist` / `test.php.dist`:

```php
'logger' => Mifrial\Core\Logger\Service\TableLogWriter::class,
```

`::class` без ведущего `\`. Не строка / не class-string — как нет ключа.

phpunit: `MIFRIAL_CONFIG=test` → живой `config/test.php`, не только dist. Dist правим в git; копии `local.php` / `test.php` на стенде — вручную, иначе `error_log`.

У `Core/Logger/module.config.php` ключ `'logger'` **снять**. Остаётся `ports[TableLogWriter::class]`. На `ILoggerContainer` порт `ILogger` **не** вешать.

`ModuleManager::assertLoggerConfig` и тесты «кривой ключ / дубль logger в module.config» — выкинуть. Уникальность адаптера даёт один ключ сайта, не гонка двух модулей.

### 2. Kernel не знает Logger

Нет `use Mifrial\Core\Logger\…` в Kernel. Нет `requireModule` «по class-string».

Замыкание `ProcessLogger` (после `bindEager`, на первой записи в таблицу): среди **уже загруженных** конфигов найти `ports[$class]` из site `'logger'`; строка `locator` того конфига → `$serviceLocator->get($locatorKey)->get($class)` → `instanceof ILogger`. `get` после `freeze` локатора законен. Два модуля с тем же ключом ports, нет/не строка ключа сайта, нет порта, нет контейнера, не `ILogger`, throwable — inner остаётся `ErrorLogLogger`. **Boot не падает** (ping без MySQL). Extra `ILogger`: фабрика каждый `get` отдаёт **тот же** захваченный `ProcessLogger`, не `new`.

Класс ленивого модуля в site config v1 не контракт: `boot()` не обязан поднимать lazy-контейнер ради логера. v1 — порт Core/Logger, контейнер already bound.

Поиск порта — отдельный тип в `Kernel/Service/` (composition root), не раздувать `ApplicationFactory`. Не интерфейс ради одного вызова снаружи Kernel.

### 3. Порядок `assemble`

`createContainerBinder` сейчас **до** `loadCore`. Таблицу в extra-порт в этот миг резолвить нельзя.

Как план 1, довести до кода:

1. `ProcessLogger(ErrorLogLogger, (): ILogger)` **до** binder.
2. Extra Kernel `ILogger` и ctor `Application` — **тот же** объект. Геттер `ILogger` на `IApplication` **не** заводить.
3. `loadCore` / `loadAllFromDisk` + `bindEager` как сейчас.
4. Замыкание **не** звать до первой записи в таблицу (`error`/`warning`/`info`). `debug` — сразу fallback, ST не открывать.

`bootSetup`: Mail collector → `IAgents` → `get(ILogger)` **до** DDL `log`. Ctor Agent таблицу не требует. Первый write до `createTable` — сбой insert внутри адаптера, строка в `error_log`.

### 4. CLI не маскирует setup

`CliFailureReporter::reportFromApplication`: нет `IKernelContainer` / `get(ILogger)` бросил / не `ILogger` → писать через `ErrorLogLogger`, **не** бросать. Иначе снова `UNKNOWN_PORT` вместо причины DDL.

`bin/setup.php` и `bin/agent.php` без namespace: снять бесполезный `use Throwable`.

### 5. Соседи

Agent и Mail **не** переводить на `ILoggerContainer`. Фабрики как сейчас: extra Kernel.

## Todo

- [x] **site-config** — ключ в dist; README одна строка.
- [x] **wire** — один `ProcessLogger`; generic замыкание; Logger без `'logger'` в module.config; снять `assertLoggerConfig`.
- [x] **cli** — reporter не бросает; `use Throwable`.
- [x] **tests** — ping: два `get(ILogger)` — один объект, `ProcessLogger`, без ST (ping не пишет в таблицу). `bootSetup` collector зелёный. Нет/мусор class → fallback. Выкинуть KernelGuard logger-в-модуле. Юнит reporter: `get(ILogger)` бросил — не маскирует, пишет fallback. quality Kernel+Logger. Не phpunit Chat. Не геттер на `IApplication`.
- [x] **canon** — этот файл; план 1 (ключ module.config — было); architecture; `TR.md`.

## Не входит

Смена `ILogger`. PSR-3. Журнал Auth. HTTP `log.*`. Выбор логера после freeze. Автозагрузка модуля по имени класса. Vue. Chat.

## Слои

| Тип | Задача | Не делает |
|---|---|---|
| site config | class-string адаптера | карта `log` |
| Logger `ports` | `TableLogWriter` | ключ `logger`, порт `ILogger` |
| Kernel extra | процессный `ILogger` | `use` Logger |
| Agent / Mail | `get(ILogger)` с Kernel | `ILoggerContainer` |

## Документы захода

этот файл; [`logger-plan-01.md`](logger-plan-01.md); [`logger-plan-02.md`](logger-plan-02.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).

## Следующий заход

После проводки — `php bin/setup.php` на стенде (схема Chat и `log`). Не этот файл.
