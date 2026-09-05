# План Chat 5 — visibility, unread, preview

**Статус:** сделано, 2026-09-04. Нарезка — [`chat-roadmap.md`](chat-roadmap.md). Канон UI — [`chat-system.md`](chat-system.md) (имена `IChatApi` / `forRole` / `see_all` **не** копировать слепо). HTTP — [`chat-plan-02.md`](chat-plan-02.md). SSE — [`chat-plan-03.md`](chat-plan-03.md). Vue real — [`chat-plan-04.md`](chat-plan-04.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Фронт — `draft-front_1.2ds/frontend-rules.md`. Агрегат — [`smarttable-plan-17-aggregate.md`](smarttable-plan-17-aggregate.md). Антипаттерн COUNT — [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md) `getCountsByGroupIds`.

Цель: скрытые сообщения не попадают в ленту, SSE, unread и preview. Unread/preview — `aggregate` + `SubqueryValue` на `last_read`, не JOIN в FROM и не страницы членств по 500 в PHP. **`chat.sync` не action.** Spoiler-сегменты, `sendSystemMessage`, thread/speaker — не этот заход.

На диске: аудитория `all`/`users`; `chat.updateMessageVisibility`; unread/preview через `aggregate`.

## Термины

| Термин | Смысл |
|---|---|
| Аудитория | Кому доставлено: `all` или `users` (список id). Отправитель виден всегда через `user_id`, в mfv его может не быть. Не Vue-мешок `all`/`forRole`/`forUsers` в колонке. |
| Видимое | Строка, которую этот актор имеет право получить: `all`, или он автор, или его id в списке `users`. |
| Unread | Число **видимых** сообщений чата с `id > last_read` актора (`coalesce` 0). Скрытые не входят. Свои видимые входят (как мок). |
| Preview | Последнее **видимое** сообщение: `MAX(id)` при фильтре видимости, затем догрузка строк. Нет видимой строки — нет `lastMessage`, `lastMessageAt` = `created_at` чата. |
| Промах группы | `GROUP BY` не возвращает чат без подходящих строк. `unreadCount` 0 / нет preview дописывает assembler, не ST. |

## Решения

### 1. Не 11-й метод `IChats`

Лимит 10. Inbox уже не на фасаде (шаг 2). Visibility **update** — HTTP + репозиторий, как список чатов.

Фильтр выдачи — внутри **существующих** `findMessagePage` / `markRead` / `send` (optional аудитория) и `getUpdatedSince` (SSE). Сигнатура `send`: пятый аргумент optional аудитория, число public то же.

Не порт `updateMessageVisibility` на `IChats`. Не `IChat`. Сосед по-прежнему не `open` и не `ListQuery`.

### 2. Vue — эскиз; `forRole` / `see_all` — не host private/group

Набросок: `ChatMessageVisibility` = `{ all?, forRole?, forUsers? }`; ГМ с `chat.see_all` видит всё. Сейчас Member JSON — всегда `status: 'member'`, колонки роли нет, Game-чатов нет. Копировать `forRole` в PHP — дефект эскиза ([`php-coding-standards.md`](php-coding-standards.md) § HTTP).

Разбор JSON visibility — **`ChatInputNormalizer::normalizeAudience`**. Только HTTP (`sendMessage` / `updateMessageVisibility`); не копировать в action. `IChats::send` уже принимает `MessageAudience`, JSON не парсит. `forUsers` ⊆ текущие члены чата (автор в список не обязателен); чужой id → `CHAT_INVALID`. Верхние лишние ключи action → binder `INVALID_PARAMS`. Лишние ключи **внутри** `visibility` — нормализатор `CHAT_INVALID` (binder вложенный объект не режет).

| Вход JSON | Сервер |
|---|---|
| нет ключа / `undefined` / `null` / `{ all: true }` / `{}` | `all` |
| `{ all: false, forUsers: number[] }` непустой | `users`; id unique, только члены |
| `{ all: false }` без `forUsers` или `forUsers: []` | `users`, mfv пустой: видно только автору |
| ключ `forRole` (любой, в т.ч. с `forUsers`) | `CHAT_INVALID` |
| `all: true` и ключ `forUsers` (даже `[]`) | `CHAT_INVALID` |
| `all` не bool; `forUsers` не list int; `visibility` не объект; непустой JSON-list | `CHAT_INVALID` |
| speaker / thread / kind на send | `INVALID_PARAMS` (верх тела) |

После `json_decode(..., true)` пустой объект `{}` и пустой list `[]` в PHP оба `[]` — оба `all` (как пустое верхнее тело у binder). Непустой list — `CHAT_INVALID`. Assoc-decode ядра не трогаем.

`see_all` / типы `game` — позже. **Не** разворачивать `forRole` в текущих членов: состав сменится — смысл сломается. Позже: ещё ветка OR + mfv ролей, этот `all`/`users` не трогать. Мок Game не ломаем.

**Меню real.** Host `private`/`group`: только «всем» / участники. `useChatVisibilityOptions`: при `type` `private`|`group` — `roleOptions: []` (роли BASE не выкидывать из типа: задел кика, не аудитория). `private`: `supportsVisibility: true` без `roles`. Game-типы по-прежнему отдают свои `roles`. PHP аудиторию на private принимает.

JSON ответа Message: `{ all: true }` или `{ all: false, forUsers: [...] }`. Не колоночные имена. `Chat.visibility` (public-чат) не заводим.

### 3. Хранение: не JSON `@`

ST `JsonField` в фильтре — только `=` / `!=`. Список id в JSON диалектом не ищешь.

Карта `chat_message` additive (`updateTable`, не `forceUpdateTable`):

| Поле | Тип | Смысл |
|---|---|---|
| `audience` | string required, default `'all'` | `all` \| `users` (нормализатор) |
| `audience_user_ids` | `IntField` **multiple**, **не** required (пустой list ок) | id при `users`; при `all` — **пусто** |

Запись аудитории: `all` → колонка `all` **и** mfv перезаписать в `[]` (не оставить старый список: иначе `@` снова найдёт шёпот). `users` → колонка `users` и mfv = list.

Видимость в SQL — **вложенный** AND (чат / порог unread / keyset SSE) + OR видимости. Тот же helper — фрагмент OR; его вклеивают **соседом** под AND, не внутрь keyset-OR (`>updated_at` / `=updated_at`+`>id`): иначе `audience=all` откроет строки вне курсора. Без внешнего AND корневой OR схлопнет «все `all` во всей таблице».

Unread (схема):

```
LOGIC AND
  chat_id in (…)
  '>id' => SubqueryValue(ChatMemberTable, last_read_message_id, filter: [
      chat_id => OuterColumn(chat_id),
      user_id => $actorId,
  ], coalesce: 0)
  LOGIC OR
    audience = all
    user_id = $actorId
    @audience_user_ids = $actorId
```

Preview / page / since / max: тот же AND **без** `>id` SubqueryValue.

JOIN в FROM нет. `forRole` в SQL нет. Chat **свой** `ChatMemberTable`.

`MessageRecord`: `getAudience()`, `getAudienceUserIds()`. `fromNormalized` требует новые ключи. Ctor Record уже `phpcs:disable` на число полей — +2 свойства, disable не снимать, не плодить Optional.

Старые строки: default `all`, mfv пустой. Setup — `updateTable` (sidecar mfv).

Visibility **меняет только `updated_at` сообщения**. `chat.updated_at` на смене аудитории не трогаем. Send по-прежнему двигает `chat.updated_at` даже при `users`. `getByIds` остаётся `updated_at` DESC — не чинить агрегатом. **UI** (`sortedChats`) уже по `lastMessageAt`: шёпот **не** поднимает строку и не меняет время в списке (§5). Шаг 2 (`lastMessageAt` = `chat.updated_at`) этот шаг **заменяет**.

### 4. Кто меняет и кто видит

- Send: член; аудитория optional, default `all`. Нормализатор §2.
- `updateMessageVisibility`: актор — **автор** и член; иначе **`CHAT_NOT_FOUND`**. Сообщение не из этого `chatId` → `CHAT_NOT_FOUND`. `updated_at` сообщения = `DateTime::now()`.
- Страница / SSE / unread / preview / `markRead` max: только **видимые** этому актору.
- `markRead`: `last_read` = `MAX(id)` **видимых**, не глобальный max. Пустой набор видимых — `null`.
- `getById` после send/update — автор, фильтр видимости не нужен.
- Не член send/page/markRead/update — `CHAT_NOT_FOUND`.

Сжатие аудитории: кто потерял доступ, в page/SSE строки не получает. Клиентский кэш до reload не затираем томбстоуном. Расширение: `updated_at` сообщения → SSE тем, кому стало видно.

### 5. Unread и preview — два `aggregate` внутри одного метода репозитория

Не `getList` страницами и не `getCountsByGroupIds`. `AggregateQuery` только в `ChatMessageRepository`.

Inbox ≤500 чатов. Пустой `in` → без ST. **Без TTL** (getChats/assembler сейчас без кэша; не выдумывать).

Два SQL (разный WHERE), **один** public: например `getInboxStats(chatIds, actorId)` → unread по chat_id + last_id по chat_id. Не два public «на выбор» — иначе с `updateAudience` + `getByIds` упрёмся в 10 (ctor репозитория считается). Промах unread → assembler **0**. Промах preview → нет ключа `lastMessage`; **`lastMessageAt` = `created_at` чата** (не `updated_at` чата и не шёпота: иначе утечка времени; Vue сортирует список по этому полю).

Preview-догрузка: один `getList` `id in` last_id (пустой → `[]`). `lastMessage` = `content` (`''` если только вложения). Если строка есть: `lastMessageAt` = `created_at` этой строки.

`countTotal` ленты — видимые, тот же helper, `getList` + `countTotal`.

### 6. HTTP и SSE

| Action | Изменение |
|---|---|
| `chat.getChats` | живой `unreadCount`; `lastMessage?`; `lastMessageAt` по §5 |
| `chat.findMessagePage` | только видимые; `total` по ним |
| `chat.sendMessage` | optional `visibility`; ответ с `visibility` |
| `chat.markChatRead` | max видимого id |
| **`chat.updateMessageVisibility`** | CSRF true; `chatId`, `messageId`, optional `visibility` (нет ключа = всем) |

Имя action — сервер. Vue `updateMessageVisibility` → этот ключ.

SSE: тот же assembler чатов; `messages` — `getUpdatedSince` с фильтром видимости. Курсор/heartbeat не менять.

`ChatHttpService`: пятый public update. Ctor уже 5 deps — не 7-й. Членство update — `IChats::getMemberIds`, не 6-й порт.

`ChatSseService` ctor уже **6** (порог quality). Актор в `getUpdatedSince` — поле `$userId`, не 7-й dep.

`ChatViewAssembler`: третий dep — message repo (`getInboxStats` + `getByIds`). Не `ListQuery` / `AggregateQuery` в assembler.

`ChatMessageRepository` (public с ctor ≤10, disable не ставить заранее): сейчас ctor+5; плюс `updateAudience`, `getByIds`, **`getInboxStats`** → 9. Зритель/аудитория на существующих `add` / `findPage` / `findMaxId` / `getUpdatedSince` (сигнатура + актор, не новые public). `add`: не два скаляра аудитории — один DTO из нормализатора. Не агрегаты в `Chats`.

`SendMessageInput`: optional visibility (absent = all). `UpdateMessageVisibilityInput`.

### 7. Vue real

`ChatApi.sendMessage`: ключ `visibility` только если `!== undefined`.

`ChatApi.updateMessageVisibility`: `runAction`, не throw.

Host `private`/`group`: меню без ролей (§2). Мок Game `forRole` / `see_all` не выкидывать.

`inlineContentToText` на клиенте. PHP `[[token]]` не парсит.

### 8. Ошибки

| Код | Когда |
|---|---|
| `AUTH_REQUIRED` | нет актора |
| `CHAT_NOT_FOUND` | нет чата / не член / не автор update / сообщение не того чата |
| `CHAT_INVALID` | `forRole`; `all: true`+ключ `forUsers`; id не член; не объект / мусор visibility; пустой send |
| `INVALID_PARAMS` | лишний ключ **верхнего** тела action |

ST наружу не течёт. `AUTH_DENIED` нет.

## Todo

- [x] **schema** — `audience` + multiple; при `all` mfv `[]`; `updateTable`; Record; нормализатор в `ChatInputNormalizer`; mysql default `all`.
- [x] **filter** — один helper AND+OR; page, since, markRead max, inbox.
- [x] **inbox** — `getInboxStats` (два aggregate внутри) + `getByIds`; unread 0; нет preview → omit `lastMessage`, `lastMessageAt` = `created_at` чата; mysql шёпот соседа; coalesce; без TTL.
- [x] **http** — update action; send visibility; JSON `visibility`; не автор → NOT_FOUND.
- [x] **sse** — filter messages; чаты как HTTP.
- [x] **vue** — ChatApi send/update; `private.supportsVisibility`; host `private`/`group` — `roleOptions: []`; мок Game жив.
- [x] **gates** — phpunit `chat` (visibility-mysql отдельно, не раздувать `ChatMysqlTest` >500); cs/quality Chat; фронт format/lint/vue-tsc/test затронутого. Не phpunit User. Не `cs-fix` чужое дерево.
- [x] **добивка** — phpdoc; `[]`/`{}` после decode; mysql: `lastMessageAt` = `created_at` чата при нуле видимых (не `updated_at`); `getUpdatedSince` без шёпота соседа.

## Не входит

JOIN в FROM. PHP COUNT пачкой. `forRole` / `see_all` / колонка роли. Spoiler. `sendSystemMessage` / thread / speaker. `getMessagesBefore`. `chat.sync` action. Delete/edit текста. Томбстоун SSE. Сортировка `getByIds` по MAX видимого времени (остаётся `chat.updated_at` send; UI — `lastMessageAt`). User `getCountsByGroupIds`. Составной INDEX. 11-й метод `IChats`. `phpcs:disable` на лимит заранее. Перенос в `Core/`. Game-чаты. TTL на inbox.

## Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| поля audience | `Table/` | DDL; mfv пустой на `all` | JSON «как Vue» |
| Record / input | `Dto/` | геттеры; JSON visibility в assembler | SQL |
| `ChatInputNormalizer` | `Service/` | JSON visibility → аудитория; члены | SQL |
| `IChats` | как есть | send optional; page/markRead фильтр | update visibility |
| `ChatHttpService` | `Service/` | action update; send visibility | `AggregateQuery` |
| `ChatViewAssembler` | `Service/` | unread 0, lastMessage / lastMessageAt | колонки |
| `ChatMessageRepository` | `Repository/` | helper, `getInboxStats`, mfv | порт соседа |
| `ChatApi` + меню | Vue | action; host без forRole | EventSource |

## Документы захода

этот файл; [`chat-roadmap.md`](chat-roadmap.md) шаг 5; [`chat-system.md`](chat-system.md); [`chat-plan-01.md`](chat-plan-01.md)–[`chat-plan-04.md`](chat-plan-04.md); [`smarttable-plan-17-aggregate.md`](smarttable-plan-17-aggregate.md); [`smarttable-plan-05-multiple.md`](smarttable-plan-05-multiple.md) (`@`); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).

## Следующий заход

Код этого файла сделан. Потом — Game-чат / `forRole` / `see_all`, не User COUNT.
