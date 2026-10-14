# Implementation plan: `game-plan-29` — физические профили атаки

Дата inventory/плана: 2026-10-11  
Режим documentation-only closure: 2026-10-11. Production-код, тесты и
frontend не изменялись и не запускались.

## Verdict

**APPROVED FOR THE ACCEPTED BACKEND SCOPE; NOT A PROJECT-WIDE APPROVAL.**

Backend Game 29 принят для scope ниже. Прежний verdict
`READY FOR ONE GAME-29 IMPLEMENTATION SESSION` и контракт далее в файле
сохранены как decision trace и не являются текущим статусом.

## Final closure record

- **Backend Game 29 — APPROVED** для принятого scope: `strike`, `throw`,
  `shoot` внутри границ Game 28, identity профиля и выбранного экземпляра,
  атомарный расход бросаемого предмета и боеприпаса, Character/NPC parity,
  single/wide, CAS, replay и rollback.
- **Exclusions.** Cover; координаты, карта и battleground; authority
  дальности и falloff; Game 30, Game 31, Game 32 и Game 33. Rule-owned
  distance/range/falloff остаются информативными и не становятся боевым
  отказом или модификатором.
- **Evidence.** `GamePhysicalProfileMysqlTest` 13 tests / 768 assertions;
  Game 28 strike/wide 26 tests / 1608 assertions; полный suite ранее
  794 tests / 11776 assertions с одним environment-only Redis skip.
  В этой documentation-only сессии PHPUnit и frontend не запускались.
- **Следующий этап.** Game 30 остаётся отдельным этапом со статусом `TODO`
  и не переводится в `IMPLEMENTED`.
- Статусный канон — [`../tr/game-plan-29.md`](../tr/game-plan-29.md).
  Очередь — [`../tr/game-roadmap.md`](../tr/game-roadmap.md).

Это закрытие backend принятого scope, не project-wide approval и не
подтверждение frontend → PHP → delivery E2E или deployment/live
verification.

## Historical contract verdict

**READY FOR ONE GAME-29 IMPLEMENTATION SESSION** — historical trace.

Девять product decisions и четыре technical details по owner/semantics
закрыты ниже. Production-код, тесты и frontend в contract-closure сессии не
изменялись и не запускались.

## Current state

### Подтверждённые Rule capabilities

В live Rule `ItemSpec` уже есть:

- `weapon` (`WeaponBlock`) с `min_strength`, `weapon_profiles`,
  `durability`, `min_action_cost`;
- `shield` (`ShieldBlock`) с `min_strength`, `durability`,
  `weapon_profiles`;
- общие поля предмета: category, weight, proficiency family, hands,
  advantages/check advantages, modifiers, block profile, armor/resistance;
- `block_profile` с dimensional efficiency/defense/resistances.

`WeaponProfile` уже является единым DTO для трёх profile types:
`strike`, `throw`, `shoot`. Его поля:

```text
type
distance: DimensionalFormula
range: DimensionalFormula|null
damage: WeaponDamage(formula, damageTypeCode|null)
penetration: DimensionalFormula
accuracy: DimensionalNumber
actionCharacteristics[]
falloff: DimensionalNumber|null
dodgeBenefit: int|null
```

`ItemWeapons::profiles()` и `ItemSpecs` парсят профили из
`weapon.weapon_profiles` и `shield.weapon_profiles`. Effective modifiers
могут переписать damage/penetration/action-related значения через
`ItemModifierOperationItems`; отдельного server-side profile identity кроме
`(itemInventoryId, itemRuleCode, profileType, profileIndex)` нет.

### Подтверждённые Game capabilities

- `GameStrikeBody` и `GameWideStrikeBody` уже принимают
  `profileType`, `profileIndex`, `itemInventoryId`, `itemRuleCode`,
  `actionRuleCode`, attacker и defender/targets.
- Backend ограничивает profile type в open-row до `strike|throw|shoot`.
- Выбранный inventory instance проверяется как единственная equipped row;
  `itemRuleCode` используется как consistency check.
- Character берётся через `ICharacters`; NPC — из authoritative
  `npc.version`; оба документа имеют `choices` и `sheet`.
- `ICharacterFormulaContexts` и `IFormulaEvaluations` уже переиспользуются
  для dimensional damage/penetration и Character/NPC parity.
- `GameCheckRoll` выполняет live hit `CheckSpec`, mechanic bindings,
  dimensional roll/rating и `{0|-1}` combat auto-fail.
- Game 28 уже закрыл defender block gate, reliability, penetration,
  resource/AP, single/wide result shape, CAS, replay, rollback и delivery
  для существующего strike pipeline.
- Single result уже содержит `battleId`, `strikeId`, `version`,
  `attackerRoll`, optional `defenderRoll`, `success`, dimensional
  `damage/resistance/injury`, optional `S`, `sheetVersion`.
- Wide result содержит общий `attackerRoll` и `targetResults[]`; каждая
  target result имеет target, code, success, dimensional
  `damage/resistance/injury`, optional `S`, `defenderRoll`,
  `sheetVersion`.
- Delivery остаётся сигналом уже принятой команды; replay сверяет весь
  request body, а mutation и battle/entity CAS выполняются в общей
  transaction boundary.

### Что Game сейчас предполагает только как один общий тип атаки

Несмотря на transport discriminator, single и wide PHP flow используют один
общий путь:

```text
declare strike
  → assert live action/item/profile/inventory
  → spend attacker resource
  → persist open row
  → resolve attacker hit roll
  → resolve optional defender block roll
  → check reaction resource
  → auto-fail gate
  → damage
  → resistance/penetration/reliability/block layers
  → dodge soak
  → injury
  → Character/NPC CAS mutation
  → close strike, advance battle, persist command, delivery signal
```

`GameStrikeRules::profile()` выбирает профиль по item rule code, type и
index; `rollHit()` различает mastery по profile type, но не применяет
`distance`, `range`, `cover` или `falloff`. `evaluateDamage()` также
получает item rule/profile identity без `itemInventoryId` и не является
effective-instance profile evaluator; это gap для случаев, когда modifier
меняет физические характеристики профиля. `evaluatePenetration()` уже
использует selected inventory instance.

`GameStrikes` и `GameWideStrikes` не имеют authoritative distance/position
input. Таблицы `game_strike` и `game_wide_strike` сохраняют profile/item
identity, но не distance, coordinates, cover, range resolution или
falloff outcome. Wide path разделяет один attacker roll/damage и считает
защитные значения target-by-target.

Frontend-аудит подтвердил, что `distanceIpari: number` является ошибочной
legacy-моделью для общего combat contract: она противоречит размерным
формулам `WeaponProfile.distance`/`range`, `DimensionalNumber` и уже
существующим `MovementDistanceExpression`. Этот scalar не является
каноническим backend-типом и не должен переноситься в Game 29 как новый
authoritative contract.

## Authoritative ownership

| Concern | Current owner/capability | Game 29 status |
|---|---|---|
| Live profile schema | Rule `WeaponBlock`/`WeaponProfile`, `ItemSpec`, effective modifier path | Exists; ammunition block extension required |
| Keyword catalog | Keyword `IKeywords`/live keyword records | Existing ID↔code resolution reused for ammunition requirements |
| Selected item identity | Game input/open row + authoritative `choices.inventory` | Exists for current strike path |
| Character sheet/context | Character `ICharacters`, `ICharacterFormulaContexts` | Reuse |
| NPC sheet/context | Game `npc.version` / `GameNpcRepository` | Reuse |
| Hit roll and rating | Game `GameCheckRoll` + live `CheckSpec`/Mechanic | Reuse; no target-distance authority in Game 29 |
| Distance/positions | No authoritative owner; dimensional value is Rule/domain data | Informative only in Game 29; battleground deferred |
| Cover | Frontend/domain/mock only; no authoritative PHP Game field | Excluded from Game 29 |
| Falloff | Rule field exists; frontend has range service; PHP resolver absent | Informative only; authoritative application deferred |
| Damage/penetration | Game + Rule formula ports | Extend without bypassing Game 28 |
| Resistance/block/reliability | Game 28 accepted Game/Character/Mechanic paths | Boundary must remain stable |
| Actual mutation | Character mutation port / Game-owned NPC CAS | Reuse |
| Battle/replay/CAS | Game battle/command repositories and `GameReplayTransaction` | Reuse |
| Delivery | `game_delivery` signal/outbox boundary | Reuse |

Character must not import Game, store battle coordinates, or calculate
cover/range. Rule must provide profile data and formula semantics, not collect
target state or execute Game block/cover decisions. Game owns the action
orchestration and authoritative acceptance of transport/context.

## Exact contracts to freeze before implementation

These are the intended contract boundaries, not silently accepted values.
Where a value is not already authoritative, the item is listed again under
the decision record or the remaining technical decision below.

### Shared attack identity and input

Authoritative attack identity is:

```text
(gameId, sessionId, battleId,
 attacker(type,id), defender/targets(type,id),
 actionRuleCode,
 itemInventoryId,
 itemRuleCode,
 profileType,
 profileIndex,
 ammunitionInventoryId?  (required for shoot))
```

The server must resolve the live Rule profile from the selected inventory
instance, verify its live rule code, equipped status and profile type/index,
and never accept client damage, penetration, range, falloff, cover result,
hit rating, or final difficulty as authoritative.

Existing `itemInventoryId`, `itemRuleCode`, `profileType`, and
`profileIndex` remain backward-compatible for `strike`. No target-distance,
cover or falloff context is added. `shoot` may add a required
`ammunitionInventoryId`; it is an inventory identity, not a client result.

### `strike`

- **Authoritative input:** existing attack identity and action/resource
  proposal; no distance/cover input is required unless a future Rule
  contract explicitly makes melee distance authoritative.
- **Live Rule owner:** selected effective `WeaponProfile` with
  `type=strike`; hit identity remains the live hit `CheckSpec` resolved by
  Game/Mechanic.
- **Selected inventory/profile:** one equipped inventory row, its live
  `ruleCode`, effective modifiers, and `(profileType, profileIndex)`.
- **Character/NPC context:** attacker `sheet` and `choices`; defender
  `sheet` and `choices`; Character and NPC must use the same formula,
  block, reliability and penetration semantics.
- **Distance/range:** no target-distance resolver or availability gate is
  part of Game 29. Rule-owned profile distance/range remains informative.
- **Cover:** no authoritative cover semantics; cover is excluded from
  Game 29 and is not defaulted into combat.
- **Falloff:** remains informative and must not affect `strike` in Game 29;
  the current Rule field alone does not establish an authoritative effect.
- **Ordering:** preserve Game 28 ordering: validate selected profile and
  action/resource eligibility; resolve attacker roll and dimensional
  auto-fail; resolve defender block according to accepted block gate;
  resolve the existing hit result without a target-distance/cover gate;
  calculate damage, penetration, resistance/reliability, soak, injury;
  mutate and close atomically. The exact insertion point for the new
  range/cover resolver is excluded from Game 29.
- **Resource/AP:** existing live action/resource resolver; attacker spend
  once at declaration, defender reaction spend in close, insufficient
  resource preserves existing automatic-ignore result and no mutation.
- **Single/wide:** `1→1` and `1→N` remain supported; wide has one
  attacker spend/roll and independent target results.
- **Invalid/missing/malformed:** preserve `GAME_INVALID` for missing live
  action/profile, selected instance, malformed authoritative sheet/choices,
  invalid Rule/Mechanic binding, or impossible transport. No fallback to
  client values.
- **Replay/CAS/rollback:** preserve request-body fingerprint, same-key
  replay, changed-body conflict, battle/entity CAS, all-or-nothing wide
  close, and rollback of resource/effect/strike/battle/command.
- **Result/delivery:** preserve existing single/wide result envelope and
  delivery signal. No physical calculation fields are added.

### `throw`

- **Authoritative input:** same attack identity; no authoritative target
  distance or cover context is required in Game 29.
- **Live Rule owner:** selected effective `WeaponProfile` with
  `type=throw`; profile `distance`, `range`, `falloff`, `damage`,
  `penetration`, `accuracy`, and action characteristics are Rule-owned.
- **Selected inventory/profile:** `itemInventoryId` is the selected thrown
  item and is also the consumed item. No separate projectile identity
  exists.
- **Character/NPC context:** reuse formula contexts and authoritative
  inventory/resource documents. Do not infer projectile state from frontend
  labels or a rule code alone.
- **Distance/range/falloff:** Rule-owned values remain informative and do
  not gate availability or modify the Game 28 hit path in Game 29.
- **Ordering:** validate transport/profile/selected item; resolve
  attack/defense interaction using the common Game 28 procedure; preserve
  dimensional roll/auto-fail; then execute Game 28
  block/reliability/resource/resistance/mutation ordering.
- **Resource/AP:** action cost uses live action/resource Rule. The selected
  thrown item is decremented atomically with AP before combat mutation; a
  missing item blocks the action.
- **Single/wide:** one selected thrown item is consumed for the whole wide
  attack.
- **Invalid/missing/malformed:** missing, duplicate, malformed,
  unequipped or zero-quantity selected item rejects before mutation.
- **Replay/CAS/rollback:** item/resource/entity writes must be in the same
  transaction and request-body replay boundary; rollback must remove every
  partial consumption.
- **Result/delivery:** preserve the existing result envelope and delivery
  signal; no physical breakdown fields are added.

### `shoot`

- **Authoritative input:** same attack identity plus required
  `ammunitionInventoryId` for `shoot`.
- **Live Rule owner:** selected `WeaponBlock` plus its effective
  `WeaponProfile` with `type=shoot`; the `WeaponBlock` owns
  `ammunition_requirements`, while the profile owns
  range/falloff/accuracy/damage/penetration and action characteristics.
  Rule owns compatibility semantics, but not mutable stock.
- **Selected inventory/profile:** distinguish the equipped weapon instance,
  selected profile, and ammunition inventory instance. Current
  `itemInventoryId` identifies only the selected attack item.
- **Character/NPC context:** attacker choices/sheet and NPC/Character parity
  as in Game 28; ammunition must be resolved from authoritative Game or
  Character storage, not from a UI count.
- **Distance/range/falloff:** Rule-owned values remain informative and do
  not gate availability or modify the Game 28 hit path in Game 29.
- **Ordering:** validate weapon/profile/ammunition; resolve attack and
  defense interaction; then apply Game 28 block, reliability,
  penetration, resource, resistance, mutation, CAS and delivery path.
- **Resource/AP cost:** AP action cost and one ammunition decrement happen
  atomically at action declaration, including miss/block/dodge/auto-fail.
- **Single/wide:** one ammunition spend applies to the whole wide attack.
- **Invalid/missing/malformed:** absent, duplicate, zero-quantity or
  incompatible ammunition, or broken Rule, rejects before mutation.
- **Replay/CAS/rollback:** ammunition/AP, weapon state, Character/NPC
  mutation, battle close and command result must share the existing
  idempotent transaction boundary.
- **Result/delivery:** preserve current result envelope; Game 29 adds no
  range/cover/ammo audit fields.

## Dependency order

1. **Rule/profile contract** — use existing dimensional profile fields and
   typed process-distance DTOs; add the approved explicit ammunition
   compatibility block to the live `WeaponBlock`.
2. **Authoritative input transport** — add only `shoot`'s selected
   `ammunitionInventoryId`; do not add target distance, cover, position,
   falloff result or new strike-table fields. Preserve existing `strike`
   bodies.
3. **Character/NPC formula/context** — expose only reusable formula
   parameters/context needed by profile strength, distance and falloff;
   retain Character/NPC parity and do not move Game state into Character.
4. **Game profile/resource services** — implement selected-instance
   ammunition validation and atomic item decrement; do not implement
   target-distance, cover or authoritative range/falloff resolvers.
5. **Strike orchestration** — integrate `strike`, `throw`, and `shoot` in
   `GameStrikeRules`, `GameStrikes`, and shared profile/context services;
   preserve Game 28 order and transaction boundaries.
6. **Single/wide acceptance** — verify 1→1 and 1→N semantics, one
   ammunition/item decrement per attack, shared attacker values and
   Character/NPC parity.
7. **Replay/CAS/rollback** — bind every new authoritative field and any
   projectile/ammunition mutation to request fingerprint, CAS, atomic
   rollback, stale handling and idempotent result.
8. **Delivery/E2E boundary** — only the backend delivery contract is in
   this plan; full frontend→PHP→delivery E2E remains excluded and belongs
   to Game 33.

## Files likely in scope

### Rule

- `www/mifrial/modules/Roleplay/Rule/Dto/Spec/Item/WeaponProfile.php`
- `www/mifrial/modules/Roleplay/Rule/Dto/Spec/Item/WeaponBlock.php`
- `www/mifrial/modules/Roleplay/Rule/Dto/Spec/Item/ShieldBlock.php`
- `www/mifrial/modules/Roleplay/Rule/Dto/Spec/Item/ItemSpec.php`
- `www/mifrial/modules/Roleplay/Rule/Spec/ItemWeapons.php`
- `www/mifrial/modules/Roleplay/Rule/Spec/ItemSpecs.php`
- `www/mifrial/modules/Roleplay/Rule/Spec/ItemModifierOperationItems.php`
- `www/mifrial/modules/Roleplay/Rule/Spec/ProcessDistances.php`
- `www/mifrial/modules/Roleplay/Rule/Service/FormulaEvaluations.php`
- `www/mifrial/modules/Roleplay/Keyword/Interface/Service/IKeywords.php`
- `www/mifrial/modules/Roleplay/Keyword/Service/Keywords.php`
- relevant `Rule` validation/serialization and live profile tests, only if
  the decisions require a Rule contract change.

### Character/Mechanic formula boundary

- `Character/Interface/Service/ICharacterFormulaContexts.php`
- `Character/Interface/Service/ICharacterRuleSlices.php`
- formula context/evaluation implementations and dimensional value helpers
- `Game/Service/GameCheckRoll.php` and, if required by the accepted
  precedence, live CheckSpec/Mechanic roll boundary

### Game transport, persistence and orchestration

- `Game/Service/GameStrikeBody.php`
- `Game/Service/GameWideStrikeBody.php`
- `Game/Service/GameStrikes.php`
- `Game/Service/GameWideStrikes.php`
- `Game/Service/GameWideStrikeOpening.php`
- `Game/Service/GameWideStrikeResults.php`
- `Game/Service/GameWideStrikeCommit.php`
- `Game/Service/GameStrikeRules.php`
- `Game/Service/GameActionResourceResolver.php`
- `Game/Service/GameReplayTransaction.php`
- `Character/Interface/Service/ICharacterActualMutations.php`
- `Character/Service/CharacterActualMutations.php`
- `Game/Repository/GameNpcRepository.php`
- `Game/Table/GameStrikeTable.php`
- `Game/Table/GameWideStrikeTable.php`
- command/target repositories and schemas if approved fields must be
  persisted; do not add columns merely to mirror frontend state.

### Tests for a later implementation session

Likely focused coverage:

- Rule profile parsing/effective modifier and malformed profile cases;
- typed profile/resource validation, ProcessDistance evaluation and malformed
  Rule cases;
- strike/throw/shoot single and wide;
- Character/NPC parity;
- block/auto-fail/reliability/penetration/resource precedence;
- selected inventory/profile/projectile/ammunition identity and live
  weapon/ammunition compatibility;
- stale CAS, rollback, replay and delivery signal.

Tests are listed as future scope only; none were changed or run for this
plan.

## Acceptance matrix

| Area | Acceptance requirement | Current status |
|---|---|---|
| Rule profile | Live `strike`, `throw`, `shoot` parse to authoritative profiles and weapon ammo requirements | Partial: DTO/parser exists; compatibility extension required |
| Effective instance | Modifiers apply only to selected equipped instance/profile | Partial: selection exists; damage path needs instance-aware review |
| Transport | Shoot ammo identity is typed; no target-distance/cover fields | Contract defined; body changes required |
| Distance | Rule-owned dimensional profile data; no target authority in Game 29 | Confirmed informative-only |
| Range | No Game 29 availability gate | Confirmed deferred |
| Cover | No Game 29 authority or modifier | Confirmed excluded |
| Falloff | No Game 29 authoritative application | Confirmed deferred |
| Strike | Existing melee contract remains backward-compatible | Accepted |
| Throw | Selected item identity and atomic consumption | Contract defined; implementation missing |
| Shoot | Selected ammo identity, keyword requirements and atomic consumption | Contract confirmed; implementation missing |
| Hit ordering | No distance/cover/falloff gate bypasses Game 28 | Accepted boundary |
| Block | Game 28 defender gate and block layers remain unchanged unless explicit decision | Accepted boundary |
| Reliability | Existing live capability and malformed fail-closed behavior remain | Accepted boundary |
| Penetration | Dimensional penetration applies only at existing defense boundary | Accepted boundary; no Game 29 falloff change |
| Resources | AP/reaction/item spend remains atomic | AP/reaction accepted; item implementation missing |
| Character/NPC | Same resolver and formula result for equivalent documents | Existing parity path; item mutation extension required |
| Single | 1→1 result/mutation/CAS/replay unchanged except approved fields | Existing boundary |
| Wide | 1→N shared attacker and one item spend per attack | Existing boundary; item spend extension required |
| Invalid input | Missing/malformed/ambiguous ammo/item reject before mutation | Existing pattern; implementation extension required |
| Replay | Same key/body replay; changed body conflicts on ammo identity | Existing boundary; transport extension required |
| Rollback | Late CAS/resource/item failure rolls back all new writes | Existing boundary; item mutation extension required |
| Delivery | Accepted command emits existing delivery signal; no E2E claim | Backend boundary exists |

## Explicit Game 28 boundaries

Game 29 must not:

- replace or weaken authoritative selected-instance validation;
- accept client damage, penetration, success, difficulty or final
  resistance as a result;
- change dimensional roll/rating or auto-fail `{0|-1}` semantics;
- bypass defender block eligibility, block roll, block-layer gate,
  reliability or penetration ordering;
- move resource/AP spend outside the established transaction;
- weaken Character/NPC parity, entity CAS, battle CAS, replay,
  rollback or delivery-after-commit behavior;
- change the accepted single/wide result shape without a separate contract
  decision;
- turn frontend/mock range or cover calculations into implicit authority.

## Explicit exclusions

- Game 30 immediate consequences.
- Game 31 wounds.
- Game 32 turn end, continuation and DOT.
- Game 33 frontend → PHP → delivery E2E.
- Map, authoritative coordinates implementation and full battleground.
- `ISpatialResolver` and полноценный battleground.
- Deployment, live-environment verification and production UI verification.
- Reopening or changing the already closed Game 28 contract.
- N→1 and N→N attack shapes.
- Spell/cast, magic perception and unrelated economy features.
- Creating a second Character sheet or storing a full sheet in Game overlay.

## Decision record

The following decisions were explicitly confirmed after review of the
frontend/domain model. They are recorded here and must not be silently
reinterpreted during implementation.

### Confirmed decisions

- Distance is a general `DimensionalNumber` (`{base|size}`), not a scalar
  `ipari` value. `1 ipari` is normally `{1|0}` and need not be stored as a
  separate unit.
- A character's movement step is character data and is itself a dimensional
  value. Movement expressions may use step counts, current step, size gaps,
  size changes and composition. A future step formula may be wrapped by
  dimensional formulas; no second scalar distance system is introduced.
- Coordinates, positions, battleground ownership and target availability are
  outside Game 29. The future battleground may become the authoritative
  source of distance without changing the dimensional value contract.
- Cover is completely outside Game 29. No cover authority, formula,
  persistence or combat modifier is added in this game.
- Range and falloff are not availability gates in Game 29. Weapon distance
  and range may be shown informatively (including in chat/roll breakdown),
  but Game 29 does not reject an attack based on target distance.
- For `throw`, `itemInventoryId` identifies the thrown item. No separate
  projectile inventory identity is introduced.
- For `shoot`, ammunition is a selected inventory instance. One accepted
  throw or shot consumes one ammunition item at the same atomic point as AP
  spend. The defender never spends ammunition.
- A `1→N` wide attack consumes one ammunition item for the whole attack, not
  one item per target. Missing ammunition rejects the action before any AP or
  other resource mutation.
- The existing Game 28 defender block procedure remains common to
  `strike`, `throw` and `shoot`. Any future authoritative cover modifier is
  conceptually before block resolution and is not removed by a failed block,
  but cover itself is not implemented in Game 29.
- The existing JSON/result envelope remains unchanged. Physical calculation
  fields are not added to frontend delivery or persisted strike tables in
  Game 29. Existing request-body replay identity remains authoritative.
- Existing `strike` transport remains backward-compatible. New context is
  required only for `throw`/`shoot` if a later authoritative ranged contract
  requires it; absent ranged context is not silently treated as a spatial
  zero.

### Frontend compatibility finding

The frontend currently has two different distance models:

- movement uses dimensional `MovementDistanceExpression` and
  `DimensionalNumber`;
- ranged hit helpers use `distanceIpari: number` and scalar falloff bands.

The latter is a frontend defect/legacy compatibility path, not a model to
copy into PHP. Chat can show Rule-owned weapon distance/range information,
but this does not make the value an authoritative target distance.

## Technical contract closure

The four remaining technical details are closed as follows. These are
implementation constraints, not new product choices.

### 1. Informative target distance — `DECISION_CONFIRMED — excluded from Game 29 authority`

Game 29 does not add target distance to authoritative attack transport,
`game_strike`/`game_wide_strike` open rows, result JSON or combat
persistence. Rule-owned weapon `distance`/`range` may still be rendered in
chat or UI as profile information. This does not assert the actual distance
to the target and does not affect validation, hit, block, damage, resource
or mutation resolution.

**Owner.** Rule owns weapon profile distance/range. A future battleground
owns actual target distance. Future chat/delivery/E2E may expose an
informative target-distance field, but that is outside Game 29 and is not
an authority input.

**Transport/storage.** No new target-distance field, open-row field,
result key, strike-table column or persistence record. Frontend
`distanceIpari: number` must not be copied into PHP.

**Invalid/replay/CAS/rollback.** There is no Game 29 target-distance value
to validate, fingerprint, CAS or roll back. Existing request-body replay,
battle/entity CAS and rollback remain unchanged. A future delivery-only
field must be optional and must not change the command fingerprint or
combat result.

**Acceptance.** Existing `strike` requests replay identically; no attack is
accepted or rejected by target distance; Rule profile distance/range can be
displayed without becoming combat authority; Character/NPC and single/wide
results remain identical.

**Exclusions.** Coordinates, LOS, availability, map range checks,
authoritative target-distance transport and frontend E2E.

### 2. Step-based weapon formulas — `DECISION_CONFIRMED`

Step-based values must reuse the typed dimensional model. The backend
already has the typed `ProcessDistance` DTO family and `ProcessDistances` parser
(`steps`, `literal`, `current_movement_step`, `size_gap_times_step`,
`change_size`, `add`); the frontend has the corresponding
`MovementDistanceExpression` shape. The repository contains the parser and
dimensional formula evaluator, but no backend evaluator for
`ProcessDistance` was found; that conversion boundary must be implemented
inside the Rule/Character context boundary. No scalar step system and no
backend `distanceIpari` authority are introduced.

**Owner.** Rule owns the backend `ProcessDistance` DTO/parser and formula
semantics. Character owns the current movement-step value and
characteristic context.

**Current implementation gap.** `WeaponProfile::getDistance()` and
`getRange()` currently return `DimensionalFormula`, while
`ProcessDistance` is a separate DTO family used by movement operations.
The implementation must extend the profile DTO/parser to support the
confirmed `ProcessDistance` union.

**Confirmed representation.** Weapon profile `distance`/`range` may use
the existing typed `ProcessDistance` family. The Rule/Character context
boundary evaluates it to a dimensional value before Game consumes it.
`WeaponProfile` DTO/parser validation must explicitly support this union;
Game must not interpret step arithmetic.
The Rule/formula evaluator is the conversion boundary:

```text
Rule ProcessDistance
  + Character dimensional context
  → DimensionalNumber
  → Game action/profile resolver
```

Movement uses the resolved dimensional distance. A weapon profile may use
an equivalent typed dimensional expression; Game receives only the
resolved dimensional value and does not interpret step arithmetic.

**Transport/storage.** Movement expression and Rule profile formula remain
Rule/DTO data. Resolved step values are not added to strike result JSON or
strike tables. `WeaponProfile.distance`/`range` accept the confirmed typed
`ProcessDistance` representation in addition to their existing dimensional
formula path.

**Malformed/null/zero.**

- malformed expression, unknown operator, invalid child, missing required
  characteristic or invalid dimensional value → `GAME_INVALID` before
  mutation;
- `null` means an optional Rule field is absent; it is not silently
  converted to zero;
- zero step count/multiplier and `{0|0}` are valid dimensional zero values;
- zero movement request remains invalid under the existing movement
  contract, while zero informative weapon distance is not a Game 29
  availability decision;
- `null` falloff remains absent and does not become a default client value.

**Replay/CAS/rollback.** Same-body replay returns the
same result, changed-body replay conflicts, and any late CAS failure rolls
back the associated action mutations. No new result field is required.

**Acceptance.** Current movement expressions evaluate
with character steps;
size changes and composition preserve dimensional shape; a half-step and
multiple steps remain dimensional values; equivalent Character/NPC
contexts resolve identically; malformed expressions fail closed.

**Exclusions.** Scalar `ipari` conversion, frontend helper semantics,
automatic target availability, map geometry and new weapon formula syntax
without a Rule contract change.

### 3. Inventory quantity zero — `DECISION_CONFIRMED`

The existing Character actual mutation contract accepts non-negative
quantity and retains the inventory row when quantity becomes zero.
Game 29 adopts that contract: consumption is an atomic decrement by one;
the row is retained with `quantity = 0`, not deleted.

**Owner.** Game owns the atomic combat command and spend ordering.
Character actual storage owns player inventory through
`ICharacterActualMutations`; NPC storage uses the transaction-bound Game
NPC mutation path and `npc.actual_version`. Because existing quantity
patching is rule-code based, Game 29 requires a selected-instance-aware
typed mutation operation/adapter for the decrement; it must not merge
duplicate rows by `ruleCode`.

**Transport/storage.** `throw` uses the existing selected
`itemInventoryId`. `shoot` adds a required selected
`ammunitionInventoryId` to the request context; it is consumed at action
declaration together with AP, so no ammunition column is added to
`game_strike` or `game_wide_strike`. Quantity zero is observable only in
authoritative Character/NPC inventory state. No new combat JSON field is
added.

**Invalid/insufficient.**

- missing, duplicate, malformed or ownership-invalid selected row →
  `GAME_INVALID`;
- selected equipped attack item that is not equipped or has a rule-code
  mismatch → `GAME_INVALID`;
- selected ammunition row with `quantity <= 0` → `GAME_INVALID` as
  unavailable material resource;
- no AP or other ordinary resource → existing Game 28 insufficient-resource
  semantics;
- all failures occur before AP, ammunition or effect mutation.

**Replay/CAS/rollback.** AP decrement and item decrement occur in one
transaction boundary. Same request key and body returns the stored result
without a second decrement. Changed body conflicts before a second spend.
Stale Character `actual_version`, NPC `actual_version`, battle CAS or a
late wide-target failure rolls back both AP and item quantity. A wide
attack decrements once for the whole `1→N` command.

**Acceptance.** Quantity `2→1` succeeds; quantity `1→0` succeeds and keeps
the authoritative row; a subsequent attempt sees unavailable material;
wide consumes once; Character/NPC parity holds; duplicate selected rows
fail; replay, stale CAS and late rollback leave quantity unchanged when
the command is not committed.

**Exclusions.** Inventory-row deletion, projectile return, item transfer,
stack splitting, new combat result fields and a second global physical-item
identity.

### 4. Weapon/ammunition compatibility — `DECISION_CONFIRMED`

The repository has no existing ammunition compatibility field or relation
on the live PHP `WeaponProfile`, `ItemSpec`, or selected inventory DTO.
`WeaponProfile` currently contains distance, range, damage, penetration,
accuracy, action characteristics, falloff and dodge benefit only. The
implementation must add the approved compatibility block to the weapon
Rule spec.

**Confirmed owner and boundary.** The weapon spec owns a list of alternative
ammunition requirements. Each requirement contains a list of required
keyword codes:

```text
weapon.ammunition_requirements: [
  { keyword_codes: ["item", "projectile", "arrow"] }
]
```

The outer list is `OR`; `keyword_codes` within one requirement are `AND`.
The ammunition item does not receive a new marker field. Its existing live
Rule `keywordIds` are resolved through the Keyword catalog and must contain
all keyword codes in one accepted requirement.

Ordinary and fire arrows may both contain `["item", "projectile", "arrow"]`;
the fire arrow may additionally contain `fire-explosion`. The weapon accepts
the common required set, while the selected `ammunitionInventoryId`
determines the concrete consumed item.

An empty `ammunition_requirements` list means that the weapon does not
consume a separate ammunition item. `throw` therefore consumes its selected
`itemInventoryId` directly and has no separate ammunition relation.

Client labels, display names, generic categories and arbitrary keyword
intersections never decide compatibility.

**Legacy profile behavior.** An existing live `shoot` profile without
`ammunition_requirements` is invalid for authoritative shooting and returns
`GAME_INVALID` before mutation. No unrestricted-ammunition fallback is
allowed because current Rule/Game paths fail closed on broken or ambiguous
authoritative Rule data.

Client labels, display names and client-proposed categories must never
decide compatibility. `throw` has no separate ammunition relation because
the selected weapon/item is the consumed object.

**Owner.** Rule owns the compatibility declaration and live item/profile
types. Game owns selection, validation ordering and atomic spend.
Character/NPC own the authoritative selected inventory documents.

**Transport/storage.** The `shoot` request carries the selected
`ammunitionInventoryId` in addition to the weapon `itemInventoryId`.
The server resolves both live Rule records and the live Keyword catalog
before mutation. No ammunition identity is added to result JSON or strike
tables; the request body and existing command/replay boundary bind the
selection.

**Invalid outcomes.**

- missing or malformed ammunition identity → `GAME_INVALID`;
- missing or duplicate inventory row → `GAME_INVALID`;
- selected weapon not equipped → `GAME_INVALID`;
- selected ammunition row is not an eligible owned inventory row →
  `GAME_INVALID`;
- wrong live Rule, failed required-keyword set, wrong item type or malformed
  live Rule/keyword relation → `GAME_INVALID`;
- ammunition quantity zero → `GAME_INVALID` as unavailable material
  resource;
- quantity less than the required one item is never converted into a
  partial action.

All these checks precede AP, ammunition, defender reaction, effect and
Character/NPC mutations.

**Replay/CAS/rollback.** The weapon identity, profile identity and
ammunition identity are included in the request-body fingerprint. Same
body replays the committed result; changed ammunition or weapon identity
conflicts. Character and NPC paths use the same live Rule validation,
selected-instance semantics and atomic AP+item transaction. Any stale CAS
or late failure rolls back the full spend.

**Acceptance.** A weapon requirement set matches an ammunition Rule only
when all its keyword codes are present; alternative requirement sets use
OR semantics; ordinary and fire arrows can share the `arrow` requirement;
Character and NPC produce the same result; a different live ammunition
Rule is rejected even with a matching client label; missing, unequipped
weapon, duplicate row, wrong type, wrong Rule and zero quantity fail before
mutation; wide consumes one validated ammunition item; replay and rollback
are idempotent.

**Exclusions.** Client-side compatibility, display-name matching,
automatic ammo inference, per-target wide consumption, projectile identity
for throw and new combat result fields.

The compatibility owner, requirement shape, transport boundary and
validation ordering are closed. The requirement must not be replaced by
client labels, inferred categories or arbitrary keyword intersections.

The original alternatives are retained below as decision history. Resolved
items are marked `DECISION_CONFIRMED`; they are not new open questions.

### 1. Distance units and source — `DECISION_CONFIRMED`

Options:

- **A.** Server-owned battleground/grid distance in `ipari`; consequence:
  requires a coordinate/position owner and is outside the current
  battleground exclusion.
- **B.** Game-owned abstract combat distance per participant/target;
  consequence: implementable without a map, but requires new battle state,
  transitions and authoritative mutation semantics.
- **C.** Client-proposed scalar distance with server validation against
  existing state; consequence: compatible with UI, but unsafe until the
  validating state owner exists.

Must decide whether the unit is integer ipari, a dimensional value, or
another canonical unit, and whether distance is attacker→target,
attacker→covering defender, or both.

### 2. Coordinate/position owner — `DECISION_CONFIRMED — deferred`

Options:

- **A.** Game battle state owns positions; consequence: adds battle fields,
  CAS and later map migration.
- **B.** A dedicated spatial/battleground owner owns positions; consequence:
  violates the requested Game 29 no-map implementation boundary unless
  prepared as an external port.
- **C.** No coordinates in Game 29; only an already authoritative abstract
  distance input is allowed; consequence: range is deterministic only if
  that input has a trusted owner.

### 3. Cover formula and authority — `DECISION_CONFIRMED — excluded`

Options:

- **A.** Server derives cover from positions/line-of-sight; consequence:
  requires spatial state and a cover algorithm.
- **B.** Attacker proposes cover, defender may counter/propose, attacker
  approves; consequence: introduces a multi-step offer/process and frozen
  context.
- **C.** GM-authoritative cover is entered once; consequence: simpler
  authority, but adds GM input and persistence semantics.

Also decide whether cover is integer additive difficulty, dimensional
difficulty, or a Rule formula; whether it is clamped at zero; and whether
cover is shared or target-specific in wide attacks.

### 4. Falloff boundaries and dimensional ownership — `DECISION_CONFIRMED — deferred`

Options:

- **A.** Use frontend-like `falloff` as the number of distance units per
  size drop; consequence: aligns existing UI/domain code but needs a
  canonical PHP definition of zero, inclusive boundaries and minimum.
- **B.** Treat Rule `range` as free reach and `falloff` as post-reach step;
  consequence: clearer separation, but requires defining both formulas and
  their dimensional units.
- **C.** Falloff changes only damage; consequence: penetration and hit
  difficulty remain unchanged, simpler but physically asymmetric.
- **D.** Falloff changes damage and penetration (and possibly hit
  difficulty); consequence: closer to the frontend design, but changes
  Game 28 ordering and requires a precise pre/post-resistance contract.

Decide whether an out-of-range shot is invalid, receives a size penalty,
or can reach indefinitely with degradation; decide inclusive boundary at
exact `range`/`falloff` and the minimum allowed damage/penetration.

### 5. Throw projectile/item identity — `DECISION_CONFIRMED`

Options:

- **A.** `itemInventoryId` is the thrown object and is consumed/moved;
  consequence: requires authoritative inventory mutation and rollback.
- **B.** Weapon/tool row remains selected item and projectile is a separate
  required `projectileInventoryId`; consequence: explicit identity, more
  transport and persistence fields.
- **C.** Throw consumes only an abstract action resource; consequence:
  avoids inventory semantics but does not model item identity.

### 6. Shoot ammunition/resource semantics — `DECISION_CONFIRMED`

Options:

- **A.** Ammo is a selected inventory instance; consequence: new identity,
  quantity and atomic consumption.
- **B.** Ammo is a typed scalar resource Rule; consequence: reuses resource
  storage but needs weapon/ammo compatibility and zero-stock behavior.
- **C.** No ammo mutation in Game 29; consequence: allows profile/range
  work only, but cannot claim a complete authoritative `shoot`.

Decide consumption on miss, block, dodge, ignore, auto-fail, insufficient
ammo, and wide attacks.

### 7. Defender block interaction — `DECISION_CONFIRMED`

Options:

- **A.** Keep Game 28 block roll/layers unchanged for all profile types;
  consequence: minimal compatibility, but ranged block semantics may not
  match the product model.
- **B.** `throw`/`shoot` use a separate ranged defender procedure;
  consequence: new live Rule/Mechanic contract and result fields/ordering.
- **C.** Ranged block is shield-only but uses the existing block roll;
  consequence: reuses infrastructure, yet requires an explicit shield
  eligibility/profile contract.

Also decide whether cover is added before or after block result and whether
failed block still contributes any cover/resistance.

### 8. New JSON fields and persistence — `DECISION_CONFIRMED`

Options:

- **A.** Keep current result envelope; range/cover/falloff remain internal;
  consequence: backward-compatible but poor audit/UI explanation.
- **B.** Add optional fields such as authoritative distance, cover,
  range band, falloff step and ammo/projection identity; consequence:
  requires command result compatibility and delivery consumer review.
- **C.** Persist only a normalized calculation snapshot in command result,
  not in strike tables; consequence: replay/audit-friendly without
  changing open-row schema, but increases result contract.

No `currentSheet` or other rejected Game 28 conflict-envelope key may be
introduced.

### 9. Backward compatibility — `DECISION_CONFIRMED`

Options:

- **A.** Existing `strike` request remains valid and means zero/new-context
  defaults; consequence: easiest migration, but defaults must be explicitly
  authoritative and safe.
- **B.** Require new context for `throw`/`shoot` only; consequence:
  preserves old melee clients and makes ranged readiness explicit.
- **C.** Version the attack transport/procedure; consequence: clearest
  semantics, but requires version routing and replay-body compatibility.

## Implementation-session gate

A Game 29 implementation session may start only when:

1. the nine decision groups above remain consistent with the confirmed
   record and the remaining contract details are resolved;
2. Rule contract states the approved `WeaponBlock.ammunition_requirements`
   field and the existing/effective `WeaponProfile` dimensional fields;
3. distance/position/cover authority is available without silently
   introducing a map or battleground implementation;
4. throw projectile and shoot ammunition identity/transaction semantics are
   explicit;
5. ranged block ordering is written next to the accepted Game 28 boundary;
6. transport, open-row/result JSON, replay fingerprint and backward
   compatibility are approved;
7. single/wide, Character/NPC, malformed-input, stale/CAS and rollback
   acceptance cases are enumerated;
8. the implementation session can remain within the dependency order
   `Rule → transport → context → resolver → orchestration → acceptance →
   replay/CAS → delivery boundary`;
9. no implementation step claims frontend E2E, map support, deployment or
   changes to Game 30–33.

All implementation-gate decisions are now resolved. This document does not
claim that Game 29 is implemented, tested, deployed or verified through
frontend E2E.

## Contract closure verdict

**HISTORICAL TRACE: READY FOR ONE GAME-29 IMPLEMENTATION SESSION**

Текущий статус — раздел Verdict в начале файла:
**APPROVED FOR THE ACCEPTED BACKEND SCOPE; NOT A PROJECT-WIDE APPROVAL.**
PHPUnit, MySQL and frontend were not run during the contract-closure session
and were not run again during the documentation-only closure.
