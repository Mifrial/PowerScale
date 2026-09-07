# План — Vue: Keyword и Mechanic из папки Rule

**Статус:** план, 2026-09-06. Нарезка — [`rule-roadmap.md`](rule-roadmap.md) блок «Позже» (`CODE_GAP`). Эталон переезда папки — [`rulespace-plan-10.md`](rulespace-plan-10.md). HTTP справочников уже есть: [`keyword-plan-02.md`](keyword-plan-02.md), [`mechanic-plan-02.md`](mechanic-plan-02.md). Канон рёбер — [`architecture.md`](architecture.md). Фронт — `draft-front_1.2ds/frontend-rules.md`. `DEC-062`, `DEC-064`.

Цель: закрыть `CODE_GAP` дерева Vue, чтобы **новый** код справочника и Engine не оседали в `Roleplay/Rule`. Не PHP. Не HTTP правил. Не смена URL `/admin/keywords` и `/admin/mechanics`. Не seed.

Откладывать нельзя: админка механик уже в `Rule/routes.ts` + `MECHANICS_ADMIN_SECTION`; файл ревизии уже протаскивает `Keyword[]` / `Mechanic[]` через RuleSpace; `Rule/init.ts` реэкспортирует Engine, хендлеры и `useKeywords`. Каждый следующий заход Character/Game только утолщает этот ком.

## Термины

| Термин | Смысл |
|---|---|
| Справочник Keyword | CRUD + HTTP `keyword.*` + UI «Признаки». Не `keywordIds` на правиле и не коды в spec. |
| Справочник Mechanic | Строка каталога (`id`, `code`, `version`, …) + HTTP `mechanic.*` + UI «Механики». |
| Binding | Узкий срез для Engine: `code` правила, `mechanicId`, `mechanicPayload`. Не весь `Rule`. |
| Донор хендлера | Модуль, который **регистрирует** хендлер в реестр Mechanic; сам Engine хендлеры не импортирует. |

## Решения

### 1. Две папки, не «Reference»

`src/modules/Roleplay/Keyword/` и `src/modules/Roleplay/Mechanic/`. Одна свалка справочников запрещена: у них разный DAG (Keyword → User; Mechanic ↛ Rule) и разный груз (CRUD vs Engine).

### 2. Порядок заходов

1. **Keyword** — [`keyword-plan-03.md`](keyword-plan-03.md).
2. **Mechanic: каталог + админка + Engine** сразу. Оставить Engine в Rule = продолжить рост, из‑за которого сплит «потом никогда».

Не параллелить в одном PR без нужды: eslint `filename` и `init` проще чинить по очереди.

### 3. Keyword — граница

Переезжает: `IKeywordApi`, `KeywordApi`, mock, Dto Keyword/Create/Update, стор, `useKeywords`, страницы списка/формы, grid columns/filters, `KEYWORD_PERMISSION_CATEGORY`, `KEYWORDS_ADMIN_SECTION`, тесты стора.

Locator: `Roleplay.Keyword.Service.KeywordApi`. Фасады `registerKeywordApi` / `getKeywordApi` / `registerKeywordModule` только в `Keyword/init.ts`. `main.ts` зовёт их оттуда, не из Rule.

`registerKeywordModule`: `registerPermissionCategory` + `registerAdminSection`. Rule больше не регистрирует категорию/секцию признаков.

`Rule.keywordIds` и валидация ссылок **остаются в Rule**. Rule → Keyword (публично): `getKeywordApi()`, тип `Keyword`.

Character/Game уже читают каталог (`useKeywords`, тип `Keyword`). Канон дополнить: **Character → Keyword**, **Game → Keyword** (публично). Keyword по‑прежнему ↛ Character/Game/Rule.

`useKeywords` живёт в Keyword, не реэкспорт из Rule.

URL `/admin/keywords` без смены. Заход Keyword: в `Roleplay/routes.ts` склеить `adminChildren` Keyword + оставшийся Rule (`mechanics`). Имя `tagAdminChildren` убрать. RuleSpace тоже → Keyword (`getKeywordApi` файла ревизии) — см. план 3.

### 4. Mechanic — граница и DAG

Переезжает: `IMechanicApi` + клиент + mock, Dto каталога и payload, страницы `/admin/mechanics`, permissions + `MECHANICS_ADMIN_SECTION`, `MechanicEngine`, registry, `MechanicHandler`, `ResolvedMechanic`, `MechanicState` / `CharacterMechanicContext`, `ROLL_EVENTS`, хендлеры, которые **не** импортируют сервисы Rule.

Locator: `Roleplay.Mechanic.Service.MechanicApi`. `registerMechanicApi` / `getMechanicApi` / `registerMechanicModule` / `mechanicEngine` — только Mechanic `init`.

Vue Mechanic → Engine, UI, **User (плагин админки)**. Строка architecture «Mechanic ↛ User» устарела: секция и `/admin/mechanics` уже в дереве.

**Vue Mechanic ↛ Rule** (как PHP). Иначе папка просто переедет вместе с комком.

Следствия:

- `MechanicEngine.resolveActive` принимает `Mechanic[]` + `Binding[]` (или эквивалент), **не** `Rule[]`. Кто имеет `Rule[]` (Rule-страницы, Character, Game) мапит сам.
- `Rule.mechanicId` / `mechanicPayload` остаются на DTO правила; тип payload импортируется из Mechanic (Rule → Mechanic).
- Character/Game → Mechanic **сейчас**, не «Позже»: они уже зовут `getMechanicApi` и `mechanicEngine`. Реэкспорта этих фасадов из Rule не оставлять — иначе `CODE_GAP` не закрыт.

Хендлеры:

| Хендлер | Дом |
|---|---|
| `purchase_surcharge` | Mechanic (контекст без Rule) |
| `six_one` / `critical_strike` + `rollScoreAdjust` | Mechanic, если контекст броска не тянет Rule; иначе донор Game |
| `movement_state` | Game (контекст действия игры) |
| `advantage_disadvantage` | **Rule** (сейчас импортирует `aggregateSourceDeltasService` / `advantageDropService`) |

Реестр наполняют `registerMechanicModule` (свои) и доноры из своего `register*Module`. Engine не импортирует доноров.

`RollMechanicContext` / `AdvantageModifier`: если контекст ссылается на Dto Rule — он не в Mechanic. Не размазывать `AdvantageModifier` копией.

### 5. Что остаётся в Rule

Спеки, редакторы, `IRuleApi` / эскиз HTTP, `useRuleDrafts`, чат-плагин `type: 'rule'`, валидация каталога, `ruleValidationService` и прочие spec-сервисы, `AbilityCard` / `RuleSlider`.

`Rule/init.ts` перестаёт быть баррелем Keyword/Mechanic/Engine. Толщину реэкспорта спек для Character/Game **не** резать в этом заходе (отдельный долг публички Rule).

### 6. Architecture / roadmap

Документы правятся **по заходу**, не оба сразу:

- Keyword (план 3): папка Keyword без CODE_GAP; Mechanic ещё в Rule; рёбра Character / Game / RuleSpace → Keyword.
- Mechanic (следующий план): снять остаток CODE_GAP; Character/Game → Mechanic (Vue Engine; PHP-хендлеры OPEN); Mechanic → User на Vue; пункт roadmap «Вынос Vue…» закрыть после **обоих**.

### 7. Тесты

Только фронт: eslint `no-foreign-module-internals` (пути модулей, отдельного filename-правила нет); vitest затронутого захода. Не phpunit. Не mount Vuetify.

Гейт захода: `npm run format` → lint → `vue-tsc` → `npm run test` в `draft-front_1.2ds`.

## Что не входит

- PHP, HTTP `rule.*`, seed справочников.
- Вынос `/admin/keywords` и `/admin/mechanics` из оболочки User в «оператор контента».
- Реализация Engine на PHP.
- Сужение публички Rule (спеки, константы check/state) сверх снятия Keyword/Mechanic.
- Модуль `Reference`.

## Риски

1. `MechanicEngine(rules: Rule[])` — главная дыра Mechanic ↛ Rule; чинить контрактом Binding, не `as`.
2. `rollAdvantageHandler` оставить донором Rule, не тащить spec-сервисы в Mechanic.
3. RuleSpace `assemble(..., keywords, mechanics)` импортирует Dto из Keyword/Mechanic, не из Rule.
4. Старый `Roleplay/Space` (если ещё в дереве) не реанимировать; admin-склейка только Keyword + Mechanic + User.

## Todo

- [x] **keyword** — [`keyword-plan-03.md`](keyword-plan-03.md).
- [ ] **mechanic** — папка каталога + Engine, Binding, доноры хендлеров, `/admin/mechanics`, DAG в `architecture.md`.
- [ ] **rule-init** — без `getKeywordApi` / `getMechanicApi` / `mechanicEngine` / admin-секций справочников.
- [ ] **gates** — eslint internals/filename; format/lint/tsc/test фронта.
- [ ] **docs** — roadmap «Позже» Vue-сплит закрыт; `CODE_GAP` снят.
