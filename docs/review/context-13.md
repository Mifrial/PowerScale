# Контекст ревью 13 — Messages/Chat (после рефакторинга)

Волна 2026-08-04. Повторное ревью `Messages/Chat` после «Волны рефакторинга Chat»
(плагинная модель + универсальные вложения). Базовый контекст — `context-12.md`;
промт — `docs/review/prompts/review-module-prompt.md`. Ревью READ-ONLY до принятия
решений пользователем.

## Что изменилось с context-12 (рефакторинг)

- `ChatMessage.rolls: DiceRollSpec[]` → `attachments: ChatAttachment[]` (`{ type, payload }`).
- `IChatApi.sendMessage(chatId, content, attachments)`.
- Удалены `Enum/ChatType.ts`, `Constant/chatType.ts` (CHAT_CONFIG), `chatIcon.ts`,
  `chatColor.ts`, `chatTabs.ts`, `Dto/ParsedRollCommand.ts`.
- `init.ts`: реестры `register/get` — command handlers, content renderers,
  attachment processors, toolbar extensions, chat types, chat tabs;
  базовые типы/вкладки `BASE_CHAT_TYPES`/`BASE_CHAT_TABS` (private/group),
  лениво при первом `get*()`.
- Доноры: `Roleplay/Game` (типы/вкладки game|game_discussion, `/roll`, рендер
  `DiceRollResult`, attachment-processor `roll`, тулбар), `Roleplay/Character`
  (тип/вкладка character_discussion) — регистрация в своих `init.ts`.
- Восстановлены игровые/персонажные чаты, потерянные на промежуточном шаге.

## Статусы findings context-12

> **Снапшот на старте волны.** Часть пунктов закрыта ниже, в этой же волне
> («Группа 2 — Store + виртуализация», «Замечания пользователя»). Итоговое
> состояние всех пунктов и перенос остатков — в разделе «Осталось открытым».

| Finding | Статус |
|---------|--------|
| P2-1 (зависимость Chat→Roleplay) | **Закрыт для production-кода** — гrep не находит импортов Roleplay в production-файлах Chat. Осталось только `Mock/mockChat.ts` (фикстуры — правило 28 допускает) |
| P2-2 (applySyncResponse без лимита) | Открыт |
| P2-3 (openChat не догружает историю) | Открыт |
| P2-4 (нет error/retry состояний, F17) | Открыт |
| P3-1 (ChatList `!`-assertions) | Открыт |
| P3-2 (ChatBar: дубль сортировки + хардкод game/game_discussion) | Открыт, хардкод стал актуальнее после плагинной модели |
| P3-3 (ChatMessenger ~296 строк) | Открыт, вырос до ~301 |
| P3-4 (renderedMessages — мёртвый слой) | Открыт |
| P3-5 (ChatSyncService в сторе) | Открыт |
| P3-6 (markChatRead fire-and-forget) | Открыт |
| P3-7 (UserProfileSlider: чувствительные поля + тихий catch) | Открыт |
| P3-8 (MessengerTabs: tabUnread дважды) | Открыт |
| P3-9 (функции в Constant/) | **Закрыт** рефакторингом |
| P3-10 (дубль источников иконок) | **Закрыт** рефакторингом |
| P3-11 (неизвестная команда отправляется текстом) | Открыт |
| P3-12 (AppShell импортирует ChatBar/ChatSlider) | Открыт |

## Findings (новые, от рефакторинга)

### P2

**P2-5. Unused imports — линт падает.** **ЗАКРЫТ** (решение A).
- `Store/chat.ts` — убран `getAttachmentProcessor`.
- `Interface/IAttachmentProcessor.ts` — убран `ChatAttachment`.

### P3

**P3-13. Мёртвая inline-renderer-инфраструктура.** **ЗАКРЫТ — реализован полный F35**
(решение пользователя: не удалять, а довести). Реализовано:
- `Utils/inlineContent.ts` — `parseInlineContent(content): InlineSegment[]`; синтаксис
  токенов `[[type:param1,param2]]` (напр. `[[user:ivan]]`, `[[rule:melee-fighting]]`).
- `Interface/IRenderer.ts` — единый тип `{ type, component }`; удалены
  `IContentRenderer.ts`/`IInlineRenderer.ts` (P3-16).
- `ChatMessenger.vue` — рендер сегментов: text → span, token → `getInlineRenderer(type)`.
- `ChatUserChip.vue` — принимает `segment`, резолвит юзера по login, клик →
  собственный `UserProfileSlider`; при отсутствии — placeholder «Объект скрыт».
- `Roleplay/Rule/Component/RuleChip.vue` + `RuleSlider.vue` — чип правила (резолюция
  по `code` через `getRuleApi().getRules(0)`) + слайдер просмотра; регистрация
  `registerInlineRenderer({ type: 'rule' })` в `Rule/init.ts` (rule может зависеть от Chat).
- Пикер в `ChatInput.vue`: кнопка mdi-link-variant → v-menu; реестр
  `ITokenSource` (`registerTokenSource`/`getTokenSources`) — источники 'user' (Chat,
  т.к. Core/User не может зависеть от Chat) и 'rule' (Rule); вставка `[[type:value]]`
  на позицию курсора.
- Mock: демо-токены в contentPool (`[[rule:melee-fighting]]`, `[[user:admin]]`).
- Тест `__tests__/Utils/inlineContent.test.ts` (6 кейсов).

**P3-14. Ленивая инициализация BASE-типов.** **ЗАКРЫТ** (решение: BASE императивно).
`chatTypes = [...BASE_CHAT_TYPES]`, `chatTabs = [...BASE_CHAT_TABS]`; `initChatFeatures`
и флаг удалены. Порядок детерминирован (BASE первым).

**P3-15. `DiceRollResult.vue:8` — каст `as DiceRollResult`.** Открыт (см. план).

**P3-16. `IContentRenderer`/`IInlineRenderer` идентичны.** **ЗАКРЫТ** — единый
`IRenderer`; оба реестра (`registerContentRenderer`, `registerInlineRenderer`)
принимают его.

**P3-17. `ChatMessenger.vue`: `getContentRenderer(att.type)` дважды.** Открыт (см. план).

## Что намеренно не блокировало

- `mockChat.ts` импортирует `ROLL_ATTACHMENT_TYPE` + `DiceRollResult` из Roleplay —
  фикстуры, правило 28.
- `Store/chat.ts` экспортирует `chatIcon`/`chatColor` (pass-through на `getChatIcon`/
  `getChatColor`) — консьюмеров нет; снять при заходе P3-4.
- `ChatToolbarContext` в `Dto/` содержит функции — пограничный случай (контекст
  тулбара, не данные); принято.
- `RuleChip` резолвит правило через `getRules(0)` — в mock spaceId игнорируется;
  для real-бэка потребует контекст пространства (отмечено как прототип-ограничение).

## Верификация волны

После правок: `vue-tsc --noEmit` чисто; `vitest run` 229/229 (23 файла);
`npm run lint` чисто; `npm run format:check` чисто; `vite build` OK.
Порт 3000 — пользовательский vitest MCP-сервер (не занимаем).

## Группа 2 — Store + виртуализация (закрыта)

**Решение пользователя:** сделать полноценный чат с виртуализацией сразу; выбран
`@tanstack/vue-virtual` (headless), `MAX_STORED` 100 → 500.

Реализовано:
- **P2-3**: `ChatState.initialized`; `openChat` догружает первую страницу + `mergeMessages`
  (дедуп по id) если state создан синком; повторное открытие не перегружает.
- **P2-2**: `applySyncResponse` капит **неактивные** чаты `slice(-MAX_STORED)`.
- **P2-4**: `chatsError`/`chatError`/`actionError` + UI повторов в `ChatMessenger`/`ChatInput`.
- **P3-4**: `renderedMessages` удалён; `ChatMessenger` → `allMessages`; также сняты
  `chatIcon`/`chatColor` из экспорта стора (без консьюмеров).
- **P3-3**: список вынесен из `ChatMessenger` (~300 строк) → `ChatMessageList.vue` +
  `ChatMessageRow.vue` + `Composables/useChatVirtualScroll.ts`.
- **Виртуализация**: `useVirtualizer` (`anchorTo:'end'`, `followOnAppend`,
  `scrollEndThreshold:40`, `overscan:8`, `estimateSize:48`, `measureElement`),
  рендер `translateY(start)`, подгрузка вверх при `scrollTop<=120`.
- **mock**: `SYNTHETIC_COUNT` 200 → 2000.
- **Тесты**: 7 новых (P2-2/P2-3/P2-4), всего 229/229.

## Замечания пользователя (закрыты)

- **Превью последнего сообщения в списке чатов.** `ChatList` показывал сырые токены
  (`[[user:elena_m]]`). Добавлен `IRenderer.describe(segment)` (человекочитаемая подпись)
  + `inlineContentToText(content)` в `Utils/inlineContent.ts`; `ChatList` рендерит превью
  через `inlineContentToText`. Чипы в превью не выводятся (плоский текст).
- **`UserProfileSlider` вынесен в `Core/User/Component`** (общий компонент: используется
  `ChatMessenger` и `ChatUserChip`). Импорты обновлены, старый путь удалён.
- **`IRenderer.describe` для правила отдаёт `name`**, а не `code`: добавлен реактивный
  `Service/ruleCatalogCache.ts` (`ruleCatalog` + `ensureRuleCatalog`), заполняется при
  `registerRuleModule` и в `RuleChip`; fallback — `code`.

### TODO (Roleplay) — логика чипов правил (по решению пользователя)

Доработать резолюцию правил по контексту чата (TODO в `ruleCatalogCache.ts`):
- обычные чаты → правило из **последней ревизии (не draft)** пространства
  «Актуальные правила»;
- чаты по персонажу / по игре → правило из ревизии, к которой относится
  персонаж/игра.
Сейчас каталог — единый плоский список `getRules(0)` (mock игнорирует spaceId),
контекст ревизии не учитывается.

## Осталось открытым (следующие группы ревью)

1. UI: P3-17 (getContentRenderer дважды — в `ChatMessageRow`), P3-1 (ChatList `!`),
   P3-2 (ChatBar), P3-8 (MessengerTabs).
2. Мелочи: P3-6 (markChatRead), P3-11 (команды), P3-5 (ChatSyncService),
   P3-7 (UserProfileSlider — чувствительные поля + тихий catch), P3-12 (AppShell),
   P3-15 (каст DiceRollResult).
