# Система Chat

**Статус:** текущий frontend/domain канон, 2026-08-30. Реальный backend transport отделён от mock/polling.

## Граница модуля

Chat — самостоятельный host сообщений. Он не импортирует Roleplay и не знает внутренние DTO Rule, Character или Game.

Прикладные модули подключаются через plugin-регистрацию в публичном `init.ts`; композиция выполняется в `main.ts`. Host объявляет generic opaque-контракт и не импортирует компоненты доноров. Rule, Character и Game могут регистрировать свои вкладки/команды/inline-renderers.

## Типы чатов и сообщения

Тип чата принадлежит модулю-владельцу контекста, например Game или Character. Chat предоставляет инфраструктуру отображения, загрузки, команд и attachment processors.

Сообщение использует универсальную модель:

```text
attachments: ChatAttachment[]
```

`roll` является видом attachment payload. Старые `dice_result` и `send(text, rolls)` не являются текущим контрактом.

Attachment может быть доменным opaque payload, который обрабатывается зарегистрированным renderer/processor. Inline-чипы используют отдельный renderer context с подписями токенов и ключами среза, без импорта внутренних DTO донора.

`IChatApi` предоставляет `getChats`, `getMessages`, `getTotalMessageCount`, `sendMessage(chatId, content, attachments)`, `updateMessageVisibility`, `sendSystemMessage`, `markChatRead` и `sync(since)`. Доменные команды не добавляют поля в базовый `ChatMessage`: результат передаётся через `ChatAttachment`.

## Загрузка и отображение

Frontend хранит состояние инициализации (`initialized`), загрузки, ошибки и retry. Для длинных списков сообщений используется виртуализация. Неактивная история ограничивается cap/pagination, чтобы не создавать тысячи DOM-узлов.

Unread, preview, mark-read, пагинация и обновление списка должны иметь явные состояния ошибки и повторной попытки. Точные поля backend response и retention — `OPEN`.

## Команды и доменные payload

Команды броска кубиков, macros и игровые команды проходят через plugin/command registry. Результат броска передаётся как attachment, а не как специальное поле сообщения.

Game может регистрировать действия, связанные с проверками, боем, loot и inline-контекстом. Chat не исполняет доменную механику самостоятельно.

## Детальный protocol contract

### Plugin host

`Messages/Chat` регистрирует opaque-контракты `IChatType`, `IChatTab`, `ICommandHandler`, content/inline renderers, `ITokenSource`, `IAttachmentProcessor` и `IChatToolbarExtension`. Базовые `private`/`group` и user inline chip принадлежат Chat/Core User. Game регистрирует `game`, `game_discussion`, `/roll`, roll renderer/processor и toolbar; Rule — `[[rule:...]]`; Character — `character_discussion`.

### Message and inline model

```typescript
interface ChatMessage {
  id: number;
  chatId: number;
  userId: number;
  content: string;
  attachments: ChatAttachment[];
  createdAt: string;
  updatedAt?: string;
  thread?: { id: string; parentId?: string; kind: string };
}

interface ChatAttachment {
  type: string;
  payload: unknown;
}
```

Сообщения могут нести `thread?: { id, parentId?, kind }` для свёртки атаки/хода/раунда. Свёрнутая группа — строка-резюме; разворот атаки вверх. По умолчанию раскрыты текущий и предыдущий раунд/ход и идущая атака; завершённая атака складывается. Это frontend current, не Chat→Game import.

Текст разбирается в `InlineSegment[]` с `text`/`token`; syntax — `[[type:param1,param2]]`. Недоступный объект рендерится как «Объект скрыт». `inlineContentToText` используется для preview списка чатов.

### Chat state and pagination

Per-chat state содержит `messages`, `hasMore`, `total`, `initialized`, `loading` и `loadingOlder`. Первая страница при открытии merge-ится с уже пришедшими sync-сообщениями, дубликаты удаляются по id. Older messages prepend-ятся, а `MAX_STORED=500` ограничивает память.

Виртуализация использует `@tanstack/vue-virtual`: anchor to end, follow-on-append только если пользователь у конца, threshold 40, overscan 8 и измерение динамической высоты. Загрузка вверх срабатывает при `scrollTop <= 120`.

### `/roll`

```text
/roll Nd6[:efficiency] [adv:N] [dis:N] [size:N] [label:text]
```

Ограничения parser: кубы ≤ 30, adv/dis ≤ 10, efficiency ≤ 20, die size 2..100 и `|size| ≤ 10`. Для d6: `1 → 2 успеха`, `[2..efficiency] → 1`, `[efficiency+1..5] → 0`, `6 → -1`. Advantage добавляет кубы и убирает худшие, disadvantage добавляет кубы и убирает лучшие. Roll modifier не используется.

Результат передаётся attachment `type: 'roll'`; renderer показывает карточку. Механики ревизии остаются источником полной runtime-семантики, а перечисленный алгоритм — базовый parser/mock fallback.

### Macros

Macro — optional text template и/или один или несколько rolls. Валиден при непустом имени и хотя бы одном содержательном компоненте. Каждый roll имеет formula, efficiency, adv, die size, optional label и `variable_adv`. При variable advantage открывается единый диалог; введённое значение применяется только к отмеченным броскам.

## Real-time

Старая документация описывает SSE как целевой transport. Mock/polling и фактическая готовность backend не должны описываться как SSE-ready без доказательства.

До подтверждения backend-контракта имеют статус `OPEN`:

- SSE authorization;
- reconnect и повторная доставка;
- visibility filtering;
- retention;
- ordering;
- unread synchronization;
- редактирование/удаление сообщений.

### Целевой SSE protocol

В mock используется polling с интервалом 5 секунд. Целевой backend transport — одно SSE-соединение на весь sync:

```text
/api/chat/sync?since=ISO_TIMESTAMP
```

Payload содержит `now`, chat summaries, `newChats` и messages. `now` используется как следующий since, heartbeat отправляется после 60 секунд тишины, reconnect продолжает с последним since. Pagination сортируется по `created_at DESC`, sync — по `updated_at ASC`; нужны индексы `(chat_id, created_at)` и `(chat_id, updated_at)`.

### Error/retry contract

Текущее frontend-поведение `ChatSyncService`: polling выполняет немедленный запрос и затем повторяет его по интервалу; исключение polling сейчас пропускается, а следующий тик повторяет запрос. Это `CODE_GAP` относительно production-ready error state: ошибка не отображается пользователю, не передаётся через typed callback и не использует backoff.

Целевой контракт: polling должен сохранять последний успешный cursor, переводить sync в наблюдаемое `error`/`retrying` состояние и повторять запрос с ограниченным backoff; SSE должен обрабатывать `onerror`, закрытие соединения и reconnect с последним cursor. Ни polling, ни SSE не считаются реализованными server-side transport без backend evidence.

Visibility задаётся `all`, `forRole` или `forUsers`. GM/owner с `see_all` видят всё; остальные получают только доставленные им сообщения. Скрытые сообщения не участвуют в unread и preview. Отправитель видит своё и может менять visibility своего сообщения. Partial-text spoiler — отдельный backlog.

## Visibility и модерация

Права доступа к чату и сообщениям проверяются в контексте пользователя, игры, роли и объекта. Частичные spoiler-сегменты и серверная фильтрация не считаются завершённым контрактом, пока формат не подтверждён.

Модерация loot связана с Game/economy, но Chat показывает её результаты через attachments/plugin-контракт. Старую модель прямой зависимости Chat → Game не возвращать.
