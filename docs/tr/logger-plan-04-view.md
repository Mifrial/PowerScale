# План Logger 4 — просмотр технического журнала

**Статус:** TODO, 2026-09-09. Предыдущие заходы: [`logger-plan-01.md`](logger-plan-01.md), [`logger-plan-02.md`](logger-plan-02.md), [`logger-plan-03.md`](logger-plan-03.md). Сначала грид: [`core-ui-grid-plan-01-server-mode.md`](core-ui-grid-plan-01-server-mode.md), затем оставшиеся гриды: [`core-ui-grid-plan-02-user-lists.md`](core-ui-grid-plan-02-user-lists.md) (сделано). Канон HTTP — [`architecture.md`](architecture.md). Доступ — [`user-plan-03-http.md`](user-plan-03-http.md). Фронт — `draft-front_1.2ds/frontend-rules.md`.

Цель: дать оператору безопасный постраничный просмотр таблицы `log` через HTTP и Vue. Это журнал технических ошибок процесса, не audit log и не пользовательская история действий.

## Термины

| Термин | Смысл |
|---|---|
| Технический журнал | Записи `error` / `warning` / `info` из `Core/Logger`; `debug` в таблицу не попадает |
| Просмотр | Read-only HTTP + экран Vue; запись по-прежнему делает только `ILogger` |
| Оператор | Актор с `logger.view` или bypass |
| Детали | Разворачиваемые поля записи и безопасно отображаемый `context` |

## Решения

### 1. Граница

Добавить read-only поверхность в существующий `Core/Logger`. Не открывать SmartTable напрямую из HTTP или Vue.

PHP:

```text
LoggerHttpService → LogRepository → IOpenedRecords
                 → IUserAccess
```

Vue:

```text
FilterBar → useGridData(mode: server) → loadPage → LoggerApi.findPage
SmartGrid(rows, total, pagination, loading) ← useGridData
```

Список **без Pinia**: pagination/loading/error держит `useGridData`. Store не заводить «на всякий». `logger.get` — прямой вызов API из dialog, если строка списка недостаточна; иначе dialog из уже загруженного `LogEntry`.

PHP: Logger → SmartTable + `IUserAccess` (новое ребро). `request_bind` у Logger **не** вешать: актор кладёт Auth. CSRF у actions — `true`. `LoggerException` extends `ActionException`.

Vue-модуль `Core/Logger`: Engine, UI, User (плагин прав/admin section). UI ↛ Logger. В `architecture.md` добавить папку в дерево Core и ребро. `adminChildren` склеить в `router/moduleRoutes.ts` рядом с User/Notifications.

Не добавлять запись, редактирование, удаление, повтор запуска Agent, экспорт и live-stream. Просмотр не должен менять записи или обновлять `last_*` поля соседних модулей.

### 2. Доступ

Все actions требуют актора и ключ `logger.view`; bypass разрешён как для остальных операторских контуров. Гость получает `AUTH_REQUIRED`, актор без ключа — `AUTH_DENIED`.

Ключ `logger.view` относится к просмотру технического журнала. Не выдавать его всем пользователям и не путать с audit-доступом. Добавление ключа в начальную группу операторов — отдельный seed/настройка User в рамках этого захода только если такой механизм уже есть; не добавлять скрытый bypass для обычного актора.

### 3. HTTP-контракт

Публичные actions:

| Action | Input | Успех |
|---|---|---|
| `logger.findPage` | `limit`, `offset`; optional `level`, `source`, `sourceMode`, `errorCode`, `errorCodeMode`, `from`, `to` | `{ items: LogEntry[], total: int }` |
| `logger.get` | `id` | `LogEntry` |

`limit` — 1…100, `offset` — ≥ 0. Сортировка фиксированная: `created_at DESC`, затем `id DESC`. Нельзя принимать произвольные поля сортировки.

Фильтры HTTP — плоский JSON, **не** сырой `FilterValue` с фронта:

- `level`: optional string, только `error` / `warning` / `info`;
- `source` / `errorCode`: optional string после trim; пустая строка = нет фильтра;
- `sourceMode` / `errorCodeMode`: optional `equals` | `contains`; нет ключа при наличии строки → `equals`; mode без строки → `LOGGER_INVALID`; иной mode → `LOGGER_INVALID`;
- `from` / `to`: optional `int` Unix UTC (`?int` default `null`; **OptionalInt нет**); `from > to` → `LOGGER_INVALID`;
- `contains` на SmartTable: оператор `%` (LIKE); wildcard добавляет репозиторий, клиент сырой `%` не шлёт.

`level` на UI — select с точным совпадением. `source` / `errorCode` — штатный `StringFilter` (`equals`/`contains`); LoggerApi мапит в поля выше. Не резать `contains` в браузере.

Не возвращать более 100 строк за запрос. `total` — количество строк после фильтрации, а не размер текущей страницы. Отсутствующий id — `LOGGER_NOT_FOUND`.

### 4. JSON-вид

```json
{
  "id": 42,
  "createdAt": 1757419200,
  "level": "error",
  "message": "Unhandled kernel error",
  "source": "character.create",
  "userId": 7,
  "exceptionClass": "Mifrial\\Core\\Kernel\\Exception\\KernelException",
  "errorCode": "INTERNAL",
  "context": {
    "file": "Application.php",
    "line": 123
  }
}
```

`source`, `userId`, `exceptionClass`, `errorCode` — nullable. `context` — nullable object. JSON-вид не должен выдавать сырой `Record`.

Для `context` нужен отдельный view-mapper с начальным allowlist безопасных ключей: `file`, `line`, `message`, `jobId`, `attempts`. Отдавать только скалярные значения, обрезать слишком длинные строки; вложенные значения и остальные ключи не показывать. Это защита отображения старых строк, а не замена правилу «не писать секреты в Logger»; `message` и `exceptionClass` остаются техническими данными записи и не маскируются эвристикой.

### 5. PHP

- Расширить `LogRepository` методом страницы и методом одной записи; запросы остаются через SmartTable.
- Добавить typed DTO для входа страницы и typed/view assembler для JSON.
- Для `from`/`to` — `?int $from = null` / `?int $to = null` (OptionalInt в Kernel нет).
- ST: `equals` → `=`; `contains` → `%` + LIKE; дата → `>=` / `<=` по `created_at`. Не класть в репозиторий сырой FilterBar.
- Добавить `LoggerHttpService` с guard, фильтрами, pagination и безопасным маппингом.
- Добавить actions/routes в `Core/Logger/module.config.php`.
- Не менять контракт `ILogger` и `TableLogWriter`.
- Не добавлять индексы в этом плане без замера; если фильтрация по `created_at` окажется медленной, отдельный план SmartTable/индексов.
- Не смешивать `logger.view` с правами Auth и User: Logger только вызывает публичный `IUserAccess`.

### 6. Vue

Создать фронтовую часть `Core/Logger`:

```text
src/modules/Core/Logger/
  init.ts
  routes.ts
  Interface/ILoggerApi.ts
  Dto/LogEntry.ts
  Dto/LogPage.ts
  Dto/LogQuery.ts
  Service/LoggerApi.ts
  Page/LoggerListPage.vue
  Component/LogContextDialog.vue
  Constant/Grid/logs/columns.ts
  Constant/Grid/logs/filterFields.ts
  Constant/Permission/LOGGER_PERMISSION_CATEGORY.ts
  Constant/Permission/LOGS_ADMIN_SECTION.ts
  Constant/LoggerLevelOptions.ts
  Mock/mockLoggerApi.ts
  __tests__/Service/loggerApi.test.ts
```

Экран `/admin/logs` — read-only таблица на существующих `FilterBar` и `SmartGrid`:

- дата/время;
- уровень (строка `StringCell`; цветной чип не вводить — отдельной ячейки в гриде нет);
- source;
- message;
- error code;
- user id;
- действие «детали».

Фильтры уровня, source, error code и периода должны отправлять новый `findPage`, а не фильтровать только уже загруженную страницу. Изменение фильтра сбрасывает страницу на 1 (`useGridData`). `useFilteredRows` на Logger не подключать. Mapping FilterBar → плоский HTTP — в `LoggerApi.loadPage`/`findPage`.

`LoggerApi` преобразует `FilterBar`-значения в HTTP DTO §3. Datetime: локальная минута `HH:mm` → UTC Unix; `equals` = `[start, start+59]`. Фиксируется тестом API. `SmartGrid` берёт `rows` / `total` / `pagination` / `loading` из `useGridData`. `update:sort` не подключать. Retry — `reload`. Pinia списка нет.

`routes.ts` — `/admin/logs`, `requiresAny: ['logger.view']`. `init.ts`: API, permission category, admin section. `main.ts` — `registerLoggerModule` + mock/real API. `moduleRoutes.ts` — `...loggerAdminChildren`.

### 7. Тесты и гейты

PHP:

- actor без ключа и bypass;
- `AUTH_REQUIRED` для гостя;
- page, total, offset и limit;
- фиксированный порядок;
- каждый фильтр и комбинация фильтров;
- invalid level, диапазоны дат и границы limit;
- get существующей и отсутствующей записи;
- mapper не выдаёт запрещённые поля;
- маршрут после boot.

Vue:

- API кодирует page/filter query (включая `sourceMode` / `errorCodeMode` и datetime → unix);
- mock режет страницу теми же фильтрами;
- mapping FilterBar → HTTP покрыт unit-тестом API;
- отсутствующий `logger.view` не показывает пункт навигации.

Гейт PHP: `phpunit` suite Logger + cs/quality. Гейт фронта: `npm run format` → `npm run lint` → `npx vue-tsc --noEmit` → `npm run test`. Dev-сервер не запускать.

## Порядок работ

1. Закрыть [`core-ui-grid-plan-01-server-mode.md`](core-ui-grid-plan-01-server-mode.md).
2. Закрыть [`core-ui-grid-plan-02-user-lists.md`](core-ui-grid-plan-02-user-lists.md) (Users/Groups на том же server-режиме).
3. Зафиксировать DTO, JSON mapper и фильтры Logger.
4. Реализовать repository page/get и HTTP с guard.
5. Добавить routes и PHP-тесты.
6. Vue-модуль: API adapter + `useGridData({ mode: 'server' })`, без store списка.
7. Страница `FilterBar`/`SmartGrid`, dialog, admin route + `architecture.md`.
8. Подключить permission-aware route/navigation.
9. Прогнать гейты и обновить канон.

## Не входит

- audit log и журнал входов/смены пароля;
- изменение `ILogger`, схемы записи и уровней;
- запись из UI, delete/purge/retention;
- экспорт CSV/JSON;
- live-поток, polling и уведомления о новых ошибках;
- собственный Logger-grid или собственный Logger-filter-bar;
- полнотекстовый поиск;
- произвольный sort;
- раскрытие stack trace, SQL, токенов или других секретов;
- индексы и отдельная оптимизация SmartTable;
- доступ обычного пользователя к собственным техническим ошибкам.

## Документы захода

этот файл; [`logger-plan-01.md`](logger-plan-01.md); [`logger-plan-02.md`](logger-plan-02.md); [`logger-plan-03.md`](logger-plan-03.md); [`core-ui-grid-plan-01-server-mode.md`](core-ui-grid-plan-01-server-mode.md); [`core-ui-grid-plan-02-user-lists.md`](core-ui-grid-plan-02-user-lists.md); [`architecture.md`](architecture.md); [`user-plan-03-http.md`](user-plan-03-http.md); `frontend-rules.md`.
