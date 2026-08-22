# Контекст ревью 16 — Roleplay/Rule (1-я волна)

> **Статус волны: НЕ ЗАВЕРШЕНА (зафиксировано 2026-08-05).** Волна оборвана на
> шаге 5: решены Пункты 1–5 (P2-1…P2-4 + внеплановая регрессия structuredClone).
> Открытые P2-5…P2-8, P3-1…P3-10 и шаги плана 6–9 (см. «План») **перенесены на
> следующую волну Roleplay/Rule** — здесь они остаются как находки, без решений.

Волна 2026-08-04. Первое ревью модуля `Roleplay/Rule` на `frontend-rules.md`
+ общее ревью качества. Режим: разбор по шагам (план внизу), правки — только
после явного решения пользователя. Базовый стандарт — context-11/12/14/15
(F17, Service/Instance, один-экспорт-на-файл, плагинная модель, hosting-контекст
Rule→Space через `useSpaceContext`, ревизии — домен Space).

## Итог ревью (находки)

### P1
Нет. Кандидаты (material-guard, stale-spec) не создают полной дыры/контрактного
разрыва — классифицированы как P2.

### P2
- **P2-1. Material-компонент: неверная модель «предмет-или-тег» одним полем.** `RuleValidationService.ts:171`
  — guard `!keywordCodes.has(item_code)` в `validateAbilityStructure` был костылём под семантику
  «материал может ссылаться на тег» (тег клался в `item_code`), что противоречило `collectSpecRefs`
  (`:436-441`, всегда тип `'item'`) и Dto. Углубление (домен от разработчика): действия и заклинания
  содержат по сути одни и те же требования/затраты; «компоненты заклинания» — лишь термин отображения.
  Введена сущность **ActionComponents** (Компоненты действий): траты ОД/ресурсов + вербальный/соматический/
  материальный. Материальный: цель = item XOR набор тегов; режим = consume XOR use. Решено на шаге 1
  (см. «Решения»).
- **P2-2. F17-дыры.** `RuleEditPage.save` (`:157-158`), `KeywordEditPage` save/delete
  (`:62-63,:74-75`) + onMounted `fetchTag` без try/catch (`:28-36`), `Store/keywords.ts`
  `fetchTags` (`:17-20`) + `KeywordsListPage` без error-UI (`:23`), `RuleSlider`
  перманентный спиннер (`:30-33,:63-65`).
- **P2-3. `ruleCatalogCache.ts`** — 2 экспорта (правило 23) + reactive `ref` в Service/
  (правило 51). TODO контекста ревизии (context-14) остаётся.
- **P2-4. `RULE_TYPE_LABELS` без `'source'`** — пустой чип у source-правил
  (`RuleDetailPage.vue:100`); дубль type→label с `expectedTypeLabel`.
- **P2-5. Файловые функции + инлайн-справочник в Service.** `RuleValidationService.ts`
  `:569-594` (`amountValue`/`hasActionPointCost`/`duplicateCodes`), `:26-38`
  (`expectedTypeLabel`) — правило 2/20/21/34.
- **P2-6. Именованные типы в файлах с кодом.** `DraftEntry` (`Store/draftRules.ts:6-9`),
  `RefExpectation` (`RuleValidationService.ts:17-20`) — правило 46.
- **P2-7. Stale-spec при смене `:ruleId` на том же роуте** — редакторы инициализируют
  черновик только в `onMounted` (`AbilityEditor:206-226`, `ItemEditor:128-143`,
  `RaceEditor:84-94`, ...); переход между edit-роутами не размонтирует компонент.
- **P2-8. `classifyDraftDiff` через `JSON.stringify`** (`RuleDiffService.ts:7-11`) —
  порядок ключей вложенного `spec` (спреды/prune меняют порядок) → риск ложных `changed`.

### P3
- P3-1. Дубликат вычислителей опций в `AbilityEditor.vue:91-134` vs `RuleReferenceService`.
- P3-2. `ItemSpecService.ensure*` мутируют входящий объект (`Spec/ItemSpecService.ts:14-51`).
- P3-3. Мёртвые `createRule/updateRule/deleteRule` (`Store/rules.ts:30-44`) — никто не зовёт.
- P3-4. `loading` в `Store/rules.ts:12` и `Store/keywords.ts:11` не потребляется.
- P3-5. Топ-уровневая уникальность `code` не валидируется (`byCode` тихо перезаписывает).
- P3-6. `getRules(0)` в `ruleCatalogCache.ts:14`/`init.ts:55`; каталог — снимок.
- P3-7. Декомпозиция: RuleEditPage 342, AbilityEditor 460, AbilityCard 303 и др. (~250).
- P3-8. Синк `limit` number↔dimensional в `GrantEditor.vue:45-59` — логика в компоненте.
- P3-9. `RuleDetailPage.vue:73-77` — 3 запроса на открытие без кэша.
- P3-10. Нет тестов: `RuleDraftService`, `RuleReferenceService`, кейс порядка ключей в diff.

## Сильные стороны
- Hosting-контекст чист: `useSpaceContext` только в RuleEditPage/RuleDetailPage;
  редакторы — `rules` пропом; импортов Space-сторов в Rule нет.
- Плагинная модель Chat (правило 29): контракт IRenderer/ITokenSource, lazy-компонент,
  хост не импортирует донора.
- Spec-сервисы: манифесты инъектируются из Constant/, синглтоны в Service/Instance.
- Моки: единый пул ruleCatalog, типизированы контрактами; Space-ревизии консистентны.
- Типизация: без any/as any в prod-коде, import type, string-union, тип на файл.
- F17 на страницах правил образцовый; ruleValidation.test.ts — 789 строк покрытия.

## Намеренно не блокировало
- Объём RuleValidationService (когезия, YAGNI); Vuetify `:rules`; `new Date()` в шаблоне;
  размеры Dto/Ability; `ruleToForm` как Utils-функция; `slugify` в Space-mock (искл. context-15).

## План (шаги)
1. **P2-1** — ActionComponents: единая сущность «Компоненты действий» (траты ОД/ресурсов + верб/сомат/матер), material = item XOR теги × consume XOR use; единый блок в AbilityEditor; сложность/длительность остаются у заклинаний.
2. **P2-2** — F17 keywords-флоу + RuleEditPage.save + RuleSlider.
3. **P2-3** — каталог в Store/ + один экспорт.
4. **P2-4** — `source` в RULE_TYPE_LABELS; убрать дубль label-справочника.
5. **P2-5/P2-6** — справочник в Constant/, функции → приватные методы, типы → Dto/.
6. **P2-7** — `:key="ruleId"` на редакторах в RuleEditPage.
7. **P2-8** — каноническое сравнение в RuleDiffService + тест.
8. **P3** — P3-1, P3-3/P3-4, P3-8, P3-10 (тесты RuleDraftService/RuleReferenceService).
9. **Верификация** — format → lint → vue-tsc → vitest.

> **Прогресс (2026-08-05):** выполнены шаги 1–5 (Пункты 1–5 в «Решения»). Шаги
> 6–9 (P2-7, P2-8, P3, верификация) — **перенесены на следующую волну Rule**,
> здесь не решались.

## Решения (заполняется по ходу)

### Пункт 1 — P2-1: ActionComponents (Компоненты действий). Решено: полная доменная модель.

Домен (от разработчика): действия и заклинания содержат по сути одни и те же требования/затраты;
«компоненты заклинания» — термин отображения (группировка не-ОД затрат в карточке). Термин «расходники»
неверен (ОД и расходуемые предметы расходуются, верб/сомат и используемые предметы — нет): корректно
«всё расходуемое и используемое для совершения действия» = **ActionComponents**. Сложность и длительность —
фишка только заклинаний. Материальный: цель = конкретный item XOR набор тегов; режим = consume XOR use;
пустой материал (ни то, ни другое) — ошибка структуры (покрыто XOR).

Модель данных:
- `Enum/Ability/MaterialMode.ts` — `type MaterialMode = 'consume' | 'use'`.
- `Dto/Ability/ActionComponent.ts` — юнион: `({type:'cost'} & ActionCost) | {type:'verbal';note?} |
  {type:'somatic';note?} | {type:'material'; mode; item_code?; keyword_codes?; description?}`.
- `AbilitySpec`/`AbilitySpecDraft`: `action_costs` → `action_components` (варианты action/spell).
- `SpellSpec`: только `difficulty`/`duration`; `SpellComponent.ts` удалён.
- `ABILITY_SPEC_FIELDS`: `action_costs` → `action_components`.

Правки:
- `AbilitySpecService`: `createEmptyActionComponent`, `ensureActionPointCost`, апдейтеры
  `add/update/patch/removeActionComponent` (работают с массивом; ОД-компонент защищён от удаления).
- `RuleValidationService`: `collectSpecRefs` — cost→resource, material item→item, keyword_codes→keyword;
  `validateAbilityStructure` — ОД по cost-компонентам, material-XOR (оба/ни одно → ошибка «должен указывать
  предмет или набор тегов») + существование item; сложность заклинания остаётся.
- Редакторы: новый `ActionComponentsEditor.vue` (единый блок: cost/верб/сомат/матер, режим и способ
  указания), удалён `ActionCostsEditor.vue`; `SpellEditor` — только сложность/длительность;
  `AbilityEditor` — одна панель «Компоненты действия».
- `AbilityCard`: карточка «Действие» — cost-компоненты; карточка «Заклинание» — сложность/длительность +
  «Компоненты»; label материального с режимом (израсходовать/использовать) и тегами.
- Mock: rule-4/rule-34 мигрированы; fire-bolt получил демо-материал `{type:'material', mode:'consume',
  keyword_codes:['crafting']}`.
- Тесты: миграция action_costs → action_components; новые кейсы — теги-ветка (валид/отсутствующий тег),
  XOR (ни одного/оба), режимы consume/use, коллизия item_code↔keyword (осталась).

Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, vitest 26 файлов / 253 теста (было 249; +4).

### Пункт 2 — P2-2 (F17 keywords-флоу + RuleEditPage.save + RuleSlider). Решено: вариант A (эталон Notifications/Space, context-11/15).

- `Store/keywords.ts`: поле `error`; `fetchTags` чистит в начале, в catch (кроме AbortError)
  ставит `'Не удалось загрузить признаки'` (вместо console.error). `loading` уже потреблялся.
- `KeywordsListPage.vue`: `v-alert type="error"` + «Повторить» → `store.fetchTags(signal)`.
- `KeywordEditPage.vue`: полный F17 на начальной загрузке — extract `loadTag()` (loading +
  `loadError` + «Попробовать снова»), гейтинг формы по loading/loadError; save → локальный
  `saveError` (closable), delete → `actionError` (closable); AbortError не выводится.
- `RuleEditPage.vue`: `save` — `console.error` → локальный `saveError` (closable),
  `'Не удалось сохранить черновик'` (синхронная операция, ветка defensive, но F17-консистентно).
- `RuleSlider.vue`: extract `loadRule(id)` + `error`-состояние; при старте загрузки сброс
  error и ruleData (согласовано с RuleDetailPage); шаблон: контент / alert с «Попробовать
  снова» / спиннер. Устранён перманентный спиннер при ошибке.
- Тесты: `__tests__/Store/keywords.test.ts` (4 кейса: успех, провал→error, ретрай чистит,
  AbortError тихий); паттерн chat.store.test.ts (registerKeywordApi + serviceLocator.reset).

Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, vitest 27 файлов / 257 тестов
(было 26/253; +1 файл keywords.test.ts, +4).

### Пункт 3 — Регрессия structuredClone-на-proxy + ренейм cost→resource (вне плана).

**Баг (воспроизведение: создать правило → способность → действие):**
`Failed to execute 'structuredClone' on 'Window': #<Object> could not be cloned`.
`structuredClone` падает на Vue-реактивных proxy (подтверждено экспериментом на установленном
vue: reactive/ref/спреды из них не клонируются). `AbilityEditor.specToEmit`/`prune` копируют
вложенные поля `innerSpec.value` (reactive) по ссылке как proxy → эмит-`structuredClone` падает.
Тот же класс — во всех spec-редакторах (Race/Species/Item/Weapon/Armor/Shield/Process/
RequirementList/Spell) и `useVModelSync` (clone:true).

Фикс (вариант A, системно):
- `Core/UI/Utils/cloneData.ts` (новый): `structuredClone(unwrap(value))`, `unwrap` — рекурсивный
  `toRaw` по объектам/массивам + `isRef`-ветка. Иммутабельно, только собственные ключи.
- `useVModelSync.ts` и все 11 редакторов Rule: `structuredClone` → `cloneData`.
- `structuredClone` остался только внутри `cloneData.ts`.

**Ренейм (решение пользователя):** `type: 'cost'` → `type: 'resource'` (по сути «трата ресурса»,
`resource_code` + `amount`; устраняет коллизию «Стоимость»/«Стоимость» тип↔поле). Полный ренейм
по 9 файлам (ActionComponent, AbilitySpecService, RuleValidationService, mockRules,
ruleValidation.test, AbilityEditor, ActionComponentsEditor, AbilityCard) + лейбл «Ресурс».
`ActionCost` (payload) не переименован — общий для ProcessStep.costs. TR.md актуализирован
(action_costs→action_components + ресурсные компоненты; запись 30.50).

Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, vitest 27 файлов / 257 тестов.

### Пункт 4 — P2-3: каталог правил в Store/ + один экспорт. Решено: вариант A (eager сохранён, pinia раньше в main.ts).

Нюанс (обнаружен при разборе): `registerRuleModule()` в `main.ts` вызывался до `app.use(createPinia())`,
поэтому eager-вызов стора прямо в регистрации бросал бы «no active Pinia». Решение: перенести
`createApp` + `app.use(createPinia())` до `register*Module` в `bootstrap` (чистый порядок инициализации;
register-функции только заполняют реестры, ни одна из 8 стор на этом этапе не использует).

- `Service/ruleCatalogCache.ts` удалён (правила 23, 51). TODO про контекст ревизии чата (context-14)
  перенесён дословно в новый стор.
- `Store/ruleCatalog.ts` (новый): единственный экспорт `useRuleCatalogStore` — `rules` ref +
  `ensureLoaded()` (ленивый `import { getRuleApi }` из init, guard по `rules.length`) + `findRule(code)`.
- `RuleChip.vue`: `computed` через `findRule` + `watch` → `ensureLoaded` (flow спиннер/«Объект скрыт» тот же).
- `Rule/init.ts`: `describe`-колбэк лениво через `useRuleCatalogStore().findRule(code)` (прецедент
  `Chat/init.ts` describe для 'user'); eager-прелоад — `useRuleCatalogStore().ensureLoaded().catch(...)`.
  `registerTokenSource.search` не тронут (работает через `getRuleApi().getRules(0)` напрямую).
- `main.ts`: pinia устанавливается до `register*Module`.
- Тест: `__tests__/Store/ruleCatalog.test.ts` (3 кейса: загрузка+findRule, повторный ensureLoaded не
  фетчит повторно — guard, ошибка API пробрасывается); паттерн keywords.test.ts (inline-mock IRuleApi +
  serviceLocator.reset + setActivePinia).

Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, vitest 28 файлов / 260 тестов
(было 27/257; +1 файл ruleCatalog.test.ts, +3).

### Пункт 5 — P2-4: `source` в RULE_TYPE_LABELS + устранение дубля label-справочника. Решено: вариант 1a (капител).

Уточнение: «Отсутствие таба Источник» — баг, а не побочный эффект: `Space/RuleListPanel.vue`
строит табы как `Object.keys(RULE_TYPE_LABELS)`, поэтому добавление `source` чинит одновременно
пустой чип у source-правил, отсутствующий таб и отсутствующий ярлык.

- `Constant/RULE_TYPE_LABELS.ts`: добавлен `source: 'Источник'`; тип сужен до `Record<RuleType, string>`
  (компилятор обязывает закрыть все RuleType — защита от повторения класса бага «недостающий ключ»).
  Следствие сужения: `RuleListPanel.vue` — `Object.keys` даёт `string[]`, индекс по `Record<RuleType,string>`
  падал (TS7053); `tabs` типизирован `computed<RuleType[]>(() => Object.keys(RULE_TYPE_LABELS) as RuleType[])`.
- `Service/RuleValidationService.ts`: `expectedTypeLabel` — инлайн-дубль `labels` убран; реализация
  через `RULE_TYPE_LABELS` + ветка `keyword → 'Признак'` (капител в сообщении «нужен тип «Раса»/«Источник»/«Признак»»;
  регистр меняется с нижнего на капител — тестами на строки не залочено). Файловые функции `:569-594` не тронуты (P2-5).
- `RULE_TYPES.ts` (селектор создаваемых типов) не тронут — вне scope P2-4.

Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, vitest 28 файлов / 260 тестов.

