# План Game 4 — смена ревизии и допуск

**Статус:** смена ревизии и предикаты в PHP сделаны, 2026-10-04. `BACKEND_OPEN`. Suite `game` и `character` зелёные. Нарезка — [`game-roadmap.md`](game-roadmap.md) G4. Строка персонажа — [`game-plan-03.md`](game-plan-03.md). Участник — [`game-plan-02.md`](game-plan-02.md). Строка игры — [`game-plan-01.md`](game-plan-01.md). Лист и миграция — [`character-system.md`](character-system.md). C7 есть, C8 этим шагом не закрывается — [`character-roadmap.md`](character-roadmap.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: `rulesRevision` игры можно сменить, пока сессии нет, и по строке персонажа посчитать допуск. Game не мигрирует лист. Character не начинает импортировать Game и не получает запрет migrate.

## Сверка G1–G3

Код `Roleplay/Game` совпадает с принятыми планами в том, на чём стоит G4.

- Таблица `game` уже хранит `rulesRevision`. `game.update` её не пишет. `UpdateGameInput` номер уже принимает, `GameHttp::assertSameWorld` отвергает любое отличие от строки вместе с чужим `spaceId`. `GamePatch`, `Games::preparePatch` и `GameRepository::patchValues` ревизии не несут. `spaceCode` в patch нет. Владелец update — `owner_id` или `game.edit_all`. `game.edit` ведущего на запись игры не переведён. `completed` отсекает `Games::update`. Колонки сессии нет. `GameViewAssembler` отдаёт `sessionRunning: false`.
- `character.migrate` — `CharacterMigration::migrate`. Пишет actual через `ICharacters::replaceMigrated`, только после пустых problems. Game не вызывает. Бонусы и потолки в это действие не входят.
- Строка `game_character` и один `GameCharacterDiff::hasChanges`. `reviewState` считает `GameCharacterReview`: маркер return важнее diff. `IGameMemberships::approve` копирует actual и не пишет таблицу `character`. Validate из G3 не вызывается.
- `GameCharacterMemberships` — 10 публичных методов и 4 зависимости конструктора. `GameCharacterHttp` — 9 публичных методов и 6 зависимостей. `GameHttp` — 9 публичных методов и 4 зависимости, `GameWorldGate` уже внутри.
- Character модуль Game не импортирует. Game читает `ICharacters` и не пишет `character`.

## Зафиксировано (модель)

Развилка validation, 2026-10-04: `canStartSession` вызывает лист через новый метод уже существующего `ICharacterSheets`. Вход — `spaceId`, ревизия actual и массив `choices` из `CharacterRecord::getChoices()`. Внутри Character тот же `CharacterChoiceAssembler`, что у save, затем `ICharacterRuleSlices::get` и нынешний `validate`. Истина — пустой `CharacterValidation::getProblems()`. Новый порт Character не заводится. Повторный diff validation не заменяет. Game по-прежнему не импортирует `Service/` Character.

`needsModeration` — нет snapshot или `hasChanges`. Маркер return сюда не входит: при пустом diff и стоящем return модерация по diff не нужна, а допуск всё равно ложен.

`canStartSession` истинен только когда одновременно: статус `active`; diff пустой и snapshot есть; `CharacterRecord::getRulesRevision()` равен `rulesRevision` игры; маркера return нет; метод листа вернул истину. Отдельного repair-флага и статуса `needs_fix` нет. Непройденная validation — уже этот метод. Явный return — уже маркер. Третьей проверки «repair» нет.

`isActiveSessionParticipant(int $gameId, int $characterId)` значит «этот персонаж уже в текущей сессии». В G4 тела сессии нет, метод возвращает `false`. G5 заполняет тот же метод чтением участника сессии и не меняет смысл на «active и не returned» и не на `canStartSession`.

Смена ревизии не трогает actual, snapshot, бонусы и статус строки персонажа. После `character.migrate` тот же diff видит новый actual. Повторный approve — снова одна замена snapshot, второй компаратор не заводится.

## Что даёт этот заход

`game.update` пишет новый `rulesRevision` того же мира, если игра не `completed`. Предикаты на тех ответах строки персонажа, где уже есть `reviewState`. Метод листа на `ICharacterSheets`.

## Что не закрыто

- Старт и stop сессии, таблица сессии, порт «участник в сессии», запрет migrate активного участника — G5. До G5 migrate о сессии не знает.
- C8 целиком: overlay, runtime mutation, process, SSE, outbox.
- Сообщение return и `returnMessageId`.
- Запись видимости секций.
- NPC, чаты, приглашения, бой, летопись, экономика.
- `game.edit` ведущего на `game.update` не переносится.
- Character не импортирует Game. Vue поверх этого PHP.

## Точки кода G1–G3

Старые `game-plan-01.md`, `game-plan-02.md`, `game-plan-03.md` не переписываются. Без этих правок номер ревизии в строку не попадает или предикат не к чему прикрепить.

- `GameHttp::assertSameWorld` — чужой `spaceId` и чужой `spaceCode` по-прежнему `GAME_INVALID`. Другой `rulesRevision` больше не отказ сам по себе.
- `GamePatch`, `Games::preparePatch`, `GameRepository::patchValues` — в запись добавляется уже существующая колонка `rules_revision`. Нормализация та же, что у create: `GameInputNormalizer::rulesRevision`, номер ≥ 1.
- `GameHttp::update` зовёт уже существующий `GameWorldGate::requireCode` только когда номер в теле отличается от колонки. Тот же номер мир заново не проверяет: сейчас update выключенный мир не отвергает, и этот заход это не меняет. Нет ревизии — `GAME_NOT_FOUND`, как create в `GameMysqlTest::testMissingOwnerWorldAndRevision`. Мир выключен — `GAME_INVALID`, как `requireCode`. Строка при отказе прежняя.
- `GameCharacterReview` получает расчёт допуска. Оба места, где уже висит `reviewState` (`GameCharacterMemberships` и `GameCharacterHttp::withReview`), отдают и предикаты. Седьмую зависимость в `GameCharacterHttp` и одиннадцатый публичный метод в `GameCharacterMemberships` не добавлять.
- `ICharacterSheets` и `CharacterSheets` — метод `acceptsStoredChoices`. Третью зависимость `ICharacterRuleSlices` получает `CharacterSheetPortFactory::create`: `module.config.php` только вызывает эту фабрику. Те же два аргумента конструктора сегодня собирают `CharacterSheetsTest` и `CharacterSaveTest`; оба вызова дополняются портом среза. Character Game не импортирует.

`GameViewAssembler`: `sessionRunning` остаётся литералом `false`. Колонку признака не заводить.

`IGames::update` по-прежнему смотрит только на `completed` и patch. Отдельного актора «только ревизия» нет: кто не проходит нынешний `assertEditable`, ревизию не меняет.

## Модуль

Тот же `Roleplay/Game` плюс один метод порта Character. Новых таблиц нет. `GameSchema` и карты `game`, `game_member`, `game_character` не растут. `events` остаётся `[]`. Новых action нет. Suite `game` тот же.

**DAG:** Game → SmartTable + User + RuleSpace + Character. Новое чтение — `ICharacterSheets`. `ICharacterRuleSlices` снаружи Game не появляется: его зовёт метод листа. Character → Game нет.

Ошибки прежние: `GAME_INVALID`, `GAME_NOT_FOUND`, `GAME_CONFLICT`, `AUTH_REQUIRED`, `AUTH_DENIED`, `INVALID_PARAMS`. Листа `needs_fix` нет.

| Источник | Лист |
|---|---|
| нет игры; чужая для update | `GAME_NOT_FOUND` |
| `completed`; чужой `spaceId`; чужой `spaceCode`; номер ревизии &lt; 1; новый номер у выключенного мира | `GAME_INVALID` |
| нового номера в мире нет | `GAME_NOT_FOUND` |
| `sessionRunning` в JSON | `INVALID_PARAMS` |
| срез для предиката не читается (`CharacterNotFoundException`, `CharacterInvalidException`) | строка на месте, `canStartSession` ложен, наружу не `CHARACTER_*` |

`acceptsStoredChoices` сам исключения среза не глотает. Их гасит предикат Game.

## Смена ревизии

Тело `game.update` то же, что в G1: id и поля карточки, среди них `rulesRevision`. Успех пишет номер и не вызывает `character.migrate`, `replacePayload`, `replaceSaved`, `replaceMigrated`, `setActive`. Snapshot строк персонажа не переписывается. Потолки и бонусы в migrate не уходят, потому что migrate не вызывается.

Пока сессии нет, проверка «сессия запущена» — постоянная ложь. Отдельного чтения несуществующей таблицы нет. `completed` по-прежнему только чтение, в том числе когда номер в теле совпадает с колонкой.

`gm` с `game.edit` и без `game.edit_all` update не получает. Владелец колонки `owner_id` — получает, даже без строки `game_member`.

## Предикаты

Класс в `Service/` Game, без своего порта. Вход — строка membership, `GameRecord` и `CharacterRecord`. Наружу три bool. Diff только `GameCharacterDiff`.

| Предикат | Истина |
|---|---|
| `needsModeration` | snapshot `null` или `hasChanges` |
| `canStartSession` | `active`, snapshot есть, diff пустой, ревизия actual равна ревизии игры, return снят, `acceptsStoredChoices` истинен |
| `isActiveSessionParticipant` | в G4 всегда ложь; смысл — участие в текущей сессии |

`left` и `submitted`: `canStartSession` ложен. `changes_pending` ломает допуск и не делает участника сессии. Совпадение ревизии snapshot с игрой само по себе допуск не даёт: смотрит ревизия actual.

`acceptsStoredChoices` собирает выборы тем же `CharacterChoiceAssembler::assemble`, что `CharacterSaveAssembly::build`. `raceCode` туда входит только строкой; списки — только массивами. Иначе метод возвращает `false` и `validate` не вызывает: у `assemble` код расы — `string`, не `null`. `active` отсутствует — в сборщик уходит `null`, как «ключа не было». Срез — `(spaceId actual, ревизия actual)`. Метод не вызывает `CharacterSaveAssembly::build`: problems денег и лимитов, которые сборка дописывает после `validate`, в этот ответ не входят. Потолок игры и бонус строки тоже не входят.

На `GameCharacterRecord` три значения появляются там же, где `reviewState`: после чтения actual. Колонок под них нет.

## HTTP

Новых action нет. `game.update` начинает сохранять присланный номер. Карточка игры та же: `sessionRunning` ложен, `spaceId` и `spaceCode` прежние.

Ответ строки персонажа, где уже есть `reviewState`, дополняется `needsModeration`, `canStartSession`, `isActiveSessionParticipant`. Список по-прежнему собирается через те же `get`, что и в G3. Reject по-прежнему `null`.

Дефекты эскиза, в фасад не копировать:

- `toCreateGameData` кладёт `tags` и `forbiddenTags`; лишние поля ядро отвергает;
- `GameMembershipEligibilityService` требует совпадения `spaceCode` у approved и actual, флаг `needsFix` и diff из модуля Character; в PHP допуск — предикаты этой страницы и один diff G3;
- `isActiveSessionParticipant` эскиза истинен от `sessionParticipant` и отсутствия return; метод G4 так не определяется;
- `game.submitCharacterMigration` — миграцию делает `character.migrate`.

## Тесты

Вторая ревизия того же мира в фикстуре Game — повторный `IRuleSpaces::commit` с изменённым `put`: голый `keep` того же состава RuleSpace отвергает как `Composition is unchanged`. `addWorldWithRevision` пишет только ревизию 1.

Mysql игры: смена номера на эту вторую ревизию пишет колонку и не меняет строку `character` и snapshot; тот же номер при выключенном мире по-прежнему проходит; новый номер при выключенном мире — `INVALID`; чужой `spaceId` и чужой `spaceCode` — `INVALID`, номер прежний; номера в мире нет — `NOT_FOUND`; `gm` без `edit_all` — `NOT_FOUND`; `edit_all` пишет номер в чужой игре; `completed` — `INVALID`; `sessionRunning` в теле — `INVALID_PARAMS`; `sessionRunning` в ответе ложен. Прежний `testUpdateOwnerForeignAndWorld` по-прежнему ждёт отказ чужого мира.

Mysql персонажа не вызывает `character.migrate`. Фикстурный лист — `choices: {race: human}`, это не документ migrate (`MigrateCharacterInput` и ключи `raceCode`, `abilities`, `inventory`). Сдвиг actual, который успешный migrate пишет через `replaceMigrated`, тест делает этим методом, как G3 двигает лист через `replacePayload`. После смены ревизии игры actual и snapshot прежние, `needsModeration` ложен, `canStartSession` ложен из-за ревизии. После `replaceMigrated` на новый номер diff непустой, `needsModeration` истинен, snapshot сам не обновляется. Повторный approve — новый snapshot, `reviewState = clean`. `canStartSession` на фикстурном `{race: human}` ложен: это не выборы `assemble`. Истина допуска проверяется unit-ом, где лист отвечает истиной. Return при пустом diff — `needsModeration` ложен, `canStartSession` ложен. `isActiveSessionParticipant` ложен на active и на changes_pending. Колонки бонуса и потолка игры после `replaceMigrated` те же: Game этот метод не вызывает, а сам `replaceMigrated` их не принимает.

Unit листа: `acceptsStoredChoices` истинен на выборах без problems и ложен на отказе валидатора. Срез подменяет порт `ICharacterRuleSlices`.

Unit Game: три предиката на сочетаниях snapshot, diff, return, ревизии и ответа листа. `isActiveSessionParticipant` ложен без чтения таблицы сессии.

Suite `game`. Suite `character` — потому что вырос `ICharacterSheets`; create, update и migrate не меняют смысл. Отдельная проверка: в `modules/Roleplay/Character` нет импорта `Roleplay\Game`.

## Todo

- [x] **revision** — `game.update` пишет `rules_revision` того же мира, не трогая actual.
- [x] **sheet** — `ICharacterSheets::acceptsStoredChoices`.
- [x] **predicates** — три предиката на ответе строки, один diff G3.
- [x] **gates** — phpunit `game` и `character`; cs/quality. Character не импортирует Game. Game не пишет `character` и не вызывает migrate.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| `GamePatch` | `Dto/` | номер ревизии в patch |
| `GameHttp` / `Games` / `GameRepository` | `Service/` `Repository/` | запись номера |
| `acceptsStoredChoices` | Character `Interface/Service/` и `Service/` | validation сохранённых choices |
| допуск | Game `Service/` | три предиката |
| `GameCharacterRecord` / assembler | `Dto/` `Service/` | три bool в JSON |

## Acceptance G4

- Suite `game` и `character` зелёные; cs/quality затронутых модулей.
- Смена `rulesRevision` не вызывает migrate и не пишет `character`. Чужой мир и `spaceCode` не меняются. Сессии и колонки признака нет, `sessionRunning` ложен.
- Актор смены — тот же, что у `game.update`: `owner_id` или `game.edit_all`.
- `completed` остаётся только чтением.
- Несовместимый персонаж не прячет игру. Его `canStartSession` ложен, пока actual не на ревизии игры и строка снова не approved новым snapshot.
- `needsModeration` — нет snapshot или semantic diff. Return сам по себе его не включает.
- `isActiveSessionParticipant` ложен. Сигнатура и смысл зафиксированы для G5.
- Бонусы GM и потолки игры в `character.migrate` не уходят.
- C8 не закрыт. Статус линии: `BACKEND_OPEN`.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G4; [`game-plan-03.md`](game-plan-03.md); [`game-plan-02.md`](game-plan-02.md); [`game-plan-01.md`](game-plan-01.md); [`character-system.md`](character-system.md); [`character-roadmap.md`](character-roadmap.md).

## Следующий заход

G5 — одна текущая сессия без боя. `isActiveSessionParticipant` получает тело «уже в этой сессии» и не меняет смысл. Порт «участник в сессии» для запрета migrate — там же.
