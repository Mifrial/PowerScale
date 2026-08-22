# Контекст ревью 2 — правила фронта и структурный рефакторинг

Живой файл ревью правил фронта (`draft-front_1.2ds/frontend-rules.md`) и вытекающего из них структурного рефакторинга. Создан 2026-08-02.

## Статус

- [x] Правила финализированы (`frontend-rules.md`, 6 разделов)
- [x] Ревью правил по 7 задачам
- [x] Рескан кода под финальные правила
- [x] П.1–8 Фазы 2 (CSRF, Input, Core/Utils+Composables, Home, NotFound, `Rule/init.ts`, Interface-миграция, константы фабрик)
- [x] **frontend-rules.md приведены к новой модели (2026-08-02)** — анатомия/границы/типизация/реактивность; context-2.md актуализирован; зафиксировано RPG-владение (владелец `Roleplay/Game`, после миграции)
- [x] **Фаза 2.5 стадия A (`Service/`=классы+`Service/Spec/`) — выполнена (2026-08-02)**: `*SpecService` в `Rule/Service/Spec/`, `RuleValidationService`/`RuleDiffService` (классы+синглтоны), `PasswordValidatorService`+`Constant/`, `AccessService`; мёртвый код удалён (`isActionLike`, `formatAbilityStructureError`, `formatRaceStructureError`); тесты → `Rule/__tests__/Service/`; импорты → `@/`; верификация `vue-tsc` + `vitest` 169/169
- [x] **Фаза 2.5 стадия B (`Mock/`) — выполнена (2026-08-02)**: 23 мок-файла выехали из `Service/` в `{Модуль}/Mock/` по модулям (включая `Core/Engine/Http/mockCsrf`→`Core/Engine/Mock/`); `groupPermissions.ts`→`Core/User/Mock/` (только mock-потребители); `main.ts` 11 динамических импортов; кросс-импорты моков; mock-тесты → `__tests__/Mock/` (ruleCatalog, mockMacros, mockUsers); `Service/`-каталоги очищены; верификация `vue-tsc` + `vitest` 169/169
- [x] **Фаза 2.5 стадия C (`Dto/`+`Enum/`) — выполнена (2026-08-02)**: ~60 файлов типов из `Interface/` → `Dto/` (данные) и `Enum/` (string-literal union: `Ability/AbilityType`, `Race/RaceCharacteristicMode`, `RuleType`, `ChatType`, `ChatVisibility`, `NotifFilter`); монолиты `Chat/Space/Notifications/Source/Tag` types сплитнуты на per-type файлы; `Auth/types.ts`→`Dto/PasswordPolicy`; `Interface/` остались только `I*Api` (10 файлов); импорты во всех модулях переведены на `@/` (в т.ч. `I*Api` → `Dto/`); `NotifFilter` извлечён в `Enum/` (INotificationApi + Store); верификация `vue-tsc` + `vitest` 169/169
- [x] **Фаза 2.5 стадия D (механические пути) — выполнена (2026-08-02)**: `Core/Engine/Type/`→`Value/` (DateTime, DimensionalNumber); `Constants/`→`Constant/` (Rule, Core/User); `Components/`→`Component/` (6 модулей); массовое обновление импортов во всём `src/`; верификация `vue-tsc` + `vitest` 169/169
- [x] **Фаза 2.5 стадия E (`Core/Engine` нормализация) — выполнена (2026-08-02)**: `index.ts`→`init.ts` (п.9) + `sl`→`serviceLocator`; `ServiceLocator`→`Service/`; `Action/ActionResponse`→`Dto/`, `Action/Engine`→`Service/`; `Http/`→`Service/` (`HttpClient`, `CsrfApi`) + `Interface/` (`ICSRFApi`) + `Mock/` (уже был), CSRF-регистрация перенесена в `init.ts`; `Roll/DiceRollSpec`→`Dto/Roll/`, `Roll/rollParser`→`Service/Roll/RollParserService` (класс+синглтон, временно в Core/Engine — до RPG-пункта); `Text/slugify`→`Rule/Utils/Text/`; `Time/debounce`→`Core/UI/Utils/`; пустой `DI/` удалён; верификация `vue-tsc` + `vitest` 169/169
- [x] **Фаза 2.5 стадия F (доки+верификация) — выполнена (2026-08-02)**: TR.md актуализирован (структура фронта в новой анатомии, пути ServiceLocator/Dto/Enum/Mock/Value, исторические решения и чек-лист; CSRF-модуль → Core/Engine); `Core/UI/Interfaces/`→`Core/UI/Dto/` (эталон `ColumnDefinition.ts` по frontend-rules.md §3); финальные `vue-tsc --noEmit` чисто + `vitest run` 169/169. **Фаза 2.5 (зеркало бэк-архитектуры) завершена.**
- [x] **п.10 (тесты → единый `__tests__/` корня модуля) — выполнена (2026-08-02)**: перенесены `Core/Engine/Value/DimensionalNumber.test.ts`→`Core/Engine/__tests__/Value/`, `Chat/Service/__tests__/{chatSyncService,rollCalc}`→`Chat/__tests__/Service/`, `Rule/Store/__tests__/draftRules`→`Rule/__tests__/Store/`, `router/{access,moduleRoutes}.test.ts`→`router/__tests__/`; импорты в тестах → `@/`; пустые каталоги удалены; верификация `vue-tsc` + `vitest` 169/169
- [x] **Флетнинг `Rule/Source` и `Rule/Tag` (2026-08-02)**: убраны вложенные 3-уровневые модули (нарушали двухуровневую модель «Группа/Модуль»); Source и Tag — часть модуля Rule: `Dto/{Source,Tag}.ts`, `Interface/{ISourceApi,ITagApi}.ts`, `Service/{SourceApi,TagApi}.ts`, `Mock/mock{Source,Tag}*`, `Store/{sources,tags}.ts`, `Page/{TagsListPage,TagEditPage}.vue`; `register/getSourceApi` и `register/getTagApi` → `Rule/init.ts`, Tag-админ-роуты → `Rule/routes.ts` (`adminChildren`); `main.ts`, `Roleplay/routes.ts`, редакторы/страницы обновлены; верификация `vue-tsc` + `vitest` 169/169
- [x] **Доменные решения 2026-08-02**: (1) **Источники → правило типа `source`** (набор источников меняется между версиями правил → часть контента ревизии, а не глобальный справочник): `RuleType += source`, `source_id`→`source_code` (DefenseSlot/ResistanceSlot/Grant), Source-справочник удалён (Dto/Interface/Service/Mock/Store/init), редакторы выбирают source-правила из ревизии, `validateRuleReferences` собирает source-ссылки, mock-правила source (armor/shield/spell/training/innate). (2) **Теги → Признаки (Keyword)**: `Tag`→`Keyword` по коду и контракту (`keywordIds`, `IKeywordApi`/`KeywordApi`/`mockKeyword*`/`Store/keywords`/`KeywordEditPage`/`KeywordsListPage`), DSL `has_tag`→`has_keyword`, `tag_code`→`keyword_code`, `resolveTypeFromTags`→`resolveTypeFromKeywords`, константы `ABILITY_TYPE_KEYWORDS`/`ABILITY_TYPE_DISTINCTIVE_KEYWORD`, права `tag.*`→`keyword.*`, роут `/admin/tags`→`/admin/keywords`, UI-подписи «Теги»→«Признаки». ТР и спеки актуализированы. Верификация `vue-tsc` + `vitest` 169/169
- [x] **RPG-владение (2026-08-02)**: RPG-кластер → `Roleplay/Game` (`RollService`, `DiceRollSpec`/`DiceRollResult`, макросы `IMacroApi`/`MacroApi`/`mockMacroApi`/`Store/macros`/`MacrosSection`/`MacroBarExtension`, dice-UI `DiceRollForm`/`DiceRollResult`/`RollFormExtension`); `Core/Engine`, `Core/User`, `Messages/Chat` — без RPG; Chat — плагинная точка (реестры `ICommandHandler`/`IContentRenderer`/`IChatToolbarExtension`, Game регистрирует `/roll`, рендер `roll`, тулбар); профиль — `registerProfileSection` в Core/User, Game регистрирует `MacrosSection`; `main.ts` — `registerMacroApi` + `registerGamePlugins()`; backend-контракт не менялся (`user.macro.*`, `chat.sendMessage`); верификация `vue-tsc` + `vitest` 169/169
- [x] **RPG-владение (выполнено 2026-08-02)**: весь RPG-кластер перенесён в `Roleplay/Game`; затем п.11 (RBAC)
- [ ] Фаза 2.6 — п.9 (`Engine/index.ts`→`init.ts`), п.10 (тесты→`__tests__/`), п.11 (RBAC, в конец)
- [x] **RPG-владение** (после приведения кода к правилам)
- [x] **п.11 RBAC — реестр прав (выполнено 2026-08-02)**: таксономия прав и админ-секции — реестры в `Core/User/init.ts` (`registerPermissionCategory`/`getPermissionCategories`/`getPermissionKeys`, `registerAdminSection`/`getAdminSections`/`getAdminSectionPermissions`, `isAdmin`, `resetPermissionRegistries`); каждый модуль объявляет категорию в своём `Constant/permissions.ts` и регистрирует через единый `register*Module()` из `init.ts` (User: user+user_group+groups; Rule: rule+keyword+keywords; Space: space; Game: game+плагины Chat+секция профиля; Character: character; Notifications: notification_template+templates); `AccessService` очищен (hasAny/hasAll + super-admin bypass; isAdmin/ADMIN_SECTION_PERMISSIONS → реестр); guard `/admin` — `meta.admin` + ленивая `isAdmin` в `evaluateRouteAccess`; `SideBar`/`PermissionMatrix` читают реестры; админ-права мока ленивы (`groupPermissions()`/`resolveGroupPermissions` — «Администраторы» = `getPermissionKeys()` при доступе, порядок регистраций в бутстрапе неважен); `main.ts` — единый плоский блок `register*Module()` после API-слоя; верификация `vue-tsc` + `vitest` 177/177
- [x] **Фаза 3 (часть `any` + декомпозиция, выполнено 2026-08-03)**: устранены все 106 употреблений `any`/`as any` (0 в production): `FilterValue`-типы (`Core/UI/Dto/FilterValue.ts`), `FieldMeta`, `Row=Record<string,unknown>`, ячейки грида `value:unknown`, `debounce` через generic, extract-функции фильтров → `Core/UI/Utils/filterExtract.ts` (убран дубль в 5 страницах); `RuleSpec` — перечисление спеков в `Enum/` (`AbilitySpec|ItemSpec|RaceSpec|SpeciesSpec|ResourceSpec|CharacteristicSpec`), `CharacteristicSpec` — Dto (formula?: string|null); `RuleValidationService` типизирован (ветвление по типу + in-проверки черновиков); редакторы — проп `spec: RuleSpec|null`, `patch(...value: unknown)`; мок-мусор убран. Декомпозиция: `AbilityEditor` 845→567 (`ZoneCostsEditor`, `ActionCostsEditor`), `SpaceDetailPage` 492→360 (`Space/Component/PublishDialog`, `RULE_TYPE_LABELS` в `Rule/Constant/`). Верификация `vue-tsc` + `vitest` 177/177. Остаток Фазы 3: декомпозиция SmartGrid 552, RaceEditor 450, ProcessEditor 437, ItemEditor 415, RuleEditPage 383, FilterBar 378, MacrosSection 364, UserProfilePage 340.
> **Ревизия правила «юнионы → Enum/» (2026-08-03, context-3):** решение
> «переносить юнионы в `Enum/`» из этого файла — **ошибочное и реверснуто**.
> Дискриминированные юнионы с payload остаются в `Dto/`; `Enum/` — только
> string-literal union. `RuleSpec`-перечисление перенесено обратно `Enum/`→`Dto/`.
> См. `context-3.md` «Решение по техдолгу "юнионы → Enum/"».
- [ ] Фаза 3 — `any`, декомпозиция, миграция `__tests__`

## 1. Ревью правил (7 задач, 2026-08-02)

1. **Сверка фактов с кодом** — совпало: `DimensionalNumber.ts`, `ruleDiff.ts`, паттерн `main.ts`+`VITE_API_MODE`. Расхождения: исключение «Core/Engine» неполное (есть ещё Core/UI); модули-скелеты (Dashboard, Character, Game); тесты в коде разбросаны не по целевой структуре.
2. **Внутренние конфликты** — найден и решён: «в корне только init.ts/routes.ts» vs «__tests__/ в корне» (уточнено: из файлов — только точки сборки, всё остальное подпапки). Пробел: доменная логика Rule (`ruleValidation`, `ruleDiff`) нужна Space → решено ре-экспортом через `Rule/init.ts`.
3. **Пробелы покрытия** — формы/валидация (инлайн-rules vs доменная), роутинг (guard/access), тема/стили. Оставлено: формы частично, роутинг — не фиксировали.
4. **Реалистичность** — mock-кросс-импорты требуют оговорки (решено: допустимы). Остальное реалистично.
5. **Измеримость** — хорошая; неизмеримы по природе YAGNI и «одна задача на файл».
6. **Процесс-утечки** — чисто; `vite build` вынесен из ежедневной верификации (веха релиза, делает пользователь).
7. **Бэклог нарушений** — см. раздел 3.

## 2. Структурные решения (согласовано)

- **Dashboard** → `Roleplay/Home/`; **NotFound** → `Core/UI/Page/NotFoundPage.vue` + `Core/UI/routes.ts`; **CSRF** → `Core/Engine/Http/`; **`DimensionalNumber*.vue`** → `Core/UI/Components/Input/`; **`Core/Utils`+`Core/Composables`** → подпапки `Core/Engine` (всё выполнено).
- **Engine/UI разделение** (новое): Engine = инфраструктура и логика, UI = всё отображаемое (Vue-компоненты и UI-утилиты, напр. `debounce`). Всё UI-шное — в `Core/UI`.
- **Анатомия модуля** (новое): `Interface/` (контракты сервисов), `Dto/` (контракты данных), `Enum/` (перечисления — string-literal union, не TS-enum), `Service/` (классы), `Constant/` (справочники), `Component/` (Vue), `Mock/` (моки), `Utils/` (generic-хелперы, подпапки, YAGNI), `Store/`, `Page/`, `Composables/`, `__tests__/`. Папки — в единственном числе; подпапки допустимы в любой папке; в корне — только `init.ts`/`routes.ts`.
- **Service/ = только классы**: файлов функций нет; доменная логика — класс-сервис (`RuleValidationService`, `*SpecService`); generic-хелпер — функция в `Utils/` модуля (по возможности не писать); value-классы — `Core/Engine/Value/`; мелочь — приватный метод сервиса.
- **Спеки** — `*SpecService` в `Service/` (подпапка по домену, напр. `Service/Spec/`); конфигурация инъектируется из `Constant/`; производные справочники (универсумы полей) выводятся из манифестов, не дублируются.
- **Моки** — в `Mock/` модуля (фикстуры + mock-API-имплементации = in-memory источник данных / фейк-бэк, не сервисы).
- **Типизация тремя слоями**: `Interface/` — контракты сервисов; `Dto/` — контракты данных (формы + дискриминированные юнионы); `Enum/` — string-literal union (реальные TS-enum не используем — номинальная типизация ломает JSON-контракт).
- **RPG-владение (согласовано, владелец `Roleplay/Game`)**: весь RPG-кластер (rollParser, DiceRollSpec, макросы, dice в чате) должен жить в `Roleplay/Game`; `Core/Engine`, `Core/User`, `Messages/Chat` — без RPG; Chat — плагинная точка (generic-контракт, аналог `events` бэка), Game регистрирует `/roll` и рендер результата. Исполняется ПОСЛЕ приведения текущей кодовой базы к правилам.

## 3. Правила: итоговые правки `frontend-rules.md`

- **П.2** — доменная логика — класс-сервис в `Service/`; generic-хелпер — функция в `Utils/` модуля (подпапки, YAGNI); value-классы — `Core/Engine/Value/`.
- **Спеки** — `*SpecService` в `Service/` (подпапка по домену); конфигурация инъектируется из `Constant/`; производные — из манифестов.
- **П.18** — корень модуля: только `init.ts`/`routes.ts`; всё остальное — подпапки. Папки — в единственном числе.
- **П.19** — анатомия: `Interface/`, `Dto/`, `Enum/`, `Service/`, `Constant/`, `Component/`, `Mock/`, `Utils/`, `Store/`, `Page/`, `Composables/`, `__tests__/`; подпапки везде; Engine/UI разделение (всё отображение — UI).
- **П.21** — межмодульный доступ: `init.ts`, `useXxxStore()`, `Interface/`/`Dto/`/`Enum/`; исключение — Core (Engine, UI); mock-импорты допустимы; моки — в `Mock/`.
- **П.38 (типизация)** — три слоя: `Interface/` (контракты сервисов), `Dto/` (контракты данных), `Enum/` (string-literal union, не TS-enum); один тип на файл; без констант/функций.
- **Реактивность** — state = plain-данные (`Dto/`), не class-инстансы; классы-сервисы статeless, вне reactive state.

## 4. Рескан кода под финальные правила (2026-08-02)

**Interface-нарушения (новое):**
- Runtime-код в `Interface/`: `User/Interface/types.ts` (PERMISSION_KEYS/LABELS, ACTION_LABELS), `Rule/Interface/abilityTypes.ts` (константы + функции), `itemTypes.ts` (константы + `pruneItemSpecBySubtypes`), `raceTypes.ts` (`createEmptyRaceSpec` и др.), `resourceTypes.ts` (`createEmptyResourceSpec`). Константы → `Constants/`, функции → `Factory/`.
- Монолиты: `Rule/types.ts` (58), `abilityTypes.ts` (188), `itemTypes.ts` (111), `raceTypes.ts` (83), `User/types.ts` (87), `Chat/types.ts` (57), `Space/types.ts` (34), `Notifications/types.ts` (31) + мелкие `Auth/Tag/Source/types.ts`. `I*Api.ts` — в порядке.

**Подтверждено:**
- `Core/Engine/index.ts` импортируют: `Source/init.ts:1` (`sl`), `SourceApi.ts:1` (`Engine`), `main.ts:51` (`HttpClient, Engine`).
- `DimensionalNumberInput.vue` — 10 импортеров (редакторы Rule, BlockProfileEditor, WeaponProfileEditor).
- `DashboardPage` — только публичные точки (Auth/User/Notifications сторы, NotificationList, FlatTextBtn).
- CSRF — единственный потребитель `main.ts` (17/30/55).
- `ruleValidation`/`ruleDiff` — потребитель `SpaceDetailPage.vue` + тесты.

## 5. Бэклог — зеркало бэк-архитектуры (Фаза 2.5)

**Выполнено ранее (п.1–8 Фазы 2):** CSRF→`Core/Engine/Http/`; `DimensionalNumber*.vue`→`Core/UI/Components/Input/`; `Core/Utils`+`Core/Composables`→подпапки `Core/Engine`; Dashboard→`Roleplay/Home/`; NotFound→`Core/UI/Page/`+routes; `Rule/init.ts` ре-экспорт; Interface-миграция (в старую модель — будет переработана в `Dto/`/`Enum/`); константы фабрик (полная инъекция, производные из манифестов).

**Стадии миграции кода к новым правилам (каждая — `vue-tsc` + `vitest`):**
- [x] **A. `Service/` = классы + `Service/Spec/`** — выполнена 2026-08-02 (см. статус): Spec-фабрики → `*SpecService` (мёртвый `isActionLike` удалён); `ruleValidation`/`ruleDiff` → `RuleValidationService`/`RuleDiffService` (мёртвые `format*` удалены); `validatePassword`→`PasswordValidatorService`; `access`→`AccessService`; `Rule/init.ts`/`Core/User/init.ts` ре-экспортируют сервисы; тесты → сервисные методы.
- [x] **B. `Mock/`** — выполнена 2026-08-02 (см. статус): моки из `Service/` → `{Модуль}/Mock/`; `groupPermissions`→`Core/User/Mock/`; `main.ts` 11 импортов; mock-тесты → `__tests__/Mock/`; `Service/` очищены.
- [x] **C. `Dto/` + `Enum/`** — выполнена 2026-08-02 (см. статус): типы из `Interface/` → `Dto/`; string-literal union → `Enum/` (`AbilityType`, `RuleType`, `RaceCharacteristicMode`, `ChatType`, `ChatVisibility`, `NotifFilter`); split-монолитов (`Chat/Space/Notifications/Auth/Source/Tag types`); `I*Api` остаются в `Interface/`.
- [x] **D. Механические пути** — выполнена 2026-08-02: `Core/Engine/Type/`→`Value/`; `Constants/`→`Constant/` (Rule, Core/User); `Components/`→`Component/` (6 модулей); импорты во всём `src/` обновлены.
- [x] **E. `Core/Engine` нормализация** — выполнена 2026-08-02: `index.ts`→`init.ts` + `sl`→`serviceLocator`; `ServiceLocator`→`Service/`; `Action/ActionResponse`→`Dto/`; `Http/*`→`Service/`+`Interface/`+`Mock/`; `Roll/DiceRollSpec`→`Dto/Roll/`, `Roll/rollParser`→`Service/Roll/RollParserService` (**временно в Core/Engine** — до RPG-пункта); `Text/slugify`→`Rule/Utils/Text/`; `Time/debounce`→`Core/UI/Utils/`.
- [x] **F. Доки + верификация** — выполнена 2026-08-02: TR.md (пути по новой структуре, разделы «Фронтенд (Shell + модули)» и волна 1), context-2.md, финальные `vue-tsc` + `vitest` 169/169. Фаза 2.5 завершена.

**После миграции:**
- [x] п.9 `Engine/index.ts`→`init.ts` + `sl`→`serviceLocator` (поглощён стадией E)
- [x] п.10 тесты → единый `__tests__/` корня модуля (выполнена 2026-08-02: все 16 тестовых файлов — под `__tests__/` модулей и `router/__tests__/`)
- [x] п.11 **Права (RBAC)** — реестр прав модулей (выполнена 2026-08-02, см. статус)

**RPG-владение (выполнено 2026-08-02):**
- Весь RPG-кластер → `Roleplay/Game`: `RollService` (парсер /roll + разрешение бросков), `DiceRollSpec`/`DiceRollResult`, макросы (`IMacroApi`/`MacroApi`/`mockMacroApi`, `Store/macros`, `UserMacro`/`MacroRollSpec`, `mockMacros`, `MacrosSection`, `MacroBarExtension`), dice-UI (`DiceRollForm`, `DiceRollResult`, `RollFormExtension`).
- `Core/Engine`, `Core/User`, `Messages/Chat` — без RPG.
- Chat — плагинная точка: реестры `registerCommandHandler`/`registerContentRenderer`/`registerToolbarExtension` (контракты `ICommandHandler`/`IContentRenderer`/`IChatToolbarExtension`); Game регистрирует `/roll`, рендер результата (`roll`), тулбар (макрос-бар, roll-форма).
- Профиль: `Core/User` — реестр `registerProfileSection`/`getProfileSections`; Game регистрирует `MacrosSection` в профиле.
- Backend-контракт без изменений: `user.macro.getList/create/update/delete`; `chat.sendMessage {chatId, content, rolls}`.
- Верификация `vue-tsc` + `vitest` 169/169.

## 6. Фаза 3

- `any`/`as any`: 108 употреблений / 41 файл (топ: `ruleValidation.ts` 10, `ProcessEditor.vue` 9, `DateTimeFilter.vue` 7, `AbilityEditor.vue` 6, `FilterBar.vue` 6)
- Декомпозиция больших `.vue`: AbilityEditor 841, RaceEditor 453, ProcessEditor 426, ItemEditor 417, RuleEditPage 380, FilterBar 377, MacrosSection 363, UserProfilePage 333, GrantEditor 298, AbilityCard 292, RequirementNodeEditor 290, SpellEditor 284, Messenger 279, ChatInput 274, FormulaInput 257

## 7. Хэнд-офф сессии (2026-08-02, завершение)

**Сделано в сессии:** обновлены `frontend-rules.md` (новая модель, см. Раздел 3); `context-2.md` актуализирован (решения Раздел 2, бэклог Раздел 5); зафиксировано **RPG-владение** (владелец `Roleplay/Game`, весь кластер roll/macros/dice, Chat=плагинная точка, исполняется после миграции).

**Выполнено далее в сессии — стадия A** (`Service/`=классы+`Service/Spec/`, раздел 5 п. A): Spec-фабрики → `Rule/Service/Spec/*SpecService.ts` (инстансы `*SpecService`, удалён мёртвый `isActionLike`); `ruleValidation.ts`→`RuleValidationService.ts` (инъектирует `AbilitySpecService`, удалены мёртвые `formatAbilityStructureError`/`formatRaceStructureError`); `ruleDiff.ts`→`RuleDiffService.ts`; `validatePassword.ts`→`PasswordValidatorService.ts` + `DEFAULT_PASSWORD_POLICY`→`Core/Auth/Constant/passwordPolicy.ts`; `access.ts`→`AccessService.ts` (`static ADMIN_SECTION_PERMISSIONS`). `Rule/init.ts` ре-экспортирует `ruleValidationService`/`ruleDiffService`; `Core/User/init.ts` — `accessService`. Все потребители обновлены (SpaceDetailPage, 7 редакторов/карточек, Auth-страницы, router/access, User/User-страницы, SideBar, usePermissions, TemplateEditPage, TagEditPage, SpaceSettingsPage); импорты в затронутых файлах переведены на `@/`. Тесты `ruleValidation`/`ruleDiff` перенесены в `Rule/__tests__/Service/` (вызовы сервисных методов); `validatePassword.test`/`access.test` → сервисы. Удалены `Rule/Factory/`, старые Service-файлы. Верификация: `vue-tsc --noEmit` чисто, `vitest run` 169/169.

**Выполнено далее в сессии — стадия B** (`Mock/`, раздел 5 п. B): 23 мок-файла выехали из `Service/` в `{Модуль}/Mock/` (Auth, Engine, User, Chat, Notifications, Rule, Rule/Source, Rule/Tag, Space); `Core/Engine/Http/mockCsrf`→`Core/Engine/Mock/`; `groupPermissions.ts`→`Core/User/Mock/` (только mock-потребители — mockGroups/mockAuth); `main.ts` 11 динамических импортов обновлены; кросс-импорты моков (mockChat→mockUsers, mockSpaces→mockRules, mockMacros→mockAuth, mockAuth→groupPermissions) — на алиас `@/`; mock-тесты → `__tests__/Mock/` (`Core/User/__tests__/Mock/mockMacros`/`mockUsers`, `Rule/__tests__/Mock/ruleCatalog`); пустые `Service/__tests__` удалены. В `Service/` остались только API-классы и сервисы. Верификация: `vue-tsc --noEmit` чисто, `vitest run` 169/169.

**Выполнено далее в сессии — стадии C–F (раздел 5 пп. C–F):**
- **C (Dto/+Enum/)**: ~60 файлов типов из `Interface/` → `Dto/` и `Enum/` (AbilityType, RuleType, RaceCharacteristicMode, ChatType, ChatVisibility, NotifFilter); монолиты Chat/Notifications/Space/Source/Tag сплитнуты; `Interface/` остались только 10 `I*Api`; импорты → `@/`.
- **D (механические пути)**: `Core/Engine/Type/`→`Value/`; `Constants/`→`Constant/`; `Components/`→`Component/` (6 модулей).
- **E (Core/Engine нормализация)**: `index.ts`→`init.ts` + `sl`→`serviceLocator`; `ServiceLocator`→`Service/`; `Action/`→`Dto/`+`Service/`; `Http/`→`Service/`+`Interface/`+`Mock/`; `Roll/`→`Dto/Roll/`+`Service/Roll/RollParserService` (временно); `slugify`→`Rule/Utils/Text/`; `debounce`→`Core/UI/Utils/`.
- **F (доки)**: TR.md актуализирован; `Core/UI/Interfaces/`→`Core/UI/Dto/`; финальная верификация `vue-tsc` чисто + `vitest` 169/169.

**Следующий шаг — декомпозиция оставшихся больших `.vue`** (SmartGrid 552, RaceEditor 450, ProcessEditor 437, ItemEditor 415, RuleEditPage 383, FilterBar 378, MacrosSection 364, UserProfilePage 340). `any`/`as any` полностью устранены (0), AbilityEditor и SpaceDetailPage декомпозированы; п.11 (RBAC) и RPG-владение выполнены.

**Ключевые решения, которые уже в правилах:** папки в единственном числе; `Interface/`=контракты сервисов, `Dto/`=контракты данных, `Enum/`=string-literal union (не TS-enum); `Service/`=только классы; `Utils/` разрешён (подпапки+YAGNI); моки→`Mock/`; value-классы→`Core/Engine/Value/`; Engine/UI разделение; реактивность=plain-данные в state; общие хелперы: `slugify`→`Rule/Utils/Text/`, `debounce`→`Core/UI/Utils/`, `rollParser`→`Core/Engine/Service/Roll/RollParserService.ts` (временно, до RPG-пункта).

**Правила окружения:** dev-сервер на порту 3000 держит пользователь (не убивать); верификация `vue-tsc --noEmit` + `vitest run` (см. AGENTS.md).
