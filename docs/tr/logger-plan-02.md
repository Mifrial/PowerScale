# План Logger 2 — куда писать

**Статус:** сделано, 2026-09-04. Конвейер — [`architecture.md`](architecture.md). v1 — [`logger-plan-01.md`](logger-plan-01.md). Проводка extra `ILogger` / site config — [`logger-plan-03.md`](logger-plan-03.md).

Тот же `ILogger` и таблица `log`. Сосед — extra Kernel, не модуль Logger.

## Правило

Писать, если оператор не увидит HTTP `fail`, процесс глотает сбой или CLI падает без строки в `log`, и это не поток на каждый запрос.

## В этом заходе

**Mail job failed.** `MailFlushService` при `markFailed`: `warning`, message `Mail job failed`, `source` = `mail.flush`, json `jobId` / `attempts`; `class` / `errorCode` если был throwable. Успешный send — тишина. Mail → `ILogger` Kernel, не Logger. Фабрика как Agent.

**CLI.** После `bootSetup` `bin/setup.php` и `bin/agent.php` ловят `Throwable`, `CliFailureReporter` → `error` `Unhandled CLI error`, `source` `mifrial.setup` / `mifrial.agent`, `exit 1`. Падение boot — PHP `error_log`. `Application::dispatch()` не оборачивать.

## Не входит

`ActionException` из `handle`. Журнал Auth. `LogMailTransport` / токен reset в `error_log`. Кэш SmartTable `CacheFailSoft`. CRUD, EventManager, HTTP `log.*`, Vue, PSR-3, `info` на успех setup.

## Todo

- [x] **mail-flush** — warning на markFailed; mysql.
- [x] **cli** — setup/agent catch; юнит reporter.
- [x] **docs** — этот файл; DAG architecture; хвост plan 1.

## Следующий заход

Extra `ILogger` и class-string в `local.php` — [`logger-plan-03.md`](logger-plan-03.md).
