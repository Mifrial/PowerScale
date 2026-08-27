# Техническая реализация (ТР) — PowerScale

> **Статус:** Актуализирован 04.08.2026 — плагинная модель Chat (реестры типов/вкладок/команд/рендеров/attachment-процессоров), универсальные вложения сообщений (`ChatAttachment` вместо доменного `rolls`); типы чатов — владельцы-модули (Game/Character); inline-чипы F35 (пользователь/правило, слайдеры, пикер); виртуализация чата (`@tanstack/vue-virtual`), логика загрузки/хранения сообщений (initialized, кап неактивных, ошибки); см. §9 и §12 «Волна рефакторинга Chat»

## Содержание

1. [Обзор проекта](#1-обзор-проекта)
2. [Архитектура](#2-архитектура)
3. [Схема БД](#3-схема-бд)
4. [Авторизация и пользователи](#4-авторизация-и-пользователи)
5. [Пространства](#5-пространства)
   - 5.1 [Ревизии и версии правил](#ревизии-и-версии-правил)
   - 5.2 [Наследование](#наследование-snapshot-copy)
   - 5.3 [SpaceRevision](#spacerevision-снимок-правил-на-момент-публикации)
   - 5.4 [Черновик](#черновик-draft)
   - 5.5 [Контекст просмотра в URL](#контекст-просмотра-в-url-вместо-режимов-в-сторе)
   - 5.6 [Публикация](#публикация-commit)
   - 5.7 [Раздел /space](#раздел-space)
   - 5.8 [Frontend-хранилища](#frontend-хранилища-1)
6. [Правила](#6-правила)
7. [Персонажи](#7-персонажи)
8. [Игры](#8-игры)
9. [Чат](#9-чат)
10. [Уведомления](#10-уведомления)
11. [Разделы интерфейса](#11-разделы-интерфейса)
12. [Волны реализации](#12-волны-реализации)
13. [Решения с фронта](#13-решения-с-фронта)
14. [Очередь первого релиза](#14-очередь-первого-релиза-август-2026)
15. [Очередь после полировки проверки](#15-очередь-после-полировки-проверки)

---

## 1. Обзор проекта

**PowerScale** — система управления RPG-персонажами и правилами.

### Ключевые сущности

- **Пользователь** — учётная запись (имя, логин, пароль). Набор полей может расширяться.
- **Группа пользователей** — набор прав. Пользователь может быть в нескольких группах, права суммируются.
- **Правило** — единица контента. Имеет **глобальный ID** (сквозной для пространств и времён).
- **Пространство** — изолированная среда правил. По умолчанию «Разработка» и «Актуальные правила». Редактирование возможно в любом пространстве. Пространство имеет **revision (x)** — автоинкремент при каждой публикации (см. решение 30.1).
- **Версия правила** — комбинация `(rule_global_id, space_id, created_at)`. Две версии одного правила в разных пространствах — разные. Версии не перетираются.
- **Версия правил** (набор) — состояние всех правил в данном пространстве на данный момент времени.
- **Персонаж** — привязан к версии правил (`space_id`, `created_at`). Сам версионируется.
- **Игра** — обёртка над версией правил. К ней крепятся персонажи той же версии.

### Версионность правил

Каждое изменение порождает новую запись `rule_versions(rule_id, space_id, created_at, content...)`. Старые версии остаются. При просмотре пространства на момент T показываются самые новые версии правил с `created_at <= T` в этом пространстве.

### Пространства — наследование

✅ **Выбрано: копирование снепшота (snapshot copy)**

- При наследовании пространство B от A в момент T: все актуальные версии правил из A копируются в B с теми же `created_at`.
- После копирования B полностью независим от A.
- Наследование может быть цепочкой (A → B → C). Каждое копирование независимо — цепочка не создаёт дополнительной сложности.
- Простота запросов: `WHERE space_id = ?` без дополнительных условий.

### Публикация правил между пространствами

- У каждого правила глобальный UUID, единый для всех пространств.
- Публикация создаёт новую версию того же `rule_id` в целевом пространстве.
- При публикации показывается diff: последняя версия в целевом пространстве → публикуемая версия.
- Если в целевом пространстве правило правили вручную — diff покажет расхождение, админ видит это.

### Удаление правил

✅ **Маркер-версия**

Создаётся новая запись в `rule_versions` с `active = false`. Все поля, кроме `active`, — такие же, как в последней активной версии (копия). `created_at` = время удаления. При просмотре пространства на момент T: последняя версия правила с `active = false` означает, что правило удалено. Удаление — частный случай версии, единообразно с soft-delete других сущностей.

---

## 2. Архитектура

### Модульная система

- Все модули строго двухуровневые: `Namespace/ModuleName/`
- ModuleManager: `includeModule($group, $module)`, `requireModule(...)`
- Автозагрузка только Core/* при старте, остальные лениво
- module.config.php содержит: services, routes, events

### DI / ServiceLocator

**Бэкенд:**

- ServiceLocator::getInstance() — основной контейнер
- Регистрация по **строковому коду** (dot-нотация): `'Core.User.Service.User'`
- Alias по `::class` для IDE-совместимости
- Фабрики: `fn($serviceLocator) => new UserRepository(...)`
- Параметр фабрики называется `$serviceLocator` (не $sl)

**Фронтенд:**

- `Core/Engine/Service/ServiceLocator.ts` — generic SL (set/get/reset), export `serviceLocator`
- Per-модуль `init.ts`: `registerXApi(impl)` + `getXApi(): IXApi`
- `main.ts`: регистрация всех API (mock или real), вызов `getCsrfApi().initToken()`
- **Core-правило:** Core-модули не импортируют из не-Core; не-Core могут импортировать из Core

### SmartTable (ORM)

- Единственная точка доступа к данным
- Abstract/Field/BaseField — абстрактный класс (name, label, required, multiple, default)
- Abstract/Table/SmartTableDefinition — абстрактный (getMap → массив BaseField)
- Любое поле может быть множественным (флаг multiple)
- ReferenceField — ссылка на другую таблицу
- Модульные hydrator'ы регистрируются через `'smarttable_fields'` в module.config.php
- Repository получает SmartTable через `$serviceLocator->get('Core.SmartTable.Service')->open($tableName)`

### Фронтенд (Shell + модули)

**Структура (актуализировано 2026-08-02, фаза 2.5 — анатомия модулей: `Interface/`=контракты сервисов, `Dto/`=контракты данных, `Enum/`=string-literal union, `Service/`=классы, `Constant/`=справочники, `Component/`=Vue, `Mock/`=моки, `Utils/`, `Store/`, `Page/`, `__tests__/`; папки в единственном числе; в корне модуля — только `init.ts`/`routes.ts`):**

```
src/
  modules/
    Core/
      Engine/
        init.ts                 — публичная точка модуля (HttpClient, Engine, ActionResponse, register/getCsrfApi)
        Service/
          ServiceLocator.ts     — DI-контейнер (set/get/reset, ключи — serviceCode) → export serviceLocator
          HttpClient.ts         — fetch-клиент, интерцепторы (401, CSRF)
          CsrfApi.ts            — чтение csrf-token из document.cookie
          Engine.ts             — runAction-клиент (fetch + CSRF + baseUrl)
        Interface/
          ICSRFApi.ts           — getToken(), initToken()
        Dto/
          ActionResponse.ts     — DTO ответа runAction
          DimensionalNumber.ts  — plain-типы DimensionalNumberValue/DimensionalNumberBaseRange
          HttpClientConfig.ts   — конфиг HttpClient
          HttpResponse.ts       — HTTP-конверт ответа HttpClient
        Mock/
          mockCsrf.ts           — генерация UUID в памяти
        Value/
          DateTime.ts
          DimensionalNumber.ts  — value-класс (plain-тип DimensionalNumberValue — в Dto/)
        Composables/
          useAbortable.ts       — AbortController composable
      Auth/
        Interface/ IAuthApi.ts
        Dto/ PasswordPolicy.ts, Session.ts (anon|guest|user{userId})
        Service/ AuthApi.ts, PasswordValidatorService.ts
        Constant/ defaultPasswordPolicy.ts — DEFAULT_PASSWORD_POLICY
        Mock/ mockAuth.ts, mockAuthApi.ts
        Store/ auth.ts
        init.ts                 — registerAuthApi() / getAuthApi()
      User/
        Interface/ IUserApi.ts, IGroupApi.ts; PermissionAction.ts, PermissionCategory.ts, AdminSection.ts (ранее в IPermissionRegistry.ts — баррель-ре-экспорт)
        Dto/ User.ts, Group.ts, ProfileSection.ts, GroupMember.ts
        Service/ UserApi.ts, GroupApi.ts, AccessService.ts (hasAny/hasAll, super-admin bypass)
        Constant/ permissions.ts, usersGridManifest.ts, groupsGridManifest.ts — категории прав Core/User (user, user_group) + админ-секция groups
        Mock/ mockUserApi.ts, mockUsers.ts, mockGroupApi.ts, mockGroups.ts, groupPermissions.ts, mockGroupMembers.ts
        Utils/ profile.ts — initials()/displayName()
        Component/ UserForm.vue, PermissionMatrix.vue, ProfileInfoCard.vue, ProfileAuthCard.vue, ProfileGroupsCard.vue, DeactivateUserDialog.vue, UserProfileSlider.vue
        Store/ users.ts, groups.ts
        init.ts                 — registerUserApi()/getUserApi(); registerProfileSection(); реестр прав:
                                  registerPermissionCategory()/getPermissionCategories()/getPermissionKeys(),
                                  registerAdminSection()/getAdminSections()/getAdminSectionPermissions(), isAdmin(),
                                  resetPermissionRegistries(); registerUserModule()
      UI/
        Component/
          Grid/                 — SmartGrid (композиция) + Grid/header/GridHeader, GridRow, GridFooter, ScrollEars; cells/registry, gridSettings
          FilterBar/            — FilterBar (композиция) + FilterPopup, FilterChips; handlers/registry, filterSettings, filterValues
          Input/                — PasswordField.vue, DimensionalNumber.vue, DimensionalNumberInput.vue, ClampedNumberField.vue
        Composables/
          useVModelSync.ts      — v-model sync-паттерн (inner + deep-watch + commit), 12 редакторов
          useColumnResize.ts, useColumnDrag.ts, useScrollEars.ts — интеракции Grid
          useGridPage.ts        — сортировка/пагинация/фильтры для Grid-страниц (кросс-модульный)
          useFilterBuffer.ts    — буфер фильтров FilterBar (редактирование/apply/reset)
        Constant/
          perPageOptions.ts, filterModeOptions.ts, uiStorage.ts
        Dto/
          ColumnDefinition.ts, FieldDefinition.ts, FieldMeta.ts, ..., PickerItem.ts
        Utils/
          debounce.ts           — UI-утилита
    Messages/
      Chat/
        Interface/ IChatApi.ts, ICommandHandler.ts, IRenderer.ts, IChatToolbarExtension.ts,
                  IAttachmentProcessor.ts, ITokenSource.ts, IChatType.ts, IChatTab.ts
        Dto/ Chat.ts, ChatMessage.ts, ChatAttachment.ts, ParsedCommand.ts, SyncResponse.ts,
            MemberInfo.ts, ChatSyncConfig.ts, ChatToolbarContext.ts, InlineSegment.ts
        Enum/ ChatVisibility.ts, ChatPermission.ts
        Constant/ avatarColors.ts; Chat/ (BASE_CHAT_TYPES, BASE_CHAT_TABS, MAX_STORED=500, PAGE_SIZE)
        Service/ ChatApi.ts, ChatSyncService.ts (SSE/polling)
        Mock/ mockChatApi.ts, mockChat.ts
        Composables/ useChatVirtualScroll.ts — @tanstack/vue-virtual (anchorTo end, followOnAppend, measureElement)
        Utils/ inlineContent.ts — parseInlineContent (токены [[type:params]] → InlineSegment[]), inlineContentToText
        Store/ chat.ts (ChatState.initialized; ошибки chatsError/chatError/actionError; кап неактивных)
        Component/ ChatMessageList.vue (виртуализированный список), ChatMessageRow.vue (сообщение)
        init.ts                 — registerChatApi()/getChatApi(); реестры плагинов Chat:
                                  registerCommandHandler()/getCommandHandlers(),
                                  registerContentRenderer()/getContentRenderer(),
                                  registerInlineRenderer()/getInlineRenderer(),
                                  registerTokenSource()/getTokenSources(),
                                  registerAttachmentProcessor()/getAttachmentProcessor(),
                                  registerToolbarExtension()/getToolbarExtensions(),
                                  registerChatType(s)/getChatTypes(), registerChatTab(s)/getChatTabs();
                                  базовые типы/вкладки (private|group) — владелец Messages (BASE_CHAT_*),
                                  инициализируются императивно (BASE первым); чип [[user:...]] + источник 'user'
      Notifications/
        Interface/ INotificationApi.ts, INotificationTemplateApi.ts
        Dto/ Notification.ts, NotificationAction.ts, NotificationTemplate.ts, NotificationButton.ts
        Enum/ NotifFilter.ts
        Service/ NotificationApi.ts, NotificationTemplateApi.ts
        Mock/ mockNotificationApi.ts, mockNotifications.ts, mockTemplateApi.ts, mockTemplates.ts
        Store/ notifications.ts, templates.ts
        init.ts                 — registerNotificationApi() / getNotificationApi()
    Roleplay/
      Rule/
        Interface/ IRuleApi.ts
        Dto/ Rule.ts, RuleVersion.ts, RuleSpec.ts, Mechanic.ts, ResourceSpec.ts, CreateRuleData.ts, UpdateRuleData.ts, RuleFormState.ts, Ability/, Item/, Race/
        Enum/ RuleType.ts, Ability/AbilityType.ts, Race/RaceCharacteristicMode.ts  (только string-literal union; юнионы-контракты — в Dto/)
        Service/ RuleApi.ts, RuleValidationService.ts, RuleDiffService.ts, RuleReferenceService.ts, RuleDraftService.ts, Spec/ (Ability, Item, Race, Resource, Process)
        Constant/ RULE_TYPES.ts, RULE_TYPE_LABELS.ts, PROCESS_TRANSITION_MODES.ts, CHARACTERISTIC_FORMULA_TYPES.ts, keywordsGridManifest.ts, Ability/ (ABILITY_*_FIELDS, GRANT_TYPES, REQUIREMENT_TYPES, ...), Item/ (ITEM_SUBTYPES, ITEM_CATEGORIES, WEAPON_PROFILE_TYPES, ...)
        Mock/ mockRuleApi.ts, mockRules.ts, mockMechanics.ts
        Component/ — редакторы (Ability, Item, Race, Spell, ...), карточки, FormulaInput; дочерние секции редакторов рядом (Editors/Item/, RaceCharacteristicsEditor, RaceAbilitiesEditor, InheritancePreview, ProcessStepEditor, ProcessTransitionEditor, ProcessStartFailureEditor, RuleConflictDialog); Chat/ (RuleChip, RuleSlider — inline-чип правила в чате)
        Utils/ Rule/formMapper.ts, Text/slugify.ts
        Store/ rules.ts, draftRules.ts
        init.ts, routes.ts
        Dto/ Source-правила (тип rule 'source'): источники модификаторов — часть правил, не справочник
      Game/
        Interface/ IMacroApi.ts
        Dto/ DiceRollSpec.ts, DiceRollResult.ts, UserMacro.ts, MacroRollSpec.ts
        Service/ RollService.ts (парсер /roll + разрешение бросков + validateRollSpec/formatRollSpecText, синглтон rollService), MacroApi.ts
        Constant/ rollLimits.ts
        Mock/ mockMacroApi.ts, mockMacros.ts
        Store/ macros.ts
        Component/ DiceRollForm.vue, DiceRollResult.vue, RollFormExtension.vue, MacroBarExtension.vue, MacrosSection.vue (композиция) + Macros/MacroForm.vue, Macros/MacroRollEditor.vue
        Dto/ RollForm.ts
        Page/ GamesPage.vue
        init.ts                 — registerMacroApi()/getMacroApi(); registerGameModule() (права + плагины Chat: типы/вкладки game|game_discussion, команда /roll, рендер DiceRollResult, attachment-processor 'roll', тулбар-расширения + секция профиля)
        routes.ts
        Interface/ IKeywordApi.ts — признаки (keywords), плоский справочник
        Dto/ Keyword.ts, Service/ KeywordApi.ts, Mock/ mockKeywordApi.ts, mockKeywords.ts, Store/ keywords.ts
        Page/ KeywordsListPage.vue, KeywordEditPage.vue
      Space/
        Interface/ ISpaceApi.ts
        Dto/ Space.ts, SpaceRevision.ts, SpaceRevisionMeta.ts, SpaceCreateData.ts, SpaceUpdateData.ts
        Service/ SpaceApi.ts
        Mock/ mockSpaceApi.ts, mockSpaces.ts
        Store/ spaces.ts, spaceRevision.ts
        init.ts, routes.ts
      Home/
        Page/ DashboardPage.vue
      Game/, Character/ — стабы
  router/
    index.ts, access.ts          — route guard (права через AccessService)
  shell/
    Shell.vue                   — корневой компонент (topbar + sidebar + router-view)
    SideBar.vue                 — collapsible rail menu
  App.vue                       — точка входа (ErrorBoundary)
  main.ts                       — инициализация Vite + плагины
  plugins/
    vuetify.ts                  — Vuetify config
```

**Конвенция страниц:** страницы живут в `modules/<Module>/Page/*.vue`; корневой layout — `shell/`, роутер — `router/index.ts`.

**Волна 2 фронта (2026-08-03, приведение к frontend-rules.md):**

- Механика: все импорты в `src/` через `@/` (относительные пути запрещены); порядок тегов SFC строго `<script setup lang="ts">` → `<template>` → `<style scoped>`.
- Типизация подтверждена: `Enum/` — только string-literal union; дискриминированные юнионы с payload (Requirement, Grant, Formula, ProcessTransition, SpellComponent, AbilitySpec) — в `Dto/`. `RuleSpec` (юнион) перенесён `Enum/` → `Dto/RuleSpec.ts`; `AbilitySpec` переведён на `AbilityType`.
- Декомпозиция 8 больших `.vue`: SmartGrid, FilterBar, RaceEditor, ProcessEditor, ItemEditor, RuleEditPage, MacrosSection, UserProfilePage → дочерние компоненты + composables (см. структуру выше). Мутации спека вынесены из редакторов.
- Спеки/доменная логика: `RaceSpecService`/`ItemSpecService`/`AbilitySpecService` расширены апдейтерами и фабриками дефолтов (Grant/Requirement/SpellComponent/SpellDuration); созданы `ProcessSpecService`, `RuleReferenceService` (общие ссылочные lookup'ы редакторов), `RuleDraftService`, `Utils/Rule/formMapper.ts`.
- Дедуп: `typeLabels` (RuleDetailPage) → `Constant/RULE_TYPE_LABELS`; `modeOptions` (Number/DateTime/StringFilter) → `Core/UI/Constant/filterModeOptions`; v-model-sync паттерн → `Core/UI/Composables/useVModelSync` (12 редакторов); `initials`/`displayName` → `Core/User/Utils/profile`; `filteredUsers` (users.ts) генерализован; константы конфигурации → `Constant/` модулей.
- Мелочи: `PickerItem` → `Core/UI/Dto`; `FormRef` → тип `VForm` из `vuetify/components` (удалён `Auth/Dto/LoginForm.ts`); `mockGroupMembers` → `Core/User/Mock`; `Chat/Config/` → `Constant/`; нативные элементы → Vuetify (DiceRollForm, MessengerTabs); `rollSummary` в ChatInput; `IPermissionRegistry` → по одному типу на файл.

**Волна 3 фронта (2026-08-03, детальная вычитка Core/Engine + уточнение правил):**

- Правила `frontend-rules.md` §2/§3: (1) типы — только в `Dto/`/`Interface/`/`Enum/`, один на файл; в файлах кода именованных типов нет, тривиальная одноразовая форма — inline; (2) `Enum/`/`Dto/` разделение сохранено (граница: плоский string-literal union vs структуры/юнионы с payload); (3) композаблы — только корневая `Composables/` модуля (в `Component/` нет); (4) общий UI — в `Core/UI`, `Core/Engine` не импортирует `Core/UI` (UI → Engine); (5) именование по доменному смыслу/терминологии бэка, generic-имена запрещены при доменном; (6) комментарии «только почему»; (7) поля-члены классов, не переприсваиваемые после инициализации, — `private readonly`.
- `Core/Engine`: типы `DimensionalNumberValue`/`DimensionalNumberBaseRange` → `Dto/DimensionalNumber.ts`; `HttpClientConfig` → `Dto/HttpClientConfig.ts`; `HttpResponse` → `Dto/HttpResponse.ts`; `init.ts` — ре-экспорты из `Dto/`, удалён мёртвый ре-экспорт `serviceLocator`; `Engine.runAction` — `encodeURIComponent`; `ServiceLocator` — имена `serviceCode`/`service`/`services`; `useAbortable` — без лишнего `ref`; `DateTime` — приватный хелпер относительного формата; тест → `__tests__/Service/serviceLocator.test.ts`.
- `Core/UI`: `useGridPage` → `Core/UI/Composables/` (устранена зависимость Engine→UI); `useFilterBuffer` → `Core/UI/Composables/`.
- `readonly`-члены классов: инжектированные зависимости (15 файлов, фикс по Sonar) + `ServiceLocator.services`/`CsrfApi.cookieName`.

**Волна тулинга (2026-08-03, ESLint + Prettier):**

- devDeps: `prettier`, `eslint` 10, `typescript-eslint` 8, `eslint-plugin-vue` 10, `eslint-config-prettier`, `vue-eslint-parser`. Конфиги: `.prettierrc.json` (2 пробела, semi, singleQuote, trailingComma all, printWidth 120), `eslint.config.js` (flat). Скрипты: `format`/`format:check`/`lint`/`lint:check`.
- ESLint-правила (механический гарант frontend-rules.md): `no-explicit-any`, `consistent-type-imports`, `prefer-readonly` (typed-linting для `src/**/*.ts`), `no-unused-vars` (`_`-префикс игнорируется), `no-non-null-assertion`, `ban-ts-comment`, `array-type`, `eqeqeq` (`null: 'ignore'`), `padding-line-between-statements` (пустая строка перед return), `no-debugger`, `no-console` (warn, allow error/warn), `vue/block-order` (бывш. `component-tags-order`), `vue/require-explicit-emits`, `vue/multi-word-component-names`. `no-undef` off (стандарт TS); в тестах ослаблены `no-explicit-any`/`no-non-null-assertion`.
- Зачистка кода по линту: 15 `!`-assertions, мёртвые импорты, пустые catch (why-комментарии), дубликат `spec` в ItemEditor (ref → `draft`), пустые интерфейсы → type-алиасы, `Shell`→`AppShell`, `Messenger`→`ChatMessenger`.
- Изменения правил: frontend-rules.md §3 — «механика закреплена линтером»; §6 — верификация включает `lint`/`format:check`.
- Открытия: `vue/component-tags-order` → `vue/block-order` (v10); `no-unnecessary-type-assertion` сломал сборку автофиксом (снял необходимые `as HTMLElement`) → правило исключено, код на generic `querySelector<HTMLElement>`.

**Волна рефакторинга Chat (2026-08-04, плагинная модель + универсальные вложения):**

- Зависимость `Messages/Chat → Roleplay/Game` устранена из production-кода (решение P2-1 из ревью): `ChatMessage.rolls: DiceRollSpec[]` → `attachments: ChatAttachment[]` (`{ type, payload }`); `IChatApi.sendMessage(chatId, content, attachments)`. `ChatType` (открытый union) удалён — типы объявляются через `IChatType`; удалены `Enum/ChatType.ts`, `Constant/CHAT_CONFIG.ts`, `chatIcon.ts`, `chatColor.ts`, `chatTabs.ts`, `Dto/ParsedRollCommand.ts`.
- Введены реестры плагинов Chat в `init.ts`: `registerCommandHandler`, `registerContentRenderer`, `registerInlineRenderer`, `registerTokenSource`, `registerAttachmentProcessor`, `registerToolbarExtension`, `registerChatType(s)`, `registerChatTab(s)` + соответств. getter'ы, `getChatIcon`/`getChatColor`. Базовые `private`/`group` — владелец Messages (`Constant/Chat/BASE_CHAT_TYPES`/`BASE_CHAT_TABS`), инициализируются императивно (BASE первым, без ленивого флага). Единый тип `IRenderer` (вместо `IContentRenderer`/`IInlineRenderer`). Один экспорт на файл.
- Доноры регистрируют своё: `Roleplay/Game/init.ts` — типы/вкладки `game`/`game_discussion`, команда `/roll`, рендер `DiceRollResult`, attachment-processor `roll` (`computeRollResult`+`describe`), тулбар; `Roleplay/Character/init.ts` — тип/вкладка `character_discussion`. Плагинные типы/вкладки убраны из `Messages` (восстановлены игровые чаты, потерянные на предыдущем шаге).
- `ChatInput.vue` — `pendingAttachments` + `addAttachment`/`removeAttachment`, чип вложения через `IAttachmentProcessor.describe`; `ChatMessenger.vue` рендерит вложения через `getContentRenderer(att.type)`; `RollService.parseRollCommand` возвращает `ParsedCommand` с `attachments:[{ type: ROLL_ATTACHMENT_TYPE, payload: spec }]` (`ROLL_ATTACHMENT_TYPE='roll'`; контакт в `Roleplay/Game/Dto/`).
- **Inline-чипы (F35, полная реализация):** `Utils/inlineContent.ts` — `parseInlineContent` (токены `[[type:param1,param2]]`) + `inlineContentToText` (плоский текст без чипов); `ChatMessenger` рендерит сегменты текста через `getInlineRenderer(type)` (чип получает `segment`); `ChatUserChip` (пользователь, владелец Chat) + `RuleChip`/`RuleSlider` (правило, владелец Rule); placeholder «Объект скрыт» при недоступном объекте; пикер «Вставить ссылку» в `ChatInput` через реестр `ITokenSource` (источники `user` в Chat, `rule` в Rule); `IRenderer.describe(segment)` — человекочитаемая подпись для превью списка чатов (`ChatList` использует `inlineContentToText`); `UserProfileSlider` перенесён в `Core/User/Component` (общий компонент); тест `inlineContent.test.ts`.
- **Виртуализация и логика сообщений (P2-2/P2-3/P2-4/P3-4):** `@tanstack/vue-virtual`; `ChatMessageList`+`ChatMessageRow`+`useChatVirtualScroll` (список вынесен из `ChatMessenger`); стор: `initialized`, кап неактивных `MAX_STORED=500`, `chatsError`/`chatError`/`actionError`, удалён `renderedMessages`; mock `SYNTHETIC_COUNT=2000`; тесты стора (7 новых). Подробно — §9 «Загрузка и рендер сообщений».

**Архитектурные решения:**

- DI: `serviceLocator` (генерализованный set/get/reset, `Core/Engine/Service/ServiceLocator.ts`)
- Per-модуль `init.ts`: `registerXApi(impl)` + `getXApi(): IXApi`
- `main.ts`: регистрация всех API (mock или real), вызов `getCsrfApi().initToken()`
- **CSRF:** `HttpClient` принимает коллбэк `getCsrfToken`; добавляет заголовок `X-CSRF-Token` на POST-запросы
- **Core-правило:** Core-модули не импортируют из не-Core; не-Core могут импортировать из Core
- **Auth:** httpOnly cookie (фронт не хранит токен), сессия в памяти
- **User:** отдельный модуль (не в Auth), хранит currentUser

**Стилевые правила (фронт):**

- Стилизация через Vuetify-классы и CSS-переменные темы (`rgb(var(--v-theme-*))`). Хардкод цветов не использовать.
- Inline-стили (`:style`) допустимы **только** для динамических значений (URL импортированного ассета, вычисляемые значения). Всё остальное — через CSS-классы.
- `!important` запрещён. Для переопределения Vuetify-стилей использовать селекторы с более высокой специфичностью (например, `.my-class.v-btn--variant-text` вместо `!important`).

---

## 3. Схема БД

> **Соглашения:**
>
> - `id` — автоинкремент (SERIAL/BIGINT UNSIGNED AUTO_INCREMENT), если не указано иное.
> - `created_at`, `updated_at` — TIMESTAMP.
> - `BOOL` — TINYINT(1).
> - `→` — внешний ключ.
> - Индексы перечислены под каждой таблицей.

### Файлы

```sql
files(
  id, owner_id → users.id NOT NULL,
  filename VARCHAR,         -- имя на диске
  original_name VARCHAR,    -- оригинальное имя при загрузке
  mime VARCHAR,
  size INT,
  path VARCHAR,
  created_at
)
INDEX: (owner_id)
```

### Аутентификация

```sql
sessions(
  id, user_id → users.id NOT NULL,
  token VARCHAR UNIQUE,        -- хеш refresh-токена
  expires_at TIMESTAMP NOT NULL,
  created_at
)
INDEX: (user_id), (token)

password_reset_tokens(
  id, user_id → users.id NOT NULL,
  token VARCHAR UNIQUE,        -- одноразовый токен
  expires_at TIMESTAMP NOT NULL,
  used BOOL DEFAULT false,
  created_at
)
INDEX: (token), (user_id)
```

### Пользователи, группы, права

```sql
users(
  id, login VARCHAR UNIQUE NOT NULL,
  password_hash VARCHAR NOT NULL,
  email VARCHAR UNIQUE,        -- для сброса пароля, может быть пустым
  first_name VARCHAR, last_name VARCHAR, nickname VARCHAR,
  avatar_file_id → files.id NULL,
  super_admin BOOL DEFAULT false NOT NULL,
  active BOOL DEFAULT true NOT NULL,
  deactivated_until DATE NULL, deactivate_reason TEXT NULL,
  created_at, updated_at
)
INDEX: (email), (login)

user_settings(
  user_id → users.id PRIMARY KEY,
  data_json JSON NOT NULL,              -- {"notif_filter":"unread","table_columns":{"characters":"name,race,status"},"theme":"dark", …}
  updated_at
)

groups(
  id, name VARCHAR NOT NULL,
  active BOOL DEFAULT true NOT NULL,
  created_at
)

user_groups(
  user_id → users.id NOT NULL,
  group_id → groups.id NOT NULL,
  protected BOOL DEFAULT false NOT NULL,  -- true для супер-админа в группе Администраторы
  PRIMARY KEY (user_id, group_id)
)

group_permissions(                         -- глобальные права
  group_id → groups.id NOT NULL,
  permission_key VARCHAR NOT NULL,         -- user.* | user_group.* | game.* | space.* | character.* | keyword.* | rule.* | notification_template.*
  PRIMARY KEY (group_id, permission_key)
)
```

### Пространства и правила

```sql
spaces(
  id, code VARCHAR UNIQUE NOT NULL,          -- слаг пространства (стабильный идентификатор в URL, напр. 'razrabotka')
  name VARCHAR NOT NULL, description TEXT,
  owner_id → users.id NOT NULL,
  revision INT DEFAULT 0 NOT NULL,           -- автоинкремент при каждой публикации
  active BOOL DEFAULT true NOT NULL,
  created_at
)
INDEX: (owner_id)
INDEX: (code)

space_moderators(
  space_id → spaces.id NOT NULL,
  user_id → users.id NOT NULL,
  PRIMARY KEY (space_id, user_id)
)

space_permissions(                         -- per-space права
  space_id → spaces.id NOT NULL,
  assignee_type VARCHAR NOT NULL,          -- 'group' | 'user'
  assignee_id INT NOT NULL,
  permission_key VARCHAR NOT NULL,         -- space.view | space.comment | space.edit
  UNIQUE (space_id, assignee_type, assignee_id, permission_key)
)
INDEX: (space_id)

space_revisions(                           -- мета-информация о ревизиях
  id, space_id → spaces.id NOT NULL,
  revision INT NOT NULL,                   -- номер ревизии (версия space.revision на момент публикации)
  published_at TIMESTAMP NOT NULL,         -- дата публикации (точка среза для rule_versions.created_at)
  changed_count INT DEFAULT 0,             -- сколько правил изменилось в этой ревизии
  UNIQUE (space_id, revision)
)
INDEX: (space_id, revision)

tags(
  id, code VARCHAR UNIQUE NOT NULL,
  name VARCHAR NOT NULL, description TEXT,
  active BOOL DEFAULT true NOT NULL
)

rules(                                     -- реестр правил (глобальный ID)
  rule_id UUID PRIMARY KEY,
  code VARCHAR UNIQUE NOT NULL,            -- глобальный семантический ключ правила (общий для всех версий и пространств; задаётся при создании, неизменяем)
  type VARCHAR NOT NULL                    -- simple | race | species | characteristic | resource | points | ability | item | damage_type
)

mechanics(                               -- механики: логика обработки правил
  id,
  code VARCHAR UNIQUE NOT NULL,            -- 'six_one_rule', 'double_strike'
  name VARCHAR NOT NULL,
  description TEXT,
  version VARCHAR NOT NULL,                -- semver: '4.5.0'
  created_at
)
INDEX: (code, version)

rule_versions(                             -- версионирование правил
  id, rule_id → rules.rule_id NOT NULL,
  space_id → spaces.id NOT NULL,
  created_at TIMESTAMP NOT NULL,           -- (rule_id, space_id, created_at) UNIQUE — версионный timestamp
  name VARCHAR NOT NULL, description_html TEXT, spec_json JSON,
  mechanic_id → mechanics.id NULL,         -- ссылка на механику (nullable — не все правила имеют механику)
  active BOOL DEFAULT true NOT NULL        -- false = правило удалено (маркер-версия)
)
INDEX: (space_id, rule_id, created_at DESC),   -- основной запрос «на момент T»
       (rule_id),                              -- все версии одного правила
       (space_id),                             -- все версии в пространстве
       (mechanic_id)                           -- поиск правил по механике

rule_keywords(
  rule_version_id → rule_versions.id NOT NULL,
  keyword_id → keywords.id NOT NULL,
  PRIMARY KEY (rule_version_id, keyword_id)
)
```

### Наборы правил (Rule Sets)

```sql
rule_sets(
  id, name VARCHAR NOT NULL, slug VARCHAR UNIQUE NOT NULL,
  description TEXT
)

rule_set_dependencies(
  rule_set_id → rule_sets.id NOT NULL,
  depends_on_set_id → rule_sets.id NOT NULL,
  PRIMARY KEY (rule_set_id, depends_on_set_id)
)

rule_set_rules(
  rule_id → rules.rule_id NOT NULL,
  rule_set_id → rule_sets.id NOT NULL,
  PRIMARY KEY (rule_id, rule_set_id)
)

game_rule_sets(
  game_id → games.id NOT NULL,
  rule_set_id → rule_sets.id NOT NULL,
  PRIMARY KEY (game_id, rule_set_id)
)
```

### Народы, языки, письменность

```sql
nations(
  id, name VARCHAR NOT NULL, description TEXT,
  short_description VARCHAR, keywords JSON
)

nation_races(
  nation_id → nations.id NOT NULL,
  race_id → rules.rule_id NOT NULL,
  PRIMARY KEY (nation_id, race_id)
)

languages(
  id, name VARCHAR NOT NULL, description TEXT,
  language_group VARCHAR
)

similar_languages(
  language_id → languages.id NOT NULL,
  similar_to_id → languages.id NOT NULL,
  PRIMARY KEY (language_id, similar_to_id)
)

nation_languages(
  nation_id → nations.id NOT NULL,
  language_id → languages.id NOT NULL,
  PRIMARY KEY (nation_id, language_id)
)

writing_systems(
  id, name VARCHAR NOT NULL, type VARCHAR NOT NULL  -- alphabet | hieroglyphs | …
)

language_writing_systems(
  language_id → languages.id NOT NULL,
  writing_system_id → writing_systems.id NOT NULL,
  PRIMARY KEY (language_id, writing_system_id)
)
```

### Игры

```sql
games(
  id, name VARCHAR NOT NULL, description TEXT,
  short_description VARCHAR,             -- краткое описание для карточки в списке
  owner_id → users.id NOT NULL,
  space_id → spaces.id NOT NULL,
  rules_version_at TIMESTAMP NOT NULL,     -- срез версии правил
  status VARCHAR DEFAULT 'draft' NOT NULL, -- draft | recruiting | in_process | paused | playing | completed
  visibility VARCHAR DEFAULT 'all' NOT NULL, -- all | friends | players | invited | whitelist
  join_policy VARCHAR DEFAULT 'anyone' NOT NULL, -- anyone | friends | invite_only | whitelist
  image_file_id → files.id NULL,
  os_points_limit INT NULL,
  ol_points_limit INT NULL,
  or_points_limit INT NULL,
  money_limit INT NULL,                 -- бюджет на закупку предметов (в gm)
  tags_json JSON,           -- теги игры (жанр, сеттинг, стиль)
  spec_json JSON,           -- доп. ограничения (запретные теги и т.д.)
  active BOOL DEFAULT true NOT NULL,
  created_at
)
INDEX: (owner_id), (space_id), (status)

game_members(
  game_id → games.id NOT NULL,
  user_id → users.id NOT NULL,
  role VARCHAR DEFAULT 'player' NOT NULL,  -- owner | gm | player
  PRIMARY KEY (game_id, user_id)
)
INDEX: (user_id)                           -- игры пользователя

game_member_permissions(                   -- инд. права игроков поверх роли
  game_id → games.id NOT NULL,
  user_id → users.id NOT NULL,
  permission_key VARCHAR NOT NULL,         -- game.edit | game.moderate | game.manage
  PRIMARY KEY (game_id, user_id, permission_key)
)

game_invitations(
  id, game_id → games.id NOT NULL,
  inviter_id → users.id NOT NULL,
  invitee_id → users.id NOT NULL,
  status VARCHAR NOT NULL,                 -- sent | viewed | accepted | declined
  created_at
)
INDEX: (invitee_id, status),               -- приглашения пользователя
       (game_id, status)                   -- приглашения игры

game_loot(
  id, game_id → games.id NOT NULL,
  item_rule_id → rules.rule_id NOT NULL,
  quantity INT NOT NULL,
  notes TEXT,
  status VARCHAR DEFAULT 'available' NOT NULL,  -- available | acquired | distributed
  created_at
)
INDEX: (game_id, status)

game_personal_notes(                       -- личные заметки зрителя по игре; не общие
  game_id → games.id NOT NULL,
  user_id → users.id NOT NULL,
  notes TEXT NOT NULL,
  PRIMARY KEY (game_id, user_id)
)

game_loot_interest(
  loot_id → game_loot.id NOT NULL,
  user_id → users.id NOT NULL,
  created_at,
  PRIMARY KEY (loot_id, user_id)
)
```

### Персонажи

```sql
characters(
  id, name VARCHAR NOT NULL,
  owner_id → users.id NOT NULL,
  space_id → spaces.id NOT NULL,
  rules_version_at TIMESTAMP NOT NULL,
  status VARCHAR DEFAULT 'draft' NOT NULL,  -- draft | ready | needs_fix — валидность ЛИСТА (модерация — в членстве игры, см. game_characters)
  state_json JSON NULL,                     -- текущее состояние свободного листа (одно; заменяется при сохранении)
  heir_of → characters.id NULL,             -- группировка итераций одного персонажа (при миграции правил)
  owner_notes TEXT NULL,                    -- личные заметки владельца; API отдаёт только владельцу, не в character_versions
  active BOOL DEFAULT true NOT NULL,
  created_at
)
INDEX: (owner_id), (status)

-- Членство персонажа в игре (N:N): у игры — ДВА состояния листа + черновик (сценарии модерации).
-- Снимки — JSON на самой записи (по одному на состояние), без истории; diff до/после = active_json vs pending_json.
game_characters(
  id,
  game_id → games.id NOT NULL,
  character_id → characters.id NOT NULL,
  role VARCHAR DEFAULT 'player' NOT NULL,      -- owner | gm | player
  membership_status VARCHAR NOT NULL,          -- pending | approved | rejected | left
  active_json JSON NULL,    -- одобренное состояние («в игре»); NULL до первого одобрения
  pending_json JSON NULL,   -- поданные изменения (на модерации)
  draft_json JSON NULL,     -- сессионные правки (эфемерно, сборка мусора)
  os_bonus INT DEFAULT 0 NOT NULL,  -- бонусные ОС от ГМ сверх лимита игры (реальные лимиты персонажа)
  or_bonus INT DEFAULT 0 NOT NULL,  -- бонусные ОР от ГМ
  ol_bonus INT DEFAULT 0 NOT NULL,  -- бонусные ОЛ от ГМ (деньги — через добычу, без бонуса)
  created_at, updated_at
)
INDEX: (game_id), (character_id), (membership_status)

character_versions(
  id,
  character_id → characters.id NOT NULL,
  created_at TIMESTAMP NOT NULL,            -- версионный timestamp (аналог rule_versions.created_at)
  race_rule_id → rules.rule_id NOT NULL,
  draft_of → character_versions.id NULL,    -- ID версии-оригинала при copy-on-write; NULL для подтверждённых версий
  data_json JSON NOT NULL,                  -- вся сборка персонажа (характеристики, способности, ресурсы, валюты)
  validated BOOL DEFAULT false NOT NULL
)
INDEX: (character_id, created_at DESC),     -- история версий персонажа
       (draft_of)                           -- поиск черновиков для сборки мусора
-- Хранилище состояний СВОБОДНОГО листа (одно текущее + черновик-копия); в игре состояния
-- персонажа живут на записи game_characters (active/pending/draft_json), а не здесь.

character_native_language(
  character_version_id → character_versions.id PRIMARY KEY,
  language_id → languages.id NOT NULL
)

character_inventory(
  id,
  character_version_id → character_versions.id NOT NULL,
  item_rule_id → rules.rule_id NOT NULL,
  quantity INT NOT NULL,
  durability_left INT NULL,
  equipped BOOL DEFAULT false NOT NULL
)
INDEX: (character_version_id)
```

```sql
character_moderation(                    -- волна 4: модерация персонажей
  id,
  character_id → characters.id NOT NULL,
  chat_message_id → chat_messages.id NULL,  -- ссылка на сообщение модерации (§12, волна 4)
  created_at
)
```

### Уведомления

```sql
notification_templates(
  id, key VARCHAR UNIQUE NOT NULL,
  title_template VARCHAR NOT NULL,      -- плейсхолдеры: {{game_name}}
  body_template TEXT NOT NULL,          -- HTML с плейсхолдерами
  buttons_json JSON                     -- [{"label":"Принять","action_type":"event","action":"accept_invite","payload":{}}]
                                        -- action_type: "event" | "url" | "action"
  active BOOL DEFAULT true NOT NULL     -- soft-delete: неактивные скрыты, связи сохраняются
)

notifications(
  id,
  from_user_id → users.id NULL,
  to_user_id → users.id NOT NULL,
  template_key → notification_templates.key NOT NULL,
  data_json JSON NOT NULL,              -- {"game_name": "..."} для плейсхолдеров
  read BOOL DEFAULT false NOT NULL,
  read_at TIMESTAMP NULL,
  created_at
)
INDEX: (to_user_id, read, created_at DESC),  -- лента уведомлений
       (to_user_id, read)                     -- счётчик непрочитанных
```

### Чаты

```sql
chats(
  id, type VARCHAR NOT NULL,            -- private | group | game | game_discussion | character_discussion
  game_id → games.id NULL,
  name VARCHAR NULL,
  created_at,
  updated_at                            -- для lastMessageAt на фронте (см. §9)
)

chat_members(
  chat_id → chats.id NOT NULL,
  user_id → users.id NOT NULL,
  joined_at,
  last_read_message_id INT NULL,        -- id последнего прочитанного сообщения чата
                                        -- unreadCount = COUNT(messages WHERE id > last_read_message_id AND user_id != me)
  PRIMARY KEY (chat_id, user_id)
)
INDEX: (user_id)

chat_messages(
  id, chat_id → chats.id NOT NULL,
  user_id → users.id NOT NULL,
  content TEXT NOT NULL,
  dice_result JSON NULL,                -- результат броска: {roll: ..., results: [...], successes: ..., size: ...}
  created_at,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
INDEX: (chat_id, created_at DESC)  -- пагинация
INDEX: (chat_id, updated_at)       -- sync (см. §9)

user_macros(
  id, user_id → users.id NOT NULL,
  name VARCHAR NOT NULL,
  text_template TEXT NOT NULL DEFAULT '',  -- может быть пустым
  created_at
)
INDEX: (user_id)

user_macro_rolls(
  id, macro_id → user_macros.id NOT NULL ON DELETE CASCADE,
  position INT NOT NULL,                   -- порядок броска в макросе
  roll_formula VARCHAR NOT NULL,           -- например "3d6"
  efficiency INT NOT NULL DEFAULT 3,
  adv INT NOT NULL DEFAULT 0,              -- преимущества (>0) / помехи (<0)
  die_size INT NOT NULL DEFAULT 0,         -- размерность успехов (суффикс в выводе)
  roll_label VARCHAR NULL,                 -- подпись броска (метка карточки, мульти-броски)
  variable_adv BOOLEAN NOT NULL DEFAULT false,  -- спрашивать число преимуществ при отправке
)
INDEX: (macro_id, position)
```

### Летопись

```sql
chronicles(
  id, name VARCHAR,                    -- название летописи (опционально)
  game_id → games.id NULL,
  character_id → characters.id NULL,
  region_id INT NULL,                  -- будущее: регион/город
  created_at
)
-- Один и только один из owner FK должен быть NOT NULL (CHECK constraint или логика приложения)
INDEX: (game_id), (character_id), (region_id)

chronicle_entries(
  id, chronicle_id → chronicles.id NOT NULL,
  title VARCHAR NOT NULL,              -- заголовок события
  content TEXT NOT NULL,               -- HTML-описание
  event_time VARCHAR NOT NULL,         -- игровая дата/время (произвольный формат: "1245 год", "3 день осени")
  sort_order INT NOT NULL,             -- порядок в таймлайне
  created_by → users.id NOT NULL,
  created_at
)
INDEX: (chronicle_id, sort_order)
```

> **Расхождение фронта (2026-08-16, спека §7.16 D82–D86):** прототип хранит игровое время структурно — сдвиг от точки отсчёта в фиксированных единицах (`GameTime`, `Utils/gameTime.ts`) вместо свободной строки `event_time`, и сортирует записи по сдвигу вместо ручного `sort_order`. Схема выше остаётся целевым бэк-дизайном; нормализация единиц и сортировка — на границе «бэка» (в моке).

---

## 4. Авторизация и пользователи

### Регистрация

- **Открытая регистрация** — любой желающий может зарегистрироваться.
- По умолчанию новый пользователь получает группу **«Игрок»**.
- Аутентификация: логин + пароль.
- Сброс пароля: стандартный — пользователь вводит email, получает письмо с одноразовой ссылкой, задаёт новый пароль.

### Модель пользователя

| Поле             | Обязательное | Описание                                                         |
| ---------------- | ------------ | ---------------------------------------------------------------- |
| `login`          | ✅           | Уникальный идентификатор                                         |
| `password`       | ✅           | Хешируется                                                       |
| `email`          | —            | Для сброса пароля                                                |
| `first_name`     | —            | Имя                                                              |
| `last_name`      | —            | Фамилия                                                          |
| `nickname`       | —            | Псевдоним (никнейм)                                              |
| `avatar_file_id` | —            | Ссылка на файл в `files`                                         |
| `super_admin`    | —            | Флаг: защищённая учётная запись (нельзя удалить, снять с группы) |

Набор полей расширяем (в будущем могут добавляться новые).

### Права на пользователей

Права на операции с пользователями разбиты на отдельные ключи (не монолитное `manage_users`):

| Ключ                  | Что даёт                                    |
| --------------------- | ------------------------------------------- |
| `user.view`           | Видеть список пользователей и чужие профили |
| `user.view_sensitive` | Видеть скрытые поля (email, группы)         |
| `user.create`         | Создавать пользователей                     |
| `user.edit`           | Редактировать чужие профили                 |
| `user.deactivate`     | Деактивировать (банить) пользователей       |

По умолчанию все пять ключей есть только у группы «Администраторы».

Группа «Игрок» по умолчанию: `user.view`, `character.create`.

### Редактирование профиля

- **Пользователь** может менять свой логин, пароль, email, имя, фамилию, псевдоним, аватар. Не может назначать себе группы.
- **Загрузка аватара:** на странице профиля при наведении на аватар снизу появляется полупрозрачная кнопка «Загрузить». Клик открывает системный выбор файла (PNG, JPG, до 2 MB).
- **Пользователь с `user.edit`** может менять всё, включая группы.

### Деактивация (бан)

- Пользователя может деактивировать только пользователь с правом `user.deactivate`.
- Деактивация — мягкое удаление (soft-delete): пользователь не может войти, данные сохраняются.
- Возможна **временная** деактивация (до определённой даты) с указанием причины.
- Полное удаление не предусмотрено.

### Супер-админ

- При установке системы создаётся один **супер-админ**.
- Супер-админ состоит в группе **«Администраторы»**.
- Группа «Администраторы» имеет все права (полный набор permission_key).
- **Нельзя:** удалить супер-админа, снять с него группу «Администраторы».
- В остальном группа «Администраторы» — обычная группа: можно добавлять других пользователей (если есть права).

### Группы пользователей

- Изначально группы создаёт только **супер-админ**.
- В будущем — пользователь с правом `group.create` может создавать группы.
- **Правило безопасности:** при создании/редактировании группы пользователь не может:
  - назначить группе прав больше, чем имеет сам
  - лишить группу прав, которых сам не имеет
- Пользователь может состоять в нескольких группах, права суммируются.
- Группу можно **деактивировать**: она перестаёт отображаться пользователям и не даёт прав. Полное удаление групп не предусмотрено.
- Назначение прав в UI — группированные чекбоксы по категориям (глобальные, на игры, на пространства).

### Права (permission keys)

#### Права пользователей

| Ключ                  | Что даёт                                    |
| --------------------- | ------------------------------------------- |
| `user.view`           | Видеть список пользователей и чужие профили |
| `user.view_sensitive` | Видеть скрытые поля (email, группы)         |
| `user.create`         | Создавать пользователей                     |
| `user.edit`           | Редактировать чужие профили                 |
| `user.deactivate`     | Деактивировать (банить) пользователей       |

#### Права групп пользователей

| Ключ                    | Что даёт                                              |
| ----------------------- | ----------------------------------------------------- |
| `user_group.view`       | Просмотр списка групп пользователей, состава и прав   |
| `user_group.create`     | Создание групп пользователей                          |
| `user_group.edit`       | Редактирование групп пользователей (участники, права) |
| `user_group.deactivate` | Деактивация групп пользователей                       |

#### Права пространств (глобальные и per-space)

| Ключ             | Уровень    | Что даёт                                                      |
| ---------------- | ---------- | ------------------------------------------------------------- |
| `space.create`   | Глобальное | Создание пространств                                          |
| `space.view_all` | Глобальное | Просмотр всех пространств                                     |
| `space.edit_all` | Глобальное | Редактирование любых пространств                              |
| `space.view`     | Per-space  | Просмотр пространства                                         |
| `space.comment`  | Per-space  | Оставление комментариев к правилам пространства               |
| `space.edit`     | Per-space  | Редактирование настроек пространства, публикация, деактивация |

#### Права правил (per-space)

| Ключ          | Уровень   | Что даёт                       |
| ------------- | --------- | ------------------------------ |
| `rule.view`   | Per-space | Просмотр правил в пространстве |
| `rule.create` | Per-space | Создание правил                |
| `rule.edit`   | Per-space | Редактирование правил          |
| `rule.delete` | Per-space | Удаление (деактивация) правил  |

#### Права персонажей

| Ключ               | Что даёт                                                |
| ------------------ | ------------------------------------------------------- |
| `character.create` | Создание персонажей (свободное и в игры)                |
| `character.view`   | Просмотр чужих персонажей (свои всегда видны владельцу) |

#### Права признаков (keywords)

| Ключ             | Что даёт                         |
| ---------------- | -------------------------------- |
| `keyword.view`   | Просмотр списка признаков        |
| `keyword.create` | Создание признаков               |
| `keyword.edit`   | Редактирование признаков         |
| `keyword.delete` | Удаление (soft-delete) признаков |

#### Права шаблонов уведомлений

| Ключ                           | Что даёт                 |
| ------------------------------ | ------------------------ |
| `notification_template.view`   | Просмотр списка шаблонов |
| `notification_template.create` | Создание шаблонов        |
| `notification_template.edit`   | Редактирование шаблонов  |
| `notification_template.delete` | Удаление шаблонов        |

#### Права игр (глобальные и per-game)

| Ключ                  | Уровень    | Что даёт                                               |
| --------------------- | ---------- | ------------------------------------------------------ |
| `game.create`         | Глобальное | Создание игр                                           |
| `game.view_all`       | Глобальное | Просмотр всех игр (включая черновики)                  |
| `game.edit_all`       | Глобальное | Редактирование любых игр                               |
| `game.edit`           | Per-game   | Редактирование настроек игры                           |
| `game.moderate`       | Per-game   | Модерация пользователей игры                           |
| `game.manage`         | Per-game   | Управление настройками игры                            |
| `game.edit_inventory` | Per-game   | Редактирование инвентаря персонажей игры (для ведущих) |

#### Права чатов

| Ключ           | Что даёт                                             |
| -------------- | ---------------------------------------------------- |
| `chat.create`  | Создание чатов (приватных, групповых)                |
| `chat.message` | Отправка сообщений (автоматически у участников чата) |
| `chat.delete`  | Удаление сообщений/чатов                             |

Все ключи расширяемы — новые добавляются без миграции схемы.

На фронте таксономия прав — **реестр категорий модулей** (`Core/User/init.ts`): каждый модуль объявляет свою категорию (`Constant/permissions.ts`) и регистрирует через `register*Module()` из своего `init.ts`; админ-разделы — реестр `AdminSection` (`/admin` guard `meta.admin` + пункты меню). Композиция — единый плоский блок `register*Module()` в `main.ts` (после API-слоя; порядок вызова не важен — админ-права мока ленивы). Признаки — `keyword.*` (модуль Rule), шаблоны уведомлений — `notification_template.*` (Notifications), группы — `user_group.*` (Core/User).

### Система авторизации

#### Принцип

Авторизация — middleware-слой, проверяющий доступ перед каждым действием. Два уровня проверки:

1. **Глобальные права** — проверяются по `group_permissions` для всех групп пользователя. Если есть — доступ есть.
2. **Per-object права** — если запрос касается конкретного объекта (игра, пространство), дополнительно проверяются права на этот объект.

#### Super-admin bypass

Пользователь с `super_admin = true` **обходит все проверки** прав. Middleware проверяет это первым делом — если супер-админ, сразу пропускать.

#### Правило «свой vs чужой»

Для ресурсов, где пользователь является владельцем (свой профиль, свой персонаж), **глобальное право не требуется** — только аутентификация. Глобальное право нужно для действий над **чужими** объектами.

Конкретно:

| Ресурс                                      | Свой                                          | Чужой                             |
| ------------------------------------------- | --------------------------------------------- | --------------------------------- |
| Профиль (`/users/:id`)                      | Всегда доступен для редактирования владельцем | `user.edit`                       |
| Персонаж (`/characters/:id`)                | Всегда доступен владельцу                     | `character.view`*                 |
| Пространство (`/space/:code/...`)           | — (нет владельца в этом смысле)               | `space.view` / `space.edit`       |
| Игра (`/games/:id`)                         | Владелец и ведущие — полный доступ            | По статусу игры + `game.edit_all` |
| Правило (`/space/:code/:ctx/rules/:ruleId`) | —                                             | `space.view` / `space.edit`       |
| Группа пользователей (`/admin/groups/:id`)  | —                                             | `user_group.*`                    |
| Признаки (`/admin/keywords`)                | —                                             | `keyword.*`                       |
| Шаблоны уведомлений                         | —                                             | `notification_template.*`         |

> _\* `character.view` — новый ключ. По умолчанию персонажи видны только владельцу и участникам игры._

#### Алгоритм проверки (псевдокод)

```
function checkAccess(user, action, resourceType, resourceId = null):
    // 1. Super-admin bypass
    if user.super_admin: return true

    // 2. Собрать все права пользователя
    permissions = globalPermissions(user)         // из group_permissions
    if resourceId:
        permissions += objectPermissions(user, resourceType, resourceId)

    // 3. Проверить специфичное право
    if action in permissions: return true

    // 4. Проверить право «своего» объекта
    if isOwner(user, resourceType, resourceId): return true

    // 5. Доступ запрещён
    return false
```

#### Связка «роль в игре + права»

Роль `owner` даёт `game.edit`, `game.moderate`, `game.manage` на эту игру без записи в `game_member_permissions`. Роль `gm` даёт `game.edit`, `game.moderate`. Роль `player` не даёт ничего сверх прав группы.

При проверке доступа к игре: если роль даёт нужное право — доступ есть, даже если в `game_member_permissions` нет соответствующей записи.

#### Множественные запросы

Если страница отображает несколько сущностей (список персонажей, список игр), **не делается N запросов прав**. Вместо этого:

- **Глобальные права** проверяются один раз на middleware
- **Список доступных объектов** фильтруется одним SQL-запросом с JOIN на `game_members`, `space_permissions`, `game_member_permissions` и т.д.
- Для каждого объекта на фронт передаётся computed-флаг `can_edit`, `can_delete` и т.д.

---

## 5. Пространства

### Ревизии и версии правил

**Пространство** имеет счётчик **ревизий** (`revision`), который автоинкрементируется при каждой публикации изменений правил. Ревизия — это внутренний счётчик коммитов пространства.

**Версия A.B.C** — свойство каждого **правила**, а не пространства. Правила в одном пространстве могут иметь разные версии механик:

- **A** — глобальная версия (серьёзные изменения)
- **B** — серьёзные изменения
- **C** — небольшие изменения

При создании правила версия по умолчанию — последняя используемая в пространстве, редактор может переопределить.

### Наследование (snapshot copy)

При создании пространства можно выбрать родительское пространство. В момент создания все актуальные версии правил из родителя копируются в новое пространство. После копирования пространства полностью независимы — связь не сохраняется.

### SpaceRevision (снимок правил на момент публикации)

Ревизия — зафиксированное состояние всех правил пространства на момент публикации. Состав правил ревизии определяется запросом: для каждого `rules.rule_id` берётся последняя `rule_versions` с `created_at ≤ revision.published_at` в данном `space_id`. Ревизии иммутабельны — revision #5 всегда revision #5, что позволяет **кешировать результат навечно** (ключ `spaceId + revision`).

```ts
interface SpaceRevisionMeta {
  revision: number;
  publishedAt: string; // ISO
  ruleCount: number;
  changedCount: number; // сколько правил изменилось относительно предыдущей
}

interface SpaceRevision {
  revision: number;
  publishedAt: string;
  rules: Rule[]; // Rule[] — полный срез правил на момент публикации
}
```

**API:**

- `space.getRevisions({ spaceId })` → `SpaceRevisionMeta[]` — список ревизий для навигации
- `space.getRevision({ spaceId, revision })` → `SpaceRevision` — полная ревизия (кешируется навечно)
- `space.commitDraft({ spaceId, rules: Rule[], removedCodes?: string[] })` → `SpaceRevision` — опубликовать черновик; `removedCodes` — маркер-версии `active=false` только в **новой** ревизии (старые срезы иммутабельны).

**Файл ревизии (2026-08-26):** конверт `{ format: 'powerscale.revision', formatVersion: 1, exportedAt, revision: SpaceRevision }`. Экспорт — выбранная опубликованная ревизия. Импорт в **существующее** пространство — черновик (только diff по `code`, без `id`/`spaceId`/дат); переключатель «убрать правила, которых нет в файле» → `removedCodes`. Импорт в **новое** пространство — клиентский черновик на пустой базе (без ревизии 0); опубликовать можно после валидатора, первая ревизия — v1.

### Черновик (Draft)

Черновик хранится **на клиенте** в `draftRuleStore` — никаких запросов к бэку до коммита.

```ts
draftRuleStore {
  drafts: Array<{
    spaceId: number
    changedRules: Record<string, Rule>
    removedCodes: string[]   // маркер-удаление при коммите; старые ревизии не трогает
  }>

  hasDraft(spaceId): boolean
  saveRule(spaceId, rule)             // → changedRules[id] = rule
  removeRule(spaceId, ruleId)         // → удалить одно правило из черновика (индивидуальный откат)
  discardDraft(spaceId)               // → удалить весь draft для space
  getDraftRules(spaceId): Rule[]      // → Object.values(changedRules)
  addToDraft(spaceId, rule): boolean  // true если перезапись существующего
}
```

**Один черновик на пространство.** Draft — это «будущая ревизия»: изменения поверх последней опубликованной ревизии. Он не привязан к конкретной ревизии, только к `spaceId`.

### Контекст просмотра в URL (вместо режимов в сторе)

Контекст просмотра кодируется **в URL** — это единый источник истины. Стор больше не хранит «в каком режиме мы находимся» как скрытое состояние: контекст парсится из URL и синхронизируется через один метод. Это даёт: F5 / прямые ссылки / кнопка «назад» работают, ссылка на конкретную ревизию шарится.

Путь строится как `/space/:code/:ctx/...`, где `:ctx` — либо литерал `draft`, либо номер ревизии. Слаг `draft` зарезервирован и не может быть code пространства.

| `:ctx`        | Что показывается                                     | effectiveRules                        |
| ------------- | ---------------------------------------------------- | ------------------------------------- |
| `draft`       | Черновик = last published revision + draft overrides | **мерж** latest + draftRules          |
| число N       | Замороженная revision N                              | только rules ревизии N, **без мержа** |
| (отсутствует) | Нет — это редирект-портал                            | —                                     |

**Редирект-портал** `/space/:code` (без `:ctx`): при заходе фетчим space, если `draftStore.hasDraft(spaceId)` → `router.replace('/space/:code/draft')`, иначе `router.replace('/space/:code/:latestRev')`. То есть «клик по пространству → самая новая версия», а черновик логически считается будущей ревизией.

**`effectiveRules` — чистая функция от `(spaceId, ctx)`:** при `ctx = 'draft'` берёт latest ревизию и поверх накладывает `draftRules` (изменённые по `rule.id` + новые); при `ctx = число` возвращает rules строго этой ревизии.

Переключение ревизий в UI — это **навигация** (`router.push`), а не мутация стора. Селектор ревизий мапится 1:1 на URL: пункты = «Черновик (N изменений)» + все ревизии.

### Публикация (commit)

1. Все изменения накапливаются в клиентском `draftRuleStore`
2. Перед публикацией фронт загружает `getLatestRevision` (актуальные опубликованные правила)
3. Для каждой changedRule вычисляется diff с её published-версией
4. **Блоки diff в диалоге подтверждения:**
   - Изменённые правила (есть в draft и в published — различаются)
   - Новые правила (есть в draft, нет в published)
   - Проблемные правила (не прошли фронт-валидацию: битые ссылки, структура способности/расы/вида, циклы видов) — блокируют публикацию
5. Фронт валидирует черновик до коммита; при ошибках валидации проблемные правила показываются в диалоге, кнопка подтверждения неактивна
6. Пользователь отмечает галочками, какие правила черновика войдут в публикацию (по умолчанию отмечены все) → `commitDraft({ spaceId, rules: выбранные changedRules })`
7. Бэк:
   - Для каждой changedRule создаёт новую запись в `rule_versions`
   - Инкрементит `spaces.revision`
   - Вставляет запись в `space_revisions`
   - Возвращает новый `SpaceRevision`
8. Фронт: из черновика удаляются только закоммиченные правила (`removeRule` по выбранным id), неотмеченные остаются в черновике; кеш revision N инвалидируется (если был), revision N+1 кешируется, **навигация** → `/space/:code/:newRev` (номер из возвращённого `SpaceRevision`, не локальный инкремент)
9. При откате последнего изменённого правила (draft стал пустым) → `router.replace('/space/:code/:latestRev')`

> **Требуется реализовать:** выбор подмножества правил черновика галочками в диалоге публикации с коммитом только выбранных и удалением из черновика только их. Сейчас коммитится весь черновик и он очищается полностью.

### Раздел `/space`

| Путь                                   | Страница                                                                                                                                                                                                                  | Доступ           |
| -------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------- |
| `/spaces`                              | **Список пространств.** Название, ревизия, дата создания, статус. Ссылка → `/space/:code` (редирект-портал).                                                                                                              | `space.view_all` |
| `/spaces/new`                          | **Создание.** Название, описание, опционально «наследовать от» (выбор родительского пространства — одноразово, копирование снепшота). Ревизия инициализируется нулём. При создании генерируется `code`.                   | `space.create`   |
| `/space/:code`                         | **Редирект-портал** → `/space/:code/draft` (если есть черновик) или `/space/:code/:latestRev`.                                                                                                                            | `space.view`     |
| `/space/:code/draft`                   | **Черновик пространства.** Список правил (мерж latest + draft). Селектор ревизий (черновик / конкретные ревизии). Кнопки: создать правило, «Опубликовать» (только в draft-виде), индивидуальный откат правила, настройки. | `space.view`     |
| `/space/:code/:rev`                    | **Страница ревизии N.** Только правила ревизии N, без черновиков. Селектор ревизий. Редактирование недоступно напрямую — см. модель ниже.                                                                                 | `space.view`     |
| `/space/:code/draft/rules/new`         | **Создание правила.** Выбор типа, форма. Всегда в draft-контексте.                                                                                                                                                        | `rule.create`    |
| `/space/:code/:ctx/rules/:ruleId`      | **Просмотр правила** в контексте `:ctx` (`draft` или ревизия). Отображение адаптируется под тип правила. Текущее состояние, история версий, diff. Кнопка «Редактировать» (см. модель редактирования).                     | `space.view`     |
| `/space/:code/:ctx/rules/:ruleId/edit` | **Редактирование.** Принцип: читать из контекста → писать в draft. База = правило из `:ctx`, запись = всегда в черновик по кнопке «Сохранить».                                                                            | `rule.edit`      |
| `/space/:code/settings`                | **Настройки.** Название, описание, ревизия (read-only), права доступа (per-space: группы + индивидуально).                                                                                                                | `space.edit`     |
| `/space/:code/deactivate`              | **Деактивация пространства.** В UI — кнопка «Деактивировать» на странице настроек (`/space/:code/settings`) с диалогом подтверждения; отдельной страницы нет.                                                             | `space.edit`     |

### Модель редактирования правил («читать из контекста → писать в draft»)

- Человек может открыть правило в **любой** ревизии и нажать «Редактировать» — форма грузит базу из того контекста, где он находится, а сохранение всегда пишет в черновик пространства.
- В процессе редактирования **не меняется ни версия в ревизии, ни версия в черновике** — запись происходит только по кнопке «Сохранить».
- **Разрешение базы:**
  - `:ctx = draft` → берём версию из черновика, если она есть; иначе из last published.
  - `:ctx = число N` → берём правило из ревизии N (то, что видит пользователь).
- **Конфликт:** если `:ctx` — число и в `draftRuleStore` уже есть версия этого правила, при заходе на edit-URL показывается попап: «В черновике уже есть версия правила A. Заменить её версией из ревизии N?». Попап рендерится в самом `RuleEditPage` (на mount), поэтому работает и при прямом заходе по ссылке.
  - «Подтвердить» → база из ревизии N, сохранение перезапишет черновик.
  - «Отмена» → **выход из редактирования** (возврат на страницу правила).
- **После сохранения** → навигация на `/space/:code/draft/rules/:ruleId` (результат виден в черновике; опубликованная ревизия не тронута).

### Frontend-хранилища

#### `useSpaceRevisionStore` — навигация и кеш ревизий

Контекст не «вспоминается» стором, а **синхронизируется из URL** одним методом.

```ts
state: {
  revisions: Map<number, SpaceRevisionMeta[]>     // spaceId → список
  cachedRevisions: Map<string, SpaceRevision>      // ключ "spaceId:revision"
  activeContext: {
    spaceId: number
    revision: number                                // номер ревизии (для draft — latest)
    kind: 'draft' | 'rev'                           // 'draft' → мерж с draftRules; 'rev' → строгая ревизия
  }
}

getters: {
  effectiveRules: Rule[]   // чистая функция от activeContext:
                           // kind='draft' → latest revision + draft overrides;
                           // kind='rev'   → rules строгой ревизии, без мержа
}

actions: {
  async fetchRevisionsMeta(spaceId): SpaceRevisionMeta[]
  async fetchRevision(spaceId, revision): SpaceRevision     // кешируется навечно
  async syncFromContext(spaceId, { kind, revision? }): void // фетчит нужное + заполняет activeContext
  clearContext(): void
  invalidateCache(spaceId, revision): void
  async commitDraft(spaceId, rules, signal?, removedCodes?): SpaceRevision
}
```

#### `draftRuleStore` — клиентский черновик

```ts
state: {
  drafts: Array<{
    spaceId: number
    changedRules: Record<string, Rule>   // ruleId → Rule
  }>
}

actions: {
  hasDraft(spaceId): boolean
  saveRule(spaceId, rule)
  removeRule(spaceId, ruleId)            // индивидуальный откат
  discardDraft(spaceId)
  getDraftRules(spaceId): Rule[]
  addToDraft(spaceId, rule): boolean     // true = перезапись существующего
}
```

**Персистентность (F42):** черновик сериализуется в `localStorage` (ключ `powerscale.drafts.v1`). Восстанавливается при старте, обновляется после каждого изменения и очищается при `discardDraft`/`clearAll` (и после коммита). Ключ версионируется — при изменении формата старые данные игнорируются (невалидный JSON безопасно отбрасывается). Переживает F5, но остаётся «до коммита»; если localStorage недоступен (квота/режим) — черновик живёт in-memory.

---

## 6. Правила

### Механики

**Механика** — логика обработки правила, реализуемая в коде (Rule Engine). Механики версионируются отдельно от правил.

Примеры:

- «Правило 6 и 1» v4.5.0 — определяет подсчёт успехов при броске кубиков
- «Двойной удар» v4.1.5 — логика обработки навыка двойного удара

Механики могут зависеть друг от друга: «Двойной удар» использует «Удар», который использует «Правило 6 и 1». Зависимости — логика в коде, не хранится в БД.

**Справочник механик** хранится в БД (`mechanics`): код, название, описание, версия (semver).

**Связь с правилами:** `rule_versions.mechanic_id` — ссылка на конкретную механику (nullable, не все правила имеют механику).

**Маппинг на код:** `(code, version) → класс обработчика` — захардкожен в Rule Engine. Это ответственность кода, не БД.

### Типы правил

Все типы — правила: версионируются, имеют глобальный ID, хранятся в `rule_versions`.

**Базовые поля всех правил:** `name`, `description` (HTML), `spec` (JSON-блоки — «Спецификация», структурированные данные), `keywordIds` (связь с признаками через rule_keywords), `mechanic_id` (nullable — ссылка на механику). Ниже указаны только типоспецифичные поля.

| Тип                 | Описание                                                                                                                                                                  |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Простое правило** | Только база. Никакой дополнительной механики.                                                                                                                             |
| **Раса**            | Иерархический контейнер. Определяет характеристики, черты, навыки по умолчанию.                                                                                           |
| **Вид/Подвид**      | Узел дерева рас (вид → подвид → раса). Контента не несёт, кроме наследуемых расой способностей.                                                                           |
| **Характеристика**  | Размерное число с базой 3-5. Может быть производной (формула).                                                                                                            |
| **Ресурс**          | Числовое значение. Может быть размерным или безразмерным.                                                                                                                 |
| **Очки**            | ОС/ОЛ/ОР — этап и «очки» создания персонажа. Ключ (`code`) используется как ключ зон способностей. Базовые значения не хранятся.                                          |
| **Способность**     | Общий тип. Делится на подтипы через признаки.                                                                                                                             |
| **Предмет**         | Именованная сущность с категорией, стоимостью, весом. Подтипы: деньги, снаряжение (оружие/броня/щит), прочее.                                                             |
| **Модификатор предмета** | Тип `item_modifier` (`ItemModifierSpec`: `type_code`, применимость, цена, эффекты с `ops`). Эффективный спек считается `applyStack` на лету; снаряжение — экземпляры. |
| **Тип модификатора предмета** | Тип `item_modifier_type` (`exclusive`: второй модификатор того же типа заменяет первый). |
| **Тип урона**       | Справочник типов урона и сопротивлений (рубящий, колющий, огонь и т.д.).                                                                                                  |
| **Источник**        | Правило-метка происхождения модификаторов (`"armor"`, `"shield"`, `"spell"`, `"training"`, `"innate"`); группировка не-суммирования модификаторов.                        |
| **Эффект**          | Runtime-статус с уровнями. Правила: наложение, длительность, снятие. **Отложен** (см. «Отложенное» п.4).                                                                  |
| **Состояние**       | Тип правила (с 06.08.2026 — отмена п.24, см. `docs/specs/state-design.md`). Объявляет хранение значения, объединение повторов и эффекты (модификаторы, урон со временем). |
| **Проверка**        | Тип `check` (2026-08-22, `docs/specs/check-design.md`): именованное действие над «Бросок»/RollEngine. Правила броска — коды карточек (`attached_rule_codes`, напр. `rule-6-and-1`), не коды механик; наследование по дереву. Эффективность грани ≠ сложность; РУ после приведения размера успехов. Соло/joint — режим запуска. |

#### Размерные числа

**Размерное число** — фундаментальный тип данных с собственной арифметикой. Используется для характеристик, ресурсов, веса предметов и других величин.

**Формат:** `{B|x}` где:

- `B` — базовое значение (целое число)
- `x` — размер (целое число, может быть отрицательным)

**Преобразование в обычное число:** `{B|x} = B × 2^x`

- `{3|-1} = 1.5`
- `{4|0} = 4`
- `{3|+1} = 6`

**Округление:** `[{B|x}]` — округление вниз

- `[{3|-1}] = 1`

**Модификация (характеристики, с диапазоном):** при изменении значения происходит автоматический переход между размерами — каждые `max−min+1` пунктов базы = один размер (`CHARACTERISTIC_BASE_RANGE = { min: 3, max: 5 }`)

- `{4|0}.modify(+1) = {5|0}`
- `{5|0}.modify(+1) = {3|+1}` (переполнение базы → увеличение размера)
- `{3|0}.modify(-1) = {5|-1}` (недостаток базы → уменьшение размера)

**Модификация (без диапазона):** `{B|x}.modify(delta)` — простой сдвиг базы `{B+delta|x}` (ресурсы, веса — границ нет).

**Хранение в БД:** JSON `{ "base": 3, "size": 1 }` (plain-тип `DimensionalNumberValue`)

**Отображение в UI:** компонент `DimensionalNumber` рендерит базу + стрелку + модуль размера (визуал унаследован, без tooltip): `{3|+1}` → `3↑1`, `{3|-1}` → `3↓1`, `{3|0}` → `3|0`

**Формат `toString()`:** `{3|+2}` → `3↑²`, `{3|+1}` → `3↑`, `{3|-1}` → `3↓`, `{3|0}` → `3`

**Реализация:** класс `DimensionalNumber` в `Core/Engine/Value/DimensionalNumber.ts` (значение — plain-тип `DimensionalNumberValue { base, size }`); типы импортируются в Rule-слой (`Dto/Ability`, `Dto/Item`, `Dto/Race`) как `DimensionalNumberValue`:

- `toNumber()` — преобразование в обычное число (округление вниз)
- `modify(delta, range?)` — сдвиг базы; при `range` — с автопереносом между размерами
- `add/subtract(other)` — арифметика ресурсов: выравнивание по меньшему размеру, результат без нормализации (отрицательный допустим, guard на вызывающем)
- `toString()` — форматирование для отображения

#### Простое правило

Для правил, которые не являются ни навыками, ни чертами, ни характеристиками. Просто текст с ключевыми словами.

- `name`, `description` (HTML), `spec` (JSON-блоки, опционально), `tagIds`

#### Раса и Вид/Подвид

**Иерархия: Вид → Подвид → … → Раса.** Вид и подвид — один тип правила `species`,
различаются только наличием `parent_race_code` (у вида — null). Раса (`race`) —
терминальная точка цепочки, из которой генерится персонаж; от расы не наследуются
(детей нет), родитель расы — всегда `species`.

- **Вид/Подвид** (`species`): `parent_race_code` (вид: null / подвид: species) +
  `abilities` (наследуются расами цепочки). Контента (характеристик, стоимости) нет.
- **Раса** (`race`): `parent_race_code` (всегда species), `cost_os` (стоимость в ОС,
  отрицательная = даёт ОС), `characteristics` (стартовый профиль), `abilities`
  (свои + наследуемые от предков).
- Виды/подвиды нужны для будущего древовидного выбора расы в селекторах.
- Полная схема и решения: `docs/specs/race-design.md`.

**Характеристики расы** — два режима на каждую характеристику:

- `fixed` (новые правила) — фиксированная база; дальше модифицируют дары черт
  (`characteristic_modify` / `characteristic` из способностей).
- `purchased` (старые правила) — базовый минимум + таблица закупки
  `purchase: [{cost, value}]` («за N ОС → значение», пропуски не перечисляются).

**Способности расы** — единый список `{ ability_code, automatic }` (бесплатная/доступная).

#### Характеристика

Размерное число с фиксированным диапазоном базы 3-5.

Поля в `spec`:

- `formula: string | null` — для производных характеристик (например, `"min(dexterity, agility)"`, ссылки по `code`)

**Особенности:**

- Всегда размерная
- База всегда в диапазоне 3-5
- Может быть производной (formula не null)
- Формула: только `min()` или `max()` из двух базовых характеристик (без формулы)
- Выбор характеристик для формулы ограничен текущим пространством

**Группировка:** осуществляется через признаки. Например: "Характеристика" + "Физическая" + "Основная".

#### Ресурс

Числовое значение, может быть размерным или безразмерным. Отдельный тип правила (НЕ подтип характеристики).

Поля в `spec`:

- `is_dimensional: boolean` — размерный или безразмерный
- `initial_value: DimensionalNumber | number | null` — начальное значение (для размерных: `{B|x}`, для безразмерных: число)

```typescript
interface ResourceSpec {
  is_dimensional: boolean;
  initial_value: DimensionalNumber | number | null;
}
```

**Особенности:**

- Может быть размерным или безразмерным
- Не может быть производной
- Не имеет фиксированного диапазона базы
- Используется для здоровья, маны, очков действий и других величин
- Правило хранит только **определение** ресурса; текущее/максимум живут на персонаже (модуль Character)
- Ссылается на ресурсы по `code` (`resource_code`)

**Группировка:** осуществляется через признаки. Например: "Ресурс" + "Магический" + "Основной".

#### Очки

ОС/ОЛ/ОР (Очки Создания / Личности / Развития) — «очки» создания персонажа, привязанные к этапу. Тип правила `points`:

- `name` — название («Очки Создания»), `code` — системное имя (`os`/`ol`/`or`), `description`, признаки. Спецификация отсутствует.
- **Базовые значения не хранятся**: ОС/ОР определяются игрой, ОЛ — правилами возраста (модуль Game/Character).
- Код очков используется как **ключ зоны** способности (`zones: Record<code, AbilityCost>`). Редактор способностей заполняет зоны из очков-правил пространства.
- Этапы создания персонажа = набор очков-правил в версии (версионно-осведомлённость, см. ТЗ).
- Термин: ОС/ОЛ/ОР — «очки», не «валюта»; «валюта/деньги» — только монеты (`item.category: money`).

#### Способность

**Не жёсткие подтипы, а категоризация через признаки + набор общих полей.**

Черты и особенности могут иметь уровни, стоимость, быть отрицательными. Разница между ними — в **контексте использования** (на каком этапе создания доступны, какими **очками** оплачиваются). Очки привязаны к этапу (зоне), отдельную сущность очков не указываем:

- Зона `os` → Очки Создания (ОС) — расы и врождённые черты
- Зона `ol` → Очки Личности (ОЛ) — особенности личности
- Зона `or` → Очки Развития (ОР) — навыки и черты

**Цена живёт в зонах:** каждая зона несёт свою цену в своих очках. Зоны нет в `zones` — там способность недоступна. `levels`/`hard`/`automatic` отдельными полями не хранятся. Ключ зоны = `code` очков-правила (тип `points`), см. «Очки».

Поля способности в `spec`:

```typescript
type ZoneId = string; // код очков-правила (type='points'): 'os' | 'ol' | 'or' | …

type AbilityCost =
  | { kind: "array"; levels_cost: number[] } // длина = макс. уровень; отрицательные значения = даёт очки
  | { kind: "progression"; max_level: number; base_cost: number; step: number } // cost(level) = base_cost + (level-1)*step
  | { kind: "automatic" }; // авто-получение при выполнении требований (не покупается)

interface AbilitySpec {
  type?: AbilityType; // 'trait'|'feature'|'skill'|'action'|'process'|'spell' — источник истины; легаси деривируется из признаков
  zones: Partial<Record<ZoneId, AbilityCost>>;
  requirements: { level: number; requirements: Requirement[] }[]; // карта уровней; ур. 1 = получение; накапливаются естественно
  grants: { level: number; grants: Grant[] }[]; // карта уровней; ур. 1 = получение (бывший general)
  action_components: ActionComponent[]; // траты ресурсов (type:'resource') + verbal/somatic/material; ОД-компонент минимум 1, label: у заклинаний ОД = «Сотворение»
  process?: ProcessSpec; // только для type='process'
  spell?: SpellSpec; // только для type='spell'
  parent_ability_code: string | null;
}
```

> **Два слоя (30.43):** в редакторе работает `AbilitySpecDraft` (широкий тип — разрешает несовместимые с текущим `type` поля на время правки); при сохранении `pruneAbilitySpecForType(spec, type)` усекает до чистого `AbilitySpec` (убирает поля/структуры, не принадлежащие типу). Полная детализация — `docs/specs/ability-resource-design.md` §4.5/§4.9.

- Бесплатная покупаемая: `{ kind: 'array', levels_cost: [0] }`.
- Макс. уровень выводится из `levels_cost.length` или `max_level` (для `automatic` — 1).
- `action_components` — ресурсные компоненты `{ type: 'resource', resource_code, amount }` (amount — число или размерное, не формула) + верб/сомат/матер. Пример: `[{ type: 'resource', resource_code: "od", amount: 2 }]`.
- **Процесс** (`ProcessSpec`): шаги «Название+Описание+Ресурсы», повтор = само-переход;
  переходы `chain(max_shift,direction)` / `free` / `custom(edges)`; `start_step_code`; `failure`.
- **Заклинание** (`SpellSpec`): `difficulty` (сложность сотворения), `duration`
  (instant/refreshable/sustained); компоненты verbal/somatic(note)/material(item_code) — в `action_components`.
- **Требования/дары — карты уровней** (с 30.44): единая структура `{level, ...}[]`, уровень 1 =
  получение; отдельные `requirements`/`requirements_by_level` и `grants.general`/`byLevel` убраны.
  У дара `permanent?: boolean` (default true — копится на уровнях ≥ N; false — строго на уровне N).
  Требования накапливаются естественно: «взял уровень N → уровни < N уже удовлетворены».
  «Навыки боя» = один `ability_level`-дар на ур. 1 (формула читает текущий производный уровень).

**Требования (Requirements):**

```typescript
type Requirement =
  | { type: "has_ability"; ability_code: string; min_level?: number }
  | { type: "has_ability_tag"; tag_code: string; min_count: number }
  | { type: "has_tag"; tag_code: string }
  | {
      type: "characteristic_value";
      characteristic_code: string;
      min: DimensionalNumber;
    }
  | { type: "resource_limit"; resource_code: string; min?: number }
  | { type: "and"; children: Requirement[] }
  | { type: "or"; children: Requirement[] };
```

**Дары (Grants)** — постоянные эффекты при получении способности:

```typescript
type Formula =
  | { type: "fixed"; value: number }
  | { type: "characteristic"; characteristic_code: string; modifier: number }
  | { type: "dimensional"; base: number; size: number }
  | {
      type: "ability_level";
      ability_code: string;
      multiplier?: number;
      offset?: number;
    }; // уровень способности (code обязателен)

type Grant =
  | {
      type: "characteristic";
      characteristic_code: string;
      value: DimensionalNumber;
      permanent?: boolean;
    } // даёт характеристику (значение — размерное)
  | {
      type: "characteristic_modify";
      characteristic_code: string;
      amount: Formula;
      source_code: string;
    } // +N к существующей
  | {
      type: "resource";
      resource_code: string;
      limit: DimensionalNumber | number;
      permanent?: boolean;
    } // даёт ресурс (лимит адаптивен: размерный или числовой)
  | {
      type: "resource_limit_change";
      resource_code: string;
      amount: Formula;
      source_code: string;
    } // меняет актуальное значение ресурса на amount (отрицательный = вниз)
  | { type: "ability"; ability_code: string } // даёт другую способность
  | { type: "keyword"; keyword_code: string; remove?: boolean } // добавить/убрать признак
  | { type: "item"; item_code: string; quantity?: number }; // даёт предмет/врождённое; quantity — число экземпляров (конечности), default 1
```

- `source_code` у `characteristic_modify`/`resource_limit_change` — ссылка (по `code`) на правило-источник типа `source` (для трассировки происхождения модификатора и группировки не-суммирования).

- Уровневые эффекты: формула дара может ссылаться на уровень способности — `{ type: 'ability_level', ability_code: "krasnorechie" }` (например «Тренировка Красноречия» = `+уровень` к Красноречию от тренировки; «Навыки боя» = `+уровень` к Мастерству боя от тренировок).
- **Постоянность** (с 30.44): у каждого дара `permanent?: boolean` (default true). `true` — эффект копится на всех уровнях ≥ уровня дара; `false` — действует строго на своём уровне.
- `characteristic` (дать) vs `characteristic_modify` (изменить) — разные дары; симметрично ресурсу: `resource` vs `resource_limit_change`.
- Естественное оружие/броня — через врождённые итемы (grant `item` + `innate` у предмета).

Подтипы — это **признаки** и (с 30.36) явное поле `type` в `AbilitySpec`: `черта`, `особенность личности`, `навык`, `действие`, `процесс`, `заклинание`. Тип определяет видимость блоков редактора и карточку; типообразующие признаки авто-синхронизируются при смене типа. Пользователь фильтрует по ним.

> Полная детализация и обоснование: `docs/specs/ability-resource-design.md`.

#### Предмет

Именованная сущность, предназначенная для хранения в инвентаре персонажа или добычи игры.

Категории (поле `category`):

- `money` — деньги (монеты). Хранятся в граммах меди (gm). Отображение: «5 гз, 7 гс, 9 гм» (1 гз = 10 гс = 100 гм).
- `equipment` — снаряжение. Имеет подтипы (`subtypes: string[]`): `weapon` (оружие), `armor` (броня), `shield` (щит). Один предмет может иметь несколько подтипов (например, `["weapon", "shield"]`).
- `other` — прочее.

Общие поля предмета:

- `cost_gm: int | null` — стоимость в граммах меди (может быть null, если стоимость не определена)
- `weight: DimensionalNumber | null` — вес (размерное число или null)
- `innate: boolean` — естественный/врождённый предмет (признак скрывает вес/стоимость при отображении; используется для естественного оружия/брони, выдаваемого через grant `item`)
- `special_rule_codes: rule_code[]` — спецправила (ссылки на простые правила по `code`)

Поля оружия (`weapon`):

- `min_strength: DimensionalNumber | null` — минимальная сила (размерное число с базой 3-5)
- `block_profile: {efficiency: DimensionalNumber, defense: int, resistances: Resistance[]} | null` — профиль блокирования
  - `efficiency: DimensionalNumber` — эффективность блокирования
  - `defense: int` — защита от блокирования
  - `resistances: Resistance[]` — сопротивления при блокировании
- `weapon_profiles: WeaponProfile[]` — профили оружия (каждый профиль — отдельный способ атаки):
  - `type: "strike" | "throw" | "shoot"` — тип атаки (удар, бросок, выстрел)
  - `distance: Formula` — дистанция (может быть фиксом или от характеристики)
  - `range: Formula | null` — дальнобойность (только для throw/shoot)
  - `damage: Damage` — урон:
    - `formula: Formula` — формула урона
    - `damage_type_code: rule_code` — ссылка на правило типа урона
  - `penetration: Formula` — пробитие
  - `accuracy: DimensionalNumber` — точность

Поля брони (`armor`):

- `defense_slots: DefenseSlot[]` — слоты защиты:
  - `defense: int` — значение защиты
  - `durability: int` — надёжность защиты
  - `source_code: rule_code | null` — источник защиты (ссылка на правило-источник типа `source`)
- `resistance_slots: ResistanceSlot[]` — слоты сопротивлений:
  - `damage_type_code: rule_code` — ссылка на правило типа урона
  - `value: int` — значение сопротивления
  - `durability: int` — надёжность сопротивления
  - `source_code: rule_code | null` — источник сопротивления (правило-источник)
- `characteristic_limits: CharacteristicLimit[]` — ограничения характеристик:
  - `characteristic_code: rule_code` — какая характеристика ограничивается
  - `limit: Formula` — формула ограничения (например, Выносливость.modify(-3))

Поля щита (`shield`):

- `min_strength: DimensionalNumber | null` — минимальная сила
- `block: {efficiency: DimensionalNumber, defense: int, resistances: Resistance[]}` — профиль блокирования

**Формат Formula:**

```typescript
type Formula =
  | { type: "fixed"; value: number }
  | { type: "characteristic"; characteristic_code: rule_code; modifier: number }
  | { type: "dimensional"; base: number; size: number }
  | {
      type: "ability_level";
      ability_code: rule_code;
      multiplier?: number;
      offset?: number;
    }; // уровень способности (только в дарах способностей)
```

**Формат Resistance:**

```typescript
{
  damage_type_code: rule_code,
  value: int,
  source_id: rule_id | null
}
```

#### Тип урона

Специальный тип правила для справочника типов урона и сопротивлений.

Поля:

- `name: string` — название типа урона (например, "Рубящий", "Колющий", "Огонь")
- `description: string` — описание
- `spec` обязателен: `{ type: 'damage_type', forms: { genitive, dative }, attached_rule_codes, defense_ignored? }`. Родительный и дательный — обязательные поля редактора (подписи вроде «5 рубящего урона» / «сопротивления рубящему урону»). Публикация и «Сохранить» не пропускают тип без этой спеки.

Используется для:

- Определения типа урона в профилях оружия
- Определения типа сопротивления в броне и щитах

### Источники модификаторов — правила типа `source`

✅ **Источник модификатора — правило типа `source`** (версионируется и живёт в ревизии спейса, как все правила), а не глобальный справочник: набор источников меняется между версиями правил, поэтому он — часть контента правил, а не фреймворка. Ссылка по `code` (поле `source_code`).

- `code` — уникальный код источника (`"armor"`, `"shield"`, `"spell"`, `"training"`, `"innate"`)
- `name` — отображаемое имя («от Доспеха», «от Щита», «от Заклинания», «Тренировка», «Врождённый»)
- Типоспецифичного `spec` нет (простое правило-метка).

Используются для:

- Определения источника защиты в слотах брони (`defense_slots[].source_code`)
- Определения источника сопротивлений (`resistance_slots[].source_code`)
- Источника модификаторов у даров (`characteristic_modify`/`resource_limit_change` → `source_code`)
- Логика суммирования модификаторов: модификаторы от одного источника не суммируются, берётся только самый большой бонус и штраф

Пример: `{Защита 0} {+2 от Доспеха} {+10 от Доспеха} = {Защита 10}` (не +12, а +10, так как оба от одного источника `armor`).

### Признаки (keywords)

✅ **Признаки — плоский справочник, единый для всей системы, без версионирования.** (ранее — «теги»; код-идентификатор `Keyword`)

```
keywords(id, code, name, description, active)
```

- `code` — уникальный строковой идентификатор (`"melee"`, `"magic"`, `"stealth"`)
- `name` — отображаемое имя («Навыки боя», «Магия», «Скрытность»)
- `description` — описание (необязательно)
- `active` — soft-delete (признак скрывается из выбора, старые связи сохраняются)

Используются для:

- Фильтрации и поиска правил
- Группировки способностей в UI
- Условных бонусов (`+1 к Восприятию, если владеете двумя навыками с признаком X`)
- Определения подтипов (черта, навык, действие и т.д.)

Привязка к правилу: `rule_keywords(rule_version_id, keyword_id)`. Конкретная версия правила имеет определённый набор признаков. При создании новой версии набор признаков может меняться.

Варианты реализации: **плоский список** (без иерархии). Иерархию добавить при необходимости.

### Описание и Спецификация

Правило имеет два вида контента:

1. **Описание** — простой HTML (WYSIWYG-редактор). На сохранении — санитайзер (очистка опасных тегов и атрибутов). Человекочитаемый текст с форматированием.
2. **Спецификация** — JSON-блоки структурированных данных (механика правила, не отображаемая напрямую):

```json
{
  "blocks": [
    { "type": "text", "content": "..." },
    { "type": "cost_table", "levels": [1, 2, 3], "difficulty": 1 },
    { "type": "requirements" },
    { "type": "action_cost", "value": 2, "unit": "ОД" },
    {
      "type": "level_scaling",
      "levels": [
        { "level": 1, "effect": "...", "cost": 1 },
        { "level": 2, "effect": "...", "cost": 2 }
      ]
    }
  ]
}
```

> **Формулы в спецификации** поддерживают синтаксис преобразования в целые числа: `[формула]` (floor), `[формула|ceil]` (ceiling), `[формула|min=N]` (минимальное значение). Подробнее — Этап 3 (преобразование в целые числа).

---

## 7. Персонажи

> **Статус фронта:** реализован список персонажей `/characters` (карточки + фильтры по §11, клик ведёт на карточку) и карточка `/characters/:id` с вкладками Обзор / Описание / Способности / Инвентарь / Обсуждение (шаги 1–2 волны 4, 2026-08-05). Карточка показывает привязку к ревизии правил пространства (`space_id` + `rules_version_at`, ссылка на `/space/{code}/{vN}`). Вкладка «Обзор» переработана на ссылочную модель (шаг 2.1, 2026-08-05): версия хранит ссылки `ruleId` + вычисленные итоги, имена/формулы правил резолвятся из ревизии (грузится через `fetchRevision`), блоки Характеристики (с модификаторами), Ресурсы (с прогрессом), Состояния, Защита (доспехи/щит), Атаки (оценка формул урона/пробития/точности). Вкладка «Способности» (шаг 2.4, 2026-08-06): паттерн «фильтр + табы + список» как в списке правил пространства — поиск, под ним табы Все/Избранное/Навык/Черта/Заклинание; каждая способность — раскрывающаяся панель: шапка «Название · уровень · тип · (у действий — ОД, у заклинаний — Сотворение/Сложность/Длительность) · открыть правило в слайдере · избранное», тело — описание + признаки (ключевые слова правила). Избранное — per-character в localStorage (таб «Избранное» для быстрого доступа). Блок «Состояния» из вкладки «Описание» убран — состояния отображаются только во вкладке «Обзор». Редактор персонажа (заход 1, 2026-08-06): `/characters/new` — настройка свободного создания (пространство + ревизия + лимиты ОС/ОР/денег, «через игру» отложено) → редактор `/characters/new/editor`; `/characters/:id/edit` — copy-on-write черновик оригинала. Общий редактор `CharacterEditPage`: табы Раса → Основа → (Личность/Развитие/Инвентарь — заглушки) → Описание, шапка-сводка бюджетов, «Сохранить черновик»/«Готово». Реализованы вкладки «Раса» (карточки рас, вид/наследование, стоимость ОС, предупреждение при смене со сбросом несовместимого) и «Основа» (врождённые черты зоны ОС: фильтры доступные/недоступные/расовые/общедоступные, цена в ОС, уровни, покупка характеристик по лестнице, live-характеристики). Расчётное ядро `CharacterEditorService` (характеристики раса+дары с агрегацией модификаторов, бюджеты, доступность/уровни способностей) + `RequirementEvaluator`; черновик `Store/characterDraft` (localStorage); API `character.create`/`character.update` (мок). Механика «Возраст» (ступени возраста + соотношение «годы расы → ступень», влияет на ОЛ) — отложена, отдельной задачей. Полная реализация (Личность/Развитие/Инвентарь, создание через игру, версии/миграция/деактивация, модерация) — следующими шагами. Доводка по замечаниям (2026-08-06): настройка `/characters/new` — ревизия-автокомплит из существующих ревизий, лимиты через `ClampedNumberField` (минимумы 0/5/0, дефолты 12/25/10000); редактор — панель навигации по макету `character-editor.html` (блоки этапов со сводкой слева + компактный блок характеристик справа, этапы = навигация); вкладка «Раса» — фильтр-бар + древовидный список рас по видам + карточка выбранной расы с кнопкой «Выбрать расу». Доводка №6 (2026-08-06): карточка расы — убран чип «вид: …», вниз добавлен блок «Признаки» (keywords расы, резолв через keywordStore; в мок добавлены расовые признаки «Гуманоид»/«Эльф»/«Лесной эльф»); панель навигации — у каждой кнопки шага своя лёгкая рамка, характеристики — подблок «Характеристики» с аналогичной рамкой (компактные чипы сеткой 3×3, `border-right` навигации отделяет от правого чатбара); шаг «Основа» — вместо двух колонок два стекаемых сворачиваемых блока «Все»/«Выбранные» (идентичные панели, вынесенные в `EditorAbilityPanels.vue`) + быстрый фильтр «Не выбранные». Доводка №7 (2026-08-07): панель навигации — убрана подпись «ХАРАКТЕРИСТИКИ», базовые характеристики (group 'base') скрыты, чипы кликабельны (попап по образцу карточки персонажа: Значение/База/модификаторы; у производной — расширенный с разделом «Производная. Базы:»), текст в чипах отцентрован; логика вынесена в `Utils/editorStatViews.ts` (+попап `EditorCharacteristicPopup.vue`); стадия «Раса» в навигации при выборе расы показывает имя расы, вторая строка — только ОС. Доводка №8 (2026-08-07): шапки способностей в `EditorAbilityPanels.vue` — уровень перенесён в чип сразу после названия как «N из M» (для авто — «1 из 1»); стоимость уровня показывается по типу зоны os: для `kind: 'array'` — массив стоимостей кнопками с бордюром (клик ставит уровень, `@click.stop` — панель не сворачивается; текущий уровень подсвечен; недоступные по требованиям отключены) — кнопки «−/+» скрыты; для `kind: 'progression'` — компактные кнопки с бордюром «[− N ОС] [M ОС +]» (N — освобождаемая при снятии цена, M — цена следующего уровня); в мок добавлена проверочная способность rule-72 «Математический шаг» (os progression, base_cost 1, step 1) для наглядной проверки у автоматических (даются расой бесплатно, уровень не хранится) уровень отображается как 1, кнопки «−/+» отключены (не снимается и не прокачивается расовый дар). Доводка №9 (2026-08-07): блоки вкладки «Основа» переведены со сручнописанных карточек на штатные Vuetify-компоненты — «Покупка характеристик», «Все способности», «Выбранные способности» как `v-expansion-panel` (дефолтно раскрыты через `v-model`, `variant` не задаётся — TS-ограничение типов `"default"|"inset"|"accordion"|"popout"`); поиск по способностям через общий `FilterBar` (`useFilteredRows`, `settings-key="character-editor-base"`) вместо `v-text-field`; способности растянуты от левого до правого края панели (обнуление горизонтального паддинга только у внешних текстовых обёрток селектором `:deep(.base-stack > .v-expansion-panels > ... > .v-expansion-panel-text__wrapper)`), тело каждой способности сохраняет свой отступ 24px; шапка способности не растёт при раскрытии (`min-height: 48px` на `.v-expansion-panel-title`, активная панель — разделитель под заголовком); «Требования» перенесены вверх тела, «Признаки» — вниз с `border-top`. Доводка №10 (2026-08-07): в редакторе персонажа убраны заголовок страницы и подзаголовок «пространство · ревизия N», кнопка «Отмена» удалена (назад — хлебные крошки), кнопки «Черновик» и «Сохранить» (ранее «Готово») вынесены в топбар (`#editor-actions` в `TopBar.vue` перед колокольчиком, `Teleport` из `CharacterEditPage.vue`). Доводка №11 (2026-08-07): «Покупка характеристик» вынесена из «Основа» в отдельный шаг-этап навигации «Характеристики» (между «Раса» и «Основа») — новый таб `CharacteristicsTab.vue` с таблицей лестниц (логика перенесена из `BaseTab.vue`, у того убран prop `rules`); «Основа» осталась только для способностей; сабтайт этапа «Характеристики» — «ОС: N» (сумма потраченных на покупки ОС). Производные характеристики (2026-08-09): Восприятие/Интеллект не хранятся в спеках рас (своего значения нет) — док-сокращения разворачиваются в базы («Интеллект 5↓» = Память 5↓ + Мышление 5↓; орки обновлены), значения вычисляет `buildCharacteristics` как min/max баз по формуле из `CharacteristicSpec.formula` (общий парсер `Rule/Utils/derivedCharacteristic.ts`); модификаторы, целящиеся в производную, применяются к её базам (attention+reaction / memory+reasoning); карточка расы показывает производную одним значением вместо вырожденного диапазона «от X до X» (логика вынесена в `Character/Utils/raceCharacteristicLabels.ts`). Требования читаемы (2026-08-09): `RequirementEvaluator` использует имена из снимка ревизии (`CharacterSnapshot` получил мапы имён), `or` из has_ability — «нужна одна из способностей: …». Бесплатная расовая характеристика + дар (2026-08-09, Ахтар/Анеит): фикс. база расы (`mode 'fixed'`, «Магия 4↓») становится бесплатной ступенью дара «Врождённая Магия X» — `value`/`min` = база (ступени ниже недоступны), стоимости инкрементальны (`табл(X) − табл(базы)`; покупка 5↓ = 1 ОС, 5 = 6 ОС), грант дара переопределяет расовую базу (покупка Магии сразу видна в характеристиках/чипах). Фундамент размерных сравнений (2026-08-09): `DimensionalNumber.equalsStrict/equals/compare`, характеристический `Rule/Value/CharacteristicNumber` (`modifyWith`, `modifyDiffTo`), `CHARACTERISTIC_BASE_RANGE` в Rule; `evaluateDerivedValue`/`raceCharacteristicLabels` сравнивают по значению, а не по `toNumber` (floor). Сброс при смене расы (2026-08-09): `CharacterBuildService.applyRace` дополнительно проверяет выбранные значения параметров «X» против диапазона `[min, max]` модели новой расы — «Врождённая Магия 5» сбрасывается при переходе на расу с потолком 4 (сравнение `DimensionalNumber.compare`); требования больше не единственный фильтр сохранения способностей. Доводка №12 (2026-08-09, группы в «Основе»): выбранные члены группы — primary-чипы в шапке; чип «N ОС» (сумма потраченного на группу, виден при выборе) + счётчик «выбрано X из Y» (Y = `selectLimit`, −1/0 → «∞»); выбранные способности и группы подсвечены бледно-голубым (`--v-theme-primaryLight`); раскрытая панель — тень `0 4px 16px rgba(0,0,0,.22)`; тело группы `0 8px 8px`, первый вложенный участник отделён верхним бордером. **Заход C реализован (2026-08-09):** этап «Личность» (зона `ol`) + полная механика возраста. Новый тип правила `age` (`AgeSpec { ages: Age[] }`, ступень `{ name, ol, featureLimit, effects }`); у вида/расы таблица лет `age_years { age, ageStart, ageEnd }` (наследуется по `parent_race_code`), разрешение — первый диапазон, за диапазонами — «Старый». ОЛ этапа = ступень (`ol.total`), эффекты возраста — real-модификаторы «от возраста» live (условные — scope, «условно: …» в попапе); `CharacterBuild.ageYears` + `CharacterVersion.ageYears`. `PersonalityTab`: выбор возраста (годы → ступень, чипы диапазонов), 15 ol-особенностей (отрицательные стоимости дают ОЛ) с группами общительность/внимательность/богатство, лимит числа особенностей (признак «Богатство» не считается), «ОЛ сгорают»; шаг «Личность» скрывается при отсутствии правила `age` (ТР §7: «если ОЛ нет — пропускается»). Деньги: грант `money` (фикс./% от лимита, max/min) — Обеспеченный 50/50%, Преуспевающий 100/100%, Богатый 400/400%, Нищий min(10 гз, 10%); «только при создании» (при edit заблокированы). Данные: `docs/rule/age/` (Возраст.csv, Эльфы.csv) + ориентиры Человека/Дворфа/Орка из AI.html. План и решения: `docs/specs/character-module-context.md` §4.8 (D94–D100); реализация S11/S12 `docs/rule/import-plan.md`. **Заход «Развитие» согласован (2026-08-09, план):** каталог навыков этапа «Развитие» (`docs/rule/skills/catalog.md` из `docs/rule/skills/AI.html`), типы без новых AbilityType (Реакция/Манёвр — признак действия; Множественный — `skill` + флаг `multiple` + домен-справочник/кастом; Техника — признак; Эффект — `state`; Клич не делаем), новые характеристики Мелкая моторика/Музицирование (Телосложение=Стойкость, Внешности нет), зона покупки (`zone` в записи способности, бюджет списывает только её), требования с размерами через `CharacteristicNumber`, агрегаты «Развитие X» и производный «Ближний бой» вычисляются, боевые — описательно, владение оружием отложено. План и решения: `docs/specs/development-module-context.md` (D103–D113). Раздел описывает целевую функциональность; таблицы доступа ниже действуют после реализации. **Заход «Развитие» реализован (2026-08-11):** вкладка «Развитие» (зона `or`, виртуализированный каталог `DevelopmentTab` + `DevelopmentAbilityNode`), типы по D106 (признаки действия, множественный навык, эффект — state), зона покупки `zone` в записи способности (бюджет списывает только её, D111), агрегаты «Развитие X» (D108) и производный «Навыки боя» (D109) с дарами `+уровень к характеристике` — `forEachAggregateGrant` обобщён и покрывает `derived_level`. Множественные навыки (Фаза 1): экземпляры — по записи на домен в `build.abilities` (`domain`/`domainCode`), словари доменов по типам правил (`DOMAIN_REF_RULE_TYPES`: species/language; языки — `LanguageSpec`, мок rule-394..397), методы `add/set/removeAbilityInstance`, агрегация max по экземплярам в снапшоте/формулах/требованиях, бюджет = сумма по экземплярам. Фаза 2: экземплярные улучшения (`multiple` + родитель) привязываются к экземпляру родителя, пер-экземплярные требования (`has_ability` домен-скоуп, `domainContext`), каскады remove/rename домена; UI домена — `VCombobox`. Вкладки «Основа» и «Личность» приведены к решению «Развитие»: один `VirtualList` лёгких строк (`EditorAbilityRow`) + группы (`EditorAbilityGroupNode`), удалены `EditorAbilityPanels`/`EditorAbilityGroupPanels`, «Выбранные» — чип. Коммуникация: характеристика `communication` → «Красноречие» (автополучение {3|0}), способность «Красноречие» → «Тренировка Красноречия» (`+уровень к Красноречию от тренировки`), «Развитие общения» (`+уровень от развития`, агрегат `method-communication`), «Манера общения» — навык с одиночным доменом (`domain_ref` без `multiple`, статичный словарь `DOMAIN_STATIC_OPTIONS` типов проверок общения), «Общительный» дарит «Тренировку Красноречия 1» и «Манеру общения». Бой: характеристика `melee-combat` → «Мастерство боя» (автополучение {3|-1}), «Ближний бой» → «Навыки боя» (производный 2/8/16, грант `+уровень к Мастерству боя от тренировок`). Автоматические характеристики: `CharacteristicSpec.automatic` (`boolean | { value }`) — Память/Мышление/Внимательность/Реакция/Красноречие/Мастерство боя; база {3|0} или значение из спеки, раса (fixed-база) и дар приоритетнее. Дары-навыки D100: потребление реализовано — апгрейд дарованного навыка списывает только разницу сверх подаренного уровня (`giftedLevel` в `EditorAbility`, `buildBudgets` вычитает стоимость дара). Доплата «Общие черты» (`purchase_surcharge`) выводится на «Основе»: `osSurcharge` в бюджете (ui-канал механик вместо отбрасывания), инфо-строка с описанием правила и чипы «+2 ОС» на доплаченных чертах. Блок характеристик навигации: только основные (`primary`), чип «все» — попап всех характеристик по группам (`buildAllEditorStatViews` + `EditorAllCharacteristicsPopup`).

> **Заход «Сохранение/редактирование + авто-ресурс ОД» (2026-08-13):** ресурс ОД стал авто-добавляемым (`ResourceSpec.auto_add` + `limit { base, adjustments }`; `initial_value` удалён): лимит = база 5 + размер Ловкости + размер Восприятия + разница Сила−Вес (полные размеры, `trunc(modifyDiffTo/3)`), минимум 0 (при 0 персонаж не может действовать, ресурс отображается). Новые виды `Formula`: `characteristic_size` и `characteristic_size_gap`. Новая характеристика «Вес» (`CharacteristicSpec.base_from {strength, [innate]}` — база = база Силы с врождёнными модификаторами). Версия хранит `budgets { osTotal, moneyBudget }` — edit восстанавливает лимиты ОС/денег; `points.orTotal` допускает null (персонаж без лимита ОР). Гейтинг «Сохранить/Готов» полный: имя + раса + не превышены ОС/ОЛ/ОР/деньги + все требования взятых способностей (список проблем — алертом). Возраст авто-меняется только если не подходит шкале (зажим к минимуму ступени). Баг: врождённые черты характеристик больше не считаются «общими» (не доплачиваются механикой `purchase_surcharge`).

> **Заход «Редактор листа переиспользуемый» (2026-08-13, Н2a):** ключ черновика `characterDraft` обобщён (`characterId` → `draftKey: string | null` с пространством имён `character:${id}`/`npc:${id}`); вкладки редактора принимают `draft-key`. Выделен переиспользуемый `CharacterSheetEditor` (черновик/правила/механики/модель/валидация «Готов» с опцией `requireRace`, эмитит `save(version)`); `CharacterEditPage` — тонкая обёртка. Публичные ре-экспорты из `Character/init.ts` (сервисы редактора + `CharacterSheetEditor` async). Поведение персонажей не изменилось (гейт зелёный); фундамент для редактора листа НПС и видимости персонажей в игре.

### Этапы создания

#### 1. Выбор расы

- Если создание через Игру — ОС и ОР определяются игрой. Если свободное — можно задать или оставить пустыми.
- Первым делом — выбор расы (вида и подвида).
- Карточка расы: полная информация (характеристики, черты, особенности).
- Итем расы в списке: краткое описание.
- Фильтры: по виду, по тегам, по названию.
- **Раса тратит ОС из бюджета игры, а не определяет его.** Значение ОС в профиле расы — это её стоимость (отрицательная стоимость = даёт ОС). Бюджет ОС задаётся игрой или вручную.

#### 2. Распределение ОС (этап «Основа»)

- Тратятся на врождённые черты.
- Черты имеют фильтры: **доступные** (можно взять сейчас), **недоступные** (с указанием причины), **расовые** (открываются расой), **общедоступные** (без расовых ограничений).
- Во время выбора — live-изменение характеристик.
- Можно потратить больше лимита → персонаж в статусе «Черновик».
- **Из документов:** у каждой расы профиль (ОС, характеристики, бесплатные черты, опции).

#### 3. Особенности личности (этап «Личность», ОЛ)

- После траты ОС.
- Могут давать способности.
- Могут быть отрицательными (давать ОЛ, а не тратить).
- **Не во всех версиях правил есть этот этап** — если ОЛ нет, пропускается.

#### 4. Способности за ОР (этап «Развитие»)

- После траты ОЛ (или после ОС, если ОЛ нет).
- Навыки, черты, действия — всё, что покупается за ОР.
- Деревья/ветки навыков, требования.

#### 5. Закупка (инвентарь)

- После распределения ОР.
- Бюджет в валюте «Деньги» (ключ `money`) — задаётся игрой или вручную.
- Список доступных предметов из правил пространства, фильтр по категории.
- Предметы добавляются в инвентарь персонажа с указанием количества.
- Остаток бюджета конвертируется в монеты в инвентаре (формат «5 гз, 7 гс, 9 гм»).

#### 6. Сохранение

- **Черновик:** в любой момент, без проверки требований, даже при перетрате лимитов.
- **Готов:** только при соблюдении лимитов и всех требований.
- **Единый черновик** — при повторном редактировании обновляется существующий, без создания новой записи. Смена статуса — без создания новой записи.
- **История персонажа** — отдельный этап, позже.
- **Статус листа** (`characters.status`) — только `draft | ready | needs_fix` (валидность листа, без игровой семантики). «Модерация» — не статус листа, а состояние членства в игре (`game_characters.membership_status`).

#### 7. Редактирование после создания — общий случай

- В любой момент. Каждое изменение → новая версия (copy-on-write).
- Вне игры — без чужого подтверждения.
- Между этапами создания (раса → основа → личность → развитие → закупка) — **свободное переключение**.
- Если изменение ранее сделанного выбора (например, смена расы) делает часть выбранных способностей недоступными — показывается **предупреждение**. При подтверждении — недоступные способности сбрасываются.

#### 8. Редактирование в игре — сессионная модель

- Игрок правит персонажа (инвентарь, характеристики, способности) — все изменения **накапливаются в автосохраняемом черновике**.
- Черновик сохраняется непрерывно (по каждому изменению + периодически). Не теряется при закрытии браузера.
- «Живой» персонаж в игре остаётся неизменным, пока черновик не отправлен на модерацию.
- По окончании сессии (или в любой момент) игрок нажимает **«Отправить на модерацию»** → черновик становится новой версией, уходит ведущему.
- Ведущий утверждает все изменения разом или отправляет на доработку.
- **Ведущий** имеет право `game.edit_inventory` — редактировать инвентарь персонажей игры напрямую (без модерации).

**Модель модерации (2026-08-13, контракт для модуля игр):** у членства в игре (`game_characters`) **два состояния листа + черновик**: `active_json` (одобренное, «в игре»; NULL до первого одобрения), `pending_json` (поданные изменения на модерации), `draft_json` (сессионные правки, эфемерно). Сценарии: создан для игры/подача готового → `pending` → approve → `active`; правка в игре → `draft` → submit → `pending` → модерация **diff(active, pending)** (сравнение до/после) → approve (`active = pending`) или reject (pending сброшен, active прежний = откат). Один и тот же персонаж может быть в нескольких играх — у каждой своя запись `game_characters` со своими состояниями, линии не пересекаются. Снимки хранятся на самой записи (по одному на состояние, без истории), память ограничена. Утилита diff реализована (2026-08-13): структурный `membershipDiff` — по-элементное сравнение списков листа (характеристики/ресурсы/способности по `ruleId|domain`/инвентарь по экземплярам/состояния/чувства) + скаляры, имена правил из ревизии; UI — сгруппированные секции с чипами Добавлено/Удалено/Изменено, первая подача — лист в слайдере.

### Модификаторы

Модификатор — это источник + цель + значение. Логика: к одной **цели** от одного **источника** применяется только самый сильный бонус и самый сильный штраф.

Примеры:

- `+3 к Силе от тренировок` + `+1 к Силе от тренировок` = `+3`
- `+3 к Силе от тренировок` + `-1 к Силе от тренировок` = `+2`
- `+3 к Силе от тренировок` + `+1 к Силе от совершенства` = `+4`

Логика общая для всех модификаторов (характеристики, помехи/преимущества). Временные модификаторы не поддерживаются в первой версии.

### Состояния персонажа

Блок «Состояния» на странице персонажа строится из правил типа `state`: список записей `{stateRuleId, value?}` (один и тот же ruleId может повторяться). Имя/иконка/значение резолвятся из ревизии; повторы объединяются по `StateSpec.aggregation` (sum/max/independent). См. `docs/specs/state-design.md`. Изменение состояний — без модерации (runtime).

### Редактирование персонажа

- Любое изменение создаёт новую версию персонажа (версионирование, как у правил).
- Можно откатиться к предыдущей версии.
- При переходе на новую версию правил:
  - Старый персонаж остаётся в игре (если он в игре).
  - Новая версия проходит валидацию.
  - Если не проходит — на модерацию к ведущему.
  - Ремап ссылок — по семантическому `code` правила (глобально уникален, работает между пространствами).
  - Инвентарь не перезакупается: список предметов и деньги переносятся как есть (лимит — наличные); предметы с удалённым правилом → кастомные «предметы мастера» (`rule_id` null + имя/описание мастера).
  - Валидация идёт по реальным лимитам персонажа (лимиты игры + бонусы ГМ `game_characters.os_bonus/or_bonus/ol_bonus`).
  - Результат миграции классифицируется: ok (чистый ремап) / resolved (отличия авто-резолвятся, недобор зелёным) / conflicts (удалённые правила, невыполненные требования, перерасход, удалена раса) → редактор на новой версии с авто-сбросом невалидного; миграция — черновик, продолжается позже.

### Согласованные решения

#### Архитектура расчётов

✅ **Фронт — активные расчёты, бэк — только валидация.**

- При каждом выборе/изменении на фронте — минимальный пересчёт (только то, что изменилось).
- При сохранении бэк проверяет: лимиты, требования, соответствие версии правил.
- Черновик сохраняется без валидации в любом состоянии.

#### Фильтры черт при выборе

✅ Четыре категории: доступные, недоступные (с причиной), расовые, общедоступные.

#### Редактирование персонажа — copy-on-write

✅ **При редактировании готового персонажа создаётся его копия-черновик.** Оригинал остаётся нетронутым. Когда игрок нажимает «Готово» — копия проверяется и, если всё ок, подменяет оригинал.

Этот же механизм работает для перехода между версиями правил: старая версия P_v1 остаётся в игре, новая P_v2 = копия-черновик, которая проходит проверку и затем подменяет P_v1.

#### Статусы (уточнение)

- **Черновик** — не снимается автоматически. Нужно нажать кнопку «Готов».
- **Готов** — персонаж соответствует всем требованиям.
- Для входа в игру — требуется одобрение ведущего.
- Вне игры — изменения без чужого подтверждения.
- **В игре** — изменения накапливаются в автосохраняемом черновике (сессионная модель). По окончании сессии игрок отправляет изменения на модерацию ведущему.

#### Редактирование в игре — сессионная модель

✅ **Сессионная модель:** игрок редактирует персонажа в игре — изменения пишутся в автосохраняемый черновик. «Живой» персонаж не меняется. По окончании сессии (или в любой момент) игрок отправляет черновик на модерацию. Ведущий утверждает все изменения разом или отправляет на доработку.
✅ **Ведущий** может напрямую редактировать инвентарь персонажей игры (право `game.edit_inventory`).

#### Отрицательные ОЛ

✅ Неиспользованные ОЛ **сгорают**. Если особенность дала +2 ОЛ, а игрок их не потратил — они пропадают.

#### Сила удара/броска/выстрела (модель оружия)

✅ **Сила удара/броска/выстрела — НЕ урон оружия**, а промежуточное значение действия: `характеристика (Сила) → + модификаторы атаки (сильный удар +2 и т.п.) → Сила удара → + модификатор оружия → Урон`. Характеристика всех трёх действий — Сила. В модели — `Formula { type:'actionCharacteristic', action, characteristic, modifier[] }`; модификаторы атаки (экшны) подключатся позже. МКС — «на сколько полных размеров сила удара превышает минимальную силу оружия» (пер-оружие мастерство; заход «Владение оружием»).

#### Владение оружием (направление)

✅ Множественный навык (домен = группа оружия, покупка за ОР), лестница стоимостей уровней — на предмете. Статы мастерства: «Мастерство ближнего боя» (`melee-combat`) и «Мастерство дальнего боя» (`ranged-combat`, метание + стрельба). «Псевдо-статы» оружий — производные тайлы (база стата + бонус владения), не отдельные характеристики.

#### Лимиты при свободном создании

✅ Если лимиты ОС/ОР не заданы — персонажа можно сделать готовым в любой момент, при условии корректности всех расчётов и соответствия версии правил.

#### Будущие расширения (контекст)

- **История персонажа** — имя, предыстория + хронологический лог сообщений.
- При проектировании архитектуры БД и кода учитывать возможность добавления новых этапов создания.

### Раздел `/characters`

| Путь                         | Страница                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | Доступ                                                      |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| `/characters`                | **Список персонажей.** Поиск + кнопка «Новый персонаж» в одной строке. Кнопка → сразу выбор расы. Фильтры: имя, раса, игра, статус версии (черновик/готов/на модерации/требует исправления), владелец, кол-во потраченной валюты (ОС/ОЛ/ОР). Фильтр «в игре» — по наличию `game_id`. Мини-карточка: имя, раса, ОР (текущее), краткое описание. Черновики видны только владельцу. Отступы вёрстки согласно скриншотам.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         | Все (видят только доступных)                                |
| `/characters/new`            | **Создание.** Настройка свободного создания: пространство правил + ревизия + лимиты ОС/ОР/денег (пустые — без лимита) → редактор `/characters/new/editor`. Создание «через игру» — во вкладке «Персонажи» игры: лимиты/правила из игры, персонаж сразу подаётся (членство pending).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | `character.create`                                          |
| `/characters/:id`            | **Карточка персонажа.** Вкладки: Обзор, Описание, Способности, Инвентарь, Обсуждение. Шапка: имя, раса · игра, статус, владелец, кнопка «Редактировать» (владелец). Обзор: блок «Правила» (привязка к ревизии, ссылка на `/space/{code}/{vN}`, ссылки на конкретные правила из ревизии) + характеристики (значение + модификаторы source→delta→target) + ресурсы (current/max, прогресс, размерность) + валюты + состояния (collapsible) + Защита (доспехи по слотам/щит) + Атаки (оценка формул). Способности: паттерн «фильтр + табы + список» — поиск по имени/описанию, под ним табы Все/Избранное/Навык/Черта/Заклинание; каждая способность — раскрывающаяся панель (шапка: название, уровень, тип способности, у действий — «ОД: N», у заклинаний — «Сотворение: N · Сложность: N · Длительность», иконки «открыть правило в слайдере» и «в избранное»; тело: описание + признаки — ключевые слова). Избранное — per-character в localStorage. Описание — только краткое/полное описание (состояния — во вкладке «Обзор»). Обсуждение — встроенный чат `character_discussion` (discussionChatId). Доступ: владелец всегда; чужой — с `character.view`; иначе NotFound. | Владелец всегда; чужой — `character.view`                   |
| `/characters/:id/edit`       | **Редактирование.** Copy-on-write: черновик-копия оригинала (оригинал не меняется). Общий редактор `CharacterEditPage` с табами Раса → Характеристики → Основа → Личность → Развитие → Инвентарь → Описание, шапка-сводка бюджетов ОС/ОЛ/ОР/денег, автосохранение черновика (localStorage), «Готово» → валидация (имя + лимиты) → подмена версии. Реализованы: «Раса» (смена расы со сбросом несовместимых), «Характеристики» (покупка по лестницам + врождённые черты), «Основа» (черты ОС), «Личность» (ОЛ + возраст), «Развитие» (ОР), «Описание». «Инвентарь» — заглушка.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 | Владелец. В игре — сессионная модель (черновик → модерация) |
| `/characters/:id/versions`   | **История версий.** Список версий, просмотр, откат.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | Владелец                                                    |
| `/characters/:id/migrate`    | **Перевод на новую версию правил.** Выбор версии (любое пространство), diff, запуск миграции → отчёт (ok/resolved/conflicts): ремап ссылок по `code`, пересчёт с реальными лимитами персонажа, предметы с удалённым правилом → кастомные «предметы мастера» (ruleId null + имя/описание); конфликт → редактор на новой версии (авто-сброс невалидного), черновик resumable, «Сравнить до/после».                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              | Владелец                                                    |
| `/characters/:id/deactivate` | **Деактивация.** Персонаж скрывается из списков, данные сохраняются.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | Владелец                                                    |

---

## 8. Игры

> **Статус фронта:** реализовано ядро модуля (2026-08-13): список `/games` (карточки + `FilterBar` по названию/статусу/владельцу/видимости), создание `/games/new` (пространство + ревизия, статус, видимость, join-policy, лимиты ОС/ОЛ/ОР/денег), карточка `/games/:id` (шапка: статус/видимость/владелец, вкладки Обзор/Участники). Гейтинг: `game.create` на создание; просмотр — по статусу/видимости/участию (`Utils/access.ts`), редактирование/модерация — предикаты по роли + per-game права. Фаза 2 (2026-08-13): вкладки карточки «Участники» (роли/инд. права/добавление/удаление + приглашения), «Персонажи» (членства `game_characters`, подача готового персонажа), «Модерация» (панель ведущего: pending-членства, полевой diff(active, pending) → approve/reject). Разделы `/games/:id/members|characters|moderate|invitations` из таблицы ниже реализованы вкладками карточки. Редактирование `/games/:id/edit` (2026-08-13): общая форма `GameForm`, пространство/ревизия зафиксированы (привязка персонажей); вкладка «Обсуждение» — встроенный `game_discussion`-чат (generic `ChatThread` в Messages/Chat, создаётся при создании игры). Вкладка «НПС» (2026-08-13): список по видимости, inline-добавление (ведущий) / inline-предложение на модерацию (игрок), модерация предложений, карточка НПС по видимости, теги + поиск; полный лист персонажа — следующий шаг. **Видимость листа — единая ролевая модель (2026-08-13, Character):** `SheetVisibility` = правила `{ audience: all/gm/список, sections }`; инъекция ролей (Game регистрирует `'gm'` с fullAccess — ведущие видят всё); оценка `Character/Utils/sheetAccess` (владелец/super_admin всегда, fullAccess-роль всегда, иначе секции по аудиториям). **Видимость «вообще»** (на персонаже, применяется везде): `Character.visibility` (дефолт — полный лист), зеркало на членстве `game_characters`; **блокировка**: `canViewCharacter` через `canSeeSheet` → 404, список `/characters` фильтрует (нет доступа — невидим); `CharacterDetailPage` рендерит лист по секциям. Расширение карточки «Видимость листа» (plugin-контракт `registerCharacterCardExtension`); глаз в строке НПС. НПС — на той же модели. Standalone и игры согласованы. **Добыча (2026-08-13):** вкладка в карточке игры — ГМ готовит лут в запас (предметы из ревизии / деньги, свободная группа-тег), выдаёт «на разбор», игроки «проявляют интерес»; раздача: предмет — одному получателю (игрок/НПС/«вникуда»), деньги — «поровну между заинтересованными» или вручную (доли игрок/НПС/«вникуда», остаток — вникуда). **Создание персонажа «через игру» (2026-08-13):** во вкладке «Персонажи» кнопка «Создать в игре» → редактор на правилах/лимитах игры, персонаж создаётся сразу с членством (pending на модерацию ГМ). **Модерация — полноценный diff (2026-08-13):** структурный `membershipDiff` (по-элементные изменения списков листа + скаляры, имена правил из ревизии), сгруппированные секции с чипами Добавлено/Удалено/Изменено; первая подача — лист в слайдере. **План (2026-08-13):** очки ГМ (гранты `os_bonus/or_bonus/ol_bonus` на членстве → реальные лимиты персонажа, «Выдать очки» в CharactersTab), смена ревизии игры (GameForm — любая ревизия любого пространства, персонажи «требуют перехода»), перевод персонажей на новую ревизию (`/characters/:id/migrate`, ремап по code, кастомные предметы, классификация ok/resolved/conflicts, конфликт → редактор; в игре — миграция → pending → модерация). Детали: `docs/specs/character-module-context.md` §4.10, `docs/specs/game-module-context.md` §7.13. **Шаг А реализован (2026-08-13):** очки ГМ (гранты + «Выдать очки»), смена ревизии игры (GameForm разблокирован), кастомные предметы (`InventoryItem.ruleId` nullable + name/description, рендеры). **Шаг Б реализован (2026-08-13):** миграция персонажа — `CharacterMigrationService` (ремап по code, классификация ok/resolved/conflicts, авто-сброс невалидного, кастомные предметы), API `character.migrate`/`applyMigration` + `previousVersion`, `/characters/:id/migrate` (выбор ревизии, отчёт, конфликт-редактор), в игре — диалог миграции (реальные лимиты = лимит игры + гранты) → `submitCharacterMigration` → pending → модерация, индикатор «Требует перехода». Мутации правил в моке (night-vision на ревизии ≥ 8) — демо. **Доводка (2026-08-13):** честный диф (обе модели, сравнение значений — без ложных «Реакция −3» и «ОС 20 → 3»); атрибуция бюджетов по способностям + каскады; имена правил в отчёте; «Исчезнут»-характеристики + сброс некорректной расы; потолки снаряжения не ужимают характеристику при отсутствии базы; in-game миграция — слайдер, «Сравнить до/после» в конфликт-редакторах; RaceTab не молча сбрасывает расу; Гаррик переведён на расу Ацелатль (была сломана вид-«Человек»). **Чат игры (live-сессия, 2026-08-16):** вкладка «Чат игры» — живой `game`-чат (сообщения «от лица кого»: персонаж/НПС/ведущий), кнопки «Начать/Остановить сессию» (2026-08-19: ранее «Начать/Завершить игру»; остановка сессии → in_process + модерация боевых изменений, терминальный `completed` ставится отдельно), чипы правил и пикер из ревизии игры, шкала инициативы (GM-панель), дефолты бросков из правила «Бросок» ревизии (Вариант А; полное «механики влияют на бросок» — отдельный заход). Летопись — следующий заход. **Уникальные правила (2026-08-17):** кастомные правила ГМ — **текстовые записи на персонаже** (`CharacterVersion.customRules`), вкладка «Уникальные правила» в карточке персонажа (владелец — просмотр; ГМ в карточке игры — управление). ГМ выдаёт «на ходу» (карточка игры → Персонажи → «Выдать кастомное правило», `character.addCustomRule`), затем «Оформить как правило» (переход в редактор правила с предзаполнением, черновик → коммит) и «Заменить на правило» (пикер из ревизии игры, `character.updateCustomRule` → `deprecated` + `replacedWithRuleId`). Записи не привязаны к ревизии → не требуют поддержки в чатах/слайдерах/бюджетах/модерации и переживают смену ревизии. Решения — game-спека §7.21 D104–D109. Контекст: `docs/specs/game-module-context.md`. **Резолюция правил из ревизии (2026-08-21, §7.20 Слой 1):** `RuleSlider`/`RuleChip` резолвят правило по `(spaceId, rulesRevision)` из среза ревизии (`fetchRevision`), а не из глобального каталога по `id`; контекст ревизии прокинут через `ChatRulesContext` (игровой чат/обсуждения/мессенджер) и `CharacterDetailPage`. Вкладка «Правила» в карточке игры отклонена — дублирует браузер ревизии пространства (`/space/{code}/{N}`, ссылка уже есть в Обзоре). Гейт 922/922. **2026-08-26:** на карточку списка `Game` вынесены `gameChatId` и `discussionChatId` (резолв чипов без N деталок; на `GameDetail` — дубль для вкладок).

### Статусы игры

Статус игры — это этап жизненного цикла, не смешивается с видимостью.

#### Жизненный цикл

`Черновик → Набор игроков → В процессе → На паузе → Идёт игра → Завершена`

| Статус            | Описание                                                    |
| ----------------- | ----------------------------------------------------------- |
| **Черновик**      | Игра создаётся, видна только создателю и `game.view_all`    |
| **Набор игроков** | Игра ищет участников. Можно подавать заявки                 |
| **В процессе**    | Игра началась, но не в данный момент (межсессионный период) |
| **На паузе**      | Игра приостановлена ведущим                                 |
| **Идёт игра**     | Игра прямо сейчас в активной сессии                         |
| **Завершена**     | Игра окончена, все данные read-only                         |

#### Видимость и вступление

Независимые от статуса настройки:

**Видимость** (кто видит игру в списке): все / друзья / принятые игроки / приглашённые / определённый список.

**Вступление** (кто может подать заявку): никто (только по приглашению) / все, кто видит / друзья / определённый список.

### Роли в игре

- **Владелец** — создатель игры, всегда ведущий. Может приглашать других ведущих.
- **Ведущий** — пользователь с правами на редактирование и модерацию в игре (по решению владельца).
- **Участник** — игрок, чей персонаж в игре. Может получить дополнительные права от владельца (индивидуально).
- Владелец может выдавать участникам индивидуальные права: редактирование игры, модерация пользователей и т.д.

### Модель выдачи прав на объекты

Права на игру или пространство выдаются двумя способами (работают одновременно):

1. **Через группы:** группе назначаются права на объект → все участники группы получают эти права.
2. **Индивидуально:** конкретному пользователю выдаётся право на объект.

Права суммируются: если пользователь получил `game.edit` через группу и `game.moderate` индивидуально — у него есть оба.

### Раздел `/games`

| Путь                     | Страница                                                                                                                                                                                             | Описание                                 | Доступ |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------- | ------ |
| `/games`                 | **Список игр.** Фильтр по статусу, названию. Карточки: название, статус, владелец, число участников.                                                                                                 | Все (мок фильтрует по видимости/участию) |
| `/games/new`             | **Создание игры.** Название, краткое описание (для карточки в списке), полное описание, пространство правил, статус.                                                                                 | `game.create`                            |
| `/games/:id`             | **Страница игры.** Название, описание, статус, правила (пространство+время, лимиты ОС/ОР, теги/запретные теги), блок создателя/ведущих, допустимые/рекомендуемые расы, краткая сводка по персонажам. | По статусу игры                          |
| `/games/:id/edit`        | **Основные настройки.** Название, краткое описание (для списка), полное описание, статус (черновик/набор игроков/в процессе/на паузе/идёт игра/завершена), картинка, допустимые/рекомендуемые расы.  | Владелец, ведущие                        |
| `/games/:id/characters`  | **Персонажи игры.** Полный список персонажей игры с привязкой к игре (в игре / вне игры) и статусами (черновик/готов/модерация), фильтры, быстрый просмотр.                                          | По статусу игры                          |
| `/games/:id/rules`       | **Правила игры.** Версия правил, лимиты ОС/ОР, теги, запретные теги.                                                                                                                                 | Владелец, ведущие                        |
| `/games/:id/members`     | **Участники.** Список игроков, назначение ведущих, выдача индивидуальных прав.                                                                                                                       | Владелец, ведущие                        |
| `/games/:id/invitations` | **Приглашения.** Отправленные, просмотренные, принятые, отклонённые.                                                                                                                                 | Владелец, ведущие                        |
| `/games/:id/moderate`    | **Модерация.** Персонажи на модерации / требующие исправления.                                                                                                                                       | Владелец, ведущие                        |
| `/games/:id/loot`        | **Добыча.** Список доступной/добытой добычи, кнопки «Проявить интерес», раздача.                                                                                                                     | Владелец, ведущие                        |
| `/games/:id/chronicle`   | **Летопись.** Хроника событий по сдвигу от точки отсчёта (игровое время), связанные персонажи/НПС.                                                                                                   | По статусу игры; запись — ведущие        |

### НПС игры

> **Статус фронта (2026-08-13):** вкладка «НПС» в карточке игры — список, inline-добавление (ведущий) / inline-предложение на модерацию (игрок), модерация предложений, настройки видимости (scope + секции, три пресета), карточка НПС (просмотр по видимости), **описательные теги НПС + поиск по имени и тегам**. **Редактор листа НПС (Н2b, 2026-08-13):** «Редактировать лист» (ведущий) → NpcEditPage через общий `CharacterSheetEditor` (`requireRace: false`, без лимитов), сохранение `version` в НПС; лист отображается по видимым секциям. **Версионность НПС (п.22, 2026-08-25):** устаревший лист — «Перевести» тем же движком миграции, apply сразу в `npc.version`. **Создание персонажа «через игру» (Н2d, 2026-08-13):** «Создать в игре» во вкладке «Персонажи» → `GameCharacterNewPage` на правилах/лимитах игры → `createGameCharacter` (персонаж + членство pending).

- **НПС — по сути персонаж игры без владельца-игрока**: полный лист `version` (CharacterVersion, как у персонажей), на текущем шаге заполнен только минимум (имя + описания).
- **Видимость для игроков** (в отличие от исходной формулировки «только ведущие»): ведущие видят все НПС; игроки — по зоне видимости и набору видимых блоков.
  - **Зона видимости (scope):** все участники / только ведущие / выбранные игроки.
  - **Блоки информации (sections):** краткое описание, полное описание, раса, состояния, характеристики, ресурсы, способности, инвентарь. **Имя НПС видно всегда**, когда НПС видим.
  - **Три частых состояния (пресеты):** «Скрыт» (только ведущие), «Имя и краткое описание», «Полностью»; секции настраиваются на каждом НПС гранулярно (инвентарь/ресурсы/способности обычно скрыты, если НПС не член группы персонажей игроков).
- **Inline-добавление (ведущий):** кнопка «+» → строка с именем → «Сохранить» → запись в БД.
- **Предложение НПС (игрок):** вместо добавления — inline-предложение (имя + краткое описание) на модерацию ведущему; ведущий принимает (НПС становится активным) или отклоняет.
- **Описательные теги НПС** (`tags`) — роль/тип (торговец, наёмник, антагонист...); поиск по списку НПС идёт по имени и тегам.
- Характеристики НПС заполняются напрямую, без привязки к правилам расы (при появлении листа).

### Чат игры

> **Статус фронта (2026-08-16):** вкладка «Чат игры» в карточке игры — живой игровой чат (`type 'game'`, `gameChatId`), сообщения «от лица кого» (персонаж/НПС/ведущий), кнопки «Начать/Остановить сессию» (2026-08-19: переходы статусов playing ↔ in_process, `canEditGame`; остановка собирает боевые изменения на модерацию; `completed` — терминальный статус отдельно), чипы правил и пикер «Вставить ссылку» из ревизии игры. **Полноценные броски (2026-08-16, Вариант Б, спека §7.17 D90–D94):** бросок резолвится **механиками ревизии** через общую событийную систему механик (события `roll.pool`/`roll.drop`/`roll.score`; хендлеры мутируют контекст, движок не знает семантику) — «Помехи/Преимущества» (лишние кубы + убрать худшие/лучшие), «6 и 1» (1 → доп. успех, грань → −1), пер-ролл механики (Критический удар) поверх. Правило «Бросок» объявляет всегда-действующие механики (`sub_mechanics`); дефолты (сложность/преимущества/размерность) — оттуда же. Результат показывает применённые механики (чипы). Инициатива использует тот же RollEngine. **Шкала инициативы (2026-08-16, переделка):** окно проверки «Инициатива» (ГМ выбирает участников — персонажи+НПС, метод: характеристика с дефолтом «Восприятие» / свободный бросок из правила «Бросок» / фиксированное значение, модификатор — системный `modifyWith`); результат броска нужен **только для порядка** (не хранится; сорт `DimensionalNumber.compare`, 3↓ < 2); результаты в чат одним сообщением с roll-вложениями; системное уведомление `--- Ходит Имя ---` при установке/передаче хода; «Передать ход» (ГМ или владелец активного персонажа), «Закончить/Продолжить» (данные сохраняются), «Добавить в бой». Решения — game-спека §7.14 D68–D74, §7.15 D75–D81, §7.17 D90–D94. **Запуск проверки (2026-08-22):** кнопка «Проверка» — соло (ask / `{0|0}` / `from_state`) и pairwise-оферта (одно окно, accept/правка, кубы после согласия, мок без SSE); инициатива своим диалогом; удар — только спека.

- **Чат игры** — общий чат для игровых сессий. Игроки пишут только от имени одного из своих персонажей (выбор персонажа при отправке).
- Ведущий пишет от роли ведущего, от имени персонажа или НПС.
- Сообщение можно скрыть от всех, кроме GM. Часть сообщения может быть скрыта выборочно (спойлер с доступом). **Реализовано (2026-08-16, game-спека §7.19):** роли чата + права (`chat.see_all` у `'gm'` игрового чата и у `'owner'` обсуждения персонажа — видят всё, включая скрытое); видимость сообщения `{ all?, forRole?, forUsers? }` через меню «Всем / Только ГМ / Выбранным» в игровом чате, обсуждениях и групповых чатах (`admin`/`member` без see_all); отправитель всегда видит своё и может **изменить видимость своего отправленного сообщения** (`updateMessageVisibility`). **Скрытие — на уровне бэка/моков** (единственный гейт): скрытые сообщения не доставляются, unread/превью — по видимым. Спойлер (часть сообщения) — отдельный заход.
- **Броски кубиков** — по умолчанию видны только GM и бросившему, если не указано иное. Расчёт — **механиками ревизии** (событийная система, спека §7.17): «Помехи/Преимущества», «6 и 1», пер-ролл механики (Критический удар); дефолты (сложность/преимущества/размерность) резолвятся из правила «Бросок» ревизии, `dieSize` — вход механики (применяется к итогу успехов).
- **Ссылки на объекты** (правила, персонажи, НПС) — вставляются как inline-чипсы. При нажатии — открывается слайдер с просмотром. Если объект скрыт от игрока — вместо содержания показывается placeholder «Объект скрыт». **Контекст правил резолвится по открытому чату** (спека §7.18): игровой чат/обсуждение игры — ревизия игры, обсуждение персонажа — ревизия персонажа, обычные чаты — последняя ревизия пространства «Актуальные правила» (провайдеры в Chat; игровой чат в мессенджере — обязательно корректная ревизия, иначе ссылки на правила ломаются). **2026-08-26:** хост отдаёт чипам data-срез `ChatInlineRendererContext` (подписи токенов + ключи среза); `RuleChip` читает `tokenLabels`, без среза — «Объект скрыт» (не `getRules(0)`); сбой резолва в мессенджере — отдельный F17, не `chatError`.
- **Шкала инициативы** (см. Совместные действия) — визуально отображается в панели чата.

### Летопись

> **Статус фронта (2026-08-16):** вкладка «Летопись» в карточке игры (`ChronicleTab`). Хроника событий с игровым временем: каждая запись имеет сдвиг от точки отсчёта — эпоха «от Начала приключения» (пока единственная, `Enum/ChronicleEpoch`; задел под свои эпохи). **Игровые единицы времени фиксированные** (пока; в перспективе — настройка правил): 1 год = 10 месяцев · месяц = 3 декады · декада = 10 дней · день = 30 часов · час = 60 минут (`Utils/gameTime.ts`); минута — минимальная хранимая/отображаемая единица (ходы/секунды — подминутная гранулярность, в летописи не используются). Записи сортируются по сдвигу (каноническая форма, «Название (1 день и 4 часа от Начала приключения)»), при изменении сдвига запись меняет позицию — **осознанное расхождение с ручной сортировкой ниже (спека §7.16 D86)**. Записи создаёт/правит/удаляет ведущий (`canEditGame`), игроки — read-only. **Ссылки на approved-персонажей и активных НПС — инлайн-токенами `[[character:id]]`/`[[npc:id]]` в содержимом (как в чате):** пикер «Вставить ссылку» (общий `InlineTokenPicker`, вынесен из `ChatInput`), чипы в тексте → просмотр листа по видимым секциям. Форма сдвига — сетка 3×3 (Годы/Месяцы/Декады · Дни/Часы/Минуты) с лимитами-порогами (месяцы ≤ 9, декады ≤ 2, дни ≤ 9, часы ≤ 29, минуты ≤ 59). Решения — game-спека §7.16 D82–D89.

- Таймлайн, привязанный к игре, персонажу или региону (отдельные FK-поля `game_id`, `character_id`, `region_id`). Ровно один владелец. На фронте реализована привязка к игре (`Chronicle`, id = gameId, создаётся лениво).
- Записи создаются ведущим (для игры) или владельцем (для персонажа). Игроки видят read-only.
- События: встречи с НПС, смерть персонажей, значимые события. **Игровое время — сдвиг от точки отсчёта в фиксированных единицах (`GameTime`), не произвольная строка.**
- Сортировка записей — **по сдвигу от точки отсчёта, не по дате создания и не ручная (`sort_order` не используется фронтом; расхождение зафиксировано в спека §7.16 D86).**

### Известные баги и план (2026-08-20)

> Зафиксировано по результатам ревизии игрового модуля. Реализация — по отдельным заходам (нумерация решений при реализации).

**Баг 1. Рассинхрон версий персонажа в игре и на карточке; нет кнопки редактирования.** — **РЕШЕНО 2026-08-20** (модель версий + единый роутер записи + модерация с выбором конфликтов + in-game редактор).

- Наблюдение: карточка персонажа показывает самую свежую версию (`mockCharacters.versions[id]` — источник истины). Членство в игре (`GameCharacterMembership`) несёт только две версии — `activeVersion` (последняя одобренная) и `pendingVersion` (на модерации) + флаг `hasDraft`; «самой свежей» и «оверлея» нет. Правка на карточке (`CharacterEditPage` → `updateCharacter`) пишет `versions[id]` и **не трогает членства игры** → новая версия не попадает на модерацию, в игре видна старая активная. В игре есть «Перевести» (миграция), но нет «Редактировать» лист.
- **Модель (зафиксирована 2026-08-20):**
  - `activeVersion` (A) — последняя одобренная, **заморожена** (снимок на момент approve); меняется ТОЛЬКО через модерацию.
  - `latestVersion` (L) = `versions[id]` (источник истины) — приёмник всех изменений вне сессии: правка карточки, лут вне сессии, миграция, `addCustomRule`; любое изменение → автоподача на модерацию.
  - Оверлей (O) — сессионный слой поверх A: во время сессии (`status === 'playing'`, членство `approved`) **все** изменения персонажа (боевые ресурсы/состояния и произвольные поля листа) пишутся в O; игра читает A + O (без модерации на лету). НПС — вне оверлея (правки сразу в `npc.version`, без модерации).
  - `pendingVersion` (P) — производная `snapshot(L + O)`; пересобирается при каждой мутации и при «Остановить сессию»; **никогда не конкурирует и никогда не пишется в L** (только просмотр для модератора).
  - Approve = применить O к L с учётом выборов ГМ → `A = L`, очистить P и O. Reject = сбросить O и P, L не трогаем.
  - Guard: модерация блокируется, пока у членства есть активный оверлей сессии (изменения сессии уходят на модерацию только при остановке сессии).
  - Конфликт L↔O: по умолчанию приоритет за O; при модерации конфликтные поля показываются с выбором значения ведущим.
  - Единый роутер записи `updateCharacter(id, data, context?)`: `context.gameId` + игра `playing` + членство `approved` → патч в O; иначе → L + автоподача. Через него идут карточка, кнопка «Редактировать» в игре, лут, `addCustomRule`, миграция (унифицирована через L).
  - `hasDraft` удаляется (фронт его не использует; черновики — в characterDraft-сторе персонажа).
- Корневая причина: `updateCharacter` не синхронизирует членства; модель членства не имеет `latest`/`overlay`; `pendingVersion` создаётся только явными флоу (подача, миграция, боевые изменения при остановке сессии).
- Реализация (2026-08-20, по плану с оговорками):
  - DTO `GameCharacterMembership`: +`latestVersion`, −`hasDraft`. **Оверлей реализован через `sheet`** (полная копия листа из in-game редактора) вместо изначального `fields` — это даёт правки любого поля листа и автоматически покрывает боевые ресурсы/состояния; боевой оверлей вложен как подвид (при отсутствии `sheet` — `mergeCombatOverlay(A, O)` для боевых полей).
  - Роутер `Mock/mockCharacterUpdate.ts`: `sessionTarget` (approved + игра `playing`, явный `gameId` или эвристика) → патч в O (`writeOverlaySheet`), иначе → L + `applyVersionChange` (`syncCharacterVersion` + автоподача через `syncCharacterVersionToMemberships`). Через роутер идут `updateCharacter`, `addCustomRule`, `updateCustomRule`, `distributeLoot`.
  - `moderateCharacter(..., choices = {})`: three-way reconcile (`reconcileVersion`) — дефолт приоритет O; guard — блок при активном оверлее сессии; approve = `A = L`, очистка P и O. `submitCombatChanges` — при активной сессии ранний выход (изменения живут в O, модерация только после остановки сессии); после остановки P = snapshot(L+O).
  - `reconcileVersion.ts`: `versionConflicts` (поля, изменённые и в L и в O относительно A) + применение выборов ГМ.
  - Кастом-правила: материализация итема вынесена в `updateCustomRuleInVersion`; замена `versions[id]` перепривязывает лист через `syncCharacterVersion` (фикс: новая версия не видна без перепривязки `details[id].version`).
  - UI: кнопка «Редактировать в игре» в `CharactersTab` (approved) → `/characters/:id/edit?gameId=`; `CharacterEditPage` в gameId-режиме стартует с эффективной версии (`overlay.sheet ?? activeVersion`), без source/листа — F17, не latest (2026-08-26); ключ черновика `character:id:game:gameId`, save несёт `gameId` (роутер → O), возврат в игру. `ModerateTab` — панель конфликтов «Карточка ↔ Игра» с выбором значения (default O). Уточнение UI (2026-08-21): метка «Первая подача — полный лист» показывается только при `activeVersion === null`; у approved-персонажа после остановки сессии (`activeVersion` есть, трёхсторонних конфликтов нет) — сгруппированный дифф active→pending (`MembershipDiffView`), слайдер для не-первой подачи показывает дельту, а не весь лист как «добавленный».
  - Тесты: `mockCharacterUpdate.test.ts`, `reconcileVersion.test.ts`, переписаны `mockGameCombatFlow`/`mockGameMemberships`/`mockGameLoot`/`combatCardModel`; полный батч 89 файлов / 914 тестов зелёный + `vue-tsc` + lint.

**Баг 2. Раздача добычи не пишет деньги/предметы в карточки.** — **РЕШЕНО 2026-08-20** (прямое применение ГМ; автоподача на модерацию — следом с Баг 1).

- Наблюдение: получатели в `LootDistributeDialog` — **игроки** (`GameMember`, userId) + НПС + «Вникуда» (селект «Игрок / НПС / Вникуда», `GameLootRecipientType = 'user' | 'npc' | 'nowhere'`). `distributeLoot` (мок) только фиксирует итог в `loot.distribution` — деньги/предметы **не пишутся** в лист получателя: ни в `npc.version`, ни в `versions[characterId]`. Участник в игре представлен персонажем (`GameCharacterMembership`), а не пользователем.
- Реализация: `GameLootRecipientType` `'user'` → `'character'`; `GameLootDistribution` — `characterId`/`npcId`/`nowhere`. `LootDistributeDialog`: селект «Персонаж / НПС / Вникуда», опции — персонажи игры (члены игры любого статуса; интерес — по владельцу `characterOwnerId`), «Поровну» — по персонажам заинтересованных владельцев. Мок `distributeLoot`: записывает в лист получателя — `versions[characterId]` (money/inventory) + `syncCharacterVersion`; НПС — ленивая инициализация минимального полного листа (`ensureNpcVersion`, Н1 → Н2) и запись. «Вникуда» — только фиксация без записи. Фикстура лут id 4 (Гаррик) приведена к `characterId: 3`.

---

## 9. Чат

### Плагинная модель Chat (2026-08-04)

`Messages/Chat` — **хост плагинов**: объявляет контракты и реестры, но не знает о доменных
типах и командах. Модули-доноры (Roleplay/Game, Roleplay/Character) регистрируют своё через
реестры в `init.ts`. Хост не импортирует компоненты доноров — только по `type` через реестры.

| Контракт                                                         | Реестр                                               | Что регистрируется                                                                     |
| ---------------------------------------------------------------- | ---------------------------------------------------- | -------------------------------------------------------------------------------------- |
| `IChatType` (`type/icon/color`)                                  | `registerChatType(s)/getChatTypes`                   | Тип чата: иконка + цвет в списке                                                       |
| `IChatTab` (`key/label/icon/types/sortOrder`)                    | `registerChatTab(s)/getChatTabs`                     | Вкладка в `MessengerTabs` (фильтр по `types`, сортировка `sortOrder`)                  |
| `ICommandHandler` (`command` + `parse(text)→ParsedCommand`)      | `registerCommandHandler/getCommandHandlers`          | Команды, вводимые с `/` в `ChatInput`                                                  |
| `IRenderer` (`type` + `component`)                               | `registerContentRenderer/getContentRenderer`         | Рендер вложения в списке сообщений (получает `attachment` + `index`)                   |
| `IRenderer` (`type` + `component`)                               | `registerInlineRenderer/getInlineRenderer`           | Рендер чипа внутри текста сообщения (получает `segment` = токен `InlineSegment`)       |
| `ITokenSource` (`type/label/icon/search`)                        | `registerTokenSource/getTokenSources`                | Источник для пикера «Вставить ссылку» в `ChatInput` (поиск + вставка `[[type:value]]`) |
| `IAttachmentProcessor` (`type`, `process(payload)`, `describe?`) | `registerAttachmentProcessor/getAttachmentProcessor` | Обработка вложения при отправке (напр. `roll`: spec → результат) + подпись чипа        |
| `IChatToolbarExtension`                                          | `registerToolbarExtension/getToolbarExtensions`      | Кнопки/панель тулбара `ChatInput` (props = `ChatToolbarContext`)                       |

**Владение:**

- `Messages/Chat`: базовые типы `private`/`group` + вкладка `personal` (`Constant/Chat/BASE_CHAT_*`),
  инициализируются императивно (BASE первым); чип `[[user:...]]` (inline-рендер `user` +
  источник токенов `user` — т.к. `Core/User` не может зависеть от `Chat`), рендер
  пользователя открывает `UserProfileSlider`.
- `Roleplay/Game/init.ts` (`registerGameModule`): типы `game`/`game_discussion`, вкладки
  «Игровые»/«Обсуждения игр», команда `/roll`, рендер `DiceRollResult`, attachment-processor
  `roll`, тулбар `RollFormExtension`/`MacroBarExtension`.
- `Roleplay/Rule/init.ts` (`registerRuleModule`): чип `[[rule:...]]` (inline-рендер `rule` +
  источник токенов `rule` — т.к. `Rule` может зависеть от `Chat`, а `Chat` — нет), открывает
  `RuleSlider` (имя, тип, описание). **2026-08-26:** публичка `init` реэкспортирует домен check/derived/damage/format/aggregate/race/`AbilityCard`; `slugify` остаётся внутренним Utils.
- `Roleplay/Character/init.ts` (`registerCharacterModule`): тип `character_discussion`, вкладка «Обсуждения персонажей» (`onlyIfMember`: в списке вкладки только чаты, где пользователь участник).

**Inline-чипы (F35):** текст сообщения парсится `Utils/inlineContent.ts` →
`InlineSegment[]` (`{kind:'text'}` | `{kind:'token', type, params[]}`). Синтаксис токена
`[[type:param1,param2]]` (напр. `[[user:ivan]]`, `[[rule:melee-fighting]]`). `ChatMessenger`
рендерит сегменты: текст как есть, токен — через `getInlineRenderer(type)` (передаёт
`segment`). Чип сам открывает свой слайдер по клику; при недоступном объекте — placeholder
«Объект скрыт». Пикер в `ChatInput` вставляет `[[type:value]]` на позицию курсора.
`IRenderer.describe(segment)` — человекочитаемая подпись токена; `inlineContentToText`
даёт плоский текст без чипов (используется в превью списка чатов `ChatList`).
`UserProfileSlider` — общий компонент в `Core/User/Component` (используется чипом
пользователя и `ChatMessenger`).

**Вложения сообщений:** `ChatMessage.attachments: ChatAttachment[]` (`{ type: string, payload: unknown }`).
`IChatApi.sendMessage(chatId, content, attachments)` — вместо доменного `rolls`. Специфика типа
живёт в payload (для `roll` — `DiceRollSpec`); при отправке `IAttachmentProcessor.process` превращает
его в результат (payload результата читает `IContentRenderer`). Это убирает зависимость
`Messages/Chat → Roleplay` (P2-1) и позволяет добавлять типы вложений без правки хоста.

### Загрузка и рендер сообщений (2026-08-04)

**Модель хранения (стор `chat.ts`):** per-chat `ChatState { messages, hasMore, total, initialized, loading, loadingOlder }`.

- `initialized` — грузится ли уже первая страница истории. `openChat`: если state создан синком
  (не `initialized`) — догружает первую страницу (`getMessages(PAGE_SIZE, 0)` + total) и **мержит**
  с уже пришедшими sync-сообщениями (дедуп по id, сортировка по id) — закрывает P2-3.
- `loadOlderMessages` — пагинация «вверх» (`getMessages(PAGE_SIZE, messages.length)`), препенд +
  кап `MAX_STORED`.
- `applySyncResponse` — мерж по id; для **неактивных** чатов кап `slice(-MAX_STORED)`
  (активный режется пагинацией) — закрывает P2-2.
- `MAX_STORED = 500` — страховка памяти поверх виртуализации (DOM уже не зависит от него).
- Ошибки: `chatsError`/`chatError`/`actionError` (P2-4), сброс в начале операции; UI повторов в
  `ChatMessenger`/`ChatInput`.

**Виртуализация списка:** `@tanstack/vue-virtual` (headless).

- `ChatMessageList.vue` + `Composables/useChatVirtualScroll.ts`: `useVirtualizer` с
  `anchorTo: 'end'` (стабильный якорь при препенде истории), `followOnAppend` (докрутка вниз
  только если юзер у конца), `scrollEndThreshold: 40`, `overscan: 8`, `estimateSize: 48`,
  динамические высоты через `measureElement` (ResizeObserver).
- Рендер: контейнер фикс. высоты `overflow:auto` → внутренний `position:relative`
  (высота `getTotalSize()`) → видимые сообщения `translateY(start)`.
- Подгрузка вверх — триггер при `scrollTop <= 120` (если `hasMoreOlder` и не `loadingOlder`).
- `ChatMessageRow.vue` — рендер одного сообщения (аватар, автор, время, сегменты текста,
  вложения); «Новые сообщения» (разделитель по `firstUnreadMessageId`) рисуется внутри item.
- `ChatMessenger` — композиция: `ChatList` + `ChatMessageList` + `ChatInput` (список вынесен,
  декомпозиция P3-3).
- mock: `SYNTHETIC_COUNT = 2000` (проверка виртуализации).

### Типы чатов

| Тип                    | Описание                                            | Владелец           |
| ---------------------- | --------------------------------------------------- | ------------------ |
| `private`              | Личный чат 1-на-1 между двумя пользователями        | Messages (базовый) |
| `group`                | Групповой чат (произвольный набор участников)       | Messages (базовый) |
| `game`                 | Чат игры (все участники игры автоматически в нём)   | Roleplay/Game      |
| `game_discussion`      | Чат в рамках игры (обсуждение игры и её персонажей) | Roleplay/Game      |
| `character_discussion` | Чат к персонажу (обсуждение конкретного персонажа)  | Roleplay/Character |

Новые типы добавляются без миграции схемы — регистрацией в своём модуле-владельце.

**Гость и список чатов:** гость видит в списке только публичные чаты (`group`, `game`, `game_discussion`, `character_discussion`), `private`-чаты ему скрыты. Писать в чаты гость не может (см. §11). На фронте (прототип) фильтрация идёт по флагу гостя (`auth.isGuest`); на бэкенде фильтрация по доступным чатам — через ролевую модель/права.

### Сообщения

- Хранятся в `chat_messages(id, chat_id, user_id, content, dice_result, created_at)`
- Подгрузка истории (scroll up)
- Поддержка вложений: `dice_result` — результат броска; на фронте обёрнут в `ChatAttachment`
  (`type: 'roll'`, payload — результат броска), рендерится встроенной карточкой `DiceRollResult`
  через `getContentRenderer('roll')`

### Команда броска кубиков

```
/roll Nd6[:efficiency] [adv:N] [dis:N] [size:N] [label:текст]
```

Алиасы: `e:` = эффективность, `prem:`/`adv:` = преимущество, `pom:`/`dis:` = помеха,
`size:`/`dim:` = размерность. Максимумы парсера: кубы ≤ 30, adv/dis ≤ 10, эффективность ≤ 20,
грань 2..100, размерность |N| ≤ 10. Невалидные параметры игнорируются (эффективность — дефолт 3).

Примеры:

- `/roll 5d6 e:3` — 5 кубов, эффективность 3
- `/roll 5d6 e:3 adv:1` — 5 кубов + 1 преимущество → всего 6, убрать 1 худший результат
- `/roll 5d6 e:3 dis:2` — 5 кубов + 2 помехи → всего 7, убрать 2 лучших результата
- `/roll 4d6 e:2 size:1 Проверка на силу` — с размерностью в итоге

**Подсчёт успехов для каждого куба d6:**

- `1` → 2 успеха
- `[2..efficiency]` → 1 успех
- `[efficiency+1..5]` → 0 успехов
- `6` → -1 успех

**Преимущества/помехи:**

- Преимущество (adv): добавить N кубов к броску, перед подсчётом убрать N худших результатов
- Помеха (dis): добавить N кубов к броску, перед подсчётом убрать N лучших результатов

**Размер успехов** = размер проверяемой характеристики.

**Пример вывода:**

```
Проверка на Силу. Сила 5↑. Эффективность 3.
Бросок: 1, 1, 2, 5, 6
 1 → 2 успеха
 1 → 2 успеха
 2 → 1 успех (2 ≤ 3)
 5 → провал
 6 → -1 успех
━━━━━━━━━━━━━━━
Итого: 4 успеха.
Сила большая → 4↑ успеха.
```

> **Ревизия 2026-08-02:** модификатор броска **не используется** (удалён из спецификации броска,
> формы и вывода). Результат выводится встроенной карточкой броска (`DiceRollResult`).
> **2026-08-22:** карточка разгружена: шапка «название · N РУ» (цвет по успеху/провалу проверки)
> и ряд кубов (успехи зелёные, снятые — приглушённые); пул, эффективность, сложность, источники
> преимуществ и применённые механики — во всплывашке по иконке info. Размерность не влияет на
> подсчёт кубов (суффикс в подробностях).

### Макросы

Хранятся в `user_macros(id, user_id, name, text_template, created_at)` + `user_macro_rolls`
(список бросков, см. схему §3).

Макрос — **преднастроенное сообщение**: текст и/или один или несколько бросков, всё опционально.
Валиден при непустом `name` и (`text_template` **ИЛИ** `rolls` непуст). При отправке используется
общий путь сообщений: `send(text, rolls)` — текст-only макрос шлёт текст без карточек,
бросок-only — карточки без текста.

- Пользователь создаёт/редактирует макрос в своём профиле:
  - **Текст сообщения** — опционально
  - Список бросков, добавляется кнопкой «Добавить бросок» в любом количестве. Каждый бросок:
    **Кубы/Грань** (собирается `NdM`), **Эффективность**, **Преимущества** (−10..+10,
    отрицательное = помеха), **Размерность**, **Подпись броска**, **«Переменные преимущества»**
  - Живой превью-чип (текст + чипы всех бросков)
- **Подпись броска** (`roll_label`) — метка карточки броска (шапка `DiceRollResult`), нужна для
  мульти-бросочных сообщений: «1 удар», «2 удар», «уклонение». Пустая → стандартное «Бросок N».
- **Переменные преимущества** (`variable_adv`, пер-бросковый флаг): при нажатии макроса в чате,
  если хотя бы один бросок отмечен, открывается диалог «Число преимуществ» (дефолт = `adv`
  первого отмеченного броска). Введённое значение применяется **только к отмеченным броскам**,
  остальные используют свой `adv`. Если отмеченных бросков нет — макрос отправляется сразу.
- Макрос отображается как кнопка в интерфейсе чата
- По нажатию: в чат отправляется `text_template` как текст сообщения + вложенные карточки бросков (`DiceRollResult`)
- Пример: `name: "Полная атака", text: "Атакую дважды"`, броски `"5d6" (adv: 1, label: "1 удар")`
  и `"5d6" (label: "2 удар")` — две карточки, у каждой своя подпись и преимущество

### Real-time синхронизация чатов (SSE)

> **Состояние транспорта (2026-08-01):** SSE — **целевой протокол реального бэка**. Пока бэка нет
> (mock-режим), фронт использует **polling** (`ChatSyncService.mode: 'poll'`, интервал 5 с) —
> временная мера. Режим выбирается конфигом сервиса (`'poll' | 'sse'`); при появлении
> SSE-бэкенда переключается на `'sse'` без изменения остального кода.

**Задача:** Получать новые сообщения и обновления чатов без polling каждого чата по отдельности.

**Решение:** Одно SSE-соединение на весь sync.

**Протокол:**

- Клиент открывает SSE на `/api/chat/sync?since=ISO_TIMESTAMP`
- `since` — время последнего известного клиенту события (изначально пустое, при первом открытии слайдера)
- Сервер держит соединение открытым. При появлении новых данных отправляет event с типом `sync`:

```json
{
  "now": "2026-07-27T15:00:10Z",
  "chats": [
    { "id": 1, "unreadCount": 3, "lastReadMessageId": 45, "lastMessage": "...", "lastMessageAt": "..." }
  ],
  "newChats": [{ "id": 26, ... }],
  "messages": {
    "1": [{ "id": 101, "content": "...", "createdAt": "..." }],
    "4": [{ "id": 203, "content": "...", "createdAt": "..." }]
  }
}
```

- `now` — новый `since` для следующего подключения (гарантирует at-least-once)
- Если событий нет 60 секунд — сервер отправляет `{"now": "..."}` (heartbeat, соединение остаётся открытым)
- При разрыве EventSource автоматически переподключается с последним полученным `since`

**Поведение клиента:**

- Слайдер закрыт → соединение закрыто
- Слайдер открыт → SSE активно, новые сообщения сразу попадают в store
- При смене чата (activeChatId) — если сообщения для этого чата уже пришли через sync, они сразу в DOM

**Индексы в БД:**

- `chat_messages(chat_id, created_at)` — покрывает sync-запрос
- `chats.updated_at` — для отслеживания изменений чата (на фронте `lastMessageAt`)

Sync-запрос:

```sql
SELECT * FROM chat_messages
WHERE chat_id IN (SELECT chat_id FROM chat_members WHERE user_id = :userId)
  AND updated_at > :since
ORDER BY updated_at ASC
LIMIT 200
```

**Отличие от пагинации:** в пагинации (первые N сообщений) сортировка по `created_at DESC`, в sync — по `updated_at ASC`, чтобы подхватить отредактированные сообщения и изменения видимости.

Для поддержки обоих запросов нужны индексы:

- `(chat_id, created_at DESC)` — пагинация
- `(chat_id, updated_at)` — sync (ASC за счёт сортировки индекса, который можно читать в любом направлении)

Тип `chat_messages` должен содержать поле `updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.

### Модерация добычи

- **Страница добычи игры** — список `available`/`acquired` предметов, фильтр по статусу
- **Кнопка «Проявить интерес»** — на карточке предмета в списке добычи
- **Раздача** — попап выбора получателя (список игроков), подтверждение → предмет уходит в инвентарь

---

## 10. Уведомления

- **Топбар:** иконка 🔔 со счётчиком непрочитанных уведомлений. При клике — выезжает панель (slider) справа с последними уведомлениями, ссылка на полный список.
- **По клику:** открывается сайдпанель (Bitrix24-стиль) — тот же роут `/notifications` в overlay-режиме с крестиком.
- **Полная страница:** `/notifications` — группировка, сортировка, фильтрация, просмотр чужих уведомлений (для админов).
- **Администрирование:**
  - `/admin/notification-templates` — список шаблонов (`notification_template.view`)
  - `/admin/notification-templates/new` — создание (`notification_template.create`)
  - `/admin/notification-templates/:id/edit` — редактирование (`notification_template.edit`)
  - Удаление шаблона — **soft-delete** (флаг `active`): кнопка «Удалить» на странице редактирования (`/admin/notification-templates/:id/edit`) с диалогом подтверждения; отдельной страницы нет (`notification_template.delete`)
- **Типы уведомлений:** приглашение в игру (кнопки Принять/Отклонить), модерация персонажа, миграция версии завершена.
- **Дедупликация:** при повторной отправке уведомления того же типа тому же пользователю (с теми же ключевыми параметрами) — обновляется существующее, новая запись не создаётся.

---

## 11. Разделы интерфейса

### Аутентификация

Страницы входа/регистрации — отдельные, вне основного лейаута (без сайдбара, минималистичный дизайн).

| Путь               | Страница                                                                                                                                                                                                                                                                                                                                    | Доступ           |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------- |
| `/login`           | **Вход.** Поля: логин/email, пароль. Кнопка «Запомнить меня» (продлённая сессия). Кнопка «Войти как гость» (без пароля, ограниченный доступ). Ссылка «Забыли пароль?».                                                                                                                                                                      | Неавторизованные |
| `/register`        | **Регистрация.** Поля: логин (уникальный), email (опционально, но рекомендуется для сброса пароля), пароль, подтверждение пароля. Email должен быть уникальным, если заполнен. После регистрации — сразу вход.                                                                                                                              | Неавторизованные |
| `/forgot-password` | **Запрос сброса пароля.** Поля: логин или email. Система находит пользователя по логину (приоритет) или email. Если найден — отправляет письмо на привязанный email (если он есть); если email не указан — выводит сообщение «Для этого аккаунта не указан email, обратитесь к администратору». При успехе — автопереход на reset-password. | Неавторизованные |
| `/reset-password`  | **Сброс пароля.** Если логин/token не переданы в query — отдельные поля их ввода. Иначе — два поля: новый пароль + подтверждение. Token одноразовый, expires_at.                                                                                                                                                                            | Неавторизованные |
| `/logout`          | **Выход.** POST-запрос, аннулирует сессию, редирект на `/login`.                                                                                                                                                                                                                                                                            | Авторизованные   |

### Сессии

- После входа создаётся запись в `sessions`, токен сохраняется в httpOnly cookie.
- Токен — случайная строка, хранится хешированной в БД.
- Время жизни: по умолчанию 24ч, «Запомнить меня» — 30 дней.
- При каждом запросе middleware проверяет токен из cookie, подгружает пользователя.
- При выходе сессия удаляется из БД.

### Гостевой вход

Гостевой вход — вход без регистрации, с ограниченными правами:

- Гость видит публичные страницы: список игр (только публичные), список пространств (только публичные), главную страницу.
- Гость НЕ может: создавать/редактировать персонажи, игры, пространства, правила; писать в чаты. Читать может только публичные чаты (см. §9 — private скрыты).
- Гостю не нужен пароль — достаточно кнопки «Войти как гость» на странице логина.
- Сессия гостя временная (до закрытия браузера), без опции «Запомнить меня».
- Гостю показывается кнопка «Войти» / «Регистрация» в топбаре вместо аватара.
- Если гость регистрируется — его временные данные (просмотры) не переносятся.

### Раздел «Пользователи» (`/users`)

| Путь                    | Страница                                                                                                                                                        | Доступ            |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------- |
| `/users`                | **Список пользователей.** Аватар, имя, фамилия, логин. Скрытые поля — только с `user.view_sensitive`. Кнопка «+ Создать» — c `user.create`.                     | `user.view`       |
| `/users/new`            | **Создание пользователя.**                                                                                                                                      | `user.create`     |
| `/users/:id`            | **Профиль.** Аватар, имя, фамилия, логин, персонажи, игры. Права/группы — если есть доступ. Кнопка «Редактировать» — c `user.edit`.                             | `user.view`       |
| `/users/:id/edit`       | **Редактирование пользователя.** Аватар (drag-n-drop / выбор файла), логин, пароль, email, имя, фамилия, псевдоним. Группы — только с `user.edit`.              | `user.edit`       |
| `/users/:id/deactivate` | **Деактивация пользователя.** Единый паттерн: **не отдельная страница** — кнопка «Отключить» на `/users/:id` с диалогом: дата окончания (опционально), причина. | `user.deactivate` |

> **Единый паттерн деактивации:** деактивация — кнопка с попап-подтверждением на странице сущности, отдельные страницы не создаются. Для игр (`/games/:id/deactivate`), пространств (`/space/:code/deactivate`), персонажей (`/characters/:id/deactivate`) — без даты и причины. Для пользователей и групп — см. строки выше.

### Раздел «Группы» (`/admin/groups`)

| Путь                           | Страница                                                                                          | Доступ                  |
| ------------------------------ | ------------------------------------------------------------------------------------------------- | ----------------------- |
| `/admin/groups`                | **Список групп пользователей.** Название, число участников, статус.                               | `user_group.view`       |
| `/admin/groups/new`            | **Создание группы пользователей.** Название, описание, назначение прав (группированные чекбоксы). | `user_group.create`     |
| `/admin/groups/:id`            | **Карточка группы пользователей.** Участники, права.                                              | `user_group.view`       |
| `/admin/groups/:id/edit`       | **Редактирование группы пользователей.** Изменение названия, участников, прав.                    | `user_group.edit`       |
| `/admin/groups/:id/deactivate` | **Деактивация группы пользователей.**                                                             | `user_group.deactivate` |

### Раздел «Признаки» (`/admin/keywords`)

| Путь                         | Страница                                                                                                                                                               | Доступ           |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------- |
| `/admin/keywords`            | **Список признаков.** `code`, `name`, `description`, флаг активности.                                                                                                  | `keyword.view`   |
| `/admin/keywords/new`        | **Создание признака.** `code` (уникальный, латиница/цифры/подчёркивание), `name` (отображаемое имя), `description` (опционально).                                      | `keyword.create` |
| `/admin/keywords/:id/edit`   | **Редактирование признака.**                                                                                                                                           | `keyword.edit`   |
| `/admin/keywords/:id/delete` | **Удаление признака (soft-delete).** В UI — кнопка «Удалить» на странице редактирования (`/admin/keywords/:id/edit`) с диалогом подтверждения; отдельной страницы нет. | `keyword.delete` |

> **Добавление признаков к правилу:** во время редактирования правила — выпадающий список / combobox с поиском по `name`. Справа кнопка «+» — открывает попап создания нового признака (`code` генерируется из `name` или вводится вручную). После сохранения попапа признак сразу доступен для выбора без перезагрузки.

### Общий лейаут

> **Решение (2026-08-02):** топ-меню в топбаре и футер `v-footer` **не реализуются** (отказались по решению; на фронте их нет). Актуальный топбар: `≡` + хлебные крошки + 🔔. Навигация — сайдбар; вход для гостя — кнопка в блоке пользователя сайдбара. Пункты про топ-меню, стрелку-`↑` с хлебными крошками и футер ниже — устаревшие формулировки, оставлены для истории.

#### Топбар (`v-app-bar`)

- Слева: кнопка `≡` — скрыть/показать сайдбар
- Центр: **топ-меню** — основные разделы (Персонажи, Игры, Пространства, Админка). Текущий пункт подсвечен.
- Стрелка `↑` рядом с текущим пунктом — при наведении показывает **хлебные крошки** в обратном порядке (текущая страница → родитель → корень)
- Справа: 🔔 иконка уведомлений со счётчиком (badge). При клике — выезжает панель (slider) справа с последними уведомлениями. Полная история — на `/notifications`.

#### Сайдбар (`v-navigation-drawer`, collapsible)

- **Блок пользователя** (вверху): аватар, имя, @логин
- **Пункты меню** (иконка + текст): Персонажи, Игры, Пользователи, Пространства
- Внизу (при наличии прав): **Администрирование** (`/admin/groups`)

#### Футер (`v-footer`)

- Копирайт, ссылки

#### Контекст пространства-времени

- В топбаре не отображается
- Показывается внутри страницы, где это уместно (редактор правил, создание персонажа для игры и т.д.) — селектор пространства и/или даты версии

#### Bitrix-style фильтр

Используется на всех страницах со списками (игры, правила, расы, черты):

- **`filter-bar`** — кликабельное поле с бордером. Содержит:
  - Чипсы активных фильтров слева
  - Текстовый ввод справа от чипсов (placeholder «Фильтр + поиск»)
  - Иконка 🔍 справа
- **`filter-popup`** — выпадающий попап, открывается по клику/фокусу на filter-bar:
  - Поля фильтрации
  - Тумблеры (`filter-toggle`) включения/отключения каждого поля
  - Кнопки «Применить» / «Сбросить»
- ✕ на чипсе — удаляет фильтр без открытия попапа

#### Horizontal editor panel

Панель под топбаром на странице редактора персонажа:

- Flex-строка с блоками-этапами: каждый блок — кликабельный, переключает содержимое ниже
- **Блоки** (слева направо): Раса (показывает стоимость расы), Врождённые черты (ОС spent/total), Личность (ОЛ spent/total), Развитие (ОР spent/total), Инвентарь
- Активный этап подсвечен синим фоном
- Справа — **чипсы основных характеристик** (сетка 2×3, полные названия):
  - Показываются только основные характеристики (базовые для расы)
  - Только ненулевые
  - Если раса не выбрана (характеристик нет) — блок скрыт целиком

#### Expansion panel

Раскрывающийся список для рас, черт, способностей и любых сущностей с краткой и полной информацией:

- **Header** (кликабельный, открывает/закрывает тело):
  - Стрелка `▶` (поворачивается при открытии)
  - Краткая информация (название, стоимость, признаки)
  - **Кнопка ✏️** — в крайней правой части шапа. Отображается только при наличии права `rule.edit`. Открывает редактирование правила в попапе или сайдпанели.
  - **Счётчик +/-** (только для черт/способностей с уровнями)
- **Body** (скрыт по умолчанию):
  - Полное описание правила
  - Требования и условия
  - Признаки
  - Дополнительная информация (характеристики, стоимость по уровням и т.д.)
- Состояния:
  - **`open`** — тело видимо, стрелка повёрнута вниз
  - **`selected`** — подсветка синей границей и фоном (выбранный элемент). Выбранные элементы отображаются в начале списка.

---

## 12. Волны реализации

init-релиз — полнофункциональная система управления RPG-персонажами и правилами.

**Ядро:** SmartTable как единая точка доступа к данным поверх Doctrine ORM. Миграции схемы через SmartTable API.

**Фронт:** Shell + модули (Vite + TypeScript + Vuetify). Единая точка входа `runAction`.

**Пользователи:** открытая регистрация, группы, права (объект.действие), деактивация, супер-админ.

**Правила:** пространства с наследованием, версии A.B.C (свойство правила) + revision пространства (x), гибридное хранение (базовые поля + spec_json).

**Персонажи:** свободное создание/через игру, 4 этапа (раса+ОС→личность→развитие→инвентарь), сессионная модель в игре, модерация.

**Игры:** 6 статусов, роли (владелец/ведущий/участник), модерация, добыча.

**Коммуникации:** чаты (private/group/game), команда /roll (Rule Engine), уведомления.

График волн (порядок реализации по техническим зависимостям):

```
Волна 1 (Фундамент):  Kernel → SmartTable → Auth + Shell
Волна 2 (CRUD):       User + Roleplay/Rule(Tag) + Messages/Notifications(бэк) (параллельно)
Волна 3 (Контент):    Roleplay/Space → Roleplay/Rule(Nation, Language, WritingSystem)
Волна 4 (Персонажи):  Roleplay/Character → модерация (ссылка chat_message_id, чат позже)
Волна 5 (Игры + Чат): Roleplay/Game → Messages/Chat (доработка модерации)
```

**Статус фронта (прототип):** Chat (волна 5) реализован; Character (§7) и Game (§8) — стаб-страницы до своих волн (F10).

**Волна 1** — самодостаточна, может быть запущена в разработку параллельно с проектированием волн 2–5. На старте загружаются только модули Core/*; остальные — лениво, через includeModule().

### Волна 1: Фундамент

#### Core/Kernel

**Назначение:** точка входа в систему, загрузчик модулей, ServiceLocator, диспатчер `runAction`.

**Внешние зависимости:** отсутствуют.

**Файловая структура:**

```
engine/
  config.php                    — глобальная конфигурация (БД, logger_class, site_name, …)
  index.php                     — фронт-контроллер (/api/dispatch.php); загружает config.php
  modules/
    Core/Kernel/
      module.config.php           — сервисы ядра (ServiceLocator, ModuleManager, EventManager, dispatch, Logger)
      Interface/
        Service/
          ModuleManagerInterface.php
          EventManagerInterface.php
          LoggerInterface.php       — error, warning, info, debug
        Http/
          RequestInterface.php       — get, post, server, cookie, header, body, isAjax
          ResponseInterface.php      — setStatus, setHeader, setBody, send
        Error/
          ErrorHandlerInterface.php — интерфейс обработчика ошибок
        Repository/
          LoggerRepositoryInterface.php — SmartTable для записей лога
      Service/
        ModuleManager.php         — implements ModuleManagerInterface: includeModule/requireModule, автозагрузка Core/*, ленивая загрузка остальных
        EventManager.php          — implements EventManagerInterface: шина событий (fire/on/off)
        Logger.php                — implements LoggerInterface; использует LoggerRepository (SmartTable), fallback на файл при старте
      Http/
        Request.php               — implements RequestInterface: обёртка над $_GET, $_POST, $_SERVER, $_COOKIE, php://input
        Response.php              — implements ResponseInterface: HTTP-ответ (статус, заголовки, тело)
      SmartTable/
        LogTable.php                — extends SmartTableDefinition (таблица log)
      Repository/
        LoggerRepository.php      — implements LoggerRepositoryInterface (таблица log)
      Controller/
        DispatchController.php    — runAction: Request → авторизация → CSRF → роутинг → вызов контроллера
      Error/
        EngineError.php            — extends \Exception, общий предок всех ошибок проекта
        KernelError.php            — extends EngineError, базовый класс ошибок ядра
        AccessDeniedError.php      — extends KernelError
        NotFoundError.php          — extends KernelError
        ValidationError.php        — extends KernelError
        DatabaseErrorHandler.php   — implements ErrorHandlerInterface (пишет в Logger)
      Dto/
        RunActionResult.php
      ServiceLocator.php          — DI-контейнер: set/get/has, регистрация по строковому коду + alias по ::class
      Kernel.php                  — bootstrap: config → ServiceLocator → ModuleManager → EventManager → Logger → DispatchController
```

`config.php` — первый файл, загружаемый `index.php`:

```php
return [
    'db' => [
        'host'     => 'localhost',
        'dbname'   => 'rpg',
        'user'     => 'root',
        'password' => '',
    ],
    'logger' => [
        'code' => 'Core.Kernel.Service.Logger',  // сервис-код для ServiceLocator
        'file' => '/var/log/engine.log',          // fallback при недоступности БД
    ],
    'site' => [
        'name' => 'PowerScale',
        'url'  => 'https://powerscale.example.com',
    ],
];
```

Логирование — преимущественно через БД (таблица `log`, SmartTable). Файловый fallback — только при старте или недоступности БД.

**Шаги реализации:**

1. ServiceLocator — регистрация/получение сервисов по строковому коду (например `'Core.Kernel.Service.ModuleManager'`), псевдонимы по ::class для IDE-совместимости
2. config.php — глобальная конфигурация (БД, logger, site), загрузка через `include` в index.php
3. index.php — `include 'config.php'`, вызов `Kernel::bootstrap()`, передача управления `DispatchController`
4. Interface/Service/ — ModuleManagerInterface, EventManagerInterface, LoggerInterface
5. ModuleManager — implements ModuleManagerInterface:
   - `includeModule(string $group, string $module): bool`
   - `requireModule(...): void`
   - Автозагрузка только Core/* при старте
   - `getLoadedModules(): array`

   ```php
   return [
       'services' => [
           'Core.User.Service.User' => [
               'class' => UserService::class,
               'alias' => [UserServiceInterface::class],
           ],
           'Core.User.Repository.User' => [
               'class'   => UserRepository::class,
               'alias'   => [UserRepositoryInterface::class],
               'factory' => fn($serviceLocator) => new UserRepository(
                   $serviceLocator->get('Core.SmartTable.Service')->open('users'),
               ),
           ],
       ],
       'routes' => [
           'user.list' => [UserController::class, 'listAction'],
           'user.get'  => [UserController::class, 'getAction'],
       ],
       'events' => [
           'character.created' => [['Messages.Notifications.Listener.CharacterCreated', 'onCharacterCreated']],
       ],
   ];
   ```

6. Kernel::bootstrap вручную регистрирует core-сервисы в ServiceLocator:
   ```
   'Core.Kernel.Service.ModuleManager' => ModuleManager::class
   'Core.Kernel.Service.EventManager'  => EventManager::class
   'Core.Kernel.Service.Logger'        => Logger::class
   'Core.Kernel.Http.Request'          => Request::class
   'Core.Kernel.Http.Response'         => Response::class
   'Core.Kernel.Error.Handler'         => DatabaseErrorHandler::class
   ```
7. Interface/Http/ — RequestInterface, ResponseInterface
8. Http/Request — implements RequestInterface
9. Http/Response — implements ResponseInterface
10. SmartTable/LogTable — extends SmartTableDefinition
11. Interface/Repository/ — LoggerRepositoryInterface
12. Repository/LoggerRepository — implements LoggerRepositoryInterface
13. Service/Logger — implements LoggerInterface; пишет в LoggerRepository, fallback на файл из config.php
14. EventManager — fire/on/off, синхронные слушатели, очередь для асинхронных
15. Kernel — bootstrap: config → ServiceLocator → ModuleManager → EventManager → Logger; автозагрузка Core/*
16. RunActionResult (Dto/) + классы ошибок (Error/)
17. DispatchController — POST /api/dispatch.php → Request → проверка CSRF/авторизации → вызов контроллера
18. ErrorHandlerInterface + DatabaseErrorHandler — перехват исключений, запись в Logger

#### Core/SmartTable

**Назначение:** ORM-обёртка с bitrix-подобным API. Единственная точка доступа к данным.

**Внешние зависимости:** Doctrine ORM.

**Файловая структура:**

```
Core/SmartTable/
  module.config.php               — сервисы SmartTable
  Abstract/
    Table/
      SmartTableDefinition.php    — абстрактный класс: наследник определяет getMap() → массив BaseField
    Field/
      BaseField.php               — абстрактный класс: name, label, required, multiple, default
  Interface/
    Service/
      SmartTableServiceInterface.php
      SmartTableServiceContainerInterface.php
      MigrationServiceInterface.php
    Field/
      FieldInterface.php          — cast($value), extract($value), hydrate($dbValue)
    Hydrator/
      HydratorInterface.php       — cast/extract/hydrate для составных объектов
  Service/
    SmartTableServiceContainer.php  — implements SmartTableServiceContainerInterface
    SmartTableService.php           — open($name) / create($name), implements SmartTableServiceInterface
    MigrationService.php            — diff схемы SmartTable vs БД → генерация миграции, implements MigrationServiceInterface
  Table/
    SmartTable.php                  — основной класс: add, update, delete, getList, transaction, beginTransaction, commit, rollback
  Field/
    IntegerField.php
    StringField.php
    TextField.php
    DateTimeField.php
    JsonField.php                   — + внешний hydrator
    ReferenceField.php              — ссылка на другую таблицу
    UserTypeField.php               — кастомное поле
  Hydrator/
    DimensionalNumberHydrator.php       — пример составного hydrator'а
    DateTimeHydrator.php
  Migration/
    SmartTableMigration.php         — базовый класс миграции (up/down через SmartTable API)
```

Любое поле может быть множественным (флаг `multiple` в BaseField). ReferenceField — частный случай для связей.

Модульные hydrator'ы (DamageFormulaHydrator и др.) живут в соответствующих модулях и регистрируются через `module.config.php`:

```php
'smarttable_fields' => [
    'damage_formula' => DamageFormulaHydrator::class,
]
```

Сервисы SmartTable регистрируются как `'Core.SmartTable.Service'`, `'Core.SmartTable.Service.Migration'` и т.д. Репозиторий получает конкретный SmartTable через `open($tableName)`, не контейнер. Миграции используют `create($tableName, $definition)` для создания таблиц, кодовая миграция — `addTable()` через MigrationService.

**Шаги реализации:**

1. Abstract/Field/BaseField (абстрактный) + базовые типы (Integer, String, Text, DateTime)
2. Interface/ (FieldInterface, HydratorInterface, Service-интерфейсы)
3. JsonField + HydratorInterface — встроенные hydrator'ы. Модульные hydrator'ы регистрируются через `'smarttable_fields'` в `module.config.php`
4. ReferenceField — связи таблиц
5. SmartTable CRUD: add, update, delete, getList (с filter/sort/pagination/select)
6. QueryBuilder (fluent `query()` для сложных случаев)
7. Транзакции: локальные (`transaction()`) и глобальные (`SmartTableService::beginTransaction()`)
8. Индексы: addIndex, addUniqueIndex, addFulltextIndex
9. Создание таблиц: `SmartTableService::create($name, $definition)` → кодовая миграция `addTable()`
10. Миграции: MigrationService (diff + генерация), SmartTableMigration
11. Кэш: тегированный кэш в getList (cache ttl, tags)

#### Core/Auth

**Назначение:** аутентификация, сессии, CSRF, middleware.

**Внешние зависимости:** Core/SmartTable (таблицы sessions, users).

**Включает минимальный UserRepository для регистрации/логина.** Полный CRUD пользователей + группы + права — в волне 2 (Core/User).

**Файловая структура:**

```
Core/Auth/
  module.config.php               — сервисы аутентификации
  Interface/
    Service/
      SessionManagerInterface.php
      CsrfProtectionInterface.php
    Repository/
      UserRepositoryInterface.php   — findUserByEmail, createUser
  SmartTable/
    SessionTable.php              — extends SmartTableDefinition (sessions)
    UserTable.php                 — extends SmartTableDefinition (users, минимальный набор)
  Service/
    SessionManager.php          — implements SessionManagerInterface: httpOnly cookie, хеш токена в БД
    CsrfProtection.php          — implements CsrfProtectionInterface: генерация/проверка CSRF-токена
  Middleware/
    AuthMiddleware.php          — проверка авторизации
    GuestMiddleware.php         — только для неавторизованных
    GuestAccessMiddleware.php   — гостевой доступ (ограниченный набор страниц)
  Controller/
    LoginController.php         — runAction('auth.login')
    LogoutController.php        — runAction('auth.logout')
    RegisterController.php      — runAction('auth.register')
    ForgotPasswordController.php
    ResetPasswordController.php
    GuestLoginController.php    — runAction('auth.guest')
  Repository/
    UserRepository.php          — implements UserRepositoryInterface (минимальный, для регистрации/логина)
  Dto/
    Session.php                 — DTO сессии
```

**Шаги реализации:**

1. SmartTable/ — SessionTable, UserTable extends SmartTableDefinition
2. Interface/ — SessionManagerInterface, CsrfProtectionInterface, UserRepositoryInterface
3. Dto/Session — DTO сессии
4. Service/SessionManager — httpOnly cookie, хеш токена в БД (sessions table)
5. Repository/UserRepository — implements UserRepositoryInterface: findUserByEmail, createUser
6. Создание таблиц через MigrationService
7. Контроллеры: login, logout, register, forgot-password, reset-password
8. Service/CsrfProtection — токен в cookie + проверка в DispatchController
9. AuthMiddleware — проверка для всех залогиненных действий
10. GuestMiddleware — редирект на /games если уже залогинен
11. GuestAccessMiddleware — гостевой доступ (ограниченный набор read-only страниц)
12. GuestLoginController — создание временной гостевой сессии

#### Shell (фронт)

**Назначение:** оболочка SPA (топбар, сайдбар, роутер, runAction-клиент).

**Внешние зависимости:** Auth (runAction), Vuetify.

**Файловая структура** (актуальна на 2026-08-02; полная анатомия модулей — в разделе «Фронтенд (Shell + модули)»):

```
frontend/
  src/
    modules/
      Core/
        Engine/
          init.ts               — публичная точка (HttpClient, Engine, ActionResponse, serviceLocator, register/getCsrfApi)
          Service/
            ServiceLocator.ts   — DI-контейнер (set/get/reset) → serviceLocator
            HttpClient.ts       — fetch-клиент, интерцепторы (401, CSRF)
            CsrfApi.ts          — чтение csrf-token из document.cookie
            Engine.ts           — runAction-клиент
          Interface/ICSRFApi.ts
          Dto/ActionResponse.ts
          Mock/mockCsrf.ts
          Value/DateTime.ts, DimensionalNumber.ts
        Auth/
          Interface/IAuthApi.ts, Dto/PasswordPolicy.ts, Service/AuthApi.ts + PasswordValidatorService.ts,
          Constant/defaultPasswordPolicy.ts, Mock/, Store/auth.ts, init.ts
        User/
          Interface/IUserApi.ts + IGroupApi.ts, Dto/User|Group|ProfileSection,
          Service/UserApi|GroupApi|AccessService.ts, Constant/permissions.ts, Mock/, Store/, init.ts
        UI/
          Component/Grid/, FilterBar/, Input/ (PasswordField, DimensionalNumber…)
          Utils/debounce.ts
      Messages/
        Chat/
          Interface/IChatApi|ICommandHandler|IRenderer|IChatToolbarExtension|IAttachmentProcessor|ITokenSource|IChatType|IChatTab,
          Dto/, Enum/ChatVisibility|ChatPermission, Constant/ (avatarColors, Chat/),
          Service/ChatApi|ChatSyncService, Mock/, Utils/inlineContent.ts, Store/chat.ts,
          init.ts (реестры плагинов Chat: командные хендлеры, рендеры вложений,
          inline-чипы, источники токенов, attachment-процессоры, типы/вкладки, тулбар)
        Notifications/
          Interface/INotificationApi|INotificationTemplateApi, Dto/, Enum/NotifFilter,
          Service/, Mock/, Store/, init.ts
      Roleplay/
        Rule/  (Interface/IRuleApi, Dto/, Enum/, Service/ (+Spec/), Constant/, Mock/, Component/ (+Chat/RuleChip|RuleSlider),
                Store/, Utils/, init.ts (registerRuleModule — права + inline-чип/источник 'rule'), routes.ts)
        Game/  (RPG-кластер: Interface/IMacroApi, Dto/ (DiceRollSpec|DiceRollResult|UserMacro|MacroRollSpec),
                Service/RollService|MacroApi, Mock/, Store/macros.ts, Component/ (dice-UI, MacrosSection, тулбар),
                Constant/Chat (GAME_CHAT_TYPES|GAME_CHAT_TABS),
                init.ts (registerMacroApi + registerGameModule — плагины Chat), routes.ts)
        Space/ (Interface/ISpaceApi, Dto/, Service/, Mock/, Store/, init.ts, routes.ts)
        Home/, Character/ (Constant/Chat — CHARACTER_CHAT_TYPES|CHARACTER_CHAT_TABS)
    router/ index.ts, access.ts
    shell/ Shell.vue, SideBar.vue
    App.vue, main.ts
    plugins/vuetify.ts
```

**Архитектура фронта:**

- DI: `serviceLocator` (генерализованный set/get/reset)
- Per-модуль `init.ts`: `registerXApi(impl)` + `getXApi(): IXApi`
- `main.ts`: регистрация всех API (mock или real), вызов `getCsrfApi().initToken()`
- **CSRF:** `HttpClient` принимает коллбэк `getCsrfToken`; добавляет заголовок `X-CSRF-Token` на POST-запросы
- **Core-правило:** Core-модули не импортируют из не-Core; не-Core могут импортировать из Core

**Стилевые правила (фронт):**

- Стилизация через Vuetify-классы и CSS-переменные темы (`rgb(var(--v-theme-*))`). Хардкод цветов не использовать.
- Inline-стили (`:style`) допустимы **только** для динамических значений (URL импортированного ассета, вычисляемые значения). Всё остальное — через CSS-классы.
- `!important` запрещён. Для переопределения Vuetify-стилей использовать селекторы с более высокой специфичностью (например, `.my-class.v-btn--variant-text` вместо `!important`).

**Шаги реализации (волна 1):**

1. `modules/Core/Engine/` — HttpClient (fetch + CSRF), Engine (runAction), ActionResponse (Dto), serviceLocator
2. `router/index.ts` — Router, RouteGuard (guard использует auth store)
3. `modules/Core/Auth/` — Interface/IAuthApi + Service/AuthApi + Mock/mockAuth + Store/auth + init.ts
4. CSRF — внутри `Core/Engine/`: Interface/ICSRFApi + Service/CsrfApi + Mock/mockCsrf + register/getCsrfApi в init.ts
5. `modules/Core/User/` — Interface/IUserApi + Service/UserApi + Mock/mockUserApi + Store/users + init.ts
6. `modules/Messages/Chat/` — Interface/IChatApi + Service/ChatApi + Mock/mockChat + Store/chat + init.ts
7. `modules/Messages/Notifications/` — Interface/INotificationApi + Service/NotificationApi + Store/notifications + init.ts
8. `shell/` — Shell.vue, SideBar
9. `modules/Core/Auth/Page/` — LoginPage, RegisterPage, ResetPasswordPage
10. Vuetify — плагин, тема (CSS-переменные)
11. `main.ts` — регистрация всех API (mock/real), вызов `getCsrfApi().initToken()`

### Волна 2: Простые CRUD

Все три раздела независимы — можно делать параллельно.

#### Core/User

**Назначение:** CRUD пользователей, групп пользователей, прав, деактивация.

**Внешние зависимости:** Core/Auth, Core/SmartTable.

**Файловая структура:**

```
Core/User/
  module.config.php               — сервисы пользователей
  Interface/
    Service/
      UserServiceInterface.php
      UserGroupServiceInterface.php
    Repository/
      UserRepositoryInterface.php
      UserGroupRepositoryInterface.php
      UserGroupMemberRepositoryInterface.php
      PermissionRepositoryInterface.php
  SmartTable/
    UserTable.php               — extends SmartTableDefinition (users)
    UserGroupTable.php          — extends SmartTableDefinition (user_groups)
    UserGroupMemberTable.php
    PermissionTable.php
  Service/
    UserService.php              — implements UserServiceInterface
    UserGroupService.php         — implements UserGroupServiceInterface
  Repository/
    UserRepository.php           — implements UserRepositoryInterface (SmartTable users)
    UserGroupRepository.php      — implements UserGroupRepositoryInterface
    UserGroupMemberRepository.php
    PermissionRepository.php     — implements PermissionRepositoryInterface
  Controller/
    UserController.php          — runAction('user.*')
    UserGroupController.php     — runAction('userGroup.*')
  Entity/
    User.php                    — чистая модель
    UserGroup.php
    Permission.php
  Error/
    UserNotFoundError.php       — extends NotFoundError
    UserValidationError.php     — extends ValidationError
    UserAccessDeniedError.php   — extends AccessDeniedError
```

**Шаги реализации:**

1. SmartTable/ — UserTable, UserGroupTable, UserGroupMemberTable, PermissionTable extends SmartTableDefinition
2. Interface/ — UserServiceInterface, UserGroupServiceInterface, UserRepositoryInterface, UserGroupRepositoryInterface, UserGroupMemberRepositoryInterface, PermissionRepositoryInterface
3. Error/ — UserNotFoundError, UserValidationError, UserAccessDeniedError
4. Repository + Service — CRUD пользователей, групп, прав (каждый репозиторий получает SmartTable через `$serviceLocator->get('Core.SmartTable.Service')->open('users')`)
5. Создание таблиц через SmartTable MigrationService (addTable)
6. Деактивация (soft-delete, временная, причина)
7. Супер-админ (защита от удаления, группа «Администраторы»)
8. Контроллеры + валидация

#### Roleplay/Rule (Признаки + Источники)

**Назначение:** плоский справочник признаков при правиле (keywords); источники модификаторов — правила типа `source` (общая часть модуля Rule). Модуль двухуровневый: `Roleplay/Rule`, без вложенных подмодулей.

**Внешние зависимости:** Core/SmartTable.

**Файловая структура (признаки — часть модуля Rule, не подмодуль):**

```
Roleplay/Rule/
  module.config.php               — сервисы модуля (Rule + справочники: признаки, механики)
  Service/
    KeywordService.php            — CRUD признаков (code, name, description, active)
    ...
  SmartTable/
    KeywordTable.php              — extends SmartTableDefinition (keywords)
  Interface/
    Service/
      KeywordServiceInterface.php
    Repository/
      KeywordRepositoryInterface.php
  Repository/
    KeywordRepository.php         — implements KeywordRepositoryInterface
  Controller/
    KeywordController.php
  Entity/
    Keyword.php
  Error/
    KeywordNotFoundError.php      — extends NotFoundError
    KeywordValidationError.php    — extends ValidationError
```

**Шаги реализации:**

1. SmartTable/KeywordTable — extends SmartTableDefinition
2. Interface/ — KeywordServiceInterface, KeywordRepositoryInterface
3. Error/ — KeywordNotFoundError, KeywordValidationError
4. KeywordRepository + KeywordService — CRUD (code, name, description, active)
5. Создание таблицы через MigrationService (addTable)
6. Привязка к правилам (rule_keywords — rule_version_id + keyword_id)
7. Контроллеры + поиск по имени/code

#### Messages/Notifications

**Назначение:** уведомления (шаблоны, отправка, лента).

**Внешние зависимости:** Core/SmartTable.

**Файловая структура:**

```
Messages/Notifications/
  module.config.php               — сервисы уведомлений
  Interface/
    Service/
      NotificationServiceInterface.php
      NotificationTemplateServiceInterface.php
    Repository/
      NotificationRepositoryInterface.php
      NotificationTemplateRepositoryInterface.php
  SmartTable/
    NotificationTable.php             — extends SmartTableDefinition
    NotificationTemplateTable.php
  Service/
    NotificationService.php          — implements NotificationServiceInterface
    NotificationTemplateService.php  — implements NotificationTemplateServiceInterface
  Repository/
    NotificationRepository.php       — implements NotificationRepositoryInterface
    NotificationTemplateRepository.php
  Controller/
    NotificationController.php
    NotificationTemplateController.php  (admin)
  Entity/
    Notification.php
    NotificationTemplate.php
  Error/
    NotificationNotFoundError.php    — extends NotFoundError
    NotificationValidationError.php  — extends ValidationError
```

**Шаги реализации (волна 2 — бэкенд):**

1. SmartTable/ — NotificationTable, NotificationTemplateTable extends SmartTableDefinition
2. Interface/ — NotificationServiceInterface, NotificationTemplateServiceInterface, NotificationRepositoryInterface, NotificationTemplateRepositoryInterface
3. Error/ — NotificationNotFoundError, NotificationValidationError
4. Создание таблиц через MigrationService
5. Шаблоны (CRUD, плейсхолдеры {{name}})
6. API отправки уведомлений (createNotification)
7. Страница /notifications

**Отложено (волна 5 — после появления триггеров из Roleplay/Game и Messages/Chat):**

- Слайдер уведомлений (фронт-компонент) — _фронт-компонент реализован (ревью context-10/11); здесь отложена бэкенд-интеграция/триггеры_
- Фильтры (все / непрочитанные / ожидают действия) — _фронт-фильтры реализованы (context-10/11); бэкенд-часть — при триггерах_
- Автоматические триггеры (по событиям из игр/чата)

### Волна 3: Контент

#### Roleplay/Space

**Назначение:** управление пространствами, revision (автоинкремент x при публикации), наследование (snapshot copy).

**Внешние зависимости:** Core/SmartTable, Core/User.

**Файловая структура:**

```
Roleplay/Space/
  module.config.php               — сервисы пространств
  Interface/
    Service/
      SpaceServiceInterface.php
      SpaceInheritanceServiceInterface.php
      SpaceDraftServiceInterface.php
      SpacePublishServiceInterface.php
    Repository/
      SpaceRepositoryInterface.php
  SmartTable/
    SpaceTable.php               — extends SmartTableDefinition
  Service/
    SpaceService.php              — implements SpaceServiceInterface
    SpaceInheritanceService.php   — implements SpaceInheritanceServiceInterface: наследование (асинхронное копирование)
    SpaceDraftService.php         — implements SpaceDraftServiceInterface: единый черновик пространства
    SpacePublishService.php       — implements SpacePublishServiceInterface: публикация (diff → транзакция)
  Repository/
    SpaceRepository.php           — implements SpaceRepositoryInterface
  Controller/
    SpaceController.php
  Entity/
    Space.php
  Error/
    SpaceNotFoundError.php        — extends NotFoundError
    SpaceValidationError.php      — extends ValidationError
```

**Шаги реализации:**

1. SmartTable/SpaceTable — extends SmartTableDefinition
2. Interface/ — SpaceServiceInterface, SpaceRepositoryInterface + интерфейсы сервисов наследования/черновика/публикации
3. Error/ — SpaceNotFoundError, SpaceValidationError
4. Создание таблицы через MigrationService
5. CRUD пространств (name, description, version_a/b/c/x)
6. Единый черновик пространства — изменения накапливаются, публикация одним коммитом
7. Ревизии пространства — автоинкремент x при публикации (версия A.B.C — свойство правила)
8. Наследование (snapshot copy) — асинхронно через очередь, UI с прогрессом
9. Публикация — выбор правил → diff → подтверждение → транзакция

#### Roleplay/Rule (Nation, Language, WritingSystem)

**Назначение:** CRUD правил, версионирование, spec_json (типы: race, species, characteristic, resource, points, ability, item, damage_type, simple; spell/effect — отложены). Справочники Nation, Language, WritingSystem.

**Внешние зависимости:** Roleplay/Space, Core/SmartTable, Roleplay/Rule/Tag.

**Файловая структура:**

```
Roleplay/Rule/
  module.config.php               — сервисы модуля (правила + справочники)
  Interface/
    Service/
      RuleServiceInterface.php
      RuleVersionServiceInterface.php
    Repository/
      RuleRepositoryInterface.php
      RuleVersionRepositoryInterface.php
  SmartTable/
    RuleTable.php                 — extends SmartTableDefinition
    RuleVersionTable.php
    NationTable.php
    LanguageTable.php
    WritingSystemTable.php
  Service/
    RuleService.php              — implements RuleServiceInterface
    RuleVersionService.php       — implements RuleVersionServiceInterface: создание версий, diff
  Repository/
    RuleRepository.php           — implements RuleRepositoryInterface
    RuleVersionRepository.php    — implements RuleVersionRepositoryInterface
  Controller/
    RuleController.php
  Hydrator/
    RaceHydrator.php
    SpeciesHydrator.php
    CharacteristicHydrator.php
    ResourceHydrator.php
    PointsHydrator.php
    AbilityHydrator.php
    ItemHydrator.php
    DamageTypeHydrator.php
    SimpleHydrator.php
  Entity/
    Rule.php
    RuleVersion.php
    Nation.php
    Language.php
    WritingSystem.php
  Error/
    RuleNotFoundError.php        — extends NotFoundError
    RuleValidationError.php      — extends ValidationError
    NationNotFoundError.php
    LanguageNotFoundError.php
    WritingSystemNotFoundError.php
  Editor/                         — специфические формы редактирования (фронт)
    RuleEditor.vue
    RaceEditor.vue
    AbilityEditor.vue
    ItemEditor.vue
```

**Шаги реализации:**

1. SmartTable/ — RuleTable, RuleVersionTable, NationTable, LanguageTable, WritingSystemTable extends SmartTableDefinition
2. Interface/ — RuleServiceInterface, RuleVersionServiceInterface, RuleRepositoryInterface, RuleVersionRepositoryInterface
3. Error/ — RuleNotFoundError, RuleValidationError, NationNotFoundError, LanguageNotFoundError, WritingSystemNotFoundError
4. Создание таблиц через MigrationService
5. RuleRepository + RuleVersionRepository — CRUD правил
6. Подход 2 (всё в JSON) — spec_json + JsonField с hydrator'ами по типу
7. Версионирование — новая версия при каждом сохранении (copy-on-write)
8. Diff между версиями
9. Nation, Language, WritingSystem — CRUD справочники (ссылаются на space)
10. Редактор правил (фронт) — форма по типу (общие поля + JSON-блоки)
11. Просмотр правила (space-rule-view) — отображение по типу, сворачиваемые блоки

### Волна 4: Персонажи

#### Roleplay/Character

**Назначение:** жизненный цикл персонажа, редактор (табы), copy-on-write, сессионная модель, модерация.

**Внешние зависимости:** Roleplay/Rule, Roleplay/Space, Core/User, Core/SmartTable.

**Файловая структура:**

```
Roleplay/Character/
  module.config.php             — сервисы персонажей
  Interface/
    Service/
      CharacterServiceInterface.php
      CharacterEditorServiceInterface.php
      CharacterDraftServiceInterface.php
      CharacterModerationServiceInterface.php
    Repository/
      CharacterRepositoryInterface.php
      CharacterVersionRepositoryInterface.php
  SmartTable/
    CharacterTable.php          — extends SmartTableDefinition
    CharacterVersionTable.php
  Service/
    CharacterService.php          — implements CharacterServiceInterface
    CharacterEditorService.php    — implements CharacterEditorServiceInterface: расчёт ОС/ОЛ/ОР, модификаторы
    CharacterDraftService.php     — implements CharacterDraftServiceInterface: единый черновик, copy-on-write
    CharacterModerationService.php — implements CharacterModerationServiceInterface: утверждение/отклонение + чат
  Repository/
    CharacterRepository.php       — implements CharacterRepositoryInterface
    CharacterVersionRepository.php
  Controller/
    CharacterController.php
  Entity/
    Character.php
    CharacterVersion.php
  Error/
    CharacterNotFoundError.php    — extends NotFoundError
    CharacterValidationError.php  — extends ValidationError
```

**Шаги реализации:**

1. SmartTable/ — CharacterTable, CharacterVersionTable extends SmartTableDefinition
2. Interface/ — CharacterServiceInterface, CharacterEditorServiceInterface, CharacterDraftServiceInterface, CharacterModerationServiceInterface, CharacterRepositoryInterface, CharacterVersionRepositoryInterface
3. Error/ — CharacterNotFoundError, CharacterValidationError
4. Создание таблиц через MigrationService
5. CRUD персонажа + версионирование (copy-on-write)
6. Редактор (табы: Раса → Основа → Личность → Развитие → Инвентарь)
7. Расчёт ОС/ОЛ/ОР — модификаторы, live-пересчёт на фронте
8. Сессионная модель — черновик во время игры, модерация после
9. Модерация — в таблице `character_moderation` (описана в §3) хранится `chat_message_id` (внешний ключ на `chat_messages.id`, nullable). Сам модуль Chat реализуется в волне 5; диалог модерации донашивается после.

**Волна 4 фронта, шаг 1 (2026-08-05, список персонажей):** модуль Roleplay/Character приведён к анатомии frontend-rules: `Dto/Character` + `Dto/CharacterPoints`, `Enum/CharacterStatus` (string-literal union), `Interface/ICharacterApi`, `Service/CharacterApi` (`character.getList`), `Mock/mockCharacters`+`mockCharacterApi`, `Store/characters`, `Constant/CHARACTER_STATUS_OPTIONS`/`CHARACTER_STATUS_COLOR`/`charactersGridManifest`. Список `/characters` — карточки + `FilterBar` (имя, статус, раса, владелец, игра, «в игре» по `game_id`) + `useFilteredRows` (паттерн SpacesPage). Кнопка «Новый персонаж» гейтится `character.create` и ведёт на стаб `/characters/new` (F10). Роут `/characters` снял `guestAllowed` (гость персонажей не видит, §11); права персонажей — только `create`/`view` (ТР §4). Контекст разработки: `docs/specs/character-module-context.md`.

**Волна 4 фронта, шаг 2 (2026-08-05, карточка персонажа):** `Dto/CharacterDetail` + `Dto/CharacterVersion` (характеристики, ресурсы, способности, валюты, инвентарь, состояния) с подтипами по одному на файл; `Character` (список) расширен `spaceCode`/`rulesRevision`. **2026-08-26:** на список вынесен `discussionChatId` (резолв чипов обсуждения без N деталок). `ICharacterApi.getCharacter(id)` → `character.get` в `Service/CharacterApi` и моках (фикстуры деталей согласованы по (spaceId, spaceCode, rulesRevision) с моками Space: razrabotka rev ≤ 5, actual rev ≤ 12; discussionChatId — из существующих `character_discussion`-чатов mockChat, иначе null). `Store/characters` — `currentCharacter` + `fetchCharacter(id)` (паттерн spaces.ts). `Page/CharacterDetailPage` — v-tabs+v-window, доступ «свой vs чужой» внутри страницы после загрузки (предикат `Utils/access.ts` `canViewCharacter`, unit-тест), отказ → NotFound. Вкладки — `Component/Detail/`: OverviewTab (имя/раса/владелец/игра/статус + блок «Правила» с ссылкой на `/space/{code}/{vN}` + характеристики/ресурсы/валюты/способности display-only), DescriptionTab (краткое/полное описание + состояния), InventoryTab (таблица инвентаря), DiscussionTab (встроенный чат `character_discussion` через `useChatStore`: openChat/canInChat/ChatMessageList/ChatInput без ChatList; очистка activeChatId/stopSync при размонтировании). Роут `/characters/:id` без route-perm; карточки списка кликабельны. Контекст разработки: `docs/specs/character-module-context.md`.

**Волна 4 фронта, шаг 2.1 (2026-08-05, ссылочная модель вкладки «Обзор»):** инвариант «персонаж ссылается на {правило, ревизия}, а не копирует правила». `CharacterVersion`/подтипы переведены на ссылки `ruleId` + вычисленные итоги (характеристики: `{ruleId, value, modifiers}`; ресурсы: `{ruleId, current, max}`; способности: `{ruleId, level}`; предметы: `{id, ruleId, quantity, equipped, note?}`; раса — `raceRuleId`; валюты: `{code, amount}` с label из справочника). Имена/формулы/спеки правил резолвятся из ревизии: страница грузит `useSpaceRevisionStore.fetchRevision` (кэш, без `syncFromContext`) и передаёт `Rule[]` во вкладки. Новые сервисы: `CharacterReferenceService` (резолвер, фолбэк по коду), `FormulaEvaluationService` (fixed/characteristic/ability_level/dimensional), `CharacterOverviewService` → view-model `Dto/Overview/`. `OverviewTab` переработан: Правила (ссылки на правила), Характеристики (модификаторы source→delta→target), Ресурсы (прогресс + размерность), Валюты, Состояния (collapsible), Защита (доспехи по слотам + щит-блокирование), Атаки (оценка формул). Шапка страницы: имя, раса · игра, статус, владелец. `InventoryTab` — ссылочные предметы через тот же сервис. Моки согласованы: `Mock/mockSpaces` расширен (item/race/species всегда в срезе ревизии), добавлены предметы Щит/Кинжал, фикстуры на ссылках + тест разрешённости каждой ссылки. Гейт: формат/линт/типы/тесты чисты. Контекст разработки: `docs/specs/character-module-context.md` (§4.1, D9–D13).

**Волна 4 фронта, шаг 2.4 (2026-08-06, вкладка «Способности»):** в карточку персонажа добавлена вкладка «Способности» (`Component/Detail/AbilityTab.vue`) по образцу списка правил пространства (`RuleListPanel`): поиск по имени/описанию, под ним табы быстрого фильтра «Все / Избранное», список. Каждая способность — раскрывающаяся панель `v-expansion-panel`: шапка «Название · чип уровня (если `level > 0`) · чип типа способности (`ABILITY_TYPE_LABELS` из спеки) · вертикальный разделитель · иконка «открыть правило» (слайдер `RuleSlider` через `openRule`, `@click.stop`) · иконка-звезда избранного»; тело — описание правила + строка «Признаки: [чипы ключевых слов]». Признаки и тип берутся из резолвнутого правила: `AbilityOverview` расширен `type: AbilityType | null` и `keywordIds: number[]`; ключевые слова грузятся лениво через `useKeywordStore.fetchTags()` (публичный стор Rule). Избранное — per-character: новый `Store/abilityFavorites.ts` (localStorage `powerscale.character.ability-favorites.v1`, entries `{characterId, ruleIds}`), DTO `CharacterAbilityFavorites`, константа ключа; таб «Избранное» фильтрует по нему, поиск сужает. `ABILITY_TYPE_LABELS` ре-экспортирован из `Rule/init.ts` (публичная точка). Блок «Состояния» из вкладки «Описание» (`DescriptionTab`) удалён — состояния показываются только во вкладке «Обзор» (`StateTile`), чтобы не дублировать; `DescriptionTab` оставляет краткое/полное описание и больше не принимает `rules`. Гейт: формат/линт/типы/тесты чисты (vitest 316). Контекст разработки: `docs/specs/character-module-context.md` (§4.4).

**Волна 4 фронта, заход 3 (2026-08-06, редактор персонажа — фундамент + Раса/Основа):** общий редактор `CharacterEditPage` для создания и редактирования. `/characters/new` — настройка свободного создания (пространство + ревизия + лимиты ОС/ОР/денег) → редактор; `/characters/:id/edit` — copy-on-write черновик оригинала (единый черновик `Store/characterDraft`, localStorage). Расчётное ядро `CharacterEditorService` (build/toVersion): характеристики (раса fixed/purchased + дары способностей с агрегацией модификаторов по роли источника — макс. бонус и макс. штраф), бюджеты ОС/ОЛ/ОР/денег, доступность способностей (зона + `RequirementEvaluator` + расовые/авто) и уровни (`AbilityCost` array/progression). Выборы хранятся в `CharacterBuild` (spaceId/spaceCode/rulesRevision, raceRuleId, characteristicPurchases, abilities, ресурсы/инвентарь/состояния/деньги, olTotal); версия выводится `toVersion`. Табы: Раса (карточки рас с видом/наследованием, предупреждение при смене и сброс несовместимых) и Основа (черты зоны ОС: фильтры, цена, уровни, покупка характеристик по лестнице, live-характеристики); Описание (имя/краткое/полное); Личность/Развитие/Инвентарь — заглушки. API `character.create`/`character.update` (мок). Механика «Возраст» отложена. Гейт: формат/линт/типы/тесты чисты (vitest 353). Контекст разработки: `docs/specs/character-module-context.md` (§4.5–4.8).

**Волна 4 фронта, заход C (2026-08-09, этап «Личность» + механика возраста):** реализован этап «Личность» (зона `ol`) с полной механикой возраста. Новый тип правила `age` (`AgeSpec { ages: Age[] }`, ступень `{ name, ol, featureLimit, effects }`; у вида/расы `age_years { age, ageStart, ageEnd }`, наследуется по `parent_race_code`; разрешение — первый диапазон `ageStart ≤ годы < ageEnd`, за диапазонами — «Старый»). Эффекты возраста — real-модификаторы «от возраста» live (условные — scope, «условно: …» в попапе), применяются к базам производных. ОЛ = ступень (`buildBudgets.ol.total`); `CharacterBuild.ageYears`/`CharacterVersion.ageYears` (round-trip), `points.olTotal` из возраста. `PersonalityTab` (выбор возраста годами, 15 ol-особенностей с группами общительность/внимательность/богатство, отрицательные стоимости дают ОЛ, лимит числа особенностей без «Богатства», «ОЛ сгорают»); шаг «Личность» скрывается при отсутствии правила `age`. Деньги: грант `money` {fixed, percent, apply max/min} — эффективный бюджет = apply(фикс, % от лимита), Обеспеченный/Преуспевающий/Богатый/Нищий; взятие богатства — только при создании (при edit заблокировано). Реализация S11/S12 `docs/rule/import-plan.md`; данные `docs/rule/age/` + AI.html. Гейт: формат/линт/типы/тесты чисты (vitest 473), vite build ✓. Контекст: `docs/specs/character-module-context.md` §4.8 (D94–D100).

### Волна 5: Игры и коммуникации

#### Roleplay/Game

**Назначение:** управление играми, роли, модерация, добыча.

**Внешние зависимости:** Roleplay/Character, Roleplay/Space, Core/User.

**Файловая структура:**

```
Roleplay/Game/
  module.config.php             — сервисы игр
  Interface/
    Service/
      GameServiceInterface.php
      GameMemberServiceInterface.php
      GameModerationServiceInterface.php
      GameLootServiceInterface.php
    Repository/
      GameRepositoryInterface.php
      GameMemberRepositoryInterface.php
      GameLootRepositoryInterface.php
  SmartTable/
    GameTable.php               — extends SmartTableDefinition
    GameMemberTable.php
    GameLootTable.php
  Service/
    GameService.php              — implements GameServiceInterface
    GameMemberService.php        — implements GameMemberServiceInterface: участники, роли, приглашения
    GameModerationService.php    — implements GameModerationServiceInterface
    GameLootService.php          — implements GameLootServiceInterface
  Repository/
    GameRepository.php           — implements GameRepositoryInterface
    GameMemberRepository.php
    GameLootRepository.php
  Controller/
    GameController.php
    GameModerationController.php
    GameLootController.php
  Entity/
    Game.php
    GameMember.php
    GameLoot.php
  Error/
    GameNotFoundError.php        — extends NotFoundError
    GameValidationError.php      — extends ValidationError
```

**Шаги реализации:**

1. SmartTable/ — GameTable, GameMemberTable, GameLootTable extends SmartTableDefinition
2. Interface/ — GameServiceInterface, GameMemberServiceInterface, GameModerationServiceInterface, GameLootServiceInterface + репозитории
3. Error/ — GameNotFoundError, GameValidationError
4. Создание таблиц через MigrationService
5. CRUD игр (6 статусов)
6. Участники + роли (владелец/ведущий/участник) + приглашения
7. Модерация персонажей (панель ведущего)
8. Добыча (prepared → available → distributed; интерес, раздача предметов/денег, НПС/«вникуда»)

#### Messages/Chat

**Назначение:** чаты (private/group/game), команда /roll, макросы.

**Внешние зависимости:** Roleplay/Game, Core/Auth.

**Файловая структура:**

```
Messages/Chat/
  module.config.php             — сервисы чатов
  Interface/
    Service/
      ChatServiceInterface.php
      RollServiceInterface.php
      MacroServiceInterface.php
    Repository/
      ChatRepositoryInterface.php
      ChatMessageRepositoryInterface.php
  SmartTable/
    ChatTable.php               — extends SmartTableDefinition
    ChatMessageTable.php
    UserMacroTable.php
  Service/
    ChatService.php              — implements ChatServiceInterface
    RollService.php              — implements RollServiceInterface: Rule Engine (броски)
    MacroService.php             — implements MacroServiceInterface
  Repository/
    ChatRepository.php           — implements ChatRepositoryInterface
    ChatMessageRepository.php
  Controller/
    ChatController.php
  Entity/
    Chat.php
    ChatMessage.php
    UserMacro.php
  Error/
    ChatNotFoundError.php        — extends NotFoundError
    ChatValidationError.php      — extends ValidationError
    ChatMessageNotFoundError.php
  RollModifier/                    — Rule Engine
    DiceModifierInterface.php
    SixOneModifierV1.php
    SixOneModifierV2.php
    WoundModifier.php
    PiercingModifier.php
  Frontend/
    ChatSidebar.vue                — кружочки
    ChatSlider.vue                 — слайдер мессенджера
    ChatSyncService.ts             — SSE-соединение /api/chat/sync
```

**Шаги реализации:**

1. SmartTable/ — ChatTable, ChatMessageTable, UserMacroTable extends SmartTableDefinition
2. Interface/ — ChatServiceInterface, RollServiceInterface, MacroServiceInterface + репозитории
3. Error/ — ChatNotFoundError, ChatValidationError, ChatMessageNotFoundError
4. Создание таблиц через MigrationService
5. CRUD чатов + сообщений (private/group/game)
6. ChatSidebar (кружочки) + ChatSlider (слайдер)
7. Rule Engine — DiceModifierInterface + реализации
8. Команда /roll — парсинг → конвейер модификаторов → результат
9. Макросы пользователей
10. Ссылки в чатах (inline-чипы)
11. **SSE sync** — одно SSE-соединение `/api/chat/sync?since=`:
    - Бэк: endpoint, держащий соединение; при новых данных из `chat_messages.created_at > since` для чатов пользователя — `text/event-stream` с JSON-пакетом (chats + messages по чатам)
    - Фронт: ChatSyncService.ts — открывает/закрывает SSE при открытии слайдера, складирует сообщения в store
    - Индекс `(chat_id, created_at)` в `chat_messages`

---

## 13. Решения с фронта

**Канон фронта (2026-08-25, уточнение DAG 2026-08-26):** `draft-front_1.2ds/frontend-rules.md` — целевой стандарт (DAG: Space владеет ревизией; Character и Game ← публичный Space; Rule без Space, донор Chat-чипов; Chat без Roleplay; Character без Game; Utils — независимые мелочи; слои/DOM/запросы/XSS). Указатель для агента — `AGENTS.md`. Промт кампании — `docs/review/prompts/campaign.md`; список замечаний — `docs/review/findings.md`. Дальше: фаза DAG, затем ревью Rule→Chat→Character→Game, затем порядок правок. Старые `docs/review/context-*.md` — история, не канон.

### 1. ServiceLocator (фронт)

**Решение:** `Core/Engine/Service/ServiceLocator.ts` — generic SL (set/get/reset), export `serviceLocator`

**Реализация:**

- Per-модуль `init.ts`: `registerXApi(impl)` + `getXApi(): IXApi`
- `main.ts`: регистрация всех API (mock или real)
- Store'ы импортируют getter из своего `init.ts`

**Обоснование:** Чистая архитектура, модули не зависят друг от друга напрямую.

### 2. httpOnly cookie

**Решение:** Auth использует httpOnly cookie (фронт не хранит токен)

**Реализация:**

- `IAuthApi` — убрать `token` из login/register (возвращают `User`), убрать token из `getCurrentUser()`
- `AuthApi` — не передаёт token в body (сервер читает из httpOnly cookie)
- `mockAuth` — внутренний `loggedInUserId` вместо генерации токенов
- `auth.ts` store — убрать `token` ref, убрать localStorage, сессия в памяти
- `HttpClient.ts` — без изменений (`credentials: 'include'` уже есть)
- `checkAuth()` — просто вызывает `getCurrentUser()` без token

**Обоснование:** Безопасность. localStorage доступен любому JS в этом origin (XSS-вектор).

### 3. CSRF protection

**Решение:** CSRF Token (Synchronizer Token Pattern) — заголовок `X-CSRF-Token`

**Реализация:**

- CSRF живёт в `Core/Engine/` (после фазы 2.5 — внутри модуля Engine):
  - `Interface/ICSRFApi` — `initToken()`, `getToken()`
  - `Service/CsrfApi` — читает `csrf-token` из `document.cookie` (реальный режим)
  - `Mock/mockCsrf` — генерирует UUID в памяти (mock-режим)
  - `init.ts` — `registerCsrfApi() / getCsrfApi()`
- `HttpClient.ts` — добавлен конфигурационный коллбэк `getCsrfToken`. POST-запросы автоматически добавляют `X-CSRF-Token` из него.
- `main.ts` — регистрирует `CsrfApi` (real) / `mockCsrfApi` (mock), вызывает `initToken()`
- Бэк: устанавливает `csrf-token` cookie (non-httpOnly, SameSite=Strict) на логине; проверяет header === cookie на всех не-GET запросах, кроме `/api/login`

**Обоснование:** Защита от CSRF-атак. Cookies отправляются автоматически, CSRF-токена не было.

### 4. Password policy API

**Решение:** `IAuthApi.getPasswordPolicy()` — политика на бэке, фронт получает через API

**Реализация:**

- `PasswordPolicy` type в `Core/Auth/Dto/PasswordPolicy.ts`
- `IAuthApi.getPasswordPolicy(): Promise<PasswordPolicy>`
- `validatePassword()` — метод сервиса `PasswordValidatorService` (`Core/Auth/Service/PasswordValidatorService.ts`, дефолтная политика в `Core/Auth/Constant/defaultPasswordPolicy.ts`)
- `PasswordField` — принимает `:rules` (стандартный проп Vuetify), не импортирует валидатор
- `RegisterPage` и `ResetPasswordPage` — fetch политики на mount, формируют правила валидации, передают в PasswordField через `:rules` (ResetPasswordPage подключён 2026-08-02, F13)
- Mock: `{ minLength: 4, requireMixedCase: false, requireDigit: false, requireSpecialChar: false }`

**Обоснование:** Политика пароля должна быть на бэке, фронт получает её через API. PasswordField — чистый UI-компонент.

### 5. User type в Core/User

**Решение:** `User` тип перенесён из `Core/Auth` в `Core/User`

**Реализация:**

- `Core/User/Dto/User.ts` — новый `User` (включая `avatar_file_id`, `super_admin`)
- `Core/Auth/Dto/PasswordPolicy.ts` — только `PasswordPolicy`
- Auth store: `user` → `userId`, удалены `username/avatarLetters/userLogin`
- `users` store: добавлены `currentUser`, `setCurrent/clearCurrent/setGuest`, `username/avatarLetters/userLogin`
- Обновлены импорты User (11 файлов), обновлены `auth.user` → `auth.userId` / `userStore.currentUser` (8 файлов)
- `IAuthApi`, `AuthApi`, `mockAuth`, `mockAuthApi`, `usePermissions` — обновлены

**Обоснование:** User — это не про авторизацию, это про пользователя. Auth должен хранить только userId.

### 6. Деактивация с датой и причиной

**Решение:** Диалог с date picker + textarea причины

**Реализация:**

- `IUserApi.deactivateUser(id, reason?, deactivatedUntil?)` — новые опциональные параметры
- `UserApi`, `mockUsers`, `users` store — обновлены
- Диалог: textarea «Причина» + text field type=date «Отключён до» (опционально)
- `handleDeactivate` передаёт причину/дату в store, сбрасывает поля после

**Обоснование:** ТЗ требует временную деактивацию (до даты) с причиной.

### 7. Batch endpoint для пользователей

**Решение:** `IUserApi.getUsersByIds(ids[])` — batch endpoint

**Реализация:**

- `IUserApi.getUsersByIds(ids: number[]): Promise<User[]>`
- `UserApi`, `mockUserApi`, `users` store — реализованы
- `useChatUsers.ensureUsers()` — использует batch вместо N запросов
- Один запрос вместо N для недостающих пользователей

**Обоснование:** Устранение N+1 проблемы при загрузке пользователей чата.

### 8. markChatRead оптимизация

**Решение:** Проверка `unreadCount > 0` перед вызовом `markChatRead` в sync-пути; `openChat`/`sendMessage` — безусловно.

**Семантика прочтения:** позиция прочтения хранится в `chat_members.last_read_message_id`; `unreadCount` — вычисляемый (`COUNT(messages WHERE id > last_read_message_id AND user_id != me)`), приходит в данных чата вместе с `lastReadMessageId`. Разделитель «Новые сообщения» в списке сообщений строится по `lastReadMessageId` — без поуровневой таблицы прочтений. Разделитель виден при новых сообщениях во время чтения истории (автоскролл выключен); открытие чата помечает его прочитанным сразу, поэтому при обычном открытии разделитель не показывается.

**Реализация:**

- `applySyncResponse` (sync-путь): `markChatRead` вызывается только если `chat.unreadCount > 0`; при вызове `unreadCount = 0` и `lastReadMessageId` обновляется на последнее полученное сообщение. При первом sync с новыми сообщениями → вызывается один раз, при последующих — нет (пока чат не откроется заново).
- `openChat`/`sendMessage`: `markChatRead` вызывается безусловно (явное действие пользователя — не тики, спам невозможен); `unreadCount = 0` и `lastReadMessageId` обновляются локально.

**Обоснование:** Устранение спама API вызовов на каждый sync-тик; при этом открытие чата и отправка сообщения всегда сбрасывают прочтение.

### 9. Generic Row тип

**Решение:** `Row<T extends Record<string, any> = Record<string, any>> = T`

**Реализация:**

- Обратная совместимость: `Row` без параметра = `Record<string, any>`
- Можно передавать `User[]`, `Game[]`, `Character[]` как `Row[]`
- Опционально: `<SmartGrid<User> :rows="pageRows" />` для строгой типизации

**Обоснование:** Type safety в Grid. `[key: string]: any` сводил на нет типизацию.

### 10. Error boundaries

**Решение:** ErrorBoundary компонент в App.vue

**Реализация:**

- Добавлен `onErrorCaptured` в App.vue
- При ошибке показывается fallback UI с иконкой, сообщением и кнопкой перезагрузки
- `reset()` очищает ошибку и перезагружает страницу

**Обоснование:** Любая ошибка в компоненте = белый экран. Нужен глобальный обработчик.

### 11. UserProfilePage error state

**Решение:** Добавить error ref + UI

**Реализация:**

- Добавлен `error` ref для хранения сообщения ошибки
- Выделена функция `loadUser()` с try/catch
- Добавлен UI блок с иконкой, сообщением и кнопкой "Попробовать снова"
- Логгер добавится в store позже (когда будем делать)

**Обоснование:** Пустая страница при ошибке загрузки пользователя. Нужен error state.

### 12. AbortController

**Решение:** AbortController в HttpClient + composable `useAbortable`

**Реализация:**

- Создан composable `useAbortable.ts` в `Core/Composables/`
- Обновлён HttpClient чтобы принимать `signal`
- Обновлён Engine.runAction чтобы принимать `signal`
- Обновлены IUserApi и реализации (UserApi, mockUserApi) чтобы принимать `signal`
- Обновлены store методы чтобы принимать `signal`
- Компоненты (UserProfilePage, UsersListPage) используют `useAbortable`
- Автоматическая отмена запросов при unmount компонента
- Обработка AbortError в компонентах и store

**Обоснование:** Inflight-запросы при unmount чата/слайдера. Race conditions.

### 13. Debounce utility

**Решение:** Вынести debounce в `Core/Engine/Utils/debounce.ts`

**Реализация:**

- Создан `Core/Engine/Utils/debounce.ts`
- Обновлён `FilterBar.vue` чтобы импортировать из утилиты
- Добавлен debounce (300ms) в `NotificationsPage.vue` для поиска

**Обоснование:** Переиспользование кода. Нет debounce на полях поиска.

### 14. Двойной `<v-app>`

**Решение:** Убрать `<v-app>` из Shell.vue

**Реализация:**

- Убран `<v-app>` из Shell.vue, оставлен в App.vue

**Обоснование:** Вложенность `<v-app>` внутри `<v-app>` может ломать layout.

### 15. Чаты не загружаются после логина

**Решение:** Загрузка чатов в Shell.vue onMounted

**Реализация:**

- Добавлена загрузка чатов в Shell.vue onMounted с проверкой `if (chatStore.chats.length === 0)`

**Обоснование:** После логина пользователь на Dashboard, но чаты загружаются только в Messenger.vue.

### 16. Виртуализация списков в SmartGrid (F22)

**Решение:** Отложено осознанно. В SmartGrid используется `items-per-page="-1"` — встроенная пагинация Vuetify отключена, потому что списки уже пагинированы родителем через `pageRows` (perPage ≤ 100). Виртуализация/`v-memo` не добавлялись.

**Реализация:** не реализовано; добавить при появлении больших **непагинированных** списков.

**Обоснование:** Пагинация родителем исключает большие DOM-наборы; виртуализация на текущих объёмах — преждевременная оптимизация.

### 17. Per-object права на фронте (F25)

**Решение:** Отложено на бэкенд-фазу. Per-object ключи (`space.view/edit`, `rule.*` per-space, `game.edit/moderate/manage`) описаны в §4, но на фронте через группы назначаются только глобальные ключи — `PERMISSION_KEYS` содержит лишь `space.create/view_all/edit_all`, `game.create/view_all/edit_all`. Проверки идут через `hasAnyPermission(user, keys)` по плоскому списку `user.permissions`.

**Реализация:** не реализовано; per-object проверки — задача бэкенд middleware (§4 «Алгоритм проверки») + передачи прав конкретного объекта в данных API.

**Обоснование:** Per-object права требуют хранения привязки к объекту (`space_permissions`, `game_members`) и вычисления на стороне API; на прототипе это ведёт к N+1. Реализуется вместе с бэкендом.

### 18. Персистентность черновика правил (F42)

**Решение:** черновик `draftRuleStore` сериализуется в `localStorage` (ключ `powerscale.drafts.v1`). Восстанавливается при старте, обновляется после каждого изменения, очищается при `discardDraft`/`clearAll` и после коммита.

**Реализация:** `draftRules.ts` — загрузка при инициализации стора (невалидный JSON/неверная структура безопасно отбрасываются), `persist()` после каждого мутатора; пустой черновик удаляет ключ. Ключ версионируется.

**Обоснование:** по §5 черновик «живёт в браузере до коммита»; in-memory терял его при F5 (случайная перезагрузка во время правки — потеря работы). localStorage переживает F5, но сбрасывается логикой очистки при коммите/отказе; sessionStorage хватало бы на вкладку, но localStorage единообразен с другими клиентскими хранилищами.

### 19. Плагинная модель Chat (F-решено по ревью)

**Решение:** `Messages/Chat` — хост плагинов с реестрами в `init.ts`; доменные типы/команды/вложения регистрируются модулями-донорами (`Roleplay/Game`, `Roleplay/Rule`, `Roleplay/Character`) через `registerChatType(s)/registerChatTab(s)/registerCommandHandler/registerContentRenderer/registerInlineRenderer/registerTokenSource/registerAttachmentProcessor/registerToolbarExtension`. Сообщения несут `attachments: ChatAttachment[]` (`{ type, payload }`) вместо доменного `rolls`; `IAttachmentProcessor.process` конвертит payload при отправке.

**Реализация:** см. §9 «Плагинная модель Chat» и «Волна рефакторинга Chat» в §12.

**Обоснование:** устранена зависимость `Messages/Chat → Roleplay/Game` из production-кода (P2-1 из `docs/review/context-12.md`); новые типы чатов и вложений добавляются регистрацией, без правки хоста.

### 20. Inline-чипы в чате (F35)

**Решение:** текст сообщения разбивается на `InlineSegment[]` (`text` | `token`); токен
`[[type:param1,param2]]` рендерится чипом через `registerInlineRenderer/getInlineRenderer`
(единый интерфейс `IRenderer`); чип открывает свой слайдер по клику. Ввод чипов — пикер
в `ChatInput` через реестр `ITokenSource` (источники `user` в Chat, `rule` в Rule).

**Реализация:** `Utils/inlineContent.ts` (`parseInlineContent` + `inlineContentToText`);
`ChatMessenger.vue` рендерит сегменты; `ChatUserChip.vue` (владелец Chat — Core/User не
может зависеть от Chat) + `RuleChip.vue`/`RuleSlider.vue` (владелец Rule — Rule может
зависеть от Chat); `IRenderer.describe(segment)` — подпись для превью списка чатов
(`ChatList` → `inlineContentToText`); placeholder «Объект скрыт» при недоступном объекте;
пикер вставки через `ITokenSource`; `UserProfileSlider` — общий компонент в
`Core/User/Component`; тест `inlineContent.test.ts`.

**Обоснование:** F35 из ТЗ (inline-чипы, слайдер, скрытый объект); направление
зависимостей сохранено (Core/User → Chat → Rule не создаёт, Rule → Chat допустимо);
новая цель ссылки добавляется регистрацией чипа и источника, без правки хоста.

### 21. Виртуализация списка сообщений (P2-2/P2-3/P2-4/P3-4)

**Решение:** подключён `@tanstack/vue-virtual` (headless виртуализация). Список сообщений
рендерится только в видимом диапазоне через `useVirtualizer` (`anchorTo: 'end'`,
`followOnAppend`, `measureElement` для произвольных высот). Стор дополнен `initialized`
(догрузка истории при state от синка), капом неактивных чатов `MAX_STORED=500`,
полями ошибок `chatsError`/`chatError`/`actionError`.

**Реализация:** `ChatMessageList.vue` + `ChatMessageRow.vue` + `Composables/useChatVirtualScroll.ts`
(вынос списка из `ChatMessenger` — декомпозиция P3-3); удалён `renderedMessages` (P3-4).
Подробно — §9 «Загрузка и рендер сообщений».

**Обоснование:** сообщения разной высоты (вложения, чипы, переносы) исключают Vuetify
`VVirtualScroll` (фикс. `itemHeight`); `followOnAppend` + `anchorTo: 'end'` дают
chat-специфичное поведение (докрутка только у конца, стабильный якорь при препенде
истории) из коробки. DOM ограничен окном независимо от объёма истории.

---

## Отложенное

Функционал, который не входит в init-релиз:

1. **Боевая карточка** — отдельная страница, после основных разделов
2. **Diff версий (UI)** — компонент DiffViewer
3. **Журнал действий** — админ-лог
4. **Эффекты (runtime-статусы)** — тип правила, требует детальной проработки
5. **Смесь рас** — future (race_mix в data_json)
6. **Заклинания** — после способностей
7. **Bulk import правил** (AI → JSON → админка)
8. **Автоматические триггеры уведомлений** (из игр/чата)

> **Ревизия 2026-08-05:** из «Отложенного» сняты «Слайдер уведомлений (фронт)»
> (было п.8) и «Фильтры уведомлений» (было п.9) — реализованы и прошли ревью
> (`docs/review/context-10.md`, `context-11.md`). Оставшиеся пункты перенумерованы.

---

## Сводка решений

### Архитектура

1. ✅ Модульная система: Core/Messages/Roleplay
2. ✅ DI: ServiceLocator (dot-нотация + alias по ::class)
3. ✅ SmartTable: единственная точка доступа к данным
4. ✅ Фронт: Shell + модули (Vite + TypeScript + Vuetify)

### Авторизация

5. ✅ Открытая регистрация, default группа «Игрок»
6. ✅ Поля: логин + пароль + email + имя/фамилия/псевдоним/аватар
7. ✅ Супер-админ, защищён от удаления/снятия с группы
8. ✅ Права на пользователей/группы — отдельные ключи (не монолит)
9. ✅ Группы с защитой от эскалации
10. ✅ Права на объекты: группы + индивидуально
11. ✅ Деактивация (soft-delete) для пользователей и групп
12. ✅ Статусы игры: 6 статусов (черновик → набор игроков → в процессе → на паузе → идёт игра → завершена)
13. ✅ Роли в игре: Владелец → Ведущий → Участник
14. ✅ Единый формат ключей: `объект.действие` (`user.*`, `user_group.*`, `game.*`, `space.*`, `character.*`, `tag.*`, `notification_template.*`)
15. ✅ Authorization middleware: super-admin bypass, двухуровневая проверка, правило «свой vs чужой»
16. ✅ Роль в игре: `owner` = полный доступ, `gm` = edit + moderate, `player` = только права группы
17. ✅ Множественные запросы: фильтрация через JOIN, не N отдельных проверок
18. ✅ `character.view` — новый ключ для просмотра чужих персонажей

### Правила

19. ✅ Валюты-очки (ОС/ОЛ/ОР) — НЕ тип правила изначально; с 30.48/30.49 введён тип `points` (Очки), зоны способностей ссылаются на него по коду
20. ✅ Раса — тип-контейнер; с 30.45 разделена на `race` (играбельная) и `species` (вид/подвид) — см. `docs/specs/race-design.md`
21. ✅ Характеристика и Ресурс — разделены на два типа правил
22. ✅ Способности — общий тип, подтипы через теги
23. ✅ Эффекты — отложены
24. ✅ Состояние — не тип правила, runtime-агрегат; **отменено 06.08.2026** — введён тип `state` (см. `docs/specs/state-design.md`)
25. ✅ Простое правило — минимальный тип
26. ✅ Теги — плоский справочник
27. ✅ Множественные навыки — шаблон + экземпляр
28. ✅ Описание — HTML, Спецификация — JSON-блоки; HTML проходит через единый allowlist-санитайзер перед отображением
29. ✅ Требования — единая модель
30. ✅ Теги как способ определения подтипов способностей
    30.1. ✅ Версия A.B.C — свойство правила, revision (x) — свойство пространства
    30.2. ✅ Механики — отдельная сущность, правило ссылается через mechanic_id (nullable), маппинг на код в Rule Engine
    30.3. ✅ Размерные числа — фундаментальный тип данных `{B|x}`, хранятся как JSON, отображаются через компонент DimensionalNumber
    30.4. ✅ Характеристика — всегда размерная, база 3-5, может быть производной (min/max из двух базовых)
    30.5. ✅ Ресурс — может быть размерным или безразмерным, не может быть производной
    30.6. ✅ Группировка характеристик и ресурсов — через признаки, а не через поле group
    30.7. ✅ Предмет — категория (money/equipment/other), подтипы через checkboxes (weapon/armor/shield)
    30.8. ✅ Тип урона — отдельный тип правила для справочника типов урона и сопротивлений
    30.9. ✅ Источники модификаторов — справочник для определения происхождения (защита, сопротивления и т.д.), модификаторы от одного источника не суммируются
    30.10. ✅ Формулы — единый формат: `{type: 'fixed', value}` или `{type: 'characteristic', characteristic_id, modifier}`
    30.11. ✅ Профили оружия — массив профилей (strike/throw/shoot) с уроном, пробитием, точностью
    30.12. ✅ Слоты защиты/сопротивлений — массивы с источником (source_id) для логики не-суммирования от одного источника
    30.13. ✅ Ограничения характеристик — массив {characteristic_id, limit: formula} для брони
    30.14. ✅ SpaceRevision — иммутабельный снимок правил на момент публикации; кеш по (spaceId, revision); API: getRevisions, getRevision, commitDraft
    30.15. ✅ Черновик (draft) — клиентский (draftRuleStore), живёт в браузере до коммита; commit отправляет пачку изменённых правил
    30.16. ✅ Контекст просмотра: published (latest) / history:N / draft — явный селектор на странице пространства
    30.17. ✅ Строковые ссылки между правилами — по семантичному `code`, не по `id`. `code` — глобальный семантический ключ **правила** (`Rule`), общий для всех версий и пространств; живёт в `rules.code`, `RuleVersion` его НЕ несёт (убрано). Задаётся при создании, после создания не изменяется.
    30.18. ✅ Способность: цена живёт в зонах (`zones: Partial<Record<ZoneId, AbilityCost>>`, ключи = коды очков-правил), `AbilityCost` = array/progression/automatic; `levels`/`hard`/`automatic` как отдельные поля убраны
    30.19. ✅ Ресурс — отдельный тип правила (`ResourceSpec: is_dimensional + initial_value`); подтип `resource` из характеристики удалён; правило хранит только определение
    30.20. ✅ Дары (Grants): `characteristic`/`characteristic_modify`, `resource`/`resource_limit_change`, `ability`, `tag`, `item`; формула `ability_level` с обязательным `ability_code`
    30.21. ✅ Требования: `has_ability`, `has_ability_tag`, `has_tag`, `characteristic_value`, `resource_limit`, `and`/`or` (рекурсивно)
    30.22. ✅ Валидация ссылок при публикации (draft commit) — `validateRuleReferences`, срез пула всегда полный и консистентный
    30.23. ✅ Предмет: `special_rules` → `special_rule_codes`, добавлен `innate` (естественный предмет)
    30.24. ✅ Реализованы типы «Способность» и «Ресурс» на фронте: `ResourceEditor`, `AbilityEditor` (панели Общее/Зоны и стоимость/Требования/Дары/Действие/Улучшение), рекурсивный `RequirementEditor`, `GrantEditor`; ветки типов в `RuleEditPage`, таб «Ресурсы» и `typeLabels.resource`
    30.25. ✅ `FormulaInput` — ссылки по `code` (`characteristic_code`, `damage_type_code`), узел `ability_level` виден только при prop `abilities`
    30.26. ✅ Валидация ссылок подключена в `publishDraft` (блокировка публикации с диалогом ошибок); моки: ресурсы (ОД/Ци Духа/Мана), способности (Ближний бой, Двойной удар), `generateRevisionRules` — замыкание ссылок, подтипы-теги (combat/utility/passive/active)
    30.27. ✅ `generateRevisionRules`: ресурсы/способности попадают в срез всегда (независимо от `count`) — решена проблема «нет в наличии»
    30.28. ✅ Единая схема типов способности — `Rule/Dto/Ability/` (`Requirement`, `Grant`, `Formula`, `AbilitySpec` с `requirements_by_level`, `DimensionalNumber`); `FormulaInput`/редакторы импортируют из неё
    30.29. ✅ Источник модификатора — `source_id` из `sourceStore.sources` (универсальный справочник, не только предметы); закреплено в дизайн-доке; моки + «Тренировка»/«Развитие»
    30.30. ✅ Зоны способности — `v-checkbox` по зонам (Создание/Личность/Развитие) вместо `v-select`; редактор стоимости на каждую включённую зону
    30.31. ✅ Требования: `RequirementListEditor` (список, неявное И) + рекурсивный `RequirementNodeEditor` (И/ИЛИ-группы); подписи-описания в селекторе условий; `has_tag` без кол-ва; `characteristic_value.min` → `DimensionalNumber`; `resource_limit.min` адаптивно (число ↔ размерное)
    30.32. ✅ `requirements_by_level` в `AbilitySpec` — авто-прогрессия «Развитие интеллекта х из 3»; панель «Требования по уровням» в редакторе; валидация ссылок из неё
    30.33. ✅ Дары: `GrantEditor` — вертикальный лэйаут; `characteristic` + `value` (размерное), `resource` + `limit` (адаптивно), `characteristic_modify`/`resource_limit_change` + `source_id`
    30.34. ✅ ОД (action-points) — простое число (`is_dimensional: false, initial_value: 3`); ресурсные `action_components[].amount` адаптивно (размерное у размерного ресурса, число у обычного, минимум у основания — 0)
    30.35. ✅ «Модификатор характеристики» — переименован дар `characteristic_modify` (+N к характеристике); в «Тип» формулы дара только «Число» (бывш. «Фикс») и «Уровень способности» (`FormulaInput.modes`); в профилях оружия/ограничениях тип «От характеристики» сохранён
    30.36. ✅ Тип способности — явное поле `AbilitySpec.type` (`AbilityType = trait/feature/skill/action/process/spell`, источник истины) + `resolveAbilityTypeFromTags` для легаси; справочники `ABILITY_TYPE_LABELS`/`ABILITY_TYPE_TAGS`; типообразующие теги авто-синхронизируются редактором
    30.37. ✅ Селектор «Тип способности» в `AbilityEditor`; видимость панелей по типу: «Компоненты действия» — только action/spell, «Процесс» — только process (без общего «Действия»), «Заклинание» — только spell
    30.38. ✅ `action_components[].label` — переопределение подписи (у заклинаний ОД = «Сотворение»); авто-добавление ОД-компонента при выборе action/process/spell (мин. 1 ОД); первый ОД-компонент зафиксирован (ресурс не меняется/не очищается, минимум 1, удаление заблокировано)
    30.39. ✅ Процесс — `ProcessSpec` (шаги «Название+Описание+Ресурсы», повтор = само-переход): переходы `chain(max_shift, direction)` / `free` / `custom(edges)`, `start_step_code`, `failure` (restart_from_first/end_action); редактор `ProcessEditor`; мок «Движение» (Ходьба→Бег→Спринт)
    30.40. ✅ Заклинание — `SpellSpec` (`difficulty` — сложность сотворения, `duration` instant/refreshable/sustained); компоненты verbal/somatic(note)/material в `action_components`; редактор `SpellEditor`; мок-заклинание
    30.41. ✅ Карточка `AbilityCard` по типу способности (цена по зонам, требования, дары, действие, шаги процесса, заклинание) + подключение в `RuleDetailPage`
    30.42. ✅ Валидация: action/spell — ОД-стоимость ≥1 (`action-points`); process — ≥2 шага, ОД у каждого шага, `start_step_code`/рёбра `custom` → существующие шаги; spell — `difficulty`, `material.item_code` → предмет; тесты резолвера типа и валидации
    30.43. ✅ Манифест блоков + prune на эмите: `ABILITY_SPEC_FIELDS`/`ITEM_SUBTYPE_FIELDS` (дискриминант → поля), универсальный паттерн для способности и итема; способность — дискриминированный юнион `AbilitySpec` (Draft — черновой слой редактора, Clean — чистый на эмите), итем — мультивыбор без юниона; чистка только на границе эмита (`specToEmit`), внутренние поля при смене типа/подтипа НЕ чистятся
    30.44. ✅ Требования и дары — единые карты уровней: `requirements: {level, requirements}[]`, `grants: {level, grants}[]` (уровень 1 = получение, бывшие `requirements`/`requirements_by_level` и `grants.general`/`byLevel` слиты); дар `permanent?: boolean` (default true — накапливается на уровнях ≥ N, false — строго на уровне); требования накапливаются естественно (взял уровень N — уровни < N уже удовлетворены); «Ближний бой x из 3» — один `ability_level`-дар на ур. 1 (формула масштабируется сама, подсказка в редакторе); панели редактора слиты в одну
    30.45. ✅ Раса разделена на два типа: `race` (играбельная, терминальная точка цепочки, из неё генерится персонаж) и `species` (вид/подвид, узел дерева). Иерархия: Вид → Подвид → … → Раса; вид и подвид различаются только наличием `parent_race_code`; от расы не наследуются, родитель расы — всегда `species`
    30.46. ✅ `RaceSpec`: `parent_race_code` (→ species), `cost_os` (стоимость в ОС, отрицательная = даёт ОС), `characteristics` (стартовый профиль), `abilities` (свои + наследуемые). Характеристики — per-characteristic режим `fixed` (фикс. база, дальше дары черт) / `purchased` (минимум + таблица закупки `purchase: [{cost, value}]` «за N ОС → значение»). Способности — список `{ability_code, automatic}`. `SpeciesSpec`: `parent_race_code` + `abilities` (наследуются расами цепочки). Дизайн: `docs/specs/race-design.md`
    30.47. ✅ Валидация рас/видов: ссылки `parent_race_code`→species, `characteristic_code`→characteristic, `ability_code`→ability; `validateRaceStructure` (cost_os число, пустые/дубли коды, purchase: cost>0 и уникальные), `validateSpeciesStructure`; проверка циклов среди species (A→B→A)
    30.48. ✅ Терминология: ОС/ОЛ/ОР — «очки», а не «валюта»; «валюта/деньги» — только монеты (`item.category: money`). Отменённый тип `currency` убран из схемы
    30.49. ✅ Тип правила `points` (Очки): `name`/`code`/`description`/теги, спеки нет; код — системное имя (`os`/`ol`/`or`). Рефактор зон способностей: `ZoneId` = код очков-правила, зоны в редакторе/карточке берутся из очков-правил пространства; валидация зон → ссылки на `points`. Поле «Код» вынесено в общий `RuleEditorBase` (у всех типов). Моки os/ol/or
    30.50. ✅ Компоненты действий — `action_components: ActionComponent[]` вместо `action_costs`: `ActionComponent = ({type:'resource'} & {resource_code, amount, label?}) | verbal | somatic | material`. Ресурсный компонент — трата ресурса (ОД = `action-points`, `label` «Сотворение» у заклинаний); материальный — item XOR набор тегов × consume XOR use (пустой материал — ошибка структуры); сложность/длительность — только у заклинаний (`SpellSpec` без компонентов). Редактор `ActionComponentsEditor` (единый блок + выбор типа при добавлении); карточка группирует не-ресурсные компоненты как «Компоненты»

### Персонажи

31. ✅ Фронт — активные расчёты, бэк — валидация
32. ✅ Фильтры черт: 4 категории
33. ✅ Copy-on-write при редактировании
34. ✅ Статусы: Черновик → Готов → Модерация
35. ✅ Сессионная модель в игре
36. ✅ Отрицательные ОЛ сгорают
37. ✅ Лимиты при свободном создании

### Интерфейс

38. ✅ Игры: полная структура (10 подстраниц)
39. ✅ Персонажи: список с фильтрами, редактор-табы, миграция версий, деактивация
40. ✅ Боевая карточка — отдельная страница, позже
41. ✅ Редактор персонажа — одна страница с табами
42. ✅ Удаление персонажа — деактивация (soft-delete)
43. ✅ Черновики в списке видны только владельцу
44. ✅ Пользователи: 5 страниц
45. ✅ Группы: 5 страниц
46. ✅ Пространства: 10 страниц
47. ✅ Редактор правил: отдельные формы для каждого типа
48. ✅ Режим редактирования: при создании персонажа с `rule.edit` можно править правила в контексте
49. ✅ Уведомления: 🔔 в топбаре, `/notifications`, админ-страницы
50. ✅ Сброс пароля: стандартный
51. ✅ Теги: админ-страницы + попап создания при редактировании правила
52. ✅ Аватар — загрузка в профиле
53. ✅ Деактивация: для пользователей — страница (дата, причина), для остальных — попап
54. ✅ Лейаут: топбар + сайдбар + футер
55. ✅ Контекст пространства-времени — внутри страницы
56. ✅ Bitrix-style фильтр
57. ✅ Horizontal editor panel
58. ✅ Expansion panel

### Фронтенд

59. ✅ ServiceLocator (фронт)
60. ✅ httpOnly cookie
61. ✅ CSRF protection
62. ✅ Password policy API
63. ✅ User type в Core/User
64. ✅ Деактивация с датой и причиной
65. ✅ Batch endpoint для пользователей
66. ✅ markChatRead оптимизация
67. ✅ Generic Row тип
68. ✅ Error boundaries
69. ✅ UserProfilePage error state
70. ✅ AbortController
71. ✅ Debounce utility
72. ✅ Двойной `<v-app>` исправлен
73. ✅ Чаты загружаются после логина
74. ✅ Плагинная модель Chat: реестры типов/вкладок/команд/рендеров/attachment-процессоров/тулбара в `init.ts`; `ChatMessage.attachments` вместо `rolls`; зависимость Chat→Roleplay устранена (P2-1)
75. ✅ Inline-чипы (F35): `parseInlineContent` (токены `[[type:params]]`), рендер сегментов в `ChatMessenger`, чипы пользователя (Chat) и правила (Rule), слайдеры, placeholder «Объект скрыт», пикер вставки через `ITokenSource`
76. ✅ Виртуализация чата (`@tanstack/vue-virtual`): `ChatMessageList`+`ChatMessageRow`, `useChatVirtualScroll` (`anchorTo:'end'`, `followOnAppend`, `measureElement`); стор: `initialized` (P2-3), кап неактивных `MAX_STORED=500` (P2-2), `chatsError`/`chatError`/`actionError` (P2-4), удалён `renderedMessages` (P3-4)

---

## 14. Очередь первого релиза (август 2026)

Порядок после инвентаря-экземпляров и влияния модификаторов на статы (`ItemModifierService.applyStack`). Не откатывать уже сделанное.

1. **Хвосты модификаторов** — ✅ цена/keywords/флаги (открытое лицо, проводник магии, минимум 2 ОД, множители «трудное в изготовлении», импровизированное = 0). Таблица качества доспеха — отдельным куском.
2. **Помехи/преимущества по `source`** — ✅ как модификаторы характеристик: `{ source_code, delta }`, max+ / min− от одного источника; `DiceRollSpec.advantages`, нетто в механике.
3. **Тип правила «Проверка»** — ✅ обёртка над «Бросок» / `RollEngine` (`docs/specs/check-design.md`): тип `check`, дерево, `attached_rule_codes`, РУ; запуск соло и pairwise (оферта, мок); инициатива своим UI; удар/увечье — позже.
4. **Инвентарь карточки** — ✅ те же строки, что в редакторе (`variant: 'sheet'`), без закупки; экип через `characterDraft`, «Сохранить изменения» = валидация «Готов» + `updateCharacter`.
5. **Заметки** — ✅ личные владельца персонажа и зрителя игры; иконка в топбаре → диалог; не в `CharacterVersion`.
6. **Боевая карточка** — ✅ `docs/specs/combat-card-design.md` (ядро + CD-13): вкладки Обзор/Способности/Инвентарь, шевроны, тайлы защиты/атак, экип в оверлей; инициатива: запуск `check-initiative`, механики броска с проверки выбранной характеристики.

## 15. Очередь после полировки проверки

Порядок следующих крупных кусков (не смешивать с полировкой текущего запуска). Детали — по заходу.

1. **Добить текущий шаг проверки** — попадание ББ 1v1: реакция у защитника, размерная эффективность — ✅; увечье v2 (4к6 vs сложность, сила = −РУ, истощение с 7 / offset 6, ручной запуск задаёт сложность, кнопка «Увечье» на карточке остаётся, `maim` independent, чат: бросок затем текст + [i]) — ✅ 2026-08-24; **с атаки автоматически накладывать увечье** (HP и рана **этого удара** в формуле, истощение с оверлея; успехи увечья без свертки отрицательной базы; 0 повреждений после сопротивления — без автопроверки, даже если колющий даёт ⌊РУ/2⌋; сложность 0 — без чата/`maim`) — ✅ 2026-08-24; SSE — дальше. **Цикл атаки со статусами** (2026-08-24): эффекты слабость/обессилен/бессознательность/увечье/кровопотеря; лимит ОД от живых характеристик; авто `check-exhaustion`; конец хода раны→кровь; резерв истощения и увечье DC 2^(r−4). **DOT** — ✅ 2026-08-25: после крови на «Передать ход» тикают горение (`fire`, РУ 1) и каждое отравление; `dotTurnsLeft`; затухание яда fixed/dimensional. Чат: «получает N урона от [[огня]]» + [i]; на карточке сила горения и яд (тип/сила).
2. **Степпер истощения на карточке** — ✅ 2026-08-25: вместо `+/-` попап с `ClampedNumberField` (min 0, текущее) и «Изменить»; одно изменение значения (истощение/рана/кровь и прочие number).
3. **Актуализировать доки** — ✅ 2026-08-25: ТР и `docs/specs/` (check / state / combat-card) под код после полировки проверки.
4. **Коммит и пуш** доработок проверки/карточки — ✅ 2026-08-25 (укрытие, DOT, правка горения/яда).
5. **Канон фронта перед ревью** — ✅ 2026-08-25: `frontend-rules.md` (DAG, Utils, слои/нагрузка/XSS) + указатель в `AGENTS.md`.
6. **Фаза DAG** — выпрямить межмодульные импорты под канон (Rule не знает Space; Chat не знает Roleplay; формы типа урона в Rule). Внутримодульный рефактор — не здесь.
7. **Помодульное ревью** — Rule → Chat → Character → Game; все находки в один файл; затем оптимальный порядок правок.
8. **Глобальное ревью** — после списка правок, если останутся сквозные темы.
9. **Импорт/экспорт ревизии** — ✅ 2026-08-26: JSON-конверт `powerscale.revision`; экспорт опубликованной vN; импорт в черновик существующего (diff по `code`) или в новое пространство как **черновик** (первая публикация v1 после валидатора, без ревизии 0); переключатель удаления → маркер-версия в **новой** ревизии (ТР §1).
10. **Модуль уведомлений** — сейчас полузаглушка: отправка с кнопками, реакции, подвеска по модулям.
11. **Бэк** — когда начинать и как: после стабилизации фронтовых границ (после ревью модулей или параллельно с уведомлениями). Ожидать переделку API/моков.
12. **Приведение правил к реальному виду + полировка** — несколько этапов: заклинания; большая пачка Видов/Рас; навыки из заглушек (кривой импорт описания) в рабочее состояние. **A1 (2026-08-26):** хвост импорта в `name` не часть названия. **A2 зона/раздел (2026-08-27):** `spec.section`; основные боевые — `or` automatic, `core-rules`, без `common`; канон боя — `blizhniy-boy`. **Стабы (2026-08-27):** из каталога убраны демо-способности. **Основы внешность (2026-08-27):** статус Привлекательность (−3…+3, по умолчанию 0); черты внешности/голоса через `state_modify`; слух/зрение по 6 ступеней; умственная группа снята; без keyword «Часть группы». **Физразвитие (2026-08-27):** один навык, пул +9 (до +6 на хар-ку), лестница ОР 2/2/3/3/4/4, каждый 2-й пункт Силы → +1 Вес от тренировок; «Основы физического развития» сняты.
13. **Плейтест полумакета** — сборка мок-фронта на GitHub Pages (`/.github/workflows/pages.yml`, ветка `dev`; `VITE_BASE=/<repo>/`, SPA-fallback `404.html`). URL: `https://<owner>.github.io/<repo>/`. В Settings → Pages источник = GitHub Actions. Вход: мок `admin` / `test`. Изолированный снимок в браузере, без общего бэка. Собрать предложения/замечания.
14. **UX инициативы** — ✅ 2026-08-25: при открытии окна проверки на инициативу в выбранные попадают все approved-персонажи (лишних снять чипом); НПС — вручную.
15. **Автомодерация без изменений** — ✅ 2026-08-25: переход версии (миграция / pending), если `membershipDiff` пустой — принимать сразу, без вкладки модерации. Первая подача (`active === null`) — всегда вручную.
16. **Клик-атака** — ✅ 2026-08-23 клик по боевому профилю открывает окно атаки (handshake попадания): выбор действия-атаки (автоматические простые ББ/ДБ, ОД из `action_components`); реакции защиты 0/1/2 ОД; урон `(оружие − сопротивление) × РУ`; защита в сопротивление, если нет `defense_ignored`; чат из 4 сообщений; списание ОД и истощения/раны/оглушения на оверлей. Надёжность vs РУ — п.22. **Бросок/выстрел** — ✅ 2026-08-25: процедуры `throw-procedure` / `shoot-procedure`, сложность `[max(1, результат защиты)+укрытие]↓` + полосы дальнобойности (игнор = результат 0), дистанция и укрытие в оферте, сила действия −размер сверх reach, блок только щит, фланг (−2 защитнику) и поворот (+1 ОД). Навес/перезарядка — позже.
17. **Модерация только своей ревизии** — ✅ нельзя одобрить персонажа, чей лист не на ревизии игры; такие подачи автоотклонять.
18. **Переподача после отклонения** — ✅ rejected может править лист / перевести на ревизию игры и подать снова, не выходя из игры.
19. **Полировка чата игры** — ✅ 2026-08-25: сплит `[Проверка (n) | +]` (цифра — оферты, ждущие текущего спикера); «Игнорировать» только у совместной проверки. Успехи ≥ 0 (`foldNegativeBase`). Попадание/атака: перед РУ размер не мельче −1 (`clampMinSize` / `withSize`).
20. **ОД в сценах** — ✅ 2026-08-24: бросок инициативы стартует сессию (`playing`); у участников сцены ОД = лимит; «Передать ход» восстанавливает ОД уходящему; в шкале `N ОД` (info) рядом с кружком истощения; ОД/истощение/лист — владелец и ведущие.
21. **Естественное оружие** — ✅ 2026-08-25: руки/ноги — innate-предметы (`ruka`/`noga`) с вида через automatic-способность `humanoid-body` (grant `item` + `quantity`). Раса перекрывает тот же `item_code`. Врождённое экипировано, не покупается и не снимается. Владение семьи — реальный экземпляр `vladenie-oruzhiem` ур.1 `gifted`, бюджет 0; апгрейд платит сверх 1. НПС без расы/вида — без естественного оружия. Когти/пасть/скелет — не в этом заходе.
22. **Версионность НПС** — ✅ 2026-08-25: устаревший лист (`version.rulesRevision`/`spaceCode` ≠ игры) — чип «Требует перехода» + «Перевести»; тот же `CharacterMigrationService`; результат сразу в `npc.version` (`updateNpc`, без модерации). Пустой лист не переводят. Редактор листа не открывает устаревший remap.
23. **Надёжность vs РУ атаки** — ✅ 2026-08-24: набранные X РУ атаки игнорируют всё с надёжностью ≤ X без оплаты РУ и без диалога (хук фазы `attack`). Видимость сопротивления/расчёта удара по ролям — позже.
24. **Наборы защит доспехов** — ✅ 2026-08-25: оба слоя `source armor` (max, не сумма). Кожа/стёганый 3/6; кольчуга 6/3+3/6; чешуя 9/3+3/6; латы 12/3+3/6. Открытое лицо снято. Закрытый шлем: +1 надёжности всех слоёв и 1 помеха на проверки внимательности от предмета.
25. **Свёртка в чате** — атака / ход / раунд вложены. Сообщения несут `thread` (`id` + `parentId` + непрозрачный `kind`). Свёрнутая группа — одна строка-резюме; разворот атаки **вверх** (резюме снизу, панель на ширину). Ход и раунд — разделитель сверху. По умолчанию раскрыты текущий и предыдущий раунд/ход и идущая атака; завершённая атака сразу складывается. Сводка атаки: результат удара + сила/флаги увечья + исход воли. ✅ 2026-08-25.
26. **Инициатива: все игроки сразу** — ✅ 2026-08-25: см. п.14 — default selectedIds = approved-персонажи.
27. **Редактор правил: полный спектр + обязательные поля** — ✅ 2026-08-26: Save = `RuleValidationService.validateCatalog` (тот же набор, что публикация); спека immediate; source/sense/language/age в UI; characteristic/species/grants/weapon_family без потери полей.
28. **Деталки правил** — ✅ 2026-08-26: страница и слайдер через `RuleSpecView`; срез ревизии для подписей; все типы RULE_TYPES; дары/ops/предмет по значениям. Критерий: не заглядывать в редактор, чтобы понять правило.
29. **Слайдер: «перейти на страницу»** — дальняя очередь; опциональная кнопка под закрытием.
30. **Вертикаль шапок страниц** — очередь; страницы вроде деталки Game: кнопки в топбар, заголовок только в крошках, остальное уплотнить, чтобы шапка не ела высоту.

---

_Конец ТР._
