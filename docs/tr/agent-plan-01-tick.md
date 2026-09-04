# План Agent 1 — тик по расписанию

**Статус:** сделано, 2026-09-03. Нарезка — [`mail-roadmap.md`](mail-roadmap.md). Setup — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Конвейер — [`architecture.md`](architecture.md).

Цель: модуль `Core/Agent` хранит расписание и по CLI вызывает обработчики, которые **зарегистрировали** другие модули. Не почта, не cron-демон, не HTTP action, не Vue Engine.

На диске `Core/Agent` с container, setup, без `routes`.

## Термины

| Термин | Смысл |
|---|---|
| Агент | Строка `agent`: стабильный `code`, интервал, `last_run_at`, `active`. |
| Обработчик | PHP-объект в памяти процесса CLI. Код агента → порт донора. **Не** PHP-строка в БД. |
| Тик | Один проход: выбрать due-строки, вызвать обработчик, записать `last_run_at`. |

## Решения

### 1. Граница модулей

**Agent** владеет таблицей, фасадом регистрации, CLI тика.

**Донор** (Mail и позже другие):

- в `module.config` ключ **`agents`**: `code => class-string` обработчика (порт **своего** контейнера, как `routes` → handler);
- data-шаг: только `IAgents::ensureAgent(code, intervalSec)` (строка расписания). Не пишет ST `agent` сам.

**CLI** (`bin/agent.php`, composition root): `bootSetup` → для каждого загруженного модуля прочитать `agents` → `container->get(class)` → `bindHandler` → `tick()`. Так обработчики существуют до тика. HTTP `loadCore()` ключ `agents` **не** исполняет (письма на запросе не шлём).

**Kernel** не знает коды агентов. Не cron внутри PHP. User / Auth Agent не импортируют. Agent **не** импортирует Mail.

### 2. Карта SmartTable

Имя: `agent`. PK обычный `IdField`.

| Поле | Заметки |
|---|---|
| `id` | |
| `code` | string unique required; стабильный ключ (`mail.flush`), не class-string |
| `interval_sec` | int required, min 1; секунды между успешными тиками |
| `last_run_at` | datetime nullable; нет = ещё не бежал → due |
| `active` | bool required, default true |

Нет колонки «PHP callback». Нет FK на чужие модули.

Граф CLI: стол без исходящих `reference` — создастся рано. `IModuleSetup::getTableClasses()` — этот class-string. Data-шагов у Agent нет (строки агентов пишет донор).

### 3. Фасад `IAgents`

Порт контейнера (сосед имеет право `get`):

| Метод | Смысл |
|---|---|
| `ensureAgent(code, intervalSec)` | Нет строки → insert; есть → не трогать interval/active (идемпотентный seed) |
| `bindHandler(code, handler)` | Память процесса: `IAgentHandler::run(): void`. Повтор того же code — замена |
| `tick()` | Due и `active`; страницы `getList` по 500; неизвестный code без handler — пропуск, `last_run_at` **не** двигать. Лог — когда будет модуль Logger. |

Due **в PHP** после `getList` `active=true` страницами 500: у каждой строки свой `interval_sec`, фильтр ST не выражает «`last_run_at` + колонка интервала ≤ now». Due: `last_run_at === null` или `last_run_at + interval_sec <= now`. После **успешного** `run()` — `last_run_at = now`. Исключение обработчика: тик остальных продолжить; `last_run_at` не двигать.

`IAgentHandler` — интерфейс Agent. Класс донора его реализует и лежит в **портах донора**.

Сосед не импортирует `AgentRepository`, не зовёт `open`. `IModuleManager` в сервис Agent не тащим: разбор `agents` из конфигов — только `bin/agent.php`.

### 4. CLI

`www/mifrial/bin/agent.php`: `bootSetup` → bind из ключа `agents` → `IAgents::tick()` → exit 0. Cron ОС раз в минуту (README). Демон — не этот план.

HTTP этот файл не вызывает. `loadCore()` агентов не крутит и handlers не биндит.

### 5. Ошибки

Листья `AGENT_*` (`ActionException` не нужен: нет routes). Не маскировать ST `TABLE_MISSING`.

## Этапы

### A — модуль и таблица

`modules/Core/Agent/`, autoload, phpunit suite `agent`, `AgentTable`, `AgentSchema::install` для mysql-тестов, setup в `module.config`.

### B — фасад и тик

Репозиторий, `AgentService` как `IAgents`, `bindHandler` + `tick`. Mysql: ensure, due, interval, inactive skip, missing handler не двигает `last_run_at`, handler exception.

### C — CLI и доки

`bin/agent.php`; README одна строка; architecture DAG; `ModuleSetupCollectorTest` знает `Core/Agent`.

## Todo

- [x] **A-module** — контейнер, карта `agent`, setup, autoload + suite `agent`, mysql install/drop.
- [x] **B-facade** — `IAgents` / `IAgentHandler`; ensure; bind; tick страницами 500; due в PHP.
- [x] **C-cli** — `bin/agent.php` + ключ `agents`; README; `ModuleSetupCollectorTest`; quality.

## Не входит

Почта и SMTP. Админка агентов. HTTP `agent.*`. Хранение PHP в БД. Демон. Параллельные тики (два cron) — без блокировки в v1. Vue. Лог missing handler / exception тика — когда появится модуль Logger; до тех пор тик молча пропускает (last_run не двигаем).

## Документы захода

этот файл; [`mail-roadmap.md`](mail-roadmap.md); [`mail-plan-01-queue.md`](mail-plan-01-queue.md); [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md); [`TR.md`](TR.md).
