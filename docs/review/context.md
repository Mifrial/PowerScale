# Контекст ревью — PowerScale

Живой файл с результатами и историей ревью. Создан 2026-08-01 в рамках ревью ТР и фронта.

## Цели ревью (2026-08-01)

1. Внутренняя консистентность ТР (`docs/tr/TR.md`)
2. Противоречия ТР и кода — осознанные решения vs ошибки
3. Противоречия ТР, кода и спек (`docs/specs/`) — к чему привести

## Статус

- [x] Фаза 0: верификация фронта
- [x] Фаза 1: ревью ТР (внутренняя консистентность)
- [x] Фаза 2: сверка код ↔ ТР ↔ спеки
- [x] Фаза 3: ревью кода по чеклисту
- [x] Фаза 4: сводная таблица проблем
- [x] Фаза 5: обсуждение проблем по одной (F1 — решено)

### Ревью закрыто (2026-08-02)

**Таблица F1–F42 и внутренние противоречия ТР (TR-1…TR-8) — РЕШЕНО.** Правки ТР внесены.

Проверки на момент закрытия:
- `vue-tsc --noEmit` — чисто
- `vitest run` — **169/169** (16 файлов)
- `vite build` — главный чанк **415.03 kB / gzip 140.38**

**Отложенные крупные задачи (осознанно):**
- **Волны 4–5** — Персонажи (§7) и Игры (§8), стабы на фронте; полная реализация по волнам 4–5 §12 (context.md F10).
- **F16–F18** — публикация подмножества правил галочками: коммит только выбранных, неотмеченные остаются в черновике (ТР §5, work_item F16-F18).
- **F8** — стратегия тестирования: вернуться при бэкенд-контракте или перед заморозкой release 1.
- **F25** — per-object права (`space.*`/`game.*`/`rule.*`) — на бэкенд-фазу (ТР §«Решения» №17).

## Фаза 0: Верификация (2026-08-01)

- [x] Типы: `vue-tsc --noEmit` — чисто, без ошибок. Известная ошибка PermissionMatrix.vue не воспроизводится (исправлена).
- [x] Тесты: `vitest run` — 3 файла, 60/60 passed.
- [x] Сборка: `vite build` — ок.
- **Замечание (производительность, MEDIUM)**: главный чанк `index-*.js` 753 KB (gzip 241 KB) — превышает 500 KB лимит Vite. Кандидат на code-splitting / manualChunks.

## Фаза 1: Ревью ТР — внутренняя консистентность (2026-08-01)

### Внутренние противоречия ТР — ВСЕ РЕШЕНЫ (2026-08-02)

**TR-1 (HIGH) — Типы чатов: §3 и §9 vs память о коде.**
- §3 БД `chats.type`: `private | group | game`
- §9 «Типы чатов»: только `private`, `group`, `game`
- В знаниевом графе зафиксировано 5 типов: `private | group | game | game_discussion | character_discussion`
- Сверить с кодом (`Config/chatType.ts`) и решить, какой вариант истинный. Кандидат на сверку в Фазе 2.
- **РЕШЕНО 2026-08-02:** истинно 5 типов (код), §3 `chats.type` и §9 «Типы чатов» актуализированы (добавлены `game_discussion`, `character_discussion`); «Новые типы — без миграции схемы» сохранено.

**TR-2 (MEDIUM) — Версия правил: «A.B.C.x у пространства» vs «A.B.C у правила + revision у пространства».**
- §1: «Пространство имеет версию правил в формате A.B.C.x (x — автоинкремент при каждой публикации)»
- §12 Волна 3 (шаги 5, 7): «версионирование A.B.C.x», «автоинкремент x при публикации»
- §5.1: «Версия A.B.C — свойство каждого правила, а не пространства», «Пространство имеет счётчик ревизий (revision)»
- Сводка 30.1: «Версия A.B.C — свойство правила, revision (x) — свойство пространства»
- §1 и §12 (Волна 3) устарели относительно §5.1 и Сводки 30.1.
- **РЕШЕНО 2026-08-02:** §1 (строка «Пространство» → revision) и §12 (2007/2554/2595) приведены к 30.1.

**TR-3 (MEDIUM) — «Очки» vs «валюта» в одном разделе §6.**
- §6 «Очки» (строка определения): «ОС/ОЛ/ОР … — „валюта“ создания персонажа»
- §6 «Очки» (строка «Термин»): «ОС/ОЛ/ОР — „очки“, не „валюта“; „валюта/деньги“ — только монеты»
- Сводка 30.48: «ОС/ОЛ/ОР — „очки“, а не „валюта“»
- Прямое противоречие внутри одного подраздела.
- **РЕШЕНО 2026-08-02:** формулировки §6 («Очки» в таблице типов и в подразделе) приведены к 30.48 — «очки», не «валюта».

**TR-4 (MEDIUM) — «Эффект»: тип правила vs «отложен».**
- §6 таблица типов: «Эффект | Runtime-статус с уровнями…» — без пометки «отложен»
- §3 БД `rules.type`: `simple | race | species | characteristic | resource | points | ability | item | damage_type` — `effect` нет
- Сводка 23: «Эффекты — отложены»; Отложенное #4: «Эффекты (runtime-статусы) — тип правила, требует детальной проработки»
- §6 таблица должна пометить тип как отложенный либо убрать.
- **РЕШЕНО 2026-08-02:** §6 тип «Эффект» помечен «Отложен (Отложенное п.4)».

**TR-5 (MEDIUM) — `chat_messages.updated_at` требует §9, но отсутствует в §3.**
- §9 (SSE sync): «Тип chat_messages должен содержать поле updated_at», индекс `(chat_id, updated_at)`
- §3 БД `chat_messages`: только `id, chat_id, user_id, content, dice_result, created_at`, индекс `(chat_id, created_at DESC)`
- Схема §3 не содержит ни поля `updated_at`, ни sync-индекса, который требует §9.
- **РЕШЕНО 2026-08-02:** в §3 добавлены `chats.updated_at`, `chat_messages.updated_at` и индекс `(chat_id, updated_at)`.

**TR-6 (MEDIUM) — Типы правил в §12 Волна 3 устарели.**
- §12 Волна 3 (Roleplay/Rule): «spec_json (типы: race, stat, ability, currency, item, simple)», hydrator'ы включают `CurrencyHydrator`
- Актуальные типы (§3/§6): simple, race, species, characteristic, resource, points, ability, item, damage_type. `currency` больше нет (30.48), `stat` → `characteristic`, добавлены species/resource/points/damage_type.
- **РЕШЕНО 2026-08-02:** §12 Волна 3 приведён к актуальным типам и гидрейторам (CurrencyHydrator удалён, StatHydrator → CharacteristicHydrator, добавлены Species/Resource/Points/DamageType/Simple).

**TR-7 (MEDIUM) — `character_moderation` упоминается в §12, отсутствует в схеме БД §3.**
- §12 Волна 4 (шаг 9): «в таблице `character_moderation` хранится chat_message_id (FK на chat_messages.id, nullable)»
- §3 БД: таблицы `character_moderation` нет.
- **РЕШЕНО 2026-08-02:** стаб-таблица `character_moderation` добавлена в §3 с пометкой «волна 4»; §12 ссылается на §3.

**TR-8 (MEDIUM) — Структура фронта в §2 и §12 Волна 1 устарела.**
- §2 и §12 Волна 1 показывают layout `pages/auth/*.vue`, `components/common/PasswordField.vue`, `pages/`; модули Roleplay (Rule/Space/Character/Game) и Dashboard не указаны
- Фактически (код): `modules/*/Page/*.vue`, `modules/Core/UI/Components/Input/PasswordField.vue`, модули Roleplay/*, Dashboard
- Не противоречие логики, но документация отстала от кода.
- **РЕШЕНО 2026-08-02:** в §2 добавлена конвенция страниц `modules/<Module>/Page/*.vue`; §12 шаг 9 → `modules/Core/Auth/Page/`.

### Замеченное, но не противоречие (проверить в Фазе 2)
- `characters.status` §3 (`draft | ready | moderation | needs_fix`) vs §7.6 («Черновик/Готов/на модерации/требует исправления») — согласуются.
- Гостевые ограничения чатов (§11 «не может писать в чаты»; в памяти — фильтр private для гостя) — в ТР деталей нет.

## Фаза 2+3: Сверка код↔ТР↔спеки и ревью кода (2026-08-01)

Код сверялся тремя агентами (Roleplay / Messages+Core / Shell+UI) против ТР, спек и чеклиста. Ключевые утверждения проверены вручную (guest-guard, vuetify wildcard, типы чатов, SSE, DimensionalNumber, конфликт ID моков).

## Фаза 4: Сводная таблица проблем

| # | Проблема | Приоритет | Класс | Модуль |
|---|----------|-----------|-------|--------|
| F1 | ~~Нет guest/admin-guards: гость (userId=0) проходит как авторизованный → полный доступ к `/admin/*`, `/users`, `/characters`, `/games`. Админ-проверки нет (мета `requiresAdmin` не используется). `router/index.ts:21-41`, `auth.ts:18-19`~~ **РЕШЕНО 2026-08-01** | CRITICAL | код↔ТР ошибка | Router/Auth |
| F2 | ~~Нет guards на приватные роуты в целом~~ **РЕШЕНО 2026-08-01** — закрыто реализацией F1 (requiresAny/requiresAll на /users*, /admin/*, /spaces/new) | CRITICAL | код↔ТР ошибка | Router |
| F3 | ~~SSE-sync из §9 не реализован — фактически polling каждые 5 c (`ChatSyncService.startSSE` — мёртвая ветка)~~ **РЕШЕНО 2026-08-01** — явный `mode: 'poll' | 'sse'`; poll — временный (mock без бэка), SSE — целевой транспорт бэка; ТР §9 актуализирован | HIGH | код↔ТР ошибка | Chat |
| F4 | ~~Конфликт ID моков: `mockRules` rule-9/10/11 = Мышление/Интеллект/Рубящий, `mockSpaces` rule-9/10/11 = Меч/Лук/Доспех. Fallback `fetchRule` вернёт не то правило~~ **РЕШЕНО 2026-08-01** — единый каталог + code на правиле | HIGH | проблема кода | Rule/Space |
| F5 | ~~Vuetify: `import * as components/directives` в `plugins/vuetify.ts` отключает tree-shaking при активном `autoImport:true` в vite.config → главный чанк 753 KB~~ **РЕШЕНО 2026-08-01** — главный чанк 455 KB (gzip 153), предупреждение исчезло | HIGH | проблема кода | Shell/Build |
| F6 | ~~Нет страницы 404 (catch-all `/:pathMatch(.*)*` отсутствует)~~ **РЕШЕНО 2026-08-01** — единая 404/403: catch-all → `/404`, запрет (гость вне guestAllowed / провал requiresAny/requiresAll) → `/404` вместо Dashboard | HIGH | код↔ТР ошибка | Router |
| F7 | ~~Отсутствуют роуты деактивации из §11: `/users/:id/deactivate`, `/admin/groups/:id/deactivate`, `/admin/tags/:id/delete`, `/space/:code/deactivate`, `/games/:id/deactivate`, `/characters/:id/deactivate`~~ **РЕШЕНО 2026-08-01** — единый паттерн «инлайн-диалог» (роуты не создаём); добавлен UI для тегов и пространств; ТР §11 актуализирован | HIGH | код↔ТР ошибка | Router/User/Tag/Space |
| F8 | ~~Тестов критически мало: 3 файла на ~220 файлов. Не покрыты guards (при F1/F2 — критично), stores, Shell, SmartGrid, UI~~ **ОТЛОЖЕНО 2026-08-01** — мок-эра: широкое покрытие преждевременно (churn при рефакторинге, закрепление мок-допущений); основная верификация — headless-скрипты. Добавлен только мелкий тест на access-контракт (access.test.ts, +16 кейсов). Вернуться: при стабилизации бэкенд-контракта или перед заморозкой release 1 | HIGH | проблема кода | Тесты |
| F9 | ~~Гонка в `openChat`: `Promise.all` без проверки активного чата; быстрый switch A→B может перезаписать сообщения B сообщениями A. AbortController к чатам не применён~~ **РЕШЕНО 2026-08-01** — per-chat state: `Map<chatId, ChatState>` (messages/hasMore/total/loading/loadingOlder); гонка исчезла по построению; sync сохраняет сообщения для всех чатов из response (не только activeChatId); возврат к открытому чату мгновенный (кеш) | HIGH | проблема кода | Chat |
| F10 | ~~Модули Character (§7) и Game (§8) — стабы «будет реализовано в следующих волнах»~~ **РЕШЕНО 2026-08-01** — осознанное расхождение зафиксировано в ТР: маркеры статуса фронта в §7/§8 + строка-статус в §12; meta `/characters` выровнен (guestAllowed для заглушки, снятие при волне 4); код-стабы не расширяются | MEDIUM | код↔ТР осознанное (волны 4-5) | Character/Game |
| F11 | ~~Типы чатов: в коде 5 (`private/group/game/game_discussion/character_discussion`), в ТР §9/§3 — 3. Осознанное расширение, ТР не актуализирован~~ **РЕШЕНО 2026-08-02** — §3 `chats.type` и §9 «Типы чатов» актуализированы (добавлены `game_discussion`, `character_discussion`) | MEDIUM | код↔ТР осознанное | Chat/ТР |
| F12 | ~~User-тип не содержит `deactivated_until`/`deactivate_reason`; `mockDeactivateUser` игнорирует reason/date; профиль не показывает причину (частично расходится с §13 #6)~~ **РЕШЕНО 2026-08-01** — поля добавлены в User, mock и store сохраняют reason/date, профиль показывает причину/дату у отключённого; тест mockUsers.test.ts (+2) | MEDIUM | код↔ТР ошибка | User |
| F13 | ~~`ResetPasswordPage` — новый пароль без password policy (§13 #4)~~ **РЕШЕНО 2026-08-02** — политика подключена по образцу RegisterPage (`validatePassword` + `auth.fetchPasswordPolicy` на mount, `:rules` на оба поля); ТР §13 #4 актуализирован; тест `validatePassword.test.ts` (+8) | MEDIUM | проблема кода | Auth |
| F14 | ~~Шаблоны уведомлений: нет роута/UI `/admin/notification-templates/:id/delete` (§10)~~ **РЕШЕНО 2026-08-02** — soft-delete по паттерну тегов: `active`-флаг добавлен в тип/mock/store, `deleteTemplate` → `deactivateTemplate` (в т.ч. engine-action `notificationTemplate.deactivate`), кнопка «Удалить» + confirm-диалог на TemplateEditPage (`notification_template.delete`), колонка «Статус» + фильтр на TemplatesListPage; ТР §10 актуализирован (отдельной страницы нет), схема += `active BOOL` | MEDIUM | код↔ТР ошибка | Notifications |
| F15 | ~~`itemTypes.ts` импортирует типы из `.vue`-компонентов (`DimensionalNumberValue` из DimensionalNumberInput.vue, `Formula` из FormulaInput.vue) — инверсия Interface→Components~~ **РЕШЕНО 2026-08-02** — тип `DimensionalNumberValue` перенесён в `Core/Engine/Type/DimensionalNumber.ts`, `Formula` — в `Interface/abilityTypes.ts`; импорты интерфейсов в `raceTypes`/`itemTypes`/`abilityTypes` и всех редакторах переведены на Interface/Core; у `DimensionalNumber.vue`/`DimensionalNumberInput.vue` убран локальный интерфейс | MEDIUM | проблема кода | Rule |
| F16 | ~~`ResourceSpec` объявлен внутри `ResourceEditor.vue`, а не в Interface-слое~~ **РЕШЕНО 2026-08-02** — перенесён в `Interface/resourceTypes.ts` (+ фабрика `createEmptyResourceSpec`, паттерн raceTypes) | MEDIUM | проблема кода | Rule |
| F17 | ~~Нет loading/error/empty на SpaceDetailPage/RuleDetailPage/RuleEditPage (пустой экран при ошибке)~~ **РЕШЕНО 2026-08-02** — паттерн UserProfilePage на всех трёх страницах: `loading` (v-progress-circular) + инлайн-ошибка с «Попробовать снова» (сброс loaded-ключа и повторный resolveRoute); редиректы на `/space/:code` при ошибке заменены инлайн-ошибкой (навигация «неверный ctx» сохранена); AbortError — тихий выход | MEDIUM | проблема кода | Space/Rule |
| F18 | ~~Публикация: номер новой ревизии = `space.revision + 1` локально, а не из возвращённого `SpaceRevision`; диалог публикации не реализует блоки diff §5 (изменённые/новые/возвращённые)~~ **РЕШЕНО 2026-08-02** — ревизия берётся из ответа `commitDraft` (store уже кеширует и выставляет контекст), навигация по `result.revision`; добавлен try/catch (snackbar ошибки); диалог — блоки Изменённые/Новые (классификатор `ruleDiff.classifyDraftDiff`) + Проблемные (не прошли фронт-валидацию, `groupProblems` по ruleCode + spaceErrors для цикла видов), кнопка неактивна при проблемах; отдельный диалог «Невозможно опубликовать» удалён; ТР §5 актуализирован (блок «Возвращённые» заменён на «Проблемные», нота про фронт-валидацию до коммита); тест `ruleDiff.test.ts` (+6) | MEDIUM | код↔ТР ошибка | Space |
| F19 | ~~`DimensionalNumber` не соответствует §6: рендер `3↑2` без tooltip и без степени `↑²`; класса с `toNumber()/modify()/toString()` нет (grep — пусто)~~ **РЕШЕНО 2026-08-02** — класс `DimensionalNumber` (toNumber/modify с range/add/subtract/toString) в `Core/Engine/Type/DimensionalNumber.ts`; `CHARACTERISTIC_BASE_RANGE` в Rule-слое; типы по всем слоям переведены на `DimensionalNumberValue`; тест `DimensionalNumber.test.ts` (+17, итого 132/132); ТР §6 актуализирован; визуал компонента сознательно не менялся (осознанное расхождение с формулировкой зафиксировано в ТР) | MEDIUM | код↔ТР ошибка | Engine |
| F20 | ~~Хардкод цветов (`FilterBar #FFFFFF`, `rgb(128,128,128,0.4)`, `SmartGrid #fff`) и 7 `!important` — нарушение стилевых правил §2~~ **РЕШЕНО 2026-08-02** — все хардкоды заменены на CSS-переменные темы (`rgb(var(--v-theme-surface))`, `rgba(var(--v-border-color), …)`, `rgba(var(--v-theme-scrim), var(--v-shadow-*))`), `!important` убраны заменой селекторов на более специфичные (`.smart-grid .smart-header-th--settings` и т.п.); grep по UI — чисто | MEDIUM | код↔ТР ошибка | UI |
| F21 | §11 «Общий лейаут»: нет топ-меню в топбаре, нет футера, нет guest-кнопок «Войти/Регистрация» в топбаре — **ОТКЛОНЕНО 2026-08-02** (решение юзера): топ-меню в топбаре и shell-футер не делаем; было реализовано и откачено целиком (меню+guest-кнопки+футер). ТР §11 помечен | MEDIUM | код↔ТР расхождение | Shell |
| F22 | ~~SmartGrid: нет виртуализации/v-memo для больших списков (items-per-page="-1")~~ **РЕШЕНО 2026-08-02** — осознанное решение: списки пагинированы родителем (`pageRows`, ≤100), виртуализация преждевременна; ТР §«Решения» №16 | MEDIUM | проблема кода | UI |
| F23 | ~~`markChatRead` вызывается безусловно в openChat/sendMessage (расхождение с §13 #8 — оптимизация только в applySyncResponse)~~ **РЕШЕНО 2026-08-01** — введена позиция прочтения `last_read_message_id` (chat_members): unreadCount вычисляется из неё, `lastReadMessageId` в пакете sync, разделитель «Новые сообщения» в чате; ТР §13 #8 переформулирован (guard только в sync-пути, openChat/sendMessage безусловно); тип/mock/store/разделитель реализованы; тесты +3 (107/107) | MEDIUM | проблема кода | Chat |
| F24 | ~~Проверка прав по имени группы `groups.includes('Администратор')` вместо permission-ключей (SideBar, UserProfilePage)~~ **РЕШЕНО 2026-08-01** — через F1: компоненты переведены на `access.ts` (см. «Решено: гостевой/админ-guard») | MEDIUM | проблема кода | User/Shell |
| F25 | ~~`PERMISSION_KEYS` (PermissionMatrix) не содержит per-object ключей (`space.*`, `game.*`, `rule.*`) — через UI нельзя назначить per-object права~~ **РЕШЕНО 2026-08-02** — осознанное решение: per-object права на бэкенд-фазу, через UI групп назначаются только глобальные ключи; ТР §«Решения» №17 (отмечено в §4) | MEDIUM | проблема кода | User |
| F26 | ~~Badge уведомлений = 0 при входе не на Dashboard (fetchData грузится только на Dashboard/открытии слайдера)~~ **РЕШЕНО 2026-08-02** — `notificationStore.fetchData()` вызывается в `Shell.vue` onMounted (рядом с fetchChats): badge актуален при входе на любую страницу | MEDIUM | проблема кода | Notifications |
| F27 | ~~Дублирование сортировка/пагинация/фильтры в 4 страницах (напрашивается composable)~~ **РЕШЕНО 2026-08-02** — composable `useGridPage` (Core/Composables) применён в UsersListPage/GroupsListPage/TagsListPage/TemplatesListPage; дублированный блок удалён, `onFilterChange` каждой страницы лишь маппит фильтры в store | MEDIUM | проблема кода | UI |
| F28 | ~~Eager-импорт mock+real всех API в `main.ts` — обе ветки в бандле~~ **РЕШЕНО 2026-08-02** — main.ts переведён на async `bootstrap()`: ветки mock/real через динамические `import()`, статично остались только `register*`/init/renderers; главный чанк **456 → 415 KB** (gzip 153 → 140), mock/real API — отдельные ленивые чанки | MEDIUM | проблема кода | Shell/Build |
| F29 | ~~Макросы (§9, `user_macros`) не реализованы вовсе; текстовая команда `/roll Nd6[...]` не парсится (есть форма-замена DiceRollForm); `dieSize` не участвует в подсчёте успехов~~ **РЕШЕНО 2026-08-02** — команда `/roll` (парсер в `Core/Utils/rollParser.ts`), макросы `user_macros` (тип `UserMacro`, API CRUD, `useMacrosStore`, `MacrosSection` в профиле, чипсы в `ChatInput`); механика: убран модификатор, инвертированные adv/dis исправлены (преимущество срезает худшие/6-ки, помеха — лучшие/1-ки), снятые кубы видны (`droppedRolls`), размерность — суффикс в итоге («4↑ успехов») | MEDIUM | код↔ТР ошибка | Chat |
| F30 | ~~Текст §6 ТР устарел относительно осознанных решений 30.31/30.33/30.43: дары без `value`/`limit`, `has_tag` c `min_count?`, `characteristic_value.min: number`, `AbilitySpec.type?`, `subtypes: string[]`~~ **РЕШЕНО 2026-08-02** — §6 актуализирован: `has_tag` без `min_count`, `characteristic_value.min: DimensionalNumber`, дары += `value`/`limit`/`source_id`, добавлена нота о двухслойной модели Draft/Clean + prune (30.43) | LOW | ТР↔спек (осознанное) | ТР |
| F31 | ~~Прямые импорты механик (`fetchMechanics`) в компоненты вместо DI/IMechanicsApi~~ **РЕШЕНО 2026-08-02** — тип `Mechanic` в `Interface/types.ts`, `IRuleApi += getMechanics`, реализации (mock → `fetchMechanics`, real → `rule.getMechanics`), страницы через `getRuleApi()` | LOW | проблема кода | Rule |
| F32 | ~~Мёртвый код: UserCell renderer, UserList.vue, fetchMechanic, canKick/meStatus, getChatMockMembers~~ **РЕШЕНО 2026-08-02** — удалены компоненты/функции/экспорты; SSE-ветка и `publicRoutes` без изменений (осознанно / уже удалён) | LOW | проблема кода | несколько |
| F33 | ~~Tag использует `code` вместо `string_id` (§3 БД) — переименование без записи в ТР~~ **РЕШЕНО 2026-08-02** — ТР §3/§6/§11/§12 переведены на `code` (совпадает с кодом) | LOW | код↔ТР осознанное | Tag |
| F34 | ~~`storeToRefs` не используется на страницах (конвенция из чеклиста)~~ **РЕШЕНО 2026-08-02** — принята конвенция `store.xxx` (шаблоны/computed); `storeToRefs` — только для деструктуризации state в setup; зафиксировано в frontend-rules.md §3 | LOW | проблема кода | UI |
| F35 | ~~Vuetify-2 пропсы `outlined`/`dense` в CharacteristicEditor (Vuetify 3 — `variant`/`density`)~~ **РЕШЕНО 2026-08-02** — заменены в 3 местах | LOW | проблема кода | Rule |
| F36 | ~~AbilityEditor эмитит `JSON.parse(JSON.stringify(...))` на каждый deep-тик~~ **РЕШЕНО 2026-08-02** — `structuredClone()` (в т.ч. во всех редакторах: Race/Species/Item/Requirement*/Spell/Process/Grant) по frontend-rules.md; AbilityEditor — приведение к `AbilitySpec` при эмите | LOW | проблема кода | Rule |
| F37 | ~~`authChecked` кэшируется навсегда (ошибка сети на старте → сессия не перепроверяется)~~ **РЕШЕНО 2026-08-02** — guard кэширует только успех: `authChecked = await auth.checkAuth()`; сетевая ошибка → повторная проверка при следующей навигации | LOW | проблема кода | Router |
| F38 | ~~SpaceNewPage «наследовать от» копирует только `rulesCount`, снепшот не копируется (ограничение мока)~~ **РЕШЕНО 2026-08-02** (решение юзера — реальное копирование) — `mockSpaces.createSpace`: при `inheritFrom` генерятся правила родительской ревизии, снепшот кладётся в `revisionCache` нового пространства (`:0`), `rulesCount` = длина снепшота | LOW | проблема кода | Space |
| F39 | ~~vitest include только `*.test.ts` (файлы `*.spec.ts` не подхватятся)~~ **РЕШЕНО 2026-08-02** — `include: ['src/**/*.{test,spec}.ts']` | LOW | проблема кода | Тесты |
| F40 | ~~Фильтр private-чатов для гостя — по `auth.isGuest`, а не через ролевую модель (в памяти — «через ролевую модель»)~~ **РЕШЕНО 2026-08-02** — формулировка в ТР §9/§11: гость видит только публичные чаты, private скрыты; фронт — `auth.isGuest`, на бэке — ролевая модель/права | LOW | код↔ТР осознанное | Chat |
| F42 | ~~`draftRuleStore` in-memory (Pinia), теряется при F5~~ **РЕШЕНО 2026-08-02** (решение юзера — localStorage) — сериализация в `powerscale.drafts.v1`, восстановление на старте, очистка при discard/commit; тест +4 | LOW | наблюдение | Rule |

### Классификация по типам
- **CRITICAL**: 2 (guards)
- **HIGH**: 7
- **MEDIUM**: 21
- **LOW**: 10
- Ключевая проблема: **внутренняя безопасность роутинга (F1/F2)** — осознанного решения не было, это ошибка.
- Главный паттерн «код↔ТР осознанное, ТР не актуализирован»: типы чатов (F11), тексты §6 (F30), Tag `code` (F33), стабы волн (F10). Правки ТР внесены (2026-08-02).
- Главные «код↔ТР ошибки» (требуют правок кода или явного решения): F3, F12, F14, F18, F19.

## Фаза 5: Обсуждение проблем (2026-08-01)

### F1 — Решено: гостевой/админ-guard (CRITICAL)

**Принятое архитектурное решение (согласовано в обсуждении):**
- Доступ к страницам = наличие permission-ключа. БД-права + middleware — защита на бэке (ТР §4), фронт-guard — UX-слой, читающий права из бэка.
- Семантика: `requiresAny` (OR) + `requiresAll` (AND), `super_admin` — bypass. Рекурсивный `access{logic, keys}` отклонён (YAGNI, одна точка проверки позволит добавить позже).
- Маппинг «роут → ключ» — в `route.meta` (статичная структура UI), не в БД.
- Контракт с бэком: `User.permissions: string[]` (мёрж прав из групп), заполняется в mockAuth как отдал бы реальный бэк.

**Реализация:**
- `src/router/meta.ts` — `guestAllowed?`, `requiresAny?`, `requiresAll?` в RouteMeta.
- `src/router/access.ts` (новый) — чистая функция `evaluateRouteAccess(to, ctx)`: auth-layout → гость → guestAllowed → requiresAll → requiresAny → «свой vs чужой» (владелец профиля всегда).
- `src/router/index.ts` — guard на `evaluateRouteAccess` (убран мёртвый `publicRoutes`).
- `src/modules/Core/User/Utils/access.ts` (новый) — `hasAnyPermission`/`hasAllPermissions`/`isAdmin`, единая точка для guard и компонентов.
- `src/modules/Core/User/Service/groupPermissions.ts` (новый) — единый справочник «группа → ключи» (АДМИНИСТРАТОРЫ=все, ИГРОК=user.view+character.create, ВЕДУЩИЙ=+character.view+space.view_all+rule.view); `resolvePermissions`.
- `User.permissions?: string[]` добавлен в тип.
- mockAuth/mockUsers — канонические имена групп, `super_admin`, permissions через `resolvePermissions`.
- Роуты: guestAllowed = Dashboard/Games/Spaces/Messenger; requiresAny = user.* (view/create/edit), user_group.*, tag.*, notification_template.*, space.create, админ-индекс.
- Компоненты (SideBar, UserProfilePage, usePermissions) переведены на `access.ts` — закрыта **F24**.
- Тест `src/router/access.test.ts` — 15 кейсов (auth-layout, гость, OR/AND, super_admin, свой/чужой).

**Проверки:** vue-tsc — чисто, vitest — 75/75 (было 60), vite build — ок.

**F41 (новая) — Имена групп в моках расходились** (LOW, код↔ТР ошибка): mockUsers/mockAuth использовали «Администратор», mockGroups — «Администраторы/Игроки/Модераторы»; права в компонентах проверялись по имени «Администратор». **Решено**: канонические имена из ТР §4 (Администраторы/Игрок/Ведущий), единый справочник `groupPermissions.ts`.

### Открытые вопросы после F1
- Сообщение гостя в мессенджере: messenger помечен `guestAllowed` (гость читает публичные чаты, писать не может — usePermissions). Если по ТР гость не должен открывать мессенджер — убрать флаг. Требует уточнения §9/§11.
- `Guest`-сессия полностью клиентская (userId=0 без API) — при появлении бэка нужен `auth.guest` на сервере (потенциальная проблема, отмечена в C8).

### F3 — Решено: транспорт синхронизации чатов (HIGH)

**Решение:** poll нужен только пока нет бэка; SSE — целевой протокол реального бэкенда (ТР §9).

**Реализация:**
- `ChatSyncService.mode: 'poll' | 'sse'` (дефолт `poll`) вместо хака «есть getSyncApi → polling». SSE-ветка чистая и достижимая через конфиг — при появлении SSE-бэкенда переключение без переписывания.
- ТР §9 «Real-time синхронизация чатов (SSE)» — добавлен статус транспорта: SSE — целевой протокол бэка; пока бэка нет — polling 5 с; режим выбирается конфигом.
- Тест `chatSyncService.test.ts` — 3 кейса (poll по умолчанию, SSE-URL + событие, sse не дёргает poll).

**Проверки:** vue-tsc чисто, vitest 78/78, vite build ок.

### F4 — Решено: единый каталог правил + code на правиле (HIGH)

**Принятые решения (согласовано в обсуждении):**
- `code` — глобальный семантический ключ **правила** (`rules.code UNIQUE`), общий для всех версий и пространств; `RuleVersion` его не несёт. Задаётся при создании, **не изменяется** после.
- Правила лежат в **одном месте** — каталог как единственный источник для пространств и ревизий.

**Реализация:**
- `mockRules.ts`: `export const ruleCatalog = rules`; добавлены 4 правила из пула ревизий (`longbow`, `plate-armor`, `movement`/process, `fire-bolt`/spell — rule-31..34); rule-4/rule-21 обогащены (`type: 'action'`/`'skill'`, теги) из более свежих версий пула.
- `mockSpaces.ts`: `revisionRulePool = ruleCatalog` — удалён хардкод (~180 строк). Конфликт ID невозможен по построению; fallback `fetchRule` и срез ревизий смотрят на один источник.
- `RuleVersion` убрано поле `code` (не читалось); `UpdateRuleData` без `code`; `mockRules.updateRule` код не меняет.
- Редактор: поле «Код» **disabled при редактировании** (`:code-disabled="isEdit"` через RuleEditorBase → SimpleRuleEditor, 8 редакторов); `buildRule` для правки сохраняет исходный `code` (`loadedCode`).
- ТР §3 `rules.code VARCHAR UNIQUE NOT NULL`; спека §1 и ТР 30.17 переформулированы; зафиксирована неизменяемость code.
- Тест `ruleCatalog.test.ts` (id/code уникальны, все типы, process/spell спеки).

**Проверки:** vue-tsc чисто, vitest 82/82, build ок, headless: fallback `rule-9`=Мышление и `rule-31`=Длинный лук согласованы с каталогом; при правке поле Код disabled+`sword`; сохранение → карточка черновика с Меч (SPA); ревизии рендерятся.

**F42 (наблюдение, вне F4) — `draftRuleStore` in-memory (Pinia), теряется при полной перезагрузке (F5).** Существующее поведение: черновик живёт в памяти до коммита (как и задумано в §5.4 «на клиенте»), но F5 его стирает. Вопрос персистентности черновика (localStorage/sessionStorage) — отдельное решение.

### F5 — Решено: tree-shaking Vuetify (HIGH)

**Причина:** `plugins/vuetify.ts` импортировал `* as components/directives` и регистрировал все компоненты глобально → rollup не мог выкинуть неиспользуемые. При этом `vite.config.ts` уже включал `vite-plugin-vuetify({ autoImport: true })` — две стратегии конфликтовали.

**Реализация:**
- `plugins/vuetify.ts`: убраны wildcard-импорты и `components, directives,` из `createVuetify`. Locale/icons/theme/defaults сохранены.
- `vite.config.ts`: `vue({ template: { transformAssetUrls } })` (по официальному гайду).
- Проверено: `<component :is>` в SmartGrid/FilterBar используют наши компоненты (registry), не глобальные; экзотических директив (v-ripple/v-click-outside и т.п.) в шаблонах нет.

**Результат:** главный чанк **753 KB → 455 KB** (gzip 241 → 153), предупреждение Vite >500 KB исчезло; Vuetify-компоненты код-сплитятся по страницам (VRow/VAlert/VPagination — отдельные ленивые чанки).

**Проверки:** vue-tsc чисто, vitest 82/82, build ок, headless 7/7 (страницы рендерятся, нет `Failed to resolve component/directive`).

### F6 — Решено: единая 404/403 (HIGH)

**Причина:** catch-all `/:pathMatch(.*)*` отсутствовал — неизвестный URL матчился на корневой `/` (Shell) без дочернего роута → пустой контент; ТЗ требует «единую 404/403 без раскрытия причин» (tz-context #40, firstAiTZ 2053/2309).

**Принятые решения (в обсуждении):**
- Catch-all **редиректит** на канонический `/404` (а не рендерит на месте) — единая страница для 404 и 403, разницу между «нет пути» и «нет прав» не видно.
- **403 унифицирован**: гость вне `guestAllowed`, провал `requiresAny`/`requiresAll` → `NotFound` (было Dashboard). Гость тоже получает 404 (у гостя есть сессия — это не «не авторизован»).
- Не менялись: неавторизованный → Login (стандартная воронка), auth-layout → Dashboard («уже вошёл»), «свой vs чужой».

**Реализация:**
- `modules/Core/NotFound/Page/NotFoundPage.vue` (новый) — иконка `mdi-compass-off-outline`, «Страница не найдена», кнопки «На главную» и «Назад» (fallback на Dashboard).
- `moduleRoutes.ts`: `/404` (name `NotFound`) и catch-all `/:pathMatch(.*)*` (name `NotFoundCatchAll`, `redirect: '/404'`), оба `guestAllowed: true` — иначе гость падал бы на Dashboard до 404. Редирект строкой, а не по имени — без warn про `pathMatch`.
- `access.ts`: три литерала редиректа Dashboard → `NotFound`; докстринг актуализирован.

**Проверки:** vue-tsc чисто, vitest **84/84** (7 файлов; access.test — 4 ассерта на NotFound, новый moduleRoutes.test — 2 кейса), build ок (главный чанк 455 KB), headless **11/11** (авториз. `/nonexistent` → /404; гость: вход→Dashboard→/characters→/404→«На главную»; неавториз. → Login).

**Наблюдение (вне F6):** `/admin` без дочернего пути матчится на родителя без компонента → пустой Shell (та же болезнь, что и у F6). Предложить редирект `/admin` → `/admin/users` или 404-редирект отдельной задачей.

### F7 — Решено: деактивация единым паттерном «инлайн-диалог» (HIGH)

**Причина (уточнена):** ТР §11 описывал отдельные роуты `/users/:id/deactivate`, `/admin/groups/:id/deactivate`, `/admin/tags/:id/delete`, `/space/:code/deactivate`, `/games/:id/deactivate`, `/characters/:id/deactivate`. Фактически деактивация уже была реализована **инлайн-диалогами** (user — в UserProfilePage с причиной/датой, group — в GroupDetailPage); для тегов и пространств API+store были, а UI — нет; игры/персонажи — стабы (F10).

**Решение (в обсуждении, вариант A):** единый паттерн **«кнопка + диалог подтверждения на странице сущности»** (то, что сам ТР предписывал для игр/пространств/персонажей). Отдельные роуты не создаются. ТР §11 актуализирован.

**Реализация:**
- `TagEditPage.vue`: кнопка «Удалить» (видна при edit + `tag.delete` + `active`), confirm-диалог → `store.deactivateTag` → `/admin/tags`.
- `SpaceSettingsPage.vue`: кнопка «Деактивировать» (по `space.edit_all` — в модели ключей нет `space.edit`), confirm-диалог → `store.deactivateSpace` → `/spaces`.
- ТР §11: строки deactivate отмечены «не отдельная страница — кнопка+диалог», добавлена нота «Единый паттерн деактивации».
- User/Group — без изменений (уже соответствуют). Game/Character — не трогаем (F10).

**Наблюдение (к F25, per-object ключи):** ТР §11 пишет `space.edit` (Per-space), в PERMISSION_KEYS только `space.edit_all`/`space.view_all`/`space.create`. Кнопка деактивации пространства повешена на `space.edit_all`. Расхождение имён — при проработке per-object прав.

**Проверки:** vue-tsc чисто, vitest 84/84, build ок (455 KB), headless **12/12** (тег: кнопка→диалог→подтверждение→`/admin/tags`; пространство: кнопка→диалог→подтверждение→`/spaces`).

### F8 — Отложено: стратегия тестирования (HIGH → отложено)

**Решение (в обсуждении):** широкое тестовое покрытие в фазе активно меняющегося прототипа преждевременно — интерфейсы и файлы переписываются многократно (churn от синхронной правки тестов), тесты закрепляют мок-допущения про будущий бэкенд, UI-тесты с mount Vuetify дороги и хрупки.

**Что остаётся верным:**
- Основная верификация прототипа — **headless-скрипты** (`/tmp/opencode/verify_f6.js`, `verify_f7.js` и др.): ловят интеграционные поломки и не требуют правки при рефакторинге.
- Существующие 84 теста — исторические верификаторы (писались вместе с критичными изменениями: access.test.ts в F1, ruleValidation в фазе способностей и т.д.); уже окупились (F6 — смена редиректов обновилась за минуту). Удалять не требуется.
- **Добавлен только** `Core/User/Utils/access.test.ts` (+16 кейсов: hasAnyPermission/hasAllPermissions/isAdmin/ADMIN_SECTION_PERMISSIONS) — стабильный контракт безопасности, 26 строк чистых функций. Итог: **100/100** (8 файлов).
- **F39** (include `*.test.ts` → `*.{test,spec}.ts`) — не трогаем до следующей правки конфига.

**Когда вернуться к F8:** (а) появится реальный бэкенд-контракт → тестировать API-слой и сторы против него; (б) перед заморозкой release 1 → компонентные/регрессионные тесты (Shell, SmartGrid, UI).

### F9 — Решено: per-chat state (HIGH)

**Причина:** гонка в `openChat` — быстрый switch A→B мог перезаписать сообщения B сообщениями A (поздний ответ A затирает `allMessages`). Аналогичные гонки в `sendMessage` и `loadOlderMessages`.

**Решение (вариант C из обсуждения):** per-chat state — `Map<chatId, ChatState>` где `ChatState = { messages, hasMore, total, loading, loadingOlder }`. Гонка исчезла по построению: каждый чат имеет свой state, `activeChatId` — только указатель для view-слоя.

**Реализация:**
- `chat.ts` store: `chatStates = ref<Map<number, ChatState>>`, computed `allMessages`/`hasMoreOlder`/`loadingMessages`/`loadingOlder` derive from active chat's state.
- `openChat`: если state есть — мгновенный показ (кеш); если нет — создаёт и загружает.
- `applySyncResponse`: сохраняет сообщения для **всех** чатов из response (не только activeChatId) — ключевое изменение для корректной работы sync.
- `sendMessage`/`loadOlderMessages`: работают с per-chat state.
- `applySyncResponse` экспортирован из store для тестируемости.

**Тесты:** 2 новых кейса в `chat.store.test.ts`:
1. Гонка: openChat(A) → не резолвим → openChat(B) → резолвим B → проверяем вид B; резолвим A → проверяем вид остался B.
2. Sync сохраняет для всех чатов: отправляем sync с сообщениями для неактивного чата → открываем его → сообщения на месте.

**Результат:** vue-tsc чисто, vitest **102/102** (8 файлов), build ок (455 KB), headless **9/9** (мессенджер: список чатов, переключение, сообщения отличаются, возврат к первому чату — кеш мгновенный).

**Побочный бонус:** возврат к ранее открытому чату мгновенный (кеш в памяти) — не нужен повторный запрос к бэку.

**Связанные вопросы (не в F9):** F23 (`markChatRead` безусловно) — решена отдельно (см. F23: позиция прочтения `last_read_message_id`, guard в sync-пути).

### F10 — Решено: стабы Character/Game, маркеры в ТР (MEDIUM)

**Причина:** модули Character (§7) и Game (§8) — стаб-страницы «будет реализовано в следующих волнах». Расхождение осознанное (ТР §12: Персонажи — волна 4, Игры — волна 5; фронт уже опередил — Chat, волна 5, реализован в F9), но ТР это никак не маркировало — читатель §7/§8 не видел, что на фронте стабы.

**Решение:** зафиксировать осознанное расхождение в ТР, код-стабы не расширять.

**Реализация:**
- ТР §7 и §8: статус-ноты «Статус фронта» — модуль представлен стаб-страницей, полная реализация — волна 4/5, таблицы доступа действуют после реализации.
- ТР §12: строка-статус под графиком волн (Chat реализован; Character/Game — стабы до своих волн).
- `Character/routes.ts`: `/characters` получил `guestAllowed: true` (выравнивание с `/games`) — заглушка видна и гостю, без 404; в комментарии — снять при волне 4 (§11 гость персонажей не видит).

**Проверки:** vue-tsc чисто, vitest 102/102 (8 файлов), build ок (455 KB), headless 5/5 (гость: /games и /characters видят заглушку без 404; игрок: /characters — заглушка; нет Failed to resolve).

**Связь:** при волнах 4-5 реализовать §7/§8 целиком и снять `guestAllowed` с `/characters`.

### F12 — Решено: деактивация сохраняет причину/дату (MEDIUM)

**Причина:** ТР §3 БД содержит `users(deactivated_until, deactivate_reason)`, §4 — временная деактивация с причиной. В коде причина/дата собирались в диалоге и уходили в API, но: `User`-тип не имел полей, `mockDeactivateUser` игнорировал `_reason`/`_deactivatedUntil` (только `active = false`), store и профиль тоже не сохраняли/не показывали.

**Решение:** сквозной персист причины/даты: тип → mock → store → отображение.

**Реализация:**
- `User` тип: `deactivated_until?: string`, `deactivate_reason?: string`.
- `mockUsers.mockDeactivateUser`: записывает `deactivate_reason`/`deactivated_until` на объект (по образцу `mockUpdateUser`).
- `users` store `deactivateUser`: обновляет локальный объект (active + reason + date).
- `UserProfilePage`: под чипом «Отключён» показывает «Причина: …» и «Отключён до: …»; `handleDeactivate` проставляет поля локально.
- Тест `Service/__tests__/mockUsers.test.ts` (+2 кейса: с причиной/датой и без).

**Проверки:** vue-tsc чисто, vitest **104/104** (9 файлов), build ок (455 KB), headless **7/7** (деактивация id=8 с причиной/датой → статус/причина/дата на профиле; причина сохраняется в mock при SPA-возврате).

**Вне скоупа:** авто-реактивация по истечении `deactivated_until` (временная логика, отдельно).

### F23 — Решено: позиция прочтения last_read_message_id (MEDIUM)

**Причина:** `markChatRead` вызывался безусловно в openChat/sendMessage и с guard в sync-пути — расхождение с §13 #8 «оптимизация в applySyncResponse». При разборе выяснилось: unreadCount и новые сообщения доставляются в одном SyncResponse (связаны), т.е. guard не про «спам», а про серверную позицию прочтения. В схеме БД поля для прочтения не было вовсе — unreadCount «висел в воздухе».

**Решение:** ввести позицию прочтения `chat_members.last_read_message_id`; unreadCount — вычисляемый (`COUNT(messages WHERE id > last_read_message_id AND user_id != me)`). Разделитель «Новые сообщения» строится по `lastReadMessageId` — поуровневая таблица `chat_message_reads` не нужна (требований к отметкам прочтения по отдельным сообщениям нет).

**Реализация:**
- ТР § «Чаты»: `chat_members + last_read_message_id INT NULL` с комментарием про вычисление unreadCount.
- ТР §9 sync-пакет: добавлен `"lastReadMessageId"` в объект чата.
- ТР §13 #8: переформулирован — guard `unreadCount > 0` только в sync-пути (applySyncResponse), openChat/sendMessage — безусловно (явные действия, не тики); при прочтении `unreadCount = 0` + `lastReadMessageId = последнее сообщение`.
- `Chat` тип: `lastReadMessageId: number | null`.
- `mockChat.ts`: `lastReadMessageId` в сид-чатах (для чатов 1–7 — реальные id сообщений, остальные null); `mockMarkChatRead` сбрасывает unreadCount и ставит lastReadMessageId на последнее сообщение; новый чат в sync — `null`.
- `chat` store: openChat/sendMessage/applySyncResponse обновляют `lastReadMessageId`; computed `firstUnreadMessageId` (первое сообщение с id > lastReadMessageId, при null — первое в списке).
- `Messenger.vue`: разделитель «Новые сообщения» перед первым непрочитанным сообщением.
- Тесты chat.store.test.ts (+3 кейса: прочтение при открытии, разделитель при sync с выключенным autoScroll, авто-прочтение при sync с autoScroll).

**Проверки:** vue-tsc чисто, vitest **107/107** (9 файлов), build ок (456 KB), headless — **9/9** (verify_f9) + **5/5** (verify_f10) + **7/7** (verify_f12).

**Доводка 2026-08-01 (поведение разделителя):**
- Баг (только в браузере): прямое присваивание `chat.lastReadMessageId = …` в openChat ПОСЛЕ `await markChatRead` не инвалидировало computed `firstUnreadMessageId` (scheduler уже пересчитал его с lastRead=null) — разделитель «залипал». Диагностика: инструментация computed/watch в store + headless-пробы (sendMessage с той же мутацией в одном тике срабатывает; замена элемента массива — тоже).
- Фикс: в openChat вместо прямой мутации — замена элемента `chats.value[idx] = { ...chats.value[idx], unreadCount: 0, lastReadMessageId }` (единый паттерн с applySyncResponse).
- Семантика (решение 2026-08-01): разделитель показывается только при новых сообщениях во время чтения истории (автоскролл выключен); открытие чата помечает прочитанным сразу, разделитель при обычном открытии не показывается. Это уже совпадает с реализацией (guard в sync-пути).
- Headless verify_f23.js переписан под семантику: **4/4** (открытие unread-чата — нет разделителя; новое сообщение при autoScroll off — разделитель на позиции прочтения; autoScroll on + sync — прочитано, разделитель скрыт; нет Failed to resolve).
- Временная debug-строка `window.__chatStore` убрана из Messenger.vue.

### F14 — Решено: soft-delete шаблонов уведомлений (MEDIUM)

**Причина:** ТР §10 требовал `/admin/notification-templates/:id/delete`, но API-слой (интерфейс/mock/реальный/стор) уже содержал `deleteTemplate` с hard-delete (splice), а UI для удаления отсутствовал вовсе. По паттерну F7 отдельную страницу/роут деактивации не создаём.

**Решение:** soft-delete по образцу тегов (F7/TagEditPage): `active`-флаг вместо физического удаления.

**Реализация:**
- `NotificationTemplate` тип: `active: boolean`.
- `mockTemplates.ts`: seed + `createTemplate` → `active: true`; `deleteTemplate` → `deactivateTemplate` (сет `active = false`, без splice).
- `INotificationTemplateApi`/`mockTemplateApi`/`NotificationTemplateApi`: `deleteTemplate` → `deactivateTemplate`, engine-action `notificationTemplate.delete` → `notificationTemplate.deactivate`.
- `templates` store: `deactivateTemplate` (локально `active = false`, паттерн tags.ts), добавлен `filterActive`.
- `TemplateEditPage.vue`: ref `active` (из fetchTemplate); кнопка «Удалить» (`isEdit && canDelete && active`) + confirm-диалог («будет деактивирован, связи сохранятся») → `store.deactivateTemplate` → `/admin/notification-templates`; `canDelete = hasAnyPermission(currentUser, ['notification_template.delete'])`.
- `TemplatesListPage.vue`: колонка «Статус» (boolean, Активен/Удалён) + фильтр статуса в FilterBar (паттерн TagsListPage).
- ТР §10: строка `:id/delete` заменена на «кнопка+диалог на `:id/edit`, отдельной страницы нет»; схема `notification_templates` += `active BOOL DEFAULT true NOT NULL`.

**Проверки:** vue-tsc чисто, vitest **138/138** (12 файлов), build ок (456 KB).

### F26 — Решено: badge уведомлений при входе не на Dashboard (MEDIUM)

**Причина:** `unreadCount` заполнялся только в `fetchData()`, который вызывался лишь из DashboardPage/NotificationsPage/NotificationSlider. При входе на любую другую страницу (Users, Spaces, Messenger…) badge оставался 0/скрыт.

**Решение:** поднять загрузку уведомлений в Shell — на старте приложения.

**Реализация:** `Shell.vue` onMounted (рядом с `fetchChats`) → `notificationStore.fetchData()`. Топбар читает тот же `unreadCount`, badge актуален с любого входа.

**Проверки:** vue-tsc чисто, vitest **138/138** (12 файлов), build ок (456 KB).

### F20 — Решено: хардкод цветов и `!important` (MEDIUM)

**Причина:** стилевые правила §2 запрещают хардкод цветов и `!important`. В UI нашлось: `FilterBar #FFFFFF`/`rgb(128,128,128,0.4)`, `SmartGrid` drag-ghost `#fff`, 7 `!important` (SmartGrid: cursor/user-select/padding×2/opacity, SpacesPage+AdminDashboard: box-shadow карточек).

**Решение:** CSS-переменные темы вместо хардкода; `!important` → селекторы с большей специфичностью (правило §2).

**Реализация:**
- `FilterBar.vue`: `background: rgb(var(--v-theme-surface))`, бордер `rgba(var(--v-border-color), var(--v-border-opacity))`, hover `0.7`.
- `SmartGrid.vue`: ghost `rgb(var(--v-theme-surface))`, тень `rgba(var(--v-theme-scrim), var(--v-shadow-md-opacity))` (паттерн SlidePanel); 5 `!important` убраны — `.smart-grid .smart-header-th--settings` / `.smart-cell--settings` / `.smart-cell--settings .smart-burger-icon:hover` / `.smart-header-th--resizing`.
- `SpacesPage.vue`/`AdminDashboard.vue`: hover-тень карточек `rgba(var(--v-theme-scrim), var(--v-shadow-sm-opacity))` без `!important`.
- grep по `!important`/`#fff`/`rgba(0,0,0` в `.vue` — пусто.

**Проверки:** vue-tsc чисто, vitest **138/138**, build ок (415 KB после F28).

### F21 — Отклонено решением юзера: топ-меню/футер (MEDIUM)

**Причина:** ТР §11 требует в топбаре топ-меню (Персонажи/Игры/Пространства/Админка), футер `v-footer` (копирайт+ссылки) и guest-кнопки «Войти»/«Регистрация» (ТР §11 строка про гостя). В коде их не было.

**Решение юзера (2026-08-02):** от топ-меню в топбаре и shell-футера **отказались** — не реализуем. Было реализовано целиком (меню + guest-кнопки + футер) и **откачено**: TopBar.vue и Shell.vue возвращены в исходное состояние (только `≡` + `AppBreadcrumbs` + bell; футера нет). Кнопка входа для гостя остаётся в сайдбаре (SideBar.vue, как было).

**Отражение в ТР:** §11 «Общий лейаут» помечен — топ-меню в топбаре и футер по решению не реализуются (см. ниже).

**Проверки:** vue-tsc чисто (после отката).

### F22 — Решено (решение): виртуализация списков (MEDIUM)

**Решение:** осознанно отложено. `items-per-page="-1"` в SmartGrid отключает встроенную пагинацию Vuetify, т.к. списки уже пагинированы родителем через `pageRows` (perPage ≤ 100). Виртуализация/`v-memo` — преждевременная оптимизация на текущих объёмах. Зафиксировано в ТР §«Решения» №16.

### F25 — Решено (решение): per-object права на фронте (MEDIUM)

**Решение:** осознанно отложено на бэкенд-фазу. Через UI групп назначаются только глобальные ключи (`PERMISSION_KEYS`: `space.create/view_all/edit_all`, `game.create/view_all/edit_all`, rule.* без per-object семантики); проверки — `hasAnyPermission` по плоскому `user.permissions`. Per-object (`space.*`, `game.edit/moderate/manage`) — бэкенд middleware + передача прав объекта в API. Зафиксировано в ТР §«Решения» №17; связь с F7 (кнопка деактивации на `space.edit_all`).

### F27 — Решено: composable `useGridPage` (MEDIUM)

**Причина:** 4 страницы (Users/Groups/Tags/Templates ListPage) содержали идентичный блок: `sort`/`pagination`/`appliedFilters` refs, `extractFilterValue`, `onFilterChange`/`onSortChange`/`onPaginationChange`, `pageRows`/`sorted*` computed.

**Решение:** единый composable `src/modules/Core/Composables/useGridPage.ts` (паттерн `useAbortable`).

**Реализация:**
- `useGridPage(getItems: () => Row[])`: `sort`/`pagination`(page:1, perPage:10)/`appliedFilters`, computed `sortedRows` (универсальный компаратор — вариант UsersListPage, superset остальных) и `pageRows` (slice по perPage), обработчики `onSortChange`/`onPaginationChange`/`onFilterChange` (сет appliedFilters + сброс page=1).
- В 4 страницах локальные refs/computed удалены; `onFilterChange` страницы теперь только маппит фильтры в store (пример: `gridFilterChange(filters)` + `store.filterName = …`).
- Импорты `Sort`/`Pagination`/`computed`/`ref` убраны из страниц.

**Проверки:** vue-tsc чисто, vitest **138/138**, build ок.

### F28 — Решено: lazy-ветки API в main.ts (MEDIUM)

**Причина:** main.ts статически импортировал и mock-, и real-реализации всех API (+ HttpClient/Engine/CsrfApi) — обе ветки попадали в стартовый бандл.

**Решение:** async `bootstrap()` с динамическими `import()` ветки по `VITE_API_MODE`.

**Реализация:**
- `registerApiLayer()`: mock-ветка — 11 динамических `import()` mock-модулей → `register*`; real-ветка — динамические `import()` HttpClient/Engine + API-классов, `new CsrfApi`/`HttpClient`/`Engine` внутри ветки.
- Статично остались: `register*`/`getCsrfApi` из init-модулей, `initBaseRenderers`/`initBaseFilterHandlers`/UserCell/ActiveCell.
- `bootstrap()`: `await registerApiLayer()` → `initToken` → renderers → `createApp`/mount.

**Результат:** главный чанк **456 → 415 KB** (gzip 153 → 140); mock- и real-классы — отдельные ленивые чанки (неиспользуемая ветка не грузится).

**Проверки:** vue-tsc чисто, vitest **138/138**, build ок (12.4 c).

### F29 — Решено: команда /roll и макросы; механика броска (MEDIUM)

**Причина:** по §9 команда `/roll Nd6[...]` и макросы `user_macros` не реализованы (была только форма `DiceRollForm`); `dieSize` не участвовал в выводе. В ходе проработки найден **критичный баг**: преимущества/помехи в `mockChat.ts` были **инвертированы** — сортировка по возрастанию при adv>0 срезала 1-ки (лучшие, 2 успеха) и оставляла 6-ки (−1), т.е. преимущество вредило.

**Решения юзера (2026-08-02):** макрос шлёт `text_template` + карточку броска (`DiceRollResult`); модификатор **убрать вообще**; преимущества/помехи сделать **видимыми** (снятые кубы); размерность — только суффиксом в итоге; параметры ручные (персонажи — стабы, F10).

**Реализация:**
- `computeRollResult(spec, rng)` (Messages/Chat/Service/rollCalc.ts) — чистый подсчёт, направления сортировок исправлены: adv>0 → desc, срезать худшие (6-ки); adv<0 → asc, срезать лучшие (1-ки). Используется в `mockSendMessage`.
- `DiceRollResult` += `droppedRolls: number[]`; `DiceRollSpec` -= `modifier`. `DiceRollResult.vue`: снятые кубы зачёркнуты + нота «убрано N худших/лучших», итог с суффиксом размерности («4↑ успехов»), из шапки убраны `мод:`/`рзмер:`.
- `Core/Utils/rollParser.ts`: `parseRollCommand` (`/roll NdM [e:N|adv:N|dis:N|prem:N|pom:N|size:N|dim:N|label:текст]`, капы кубы≤30/adv≤10/eff≤20/грань≤100, невалид → null) и `parseRollFormula`. Хук в `ChatInput.handleSend`: текст со `/roll` → карточка вместо ручных pending-чипов.
- Макросы: `UserMacro` в Core/User/Interface/types.ts; `IUserApi` += `getMyMacros/createMacro/updateMacro/deleteMacro` (реальный `UserApi` через `user.macro.*` actions, mock в `mockMacros.ts`, in-memory по текущему юзеру, сид 2 шт.); `useMacrosStore` (Core/User/Store/macros.ts); `MacrosSection.vue` — CRUD-блок на своей странице профиля; в `ChatInput` — ряд чипсов макросов (клик → `send(textTemplate, [spec из rollFormula + efficiency])`).

**Проверки:** vue-tsc чисто, vitest **160/160** (+22: rollParser/rollCalc/mockMacros), build ок (главный чанк 415 KB, gzip 140).

**Доводка (2026-08-02):** макрос не позволял задать преимущества/помехи и настраивался неудобно. Добавлено:
- `UserMacro` += `adv` (преимущества >0 / помехи <0), `dieSize`; `user_macros` += `adv INT DEFAULT 0, die_size INT DEFAULT 0` (ТР §3).
- Форма макроса переработана: поля **Кубы/Грань** числами (вместо строки формулы, собирается `NdM`), **Эффективность**, **Преимущества**, **Размерность** + живой превью-чип (`5к6 +1 ↑ · сл:3`).
- Общий хелпер `formatRollSize` в `Core/Utils/rollParser.ts` (используется превью макроса и `DiceRollResult.vue`, убран дублирующий `SUPERSCRIPTS`).
- `ChatInput.sendMacro` шлёт `adv`/`dieSize` в спец; tooltip чипса макроса показывает их.
- Тесты: mockMacros (+adv/dieSize/дефолты), rollParser (formatRollSize 0/±1/±2/±10), стаб container.test += `adv:0, dieSize:0`. Парсер `/roll` не менялся (adv:/dis:/size: уже были).

**Доводка 2 (2026-08-02):** по предложению юзера макрос стал **преднастроенным сообщением**, а не связкой «обязательный текст + обязательный бросок»:
- `text_template` — опционально; `roll_formula` — `NULL`-able (NULL = без броска); макрос валиден при `name` + (текст ИЛИ бросок). Отправка через общий `send(text, rolls)` (текст-only / бросок-only / оба) — «общая реализация с сообщениями».
- `UserMacro` += `rollLabel?` (подпись броска — метка карточки для мульти-бросков «1 удар»/«уклонение»; пустая → «Бросок N») и `variableAdvantages` (галочка «переменные преимущества»).
- Форма: переключатель «Бросок кубиков» прячет/показывает блок (кубы/грань/эффективность/преимущества/размерность/подпись/переменные преимущества), превью показывает текст + чип.
- Чат: макрос с `variableAdvantages` → диалог «Число преимуществ» (дефолт = adv макроса) → отправка; клик отправляет сразу.
- Схема `user_macros` += `text_template DEFAULT ''`, `roll_formula NULL`, `roll_label NULL`, `variable_adv BOOLEAN DEFAULT false` (ТР §3).

**Доводка 3 (2026-08-02):** макрос теперь поддерживает **несколько бросков** — `UserMacro.rolls: MacroRollSpec[]` (вместо одиночного `roll_formula` + плоских полей):
- `MacroRollSpec` = { rollFormula, efficiency, adv, dieSize, rollLabel?, variableAdvantages } — пер-бросковый конфиг, `variableAdvantages` переносится на бросок.
- Форма: список бросков с кнопкой «Добавить бросок» (удаление по крестику), превью = текст + чипы всех бросков; макрос валиден при `name` + (текст ИЛИ ≥1 бросок).
- Чат: при отправке каждый бросок → своя карточка `DiceRollResult`. Если есть броски с пометкой «переменные преимущества» — диалог, введённое число применяется только к отмеченным, остальные используют свой `adv`; иначе — сразу.
- Схема нормализована (ТР §3): `user_macros(id, user_id, name, text_template, created_at)` + `user_macro_rolls(id, macro_id, position, roll_formula, efficiency, adv, die_size, roll_label, variable_adv)`.
- Сид mock: «Полная атака» — 2 броска (Удар 1/Удар 2); «Отдохнуть» — только текст.

### LOW-бэтч F30–F40 + F42 — Решено (2026-08-02)

**Решение юзера:** весь хвост ревью закрыт одной итерацией. F34 — принята конвенция `store.xxx` (не не-проблема: деструктуризации state нет, `storeToRefs` ничего не даёт при текущем стиле). F38 — реальное копирование правил при наследовании. F42 — персистентность черновика в `localStorage`.

**Код:**
- F31: `Mechanic` → `Interface/types.ts`; `IRuleApi.getMechanics` (mock → `fetchMechanics`, real → `rule.getMechanics`); RuleDetailPage/RuleEditPage через `getRuleApi()`, прямые импорты mock убраны.
- F32: удалены `UserCell.vue` (регистрация в main.ts), `UserList.vue`, `fetchMechanic`, `getChatMockMembers`, экспорты `canKick`/`meStatus`/`groups` из usePermissions. SSE и `publicRoutes` — без изменений.
- F35: `outlined`/`dense` → `variant="outlined"`/`density="compact"` (CharacteristicEditor, 3 места).
- F36: `JSON.parse(JSON.stringify())` → `structuredClone()` во всех редакторах (Ability/Race/Species/Item/RequirementNode/RequirementList/Spell/Process/Grant); AbilityEditor — `as AbilitySpec` при эмите.
- F37: guard кэширует только успех `authChecked = await auth.checkAuth()`.
- F38: `mockSpaces.createSpace` с `inheritFrom` генерит правила родителя и кладёт снепшот в `revisionCache` (`${id}:0`), `rulesCount` = длина снепшота.
- F39: vitest `include: ['src/**/*.{test,spec}.ts']`.
- F42: `draftRules.ts` — localStorage `powerscale.drafts.v1` (загрузка в setup, `persist()` после мутаторов, очистка при пустом), тест `Store/__tests__/draftRules.test.ts` (+4 кейса).

**ТР:** §6 (F30 — `has_tag` без `min_count`, `characteristic_value.min: DimensionalNumber`, дары += `value`/`limit`/`source_id`, нота Draft/Clean + prune; F33 — теги `string_id` → `code` в §3/§6/§11/§12); §9/§11 (F40 — гость видит только публичные чаты); §5 + §13 №18 (F42 — персистентность черновика). `frontend-rules.md` §3 += конвенция Pinia-сторов (F34).

**Проверки:** vue-tsc чисто, vitest **169/169** (16 файлов, +4 draftRules), build ок.
