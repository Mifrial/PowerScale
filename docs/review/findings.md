# Находки кампании ревью фронта

Канон: `draft-front_1.2ds/frontend-rules.md`. Промт: `docs/review/prompts/campaign.md`.
Старые `context-*.md` не источник.

## Очередь правок

Канон: P1 раньше P3, блокеры раньше зависимых, один модуль подряд. DAG фазы A не трогать. Код — отдельными сессиями «делай очередь с пункта N».

### Спорные кластеры

**N+1 чат→сущность (Character-1 / Game-1).** Плюс поля на Dto списка: без нового API, мок сразу. Минус: список толще. Отдельный `getByChatId` чище, ждёт бэк. Рекомендация: поле на списке + фасад; один паттерн на оба донора в соседних сессиях.

**Контракт чипа (Rule-2 / Rule-5 / Chat-2 / Game-4 `actual`).** Цикл в находках: Chat-2 → Rule-5 → Rule-2. Разрыв: тип живёт у хоста Chat (`ChatInlineRendererContext`), потом RuleChip, потом поведение каталога, потом дефолтный провайдер. Чип без контекста — «скрыт»; `actual` — константа Space / фетчер, не `getRules(0)` и не литерал в Game.

**Utils → Service.** Везде семейства 1:1 с текущими файлами/тестами, не один бог-класс (Rule-1, Chat-4, Character-2, Game-2).

**Лист в бою (Game-3).** Реэкспорт тайлов из Character `init` быстро и дыряво; слоты/`CharacterSheetEditor` — канон. Рекомендация: после Character-2 — `SheetCard`+доступ как мост, слоты не блокировать очередь.

**ChatThread.** Один реэкспорт из Chat `init` (как `CharacterSheetEditor`); Character-3 и Game-4 только переключают импорт.

### Очередь (шаги)

1. **Character-1** — N+1 обсуждение персонажа (P1).
2. **Game-1** — тот же паттерн для игры (P1; тот же контракт списка).
3. **Chat-2** — Dto inline-контекста, функции не в `ref`, F17 `resolveChatRules` (P2, разрывает цикл с Rule-5).
4. **Chat-1** — `onlyIfMember` на вкладке (P2, пока в Chat).
5. **Chat-3** — `loadOlder` / parse sync / `ensureUsers` не в computed (P2).
6. **Rule-5** — RuleChip на тип Chat (P2).
7. **Rule-2** — каталог/`getRules(0)` (P1, после контракта чипа).
8. **Rule-1 + Rule-4** — домен Utils → Service и типы в слой (P2; одна сессия, тесты зеркалом).
9. **Rule-3** — реэкспорт из Rule `init`; импорты соседей — заглушки до их шагов (P2).
10. **Character-4** — F17 загрузки редактора / in-game source (P2, изолирован).
11. **Character-2** — Utils листа → Service, реэкспорт (P2; после Rule-1).
12. **Character-3** — ChatThread из Chat `init` + F17 ревизии (P2; после 3 и 9). Реэкспорт Thread — в этой же сессии, если ещё не сделан в шаге 3.
13. **Game-4** — Thread, `gameId` в composable, `actual` из Space, F17 обсуждения (P2; после 3, 7, 12).
14. **Game-2 + Game-5** — бой/DOT/права в Service, type-guard броска (P2; после Rule-1).
15. **Game-3** — карточка боя на публичку Character (P2; после 11).
16. **P3 пакетом, не раньше:** Chat-4 вместе с Chat-5 (свёртки); Chat-6 токен+SSE только с транспортом; Rule-6 не пилить страницу; Rule-7 с касанием Dto; **Rule-8 + Character-6** одним паттерном persist; Character-5 после Character-2; Game-6 после 14–15.

Не вставлять в очередь: сознательные хвосты DAG (тесты + mockSpaceApi / Game mock).

### Roleplay/Rule

### DAG

Фаза A (2026-08-26):

- Выпрямили: Rule не импортирует Space/Character (inject `IRuleHostContext` + `registerRevisionRulesFetcher`; `DAMAGE_TYPE_FORMS` в Rule). Chat не импортирует Roleplay (`IChatRulesProvider` → opaque `ChatRulesContext`). Character production не импортирует Game (`registerInGameSheetSource` / `registerCharacterSessionOverlay`, донор Game).
- Канон: Character и Game ← публичный Space (ревизия). Rule → Chat только `register*` чипов. Character не ← Game.
- Сознательно: тест Rule `ruleRevisionResolver.test.ts` подключает `mockSpaceApi` как проводку фетчера. Тест Character `mockCharacterUpdate.test.ts` бутстрапит Game mock (фикстуры членства/оверлея).

### Roleplay/Rule

Ревью 2026-08-26 (промт B). DAG фазы A не регрессировал; хвост теста `ruleRevisionResolver.test.ts` + `mockSpaceApi` сюда не переносим.

### Rule-1 — Домен в Utils/ и логика в Constant/
- Модуль: Roleplay/Rule
- Критичность: P2 (правило/домен)
- Где: `Utils/checkResolution.ts`, `Utils/checkLaunch.ts`, `Utils/checkSuccessRating.ts`, `Utils/damageTypeSpec.ts`, `Utils/derivedCharacteristic.ts`, `Utils/aggregateSourceDeltas.ts`, `Utils/applyAdvantageDrop.ts`, `Utils/State/formatStateEffects.ts`, `Utils/ruleRevisionResolver.ts:22` (`getRevisionRulesFetcher` + `useRuleStore` — не stateless), `Constant/Ability/FORMULA_TYPE_LABELS.ts:14` (`formulaTypeItems`)
- Правило: §2 Utils / склонность к ООП
- Суть: семейства функций одной предметной области (проверка, тип урона, производная характеристика, преимущество, резолв ревизии) лежат в Utils; резолвер ещё ходит в Pinia. `formulaTypeItems` — поведение в Constant. Мелочи, которые уместны в Utils: `slugify`, `ruleToForm`, `resourceShortName`, `parameterLimitName`, `formatPeriodicity`.
- Зависит от: ничего
- Фикс (черновик): семейства 1:1 в `Service/` (+ Instance), типы — в Dto/Interface; реэкспорт из `init` (см. Rule-3). Развилка: один толстый `CheckService` vs несколько классов по текущим файлам — рекомендация второе (тесты `__tests__/Utils/*` переезжают зеркалом в `__tests__/Service/`).
- Статус (2026-08-26): код готов (семейства 1:1 + Instance; типы в Dto/Interface/`MechanicHandler`; тесты в `__tests__/Service/`). В «Закрытые» — после коммита.

### Rule-2 — Каталог и токены правил: `getRules(0)` и тихий F17
- Модуль: Roleplay/Rule
- Критичность: P1 (баг/контракт)
- Где: `Store/ruleCatalog.ts:4-17` (TODO + `getRules(0)`), `init.ts:58-61` (`.catch` у `ensureLoaded`), `init.ts:75-86` (`describe`/`search` через каталог и `getRules(0)`), `Component/RuleChip.vue:32-48` (тихий catch)
- Правило: §7 бэк/F17; контракт чипа vs ревизия
- Суть: без `ChatRulesContext` чип и «Вставить ссылку» смотрят плоский каталог space `0`. Слайдер уже резолвит срез через `IRevisionRulesFetcher`. Тихие провалы каталога маскируют ошибку под «Объект скрыт» / пустой поиск.
- Зависит от: ничего
- Фикс (черновик): источник истины — контекст чата (`tokenLabels` + `spaceId`/`rulesRevision`). Fallback не литерал `0`, а фетчер с явным space/revision от хоста чата. Catch — F17, не пустой список. Развилка: чип без контекста всегда «скрыт» vs отдельный контракт «последняя ревизия актуальных правил» — рекомендация первое + фетчер только когда хост передал ревизию.
- Статус (2026-08-26): код готов (чип без среза скрыт; стор `getRules(0)` снят; глобальный поиск правил пустой; срез — провайдер/слайдер). В «Закрытые» — после коммита.

### Rule-3 — Публичная поверхность не покрывает домен, которым живут Character/Game
- Модуль: Roleplay/Rule
- Критичность: P2 (правило/домен)
- Где: потребители: `Character/Service/Overview/CharacterOverviewService.ts` (`derivedCharacteristic`, `formatStateEffects`, `itemModifierService`), `Character/Service/CharacterEditorService.ts` (`RaceSpecService` класс, `checkResolution`, handler `PURCHASE_SURCHARGE_EVENT`), `Character/Component/Editor/EditorAbilitySlider.vue` (`AbilityCard.vue`), `Game/Utils/hitRoll.ts` / `characteristicRoll.ts` / `simpleCheckRoll.ts` (`checkResolution`), `Game/init.ts` (`aggregateSourceDeltas`), `Space/Mock/mockSpaces.ts` (`slugify`)
- Правило: §2 межмодульный доступ (`init.ts` / сторы / Interface/Dto/Enum)
- Суть: `init.ts` отдаёт validation/diff/mechanicEngine/itemModifier, но не check/derived/damage/format/AbilityCard. Соседи импортируют внутренние Utils/Service/Component.
- Зависит от: Rule-1
- Фикс (черновик): после переноса в Service — точечный реэкспорт из `init` (баррель запрещён); импорты Character/Game править в их волнах. `slugify` для Space — кандидат в Core, не раздувать публичку Rule.
- Статус (2026-08-26): код готов (реэкспорт check/derived/damage/format/aggregate/race/AbilityCard; `slugify` не в публичке; импорты соседей — в их волнах). В «Закрытые» — после коммита.

### Rule-4 — Именованные типы вне Dto/Interface
- Модуль: Roleplay/Rule
- Критичность: P2 (правило/домен)
- Где: `Service/Mechanic/MechanicEngine.ts:8-19` (`ResolvedMechanic`, `ResolveActiveOptions`), `Service/Mechanic/MechanicHandler.ts:9` (контракт сервиса), `Utils/ruleRevisionResolver.ts:5` (`RuleRevisionQuery`), `Utils/checkSuccessRating.ts:4` (`CheckSuccessRating`), `Utils/derivedCharacteristic.ts:4-8` (`DerivedOperator`, `ParsedDerivedFormula`)
- Правило: §3 типизация — слои
- Суть: именованные типы объявлены в Service/Utils; `MechanicHandler` по смыслу — `Interface/`. `init.ts` уже реэкспортирует `ResolvedMechanic`/`MechanicHandler` из Service.
- Зависит от: Rule-1 (переезд Utils)
- Фикс (черновик): типы в Dto/Interface отдельными файлами; `MechanicHandler` → `Interface/`; реэкспорт init поправить путями.
- Статус (2026-08-26): код готов (типы в Dto/Interface, init реэкспорт с новых путей). В «Закрытые» — после коммита.

### Rule-5 — RuleChip дублирует opaque-контекст чата
- Модуль: Roleplay/Rule
- Критичность: P2 (правило/домен)
- Где: `Component/RuleChip.vue:9-16`
- Правило: §2 плагин (хост объявляет generic-контракт; донор без своей копии)
- Суть: проп `context` — инлайн `{ ruleNames?, spaceId?, rulesRevision? }` вместо `import type { ChatRulesContext }` из Chat Dto (ребро Rule → Chat Dto канонично). Расхождение полей (`tokenSources` / `processAttachments`) не ловится компилятором.
- Зависит от: Rule-2 (смысл полей ревизии)
- Фикс (черновик): тип пропа из `ChatRulesContext` (или `Pick` нужных полей в Chat Dto, если полный контекст чипу не нужен — тогда тип живёт у хоста).
- Статус (2026-08-26): код готов (`ChatInlineRendererContext` / `tokenLabels`, shim `ruleNames` снят). В «Закрытые» — после коммита.

### Rule-6 — Крупные SFC: сигнал декомпозиции
- Модуль: Roleplay/Rule
- Критичность: P3 (улучшение)
- Где: `Component/Editors/AbilityEditor.vue` (~484), `Component/Cards/AbilityCard.vue` (~393), `Component/FormulaInput.vue` (~300), `Component/Editors/GrantEditor.vue` (~304), `Component/Editors/ItemModifierOpsEditor.vue` (~329), `Page/RuleEditPage.vue` (~484)
- Правило: §1 одна задача; ~250 — сигнал
- Суть: RuleEditPage — один switch редакторов, пилить по счётчику не стоит. AbilityEditor совмещает тип/зоны/требования/гранты/процесс и мутации spec в script — ближе ко второй задаче.
- Зависит от: ничего
- Фикс (черновик): при правке AbilityEditor — вынести блоки в уже существующие дочерние редакторы, не резать карточку/страницу ради длины.
- Статус (2026-08-26): код готов (страницу/карточку не пилили; F17 признаков — в Rule-8). В «Закрытые» — после коммита.

### Rule-7 — Несколько сущностей в одном TS-файле; реэкспорт типа из .vue
- Модуль: Roleplay/Rule
- Критичность: P3 (улучшение)
- Где: `Dto/Item/ItemModifierSpec.ts` (Applies/Price/Override/Effect/Spec), `Dto/MechanicPayload.ts`, `Dto/Check/CheckSpec.ts`, `Service/Mechanic/MechanicEngine.ts` (класс + 2 типа), `Component/FormulaInput.vue:4` (`export type { Formula }`)
- Правило: §2 один экспорт на файл; §3 типы только в Dto/Interface/Enum
- Суть: свалки связанных Dto в одном файле; FormulaInput реэкспортирует тип из SFC.
- Зависит от: Rule-4 (типы MechanicEngine)
- Фикс (черновик): по файлу на интерфейс там, где уже есть отдельные потребители; убрать реэкспорт из FormulaInput (импорт `Formula` из Dto).
- Статус (2026-08-26): код готов (ItemModifier/Check/MechanicPayload по файлам; Formula только из Dto; типы MechanicEngine уже в слое с Rule-4). В «Закрытые» — после коммита.

### Rule-8 — Черновик localStorage и fetch признаков в редакторе без F17
- Модуль: Roleplay/Rule
- Критичность: P3 (улучшение)
- Где: `Store/draftRules.ts:7-33` (`JSON.parse`, слабая форма `DraftEntry`, persist в пустой catch), `Component/Editors/AbilityEditor.vue:189-192` (`fetchTags()` без abort/ошибки на форме)
- Правило: §7 валидация входа; F17
- Суть: черновик из localStorage почти не валидируется; квота глотается (комментарий есть — in-memory ок). Список keywords на админке F17 имеет; редактор способности при пустом сторе молча ждёт.
- Зависит от: ничего
- Фикс (черновик): узкий parse `DraftEntry`/`Rule`; на форме — тот же error/retry, что у `KeywordsListPage`, либо полагаться на предзагрузку `RuleEditPage` (`keywordStore.fetchTags`) и не дублировать fetch.
- Статус (2026-08-26): код готов (`DraftRulesPersistService`; битый ключ — discard + snackbar; AbilityEditor — error/retry признаков). В «Закрытые» — после коммита.

### Messages/Chat

Ревью 2026-08-26 (промт B). DAG: Chat production не импортирует Roleplay. XSS текста: интерполяция `{{ seg.text }}`, `v-html` нет. Виртуализация списка есть (`useChatVirtualScroll`). Rule-5 (инлайн-контекст у RuleChip) не дублируем — здесь сторона хоста.

### Chat-1 — Хост знает тип `character_discussion`
- Модуль: Messages/Chat
- Критичность: P2 (правило/домен)
- Где: `Store/chat.ts:76-84` (`currentTabChats`), комментарий про «Обсуждения персонажей»; тест `__tests__/Store/chat.store.test.ts` закрепляет это
- Правило: §2 плагин (хост без донора)
- Суть: вкладки/типы регистрирует Character (`registerChatTabs`), но фильтр «только чаты, где пользователь участник» зашит в хосте по строке типа донора. Новые типы с той же семантикой не подхватятся; хост зависит от имени чужого типа.
- Зависит от: ничего
- Фикс (черновик): флаг на `IChatTab` (например `onlyIfMember: true`) или предикат, который задаёт донор при `registerChatTab`. Стор фильтрует generic. Развилка: флаг на вкладке vs на `IChatType` — рекомендация вкладка (семантика UI «эта вкладка = мои обсуждения», не всех чатов типа).
- Статус (2026-08-26): код готов (`onlyIfMember` на `IChatTab`, хост без имени типа донора). В «Закрытые» — после коммита.

### Chat-2 — Opaque-контекст правил: `Record<string, unknown>` и функции в ref
- Модуль: Messages/Chat
- Критичность: P2 (правило/домен)
- Где: `Dto/ChatRulesContext.ts:10-19` (`processAttachments`, `tokenSources` с async search); `Component/ChatMessenger.vue:24,62-66,120-137`; `Component/ChatThread.vue:34-44,71-76`; `Component/ChatMessageList.vue:19-20`; `Component/ChatMessageRow.vue:22-23,70-78` (`openEntity` из мешка)
- Правило: §2 плагин; §3 reactive = plain Dto
- Суть: у хоста уже есть `ChatRulesContext`, но лента принимает безтиповой bag (туда же `openEntity` от Game). Messenger кладёт контекст в `ref` вместе с функциями. Thread дублирует поля контекста отдельными пропами и сам `resolveChatRules` не зовёт. Ошибки провайдера в Messenger глотаются (`catch` → `null`) — пересекается с Rule-2.
- Зависит от: Rule-5 (чип должен принимать тот же тип)
- Фикс (черновик): один Dto хоста для inline (`Pick<ChatRulesContext, 'ruleNames' | 'spaceId' | 'rulesRevision'>` + опциональный `openEntity?: (ref: string) => void` в Chat Dto, без типов Character/Game). `processAttachments`/`tokenSources` не класть в reactive state — проп/замыкание. Messenger и Thread — один путь. `catch` провайдера — F17, не немой null.
- Статус (2026-08-26): код готов (`ChatInlineRendererContext`, `openEntity` колбэком, отдельный F17 резолва). В «Закрытые» — после коммита.

### Chat-3 — F17: loadOlder, sync, computed-fetch
- Модуль: Messages/Chat
- Критичность: P2 (правило/домен)
- Где: `Store/chat.ts:205-227` (`loadOlder` try/finally без catch), `Store/chat.ts:33-43` (poll: пустой catch), `Service/ChatSyncService.ts:52-59` (SSE `JSON.parse` без валидации Dto), `init.ts:60-64` (`fetchUsers` без catch), `Composables/useChatVisibilityOptions.ts:25-32` (`ensureUsers` внутри `computed`)
- Правило: §7 F17 / запросы; §2 слои (стор не молчит; computed без сайд-эффектов)
- Суть: ошибка подгрузки истории не попадает в `chatError`; зрителю кажется, что сообщений больше нет. Sync/poll намеренно тихие — ок для фона, но SSE-мусор и невалидный payload принимаются как есть. В computed видимости — скрытый fetch участников на каждый пересчёт.
- Зависит от: ничего
- Фикс (черновик): `loadOlder` → `chatError` + retry (как `openChat`). `ensureUsers` — `watch`/`onMounted`, не computed. Sync: узкий parse `SyncResponse` (отбросить кадр, не применять). Token search — ошибка поиска, не пустой список.
- Статус (2026-08-26): код готов (отдельный `olderError` + retry, parse sync, `ensureUsers` в watch). В «Закрытые» — после коммита.

### Chat-4 — Домен видимости и свёрток в Utils
- Модуль: Messages/Chat
- Критичность: P2 (правило/домен)
- Где: `Utils/chatVisibility.ts` (`isMessageVisible`, права), `Utils/flattenChatFolds.ts` (лес → ряды виртуализатора)
- Правило: §2 Utils vs класс-сервис
- Суть: не мелочи: инварианты видимости (тесты есть) и алгоритм свёрток с unread. `messagePreview` — мелочь, оставить в Utils.
- Зависит от: ничего
- Фикс (черновик): `ChatVisibilityService` / `ChatFoldService` в `Service/` + Instance; тесты зеркалом в `__tests__/Service/`. Не смешивать с Rule-1.
- Статус (2026-08-26): код готов (`ChatVisibilityService` / `ChatFoldService` + Instance; `messagePreview` в Utils). В «Закрытые» — после коммита.

### Chat-5 — Типы вне слоя; несколько сущностей в ChatFold
- Модуль: Messages/Chat
- Критичность: P3 (улучшение)
- Где: `Store/chat.ts:18-26` (`ChatState`), `Dto/ChatFold.ts` (Tone/Chrome/Variant/Node/Child/VisibleRow), `Composables/useChatVirtualScroll.ts:76` (`as HTMLElement`)
- Правило: §3 слои типов; §2 один экспорт на файл
- Суть: `ChatState` живёт в сторе. Dto свёрток — свалка union'ов (потребители есть у нескольких).
- Зависит от: Chat-4 (если Fold-сервис заберёт типы)
- Фикс (черновик): `ChatState` → `Dto/`; VisibleRow/Child — отдельные файлы по мере правок свёрток, не ради счётчика.
- Статус (2026-08-26): код готов (`ChatState`, fold Dto/Enum по файлу; measure без `as HTMLElement`). В «Закрытые» — после коммита.

### Chat-6 — SSE без reconnect; неизвестный inline-токен пропадает
- Модуль: Messages/Chat
- Критичность: P3 (улучшение)
- Где: `Service/ChatSyncService.ts:49-60` (нет `onerror`/`onopen`, в комментарии конфига — auto-reconnect); `Component/ChatMessageRow.vue:136-144` (`v-else-if="inlineRenderers[si]"` — нет ветки для неизвестного типа)
- Правило: §7 sync; XSS ок, UX токена
- Суть: целевой режим SSE сейчас не выбран (default poll) — не баг транспорта. Неизвестный `[[type:]]` не рендерится вовсе (не текст) — не XSS, но теряется содержимое.
- Зависит от: Chat-3 (parse кадра)
- Фикс (черновик): когда включится SSE — EventSource reconnect (native) + смена `since`. Неизвестный токен — fallback `{{ params }}` как текст.
- Статус (2026-08-26): код готов (неизвестный токен как `[[type:params]]`; SSE `onerror` переоткрывает с актуальным `since`). В «Закрытые» — после коммита.

### Roleplay/Character

Ревью 2026-08-26 (промт B). DAG: production не импортирует Game (только тест `mockCharacterUpdate.test.ts` — хвост фазы A). Плагины `IInGameSheetSource` / `ICharacterSessionOverlay` используются. `buildCharacterChatRulesContext` без RollEngine. Импорты Rule Utils/Service/Component — не копируем списком (Rule-3); здесь только сторона Character и ChatThread.

### Character-1 — Резолв чат→персонаж: N+1 и вечный кэш
- Модуль: Roleplay/Character
- Критичность: P1 (баг/контракт)
- Где: `Chat/characterChatRulesProvider.ts:7-32,41-49`
- Правило: §7 запросы; F17
- Суть: `resolve` для каждого `character_discussion` тянет `getCharacters()`, затем `getCharacter` по одному, пока не совпадёт `discussionChatId`. В списке `Character` поля чата нет. Кэш `Map` на модуле не инвалидируется (новый персонаж / смена чата — stale `null`). Ошибка API роняет `resolveChatRules` (Chat-2 глотает в null).
- Зависит от: Chat-2 (тихий catch хоста)
- Фикс (черновик): индекс на списке (`discussionChatId` в Dto списка) или `getCharacterByDiscussionChatId` у API. Кэш с TTL/сбросом при `fetchCharacters`. Развилка: поле на карточке списка (дешевле, болтливее контракт) vs отдельный endpoint (чище, ждёт бэк) — рекомендация поле на списке для мока + фасад API, без N детальных fetch.
- Статус (2026-08-26): код готов (поле на списке, `getCharacters` + `find`, без `getCharacter` и без вечного кэша). В «Закрытые» — после коммита.

### Character-2 — Домен листа в Utils/
- Модуль: Roleplay/Character
- Критичность: P2 (правило/домен)
- Где: `Utils/access.ts`, `Utils/sheetAccess.ts` (права, тесты F8), `Utils/characterSheetValidation.ts`, `Utils/stateRuntimeEffects.ts` (+ типы `CheckAdvantageQuery` в Utils), `Utils/itemWeaponProfiles.ts` (`WeaponProfileView`), `Utils/itemCheckAdvantages.ts`, `Utils/racialInnateGear.ts`, `Utils/liveActionPointsLimit.ts`, `Utils/weaponProficiency.ts`, `Utils/editorStatViews.ts`, `Utils/buildCharacterChatRulesContext.ts`
- Правило: §2 Utils vs класс; §3 типы в Dto
- Суть: права, валидация «Готов», рантайм состояний, профили оружия — предметная область Character, не мелочи. `clampAgeYears` / `moneyBreakdown` / `formulaLabel` — ближе к мелочам.
- Зависит от: Rule-1 (derived/check, которые Character вызывает из Rule Utils)
- Фикс (черновик): `SheetAccessService`, `CharacterSheetValidationService`, `StateRuntimeService`, `WeaponProfileViewService` (или методы Overview/Editor), builder контекста чата — класс рядом с провайдером. Типы view — в `Dto/`. Развилка: один `CharacterSheetService` vs семейства — рекомендация семейства (уже разные тестовые файлы).
- Статус (2026-08-26): код готов (семейства 1:1 + Instance; типы в Dto; builder чата в `Chat/`; реэкспорт из Character `init`; Game пока на Instance, как после Rule-1). В «Закрытые» — после коммита.

### Character-3 — DiscussionTab: внутренний ChatThread и тихий срыв правил
- Модуль: Roleplay/Character
- Критичность: P2 (правило/домен)
- Где: `Component/Detail/DiscussionTab.vue:8,21-31,42-48`; `Page/CharacterDetailPage.vue:18` (`RuleSlider` — уже в Rule-3)
- Правило: §2 публичные точки; §7 F17
- Суть: вкладка импортирует `ChatThread.vue` в обход `init` Chat. `fetchRevision` в пустой catch → `rules=[]` → чипы без имён, без индикатора. Контекст режется на пропы Thread (Chat-2).
- Зависит от: Chat-2, Rule-3
- Фикс (черновик): реэкспорт `ChatThread` из Chat `init` (как CharacterSheetEditor) или обёртка-страница Chat. Ошибка ревизии — alert/retry. Передавать цельный контекст, когда Chat-2 сузит проп.
- Статус (2026-08-26): код готов (`ChatThread` из Chat `init`; F17 ревизии на DiscussionTab; пропы Chat-2). Game — в Game-4. В «Закрытые» — после коммита.

### Character-4 — Загрузка редактора без общего F17
- Модуль: Roleplay/Character
- Критичность: P2 (правило/домен)
- Где: `Page/CharacterEditPage.vue:64-117` (нет try/finally на весь `load`; early return на NotFound без `loading=false`; overlay `catch` → latest без сообщения); `Composables/useCharacterCardDraft.ts:142-151` (`getMechanics`/`fetchTags` → пустой catch)
- Правило: §7 F17
- Суть: падение `fetchRevision`/`fromVersion` оставляет спиннер. In-game без зарегистрированного source или с ошибкой игры молча открывает latest — риск править не ту версию.
- Зависит от: ничего
- Фикс (черновик): `try/catch/finally` как на деталке; нет source при `?gameId=` — явная ошибка, не latest. Карточка: ошибка механик видима или блок save.
- Статус (2026-08-26): код готов (F17 load редактора; in-game без source/листа не latest; каталог карточки с retry и блок save). В «Закрытые» — после коммита.

### Character-5 — Крупные SFC редактора и типы из .vue
- Модуль: Roleplay/Character
- Критичность: P3 (улучшение)
- Где: `Component/Editor/EditorAbilityRow.vue` (~764, расчёт spent/cost в script), `InventoryTab.vue` (~663, секции/типы предметов), `InventoryItemRow.vue` (~552, `export type`), `RaceTab.vue` (~494), `CharacterDetailPage.vue` (~405)
- Правило: §1 одна задача; §3 типы не в SFC
- Суть: строки редактора совмещают каталог, цены, UI. Деталка — вкладки (одна страница-оболочка, пилить по счётчику не стоит).
- Зависит от: Character-2 (view-модели уйдут из Vue)
- Фикс (черновик): spent/cost — методы editor-сервиса; типы каталога инвентаря — Dto; InventoryTab резать только если вынесут фильтр/каталог отдельно.
- Статус (2026-08-26): код готов (`spentInZone`/`costKind` в editor-сервисе; типы инвентаря в Dto/Enum; деталку не пилили). В «Закрытые» — после коммита.

### Character-6 — Черновик localStorage и свалка Dto видимости
- Модуль: Roleplay/Character
- Критичность: P3 (улучшение)
- Где: `Store/characterDraft.ts:10-24` (тот же слабый parse, что Rule-8); `Dto/SheetVisibility.ts` (Audience/Rule/Visibility); `Dto/Editor/CharacterDraftEntry.ts` (`InventoryBaseline` + `CharacterDraftEntry`)
- Правило: §7 валидация входа; §2 один экспорт
- Суть: битый JSON даёт пустой черновик без сигнала; пользователь думает, что правок не было.
- Зависит от: Rule-8 (тот же паттерн persist)
- Фикс (черновик): узкий parse `CharacterDraftEntry`; битый ключ — discard + toast. Audience/Rule — отдельные файлы при касании видимости.
- Статус (2026-08-26): код готов (`CharacterDraftPersistService`; toast в редакторе; Audience/Rule/InventoryBaseline по файлам). В «Закрытые» — после коммита.

### Roleplay/Game

Ревью 2026-08-26 (промт B). DAG: Game ← Character/Rule/Chat/Space канон; ревизию берёт из Space сама. Overlay сессии регистрирует Mock (`mockCharacterSessionOverlay`), production `init` — `registerInGameSheetSource`. Импорты Rule Utils — не копируем (Rule-3). Combat/hit/DOT — фокус спеки.

### Game-1 — Резолв чат→игра: N+1 и вечный кэш
- Модуль: Roleplay/Game
- Критичность: P1 (баг/контракт)
- Где: `Chat/gameChatRulesProvider.ts:8-33,43-49`; список `Dto/Game.ts` без `gameChatId`/`discussionChatId`
- Правило: §7 запросы
- Суть: тот же паттерн, что Character-1: `getGames()` + `getGame` по одному, кэш `Map` без сброса. Два id чата на деталке (`gameChatId` / `discussionChatId`).
- Зависит от: Character-1 (один фикс-паттерн на оба донора)
- Фикс (черновик): поля чатов на карточке списка или `getGameByChatId`. Общий подход с Character-1.
- Статус (2026-08-26): код готов (`gameChatId`/`discussionChatId` на списке, `getGames` + `find`, без `getGame` и без вечного кэша). В «Закрытые» — после коммита.

### Game-2 — Бой, удар, DOT — семейства функций в Utils/
- Модуль: Roleplay/Game
- Критичность: P2 (правило/домен)
- Где: `Utils/hitRoll.ts` (+ типы `HitRollInput`/`HitBlockProfile`), `Utils/applyAttackDamage.ts`, `Utils/applyEndOfTurnDots.ts`, `Utils/applyInjuryCheck.ts`, `Utils/applyExhaustionCheck.ts`, `Utils/applyBloodLoss.ts`, `Utils/dotTickMath.ts`, `Utils/checkRoll.ts`, `Utils/characteristicRoll.ts`, `Utils/simpleCheckRoll.ts`, `Utils/injuryRoll.ts`, `Utils/combatCardModel.ts`, `Utils/combatChatFold.ts`, `Utils/combatChatSend.ts`, `Utils/mergeCombatOverlay.ts`, `Utils/resolveDamageTypeHooks.ts`, `Utils/chatRulesContext.ts`; при этом удар уже частично в `Service/Strike/`, увечье в `Service/Injury/`
- Правило: §2 Utils vs класс-сервис (спека кампании)
- Суть: расчёт удара/тико DOT — не мелочи; тесты уже в `__tests__/Utils/`. Strike-реестр классами, hit/DOT — свободные функции. `buildChatRulesContext` с `Math.random` и `as DiceRollSpec`.
- Зависит от: Rule-1 (checkResolution/damageTypeSpec)
- Фикс (черновик): `HitRollService`, `AttackDamageService`, `DotTickService` / `CombatOverlayService` в `Service/` (+ Instance), типы в Dto; rng в аргументе. Развилка: один `CombatResolutionService` vs семейства рядом со Strike/Injury — рекомендация семейства (уже разные тестовые файлы и реестры процедур).
- Статус (2026-08-26): код готов (семейства 1:1 + Instance; типы в Dto; rng у `buildChatRulesContext`; тесты в `__tests__/Service/`). В «Закрытые» — после коммита.

### Game-3 — Боевая карточка тянет внутренности Character, не публичную поверхность
- Модуль: Roleplay/Game
- Критичность: P2 (правило/домен)
- Где: `Component/Detail/CombatCardPanel.vue:36-51` (AbilityTab, InventoryTab, AttackTile, DefenseValue, ArmorTile, RuleLink, `useRuleDetailSlider`, Character Utils); также `CharactersTab`/`NpcCard`/`ChronicleTab` → `SheetCard`/`sheetAccess`; `HitLaunchDialog`/`CheckLaunchDialog` → Character Utils
- Правило: §2 публичные точки; спека: лист — через плагины Character, не наоборот
- Суть: Game собирает лист из внутренних Vue Character. `CharacterSheetEditor` в init Character есть, карточка боя его не использует. Правки листа в Character ломают бой без контракта.
- Зависит от: Character-2 (Utils станут публичными сервисами)
- Фикс (черновик): обзор/экип через публичный `characterOverviewService` / `CharacterSheetEditor` (или узкий `registerCombatSheetSlots` на стороне Character). Не копировать вкладки. Развилка: реэкспорт тайлов из Character `init` (быстро, дырявая публичка) vs слоты плагина на Character (дольше, канон) — рекомендация слоты/editor, реэкспорт только `SheetCard`+`sheetAccess` как временный мост.
- Статус (2026-08-26): код готов (`CharacterCombatSheet` + `SheetCard` из Character `init`; сервисы листа с публички; Game без внутренних тайлов Character). В «Закрытые» — после коммита.

### Game-4 — Хост чата: внутренний ChatThread, дефолтный провайдер `actual`, снимок gameId
- Модуль: Roleplay/Game
- Критичность: P2 (правило/домен)
- Где: `Component/Detail/GameChatTab.vue:20,48` (`ChatThread` + `useCombatChatThread(gameId.value)`); `Page/GameDetailPage.vue:20,228-241`; `Chat/actualRulesChatRulesProvider.ts:7-24` (`types: []`, код пространства `'actual'`)
- Правило: §2 публичные точки / плагин; §3 реактивность; Rule-2
- Суть: вкладка чата как Character-3 импортирует внутренний Thread. `useCombatChatThread` фиксирует `gameId` на setup — смена игры без размонтирования клеит штампы свёрток к старому id. Default-провайдер обычных чатов живёт в Game и хардкодит space `actual` (это и есть fallback Rule-2, но контракт пространства спрятан в Game). `loadDiscussionContext` глотает ошибку в пустые rules.
- Зависит от: Chat-2, Character-3, Rule-2
- Фикс (черновик): `ChatThread` из Chat `init`; composable — `ComputedRef<number>` / `watch` id. Код «актуальных правил» — константа Space или фетчер, не литерал в Game. Ошибка ревизии — F17.
- Статус (2026-08-26): код готов (`ChatThread` из Chat `init`; `useCombatChatThread` по текущему id; `ACTUAL_RULES_SPACE_CODE` в Space; F17 обсуждения и ревизии игрового чата; мессенджер резолвит срез сразу). В «Закрытые» — после коммита.

### Game-5 — Права и статусы игры в Utils; типы вне слоя
- Модуль: Roleplay/Game
- Критичность: P2 (правило/домен)
- Где: `Utils/access.ts`, `Utils/gameStatusTransitions.ts`; `Utils/hitRoll.ts:35-62`; `init.ts:114-116` (`as DiceRollResult`/`as DiceRollSpec`); `Dto/CheckOffer.ts` (несколько сущностей); `Component/Detail/CombatStateTile.vue` (`export type`)
- Правило: §2 Utils; §3 типы; запрет `as` к Dto
- Суть: `canViewGame`/`canEditGame` — F8-логика в Utils (как Character access). Дискриминант вложения броска через `'rolls' in` + assertion.
- Зависит от: Game-2 (типы hit уедут вместе)
- Фикс (черновик): `GameAccessService`; type-guard `isDiceRollResult(payload)`; CheckOffer — по файлу на тип при касании.
- Статус (2026-08-26): код готов (`GameAccessService` / статусы; `isDiceRollResult`; CheckOffer по файлам; типы тайла состояния в Dto/Enum). В «Закрытые» — после коммита.

### Game-6 — Крупные боевые SFC
- Модуль: Roleplay/Game
- Критичность: P3 (улучшение)
- Где: `Component/Detail/CombatCardPanel.vue` (~1146), `HitLaunchDialog.vue` (~993), `CheckLaunchDialog.vue` (~844), `GameChatTab.vue` (~712), `InitiativeTrack.vue` (~676), `CharactersTab.vue` (~646)
- Правило: §1 одна задача; ~250 — сигнал
- Суть: GameChatTab совмещает чат, инициативу, карточки, диалоги запуска. CombatCardPanel — лист+оверлей+чатовые действия. HitLaunch — UI оферты и расчёт удара.
- Зависит от: Game-2, Game-3 (логика уйдёт из Vue)
- Фикс (черновик): диалоги — тонкая оболочка над сервисами; GameChatTab оставить оркестратором слотов, не резать трек инициативы «по строкам».
- Статус (2026-08-26): код готов (лист боя вынесен в Game-3; диалоги уже на сервисах Game-2; GameChatTab/InitiativeTrack не пилили по строкам). В «Закрытые» — после коммита.

## Закрытые

(переносить сюда с датой и коммитом, когда пункт сделан)
