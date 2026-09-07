# План Rule 2 — поверхность Vue `Rule/init`

**Статус:** сделано, 2026-09-07. Нарезка — [`rule-roadmap.md`](rule-roadmap.md) «Позже» (не шаги 1–12). Сплит справочников закрыт ([`vue-rule-split-plan-01.md`](vue-rule-split-plan-01.md), [`keyword-plan-03.md`](keyword-plan-03.md), [`mechanic-plan-03.md`](mechanic-plan-03.md)). Канон рёбер — [`architecture.md`](architecture.md). Фронт — `draft-front_1.2ds/frontend-rules.md`. `DEC-064`, `DEC-065`; в заходе — `DEC-082`.

Цель: Character/Game по-прежнему зависят от Rule (спека и её интерпретатор — хозяин Rule). Узкое место — **баррель `Rule/init.ts`**: константы, value-класс, синглтоны, мёртвый Vue-экспорт и `import 'vue'` на верхнем уровне. Не PHP. Не HTTP `rule.*`. Не перенос интерпретации в Character/Game. Не модуль `RuleRuntime`. Не открывать `Service/` / `Utils/` / `Component/` целиком.

Эталон узкого `init` — Keyword (`get*` / `register*` / `useKeywords`) и Mechanic (фасады + явный перечень Engine). Rule шире Keyword: он продаёт рантайм спек. Это не долг сплита, а отдельный заход.

## Термины

| Термин | Смысл |
|---|---|
| Поверхность | Что чужой модуль имеет право импортировать. Сейчас: `init` / `Dto` / `Interface` / `Enum` / `Mock` / `routes`. |
| Баррель | `export` всего подряд из `init`, в т.ч. `export *` констант. |
| Интерпретатор спек | Синглтоны и классы: проверка, модификаторы предмета, раса, подписи эффектов и т.д. Хозяин — Rule. |
| Secure import | Коды/value **не** через `init`. Импорт интерпретатора из `init` не тянет **SFC/CSS**. Vue runtime в `init` остаётся из-за композабл-фасадов (`useRuleDrafts` → Pinia/`vue`). |

## Решения

### 1. Constant и Value — публичный слой, как Dto (`DEC-082`)

Eslint `PUBLIC_DIRS` += `Constant`, `Value`. Для **всех** прикладных модулей, не исключение Rule.

Чужой модуль импортирует константу/value **прямым путём**:

```text
@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES
@/modules/Roleplay/Rule/Value/CharacteristicNumber
```

не через `Rule/init`.

`DEC-064` в части «не Constant» **сужается**: Constant и Value — контракт данных (коды, диапазоны, value-класс), не внутренность. По-прежнему закрыты: `Service/`, `Component/`, `Store/`, `Page/`, `Composables/`, `Utils/`. `useXxxStore` из `init` не реэкспортировать.

`frontend-rules.md` § межмодульный доступ и `architecture.md` § Поверхность / «Публично =» — тем же заходом. В architecture дописать и то, что eslint уже считает публичным: `Mock`, `routes` (сейчас канон их пропускает). Тест `no-foreign-module-internals`: valid Game→Rule Constant и Game→Rule Value; **инвертировать** уже существующий invalid-кейс Character→`Rule/Value/CharacteristicNumber`; invalid по-прежнему Store/Service и статический `.vue`.

Папка `Constant/` целиком: вместе с кодами проверок наружу уйдут grid-манифесты, ключи storage, категории прав (`User/Constant/Grid`, `Chat/Constant/Chat` backoff, `Rule/Constant/Item/...`). Линтер не отличит «контракт» от «макета таблицы». Принимаем: DAG по-прежнему режет незаконные рёбра (Keyword ↛ Game); легальное ребро сможет импортировать чужой grid. Не плодить вторую папку констант в этом заходе.

Не открывать `Utils/`. Три функции, которые сейчас реэкспортирует `init` (`slugify`, `parameterLimitName`, `resourceShortName`), остаются **именованными** реэкспортами `init` (явный список, не `export *`). В класс не сворачивать в этом заходе.

Линтер **уже** ходит в `ImportExpression`: динамический `import('.../Component/X.vue')` сейчас **запрещён**. Исключение §4 — правка правила, не «и так можно».

### 2. Что остаётся в `Rule/init`

Только фасады и **закрытый перечень** интерпретатора. Новый синглтон наружу — осознанная строка в этом списке, не «ещё `export *`».

Фасады:

- `registerRuleApi` / `getRuleApi`
- `registerRevisionRulesFetcher` / `getRevisionRulesFetcher`
- `registerRuleModule`
- `useRuleHostContext` / `useRuleDrafts` (узкие композабл-фасады; не стор)

Интерпретатор (имена как сейчас, без переноса логики):

- `ruleValidationService`, `ruleDiffService`
- `itemModifierService`
- `checkResolutionService`, `checkLaunchService`, `checkSuccessRatingService`
- `damageTypeSpecService`, `derivedCharacteristicService`
- `aggregateSourceDeltasService`, `advantageDropService`
- `formatStateEffectsService`, `raceSpecService`, `RaceSpecService`
- `ruleReferenceService`, `actionEffectLabelService`, `movementDistanceExpressionService`
- `slugify`, `parameterLimitName`, `resourceShortName`

С `init` **снять**:

- все `export` / `export *` из `Constant/` и `Value/` (потребители — прямой путь);
- `export type { ProblemEntry }` / `IRuleHostContext` / `IRevisionRulesFetcher` (уже `Dto/` / `Interface/`);
- `export const AbilityCard` — снаружи никто не импортирует; свой `RuleSpecView` берёт карточку внутренним путём;
- `export const RuleSlider`.

Типы соседи берут из `Dto/` / `Interface/`, не баррель. `ruleHostContextKey` — `Constant/`, прямой путь.

`Character/Constant/CHARACTERISTIC_BASE_RANGE.ts` реэкспортирует диапазон из Rule `init` — перевести на `Rule/Value/CharacteristicNumber`.

### 3. SFC не на верхнем уровне `init`

Сейчас `init.ts` статически импортирует `defineAsyncComponent` из `vue` и объявляет `AbilityCard` / `RuleSlider`. Комментарий «async — init не тянет Vue в node-тестах» **неверен**: пакет `vue` уже грузится; не грузится только сам `.vue` до вызова фабрики. Vitest броска уже зелёный на этом графе — цель захода не «починить падающие тесты», а убрать SFC с точки сборки и перестать гонять коды через баррель.

`useRuleDrafts` / `useRuleHostContext` остаются реэкспортом `init` (фасады). Они импортируют `vue` и Pinia. Значит **`import { checkResolutionService } from Rule/init` по-прежнему поднимает `vue`**. Не обещать «init без vue». Обещать: нет top-level `defineAsyncComponent` и нет `.vue` в модульном графе `init.ts`.

Чат-плагин (`RuleChip`, token source) нельзя оставлять в `init.ts` рядом с синглтонами, если там `import { defineAsyncComponent } from 'vue'` — это снова SFC-точка. Вынести в `Service/Instance/ruleChatPlugins.ts` (один экспорт: `registerRuleChatPlugins`).

Сейчас `registerRuleModule()` **синхронный** и в `bootstrap` зовётся **до** `app.use(router)` / `mount`: чип `type: 'rule'` уже в реестре к первому кадру. Гонки нет. Зато любой `import` из `Rule/init` статически тянет Chat (`registerInlineRenderer`) и `vue` (`defineAsyncComponent`).

`registerRuleModule` **синхронно** вызывает `registerRuleChatPlugins()` через **статический** импорт Instance-файла. Тогда `init.ts` снова тянет vue/Chat — тот же граф.

Чтобы `init.ts` не импортировал vue/SFC/Chat статически, чат-плагин только через `await import()`. Тогда `registerRuleModule` становится `async`, и `main.ts` **ждёт** его **до** `createApp` / `app.use(router)`:

```text
await registerRuleModule()   # внутри: await import(ruleChatPlugins); register...
createApp / use(router) / mount
```

`void import().then()` без await — **новая** гонка (сегодня её нет). Не делать `registerRuleModule` async без `await` в `main.ts`.

### 4. `RuleSlider` без ребра Rule → Character

Плагин «Character-хост, Rule-донор» **нельзя**: `register*` на Character из `registerRuleModule` даёт **Rule → Character**. Канон: Rule ↛ Character.

Слайдер — продукт Rule. Character (лист, бой, редактор) уже зависит от Rule.

После снятия экспорта из `init` страницы Character берут слайдер так:

```ts
const RuleSlider = defineAsyncComponent(
  () => import('@/modules/Roleplay/Rule/Component/RuleSlider.vue'),
);
```

Eslint: разрешить **только** динамический `import()` / аргумент `defineAsyncComponent` на чужой путь `Component/**/*.vue`. Статический `import Foo from '.../Component/Foo.vue'` и любой импорт `.ts` из чужого `Component/` — по-прежнему ошибка.

Свой модуль по-прежнему импортирует свои SFC напрямую (`RuleChip`, `RuleDetailPage`).

Потребители сейчас: `CharacterSheetEditor`, `CharacterDetailPage`, `CharacterCombatSheet`. Свой `RuleChip` / `RuleDetailPage` не трогать.

### 5. Что не меняем в интерпретаторе

Сервисы спек **не** переезжают в Character/Game. Locator на каждый калькулятор **не** заводим. `Service/` Rule **не** открываем.

`ROLL_EVENTS` остаётся в Mechanic `init` (уже узкий перечень). Этот заход не обязывает Mechanic дублировать Constant-публичность, но после `DEC-082` Game *может* импортировать `Mechanic/Constant/ROLL_EVENTS` напрямую; менять импорты Mechanic не обязательно.

### 6. Потребители

Grep `from '@/modules/Roleplay/Rule/init'` в Character / Game / RuleSpace / тесты: что есть в §1 — прямой Constant/Value; что в перечне §2 — `init`; типы — Dto/Interface.

Не копировать полный список путей в план — источник: grep.

RuleSpace: `RULE_TYPE_LABELS`, `RULE_CONTENT_STATUSES`, `slugify`, `useRuleDrafts`, `ruleValidationService` / `ruleDiffService` — labels/statuses → Constant; slugify/сервисы/композаблы → `init`.

Game хендлер преимущества: `aggregateSourceDeltasService` / `advantageDropService` остаются с `Rule/init` (сервисы). `ADVANTAGE_SOURCE_*` — Constant.

## Docs этого захода

- `architecture.md`: поверхность += Constant, Value; «Публично =» у рёбер; не CODE_GAP.
- `frontend-rules.md`: тот же абзац межмодульного доступа; динамический `.vue` как исключение.
- `decisions.md`: `DEC-082` — Constant и Value публичны как Dto; `DEC-064` в части запрета Constant уточнён, не отменён целиком.
- `TR.md` — индекс уже ссылается; **поправить строку** «без Vue на верхнем уровне» (ложь: композаблы в `init` остаются). `rule-roadmap.md` / `vue-rule-split-plan-01.md` — не дублировать.

Не phpunit. Не смена URL. Не PHP Engine.

## Тесты и гейт

- Eslint internals: valid Constant/Value (в т.ч. инвертировать нынешний invalid Value); valid динамический `import('.../Component/X.vue')`; invalid Store, Service, Utils, **статический** `import ... from '.../Component/X.vue'`. Текст `messages.foreignInternals` — тот же перечень, что PUBLIC_DIRS.
- Vitest: существующие roll/character тесты зелёные. Не заводить отдельный тест «vue не загрузился» — композаблы в `init` грузят vue. Регрессия SFC: в `init.ts` нет `.vue` и нет `defineAsyncComponent`.
- Гейт: в `draft-front_1.2ds` — `npm run format` → `npm run lint` → `npx vue-tsc --noEmit` → `npm run test`. Dev-сервер не запускать.

## Порядок работ (один заход)

1. `DEC-082` + architecture + frontend-rules + eslint `PUBLIC_DIRS` + исключение динамического `.vue`.
2. Снять с `Rule/init` Constant/Value/типы/AbilityCard/RuleSlider; чат-плагин в Instance; `await registerRuleModule()` в `main.ts`.
3. Переписать потребителей (grep `Rule/init` и `CHARACTERISTIC_BASE_RANGE`).
4. Character: три места `RuleSlider` через `defineAsyncComponent` + динамический `import()` `.vue`.
5. Тесты eslint + полный гейт.
6. Docs: DEC-082, architecture (+ Mock/routes как уже есть в eslint), frontend-rules, одна строка в `TR.md`.

## Что не входит

- PHP Engine / hydrator spec / HTTP `rule.*`.
- Перенос `AdvantageDropService` в Mechanic.
- Открыть `Utils/` или свернуть три функции в сервис.
- Плагин RuleSlider на Character (ребро Rule → Character).
- Сужение публички Mechanic/Keyword сверх уже сделанного.
- Модуль `Reference`.

## Риски

1. Открыть Constant у всех модулей — с кодами наружу уходят grid/права/storage. DAG держит рёбра; легальный сосед *может* импортировать чужой grid. Не Store.
2. Обещать «init без vue», оставив `useRuleDrafts` в том же файле — ложь: композабл тянет vue/Pinia.
3. Статический импорт `.vue` из Character «раз уж eslint ослабили» — исключение только `import()`.
4. Вынести `checkResolutionService` в Constant «для симметрии» — это поведение, не справочник.
5. Реэкспорт Constant из `init` «для совместимости» — баррель не закрыт.
6. `void import(chatPlugins)` без `await` в `main.ts` до router/mount — чип правила не зарегистрирован к первому кадру.
7. Считать vitest броска сегодня падающим из‑за Vue — не так; не чинить несуществующий красный тест.

## Todo

- [x] **canon** — DEC-082, architecture, frontend-rules, eslint PUBLIC_DIRS + dynamic `.vue`.
- [x] **init** — перечень §2; без SFC/`defineAsyncComponent` в `init.ts`; `await registerRuleModule` в `main.ts`.
- [x] **consumers** — Constant/Value прямым путём; RuleSlider через динамический `import()` `.vue`.
- [x] **tests** — internals + гейт фронта.
- [x] **docs** — DEC-082 + architecture + frontend-rules + строка в `TR.md`.
