# План Game 1 — каркас модуля и строка игры

**Статус:** каркас PHP сделан, 2026-10-04. `BACKEND_OPEN`. Suite `game` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G1. Канон — [`game-system.md`](game-system.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Устройство lazy-модуля — [`character-plan-02.md`](character-plan-02.md), не образец полей.

Цель: ленивый **`Roleplay/Game`**, одна карта строки игры, фасад и четыре HTTP. Сессии нет: `sessionRunning` в ответе всегда `false`. Character не меняется и Game не импортирует.

## Зафиксировано (модель)

- Статусы: `draft | recruiting | in_process | paused | completed`. Слова `playing` нет. `in_process` — фаза кампании, не живой стол.
- Направленного графа в каноне нет. Create и update принимают любой из пяти статусов. Между `draft`, `recruiting`, `in_process`, `paused` можно переходить в любую сторону, в том числе в `completed`. Строка `completed` отвергает любую запись.
- `sessionRunning` — признак ответа, не колонка и не вход. Истинен, только когда есть текущая сессия. До G5 сессии нет, признак ложен. `game.create` / `game.update` его не принимают.
- `spaceId` и `rulesRevision` задаёт create и больше не меняет. Смена ревизии — G4. `spaceCode` копирует сервер из RuleSpace в момент create. Код мира после создания не меняется (`RuleSpacePatch` — только name и description). Выключенный мир (`RuleSpaceRecord::isActive() === false`) → `GAME_INVALID`, `getRevision` не вызывается. Так же лист отсекает мир в `CharacterRuleSlices`.
- Потолки `osPointsLimit`, `olPointsLimit`, `orPointsLimit`, `moneyLimit`: `null` — потолка нет. Бонусов ОС/ОЛ/ОР на строке нет. Сумму «потолок игры + бонус персонажа» этот заход не считает.
- `visibility` и `join_policy` хранятся и в G1 не толкуются. Предикаты `friends` / `players` / `invited` / `whitelist` и join — один поздний заход, когда появятся люди и приглашения. До него карточку видят владелец и `game.view_all`.
- Владелец — актор create, не поле клиента.

## Что даёт этот заход

Строка игры в MySQL и round-trip JSON четырёх действий. Тесты фасада через `IGames`. Сосед: `$locator->get(IGameContainer::class)->get(IGames::class)`.

## Что не закрыто

- Участники `(gameId, userId)`, роли, `game.edit` / `game.moderate` / `game.manage`.
- Строка персонажа, `approvedCharacterVersion`, бонусы персонажа, лимит листа.
- Сессия, `game.startSession`, `game.stopSession`. Пока их нет, `sessionRunning` ложен.
- Смена `rulesRevision`, предикаты допуска, порт Character.
- Применение `visibility` и `join_policy`. Чаты, приглашения, `personalNotes`, NPC, летопись, экономика, бой, SSE, outbox, EventManager.
- Character, `character.migrate`, Vue поверх этого PHP.

## Модуль

Путь: `www/mifrial/modules/Roleplay/Game`. Неймспейс `Mifrial\Roleplay\Game`. **lazy** в `config/modules.php`: `IGameContainer` → group `Roleplay`, name `Game`. `module.config.php`: четыре routes ниже; `events` `[]`; в карту портов — `IGames`. `setup` → `GameModuleSetup`: `getTableClasses()` = `GameSchema::getTableClasses()` (`game`), data-steps `[]`.

Suite `game` в `phpunit.xml.dist`. PSR-4 `Mifrial\Roleplay\Game\` и `Mifrial\Roleplay\Game\Tests\`, `exclude-from-classmap` tests, как Character. `ModuleSetupCollectorTest` не расширять.

**DAG прод-PHP:** Game → SmartTable + User (`IUserAccounts`, `IUserAccess`) + RuleSpace (`IRuleSpaces`). Карта: `owner_id` / `space_id` — `ReferenceField::forTable` (`user`, `rule_space`). Сервисы не импортируют Character, Chat, Keyword, Mechanic, Rule, Versioning. Kernel ↛ Game. Character ↛ Game. Тесты mysql ставят ревизию так же, как `RuleSpaceMysqlTest`: схемы User, Keyword, Mechanic, Rule, RuleSpace и публикация через `IRuleSpaces`. Вставка одной строки `rule_space`, как `CharacterMysqlTest::addSpace`, ревизии не даёт: у часов нет sidecar, `IRuleSpaces::get` её не видит.

Ошибки: `GameException` extends `ActionException`. Листья: `GAME_INVALID`, `GAME_NOT_FOUND`. Наружу не коды ST (`MAP_*`, `REFERENCE_CONSTRAINT`, `UNIQUE_*`), не `USER_*` и не `RULESPACE_*`. Нет актора — `AUTH_REQUIRED` из `IUserAccess::requireActor`. Нет ключа — `AUTH_DENIED` из `requireKey`, не `GAME_*`. Лишнее поле JSON (`sessionRunning`, chat id, теги) ядро отвергает до handler: `INVALID_PARAMS`, `Unknown parameter` (`ActionParameterBinder`). Это не `GAME_INVALID`.

| Источник | Лист |
|---|---|
| нет строки игры; чужая строка без `game.view_all` / без `game.edit_all` | `GAME_NOT_FOUND` |
| `RuleSpaceNotFoundException`, нет ревизии | `GAME_NOT_FOUND` |
| `UserNotFoundException` / `ReferenceConstraintException` на владельца | `GAME_NOT_FOUND` |
| `RuleSpaceInvalidException` | `GAME_INVALID` |
| `FieldInvalidException` / `FieldRequiredException` / `MapInvalidException` | `GAME_INVALID` |
| пустое имя, статус не из пяти значений, отрицательный потолок, выключенный мир, `spaceCode` не совпал с миром, присланные `spaceId` / `rulesRevision` не равны строке, запись в `completed` | `GAME_INVALID` |

Не писать «SQLSTATE» в коде — только исключения ST.

## Зачем колонки

**`space_id` — reference; `rules_revision` — номер; `space_code` — копия.**  
`spaceId` API = id `rule_space` (`IRuleSpaces::get`). Нет мира или номера → `GAME_NOT_FOUND`. Мир есть, но `isActive()` ложен → `GAME_INVALID`, `getRevision` не вызывать. Иначе ревизия проверяется `IRuleSpaces::getRevision(spaceId, revision)`, не «latest» и не Versioning. `space_code` берётся из `RuleSpaceRecord`, не из клиента. Клиентский `spaceCode`, если пришёл и не равен коду мира, → `GAME_INVALID`.

**Статус и `completed`.** Колонка `status`. Update строки `completed` не открывает запись. Переход в `completed` — последняя успешная запись статуса.

**Потолки.** Четыре колонки, NULL разрешён. `null` не заменяется нулём. Отрицательное и не-int → `GAME_INVALID`.

**Видимость.** Колонки `visibility` и `join_policy` пишутся как присланы и отдаются как лежат. Список и карточка ими не фильтруются.

## Таблица `game`

Физическое имя `game`. Только SmartTable, без сырого SQL. DDL: `createTable` / `updateTable`, не `forceUpdateTable`. IdField обычный (не `IdField::big()`), как Character.

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | `gameId`. |
| `owner_id` | `forTable(..., 'user', 'restrict')` required | Актор create. |
| `name` | string 255, required | Trim; пустой → `GAME_INVALID`. |
| `short_description` | string 255, required, default `''` | Trim. Пустая строка допустима. Клиентский `null` → `''`. |
| `description` | text, required, default `''` | То же. |
| `status` | string 32, required | Пять значений выше. Иное → `GAME_INVALID`. |
| `visibility` | string 32, required | `all \| friends \| players \| invited \| whitelist`. Иное → `GAME_INVALID`. Не фильтр. |
| `join_policy` | string 32, required | `anyone \| friends \| invite_only \| whitelist`. Иное → `GAME_INVALID`. Не фильтр. |
| `space_id` | `forTable(..., 'rule_space', 'restrict')` required | Часы мира. |
| `space_code` | string 255, required | Копия кода RuleSpace. |
| `rules_revision` | int, required, min 1 | Номер ревизии. |
| `os_points_limit` | int, `required` false, min 0 | Потолок ОС. `null` — нет потолка. `required` true не ставить: `BaseField` при required и `null` бросает `FieldRequiredException`. |
| `ol_points_limit` | int, `required` false, min 0 | Потолок ОЛ. То же. |
| `or_points_limit` | int, `required` false, min 0 | Потолок ОР. То же. |
| `money_limit` | int, `required` false, min 0 | Потолок денег. Без бонуса персонажа. То же. |
| `created_at` | `DateTimeField`, required, default `DateTimeNow` | Как `CharacterTable`: тип поля `DateTimeField`, default `DateTimeNow::instance()`. |
| `updated_at` | `DateTimeField`, required, default `DateTimeNow` | Фасад на insert и update передаёт `DateTime::now()`, как `Characters`. |

Колонок нет: бонусы, `session_running`, чаты, теги, `member_count`, заметки, optimistic lock.

`GameSchema`: один `IOpenedSchema`. `getTableClasses()`: `GameTable`.

`NewGame` / `GameRecord` — `DEC-079`: Record геттеры; New — `fromNormalized` + `fields()`. Не публичный мешок.

Индексы: FK owner и space, `status`.

Граф mysql-теста: схемы как у `RuleSpaceMysqlTest` (User, Keyword, Mechanic, Rule, RuleSpace), мир и опубликованная ревизия через `IRuleSpaces`, затем Game schema. Drop в обратном порядке, вместе с таблицами, которые тест создал. Не копировать `CharacterMysqlTest::addSpace`.

## Фасад `IGames`

Не `IGame`. Не HTTP-вид.

| Метод | Смысл |
|---|---|
| `add(NewGame $new): int` | Сначала `IUserAccounts::getById(owner)`. `IRuleSpaces::get`. Мир выключен → `INVALID`, без `getRevision`. Иначе `getRevision`. Insert. Нет user / мира / ревизии → `NOT_FOUND`. Пустое имя, revision &lt; 1, плохой enum, потолок &lt; 0 → `INVALID`. |
| `get(int $id): GameRecord` | Нет строки → `NOT_FOUND`. Без ACL (как Character `get`). |
| `update(int $id, GamePatch $patch): GameRecord` | Нет строки → `NOT_FOUND`. Строка `completed` → `INVALID`, без write. Пишет только имя, описания, статус, visibility, join policy и четыре потолка. `spaceId`, `rulesRevision`, `spaceCode` и владелец в patch нет, фасад их не меняет. |
| `listByOwnerOrAll(int $ownerUserId, bool $viewAll): list<GameRecord>` | `viewAll` — все строки. Иначе `owner_id = ownerUserId`. Без разбора visibility. |

`NewGame`: ownerUserId, name, shortDescription, description, status, visibility, joinPolicy, spaceId, rulesRevision, четыре потолка (`?int`). Нет `spaceCode` (его ставит фасад), нет `sessionRunning`, нет чатов.

`GameRecord`: геттеры id, ownerId, name, shortDescription, description, status, visibility, joinPolicy, spaceId, spaceCode, rulesRevision, четыре потолка, createdAt, updatedAt (`DateTime` Kernel). `sessionRunning` на Record нет.

`GamePatch`: имя, описания, статус, visibility, join policy, четыре потолка. Без владельца, space и `spaceCode`. Сверка присланных `spaceId` / `rulesRevision` / `spaceCode` со строкой — в HTTP-сценарии до `update`. Расхождение → `GAME_INVALID`, фасад не вызывается.

Фабрика: gateway ST + `IUserContainer` → `IUserAccounts` + `IRuleSpaceContainer` → `IRuleSpaces`. Ctor фасада ≤ 6.

## HTTP

Тонкие action, сценарий рядом с фасадом. CSRF как у Character. Вход — свой DTO, не мешок Vue.

| Action | Кто | Тело |
|---|---|---|
| `game.create` | `requireKey('game.create')`. Владелец = актор. | name, shortDescription, description, status, visibility, joinPolicy, spaceId, rulesRevision, четыре потолка. Описания в DTO — `?string`: JSON `null` биндер не кладёт в `string` (`Invalid parameter`), сервис делает `''`. Потолки — `?int` без default: ключ обязателен, значение `null` допустимо. `spaceCode` — `?string` default `null`: нет ключа или `null` — не сверять; строка сверяется с кодом мира. |
| `game.get` | актор. Чужая без `game.view_all` → `GAME_NOT_FOUND`. | `{ id }`. |
| `game.getList` | актор. `game.view_all` — все, иначе свои. | без тела. |
| `game.update` | владелец или `game.edit_all`. Чужой → `GAME_NOT_FOUND`. | `{ id }` и те же поля, что create. |

Ответ get / create / update — плоский объект: id, name, shortDescription, description, status, `sessionRunning: false`, visibility, joinPolicy, ownerId, spaceId, spaceCode, rulesRevision, четыре потолка, gameChatId `null`, discussionChatId `null`, createdAt, updatedAt (unix, как RuleSpace). Список — те же поля карточки без description и без четырёх потолков: id, name, shortDescription, status, sessionRunning, visibility, joinPolicy, ownerId, spaceId, spaceCode, rulesRevision, два chat id `null`.

Дефекты эскиза, в фасад не копировать:

- вложенный `GameDetail` (`game` + limits + `members`);
- `ownerName`, `memberCount`, `tags`, `forbiddenTags`, `personalNotes`, `members`;
- `sessionRunning` и chat id во входе;
- `game.stopSession(targetStatus)` и `game.stopStateSession` — не этот шаг.

## Тесты

Mysql (`MIFRIAL_CONFIG=test`): User + мир RuleSpace с ревизией → Game.

- add+get: поля, `spaceCode` с мира, потолок `null` остаётся `null`, статус как задан;
- нет user / нет мира / нет ревизии → `NOT_FOUND`;
- мир после `IRuleSpaces::deactivate` → `INVALID`, строки нет;
- пустое имя, revision 0, статус `playing`, visibility не из списка, потолок −1 → `INVALID`;
- update меняет имя, статус `paused` → `recruiting`, потолок с числа на `null`; `spaceId` и `rulesRevision` после update те же;
- update в `completed`, затем второй update → `INVALID`, строка `completed`;
- два add с одним именем — ок;
- get / update неизвестного id → `NOT_FOUND`;
- list: чужая строка не входит без viewAll и входит с viewAll;
- boot: `lazy`, `get(IGames)`; routes четырёх действий, `events` `[]`.

HTTP mysql: без актора — `AUTH_REQUIRED`; create без ключа — `AUTH_DENIED`; create с ключом — владелец = актор, в JSON `sessionRunning === false` и оба chat id `null`; клиентский `spaceCode` другого мира → `GAME_INVALID`, строки нет; update с другим `spaceId` или `rulesRevision` → `GAME_INVALID`, строка прежняя; get чужого → `NOT_FOUND`; get с `game.view_all` → 200; update владельца пишет статус; update чужого → `NOT_FOUND`; `game.edit_all` пишет чужую не-`completed` строку; вход с `sessionRunning` → `INVALID_PARAMS`, не `GAME_INVALID`.

Unit: trim name; `null` потолка не становится 0; разбор enum; ключ `completed`; мок `IRuleSpaces`: выключенный мир → `INVALID`, `getRevision` не вызывается.

cs/quality дерева Game. Не suite `character` / `rule`.

## Todo

- [x] **module** — lazy, контейнер, setup, autoload, suite `game`.
- [x] **table** — карта `game` + Schema `install`.
- [x] **facade** — add / get / update / list, сверка RuleSpace, запрет записи `completed`.
- [x] **http** — четыре action, плоский JSON, `sessionRunning` всегда false.
- [x] **gates** — phpunit `game`; cs/quality.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| `GameTable` | `Table/` | карта |
| Record / New / Patch | `Dto/` | геттеры / ключи insert / поля update |
| вход HTTP | `Dto/Action/` | четыре DTO |
| `IGames` | `Interface/Service/` | сосед |
| `IGameContainer` | `Interface/Container/` | слот |
| `Games` | `Service/` | trim, enum, потолки, RuleSpace, completed |
| сценарий HTTP | `Service/` | ключи, список, сборка JSON |
| `GamePortFactory` | `Service/` | `new` + User + RuleSpace |
| репозиторий | `Repository/` | records, map FK |
| Schema | `Schema/` | один opened schema |
| `GameModuleSetup` | `Setup/` | карта в CLI |
| action | `Action/` | четыре handler |
| листья | `Exception/` | `GAME_*` |

Нормализатор — в `Service/` (не отдельный порт, пока один сток).

## Acceptance G1

- Suite `game` зелёный; cs/quality модуля.
- Нет импорта Character и Chat. Character не импортирует Game.
- Ревизия проверена через `IRuleSpaces::getRevision`. Колонки сессии, чата и бонуса нет.
- `sessionRunning` в каждом ответе `false` и отсутствует во входе.
- `completed` отвергает следующую запись. `visibility` на выборку не влияет.
- Статус линии: `BACKEND_OPEN`.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G1; `www/mifrial/composer.json` (PSR-4 `Game` + `Game\\Tests`, exclude-from-classmap `modules/Roleplay/Game/tests/`); `www/mifrial/phpunit.xml.dist` suite `game`; `www/mifrial/config/modules.php`.

## Следующий заход

G2 — участники-люди и роли. G1 после этого остаётся строкой игры без сессии и без толкования видимости.
