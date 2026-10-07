# План Game 19 — широкий удар

**Статус:** широкий удар в PHP сделан. `BACKEND_OPEN`. Suite `game` по `GameWideStrikeMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G19. Канон — [`game-system.md`](game-system.md): обычный удар — `1 → 1`; `1 → N` считает одного атакующего против каждой цели и возвращает `targetResults[]` на каждого защитника; свёртка не прячет отказ одной цели; `N → 1` — backlog; `N → N` не контракт. Удар `1 → 1` — [`game-plan-13.md`](game-plan-13.md): готовый урон во входе не принимается; персонаж через порт G10, NPC через `npc.version` уже лежащей строки; повтор ключа эффект второй раз не применяет; `endBattle` сессию не гасит. Проверка — [`game-plan-18.md`](game-plan-18.md): соло и pairwise одной цели уже есть; этот шаг их не заменяет второй моделью броска и `game_check` не пишет. Process — [`game-plan-17.md`](game-plan-17.md): строка process уже есть; широкий удар её не открывает. Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. В уже открытом бою появляется сценарий `1 → N`: один атакующий и несколько защит. Клиент шлёт решения и ожидаемые версии. Ответ содержит `targetResults[]` — по записи на каждого защитника. Отказ одной цели виден в её записи, не склеивается в один общий успех и не отменяет уже посчитанный итог другой. Готовые успех и урон во входе не принимаются. Числа успеха и урона шаг не считает: у принятой цели оба поля `null`. Их заполнит G20, когда появится исполнитель `DimensionalFormula`. Если исход пишет лист, версии целей и листы меняются в одной транзакции: персонаж через порт G10, NPC через `npc.version` существующей строки. В этом заходе список операций пуст, порт не вызывается. Повтор команды с тем же ключом эффект второй раз не применяет. `endBattle` сессию не гасит.

Зависимость — G13 и G18. G20 не начинается. `N → 1`, `N → N`, инициатива, DOT, каст и сцена не входят.

## Физическая схема

**Строка `game_strike` не заменяется.** В ней один `defender_kind` и один `defender_id`, оба required. Несколько защит в эту строку не кладутся. `declareStrike` и `resolveStrike` по-прежнему отвергают список целей.

**Тело широкого удара — две новые таблицы.** `game_wide_strike`: один бой, одна сессия, один атакующий, коды действия и предмета, профиль, `open`. `session_id` — restrict на `GameSessionTable`. `battle_id` без ссылки на бой, как у `game_strike`. Защитника в этой строке нет. `game_wide_strike_target`: `strike_id` без `ReferenceField` (stop снимает сессию, restrict на удар мешал бы снять цели вместе со строкой), пара `defender_kind` и `defender_id`, реакция и код предмета блока. У ещё открытого удара реакция пустая. Успех, урон и отказ в колонки не пишутся: они живут в итоге команды. Одной грани `1..20` в строке нет.

**Ключ.** `game_wide_strike_command`: `game_id`, `session_id` restrict, `idempotency_key` длины 255, тело и итог — два `JsonField`. Пара (`game_id`, ключ) уникальна. Журнал `game_strike_command` этот шаг не делит: повтор `1 → 1` и повтор `1 → N` не встречаются.

## Сверка G10, G13, G17 и G18

- `IGameStrikes` принимает одного защитника и одну `expectedSheetVersion`. Подпись не меняется. Список целей по-прежнему `INVALID_PARAMS`.
- `GameStrikeRules::operations` возвращает пустой список. `1 → 1` порт не зовёт. Широкий удар этот метод не подменяет: иначе обычный удар начал бы писать лист.
- `GameStrikeTable` одного защитника не растягивается на несколько целей.
- Проверка G18 зовёт `IMechanicRolls::roll` и `rate` для `CheckSpec`. Широкий удар тот фасад не вызывает и строку `game_check` не пишет. Process не открывается: `IGameProcesses` не меняется.
- `ICharacterActualMutations::apply` принимает список операций и ожидаемую `actual_version`. Подпись не меняется. NPC — `applyToDocument` и `GameNpcRepository::replaceVersion` уже лежащей строки. Нет строки NPC — операция в персонажа игры не становится созданием NPC.
- `endBattle` сессию не останавливает и строку `game_strike` не удаляет. Этот ход не меняется. Незакрытый широкий удар до stop эффект задним числом не дописывает.
- Character модуль Game не импортирует.

## Зафиксировано (модель и права)

**Сценарий.** Две команды одного боя. Атака открывает один незакрытый широкий удар: один атакующий и не меньше двух защит, все уже в составе этого `battleId`. Защита закрывает этот удар и считает каждую цель отдельно. Второй незакрытый широкий удар того же боя — `GAME_INVALID`. Обычный открытый `game_strike` этому замку не мешает и сам от широкого удара не закрывается.

**Один атакующий.** В теле один ключ атакующего. Второй атакующий, список атакующих, `N → 1` и `N → N` — `INVALID_PARAMS`, строки нет. Одна цель — тоже `INVALID_PARAMS`: это уже `game.declareStrike`, второй путь `1 → 1` не заводится. У каждой цели обязателен ключ защитника `{ type, id }`. Нет ключа — `INVALID_PARAMS`, удара нет: свёртка не пропускает цель молча.

**Решения, не итог.** Атака: атакующий, `targets[]`, `actionRuleCode`, `itemRuleCode`, `profileType`, `profileIndex`. Защита: `defenses[]` той же длины и в том же порядке, что объявленные цели. Элемент — `reaction` (`ignore` | `dodge` | `block`), необязательный `blockItemRuleCode` и `expectedSheetVersion` этой цели. Во входе нет урона, успеха, `remaining`, `damageTypeCode`, `actionPointCost`, `resourceRuleCode`, операций листа и полного листа. Эти ключи — `INVALID_PARAMS`.

**Итог цели без чисел.** Один и тот же атакующий закрывается против каждого защитника. `IMechanicRolls` шаг не зовёт: `rate` возвращает `CheckRating`, а не урон профиля. `WeaponDamage::getFormula()` — `DimensionalFormula`, исполнителя формулы в `Roleplay` нет. `IGameChecks` и `CheckSpec` не вызываются. У принятой цели `success` и `damage` — `null`, список операций каталога G10 пуст. Новый `kind` не заводится. Лист не меняется, `apply` и `replaceVersion` не вызываются. Числа подставит G20 в те же слоты.

**Отказ одной цели.** Сначала считаются все цели, без записи. Отказ — чужая `expectedSheetVersion`, защитник вне состава, нет строки NPC, реакция вне набора, предмет блока не живой. Такой отказ становится записью этой цели: свой `code`, без успеха и без урона. Уже посчитанные записи других целей остаются. Общего флага «удар не удался» нет. Отказ не отменяет чужой итог и не выкидывает чужую запись из массива.

**Запись.** В транзакцию входят только цели без отказа и с непустым списком операций. В этом заходе списки пусты, порт не вызывается, версии листов те же. Когда список цели не пуст, её лист пишется в том же commit, что закрытие удара и одна новая версия боя: персонаж — `ICharacterActualMutations::apply`, NPC — `applyToDocument` и `replaceVersion`. Цели с отказом в этот commit не входят. Исключение откатывает весь commit: лист, закрытие и версию боя. Посчитанный ответ при этом клиенту не отдаётся как частичный успех записи. Сигнал `GameDeliverySignal` этот шаг не шлёт.

**CAS.** Атака сверяет версию боя. Защита сверяет версию боя до расчёта. Версия листа — по каждой цели, даже когда список операций пуст. Персонаж — `ICharacters::get`. NPC — `GameNpcRecord::getActualVersion()`. Чужая версия боя — `GAME_CONFLICT` на всю команду: удар не закрывается, `targetResults` нет. Чужая версия одной цели — отказ только этой записи. Версия сессии не участвует.

**Идемпотентность.** Журнал `game_wide_strike_command`. Повтор с тем же телом возвращает сохранённый итог, включая отказы отдельных целей, и второй раз удар не открывает, не закрывает и лист не пишет. Тот же ключ с другим телом — `GAME_CONFLICT`. Пустой ключ — `GAME_INVALID`.

**Ход записи.** До транзакции: видимость, повтор ключа, `completed`, право.

1. Карточка скрыта — `GAME_NOT_FOUND`, даже если ключ уже есть.
2. Есть команда с этим ключом — сверка тела и возврат итога.
3. Игра `completed` или нет сессии — `GAME_INVALID`.
4. Нет `game.edit` этой игры и нет `game.edit_all` — `AUTH_DENIED`. Владелец, `gm` или `editAll`, как удар.
5. `battleId` этой сессии. Атакующий и каждая цель — разные участники состава. Нет в составе на атаке — `GAME_NOT_FOUND`, строки нет. Повтор одной и той же цели в списке — `INVALID_PARAMS`. Коды и профиль — та же проверка среза, что `GameStrikeRules::assertAttack`. Не прошли — `GAME_INVALID`.
6. Атака: открытого широкого удара нет, версия боя совпала. Иначе `GAME_INVALID` или `GAME_CONFLICT`.
7. Защита: открытый широкий удар есть, число защит равно числу целей. Нет открытого удара — `GAME_INVALID`. Версия боя не совпала — `GAME_CONFLICT`, расчёта нет.
8. Одна транзакция `ISmartTableGateway`. Атака вставляет строку удара, строки целей и вызывает `advanceVersion`. Защита считает `targetResults`. Списки операций пусты — закрытие удара и один `advanceVersion`. Исключение откатывает транзакцию.

**Права.** Нового ключа нет. Атака и защита — владелец, `gm` или `game.edit_all`.

**Чего шаг не делает.** Не пишет `game_strike`, `game_check` и process. Не меняет `declareStrike` и `resolveStrike`. Не принимает готовый урон и успех и сам их не считает. Не считает `N → 1`, `N → N`, инициативу, DOT, каст и сцену. Не начинает G20. Не останавливает сессию. `endBattle` сессию не гасит.

## Что даёт этот заход

Фасад `IGameWideStrikes` и два действия: объявить широкий удар и закрыть его. Атака возвращает `battleId`, `strikeId`, новую версию боя и `targetResults: []`. Защита возвращает то же и `targetResults` длины списка целей. Запись цели: `target`, `success`, `damage`, `sheetVersion`, `code`. У принятой цели `success`, `damage`, `sheetVersion` и `code` — `null`. У отказа `success`, `damage` и `sheetVersion` — `null`, `code` — код этой цели. Сессия остаётся запущенной.

## Что не закрыто

- Числовые успех и урон и поле листа под них (G20). Ветка записи в том же commit уже названа; в этом заходе список операций пуст, слоты `null`.
- `N → 1` и `N → N`.
- Инициатива, DOT, каст, сцена.
- `personalNotes`.

## Точки кода G1–G18

Старые планы не переписываются. `IGameStrikes`, `GameStrikes`, `GameStrikeRules`, `GameStrikeTable`, `IGameChecks`, `IGameProcesses`, `CharacterActualMutations`, подпись `apply`, `GamePortFactory` и `GameBattleMutator::end` не меняются. Character Game не импортирует.

Без одной точки строки широкого удара не снимаются вместе с сессией: у `session_id` restrict, как у `game_strike`.

- `GameBattleCleanup::deleteWithSession`. В уже открытой транзакции, до снятия сессии и до удаления боёв, удаляются строки `game_wide_strike_target`, `game_wide_strike` и `game_wide_strike_command` этой сессии. Иначе restrict на сессию не даёт снять стол, а ключ переживает в следующую сессию. `endBattle` эти строки не удаляет. Конструктор `GameSessions` не растёт.
- `GameModuleSetup::getTableClasses` сливает `GameWideStrikeSchema::getTableClasses()`. `GameMysqlFixture::installGameSchemas` ставит схему отдельным `install()`, как `GameStrikeSchema`. `dropGameTables` удаляет новые таблицы до `GameSessionTable`.

`GameStrikeRepository::findOpen` широкий удар не читает. Второй замок — свой, на `game_wide_strike`.

## Модуль

Логика в `Roleplay/Game`, класс `GameWideStrikes` в `Service/`. Порт `IGameWideStrikes`. Сборка — `GameWideStrikePortFactory` с `create` и `createHttp`, ключ в `module.config.php` рядом с `IGameStrikes`. Action получает `GameWideStrikeHttp`, в `handle` только его вызов. Ключи актора — `GamePermissionKeys::EDIT_ALL` и `VIEW_ALL`, как `GameStrikeHttp`. `events` остаётся `[]`.

**DAG:** Game → Character через уже существующие `ICharacterActualMutations`, `ICharacterRuleSlices` и `ICharacters`. Ребра к Mechanic нет. Character Game не импортирует.

Конструктор фасада, не больше шести: шлюз, `IGames`, `GameCardAccess`, `GameSessionRepository`, `ICharacterActualMutations`, `GameStrikeRules`. Репозитории боя, NPC и широкого удара фасад создаёт сам. Отдельный класс броска не заводится.

Новый код ошибки не заводится.

| Условие | Код |
|---|---|
| нет актора | `AUTH_REQUIRED` |
| карточка скрыта; бой не этой сессии; на атаке участник не в составе | `GAME_NOT_FOUND` |
| нет `game.edit` и нет `game.edit_all` | `AUTH_DENIED` |
| пустой ключ; игра `completed`; нет сессии; второй открытый широкий удар; нет открытого широкого удара; код не живой; нет профиля; атакующий совпал с целью; на защите число `defenses` не равно числу целей | `GAME_INVALID` |
| чужая версия боя; тот же ключ с другим телом | `GAME_CONFLICT`, частичной записи нет |
| меньше двух целей; нет ключа защитника; повтор цели; несколько атакующих; готовые урон, успех, операции, лист | `INVALID_PARAMS` |
| одна цель на защите: чужая версия листа, нет в составе, нет строки NPC, реакция или предмет блока | запись этой цели в `targetResults`, чужие итоги на месте |

`CharacterConflictException` и `CharacterInvalidException` фасад наружу не выпускает: `GAME_CONFLICT` и `GAME_INVALID`.

## Фасад

`IGameWideStrikes`. Не метод `IGameStrikes`, не метод `IGameChecks` и не метод `ICharacters`.

- `declareWideStrike(int $gameId, int $actorUserId, bool $editAll, bool $viewAll, int $battleId, string $idempotencyKey, int $expectedBattleVersion, array $attack): array`
- `resolveWideStrike(int $gameId, int $actorUserId, bool $editAll, bool $viewAll, int $battleId, string $idempotencyKey, int $expectedBattleVersion, array $defense): array`

`attack` — `{ attacker, targets, actionRuleCode, itemRuleCode, profileType, profileIndex }`. `attacker` и элемент `targets` — `{ type, id }`. `defense` — `{ defenses }`. Элемент — `{ reaction, blockItemRuleCode?, expectedSheetVersion }`.

Возврат — `{ battleId, strikeId, version, targetResults }`. Элемент — `{ target, success, damage, sheetVersion, code }`. У атаки `targetResults` пуст. Повтор ключа отдаёт тот же объект.

## HTTP

Свои DTO, `csrf` true. В `handle` только вызов сценария.

| Action | Кто | Тело | Успех |
|---|---|---|---|
| `game.declareWideStrike` | владелец, `gm` или `game.edit_all` | `{ gameId, battleId, idempotencyKey, expectedVersion, attack }` | удар открыт, `targetResults: []` |
| `game.resolveWideStrike` | тот же актор | `{ gameId, battleId, idempotencyKey, expectedVersion, defense }` | `targetResults` по каждой цели |

`game.declareStrike` и `game.resolveStrike` остаются `1 → 1`.

## Тесты

Suite `game`. Suite `character` не расширяется.

Mysql `1 → N`. Две цели одного атакующего пишут одну строку удара и две строки целей, увеличивают версию боя на 1 и не меняют `actual_version` и `npc.actual_version`. Закрытие возвращает две записи `targetResults`: `success`, `damage`, `sheetVersion` и `code` — `null`. Вызова `ICharacterActualMutations::apply`, `replaceVersion` и `IMechanicRolls` нет. Одна цель, второй атакующий и урон во входе — `INVALID_PARAMS`, строки нет. `game.declareStrike` со списком целей по-прежнему `INVALID_PARAMS`.

Mysql отказа. Чужая `expectedSheetVersion` одной цели даёт этой записи `code` отказа и `success`, `damage` — `null`. Вторая цель в том же ответе остаётся принятой: те же четыре поля `null`, включая `code`. Версия листа отказанной цели прежняя. Общего свёрнутого успеха нет. Чужая версия боя не закрывает удар и `targetResults` не возвращает.

Mysql отката и повтора. Повтор того же ключа возвращает прежний `targetResults` и второй раз лист не пишет. Тот же ключ с другим телом — `GAME_CONFLICT`.

Mysql сессии. `endBattle` снимает бой и сессию не останавливает. Stop удаляет строки широкого удара, целей и команды этой сессии. Строки `game_strike` и process этим шагом не переписываются. `targetStatus` — `INVALID_PARAMS`. Action `N → 1`, инициативы, DOT, каста и сцены нет.

## Todo

- [x] **schema** — `game_wide_strike`, `game_wide_strike_target`, `game_wide_strike_command`. Колонки `game_strike` не менять.
- [x] **cleanup** — строки широкого удара снимаются до сессии. `endBattle` их не удаляет.
- [x] **resolve** — один атакующий против каждой цели. `success` и `damage` — `null`. Список операций пуст. Отказ одной цели остаётся в её записи. `IMechanicRolls` не вызывается.
- [x] **http** — два action без готового урона. `1 → 1` не менять.
- [x] **gates** — phpunit `game`. Повтор не применяет эффект второй раз. Отказ одной цели не прячет и не отменяет итог другой. Character не импортирует Game. `N → 1` не появляется.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| широкий удар | Game `Interface/Service/` `Service/` | `1 → N`, CAS, транзакция, `targetResults` с пустыми числами |
| снятие сессии | `GameBattleCleanup::deleteWithSession` | строки широкого удара до сессии |
| сборка | `GameWideStrikePortFactory` | `create` и `createHttp` |
| таблицы | `GameWideStrikeSchema`, `GameModuleSetup`, `GameMysqlFixture` | карта как `GameStrikeSchema`; drop до `GameSessionTable` |
| HTTP | Game `Action/`, `GameWideStrikeHttp` | два действия, ключи как у удара |

## Acceptance G19

- Один атакующий и несколько защит. У каждой цели свой итог в `targetResults[]`.
- Клиент присылает решения и ожидаемые версии. Урон и успех во входе отвергаются. В ответе у принятой цели оба поля `null`. Числа — G20.
- Отказ одной цели виден в её записи и не отменяет уже посчитанный итог другой. Свёртки в один успех нет.
- Если исход пишет лист, версии целей и листы меняются в одной транзакции. Персонаж — порт G10, NPC — `npc.version` существующей строки. В этом заходе список операций пуст, версии листа те же.
- Повтор ключа эффект второй раз не применяет. `endBattle` сессию не гасит.
- `N → 1`, `N → N`, инициатива, DOT, каст и сцена не появляются. Проверка G18 не заменяется второй моделью броска. G20 не появляется.
- Character не импортирует Game.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G19; [`game-system.md`](game-system.md); [`game-plan-13.md`](game-plan-13.md); [`game-plan-18.md`](game-plan-18.md); [`game-plan-17.md`](game-plan-17.md); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md).
