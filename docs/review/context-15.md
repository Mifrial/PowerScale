# Контекст ревью 15 — Roleplay/Space (1-я волна)

Волна 2026-08-04. Первое ревью модуля `Roleplay/Space` на `frontend-rules.md`
+ общее ревью качества. Режим: разбор по шагам (план внизу), правки — только
после явного решения пользователя. Базовый стандарт — context-11/12/14
(F17-паттерн ошибок/повторов, Service/Instance, один-экспорт-на-файл, плагинная
модель роутов).

## Итог ревью (находки)

### P1
- **P1-1. После publish пустеет селектор версий.** `Store/spaceRevision.ts:105-112`
  (commitDraft) + `Page/SpaceDetailPage.vue:42-53,159-163`: `revisionsMeta` удаляется
  и не перечитывается; селектор `v-select` без items при модели revision N.

### P2
- **P2-1. Нет `__tests__/`** при критичной логике ревизий (правило 5/F8).
- **P2-2. F17 `fetchSpaces`** — `Store/spaces.ts:17-19` только console.error; `SpacesPage`
  без error-UI/повтора.
- **P2-3. F17 `SpaceNewPage.save`** — `SpaceNewPage.vue:39-40` только console.error.
- **P2-4. F17 `SpaceSettingsPage.save/deactivate`** — `SpaceSettingsPage.vue:48-49,61-62`.
- **P2-5. `RULE_TYPE_LABELS` через чужой `Constant/`** (правило 28) —
  `SpaceDetailPage.vue:8,261`, `PublishDialog.vue:10,122,134`. Фикс: реэкспорт через `Rule/init`.
- **P2-6. `RevisionKind` в `Dto/`** вместо `Enum/` (правило 46; эталон `Notifications/Enum/NotifFilter.ts`).
- **P2-7. Валидационная композиция в `.vue`** (правило 2) — `PublishDialog.vue:47-83` `prepare()`.
  Фикс: `Space/Service/PublishService.prepareSummary(...)`.
- **P2-8. Декомпозиция `SpaceDetailPage`** (правило 8) — 328 строк, несколько задач.
  Фикс: `Component/RuleListPanel.vue`.

### P3
- P3-1. Хардкод табов `SpaceDetailPage.vue:234-245` — вывести из `RULE_TYPE_LABELS`.
- P3-2. Двойной источник `space`: локальный ref vs `store.currentSpace`
  (`SpaceDetailPage.vue:20,161`, `SpaceSettingsPage.vue:28`).
- P3-3. `fetchRevision` без error-состояния; `loading` в сторе не потребляется
  (`spaceRevision.ts:14,55-69`).
- P3-4. Мок: `changedCount: Math.random()` (`mockSpaces.ts:187`); `ruleCount`
  (186) не совпадает с фактическим срезом; `rulesCount` хардкод (25,34) vs каталог.
- P3-5. `slugify` из `Rule/Utils/Text` в моке (`mockSpaces.ts:9`) — внутренний Utils (правило 28, mock-only).
- P3-6. `isRuleInDraft` в v-for (`SpaceDetailPage.vue:165-170,253`) — пересчёт на правило.
- P3-7. `effectiveRules` — кросс-стор `useDraftRuleStore()` внутри computed
  (`spaceRevision.ts:32`); мерж-функция для F8-теста.
- P3-8. `invalidateCache` публичен, но не вызывается; `revisionsCache`/`revisionCache`
  (174,203) — похожие имена.

## Сильные стороны
- Анатомия эталонная; API через ServiceLocator/`getSpaceApi` (правило 26).
- Сигналы реально пробрасываются; AbortError-обработка корректна (в отличие от Chat).
- Плагинная точка роутов — generic `RouteRecordRaw[]`, композиция в `Roleplay/routes.ts`; цикла нет.
- Мок: единый пул `ruleCatalog`, always-included + рекурсия по `*_code`.
- Типизация по слоям, без any, `import type`.

## Намеренно не блокировало
- Rule/routes.ts читает `useSpaceRevisionStore` для breadcrumb'ов (публичный стор, ок).
- `formatPublished` в компоненте (мелочь); Vuetify `:rules` на name.
- `PublishDialog.publishDraft` без собственного signal.

## План (шаги)
1. **P1-1** — commitDraft перечитывает revisionsMeta после коммита. ✅
2. **P2-5** — RULE_TYPE_LABELS: реэкспорт через Rule/init; обновить импорты. ✅
3. **P2-6** — RevisionKind → Space/Enum/. ✅
4. **P2-2/3/4** — F17: error-состояния + UI повторов. ✅
5. **P2-7** — PublishService.prepareSummary из PublishDialog + тест. ✅
6. **P2-8** — декомпозиция SpaceDetailPage (RuleListPanel) + P3-1/P3-6. ✅
7. **P2-1** — __tests__/Store/spaceRevision.test.ts. ✅
8. **Архитектура: развязка `Rule→Space` (hosting-контекст)**. ✅ (вариант A)
9. **P3-2..P3-8** — остальные улучшения. ✅
10. **Верификация** — format → lint → vue-tsc → vitest. ← текущий

## Решения (заполняется по ходу)

### Пункт 1 — P1-1 (пустой селектор после publish). Решено: вариант A.

- `Store/spaceRevision.ts commitDraft`: после смены контекста добавлен
  `await fetchRevisionsMeta(spaceId, signal)`; `revisionsMeta.delete(spaceId)` убран
  (мета замещается свежим срезом). Любой потребитель `commitDraft` получает свежую мета.
- Верификация: format/lint чисто.

### Пункт 2 — P2-5 (RULE_TYPE_LABELS). Решено: вариант A + принцип направленности.

- `Rule/init.ts`: реэкспорт `export { RULE_TYPE_LABELS } from .../Constant/RULE_TYPE_LABELS`;
  `SpaceDetailPage.vue` и `PublishDialog.vue` импортируют из `@/modules/Roleplay/Rule/init`.
- **Принцип-стандарт (одобрен пользователем)**: Space — модуль «наборов правил»,
  поэтому **Space может зависеть от Rule, Rule от Space — нет**.
- Зафиксирована обратная зависимость `Rule → Space` (hosting-контекст): RuleEditPage/
  RuleDetailPage резолвят space по code + `syncFromContext`, 5 редакторов читают
  `effectiveRules`, `Rule/routes.ts` читает breadcrumb. Сейчас это публичные сторы
  (правило 28 формально ок), но противоречит строгому принципу. Вынесено отдельным
  пунктом плана (шаг 8).

### Пункт 3 — P2-6 (RevisionKind → Enum/). Решено: вариант A.

- `Dto/RevisionKind.ts` → `Enum/RevisionKind.ts` (string-literal union, правило 46);
  обновлены импорты в `Dto/RevisionContext.ts` и `Store/spaceRevision.ts`. Потребителей больше нет.
- Верификация: format/lint чисто.

### Пункт 4 — P2-2/3/4 (F17). Решено: вариант A (эталон Notifications).

- `Store/spaces.ts`: новое поле `error`; `fetchSpaces` чистит в начале, в catch
  (кроме AbortError) ставит `'Не удалось загрузить пространства'`.
- `SpacesPage.vue`: `v-alert type="error"` + кнопка «Повторить» → `fetchSpaces(signal.value)`.
- `SpaceNewPage.vue`: локальный `saveError` (closable alert).
- `SpaceSettingsPage.vue`: локальный `actionError` (closable alert); сообщения
  раздельные для save/deactivate; AbortError не выводится.
- Верификация: format/lint чисто.

### Пункт 5 — P2-7 (PublishService). Решено: вариант A.

- `Space/Dto/PublishSummary.ts` — интерфейс `{ added, changed, problems, spaceErrors }`.
- `Space/Service/PublishService.ts` — stateless класс, `prepare(published, draftRules,
  effective, keywords): PublishSummary`; зависимости `ruleValidation`/`ruleDiff`
  инъектируются в конструктор (тип — `typeof` из `Rule/init`, публичная точка).
- `Space/Service/Instance/publishService.ts` — синглтон (правило 27).
- `PublishDialog.vue` — `summary = ref<PublishSummary | null>`, 4 производных computed,
  шаблон не менялся.
- `Space/__tests__/Service/publishService.test.ts` — 5 кейсов (diff, пустой draft,
  отсутствующая ссылка, валидный набор, цикл видов). vitest 5/5.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто (попутно исправлен
  `signal.value` → `signal` в шаблоне SpacesPage:43 — в шаблоне ref разворачивается).

### Пункт 6 — P2-8 (декомпозиция SpaceDetailPage). Решено: вариант A + P3-1/P3-6.

- `Component/RuleListPanel.vue` — новый: табы + поиск + список + заголовок «Правила (N)» +
  «Создать правило». Props `rules/spaceCode/ctx/isDraftContext/draftRuleIds`, emit `discard`.
  - **P3-1**: табы из `Object.keys(RULE_TYPE_LABELS)` (хардкод 9 типов убран).
  - **P3-6**: `draftRuleIds` — Set id из родителя; `isRuleInDraft` удалён.
- `SpaceDetailPage.vue` 328 → 256 строк: остались контекст-бар, loadSpace/resolveRoute/
  redirectPortal, диалоги, snackbar; секция списка — `<RuleListPanel>`.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто.

### Пункт 7 — P2-1 (тесты стора ревизий). Решено: вариант A.

- `Space/__tests__/Store/spaceRevision.test.ts` — 8 кейсов (F8): effectiveRules
  (rev=draft→merge по id + новые в конец, без черновиков→published), resolveLatestRevision
  (кэш meta / fetch), syncFromContext (draft/rev), commitDraft (кэш новой ревизии +
  смена контекста + перечитанная meta — регрессия P1-1).
- Нюанс: реактивная Map возвращает proxy — проверка по значению (`?.revision`), не `toBe`.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, vitest 8/8.

### Пункт 8 — Архитектура: развязка `Rule→Space`. Решено: вариант A (контекст-инъекция).

Домен: работа с правилами идёт через ревизию (draft/rev), а не «через пространство»;
ревизии — домен Space. Правило: **Rule не импортирует Space-сторы/сервисы**; доступ —
только через контракт хоста из `Space/init`.

Новое:
- `Space/Interface/ISpaceContext.ts` — контракт `{ space, spaceId, effectiveRules, ctx,
  isDraftContext, isRevisionContext, loading, error, retry }`.
- `Space/Constant/spaceContextKey.ts` — `InjectionKey<ComputedRef<ISpaceContext>>`.
- `Space/Composables/useSpaceContext.ts` — `inject` + guard (бросает вне layout).
- `Space/init.ts` — реэкспорт `spaceContextKey` + `useSpaceContext` + `ISpaceContext`.
- `Space/Component/SpaceContextLayout.vue` — route-layout на `:code`: грузит space +
  meta, синкает контекст по `:ctx`, гейтит loading/error/retry, `provide(spaceContextKey)`,
  рендерит `<RouterView/>`. Инвалидный `:ctx` → редирект на `/space/:code`.
- `Space/Page/SpaceLandingPage.vue` — редирект портала (draft или последняя ревизия).

Переписаны:
- `Space/routes.ts` — `:code` → `component: SpaceContextLayout` (lazy); лендинг отдельной
  страницей; `SpaceDetailPage` только для ctx.
- `SpaceDetailPage.vue` — потребляет контекст; убраны loadSpace/applyContext/resolveRoute/
  redirectPortal; `onPublished` обновляет `currentSpace.revision`.
- `SpaceSettingsPage.vue` — берёт space из загруженного layout'ом стора (без fetch).
- `Rule/Page/RuleEditPage.vue`, `RuleDetailPage.vue` — `useSpaceContext()` из `Space/init`;
  `spaceId` = computed; убраны fetch/sync/редирект.
- `Rule/Store/rules.ts` — новый action `setCurrentRule`.
- 5 редакторов (Ability/Characteristic/Item/Race/Species) — проп `rules: Rule[]`
  вместо `revisionStore.effectiveRules`; используются только в RuleEditPage.
- `Rule/routes.ts` — breadcrumb правила из `useRuleStore().currentRule` (без Space).

Компромисс (зафиксирован): breadcrumb имени правила теперь из Rule-стора
(`setCurrentRule` заполняется в RuleDetailPage), а не из `effectiveRules` — имя
появляется после первого открытия правила; deep-link показывает фолбэк «Правило».
Route-meta не может использовать provide/inject — некомпонентный контекст намеренно
не вводили (вариант C был бы модуль-глобалом).

Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, vitest 26/26 файлов
(249 тестов) — попутно layout переведён на lazy-import (статический импорт тянул
Vuetify CSS в `moduleRoutes.test.ts`).

### Пункт 9 — P3-2..P3-8. Решено по пунктам.

- **P3-1 / P3-6** — закрыты шагом 6 (табы из RULE_TYPE_LABELS; Set черновиков).
- **P3-2** — закрыт шагом 8 (единый источник: context/currentSpace).
- **P3-3** — из `Store/spaceRevision.ts` удалены мёртвые `loading` и `invalidateCache`
  (потребителей нет; error/retry load-пути владеет layout).
- **P3-4** — мок-фикстуры: `changedCount` детерминирован (`1 + (r % 3)` вместо
  `Math.random()`); `ruleCount` в meta согласован с фактическим срезом через хелпер
  `generatedRuleCount(revision)`; `rulesCount` на seed-space вычислен тем же хелпером
  вместо хардкода 42/38.
- **P3-5** — решено: вариант B (без правок). `slugify` доменно-специфичен (фолбэк
  `|| 'rule'`), в Core не переносится; импорт в `Mock/mockSpaces.ts` из `Rule/Utils/Text`
  остаётся как принятое мок-only исключение (та же группа, не потребитель).
- **P3-7** — закрыт шагом 7 (тесты effectiveRules через стор), без выноса merge-функции (YAGNI).
- **P3-8** — мок-карты переименованы: `revisionsCache`→`revisionMetaCache`,
  `revisionCache`→`revisionRulesCache`.

Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, Space vitest 13/13
(промежуточно); полный гейт — ниже.

## Верификация
После каждого изменения: `npm run format` + `npm run lint`; на завершении —
`npx vue-tsc --noEmit` + `npm run test`. Порт 3000 — пользовательский (не занимаем).
