# Audit 3 — реакция `block` и ОД

## Git

Tracked diff отсутствует (`git diff --name-status` и staged diff пусты).
Обнаружены только untracked:

- `tools/review/__pycache__/`
- `www/mifrial/var/`

Аудит и сохранение отчёта выполнены без запуска PHPUnit/MySQL и frontend
dev-server.

## 1. Подтверждённый контракт

Подтверждены только общие границы:

- Player `actualCharacter` — единственный persisted лист Character.
- NPC `npc.version` — authoritative лист NPC.
- Game хранит только session/battle/process state, не ресурсы и не второй лист.
- Реакции Game принимает как `ignore | dodge | block`.
- Block-предмет должен быть надетым экземпляром с `BlockProfile`.
- Проекция block-слоёв выполняется через Character-порт
  `ICharacterCombatLayers::project`.
- Списание ОД реакции block, бросок защитника и полный block effect
  authoritative PHP-контрактом не подтверждены.

Evidence:

- `docs/tr/game-system.md`: Membership/overlay, `Game state and Character
  actual`, session transitions.
- `docs/tr/decisions.md`: `DEC-084`.
- `docs/tr/game-combat-readiness.md`: block имеет статус
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.
- `docs/tr/game-plan-28.md`: полный block path остаётся незакрытым.
- `docs/tr/combat-layers-prerequisite.md`: требуется defender check,
  автопровал, успешный/проваленный block и eligibility/spend ОД.

## 2. Canonical owner

### Character

Архитектурный owner — Character actual:

- `CharacterTable::payloadFields()` хранит `choices` и `sheet`.
- `CharacterRecord::getChoices()` / `getSheet()` читают actual.
- `CharacterActualMutations::apply()` является mutation port.
- `CharacterRepository::replacePayload()` выполняет CAS по `actual_version`.

Но отдельного ресурса текущих ОД в actual-контракте нет:

- нет поля/ресурса ОД;
- нет resource mutation service;
- `CharacterActualMutations::applyOperation()` принимает только inventory,
  money, state и `putDamageSplit`.

Итог: owner boundary Character подтверждён, owner именно текущих ОД в
runtime отсутствует.

### NPC

Архитектурный owner — Game-owned `game_npc.version`:

- `GameNpcTable` хранит `version` и `actual_version`;
- `GameNpcRecord::getVersion()` возвращает authoritative документ;
- `GameNpcRepository::replaceVersion()` выполняет CAS;
- `GameStrikeSheetWrites::writeNpc()` применяет документ и вызывает
  `replaceVersion()`.

Но отдельного ресурса текущих ОД в `npc.version` также нет.

Итог: storage boundary NPC подтверждён, runtime-модель текущих ОД
отсутствует.

Game overlay не может быть owner текущих ОД: это запрещено
`game-system.md` и `DEC-084`.

## 3. Evidence и текущая реализация

### Rule DTO

- `WeaponBlock::__construct()` содержит nullable `minActionCost`;
  getter — `getMinActionCost()`.
- `ShieldBlock` поля `minActionCost` не имеет.
- `BlockProfile` содержит только `efficiency`, `defense`, `resistances`.
- `ItemWeapons::block()` только парсит `weapon.min_action_cost`.
- `ItemModifiers::rest()` парсит `MinActionCostOp`.
- `MinActionCostOp::getType()` возвращает `min_action_cost`.

Это DTO/spec evidence, но не runtime-контракт.

### Modifier merge

`ItemModifierOperationItems::weapon()` сохраняет исходный
`$weapon->getMinActionCost()`.

`min_action_cost` не участвует в:

- `ItemModifierOperationNumbers::delta()`;
- `ItemModifierOperationNumbers::size()`;
- `ItemModifierOperationItems::weapon()`;
- любом Game resolver.

Следовательно, `min_action_cost` modifier merge не реализован и его
семантика не подтверждена.

### Eligibility и экипировка

`GameStrikes::assertReaction()` и
`GameWideStrikeResults::assertReaction()` проверяют только:

- допустимость строки reaction;
- наличие `blockItemRuleCode` для `block`;
- что Rule является weapon или shield через
  `GameStrikeRules::assertBlock()`.

`GameStrikeRules::assertBlock()` не проверяет:

- экипировку защитником;
- наличие `BlockProfile`;
- соответствие inventory instance;
- strength/hand/item requirements;
- наличие ОД;
- допустимость реакции для конкретного участника.

Позднее, внутри проекции:

- `GameStrikeLayers::blockId()` ищет ровно один
  `choices.inventory[].id`;
- проверяет `equipped === true` и совпадение `ruleCode`;
- `CharacterCombatItemLayers::block()` требует `getBlockProfile()`.

Это частичная проверка предмета, а не complete authoritative eligibility.

### Бросок защитника

`GameStrikes::close()` вызывает:

- `rateOf()` — только рейтинг атакующего;
- `resistanceOf()` — projection слоёв;
- `soakOf()` только для dodge.

Отдельного defender roll для block нет.

`GameWideStrikes::close()` получает один rating атакующего и передаёт его
всем target results. Независимого block rating для каждой цели нет.

### Момент списания

Списания ОД нет.

`GameStrikes::close()` в transaction выполняет:

1. `assertSheet()`;
2. расчёт rating/resistance/injury;
3. `writeSheet()`;
4. `strikes->close()`;
5. `battles->advanceVersion()`;
6. запись command result.

Ни Character resource spend, ни NPC resource spend в этой последовательности
отсутствует.

`GameWideStrikes::commitClose()` аналогично меняет листы целей, закрывает
reactions/strike и battle, но ресурсную операцию не выполняет.

### Atomicity

Существующая atomicity `sheet mutation + strike close` подтверждена:

- `GameStrikes::close()` обёрнут в `ISmartTableGateway::transaction()`.
- `GameWideStrikes::commitClose()` также обёрнут в transaction.
- Character пишет через `ICharacterActualMutations::apply()`.
- NPC пишет через `applyToDocument()` +
  `GameNpcRepository::replaceVersion()` внутри outer transaction.

Atomicity `spend ОД + strike close` не существует, потому что spend
operation отсутствует.

### Replay/idempotency

Для strike-команд есть:

- `GameStrikeCommandRepository::findByKey()` / `add()`;
- replay в `GameStrikes::ready()` и `GameStrikes::replay()`;
- аналогичный `GameWideStrikeReplay`;
- сравнение нормализованного тела команды;
- unique idempotency key.

Это защищает повторную запись уже существующего strike result. Но не
защищает повторный spend ОД: resource mutation не входит в command result и
не имеет собственного version/idempotency contract.

### Stale behavior

Подтверждено:

- battle CAS через `GameStrikes::battleOf()` /
  `GameWideStrikes::battleOf()`;
- Character sheet CAS через `GameStrikes::assertSheet()` и
  `GameStrikeRules::characterVersion()`;
- NPC CAS через `npcVersion()` и `actual_version`;
- для `1 → 1` stale конфликт оставляет удар открытым;
- для `1 → N` stale одной цели превращается в target-level refusal,
  остальные цели могут продолжить.

Не подтверждено:

- stale resource version;
- конфликт ОД с одновременной реакцией;
- правило rollback/partial acceptance для spend.

### Character/NPC parity

Есть parity для базовой projection:

- Character получает `sheet/choices` через `ICharacters`;
- NPC получает `sheet/choices` из `GameNpcRecord::version`;
- оба проходят `GameStrikeRules::evaluateResistance()` и
  `ICharacterCombatLayers::project()`.

Parity mutation неполная:

- Character: `apply()` выполняет validation;
- NPC: `applyToDocument()` validation не выполняет, затем
  `replaceVersion()`.

Это отмечено в `docs/tr/game-combat-readiness.md` как требующее отдельного
invariant-теста.

## 4. Текущий gap

Критические gaps:

1. Нет runtime owner/storage для текущих ОД.
2. Нет authoritative Character/NPC resource spend port.
3. Нет базовой стоимости block.
4. Нет подтверждённой семантики `min_action_cost`.
5. `min_action_cost` modifier не merge-ится.
6. У Shield нет стоимости block.
7. Нет defender block check.
8. Нет автопровала атакующего `{0|-1}`.
9. Нет веток successful/failed block.
10. Нет проверки ОД и resource CAS.
11. Нет atomic `spend + strike close`.
12. Нет replay/idempotency semantics для spend.
13. Character/NPC resource mutation parity отсутствует.

Активный `docs/tr/rule-content-plan-15-melee-tactics-design.md` задаёт
стоимость способности `Прикрытие`, но не стоимость реакции `block`.
Упоминания стоимости в `rule-content-plan-06-wounds.md` относятся к ранам,
не к block.

## 5. Зависимости

До runtime-контракта block spend необходимы:

- решение, какой именно ресурс тратится: ОД или ОР;
- canonical resource representation в Character actual;
- canonical resource representation в NPC `version`;
- порт и version/CAS для изменения ресурса;
- базовая стоимость block для weapon и shield;
- подтверждённая merge-семантика `min_action_cost`;
- полный eligibility contract предмета;
- момент оплаты: declaration, accepted reaction или close;
- правила stale и target-level refusal для wide strike;
- единый replay/idempotency contract;
- parity-инвариант Character/NPC;
- transaction boundary, включающая spend и close.

## 6. Отдельный prerequisite-plan

Это prerequisite-plan, не implementation plan:

1. Зафиксировать ресурс реакции и его имя.
2. Зафиксировать owner/storage:
   - Character actual;
   - NPC `npc.version`;
   - запрет Game overlay как authoritative resource storage.
3. Зафиксировать форму стоимости:
   - base block cost;
   - weapon/shield applicability;
   - значение отсутствующего cost;
   - semantics `min_action_cost`.
4. Зафиксировать merge modifier:
   - min/max rule;
   - source grouping;
   - behavior нескольких modifiers.
5. Зафиксировать eligibility:
   - equipped inventory instance;
   - `BlockProfile`;
   - strength, hands и прочие ограничения;
   - reaction legality;
   - достаточность ОД.
6. Зафиксировать payment timing и rollback.
7. Зафиксировать transaction-bound ports и expected versions.
8. Зафиксировать replay/stale semantics для `1 → 1` и `1 → N`.
9. Зафиксировать Character/NPC parity acceptance.

До закрытия этих prerequisites implementation plan составлять нельзя.

## 7. Verdict

**BLOCKED**

Причина: отсутствуют authoritative owner/storage текущих ОД, spend port и
transaction boundary `spend + strike close`; стоимость block и merge
`min_action_cost` также не подтверждены.
