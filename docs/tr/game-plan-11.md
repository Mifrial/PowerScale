# План Game 11 — экономика

**Статус:** экономика в PHP сделана, 2026-10-04. `BACKEND_OPEN`. Suite `game` по `GameEconomyMysqlTest` и suite `character` по `CharacterActualMutationMysqlTest` зелёные. Нарезка — [`game-roadmap.md`](game-roadmap.md) G11. Канон операции — [`game-system.md`](game-system.md): деньги, магазин, шесть видов и атомарность. Порт листа — [`game-plan-10.md`](game-plan-10.md). NPC — [`game-plan-06.md`](game-plan-06.md): `npc.version` и `npc.actual_version` пишет Game, создание NPC побочным эффектом не является. Индекс — [`TR.md`](TR.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. Появляется typed `EconomyOperation`: buy, sell, discard, transfer_item, transfer_money, loot. У операции есть idempotency key и ожидаемые версии. Обычная разрешённая операция не ждёт ручного approve. Конфликт версии не оставляет частичной записи. Повтор с тем же ключом не применяет эффект второй раз. Выдача в NPC пишет `npc.version` уже существующей строки и строку NPC не создаёт.

Зависимость шага — G6 и G10. Бой (G12–G13), проекции (G14) и доставка (G15) этим планом не проектируются.

## Сверка G1–G10

- Строка `character` хранит `choices.money` целым и строки инвентаря в `choices.inventory`. Белый список `CharacterSaveKeys::INVENTORY` прежний. Поля `itemId` нет.
- Каталог порта — одна операция `setInventoryQuantity`. Деньги, `equipped` и `states` порт не принимает. Полный лист и повторный build из устаревших choices — нет.
- Ход G10: `get`, сверка `actual_version`, операции по копии `choices`, `validate`, девять ключей `CharacterSheetDocument` из choices после патча, копия прочих ключей `sheet`, один `replacePayload`. `CharacterSaveAssembly::build` наличные переписывает и этим путём не вызывается.
- `ICharacterActualMutations::apply` пишет строку `character`. Разбор документа — второй метод того же порта, его зовут и `apply`, и экономика для NPC. `CharacterPortFactory` не растёт: оба метода в уже существующем классе.
- `GamePortFactory` уже имеет десять публичных `create*`. Одиннадцатый метод в него не встаёт. Сборка экономики — отдельный класс, как `GameChroniclePortFactory`.
- `setInventoryQuantity` меняет `quantity` единственной уже существующей строки. Нет строки — `CHARACTER_INVALID`, лист не пишется. Этот контракт и HTTP `character.applyActualPatch` не меняются: опечатка кода не создаёт строку.
- Строка инвентаря без строкового `ruleCode` в модель не попадает (`CharacterChoiceAssembler::inventory`) и адресом операции не является.
- `CharacterRepository::writeGuarded` открывает транзакцию шлюза, а если транзакция соединения уже открыта — выполняет запись внутри неё (`SmartTableGateway::transaction` при `transactionLevel() > 0` вызывает работу без второго begin). Подписи `apply` и `replacePayload` из-за этого не меняются. Параметра gateway и `commandId` у порта нет и не появляется: ключ живёт в строке операции Game.
- `GameNpcRepository::save` увеличивает `actual_version` без предиката в update. Экономика его не вызывает.
- Создание NPC — `game.createNpc`. Оно пишет минимальный лист и `actual_version = 1`. Операция экономики этот action не вызывает.
- `game.updateNpc` и `game.translateNpc` ждут клиентский лист или ремап ревизии. Точечная выдача их не вызывает.
- Character модуль Game не импортирует. Game вызывает порт Character. Нового ребра DAG нет.
- Права G2: владелец и `gm` имеют `game.edit` и `game.moderate`; `player` из роли ничего не получает. Глобальный обход — `game.edit_all`. Ключа `game.edit_inventory` в `GamePermissionKeys` нет. Этот заход ключ не заводит.
- Overlay, бой и летопись экономика не открывает.

## Зафиксировано (модель и права)

**Операция.** Одно тело, один `idempotencyKey`, один список частей. Часть — один из шести видов. Отдельного вида «обмен» и отдельного типа склада нет: обмен — две или больше частей `transfer_item` и `transfer_money` в этом списке, одна транзакция, один ключ.

`quantity` и `amount` части — положительная дельта, не новый итог. Ноль и отрицательное — `GAME_INVALID`. Цена в теле не приходит: buy и sell берут её из позиции. Клиентский итог баланса сервер не принимает. Порт получает уже абсолютные `quantity` и `amount`.

| `kind` | Поля части | Эффект |
|---|---|---|
| `buy` | `characterId`, `ruleCode`, `quantity` | деньги персонажа уменьшаются на `buyPrice * quantity`, его инвентарь растёт на `quantity`, остаток позиции падает на `quantity` |
| `sell` | `characterId`, `ruleCode`, `quantity` | инвентарь падает, деньги растут на `sellPrice * quantity`, остаток позиции растёт |
| `discard` | `characterId`, `ruleCode`, `quantity` | инвентарь падает на `quantity`; магазин и деньги не меняются |
| `transfer_item` | `from`, `to`, `ruleCode`, `quantity` | `from` и `to` — `{ type: character \| npc, id }` |
| `transfer_money` | `from`, `to`, `amount` | те же `from` и `to`; лист предмета не меняется |
| `loot` | либо `ruleCode`, `quantity`, `to`, либо `shares` | `to` — `{ type: character \| npc \| nowhere, id }`; `id` обязателен кроме `nowhere`. `shares` — список `{ type, id, amount }` для денег. Один предмет — один `to`. Деньги — только `shares`, без `ruleCode` |

`from` и `to` типа `character` — строка `active`. Тип `npc` — строка этой игры. Один и тот же конец в `from` и `to` — `GAME_INVALID`. В одной части не оба набора полей предмета и денег. Произведение цены и `quantity`, которое в PHP не остаётся `int`, — `GAME_INVALID` до записи: `CharacterActualMutations::moneyOf` принимает только `int`.

**Деньги.** Одно целое в минимальных единицах в `choices.money` персонажа и в том же ключе внутри `npc.version.choices`. Номиналы — форматирование клиента. `moneyBudget` и `moneyLimit` эта операция не читает и не пишет. Отрицательный баланс — отказ до записи.

**Магазин.** Набор позиций игры, не склад обмена. Позиция: `ruleCode`, цена покупки `buyPrice`, необязательная `sellPrice`, остаток `quantity`, свой `version`. Нет `sellPrice` — sell этой позиции отвергается. Создание позиции ведущим задаёт цены и остаток явно. Подстановка `ItemSpec.cost_gm` в момент buy не выполняется: в сохранённой позиции уже лежит число. Предмет вне набора купить и продать нельзя. Отдельного порта «это предмет ревизии» нет: `CharacterInputChecks::checkInventory` отклоняет только tombstone. Код позиции хранится строкой; при записи персонажа tombstone даёт `CHARACTER_INVALID` с `problems`, транзакция пустая.

**Loot.** Часть `loot` переносит предмет одному получателю (`character`, `npc`, `nowhere`) или деньги по явным долям. Доля «вникуда» фиксируется в строке операции и лист не меняет. Интерес игроков, статус prepared/available и идентификатор лута эскиза в контракт не входят. Запаса лута отдельной таблицей нет: состав выдачи лежит в теле операции.

**Идемпотентность.** Ключ уникален в паре с `gameId`, через `defineUniqueKeys`, как пара `game_member`. Первый успешный commit пишет строку операции с разобранным телом и итоговыми версиями. Повтор с тем же ключом сравнивает это разобранное тело, не сырую JSON-строку. Совпало — возвращает сохранённый итог и строки листа, NPC, магазина не меняет. Тот же ключ с другим телом — `GAME_CONFLICT`, без второй записи. Пустой ключ — `GAME_INVALID`.

**Ход записи.** Одна транзакция `ISmartTableGateway`.

1. Нет строки операции с этим ключом. Иначе сверка тела и возврат сохранённого итога, без записи.
2. Игра не `completed`. Иначе `GAME_INVALID`.
3. Права части. Не сошлись — `AUTH_DENIED` или `GAME_NOT_FOUND` по фильтру карточки, как у соседних мутаций Game. Записи нет.
4. Сверка каждой ожидаемой версии: `actual_version` персонажа, `npc.actual_version`, `version` позиции. Расхождение персонажа — `CHARACTER_CONFLICT`, в деталях уже есть `currentVersion`. Расхождение NPC, позиции или тот же ключ с другим телом — не `GameConflictException`: его `getErrorDetails` отдаёт `currentActualVersion` и `currentMembershipRevision` строки `game_character`. Для экономики свой класс с тем же кодом `GAME_CONFLICT` и деталью `currentVersion` несовпавшей строки. У конфликта ключа этой детали нет. Ни одна строка не меняется.
5. Расчёт новых целых: баланс, `quantity` инвентаря, остаток позиции. Не хватает денег, количества или остатка; sell без `sellPrice`; получатель NPC без строки этой игры; персонаж не в статусе `active` этой игры — `GAME_INVALID`. `submitted` и `left` целью и источником не являются. `returned` — это `reviewState`, не статус строки. И персонаж, и NPC получают один и тот же разбор документа порта: `putInventoryQuantity` и `setMoney`, не `setInventoryQuantity`. Для персонажа `apply` после разбора пишет строку через `replacePayload`. Для NPC Game подставляет возвращённые `choices` и `sheet` в уже лежащий `npc.version` и пишет строку предикатом версии. `spaceId`, `spaceCode` и `rulesRevision` внутри `npc.version` не меняются. `GameNpcs::change` и `ICharacterSheetEngines` не вызываются: `CharacterSaveAssembly::build` при `shopCreate false` подставляет `updateMoney ?? 0`, а `CharacterChoiceAssembler::inventory` не переносит `custom`, `modifiers`, `durabilityLeft`, `note`. Эти поля остаются, потому что порт пишет пропатченный массив `choices`, не результат `assemble`. `applyToDocument` не вызывает `ICharacterSheets::validate`. Иначе пустая раса `GameNpcs::seedChoices` даёт `CHARACTER_RACE` и выдача в только что созданного NPC не пишется. Tombstone проверяет сам разбор: у каждого `ruleCode` из `putInventoryQuantity` срез `ICharacterRuleSlices::get(spaceId, rulesRevision)` и `CharacterRuleSlice::hasTombstone`. Истина — `CHARACTER_INVALID`, без записи. Полный `validate` остаётся внутри `apply` после `applyToDocument`, только для строки `character`. В Game отдельной проверки tombstone нет.
6. Commit вместе со строкой операции. Исключение на любом шаге откатывает транзакцию.

Порядок частей одного тела сохраняется. Два изменения одного листа уходят одним вызовом разбора: список `setMoney` и `putInventoryQuantity`, одна прибавка версии. Одна позиция магазина за операцию получает одну прибавку `version`, даже если её тронули несколько частей.

**Каталог порта, который этому ходу не хватает.** Два новых `kind` того же хода. Второй механизм записи actual не заводится. `equipped` и `states` по-прежнему не принимаются. Полный лист порт не принимает.

- `{ kind: setMoney, amount }` — `amount` целое ≥ 0, пишется `choices.money`. В `applyToDocument` в `sheet` меняется только ключ `money` на это целое. Девять ключей заново собирает `apply`, и только для строки `character`.
- `{ kind: putInventoryQuantity, ruleCode, quantity }` — `quantity` целое ≥ 0. Нет строки с этим `ruleCode` — в конец `choices.inventory` добавляется `{ ruleCode, quantity, equipped: false }`. Одна строка — меняется только `quantity`. Две строки — `CHARACTER_INVALID`, без записи. Прочие поля уже лежащей строки не стираются. `setInventoryQuantity` остаётся прежним: нет строки — отказ. Экономика его не вызывает. Так HTTP владельца не начинает создавать предметы опечаткой кода, а buy, transfer и loot не получают второй проход «сначала вставить».

Разбор документа не пишет строку и не знает про игру:

`applyToDocument(int $spaceId, int $rulesRevision, array $choices, array $sheet, array $operations): array`

Возврат — `choices` и `sheet`. `apply` читает строку, сверяет `actual_version` и передаёт в `applyToDocument` колонки `CharacterRecord::getSpaceId` и `getRulesRevision`, затем один `replacePayload`. Экономика для NPC берёт `choices`, `sheet`, `spaceId` и `rulesRevision` из `npc.version` (`GameNpcs::version` кладёт их туда) и `apply` не вызывает: у NPC нет строки `character`. `spaceCode` метод не принимает и не возвращает.

`applyToDocument` патчит `choices` и, если среди операций был `setMoney`, пишет то же целое в `sheet.money`. Остальные ключи `sheet` не трогает. `CharacterSheetDocument::build` сюда не вызывается: он принимает `CharacterValidation`, а её даёт только `ICharacterSheets::validate`. Этот `validate` на пустой расе NPC возвращает `CHARACTER_RACE`, поэтому в общий разбор он не входит. `apply` после `applyToDocument` вызывает прежний ход G10: `validate` и `CharacterSheetDocument::build` по уже пропатченным `choices`. Problems — нет `replacePayload`. Для NPC этого хода нет: восьми производных ключей операция экономики не меняет, от количества они не зависят.

**Чего операция не делает.** Не создаёт строку `game_npc` и не вызывает `game.createNpc`. Не пишет snapshot, бонус, видимость секций, летопись, сессию и бой. Не зовёт `character.migrate`, `game.updateNpc`, `game.translateNpc`, `CharacterSaveAssembly::build`. Не шлёт событие и не кладёт outbox. Не ждёт approve: разрешённая часть применяется сразу. `changes_pending` не выставляется; следующее чтение membership само увидит diff.

**Права.** Карточка скрыта — `GAME_NOT_FOUND`.

| Вид | Кто проводит |
|---|---|
| buy, sell | владелец предмета-персонажа, и строка `game_character` этого персонажа в статусе `active`; либо владелец игры, `gm`, `game.edit_all` при том же `active` |
| discard, transfer_item, transfer_money | то же для источника-персонажа; цель-персонаж тоже `active` в этой игре |
| loot | владелец игры, `gm` или `game.edit_all` |
| запись набора позиций | владелец игры, `gm` или `game.edit_all` |

`player` не выдаёт loot и не пишет магазин. Владелец персонажа — `GameCharacterRecord::getCharacterOwnerId()` строки membership, как `GameCharacterMemberships::leave`. Это не повторное чтение `character.owner_id`. Чужой персонаж без роли ведущего — `GAME_NOT_FOUND`, если карточка ему не видна, и `AUTH_DENIED`, если карточку он видит, но источник не его. Активная сессия не требуется и не запрещает операцию.

`character.applyActualPatch` разбирает операции тем же `apply`. Новые `kind` поэтому принимает и владелец персонажа. Отдельного фильтра HTTP нет: `ApplyCharacterActualPatchInput` хранит `operations` массивом и коды не белит. `setInventoryQuantity` по-прежнему не создаёт строку.

## Что даёт этот заход

Фасад Game `IGameEconomy` и действие `game.applyEconomy`. Рядом чтение и замена набора позиций магазина. Успех меняет только затронутые authoritative records и увеличивает их версии на 1. Строка операции хранит ключ и итог.

## Что не закрыто

- Бой (G12–G13), проекции (G14), доставка (G15). SSE, outbox, `CharacterChanged`.
- Экипировка, custom item, модификаторы предмета, `states`, урон. Следующий `kind` листа входит в ход G10, не во второй писатель. Строка без `ruleCode` этой операцией не адресуется. Новая строка от `putInventoryQuantity` несёт только `ruleCode`, `quantity` и `equipped: false`.
- Подготовка лута, интерес игроков, деление денег поровну, модерация выдачи. Обычная операция approve не ждёт.
- Боевой журнал и process state overlay.
- Vue поверх этого PHP.
- Новый ключ `game.edit_inventory`.

## Точки кода G1–G10

Старые `game-plan-01.md`–`game-plan-10.md` не переписываются. Character не начинает импортировать Game.

Без этих двух точек операция не пишет лист и `npc.version` одним commit:

- `CharacterActualMutations` и `ICharacterActualMutations`. В разбор добавляются `setMoney`, `putInventoryQuantity` и метод `applyToDocument`. `apply` остаётся записью строки `character` и зовёт `applyToDocument`. `setInventoryQuantity` не меняется. `CharacterPortFactory`, `module.config.php` Character и `CharacterRepository` не меняются: внешняя транзакция уже подхватывается `writeGuarded`.
- `GameNpcRepository`. Новый метод условной записи `version` и `actual_version + 1` при совпавшем `actual_version`. `save` не правится и экономикой не вызывается: у него нет предиката, конфликт версии внутри общей транзакции поймать нечем. `game.createNpc` не вызывается.

Остальной код G1–G10 не меняется.

## Модуль

Сценарий живёт в `Roleplay/Game`, `Interface/Service/IGameEconomy`. Реализация в `Service/` Game. Сборщик — новый `GameEconomyPortFactory` с `create` и `createHttp`. `GamePortFactory` не растёт: у него уже десять публичных `create*`. Порт Character сценарий вызывает, не наследует. `events` остаётся `[]`. `module.config.php` Game регистрирует порт `IGameEconomy` в `ports`, как `IGameChronicles`, и три action через этот сборщик. `GameContainer` список портов не хранит.

**DAG:** как после G10. Game читает и пишет actual через порт. Character Game не импортирует.

Ошибки Game: `GAME_INVALID`, `GAME_NOT_FOUND`, `GAME_CONFLICT`, `AUTH_REQUIRED`, `AUTH_DENIED`, `INVALID_PARAMS`. `CharacterConflictException` уже несёт код `CHARACTER_CONFLICT` и всплывает из action Game без переводчика. Проблемы `validate` — `CHARACTER_INVALID` с `problems`, транзакция пустая. Две строки инвентаря с одним `ruleCode` — `CHARACTER_INVALID` порта, без записи. Персонаж `submitted` или `left` — `GAME_INVALID`.

| Источник | Код |
|---|---|
| нет актора | `AUTH_REQUIRED` |
| карточка скрыта; NPC или персонаж не этой игры; чужой персонаж не виден вместе с карточкой | `GAME_NOT_FOUND` |
| вид не разрешён; чужой персонаж, когда карточка актору видна | `AUTH_DENIED` |
| пустой ключ; пустой список частей; вид вне шести; дельта не больше нуля; в одной части и предмет, и `shares`; нет позиции; нет `sellPrice` на sell; не хватает баланса, количества или остатка; игра `completed`; персонаж не `active` | `GAME_INVALID` |
| ожидаемая версия NPC или позиции не совпала; тот же ключ с другим телом | `GAME_CONFLICT`, записи нет |
| ожидаемая `actual_version` персонажа не совпала | `CHARACTER_CONFLICT`, записи нет |
| тело не того типа; лишний ключ верхнего уровня | `INVALID_PARAMS` |

## Таблицы

Новая карта `GameEconomySchema` со своими table-классами. `GameModuleSetup::getTableClasses` сливает её с `GameSchema`, `GameAdmissionSchema` и `GameChronicleSchema`. `GameMysqlFixture::installGameSchemas` ставит её тем же вызовом `install`, иначе suite `game` таблиц не увидит. Колонки `character` и `game_npc` не добавляются.

`game_shop_position`: `game_id`, `rule_code`, `buy_price`, `sell_price`, `quantity`, `version`. `sell_price` — `IntField` с `required false`, как необязательные поля `GameTable`. Пара (`game_id`, `rule_code`) уникальна через `defineUniqueKeys`. `version` со старта 1.

`game_economy_operation`: `game_id`, `idempotency_key`, тело запроса и итог версий — два `JsonField`. Пара (`game_id`, `idempotency_key`) уникальна. Повтор читает эту строку. Вставка стоит в конце той же транзакции. Нарушение unique откатывает её и наружу даёт `GAME_CONFLICT`.

Замена набора — одна транзакция. `positions` — полный будущий набор объектов `{ ruleCode, buyPrice, sellPrice, quantity }`. `sellPrice` — `int` или `null`. Повтор `ruleCode` в списке, отрицательная цена или отрицательный остаток — `GAME_INVALID`. `expectedPositions` — все текущие пары `{ ruleCode, version }`, включая те, которых в `positions` уже нет и которые надо удалить. Набор пар не совпал с базой — `GAME_CONFLICT` тем же новым классом, ничего не пишется и не удаляется. Совпал — переданные коды пишутся, отсутствующие в `positions` коды этой игры удаляются. Новый код не входит в `expectedPositions`, его `version` становится 1. Код есть и в базе, и в `positions` — пишется с `version + 1`. `game.getShop` отдаёт те же поля плюс `version`.

## Фасад

`IGameEconomy`. Не метод `IGames` и не метод `ICharacters`.

Три публичных метода сверх конструктора:

- `apply(int $gameId, int $actorUserId, string $idempotencyKey, array $parts, array $expectedVersions): array`
- `getShop(int $gameId): array`
- `replaceShop(int $gameId, int $actorUserId, array $positions, array $expectedPositions): array`

`apply` открывает транзакцию шлюза до порта и до записи NPC. Персонаж меняется через `ICharacterActualMutations::apply`. NPC меняется через `applyToDocument` и новый метод `GameNpcRepository`: порт возвращает документ, репозиторий пишет его предикатом `actual_version`. Список операций в обоих вызовах — только `setMoney` и `putInventoryQuantity` с уже посчитанными абсолютными числами. Нет строки NPC — `GAME_NOT_FOUND` до любого insert в `game_npc`.

`expectedVersions` — объект с тремя списками: персонажи `{ characterId, actualVersion }`, NPC `{ npcId, actualVersion }`, позиции `{ ruleCode, version }`. Лишний участник, которого части не трогают, — `GAME_INVALID`. Пропущенная версия затронутой строки — `GAME_INVALID`.

Возврат `apply` — `{ operationId, idempotencyKey, versions }`. Лист в ответ не кладётся. Повтор отдаёт тот же объект.

Класс не принимает полный лист, цену, готовый урон и `commandId` эскиза. Имя ключа в фасаде — `idempotencyKey`.

## HTTP

Свои DTO, `csrf` true. Action тонкий: в `handle` только вызов сценария. Сценарий сам проверяет актора.

| Action | Кто | Тело | Успех |
|---|---|---|---|
| `game.applyEconomy` | по таблице прав вида | `{ gameId, idempotencyKey, parts, expectedVersions }` | `{ operationId, idempotencyKey, versions }` |
| `game.getShop` | кто видит карточку | `{ gameId }` | `{ positions }` |
| `game.replaceShop` | владелец, `gm` или `game.edit_all` | `{ gameId, positions, expectedPositions }` | `{ positions }` |

`parts` — массив `{ kind, ...поля вида }`. Верхний лишний ключ — `INVALID_PARAMS`. Чужой `kind` и лишний ключ части — `GAME_INVALID`.

Дефекты эскиза `draft-front_1.2ds`, в фасад не копировать:

- `distributeLoot` — отдельный поток. Тело: `lootId`, `commandId`, `expectedActualVersions`, `distribution[]` с `type` и суммой. Это не `EconomyOperation` и не шесть видов. Ключ в PHP называется `idempotencyKey`.
- Тот же мок заводит запас, `handoutLoot` (prepared → available) и `toggleLootInterest`. Этих состояний у операции нет.
- Мок и абзац Loot в `game-system.md` про выдачу NPC говорят, что минимальный лист инициализируется. G6 и граница G11 этого не делают: нет строки NPC — отказ, insert нет.
- Деление денег поровну между заинтересованными мок собирает сам. Сервер принимает только явные доли в частях `loot` / `transfer_money`.
- `GameApi.distributeLoot` не становится действием каркаса.

## Тесты

Suite `game`. В suite `character` рядом с G10: `setMoney` меняет `choices.money` и производный `sheet.money`; `putInventoryQuantity` на пустом инвентаре добавляет одну строку `{ ruleCode, quantity, equipped: false }`, на существующей меняет только `quantity`. Чужая версия не пишет строку. `setInventoryQuantity` по-прежнему отвергает отсутствующую строку. `CharacterSaveAssembly::build` не вызывается.

Mysql атомарности. Buy списывает деньги персонажа, увеличивает `quantity` инвентаря, уменьшает остаток позиции и пишет строку операции. Подмена `actual_version` в ожидаемом теле откатывает позицию, NPC (если он в той же операции) и строку операции: счётчики и JSON прежние. То же для чужой `npc.actual_version` и чужой `version` позиции.

Mysql идемпотентности. Повтор с тем же ключом и тем же телом возвращает прежний итог; `quantity`, деньги и остаток не удваиваются. Тот же ключ с другим телом — `GAME_CONFLICT`.

Mysql NPC. Loot предмета в существующего NPC с пустым инвентарём и пустой расой добавляет одну строку в `npc.version.choices.inventory` и увеличивает `npc.actual_version` на 1. `CHARACTER_RACE` нет. Число строк `game_npc` не растёт. Поля `custom`, `modifiers`, `durabilityLeft`, `note` уже лежащей строки не пропадают. Tombstone `ruleCode` — `CHARACTER_INVALID`, строка NPC прежняя. Неизвестный `npcId` — `GAME_NOT_FOUND`, строка персонажа и позиция прежние. `game.createNpc` не вызывается.

Mysql допуска. Персонаж `submitted` или `left` — `GAME_INVALID`, лист и магазин прежние. `active` проходит.

Mysql границ. Sell без `sellPrice` — `GAME_INVALID`. Discard и transfer не требуют сессии. `player` на loot — `AUTH_DENIED`. Обмен двумя частями transfer в одном теле либо проходит целиком, либо не оставляет ни одной передачи. Nowhere не меняет `character` и `game_npc`.

## Todo

- [x] **port-kind** — `setMoney`, `putInventoryQuantity` и `applyToDocument`. `apply` пишет строку через этот разбор. `setInventoryQuantity` не меняется. `writeGuarded` не переписывается.
- [x] **npc-cas** — условная запись `GameNpcRepository` по `actual_version`. `save` и `game.createNpc` не используются.
- [x] **operation** — `IGameEconomy::apply` в одной транзакции шлюза: версии, расчёт, порт, NPC, позиция, строка ключа.
- [x] **shop** — таблица позиций, `game.getShop`, `game.replaceShop`.
- [x] **http** — `game.applyEconomy`. Ответ без листа.
- [x] **gates** — phpunit. Конфликт версии не оставляет частичной записи. Повтор ключа не применяет эффект дважды. Выдача в NPC не создаёт строку NPC.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| операция | Game `Interface/Service/` `Service/` | разобрать вид, проверить версии и права, провести транзакцию |
| сборка | Game `Service/GameEconomyPortFactory` | `create` и `createHttp`; `GamePortFactory` не меняется |
| лист | Character `Service/CharacterActualMutations` | один разбор `applyToDocument` для персонажа и NPC |
| запись NPC | Game `Repository/GameNpcRepository` | CAS уже собранного `npc.version` |
| магазин и ключ | Game `Schema/` `Repository/` | позиции и строка операции |
| HTTP | Game `Action/` | три действия |

## Acceptance G11

- Шесть видов проходят одним фасадом. Обмена как седьмого вида и как склада нет.
- Деньги — одно целое. Цена buy/sell берётся из позиции игры.
- Конфликт любой ожидаемой версии откатывает персонажа, NPC, позицию и строку операции.
- Повтор того же idempotency key не меняет количества и деньги второй раз.
- Мутация персонажа и NPC идёт через один `applyToDocument`: `putInventoryQuantity`, `setMoney` и tombstone затронутого `ruleCode`. В `sheet` NPC меняется только `money`. Пустая раса выдачу не блокирует. Полный `validate` и `CharacterSheetDocument::build` остаются в `apply` и для NPC не вызываются. Нет строки инвентаря — добавляется минимальная. `setInventoryQuantity` по-прежнему требует существующую строку. Полный лист, `equipped` и `states` порт не принимает.
- Источник и цель-персонаж — только статус `active`.
- Выдача в NPC пишет `npc.version` и `npc.actual_version` существующей строки документом порта. Новой строки NPC нет. `spaceId`, `spaceCode` и `rulesRevision` листа NPC прежние.
- Обычная разрешённая операция не ждёт approve.
- Character не импортирует Game.
- Бой, проекции и доставка этим заходом не спроектированы.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G11; [`game-system.md`](game-system.md); [`game-plan-10.md`](game-plan-10.md); [`game-plan-06.md`](game-plan-06.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).
