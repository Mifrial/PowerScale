# План Mechanic 2 — HTTP справочника

**Статус:** каркас PHP+Vue сделан, 2026-09-06. Нарезка — [`rule-roadmap.md`](rule-roadmap.md) шаг **12** (последний шаг этой нарезки). Каркас — [`mechanic-plan-01.md`](mechanic-plan-01.md). Эталон публички справочника — [`keyword-plan-02.md`](keyword-plan-02.md); физика другая — не копировать `active` / `deactivate` / unique `code`. Актор — [`user-plan-03-http.md`](user-plan-03-http.md). Input-DTO — [`kernel-plan-02-action-input.md`](kernel-plan-02-action-input.md). JSON-вид — `DEC-079`. UI-термин — `DEC-062`. Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Фронт — `draft-front_1.2ds/frontend-rules.md`.

Цель: публичка **`mechanic.*` в модуле `Roleplay/Mechanic`**, ключи **`mechanic.create` / `mechanic.edit`**, JSON как вид API (camelCase, без дат). Vue-папка — [`mechanic-plan-03.md`](mechanic-plan-03.md). Не RuleSpace, не Keyword, не Engine, не seed справочника, не физический DELETE, не колонка `active`.

Эскиз Vue бьёт в **`rule.getMechanics`** на `IRuleApi`; JSON-поле **`version`**. Канон — этот файл: action **`mechanic.getList`**, PHP Mechanic ↛ Rule.

## Термины

| Термин | Смысл |
|---|---|
| Семейство | Один `code` хендлера (`six_one_rule`). Не unique один. |
| Поставка | Строка: тот же `code` + `handler_version`. Unique пара. Правило хранит **id строки**. |
| JSON Mechanic | Вид API: `id`, `code`, `name`, `description`, `version`. Не Record. |
| HTTP-сценарий | `MechanicHttpService`: актор, фасад, сборка JSON. |
| Ключ права | `mechanic.create` / `mechanic.edit`. Ключа `mechanic.delete` нет. |

## Решения

### 1. Граница

Actions и маршруты — **Mechanic**, не Rule. Mechanic ↛ Rule. Новое ребро PHP: **Mechanic → User** (`IUserAccess`), плюс SmartTable. Не Auth, не Versioning, не Keyword, не RuleSpace, не Engine.

Модуль lazy; маршруты как у Keyword (`requireModule` уже грузит конфиг). Порт `MechanicHttpService` в карте. Фасад `IMechanics` без ACL.

`MechanicException` **extends `ActionException`**. Сейчас база — `MifrialException`; иначе `MECHANIC_*` → 500. Коды листьев те же (`MECHANIC_INVALID`, `MECHANIC_NOT_FOUND`).

### 2. Фасад (additive)

Сейчас `add` / `get` / `getByCodeVersion`. ≤10 public.

| Метод | Смысл |
|---|---|
| `getList(): list<MechanicRecord>` | Все поставки, sort `id` ASC. Не фильтр в репозитории. Не «последняя» по семейству. |
| `update(int $id, string $name, string $description): void` | Trim name; пустой → `MECHANIC_INVALID`. Description trim, `''` ок. **`code` и `handler_version` не менять.** Нет строки → `NOT_FOUND`. HTTP-patch (одно поле) **мержит** в сценарии с текущим Record, на фасад всегда оба. |

`add` уже есть: в этом заходе **trim `description`**, как Keyword (сейчас `Mechanics::add` description не trim). Сигнатуру не менять.

`update` нужен: правка подписи без новой поставки. Новая поставка семейства = **`add`**, не patch version. Итого public фасада: 5 (`add`/`get`/`getByCodeVersion`/`getList`/`update`).

Нет `delete`. Нет `deactivate`. Нет колонки `active` (не тащить «для симметрии с Keyword»). Нет `getByCode`. `getByCodeVersion` остаётся для Rule, **не HTTP**.

### 3. Actions

CSRF **true**. Нет актора → `AUTH_REQUIRED` → 400. Лишний ключ корня тела → `INVALID_PARAMS`.

| Action | `handle` | Успех `data` | Guard |
|---|---|---|---|
| `mechanic.getList` | без параметров | `Mechanic[]`, ≤500 | актор |
| `mechanic.get` | `id` | Mechanic | актор |
| `mechanic.create` | `CreateMechanicInput`: `code`, `name`, `version`; `description` default `''` | Mechanic | `mechanic.create` |
| `mechanic.update` | `UpdateMechanicInput`: `id`; OptionalString `name` / `description` | Mechanic | `mechanic.edit` |

Нет `mechanic.getByCode`, нет `mechanic.getByCodeVersion`, нет `findPage`, нет DELETE/deactivate.

Кап HTTP **500**: если строк больше — `MECHANIC_INVALID` (неполный каталог), **не** тихая обрезка. Мок ~18; тот же dump+кап, что Keyword. `findPage` не этот заход.

**Чтение HTTP без ключа view.** Каталог нужен редактору правила, Character, Game (снимки хранят `mechanic_id` конкретной поставки). PHP **не** проверяет `mechanic.view` на `getList`/`get`. Ключ `mechanic.view` — только Vue-оболочка `/admin/mechanics` (как `keyword.view`), не маска «это админка User». Нет актора → `AUTH_REQUIRED` (гость не читает). Не открывать анониму.

`create` / `update`: нет актора → `AUTH_REQUIRED` (`requireKey` сначала требует актора). Актор без ключа → `AUTH_DENIED`. Bypass — пропуск ключа. Нет строки при акторе на get/update → `MECHANIC_NOT_FOUND`, не DENIED.

`update`: ни одного Optional present → `MECHANIC_INVALID`. Ключ present со значением JSON `null` → `MECHANIC_INVALID` (как Keyword). Ключ `code` / `version` / `handlerVersion` / `handler_version` на update → `INVALID_PARAMS`.

`create`: ключ `handlerVersion` / `handler_version` (вместо `version`) → `INVALID_PARAMS`. Версия поставки на проводе — только `version`. `getList` без `IActionInput` (как Keyword `handle()`).

### 4. JSON Mechanic

| Ключ | Смысл |
|---|---|
| `id` | PK; цель `mechanic_id` у правила |
| `code` | код семейства, trim |
| `name` | trim |
| `description` | всегда string, в т.ч. `''` |
| `version` | поставка контракта; колонка PHP `handler_version`, Record `handlerVersion` |

Нет `createdAt`. Нет `active`. Нет `handlerVersion` / `handler_version` на проводе.

**Имя ключа версии:** `version`, как эскиз Vue и [`rule-system.md`](rule-system.md) («Mechanic имеет … version»), не `handlerVersion` Record. `DEC-079`: JSON ≠ Record. Колонку по-прежнему не звать `version` (план 1). Assembler мапит `getHandlerVersion()` → JSON `version`. Клиент DTO `Mechanic.version` не переименовывать.

### 5. Create: code и version

Trim; пустой `code` / `name` / `version` → `MECHANIC_INVALID`. Дубль пары `(code, version)` → `MECHANIC_INVALID`. Не slug из name. Алфавит code как план 1: непустой trim, без отдельного regex. `version` на HTTP — та же строка, что `handler_version` (не semver-проверка в SQL).

Вторая поставка того же семейства — второй `create` с тем же `code` и другим `version`, новая строка / новый id.

### 6. Vue (папка Rule, не сплит)

В этом заходе, не отдельный шаг:

- Снять `getMechanics` / `rule.getMechanics` с `IRuleApi`, `RuleApi`, `mockRuleApi`. PHP-маршрута `rule.getMechanics` нет — только эскиз клиента.
- Клиент **`IMechanicApi` + `MechanicApi` + mock** в папке Rule. `registerMechanicApi` / `getMechanicApi` в `Rule/init.ts`; `main.ts` mock/real рядом с Keyword. Метод каталога: `getMechanics()` → action **`mechanic.getList`**. Character/Game зовут **публичку Rule** (`getMechanicApi`), не внутренности и не будущий Vue-модуль Mechanic (ребро Character/Game → Mechanic по канону — «Позже», Engine).
- Call sites и тесты, которые `registerRuleApi` + `getMechanics`: `RuleDetailPage`, `RuleEditPage`, Character editor/draft, Game chat/providers/`GameDetailPage`. Перевести на `registerMechanicApi` / `getMechanicApi`.
- JSON: поле **`version`** оставить. `description` всегда string.
- Методы `createMechanic` / `updateMechanic` / `getMechanic` на Api — для админки и импорта ревизии.
- Категория прав в Rule (плагин User, как Keyword): `mechanic.view` / `mechanic.create` / `mechanic.edit` в матрице групп. **Без `delete`.** Секция `registerAdminSection` → `/admin/mechanics`. HTTP записи по-прежнему `create`/`edit`; `view` не гейтит PHP. Не seed PHP «Администраторы».
- Не seed PHP из `mockMechanics`. Не выносить Engine / хендлеры. Vue DAG: Mechanic по-прежнему ↛ User (нет админ-плагина модуля Mechanic); категория и страницы живут в папке Rule (`CODE_GAP`).

### 7. Тесты

Mysql `MechanicHttpMysqlTest` + suite `mechanic`. Актор с ключами / bypass на запись.

- Нет актора → `AUTH_REQUIRED` на getList, get, create, update.
- Актор без ключей: getList/get ок; create/update → `AUTH_DENIED`. Bypass: create ок без ключа.
- CRUD: две поставки одного `code` в getList; unique пары; update без полей → INVALID; update `code`/`version` → INVALID_PARAMS; create с `handlerVersion` → INVALID_PARAMS; getList >500 → INVALID.
- get нет строки при акторе → `NOT_FOUND`.
- Маршрут `mechanic.getList` в `getRoutes()` после boot (`MechanicPortBootTest`).

`MechanicMysqlTest`: `getList` / `update` на фасаде (плюс уже закрытые add/get/getByCodeVersion). Не phpunit Keyword/RuleSpace/User.

Vue: call sites и мок каталога; не mount Vuetify. Не трогать тесты Engine/хендлеров сверх смены источника списка.

### 8. Quality

cs/quality модуля Mechanic. HttpService без +1 public сверх сценария. `IUserAccess` без новых методов. Кап getList в HTTP, не новый ST-метод.

## Что не трогаем

- `MechanicEngine`, hydrator payload, хендлеры PHP/Vue.
- Вынос Vue Mechanic — [`mechanic-plan-03.md`](mechanic-plan-03.md). Keyword уже вынесен. Hypothetical `/admin` в оператор контента — не этот HTTP-заход.
- HTTP правил в `Roleplay/Rule`. RuleSpace.
- Seed справочника, semver-«последняя» в SQL, `findPage`, смена `code` / `handler_version`.
- PHP-каталог ключей / seed на «Администраторы».
- Колонка `active`, реактивация, DELETE.

## Риски эскиза (править клиент)

1. `rule.getMechanics` на `IRuleApi` — чужой модуль; канон `mechanic.getList`.
2. Имя колонки `handler_version` vs JSON `version` — не тащить Record-ключ на провод.
3. Deactivate механики нет — не копировать кнопку «Выключить» с Keyword. `mechanic.view` только для Vue-маршрута/сайдбара.
4. `getByCode` без версии неоднозначен; на HTTP не открывать даже `getByCodeVersion` (каталог dump + get по id).
5. Тесты Game/Character держат каталог через `registerRuleApi(mockRuleApi)` — сломаются, если снять `getMechanics` и не зарегистрировать MechanicApi.

## Todo

- [x] **facade** — `getList` / `update`; trim description на `add`; `code` и `handler_version` иммутабельны.
- [x] **http** — четыре actions, JSON `version`, `ActionException`, Mechanic → User.
- [x] **guard** — чтение любому актору; запись `mechanic.create` / `mechanic.edit`; HTTP без `mechanic.view`.
- [x] **vue** — `IMechanicApi` + locator в Rule init/`main.ts`; снять `rule.getMechanics`; категория view/create/edit + `/admin/mechanics`; DTO `version`.
- [x] **gates** — phpunit `mechanic`; cs/quality Mechanic; фронт format/lint/tsc/test.
- [x] **docs** — шаг 12 в `rule-roadmap.md`; `TR.md`; PHP Mechanic → User (`IUserAccess`) в `architecture.md` (абзац сервера **и** строка Vue-DAG: PHP-ребро, **не** Vue Mechanic → User); `mechanic-plan-01.md`: HTTP этого шага закрыт, модуль **не** закрыт (Engine — «Позже»).

## Не входит

Engine. Сплит Vue. Seed. Rule HTTP. Файл ревизии. Keyword.

## Документы захода

этот файл; [`mechanic-plan-01.md`](mechanic-plan-01.md); [`keyword-plan-02.md`](keyword-plan-02.md); [`rule-roadmap.md`](rule-roadmap.md); [`architecture.md`](architecture.md); `frontend-rules.md`.
