# Read-only implementation plan: AP/action-cost prerequisite

Дата: 2026-10-08

## Verdict

**BLOCKED**

Конкретный prerequisite: в authoritative backend отсутствует representation текущего AP и его spend-contract.

- Character actual содержит только `choices` и `sheet`; текущего ресурса AP нет.
- NPC `version` содержит документ `choices`/`sheet`; текущего AP нет.
- `sheet.resources` кодом не подтверждён.
- Game overlay не может быть владельцем AP.
- Typed resource contract существует только для Rule parsing (`ResourceSpec`, `ResourceComponent`), но не для persisted sheet resource.
- Нет server-side spend port для Character/NPC.

До принятия storage/initialization contract AP implementation утверждать нельзя.

## Authoritative owners

| Объект | Фактический owner | Текущее состояние |
|---|---|---|
| Player actual | `character.choices` + `character.sheet`, `actual_version` | AP отсутствует |
| NPC actual | `game_npc.version`, `actual_version` | AP отсутствует |
| Game | session/battle/process/reaction state | AP хранить запрещено |
| Rule | live Rule revision и declarative action/resource specs | Parser частично готов |
| CAS | `actual_version` / `npc.actual_version` | Conditional CAS primitive есть |

Evidence:

- `www/mifrial/modules/Roleplay/Character/Dto/CharacterRecord.php`
- `www/mifrial/modules/Roleplay/Game/Dto/GameNpcRecord.php`
- `www/mifrial/modules/Roleplay/Character/Service/CharacterActualMutations.php`
- `www/mifrial/modules/Roleplay/Game/Repository/GameNpcRepository.php`
- `www/mifrial/modules/Core/SmartTable/Service/Query/ConditionalTableRows.php`

## Что уже есть

### Rule parser

Есть:

- `AbilitySpecs::read()` с веткой `type=action`;
- `AbilityBase::getCombatAction()`;
- `AbilityActionSpec::getComponents()`;
- `ActionComponents::read()` по ключу `components`;
- `ResourceComponent`;
- `ResourceSpec::isAutoAdd()`;
- `WeaponBlock::getMinActionCost()`;
- `MinActionCostOp`.

Но нет:

- live resolver для semantic `type=action`, `combat_action=block`;
- проверки ровно одного AP resource;
- проверки fixed integer AP amount;
- проверки соответствия `ResourceComponent.resourceCode` live resource Rule;
- effective-cost resolver;
- merge `min_action_cost` через `max`.

Текущий backend использует JSON-ключ `components`, а не `action_components`. Второй формат вводить нельзя; каноническое имя требует отдельного подтверждения.

### CAS P1

Conditional CAS уже представлен:

- `ConditionalTableRows::updateConditional()`;
- `CharacterRepository::writeGuarded()`;
- `GameNpcRepository::replaceVersion()`;
- unified combat conflict с `currentVersion`, `currentSheet`, wide target.

AP не должен создавать отдельный `resource_version` или отдельный NPC CAS path.

AP зависит от окончательной combat-orchestration semantics: stale/CAS должен возвращаться как `GAME_CONFLICT`, а не становиться auto-ignore.

## Предлагаемый backend contract

После закрытия AP storage prerequisite:

```text
authoritative resource:
  owner: Character actual | NPC version
  resourceCode: server-resolved live resource Rule
  current: persisted non-negative integer/resource value
  limit/base/bonuses: derived
  version: entity actual_version
```

Mutation boundary:

```text
spendResource(resourceCode, amount)
```

Операция должна:

- принимать только server-resolved resource code и amount;
- не принимать client-provided current;
- отклонять недостаток AP без записи;
- не допускать значение ниже нуля;
- увеличивать entity version ровно один раз;
- использовать существующий Character mutation port;
- использовать `GameNpcRepository::replaceVersion()` для NPC;
- выполняться в общей transaction с effect/strike close;
- быть replay-safe через существующий command idempotency result.

Необходимо отдельно решить, будет ли resource row храниться в `sheet` или в другой уже существующей authoritative части actual. Автоматическое введение `sheet.resources` недопустимо без evidence/решения.

## Live action-cost resolver

Новый resolver должен:

1. Получить `CharacterRuleSlice` для game `spaceId/rulesRevision`.
2. Найти live Rule по client-provided semantic code.
3. Проверить:
   - Rule `type === action`;
   - `AbilityActionSpec`;
   - `AbilityBase::getCombatAction() === 'block'`.
4. Из `getComponents()` выбрать компоненты `ResourceComponent`.
5. Разрешить AP resource по live `resource` Rule с `auto_add=true`.
6. Требовать ровно один такой resource.
7. Требовать fixed integer amount.
8. Отклонять:
   - отсутствие AP component;
   - несколько AP components;
   - отсутствующий/невалидный AP resource;
   - `ChosenAmount`;
   - dimensional amount;
   - неоднозначный ресурс;
   - fallback на hardcoded cost.

`2` не должен появляться в PHP как authority. Он может быть только текущим content/fixture значением.

Effective cost:

```text
effectiveCost = max(actionRuleCost, allApplicableMinActionCostValues)
```

`min_action_cost`:

- применяется к выбранному inventory instance;
- учитывает modifier merge;
- объединяется глобально через `max`;
- не суммируется;
- не использует source-collapse;
- для shield отсутствует отдельное minimum-поле;
- shield без minimum использует action Rule cost.

## Eligibility contract

### Attacker declaration

До открытия strike:

- live attacker action Rule;
- semantic action validity;
- selected attacker inventory instance;
- instance принадлежит attacker;
- instance экипирован;
- live item/profile;
- existing strength/hands/item constraints;
- server-resolved attack cost;
- достаточный AP;
- atomic attacker spend + open strike + battle version + command result.

Wide declaration списывает attacker AP один раз на весь wide strike.

### Defender accepted resolve

Для `ignore`:

- explicit ignore;
- AP spend отсутствует;
- effect отсутствует;
- authoritative sheet не меняется.

Для `dodge`/`block`:

- live reaction action Rule;
- selected defender inventory instance;
- instance экипирован;
- instance уникален;
- `blockItemRuleCode` совпадает с live item;
- присутствует допустимый `BlockProfile`;
- проверяются strength/hands и прочие существующие constraints;
- AP читается из authoritative current sheet/NPC version.

Confirmed insufficient AP:

- превращается в target-level auto-ignore;
- AP не списывается;
- block/dodge effect не применяется.

Stale/CAS conflict:

- никогда не превращается в auto-ignore;
- возвращает актуальный `currentVersion/currentSheet`;
- возвращает retryable `GAME_CONFLICT`;
- single strike остаётся pending/open.

## Runtime mutation

Точные существующие boundaries:

- Character:
  - `CharacterActualMutations::apply()`;
  - `CharacterRepository::replacePayload()` / conditional CAS.
- NPC:
  - `CharacterActualMutations::applyToDocument()`;
  - `GameNpcRepository::replaceVersion()`.

AP implementation должна расширить mutation operation model server-side spend-операцией, а не добавлять прямое изменение JSON из Game.

Для одного accepted effect:

```text
read authoritative version
→ validate/spend AP
→ apply effect
→ conditional entity update
→ close reaction/strike
→ advance battle
→ persist idempotency result
```

Всё должно быть внутри одной outer transaction. При любой поздней ошибке откатываются AP, sheet effect, reaction, strike close, battle version и command result.

## Lifecycle and wide strike

- Declaration attacker: AP spend before opening strike.
- Accepted defender resolve: AP spend after accepted reaction.
- Explicit ignore: zero cost, no mutation.
- Confirmed current AP failure: auto-ignore, no spend.
- Stale/CAS conflict: `GAME_CONFLICT`, not ignore.
- Single stale: strike remains pending.
- Wide declaration: one attacker spend for all targets.
- Wide target AP failure: target-level auto-ignore; other targets may continue.
- Wide target stale/CAS or transaction failure: whole close rolls back; no partial commit.
- Successful replay returns the stored complete result without another spend/effect/version bump.

## Точные файлы реализации

### Rule/action cost

- `modules/Roleplay/Rule/Spec/ActionComponents.php`
- `modules/Roleplay/Rule/Dto/Spec/Ability/AbilityActionSpec.php`
- `modules/Roleplay/Rule/Dto/Spec/Ability/AbilityBase.php`
- `modules/Roleplay/Rule/Dto/Spec/Ability/Component/ResourceComponent.php`
- `modules/Roleplay/Rule/Dto/Spec/ResourceSpec.php`
- `modules/Roleplay/Rule/Spec/ItemModifierOperationItems.php`
- `modules/Roleplay/Rule/Spec/ItemModifierOperationWhen.php`
- `modules/Roleplay/Rule/Dto/Spec/Item/WeaponBlock.php`
- `modules/Roleplay/Rule/Dto/Spec/Item/ShieldBlock.php`

Добавить новый typed resolver/interface в Rule/Game boundary, без hardcoded cost.

### Character resource

- `modules/Roleplay/Character/Dto/CharacterRecord.php`
- `modules/Roleplay/Character/Service/Save/CharacterSheetDocument.php`
- `modules/Roleplay/Character/Service/CharacterActualMutations.php`
- `modules/Roleplay/Character/Interface/Service/ICharacterActualMutations.php`
- соответствующий Character document/assembly код для принятой AP representation.

Если existing sheet contract не поддерживает AP snapshot, сначала нужен отдельный storage/backfill prerequisite.

### NPC resource

- `modules/Roleplay/Game/Dto/GameNpcRecord.php`
- `modules/Roleplay/Game/Repository/GameNpcRepository.php`
- `modules/Roleplay/Game/Service/GameStrikeSheetWrites.php`
- новый общий/resource mutation adapter, использующий `replaceVersion()`.

### Single strike

- `modules/Roleplay/Game/Service/GameStrikeBody.php`
- `modules/Roleplay/Game/Service/GameStrikes.php`
- `modules/Roleplay/Game/Service/GameStrikeRules.php`
- `modules/Roleplay/Game/Service/GameStrikeSheetWrites.php`
- `modules/Roleplay/Game/Service/GameReplayTransaction.php`
- соответствующие action/HTTP assemblers.

### Wide strike

- `modules/Roleplay/Game/Service/GameWideStrikeBody.php`
- `modules/Roleplay/Game/Service/GameWideStrikes.php`
- `modules/Roleplay/Game/Service/GameWideStrikeResults.php`
- `modules/Roleplay/Game/Service/GameWideStrikeCommit.php`
- `modules/Roleplay/Game/Service/GameWideStrikeReplay.php`
- `modules/Roleplay/Game/Service/GameWideStrikeSheetWrites.php`, если потребуется выделение target resource writes.

Central stale/transaction orchestration не следует дублировать в P2.

## Acceptance matrix

| Scenario | Required result |
|---|---|
| Valid live block action Rule | Cost resolved from current revision |
| Different Rule revisions | May produce different costs |
| Missing/wrong/duplicate action or AP Rule | `GAME_INVALID`, no mutation |
| Client sends current AP | Rejected/not part of contract |
| Weapon without minimum | Action Rule cost |
| Minimum below/above base | Base cost / higher minimum |
| Multiple minimums | `max`, never sum |
| Shield without minimum | Action Rule cost |
| Declaration with enough attacker AP | One spend, strike opens |
| Wide declaration | One attacker spend for whole wide strike |
| Declaration insufficient AP | Reject, no mutation |
| Declaration stale | `GAME_CONFLICT`, no spend |
| Explicit ignore | No AP spend, no sheet mutation |
| Accepted dodge/block | Defender spend + effect + close atomically |
| Confirmed defender AP failure | Target auto-ignore, no spend |
| Defender stale | `GAME_CONFLICT`, not auto-ignore |
| Wide one target AP failure | Target auto-ignore; eligible targets continue |
| Wide target CAS stale | Whole close rolls back/remains pending |
| Wide late mutation failure | All previous target spends/effects roll back |
| Replay same key/body | Stored result, no second spend |
| Replay same key/different body | `GAME_CONFLICT` |
| Character/NPC parity | Same spend, floor, version and conflict semantics |
| Entity version | Exactly one increment per successful entity mutation |

## Dependencies from P1 CAS

AP depends on, but must not reimplement:

- DB conditional update with expected version;
- fresh conflict read;
- `currentVersion/currentSheet` envelope;
- Character/NPC parity;
- whole-wide rollback on stale/mutation failure;
- replay behavior after successful command.

The CAS core exists in the current backend. The verification audit still records unresolved P1 items: concurrent same-key read-back coverage, concurrent CAS acceptance coverage, and quality regressions. They should be closed or explicitly accepted before AP implementation is marked production-ready.

## Unresolved decisions

1. Exact authoritative location and shape of current AP.
2. Initialization/backfill for existing Character/NPC rows.
3. Whether resource current is integer-only or supports dimensional values at storage boundary.
4. Canonical JSON key: current parser uses `components`; `action_components` is not implemented.
5. Exact live AP resource identification if multiple `auto_add=true` resources exist.
6. Public representation of `auto-ignore`.
7. Exact inventory-instance identity contract: current transport uses rule codes, while eligibility requires instance identity.
8. Complete strength/hands/item eligibility rules.
9. Whether attacker item eligibility is checked at declaration only or revalidated later.
10. Final P1 acceptance of concurrent same-key replay and whole-wide stale behavior.

До решения пунктов 1–2 и typed spend contract итоговый статус остаётся **BLOCKED**.

Код, существующие roadmap/readiness/status/audit-файлы и frontend в рамках подготовки этого плана не изменялись; PHPUnit suite не запускался.
