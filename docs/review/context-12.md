# Контекст ревью 12 — Messages/Chat

Волна 2026-08-04. Ревью модуля `Messages/Chat` на `frontend-rules.md` + общее
ревью качества. Модуль проходит ревью впервые (context-10/11 — Notifications).
Промт волны — `docs/review/prompts/review-module-prompt.md`.

## Решения пользователя

- **Зависимости**: у `Messages/Chat` допустимы зависимости только от группы Core.
  Иных — быть не должно.
- **Формат ревью**: сначала находки по критичности, потом разбор нетривиальных
  проблем с вариантами и рекомендацией.

## Что проверялось и подтверждено

- **Анатомия/слои**: `Dto/`, `Interface/`, `Enum/`, `Service/`, `Constant/`
  (+`Constant/Chat/`), `Component/`, `Composables/`, `Mock/`, `Store/`,
  `Page/`, `__tests__/` (+ `__tests__/Store`, `__tests__/Service`). В корне только
  `init.ts`/`routes.ts`. Папки в единственном числе. Правило 22 соблюдено.
- **Один экспорт на TS-файл** ✓ (точки `init.ts`/`routes.ts` — исключение;
  мультиэкспорт в `Mock/mockChat.ts` — принятая конвенция, context-10).
- **Плагинные точки** (правило 29): хост объявляет generic-контракты
  (`ICommandHandler`, `IContentRenderer`, `IChatToolbarExtension`) и реестры
  `register*/get*` в `init.ts`; донор `Roleplay/Game` регистрирует `roll`-команду,
  рендерер и тулбар-расширения в своём `init.ts` (`registerGameModule`, main.ts:95).
  Хост не импортирует компоненты донора (получает через `getContentRenderer('roll')`,
  `getCommandHandlers()`, `getToolbarExtensions()`). ✓
- **API-сервис через ServiceLocator + `getChatApi()`** в сторе ✓ (правило 26;
  `ChatApi` регистрируется в `main.ts` через `registerChatApi`).
- **Типизация по слоям**, string-union `ChatType`/`ChatVisibility`/`ChatPermission`,
  `import type`, алиас `@/`, без `any` ✓ (правила 43–46, lint чист).
- **Безопасность рендера**: `msg.content`/`chat.lastMessage` выводятся только через
  `{{ }}` (Vue-эскейпинг), `v-html` не используется. XSS-вектора нет.
- **Синхронизация**: `ChatSyncService` — stateless class-service с инъекцией
  конфига; poll по умолчанию (mock), SSE — целевой протокол (комментарий-«почему»);
  фоновые ошибки намеренно тихие (комментарий). `stopSync` через refCount корректен,
  утечки таймеров нет.
- **Тесты**: `chat.store.test.ts` (гонка openChat, sync для неактивных чатов,
  unread/`firstUnreadMessageId`) + `chatSyncService.test.ts` (poll/SSE). Критичная
  логика покрыта (F8). ✓

## Findings

### P2 — баги данных / нарушение требования по зависимостям

**P2-1. Межмодульная зависимость Messages/Chat → Roleplay/Game (контракты бросков).**
`DiceRollSpec`/`DiceRollResult` импортируются из чужого прикладного модуля:
`Store/chat.ts:6`, `Service/ChatApi.ts:6`, `Interface/IChatApi.ts:4`,
`Dto/ChatMessage.ts:1`, `Dto/ParsedCommand.ts:1`, `Dto/ChatToolbarContext.ts:1`,
`Component/ChatInput.vue:3`, `Component/ChatMessenger.vue:8`, а `Mock/mockChat.ts:8`
дополнительно тянет `rollService` из `Roleplay/Game/Service/Instance`.
Противоречит требованию пользователя «иных зависимостей, кроме Core, не должно».
Разбор вариантов — ниже.

**P2-2. `applySyncResponse` без ограничения размера: рост `state.messages` для
неоткрытых чатов** (`Store/chat.ts:201-212`). При каждом sync сообщения пушятся в
state всех затронутых чатов, включая никогда не открывавшиеся; `slice(-MAX_STORED)`
применяется только в `loadOlderMessages`. При длительной работе стор копит историю
всех чатов без предела (память/производительность). Фикс: для неактивных чатов либо
не хранить сообщения вовсе (только счётчик), либо держать последние N (MAX_STORED)
и помечать «требуется загрузка истории» при открытии.

**P2-3. `openChat` не догружает историю, если state уже создан sync'ом**
(`Store/chat.ts:100-101`, `if (state) return;`). Синхронизация создаёт state с
несколькими свежими сообщениями и `total: 0`. Открытие такого чата показывает только
эти сообщения; первая страница истории и `total` не подгружаются (история доступна
лишь скроллом вверх через `loadOlderMessages`). Фикс: при открытии, если
`state.total === 0 && state.messages.length < PAGE_SIZE` — догружать первую страницу
и выставлять `hasMore`/`total`.

**P2-4. Нет состояний ошибки/повтора у ключевых операций (F17, правило 36).**
`fetchChats`/`openChat`/`loadOlderMessages`/`sendMessage` (`Store/chat.ts`) — только
`try/finally`, без `error`-состояния в сторе и без UI-отображения ошибки (по
аналогии с `error`/`actionError` в Notifications, context-11). Провал запроса
уходит в консоль, пользователь молчит. Фикс: поле `error` (загрузка списка/чата) и
`actionError` (send) в сторе + алерты с повтором в `ChatMessenger.vue`.

### P3 — улучшения

- **P3-1. Non-null assertion в шаблоне `ChatList.vue`**: `otherMember(c)!` на
  строках 72/75/77 — обходит правило 54 (ESLint ловит `!` только в `<script>`), плюс
  `otherMember` пересчитывается 2–3 раза на элемент. Фикс: computed
  `currentPrivateChat` (или `otherMemberByChat`) на элемент через подкомпонент/в
  `ChatList` с `v-for` на провайдере, либо guard-метод без `!`.
- **P3-2. `ChatBar.vue` дублирует сортировку стора** (строки 14-16) — своя копия
  `sortedChats` + `.slice(0,10)`. Переиспользовать `store.sortedChats` (добавив
  лимит), `hasGameDiscussionAfter` (строка 23-28) — хрупкая логика соседства в
  шаблоне, вынести/упростить.
- **P3-3. `ChatMessenger.vue` ~296 строк**: рендер сообщений (шаблон 130-174) +
  скролл-логика — кандидат на вынос `Component/ChatMessageList.vue` (правило 8,
  ~250 — сигнал перепроверить декомпозицию).
- **P3-4. `renderedMessages`** (`Store/chat.ts:72`) — computed, возвращающий
  `allMessages` без преобразований. Мёртвый слой под «будущие фильтры» — YAGNI
  (правило 37). Удалить или использовать.
- **P3-5. `ChatSyncService` инстанцируется прямо в сторе** (`Store/chat.ts:227`),
  а не через `Service/Instance/` (правило 27). Инстанс несинглтонный и зависит от
  стора (`onSync`, `getSyncApi`), поэтому фабрика в сторе объяснима, но стоит
  зафиксировать решение (комментарий/док) или вынести фабрику.
- **P3-6. `markChatRead` fire-and-forget** (`Store/chat.ts:172, 218`) — без `await`
  и обработки ошибок. Приемлемо (read-маркировку повторит следующий sync), но не
  отмечено комментарием.
- **P3-7. `UserProfileSlider.vue`**: отображает `email`, `lastLogin`, `registered`
  другого пользователя (строки 91-110) — потенциально чувствительные данные в
  чужом профиле; `catch { userData.value = null }` (строка 46-48) — тихий провал
  (F17), нет состояния ошибки/повтора.
- **P3-8. `MessengerTabs.vue:14-18`**: `store.tabUnread(tab.key)` вызывается дважды
  на вкладку (`v-if` + `:content`). Один проход через локальную переменную/computed.
- **P3-9. Функции в `Constant/`**: `chatIcon`/`chatColor` (`Constant/Chat/chatIcon.ts`,
  `chatColor.ts`) — stateless-функции в папке справочников. Допустимо как
  «производные справочники» (правило 21), но альтернатива — `Utils/`. Вопрос стиля.
- **P3-10. Дублирование иконок**: `chatTabs.ts` и `CHAT_CONFIG` задают icon для
  одних типов (`game`/`game_discussion`/`character_discussion`) независимо — единый
  источник манифеста (правило 21): вывести `tabs` из `CHAT_CONFIG` + labels.
- **P3-11. `ChatInput.vue`**: команда, не распознанная ни одним `ICommandHandler`
  (`text.startsWith('/')`), отправляется как обычный текст (строки 49-60) — возможно,
  стоит показывать hint или не отправлять. Продуктовое решение.
- **P3-12. `AppShell.vue` импортирует компоненты Chat напрямую** (`ChatBar`,
  `ChatSlider`, `useChatStore`, строки 4-10) — не через публичную точку (правило 28
  допускает `useXxxStore`, но не `Component/`). Для shell-композиции логично, но
  стоит зафиксировать как допустимый слой или вынести в реестр.

## Разбор нетривиальных проблем

### P2-1: зависимость Chat → Roleplay (DiceRollSpec/DiceRollResult)

- **Вариант A — вынести контракты бросков в Core** (`Core/Engine/Dto/DiceRollSpec`,
  `DiceRollResult`, roll-логика в `Core/Engine/Service`). Обе группы (Messages,
  Roleplay) импортируют из Core; Chat остаётся «чистым» (только Core). Плюсы:
  соответствует требованию пользователя и правилу 31 (направление Core-центрично);
  `ChatMessage` не знает про домен RPG. Минусы: броски кубиков — домен Roleplay,
  вынос в фундамент размывает границу Core; заметный рефакторинг (моки, стора,
  тесты) + миграция в Roleplay/Game.
- **Вариант B — развязать тип**: `ChatMessage.rolls` сделать нейтральным
  (`unknown[]`/`Record<string, unknown>[]`), а конкретную типизацию оставить в
  `IContentRenderer`/рендерере. Плюсы: Chat перестаёт зависеть от Roleplay полностью
  (плагинная точка уже для этого есть). Минусы: потеря строгой типизации rolls в
  сторе/шаблоне; `sendMessage` всё равно должен принимать спеки бросков — придётся
  типизировать через generic или контракт.
- **Вариант C — признать намеренной**: броски — часть мессенджера RPG-платформы,
  тип-контракт через `Dto/` формально разрешён правилом 28. Плюсы: ноль работы.
  Минусы: противоречит явному требованию пользователя.

**Рекомендация**: Вариант A (контракты — в Core), но это архитектурное решение,
требует согласования и правок в Roleplay/Game. Если объём пугает — промежуточный шаг:
оставить на волне, зафиксировать в ревью, вернуться отдельной волной.

### P2-4: состояния ошибок (F17)

Повторить паттерн Notifications (context-11): `error` для загрузки списка/чата,
`actionError` для отправки; алерты с повтором. Плюсы: единый UX-стандарт, консистентно
с соседним модулем. Минусы: небольшой рост стора. Рекомендация — принять.

## Что намеренно не блокировало

- Плагинные точки Chat монтируются только донором Roleplay/Game: если рендерер
  `roll` не зарегистрирован, роллы не рендерятся молча (fail-safe) — приемлемо.
- `AppShell.onMounted` всегда вызывает `fetchChats()` (для badge-счётчиков на всех
  страницах) — осознанная цена, лёгкий запрос.
- `usePermissions` — композабл с доменным именем «Chat» (кандидат в
  `ChatPermissions`), не трогали.
- `MemberInfo.status: string` без union — соответствует бэк-терминологии (`gm`,
  `admin`, `member`), не сужали.

## План работ

1. **Архитектурное решение по P2-1** (Chat ↔ Roleplay): выбрать A/B/C, согласовать;
   при A — вынести контракты в Core, обновить Roleplay/Game и моки.
2. **Store (`chat.ts`)**: P2-2 (лимит/отказ от хранения сообщений неактивных чатов),
   P2-3 (догрузка истории в `openChat`), P2-4 (поля `error`/`actionError`).
3. **UI**: P3-3 (вынос `ChatMessageList`), P3-1 (`ChatList` без `!`), P3-2 (`ChatBar`),
   P3-8 (`MessengerTabs`), P3-7 (`UserProfileSlider` — убрать чувствительные поля или
   оставить по решению, добавить состояние ошибки).
4. **Мелочи**: P3-4 (удалить `renderedMessages`), P3-6 (комментарий к fire-and-forget),
   P3-9/P3-10 (Constant: стиль/манифест), P3-11 (поведение команд), P3-5 (зафиксировать
   решение по `ChatSyncService`).
5. **Верификация**: `vue-tsc --noEmit`, `vitest run`, `npm run lint`,
   `npm run format:check`.

## Верификация волны

Состояние до правок: `vue-tsc --noEmit` чисто, `vitest run` 213/213, `npm run lint`
чисто. (После правок — перепроверить.)

> **Примечание (2026-08-05):** этот файл — находки и план (1-я волна).
> Решения по найденным пунктам закрыты в последующих волнах Chat:
> `context-13.md` (плагинная модель, P2-1) и `context-14.md`
> (P2-2/P2-3/P2-4 + `error`/`actionError` F17, виртуализация, «Итог волны 14»).
> Актуальное состояние — см. index `docs/review/README.md`.

Ссылки: `frontend-rules.md`, `docs/review/prompts/review-module-prompt.md`,
`docs/review/context-11.md` (эталон F17 для Notifications),
`src/modules/Messages/Chat/`.
