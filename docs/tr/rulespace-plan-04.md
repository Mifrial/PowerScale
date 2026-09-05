# План RuleSpace 4 — HTTP `ruleSpace.*`

**Статус:** каркас PHP сделан, 2026-09-05. Нарезка — [`rule-roadmap.md`](rule-roadmap.md) шаг **7**. Оператор — планы 1–3. Input-DTO — [`kernel-plan-02-action-input.md`](kernel-plan-02-action-input.md). Актор — [`user-plan-03-http.md`](user-plan-03-http.md). JSON-вид — `DEC-079`. Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Конвейер — [`architecture.md`](architecture.md).

Цель: actions в **`Roleplay/RuleSpace`**, префикс **`ruleSpace.*`** (как `userGroup.*`; не `space.*` часов Versioning и не URL `/space/...`). Набросок Vue `space.getList` — эскиз, шаг 10 переименует клиент. Без Vue, без ключей прав `space.create` / `view_all` / `edit_all` (шаг 8; action ≠ permission), без секций, без файла ревизии, без `changedCount`. Не actions в `Roleplay/Rule`.

Модуль по-прежнему lazy. Маршруты уже попадают в `getRoutes()` после `requireModule` (как Chat 2).

## Термины

| Термин | Смысл |
|---|---|
| JSON Space / Rule / Revision | Вид API, не Record. Unix int UTC. |
| HTTP-сценарий | `RuleSpaceHttpService`: актор, фасад, сборка JSON. |
| Первая публикация | Мир без ревизий: `commit(list put)`, не `commitSelected`. |

## Решения

### 1. Actions

Имена **серверного** контракта, не копия Vue `SpaceApi`. CSRF **true** на всех. HTTP 401 нет: нет актора → `AUTH_REQUIRED` → 400.

| Action | `handle` | Успех `data` |
|---|---|---|
| `ruleSpace.getList` | без параметров | `Space[]`, только `active=true`, ≤500, sort `id` ASC |
| `ruleSpace.get` | `id` | Space |
| `ruleSpace.getByCode` | `code` | Space |
| `ruleSpace.create` | `CreateSpaceInput`: `name`; `description` default `''`; `inheritFrom` `?int=null`; `code` `?string=null` | Space |
| `ruleSpace.update` | `UpdateSpaceInput`: `id` + OptionalString `name` / `description` | Space |
| `ruleSpace.deactivate` | `id` | `null` |
| `ruleSpace.getRevisions` | `spaceId` | `SpaceRevisionMeta[]` (без `changedCount`) |
| `ruleSpace.getRevision` | `GetRevisionInput`: `spaceId`, `revision` | SpaceRevision |
| `ruleSpace.commitDraft` | `CommitDraftInput` | SpaceRevision |

Не `ruleSpace.commit` полного list в JSON. Поле JSON `spaceId` — id мира (часы), не префикс action. Базу ревизии клиент **не** шлёт.

Лишние ключи **корня** тела → `INVALID_PARAMS`.

### 2. Guards

На все девять: `IUserAccess::requireActor()`. Нет ключей `space.*` (шаг 8). Bypass не фильтрует миры. Нет актора → `AUTH_REQUIRED`. Нет sidecar / ревизии при акторе → `RULESPACE_NOT_FOUND`, не `AUTH_DENIED`.

RuleSpace **не** импортирует Auth. Guard: `IUserContainer` → `IUserAccess` в фабрике. Новое ребро DAG: RuleSpace → User (публично), как Chat.

### 3. Исключения HTTP

`RuleSpaceException` extends `ActionException` (как Chat/User). Иначе `RULESPACE_*` → 500. Коды листьев те же.

`IRules` по-прежнему мапится Guard в `RULESPACE_*`. Маппер draft ловит `RuleInvalidException` → `RULESPACE_INVALID`.

### 4. JSON (сервер, не копия Vue)

Даты — **unix int UTC**, не ISO. Шаг 10 подстроит клиент.

**Space:** `id`, `code`, `name`, `description`, `ownerId`, `revision` (latest или **0** если ревизий нет), `active`, `createdAt`, `rulesCount` (`ruleCount` latest, включая tombstone, или 0). Нет `latestRevision` на Record — HTTP читает ленту / пусто.

**SpaceRevisionMeta:** `revision`, `publishedAt`, `ruleCount`. Нет `changedCount` (не заглушка 0).

**SpaceRevision:** `revision`, `publishedAt`, `spaceCode`, `spaceName`, `rules`. Нет `sections`.

**Rule** в срезе и ответ commit:

| Ключ | Смысл |
|---|---|
| `id` | `versionId` снимка |
| `code` | identity |
| `type` / `name` / `description` | тело |
| `spaceId` | мир запроса |
| `spec` | object/array как persist |
| `keywordIds` | id |
| `mechanicId` | id или null |
| `mechanicPayload` | JSON; не `mechanic_payload` |
| `contentStatus` | persist |
| `active` | маркер снимка (tombstone = false) |
| `createdAt` | unix |

Нет `catalogSection` / `catalogSortOrder` / `updatedAt`.

### 5. Create: code

Набросок slugify из имени, JSON `code` не шлёт. HTTP: если `code` есть и не пуст после trim — он. Иначе slug из имени (транслит кириллицы как Vue `slugify`, `[a-z0-9_-]`). Пустой slug → `RULESPACE_INVALID`. Unique → тот же INVALID, что фасад. `inheritFrom` → `add(..., $inheritFrom)`.

### 6. `commitDraft`

`spaceId`, `rules` (list объектов), `removedCodes` default `[]`. Нет `baseRevision`.

Накат **всегда на последнюю** ревизию мира (лента DESC `[0]`). Клиент не выбирает ctx как базу: черновик кладётся поверх latest. Откат к старому срезу через HTTP не этот заход (`commitSelected` с чужим base остаётся на фасаде/тестах).

- Ревизий нет: только `commit(puts)`. Непустой `removedCodes` → `INVALID` (tombstone не к чему). Пустой `rules` → `INVALID`.
- Ревизии есть: `commitSelected(latest, puts, removed)` — omit-keep с latest, removed — tombstone телом latest.

Элемент `rules` → `RuleCommitEntry::put`. Нет keep во входе. `contentStatus` нет ключа → `'needs_work'`. `active` нет ключа → `true`. `put(..., false)` допустим. Вложенный неизвестный ключ **игнорируем** (`id`/`spaceId` Vue). `mechanic_payload` не принимаем.

Класс `RuleSpaceCommitDraftMapper` в `Service/` (не порт).

### 7. Фасад и список

`IRuleSpaces` **не** расширять (уже 10 public). `getList` миров — репозиторий sidecar `filter active=true`, limit 500. HTTP зовёт `getRevisionList` на каждый элемент списка (N лент, N≤500).

### 8. Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| Actions | `Action/` | csrf; тонкий `handle` | `ActionResponse` |
| `Dto/Action/` | RuleSpace | create/update/revision/draft | Record |
| `RuleSpaceHttpService` | `Service/` | actor, фасад, JSON | `ListQuery` |
| `RuleSpaceViewAssembler` | `Service/` | Record/slice → JSON | порт контейнера |
| `RuleSpaceCommitDraftMapper` | `Service/` | JSON rules → put | часы |
| `RuleSpaceSlug` | `Service/` | code из имени | unique |
| Репозиторий | additive `getActiveList` | ST getList | HTTP |
| `IRuleSpaces` | как есть | add/get/commit* | список / JSON |

Карта `ports`: `IRuleSpaces`, `RuleSpaceHttpService`, девять Action. `createHttp` / `fromContainer` на той же `RuleSpacePortFactory`.

### 9. Тесты

`RuleSpaceHttpMysqlTest`: boot, актор, dispatch. Unit slug + mapper.

- Нет актора → `AUTH_REQUIRED`.
- create + getList (inactive нет в списке) + get / getByCode unix.
- update name; deactivate → getList пуст, get ещё отдаёт `active: false`.
- commitDraft без ревизий → ревизия 1; второй commitDraft + removedCodes поверх latest (без base в JSON).
- нет мира → `NOT_FOUND`.
- После boot есть маршрут `ruleSpace.getList`.

Не phpunit User suite. Не Vue.

### 10. Quality

cs/quality RuleSpace. HttpService у капа 10 public (ctor+9) — если sniff 11, disable: один HTTP-сценарий мира, не второй фасад. `IRules` без новых методов.

## Todo

- [x] **exception-http** — `RuleSpaceException` → `ActionException`.
- [x] **list-repo** — `getActiveList`.
- [x] **views-draft** — assembler, slug, mapper.
- [x] **http-actions** — девять routes; mysql + unit.
- [x] **docs** — роудмап шаг 7, TR, architecture ребро User.

## Не входит

Права `space.*`. Vue-имя папки. Секции. `changedCount`. Файл ревизии. Dump inactive в getList. `findPage`. HTTP Keyword/Mechanic. Actions в Rule.

## Документы захода

этот файл; [`rule-roadmap.md`](rule-roadmap.md); [`rulespace-plan-01.md`](rulespace-plan-01.md); [`rulespace-plan-03.md`](rulespace-plan-03.md); [`php-coding-standards.md`](php-coding-standards.md); [`architecture.md`](architecture.md).
