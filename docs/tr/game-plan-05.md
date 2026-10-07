# План Game 5 — одна текущая сессия без боя

**Статус:** сессия без боя в PHP сделана, 2026-10-04. `BACKEND_OPEN`. Suite `game` и `character` зелёные. Нарезка — [`game-roadmap.md`](game-roadmap.md) G5. Допуск — [`game-plan-04.md`](game-plan-04.md). Строка персонажа — [`game-plan-03.md`](game-plan-03.md). Участник — [`game-plan-02.md`](game-plan-02.md). Строка игры — [`game-plan-01.md`](game-plan-01.md). Сессия и migrate — [`game-system.md`](game-system.md), [`character-system.md`](character-system.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: у игры появляется одна текущая сессия и снимок персонажей, вошедших на старте. Старт и `game.stopSession` не меняют статус кампании. Пока сессия есть, `spaceId`, `spaceCode` и `rulesRevision` не меняются. `isActiveSessionParticipant` читает этот снимок. `character.migrate` участника снимка отклоняется портом Character, который реализует Game.

## Сверка G1–G4

Код `Roleplay/Game` и `character.migrate` совпадает с принятыми планами в том, на чём стоит G5.

- Таблица `game` без колонки сессии. `GameRecord` сессии не несёт. `GameViewAssembler` пишет `sessionRunning: false` в список и в карточку. `game.create` и `game.update` признак не принимают.
- `Games::update` пишет имя, описания, статус, visibility, join policy, потолки и `rules_revision`. `completed` отсекает любую запись. Чужой `spaceId` и чужой `spaceCode` режет `GameHttp::assertSameWorld` до фасада. Номер ревизии фасад меняет, пока игра не `completed`: чтения сессии нет, потому что таблицы нет.
- Владелец — `owner_id`. `game.update` пускает его и `game.edit_all`. `gm` с `game.edit` и без `game.edit_all` update не получает. `GamePermissionKeys::keysFor`: владелец — `game.edit`, `game.moderate`, `game.manage`; роль `gm` — `game.edit` и `game.moderate`; `player` — пусто.
- Строка `game_character`, один `GameCharacterDiff::hasChanges`. `canStartSession` — `active`, snapshot есть, diff пустой, ревизия actual равна ревизии игры, return снят, `ICharacterSheets::acceptsStoredChoices` истинен. `GameCharacterReview::isActiveSessionParticipant` принимает `gameId` и `characterId` и возвращает `false`.
- `character.migrate` — `CharacterMigration::migrate`. Вход без `gameId`. Пишет actual через `replaceMigrated` после пустых problems. Сессию не спрашивает. Character модуль `Roleplay\Game` не импортирует. Конструктор `CharacterMigration` — четыре зависимости. `GameCharacterHttp` — шесть, это потолок конструктора.

## Зафиксировано (модель)

Развилка состава, 2026-10-04: вариант 3. Текущая сессия — своя строка. Состав — отдельные строки «эта сессия + персонаж», снимок старта, не пересчёт diff. Список id на `game` и bool-колонка не используются: строка кампании не получает версию стола и id, на который дальше сядут бой и process, а NPC не является строкой `game_character`. Историю прошлых сессий эта пара таблиц не хранит.

`sessionRunning` истинен ровно когда строка сессии этой игры есть. Колонки признака на `game` нет.

Статус строки сессии в G5 один: `playing`. Это не статус кампании и не `active` membership. Других значений шаг не пишет.

Техническая версия — целое на строке сессии, со старта равна 1. В JSON старта и stop её нет, stop её не сверяет. Колонка остаётся родителем для команд, которые сверяют версию сессии. Бой в эту таблицу не входит.

Состав на старте — те строки `game_character` этой игры, у которых `canStartSession` истинен. Остальные не входят и старт не блокируют. Ноль таких строк — сессия есть, состав пуст. `changes_pending` после старта из состава не удаляет и в следующий старт не пускает: следующий старт снова зовёт `canStartSession`.

`isActiveSessionParticipant(int $gameId, int $characterId)` начинает читать пару этой игры. Смысл прежний: уже в текущей сессии. Это не `canStartSession` и не «active и не returned».

Порт Character отвечает на другой вопрос, потому что у `character.migrate` нет `gameId`: этот `characterId` есть в составе какой-либо текущей сессии. Имя и смысл метода Game не подменяются портом.

## Что даёт этот заход

`game.startSession` и `game.stopSession`. Таблицы `game_session` и `game_session_character`. `sessionRunning` в уже существующих ответах игры считается из строки сессии. Запрет migrate участника снимка.

## Что не закрыто

- Бой, `game.startBattle`, `game.endBattle`, `game.submitCombatCommand`, battleground, `ISpatialResolver`.
- Process, offer, initiative, effects, каст, DOT, decay.
- NPC в составе. Колонка под NPC в G5 не заводится.
- Чаты, приглашения, летопись, экономика, SSE, outbox, EventManager.
- Сообщение return.
- C8 и C9 целиком.
- `game.edit` ведущего на `game.update` по-прежнему не переносится.
- Vue поверх этого PHP.

## Точки кода G1–G4

Старые `game-plan-01.md`–`game-plan-04.md` не переписываются. Ниже только те места, без которых сессия не запирает ревизию или `isActiveSessionParticipant` остаётся ложью.

- `Games::update`. Если у игры есть строка сессии и patch несёт другой `rulesRevision` — `GAME_INVALID`, колонка прежняя. Тот же номер пишется как сейчас. Чужой `spaceId` и `spaceCode` по-прежнему режет `GameHttp::assertSameWorld`: в patch их нет. Если patch переводит статус в `completed`, а строка сессии есть — `GAME_INVALID`. Иначе кампания станет только для чтения вместе с живой сессией, и stop будет нечем вызвать. Прочие поля карточки во время сессии пишутся как раньше. `paused` сессию не гасит и сам по себе старт не запрещает. Шестая зависимость `Games` и пятая `GameHttp` — чтение наличия строки; `GamePortFactory::create` и `createHttp` её прокидывают. У `Games` это потолок конструктора.
- `GameViewAssembler`. Литерал `false` заменяется аргументом вызывающего. `game.create` по-прежнему отдаёт ложь: сессии только что созданная игра не имеет. `game.get`, `game.getList` и успешный `game.update` читают наличие строки.
- `GameCharacterReview::isActiveSessionParticipant`. Тело читает состав текущей сессии этой пары. Сигнатура та же. Третья зависимость — интерфейс чтения состава внутри Game, не `final`-репозиторий: `GameCharacterDiffTest` трижды собирает `GameCharacterReview` и подменяет `ICharacterSheets`, репозиторий так не подменить. `GamePortFactory::review` передаёт реализацию. `GameCharacterMemberships` и `GameCharacterHttp` седьмую зависимость не получают: они уже зовут этот метод. Тот же интерфейс использует адаптер порта Character; у порта вопрос по одному `characterId`.
- `CharacterMigration::migrate`. Сразу после загрузки своей строки и до `prepare`: истина порта — `CharacterInvalidException`, срез и `replaceMigrated` не вызываются. Проверка после `prepare` пропускала бы ответ `conflicts` у участника сессии. Ложь — прежний сценарий. Запрет не входит в `acceptsStoredChoices` и не в правила листа.
- `CharacterPortFactory::createMigration` и три `new CharacterMigration` в `CharacterMigrationTest`. Пятая зависимость — порт, в этих тестах он ложен. Реализация регистрируется в `ports` `Game/module.config.php`. Замыкание `MigrateCharacterAction` в `Character/module.config.php` только делает `get` с `IGameContainer` и не конструирует класс Game. Это единственный импорт Game со стороны Character. `Service/`, `Action/`, `Repository/` Character по-прежнему не импортируют `Roleplay\Game`. Фабрика этой реализации открывает шлюз SmartTable и не берёт порт Character: контейнер Character в этот момент уже разрешает `MigrateCharacterAction`.

## Модуль

Тот же `Roleplay/Game` плюс порт в `Character/Interface/Service/`. `events` остаётся `[]`. Suite `game` и suite `character`.

**DAG:** Game → SmartTable + User + RuleSpace + Character. Новое ребро сборки — конфиг Character читает контейнер Game, чтобы передать порт в `CharacterMigration`. Character не начинает импортировать `Service/` Game.

Ошибки прежние: `GAME_INVALID`, `GAME_NOT_FOUND`, `GAME_CONFLICT`, `CHARACTER_INVALID`, `AUTH_REQUIRED`, `AUTH_DENIED`, `INVALID_PARAMS`.

| Источник | Лист |
|---|---|
| нет игры; актор без `game.edit` на этой игре и без `game.edit_all`; actual строки состава не читается | `GAME_NOT_FOUND` |
| игра `completed`; stop, когда строки сессии нет; битый snapshot на старте (`GameInvalidException` из diff) | `GAME_INVALID` |
| старт, когда строка сессии уже есть, в том числе `UniqueConstraintException` по `game_id` | `GAME_CONFLICT` |
| `targetStatus`, `sessionId`, техническая версия, `participantEntityKeys` во входе | `INVALID_PARAMS` |
| migrate, когда `characterId` есть в составе текущей сессии | `CHARACTER_INVALID` |
| нет актора | `AUTH_REQUIRED` |

`game.edit` не глобальный ключ каталога. `requireKey('game.edit')` не вызывается: у владельца ключ выводится из `owner_id`, у `gm` — из роли строки. Нет этого вывода и нет `game.edit_all` — тот же `GAME_NOT_FOUND`, что у чужого `game.update`. `player` старт и stop не получает.

## Таблицы

Обе карты в `GameSchema::getTableClasses` и в `install()` после `game_character`: сначала `game_session`, затем `game_session_character`. Состав ссылается на сессию. Конструктор `GameSchema` сейчас принимает три `IOpenedSchema`; станет пять. Единственный `new GameSchema` — `GameMysqlFixture::installGameSchemas`. DDL — `createTable` / `updateTable`. Unique пары состава — `defineUniqueKeys`, как у `game_member`. Unique одной сессии на игру — `unique` на поле `game_id`: кортеж из одного поля `UniqueKeyMap` отвергает. `dropGameTables` сначала удаляет состав, потом сессию, затем прежний порядок с `game_character`.

**`game_session`.** Одна текущая сессия игры. Unique по `game_id`: вторая строка той же игры не вставляется, stop строку удаляет, следующая сессия получает новый id. История в таблице не остаётся.

| Поле | Смысл |
|---|---|
| `id` | IdField, суррогат. Это `sessionId` следующих команд. В JSON G5 не отдаётся |
| `game_id` | reference на `game`, `restrict`, unique |
| `status` | строка `playing` |
| `state_version` | IntField, минимум 1, как `membership_revision`. В insert пишется 1 |

Бой, process, offer, initiative, effects, маркеры и список id в эту карту не входят.

**`game_session_character`.** Снимок входа.

| Поле | Смысл |
|---|---|
| `id` | IdField |
| `session_id` | reference на `game_session`, `restrict` |
| `character_id` | reference на `character`, `restrict` |

Unique пары `(session_id, character_id)`. Колонки NPC нет.

Stop удаляет состав этой сессии, затем строку сессии. Статус кампании, snapshot, actual, бонусы и потолки не пишет.

## Фасад

Новый сценарий в `Service/` Game, не методы `IGames` и не методы `IGameMemberships`. Счётчик публичных методов в `ClassQualitySniff` включает `__construct`. У `GameHttp` их уже девять, у `Games` тоже девять. Два action на `GameHttp` дают одиннадцать и не проходят quality. Start и stop — отдельный HTTP-класс со своими action, по образцу `GameCharacterHttp`: порты и `routes` в `Game/module.config.php`, `csrf` true.

`Games` получает шестую зависимость конструктора — чтение «строка сессии есть». Это потолок зависимостей. Зависимость — читатель таблицы, не сценарий старта: сценарий старта сам зовёт `IGames` и `canStartSession`, обратная связь зациклила бы сборку.

Старт. Актор с `game.edit` этой игры или с `game.edit_all`, та же сборка ключа, что `GameCharacterHttp::canModerate`, только константа `EDIT`. Игра не `completed`. Строки сессии ещё нет. Список строк — `IGameMemberships::getListByGame($gameId, null)`: второй аргумент `null` берёт все строки, не фильтр владельца актора. Для каждой строки actual через `ICharacters::get`. Нет actual — `GAME_NOT_FOUND`, как `GameCharacterHttp::withReview`, сессия не создаётся. `canStartSession` ложен — строка пропускается. Исключение diff из `canStartSession` наружу, сессия не создаётся. Запись сессии и состава — один `ISmartTableGateway::transaction`. Иначе обрыв после вставки сессии оставляет строку, и повторный старт становится `GAME_CONFLICT`. Unique по `game_id` внутри этого же сценария — `GAME_CONFLICT`, не `GAME_INVALID`, куда `GameRepository::add` кладёт любой unique. Статус кампании не меняется. Leave, approve и return состав не переписывают: это снимок старта.

Stop. Тот же актор. Строка сессии есть. В той же транзакции удаляет состав и сессию. `targetStatus` не принимает, `in_process` и `completed` не ставит, лист не коммитит.

`GameHttp::update` сам сессию не запускает и не останавливает. Замок ревизии и отказ `completed` при живой сессии — в `Games::update`. `GameHttp` для `get`, `getList` и `update` читает наличие строки пятой зависимостью и передаёт её в assembler.

## HTTP

`game.startSession` и `game.stopSession`. Тело — `gameId`. Успех — карточка того же состава полей, что `game.get`: после старта `sessionRunning` истинен, после stop ложен, `status`, `spaceId`, `spaceCode`, `rulesRevision` прежние. Собирает её `IGames::get` и assembler, без `GameHttp::assertVisible`. `assertVisible` пускает только владельца и `game.view_all`; `gm` с `game.edit` и `game.edit_all` без `view_all` через `game.get` получают `GAME_NOT_FOUND`, хотя старт им разрешён.

Ответ строки персонажа те же три предиката. У вошедшего `isActiveSessionParticipant` истинен и остаётся истинным, если actual после старта разошёлся со snapshot. У не вошедшего ложен, даже если он `active`.

Дефекты эскиза, в фасад не копировать:

- `game.stopSession` с `targetStatus`;
- второй метод `game.stopStateSession`;
- `participantEntityKeys`, `commandId`, `expectedSessionStateVersion` и статус сессии `active` на объекте, где уже лежат battle и markers.

## Тесты

Mysql сессии. Два `active` с истинным допуском и один с ложным: старт пишет двух, третьего нет, статус кампании прежний, `sessionRunning` истинен. Повторный старт — `GAME_CONFLICT`, состав прежний. Stop удаляет обе таблицы, лист и snapshot те же, `sessionRunning` ложен. Stop без сессии — `GAME_INVALID`. `completed` — старт и stop `GAME_INVALID`. `gm` стартует чужую для владельца игру, где он `gm`. `player` и посторонний — `GAME_NOT_FOUND`. `edit_all` стартует без роли. `targetStatus` во входе — `INVALID_PARAMS`.

Mysql ревизии. При живой сессии другой `rulesRevision` — `GAME_INVALID`, номер прежний. Тот же номер проходит. Перевод в `completed` при живой сессии — `GAME_INVALID`, сессия на месте. `paused` при живой сессии пишется и сессию не удаляет. После stop смена номера снова проходит и не вызывает migrate.

Mysql предиката. Вошедший с последующим `replacePayload` остаётся участником, `canStartSession` ложен, `needsModeration` истинен. Он не входит в сессию, запущенную уже после stop. Не вошедший на первом старте имеет ложный предикат.

Mysql migrate. Участник снимка: `character.migrate` даёт `CHARACTER_INVALID` до среза, actual не меняется. Персонаж той же игры вне снимка мигрирует прежним путём. Порт в `CharacterMigrationTest` отвечает ложью, suite `character` не начинает требовать таблицу сессии в каждом тесте. Отдельная проверка: импорт `Roleplay\Game` внутри Character есть только в `module.config.php` у сборки migrate.

Unit. Пустой допуск всё равно создаёт сессию. `isActiveSessionParticipant` не зовёт `canStartSession`. Порт по одному `characterId` истинен только при строке состава.

Suite `game`. Suite `character`.

## Todo

- [x] **tables** — `game_session` и `game_session_character`, unique одной текущей сессии.
- [x] **lifecycle** — start и stop, статус кампании не меняется, stop не принимает `targetStatus`.
- [x] **roster** — снимок `canStartSession`, предикат читает его.
- [x] **lock** — живая сессия запрещает другой `rulesRevision` и перевод в `completed`.
- [x] **migrate** — порт Character, реализация Game, участник снимка не мигрирует.
- [x] **gates** — phpunit `game` и `character`; cs/quality. Лист после stop тот же.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| карты сессии и состава | `Table/` `Schema/` | две таблицы |
| сценарий сессии | Game `Service/` | start, stop, чтение состава |
| HTTP сессии | Game `Service/` `Action/` | отдельный класс, не методы `GameHttp` |
| `GameViewAssembler` | Game `Service/` | `sessionRunning` из наличия строки |
| `Games::update` | Game `Service/` | замок ревизии и `completed` |
| `GameCharacterReview` | Game `Service/` | предикат по составу |
| порт | Character `Interface/Service/` | вопрос migrate |
| реализация порта | Game `Service/` | чтение состава по `characterId` |
| `CharacterMigration` | Character `Service/` | отказ до записи |

## Acceptance G5

- Suite `game` и `character` зелёные; cs/quality затронутых модулей.
- Одна текущая сессия. Stop не меняет статус кампании, не принимает `targetStatus`, не ставит `in_process` или `completed`, не пишет лист.
- Персонаж с ложным `canStartSession` не блокирует старт остальных и в состав не входит.
- `changes_pending` не выкидывает вошедшего и не пускает его в следующую сессию.
- `isActiveSessionParticipant` истинен только для пары из состава текущей сессии.
- `sessionRunning` истинен ровно при наличии строки сессии. Колонки на `game` нет.
- При живой сессии другой `rulesRevision` не пишется. `spaceId` и `spaceCode` по-прежнему не патчатся. Отдельного `gameRevision` нет.
- Migrate участника снимка отклоняется. `character.migrate` остаётся действием Character. Запрет не встроен в правила листа.
- Актор старта и stop — `game.edit` этой игры или `game.edit_all`. `player` не проходит.
- Бой, C8 и C9 этим заходом не закрыты.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G5; [`game-plan-04.md`](game-plan-04.md); [`game-plan-03.md`](game-plan-03.md); [`game-plan-02.md`](game-plan-02.md); [`game-plan-01.md`](game-plan-01.md); [`game-system.md`](game-system.md); [`character-system.md`](character-system.md).
