# Core\Logger

Карта входа: как писать и читать технический журнал. Пути даны от корня
репозитория.

**Роль:** технический журнал ядра. Порт-писатель реализует kernel-овский
`ILogger` и пишет `error`/`warning`/`info` в таблицу `log` с fallback в
`error_log` (`debug` идёт только в fallback). Отдельный HTTP-сервис отдаёт записи
наружу с пагинацией, фильтрами и правом `logger.view`. Это **не** журнал аудита.

**Канон:** отдельного канон-документа нет; контракт описан этой картой входа.
Протоколы работ (`сделано`) — [`logger-plan-01.md`](../../../../../docs/tr/logger-plan-01.md)
… [`logger-plan-04-view.md`](../../../../../docs/tr/logger-plan-04-view.md).
[`logger-plan-05-vue-datetime.md`](../../../../../docs/tr/logger-plan-05-vue-datetime.md)
— активный `TODO` про даты на экране журнала (HTTP не трогает).

**Сверено по коду:** 2026-10-18.

## Ключевые типы

| Тип | Путь | Зачем |
|---|---|---|
| `TableLogWriter` | `Service/TableLogWriter.php` | Порт-писатель, `implements ILogger` (Kernel): `error`, `warning`, `info`, `debug` |
| `LoggerHttpService` | `Service/LoggerHttpService.php` | Порт чтения: `findPage(FindLogPageInput)`, `get(int)` |
| `LoggerPermissionKeys` | `Service/LoggerPermissionKeys.php` | `VIEW = 'logger.view'` |
| `LogFindPageParser` | `Service/LogFindPageParser.php` | Разбор и валидация входа `findPage` |
| `LogViewAssembler` | `Service/LogViewAssembler.php` | JSON-вид записи с allowlist контекста |
| `LogRepository` | `Repository/LogRepository.php` | `add`, `findPage`, `getById`, `listRecent` |
| `LogTable` | `Table/LogTable.php` | Карта `log`: `id`, `created_at`, `level`, `message`, `source`, `user_id`, `exception_class`, `error_code`, `context` |
| `LoggerSchema` / `LoggerModuleSetup` | `Schema/LoggerSchema.php`, `Setup/LoggerModuleSetup.php` | Накат таблицы; data-шагов нет |
| `LoggerPortFactory` | `Service/LoggerPortFactory.php` | Сборка писателя, HTTP-сервиса и action-ов |
| `ILoggerContainer` / `LoggerContainer` | `Interface/Container/ILoggerContainer.php`, `Container/LoggerContainer.php` | Локатор модуля |
| `FindLogPageAction` / `GetLogAction` | `Action/*.php` | Маршруты `logger.findPage`, `logger.get` (оба `csrf: true`) |
| `LogRecord` / `LogRecordPage` / `LogPageQuery` / `FindLogPageInput` | `Dto/*.php` | Записи, страница, запрос, вход |
| `LoggerException` / `LoggerInvalidException` / `LoggerNotFoundException` | `Exception/*.php` | Ошибки модуля |

## Как пользоваться

### Писать

```php
/** @var TableLogWriter $writer */
$writer = $serviceLocator->get(ILoggerContainer::class)->get(TableLogWriter::class);

$writer->error('boom', [
    'source' => 'mifrial.ping',
    'class' => SomeException::class,  // → exception_class
    'errorCode' => 'X',              // → error_code
    'userId' => 1,                   // → user_id
]);
$writer->warning('miss', ['source' => 'mail.flush']);
$writer->info('note');
$writer->debug('trace');             // только error_log, не в таблицу
```

Зарезервированные ключи контекста: `source`, `class` → `exception_class`,
`errorCode` → `error_code`, `userId` → `user_id`. Остальные **скаляры**
собираются в JSON-колонку `context`; всё нескалярное и вложенное отбрасывается,
строки обрезаются до 8000 символов.

### Читать

```php
/** @var LoggerHttpService $http */
$http = $serviceLocator->get(ILoggerContainer::class)->get(LoggerHttpService::class);

$page = $http->findPage($input);  // ['items' => [...], 'total' => int]
$item = $http->get($id);          // JSON-вид или LoggerNotFoundException
```

Границы `findPage`: `limit` 1..100, `offset ≥ 0`, `level` из
`error`/`warning`/`info`; `source` и `errorCode` — `equals` (по умолчанию) или
`contains` (LIKE с экранированием `%`, `_`, `\`); `from ≤ to`. Сортировка
`created_at desc, id desc`.

JSON-вид записи: `id`, `createdAt`, `level`, `message`, `source`, `userId`,
`exceptionClass`, `errorCode`, `context`. Allowlist контекста:
`file`, `line`, `trace`, `message`, `jobId`, `attempts`.

### Подключение к ядру

Kernel **не импортирует** `Core/Logger`. Реализация выбирается по class-string
в ключе `logger` файла `local.php` из числа объявленных портов загруженных
модулей (`ConfiguredLoggerResolver`) и оборачивается в `ProcessLogger`.
Текущее значение — `Mifrial\Core\Logger\Service\TableLogWriter::class`.

## Инварианты

- `error`/`warning`/`info` пишут строку в таблицу; `debug` — только fallback.
- Сбой insert не пробрасывается: fallback в `error_log` по уровню
  (`warning`→warning, `info`→info, иначе error). Отсутствие таблицы тоже не
  бросает исключение.
- HTTP-методы требуют права `logger.view`: нет актора → `AUTH_REQUIRED`, нет
  ключа → `AUTH_DENIED`.
- `getById` несуществующего id → `LoggerNotFoundException`.
- Невалидные границы, уровень или режим → `LoggerInvalidException`.
- Исключения SmartTable наружу не протекают: `MapInvalidException` маппится в
  `LoggerInvalidException`.
- Журнал не является аудитом: нет семантики «кто что изменил», нет ретеншена и
  ротации.

## Точки расширения

- **Свой адаптер `ILogger`:** объявить порт в модуле и указать его class-string
  в ключе `logger` файла `local.php`.
- **Парсер, ассемблер, репозиторий** — внутренние классы модуля; расширение
  идёт через них, не через конфиг.
- Data-шагов у модуля нет (`LoggerModuleSetup::getDataSteps()` — пусто).

## Зависимости

- Импортирует: `Core/Kernel` (`ILogger`, `ErrorLogLogger`, `DateTime`),
  `Core/User` (`IUserAccess`), `Core/SmartTable`.
- Импортируется: Kernel — только в тесте `ModuleSetupCollectorTest.php`;
  связывание с рантаймом идёт через конфиг-ключ `logger`, а не через импорт.
- Потребители записи: `API/action.php` (непойманный throwable), `bin/setup.php`,
  flush почты.

## Тесты

- `tests/LoggerMysqlTest.php` — `error`/`warning`/`info` в таблицу, `debug` в
  fallback, сбой insert → `error_log`, маппинг контекста (MySQL, self-skip).
- `tests/LoggerHttpMysqlTest.php` — `findPage`/`get`, право `logger.view`,
  фильтры, JSON-вид.
- `tests/LogRepositoryFilterTest.php` — `from`/`to` как `DateTime`, `contains`
  экранирует `%` и `_`.
- `tests/LogViewAssemblerTest.php` — allowlist скаляров, обрезка до 8000,
  отброс вложенного.

## Не описано / OPEN

- Не реализовано: аудит-семантика, ретеншен и ротация, дедупликация, запись
  `debug` в таблицу, экспорт, асинхронная запись.
- Уровень `debug` доступен только через fallback — в таблицу не попадает.
