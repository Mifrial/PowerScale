# Audit 7 — P2 authoritative AP lifecycle и action cost

## Baseline

- HEAD: `26048f3778555fbf4646c7eb861ce92fd7b23c7f`, branch `dev`.
- Tracked/staged diff отсутствует.
- Существующие untracked сохранены без изменений.
- PHPUnit/MySQL и frontend dev-server не запускались.

## P2: authoritative AP lifecycle

### Owners

- Rule:
  - declarative action cost;
  - `action_components`;
  - `min_action_cost` и его merge.
- Character:
  - AP в `actualCharacter`;
  - resource spend mutation через существующий Character mutation port.
- Game:
  - action/reaction eligibility;
  - declaration/resolve lifecycle;
  - outer transaction;
  - replay, pending и wide orchestration.
- NPC:
  - AP внутри `npc.version`;
  - version counter — `npc.actual_version`.
- CAS:
  - отдельная P1-зависимость;
  - P2 не реализует и не дублирует CAS.

### Cost contract

1. Canonical AP resource определяется сервером из live `resource` Rule с `auto_add=true`. Ноль или несколько таких ресурсов — `GAME_INVALID`; код ресурса клиент не передаёт.
2. Base block cost для weapon и shield одинаков: `2 AP`.
3. `2 AP` хранится в live ability Rule:
   - `type = action`;
   - `combat_action = block`;
   - fixed `resource` component для AP.
4. PHP не вводит константу `2` как источник authority. Отсутствующая, неоднозначная или некорректная Rule означает отказ.
5. Effective cost:

```text
max(action_rule_AP_cost, applicable min_action_cost values)
```

6. Несколько `min_action_cost` объединяются глобально через `max`, не через сумму и не через source-collapse.
7. Текущий `min_action_cost` принадлежит `WeaponBlock`. `ShieldBlock` отдельного minimum-cost поля не получает: shield block остаётся стоимостью action Rule, то есть 2 AP.
8. `ActionComponents` используется только для расчёта resource cost. `verbal`, `somatic`, `material` не расширяют P2 eligibility блока.
9. `chosen`, dimensional или неоднозначный AP amount для strike/block action отклоняется; fallback не применяется.

### Eligibility

До declaration:

- action Rule жива и является допустимым action;
- выбранный атакующий, предмет и profile принадлежат участнику;
- предмет экипирован;
- profile существует в актуальной Rule revision;
- effective attack cost вычислена сервером;
- AP достаточно.

До accepted resolve:

- reaction — `ignore`, `dodge` или `block`;
- для `dodge`/`block` существует соответствующий live action Rule;
- для `block` выбранная inventory row:
  - экипирована;
  - уникальна;
  - соответствует `blockItemRuleCode`;
  - имеет допустимый `BlockProfile`;
- проверка достаточности AP выполняется по authoritative actual/NPC version, а не по UI-снимку.

Explicit `ignore` имеет cost `0` и не изменяет actual/NPC.

## Resource representation

- Нового AP storage, overlay-поля или отдельной таблицы не создавать.
- AP хранить в authoritative `sheet.resources`:
  - current — persisted;
  - limit/base/bonuses — derived из Rule и листа.
- Для Character источник — `character.sheet`.
- Для NPC источник — `npc.version.sheet`.
- `actual_version` / `npc.actual_version` — единственный resource version; отдельный `resource_version` не вводить.
- Server-generated mutation должна быть операцией вида `spendResource(resourceCode, amount)`, без принятия готового `current` от клиента.
- Existing sheets без resource snapshot требуют отдельного backfill/initialization шага. Game не должен молча подставлять максимум AP во время боя.

## Lifecycle

### Declaration

`declareStrike` и `declareWideStrike`:

1. Проверяют idempotency и battle version.
2. Проверяют attacker actual/NPC version.
3. Разрешают action Rule и effective AP cost.
4. Если AP недостаточно — `GAME_INVALID`, strike не открывается.
5. В одной outer transaction:
   - списывают AP атакующего;
   - открывают strike;
   - увеличивают battle version;
   - сохраняют command result.
6. Wide strike списывает AP атакующего ровно один раз, независимо от числа целей.
7. Response содержит authoritative `actionPointCost` и новую attacker resource/entity version.

### Accepted resolve

`resolveStrike`:

- для `ignore` AP не списываются;
- для `dodge`/`block` AP списываются после принятия реакции;
- spend, reaction effect, target mutation, strike close, battle version и idempotency record — одна транзакция;
- если последующая проверка блока провалена, уже принятая реакция всё равно считается оплаченной;
- недостаток AP, подтверждённый authoritative read, даёт auto-ignore без spend и без block effect.

`resolveWideStrike`:

- стоимость и eligibility проверяются по каждой цели;
- недостаток AP конкретной цели даёт только её target-level auto-ignore;
- auto-ignore должен быть различим в результате (`autoIgnored: true`);
- stale version не превращается в auto-ignore;
- любой mutation-time CAS/validation failure откатывает весь wide close;
- intentional target-level auto-ignore — нормальный результат, а не rollback.

## Replay, stale и retry

- Повтор того же idempotency key и тела возвращает сохранённый результат; spend не повторяется.
- Тот же key с другим телом после успешной команды — `GAME_CONFLICT`.
- Stale attacker version при declaration:
  - `GAME_CONFLICT` с `currentVersion`;
  - AP и strike не изменяются.
- Stale defender version при resolve:
  - `GAME_CONFLICT`, не auto-ignore;
  - strike остаётся pending/open;
  - AP и effect не изменяются.
- Для wide close любой stale target блокирует commit всего close; ни одна цель не получает partial mutation. Возвращаются current versions для retry.
- Неуспешный wide resolve не записывает idempotency result, поэтому после reload версий тот же pending strike можно повторить.
- При успешном wide close replay возвращает полный `targetResults[]`, включая auto-ignore, без повторного spend.
- Transport retry после успешного commit использует replay; retry после conflict повторно читает версии и отправляет актуальное тело.

## Files and boundaries

### Rule

Изменить:

- `Rule/Spec/ItemModifierOperationItems.php` — runtime merge `min_action_cost` через `max`.
- `Rule/Spec/ItemModifierOperationWhen.php` — authoritative target eligibility для minimum-cost operation.
- новый Rule cost resolver и его public interface/registration.
- Rule tests для action components и item cost merge.

Проверить без расширения контракта:

- `WeaponBlock.php`;
- `ShieldBlock.php`;
- `MinActionCostOp.php`;
- `ItemModifiers.php`;
- `ItemWeapons::block()`;
- `ActionComponents.php`;
- `AbilityBases.php`;
- `AbilityActionSpec.php`.

`ShieldBlock` и parser shield не дополнять minimum-cost полем.

### Character

Изменить:

- `CharacterSheetDocument.php`;
- `CharacterSaveAssembly.php`;
- `CharacterActualMutations.php`;
- `ICharacterActualMutations.php`;
- при необходимости выделить внутренний resource-row helper.

Не изменять в рамках P2:

- `CharacterRepository::writeGuarded()` и DB CAS;
- Character public HTTP contract для произвольного client-set resource.

### Game/NPC

Изменить:

- `DeclareGameStrikeInput.php`;
- `DeclareGameWideStrikeInput.php`;
- `ResolveGameStrikeInput.php`;
- `ResolveGameWideStrikeInput.php`;
- `GameStrikes.php`;
- `GameWideStrikes.php`;
- `GameWideStrikeResults.php`;
- `GameStrikeRules.php`;
- `GameStrikeSheetWrites.php`;
- новый Game resource/action-cost orchestration service;
- соответствующие HTTP assemblers/actions.

Новые таблицы и отдельные resource columns не нужны. Cost/version facts хранятся в существующем command result и authoritative actual/NPC JSON.

`GameNpcRepository::replaceVersion()` используется после P1 CAS-контракта; отдельный NPC spend/CAS механизм не создавать.

## Acceptance tests

1. Action Rule с AP component `2` даёт block cost `2` для weapon и shield.
2. Weapon без `min_action_cost` — cost `2`.
3. Weapon с minimum `1` — cost `2`; с minimum `3` — cost `3`.
4. Несколько minimum values `2`, `4`, `3` дают `4`.
5. Minimum не суммируется и не зависит от source.
6. Shield без minimum всегда использует cost action Rule.
7. Invalid/missing/duplicate AP resource или block action Rule дают `GAME_INVALID`.
8. AP spend уменьшает authoritative current, не уходит ниже нуля и увеличивает entity version ровно один раз.
9. Недостаток AP не пишет actual/NPC.
10. Character и NPC дают одинаковую spend semantics.
11. Declaration списывает attacker AP один раз; wide declaration — один раз на весь wide strike.
12. Declaration conflict/invalid/replay не создаёт второй spend.
13. Accepted block/dodge resolve списывает defender AP атомарно с effect и close.
14. Explicit ignore не меняет AP.
15. Недостаток AP при resolve даёт target-level auto-ignore без spend/effect.
16. Stale CAS даёт `GAME_CONFLICT`, никогда не auto-ignore.
17. Single-target stale оставляет strike pending.
18. Wide stale оставляет wide strike pending и не коммитит ни одну цель.
19. Wide hard failure после успешной промежуточной target mutation откатывает все AP/effects/reactions/close/battle version/idempotency.
20. Wide auto-ignore targets и accepted targets возвращаются единым committed result.
21. Replay успешного single/wide result не увеличивает версии и не списывает AP повторно.

Вне P2: defender roll/block arithmetic, penetration, reliability handler, wounds/DOT, battleground, frontend, roadmap/readiness/audit updates и реализация P1 CAS.
