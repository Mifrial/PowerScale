# Audit 5 — production capability reliability cut

**Дата:** 2026-10-07  
**Режим:** read-only audit  
**Scope:** только production capability reliability cut  
**Verdict:** `BLOCKED`

## Scope and constraints

Проверены канонические документы:

- `AGENTS.md`
- `docs/tr/TR.md`
- `docs/tr/game-roadmap.md`
- `docs/tr/game-combat-readiness.md`
- `docs/tr/combat-layers-prerequisite.md`
- `docs/tr/game-plan-28.md`
- `docs/tr/php-coding-standards.md`
- `docs/tr/mechanic-roadmap.md`
- `docs/tr/mechanic-plan-07.md`
- `docs/tr/rule-roadmap.md`
- `docs/tr/rule-system.md`
- `docs/tr/character-plan-12.md`

Tracked diff отсутствует. Pre-existing untracked paths не изменялись:

- `tools/review/__pycache__/`
- `www/mifrial/var/`

MySQL/PHPUnit integration suites не запускались: их fixtures создают и
удаляют таблицы. Frontend использовался только как источник требований.

## Verdict

`BLOCKED`.

Capability infrastructure готова как backend-open infrastructure, но
production handler и live Rule content, включающий capability для реального
`damage_type`, не подтверждены. Поэтому production reliability cut нельзя
считать готовым.

Дополнительный blocker: malformed mechanic binding в Game adapter молча
пропускается, вместо fail-closed отказа.

## 1. Подтверждённый контракт

- Capability определяется только через `IMechanicEngine::hasReliabilityCut()`.
- Решение основано на `instanceof IReliabilityCut` у уже разрешённого
  handler.
- Game не сравнивает mechanic code.
- Game не принимает client boolean.
- `pay_sr` не используется как источник capability.
- Слой с `durability=null` остаётся применимым.
- Threshold снимается только при включённой capability и при
  `durability <= rating`.
- Отсутствующий catalog или handler должен давать отказ, а не выключать
  capability молча.
- Проекция Character и NPC проходит через один authoritative
  `ICharacterCombatLayers::project()` path.

## 2. Canonical owners

- **Mechanic** — semantic capability и production handler.
- **Rule** — live `damage_type` Rule и его mechanic binding.
- **Character** — projection слоёв цели из `sheet` и `choices`.
- **Game** — orchestration удара, запрос capability, фильтрация threshold и
  resistance calculation.

## 3. Evidence

### Capability infrastructure

`www/mifrial/modules/Roleplay/Mechanic/Interface/Service/IMechanicEngine.php`

- `IMechanicEngine::hasReliabilityCut()`.

`www/mifrial/modules/Roleplay/Mechanic/Interface/IReliabilityCut.php`

- marker interface без строкового кода или дополнительного boolean.

`www/mifrial/modules/Roleplay/Mechanic/Service/MechanicEngine.php`

- `MechanicEngine::hasReliabilityCut()` вызывает `resolveActive()` и проверяет
  `instanceof IReliabilityCut`.
- `MechanicEngine::listed()` отказывает при отсутствии строки каталога.
- `MechanicEngine::handlerOf()` отказывает при отсутствии handler в registry.
- `MechanicEngine::pushBinding()` не сравнивает mechanic code.

`www/mifrial/modules/Roleplay/Mechanic/tests/MechanicEngineTest.php`

- `testHasReliabilityCutReadsMarker()` подтверждает marker-based detection.
- `testResolveActiveRejectsBindingWithoutHandler()` подтверждает отказ при
  отсутствующем handler.
- `cuttingHandler()` реализован только как anonymous test handler с
  `IReliabilityCut`; это не production handler.

### Production handler and registry

`www/mifrial/modules/Roleplay/Mechanic/Service/MechanicPortFactory.php`

- `MechanicPortFactory::createEngine()` регистрирует только:
  `PlainRollHandler`, `PurchaseSurchargeHandler`, `SixOneRuleHandler`,
  `AdvantageDisadvantageHandler`.

Production handler, реализующий `IReliabilityCut`, отсутствует.
Следовательно, production registry capability не содержит.

Проверенные production handlers:

- `PlainRollHandler.php`
- `PurchaseSurchargeHandler.php`
- `SixOneRuleHandler.php`
- `AdvantageDisadvantageHandler.php`

Все реализуют только `IMechanicHandler`.

### Live Rule content

`www/mifrial/modules/Roleplay/Rule/Dto/Spec/DamageTypeSpec.php`

- Хранит `damage_type`, `defense_ignored`, spell-difficulty flag и
  `maxSuccessRating`.
- Не содержит reliability capability.

`www/mifrial/modules/Roleplay/Game/tests/GameStrikeMysqlTest.php`

- `worldWithWeapon()` создаёт `cut` и `crush` как `damage_type`.
- `roll` получает `mechanic_id` для `plain_roll`.
- У `damage_type` mechanics binding отсутствует.

`www/mifrial/modules/Roleplay/Game/tests/GameWideStrikeMysqlTest.php`

- `worldWithWeapon()` также создаёт `cut` без reliability binding.
- `roll` связывается только с `plain_roll`.

В production seed/content для `damage_type` с binding на reliability handler
доказательств нет.

### Game consumer

`www/mifrial/modules/Roleplay/Game/Service/GameStrikeLayers.php`

- `GameStrikeLayers::total()` получает живой `DamageTypeSpec`, вызывает
  `IMechanicEngine::hasReliabilityCut()` и передаёт результат в filtering
  path.
- `GameStrikeLayers::bindings()` создаёт `MechanicBinding` по
  `mechanic_id`.
- `GameStrikeLayers::shaved()` снимает слой только при:
  `cut === true`, `durability !== null` и `durability <= rating`.
- В production path нет сравнения mechanic code, client boolean или
  `pay_sr`.

`www/mifrial/modules/Roleplay/Game/Service/GameStrikeRules.php`

- `GameStrikeRules::evaluateResistance()` передаёт в `GameStrikeLayers` уже
  полученный hit rating.
- `GameStrikeRules::defenderDocuments()` загружает документы Character или
  NPC, после чего использует общий layer path.

`www/mifrial/modules/Roleplay/Game/Service/GameStrikes.php`

- `GameStrikes::resistanceOf()` вызывает
  `GameStrikeRules::evaluateResistance()`.

`www/mifrial/modules/Roleplay/Game/Service/GameWideStrikeResults.php`

- `GameWideStrikeResults::resistanceOf()` вызывает тот же
  `GameStrikeRules::evaluateResistance()` для каждой цели.

### Character/NPC projection

`www/mifrial/modules/Roleplay/Character/Interface/Service/ICharacterCombatLayers.php`

- `ICharacterCombatLayers::project()` — authoritative read-only projection
  contract.

`www/mifrial/modules/Roleplay/Character/Service/CharacterCombatLayers.php`

- `CharacterCombatLayers::project()` собирает armor, block layers и grants.
- Один метод используется для документов Character и NPC.

`www/mifrial/modules/Roleplay/Character/Service/CharacterCombatItemLayers.php`

- `CharacterCombatItemLayers::armor()` и `block()` сохраняют nullable
  durability.
- `CharacterCombatItemLayers::source()` обрабатывает source блока.

### Durability

`www/mifrial/modules/Roleplay/Rule/Dto/Spec/Item/ResistanceSlot.php`

- `ResistanceSlot::getDurability()` возвращает `int|null`.

`www/mifrial/modules/Roleplay/Rule/tests/ItemSlotDurabilityTest.php`

- `testMissingDurabilityIsNull()` подтверждает отсутствие threshold как
  `null`.

`www/mifrial/modules/Roleplay/Character/tests/CharacterCombatLayerTest.php`

- `testEquippedArmorComesFromRevision()` подтверждает nullable durability в
  projection.
- `testGrantUsesAbilityParameter()` подтверждает общий projection behaviour
  для одинаковых документов.

## 4. Current gaps

### Production capability infrastructure

Инфраструктура готова, но имеет статус `BACKEND_OPEN`, а не production
ready: marker и API существуют, production implementation отсутствует.

### Production handler

Handler, реализующий одновременно `IMechanicHandler` и `IReliabilityCut`,
отсутствует. Production registry его не регистрирует.

### Live Rule content

Live `damage_type` binding на reliability handler не подтверждён.
Имеющиеся Game fixtures используют mechanic binding для `roll`, а не для
damage type.

### Fail-closed malformed binding

Новый `RuleVersionBody` валидирует mechanic rows через
`RuleVersionBody::assertMechanics()`, а `Rules::assertKnownMechanics()`
проверяет catalog id при commit.

Однако:

- `RuleVersionRecord::requireMechanicList()` проверяет только list-форму;
- `GameStrikeLayers::bindings()` пропускает row, если он не array или
  `mechanic_id` не integer;
- такой malformed row превращается в отсутствие capability вместо typed
  отказа.

Это не подтверждает требование fail-closed для runtime boundary.

## 5. Test coverage

Покрыто:

- marker detection в `MechanicEngineTest`;
- отказ отсутствующего handler в `MechanicEngineTest`;
- nullable durability parser в `ItemSlotDurabilityTest`;
- Character projection в `CharacterCombatLayerTest`;
- базовый resistance path и раздельные Character/NPC targets в
  `GameStrikeMysqlTest` и `GameWideStrikeMysqlTest`.

Не покрыто:

- production handler в production registry;
- live damage-type binding;
- reliability cut end-to-end;
- malformed binding fail-closed;
- nullable durability при включённой production capability;
- production registry integration.

## 6. Dependencies

Reliability cut зависит от:

1. `MechanicHandlerRegistry` и `IMechanicEngine`;
2. production handler с `IReliabilityCut`;
3. catalog entry `code@version`;
4. Rule `damage_type` с `mechanic_id` binding;
5. Character projection слоёв;
6. Game resistance path и hit rating;
7. fail-closed validation runtime binding.

Character projection и базовый resistance path уже существуют, но они не
закрывают отсутствие production handler/content.

## 7. Prerequisite-plan: только Mechanic/Rule content

Это prerequisite-plan, а не Game implementation plan.

1. Создать production handler, реализующий `IMechanicHandler` и
   `IReliabilityCut`.
2. Зарегистрировать handler в `MechanicPortFactory::createEngine()`.
3. Создать catalog entry с согласованными `code@version`.
4. Создать live `damage_type` Rule content с `mechanic_id` этого handler.
5. Проверить, что binding относится к damage type, а не к check или
   unrelated rule.
6. Закрыть malformed runtime binding fail-closed: либо на Rule hydration
   boundary, либо в Game adapter с typed invalid error.
7. Добавить unit/integration evidence для registry, live binding, missing
   handler, malformed binding и nullable durability.

Game implementation plan не составлялся. К `game-plan-29` перехода нет.

## Final verdict

`BLOCKED`: infrastructure существует, но production handler и live Rule
content отсутствуют/не подтверждены, а malformed binding не гарантированно
обрабатывается fail-closed.
