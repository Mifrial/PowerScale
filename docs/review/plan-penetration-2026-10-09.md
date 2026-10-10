# Read-only audit and implementation plan: authoritative penetration

Дата: 2026-10-09

## Verdict

**READY FOR ONE PENETRATION IMPLEMENTATION SESSION**

Penetration можно выделить как отдельный размерный Rule/Game path, но
authoritative acceptance полного combat pipeline сейчас ещё не является
реализацией penetration:

1. production reliability dependency подтверждена и закрыта для penetration;
2. текущий Game resistance path уже вызывает `hasReliabilityCut()`, поэтому
   окончательная последовательность `reliability/cut → source collapse →
   penetration → resistance → damage → injury` зависит от незакрытой
   penetration implementation;
3. в текущем коде нет отдельного расчёта penetration и нет подтверждённого
   контракта дополнительных penetration-полей результата.

Reliability implementation, handler, catalog row, binding и frontend в этот
plan не входят. Сначала закрывается reliability dependency, затем проводится
одна implementation session penetration по плану ниже.

Номер следующего этапа намеренно не назначается.

## 1. Evidence и границы аудита

Проверены:

- `AGENTS.md`;
- `docs/tr/TR.md`;
- `docs/tr/game-plan-28.md`;
- `docs/tr/combat-layers-prerequisite.md`;
- `docs/review/plan-p3-defender-check-block-2026-10-08.md`;
- `docs/review/plan-p2b-resource-spend-2026-10-08.md`;
- `docs/review/audit-12-p1-verification-2026-10-08.md`;
- `docs/tr/rule-plan-07.md`;
- `docs/tr/evaluation-plan-01.md` как связанный, но не достаточный сам по
  себе источник формулы;
- Rule `ItemWeapons`, `WeaponProfile`, `ItemModifierOperations` и
  `ItemModifierOperationItems`;
- Character `CharacterFormulaContexts` и combat-layer projection;
- Game `GameStrikeRules`, `GameStrikeLayers`, `GameStrikeAmounts`;
- single/wide strike result, transaction, replay, stale/CAS и rollback paths.

Frontend не использовался как authoritative source. Исторические или
исследовательские документы не заменяют код и принятый backend contract.

## 2. Current state

### Уже подтверждено

- `ItemWeapons::profile()` читает `penetration` как
  `DimensionalFormula`.
- `WeaponProfile::getPenetration()` возвращает формулу, а не готовый scalar.
- `IFormulaEvaluations::evaluateDimensional()` возвращает
  `DimensionalNumber`.
- `CharacterFormulaContexts` строит контекст из authoritative `sheet`:
  `characteristicPurchases`, `abilityLevels` и server-owned parameters.
- Character source для выбранного предмета — `choices.inventory`:
  `itemInventoryId` разрешается в ровно одну equipped inventory row, после
  чего backend берёт live `ItemSpec` по её `ruleCode`.
- Effective item modifier operations применяются к live `ItemSpec`. Операция
  с `field = penetration` переписывает penetration formula и не переписывает
  damage formula.
- Character и NPC имеют параллельные authoritative document paths:
  Character читает actual `sheet` и `choices`; NPC читает
  `game_npc.version.sheet` и `version.choices`.
- `GameStrikeLayers` получает typed layers от Character projection и уже
  применяет damage type filter, reliability cut, durability и source
  collapse.
- `GameStrikeAmounts` содержит native dimensional add/subtract и
  `floorAtZero()`.
- single и wide paths используют `GameReplayTransaction`; committed result
  возвращается по тому же idempotency key без повторного domain work.
- stale sheet/battle и mutation-time CAS проходят через существующие Game
  conflict/transaction boundaries; поздняя ошибка должна откатывать outer
  transaction.

### Не подтверждено или отсутствует

- Game не оценивает `WeaponProfile::getPenetration()`.
- `GameStrikeRules::evaluateDamage()` считает только damage.
- `GameStrikeLayers::total()` считает resistance без penetration и не
  возвращает раздельно defense/resistance components.
- `GameStrikeAmounts` не имеет отдельной операции для
  `max(0, defense - penetration)`.
- Current JSON содержит `damage`, `resistance`, `injury`, но не имеет
  подтверждённых `rawPenetration` или `effectivePenetration` полей.
- Reliability handler `reliability_cut@1.0.0` зарегистрирован.
- Canonical `{}` binding и live Rule chain подтверждены.
- Piercing/cutting/slashing content проходит publication path.
- Fixture revision bug исправлен.
- Runtime acceptance: 19 тестов, 1229 assertions, failures=0.
- Production reliability dependency закрыта для penetration.
- Focused non-MySQL Rule/Game tests отдельно не запускались; это не является
  новым penetration blocker. Глобальная чистота worktree не требуется,
  поскольку P1/P2b/P3 изменения являются накопленным scope.

## 3. Authoritative owners and context

| Concern | Authoritative owner | Required context |
| --- | --- | --- |
| Weapon profile and base penetration formula | Rule live `ItemSpec` / `WeaponProfile` | `spaceId`, `rulesRevision`, live `itemRuleCode`, `profileType`, `profileIndex` |
| Selected item instance | Character/NPC `choices.inventory` | `itemInventoryId`, exactly one equipped row, its live `ruleCode` |
| Item instance modifiers | Character/NPC choices, resolved by Character/Rule item path | modifier rows attached to the selected inventory instance |
| Formula evaluation | Rule `IFormulaEvaluations` | attacker authoritative `sheet` through `CharacterFormulaContexts` |
| Defense/resistance layers | Character `ICharacterCombatLayers` | defender authoritative `sheet` + `choices`, optional successful block instance |
| Reliability cut | Mechanic capability resolved by Game | live damage-type rule bindings and catalog, no literal mechanic code |
| Ordering, reaction, hit gate and result | Game | single or wide strike orchestration |
| Persistence/CAS/rollback/replay | Game transaction + Character/NPC mutation ports | expected battle/entity versions and stored command body/result |

The instance is authoritative for selection and modifiers; the Rule is
authoritative for the immutable/effective spec. The implementation must not
replace the selected instance with a catalog lookup inferred from a
client-provided `itemRuleCode`. `itemRuleCode` is a consistency input; the
instance id selects the row.

Character/NPC parity means the same resolver, formula context semantics,
dimension arithmetic and Game result rules. Only the document adapter differs.
No hardcoded penetration, damage-type, mechanic, resource or item rule code is
permitted. Dynamic codes may be read from request data or live Rule data and
then resolved through the existing live-slice APIs.

## 4. Dimensional formula and arithmetic

### 4.1 Exact intended formula

For a successful, non-auto-fail attack after the authoritative layer
projection:

```text
P = evaluateDimensional(
      liveWeaponProfile.penetration,
      attackerFormulaContext
    )

D = sum(applicable collapsed layers where kind == defense)
R = sum(applicable collapsed layers where kind == resistance)

E = max(0, D - P)
effectiveResistance = R + E

effectiveDamage = damage
injury = floorAtZero((effectiveDamage - effectiveResistance) × hitRating - soak)
```

`P`, `D`, `R`, `E` and `effectiveResistance` are dimensional values. The
minus and plus operations align both operands to the smaller size by shifting
the larger-size base by powers of two, as in existing
`GameStrikeAmounts::subtract()` and `add()`.

`max(0, ...)` is the existing dimensional floor semantics:

- compare the result at a common size;
- if the aligned base is negative, return dimensional zero `{base: 0, size: 0}`;
- zero remains zero;
- a positive result keeps its native dimensional representation;
- if `P > D`, `E` is zero; defense cannot make effective resistance
  negative;
- penetration never reduces `R`;
- damage is not recomputed or modified by penetration;
- injury changes only because `effectiveResistance` changed.

There is no implicit scalar conversion, no `toInteger()` in the
penetration/defense/resistance path, and no use of integer hit rating as a
replacement for a dimensional operand. Formula failures remain
authoritative `GAME_INVALID` and must not become zero.

### 4.2 Source collapse boundary

The implementation must filter and cut layers before the penetration floor.
To preserve the invariant that penetration affects only defense, the selected
layers must be partitioned by `kind` before applying the defense subtraction:

```text
applicable layers
→ type filter
→ reliability cut/durability
→ source collapse within the layer kind
→ D and R sums
→ E = floorAtZero(D - P)
→ R + E
```

The current `GameStrikeLayers::collapse()` receives a mixed list. A direct
mixed collapse followed by penetration is unsafe if a defense and a
resistance layer share a source: one kind could suppress the other before
penetration is applied. The implementation session must either:

1. make source collapse explicitly kind-preserving; or
2. document and test a confirmed cross-kind source contract before coding.

The default implementation-plan choice is (1), because it is the only option
that proves resistance invariance independently of source naming.

The existing source rules remain unchanged inside each kind:

- explicit source: strongest positive and strongest negative survive;
- empty source: every layer remains unique;
- strength comparison is dimensional and aligned, not scalar-flattened;
- multiple modifiers are all collected, then source-collapsed by the existing
  modifier operation rules before the effective item is evaluated.

## 5. Exact pipeline placement

The authoritative order for an accepted hit is:

```text
battle/sheet preflight
→ selected item instance and profile validation
→ attacker roll
→ defender roll for block, if reaction == block
→ attacker auto-fail gate
→ successful-block decision / reaction resource eligibility
→ attacker damage formula
→ defender layer projection
→ damage-type filter
→ reliability cut and durability filtering
→ source-preserving D/R aggregation
→ penetration evaluation
→ effective defense E = max(0, D - P)
→ effective resistance R + E
→ damage
→ injury/soak
→ Character/NPC conditional mutation
→ close strike, battle version and command result
```

Required invariants:

- penetration reduces only `kind == defense`;
- typed resistance and untyped resistance are unchanged by penetration;
- weapon damage formula/value is unchanged by penetration;
- injury changes only through the changed effective defense component;
- auto-fail returns before layer projection and before penetration evaluation;
- a failed block does not add block layers and does not evaluate penetration
  for a non-applied effect;
- replay returns the stored result and does not re-evaluate penetration,
  reliability, formulas, rolls, projection or mutations;
- single and wide use the same calculation semantics per accepted target;
- wide attacker roll is shared, but each target gets its own defense layers,
  reliability result, penetration application and injury.

The current P3 code performs defender roll before the auto-fail branch. The
future penetration step must still be after the existing authoritative
auto-fail outcome boundary and must never make a failed attack effective.

## 6. Modifiers and formula ownership

Attacker-effective modifiers participating in penetration are only those that
are already authoritative on the selected inventory instance and applicable
to the selected profile:

1. read selected equipped `choices.inventory` row by `itemInventoryId`;
2. read its modifier references;
3. resolve live modifier Rules in the same revision;
4. apply `ItemModifierOperations::apply()` with the selected item's keyword
   context;
5. select the requested profile type/index;
6. evaluate the resulting `WeaponProfile::getPenetration()` formula in the
   attacker's `CharacterFormulaContexts` context.

The effective modifier order is:

```text
live base ItemSpec
→ applicable modifier operations
→ per-operation source collapse
→ effective WeaponProfile penetration formula
→ FormulaContext evaluation
→ Game defense subtraction
```

Multiple penetration modifiers must be additive/size-aware according to the
existing Rule operation semantics. A modifier that changes penetration must not
change damage unless it also contains a separate authoritative `damage`
operation. Tests must prove the two fields remain independent.

The implementation must not:

- evaluate penetration from the defender sheet;
- use `equippedModifiers` as a substitute for the selected inventory row;
- read a copied profile from Character storage instead of live Rule spec;
- infer penetration from damage, accuracy, mastery, resistance or a rule code;
- copy a frontend formula into Game;
- compare a mechanic or damage-type code to a literal.

## 7. Result JSON

Current canonical result shape remains authoritative unless a separate result
contract is approved:

- single: existing `attackerRoll`, optional `defenderRoll`, `success`,
  `damage`, `resistance`, `injury`, optional `S`, `sheetVersion`;
- wide: existing top-level `attackerRoll` and per-target
  `defenderRoll`, `success`, `damage`, `resistance`, `injury`, optional `S`,
  `sheetVersion`, `code`.

No `rawPenetration`, `effectivePenetration`, `rawDefense` or
`effectiveDefense` fields should be added in this implementation session.

Reason: the current Game result contract exposes effective `resistance`, not
an approved diagnostic breakdown, and replay must return the exact stored
result. Adding fields would change response and delivery compatibility without
an accepted contract. The implementation must therefore make the existing
`resistance` value the post-penetration effective resistance. If diagnostics
are later required, they need a separate result-contract decision, storage
compatibility review and exact single/wide/replay acceptance.

Auto-fail, explicit ignore and insufficient-resource results keep their
existing zero effect fields. They do not expose a newly evaluated penetration
value.

## 8. Dependency order

1. **Reliability prerequisite — closed for penetration.** The handler
   `reliability_cut@1.0.0` is registered; canonical `{}` binding and live
   Rule chain are confirmed; piercing/cutting/slashing content passes the
   publication path; the fixture revision bug is fixed; runtime acceptance
   is 19 tests, 1229 assertions, failures=0. The production reliability
   dependency is closed for penetration. Reliability implementation remains
   out of scope here.
2. **P3 gate.** Preserve the accepted attacker/defender roll, block,
   auto-fail, resource eligibility, stale and rollback semantics. Penetration
   must be called only from the accepted-effect branch.
3. **Effective item resolution.** Ensure single and wide attacker paths both
   resolve the selected instance and its modifiers before evaluating the live
   profile formula. Character/NPC use the same helper.
4. **Dimensional Game arithmetic.** Add a small pure operation for
   dimensional `floorAtZero(D - P)` and kind-preserving effective-resistance
   aggregation, reusing native `DimensionalNumber`/`GameStrikeAmounts`
   semantics.
5. **Layer integration.** Change `GameStrikeLayers` or a dedicated Game
   service to return effective resistance after applying penetration only to
   defense. Keep reliability cut and source rules at their existing owner
   boundaries.
6. **Single integration.** Pass authoritative attacker penetration into the
   accepted `GameStrikes` close path; do not evaluate it on auto-fail,
   ignore, stale preflight or replay.
7. **Wide integration.** Pass the same attacker penetration into every target
   calculation, while recalculating each target's D/R layers and reliability
   cut independently.
8. **Transaction/replay verification.** Confirm formula/projection occurs
   inside the existing transaction work only, is rolled back with late CAS
   failure, and is not executed for replay.
9. **Result compatibility.** Keep existing JSON fields and verify stored
   replay equality.

No fallback “reliability off” mode may be introduced for authoritative combat.

## 9. Files for one penetration implementation session

### Primary Game files

- `www/mifrial/modules/Roleplay/Game/Service/GameStrikeRules.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameStrikeLayers.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameStrikeAmounts.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameStrikes.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameWideStrikes.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameWideStrikeResults.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameWideStrikeCommit.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameStrikePortFactory.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameWideStrikePortFactory.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameReplayTransaction.php`

Only add a new typed Game service/DTO if the existing class boundaries cannot
carry the already-authoritative dimensional values. Do not add response
fields as a workaround for service coupling.

### Rule/Character owners to reuse or minimally adapt

- `www/mifrial/modules/Roleplay/Rule/Spec/ItemWeapons.php`
- `www/mifrial/modules/Roleplay/Rule/Dto/Spec/Item/WeaponProfile.php`
- `www/mifrial/modules/Roleplay/Rule/Spec/ItemModifierOperations.php`
- `www/mifrial/modules/Roleplay/Rule/Spec/ItemModifierOperationItems.php`
- `www/mifrial/modules/Roleplay/Rule/Service/FormulaEvaluations.php`
- `www/mifrial/modules/Roleplay/Character/Service/CharacterFormulaContexts.php`
- `www/mifrial/modules/Roleplay/Character/Interface/Service/ICharacterCombatLayers.php`
- `www/mifrial/modules/Roleplay/Character/Dto/CharacterCombatLayer.php`

Do not move Rule formula ownership into Game and do not make Character own
combat orchestration.

### Tests

- `Roleplay/Rule/tests/ItemModifierOperationTest.php`
- `Roleplay/Rule/tests/FormulaEvaluationTest.php`
- `Roleplay/Rule/tests/DimensionalNumberTest.php`
- `Roleplay/Game/tests/GameStrikeMysqlTest.php`
- `Roleplay/Game/tests/GameWideStrikeMysqlTest.php`
- a new pure Game arithmetic/layer test if existing tests cannot isolate
  kind-preserving penetration;
- a new Character/NPC parity fixture only if current fixtures do not cover
  identical authoritative documents.

No frontend files, roadmap/readiness/status files, TR/AGENTS files or existing
review documents are in scope.

## 10. Acceptance matrix

### Authority and selected instance

- base penetration is read from the live `WeaponProfile` formula;
- formula is evaluated with the attacker's authoritative Character context;
- selected `itemInventoryId` must resolve exactly one equipped instance;
- same rule code in two inventory rows does not collapse instance identity;
- Character and NPC with equal sheet/choices produce equal penetration;
- modifier references are read from the selected instance;
- modifier changes penetration without changing damage;
- multiple applicable modifiers follow Rule source/order semantics;
- no hardcoded item, damage-type or mechanic rule code is used;
- no frontend-only formula or client-provided penetration is accepted.

### Dimensional arithmetic

- base penetration;
- modified penetration;
- different penetration/defense sizes align to the smaller size;
- zero penetration is a no-op;
- negative raw penetration increases effective defense only according to the
  dimensional subtraction (`D - P`), never by scalar coercion;
- negative `D - P` floors to dimensional zero;
- penetration larger than defense yields zero effective defense;
- positive result preserves native dimensional representation;
- scalar `toInteger()` is not called by the penetration path;
- malformed formula/context is `GAME_INVALID`, not zero or fallback.

### Defense/resistance invariants

- only defense layers are reduced;
- resistance layers remain byte-for-byte/equivalently dimensionally
  invariant;
- source collapse does not allow a resistance layer to be removed by a
  defense layer with the same source;
- damage remains unchanged for equal hit rating and unchanged profile damage;
- injury changes only through effective defense/resistance;
- `defense_ignored` removes defense before penetration and leaves typed
  resistance unchanged;
- reliability cut and durability filtering happen before penetration;
- a layer removed by cut is not reintroduced by penetration.

### Pipeline and failures

- block defense/reliability/cut/source collapse/penetration/resistance/damage/
  injury order is observable in pure tests;
- failed defender block does not add block layers;
- successful defender block includes its defense/resistance layers before
  penetration;
- attacker auto-fail does not evaluate penetration or read layers;
- explicit ignore does not evaluate penetration;
- insufficient-resource automatic ignore does not evaluate penetration;
- accepted single and wide attacks use the same formula;
- wide target layers and reliability are independent per target.

### Result, replay, stale and rollback

- current single JSON shape is unchanged;
- current wide `targetResults[]` shape is unchanged;
- `resistance` equals post-penetration effective resistance;
- no unapproved raw/effective penetration fields are added;
- same-key/same-body replay returns byte-equivalent stored result;
- replay does not evaluate formula, modifier, reliability, projection, roll or
  mutation a second time;
- same-key/different-body remains `GAME_CONFLICT`;
- stale battle/sheet is rejected before penetration and leaves state unchanged;
- mutation-time Character CAS conflict rolls back strike/result/effect;
- mutation-time NPC CAS conflict has the same rollback semantics;
- wide late target CAS failure rolls back earlier target mutations and does
  not persist a partial penetration result;
- Character and NPC conflict/replay paths preserve the same result semantics.

## 11. Explicit exclusions

This plan does not implement or decide:

- further production reliability handler/content or mechanic catalog delivery
  beyond the confirmed dependency;
- defender-check redesign beyond consuming the accepted P3 result;
- action-point/resource implementation from P2b;
- frontend formulas, UI fields or frontend result interpretation;
- new combat JSON fields;
- new tables, columns or persistent penetration state;
- armor durability depletion;
- wounds/DOT/battleground;
- roadmap, readiness, status, TR or existing review documents.

## Final decision

**READY FOR ONE PENETRATION IMPLEMENTATION SESSION**

The production reliability contract and its live content path are confirmed,
and the dependency is closed for penetration. Run one backend implementation
session that evaluates the live,
modifier-adjusted dimensional penetration in Game, applies it only to defense,
preserves resistance/damage invariance, keeps the current result JSON, and
proves single/wide, Character/NPC, selected-instance, replay, stale/CAS,
rollback and auto-fail acceptance.
