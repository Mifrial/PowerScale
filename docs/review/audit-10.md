# Audit 10 — production reliability capability/content

**Дата:** 2026-10-08  
**Режим:** implementation plan  
**Scope:** только production reliability capability/content

## Baseline

- HEAD: `26048f3778555fbf4646c7eb861ce92fd7b23c7f`, branch `dev`.
- На момент исследования tracked/staged diff отсутствовал.
- Существующие untracked-файлы сохраняются.
- Не входят: penetration/AP, defender-check/block, frontend и новые mechanics.

## Backend contract

1. Game вызывает только:
   `IMechanicEngine::hasReliabilityCut(bindings, catalog, ResolveActiveOptions)`.
2. Capability определяется только через `instanceof IReliabilityCut` у уже разрешённого handler.
3. Цепочка resolution:
   `damage_type Rule → mechanics[] → mechanic_id → MechanicRecord(code, handler_version) → registry code@version → handler`.
4. Game не сравнивает mechanic code, client boolean или `pay_sr`.
5. При включённой capability удаляются только слои, где:
   `durability !== null && durability <= hitRating`.
   `durability === null` всегда остаётся применимым.
6. Отсутствие mechanics у корректного damage type означает `false`.
7. Неправильная строка binding, отсутствующий catalog entry или отсутствующий handler — typed failure, не `false`.
8. Ошибка capability-resolution преобразуется в `GAME_INVALID`; mutation, battle-version increment и idempotency record не выполняются.

## Owners

- Mechanic:
  - production handler;
  - `IReliabilityCut`;
  - registry delivery `code@version`.
- Rule:
  - catalog row;
  - опубликованный `damage_type` binding;
  - строгая валидация mechanics rows.
- Character:
  - существующий `ICharacterCombatLayers::project()`;
  - единая projection-семантика для Character и NPC.
- Game:
  - вызов `hasReliabilityCut`;
  - strict adapter bindings;
  - filtering durability и resistance calculation.
- `IMechanicEngine`, `IReliabilityCut`, `MechanicEngine` и `MechanicHandlerRegistry` менять только если тесты выявят несоответствие уже принятому контракту.

## План реализации

### 1. Production handler

Добавить:

- `modules/Roleplay/Mechanic/Service/Handler/ReliabilityCutHandler.php`

Контракт:

- реализует `IMechanicHandler` и `IReliabilityCut`;
- delivery key: `reliability_cut@1.0.0`;
- subscriptions — пустые;
- `run()` не меняет context;
- handler является semantic capability marker, а не event-effect handler.

### 2. Production registry

Изменить:

- `modules/Roleplay/Mechanic/Service/MechanicPortFactory.php`

Зарегистрировать handler в `createEngine()`.

Не менять:

- `module.config.php`;
- публичный интерфейс engine;
- payload-модель;
- существующие handlers.

### 3. Catalog/content binding

Catalog entry создать через существующий `IMechanics` API:

- `code = reliability_cut`;
- `handler_version = 1.0.0`;
- уникальная пара `(code, handler_version)`.

Затем в опубликованной Rule revision:

- у явно выбранного production `damage_type` добавить `mechanic_id` этой строки;
- `mechanic_payload` хранить как корректный массив, обычно `[]`;
- не добавлять binding автоматически ко всем damage types;
- не привязывать его к `check`, `roll` или unrelated rule.

В репозитории production seed/content-механизм для такого Rule content не найден. Поэтому binding является DB revision operation через существующие Mechanic/RuleSpace фасады, а не новым произвольным seed-файлом.

### 4. Fail-closed validation

Изменить:

- `modules/Roleplay/Rule/Dto/RuleVersionRecord.php`
- `modules/Roleplay/Game/Service/GameStrikeLayers.php`

`RuleVersionRecord` должен отвергать malformed mechanics rows при hydration:

- mechanics не list;
- row не object/array;
- отсутствует `mechanic_id`;
- `mechanic_id` не положительный integer;
- отсутствует `mechanic_payload`;
- payload не array.

`GameStrikeLayers::bindings()` должен иметь defensive validation и не пропускать malformed row. Для capability-проверки payload не исполняется, но его форма должна быть проверена.

Уже существующие проверки `RuleVersionBody`, `RuleSpaceCommitDraftMapper` и `Rules::assertKnownMechanics` сохранить и покрыть тестами; не дублировать новую бизнес-логику в этих слоях без необходимости.

## Files

Production:

- `Mechanic/Service/Handler/ReliabilityCutHandler.php` — новый.
- `Mechanic/Service/MechanicPortFactory.php` — registry entry.
- `Rule/Dto/RuleVersionRecord.php` — strict hydration validation.
- `Game/Service/GameStrikeLayers.php` — defensive binding validation.

Tests:

- `Mechanic/tests/MechanicEngineTest.php`
- `Mechanic/tests/MechanicEnginePortTest.php`
- новый `Rule/tests/RuleVersionRecordTest.php`
- `Rule/tests/RuleUnitTest.php` или существующий parser suite
- новый `Game/tests/GameStrikeLayersTest.php`
- `Character/tests/CharacterCombatLayerTest.php`
- `Game/tests/GameStrikeMysqlTest.php`
- `Game/tests/GameWideStrikeMysqlTest.php`

Документацию, roadmap, readiness и audit-файлы не менять.

## Acceptance tests

### Mechanic

- Production port resolves `reliability_cut@1.0.0` without external handler registration.
- `hasReliabilityCut()` возвращает `true` только для handler с `IReliabilityCut`.
- Arbitrary handler code with the marker still returns `true`; Game does not compare code.
- Non-marker handler returns `false`.
- Empty binding list returns `false`.
- Missing catalog row throws `MechanicInvalidException`.
- Missing registry handler throws `MechanicInvalidException`.

### Rule/parser

- Valid damage-type mechanics binding hydrates unchanged.
- Every malformed row is rejected, never dropped.
- Unknown catalog id is rejected on Rule commit.
- Missing mechanics on a non-designated damage type remains valid and means no capability.
- `durability` omitted from armor/resistance slot remains `null`.

### Character/NPC projection

- Identical Character and NPC `sheet/choices` produce identical layer DTOs.
- Projection remains read-only.
- `null` durability survives armor, resistance and grant projection.
- Block defense keeps nullable durability semantics.
- No full sheet or persistent layer list is added to Game state.

### Game resistance

- Capability disabled: thresholded layers remain.
- Capability enabled and `durability <= rating`: layer is removed.
- Capability enabled and `durability > rating`: layer remains.
- Capability enabled and `durability === null`: layer remains.
- Typed resistance/source collapse behavior remains unchanged.
- No binding returns no cut; no fallback is inferred from damage type, mechanic code or `pay_sr`.
- Malformed binding, missing catalog or missing handler returns `GAME_INVALID`.
- No Character/NPC mutation, strike close, battle-version increment or command record occurs after such failure.
- `1 → 1` and `1 → N` use the same Character projection path for Character and NPC targets.

### Live content

- Published production revision contains the selected live `damage_type`.
- Its binding points to the catalog row for `reliability_cut@1.0.0`.
- Catalog row resolves to the production marker handler.
- No dangling `mechanic_id` exists.
- Runtime does not use `contentStatus` as capability detection.

## Replay, stale and conflict semantics

- Same idempotency key and identical command body: return stored result; do not re-resolve effects, mutate sheets, or increment versions.
- Same idempotency key with different body: `GAME_CONFLICT`.
- Invalid reliability binding: hard command failure; no idempotency record, so a corrected content deployment may retry the command.
- Stale battle version in `1 → 1` or `1 → N`: existing `GAME_CONFLICT` behavior remains.
- Stale target sheet version in `1 → 1`: `GAME_CONFLICT`, strike remains open.
- Stale target sheet version in `1 → N`: target-level refusal; other targets may proceed.
- Reliability-resolution failure is not target-level refusal: it is a shared invalid revision/content error and must abort the whole wide strike transaction.
- Existing replay, stale, target refusal and rollback behavior is not otherwise changed.

## P5 completion boundary

P5 is complete only when the production handler, registry delivery, catalog row, published live damage-type binding, malformed-row rejection, nullable durability behavior, Character/NPC parity and replay/conflict acceptance tests all pass.

No implementation of penetration, AP spend, defender roll, block effect or unrelated mechanics is part of P5.
