# План Game 3 — строка персонажа в игре

**Статус:** строка персонажа в PHP сделана, 2026-10-04. `BACKEND_OPEN`. Suite `game` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G3. Участник — [`game-plan-02.md`](game-plan-02.md). Строка игры — [`game-plan-01.md`](game-plan-01.md). Лист — [`character-system.md`](character-system.md). Гейт «C8 без Game» — [`character-roadmap.md`](character-roadmap.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: строка `(gameId, characterId)` отдельно от `game_member`. Заявка, неизменяемый snapshot, бонус этого персонажа и видимость секций в игре. Character этим заходом не меняется и Game не пишет таблицу `character`.

## Сверка G1–G2

Код `Roleplay/Game` совпадает с принятыми планами в том, на чём стоит G3.

- Таблица `game`. Владелец — `owner_id` актора create. `game.update` пускает актора, если он равен `owner_id`, либо есть `game.edit_all`. На `game.edit` ведущего запись строки игры не переведена.
- Таблица `game_member`: `game_id`, `user_id`, `role` (`gm` или `player`). Уникальная пара. Владельцу строка не заводится. Бонусов на ней нет. Таблицы `game_member_permissions` нет.
- `GamePermissionKeys::keysFor`: владелец колонки — `game.edit`, `game.moderate`, `game.manage`; `gm` — `game.edit` и `game.moderate`; `player` и любой другой — пусто. Глобальные ключи те же: `game.create`, `game.view_all`, `game.edit_all`.
- `Games` уже на потолке снифа: 10 публичных методов (конструктор и девять) и 5 зависимостей конструктора при потолке 6. Методы участника на `IGames`. HTTP снятия участника — `game.removeMember`; метод фасада в коде называется `deleteMember`.
- Character модуль Game не импортирует. `ICharacters::get` читает actual и не смотрит на игру. Компаратора листа в PHP нет. `CharacterSheetAccess` — единственный обход видимости листа Character; G3 его не вызывает и не ослабляет.

## Зафиксировано (модель)

Развилка акторов, 2026-10-04: подача и выход — владелец персонажа (`character.owner_id`). Строка `game_member` для этого не нужна. Запись `osBonus`, `orBonus`, `olBonus` — у кого есть `game.moderate` (владелец колонки `owner_id` или роль `gm`) либо глобальный `game.edit_all`.

Approve, return и reject — то же право, что запись бонуса: `game.moderate` или `game.edit_all`. `game.edit` ведущего на эти действия не переносится и `game.update` не переводится.

Развилка diff, 2026-10-04: G3 хранит snapshot и сам считает `clean` и `changes_pending` одним semantic diff snapshot к actual. Второго алгоритма нет. Предикаты `canStartSession`, `isActiveSessionParticipant` и `needsModeration` как допуск сессии остаются G4 и вызывают этот же `hasChanges`, не новый компаратор.

`returned` хранится. Пока маркер return стоит, наружу `reviewState = returned`, даже если diff пустой или нет. `clean` — маркера нет, snapshot есть и diff пустой. `changes_pending` — маркера нет и (snapshot нет или diff непустой). Колонки `review_state` нет.

Approve в G3 не вызывает `ICharacterSheets::validate` и `ICharacterRuleSlices::get`. У `validate` вход — `CharacterChoices` и срез, не сохранённая строка. Сборщик выборов не порт. Новый метод Character не заводится. Проверка листа остаётся на create/update Character. Допуск сессии с validation — G4.

На строке хранится `character_owner_id`: `owner_id` персонажа на момент submit. Смены владельца в `ICharacters` нет, колонка после submit не переписывается. Это FK на `user`, как `user_id` у участника. Список без `game.moderate` и без `game.edit_all` фильтрует по этой колонке и вызывает `ICharacters::get` только для своих строк. Чужой actual в этом запросе не читается.

## Что даёт этот заход

Таблица `game_character`. Подача существующего персонажа (`submitted`), approve, return, reject, выход (`left`), запись бонуса. Чтение строки и список строк игры. Лимит ОС/ОЛ/ОР этой строки считает Game и в `character` не пишет. Тесты фасада через новый порт `IGameMemberships`. Сосед по-прежнему берёт `IGames` для игры и участника; членство персонажа — `$locator->get(IGameContainer::class)->get(IGameMemberships::class)`.

## Что не закрыто

- Смена `rulesRevision` и предикаты допуска сессии — G4. После G3 diff уже есть; G4 не заводит второй.
- Сессия и порт «участник в сессии» для запрета `character.migrate` — G5.
- Сообщение return в обсуждение и `returnMessageId`. Отмена process при return.
- Запись видимости секций отдельным действием. Колонка есть, подача пишет пустой список.
- NPC, чаты, приглашения, бой, battleground, летопись, экономика, SSE, outbox, EventManager.
- `game.update` по-прежнему смотрит на `owner_id` и `game.edit_all`.
- Character, Vue поверх этого PHP.

## Модуль

Тот же `www/mifrial/modules/Roleplay/Game`. Порт `IGameMemberships` рядом с `IGames`: в `Games` одиннадцатый публичный метод и шестая зависимость конструктора уже не лезут в сниф, а approve и diff не входят в класс на 500 строк. `IGames` и методы G1–G2 не меняют смысл.

`GameSchema::getTableClasses()` дополняется `GameCharacterTable`. Конструктор принимает третий `IOpenedSchema`. `install()` приводит `game`, затем `game_member`, затем `game_character`. `events` остаётся `[]`. Suite тот же `game`. `ModuleSetupCollectorTest` не расширять. Порт `IGameMemberships` регистрируется в `ports` `module.config.php` так же, как `IGames`. Новые action — в `ports` и `routes`: handler из фабрики HTTP, `csrf` true.

Сценарий HTTP — новый класс, не методы `GameHttp`. У `GameHttp` уже 9 публичных методов при потолке снифа 10.

**DAG прод-PHP:** Game → SmartTable + User + RuleSpace + Character. Новое ребро — чтение `ICharacters`. `ICharacterRuleSlices` и `ICharacterSheets` этот заход не вызываются. Новых методов Character нет. `CharacterSheetAccess` не вызывается. Character по-прежнему не импортирует Game.

Ошибки: прежние `GAME_INVALID`, `GAME_NOT_FOUND`, плюс лист `GAME_CONFLICT` на устаревший CAS. Наружу не коды ST и не `CHARACTER_*`. Нет актора — `AUTH_REQUIRED`. Нет глобального ключа там, где он требуется, — `AUTH_DENIED` из `requireKey`. Нет права на операцию — `GAME_NOT_FOUND`, как карточка G1. Лишнее поле JSON ядро отвергает до handler: `INVALID_PARAMS`.

| Источник | Лист |
|---|---|
| нет игры; игра не видна актору для этой операции | `GAME_NOT_FOUND` |
| нет персонажа; актор не владелец персонажа там, где нужен владелец | `GAME_NOT_FOUND` |
| нет строки membership на approve, return, reject, leave, bonus, get | `GAME_NOT_FOUND` |
| второй не-`left` на тот же `characterId`; статус не допускает действие; пустая причина return; бонус не целое ≥ 0; игра `completed` | `GAME_INVALID` |
| `actual_version` или `membershipRevision` не совпали | `GAME_CONFLICT`, без записи snapshot и без записи actual |
| `ReferenceConstraintException` на `game_id`, `character_id` или `character_owner_id`; `RowNotFoundException` | `GAME_NOT_FOUND` |
| `FieldRequiredException` / `FieldInvalidException` / `MapInvalidException` / `UniqueConstraintException` / `RowWriteFailedException` | `GAME_INVALID` |

`ICharacters::get` бросает только `CharacterNotFoundException`. Фасад переводит её в `GAME_NOT_FOUND` и в `character` не пишет. `CharacterConflictException` этот заход не ловит: approve не вызывает запись Character, CAS сравнивает число из `CharacterRecord::getActualVersion()` с телом запроса.

`GameConflictException` — тот же приём, что `CharacterConflictException`: код `GAME_CONFLICT`, `getErrorDetails()` отдаёт `currentActualVersion` и `currentMembershipRevision`. Оба числа — то, что лежит в строках на момент отказа.

## Зачем колонки

**Пара игра+персонаж.** Заявка, не человек. Один `userId` владельца может иметь в одной игре несколько строк. Уникальность «не больше одной игры» — у персонажа, пока статус не `left`.

Физически это nullable `live_character_id`: равен `character_id`, пока статус `submitted` или `active`, и `null` после `left`. Unique на `live_character_id`. В MySQL несколько `null` допустимы, поэтому история `left` остаётся, а второй живой вход ловит и фасад, и уникальный ключ. Голый unique на `character_id` стёр бы повторный вход после `left`.

**Статус.** `submitted`, `active`, `left`. Reject удаляет только `submitted`. `active` и `left` reject не принимает.

**Snapshot.** Nullable JSON `approved_character_version`. Копия полей actual на момент успешного approve: `name`, `active`, `spaceId`, `rulesRevision`, `actualVersion`, `choices`, `sheet`. Это не строка `CharacterVersion` и не вторая история. До первого approve — `null`. Повторный approve заменяет копию и не дописывает журнал. Actual фасад не вызывает на запись (`replacePayload`, `replaceSaved`, `replaceMigrated`, `setActive` не вызываются).

**membershipRevision.** Int, с 1. Растёт на approve, return, leave и записи бонуса. Это не `actual_version`.

**Return.** `returned_at` и `return_reason` пишутся return-ом и снимаются успешным approve. `return_message_id` нет.

**Бонус.** `os_bonus`, `or_bonus`, `ol_bonus` — целые ≥ 0, по умолчанию 0. Денежного бонуса нет. `moneyLimit` игры этот заход не трогает.

**Владелец строки.** `character_owner_id` — `ReferenceField::forTable` на `user`, `restrict`, required. Пишется на submit из `CharacterRecord::getOwnerId()` и дальше не меняется. Leave и approve его не переписывают. Это фильтр списка, не роль `game_member`.

**Видимость секций в игре.** JSON-список кодов `CharacterSheetSection`. Подача пишет `[]`. Это не `character.visibility_fields` и не аудитория `SheetVisibility` эскиза. Отдельного HTTP записи в G3 нет.

**Лимит листа в этой игре.** Функция, не колонка и не запись в `character`. Для ОС, ОЛ и ОР по отдельности: потолок игры `null` → результата нет, бонус потолок не создаёт; иначе потолок плюс бонус этой строки. `moneyLimit` в функцию не входит.

## Diff

Класс в `Service/` Game. Вход — сохранённый snapshot и `CharacterRecord` из `ICharacters::get`. Наружу `hasChanges`. Сравниваются `name`, `rulesRevision`, `choices` и `sheet`. Ключи JSON сравниваются без учёта порядка. Вне diff: `id`, владелец, `owner_notes`, `visibility_fields`, `is_public`, метки времени, `actual_version` как счётчик (внутри snapshot версия хранится, в равенство листа не входит). Ключ `heldBy` внутри `wound`, если он есть в JSON, из сравнения выкидывается; в текущем PHP Character этого ключа нет. Operational battle markers сверх этого в G3 не появляются: сессии нет.

`isCharacterChanged` — только обёртка над `hasChanges`.

## Таблица `game_character`

Физическое имя `game_character`. Legacy `game_characters` из [`data-model.md`](data-model.md) — не эта схема. Только SmartTable. DDL: `createTable` / `updateTable`, не `forceUpdateTable`. IdField обычный.

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | Суррогат. |
| `game_id` | reference `GameTable`, `restrict`, required | Игра. |
| `character_id` | `ReferenceField::forTable(..., 'character', 'restrict')`, required, indexed | Персонаж. Не пользователь. Чужой модуль, как `user_id` у `game_member`. |
| `character_owner_id` | `forTable(..., 'user', 'restrict')`, required, indexed | Владелец персонажа на момент submit. |
| `live_character_id` | int, nullable, unique | `character_id` пока статус не `left`, иначе `null`. |
| `status` | string 32, required | `submitted`, `active`, `left`. |
| `approved_character_version` | JSON, nullable | Копия actual или `null`. |
| `membership_revision` | int ≥ 1, required | CAS membership. |
| `returned_at` | datetime, nullable | Момент return. |
| `return_reason` | text, nullable | Причина return. |
| `os_bonus`, `or_bonus`, `ol_bonus` | int ≥ 0, required, default 0 | Бонус этой строки. |
| `section_visibility` | JSON, required, default `[]` | Коды секций в игре. |

Unique на поле `live_character_id` (`FieldSettings` `unique`). `defineUniqueKeys()` в SmartTable принимает только кортеж из двух и больше колонок (`UniqueKeyMap`). Несколько `null` на nullable unique допустимы.

Колонок нет: `review_state`, `return_message_id`, overlay, роль, имя владельца, денежный бонус, сумма потолка.

`GameCharacterRecord` / `NewGameCharacter` / патчи approve, return и бонуса — `DEC-079`. Record из репозитория и из `listByGame` несёт хранимые поля и не вызывает `ICharacters`. `reviewState` на нём появляется только в `get`, `approve`, `returnToOwner`, `leave` и `setBonus`: эти методы уже читают actual. New на подаче не принимает snapshot и бонус: бонус 0, snapshot `null`, revision 1, видимость `[]`, `character_owner_id` равен владельцу персонажа.

Колонки `game` и `game_member` не меняются.

## Фасад `IGameMemberships`

| Метод | Смысл |
|---|---|
| `submit(int $gameId, int $characterId, int $ownerUserId): GameCharacterRecord` | Персонаж есть, `getOwnerId()` равен актору. Игра есть и не `completed`. Живого membership этого `characterId` нет. Insert `submitted`, `character_owner_id` = этот владелец. |
| `get(int $gameId, int $characterId): GameCharacterRecord` | Нет строки → `NOT_FOUND`. Без ACL, как `get` игры. `reviewState` уже посчитан: для этого метод читает actual через `ICharacters::get`. |
| `listByGame(int $gameId, ?int $ownerUserId): list<GameCharacterRecord>` | Строки игры, `id ASC`, `ListQuery::MAX_LIMIT`, offset 0, без total. `ownerUserId` задан — только строки с этим `character_owner_id`. `null` — все строки игры. Нет игры → `NOT_FOUND`. Actual здесь не читается. |
| `approve(...ожидаемые actualVersion и membershipRevision): GameCharacterRecord` | Статус `submitted` или `active`. Версии совпали. Пишет новую копию actual, статус `active`, снимает return, revision + 1. Validate не вызывает. Actual не пишет. Конфликт — ни snapshot, ни actual. `left` → `INVALID`, без write. |
| `returnToOwner(...revision, reason): GameCharacterRecord` | Статус `submitted` или `active`. Причина не пустая. Строка остаётся, пишутся `returned_at` и `return_reason`, revision + 1. Snapshot и actual не меняются. |
| `reject(int $gameId, int $characterId, int $expectedRevision): void` | Удаляет строку только при `submitted` и совпавшем revision. `active` и `left` → `INVALID`, строки нет только если это всё ещё `submitted`. |
| `leave(...revision): GameCharacterRecord` | Вызывает владелец персонажа. `submitted` или `active` → `left`, `live_character_id = null`, revision + 1. |
| `setBonus(...revision, os, or, ol): GameCharacterRecord` | Строка `submitted` или `active`. Три целых ≥ 0. Revision + 1. Snapshot, actual и return не меняются. |
| `pointsLimit(?int $gameCeiling, int $bonus): ?int` | `null` потолка → `null`. Иначе сумма. В таблицу не пишет. Вместе с конструктором это десятый публичный метод класса. `isCharacterChanged` на этот класс не класть. |

Чтение actual для модерации и для `reviewState` — `ICharacters::get` после проверки права. HTTP списка не отдаёт Record из `listByGame` как есть: у него нет `reviewState`. Модератор (`game.moderate` или `game.edit_all`) берёт все id и для каждого зовёт `get`. Владелец персонажа зовёт `listByGame($gameId, $actorId)` и `get` только по этим строкам.

Создание «из игры» фасад не делает. Клиент вызывает обычный `character.create`, затем `submit`. Лимиты игры в `character.create` не кладутся. Второго create внутри Game нет.

## HTTP

Тонкие action, сценарий рядом с портом membership. CSRF как у G1. Вход — свой DTO.

| Action | Кто | Тело |
|---|---|---|
| `game.submitCharacter` | `character.owner_id` | `{ gameId, characterId }` |
| `game.approveCharacter` | `game.moderate` или `game.edit_all` | `{ gameId, characterId, actualVersion, membershipRevision }` |
| `game.returnCharacter` | то же | `{ gameId, characterId, membershipRevision, reason }` |
| `game.rejectCharacter` | то же | `{ gameId, characterId, membershipRevision }` |
| `game.leaveCharacter` | `character.owner_id` | `{ gameId, characterId, membershipRevision }` |
| `game.setCharacterBonus` | `game.moderate` или `game.edit_all` | `{ gameId, characterId, membershipRevision, osBonus, orBonus, olBonus }` |
| `game.getCharacter` | владелец этой строки, `game.moderate` или `game.edit_all` | `{ gameId, characterId }` |
| `game.getCharacterList` | `game.moderate` или `game.edit_all` — все строки; владелец персонажа без этого права — только свои строки этой игры | `{ gameId }` |

Ответ строки — плоский объект: `gameId`, `characterId`, `characterOwnerId`, `status`, `membershipRevision`, `reviewState`, `returnedAt`, `returnReason`, `osBonus`, `orBonus`, `olBonus`, `sectionVisibility`, `approvedCharacterVersion` (`null` или копия). Список — массив таких объектов. Reject — `null`. Имён и `permissions` нет. `characterOwnerId` — колонка строки, не имя.

`game.view_all` лист через эти action не открывает и `ICharacters::get` ради чужого персонажа не получает.

Дефекты эскиза, в фасад не копировать:

- `game.createCharacter` с телом `character.create` внутри Game;
- один `game.moderateCharacter` / `game.moderateCharacterCommand` с полем `action` вместо отдельных approve, return и reject; у команды нет `reason`;
- `game.leaveGame` как имя выхода персонажа;
- `game.updateCharacterGrants` и бонус не на этой строке;
- `returnMessageId`, `overlay`, `role`, `characterOwnerName`, `characterName`, `updatedAt` на `GameCharacterMembership`;
- `visibility` как `SheetVisibility` (audience `all` / `gm` / user ids);
- `game.updateMembershipVisibility` — отдельной записи секций в G3 нет;
- `game.submitCharacterMigration` — G4, и миграцию делает Character, не Game.

## Тесты

Mysql: `GameMysqlFixture` ставит `CharacterSchema` (`character`, `character_viewer`) до `GameSchema`. `game_character.character_id` ссылается на `character`, а `character` — на `user` и `rule_space`, которые фикстура уже ставит и в конце сносит. `dropGameTables` снимает `game_character` до `game_member` и `game`, затем `character_viewer` и `character`, и только потом rule и user. Иначе `restrict` не даст снести `rule_space`. Персонажа тест создаёт через `ICharacters::add`: владелец — учётка фикстуры, `spaceId` — мир фикстуры, `rulesRevision` 1, имя непустое, `choices` и `sheet` — массивы. `Characters::add` проверяет учётку и trim имени; пустое имя даёт `CharacterInvalidException`, такой строки в тесте нет. HTTP `character.create` не вызывается. Правка actual после approve — `ICharacters::replacePayload`, не запись из Game.

- submit+get: `submitted`, snapshot `null`, бонусы 0, revision 1, `reviewState = changes_pending`;
- тот же владелец, второй персонаж, та же игра — две строки;
- тот же `characterId` во второй игре, пока первая не `left` — `INVALID`, второй строки нет;
- после `left` новый submit того же персонажа в другую игру проходит;
- чужой user на submit и leave — `NOT_FOUND`, строка прежняя;
- submit без строки `game_member` проходит и пишет `character_owner_id`;
- список с `ownerUserId` не содержит чужих строк и не читает их actual; `null` возвращает все строки игры;
- approve `submitted`: статус `active`, snapshot равен actual, actual в таблице `character` тот же, revision 2, return пустой, `reviewState = clean`;
- правка actual через Character после approve даёт `changes_pending` без записи в `game_character`;
- повторный approve копирует новый actual и снова `clean`;
- CAS: неверный `actualVersion` или `membershipRevision` — `GAME_CONFLICT`, в details текущие `currentActualVersion` и `currentMembershipRevision`, snapshot и actual прежние;
- reject `submitted` удаляет строку; reject `active` — `INVALID`, строка на месте;
- return пишет причину и `returned`, строку не удаляет; пустая причина — `INVALID`;
- leave переводит в `left` и освобождает `live_character_id`;
- бонус: потолок игры `null` → лимит `null` при бонусе > 0; потолок 10 и бонус 2 → 12; `moneyLimit` без слагаемого;
- отрицательный бонус — `INVALID`;
- игра `completed`: submit, approve, return, reject, leave, bonus — `INVALID`, строки прежние;
- `player` без `game.moderate` не approve и не пишет бонус.

HTTP mysql: без актора — `AUTH_REQUIRED`; submit чужого персонажа — `NOT_FOUND`; submit владельцем — 200; `game.edit_all` делает approve в чужой игре; одного `game.view_all` недостаточно, чтобы прочитать чужой лист; вход с `returnMessageId` или `overlay` — `INVALID_PARAMS`; конфликт approve — `GAME_CONFLICT`.

Unit: `reviewState` для четырёх сочетаний (нет snapshot, diff пустой, diff непустой, маркер returned); `pointsLimit` для `null` и числа; diff игнорирует порядок ключей и `wound.heldBy`.

Существующие тесты G1–G2 остаются зелёными: create игры и add участника не пишут `game_character`.

cs/quality дерева Game. Suite `character` не гонять: Character не меняется. Отдельная проверка: в `modules/Roleplay/Character` нет импорта `Roleplay\Game`.

## Todo

- [x] **table** — карта `game_character`, unique `live_character_id`, schema `install`.
- [x] **facade** — порт `IGameMemberships`: submit, get, list, approve, return, reject, leave, setBonus, `pointsLimit`.
- [x] **diff** — один компаратор, `reviewState` без колонки.
- [x] **http** — восемь action, плоский JSON без полей эскиза из списка дефектов.
- [x] **gates** — phpunit `game`; cs/quality. Character не импортирует Game. Game не пишет `character`.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| `GameCharacterTable` | `Table/` | карта |
| Record / New / patch | `Dto/` | строка персонажа |
| вход HTTP | `Dto/Action/` | восемь DTO |
| `IGameMemberships` | `Interface/Service/` | порт, не методы `IGames` |
| сценарий и CAS | `Service/` | статусы, бонус, лимит, владелец строки |
| компаратор | `Service/` | semantic diff |
| репозиторий | `Repository/` | records строки |
| `GameConflictException` | `Exception/` | CAS, details двух версий |
| action | `Action/` | восемь handler |

[`game-plan-01.md`](game-plan-01.md) и [`game-plan-02.md`](game-plan-02.md) не меняются. Колонки `game` и `game_member` не меняются. Файлы, куда входит третья карта (`GameSchema`, фабрика, `module.config.php`, фикстура mysql), расширяются. `Games` и `IGames` не растут.

## Acceptance G3

- Suite `game` зелёный; cs/quality модуля.
- Нет нового импорта Game из Character. Game не пишет таблицу `character` и не вызывает `CharacterSheetAccess`.
- Второй не-`left` того же `characterId` — отказ. Несколько персонажей одного владельца в одной игре — да.
- Approve пишет snapshot и не меняет actual. Validate не вызывается. Конфликт не меняет ни snapshot, ни actual.
- Список без права модерации фильтрует по `character_owner_id` и не читает чужой actual.
- Reject не удаляет `active`. Return оставляет строку и причину.
- `clean` и `changes_pending` считаются diff-ом. `returned` хранится.
- Лимит ОС/ОЛ/ОР — потолок игры плюс бонус строки; `null` потолка остаётся `null`. В `character` суммы нет.
- `game.update` по-прежнему смотрит на `owner_id` и `game.edit_all`.
- Статус линии: `BACKEND_OPEN`.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G3; [`game-plan-02.md`](game-plan-02.md); [`game-plan-01.md`](game-plan-01.md); [`character-system.md`](character-system.md); [`character-roadmap.md`](character-roadmap.md).

## Следующий заход

G4 — смена ревизии и предикаты допуска. Компаратор G3 они не заменяют. Сессия и порт запрета migrate — G5.
