# План RuleSpace 10 — Vue: Space → RuleSpace

**Статус:** каркас Vue сделан, 2026-09-05. Нарезка — [`rule-roadmap.md`](rule-roadmap.md) шаг **10**. HTTP — [`rulespace-plan-04.md`](rulespace-plan-04.md), права — [`rulespace-plan-05.md`](rulespace-plan-05.md), секции — [`rulespace-plan-06.md`](rulespace-plan-06.md). Канон — [`rule-system.md`](rule-system.md), [`architecture.md`](architecture.md). Фронт — `draft-front_1.2ds/frontend-rules.md`. `DEC-079`.

Цель: продуктовый Vue-модуль мира правил живёт в **`Roleplay/RuleSpace`**, клиент бьёт в **`ruleSpace.*`**, JSON как PHP (unix, `ownerId`, `sections`). URL `/spaces` и `/space/:code/...` **не** менять. Rule по-прежнему **не** импортирует RuleSpace; RuleSpace только `provide` / `registerRevisionRulesFetcher`. Не PHP (кроме явного бага контракта). Не вынос Keyword/Mechanic из папки Rule. Не HTTP файла ревизии.

Набросок `Roleplay/RuleSpace` — эскиз UI; имена action `space.*` и ISO-даты — не канон.

## Термины

| Термин | Смысл |
|---|---|
| Модуль Vue | Папка `src/modules/Roleplay/RuleSpace/`. |
| JSON Space | Вид API мира, не Pinia и не PHP Record. |
| Черновик правил | Уже в Rule (`useRuleDrafts`). Не тащить секции туда. |
| Черновик секций | Только клиент (память / `localStorage` модуля RuleSpace). Нет action «сохранить секции». |
| Ключ права | `space.create` / `view_all` / `edit_all`. Не префикс action. |

## Решения

### 1. Папка и публичная точка

Переименовать `Roleplay/RuleSpace` → `Roleplay/RuleSpace`. Все `@/modules/Roleplay/RuleSpace/...` и тесты eslint (`filename` Space) — на новый путь.

Locator: `Roleplay.RuleSpace.Service.RuleSpaceApi`. Класс `RuleSpaceApi`, контракт `IRuleSpaceApi`. `registerRuleSpaceApi` / `getRuleSpaceApi` / `registerRuleSpaceModule`. Старых `getSpaceApi` / ключа `Roleplay.Space.*` не оставлять (один модуль — одно имя).

Продуктовые имена в UI и JSON можно оставить **Space** (`Space`, `useSpaceCatalog`, Pinia `spaces` / `spaceRevision`, крошки «Пространства»): это сущность HTTP, не имя PHP-модуля Versioning.

`createSpaceRoutes` остаётся именем фабрики роутов группы Roleplay или становится `createRuleSpaceRoutes` — без разницы для URL; предпочесть `createRuleSpaceRoutes`, вызов в `Roleplay/routes.ts`.

### 2. URL и гость

Пути **`/spaces`**, **`/spaces/new`**, **`/space/:code`**, **`/space/:code/:ctx`**, **`/space/:code/settings`** не менять. Это **редактор миров** (оператор), не витрина правил.

Гость в этот модуль **не ходит**. Публичное чтение среза мира, который открыт «для всех» (в т.ч. «Актуальные правила», код `actual`) — **другой, ещё не спроектированный раздел**: удобный просмотр по секциям, без create/draft/settings. Per-object права на мир (открыть конкретное пространство гостю/игроку) — **позже**; без них гостю в продукте почти нечего смотреть в операторе, и это нормально: гость нужен витрине, не `/spaces`.

Сейчас у списка **`/spaces`** стоит `guestAllowed: true` — ложь эскиза. Ветка **`/space/:code/...`** этого флага уже нет (гость на деталь/настройки не проходит router). PHP `getList` требует актора и без `view_all` отдаёт только свои. **Снять `guestAllowed`** с `/spaces` (и не ставить на `/space/:code`). Не открывать `get` / `getRevision` анониму «чтобы гость видел actual». Не расширять `view_all` до «все active любому актору» в этом заходе.

Character/Game с `guestAllowed` по-прежнему зовут каталог/срез мира через публичный init. В real это уже `AUTH_REQUIRED` / `AUTH_DENIED` без owner/`view_all`. **Не** чинить отсюда (не PHP для гостя, не витрина). Обновить только пути импорта.

Создание: роут `/spaces/new` уже `requiresAny: ['space.create']`. Кнопка «Создать» на списке сейчас без гейта — спрятать без ключа. Мета/deactivate/commitDraft: HTTP — **владелец или `edit_all`**. Эскиз `SpaceSettingsPage` гейтит deactivate только `space.edit_all` — **починить**: `ownerId === currentUser.id` или ключ (bypass как у User). Не требовать `edit_all` у владельца. UI не маскирует `AUTH_DENIED` под 404.

### 3. Actions

Только префикс **`ruleSpace.*`**. Методы клиента могут зваться `getSpaces` — это не имя action.

`commitDraft`: тело `{ spaceId, rules, removedCodes?, sections? }` как план 6. Базу ревизии не слать.

### 4. JSON ↔ Dto

Канон — PHP assembler, не старый Vue.

**Space:** добавить `ownerId`. `createdAt` / `publishedAt` — **unix number**, не ISO-строка (как Chat 4 / User). `revision === 0` — мира без ревизий.

**SpaceRevisionMeta:** нет `changedCount` (поле вычеркнуть из Dto и UI ленты, не заглушка 0).

**SpaceRevision:** `sections` всегда есть (в т.ч. `[]`). Не optional «старые ревизии без поля».

**Rule в срезе/commit** (тип в модуле **Rule**): канон провода = PHP. Замена `mechanic_payload` → `mechanicPayload`, `createdAt` unix number, поля `active` / `contentStatus`, без `updatedAt` на API. Это **не** только файлы RuleSpace: моки и фикстуры Rule/Character/Game, которые собирают `Rule`, надо привести. Не оставлять змею «для совместимости с эскизом» на `commitDraft`.

Вложенный `id` / `spaceId` на put по-прежнему игнорирует PHP. Не чинить бэкенд под snake_case.

`catalogRootFor` на секции: PHP — любая строка. Vue-enum `RuleCatalogArea` — корни редактора; неизвестная строка с сервера не падать (Dto: `string`, не закрытый union на проводе).

### 5. DAG и Character/Game

Rule **не** импортирует `Roleplay/RuleSpace` (ни Dto секций, ни Api). Срез правил — `registerRevisionRulesFetcher` из `registerRuleSpaceModule` (уже так: Space → Rule). Тесты Rule, которые тянут mock мира, импортируют **Mock** RuleSpace (фикстуры между модулями допустимы).

Character/Game → публичный `init` / `Dto` / `Enum` RuleSpace (сейчас Space). Прямой импорт `Dto/AbilitySection` после переноса пути — ок. `Store/` / `Service/` чужие — нет.

### 6. Черновик секций (клиент)

План 6: нет серверного PATCH. Черновик дерева — в RuleSpace (стор + `localStorage` по `spaceId`), не в `useRuleDrafts`.

Публикация:

- нет ключа `sections`, меняются rules/removed → шаринг latest;
- есть `sections` (в т.ч. `[]`) → явный снимок;
- пустые put+removed и дерево ≠ latest → catalog-only (HTTP keep);
- no-op → ошибка с сервера, показать (F17).

`PublishService` учитывает грязность каталога, не только diff правил.

Импорт/экспорт файла ревизии остаётся **локальным** UI/моком; HTTP файла — OPEN роудмапа, не этот заход.

### 7. Права

Категория админки **`space`** + три action — уже совпадает с PHP. Не переименовывать в `ruleSpace.*`. Свои миры без `*_all` — HTTP; UI списка показывает то, что вернул `getList`.

Не seed «Актуальные правила» и не ACL на один `space_id`. Когда появятся права на пространство, витрина (не этот модуль) будет читать открытый срез; оператор останется для владельца/`*_all`.

### 8. Моки

`mockSpaceApi` / `mockSpaces` переименовать к модулю (`mockRuleSpaceApi`). Контракт = `IRuleSpaceApi`. `VITE_API_MODE` real бьёт в PHP; mock остаётся in-memory для тестов Character/Game.

### 9. Quality

`frontend-rules.md`: один экспорт на файл, `@/`, unix, F17. Гейт: `format` → `lint` → `vue-tsc` → `test`. PHP не трогать, если контракт уже как планы 4–6.

Линтер `powerscale/no-foreign-module-internals`: путь модуля `Roleplay/RuleSpace`.

## Что не трогаем

- PHP RuleSpace / часы / Keyword HTTP (шаг 11).
- Вынос Vue Keyword/Mechanic (`CODE_GAP`).
- Смена URL на `/rulespace`.
- `changedCount`, per-object ACL, смена owner, seed секций, витрина правил для гостя, seed мира `actual`.
- Поле `spaceId` на identity `rule` в PHP.
- Vue-модуль Versioning.

## Риски эскиза (править клиент, не PHP)

1. Action `space.*` → `ruleSpace.*`.
2. ISO vs unix.
3. `changedCount` / нет `ownerId`.
4. `guestAllowed` на редакторе `/spaces` (снять); витрина гостя — не этот шаг.
5. `mechanic_payload` / ISO `createdAt` на `Rule` — волна фикстур Rule/Character/Game.
6. `sections` optional vs всегда list.

## Todo

- [x] **move** — папка, locator, `main.ts`, Roleplay `routes.ts`, Character/Game/Rule-тесты, eslint fixture.
- [x] **http** — `ruleSpace.*`, Dto unix/`ownerId`/без `changedCount`, Rule JSON среза.
- [x] **draft** — секции local; `commitDraft.sections`; catalog-only в PublishService.
- [x] **authz** — снять `guestAllowed` с `/spaces`; владелец правит без `*_all` (`ownerId`); кнопка create; `access.test` Spaces; ключи `space.*`.
- [x] **gates** — format/lint/tsc/test; канон architecture/TR/роудмап.

## Не входит

Keyword/Mechanic HTTP и Vue-сплит. Файл ревизии на сервер. PHP. Смена URL.

## Документы захода

этот файл; [`rule-roadmap.md`](rule-roadmap.md); [`rulespace-plan-04.md`](rulespace-plan-04.md); [`rulespace-plan-05.md`](rulespace-plan-05.md); [`rulespace-plan-06.md`](rulespace-plan-06.md); [`architecture.md`](architecture.md); `frontend-rules.md`.
