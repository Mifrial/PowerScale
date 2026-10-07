# План Game 12 — бой без тактики

**Статус:** бой без тактики в PHP сделан, 2026-10-04. `BACKEND_OPEN`. Suite `game` по `GameBattleMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G12. Канон — [`game-system.md`](game-system.md): несколько независимых боёв в одной текущей сессии; `endBattle` сессию не завершает. Сессия — [`game-plan-05.md`](game-plan-05.md): одна текущая сессия; stop статус не меняет и `targetStatus` не принимает; NPC в состав сессии не входит. NPC — [`game-plan-06.md`](game-plan-06.md): строка уже есть; участие в бою не побочный эффект создания. Порт листа — [`game-plan-10.md`](game-plan-10.md): этот шаг лист через него не пишет. Индекс — [`TR.md`](TR.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. В одной текущей сессии появляется несколько независимых боёв. У боя свой `battleId`, свой состав и своя версия CAS. `endBattle` закрывает только этот бой и сессию не останавливает. Новый бой не наследует transient state предыдущего. Продолжение того же боя сохраняет его `battleId`. Повтор той же команды второй бой не создаёт и уже закрытый бой второй раз не закрывает.

Зависимость шага — G5, G6 и G10. Порт G10 в этом заходе не вызывается: лист не меняется. Экономика G11 этим планом не переписывается. Расчёт удара, урон, DOT, каст, движение и battleground не входят. Применение эффекта начинается в G13. Проекции (G14) и доставка (G15) не проектируются.

## Сверка G5, G6, G10, G11

- Текущая сессия — одна строка `game_session` на игру. Статус строки — `playing`. Техническая `state_version` со старта равна 1. Старт и stop её не сверяют и в JSON не отдают. Бой в эту таблицу не входит.
- Состав сессии — строки `game_session_character`. Это снимок старта, не пересчёт diff. Колонки NPC нет. `game.createNpc` туда не пишет.
- `game.stopSession` удаляет состав сессии и строку сессии. Статус кампании, snapshot и actual не пишет. `targetStatus` во входе — `INVALID_PARAMS`.
- `npc.version` и `npc.actual_version` пишет Game. Участие NPC в бою G6 не открывает.
- `ICharacterActualMutations` меняет только строку `character`. Этот шаг его не вызывает. `GameNpcRepository::save` и `replaceVersion` не вызываются: `npc.actual_version` после старта и конца боя тот же.
- Character модуль Game не импортирует. Нового ребра DAG нет.
- `GamePortFactory` уже имеет десять публичных `create*`. Сборщик боя в него не встаёт.
- `GameSessions` собирается только в приватном `GamePortFactory::sessions` и имеет пять зависимостей. Шестая — потолок стандарта: седьмая запрещена. Шлюза в конструкторе нет.
- `GameSessionRepository::deleteSession` сам открывает `ISmartTableGateway::transaction`. Если транзакция соединения уже открыта, `SmartTableGateway::transaction` выполняет работу внутри неё и второй begin не делает.
- `IGameSessionRoster::isParticipant` уже отвечает, есть ли персонаж в снимке этой игры. `GameSessionRepository::findSessionId` отдаёт id строки сессии. `GameNpcRepository::getById` отдаёт строку, `GameNpcRecord::getGameId` — игру. Новых методов у этих классов шаг не заводит.
- `ReferenceField` по умолчанию `onDelete restrict`. Так ссылается `game_session_character.session_id`. Удаление сессии при живых дочерних строках ссылку не снимает само.
- CAS NPC в `GameNpcRepository::replaceVersion` — чтение счётчика и отказ до `update`, не отдельный SQL-предикат. `GameConflictException` несёт `currentActualVersion` и `currentMembershipRevision`. Для чужого счётчика экономика завела `GameEconomyConflictException` с одной деталью `currentVersion`.
- `GameSchema` уже принимает шесть `IOpenedSchema`. Седьмой аргумент стандартом не проходит. Карты G8–G11 ставятся отдельным `install()` в `GameMysqlFixture::installGameSchemas` и сливаются в `GameModuleSetup::getTableClasses`.
- `idempotency_key` экономики — `StringField` длины 255. Роль и статус игры — `StringField` длины 32.
- Ограничение мока `draft-front_1.2ds` «не больше одного active battle» в PHP не переносится. У сессии нет колонки `activeBattleId`.

## Зафиксировано (модель и права)

**Идентичность.** Бой — строка текущей сессии, не строка игры и не строка сессии. У одной сессии несколько строк боя. У каждой свой целочисленный `battleId`. Новый старт пишет новую строку и новый id. Уже открытый бой этот старт не закрывает и его id не подменяет. Продолжение — тот же id, пока `endBattle` строку не снял. После конца тот же id не возобновляется: следующий бой — новая строка.

Transient state предыдущего боя не хранится: нет колонок и JSON инициативы, process, offer, pending effects, markers, скорости и заклинаний. Новый бой не из чего их наследовать. Общий журнал ударов не заводится.

**Состав боя.** Отдельные строки, не колонка `game_session_character` и не побочный эффект `game.createNpc`. Участник — пара `(battleId, type, id)`. `type` — `character` или `npc`. Персонаж обязан быть в снимке этой сессии. NPC обязан быть строкой `game_npc` этой игры. Чужой персонаж и чужой NPC в состав не входят. Повтор той же пары в одном теле — `GAME_INVALID`. Пустой состав допустим. Состав сессии старт боя не переписывает: leave, approve и return по-прежнему снимок сессии не трогают.

Смена состава открытого боя сохраняет `battleId` и увеличивает версию боя на 1. Это не новый бой.

**CAS.** Версия — целое на строке боя, со старта 1. Её сверяют конец боя и смена состава тем же ходом, что `GameNpcRepository::replaceVersion`: прочитали счётчик, не совпал — исключение до `update`. Чужая ожидаемая версия не пишет строки и не увеличивает счётчик. Версия сессии `game_session.state_version` боем не сверяется и не увеличивается: иначе второй независимый бой делал бы первый устаревшим. `actual_version` персонажа и `npc.actual_version` бой не читает как ожидаемые и не пишет.

**Идемпотентность.** Ключ уникален в паре с `gameId`. Первый успешный commit пишет строку команды с разобранным телом и итогом (`battleId`, версия, признак конца). Повтор с тем же ключом сравнивает тело так же, как `GameEconomy::replay`: `sortKeys` и `json_encode`, не сырую JSON-строку запроса. Совпало — возвращает сохранённый итог, новые строки боя не создаёт и закрытый бой повторно не снимает. Тот же ключ с другим телом — `GAME_CONFLICT`, без второй записи. Пустой ключ — `GAME_INVALID`.

**Ход записи.** Порядок до транзакции — как `GameEconomy::apply`: сначала видимость, затем повтор ключа, затем `completed`. Право писать проверяется только на новом проходе, не на повторе.

1. `Games::get` и `GameCardAccess::isVisible`. Карточка скрыта — `GAME_NOT_FOUND`, даже если ключ уже есть.
2. Нет строки команды с этим ключом. Иначе сверка тела, как `GameEconomy::replay`, и возврат сохранённого итога, без записи и без `AUTH_DENIED`.
3. Игра не `completed`. Иначе `GAME_INVALID`.
4. Нет `game.edit` этой игры и нет `game.edit_all` — `AUTH_DENIED`. Записи нет. Проверка — как `GameEconomy::assertWriter`: владелец, `gm` или переданный `editAll`.
5. Есть текущая сессия этой игры. Id берётся из `GameSessionRepository::findSessionId`. Нет — `GAME_INVALID`.
6. Старт: персонаж проходит `IGameSessionRoster::isParticipant`. NPC — `GameNpcRepository::getById`, и `getGameId` равен этой игре. Конец и смена состава: строка этого `battleId` принадлежит этой сессии. Нет строки, в том числе после уже выполненного конца другим ключом — `GAME_NOT_FOUND`. Версия не совпала — `GAME_CONFLICT`, ничего не пишется.
7. Одна транзакция `ISmartTableGateway`. Старт вставляет бой и состав, версия 1. Смена состава заменяет строки состава и пишет `version + 1`. Конец удаляет состав этого боя, затем строку боя. Строка команды остаётся: повтор того же ключа читает её. Сессия и `game_session_character` остаются. Commit вместе со строкой команды. Исключение откатывает транзакцию.

Stop сессии в той же транзакции, что снимает бои, удаляет строки `game_battle_command` с `session_id` этой сессии. После stop тот же ключ в новой сессии — новая команда, не повтор старого итога. `endBattle` строки команды не удаляет.

**Чего шаг не делает.** Не вызывает порт G10, `replacePayload`, `GameNpcRepository::save`, `replaceVersion` и `game.createNpc`. Не меняет `choices`, `sheet`, `actual_version`, `npc.version`. Не считает удар, урон, защиту, DOT и каст. Не пишет инициативу, process, offer и сцену. Не принимает готовый урон и полный лист. Не останавливает сессию и не меняет статус кампании. Не шлёт событие и не кладёт outbox. Экономические таблицы не читает.

**Права.** Старт, смена состава и конец — владелец игры, `gm` или `game.edit_all`. Коды отказа — как у `GameEconomy`, не как у `GameSessionHttp::assertEditor`. Карточка скрыта — `GAME_NOT_FOUND`. Карточка видна, а `game.edit` этой игры и `game.edit_all` нет — `AUTH_DENIED`. `player` бой не открывает и не закрывает.

## Что даёт этот заход

Фасад Game `IGameBattles` и действия `game.startBattle`, `game.setBattleRoster`, `game.endBattle`. Успешный старт возвращает новый `battleId` и версию 1. Конец возвращает закрытый `battleId` и не снимает сессию. `sessionRunning` остаётся истинным, пока жива строка сессии.

## Что не закрыто

- Расчёт удара, урон, защита, DOT, каст, движение, инициатива, process, offer. Это G13 и отдельные контракты, не колонки этого шага.
- Применение эффекта к actual. G13 идёт через порт G10, без второго листа в overlay.
- `1 → N` и `N → 1`. Сцена и `ISpatialResolver`.
- Проекции чтения (G14) и доставка (G15). SSE, outbox, `CharacterChanged`.
- Чтение списка боёв отдельным action. Ответ мутации содержит только затронутый бой.
- Vue поверх этого PHP.
- Переписывание экономики G11.

## Точки кода G1–G11

Старые `game-plan-01.md`–`game-plan-11.md` не переписываются. Character не начинает импортировать Game. Порт мутации, `CharacterActualMutations`, `GameNpcRepository` и фасад экономики не меняются: без них идентичность, состав и CAS боя пишутся.

Без этих точек строка боя не снимается вместе с сессией, а suite `game` не ставит новые таблицы:

- `GameSessions::stop` и приватный `GamePortFactory::sessions`. Конструктор `GameSessions` получает шестую зависимость: снятие боёв сессии. Шлюз седьмой зависимостью не добавляется. Эта зависимость открывает транзакцию, удаляет состав боёв, строки боёв и строки `game_battle_command` этой сессии, затем вызывает уже существующий `deleteSession`. Тот видит открытую транзакцию и второй begin не делает. `deleteSession` про бой не знает и не переписывается. Иначе `onDelete restrict` не даёт снять сессию при живом бое, а два отдельных commit оставляют бой без сессии или сессию без отката боя. `start` сессии не меняется и бой не создаёт. `state_version` сессии не увеличивается. `targetStatus` по-прежнему не принимается. Статус кампании stop не пишет. Публичных методов `GamePortFactory` не прибавляется.
- `GameMysqlFixture`. `installGameSchemas` вызывает отдельный `(new GameBattleSchema(...))->install()`, как `GameEconomySchema`. В конструктор `GameSchema` бой не добавляется: там уже шесть схем. `dropGameTables` удаляет состав боя, бой и `game_battle_command` до `GameSessionCharacterTable` и `GameSessionTable`: команда ссылается на сессию. Иначе снос упирается в restrict.

Остальной код G1–G11 не меняется.

## Модуль

Сценарий живёт в `Roleplay/Game`, `Interface/Service/IGameBattles`. Реализация в `Service/` Game. Сборщик — новый `GameBattlePortFactory` с `create` и `createHttp`. Публичных `create*` у `GamePortFactory` не прибавляется. Меняется только приватный `sessions`: он передаёт шестую зависимость `GameSessions`. Порт Character сценарий не вызывает. `events` остаётся `[]`. `module.config.php` Game регистрирует порт `IGameBattles` в `ports` и три action через этот сборщик. `GameContainer` список портов не хранит.

**DAG:** как после G11. Character Game не импортирует.

Ошибки Game: `GAME_INVALID`, `GAME_NOT_FOUND`, `GAME_CONFLICT`, `AUTH_REQUIRED`, `AUTH_DENIED`, `INVALID_PARAMS`. Класс конфликта — свой, код `GAME_CONFLICT`, деталь `currentVersion` строки боя. У конфликта ключа этой детали нет. `CharacterConflictException` этот шаг не бросает.

| Источник | Код |
|---|---|
| нет актора | `AUTH_REQUIRED` |
| карточка скрыта; `battleId` не этой сессии; NPC не этой игры; персонаж не в снимке сессии | `GAME_NOT_FOUND` |
| нет `game.edit` и нет `game.edit_all`, карточка видна | `AUTH_DENIED` |
| пустой ключ; игра `completed`; сессии нет; `type` вне двух; повтор пары в теле | `GAME_INVALID` |
| ожидаемая версия боя не совпала; тот же ключ с другим телом | `GAME_CONFLICT`, записи нет |
| тело не того типа; лишний ключ верхнего уровня; `targetStatus`; готовый урон; полный лист | `INVALID_PARAMS` |

## Таблицы

Новая карта `GameBattleSchema` со своими table-классами. `GameModuleSetup::getTableClasses` сливает её с уже стоящими картами Game. Колонки `game_session`, `game_session_character`, `character` и `game_npc` не добавляются.

`game_battle`: `session_id` — `ReferenceField` на `GameSessionTable`, `onDelete` по умолчанию restrict; `state_version` — `IntField`. Unique по `session_id` нет: несколько открытых боёв одной сессии. `state_version` со старта 1. Строка конца в таблице не остаётся: `endBattle` её удаляет. История боя не хранится.

`game_battle_participant`: `battle_id` — restrict на `game_battle`; `kind` — `StringField` длины 32, значения `character` и `npc`; `subject_id` — целое без ссылки на `character` и `game_npc`. Имена короче `participant_type` / `participant_id`: `UniqueKeyMap` собирает индекс `{таблица}_{поля}_unq` не длиннее 64, и тройка с длинными именами не проходит. Пара `(battle_id, kind, subject_id)` уникальна через `defineUniqueKeys`. Чужой id отсекает шаг 5 хода, не внешний ключ.

`game_battle_command`: `game_id`, `session_id` — restrict на `GameSessionTable`, `idempotency_key` — `StringField` длины 255, тело запроса и итог — два `JsonField`. Пара (`game_id`, `idempotency_key`) уникальна через `defineUniqueKeys`. `session_id` нужен, чтобы stop удалил команды этой сессии и не тронул чужую игру. Вставка в конце той же транзакции. Нарушение unique откатывает её и наружу даёт `GAME_CONFLICT`. Stop удаляет эти строки до строки сессии: ссылка restrict иначе не даст снять сессию.

## Фасад

`IGameBattles`. Не метод `IGames`, не метод сессии и не метод `ICharacters`.

Три публичных метода сверх конструктора. Флаги ключей — как у `IGameEconomy::apply`: HTTP читает их у актора и передаёт в фасад. Фасад `IUserAccess` не принимает.

- `start(int $gameId, int $actorUserId, bool $editAll, bool $viewAll, string $idempotencyKey, array $participants): array`
- `setRoster(int $gameId, int $actorUserId, bool $editAll, bool $viewAll, int $battleId, string $idempotencyKey, array $participants, int $expectedVersion): array`
- `end(int $gameId, int $actorUserId, bool $editAll, bool $viewAll, int $battleId, string $idempotencyKey, int $expectedVersion): array`

Репозитории, которым нужен только шлюз, фасад создаёт сам, как `GameEconomy` создаёт `GameNpcRepository`. В конструкторе не больше шести зависимостей: шлюз, `IGames`, `GameCardAccess`, `GameMemberRepository`, `GameSessionRepository`, `IGameSessionRoster`. Седьмая запрещена стандартом. `GameEconomy` уже держит семь; бой этот предел не повторяет.

`participants` — список `{ type, id }`. `type` — `character` или `npc`.

Возврат старта и смены состава — `{ battleId, version, ended: false }`. Возврат конца — `{ battleId, ended: true }`. Лист, `sessionId` кампании как статус и `sessionRunning` в это тело не кладутся: признак сессии по-прежнему читается карточкой игры. Повтор ключа отдаёт тот же объект.

Класс не принимает полный лист, готовый урон, `commandId`, `expectedSessionStateVersion`, `activeBattleId` и `participantEntityKeys` эскиза. Имя ключа — `idempotencyKey`.

## HTTP

Свои DTO, `csrf` true. Action тонкий: в `handle` только вызов сценария. Сценарий сам проверяет актора.

| Action | Кто | Тело | Успех |
|---|---|---|---|
| `game.startBattle` | владелец, `gm` или `game.edit_all` | `{ gameId, idempotencyKey, participants }` | `{ battleId, version, ended: false }` |
| `game.setBattleRoster` | тот же актор | `{ gameId, battleId, idempotencyKey, participants, expectedVersion }` | `{ battleId, version, ended: false }` |
| `game.endBattle` | тот же актор | `{ gameId, battleId, idempotencyKey, expectedVersion }` | `{ battleId, ended: true }` |

Верхний лишний ключ — `INVALID_PARAMS`. Чужой `type` и повтор пары — `GAME_INVALID`.

Дефекты эскиза `draft-front_1.2ds`, в фасад не копировать:

- Мок держит один `store.battle` и `session.activeBattleId`. Второй `startBattle` при живом бое даёт `already_active`. В PHP у одной сессии несколько открытых боёв, колонки единственного боя нет.
- `startBattle` и `endBattle` эскиза требуют `expectedSessionStateVersion` и увеличивают её. Это связывает независимые бои. PHP версию сессии боем не сверяет.
- Ключ эскиза — `commandId`. В PHP — `idempotencyKey`.
- `GameCombatEffect` с `kind: damage` и уже посчитанным уроном — набросок G13. G12 это поле не принимает.
- `GameCombatOverlay` эскиза исторически тащил полный лист. В PHP overlay нет, лист в строку боя не пишется.
- `participantEntityKeys` на старте сессии в G5 уже отвергнут. Старт боя их не вводит.

## Тесты

Suite `game`. Suite `character` этот шаг не расширяет: порт не меняется.

Mysql идентичности. При живой сессии два `game.startBattle` дают два разных `battleId`, оба открыты, `sessionRunning` истинен, статус кампании прежний. `game.endBattle` одного снимает только его строки. Второй `battleId` на месте, строка `game_session` на месте. Повторный старт без общего ключа создаёт третий id и не копирует состав первого.

Mysql CAS. `game.setBattleRoster` и `game.endBattle` с чужой `expectedVersion` оставляют состав, версию и сессию прежними. Верный вызов увеличивает версию на 1 либо удаляет только этот бой. `game_session.state_version` после старта, смены состава и конца равна 1.

Mysql состава. Персонаж из снимка сессии входит. Персонаж той же игры вне снимка — `GAME_NOT_FOUND`, бой не создаётся. NPC этой игры входит и в `game_session_character` не появляется. Неизвестный `npcId` — `GAME_NOT_FOUND`. Повтор пары — `GAME_INVALID`. `game.createNpc` число строк боя не увеличивает.

Mysql конца и сессии. После `endBattle` `game.stopSession` по-прежнему снимает сессию и статус не меняет. `targetStatus` — `INVALID_PARAMS`. Stop при ещё открытых боях удаляет эти бои, их команды и сессию в одной транзакции; actual и `npc.version` те же. Повтор ключа старта после новой сессии создаёт новый `battleId`, а не возвращает снятый. Stop без сессии — `GAME_INVALID`. `player` при видимой карточке — `AUTH_DENIED`. Карточка скрыта — `GAME_NOT_FOUND`.

Mysql листа. До и после старта и конца `actual_version` персонажа и `npc.actual_version` совпадают, JSON листа тот же. Вызова порта мутации нет.

Mysql идемпотентности. Повтор старта с тем же ключом и тем же телом возвращает прежний `battleId`; число строк боя не растёт. Тот же ключ с другим телом — `GAME_CONFLICT`. Повтор конца с тем же ключом возвращает `ended: true` и не требует живой строки.

Mysql границ. Нет сессии — старт `GAME_INVALID`. Игра `completed` — `GAME_INVALID`. Повтор чужого ключа игроком, которому карточка видна, возвращает сохранённый итог и не даёт `AUTH_DENIED`. Скрытая карточка с тем же ключом — `GAME_NOT_FOUND`. Второго action широкого удара и сцены нет.

## Todo

- [x] **schema** — `game_battle`, `game_battle_participant`, `game_battle_command`. Unique одной сессии на боях нет.
- [x] **stop** — шестая зависимость `GameSessions` открывает транзакцию, снимает бои и команды этой сессии, зовёт прежний `deleteSession`. Версию сессии не трогает. Шлюз в `GameSessions` не добавляется.
- [x] **facade** — `IGameBattles`: старт, смена состава, конец. CAS версии боя. Ключ идемпотентности.
- [x] **http** — три action. Ответ без листа и без `activeBattleId`.
- [x] **gates** — phpunit. `endBattle` не гасит сессию. Второй бой живёт рядом с первым. Actual и `npc.version` после старта и конца те же. Сцена и широкий удар не появляются.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| бой | Game `Interface/Service/` `Service/` | идентичность, состав, CAS, транзакция |
| сборка | Game `Service/GameBattlePortFactory` | `create` и `createHttp`; у `GamePortFactory` меняется только приватный `sessions` |
| снятие сессии | Game `Service/GameSessions` | удалить бои до строки сессии |
| таблицы | Game `Schema/` `Repository/` | бой, состав, ключ |
| HTTP | Game `Action/` | три действия |

## Acceptance G12

- В одной текущей сессии несколько открытых боёв, у каждого свой `battleId`.
- Новый бой не получает transient state предыдущего: таких колонок нет. Продолжение того же боя сохраняет `battleId`.
- `endBattle` удаляет только этот бой. Сессия, статус кампании и остальные бои остаются.
- Состав — явные пары character/npc. NPC в `game_session_character` не попадает. Создание NPC бой не открывает.
- Чужая версия боя не пишет строки. Версия сессии боем не меняется.
- Повтор того же ключа второй бой не создаёт и эффект конца не повторяет записью. Stop удаляет команды этой сессии; тот же ключ в следующей сессии пишет новый бой.
- `actual_version` и `npc.actual_version` после старта и конца те же. Порт G10 не вызывается. Второго листа в overlay нет.
- Character не импортирует Game.
- Удар, урон, сцена, проекции и доставка этим заходом не спроектированы.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G12; [`game-system.md`](game-system.md); [`game-plan-05.md`](game-plan-05.md); [`game-plan-06.md`](game-plan-06.md); [`game-plan-10.md`](game-plan-10.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).
