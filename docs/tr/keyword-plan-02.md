# План Keyword 2 — HTTP справочника

**Статус:** каркас PHP+Vue сделан, 2026-09-06. Нарезка — [`rule-roadmap.md`](rule-roadmap.md) шаг **11**. Каркас — [`keyword-plan-01.md`](keyword-plan-01.md). Актор — [`user-plan-03-http.md`](user-plan-03-http.md). Input-DTO — [`kernel-plan-02-action-input.md`](kernel-plan-02-action-input.md). JSON-вид — `DEC-079`. UI-термин — `DEC-062`. Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Фронт — `draft-front_1.2ds/frontend-rules.md`.

Цель: публичка **`keyword.*` в модуле `Roleplay/Keyword`**, ключи **`keyword.*`**, JSON как Record (camelCase, без дат). Vue-папка этого шага оставалась в Rule; вынос — [`keyword-plan-03.md`](keyword-plan-03.md). Не RuleSpace, не Mechanic, не seed справочника, не физический DELETE.

Набросок Vue уже бьёт в `keyword.getList` / `get` / `create` / `update` / `deactivate`; `IKeywordApi.*Tag` и ключ `keyword.delete` — эскиз, канон — этот файл.

## Термины

| Термин | Смысл |
|---|---|
| JSON Keyword | Вид API: `id`, `code`, `name`, `description`, `active`. Не Record. |
| HTTP-сценарий | `KeywordHttpService`: актор, фасад, сборка JSON. |
| Ключ права | `keyword.view` / `create` / `edit` / `delete`. Action `keyword.deactivate` ≠ ключ. |
| Выключение | `active=false`. Строка и unique `code` остаются. |

## Решения

### 1. Граница

Actions и маршруты — **Keyword**, не Rule. Keyword ↛ Rule. Новое ребро PHP: **Keyword → User** (`IUserAccess`), плюс SmartTable. Не Auth, не Versioning.

Модуль lazy; маршруты как у RuleSpace (`requireModule` уже грузит конфиг). Порт `KeywordHttpService` в карте. Фасад `IKeywords` без ACL.

`KeywordException` **extends `ActionException`**. Иначе `KEYWORD_*` → 500. Коды листьев те же (`KEYWORD_INVALID`, `KEYWORD_NOT_FOUND`).

### 2. Фасад (additive)

Сейчас `add` / `get` / `getByCode`. ≤10 public.

| Метод | Смысл |
|---|---|
| `getList(): list<KeywordRecord>` | Все строки, sort `id` ASC. Не фильтр в репозитории. |
| `update(int $id, string $name, string $description): void` | Trim name; пустой → `KEYWORD_INVALID`. Description trim, `''` ок. **`code` не менять.** Нет строки → `NOT_FOUND`. HTTP-patch (одно поле) **мержит** в сценарии с текущим Record, на фасад всегда оба. |
| `deactivate(int $id): void` | `active=false`, идемпотентно. Нет строки → `NOT_FOUND`. |

Нет `delete`. Нет смены `code`. Нет реактивации (`active=true`) — OPEN, не заглушка через `update`. `getByCode` остаётся для Rule, не HTTP.

### 3. Actions

CSRF **true**. Нет актора → `AUTH_REQUIRED` → 400. Лишний ключ корня тела → `INVALID_PARAMS`.

| Action | `handle` | Успех `data` | Guard |
|---|---|---|---|
| `keyword.getList` | без параметров | `Keyword[]`, ≤500 | актор |
| `keyword.get` | `id` | Keyword | актор |
| `keyword.create` | `CreateKeywordInput`: `code`, `name`; `description` default `''` | Keyword | `keyword.create` |
| `keyword.update` | `UpdateKeywordInput`: `id`; OptionalString `name` / `description` | Keyword | `keyword.edit` |
| `keyword.deactivate` | `id` | `null` | `keyword.delete` |

Нет `keyword.getByCode`, нет `findPage`. Админка Vue режет страницу клиентом (`useGridPage`) — как сейчас. Кап HTTP **500**: если строк больше — `KEYWORD_INVALID` (неполный каталог), **не** тихая обрезка. Мок ~220; `findPage` не этот заход.

**Чтение без `keyword.view`.** Каталог нужен редакторам Character/Rule (снимки хранят id, в т.ч. выключенных). Ключ `keyword.view` — только роут `/admin/keywords`, не HTTP getList/get. Нет актора → `AUTH_REQUIRED` (гость не читает). Не открывать анониму.

`create` / `update` / `deactivate`: нет ключа → `AUTH_DENIED`. Bypass — пропуск ключа. Нет строки при акторе на get/update/deactivate → `KEYWORD_NOT_FOUND`, не DENIED.

`update`: ни одного Optional present → `KEYWORD_INVALID`. Ключ `code` / `active` на update → `INVALID_PARAMS` (эскиз code disabled и не шлёт; active только через deactivate).

### 4. JSON Keyword

| Ключ | Смысл |
|---|---|
| `id` | PK |
| `code` | unique, trim |
| `name` | trim |
| `description` | всегда string, в т.ч. `''` |
| `active` | bool |

Нет `createdAt`. `description` на Vue сейчас optional — выровнять: всегда string.

### 5. Create: code

Trim; пустой code/name → `KEYWORD_INVALID`. Дубль `code` (в т.ч. у inactive) → `KEYWORD_INVALID`. Не slug из name.

**Не копировать regex эскиза** `^[a-z0-9_]+$`: в каталоге уже дефис (`wood-elf`, `section-medicine`, `item-section-*`). Канон как план 1: непустой trim. На HTTP то же, без отдельного алфавита. Vue-форма: разрешить `-`, иначе create из UI врёт относительно живых кодов.

### 6. Vue (папка Rule, не сплит)

Клиент уже `keyword.*`. В этом заходе, не отдельный шаг:

- JSON: `description: string` (эскиз шлёт `description || undefined` — всегда строка, в т.ч. `''`).
- Кнопка «Создать» — `keyword.create`.
- Копирайт deactivate: «Выключить», не «удалить признак» как DROP; ключ кнопки **`keyword.delete`**.
- Методы `*Tag` можно оставить (имя эскиза) или переименовать в `getKeywords` / `deactivate` — без разницы для PHP; предпочесть `deactivate` на Api, стор подтянуть.
- Валидация code в форме: дефис допустим (как живые коды), не только `[a-z0-9_]`.
- Не выносить модуль из `Roleplay/Rule`. Не seed PHP из `mockKeywords`.
- **Оболочка `/admin/keywords` — эскиз, не домен.** Справочник — контент правил, не учётка. В админку User имеют смысл группы/ключи/сессии, не признаки и не механики. URL и пункт сайдбара в этом заходе не переезжают (`DEC-062`); вынос UI Keyword (и Mechanic) из `/admin` — вместе со сплитом папки (`CODE_GAP` / «Позже» нарезки), не маскировать ключом `keyword.view` как «это админка».

### 7. Тесты

Mysql `KeywordHttpMysqlTest` + suite `keyword`. Актор с ключами / bypass на запись.

- Нет актора → `AUTH_REQUIRED` на getList и get.
- Актор без ключей: getList/get ок; create/update/deactivate → `AUTH_DENIED`.
- CRUD + deactivate идемпотентно; unique code; update без полей → INVALID; update `code` → INVALID_PARAMS; getList >500 → INVALID.
- get нет строки при акторе → `NOT_FOUND`.
- Маршрут `keyword.getList` в `getRoutes()` после boot.

`KeywordMysqlTest`: `getList` / `update` / `deactivate` на фасаде. Не phpunit User/RuleSpace.

Vue: существующие keyword-тесты + гейт create, если уже есть паттерн. Не mount Vuetify.

### 8. Quality

cs/quality модуля Keyword. HttpService без +1 public сверх сценария. `IUserAccess` без новых методов. Кап getList в HTTP, не новый ST-метод.

## Что не трогаем

- Вынос Vue Keyword (`CODE_GAP`), в т.ч. с `/admin` в оператор контента. Mechanic HTTP (шаг 12). RuleSpace.
- Seed признаков, реактивация, смена `code`, `findPage`, `keyword.getByCode` HTTP.
- PHP-каталог ключей / seed на «Администраторы».
- Join-таблица `rule_keywords`; `linkset` уже на Rule.

## Риски эскиза (править клиент)

1. `*Tag` / «удалить» vs deactivate.
2. `description?` / `|| undefined` vs всегда string.
3. Список админки без гейта create.
4. Ключ `keyword.delete` = выключение, не DROP (`data-model` уже так для soft-deactivate).
5. Regex code без дефиса vs живые коды.

## Todo

- [x] **facade** — `getList` / `update` / `deactivate`; `code` иммутабелен.
- [x] **http** — пять actions, JSON, `ActionException`, Keyword → User.
- [x] **guard** — чтение любому актору; запись по ключам; `keyword.view` только роут Vue.
- [x] **vue** — description, create-кнопка, копирайт выключения; папка Rule.
- [x] **gates** — phpunit `keyword`; cs/quality Keyword; фронт format/lint/tsc/test.

## Не входит

Mechanic. Сплит Vue. Seed. Rule HTTP. Файл ревизии.

## Документы захода

этот файл; [`keyword-plan-01.md`](keyword-plan-01.md); [`rule-roadmap.md`](rule-roadmap.md); [`architecture.md`](architecture.md); `frontend-rules.md`.
