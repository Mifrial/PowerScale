# Контекст ревью 14 — Messages/Chat (3-я волна)

Волна 2026-08-04. Повторное ревью `Messages/Chat` после context-13 (плагинная
модель + универсальные вложения + виртуализация). Промт —
`docs/review/prompts/review-module-prompt.md`. Режим: разбор по пунктам,
изменения — только после явного одобрения пользователя.

## Статусы findings context-13

> **Снапшот на старте волны.** Помеченные «Открыт» пункты закрываются ниже по
> тексту волны (Пункты 1–17, «Решения»). Итог — раздел «Итог волны 14».

| Finding | Статус |
|---------|--------|
| P2-1 (зависимость Chat→Roleplay, prod) | Закрыт (grep: только Core в prod-коде; Roleplay — только `Mock/mockChat.ts`, правило 28) |
| P2-2 (applySyncResponse без лимита) | Закрыт — `slice(-MAX_STORED)` для неактивных |
| P2-3 (openChat не догружает историю) | Закрыт — `initialized` + догрузка |
| P2-4 (нет error/retry, F17) | Закрыт — `chatsError`/`chatError`/`actionError` + UI повторов |
| P3-1 (ChatList `!`-assertions) | **Открыт** — `ChatList.vue:73,76,78` |
| P3-2 (ChatBar: дубль сортировки + хардкод game/game_discussion) | **Открыт** — актуален после плагинной модели |
| P3-3 (ChatMessenger ~300 строк) | Закрыт — вынесен `ChatMessageList`/`ChatMessageRow` |
| P3-4 (renderedMessages — мёртвый слой) | Закрыт — удалён |
| P3-5 (ChatSyncService в сторе) | **Открыт** — `Store/chat.ts:8,223,272` |
| P3-6 (markChatRead fire-and-forget) | **Открыт** — `Store/chat.ts:212,263`; усугублён `openChat:144` (await в try) |
| P3-7 (UserProfileSlider: чувствительные поля + тихий catch) | **Открыт** — `Core/User/Component/UserProfileSlider.vue` |
| P3-8 (MessengerTabs: tabUnread дважды) | **Открыт** — `MessengerTabs.vue:14-16` |
| P3-9 (функции в Constant/) | Закрыт рефакторингом |
| P3-10 (дубль источников иконок) | Закрыт рефакторингом |
| P3-11 (неизвестная команда отправляется текстом) | **Открыт** — `ChatInput.vue:115-126` |
| P3-12 (AppShell импортирует ChatBar/ChatSlider) | **Открыт** — `AppShell.vue:4,8,10,36,38` |
| P3-13 (мёртвая inline-renderer-инфраструктура) | Закрыт — полный F35 |
| P3-14 (ленивая инициализация BASE-типов) | Закрыт — BASE императивно |
| P3-15 (DiceRollResult.vue:8 каст) | **Открыт** — `as DiceRollResult` |
| P3-16 (IContentRenderer/IInlineRenderer идентичны) | Закрыт — единый `IRenderer` |
| P3-17 (getContentRenderer дважды) | **Открыт** — `ChatMessageRow.vue:58-59` (+ getInlineRenderer дважды 49-50) |

## Findings волны 14

### P2

**P2-1 (правило 23). Два экспорта на TS-файл.**
- `Interface/ITokenSource.ts:1-11` — `ITokenOption` + `ITokenSource`.
- `Utils/inlineContent.ts:6,29` — `parseInlineContent` + `inlineContentToText`.

### P3 (новые)

- **P3-A.** `Store/chat.ts:144` — `openChat` делает `await markChatRead` внутри try;
  провал read-маркировки валит открытие чата с `chatError`, хотя сообщения загружены.
- **P3-B.** Дубль вывода превью: `Store/chat.ts:207` повторяет `Mock/mockChat.ts:864`
  (`deriveChat`) — `content || attachments.length ? … : ''`.
- **P3-C.** `usePermissions()` инстанцируется в actions стора (`Store/chat.ts:110,240,261-262`),
  в т.ч. в цикле по `newChats`. Заменить на прямое чтение `useAuthStore().isGuest`.
- **P3-D.** `useChatUsers.ensureUsers` (`useChatUsers.ts:31-34`) мутирует `userStore.users`
  напрямую (push) в обход action'ов; тихий провал fetch (F17).
- **P3-E.** `ChatInput.runSearch` (`ChatInput.vue:67-76`) — только try/finally, без состояния
  ошибки; `handleSend` (`ChatInput.vue:112-130`) очищает поле/вложения до ответа — текст теряется.
- **P3-F.** `ChatMessageList.vue:90-94` — `getUser`/`segmentsOf`/`allMessages[i]` на каждый рендер.
- **P3-G.** Мёртвая обработка `AbortError` (`Store/chat.ts:113,154,214`) — нет AbortController/signal.
- **P3-H.** `ChatMessenger.vue:88` — `store.activeChat!.id` `!` в шаблоне (тот же класс, что P3-1).

## План по пунктам (очередь обсуждения)

1. P2-1 — мультиэкспорт в `ITokenSource.ts` + `inlineContent.ts` (варианты A/B/C).
2. P3-2 — ChatBar: плагинный разделитель (`IChatType.dividerAfter`) + убрать дубль сортировки.
3. P3-A/P3-6 — markChatRead: не блокировать openChat, единый fire-and-forget.
4. P3-C — `usePermissions` в сторе → `auth.isGuest`.
5. P3-B — общий `messagePreview` (Utils), стереть дубль.
6. P3-G — убрать мёртвый AbortError.
7. P3-1/P3-H — `!` в шаблонах (ChatList + ChatMessenger).
8. P3-8 — MessengerTabs: tabUnread один раз.
9. P3-17 — ChatMessageRow: renderer-резолюция один раз.
10. P3-E — runSearch + потеря текста при провале send.
11. P3-F — парсинг сегментов в ChatMessageRow.
12. P3-D — Core/User: action addUsers.
13. P3-7 — UserProfileSlider (вне модуля, зона интеграции).
14. P3-5 — ChatSyncService: зафиксировать решение.
15. P3-11 — поведение неизвестных команд.
16. P3-12 — AppShell.
17. P3-15 — DiceRollResult каст.

## Решения (заполняется по ходу)

### Пункт 1 — P2-1 (правило 23). Решено: вариант B + раздельный фикс ITokenSource.

- **`Service/InlineContentService.ts`** — класс с `parse(content)`/`toText(content)`;
  конфиг инъектируется: `tokenRe` (default — `Constant/Chat/INLINE_CONTENT_TOKEN_RE.ts`,
  правило 21) и `tokenLabel` (резолвер подписи токена, default null → fallback params[0]).
- **`Service/Instance/inlineContentService.ts`** — синглтон (правило 27), `tokenLabel`
  провязан на `getInlineRenderer(type)?.describe` (реестр init).
- Потребители: `ChatMessageList.vue` (`inlineContentService.parse`),
  `ChatList.vue` (`inlineContentService.toText`).
- Тест перенесён `__tests__/Utils/inlineContent.test.ts` → `__tests__/Service/inlineContentService.test.ts`
  (структура повторяет модуль), `Utils/` удалён.
- **`Interface/ITokenSource.ts` разнесён**: `ITokenOption.ts` (отдельный файл) +
  `ITokenSource.ts` (import type). `ChatInput.vue` импортирует `ITokenOption` из нового файла.
- Верификация: `format`/`lint` чисто, `vue-tsc --noEmit` чисто, `vitest run` 236/236 (24 файла).

### Пункт 2 — P3-2 (ChatBar). Решено: разделитель удалить (B), дубль сортировки убрать.

- Разделитель (`v-divider` game→game_discussion) и `hasGameDiscussionAfter` удалены —
  декоративная группировка не стоит хардкода чужих типов (YAGNI). Доменный разрыв
  Chat→Game закрыт полностью (production-код Chat теперь не знает типов доноров).
- Сортировка: `recentChats = computed(() => store.sortedChats.slice(0, 10))` вместо
  собственной копии сортировки в `ChatBar.vue`.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто.

### Пункт 3 — P3-A/P3-6 (markChatRead). Решено: A.

- В `Store/chat.ts` добавлен приватный помощник `markRead(chatId)` — fire-and-forget
  с тихим `.catch` и комментарием-«почему» (read-маркировку повторит следующий sync).
- `openChat:144` — `await markChatRead` убран из `try` (не блокирует открытие);
  `sendMessage:212` и `applySyncResponse:263` переведены на `markRead` (нет unhandled rejection).
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, store-тесты 21/21.

### Пункт 4 — P3-C (usePermissions в сторе). Решено: A.

- `Store/chat.ts` больше не вызывает композабл в actions: добавлен `const auth = useAuthStore()`
  наверху setup-стора, `fetchChats:110` и `applySyncResponse:240` читают `auth.isGuest`.
- `usePermissions` остался только там, где нужен `canInChat` (компоненты).
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, store-тесты 21/21.

### Пункт 5 — P3-B (дубль превью). Решено: A.

- `Utils/messagePreview.ts` — `messagePreview(content, attachments)` (stateless, одноцелевой).
- `Store/chat.ts:207` и `Mock/mockChat.ts:864` используют общую функцию; `Utils/` восстановлен.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, Chat-тесты 37/37 (4 файла).

### Пункт 6 — P3-G (мёртвый AbortError). Решено: A.

- Из `Store/chat.ts` удалены три проверки `DOMException AbortError`
  (`fetchChats`, `openChat`, `sendMessage`) — отмены запросов в API нет (signal не
  пробрасывается), ветка мёртвая. Гонки решаются per-chat state.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, store-тесты 21/21.

### Пункт 7 — P3-1/P3-H (`!` в шаблонах). Решено: B (ChatListItem) + guard-метод.

- `Component/ChatListItem.vue` — вынесена строка списка: computed `otherMember`/`name`/
  `groupColor`/`groupInitials`, `v-if="otherMember"` без `!`; вызов `otherMember` один раз
  (был 3x на элемент). `ChatList.vue` стал тонким контейнером (v-for + эвенты).
- `ChatMessenger.vue` — добавлен guard-метод `retryOpenChat()` (без `!` и без
  инлайн-условия в шаблоне).
- Grep подтверждает: `!`-assertions в шаблонах модуля отсутствуют.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, Chat-тесты 37/37.

### Пункт 8 — P3-8 (MessengerTabs). Решено: A.

- `MessengerTabs.vue` — локальный computed `unreadByKey` (`Object.fromEntries` по `store.tabs`),
  `tabUnread` вызывается один раз на вкладку.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто.

### Пункт 9 — P3-17 (ChatMessageRow). Решено: A.

- `ChatMessageRow.vue` — computed `inlineRenderers`/`attachmentRenderers` (резолвер один
  раз на элемент, правило 35); шаблон читает массив (v-if + :is). Двойного вызова
  `getInlineRenderer`/`getContentRenderer` нет.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто.

### Пункт 10 — P3-E (ChatInput). Решено: A.

- **send → async-проп**: `Store/chat.ts sendMessage` возвращает `Promise<boolean>`
  (true/false, ошибки ловит сам); `ChatMessenger.handleSend` возвращает результат и
  передаёт его в `ChatInput` пропом `:send`. `ChatInput` очищает `messageText`/
  `pendingAttachments` **только при успехе** — текст не теряется при провале.
  `ChatToolbarContext.send` и тулбар-расширения не затронуты (send не бросает).
- **Пикер**: добавлен `pickerError` — состояние ошибки `runSearch` (F17), вывод в UI;
  повторный ввод перезапускает поиск.
- Тесты: `sendMessage` success → true / failure → false (контракт закреплён).
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, Chat-тесты 37/37.

### Пункт 11 — P3-F (парсинг в рендере). Решено: A.

- Парсинг сегментов перенесён в `ChatMessageRow`: `segments = computed(() =>
  inlineContentService.parse(props.msg.content))` — кэшируется на сообщение; проп
  `segments` убран. `ChatMessageList` больше не парсит контент на каждый рендер
  (убраны `segmentsOf`, импорты `InlineSegment`/`inlineContentService`).
- `getUser(...)` в списке оставлен (дешёвый Map-lookup).
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, Chat-тесты 37/37.

### Пункт 12 — P3-D (мутация чужого стора). Решено: B.

- Логика «догрузить недостающие профили в каталог» перенесена в `Core/User/Store/users.ts`
  как публичный action `ensureUsers(ids)`: сам определяет missing, при пустом каталоге —
  `fetchUsers()`, иначе `getUsersByIds(missing)` с дедупом по id; ошибки — best-effort
  (комментарий-«почему»), чат не ломается.
- `useChatUsers.ensureUsers` — тонкий делегат `userStore.ensureUsers(ids)`; прямого
  `userStore.users.push` больше нет. `getUsersByIds` остаётся публичным (используется
  внутри `ensureUsers`).
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, Chat+Core/User 59/59.

### Пункт 13 — P3-7 (UserProfileSlider). Решено: B.

- Чувствительные поля `email`/`registered`/`lastLogin` скрыты за `canViewSensitive`
  (`props.userId === auth.userId || isAdmin(userStore.currentUser)`) — видны только
  своему профилю и админу. Статус/группы остаются публичными.
- Тихий catch заменён на `loadError` + UI ошибки с кнопкой «Попробовать снова» (`retryLoad`,
  без `!` в шаблоне); спиннер — только пока идёт загрузка без ошибки.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, Chat+Core/User+Core/Auth 67/67.

### Пункт 14 — P3-5 (ChatSyncService в сторе). Решено: A.

- Добавлен комментарий-«почему» над `syncService` в `Store/chat.ts`: инстанс store-bound
  (onSync замыкается на state стора) и per-instance, а не app-синглтон — правило 27
  (`Service/Instance/`) намеренно не применяется.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто.

### Пункт 15 — P3-11 (неизвестная команда). Решено: A.

- `ChatInput.handleSend`: текст, начинающийся с `/`, без распознанного хендлера **не
  отправляется** — выводится локальный `commandError` («Неизвестная команда»), текст
  сохраняется; очистка при вводе (watch на `messageText`).
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, Chat-тесты 37/37.

### Пункт 16 — P3-12 (AppShell). Решено: A.

- `AppShell.vue` — комментарий-«почему»: shell — composition root, импортирует
  app-level компоненты модулей напрямую (по аналогии с main.ts), исключение из правила 28.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто.

### Пункт 17 — P3-15 (каст DiceRollResult). Решено: B.

- `Dto/ChatAttachment.ts` — дженерик `ChatAttachment<TPayload = unknown>` (дефолт
  `unknown`, все существующие использования не меняются).
- `DiceRollResult.vue` — `attachment: ChatAttachment<DiceRollResult>`, каст `as
  DiceRollResult` убран; `roll`/`sizeSuffix` — computed.
- `Game/init.ts` (process/describe `as DiceRollSpec`) оставлены: это граница
  `IAttachmentProcessor.payload: unknown` — отдельный вопрос, не трогаем в этой волне.
- Верификация: format/lint чисто, `vue-tsc --noEmit` чисто, тесты 236/236.

## Итог волны 14

Все 17 пунктов плана закрыты. Найденные по ходу нового: P3-A…P3-H обработаны
(см. решения выше). Открытыми остаются только вопросы, вынесенные за рамки:
`Game/init.ts` касты (IAttachmentProcessor), `RuleChip` контекст ревизии (TODO),
`mockChat` прототип-ограничения (spaceId игнорируется) — отдельные волны вне Chat.

## Дополнение после плана

- **Касты `as DiceRollSpec` в `Game/init.ts` убраны**: `IAttachmentProcessor<TPayload = unknown>`
  (дженерик), `registerAttachmentProcessor<TPayload>` в `init.ts`, параметры `process`/`describe`
  аннотированы `DiceRollSpec`. Верификация: format/lint чисто, `vue-tsc --noEmit` чисто,
  тесты 236/236.
- **Отложено** (отдельные волны Roleplay): `ruleCatalogCache` TODO (резолюция правил по
  контексту ревизии), mock `spaceId` (часть той же задачи).

## Верификация

После каждого изменения: `npm run format` + `npm run lint`; на завершении группы —
`npx vue-tsc --noEmit` + `npm run test`. Порт 3000 — пользовательский (не занимаем).
