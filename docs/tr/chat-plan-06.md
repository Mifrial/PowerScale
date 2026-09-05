# План Chat 6 — создать host private/group

**Статус:** сделано, 2026-09-05. Нарезка — [`chat-roadmap.md`](chat-roadmap.md). HTTP — [`chat-plan-02.md`](chat-plan-02.md) (create там **не** входило). Vue real — [`chat-plan-04.md`](chat-plan-04.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Фронт — `draft-front_1.2ds/frontend-rules.md`.

Цель: актор сам заводит **обычный** (`private`) и **групповой** (`group`) чат с UI и HTTP. Список мессенджера — только эти два типа. Чаты игры / обсуждения игры / обсуждения персонажа **не** создаются здесь и **не** попадают в этот inbox: их заводят доноры сами, другим типом, когда будут.

`IChats::addPrivate` / `addGroup` уже есть. **11-й метод фасада не заводить.** HTTP зовёт фасад, как send. Сосед (будущий Game) тоже зовёт фасад, не HTTP create.

## Термины

| Термин | Смысл |
|---|---|
| Host-чат | `type` `private` или `group`. Единственные строки inbox `/messenger` и SSE `/api/chat/sync`. |
| Донорский чат | Будущие `game` / `game_discussion` / `character_discussion` (имена донора). Не этот UI, не этот список. |
| Пара | Двое людей; `addPrivate` идемпотентен (тот же id). |

## Решения

### 1. Inbox — только host

Сейчас в таблице только `private`/`group`, но `assembleInbox` / SSE берут **все** id членства. Когда появится другой `type`, он всплывёт в мессенджере.

Один allowlist литералов `private` / `group` (не enum ST, не колонка `host`). Общий предикат в модуле Chat (крошечный тип или метод assembler), **не** копипаст в трёх местах.

**Не** зашивать отсев в `assembleChatsByIds`: тот же метод отдаст JSON host-чата после create и позже может собрать донорский чат для **другого** UI. Inbox режет в `assembleInbox`. SSE режет **свой** набор id.

SSE (важно, иначе сообщения донорского чата утекут в `messages` кадра):

- `knownChatIds` только host. На `openStream`: `getChatIdsOfUser` + **один** `getByIds` членства (как цена коннекта, не каждый тик).
- Тик: полный список id членства; **новые** id — `getByIds` только их; host → кандидат `newChats`, иначе игнор (не в known).
- `getUpdatedSince` — только host-id, не все членства.
- `IChats::getChatIdsOfUser` не сужать.

`ChatRecord::fromNormalized` принимает любой string `type` — unit отсева с `'game'` без DDL.

Не фильтр «нет game_id».

### 2. HTTP create — два action

Имена сервера. CSRF true. Актор `requireActor`. Гость — `AUTH_REQUIRED`.

| Action | Тело | Успех `data` |
|---|---|---|
| `chat.addPrivate` | `userId` int — **вторая** учётка | объект Chat (как элемент getChats) |
| `chat.addGroup` | `name` string; `memberIds` int[] (нет ключа → `[]`) | объект Chat |

Актор **не** в JSON private (сам первый). `userId === актор` → `CHAT_INVALID` (как фасад self-private). Нет учётки → `CHAT_NOT_FOUND` (не `USER_*`). Повтор той же пары → **тот же** Chat, не ошибка.

Group: trim имя, пустое → `CHAT_INVALID`. Создатель всегда член (`NewGroupChat`). Чужой id в `memberIds` → `CHAT_NOT_FOUND`. Лишний ключ тела → `INVALID_PARAMS`.

Не `chat.create` с полем type. Не add/remove member HTTP после create. Не create донорского type.

Актор в `memberIds` группы допустим (фасад схлопнет). Неактивная учётка — как `IUserAccounts::getById` на фасаде, не второй смысл HTTP.

### 3. Фасад и HTTP-слой

`IChats` как есть (10 public). `ChatHttpService`: сейчас ctor+5 public; плюс `addPrivate` / `addGroup` → ctor+7 ≤10. Ctor **не** расширять (5 deps). Quality смотреть, `phpcs:disable` на лимит не ставить заранее.

Ответ create — `assembleChatsByIds` одного host-id (не сырой Record).

Input-DTO: `AddPrivateChatInput` / `AddGroupChatInput` в `Dto/Action/`. `memberIds` в binder: нет ключа → `[]`; не int в списке → `INVALID_PARAMS` / `CHAT_INVALID` как остальные list-int модуля.

### 4. Vue

Real `ChatApi`: `addPrivate(userId)`, `addGroup(name, memberIds)`. Стор: после успеха — upsert чата (как sync) и `openChat`. Мок — те же методы, in-memory.

UI мессенджера: создать private (другой user **id** через публичный `getUserApi().findPage`, не внутренности User, не имя как ключ) и group (имя + опционально участники). Пустой список не тупик. Ошибка/loading/retry как F17.

`user.findPage` уже с актором у любого залогиненного — не разводить второй каталог людей в Chat. Не форма `game`. Плагины Game не трогать.

### 5. «Порт» для соседей

Донор не ходит в `chat.addPrivate` HTTP. Создаёт свой тип **своим** кодом на `IChats` (когда type расширят) или своим фасадом. Host create **не** переименовывать в generic `open` «для всех модулей».

## Todo

- [x] **filter** — `assembleInbox` + SSE (known/host `getUpdatedSince` / new id) только `private`/`group`; не фильтр внутри `assembleChatsByIds`. Unit assembler: Record с `type` не host не в inbox. Mysql host-членство есть в getChats.
- [x] **http** — два action; mysql: private пара/повтор тот же id; group имя; self INVALID; нет user NOT_FOUND; JSON Chat. Не раздувать `ChatMysqlTest`.
- [x] **vue** — ChatApi + стор upsert/`openChat`; диалог; mock; format/lint/vue-tsc/test затронутого.
- [x] **gates** — phpunit `chat`; cs/quality Chat. Не phpunit User. Не `cs-fix` чужое дерево.
- [x] **canon** — этот файл; roadmap; `chat-plan-02` «не create» → хвост этот файл; `TR.md`.

## Не входит

Типы Game/Character и их create. `addMember`/`removeMember` HTTP после create. Закрыть send/page по type (донорский id с HTTP messenger не режем в этом файле). `forRole`. Files. `chat.sync` action. 11-й метод `IChats`. Перенос в `Core/`. Гость.

## Слои

| Тип | Задача | Не делает |
|---|---|---|
| `ChatHttpService` | addPrivate/addGroup, актор | `ListQuery` |
| assembler | JSON Chat; filter type | create |
| `ChatSseService` | кадр только host-id | Game SSE |
| `ChatApi` + диалог | два action | донорские type |

## Документы захода

этот файл; [`chat-plan-02.md`](chat-plan-02.md); [`chat-roadmap.md`](chat-roadmap.md); [`chat-system.md`](chat-system.md); [`php-coding-standards.md`](php-coding-standards.md); `frontend-rules.md`.
