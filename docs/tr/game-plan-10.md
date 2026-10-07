# План Game 10 — порт мутации actual

**Статус:** порт мутации actual в PHP сделан, 2026-10-04. `BACKEND_OPEN`. Suite `character` по `CharacterActualMutationMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G10. Узкий шов C9 — [`character-roadmap.md`](character-roadmap.md): decay, DOT и каст этим заходом не делаются. Лист — [`character-system.md`](character-system.md). Чтение actual — [`game-plan-03.md`](game-plan-03.md): Game берёт лист через `ICharacters::get` и в `character` не пишет. NPC — [`game-plan-06.md`](game-plan-06.md): `npc.version` и `npc.actual_version` пишет Game сам. Индекс — [`TR.md`](TR.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход, которого у G3 не было. Появляется порт Character, который меняет уже сохранённый `actualCharacter` типизированной операцией и ожидаемой `actual_version`. Устаревшая версия лист не пишет. Game полный лист в свои таблицы не кладёт.

Зависимость шага — G3 и уже существующие CAS-методы `ICharacters`. G6 этот порт не вызывает. G11 и G13 этим планом не проектируются: общая транзакция мутации листа и состояния Game появляется только там.

## Сверка G1–G9 и Character

- Строка `character` уже хранит `choices`, `sheet` и `actual_version`. Новой колонки и новой таблицы нет.
- `ICharacters` — шесть методов записи и чтения. Седьмой сюда не добавляется. Запись патча идёт через уже существующий `replacePayload`.
- `CharacterPortFactory` — пять публичных методов: `create`, `createSave`, `createMigration`, `createSheetEngine`, `createRead`. Шестой `createActualMutation` влезает в потолок десять. Эти пять не растут.
- `module.config.php` Character уже резолвит `IGameContainer` для `character.migrate`. Новый порт второй такой резолв не добавляет.
- `CharacterSave::update` собирает лист из choices (`builtUpdate` → сборка) и пишет производный `sheet` через `CharacterSheetDocument`. Этот путь патч не вызывает и сам не переписывается.
- Сохранённый `sheet` — не копия choices. `CharacterSheetDocument::build` пишет только `abilityLevels`, `racialAbilityCodes`, `osSurchargeTotal`, `equippedModifiers`, `coveredPaths`, `characteristicPurchases`, `characteristicPurchaseOs`, `active`, `money`. Ключей `resources`, `states` и `itemId` в PHP нет.
- Строка инвентаря живёт в `choices`. Белый список `CharacterSaveKeys::INVENTORY`: `ruleCode`, `custom`, `quantity`, `equipped`, `modifiers`, `durabilityLeft`, `note`. Поля `itemId` нет. `quantity` и `equipped` в `sheet` отдельными ключами не пишутся. `equipped` меняет производный `sheet.equippedModifiers`, `quantity` — нет.
- `ICharacterSheetEngines` — порт сборки без записи. Его зовёт Game для NPC. Порт мутации его не вызывает и NPC через него не пишет.
- `IGameMemberships` читает actual и пишет snapshot, бонус и статус строки. В `character` не пишет. Этот заход его не вызывает: `reviewState` по-прежнему считается при чтении G3, когда actual уже другой.
- `game_npc.version` и `game_npc.actual_version` этим портом не читаются и не пишутся. CAS NPC остаётся в Game.
- Character модуль Game не импортирует. Нового ребра DAG нет.

## Зафиксировано (модель и права)

**Ход записи.** Один список операций и одна ожидаемая `actual_version`.

1. `ICharacters::get`.
2. `expectedActualVersion` не равна `actual_version` — `CHARACTER_CONFLICT`, в деталях `currentVersion`, строка прежняя.
3. Операции применяются к копии сохранённых `choices`. Тело клиента не становится новым листом.
4. Срез — `ICharacterRuleSlices::get(spaceId, rulesRevision)` строки, как `CharacterSheets::acceptsStoredChoices`. Мир выключен — уже существующий `CharacterInvalidException`. Нет ревизии — уже существующий `CharacterNotFoundException`. Документ не проходит ту же проверку формы, что `CharacterSheets::storedShape`: `name` и `raceCode` — строки, четыре списка — массивы, `active` отсутствует либо bool. Иначе `assemble` не вызвать, это `CHARACTER_INVALID`, без записи. Сырой массив в `ICharacterSheets::validate` не передаётся: метод принимает `CharacterRuleSlice` и `CharacterChoices`. Модель собирает `CharacterChoiceAssembler::assemble` из этих ключей. Problems — `CharacterSaveRejectedException`: код `CHARACTER_INVALID`, в деталях `problems`. Строка прежняя. Затем `CharacterSheetDocument::build` заново пишет девять производных ключей. Наличные — целое `choices.money`. Ключа нет или это не целое — `CHARACTER_INVALID`, без записи. Магазинный пересчёт не вызывается. Любой ключ `sheet`, которого нет в этих девяти, копируется со старого снимка. Сейчас таких ключей нет. `normalizePayload` массив не фильтрует, чужой ключ до записи доходит.
5. Один `replacePayload` с массивом `choices` после патча, не с результатом `assemble`. `CharacterChoiceAssembler::inventory` пропускает строку без строкового `ruleCode` и в модель не кладёт `custom`, `modifiers`, `durabilityLeft`, `note`. Эти поля остаются в записанном JSON. `actual_version` увеличивается на 1.

`CharacterSaveAssembly::build` этот путь не вызывает: он переписывает `choices.money`. `character.update` не заменяется. Имя, `active` и `rules_revision` операция не меняет.

Пустой список операций — `CHARACTER_INVALID`. Операции идут по порядку по одной копии. Повтор того же `ruleCode` в списке — не две строки: вторая пишет ту же строку, в базе остаётся последнее `quantity`. Ошибка любой операции не пишет строку.

**Каталог этого захода.** Одна операция: `{ kind: setInventoryQuantity, ruleCode, quantity }`. `quantity` — целое ≥ 0. Меняется `quantity` единственной строки `choices.inventory` с этим `ruleCode`. Нет строки или две строки с одним кодом — `CHARACTER_INVALID`, без записи. Индекс массива и `itemId` адресом не являются. От количества девять производных ключей не зависят; шаг 4 всё равно их собирает, чтобы следующий `kind` не завёл второй механизм.

**Чего порт не принимает.** Полный лист, `replaceSection`, `setResourceCurrent`, смену `equipped`, готовый урон, рану, DOT, каст, decay, смену ревизии, деньги. Эскиз с уже посчитанным уроном в контракт не переносится. `equipped` и `states` — будущие `kind` того же хода, не этот заход: ключа `states` в actual нет, а `equipped` меняет `sheet.equippedModifiers`. `choices.money` этот шаг не выбирает целью.

**Идемпотентность.** `commandId` порт не хранит. После успеха версия уже другая, повтор с прежней `actual_version` — `CHARACTER_CONFLICT`. Журнал команд и повтор без второго эффекта — G11 и G13, вместе с состоянием Game.

**Транзакция.** Запись — та же, что у `replacePayload`: одна строка `character`. Параметра внешнего gateway нет. Общая транзакция с таблицей Game этим шагом не вводится.

**Права.** Их проверяет HTTP-сценарий, не `apply`. Иначе ведущий и G11 не смогут звать тот же порт. Сценарий повторяет `CharacterSave::owned`: нет актора — `AUTH_REQUIRED`; чужой или отсутствующий id — `CHARACTER_NOT_FOUND`. `game.edit_inventory`, `game.moderate` и `game.edit_all` этот action не открывают. Игра и сессия не читаются. Активная сессия запись не запрещает и не требует. Action тонкий, как `UpdateCharacterAction`: в `handle` только вызов сценария.

`changes_pending` порт не пишет. Следующее чтение membership само увидит diff actual к snapshot.

## Что даёт этот заход

Порт `ICharacterActualMutations` и одно действие `character.applyActualPatch`. Успех меняет только названные поля actual и увеличивает `actual_version`.

## Что не закрыто

- Экономика (G11), бой (G12–G13), проекции (G14), доставка (G15).
- Decay, DOT, каст и ключ `states`. Следующий `kind` входит в тот же ход записи. Когда состояние начнёт менять производные поля, в шаг 4 передаётся уже сохранённый `states`. Копирование незнакомых ключей само их не пересчитает.
- Общая транзакция мутации листа и строки Game. Её обязаны ввести G11 и G13. Разбор операций и CAS версии не меняются; у вызова добавятся внешний gateway и `commandId`. Этот порт под чужой gateway не подстраивается.
- Запись `npc.version`. Отдельного NPC-метода у порта нет.
- `commandId`, outbox, `CharacterChanged`, SSE.
- Правка actual ведущим (`game.edit_inventory`).
- Пересчёт `reviewState` в момент записи. Строка `game_character` не читается и не пишется.
- Vue поверх этого PHP.

## Точки кода G1–G9 и Character

Старые `game-plan-01.md`–`game-plan-09.md` не переписываются. Классы Game не меняются: порту не нужна новая колонка Game, новый метод `IGames` и новый вызов из membership, сессии, NPC или летописи.

Без этих точек Character порт не к чему привязать:

- `CharacterPortFactory`. Новый метод `createActualMutation`. Пять старых методов не меняются. `ICharacters` не растёт.
- `module.config.php` модуля Character. Новый `ports` и маршрут `character.applyActualPatch` зовут этот сборщик. Старые action не переименовываются.

`Characters::replacePayload` вызывается как есть. `CharacterSave`, `CharacterMigration`, `CharacterSheetEngine` и `ICharacterSessionParticipants` не меняются и из порта не вызываются. Импорта Game нет.

## Модуль

Порт живёт в `Roleplay/Character`, `Interface/Service/ICharacterActualMutations`. Сценарий — `Service/` Character. Game этот класс не наследует и не вызывает в этом заходе. `events` остаётся `[]`.

**DAG:** как после G3. Game по-прежнему читает Character. Новый порт Game не импортирует. Уже существующий резолв `IGameContainer` в `character.migrate` не расширяется.

Ошибки прежние для Character: `CHARACTER_INVALID`, `CHARACTER_NOT_FOUND`, `CHARACTER_CONFLICT`, `AUTH_REQUIRED`, `INVALID_PARAMS`. `GAME_*` этому действию не принадлежат.

| Источник | Лист |
|---|---|
| нет актора | `AUTH_REQUIRED` |
| нет строки или она не этого владельца | `CHARACTER_NOT_FOUND` |
| `expectedActualVersion` не равен `actual_version` | `CHARACTER_CONFLICT`, лист прежний |
| пустые операции; `kind` вне каталога; нет строки; два `ruleCode`; `quantity` не целое ≥ 0; `choices.money` не целое | `CHARACTER_INVALID`, лист прежний |
| `validate` вернул problems | `CHARACTER_INVALID`, в деталях `problems`, как `CharacterSaveRejectedException` |
| тело не того типа; лишний ключ верхнего уровня | `INVALID_PARAMS` |

## Таблицы

Новой карты нет. Новый установщик не заводится. `GameModuleSetup` и схемы Game не меняются.

Пишется существующая строка `character`: те же `choices` и `sheet` после точечной замены, `actual_version + 1`. `game_character`, `game_npc`, `game_session` и летопись не открываются.

## Фасад

`ICharacterActualMutations`. Не метод `ICharacters` и не метод `IGames`.

Один публичный метод сверх конструктора класса реализации:

`apply(int $characterId, int $expectedActualVersion, array $operations): CharacterRecord`

Класс читает строку через `ICharacters::get`. Версия не совпала — `CharacterConflictException` до записи. В деталях уже есть `currentVersion` (`CharacterConflictException::getErrorDetails`). Дальше ход из раздела «Зафиксировано». Сборщик даёт ему `ICharacterRuleSlices` и `ICharacterSheets` тем же способом, что `createSave`. `CharacterChoiceAssembler` и `CharacterSheetDocument` создаются рядом, это не новые порты. Репозиторий пишет `choices`, `sheet`, `actual_version + 1` и `updated_at`. Конфликт внутри `replacePayload` наружу тот же `CHARACTER_CONFLICT`.

Операция не создаёт ключ, которого в документе нет. `states` этим заходом не появляется.

Класс не принимает `gameId`, `npcId`, полный лист и готовый урон. Возврат — `CharacterRecord` после записи. В состояние Game этот record не копируется: вызывающего в Game на этом шаге нет.

`CharacterActualMutationPortFactory` не нужен: метод один, он встаёт в `CharacterPortFactory` шестым.

## HTTP

Свой DTO, `csrf` true. Сценарий HTTP проверяет актора и владельца, затем зовёт `apply`. Порт владельца не знает.

| Action | Кто | Тело | Успех |
|---|---|---|---|
| `character.applyActualPatch` | владелец персонажа | `{ characterId, expectedActualVersion, operations }` | `{ characterId, actualVersion }` |

`operations` — массив объектов `{ kind, ...поля операции }`. Верхний лишний ключ тела — `INVALID_PARAMS`. Внутри элемента лишний ключ и чужой `kind` — `CHARACTER_INVALID`.

Ответ не содержит `choices` и `sheet`. Клиент, которому нужен лист, читает его отдельным уже существующим действием. Игра в теле не передаётся.

Дефекты эскиза `draft-front_1.2ds`, в фасад не копировать:

- `MockGameCombatCommandService` перед записью сам считает остаток очков действия и кладёт в патч уже готовое `current`, а для защиты собирает `replaceSection` секции `states` с готовой раной и эффектом `damage`. В PHP-листе нет ни `resources`, ни `states`. Контракт G10 не принимает готовый урон и замену секции.
- `MockGameRuntimeMutationService` тем же патчем пишет и персонажа, и NPC и помнит `commandId` ради повтора. Этот порт NPC не пишет и `commandId` не хранит. Повтор с прежней версией — конфликт CAS.
- Редакторский `CharacterPatch` несёт `setResourceCurrent` по `ruleCode`, инвентарь по `itemId`, `setField` на деньги и имя и `replaceSection` целых массивов. Этих ключей в сохранённом actual PHP нет либо это целый фрагмент листа. Обычный `character.update` остаётся путём сборки из choices и этим планом не заменяется.

## Тесты

Suite `character`. Suite `game` не расширяется. Фикстура Character таблицы Game не ставит, поэтому тест не считает строки `game_npc`. Граница «Game не пишет свой лист» — у порта нет репозитория Game, а `replacePayload` обновляет только `choices`, `sheet`, `actual_version` и `updated_at` строки `character`.

Mysql CAS. `setInventoryQuantity` с верным `ruleCode` и верной `expectedActualVersion` меняет `quantity` этой строки и увеличивает `actual_version` на 1. Девять ключей `sheet` совпадают с `CharacterSheetDocument` от choices после патча. `choices.money` прежний. Та же команда со старой версией — `CHARACTER_CONFLICT`, в деталях `currentVersion`, оба JSON и счётчик прежние. Две операции по разным `ruleCode` пишутся вместе; вторая с неизвестным кодом не оставляет первую.

Mysql границ. `CharacterSaveAssembly::build` не вызывается. Чужой ключ в старом `sheet` после успеха на месте. Чужой владелец — `CHARACTER_NOT_FOUND`, как `CharacterSave::owned`. Нет строки инвентаря, две строки с одним `ruleCode`, отрицательное `quantity`, `replaceSection` и поле урона — `CHARACTER_INVALID`, строка прежняя.

## Todo

- [x] **port** — `ICharacterActualMutations`. Ход: CAS, `setInventoryQuantity` по `ruleCode`, `validate`, девять ключей `CharacterSheetDocument` из choices после патча, копия прочих ключей `sheet`, один `replacePayload`. Не `CharacterSaveAssembly::build`.
- [x] **http** — `character.applyActualPatch`. Ответ — id и новая `actual_version`, без листа.
- [x] **gates** — phpunit suite `character`. Stale `actual_version` не пишет лист. Строки Game не меняются. Character не импортирует Game.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| порт | Character `Interface/Service/` `Service/` | применить операции к сохранённому actual |
| запись | Character `Service/Characters` | уже существующий `replacePayload`, без нового метода |
| HTTP | Character `Action/` | одно действие владельца |
| сборка | Character `Service/CharacterPortFactory` | шестой метод, старые пять не меняются |
| Game G1–G9 | — | не меняется |

## Acceptance G10

- Stale `actual_version` не пишет `choices`, `sheet` и счётчик.
- Успех пишет только поля названных операций и увеличивает `actual_version` на 1.
- Вход — не полный лист. Производный `sheet` собирается из choices после патча. `CharacterSaveAssembly::build` не переписывает наличные. Незнакомый ключ `sheet` не стирается.
- Game не кладёт лист в `game_character`, `game_npc` и прочие свои таблицы: этот заход их не открывает.
- `npc.version` и `npc.actual_version` не меняются.
- Character не импортирует Game. Общей транзакции с состоянием Game нет.
- Экономика, бой, проекции и доставка этим заходом не спроектированы.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G10; [`character-roadmap.md`](character-roadmap.md) C9; [`character-system.md`](character-system.md); [`game-plan-03.md`](game-plan-03.md); [`game-plan-06.md`](game-plan-06.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).
