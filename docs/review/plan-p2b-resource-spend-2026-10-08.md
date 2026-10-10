# P2b — generic resource spend и action resolver

Дата: 2026-10-08

## Verdict

**READY FOR ONE P2b IMPLEMENTATION SESSION**

P2b не реализован. Перед началом implementation session зафиксированы
следующие границы:

1. P1 wide-strike gate принят после исправления теста и ручного подтверждения
   Андреем следующих проверок:
   - target-level refusal;
   - wide stale как top-level conflict;
   - rollback поздней CAS-ошибки;
   - concurrent same-key replay.
2. Числовые PHPUnit-метрики для этого подтверждения не записаны. Это
   ограничение evidence, но не переоткрывает принятое решение и не требует
   запуска полного suite.
3. P2b не исправляет P1 orchestration и не добавляет отдельный CAS path.

P2a resource storage/backfill предоставляет необходимую основу: `sheet.resources` для Character, `version.sheet.resources` для NPC, typed scalar/dimensional values, native arithmetic, backfill и entity CAS.

## Обязательный prerequisite-plan

### P1 CAS и orchestration

Owner: Game + Character + Core/SmartTable.

Файлы:

- `Core/SmartTable/Service/Query/TableRows.php`
- `Roleplay/Character/Repository/CharacterRepository.php`
- `Roleplay/Game/Repository/GameNpcRepository.php`
- `Roleplay/Game/Service/GameStrikes.php`
- `Roleplay/Game/Service/GameWideStrikes.php`
- `Roleplay/Game/Service/GameWideStrikeResults.php`
- `Roleplay/Game/Service/GameReplayTransaction.php`

Необходимо:

- stale любого wide target → top-level `GAME_CONFLICT`;
- target-level refusal оставить только для обычной не-CAS eligibility/error semantics;
- mutation-time conflict любой цели откатывает весь wide close;
- сохранять подтверждённые target-level refusal, top-level stale conflict,
  rollback поздней CAS-ошибки и concurrent same-key replay.

Числовые PHPUnit-метрики не являются частью зафиксированного evidence.
Полный suite для перехода к P2b не требуется.

### Auto-ignore JSON

Owner: Game.

Принято:

- недостаток ресурса использует тот же публичный результат, что и явно
  выбранный `ignore`;
- отдельный public code/reason не добавляется;
- automatic insufficient-resource ignore и explicit ignore используют
  существующую форму ignore target result;
- spend не выполняется;
- effect не выполняется;
- `sheet`, entity version и battle version не изменяются;
- stale остаётся `GAME_CONFLICT` и никогда не превращается в ignore;
- partial spend запрещён;
- Character и NPC используют одинаковую semantics.

Это решение снимает auto-ignore JSON как contract blocker.

## Dependency gate

### Defender-check semantics

Не блокирует generic P2b.

P2b не должен реализовывать defender roll, block success/failure, defender pool, mastery и efficiency precedence. Эти элементы остаются отдельным P3 prerequisite. P2b может использовать существующий authoritative effect path, если его вызов не требует новой block-семантики.

### Auto-fail

Не блокирует generic P2b, если P2b не добавляет block-specific branching. Автопровал `{0|-1}` и запрет чтения block layers остаются вне P2b.

### Penetration

Не блокирует P2b resource/action implementation. Penetration остаётся отдельным P9 prerequisite. P2b не меняет `GameStrikeRules::evaluateDamage()`, resistance arithmetic или response JSON penetration.

### Production reliability capability

Не блокирует generic spend/action resolver. P2b не добавляет production reliability handler, registry delivery, catalog row или published damage-type binding.

Любой уже существующий capability-resolution failure должен сохранять fail-closed behavior и откатывать transaction.

## Implementation plan после закрытия prerequisites

### 1. Typed resource spend

Owner: Character resource boundary + Game orchestration.

Файлы/классы:

- `Character/Service/Resource/CharacterResourceStorage`
- `Character/Service/Resource/CharacterResourceArithmetic`
- `Character/Service/CharacterActualMutations`
- `Character/Interface/Service/ICharacterActualMutations`
- `Game/Repository/GameNpcRepository`
- новый общий resource-spend service на границе Character/Game

Вход:

- live `CharacterRuleSlice`;
- authoritative current `sheet.resources`;
- список уже resolved `ResourceValue` spends;
- expected `actual_version`.

Выход:

- новый typed resource row set;
- новый Character/NPC record;
- domain status insufficient/accepted.

Правила:

- scalar → только native `int`;
- dimensional → только `{base, size}`;
- scalar/dimensional mismatch → `GAME_INVALID`;
- implicit conversion запрещён;
- несколько компонентов обрабатываются как единая prepared mutation;
- недостаток одного компонента отменяет все spends;
- current не может стать отрицательным;
- dimensional comparison/subtraction выполняются через native `DimensionalNumber`;
- `ChosenAmount` сюда не передаётся.

Transaction/CAS boundary:

- Character: existing `actual_version` через `ICharacterActualMutations`;
- NPC: existing `actual_version` через `GameNpcRepository::replaceVersion()`;
- отдельный `resource_version` не создавать;
- entity version увеличивается ровно один раз.

Acceptance:

- scalar spend;
- dimensional spend;
- несколько scalar и dimensional компонентов;
- atomic failure при недостатке одного компонента;
- shape mismatch;
- отсутствие implicit conversion;
- Character/NPC parity;
- stale CAS без записи;
- rollback без частичного `sheet.resources`.

### 2. Action Rule resolver

Owner: Rule parsing + Game resolver.

Файлы/классы:

- `Rule/Spec/ActionComponents.php`
- `Rule/Dto/Spec/Ability/AbilityActionSpec.php`
- `Rule/Dto/Spec/Ability/Component/ResourceComponent.php`
- `Rule/Spec/ItemModifiers.php`
- новый `MinResourceCostOp`
- соответствующий merge-код в `ItemModifierOperationItems` / `ItemModifierOperationWhen`
- новый Game-side action resolver

Вход:

- `spaceId`;
- `rulesRevision`;
- live action Rule;
- selected item/effective modifier context;
- frontend chosen amount proposal.

Выход:

- resolved action;
- список ResourceComponents;
- resolved native amounts;
- effective minimums;
- eligibility decision.

Правила:

- читать live Rule, а не client-provided cost;
- поддержать несколько `ResourceComponent`;
- `min_resource_cost` содержит `resource_code` и native `minimum`;
- minimum применяется только к matching component;
- несколько minimum одного resource объединяются native `max`;
- разные resources не смешиваются;
- отсутствие matching component ничего не создаёт;
- не hardcode-ить resource code или numeric cost;
- `ChosenAmount` разрешается только resolver-ом;
- unresolved/ambiguous/invalid choice → `GAME_INVALID` без mutation;
- block не получает отдельной ветки resolver-а.

Migration:

- старый `min_action_cost` обрабатывается отдельным явным migration path;
- resource code должен поступать из подтверждённого mapping;
- ambiguous mapping → explicit invalid/conflict;
- fallback на `action-points` или фиксированную стоимость запрещён.

Acceptance:

- live action Rule с одним компонентом;
- несколько компонентов;
- scalar minimum;
- dimensional minimum;
- несколько minimum → native max;
- minimum другого resource не влияет;
- missing/unknown resource;
- malformed modifier;
- migration старого `min_action_cost`;
- отсутствие hardcoded AP/cost;
- разные Rule revisions дают разные costs.

### 3. Eligibility и ignore

Owner: Game action resolver/orchestrator.

Файлы:

- `Game/Service/GameStrikes.php`
- `Game/Service/GameWideStrikes.php`
- `Game/Service/GameWideStrikeResults.php`
- `Game/Service/GameStrikeRules.php`
- новый action/eligibility resolver
- transport DTO только если принятый contract потребует дополнительные входы

Вход:

- explicit user reaction;
- live action Rule;
- authoritative Character/NPC record;
- resolved resource amounts;
- expected entity version.

Выход:

- accepted action;
- explicit ignore;
- automatic insufficient-resource ignore;
- stale conflict;
- exact result JSON.

Правила:

- explicit user-selected ignore не вызывает spend/effect;
- insufficient current автоматически даёт ignore;
- insufficient resource не является partial spend;
- stale не превращается в ignore;
- invalid shape/content → `GAME_INVALID`;
- ignore не изменяет sheet, entity version, reaction effect или battle state.

Acceptance:

- explicit ignore;
- insufficient scalar resource;
- insufficient dimensional resource;
- один insufficient component среди нескольких;
- stale resource/entity version;
- no effect/no mutation/no spend;
- exact single and wide JSON;
- Character/NPC parity.

### 4. Atomic single-strike orchestration

Owner: Game transaction boundary.

Файлы:

- `Game/Service/GameStrikes.php`
- `Game/Service/GameStrikeSheetWrites.php`
- `Game/Service/GameReplayTransaction.php`
- `Game/Repository/GameNpcRepository.php`
- `Character/Service/CharacterActualMutations.php`

Порядок transaction:

```text
read authoritative entity/version
→ resolve live action and native resource amounts
→ classify eligibility/ignore
→ spend all resources
→ calculate authoritative effect
→ apply effect
→ conditional Character/NPC CAS
→ close strike/reaction
→ advance battle
→ persist idempotency result
```

При любой ошибке эффекта, CAS или close откатываются resource spend, effect, sheet, reaction/strike close, battle version и command result.

Replay:

- same key + same body → stored result;
- повторного spend/effect/version bump нет;
- same key + different body → `GAME_CONFLICT`.

Acceptance:

- accepted single strike;
- resource spend + effect + CAS атомарны;
- effect failure rollback;
- stale conflict;
- same-key replay;
- no duplicate spend;
- Character/NPC parity.

### 5. Atomic wide-strike orchestration

Owner: Game wide commit layer.

Файлы:

- `Game/Service/GameWideStrikes.php`
- `Game/Service/GameWideStrikeCommit.php`
- `Game/Service/GameWideStrikeResults.php`
- `Game/Service/GameWideStrikeReplay.php`
- `Game/Service/GameStrikeSheetWrites.php`

Правила:

- attacker declaration spend выполняется один раз на весь wide strike;
- target-level insufficient resource даёт только target-level automatic ignore;
- accepted targets получают spend + effect;
- explicit ignore не получает spend;
- stale/CAS любого target → top-level conflict;
- поздняя ошибка любого target откатывает все предыдущие target mutations;
- committed result содержит exact frozen target result JSON;
- replay возвращает весь сохранённый result без повторного списания.

Acceptance:

- one attacker spend for N targets;
- mixed accepted/explicit-ignore/insufficient-resource targets;
- no partial spend;
- no partial effect;
- late second-target CAS conflict rolls back first target;
- wide replay;
- wide stale leaves strike pending;
- Character/NPC target parity.

## Acceptance matrix

### Storage and arithmetic

- native scalar round-trip;
- native dimensional round-trip;
- strict row shape;
- duplicate/unknown/malformed row rejection;
- no scalar/dimensional conversion;
- atomic multi-component spend;
- clamp and non-negative current.

### Action resolution

- live Rule only;
- `ResourceComponent` resolution;
- `wait` uses `amount.type = chosen`;
- frontend sends the selected number as an unvalidated proposal;
- backend validates the proposal against the live action/resource Rule;
- scalar chosen amount is an integer in the range `1..current available`;
- invalid, missing, ambiguous or overflow chosen amount → `GAME_INVALID` with
  no mutation;
- mutation receives only typed resolved `ResourceSpend`;
- spend is atomic on the open action path;
- successful CAS is the authoritative fixation of the validated amount;
- request body is the idempotency fingerprint;
- same key + same body returns the stored result without a second spend;
- same key + changed amount returns `GAME_CONFLICT` before a second spend;
- no persisted frozen `chosenAmounts` field is required for the current
  single-step action flow;
- a separate action context is deferred to future multi-step actions where
  spend does not happen on open;
- `all_available` semantics for `magic-perception` are a separate future task;
- `min_resource_cost`;
- native max merge;
- explicit legacy migration;
- no hardcoded resource/cost;
- no block-specific resolver logic.

### Eligibility

- explicit ignore;
- automatic insufficient-resource ignore;
- no partial spend;
- no effect on ignore;
- stale remains conflict;
- exact JSON envelope;
- hidden resources remain hidden in result/conflict projection.

### Orchestration

- single atomic spend/effect/CAS;
- wide atomic spend/effect/CAS;
- rollback after effect failure;
- rollback after late target conflict;
- replay without second spend;
- same key/different body conflict;
- exactly one entity-version increment;
- Character/NPC parity.

## Out of scope for P2b

- P2b implementation itself;
- frontend;
- battleground;
- wounds;
- DOT;
- game-plan-29;
- defender roll;
- auto-fail;
- block success/failure;
- penetration;
- production reliability handler/content;
- runtime resource refresh/reset;
- new tables or resource columns;
- separate resource CAS;
- roadmap/readiness/status/audit/document changes.

Defender roll/block semantics остаются P3. Auto-fail остаётся вне P2b.
Penetration остаётся вне P2b. Production reliability capability остаётся вне
P2b. Frontend, battleground, wounds, DOT и game-plan-29 остаются вне scope.
