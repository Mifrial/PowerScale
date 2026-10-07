# План Game 13 — один удар

**Статус:** один удар в PHP сделан, 2026-10-04. `BACKEND_OPEN`. Suite `game` по `GameStrikeMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G13. Канон — [`game-system.md`](game-system.md): обычный удар `1 → 1`; клиент шлёт решения и ожидаемые версии; урон считает сервер; authoritative effect пишется в actual в момент применения; повтор команды не повторяет мутацию; ошибка версии не оставляет частичной записи. Бой — [`game-plan-12.md`](game-plan-12.md): `battleId`, состав и CAS версии боя уже есть; `endBattle` сессию не гасит; лист тот шаг не писал; порт G10 не вызывался. Порт — [`game-plan-10.md`](game-plan-10.md): typed patch и `expected actual_version`; каталог — `setInventoryQuantity`, `setMoney`, `putInventoryQuantity`. NPC — [`game-plan-06.md`](game-plan-06.md): эффект пишет `npc.version` уже существующей строки; CAS делает Game. Индекс — [`TR.md`](TR.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. В уже открытом бою появляется сценарий `1 → 1`: решение атаки, ответ защиты, серверный исход. Клиент шлёт выборы и ожидаемые версии. Готовый урон, стоимость хода и полный лист во входе не принимаются: расчёт фронта — превью, не источник истины. Повтор команды с тем же ключом исход второй раз не применяет. Если исход называет операции листа, версия боя и лист меняются в одной транзакции: персонаж через порт G10, NPC через `npc.version` существующей строки. `endBattle` сессию не гасит.

Зависимость шага — G12. `1 → N` и `N → 1` не входят. DOT, каст, движение, инициатива, battleground и `ISpatialResolver` не входят. Проекции (G14) и доставка (G15) не проектируются. Экономика G11 не переписывается.

## Сверка G6, G10, G11, G12

- Строка боя и состав уже есть. Версия боя со старта равна 1. `GameBattleRepository::advanceVersion` сверяет ожидаемую версию до `update` и увеличивает её на 1. Чужая версия бросает `GameBattleConflictException` и строку не пишет.
- `endBattle` удаляет состав и строку этого боя. Сессию и остальные бои не снимает. Stop сессии снимает бои и `game_battle_command`, затем строку сессии. `targetStatus` не принимается.
- `game_session.state_version` боем не сверяется и не увеличивается.
- Каталог порта: `setInventoryQuantity`, `setMoney`, `putInventoryQuantity`. Ключа ресурса, раны и `states` в choices нет. `CharacterChoiceAssembler` пишет имя, описания, возраст, лимиты, расу, закупки, способности, инвентарь, custom rules и `active`. `assertStoredShape` проверяет `name`, `raceCode`, четыре списка и `active`.
- `CharacterRepository::writeGuarded` при уже открытой транзакции соединения второй begin не делает. Подписи `apply` и `replacePayload` не меняются.
- `GameNpcRepository::replaceVersion` пишет `npc.version` предикатом `npc.actual_version` и при расхождении бросает `GameEconomyConflictException`.
- `ICharacterRuleSlices::get` отдаёт `CharacterRuleSlice`. Живое правило ищется `findLive`. У `CharacterResolvedRule::getSpec` спецификация правила. У предмета это `ItemSpec`: `getWeapon` и `getShield`, у блока — `getWeaponProfiles(): WeaponProfile[]`. У профиля есть `getType()` (`strike`, `throw`, `shoot`) и урон. Game этот срез сейчас не читает.
- `GameBattles` уже держит шесть зависимостей. Удар в этот конструктор не встаёт.
- Character модуль Game не импортирует.
- Повтор команды боя сравнивает тело через `sortKeys` и `json_encode`.

## Зафиксировано (модель и права)

**Сценарий.** Две команды одного боя. Атака открывает один незакрытый удар: один атакующий и один защитник, оба уже в составе этого `battleId`. Защита закрывает этот удар. Второй незакрытый удар того же боя — `GAME_INVALID`. Списка целей нет: лишний ключ — `INVALID_PARAMS`. Широкого удара и `targetResults` нет.

Атака лист не меняет. `endBattle` строку `game_strike` не удаляет: у `battle_id` нет ссылки, ход конца боя не меняется. Незакрытый удар остаётся до stop и эффект задним числом не дописывает. Закрытие по уже снятому `battleId` — `GAME_NOT_FOUND`. Уже записанный лист `endBattle` и stop не откатывают.

**Выборы, не расчёт.** Тело атаки: атакующий, защитник, `actionRuleCode`, `itemRuleCode`, `profileType` (`strike` | `throw` | `shoot`), `profileIndex` — целое ≥ 0. Тело защиты: `reaction` (`ignore` | `dodge` | `block`) и необязательный `blockItemRuleCode`. Во входе нет урона, `remaining`, `damageTypeCode`, `actionPointCost`, `resourceRuleCode` и полного листа. Имя ресурса и величину эффекта клиент не выбирает.

**Проверка выбора.** До записи Game читает срез через `ICharacterRuleSlices::get(GameRecord::getSpaceId(), GameRecord::getRulesRevision())`. `actionRuleCode` и `itemRuleCode` обязаны быть живыми правилами этой ревизии. `getSpec()` предмета обязан быть `ItemSpec`. `null` и `isSpecBroken()` — `GAME_INVALID`. `profileIndex` — смещение в списке `WeaponBlock::getWeaponProfiles()` у `ItemSpec::getWeapon()`, не поле `WeaponProfile`: такого поля нет. `ShieldBlock` тоже хранит `weapon_profiles`, и разбор читает их без блока `weapon`. Этот шаг тот список не смотрит: ударный профиль щита, если предмет ещё и оружие, лежит в `getWeapon()`. Щит без блока оружия профиля атаки здесь не даёт. Элемент с этим смещением обязан иметь `getType()`, равный `profileType`. Иначе `GAME_INVALID`, удар не открывается. `blockItemRuleCode`, если он есть, — живой `ItemSpec`, у которого `getWeapon()` или `getShield()` не `null`; нет — `GAME_INVALID`, удар остаётся открытым. Формулу действия этот шаг не исполняет: код действия только проверяется как живое правило.

**Исход.** Отдельный расчёт в Game, не метод порта и не код Vue. Вход — уже проверенные выборы и actual атакующего и защитника. Выход — список операций каталога порта, который сервер подставляет сам. Пустой список значит, что лист не меняется. Этот заход возвращает пустой список: в choices нет поля, куда писать урон, ресурс или рану, а фиктивный `kind` и константа «минус один» не заводятся. Следующий расчёт с тем же входом и тем же выходом сможет вернуть операции, не меняя HTTP и не заводя второй писатель actual.

Промах и попадание этого захода лист не различают записью: оба закрывают удар. Версия боя увеличивается, потому что удар закрыт. Версия листа увеличивается только если список операций не пуст. Повтор отличает сохранённый итог команды, а не повторная запись того же `current`.

**Запись листа, когда список не пуст.** Тот же commit, что версия боя. Персонаж — `ICharacterActualMutations::apply`. NPC — `applyToDocument`, затем `GameNpcRepository::replaceVersion` уже лежащей строки. Нет строки NPC — `GAME_NOT_FOUND` до insert. Новый `kind` добавляется в тот же разбор порта только вместе с полем листа, которое он меняет. Этот заход порт не вызывает: список пуст, и тест это проверяет.

**CAS.** Атака сверяет версию боя. Защита сверяет версию боя и ожидаемую версию листа защитника до записи, даже когда список операций пуст. Персонаж — `ICharacters::get` и `CharacterRecord::getActualVersion()`. NPC — `GameNpcRecord::getActualVersion()`. Чужая версия — `GAME_CONFLICT`, удар не закрывается, лист прежний. Версия сессии не участвует.

**Идемпотентность.** Свой журнал `game_strike_command`, не `game_battle_command`. Ключ уникален в паре с `gameId`. Повтор с тем же телом возвращает сохранённый итог и ничего не пишет. Тот же ключ с другим телом — `GAME_CONFLICT`. Пустой ключ — `GAME_INVALID`.

**Ход записи.** До транзакции: видимость, повтор ключа, `completed`, право писать только на новом проходе.

1. Карточка скрыта — `GAME_NOT_FOUND`, даже если ключ уже есть.
2. Есть строка команды с этим ключом — сверка тела и возврат итога, без `AUTH_DENIED`.
3. Игра `completed` или нет сессии — `GAME_INVALID`.
4. Нет `game.edit` этой игры и нет `game.edit_all` — `AUTH_DENIED`. Проверка та же, что у экономики: владелец, `gm` или `editAll`.
5. `battleId` этой сессии. Атакующий и защитник — разные участники состава. Состав читается новым публичным методом `GameBattleRepository`: сейчас снаружи есть только `countParticipants`, а `kind` и `subject_id` читают приватные методы. Нет в составе — `GAME_NOT_FOUND`. Коды и профиль не прошли проверку среза — `GAME_INVALID`.
6. Атака: открытого удара нет, версия боя совпала. Иначе `GAME_INVALID` или `GAME_CONFLICT`.
7. Защита: открытый удар есть. Тело защитника не называет. Нет открытого удара — `GAME_INVALID`. Версия боя или листа не совпала — `GAME_CONFLICT`.
8. Одна транзакция `ISmartTableGateway`. Атака вставляет строку удара и вызывает `advanceVersion`. Защита считает список операций. Список пуст — только закрытие удара и `advanceVersion`. Список не пуст — сначала порт или `replaceVersion`, затем закрытие и `advanceVersion`. Строка команды в том же commit. Исключение откатывает транзакцию.

**Чего шаг не делает.** Не принимает готовый урон и полный лист. Не пишет `choices.resources`. Не вызывает порт G10, пока расчёт не вернул операции. Не создаёт NPC. Не считает `1 → N`, DOT, каст и движение. Не пишет сцену. Не шлёт событие. `endBattle` сессию не останавливает.

**Права.** Атака и защита — владелец, `gm` или `game.edit_all`. `player` удар не объявляет. Новый ключ прав не заводится.

## Что даёт этот заход

Фасад `IGameStrikes` и действия `game.declareStrike`, `game.resolveStrike`. Атака возвращает `battleId`, `strikeId` и новую версию боя. Защита возвращает то же и `sheetVersion`: `null`, пока лист не писался. Сессия остаётся запущенной. Выборы сохраняются на строке удара и проверяются по `WeaponProfile` ревизии игры.

## Что не закрыто

- Непустой список операций и поле листа под урон, ресурс или рану. Следующий расчёт пишет их тем же коммитом через уже стоящий вызов порта, без второго механизма.
- Формула удара, бросок, увечье, истощение, `states`.
- `1 → N`, `N → 1`, сцена, движение, DOT, каст, инициатива.
- Проекции (G14) и доставка (G15).
- Vue поверх этого PHP.

## Точки кода G1–G12

Старые планы не переписываются. Character не импортирует Game. `CharacterActualMutations`, `GameBattles`, `GamePortFactory` и `writeGuarded` не меняются: писать в лист этим заходом нечего, а внешняя транзакция уже подхватывается.

- `GameBattleRepository`. Публичное чтение состава: `kind` и `subject_id`. Без него фасад не отличает участника боя от постороннего. Запись состава, `advanceVersion` и `delete` не меняются.
- `GameBattleCleanup::deleteWithSession`. В уже открытой транзакции, до `deleteSession`, удаляются строки удара и команды удара этой сессии. Иначе restrict на сессию не даёт снять стол, а ключ переживает в следующую сессию. `endBattle` эти строки не удаляет. `GameSessions` седьмую зависимость не получает.
- `GameMysqlFixture`. Отдельный `(new GameStrikeSchema(...))->install()`, как `GameBattleSchema`. `dropGameTables` удаляет новые таблицы до `GameSessionTable`. `GameModuleSetup::getTableClasses` сливает `GameStrikeSchema::getTableClasses()`.

Остальной код G1–G12 не меняется.

## Модуль

Сценарий в `Roleplay/Game`, `Interface/Service/IGameStrikes`. Сборщик — `GameStrikePortFactory` с `create` и `createHttp`. Публичных `create*` у `GamePortFactory` не прибавляется. `events` остаётся `[]`. `module.config.php` регистрирует порт и два action. `GameContainer` список портов не хранит.

**DAG:** Game → Character через уже существующие `ICharacterActualMutations`, `ICharacterRuleSlices` и `ICharacters`. Character Game не импортирует.

Расчёт — класс в `Service/` Game. Не порт и не action. Его конструктор получает `ICharacterRuleSlices` и `ICharacters`: срез для профиля и `getActualVersion()` защитника-персонажа. Фасад получает уже собранный расчёт и сам в локатор не ходит. Конструктор фасада, не больше шести: шлюз, `IGames`, `GameCardAccess`, `GameSessionRepository`, `ICharacterActualMutations`, этот расчёт. Седьмой аргумент нельзя: у `GameEconomy` их уже семь, и стандарт это запрещает. Репозитории боя, NPC и участников фасад создаёт сам. Версию NPC читает созданный рядом `GameNpcRepository`.

Ошибки: `GAME_INVALID`, `GAME_NOT_FOUND`, `GAME_CONFLICT`, `AUTH_REQUIRED`, `AUTH_DENIED`, `INVALID_PARAMS`. Версия боя — `GameBattleConflictException`. Версия NPC — уже `GameEconomyConflictException`. Оба кода `GAME_CONFLICT`, деталь `currentVersion`. У конфликта ключа этой детали нет. Когда порт всё же бросит `CharacterConflictException` или `CharacterInvalidException`, фасад отдаёт `GAME_CONFLICT` или `GAME_INVALID` и не выпускает код Character. Этот заход до вызова порта не доходит.

| Источник | Код |
|---|---|
| нет актора | `AUTH_REQUIRED` |
| карточка скрыта; бой не этой сессии; участник не в составе | `GAME_NOT_FOUND` |
| нет `game.edit` и нет `game.edit_all` | `AUTH_DENIED` |
| пустой ключ; игра `completed`; сессии нет; второй открытый удар; нет открытого удара; код не живой; нет профиля; `reaction` или `profileType` вне набора; атакующий и защитник совпали | `GAME_INVALID` |
| версия боя или листа не совпала; тот же ключ с другим телом | `GAME_CONFLICT`, записи нет |
| лишний ключ; урон; `actionPointCost`; `resourceRuleCode`; список целей; полный лист | `INVALID_PARAMS` |

## Таблицы

Новая карта `GameStrikeSchema`. Колонки `game_battle`, `character` и `game_npc` не добавляются.

`game_strike`: `battle_id` без ссылки на бой; `session_id` — restrict на `GameSessionTable`; виды и id атакующего и защитника; `action_rule_code`, `item_rule_code` и `block_item_rule_code` — `StringField` длины 255, как `game_shop_position.rule_code`; `profile_type` длины 32; `profile_index`; `reaction` длины 32. `reaction` и `block_item_rule_code` не required: до защиты их нет. `open` — `BoolField`. Один открытый удар на бой держит шаг, не unique по всем историческим строкам.

`game_strike_command`: `game_id`, `session_id` restrict, `idempotency_key` длины 255, тело и итог — два `JsonField`. Пара (`game_id`, ключ) уникальна.

## Фасад

`IGameStrikes`. Не метод `IGameBattles` и не метод `ICharacters`.

- `declareStrike(..., int $battleId, string $idempotencyKey, int $expectedBattleVersion, array $attack): array`
- `resolveStrike(..., int $battleId, string $idempotencyKey, int $expectedBattleVersion, int $expectedSheetVersion, array $defense): array`

`attack` — `{ attacker, defender, actionRuleCode, itemRuleCode, profileType, profileIndex }`. `attacker` и `defender` — `{ type, id }`. `defense` — `{ reaction, blockItemRuleCode? }`.

Возврат — `{ battleId, strikeId, version, sheetVersion }`. У атаки `sheetVersion` всегда `null`. У защиты этого захода тоже `null`. Повтор ключа отдаёт тот же объект.

## HTTP

Свои DTO, `csrf` true. В `handle` только вызов сценария.

| Action | Кто | Тело | Успех |
|---|---|---|---|
| `game.declareStrike` | владелец, `gm` или `game.edit_all` | `{ gameId, battleId, idempotencyKey, expectedVersion, attack }` | `{ battleId, strikeId, version, sheetVersion: null }` |
| `game.resolveStrike` | тот же актор | `{ gameId, battleId, idempotencyKey, expectedVersion, expectedSheetVersion, defense }` | `{ battleId, strikeId, version, sheetVersion: null }` |

Дефекты эскиза `draft-front_1.2ds`, в фасад не копировать:

- `GameCombatCommand.action.actionPointCost` — уже посчитанное число. В PHP его нет.
- `GameCombatEffect` с готовым `damage` — превью. Сервер его не принимает и сам в этом заходе лист не вычитает.
- `commandId` эскиза — в PHP `idempotencyKey`.
- Один `submitCombatCommand` с `processId` и `offerId`. В PHP два action и одна строка `game_strike`.
- Широкий удар и `targetResults[]` этим шагом не открываются.
- `GameCombatOverlay` тащил полный лист. В строку удара лист не пишется.

## Тесты

Suite `game`. Suite `character` не расширяется: каталог порта не меняется.

Mysql выбора. Атака с живым предметом и существующим `WeaponProfile` этого `profileType` и `profileIndex` пишет одну строку, хранит коды и индекс, увеличивает версию боя на 1, не меняет `actual_version` и `npc.actual_version`. Нет правила, чужой `profileType` или индекс вне списка — `GAME_INVALID`, строки удара нет. `actionPointCost` и урон в теле — `INVALID_PARAMS`.

Mysql защиты. `ignore`, `dodge` и `block` закрывают удар и увеличивают версию боя. `sheetVersion` в ответе `null`. Версии листа те же. Вызова `ICharacterActualMutations::apply` и `replaceVersion` нет. `block` без живого предмета блока — `GAME_INVALID`, удар открыт, версия боя прежняя.

Mysql отката. Чужая версия боя или листа на защите не закрывает удар и не пишет лист. Повтор того же ключа возвращает прежний итог и вторую строку не создаёт. Тот же ключ с другим телом — `GAME_CONFLICT`.

Mysql сессии. `endBattle` снимает бой и сессию не гасит. `current` листа тот же, потому что лист не писался. Stop удаляет строки удара и команды этой сессии. `targetStatus` — `INVALID_PARAMS`. Тот же ключ в новой сессии — новая команда. Action широкого удара и сцены нет.

## Todo

- [x] **schema** — `game_strike` с кодами выбора и профилем, `game_strike_command`.
- [x] **cleanup** — строки удара снимаются до сессии. Конструктор `GameSessions` не растёт.
- [x] **resolve** — проверка `actionRuleCode`, `itemRuleCode` и `WeaponProfile` по `ICharacterRuleSlices`. Выход — пустой список операций. Порт не вызывается.
- [x] **facade** — атака и защита, CAS версии боя и листа, одна транзакция. Запись листа только при непустом списке.
- [x] **http** — два action без урона и без `actionPointCost`.
- [x] **gates** — phpunit. Повтор не пишет лист второй раз. Чужая версия не закрывает удар. Порт G10 не вызывается. Сцена и широкий удар не появляются.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| удар | Game `Interface/Service/` `Service/` | выборы, CAS, транзакция |
| расчёт | Game `Service/` | срез правил → список операций порта |
| снятие сессии | Game `Service/GameBattleCleanup` | строки удара до сессии |
| сборка | Game `Service/GameStrikePortFactory` | `create` и `createHttp` |
| таблицы | Game `Schema/` `Repository/` | удар и ключ |
| HTTP | Game `Action/` | два действия |

## Acceptance G13

- Клиент присылает выборы и версии. Урон, стоимость хода и имя ресурса во входе отвергаются.
- Коды и профиль проверяются по живому правилу ревизии. Чужой профиль удар не открывает.
- Расчёт возвращает список операций порта. В этом заходе список пуст, порт не вызывается, версии листа те же.
- Место записи листа — тот же commit, что версия боя. Персонаж пойдёт через `apply`, NPC через `replaceVersion`. Второй писатель не заводится.
- Повтор ключа не повторяет закрытие. Чужая версия не оставляет закрытый удар.
- `endBattle` сессию не гасит.
- Character не импортирует Game.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G13; [`game-system.md`](game-system.md); [`game-plan-12.md`](game-plan-12.md); [`game-plan-10.md`](game-plan-10.md); [`game-plan-06.md`](game-plan-06.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).
