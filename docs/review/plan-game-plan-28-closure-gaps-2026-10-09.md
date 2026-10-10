# Final closure/reconciliation review: `game-plan-28`

Дата финальной reconciliation: 2026-10-10
Режим: documentation-only. В этой сессии production-код, тесты, frontend,
battleground, wounds/DOT, review implementation artifacts,
roadmap/readiness/status и конфигурация не изменялись.

## Verdict

**CLOSED FOR THE ACCEPTED BACKEND SCOPES; NOT A PROJECT-WIDE APPROVAL.**

Финальная closure/reconciliation сессия сопоставила plan, game-plan-28,
накопленный diff/status и переданные оркестратором acceptance results.
Ниже зафиксированы только подтверждённые закрытия; прежний
implementation-plan ниже сохранён как исторический trace и не является
текущим verdict.

## Final closure record

### Закрытые статусы и acceptance evidence

Следующие backend scopes приняты оркестратором:

- **P2b resource spend — APPROVED.** Подтверждены typed scalar и
  dimensional resource values, strict shape/arithmetic, multi-component
  atomic spend, insufficient-resource automatic ignore без spend/effect/
  version mutation, Character/NPC parity, entity CAS, rollback, single/wide
  orchestration и idempotent replay. Wide path сохраняет one attacker spend,
  target-level insufficient-resource result и whole-close rollback при stale
  или поздней CAS-ошибке.
- **P3 defender/block — APPROVED.** Подтверждены selected defensive
  instance/profile, attacker и независимый defender roll, block
  success/failure, block-layer gate, single/wide parity, authoritative
  dimensional roll и integer rating, auto-fail `{0|-1}`, отсутствие
  auto-fail у `{0|0}`/`{0|3}`, replay и существующие stale/CAS/rollback
  boundaries.
- **P3 efficiency precedence — APPROVED.** Зафиксирован порядок
  `BlockProfile` для defender block roll → live `CheckSpec` efficiency →
  `RollMechanicPayload` → active mechanic pipeline; dimensional roll не
  сплющивается в scalar, а rating выводится после dimensional comparison.
- **Selected inventory instance — APPROVED.** `itemInventoryId` проходит
  single и wide action paths до resolver; выбирается ровно одна equipped
  row, её live `ruleCode` проверяется как consistency input, modifiers
  читаются только из выбранной instance. Missing, duplicate, not-equipped и
  rule-code mismatch отклоняются до mutation; Character/NPC semantics
  совпадают.
- **Reliability — ACCEPTED RUNTIME EVIDENCE.** Приняты переданные runtime
  результаты: **19/19 MySQL tests, 1229 assertions, failures=0**.
  Capability определяется через registered
  `reliability_cut@1.0.0`/`IReliabilityCut`; canonical `{}` binding,
  nullable durability, fail-closed malformed/missing binding behavior,
  Character/NPC parity и replay/CAS/rollback boundaries входят в принятый
  scope.
- **Penetration — APPROVED.** Приняты live selected-instance/profile path,
  dimensional penetration, применение только к defense, сохранение
  resistance/damage invariants, source-kind boundary, single/wide и
  Character/NPC parity. В acceptance отдельно включены auto-fail,
  CAS rollback, insufficient-resource ignore и replay boundaries; replay
  не повторяет formula/projection/mutation.
- **ChosenAmount — ACCEPTED single-step open-path contract.** Frontend
  amount является proposal; backend валидирует его по live Rule, передаёт
  mutation только typed `ResourceSpend`, выполняет atomic spend/effect/CAS
  на open path и использует request body как idempotency fingerprint.
  Same key + same body возвращает сохранённый result; changed amount даёт
  `GAME_CONFLICT` до повторного spend. Persisted frozen amount не требуется
  для single-step flow.
- **Conflict envelope — APPROVED accepted flat external shape.**
  Внешняя форма — `{currentVersion, choices, sheet}`; внутреннее имя
  `currentSheet` не является внешним ключом. Legitimate key-reservation
  conflicts могут содержать только `currentVersion` либо не иметь version.

Эти статусы закрывают перечисленные backend scopes game-plan-28, а не
весь проект и не все связанные delivery/runtime gates.

### Evidence packet handed to the orchestrator

Точный targeted evidence packet, на котором основана reconciliation:

- resource/action: `CharacterResourceLimitsTest`,
  `CharacterResourceStorageTest`, `GameActionResourceResolverTest`,
  `GameEconomyMysqlTest`, `GameStrikeMysqlTest`,
  `GameWideStrikeMysqlTest`;
- defender/block and efficiency: `GameCheckRollTest`,
  `GameCheckMysqlTest`, `GameEfficiencyPrecedenceTest`,
  `GameStrikeBodyTest`, `GameStrikeLayersTest`, `GameStrikeMysqlTest`,
  `GameWideStrikeMysqlTest`, `MechanicRollTest`;
- selected instance and replay: `GameActionResourceResolverTest`,
  `GameStrikeBodyTest`, `GameStrikeMysqlTest`,
  `GameWideStrikeMysqlTest`, `GameReplayTransactionTest`;
- penetration and boundaries: `PenetrationMutationCasProbe`,
  `PenetrationRuntimeEvidenceTest`, `GameStrikeMysqlTest`,
  `GameWideStrikeMysqlTest`;
- reliability and Rule/Mechanic resolution:
  `ReliabilityCutHandler.php`, `MechanicEngineTest`,
  `MechanicEnginePortTest`, `RuleVersionRecordTest`,
  `GameStrikeMysqlTest`, `GameWideStrikeMysqlTest`;
- conflict projection: `CharacterActualMutationMysqlTest`,
  `GameEconomyMysqlTest`, `GameStrikeMysqlTest`,
  `GameWideStrikeMysqlTest`, with the accepted external shape
  `{currentVersion, choices, sheet}`.

The packet is targeted evidence, not a claim that the complete suite or
cross-layer delivery path was run.

### Не подтверждено этой closure-сессией

Не заявлялись и не считаются закрытыми:

- полный PHPUnit/полный backend suite;
- полный frontend → PHP → delivery E2E;
- frontend acceptance и production UI behavior;
- production deployment, live environment, published operational content и
  runtime configuration вне переданного targeted evidence;
- нагрузочные, multi-process и real-concurrency свойства сверх явно
  переданного acceptance evidence;
- глобальные quality/style baseline issues и общая worktree cleanliness;
- любые scope вне game-plan-28: battleground, wounds/DOT, roadmap,
  readiness/status и unrelated project limitations.

Отсутствие повторного запуска уже принятого успешного targeted evidence не
является новым дефектом и не переоткрывает принятые контракты.

### Future / out of scope

- `magic-perception` `all_available` semantics — отдельная future task;
- multi-step action context и persisted frozen `chosenAmounts`, если spend
  будет происходить не на open;
- frontend delivery/UI и полный cross-layer E2E;
- production deployment/live-environment verification;
- новые combat result fields или расширение accepted conflict envelope;
- battleground, wounds/DOT, roadmap/readiness/status и unrelated
  implementation work.

## Final gate

**FINAL VERDICT: `GAME-PLAN-28 BACKEND CLOSURE APPROVED FOR THE ACCEPTED
SCOPES ABOVE; PROJECT-WIDE APPROVAL: NOT GRANTED.**

The document records closure of the accepted P2b, P3
(defender/block and efficiency), selected-instance, reliability,
penetration, ChosenAmount single-step, and flat conflict-envelope scopes.
It does not certify full-suite, frontend-to-delivery E2E, deployment, live
environment, or the listed future/out-of-scope work.

## Historical implementation plan

The remainder of this document is retained as the pre-closure plan and
evidence trace. Its earlier `READY FOR ONE IMPLEMENTATION SESSION` verdicts
are superseded by the final closure record above.

`ChosenAmount` больше не является blocker решения: его server-owned contract
зафиксирован ниже и не является отдельным implementation blocker
`game-plan-28`. Реальными оставшимися gaps являются:

- P3 efficiency — implementation gap;
- P2 selected inventory instance — implementation gap;

Для текущего single-step action flow frontend choice является
непроверенным предложением. Backend проверяет его по live Rule, выполняет
spend на open action path и передаёт mutation только typed `ResourceSpend`.
Успешный CAS является authoritative фиксацией значения. Request body
сохраняется как idempotency fingerprint: тот же key с тем же body возвращает
сохранённый result, а тот же key с изменённым amount даёт `GAME_CONFLICT` до
повторного spend. Persisted `frozen chosenAmounts` не требуется. Отдельный
action context понадобится только для будущего multi-step action, где spend
происходит не на open.

Принятые P1/P2a/P2b/P3/reliability/penetration paths повторно не
аудируются. Runtime evidence reliability — 19/19 MySQL tests и 1229
assertions — принимается как имеющееся evidence. Предыдущее
concurrency/fork acceptance принимается; отсутствие повторного запуска без
изменения CAS-кода не блокирует этот план. Worktree cleanliness не относится
к scope.

## 1. P3 efficiency

### Зафиксированный gap

Нужно пройти весь attacker/defender roll flow, включая
`GameStrikeRules::rollHit()`.

Сейчас `GameCheckRoll::thrown()` создаёт `RollSpec` с fixed efficiency
`3` и fixed die size `0`. В `GameStrikeRules::rollHit()` attacker path
передаёт `new DimensionalNumber(3, 0)`; defender path передаёт
`BlockProfile::efficiency`. Общий `MechanicRolls::roll()` затем применяет
`RollSpec::withRollDefaults()` и active mechanic pipeline.

Это оставляет две проверяемые проблемы:

1. fixed `{3|0}` может обходить live `CheckSpec` efficiency, если у
   `CheckSpec` задан `default_efficiency`;
2. порядок источников efficiency не оформлен одним authoritative contract.

`CheckSpec::getDefaultEfficiency()` существует и парсится, но production
roll flow по найденному коду его не использует. `RollSpec` сейчас знает
только integer threshold, а `RollMechanicPayload` является fallback для
нейтральной точки `3`.

### Authoritative precedence

Порядок должен быть зафиксирован и реализован так:

1. `BlockProfile` efficiency выбранного block profile — только для
   defender block roll;
2. `CheckSpec` efficiency (`default_efficiency`) live hit/check spec;
3. `RollMechanicPayload` efficiency;
4. существующий `Mechanic` roll pipeline и его активные modifiers.

Для attacker обычного hit roll источника `BlockProfile` нет. Для defender
block roll выбранный live profile является более специфичным источником и не
может быть заменён client `itemRuleCode` или fixed default. Neutral source
может передать управление следующему уровню precedence; production code не
должен использовать `{3|0}` как authoritative value, если live contract
даёт другое значение.

Не менять dimensional semantics:

- `roll` остаётся `{base, size}`;
- `rating` остаётся integer РУ;
- auto-fail определяется только dimensional `roll`, а не `rating === 0`
  или `rating === -1`;
- dimensional efficiency не превращается в число кубиков и не
  сериализуется как scalar.

### Владельцы и файлы

- Rule: `CheckSpec.php`, `RootSpecs.php`,
  `Item/BlockProfile.php`, `ItemSpecs.php`;
- Mechanic: `RollSpec.php`, `RollMechanicPayload.php`,
  `MechanicRolls.php`, `RollMechanicContext.php`;
- Game roll boundary: `GameCheckRoll.php`, `GameStrikeRules.php`;
- single/wide orchestration: `GameStrikes.php`,
  `GameWideStrikes.php`, `GameWideStrikeResults.php`;
- regression coverage:
  `GameCheckMysqlTest.php`, `GameStrikeMysqlTest.php`,
  `GameWideStrikeMysqlTest.php`, `MechanicRollTest.php`.

### Acceptance

- attacker roll uses live hit `CheckSpec` efficiency;
- selected block profile uses its dimensional efficiency;
- payload is used only at the documented neutral/fallback point;
- active mechanic roll events still run once through `MechanicRolls`;
- a test with two equipped rows and different block efficiencies proves that
  the selected profile controls the defender roll;
- `{base, size}` remains in roll/difficulty output and integer rating is
  derived only after dimensional comparison;
- `{0|-1}` auto-fail and non-auto-fail `{0|0}`/`{0|3}` behavior remains
  dimensional.

## 2. P2 selected inventory instance

### Trace and actual gap

Transport already requires `itemInventoryId` in both
`GameStrikeBody::attack()` and `GameWideStrikeBody::attack()`. It is stored
in the open single/wide strike row and is used by `assertAttack()`,
`rollHit()`, penetration and selected item resolution.

The spend resolver boundary is different:

```text
single/wide action input
→ open row
→ spendAttacker()
→ GameStrikeRules::resolveResourceSpends()
→ GameActionResourceResolver::resolve()
```

The resolver currently receives `itemRuleCode`, but not `itemInventoryId`.
`GameActionResourceResolver::minimums()` scans every equipped inventory row
whose `ruleCode` equals that code. Therefore two equipped rows with the same
`itemRuleCode` can contribute a combined modifier set. This violates selected
instance identity.

The same issue exists for Character and NPC because both flows eventually
provide authoritative `choices`, but neither passes the selected instance to
the resolver. `itemRuleCode` remains useful as a live-rule consistency check;
it must not replace instance identity.

### Required contract

- resolver receives the selected `itemInventoryId`;
- exactly one equipped row is resolved;
- its live `ruleCode` is checked against the supplied action context;
- modifiers are read only from that row;
- duplicate/ambiguous/missing/not-equipped instance is `GAME_INVALID`;
- the same selected-instance semantics apply to Character and NPC;
- single and wide action paths pass the same identity;
- client `itemRuleCode` never selects a different row and never merges rows.

### Владельцы и файлы

- input/opening:
  `GameStrikeBody.php`, `GameWideStrikeBody.php`,
  `GameWideStrikeOpening.php`;
- Game orchestration:
  `GameStrikes.php`, `GameWideStrikes.php`,
  `GameWideStrikeCommit.php`;
- resolver boundary:
  `GameStrikeRules.php`, `GameActionResourceResolver.php`;
- authoritative selected item helpers:
  `GameStrikeRules.php` (`selectedItem`, `selectedItemById`,
  `assertInventoryItem`, `effectiveItem`);
- Character/NPC source and mutation:
  `CharacterRepository.php`, `GameNpcRepository.php`,
  `GameStrikeSheetWrites.php`;
- acceptance:
  `GameStrikeMysqlTest.php`, `GameWideStrikeMysqlTest.php`,
  `GameEconomyMysqlTest.php`.

### Acceptance

- two equipped rows with the same rule code and different modifiers;
- single attack resolves only the selected row's modifiers;
- wide attack resolves only the selected row's modifiers;
- Character and NPC produce identical resolution for equivalent documents;
- missing, not-equipped, duplicate and rule-code-mismatch selections fail
  before mutation;
- changing client `itemRuleCode` cannot select or merge another instance;
- prepared spend and replay result retain the already resolved selection
  rather than re-reading a different item row.

## 3. ChosenAmount contract boundary

`ChosenAmount` остаётся частью acceptance P2 resource/action path, но не
отдельным blocker `game-plan-28` и не требует persisted frozen field.

```text
frontend proposal
→ open action path
→ live Rule/ResourceSpec validation
→ typed ResourceSpend
→ atomic spend + effect + entity CAS
→ idempotency result
```

Contract:

- `wait` использует `amount.type = chosen`;
- frontend передаёт chosen amount как предложение;
- backend валидирует предложение по live action/resource Rule;
- scalar amount — integer в диапазоне `1..available`;
- invalid, missing, ambiguous, malformed, variant-mismatch или overflow
  proposal → `GAME_INVALID` до mutation;
- mutation получает только typed `ResourceSpend`;
- spend выполняется атомарно на open action path;
- успешный CAS является authoritative фиксацией значения;
- request body сохраняется как idempotency fingerprint;
- same key + same body возвращает сохранённый result без повторного spend;
- same key + changed amount возвращает `GAME_CONFLICT` до повторного spend;
- отдельное persisted поле `frozen chosenAmounts` не требуется;
- отдельный action context нужен только будущему multi-step action, где spend
  происходит не на open;
- dimensional values сохраняют native shape без flattening;
- Character/NPC и single/wide используют одинаковую validation и CAS
  semantics.

Оставить acceptance на validation, atomic spend и replay/idempotency.
`all_available` semantics для `magic-perception` — отдельная будущая задача.

## 4. Conflict envelope classification

### Accepted external shape

The external conflict details shape is:

```text
{currentVersion, choices, sheet}
```

The current `getErrorDetails()` implementations already flatten an internal
`currentSheet` value into top-level `choices` and `sheet`. Existing tests also
assert that `currentSheet` is absent from the external payload. Therefore:

- do not return `currentSheet` externally;
- do not classify this as a production blocker;
- reconcile documentation and PHPDoc terminology only, preferably describing
  `currentSheet` as an internal constructor/projection value and the external
  envelope as `{currentVersion, choices, sheet}`;
- preserve key-conflict cases that legitimately contain only
  `currentVersion` (or no version where the conflict is a key reservation).

### Владельцы и files

- production shape:
  `Character/Exception/CharacterConflictException.php`,
  `Game/Exception/GameEconomyConflictException.php`,
  `Game/Exception/GameBattleConflictException.php`;
- projection/propagation:
  `ConflictSheetProjection.php`, `GameStrikeSheetWrites.php`,
  `GameStrikes.php`, `GameWideStrikeResults.php`;
- evidence:
  `CharacterActualMutationMysqlTest.php`, `GameEconomyMysqlTest.php`,
  `GameStrikeMysqlTest.php`, `GameWideStrikeMysqlTest.php`.

This item requires documentation reconciliation only. It does not require
changing the production payload when the flat shape remains unchanged.

## Dependency order

1. Close selected inventory instance propagation through both action paths.
2. Define the shared efficiency precedence and update the roll boundary,
   preserving dimensional roll/rating/auto-fail semantics.
3. Add focused acceptance coverage for the remaining P3 and selected-instance
   gaps, including ChosenAmount validation, atomic spend and replay/idempotency.
4. Perform conflict-envelope documentation reconciliation.
5. Re-run only the implementation-session acceptance suite; do not repeat
   accepted concurrency/reliability/penetration audits without a relevant
   code change.

Selected-instance propagation should precede resource resolution because each
resolved amount or minimum must be bound to the same action instance.
Efficiency work is independent of resource choice and can be implemented in
the same session after its precedence is frozen. Conflict documentation is
independent and may be done last.

## Acceptance matrix by gap

- **P3 efficiency:** live profile/spec/payload precedence; full
  attacker/defender flow; dimensional roll and integer rating preserved;
  dimensional-only auto-fail; single and wide results preserve authoritative
  rolls.
- **P2 selected instance:** input-to-resolver trace includes
  `itemInventoryId`; one equipped row only; no same-code modifier mixing;
  Character/NPC parity; client code is consistency data only.
- **ChosenAmount acceptance:** frontend proposal; live spec validation;
  typed `ResourceSpend`; atomic open-path spend/CAS; request-body
  fingerprint; same-body replay; changed-amount conflict before spend.
- **Conflict envelope:** external flat shape verified; no `currentSheet`;
  documentation/PHPDoc terminology reconciled; no production payload change.
- **Evidence:** existing reliability 19/19 and 1229 assertions accepted;
  prior concurrency/fork acceptance accepted; worktree cleanliness excluded.

## Implementation classification

### Requires implementation

- P3 efficiency precedence and fixed `{3|0}` removal where it bypasses live
  contract;
- `itemInventoryId` propagation into the resource resolver and
  instance-scoped modifier lookup;
- focused tests for P3 efficiency, selected inventory instance, and
  ChosenAmount validation/atomic/replay acceptance.

### Documentation-only reconciliation

- naming and description of the already-flat conflict envelope;
- explicit statement that `currentSheet` is not an external key;
- evidence classification and scope exclusions recorded above.

## Final gate

**READY FOR ONE IMPLEMENTATION SESSION.** `ChosenAmount` снят как
implementation blocker `game-plan-28`: текущий open-path contract требует
только live validation, typed spend, atomic CAS и request-body
replay/idempotency acceptance. Persisted frozen amount и отдельный action
context остаются вне текущего single-step flow. No code or tests are
implemented by this document.
