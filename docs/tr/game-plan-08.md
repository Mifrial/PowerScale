# План Game 8 — фильтр карточки и вступление

**Статус:** фильтр карточки и вступление в PHP сделаны, 2026-10-04. `BACKEND_OPEN`. Suite `game` по `GameAdmissionMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G8. Строка игры — [`game-plan-01.md`](game-plan-01.md). Участник — [`game-plan-02.md`](game-plan-02.md). Роли — [`auth-system.md`](auth-system.md). Границы — [`game-system.md`](game-system.md), [`architecture.md`](architecture.md), индекс [`TR.md`](TR.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход, который G1 отложил. `game.get` и `game.getList` начинают толковать `visibility`. Вступление идёт двумя потоками — приглашение и заявка — и только при подходящем `join_policy` создаёт строку `game_member`. Владелец остаётся колонкой `game.owner_id`.

Это не `game_character.section_visibility` (G7) и не объект видимости NPC (G6).

## Сверка G1–G7

Колонки уже лежат и не толкуются.

- `game.visibility`: `all | friends | players | invited | whitelist`. `game.join_policy`: `anyone | friends | invite_only | whitelist`. Иное значение по-прежнему `GAME_INVALID` на create и update. Смысл значений до этого шага не читается.
- `GameRepository::getListByOwnerOrAll`: `view_all` — все строки, иначе `owner_id`. Visibility не разбирает.
- `GameHttp::assertVisible`: карточка открывается, если актор равен `owner_id` или есть `game.view_all`. Иначе `GAME_NOT_FOUND`. Участник без колонки владельца карточку не открывает. Это поведение G1 и G2, его меняет этот шаг.
- Владелец — `game.owner_id`. Строки участника у него нет. Роль строки — только `gm` или `player`. `game.addMember` владельца не пишет.
- `Games` — десять публичных методов, потолок. `IGames` этим шагом не расширяется.
- `GameSchema` — шесть зависимостей конструктора, потолок. Седьмую карту в этот конструктор не класть.
- `GameHttp` — пять зависимостей и восемь публичных методов. Шестая зависимость — потолок конструктора. Девятый публичный метод ещё влез бы по счётчику методов, но сценарии вступления в этот класс не ставятся: ось другая.
- Дружбы в User нет. Предикат `friends` не из чего вычислить.
- Списка whitelist на строке `game` нет. `reference` с `multiple` карта не принимает. Набор id другой таблицы — `LinkSetField` (`type()` `linkset`), не множественный `int`: у `int` нет цели и внешнего ключа. Так уже лежит `keywords` на `RuleVersionTable`. Канон поля — [`smarttable-plan-20-linkset.md`](smarttable-plan-20-linkset.md).
- `section_visibility` и `game_npc.visibility` этот заход не читает и не пишет.

## Зафиксировано (модель)

Две новые таблицы потоков. Whitelist — не третья таблица и не строка участника: `LinkSetField` на `game`. Приглашение и заявка по-прежнему не одна таблица и не строка персонажа.

**Фильтр карточки.** Сначала актор. Нет актора — `AUTH_REQUIRED`. Дальше, до разбора `visibility`:

- актор равен `owner_id` — карточка открыта, включая `draft`;
- есть `game.view_all` — карточка открыта, включая `draft`;
- статус `draft` — `GAME_NOT_FOUND` для всех остальных. `visibility` на черновике не читается.

Иначе, игра не `draft`:

| `visibility` | Кто ещё открывает |
|---|---|
| `all` | любой актор |
| `players` | строка `game_member` этого актора |
| `invited` | строка участника или приглашение этого актора в статусе `pending` или `accepted` |
| `whitelist` | строка участника или id актора в `LinkSetField` этой игры |
| `friends` | только строка участника |

`declined` приглашение карточку не открывает. Чужой пользователь вне таблицы — `GAME_NOT_FOUND`, как скрытая карточка G1. `game.edit_all` фильтр чтения не обходит.

`friends` без отношения дружбы не равен `all`. Эскиз трактует `friends` как «все»; это дефект наброска. Участник, уже записанный G2, карточку `friends` открывает. Остальные — нет.

**Список.** `game.view_all` по-прежнему получает все строки. Иначе строка попадает в список, если актор владелец или проходит тот же фильтр, что `game.get`. Выборка «только `owner_id`» после этого шага не является ответом `game.getList`.

**Вступление.** `join_policy` не создаёт участника сам. Строку пишет уже существующий `IGames::addMember`: роль `player`, не владелец, игра не `completed`, пара ещё свободна. Повтор и `userId = owner_id` остаются `GAME_INVALID` внутри `addMember`. Колонка владельца не появляется.

| `join_policy` | Какой поток создаёт участника |
|---|---|
| `anyone` | заявка `pending`, затем accept ведущего |
| `friends` | ни один. Заявку и приглашение этот policy не принимает: отношения дружбы нет |
| `invite_only` | приглашение `pending`, затем accept приглашённого |
| `whitelist` | сам актор, если его id есть в `LinkSetField`. Заявки нет |

Прямой `game.addMember` владельца и `game.edit_all` остаётся обходом политики, как в G2. Его тело и допуск не меняются.

Игра `completed` отвергает новую заявку, новое приглашение, accept, decline и запись whitelist: `GAME_INVALID`, строк потока нет. `draft` фильтром карточки закрыт от чужих, но запись потока на не-`completed` не запрещается отдельно: приглашённый в черновик после accept участником становится и карточку всё равно не открывает, пока статус `draft`.

## Что даёт этот заход

Фильтр `game.get` и `game.getList`. Таблицы `game_invitation` и `game_join_request`. Поле `game.whitelist` типа `linkset` на учётки. Действия приглашения, заявки и этого набора. Accept подходящего потока вызывает `addMember` с ролью `player`.

## Что не закрыто

- Летопись (G9), порт мутации actual (G10), экономика, бой, проекции (G14), доставка (G15).
- Чаты и `returnMessageId`. `gameChatId` и `discussionChatId` остаются `null`.
- `personalNotes`.
- Отношение дружбы. Пока его нет, `friends` никого кроме владельца, `game.view_all` и уже записанного участника не пускает и участника не создаёт.
- Перенос `game.update` на `game.edit` ведущего.
- Смена владельца и строка участника с ролью `owner`.
- Видимость секций строки персонажа и вырезание листа (G14). Объект видимости NPC.
- Vue поверх этого PHP.

## Точки кода G1–G7

Старые `game-plan-01.md`–`game-plan-07.md` не переписываются. Подача, модерация, бонус, сессия, NPC и секции не меняются. `IGames` не растёт. `GameSchema` не получает седьмую зависимость.

Без этих точек фильтр и вступление некуда положить:

- `GameHttp::assertVisible` и `get`. Сейчас чужого без `game.view_all` прячет сразу. Здесь вызывается новый разбор `visibility`. Шестая зависимость конструктора — класс разбора, это потолок.
- `GameHttp::getList`. Сейчас список собирает `getListByOwnerOrAll` и на этом заканчивает. Здесь тот же разбор, что у карточки, для актора без `game.view_all`.
- `GameRepository`. Два новых публичных метода. Сейчас их четыре: `add`, `getById`, `update`, `getListByOwnerOrAll`. Потолок десять. `getListForCard` читает строки для разбора списка актора без `game.view_all`. Не замена `getListByOwnerOrAll`. У того при `viewAll = false` фильтр `owner_id`, поэтому чужие `all` он не вернёт. `getListForCard` — тот же `getList`: без фильтра владельца, сортировка `id ASC`, `ListQuery::MAX_LIMIT`, offset 0, без total. Потолок 10000 уже стоит на списке `view_all`. Второй метод пишет только `whitelist`. `ReferenceConstraintException` → `GAME_NOT_FOUND`, `MapInvalidException` → `GAME_INVALID`.
- `GameMemberRepository`. Новый публичный метод читает строки одного `user_id` тем же `getList`, что `getListByGame`. Сейчас публичных методов пять (`add`, `getByPair`, `updateRole`, `deleteById`, `getListByGame`). `IGames::getMember` на отсутствие пары бросает `GAME_NOT_FOUND`; для списка игр его не зовут по строке. Шестой метод, потолок десять. `Games` и `IGames` этот метод не получают.
- `GameTable`. Новое поле `whitelist`: `LinkSetField::forTable` на физическое имя `user`, `onDelete` `restrict`. Флага `multiple` нет. Поле не `required`, default `[]`: `required` и пустой список дают `FIELD_REQUIRED`. Колонки на `game` нет, sidecar `game_mfv_whitelist` создаёт `updateTable` уже существующей карты. `GameSchema` и число его зависимостей не меняются.
- `GameRecord`. Геттер списка id и чтение ключа `whitelist` в `fromNormalized`. Класс уже с `phpcs:disable` на `TooManyPublicMethods`. Без ключа строка битая, как без `visibility`. Единственная ручная сборка — `GameCharacterDiffTest::game`: в массив добавляется `'whitelist' => []`. Остальные тесты берут строку из базы, там ключ уже гидратирован. В JSON `game.get` и `game.getList` этот список не попадает: `GameViewAssembler::listItem` и `detail` поле не добавляют.
- `GameRepository::insertValues`. Create пишет `whitelist` как `[]`. `patchValues` ключ не передаёт: нет ключа — sidecar не затирается. `game.update` набор не меняет.
- `GameModuleSetup::getTableClasses`. Сейчас возвращает только `GameSchema::getTableClasses()`. Сюда дописываются две карты нового установщика. Отдельной карты whitelist нет.
- `GameMysqlFixture`. Рядом с уже открытыми картами открываются две новые. Sidecar whitelist поднимает повторный `install()` уже стоящего `GameSchema`.
- `Game/module.config.php`. Новые `ports` и `routes`. Старые action не переименовываются.

`Games::addMember` не меняется: accept зовёт его как есть. `game.addMember` HTTP не меняется. `game.getMemberList` остаётся допуском G2: владелец, `game.view_all` или `game.edit_all`. Участник, которому этот шаг открыл карточку, список людей по-прежнему не читает. `game.getCharacter`, `game.getNpc` и сессии свои прежние допуски не меняют.

## Модуль

Тот же `Roleplay/Game`. Новый порт `IGameAdmissions` в `Interface/Service/`. Сценарии в `Service/` Game. `events` остаётся `[]`. Suite `game`.

**DAG:** Game → SmartTable + User + RuleSpace + Character, как после G3. Нового ребра нет. Character не импортирует Game. Дружбу User не заводит.

Ошибки прежние: `GAME_INVALID`, `GAME_NOT_FOUND`, `AUTH_REQUIRED`, `AUTH_DENIED`, `INVALID_PARAMS`. `GAME_CONFLICT` и `CHARACTER_*` этим действиям не принадлежат.

| Источник | Лист |
|---|---|
| нет игры; карточка вне фильтра; нет права на список заявок, приглашений или whitelist; нет своей заявки или своего приглашения | `GAME_NOT_FOUND` |
| игра `completed`; `friends` на запись потока; `invite_only` на заявку; `anyone` на приглашение; `whitelist` на заявку и на приглашение; accept не в `pending`; актор — владелец и просит себя записать; повтор участника | `GAME_INVALID` |
| тело не того типа; лишний ключ; статус не из трёх | `INVALID_PARAMS` |
| нет актора | `AUTH_REQUIRED` |
| учётки нет (`UserNotFoundException` или `ReferenceConstraintException` на user) | `GAME_NOT_FOUND` |

Так уже делает `Games::addMember`: `requireMemberUser` и `GameMemberRepository` переводят отсутствие учётки в `GAME_NOT_FOUND`, не в `GAME_INVALID`. Приглашение и заявка до insert проверяют учётку так же. `setWhitelist` участника не создаёт: чужой id ловит отдельный метод репозитория как `ReferenceConstraintException`.

Чужая карточка не отличается от отсутствующей игры.

## Таблицы

Новый установщик `GameAdmissionSchema` в `Schema/`. Конструктор — два `IOpenedSchema`. `GameSchema` остаётся на шести. `install()` нового класса приводит две карты: приглашение, затем заявка. DDL: `createTable` / `updateTable`.

Имена физические: `game_invitation`, `game_join_request`. Legacy `game_invitations` из [`data-model.md`](data-model.md) — не эта схема. Таблицы `game_whitelist` нет.

**`game_invitation`**

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | Суррогат. |
| `game_id` | reference `GameTable`, `restrict`, required | Игра. |
| `inviter_id` | `forTable` user, `restrict`, required | Кто пригласил. |
| `invitee_id` | `forTable` user, `restrict`, required, indexed | Кого пригласили. |
| `status` | string 32, required | `pending`, `accepted`, `declined`. |
| `created_at`, `updated_at` | DateTime Kernel | Как у `game`. |

Не второй `pending` на `(game_id, invitee_id)`. Повтор — `GAME_INVALID` до insert. `accepted` и `declined` пару не запирают: после `declined` новое приглашение снова `pending`. `defineUniqueKeys()` на этой карте нет. У `GameMemberTable` уникальность — на всю пару, без статуса; такой ключ здесь запретил бы повтор после отказа. Частичного уникального индекса «только pending» карты SmartTable в модуле не задают.

**`game_join_request`**

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | Суррогат. |
| `game_id` | reference `GameTable`, `restrict`, required | Игра. |
| `user_id` | `forTable` user, `restrict`, required, indexed | Кто просится. |
| `status` | string 32, required | `pending`, `accepted`, `declined`. |
| `created_at`, `updated_at` | DateTime Kernel | Как у `game`. |

Не второй `pending` на `(game_id, user_id)`, проверка в сервисе до insert. `defineUniqueKeys()` нет, по той же причине, что у приглашения. После `declined` новая заявка снова `pending`.

**`game.whitelist`**

`LinkSetField` на строке `game`. В PHP это `list<int>` id учёток. Дубль в списке — `MapInvalidException` (`Multiple list cannot contain duplicates`), наружу `GAME_INVALID`. Чужой или нулевой id — `ReferenceConstraintException`, наружу `GAME_NOT_FOUND`. `GameRepository::update` эту вторую ошибку не ловит: он переводит `MapInvalidException` в `GAME_INVALID` и `ReferenceConstraintException` не перехватывает. Поэтому набор пишет отдельный метод репозитория, не `patchValues`. Имен нет. Уникальность пары `(owner_id, value)` держит sidecar, не `defineUniqueKeys()` карты `game`.

`game.create` кладёт `[]`. `game.update` ключ не шлёт. Замена набора — отдельное действие, оно пишет поле целиком: у `linkset` update с ключом заменяет множество, а не один id.

Колонки `game_member`, `game_character`, `game_npc` не меняются. На `game` новое только это поле.

## Фасад

`IGameAdmissions`. Не методы `IGames`.

Разбор карточки — класс `GameCardAccess` в `Service/`. Его зовут `GameHttp::get` и `GameHttp::getList`. Два публичных метода: видна ли одна запись; оставить из списка видимые. Один проход на актора, не `getMember` на каждую игру: id игр из `GameMemberRepository` по `user_id`, id игр из приглашений `pending` и `accepted`. Whitelist читается с самой строки: `GameRecord` уже несёт `list<int>`, отдельной выборки нет. `game.view_all` список не режет и эти две выборки не делает: `GameHttp::getList` по-прежнему зовёт `getListByOwnerOrAll` с `true`. Без ключа `getList` берёт `getListForCard` и отдаёт строки, где актор владелец или запись проходит таблицу `visibility`. `draft` отсекается до неё. `friends` для id вне членства — ложь. `IGames::getMember` снаружи по-прежнему бросает `GAME_NOT_FOUND`, если пары нет.

Запись потоков — класс `GameAdmissions` (реализация порта) и отдельный HTTP-класс `GameAdmissionHttp`. `GameHttp` эти методы не получает.

Кто приглашает (`invite_only` только): владелец, строка `gm`, или `game.edit_all`. `player` — `GAME_NOT_FOUND`. Приглашённый не равен `owner_id` и ещё не участник, иначе `GAME_INVALID`. Учётки нет — `GAME_NOT_FOUND`, как у `addMember`.

Кто принимает приглашение: `invitee_id`. Accept сначала вызывает `addMember` с `player`. Исключение `addMember` оставляет статус `pending`. Статус `accepted` пишется после успешной строки участника. Decline только статус `declined`, участника нет.

Кто подаёт заявку (`anyone` только): актор видит карточку, не владелец, не участник, живого `pending` нет. Accept заявки: владелец, `gm` или `game.edit_all`; дальше тот же `addMember`. Decline — статус, без участника.

Whitelist целиком заменяет владелец или `game.edit_all`. `gm` набор не пишет. Игра `completed` — `GAME_INVALID`, прежний список на месте. Чтение набора — владелец, `gm` или `game.edit_all`. Иначе `GAME_NOT_FOUND`. В общий `game.get` список не входит. Вступление `whitelist`: актор есть в списке, не владелец, не участник; сразу `addMember`, строки заявки нет. Карточку для этого видеть не обязан: допуск — id в наборе и policy `whitelist`.

Порядок проверок записи: актор, игра (`get` → нет игры `GAME_NOT_FOUND`, `completed` `GAME_INVALID`), policy, фильтр карточки там, где актор должен её видеть, затем вставка. Приглашённый, которому карточку ещё не открыли (`players` без членства), свой `pending` всё равно принимает: допуск accept — совпадение `invitee_id`, не фильтр `visibility`.

Ответ собирают новые методы, не `GameViewAssembler`: у сборщика три публичных метода, четвёртый и пятый влезли бы, но JSON потока — другая ось. Имен нет, как у `member`.

`GamePortFactory` собирает порт и HTTP двумя методами: `createAdmissions` и `createAdmissionHttp`. Сейчас публичных методов восемь (`create`, `createHttp`, `createMemberships`, `createCharacterSectionHttp`, `createCharacterHttp`, `createSessionHttp`, `createSessionParticipants`, `createNpcHttp`). Станет десять, это потолок. Третий `create` сюда не встанет.

## HTTP

Свой DTO на действие, `csrf` true. Класс `GameAdmissionHttp`.

| Action | Кто | Тело | Успех |
|---|---|---|---|
| `game.invite` | владелец, `gm` или `game.edit_all`; policy `invite_only` | `{ gameId, inviteeId }` | `{ id, gameId, inviterId, inviteeId, status: pending }` |
| `game.getInvitations` | те же, кто приглашает | `{ gameId }` | список таких объектов |
| `game.getMyInvitations` | актор | `{}` | его приглашения всех статусов |
| `game.respondInvitation` | invitee | `{ invitationId, action: accept \| decline }` | тот же объект, новый статус. `accept` ещё создаёт участника `player` |
| `game.requestJoin` | актор, policy `anyone`, карточка видна | `{ gameId }` | `{ id, gameId, userId, status: pending }` |
| `game.getJoinRequests` | владелец, `gm`, `game.edit_all` или `game.view_all` | `{ gameId }` | список заявок игры |
| `game.respondJoinRequest` | владелец, `gm` или `game.edit_all` | `{ gameId, userId, action: accept \| decline }` | заявка. `accept` создаёт участника `player` |
| `game.getWhitelist` | владелец, `gm` или `game.edit_all` | `{ gameId }` | `{ userIds }` — список int, без имён |
| `game.setWhitelist` | владелец или `game.edit_all` | `{ gameId, userIds }` | тот же объект. Список заменяется целиком. `gm` — `GAME_NOT_FOUND` |
| `game.joinWhitelist` | актор из списка, policy `whitelist` | `{ gameId }` | объект участника `userId`, `role: player` — тот же вид, что `game.addMember` |

`game.get` и `game.getList` те же поля, что в G1. Меняется только то, кто их получает.

Дефекты эскиза `draft-front_1.2ds`, в фасад не копировать:

- `canViewGame` считает `friends` равным `all`. Сервер для не-участника карточку `friends` не открывает.
- `invited` и `whitelist` в эскизе открыты только членам `memberIds`. Сервер открывает ещё `pending`/`accepted` приглашение и id из `LinkSetField`. Отдельной таблицы и множественного `int` без ключа на `user` нет.
- Заявка в эскизе доступна и при `anyone`, и при `friends`. Сервер заявку при `friends` не создаёт.
- Статусы приглашения `sent | viewed | accepted | declined` и поля `inviterName`, `inviteeName`, `userName`, `createdAt` строкой. Контракт: `pending | accepted | declined`, id пользователей, без имён и без `viewed`.
- `game.respondJoinRequest` в эскизе не требует policy. Сервер принимает заявку только если она есть и policy был `anyone` в момент подачи; смена policy после `pending` не превращает заявку в приглашение и не создаёт участника, если policy уже не `anyone`: `GAME_INVALID`.
- Прямое добавление участника эскиз оставляет владельцу. Это уже G2 и этим планом не заменяется потоком.

## Тесты

Mysql фильтра. Чужой при `all` открывает не-`draft` и видит её в списке. `draft` при `all` — `GAME_NOT_FOUND` и нет в списке; владелец и `view_all` видят. `players`: участник видит, посторонний нет. `invited`: посторонний нет; `pending` приглашение открывает; `declined` снова нет. `whitelist`: id из набора открывает, сосед нет. `game.get` набор не содержит. `friends`: участник видит, посторонний нет. `view_all` видит чужой `friends` и чужой `draft`. `game.getWhitelist`: владелец, `gm` и `edit_all` видят список; `player` и посторонний — `GAME_NOT_FOUND`. `game.setWhitelist` у `gm` — `GAME_NOT_FOUND`, набор прежний.

Mysql вступления. `anyone`: заявка, accept даёт `game_member.role = player`, владельца в `game_member` нет. Decline участника не пишет. `invite_only`: заявка — `GAME_INVALID`; accept приглашения пишет `player`. `whitelist`: `joinWhitelist` пишет `player`; заявка и приглашение — `GAME_INVALID`. `friends`: заявка и приглашение — `GAME_INVALID`, участника нет. `completed` не пишет ни поток, ни участника. `addMember` владельцем по-прежнему пишет `gm` мимо policy.

Mysql границ. `game_character` и `game_npc` не появляются. `section_visibility` не читается. Повторный accept — `GAME_INVALID`, вторая строка участника не появляется.

Suite `game`. Suite `character` не расширяется.

## Todo

- [x] **schema** — `GameAdmissionSchema` и две карты; `whitelist` — `LinkSetField` на `GameTable`; `GameSchema` без новой зависимости.
- [x] **filter** — `GameCardAccess` в `game.get` и `game.getList`. Черновик только владелец и `game.view_all`.
- [x] **flows** — приглашение, заявка, whitelist. Участник только через `addMember` роли `player`.
- [x] **gates** — phpunit `GameAdmissionMysqlTest`. Владелец не становится строкой. NPC и секции не задеты.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| две карты и linkset | Game `Schema/` `Table/` | приглашение, заявка; `game.whitelist` |
| разбор карточки | Game `Service/` | `visibility`, включая закрытый `friends` |
| потоки | Game `Service/` `Repository/` | запись и accept через `IGames::addMember` |
| HTTP | Game `Service/` `Action/` | `GameAdmissionHttp`, не девятый метод `GameHttp` |
| строка G1 | Game `Service/` | `assertVisible` и `getList` зовут разбор |
| участник G2 | Game `Service/` | `addMember` без смены допуска |

## Acceptance G8

- Чужая карточка вне `visibility` не открывается и не попадает в список актора без `game.view_all`.
- `draft` по-прежнему открыт владельцу и `game.view_all`.
- `join_policy` без своего потока участника не создаёт. `friends` участника не создаёт.
- Accept `anyone` и `invite_only`, и `joinWhitelist`, пишут `player` и не пишут строку владельца.
- `game.whitelist` — `LinkSetField` на `user`. `game.get` и `game.getList` набор не отдают. `game.getWhitelist` открыт владельцу, `gm` и `game.edit_all`. Пишет набор владелец или `game.edit_all`.
- `game.addMember` владельца и `game.edit_all` работает как в G2.
- Character не импортирует Game. `game_npc` и `section_visibility` не меняются.
- Чаты, летопись, экономика, бой, проекции и доставка этим заходом не спроектированы.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G8; [`game-plan-01.md`](game-plan-01.md); [`game-plan-02.md`](game-plan-02.md); [`game-system.md`](game-system.md); [`auth-system.md`](auth-system.md); [`smarttable-plan-20-linkset.md`](smarttable-plan-20-linkset.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).
