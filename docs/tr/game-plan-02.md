# План Game 2 — участники-люди и права

**Статус:** участники PHP сделаны, 2026-10-04. `BACKEND_OPEN`. Suite `game` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G2. Строка игры — [`game-plan-01.md`](game-plan-01.md), код `Roleplay/Game` с ней совпадает. Канон ролей — [`auth-system.md`](auth-system.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: строка участника `(gameId, userId)` для человека, который не владелец. Роль этой строки настраивается: `gm` или `player`. Владелец остаётся колонкой `game.owner_id` и этим заходом не становится строкой участника.

Сверка G1: таблица `game`, владелец — актор create в `owner_id`. `game.get` / `game.update` пускают актора, если он равен `owner_id`, либо есть `game.view_all` / `game.edit_all`. Таблицы участника нет. `GamePermissionKeys` — только `game.create`, `game.view_all`, `game.edit_all`. Character модуль Game не импортирует. В G2 Character не меняется и Game его не импортирует.

## Зафиксировано (модель)

Решение по развилке «колонка или строка»: владелец — источник всех прав этой игры. Это пользователь `game.owner_id`. Строка участника ему не заводится, роль `owner` в таблицу участника не пишется. Три ключа владельца выводятся из колонки, не из строки и не из `game_member_permissions`.

У остальных пользователей-членов права настраиваемые. Настройка G2 — роль `gm | player` на строке. Отдельного списка ключей у строки нет.

- `gm` даёт `game.edit` и `game.moderate`.
- `player` из роли ничего не добавляет.
- Эти три ключа (`game.edit`, `game.moderate`, `game.manage`) записью в `game_member_permissions` не дублируются. Таблицы `game_member_permissions` в G2 нет.
- `game.manage` есть у владельца колонки. У `gm` и `player` его нет.
- Глобальные `game.create`, `game.view_all`, `game.edit_all` — проверка уже описанных ключей. Новый каталог прав не заводится. `game.edit_inventory` из legacy-списка в этот заход не входит.

Один пользователь позже может подать несколько персонажей. Строки персонажа в G2 нет. Уникальность участника — пара `(game_id, user_id)`, не «одна игра на пользователя».

Приглашения и заявки на вступление остаются `OPEN`. Каркас живёт без них: владельца создаёт G1.

`game.get`, `game.getList`, `game.update` и `game.create` этот заход не переводит на роль участника. Карточку по-прежнему видят владелец и `game.view_all`. Запись строки игры по-прежнему у владельца и `game.edit_all`. Подключение `game.edit` ведущего к `game.update` изменило бы G1; это не G2.

## Что даёт этот заход

Таблица `game_member` и round-trip трёх действий: добавить, сменить роль, снять. Чтение тех же строк — список участников игры. Тесты фасада через `IGames`. Сосед по-прежнему `$locator->get(IGameContainer::class)->get(IGames::class)`.

## Что не закрыто

- Строка персонажа, `approvedCharacterVersion`, `osBonus`, `orBonus`, `olBonus`. Лимит листа «потолок игры + бонус строки» — G3.
- Сессия, `playing`, бой, NPC, чаты, летопись, экономика, SSE, outbox.
- Приглашения и заявки на вступление.
- Применение `visibility` и `join_policy`. Участник без колонки владельца карточку игры этим заходом не открывает.
- Перенос `game.update` на ключ `game.edit` ведущего.
- Смена владельца и вторая роль `owner`.
- Character, Vue поверх этого PHP.

## Модуль

Тот же `www/mifrial/modules/Roleplay/Game`. Новый порт в `module.config.php` не добавляется: методы участника на `IGames`. `GameSchema::getTableClasses()` дополняется `GameMemberTable`. Сейчас конструктор `GameSchema` принимает один `IOpenedSchema` и `install()` приводит только `game`. Станет два аргумента, как `CharacterSchema`: `install()` создаёт или обновляет обе карты, сначала `game`, потом `game_member`. CLI-порядок карт идёт через `TableSetupOrder` по FK, тестовый `install()` — нет. `GameMysqlFixture` собирает схему одним `open(GameTable::class)` — туда добавляется `open(GameMemberTable::class)`. `events` остаётся `[]`. Suite тот же `game`. `ModuleSetupCollectorTest` не расширять. Четыре новых action регистрируются в `ports` и `routes` `module.config.php` так же, как текущие четыре: handler из `GamePortFactory::createHttp`, `csrf` true.

**DAG прод-PHP** как в G1: Game → SmartTable + User + RuleSpace. Новая карта: `game_id` — reference на класс `GameTable` (`restrict`); `user_id` — `ReferenceField::forTable` (`user`, `restrict`). Character, Chat и прочие соседи не появляются.

Ошибки те же листья: `GAME_INVALID`, `GAME_NOT_FOUND`. Наружу не коды ST. Нет актора — `AUTH_REQUIRED`. Нет глобального ключа там, где он требуется, — `AUTH_DENIED` из `requireKey`, не `GAME_*`. Чужая игра без права на операцию — `GAME_NOT_FOUND`, как карточка G1. Лишнее поле JSON (`permissions`, `userName`, бонусы) ядро отвергает до handler: `INVALID_PARAMS`. Это не `GAME_INVALID`.

| Источник | Лист |
|---|---|
| нет игры; игра не видна актору для этой операции | `GAME_NOT_FOUND` |
| нет строки участника на update/remove | `GAME_NOT_FOUND` |
| `UserNotFoundException` на добавляемого | `GAME_NOT_FOUND` |
| роль не `gm` и не `player`; `userId` равен `owner_id`; повтор пары игра+пользователь; запись при статусе игры `completed` | `GAME_INVALID` |
| `ReferenceConstraintException` на `game_id` или `user_id`; `RowNotFoundException` на delete/update | `GAME_NOT_FOUND` |
| `FieldRequiredException` / `FieldInvalidException` / `MapInvalidException` / `UniqueConstraintException` / `RowWriteFailedException` | `GAME_INVALID` |

## Зачем колонки

**Пара игра+пользователь.** Один человек — одна строка в одной игре. Владелец в эту пару не входит: его права даёт `owner_id`. Попытка добавить владельца или записать роль `owner` — `GAME_INVALID`, строки нет.

**Роль.** Колонка `role`. Допустимы только `gm` и `player`. Смена роли — единственная настройка прав. Бонусов ОС/ОЛ/ОР на строке нет.

**Три ключа.** Функция от того, кто человек, не колонка:

| Кто | Ключи |
|---|---|
| `game.owner_id` | `game.edit`, `game.moderate`, `game.manage` |
| строка `gm` | `game.edit`, `game.moderate` |
| строка `player` | пусто |

`game.edit_all` по-прежнему глобальный обход записи, не роль участника.

## Таблица `game_member`

Физическое имя `game_member`. Legacy `game_members` / `game_member_permissions` из [`data-model.md`](data-model.md) — не эта схема. Только SmartTable. DDL: `createTable` / `updateTable`, не `forceUpdateTable`. IdField обычный, как у `game`.

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | Суррогат, как `chat_member`. |
| `game_id` | reference `GameTable`, `restrict`, required | Игра. |
| `user_id` | `forTable(..., 'user', 'restrict')` required, indexed | Учётка. Не персонаж. |
| `role` | string 32, required | `gm` или `player`. |

`defineUniqueKeys()`: `['game_id', 'user_id']`.

Колонок нет: бонусы, `permissions`, `user_name`, приглашение, персонаж, optimistic lock, `joined_at`.

`GameMemberRecord` / `NewGameMember` / `GameMemberPatch` — `DEC-079`. Record: id, gameId, userId, role. New: gameId, userId, role. Patch: только role.

Колонки `game` не меняются. `game.create` строку участника не пишет.

## Фасад `IGames`

Методы G1 не меняют смысл. Рядом:

| Метод | Смысл |
|---|---|
| `addMember(NewGameMember $new): GameMemberRecord` | Игра есть. Статус не `completed`. Пользователь есть (`IUserAccounts::getById`). Не владелец. Роль `gm` или `player`. Пара ещё не занята. Иначе как в таблице ошибок. Insert. |
| `getMember(int $gameId, int $userId): GameMemberRecord` | Нет строки → `NOT_FOUND`. Без ACL, как `get` игры. Поиск пары — `records()->getUnique` с фильтром `game_id` и `user_id`, как `ChatMemberRepository::findId`. |
| `updateMember(int $gameId, int $userId, GameMemberPatch $patch): GameMemberRecord` | Нет строки → `NOT_FOUND`. Игра `completed` → `INVALID`, без write. Роль только `gm` или `player`. |
| `removeMember(int $gameId, int $userId): void` | Нет строки → `NOT_FOUND`. Игра `completed` → `INVALID`, без delete. |
| `listMembers(int $gameId): list<GameMemberRecord>` | Строки этой игры. Нет игры → `NOT_FOUND`. Пустой список, если участников нет. Владельца в списке нет. Чтение — `getList`, фильтр `game_id`, сортировка `id ASC`, `ListQuery::MAX_LIMIT`, offset 0, без total, как `GameRepository::getListByOwnerOrAll`. |

Вывод ключей — не метод `IGames` и не публичный метод `Games`. У `Games` публичные методы — конструктор и четыре метода G1; пять методов участника доводят счётчик до 10. `ClassQualitySniff` режет класс на 11-м публичном методе. Константы `game.edit`, `game.moderate`, `game.manage` и функция «владелец / `gm` / `player`» живут в `GamePermissionKeys`: этот класс уже держит строки ключей игры. Фасад ключи не пишет.

Второго порта нет. У `Games` сейчас четыре аргумента конструктора (`GameRepository`, `IUserAccounts`, `GameWorldGate`, `GameInputNormalizer`). Пятый — репозиторий участника. `GamePortFactory::create` передаёт его так же, как `GameRepository`: один `ISmartTableGateway`, внутри `open(GameMemberTable::class)->records()`. Удаление строки — `IOpenedRecords::delete` по id найденной строки.

## HTTP

Тонкие action, сценарий рядом с фасадом. CSRF как у G1. Вход — свой DTO.

Право писать участников: актор равен `owner_id` (у него `game.manage`) или есть `game.edit_all`. Иначе `GAME_NOT_FOUND`. Отдельного `requireKey('game.manage')` нет: ключ не лежит в каталоге групп, он выводится из колонки. `game.edit_all` — уже описанный глобальный ключ, его проверяет `hasKey`.

Право читать список: владелец, `game.view_all` или `game.edit_all`. Кто может менять состав, тот список и читает. Иначе `GAME_NOT_FOUND`.

| Action | Кто | Тело |
|---|---|---|
| `game.addMember` | владелец или `game.edit_all` | `{ gameId, userId, role }`. `role`: `gm` или `player`. |
| `game.updateMember` | то же | `{ gameId, userId, role }`. |
| `game.removeMember` | то же | `{ gameId, userId }`. |
| `game.getMemberList` | владелец, `game.view_all` или `game.edit_all` | `{ gameId }`. |

Ответ add/update — плоский объект: `userId`, `role`. Список — массив таких объектов. Remove — `null`. `userName` и `permissions` в ответе нет: имя не хранится, ключи выводятся из роли и в JSON строки не кладутся.

Имена action совпадают с вызовами эскиза. Тело эскиза — нет.

Дефекты эскиза, в фасад не копировать:

- `GameMember.userName` и `GameMember.permissions`;
- `UpdateGameMemberData.permissions` и вызов `game.updateMember` с массивом ключей (`game.moderate` поверх роли);
- вложенный `members` внутри `GameDetail` на `game.get`;
- `game.edit_inventory` и любые ключи сверх трёх выводимых и трёх глобальных.

## Тесты

Mysql: к графу G1 добавляется карта `game_member`. В `dropGameTables` её снимают до `game`: у `game_id` режим `restrict`, а фикстура сейчас первой удаляет `GameTable`.

- add+get: `gm`, затем update в `player`, list содержит одну строку, remove убирает её;
- владелец в list не входит, пока его не добавляли; add с `userId = owner_id` → `INVALID`, строки нет;
- роль `owner` и неизвестная роль → `INVALID`;
- второй add той же пары → `INVALID`;
- нет user → `NOT_FOUND`; нет игры → `NOT_FOUND`;
- update/remove неизвестной пары → `NOT_FOUND`;
- игра `completed`: add/update/remove → `INVALID`, состав прежний;
- один user в двух играх — две строки; два user в одной игре — две строки;
- ключи: владелец — три, `gm` — edit и moderate, `player` — пусто.

HTTP mysql: без актора — `AUTH_REQUIRED`; add чужой игры — `NOT_FOUND`; add владельцем — 200, в JSON нет `permissions` и нет `userName`; `game.edit_all` добавляет в чужую не-`completed` и читает её список; одного `game.view_all` достаточно, чтобы прочитать список чужой игры; игрок, уже добавленный, чужую карточку не открывает и список не читает; вход с `permissions` → `INVALID_PARAMS`.

Unit: разбор роли; владелец не становится членом; функция ключей для трёх случаев.

Существующие тесты G1 остаются зелёными: create не пишет `game_member`.

cs/quality дерева Game. Не suite `character`.

## Todo

- [x] **table** — карта `game_member`, unique пары, schema `install`.
- [x] **facade** — add / get / update / remove / list; `getUnique` пары; запрет владельца и `completed`; ключи в `GamePermissionKeys`, не публичным методом `Games`.
- [x] **http** — четыре action, плоский JSON без `permissions`.
- [x] **gates** — phpunit `game`; cs/quality. Character не импортирует Game.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| `GameMemberTable` | `Table/` | карта |
| Record / New / Patch | `Dto/` | участник |
| вход HTTP | `Dto/Action/` | четыре DTO |
| методы на `IGames` | `Interface/Service/` | без второго порта |
| `Games` | `Service/` | роль, владелец, completed, ключи |
| сценарий HTTP | `Service/` | кто пишет и кто читает список |
| репозиторий | `Repository/` | records участника |
| action | `Action/` | четыре handler |

[`game-plan-01.md`](game-plan-01.md) не меняется. Колонки `game` и поведение `game.create`, `game.get`, `game.getList`, `game.update` не меняются. Файлы модуля, куда входят новые методы и вторая карта (`IGames`, `Games`, `GameSchema`, `GamePortFactory`, `module.config.php`, фикстура mysql), расширяются.

## Acceptance G2

- Suite `game` зелёный; cs/quality модуля.
- Нет импорта Character. Character не импортирует Game.
- У владельца нет строки `game_member`. У `gm` и `player` нет колонок бонуса и нет таблицы `game_member_permissions`.
- `game.create` по-прежнему не пишет участника. `game.update` по-прежнему смотрит на `owner_id` и `game.edit_all`.
- Статус линии: `BACKEND_OPEN`.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G2; [`game-plan-01.md`](game-plan-01.md); [`auth-system.md`](auth-system.md).

## Следующий заход

G3 — строка персонажа. G2 после этого остаётся людьми и ролями, без листа и без сессии.
