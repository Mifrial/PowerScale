# План Game 18 — проверка

**Статус:** план. `BACKEND_OPEN`. Код этого шага не написан. Нарезка — [`game-roadmap.md`](game-roadmap.md) G18. Канон — [`game-system.md`](game-system.md): у проверки есть соло и pairwise; в pairwise предложение ждёт ответа цели, бросок строится после согласия; клиент шлёт решения и ожидаемые версии; трудность, бросок и итог считает сервер; лист меняется только в applied transition; ошибка версии не оставляет частичной записи. Process — [`game-plan-17.md`](game-plan-17.md): строка `game_process` уже есть, статусы `open | resolved | cancelled`; этот шаг её не заменяет второй моделью и колонки листа в неё не кладёт. Порт — [`game-plan-10.md`](game-plan-10.md): typed patch и ожидаемая `actual_version`; каталог — `setInventoryQuantity`, `setMoney`, `putInventoryQuantity`. Образец одной транзакции — [`game-plan-13.md`](game-plan-13.md): готовый урон во входе не принимается; персонаж через порт G10, NPC через `npc.version` уже лежащей строки; повтор ключа эффект второй раз не применяет. Бросок — [`mechanic-plan-06.md`](mechanic-plan-06.md): `IMechanicRolls` уже есть. Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. Поверх уже существующей строки process появляются соло-проверка и pairwise-предложение одной цели. Клиент шлёт решения и ожидаемые версии. Трудность, бросок и успех считает сервер. Итог, который пишет лист, меняет лист и эту строку process в одной транзакции: персонаж через порт G10, NPC через `npc.version` существующей строки. Конфликт версии не оставляет ни листа, ни смены статуса. Повтор команды с тем же ключом эффект второй раз не применяет.

Зависимость — G17 и уже сделанная сессия 4 Mechanic, [`mechanic-plan-06.md`](mechanic-plan-06.md). Широкий удар, инициатива, DOT, каст и сцена не входят. G19 не начинается.

Порт `IMechanicRolls` уже есть. Код G18 зовёт `roll` и `rate` и свою формулу кубов не пишет. Ждать ещё одного шага механик не нужно.

## Физическая схема

**Строка process не заменяется.** Статусы, привязка к сессии и необязательному `battleId`, участник и три отмены G17 остаются. Колонки `game_process` не растут: нет трудности, броска, предложения, листа и `CharacterVersion`. Техническая версия process не вводится.

**Тело проверки живёт в `game_check`.** Одна строка на один process. `process_id` — целое без `ReferenceField`: stop сессию снимает, строку process оставляет, restrict на process мешал бы снять проверку вместе с сессией. Режим `solo` или `pairwise`. Код правила — `StringField` длины 255. Цель pairwise — пара `target_type` и `target_id`; у соло оба пустые. Предложение: `pending`, `accepted` или `declined`; у соло сразу `accepted`. Трудность и успехи броска — пары целых `base` и `size`. У ещё не принятого предложения они пустые: броска до согласия нет. Успех — bool. Одной грани `1..20` в строке нет.

**Ключ.** `game_check_command`: `game_id`, `session_id` restrict, `idempotency_key` длины 255, тело и итог — два `JsonField`. Пара (`game_id`, ключ) уникальна. Тот же приём, что `game_strike_command`.

## Сверка G10, G13 и G17

- `IGameProcesses::open` пишет `open` при живой сессии. `resolve` переводит одну строку `open` в `resolved` и лист не пишет. Отдельного `cancel` одной строки нет: три отмены G17 гасят все `open` персонажа, боя или сессии.
- Return, `endBattle` и `stopSession` уже гасят незакрытый process и применённый эффект не откатывают. Эти три метода проверка не переписывает. Непринятое предложение — всё ещё `open`, поэтому та же отмена его гасит. Уже `resolved` проверка остаётся `resolved`.
- `ICharacterActualMutations::apply` принимает список операций и ожидаемую `actual_version`. Подпись не меняется. `CharacterRepository::writeGuarded` при уже открытой транзакции соединения второй begin не делает.
- `GameNpcRepository::replaceVersion` пишет `npc.version` предикатом версии. Нет строки NPC — операция в персонажа игры не становится созданием NPC.
- У `GameProcesses` уже шесть зависимостей. Проверка в этот конструктор не встаёт. У `GamePortFactory` порог публичных `create*` уже выбран; новый метод туда не добавляется.
- Character модуль Game не импортирует.

## Зафиксировано (модель и права)

**Соло.** Одна команда. Участник — тот, кого проверяют: персонаж снимка этой сессии или NPC этой игры; при `battleId` — участник состава этого боя. В той же транзакции: `open`, расчёт, при непустом списке операций запись листа, `resolve`. Process, который остался `open` после успеха соло, этот шаг не оставляет.

**Pairwise.** Две команды и одна цель. Предложение открывает process проверяющего и пишет `pending`. Броска нет, лист не пишется, статус process остаётся `open`. Ответ цели — одно решение `accept` или `decline`. `accept` считает трудность, бросок и итог и в той же транзакции переводит process в `resolved`. `decline` переводит эту одну строку в `cancelled` и лист не пишет. Второй цели нет: ключ списка целей — `INVALID_PARAMS`. Это не широкий удар.

**Решения, не итог.** Тело: `ruleCode`; для `CheckDifficulty` вида `ask` — трудность `{ base, size }`, оба целые. Pairwise добавляет цель `{ type, id }`, отличную от проверяемого. Во входе нет посчитанной трудности, броска, успеха, урона, операций листа и полного листа. Эти ключи — `INVALID_PARAMS`.

**Бросок.** Срез — `ICharacterRuleSlices::get(GameRecord::getSpaceId(), GameRecord::getRulesRevision())`. `findLive($ruleCode)` пустой, `isSpecBroken()` или `getSpec()` не `CheckSpec` — `GAME_INVALID`, строки нет. `hitCheck`, `concentrationToken`, `willpower` или `unstableCheck` — `GAME_INVALID`: удар, каст и жетон не этот шаг. Вид `from_state` — `GAME_INVALID`: состояния и DOT не этот шаг. `getAllowedModes()` — строка `solo`, `joint` или `both` ([`rule-system.md`](rule-system.md)). Соло принимает `solo` и `both`. Предложение принимает `joint` и `both`. Иначе `GAME_INVALID`. Вид `none`: трудность `SizedBase` `{ base: 0, size: 0 }`. Вид `ask`: трудность — решение клиента `{ base, size }`, ответ его не повторяет.

Кубы считает `IMechanicRolls::roll`, успех — `IMechanicRolls::rate`. Game хендлеры не регистрирует и `MechanicBindingList.fromRules` не переносит. `CharacterMechanicBindings` не зовёт: тот класс собирает только `purchase_surcharge`. Каталог `MechanicRecord` — `IMechanics::getList`. `MechanicEngine::resolveActive` сшивает binding с каталогом по `mechanicId`, поэтому строка механики без binding в бросок не попадает.

Каждая строка `getMechanics()` live-правила среза становится `MechanicBinding`. `mechanic_payload` — массив с `type`. `type === roll`: объект `data` (`diceCount`, `dieFaces`, `efficiency`, `adv`, `dieSize`, `sub_mechanics`) собирается в `RollMechanicPayload`. `type === roll_score_adjust`: `data.oneDelta` и `data.faceDelta` — в `RollScoreAdjustPayload`; хендлера под него нет, строка молча пропускается движком. Иной `type` и payload без `type` — binding с `null`, чтобы `six_one_rule` и `advantage_disadvantage` всё равно нашлись по id.

Характеристику и прикреплённые коды Game читает по цепочке `CheckSpec::getParentCheckCode()`. Сначала карточка проверки, затем предки. Повтор кода обрывает цикл. Первая непустая `getCharacteristicCode()` в цепочке — код пула. Первый `getAttachedRuleCodes()`, который не `null`, уходит в `ResolveActiveOptions` как `extraRuleCodes`; пустой массив задан и дальше не наследуется. `null` значит «смотреть предка». Обход без характеристики и без списка оставляет оба пустыми. Отдельной сессии Mechanic под этот обход нет.

`RollSpec` задаёт вызывающий. `withRollDefaults` подставляет эффективность, размер и `adv`, но не `diceCount` и не `dieFaces`: у `RollMechanicPayload` это `?int`, и `MechanicRolls` их не читает. `dieFaces` — `getDieFaces()` payload `roll`. `diceCount`: код характеристики найден — `value.base` строки `CharacterRecord::getSheet()['characteristicPurchases']` с этим `characteristicCode`. В `choices` у закупки только `characteristicCode` и `cost`, поля `value` там нет (`CharacterChoiceAssembler`). Нет строки или `value.base` не целое — `GAME_INVALID`. Кода характеристики нет — `getDiceCount()` того же payload. `null` у числа кубов или граней — `GAME_INVALID`, строки проверки нет: подставлять 6 или 3 нельзя. У NPC тот же ключ внутри `npc.version.sheet`. В `advantages` уходит пустой список: ручную помеху клиент в этом шаге не шлёт. `rng` — замыкание Game с `float` в `[0, 1)`, как требует `IMechanicRolls::roll`; грани считает порт. `rate` получает `{ base: RollResult::getTotalSuccesses(), size: RollResult::getSpec()->getDieSize() }` и трудность. `passed` и `rating` пишутся в итог. Повтор ключа `roll` второй раз не вызывает. Сравнение тела ключа — `sortKeys` и `json_encode`, как `GameStrikes::replay`.

Класс расчёта держит `IMechanicRolls`. В конструктор `GameChecks` седьмой зависимостью он не входит.

**Список операций.** Считает класс в `Service/` Game, не порт и не Vue. Вход — уже проверенные решения и actual. Выход — список каталога G10. У `CheckSpec` нет дельты денег и инвентаря, новый `kind` не заводится, поэтому формула этого захода возвращает пустой список: лист не меняется, `apply` и `replaceVersion` не вызываются. Пустой список всё равно закрывает соло и принятый pairwise: process становится `resolved`.

**Запись, когда список не пуст.** Тот же commit, что смена статуса. Сначала лист, потом `resolve`. Персонаж — `ICharacterActualMutations::apply`. NPC — `ICharacterActualMutations::applyToDocument`, затем `GameNpcRepository::replaceVersion` уже лежащей строки, как `GameEconomyApply`. Нет строки NPC — `GAME_NOT_FOUND` до insert. `GameStrikes::applyOperations` при непустом списке бросает `GAME_INVALID` и порт не зовёт; проверка этот throw не копирует. Исключение откатывает и лист, и статус. Сигнал `GameDeliverySignal` этот шаг не шлёт: список пуст, лист не меняется. `GameDeliveryListener::register` из фабрики проверки не вызывать: его уже вызывает `GameStrikePortFactory::create`.

**CAS.** Соло, предложение и ответ сверяют ожидаемую версию листа участника process до записи, даже когда список пуст. Персонаж — `ICharacters::get`. NPC — `GameNpcRecord::getActualVersion()`. Чужая версия — `GAME_CONFLICT`: соло не пишет process, предложение не открывает строку, ответ не меняет `open` и не пишет лист. Версия боя и версия сессии не участвуют: проверка их не увеличивает.

**Идемпотентность.** Журнал `game_check_command`. Повтор с тем же телом возвращает сохранённый итог и второй раз process не открывает, не закрывает и лист не пишет. Тот же ключ с другим телом — `GAME_CONFLICT`. Пустой ключ — `GAME_INVALID`.

**Ход записи.** До транзакции: видимость, повтор ключа, `completed`, право.

1. Карточка скрыта — `GAME_NOT_FOUND`, даже если ключ уже есть.
2. Есть команда с этим ключом — сверка тела и возврат итога.
3. Игра `completed` или нет сессии — `GAME_INVALID`.
4. Нет `game.edit` этой игры и нет `game.edit_all` — `AUTH_DENIED`. Владелец, `gm` или `editAll`, как удар.
5. Граница участника — та же, что `open` G17. Цель pairwise вне этой границы — `GAME_NOT_FOUND`. Правило не проверка или флаг удара, каста, жетона, воли, неустойчивости, `from_state` — `GAME_INVALID`.
6. Ответ читает process. `session_id` строки обязан быть текущей сессией этой `gameId`. Чужая сессия или чужая игра — `GAME_NOT_FOUND`: `resolve` и `cancel` сами игру не сверяют. Статус не `open` — `GAME_INVALID`.
7. Одна транзакция `ISmartTableGateway`. Вложенный `transaction()` при уже открытой транзакции выполняет замыкание на том же соединении и второй commit не начинает (`SmartTableGateway`). Соло: `open`, расчёт, лист если список не пуст, `resolve`, строка проверки и команда. Предложение: `open`, строка `pending` без броска, команда. Ответ `accept`: расчёт, лист если список не пуст, `resolve`. Ответ `decline`: `cancel` этой строки. `askedDifficulty` повторно не присылается. Исключение откатывает транзакцию.

**Права.** Нового ключа нет. Соло, предложение и ответ — владелец, `gm` или `game.edit_all`.

**Чего шаг не делает.** Не пишет колонки process. Не считает широкий удар, инициативу, DOT, каст и сцену. Не принимает готовый итог. Не откатывает уже `resolved`. Не останавливает сессию из проверки. Не начинает G19.

## Что даёт этот заход

Фасад `IGameChecks` и три действия: соло, предложение, ответ. Соло и принятый ответ возвращают `processId`, статус `resolved`, трудность, бросок, успех и `sheetVersion`. `sheetVersion` — `null`, пока список операций пуст. Предложение возвращает `processId`, статус `open` и пустые бросок и успех. Отказ возвращает статус `cancelled` и лист не меняет.

## Что не закрыто

- Непустой список операций проверки: поля листа под этот итог нет, новый `kind` не заводится. Ветка записи в том же commit уже стоит.
- Широкий удар (G19).
- Инициатива, DOT, каст, сцена.
- `personalNotes`.

## Точки кода G1–G17

Старые планы не переписываются. Остальной код этих шагов не меняется. `CharacterActualMutations`, подпись `apply`, `GamePortFactory`, return, `GameBattleMutator::end` и `GameSessions::stop` не меняются. Character Game не импортирует.

Без двух точек проверка не переводит одну строку process и не снимается вместе с сессией.

- `IGameProcesses` и `GameProcesses`. Один новый метод `cancel(int $processId): array`. Он переводит эту строку из `open` в `cancelled`. Нет строки — `GAME_NOT_FOUND`. Статус не `open` — `GAME_INVALID`, статус прежний. `open`, `resolve` и три массовые отмены не меняются. Конструктор остаётся на шести аргументах. Ответ `decline` зовёт этот метод, а не `cancelOpenForCharacter`: иначе погасли бы все `open` персонажа. Репозиторий process получает запись одного id рядом с `markResolved`.
- `GameBattleCleanup::deleteWithSession`. В уже открытой транзакции, до снятия сессии, удаляются строки `game_check` и `game_check_command` этой сессии. Иначе restrict на сессию не даёт снять стол, а ключ переживает в следующую сессию. Строки `game_process` по-прежнему не удаляются. `endBattle` строки проверки не удаляет: незакрытое предложение гасит уже существующая отмена `open` этого боя.
- `GameModuleSetup::getTableClasses` сливает `GameCheckSchema::getTableClasses()`. `GameMysqlFixture::installGameSchemas` ставит схему отдельным `install()`, как `GameStrikeSchema`. `dropGameTables` удаляет новые таблицы до `GameSessionTable`. Без этого тесты и setup таблицы не видят.

Повторный `transaction()` шлюза при уже открытой транзакции выполняет замыкание на том же соединении. `open`, `resolve` и `cancel` зовутся из этого замыкания и свой commit не начинают.

## Модуль

Логика в `Roleplay/Game`, класс `GameChecks` в `Service/`. Порт `IGameChecks`. Сборка — `GameCheckPortFactory` с `create` и `createHttp`, ключ в `module.config.php` рядом с `IGameProcesses`. Action получает `GameCheckHttp`, в `handle` только его вызов. Ключи актора — `GamePermissionKeys::EDIT_ALL` и `VIEW_ALL`, как `GameStrikeHttp`. `events` остаётся `[]`.

**DAG:** Game → Character через уже существующие `ICharacterActualMutations`, `ICharacterRuleSlices` и `ICharacters`. Нового ребра нет. Character Game не импортирует.

Расчёт — класс в `Service/` Game, не порт и не action. Конструктор фасада, не больше шести: шлюз, `IGames`, `GameCardAccess`, `IGameProcesses`, `ICharacterActualMutations`, расчёт. Репозитории сессии, боя, NPC и проверки фасад создаёт сам.

Новый код ошибки не заводится.

| Условие | Код |
|---|---|
| нет актора | `AUTH_REQUIRED` |
| карточка скрыта; участник или цель вне границы G17; нет строки NPC; нет process на ответ | `GAME_NOT_FOUND` |
| нет `game.edit` и нет `game.edit_all` | `AUTH_DENIED` |
| пустой ключ; игра `completed`; нет сессии; правило не живой `CheckSpec`; режим не `solo`/`joint`/`both` или не тот поток; удар, каст, жетон, воля, неустойчивость, `from_state`; проверяемый и цель совпали; ответ не `accept` и не `decline`; `cancel` или `resolve` не из `open` | `GAME_INVALID` |
| чужая версия листа; тот же ключ с другим телом | `GAME_CONFLICT`, частичной записи нет |
| готовые трудность, бросок, успех, урон, операции, лист; список целей | `INVALID_PARAMS` |

`CharacterConflictException` и `CharacterInvalidException` фасад наружу не выпускает: `GAME_CONFLICT` и `GAME_INVALID`.

## Фасад

`IGameChecks`. Не метод `IGameProcesses` и не метод `ICharacters`.

- `declareCheck(int $gameId, int $actorUserId, bool $editAll, bool $viewAll, ?int $battleId, string $idempotencyKey, int $expectedSheetVersion, array $check): array`
- `proposeCheck(int $gameId, int $actorUserId, bool $editAll, bool $viewAll, ?int $battleId, string $idempotencyKey, int $expectedSheetVersion, array $proposal): array`
- `answerCheck(int $gameId, int $actorUserId, bool $editAll, bool $viewAll, int $processId, string $idempotencyKey, int $expectedSheetVersion, array $answer): array`

`check` — `{ participant, ruleCode, askedDifficulty? }`. `proposal` — то же и `target`. `participant` и `target` — `{ type, id }`. `answer` — `{ decision }`, `decision` — `accept` или `decline`.

Возврат — `{ processId, status, difficulty, roll, success, sheetVersion }`. У предложения `difficulty`, `roll` и `success` — `null`, `status` — `open`, `sheetVersion` — `null`. У отказа бросок не считается, лист не пишется, `status` — `cancelled`. Повтор ключа отдаёт тот же объект.

## HTTP

Свои DTO, `csrf` true. В `handle` только вызов сценария.

| Action | Кто | Тело | Успех |
|---|---|---|---|
| `game.declareCheck` | владелец, `gm` или `game.edit_all` | `{ gameId, battleId?, idempotencyKey, expectedSheetVersion, check }` | process `resolved`, трудность, бросок, успех, `sheetVersion: null` |
| `game.proposeCheck` | тот же актор | `{ gameId, battleId?, idempotencyKey, expectedSheetVersion, proposal }` | process `open`, бросок `null`, `sheetVersion: null` |
| `game.answerCheck` | тот же актор | `{ gameId, processId, idempotencyKey, expectedSheetVersion, answer }` | `resolved` или `cancelled` |

`battleId` отсутствует — process сессии, как `open` с `null`.

## Тесты

Suite `game`. Suite `character` не расширяется.

Mysql соло. Живое `CheckSpec` вида `none` и режима `solo` или `both` пишет один `open`, тут же `resolved`, хранит трудность `{0, 0}` и итог порта, не меняет `actual_version` и `npc.actual_version`. Своего подсчёта граней в тесте нет. Режим `joint` соло не открывает. Во входе трудность, бросок, успех или урон — `INVALID_PARAMS`, строки нет. `hitCheck` и `from_state` — `GAME_INVALID`.

Mysql pairwise. Предложение режима `joint` или `both` пишет `open` и `pending`, броска нет, лист прежний. Режим `solo` предложение не открывает. `accept` переводит в `resolved` и заполняет бросок. `decline` переводит только эту строку в `cancelled`; другой `open` того же персонажа остаётся `open`. Process чужой сессии — `GAME_NOT_FOUND`. Список целей — `INVALID_PARAMS`.

Mysql отката. Чужая `expectedSheetVersion` на соло не оставляет process. На ответе не переводит `open` и не пишет лист. Повтор того же ключа возвращает прежний итог и второй `resolved` не ставит. Тот же ключ с другим телом — `GAME_CONFLICT`.

Mysql сессии. `endBattle` гасит `open` предложения этого боя и сессию не останавливает. `resolved` соло остаётся `resolved`. Stop гасит оставшийся `open` и удаляет строки проверки и команды этой сессии. Строки process не удалены. `targetStatus` — `INVALID_PARAMS`. Action широкого удара, инициативы, DOT, каста и сцены нет.

## Todo

- [ ] **schema** — `game_check` и `game_check_command`. Колонки `game_process` не менять.
- [ ] **cancel** — `IGameProcesses::cancel` одной строки `open`. Три массовые отмены не менять.
- [ ] **cleanup** — строки проверки и команды снимаются до сессии. Строки process не удалять.
- [ ] **resolve** — трудность, бросок и успех считает сервер. Список операций пуст, порт не вызывается. Ветка непустого списка — тот же commit, что `resolve`.
- [ ] **http** — три action без готового итога и без списка целей.
- [ ] **gates** — phpunit `game`. Повтор не применяет эффект второй раз. Чужая версия не оставляет частичной записи. Character не импортирует Game. G19 не появляется.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| проверка | Game `Interface/Service/` `Service/` | соло, предложение, ответ, CAS, транзакция |
| расчёт | Game `Service/` | `CheckSpec` → трудность, бросок, успех, список операций |
| точка G17 | `IGameProcesses::cancel` | одна строка `open` → `cancelled` |
| снятие сессии | `GameBattleCleanup::deleteWithSession` | строки проверки до сессии |
| сборка | `GameCheckPortFactory` | `create` и `createHttp`; слушателя доставки не регистрирует |
| таблицы | `GameCheckSchema`, `GameModuleSetup`, `GameMysqlFixture` | карта как `GameStrikeSchema`; drop до `GameSessionTable` |
| HTTP | Game `Action/`, `GameCheckHttp` | три действия, ключи как у удара |

## Acceptance G18

- Соло и pairwise одной цели идут через уже существующую строку process. Второй модели process нет.
- Клиент присылает решения и ожидаемую версию листа. Трудность, бросок, успех и урон во входе отвергаются. Их считает сервер. Бросок pairwise строится после согласия.
- Итог, который пишет лист, делает это в той же транзакции, что переход process. Персонаж — порт G10, NPC — `npc.version` существующей строки. В этом заходе список операций пуст, версии листа те же.
- Конфликт версии не оставляет process соло и не закрывает предложение. Повтор ключа эффект второй раз не применяет.
- Широкий удар, инициатива, DOT, каст и сцена не появляются.
- Character не импортирует Game.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G18; [`game-system.md`](game-system.md); [`game-plan-17.md`](game-plan-17.md); [`game-plan-10.md`](game-plan-10.md); [`game-plan-13.md`](game-plan-13.md); [`mechanic-plan-06.md`](mechanic-plan-06.md); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md).
