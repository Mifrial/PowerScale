# План Chat 3 — SSE sync

**Статус:** сделано, 2026-09-04. Нарезка — [`chat-roadmap.md`](chat-roadmap.md). Канон UI — [`chat-system.md`](chat-system.md) (`sync` action и ISO **не** копировать слепо). HTTP — [`chat-plan-02.md`](chat-plan-02.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Конвейер — [`architecture.md`](architecture.md). Актор — [`user-plan-03-http.md`](user-plan-03-http.md). ST `>` — [`smarttable.md`](smarttable.md).

Цель: живой поток `/api/chat/sync`. Cookie, без CSRF. Кадр тех же Chat/Message, что HTTP. **`chat.sync` не action.** Vue не трогаем (шаг 4). Visibility / unread / preview — шаг 5.

## Парадигма

PHP-FPM держит **один воркер на открытый слайдер**, пока жив EventSource. Это главная цена SSE без брокера, её не спрятать циклом 1 с. Значит:

1. **Тик дешёвый.** Не `assembleInbox` (три пачки на все чаты). Не полные строки членства каждый раз. Не периодический пустой JSON.
2. **Простой — не костыль.** Фильтр 500 Record в PHP, чтобы не трогать репозиторий на лимите 10 — это обход линтера, его выкинут. Либо существующий метод id, либо новый тип с своей осью, не `phpcs:disable` «пока».
3. **Контракт кадра стабильный.** Keyset `(since, afterId)`, те же Chat/Message, что HTTP. Когда воркеры кончатся — отдельный план notify (не этот шаг); **кадр и пара курсора не меняются**. Не брокер «на всякий случай» и не poll 1 с «потом заменим».
4. **Надёжный cursor.** Не одна unix-секунда: в одной секунде много сообщений, limit 500 иначе отрежет хвост. `now` в кадре — **timestamp последней отданной строки**, не стена часов (иначе дыра `(last.ts, H]`). Пустой query — live + один hello. Дальше JSON только при дельте.
5. **Не грузить то, чем шаг не владеет.** markRead не двигает `chat.updated_at` (чужой inbox не всплывает). Чужой таб и `lastRead` без `chat_member.updated_at` честно не синхроним — колонку «про запас» не заводим.

Потолок этого шага: десятки одновременных слайдеров, тик = узкие `getList`. Сотни воркеров — операционный предел `pm.max_children`, не повод городить Redis в Chat 3.

## Термины

| Термин | Смысл |
|---|---|
| Кадр | JSON `event: sync`: `{ now, afterId, chats, newChats, messages }`. Не `ActionResponse`. |
| Cursor | Пара `(since, afterId)`: unix int UTC + bigint id. Query и кадр — одна пара. |
| Тик | Горизонт `H` только верх выборки; emit **только если есть дельта**, sleep. |
| Heartbeat | SSE-комментарий `: ping` раз в ~60 с без MySQL. Не кадр, cursor не двигает. |

## Решения

### 1. Entrypoint, не action

`www/mifrial/API/chat-sync.php` рядом с `action.php`.

```apache
RewriteRule ^api/chat/sync$ mifrial/API/chat-sync.php [L,QSA]
```

`.htaccess` сейчас пускает только `API/action.php`. В allowlist **оба**, иначе 403.

GET, cookie `mifrial-session`. CSRF **не** проверяем: поток только читает; запись — `chat.sendMessage` / `markChatRead` с csrf. Сессия `SameSite=Lax` — чужой сайт cookie на этот GET не приложит. CORS на URL не ставить (иначе чужой JS мог бы читать кадры). Fetch *может* послать `X-CSRF-Token` — сервер его не требует и не сверяет: иначе контракт привязан к заголовку, а защита мутаций уже на action. Не POST.

Не `chat.sync` в `routes`. Сцена — другой entrypoint.

Цепочка: `boot` → `prepareHttp` → `get(IChatContainer)` → порт цикла. Kernel не `use` Chat.

Фронт **не** зовёт `runAction('chat.sync')`. Пара транспортов Engine: JSON-action и SSE. Контракт — §9; код Vue/Engine — шаг 4.

### 2. Kernel: запрос без JSON-exit

`handle` = cookie + актор + CSRF + dispatch + `emitJson` + `never`. SSE так нельзя.

Additive `IApplication::prepareHttp(IHttpRequest): void` (5-й public) — `beginRequest` (cookie в контекст) + `bindRequestActors`. Без CSRF, без JSON-exit. `handle()` зовёт `prepareHttp`, затем маршрутизацию action; `bindRequestActors` второй раз не дублировать.

`Kernel\Http\SseEmitter` (не хаб): `text/event-stream`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`; `writeEvent`; `writeComment`; `flush`. Не знает Chat. Battleground потом этот тип, не этот заход.

`ignore_user_abort(true)`, `set_time_limit(0)`. Сбой тика (MySQL) — закрыть поток, клиент reconnect; не супервизор внутри цикла.

### 3. Актор

`IUserAccess::requireActor()`. Нет актора → **до** потока: 400 JSON `AUTH_REQUIRED`. Не 401. Bypass чужие чаты не открывает.

Несколько соединений на `userId` — норма. Лок «одно SSE» не заводим (хуже для вкладок, не легче для сервера).

### 4. Cursor: unix + id, live

`DateTime` — unix **секунды**. Несколько сообщений в одну секунду — норма. Курсор только из `since` + `updated_at > since` при limit 500 **теряет хвост** той же секунды. Это контракт шага, не хвост.

Query (GET): `since` unix, `afterId` bigint (нет ключа → 0). ISO не парсим. Кривые значения → 400 JSON `CHAT_INVALID` до потока. **`since=0` — эпоха, не «пусто».** Пусто — нет ключа / `''`.

Нет `since` (первый коннект): `H = clock.now()`, **один** кадр `{ now: H, afterId: 0, chats: [], newChats: [], messages: {} }`. Клиент запоминает пару. Без этого reconnect снова live с обрыва.

Есть `since`: hello нет, курсор с query (`afterId` нет → 0), сразу выборка (догонялка). Первый тик reconnect: `S_prev = S` (все текущие чаты не «новые»; inbox уже дал `getChats`). Чат, куда добавили за простой **без** сообщений, в кадре не появится — до следующего `getChats` или сообщения. Сообщения нового чата приходят в `messages` + `chats`; клиент шага 4 **upsert** чат из `chats`, не только из `newChats`.

Тик, нижняя граница **исключительная** `(since, afterId)`, верх `updated_at <= H`. В ST — `DateTime::fromUnix`, не сырой int в datetime-фильтр. `chat_id => $S` это IN (как `getByIds`). Пустой `S` — запрос сообщений не слать.

```text
filter AND:
  chat_id IN S
  updated_at <= H
  OR:
    updated_at > since
    AND: updated_at = since, id > afterId
sort updated_at ASC, id ASC
limit 500
```

Вложенный LOGIC, один getList. После тика:

- 0 строк → кадра нет; **курсор в процессе не двигать** (не `(H, 0)`: иначе reconnect и следующий тик теряют `(last.ts, H]`).
- есть строки (хоть 1, хоть 500) → `now` / `afterId` = `updated_at` и `id` **последней строки**, всегда. Не подставлять стену `H` к чужому id.

Кадр несёт `afterId`: шаг 4 шлёт оба query. Текущий Vue `isSyncResponse` лишний ключ не отвергает. Пока фронт poll — неважно.

Живой TCP: `: ping` ~60 с, без MySQL. Обрубок `{"now"}` не шлём. Apache: `Timeout` на этот URL не дефолт 60 с; gzip/deflate на поток выключить (иначе буфер).

### 5. Кадр

Ключи как `SyncResponse`, тела как HTTP шага 2:

| Ключ | Смысл |
|---|---|
| `now` | unix `updated_at` последней строки кадра; hello — стена `H` |
| `afterId` | bigint, 0 после hello; иначе `id` той же последней строки |
| `chats` | Chat[] по id, где в этом кадре есть сообщения, и id **был** у актора до тика; не пересекается с `newChats` |
| `newChats` | Chat[] по id, которых не было в наборе членства **этого соединения** до тика |
| `messages` | `chatId → Message[]`; sort `updated_at` ASC, `id` ASC |

Источник дельты — **новые сообщения** в чатах актора и **новые id членства**, не скан `chat.updated_at` по всему inbox и не `getByUserId` Record.

Chat/Member/Message — тот же assembler, что HTTP: unix, `unreadCount` 0, без `lastMessage`. Username пачкой `getByIds`. Не звать `assembleInbox`.

### 6. Тик: мало запросов

`IChats` не расширять. `send` уже пишет `chat_message.updated_at` и `chat.updated_at`.

Каждый тик:

1. `getChatIdsOfUser` (уже есть, select id, ≤500) — набор `S`. Пустой `S` → сообщений нет; всё равно запомнить `S`.
2. `newChatIds = S \ S_prev` (`S_prev` в памяти соединения, не ST). Первый тик: `S_prev = S`, `newChatIds = []` (не объявлять все чаты «новыми» при открытии слайдера — их уже дал `getChats`).
3. Сообщения: `getUpdatedSince($S, $since, $afterId, $H, 500)` — пустой `S` без запроса. Keyset как в §4.
4. JSON чатов только для `newChatIds ∪ chatIds(messages)`: assembler по **этому** множеству id (открыть сейчас private `assembleChats` / узкий public из id), не `assembleInbox`. Username авторов — `IUserAccounts::getByIds` пачкой, как HTTP.

`S_prev = S` после тика.

Членство: **нового public нет**. Id-список уже есть. Не грузить 500 MemberRecord чтобы отфильтровать `joined_at` в PHP.

Сообщения: один additive `getUpdatedSince` (keyset в репозитории, не в HTTP-слое). Отдельный since на **чатах не нужен**.

Интервал sleep: после **пустого** тика 2 с, потолок 5 с (2→4→5). После **непустого** кадра сброс на 2 с. Не 1 с всегда. Не 5 с всегда (тогда «живой» поток не отличить от poll). Константы в одном месте сервиса, не `local.php`.

Не unread. Не `getCountsByGroupIds`. Составной INDEX — когда появится в ST, накат картой; запрос тот же.

### 7. Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `chat-sync.php` + rewrite | Apache / entry | GET поток | CSRF, `ActionResponse` |
| `prepareHttp` + `SseEmitter` | Kernel | cookie+актор; байты | `use` Chat |
| `ChatSseService` | `Service/` | актор, тик, backoff, abort | `ListQuery`, inbox dump |
| `ChatViewAssembler` | шаг 2 + public из **подмножества** id | JSON unix | полный inbox на тик |
| `ChatPortFactory` | `createSse` | как `createHttp` | сценарий |
| `ChatMessageRepository` | `getUpdatedSince` | keyset + IN; 6-й public | unread |
| `IChats` | как есть | `getChatIdsOfUser` | `sync()` |

Порт: `ChatSseService`. Ctor ровно 6: `IUserAccess`, `IChats` (только id членства), assembler, message repo, `IUserAccounts`, **один** clock/sleeper (`now` + `sleep`). Три репозитория + accounts + clock не влезают. `DateTime::now()` в цикле не звать — иначе mysql-тест не сдвинет горизонт. Цикл: `run` / `tick` / sleep — отдельные методы, иначе phpcs 30 строк / CC.

`routes` без `chat.sync`.

### 8. Ошибки

| Код / HTTP | Когда |
|---|---|
| `AUTH_REQUIRED` 400 JSON | нет актора, до потока |
| `CHAT_INVALID` 400 JSON | кривой `since` / `afterId`, до потока |
| выход | `connection_aborted` |
| `INTERNAL` | до потока — как action; в цикле — лог и закрыть, не полукадр |

`AUTH_DENIED` нет. CSRF на URL нет.

### 9. Фронт: `Engine.openSse`, не `runAction`

`runAction` = POST `${baseUrl}/run?action=…` + CSRF + конверт `ActionResponse`. Поток чата — другой вход: GET, cookie, без CSRF, без конверта, живёт минутами. Второй `runAction` под это не подходит и `chat.sync` action не появится.

Сейчас `ChatSyncService` в sse-режиме сам делает `new EventSource(baseUrl + '/api/chat/sync')`. Это дыра слоя: `baseUrl` уже `/api` (как у `runAction` → `/api/run`), путь склеится в `/api/api/chat/sync`; Chat знает URL Apache; `EventSource` не читает тело 400 (`AUTH_REQUIRED` / `CHAT_INVALID`) — редирект логина из `HttpClient` не сработает. Нативный auto-reconnect `EventSource` ещё и конфликтует с backoff сервиса (сервис и так `close` на `onerror`).

Шаг 4:

| Слой | Делает | Не делает |
|---|---|---|
| `HttpClient` | GET `${baseUrl}${path}?…`, `credentials: 'include'`, без CSRF-заголовка; разбор SSE (`event` / `data`; `: ping` выкинуть); 400 JSON — тот же `AUTH_REQUIRED` → login, что у `post` | Chat, `ActionResponse`, POST |
| `Engine.openSse` | как `runAction`: путь относительно `/api`, query, handle `{ close() }` | имя action, тело JSON |
| `ChatSyncService` | `mode: 'sse'` → `engine.openSse('/chat/sync', query, { onEvent, onError })`; cursor; backoff; `event === 'sync'` → JSON кадра | `EventSource`, строка URL, `runAction` |
| `ChatApi` / `IChatApi` | `getChats` / страница / send / markRead через `runAction` | `sync()` в real; mock poll оставляет `sync` |

Вызов (шаг 4):

```ts
engine.openSse(
  '/chat/sync',
  {
    since: cursor.since,      // ключ не слать, если live / нет курсора
    afterId: cursor.afterId,  // не слать, если 0
  },
  {
    onEvent: (event, data) => { /* event === 'sync' → кадр */ },
    onError: (error) => { /* канал; AUTH_REQUIRED уже увёл на login */ },
  },
);
```

Итоговый URL: `/api/chat/sync` (+ query). Не `/api/run`. `since=0` слать (`0` — эпоха). Пустой `since` — нет ключа (hello). `AbortSignal` / `close()` = `disconnect` слайдера.

Почему **fetch-stream в Engine**, не `EventSource` в Chat: один `baseUrl`/cookie/`AUTH_REQUIRED`; сцена потом тот же `openSse('/…')`, не второй копипаст; тест Engine подменяет `fetch`, Chat не мокает глобальный `EventSource`.

`isSyncResponse`: `now` — unix number, `afterId` — number; лишний ключ не отвергать. Poll mock — тот же unix-кадр; ISO `since` снимает шаг 4 ([`chat-plan-04.md`](chat-plan-04.md)).

`CHAT_INVALID` на открытии (кривой курсор): не крутить тот же query; сброс на live (без `since`) или стоп+баннер — шаг 4, не этот PHP.

Код Engine/Vue в **этом** заходе не пишем.

## Todo

- [x] **kernel-http** — `prepareHttp`; `SseEmitter`; Kernel-тест без Chat.
- [x] **apache** — rewrite + allowlist двух PHP; `Timeout` + без gzip на `/api/chat/sync`; README строка.
- [x] **repo-since** — `getUpdatedSince` keyset + `DateTime::fromUnix` + IN; пустой S без запроса; sort ASC; limit 500.
- [x] **frame** — дельта id; `afterId`; `now` с последней строки; `newChats` vs `chats`; assembler из id; unix.
- [x] **loop** — hello только без `since`; иначе догонялка; пустой тик курсор не двигает; backoff 2–5 с; comment 60 с; abort. `chat-sync.php`.
- [x] **tests-quality** — fake clock/sleeper, N тиков без `sleep(2)`; send → кадр; пустой since не dump; два id в одну секунду + limit 1 не теряет второе; `now=H`+чужой afterId **не** контракт; reconnect с парой; cs/quality Chat+Kernel. Vue нет.

## Не входит

Код Vue / `Engine.openSse` / ISO / `mode: 'sse'` (контракт §9 — этот файл, реализация — шаг 4). `chat.sync` action. Гость. HTTP 401. Visibility / unread / `lastMessage`. `lastRead` с другого таба (нет `member.updated_at`). Составной INDEX. 11-й метод `IChats` / членства. Брокер. Хаб. SSE сцены. Files. Колонки «на будущее». `phpcs:disable` на лимит.

Notify/воркеры — **следующий** план, когда упрёмся в `pm.max_children`. Контракт кадра этого файла для него закон.

## Документы захода

этот файл; [`chat-roadmap.md`](chat-roadmap.md) шаг 3; [`chat-plan-02.md`](chat-plan-02.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); `www/.htaccess`; `Application`.

## Следующий заход

Vue real: `Engine.openSse('/chat/sync', …)`; unix `(since, afterId)`; upsert чата из `chats`; `mode: 'sse'`. `ChatApi.sync` / `runAction('chat.sync')` убрать из real. Mock poll не удалять.
