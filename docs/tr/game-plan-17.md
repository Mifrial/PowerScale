# План Game 17 — process

**Статус:** process в PHP сделан, 2026-10-04. `BACKEND_OPEN`. Suite `game` по `GameProcessMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G17. Канон — [`game-system.md`](game-system.md): overlay хранит process state, не второй лист; `endBattle` отменяет незакрытые process боя и сессию не останавливает; `stopSession` отменяет оставшиеся process сессии; уже применённый эффект не откатывается. Сессия — [`game-plan-05.md`](game-plan-05.md): одна текущая сессия; stop не меняет статус и не принимает `targetStatus`. Бой — [`game-plan-12.md`](game-plan-12.md): несколько боёв в одной сессии; `endBattle` гасит только этот бой. Return — [`game-plan-03.md`](game-plan-03.md): return оставляет membership. Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. В Game появляется строка process. Она привязана к текущей сессии или к одному `battleId`, помнит участника и статус `open | resolved | cancelled`. Это не второй лист и не история `CharacterVersion`. Return отменяет незакрытые process этой строки персонажа. `endBattle` отменяет незакрытые process этого боя и сессию не останавливает. `stopSession` отменяет оставшиеся process сессии. Уже применённый эффект эти три перехода не откатывают. Лист этот шаг не пишет.

Зависимость — G5, G12 и return из G3. Удар G13 и чаты G16 этот шаг не пересчитывает. Проверка, pairwise-предложение, инициатива, широкий удар, DOT, каст и сцена не входят. G18 и G19 не начинаются.

## Физическая схема

Схему id до этого плана не фиксировали. Этот план выбирает её явно.

**Строка живёт в таблице Game `game_process`.** Колонки листа нет: нет копии actual, нет `states`, ресурсов, денег, инвентаря и экипировки, нет payload проверки и нет `CharacterVersion`. Колонки `character` этим шагом не меняются.

**Привязка.** Обязательное целое `session_id` — id текущей сессии на момент записи. Необязательное целое `battle_id`: пустое — process сессии, число — process одного боя этой сессии. Это не `ReferenceField` на `game_session` и не на `game_battle`. Иначе restrict не дал бы снять бой и сессию, как их уже снимают G12 и G5, а отменённая строка должна остаться. `onDelete` каскада нет.

**Участник.** Пара `participant_type` и `participant_id`. Тип — `character` или `npc`, те же два значения, что состав боя. Персонаж — id персонажа строки membership этой игры. NPC — id строки `game_npc` этой игры. Отдельной ссылки на `game_character.id` нет: return ищет process по `characterId` этой игры.

**Статус.** Строка `open`, `resolved` или `cancelled`. Новая строка пишется как `open`. `resolved` ставит только переход resolve этого шага. `cancelled` ставят return, `endBattle` и `stopSession` и только из `open`. Обратного перехода нет.

**Чего в строке нет.** Итога броска, трудности, предложения, инициативы, цели удара, эффекта, версии листа и текста причины. Техническая версия process не вводится: CAS этого шага — уже существующие `membershipRevision` return и `expectedVersion` боя.

## Сверка G3, G5 и G12

- `returnToOwner` уже оставляет строку membership, пишет причину и `return_message_id`, поднимает revision. Снимок сессии и actual не меняет. Сюда добавляется отмена `open` этого `characterId`. `reject` по-прежнему удаляет только `submitted`. `leave` по-прежнему ставит `left`. Оба process не отменяют.
- `GameSessions::stop` по-прежнему не принимает `targetStatus`, не ставит `in_process` или `completed` и лист не пишет. Снятие сессии идёт через `GameBattleCleanup::deleteWithSession`. Сюда добавляется отмена оставшихся `open` этой сессии до удаления боёв и сессии.
- `GameBattleMutator::end` удаляет одну строку боя и пишет команду. Сессию не останавливает. Сюда добавляется отмена `open` этого `battleId` в той же транзакции, до `delete`. Process других боёв и process с пустым `battle_id` не трогает.
- Character модуль Game не импортирует. Порт мутации actual не вызывается.

## Зафиксировано (модель и права)

**Открытие.** Порт пишет `open` только при живой текущей сессии и игре не `completed`. Process сессии: `battle_id` пустой, персонаж есть в снимке этой сессии, NPC — строка этой игры. Process боя: `battleId` — открытый бой этой сессии, участник входит в состав этого боя. Чужой бой, чужая сессия и участник вне границы — отказ, строка не пишется. Это не проверка и не удар: тело эффекта нет.

**Resolve.** Переводит одну строку `open` в `resolved`. Лист, `npc.version`, деньги и инвентарь не читает и не пишет. Повтор на уже `resolved` или `cancelled` — отказ, статус прежний. Этот переход нужен, чтобы отличить уже применённый эффект от незакрытого process. Сам эффект считает G18, не этот шаг.

**Return.** В той же транзакции, что причина и revision. `GameSessionRepository::findSessionId($gameId)` даёт текущую сессию. Если её нет, отменять нечего: предыдущий stop уже перевёл `open` в `cancelled`. Если есть, `open` с этим `session_id`, `participant_type = character` и `participant_id` этого персонажа становятся `cancelled`. И сессионные, и боевые: у обоих записан `session_id`. Отбор по одному `characterId` без сессии не делается. `leave` process не гасит, а после `left` тот же персонаж может войти в другую игру; чужая сессия остаётся. `resolved` и уже `cancelled` не меняются. Membership остаётся. Actual и snapshot сессии те же. Process другого персонажа этой сессии остаётся `open`. NPC этот переход не гасит.

**Конец боя.** В той же транзакции, что удаление этого боя: `open` с этим `battle_id` становятся `cancelled`. `resolved` этого боя остаётся `resolved`. Process с пустым `battle_id` и process другого `battleId` остаются. Строка `game_session` остаётся. `sessionRunning` остаётся истинным. Статус кампании прежний. `GameSessions::stop` из конца боя не вызывается.

**Stop.** В той же транзакции `deleteWithSession`, до удаления боёв и сессии: оставшиеся `open` с этим `session_id` становятся `cancelled`. В том числе process ещё открытых боёв и process с пустым `battle_id`. `resolved` остаётся `resolved`. Затем бои, команды и сессия снимаются как сейчас. Отменённые и resolved строки process не удаляются. Лист тот же. `targetStatus` по-прежнему не принимается.

**Повтор.** Повтор `endBattle` тем же ключом возвращает сохранённый итог до записи, второй отмены нет. Повтор return с новой revision снова ищет `open`: уже `cancelled` не переписывается. Stop без сессии по-прежнему отказ, process не создаёт.

**Права.** Нового кода нет. Return — кто уже может `game.returnCharacter`. Конец боя и stop — кто уже может эти действия. Открытие и resolve — методы порта для этого шага и для тестов, не отдельные права HTTP.

**Чего шаг не делает.** Не считает трудность, бросок и итог. Не пишет предложение, инициативу, широкий удар, DOT, каст и сцену. Не откатывает уже применённый эффект и не ставит `resolved` обратно в `open`. Не удаляет membership. Не останавливает сессию из `endBattle`. Не меняет статус кампании из stop. Не начинает G18 и G19.

## Что даёт этот заход

Таблица `game_process`. Порт, который открывает строку, переводит её в `resolved` и отменяет `open` по персонажу, по бою и по сессии. Три уже существующих перехода вызывают отмену. Ответы return, `endBattle` и `stopSession` новых полей process не получают.

## Что не закрыто

- Соло-проверка и pairwise-предложение (G18).
- Широкий удар (G19).
- Инициатива, DOT, каст, сцена.
- Откат уже применённого эффекта.
- `personalNotes`.

## Точки кода G1–G16

Старые планы не переписываются. Остальной код этих шагов не меняется. Ниже только места, без которых незакрытый process не отменяется.

- `GameCharacterMemberships::returnToOwner`. Внутри уже открытой транзакции шлюза, рядом с `saveReturned`: отмена `open` текущей сессии этого `characterId`, как в разделе Return. Конструктор уже принимает шесть зависимостей, седьмой не добавлять. Репозиторий process и `GameSessionRepository` создаются от того же `ISmartTableGateway`, как `GameBattleCleanup` создаёт репозитории боя внутри `deleteWithSession`. `reject` и `leave` этот вызов не получают. Сигнатура `returnToOwner` новый аргумент не получает.
- `GameBattles::mutator`. Сейчас собирает `GameBattleMutator` из уже хранимых репозиториев. Туда же передаётся `GameProcessRepository` от шлюза, который у `GameBattles` уже есть. Конструктор `GameBattles` остаётся на шести аргументах.
- `GameBattleMutator::end`. После принятой версии и до `GameBattleRepository::delete`: отмена `open` этого `battleId`. `stop` сессии не вызывать. Повтор ключа по-прежнему выходит в `GameBattles::execute` до `end`, поэтому второй проход сюда не входит.
- `GameBattleCleanup::deleteWithSession`. В той же транзакции, до `deleteBySession` боёв и до `deleteSession`: отмена оставшихся `open` этого `session_id`. Репозиторий process создаётся здесь так же, как уже создаются `GameBattleRepository` и `GameStrikeRepository`. Удаление строк process сюда не добавлять. `GameSessions::stop` тело не расширяет: отмена живёт в cleanup, который stop уже вызывает. `targetStatus` не появляется.

Повторный `transaction()` шлюза при уже открытой транзакции выполняет замыкание на том же соединении и второй commit не начинает. Отмена всё равно пишется прямыми вызовами репозитория внутри уже открытого замыкания return, `end` и `deleteWithSession`.

`GameCharacterHttp::returnToOwner`, `GameBattleHttp::end` и `GameSessionHttp::stop` контракт ответа не меняют.

## Модуль

Логика в `Roleplay/Game`, класс `GameProcesses` в `Service/`. Порт `IGameProcesses`: открыть, resolve, отменить по персонажу текущей сессии, по бою, по сессии. Сборка — `GameProcessPortFactory`, ключ `IGameProcesses` в `module.config.php` рядом с `IGameBattles`. `GamePortFactory` уже имеет десять публичных методов, это порог phpcs; одиннадцатый `create*` туда не добавляется. Character этот шаг не получает нового import Game. Уже лежащий `IGameContainer` в `Character/module.config.php`, которым читается `ICharacterSessionParticipants`, не трогать.

**DAG:** новых рёбер нет. Game по-прежнему не пишет Character storage.

Новый код ошибки не заводится. У `open` и `resolve` нет актора и нет фильтра карточки.

| Условие | Код |
|---|---|
| нет текущей сессии; игра `completed`; resolve не из `open`; тип не `character` и не `npc`; id участника меньше 1 | `GAME_INVALID` |
| нет строки process; персонаж не в снимке сессии; NPC не этой игры; бой не этой сессии; участник не в `GameBattleRepository::findParticipants` | `GAME_NOT_FOUND` |
| чужая revision return или чужая версия боя | `GAME_CONFLICT`, process не меняется |

Состав боя читается уже существующими `find` и `findParticipants`. Колонки состава — `kind` и `subject_id`; в `game_process` свои `participant_type` и `participant_id`.

## Фасад

`IGameProcesses`:

- `open(int $gameId, ?int $battleId, string $participantType, int $participantId): array` — строка `open`. `battleId === null` — process сессии.
- `resolve(int $processId): array` — `open` → `resolved`. Лист не пишет.
- `cancelOpenForCharacter(int $gameId, int $characterId): void` — только `open` текущей сессии этой игры. Сессии нет — пустой проход.
- `cancelOpenForBattle(int $battleId): void`
- `cancelOpenForSession(int $sessionId): void`

Возврат `open` и `resolve` — `processId`, `sessionId`, `battleId` (`null` или число), `participantType`, `participantId`, `status`. В карточку игры и в тело return / `endBattle` / stop эти поля не кладутся.

`IGames`, `IGameMemberships` и `IGameBattles` методы не прибавляют.

## HTTP

Нового action нет. `game.returnCharacter`, `game.endBattle` и `game.stopSession` тела успеха не меняют. `targetStatus` на stop остаётся `INVALID_PARAMS`. Вход с полем process на эти действия — `INVALID_PARAMS`.

Открытие и resolve снаружи HTTP не торчат. Их вызывает порт в тестах и, позже, G18.

## Тесты

Suite `game`. Suite `character` не расширяется.

Mysql строки. При живой сессии `open` без `battleId` пишет `open` и не пишет лист. `open` с `battleId` этого боя пишет тот же статус и чужой `battleId` не занимает. Персонаж вне снимка и NPC чужой игры — `GAME_NOT_FOUND`, строки нет. `resolve` переводит в `resolved` и actual не меняет. Нет строки process — `GAME_NOT_FOUND`. Второго листа и истории `CharacterVersion` в `game_process` нет. Статус тест читает через шлюз и `ListQuery`, как `GameBattleMysqlTest` читает `game_battle`. Отдельного `get` у порта нет.

Mysql return. Два `open` этого персонажа, сессия и бой, и один `resolved`. Return оставляет membership, гасит оба `open` в `cancelled`, `resolved` оставляет `resolved`, actual прежний. `open` другого персонажа остаётся `open`. `reject` и `leave` число `open` не меняют.

Mysql боя. Два боя. `endBattle` одного гасит только его `open`. `open` второго боя и `open` сессии без `battleId` остаются. `resolved` закрытого боя остаётся `resolved`. Строка сессии на месте, `sessionRunning` истинен, статус кампании прежний. Повтор того же ключа конца статус повторно не переписывает.

Mysql stop. После stop не остаётся `open` этой сессии: и сессионные, и боевые стали `cancelled`. `resolved` остаётся `resolved`. Строки process не удалены. Сессия снята, статус кампании прежний, лист тот же. `targetStatus` — `INVALID_PARAMS`. Stop без сессии — `GAME_INVALID`.

## Todo

- [x] **columns** — `game_process`: сессия, необязательный бой, участник, статус. Без листа и без `ReferenceField` на сессию и бой.
- [x] **open** — `open` и `resolve` через `IGameProcesses`. Resolve лист не пишет.
- [x] **return** — `returnToOwner` гасит `open` этой строки персонажа и membership оставляет.
- [x] **end** — `GameBattleMutator::end` гасит `open` этого боя и сессию не останавливает.
- [x] **stop** — `GameBattleCleanup::deleteWithSession` гасит оставшиеся `open` сессии, затем снимает стол как сейчас.
- [x] **gates** — phpunit `game`. Уже `resolved` не отменяется. Character не импортирует Game. G18 и G19 не появляются.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| колонки | Game `Table/` | `game_process` |
| сценарий | Game `Service/GameProcesses` | open, resolve, три отмены |
| точки G3, G12, G5 | `returnToOwner`, `GameBattleMutator::end`, `GameBattleCleanup::deleteWithSession` | погасить `open` в уже существующей транзакции |
| установка | `GameProcessSchema`, `GameModuleSetup::getTableClasses`, `GameMysqlFixture::installGameSchemas`, `dropGameTables` | новая таблица в том же списке, что `GameBattleSchema` |

## Acceptance G17

- Строка process в Game. Полного листа и истории `CharacterVersion` в ней нет.
- Return отменяет незакрытые process этой строки персонажа и membership оставляет.
- `endBattle` отменяет незакрытые process этого боя и сессию не останавливает.
- `stopSession` отменяет оставшиеся process сессии, статус кампании не меняет и `targetStatus` не принимает.
- `resolved` эти три перехода не переводят в `cancelled` и не удаляют. Лист не пишется.
- Нового import Game в Character нет. Уже существующий `IGameContainer` в `Character/module.config.php` не меняется.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G17; [`game-system.md`](game-system.md); [`game-plan-05.md`](game-plan-05.md); [`game-plan-12.md`](game-plan-12.md); [`game-plan-03.md`](game-plan-03.md); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md).
