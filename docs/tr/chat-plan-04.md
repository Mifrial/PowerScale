# План Chat 4 — Vue real

**Статус:** сделано, 2026-09-04. Нарезка — [`chat-roadmap.md`](chat-roadmap.md). Канон UI — [`chat-system.md`](chat-system.md). HTTP — [`chat-plan-02.md`](chat-plan-02.md). SSE — [`chat-plan-03.md`](chat-plan-03.md) §9 (контракт транспорта; код Vue — этот файл). Правила фронта — `draft-front_1.2ds/frontend-rules.md`. Конвейер — [`architecture.md`](architecture.md).

Цель: при `VITE_API_MODE=real` чат ходит в четыре action и в `GET /api/chat/sync`. Mock poll не удалять. PHP не трогаем. Плагины Game / visibility / unread — не этот заход.

## Парадигма

Сервер шага 3 уже держит поток. Дыра на фронте: `ChatSyncService` в `sse` сам клеит `EventSource(baseUrl + '/api/chat/sync')` — двойной `/api`, Chat знает Apache, `EventSource` не читает 400 `AUTH_REQUIRED` / `CHAT_INVALID`. `ChatApi` зовёт несуществующие `chat.getMessages`, `chat.getTotalMessageCount`, `chat.sync` и шлёт ISO/`speaker`/`thread`. DTO чата — строки ISO, JSON PHP — unix int, как User.

Значит:

1. **Два транспорта Engine.** `runAction` = POST `/run?action=` + CSRF + конверт. Живой поток = `openSse` GET относительно `baseUrl`, cookie, без CSRF, без конверта. Второй `runAction` под SSE не заводить и `chat.sync` action не появится.
2. **Граница дат — unix.** Как User (`registered: number`), не ISO в сторе «чтобы DateTime не трогать». Мок тот же контракт (`frontend-rules`: моки = контракт).
3. **Клиент подстраивается под сервер**, не наоборот. Страница — `chat.findMessagePage`. Лишние ключи send → `INVALID_PARAMS`. Курсор SSE — пара `(since, afterId)`, не одна ISO-строка.
4. **Не тащить шаг 5.** `unreadCount` 0, без `lastMessage`. `updateMessageVisibility` / `sendSystemMessage` на real не чинить (PHP нет; Game остаётся на mock).

## Термины

| Термин | Смысл |
|---|---|
| Live | Первый коннект: в query **нет** `since`. Сервер шлёт hello. |
| Cursor | Пара `{ since: number, afterId: number }` из последнего принятого кадра. `since=0` слать. `afterId=0` ключ не слать. |
| Кадр | JSON `event: sync`: `{ now, afterId, chats, newChats, messages }`. Не `ActionResponse`. |
| `openSse` | GET-поток Engine: путь как у `runAction` (`/chat/sync` → `/api/chat/sync`). |

## Решения

### 1. Engine / HttpClient: поток, не EventSource

`HttpClient` остаётся один транспорт. Additive GET:

- `credentials: 'include'`; **без** `X-CSRF-Token`; `Accept: text/event-stream`.
- Статус **не** 2xx: тело целиком как JSON (как `post`). `400 AUTH_REQUIRED` — тот же `onUnauthorized` / login, что у action; **`onError` потока не звать** (Chat иначе начнёт backoff на уже уходящей странице). Иной 400 — `onError` с `error.code` (`CHAT_INVALID` и прочие), не голая строка.
- 2xx: `ReadableStream`, разбор SSE (`event` / `data`; несколько `data` склеивать; строка `: …` — ping, выкинуть). Кадр с `event === 'sync'` → `onEvent('sync', parsedJson)`. Иное имя события — игнор.
- Мусорный JSON в `data` — не `onError` канала (как сейчас в ChatSyncService).
- `close()` / abort слайдера — **не** ошибка канала: не `onError`, не `retrying`. Иначе disconnect выглядит как обрыв и сервис ретраит.
- Нативный auto-reconnect `EventSource` не использовать.

`Engine.openSse(path, query, handlers): { close() }`. Хендл **сразу**, как EventSource: `openSse` **не бросает**. 400, сеть, не-JSON 502, конец потока — только `onError`. `close()` / abort — тишина. Path относительно `baseUrl` (`/chat/sync`). Query: только ключи с значением `!== undefined` / `!== null`, `encodeURIComponent`. **`0` сериализовать** (`since=0` — эпоха). Не `if (value)` — иначе live-сброс и эпоха схлопнутся в hello. Engine **не** знает смысл `afterId=0`; ключ не кладёт ChatSyncService (`undefined`).

Именованные типы потока — `Core/Engine/Dto/` (хендлы, query как `Record<string, string | number | undefined>` inline в сигнатуре, без свалки). Chat в Engine не импортировать.

`Engine` сейчас не в локаторе: `main.ts` отдаёт его только в `*Api`. Additive `registerEngine` / `getEngine` / `tryGetEngine(): Engine | null` в `Engine/init.ts` (как CSRF). Mock Engine не регистрирует. Стор выбирает sse **по `tryGetEngine()`**, не по `import.meta.env` в Pinia (иначе тесты стора зависят от Vite-флага). Тесты `openSse` подменяют `fetch`, не глобальный `EventSource`.

Публично: `Engine` + `openSse`; `HttpClient` держит разбор байтов. Не второй клиент «SseClient».

### 2. ChatSyncService: `mode: 'sse'` через Engine

Конфиг: `engine` при sse; `getSyncApi` только poll. `baseUrl` убрать — URL клеит HttpClient.

Cursor сервиса — не `string lastSync`. Dto `ChatSyncCursor`: `{ since: number, afterId: number }` или `null` (live). `connect(cursor | null)`. Hello / кадр: `now` и `afterId` — number; сдвиг только после `isSyncResponse`.

Query sse:

| Состояние | Query |
|---|---|
| `null` (live) | ключей нет |
| `{ since: S, afterId: 0 }` | `since=S` |
| `{ since: S, afterId: A }` A>0 | `since=S&afterId=A` |
| эпоха | `since=0` (ключ обязателен) |

`isSyncResponse`: `now` number (в т.ч. `0`); `afterId` number; `chats`/`newChats` массивы; `messages` объект, не массив. Лишний ключ не отвергать. Текущий тест «`now: 1` — мусор» **сменить** — это легальный кадр.

Канал: `onEvent` с `event !== 'sync'` игнор; `onError` без `CHAT_INVALID` → `retrying`, cursor не двигать, backoff `1s→2s→…30s`, `retryNow` / reconnect с **тем же** cursor. `AUTH_REQUIRED` до `onError` не доходит.

`CHAT_INVALID` на открытии (кривой курсор): **не** крутить ту же пару. Сброс cursor на `null` (live) и один reconnect. Повторный `CHAT_INVALID` на live — стоп, `retrying`, баннер; `retryNow` снова live.

Poll mock: `IChatApi.sync(since: number)` (не string ISO). `now` / `afterId` в ответе — unix number; мок `since` может игнорировать, как сейчас. Интервал 5 с. Строка плана 3 «poll ISO не ломать» этим шагом снята: моки = контракт.

`EventSource` из Chat и из тестов ChatSyncService выкинуть.

### 3. Стор: upsert из `chats`, курсор пары

`startSync`: `tryGetEngine()` не null → `mode: 'sse'` + этот engine; иначе poll + `getSyncApi`.

`applySyncResponse`:

- cursor стора = `{ since: now, afterId }` кадра (и hello).
- `newChats` — как сейчас (нет в списке → push; гость + private — skip).
- `chats` — **upsert**: есть id → `mergeSyncedChat`; нет id → push (сообщения нового членства приходят в `chats`, не только в `newChats`). Без этого слайдер не увидит чат, куда добавили с сообщениями.
- `messages` — merge по id; PHP страница `created_at DESC`, в сторе после merge — **ASC** числовой `createdAt`, затем `id` (не `localeCompare` ISO).
- Список чатов: `lastMessageAt` unix, sort числом.
- Локальные даты в сторе (сейчас `joinedAt: new Date().toISOString()` при авто-членстве после send) — тоже unix, не ISO.

`loadedCount` — сумма длин **страниц HTTP**, не длина массива после sync. Sync merge `loadedCount` не увеличивает: иначе offset перескакивает и в истории дыра. Рост ленты сверху даёт overlap страниц — merge по id это съедает.

`hasMore`: короткая страница (`items.length < PAGE_SIZE`) или пустой added — конец. Не `loadedCount < total` с первой загрузки: `total` после SSE/send устаревает, отрежет хвост истории.

### 4. `ChatApi` real: четыре action

| Сейчас Vue | Сервер | Этот шаг |
|---|---|---|
| `chat.getChats` | `chat.getChats` | как есть |
| `getMessages` + `getTotalMessageCount` | один `chat.findMessagePage` `{ items, total }` | стор `loadChat` / `loadOlder` — **один** вызов |
| `getMessagesBefore` | нет | стор старше не beforeId: `findMessagePage` `offset = loadedCount` |
| `chat.sendMessage` + speaker/visibility/thread | лишний ключ → `INVALID_PARAMS` | JSON только `chatId`, `content`, `attachments` |
| `chat.markChatRead` | есть | как есть |
| `chat.sync` | нет | `sync(since: number)` только mock; `ChatApi.sync` бросает |
| visibility / system | нет | real бросает; Game на mock |

Сигнатуру `sendMessage` на `IChatApi` **не сужать** (Game/мок передают speaker). `ChatApi` real лишние аргументы в JSON не кладёт.

`IChatApi`: additive `findMessagePage(chatId, limit, offset): Promise<{ items, total }>`. Mock оборачивает текущие getMessages+count. Стор и его тесты переходят на него. `getMessages` / `getTotalMessageCount` / `getMessagesBefore` на интерфейсе можно оставить тонкой обёрткой для старых фейков; **стор их не зовёт**. На real `getMessagesBefore` бросает (случайный вызов не должен тихо ходить в `UNKNOWN_ACTION`). Не заводить alias PHP «как Vue».

Ошибки action: `!success` → `Error` с `error.message` (как сейчас). Не глотать.

### 5. Unix в Dto чата

Как User, не параллельный ISO-слой «для UI».

| Поле | Тип |
|---|---|
| `Chat.lastMessageAt` | `number` |
| `MemberInfo.joinedAt` | `number` |
| `ChatMessage.createdAt` / `updatedAt` | `number` |
| `SyncResponse.now` | `number` |
| `SyncResponse.afterId` | `number` |

`lastMessage?` optional строка preview — PHP не шлёт; стор может по-прежнему выводить из сообщения. `unreadCount` остаётся number.

`Core/Engine/Value/DateTime`: additive `fromUnix(unix: number)` (миллисекунды = `* 1000`). Компоненты чата: `DateTime.fromUnix(…).formatRelative()` / `formatTime()`. Конструктор ISO **не** ломать — Notifications ещё строки. Не тащить `User/Utils/formatUnix` в Chat (чужой Utils).

Мок-фикстуры и тесты Chat — unix. Потребители `ChatMessage` вне Chat (складки Game) — тоже unix, не логика Game. `chat-system.md` Real-time / модель сообщения — unix, не ISO.

### 6. Слои

| Тип | Делает | Не делает |
|---|---|---|
| `HttpClient` | GET stream + POST JSON; `AUTH_REQUIRED` | Chat, имя action |
| `Engine.openSse` | путь, query, `{ close() }` | EventSource, CSRF |
| `Engine/init` | `registerEngine` / `getEngine` / `tryGetEngine` | Chat |
| `ChatSyncService` | cursor; poll или `openSse('/chat/sync')`; health | URL Apache, `runAction`, EventSource |
| `ChatApi` | четыре action + findMessagePage | `chat.sync`; лишние ключи send |
| `mockChatApi` | poll `sync(since: number)`, unix | SSE |
| стор | upsert `chats`; offset-страница; sse если `tryGetEngine()` | знать `/api/chat/sync`, `VITE_API_MODE` |
| `.vue` | баннер `retrying` как сейчас | новый транспорт |

`main.ts` real: `registerEngine(engine)` рядом с `registerChatApi(new ChatApi(engine))`.

### 7. Ошибки UI

| Ситуация | Поведение |
|---|---|
| `AUTH_REQUIRED` 400 | login; `onError` нет; Chat не ретраит |
| `CHAT_INVALID` 400 | live reconnect один раз; повтор — баннер |
| обрыв потока / сеть | `retrying`, backoff, тот же cursor |
| мусорный кадр | тишина, cursor стоит |
| `UNKNOWN_ACTION` / прочий action | `Error` в `chatsError` / `chatError` / `actionError` |

Баннер sync не подменяет empty-state списка.

## Todo

- [x] **engine-sse** — `HttpClient` GET+SSE parse; `Engine.openSse`; `registerEngine`/`tryGetEngine`; query сериализует `0`; тесты fetch-stream, ping, `AUTH_REQUIRED` без `onError`, abort без retry. Не EventSource.
- [x] **unix-dto** — Chat/Member/Message/Sync unix; `DateTime.fromUnix`; mock+тесты; sort стора числом; локальный `joinedAt` unix.
- [x] **chat-api** — `findMessagePage`; send без лишних ключей JSON; `sync(since: number)` mock / real бросает; стор loadChat/loadOlder на offset/`loadedCount` + короткая страница.
- [x] **sync-sse** — cursor пары; query live/epoch/reconnect; upsert `chats`; sse если `tryGetEngine()`; poll mock жив. Тесты без FakeEventSource.
- [x] **gate** — `npm run format` → `lint` → `vue-tsc --noEmit` → `npm run test`. PHP/phpunit не гонять. Dev-сервер не трогать.

## Не входит

PHP / Apache / `chat.sync` action. Гость на SSE. HTTP 401. Visibility / unread / `lastMessage` / `see_all`. `sendSystemMessage` / thread / speaker на real. `getMessagesBefore` на PHP. Плагины Game. Хаб сцены / второй SSE. Files. Составной INDEX. Перенос Chat в `Core/`.

## Документы захода

этот файл; [`chat-plan-03.md`](chat-plan-03.md) §9; [`chat-roadmap.md`](chat-roadmap.md) шаг 4; [`chat-system.md`](chat-system.md); [`architecture.md`](architecture.md); [`chat-plan-02.md`](chat-plan-02.md); `frontend-rules.md`; `Engine` / `HttpClient` / `ChatSyncService` / `ChatApi` / `Store/chat.ts`.

## Следующий заход

Visibility / unread / preview — шаг 5, после [`smarttable-plan-17-aggregate.md`](smarttable-plan-17-aggregate.md), не обход `getCountsByGroupIds`.
