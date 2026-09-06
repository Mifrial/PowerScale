# План 1 — формат файла ревизии v3

**Статус:** каркас Vue сделан, 2026-09-06. Нарезка — [`revision-file-roadmap.md`](revision-file-roadmap.md) шаг **1**. Канон ссылок — [`rule-system.md`](rule-system.md) (`DEC-060`). Срез HTTP — [`rulespace-plan-04.md`](rulespace-plan-04.md), секции — [`rulespace-plan-06.md`](rulespace-plan-06.md). JSON справочников — [`keyword-plan-02.md`](keyword-plan-02.md), [`mechanic-plan-02.md`](mechanic-plan-02.md). Фронт — `draft-front_1.2ds/frontend-rules.md`.

Цель: заменить текущий JSON `powerscale.revision` на **внешний** вид v3: без `id` / `spaceId` / `keywordIds` / `mechanicId` / `createdAt` на правиле. `RevisionFileService` parse/serialize и ошибки с `code`/`path`/`stage`. Живые кнопки Экспорт/Импорт в этом заходе **подключают v3 только если** round-trip на фикстуре зелёный; потоки apply/резолв справочников — шаг 2–3. Не PHP. Не HTTP файла. Не `commitDraft`. Не Engine.

Текущий UI (`formatVersion` 1|2, ISO `exportedAt`, внутренние id) — **legacy**. Parse v1/v2 → ошибка формата (не молчаливый strip id).

## Термины

| Термин | Смысл |
|---|---|
| Файл ревизии | JSON документа для переноса. Не HTTP срез, не localStorage draft. |
| Правило внешнее | Тело снимка + ссылки **кодами**. |
| Provenance | `source`: откуда выгрузили. Не адрес импорта, не `spaceId`. |
| Stage | Этап проверки: `syntax` \| `format`. Другие stage — шаги 2–3. |

## Решения

### 1. Envelope

```text
format: "powerscale.revision"
formatVersion: 3
exportedAt: unix int UTC
source: { spaceCode, spaceName, revision, publishedAt }
keywords: list KeywordExternal
mechanics: list MechanicExternal
sections: list AbilitySection-вида (как HTTP: code, name, parentCode, sortOrder, catalogRootFor?)
rules: list RuleExternal  (непустой)
```

`REVISION_FILE_FORMAT` не менять. Константа версии файла = **3**. `SUPPORTED` только 3.

`exportedAt` — unix, как `publishedAt` / `createdAt` среза, не ISO.

Лишний ключ **корня** → ошибка `format`. Нет `revision` как копии всего `SpaceRevision<Rule>` (сейчас так — сломать).

Пустые `keywords` / `mechanics` / `sections` — `[]`, ключ обязателен (как `sections` на срезе). Шаг 1 не резолвит, что они согласованы со справочником; только форма list.

### 2. KeywordExternal / MechanicExternal

Снимки **использованных** в шаге 2; в шаге 1 — форма:

- Keyword: `code`, `name`, `description` (string, в т.ч. `''`), `active`. Без `id`. Как JSON Keyword, минус `id`.
- Mechanic: `code`, `name`, `description`, `version`. Без `id`. Ключ поставки на проводе — **`version`**, как `mechanic.*`, не `handlerVersion` / `handler_version`.

Дубль `keywords[].code` или дубль mechanics `(code, version)` → `format`.

### 3. RuleExternal

Обязательные: `code` (непустой trim), `type` (непустая строка), `name` (непустой trim), `description` (string, `''` ок), `spec` (object или array), `keywordCodes` (list string), `mechanic` (`null` или `{ code, version }`), `mechanicPayload` (object или array), `contentStatus` (непустая строка), `active` (bool).

Опционально, как HTTP срез: `catalogSection` (`string | null`), `catalogSortOrder` (int, нет ключа = нет размещения / 0 при наличии секции — **как сейчас mapper**: нет/`null` catalogSection → нет карточки; `catalogSortOrder` default 0).

Запрещены на объекте правила (наличие ключа → `format`): `id`, `spaceId`, `keywordIds`, `mechanicId`, `createdAt`, `updatedAt`, `mechanic_payload`.

Инвариант: `mechanic === null` ⇒ `mechanicPayload` это `[]`. Иначе `format`.

`type` на шаге 1 **не** сверять с `RULE_TYPE_LABELS` (PHP persist неизвестный тип допускает). Неизвестный type — не ошибка parse. Warning UI — шаг 3.

Дубль `rules[].code` → `format`. Пустой `rules` → `format` (как сейчас «в файле нет правил»).

Внутри `spec` и `mechanicPayload` неизвестные поля **не** режем и не валидируем по RuleType.

### 4. Секции

Тот же JSON, что `AbilitySection` на срезе. Parse: list объектов с `code`, `name`, `parentCode` (`string | null`), `sortOrder` (int); `catalogRootFor` optional string. Циклы / placement vs rules — **шаг 3** (нужен apply). Шаг 1: форма list и unique `sections[].code`.

Размещение карточки — поля на правиле (`catalogSection`), не отдельный массив placements. Не плодить второй канал.

### 5. Provenance `source`

Все четыре поля обязательны: `spaceCode`/`spaceName` string, `revision` number ≥ 1, `publishedAt` unix int. Импорт шага 3 **игнорирует** их как цель мира.

Serialize шага 1 из текущего `SpaceRevision`: заполнить `source` из `spaceCode`/`spaceName`/`revision`/`publishedAt`; не класть `spaceId` никуда.

### 6. Ошибки

Бросать не голую `Error` с русской фразой как id. Тип (один файл Dto):

```text
code: string      // например REVISION_FILE_NOT_JSON, REVISION_FILE_FORMAT, REVISION_FILE_UNSUPPORTED_VERSION
path: string      // JSON Pointer-подобно: /rules/0/keywordIds
stage: "syntax" | "format"
```

Сообщение для UI — отдельно (текст исключения или `message` на объекте), не ключ сравнения в тестах. Тесты цепляются за `code` + `path` + `stage`.

`syntax` — не JSON. Остальное разбора — `format`.

### 7. Diff / UI / резолв

`diffAgainstPublished`, `remapForSpace`, диалоги, New space, `samePayload` с `keywordIds` — **не этот заход**, кроме: serialize не должен звать diff.

`RuleDiffService.samePayload` остаётся на внутренних id до шага 3 (там внешний payload). Не ломать публикацию в этом заходе.

### 8. Сортировка serialize

Детерминизм dump (тест snapshot порядка ключей массивов, не ключей объекта JSON):

- `rules` по `code` ASC;
- `keywordCodes` ASC;
- `keywords` по `code` ASC;
- `mechanics` по `code` ASC, затем `version` ASC;
- `sections` по `sortOrder` ASC, затем `code` ASC (как стабильный dump; не менять смысл дерева).

`exportedAt` в тесте serialize — фиксировать (clock inject) или не сравнивать целиком dump.

На шаге 1 serialize из `SpaceRevision<Rule>` + карт id→keyword/mechanic **ещё нет** (это шаг 2). Serialize шага 1 принимает **уже внешнюю** структуру (`RevisionFile` v3) или собирает из правил, у которых вместо ids уже `keywordCodes`/`mechanic`. Вход «сырой срез HTTP» — шаг 2.

Практически: внутренний тип `RevisionFile` заменить на v3; `serialize(revision: SpaceRevision<Rule>)` **ломать** — либо сузить до v3-сборки из уже внешних rules (тестовая фикстура), либо оставить overload только в тесте. Живой `SpaceDetailPage.exportRevision` на шаге 1: если ещё нет карт справочника — **не** выгружать v2 с id. Варианты: временно скрыть кнопку / кнопка падает с «нужен шаг 2» — плохо. Правило захода: **подключить экспорт к v3 только через заглушку карт из сторов, если они уже загружены**; иначе не писать keywordIds в файл. Если сторы пусты — ошибка экспорта, не fallback на id.

Минимум, чтобы не оставить прод с v2: serialize **отказывается** писать `keywordIds`. Резолв id→code, если карта есть; нет id в карте → ошибка `format`/`resolve` (stage `resolve` можно ввести уже здесь для экспорта, либо не экспортировать из UI до шага 2).

**Решение захода (узкое):** шаг 1 чинит parse/serialize **на v3-объекте** и тестах фикстуры. `SpaceDetailPage` / диалог: parse принимает только v3; экспорт UI **в этом же заходе** обязан не писать запрещённые ключи. Резолв через уже загруженные keyword/mechanic stores (модуль Rule). Нет в карте — throw с code, не id в JSON. Это чуть заходит на шаг 2, иначе кнопка Экспорт останется вредной. Полный набор used-keywords в блоке `keywords[]`, детерминизм, tombstone — довести; если стор не покрыл id — ошибка, не частичный файл.

### 9. Слои (Vue)

Всё в `Roleplay/RuleSpace`, кроме чтения публичных DTO Keyword/Mechanic из Rule (`init` / Dto / Interface). Rule ↛ внутренности RuleSpace. Не трогать `Roleplay/Space` (мёртвый дубль; вынос не этот заход).

| Тип | Задача |
|---|---|
| `Dto/RevisionFile.ts` | envelope v3 |
| `Dto/RuleExternal.ts` (или поля в RevisionFile) | внешнее правило |
| `Dto/RevisionFileProblem.ts` | code/path/stage |
| `Constant/REVISION_FILE_FORMAT.ts` | version 3 |
| `Service/RevisionFileService.ts` | parse/serialize, ошибки |
| тесты `revisionFileService.test.ts` | фикстура, reject id, v1/v2 |

Не класть файл-сервис в Rule: envelope и секции — мир.

### 10. Тесты

- не JSON → `syntax`;
- чужой `format` / version 1|2 / отсутствие `source` → `format`;
- круг serialize(parse(fixture)) равен по смысловым полям (без `exportedAt` или с inject);
- в дампе нет `id`/`spaceId`/`keywordIds`/`mechanicId`/`createdAt` у правил;
- ключ `keywordIds` на правиле → ошибка path `/rules/0/keywordIds`;
- `mechanic: null` и непустой payload → ошибка;
- mechanic в файле с ключом `handlerVersion` → ошибка (канон `version`);
- дубль code правил; пустой rules;
- extra ключ корня;
- `spec` с неизвестным полем сохраняется;
- `exportedAt` number.

Не phpunit. Не Character/Game фикстуры, кроме если сломается tsc из-за типа `RevisionFile`.

## Todo

- [x] **dto** — v3 envelope, RuleExternal, Problem.
- [x] **parse-serialize** — только version 3; unix; сортировка; инварианты mechanic.
- [x] **export-guard** — UI-экспорт не пишет внутренние id; резолв из keyword/mechanic store или ошибка.
- [x] **parse-ui** — диалог/New: ловить Problem, не только `Error.message`.
- [x] **tests** — фикстура + reject legacy.
- [x] **docs** — этот файл; роудмап шаг 1.

## Не входит

Шаг 3 apply/diff/`removeMissing`/секции-циклы. PHP mapper codes. HTTP файла. Создание keyword/mechanic. Смена `commitDraft`. `RuleDiffService` на codes. Вычищение `Roleplay/Space`. Spell/`RuleType` enum. Кап 500 — только ошибка «нет в карте», не новый API.

## Документы захода

этот файл; [`revision-file-roadmap.md`](revision-file-roadmap.md); [`rule-system.md`](rule-system.md); [`mechanic-plan-02.md`](mechanic-plan-02.md); [`keyword-plan-02.md`](keyword-plan-02.md).
