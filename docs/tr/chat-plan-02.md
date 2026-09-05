# План Chat 2 — HTTP commands

**Статус:** сделано, 2026-09-04. Нарезка — [`chat-roadmap.md`](chat-roadmap.md). Канон UI — [`chat-system.md`](chat-system.md) (имена `IChatApi` / `sync` **не** копировать слепо). Актор — [`user-plan-03-http.md`](user-plan-03-http.md). Input-DTO — [`kernel-plan-02-action-input.md`](kernel-plan-02-action-input.md). Список не dump — [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Конвейер — [`architecture.md`](architecture.md). Фасад — [`chat-plan-01.md`](chat-plan-01.md).

Цель: четыре action чата на `action.php`. CSRF, актор, `AUTH_REQUIRED`. Вложения opaque json, без Files. **`chat.sync` не action.** Vue не трогаем (шаг 4).

На диске уже есть `IChats`, lazy-слот, три карты. Сосед по-прежнему не `open` и не Repository. Листья — `ChatException`. Порт не `IChat`. `IChats` **остаётся 10 public** — список чатов и JSON не раздувают фасад.

## Термины

| Термин | Смысл |
|---|---|
| Слот `lazy` | `config/modules.php`: ключ локатора → `{group, name}`. Контейнер не создаётся на `boot()`. |
| HTTP-сценарий | `ChatHttpService`: guard, актор в `IChats`, сборка JSON. Не колонки, не `ActionResponse`. |
| JSON Chat / Message | Вид API (`DEC-079`), не `ChatRecord`. Unix int UTC, как User. |

## Решения

### 1. Маршруты lazy, контейнер по запросу

`getRoutes()` читает **уже загруженные** модули. Сейчас `boot()` вешает слот `IChatContainer`, но `requireModule` не зовёт — `chat.*` → `UNKNOWN_ACTION`. Перенос в `Core/` запрещён. Грузить контейнер Chat на каждый ping/user.* тоже незачем: `bindEager` открыл бы порты и таблицы.

**Chat остаётся в `lazy`**, тот же ключ `IChatContainer`. Нового каталога `eager` нет.

Additive Kernel: в `registerLazyFromCatalog` после слота — `requireModule(group, name)`. Это только `include` `module.config.php` и запись в loaded: маршруты попадают в `getRoutes()`. `bindEager` к этому моменту уже прошёл (только `Core/*`) — контейнер Chat **не** собирается.

Порядок `assemble` (`boot()`, не setup): `loadCore()` → `getRoutes()` (Core) → `bindEager()` → `registerLazyFromCatalog()` (слот **и** `requireModule`) → `freeze`. Повторный сбор маршрутов на запросе видит Chat. `bootSetup()` без изменений.

Первый `get(IChatContainer)` — как сейчас: фабрика слота `attachLoadedModule`. Первый `chat.*` без предварительного `get`: у `Dispatcher` нет контейнера. Additive: нет `hasContainer` → `locator->get(config['locator'])` (тот же слот). Локатор в Dispatcher **опционален** (`null` по умолчанию): unit `DispatcherActionResultTest` остаётся `new Dispatcher($moduleManager)` — контейнер уже в stub. `ApplicationFactory` передаёт локатор. `Application::dispatch` и HTTP-`handle` оба идут через Dispatcher. Kernel не `use` Chat: group/name/locator из конфига модуля.

`request_bind` у Chat нет. `bindRequestActors` не зовёт `getContainer` у модулей без ключа. Lazy-модуль с `request_bind` этим заходом не заводим.

Тест шага 1 сменить смысл: после `boot()` модуль в loaded и есть `chat.getChats`; `hasContainer` false; ping без MySQL; первый `get(IChatContainer)` или `dispatch('chat.getChats')` вешает контейнер. `LazyModuleLoadTest` (фикстура без `ApplicationFactory`) не трогать.

### 2. Actions

Имена **серверного** контракта, не копия наброска. Vue `ChatApi` ходит в `chat.getChats` / `chat.getMessages` / `chat.getTotalMessageCount` / `chat.sendMessage` / `chat.markChatRead` и ещё `sync` / visibility / system / `beforeId`. Шаг 4 подстроит клиент. Не заводить alias «как во Vue».

CSRF **true** на всех четырёх. HTTP 401 нет: нет актора → `AUTH_REQUIRED` → 400.

| Action | `handle` | Успех `data` |
|---|---|---|
| `chat.getChats` | без параметров | `Chat[]` своих, ≤500 |
| `chat.findMessagePage` | `FindMessagePageInput`: `chatId`, `limit`, `offset` | `{ items: Message[], total }` |
| `chat.sendMessage` | `SendMessageInput`: `chatId`, `content`, `attachments` | объект Message |
| `chat.markChatRead` | `int $chatId` | `null` |

Не `chat.getList` (имя ST / снятый dump User). Не два action getMessages+count: то же, что запрет второго метода на `IChats`. Не `chat.getMessagesBefore` (`beforeId`) — лента шага 1 offset. Не create private/group **в этом файле** (хвост — [`chat-plan-06.md`](chat-plan-06.md)). Не add/remove member после create, не `get` одного чата.

Лишние ключи тела → `INVALID_PARAMS` (в т.ч. `speaker` / `visibility` / `thread` / `kind` на send, `beforeId` на странице, `since` нигде). Vue `undefined` в JSON не попадает — шаг 4 не обязан слать эти поля на private/group.

`FindMessagePageInput` / `SendMessageInput` — `Dto/Action/`, `IActionInput`. `attachments` на send: `array $attachments = []` (нет ключа → пустой список). Границы limit/offset и пустой send — как фасад (`CHAT_INVALID`), не второй раз в binder.

### 3. Guards

Нет ключей группы `chat.read` / `chat.write` / `chat.message`. Vue `canInChat` смотрит **статус члена** в JSON, не каталог User. HTTP: **`IUserAccess::requireActor()`** на все четыре. Нет актора → `AUTH_REQUIRED`. Bypass не открывает чужие чаты.

Членство — как шаг 1: send / page / markRead без членства → **`CHAT_NOT_FOUND`**, не `AUTH_DENIED` (не светить существование id). `getChats` — только чаты актора; чужой id в списке не появляется. Гость / сессия без `userId` — актор `null` (Auth 4), тот же `AUTH_REQUIRED`.

Chat **не** импортирует Auth и `User/Service`. Guard: `$locator->get(IUserContainer::class)->get(IUserAccess::class)` в фабрике. `IUserAccounts` — username в JSON.

### 4. JSON

Контракт **сервера**. Даты — **unix int UTC** (`DateTime::toUnix()`), как User; не ISO-строка Vue и не разбор строк в Kernel `DateTime`. Шаг 3 SSE `since` — отдельный кадр (ISO query), не этот заход.

**Chat** (элемент `getChats`):

| Ключ | Смысл |
|---|---|
| `id` | int |
| `type` | `private` / `group` |
| `name` | string; private `''` |
| `unreadCount` | `0` (шаг 5: visibility + preview) |
| `lastReadMessageId` | int или `null` (поле членства актора) |
| `lastMessageAt` | unix; `chat.updated_at` (send уже двигает) |
| `members` | `Member[]` |

Нет `lastMessage` (optional во Vue; preview — шаг 5). Нет `visibility` (нет public). Нет `pair_key`, нет `username` на чате. Нет чатов у актора → `[]`, не `CHAT_NOT_FOUND`.

**Member:** `userId`, `status` (пока всегда `'member'` — колонки роли нет), `joinedAt` unix. Нет `role`. Имена людей Vue берёт с User.

**Message:** `id`, `chatId`, `userId`, `username` (`UserRecord::getName()`; нет учётки → `''`, страницу не 404), `content`, `attachments` (как в строке: `type`+`payload`), `createdAt` / `updatedAt` unix. Нет `speaker` / `kind` / `visibility` / `thread`.

`getChats` — три пачки `getList` (диалект ST, не обход): членства актора; чаты `id in` (`sort` `updated_at` DESC, `id` DESC); члены `chat_id in`. Пустой `in` → `[]` без запроса. Лимит 500. Не считать unread/preview в PHP и не N× COUNT: это не «пока нет GROUP BY», а чужой заход. Unread/preview — шаг 5 **после** [`smarttable-plan-17-aggregate.md`](smarttable-plan-17-aggregate.md) (`SubqueryValue` на `last_read`, не JOIN в FROM). Не копировать User `getCountsByGroupIds`.

### 5. Фасад и репозитории (additive, узко)

`IChats` **не** расширять. Список и `last_read` — HTTP-слой того же модуля + методы репозиториев. Как `UserViewAssembler` ходит в репозитории User, не как сосед.

`ChatRecord` additive: `getCreatedAt()` / `getUpdatedAt()`. Не `pair_key`. `fromNormalized` требует эти ключи (строка `getById` их уже отдаёт). Тесты шага 1, которые смотрят только type/name, живы.

Репозитории (публичных методов **с ctor** ≤10, disable не ставить заранее):

- членство: **два** новых (`getChatIdsOfUser` не трогать): строки актора (`chat_id`, `last_read_message_id`, `joined_at`); пачка членов по `chat_id in`. Сейчас 7+ctor=8 → 9+ctor=10;
- чат: пачка `getByIds` (пустой список → `[]` без ST `IN`; sort как выше);
- сообщение: `getById` (после send → JSON). Не last/unread на список.

`send` по-прежнему `int`. HTTP после id читает строку через репозиторий сообщений, не новый метод `IChats`.

Нормализатор send тот же (`ChatInputNormalizer`); HTTP не дублирует trim.

### 6. Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `lazy` + `requireModule` | Kernel `ApplicationFactory` / Dispatcher | routes на boot; контейнер по `get` / action | `use` Chat, bind контейнера на ping |
| Actions | Chat `Action/` | csrf; скаляр / один input | `ActionResponse`, `open` |
| `Dto/Action/` | Chat | `FindMessagePageInput`, `SendMessageInput` | Record |
| `ChatHttpService` | `Service/` | actor, `IChats`, JSON | `ListQuery` |
| `ChatViewAssembler` | `Service/` | Record/строки → JSON unix | порт соседа |
| `ChatPortFactory` | `Service/` | `create` + `createHttp` (как Mail: один тип, два create) | сценарий |
| `IChats` | как шаг 1 | send/page/markRead/getById | список inbox |
| репозитории | additive методы | IN / getById message | `IUserAccounts`, N× COUNT |

`ChatHttpService` — порт контейнера (как `UserHttpService`), сосед не обязан `get`. Actions — тонкие `handle`, фабрика `fromContainer` или прямой `createHttp`. Ctor HTTP ≤6: `IUserAccess`, `IChats`, `IUserAccounts`, assembler (репозитории внутри assembler/factory).

Карта `ports`: `IChats` (уже), `ChatHttpService`, четыре Action. `routes` — четыре ключа, `csrf: true`.

### 7. Ошибки

| Код | Когда |
|---|---|
| `AUTH_REQUIRED` | нет актора |
| `CHAT_NOT_FOUND` | нет чата / не член (send, page, markRead) |
| `CHAT_INVALID` | пустой send, вложение, limit/offset, пустое имя не этот шаг |
| `INVALID_PARAMS` | лишний ключ, тип binder |
| `UNKNOWN_ACTION` | нет `requireModule` lazy при boot — регрессия маршрутов |

`AUTH_DENIED` на этих четырёх **нет** (нет ключа права). CSRF 403 до action. ST/`USER_*` наружу не текут (`USER_NOT_FOUND` на username не поднимать).

## Todo

- [x] **lazy-routes** — Chat остаётся в `lazy`; `registerLazyFromCatalog` + `requireModule`; Dispatcher: нет контейнера → `locator->get` (локатор опционален); Kernel-тест на `ApplicationFactory::boot` (не ломать `LazyModuleLoadTest`); ChatMysqlTest: loaded после boot, `hasContainer` после первого get.
- [x] **record-dates** — `ChatRecord` created/updated; mysql getById.
- [x] **repo-inbox** — пачка чатов/членов, `getById` сообщения; пустой `in` без запроса; членство ровно +2 public. Не COUNT/last на чат.
- [x] **json-http** — assembler Chat/Member/Message; unix; username; `unreadCount` 0; без `lastMessage`; пустой inbox `[]`.
- [x] **actions** — четыре маршрута; input-DTO; `requireActor`; CSRF true; **`ChatHttpMysqlTest`** (не раздувать `ChatMysqlTest` >500); явный актор (`setActor`, не два login).
- [x] **quality** — phpunit suites `chat` + kernel (маршруты lazy); cs/quality по Chat и задетому Kernel. Не `composer cs-fix` на чужое дерево.

## Не входит

Vue / смена `ChatApi` и ISO-дат. SSE / `chat.sync` action. `getMessagesBefore`. Visibility / `see_all` / unread/preview (`lastMessage`, ненулевой `unreadCount`) — и **не** PHP-агрегат «как memberCount». `sendSystemMessage` / thread / speaker. Files. Create/addMember HTTP. Роль члена (`creator`/`admin`/`gm`). Гость на чате. HTTP 401. Транзакции. Составной INDEX. `IChats` 11-й метод. Перенос в `Core/`. `phpcs:disable` на лимит заранее. План ST — [`smarttable-plan-17-aggregate.md`](smarttable-plan-17-aggregate.md).

## Документы захода

этот файл; [`chat-roadmap.md`](chat-roadmap.md) шаг 2; [`chat-plan-01.md`](chat-plan-01.md); [`architecture.md`](architecture.md) (lazy: routes на boot, контейнер по запросу); [`TR.md`](TR.md); [`user-plan-03-http.md`](user-plan-03-http.md); [`kernel-plan-02-action-input.md`](kernel-plan-02-action-input.md); [`php-coding-standards.md`](php-coding-standards.md); `config/modules.php`; `ApplicationFactory`; Dispatcher.

## Следующий заход

SSE `API/chat-sync.php` — шаг 3. Кадр читает те же строки, что send/page. `sync` по-прежнему не action. Unread/preview не начинать с обхода ST.
