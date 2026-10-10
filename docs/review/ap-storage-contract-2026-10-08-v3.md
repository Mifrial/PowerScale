# AP storage/action-cost contract v3

Дата: 2026-10-08

Основание: `ap-storage-contract-2026-10-08-v2.md`,
`plan-p2-ap-action-cost-prerequisite-2026-10-08.md` и принятые решения
от 2026-10-08.

Это design/review artifact. Документ не реализует AP spend, action resolver,
Game orchestration, block roll, penetration, reliability или frontend.

## A. Обновлённый accepted contract

### A.1. Authority и parity

- Для Character authoritative AP/resource state находится только в
  `character.sheet`.
- Для NPC authoritative AP/resource state находится только в
  `game_npc.version.sheet`.
- Game overlay, `gameState` и `choices` AP не хранят.
- Character и NPC используют одну форму `sheet.resources`, одну валидацию,
  одинаковые floor/shape/migration rules и одинаковую conflict semantics.
- Conditional write использует существующий entity `actual_version`.
  Отдельные `resource_version` и второй CAS path не вводятся.
- Клиент не передаёт authoritative `current`.
- Projection использует существующие visibility rules. Resource не должен
  становиться видимым через overlay, conflict envelope или unfiltered
  assembler в обход `SheetVisibility`.

### A.2. Resource row и native value

`resources` — список typed rows. Persisted row содержит ровно:

```json
{
  "ruleCode": "action-points",
  "current": 3
}
```

Dimensional row содержит:

```json
{
  "ruleCode": "qi",
  "current": { "base": 2, "size": 1 }
}
```

Правила:

- `ruleCode` — непустой код live `ResourceSpec`.
- `current` — authoritative persisted value.
- Scalar resource хранит native integer.
- Dimensional resource хранит native `{base: int, size: int}`.
- Live `ResourceSpec` определяет variant; persisted `kind` не добавляется.
- `limit`, `base`, bonuses, source labels и display metadata не persisted.
- Duplicate `ruleCode`, неизвестный Rule, malformed row, malformed JSON и
  scalar/dimensional shape mismatch дают `GAME_INVALID`; первый подходящий ряд
  не выбирается.
- Scalar нельзя оборачивать в dimensional object, dimensional value нельзя
  сплющивать в integer.
- На storage boundary authoritative current неотрицателен; clamp применяется
  до записи.

### A.3. Native value DTO boundary

На PHP boundary нужен typed `ResourceValue` с двумя concrete variants:

```text
ResourceValue =
    ScalarResourceValue(native int)
  | DimensionalResourceValue(DimensionalNumber)
```

Имена классов остаются implementation detail. Boundary обязан:

1. разрешить live `ResourceSpec`;
2. выбрать variant по live Rule;
3. parse только native integer для scalar;
4. parse только object с integer `base` и `size` для dimensional;
5. отклонить implicit conversion;
6. сериализовать обратно строго в соответствующую native форму.

Единый интерфейс допускается для boundary/serialization, но он не должен
заставлять scalar и dimensional значения использовать одинаковую арифметику.
Dimensional arithmetic переиспользует `DimensionalNumber`; scalar arithmetic
остаётся native integer arithmetic в общем resource calculation service.

### A.4. Persisted row parser/serializer

Parser/serializer — отдельная boundary responsibility, не часть Rule parser и
не часть Game orchestration.

Parser:

1. проверяет, что row является object;
2. проверяет наличие только `ruleCode` и `current` (лишние authoritative
   поля не принимаются);
3. разрешает live resource Rule;
4. проверяет native shape согласно live Rule;
5. строит typed row/value;
6. проверяет уникальность `ruleCode` для всего списка.

Serializer принимает только validated typed row, пишет `ruleCode` и
`current`, не пишет `kind`, derived limit или fallback zero. Unknown/removed
Rule не нормализуется молча.

### A.5. Effective limit и lifecycle

Effective limit — live-derived native value, рассчитанный из base и всех
применимых grants, adjustments, effects и иных действующих источников.
Integer flattening dimensional limit запрещён.

Initialization/backfill:

- новый resource row получает `current = effective limit`;
- существующий row сохраняет current;
- уменьшение limit ниже current вызывает clamp;
- увеличение limit current не пополняет;
- legacy document без resource row не означает `current = 0`;
- до успешного backfill AP-dependent operation получает
  `GAME_INVALID`/migration-required result.

Backfill идемпотентен, выполняется в отдельной transaction boundary и
защищён expected entity version/CAS. Повторный запуск не дублирует rows и не
пополняет current.

Rule revision migration:

- одинаковый `resourceCode` сохраняет current и применяет только clamp;
- новый code инициализируется effective limit;
- scalar↔dimensional change не конвертируется молча и даёт explicit
  migration conflict;
- removed/unknown code не превращается в zero;
- migration сохраняет current по resource code;
- migration write атомарен и CAS-protected.

Initialization/backfill/migration — одноразовые или version-transition
операции. Runtime refresh/reset (battle start, turn start, `endBattle`,
`stopSession`, explicit effects) сейчас не реализуется и не должен
появляться в storage или spend service.

### A.6. Generic action resource spend

Действие состоит из одного или нескольких `ResourceComponent`. Для каждого
component generic spend service выполняет строго такой pipeline:

1. разрешает live resource Rule по `resource_code`;
2. определяет scalar/dimensional variant;
3. проверяет native shape amount;
4. проверяет достаточность persisted current;
5. подготавливает native spend;
6. применяет все resource spends одной atomic mutation.

Допустимый пример:

```text
action-points: 3
qi: {base: 1, size: -1}
```

Block не имеет отдельной scalar/dimensional логики. Он использует тот же
generic service. PHP не hardcode-ит `action-points`, AP identity или numeric
cost.

Shape rules:

- scalar resource принимает только native integer amount;
- dimensional resource принимает только `{base: int, size: int}`;
- mismatch даёт `GAME_INVALID`;
- implicit scalar↔dimensional conversion запрещён;
- amount должен быть валидным native spend и не создавать отрицательный
  current.

Для dimensional resource:

- comparison выполняется в native `DimensionalNumber` semantics;
- достаточность проверяется по полной dimensional value, без integer
  flattening;
- subtraction выполняется native dimensional subtraction;
- clamp выполняется компонентно/по правилам `DimensionalNumber`, с
  сохранением native representation;
- результат никогда не сериализуется как scalar.

### A.7. `ChosenAmount`

`ChosenAmount` не является готовой persisted или spend value. До вызова
generic spend service на open action path должен:

1. получить frontend proposal;
2. разрешить `ChosenAmount` по live action/resource Rule;
3. получить конкретный native scalar или native dimensional amount;
4. повторно проверить shape против live `ResourceSpec`;
5. передать mutation только typed `ResourceSpend`.

Если choice отсутствует, ambiguous, не разрешается в native amount или не
соответствует live variant, результат — `GAME_INVALID`, без mutation.
Generic spend service не интерпретирует `ChosenAmount` и не выбирает default.
Frontend proposal не является authoritative value. Spend выполняется
атомарно на open action path; успешный entity CAS является authoritative
фиксацией значения. Request body сохраняется как idempotency fingerprint.
Same key + same body возвращает сохранённый result, same key + changed amount
даёт `GAME_CONFLICT` до повторного spend.
Отдельное persisted поле `frozen chosenAmounts` для текущего single-step
action flow не требуется. Отдельный action context нужен только будущему
multi-step action, где spend происходит не на open.

`all_available` semantics для `magic-perception` не входит в этот contract и
остаётся отдельной будущей задачей.

### A.8. New `min_resource_cost` modifier

Старый `min_action_cost` заменяется на:

```json
{
  "type": "min_resource_cost",
  "resource_code": "action-points",
  "minimum": 2
}
```

Для dimensional resource:

```json
{
  "type": "min_resource_cost",
  "resource_code": "qi",
  "minimum": { "base": 1, "size": -1 }
}
```

Contract:

- `resource_code` обязателен;
- `minimum` должен быть native value live `ResourceSpec`;
- scalar/dimensional mismatch даёт `GAME_INVALID`;
- implicit conversion запрещён;
- modifier действует только на matching `ResourceComponent`;
- другие components не изменяются;
- несколько minimum для одного code объединяются через native `max`;
- source-collapse для minimum не используется;
- отсутствие matching component не создаёт component и не меняет стоимость
  другого resource; такой modifier для данного action неприменим;
- malformed modifier, unknown resource code и duplicate/ambiguous modifier
  definition дают `GAME_INVALID`;
- PHP не hardcode-ит resource code или numeric value.

Effective amount для matching component:

```text
effectiveAmount = nativeMax(actionAmount, allMatchingMinimums)
```

`max`, comparison и shape выбираются native variant resource. Для
dimensional value запрещено сравнение после integer flattening.

Content migration старого `min_action_cost` выполняется явно: старое numeric
значение переносится в `minimum`, а `resource_code` добавляется из
подтверждённого live action/resource mapping. Если mapping неоднозначен,
migration останавливается с explicit invalid/conflict; fallback на
`action-points` запрещён.

## B. Owners и dependency order

### Owners

- Rule owns live `ResourceSpec`, resource variant, `ResourceComponent`,
  `DimensionalNumber` и modifier parsing.
- Resource value/storage boundary owns typed DTOs, row parser/serializer,
  shape validation и native arithmetic dispatch.
- Character owns Character sheet assembly, initialization/migration adapter и
  Character CAS mutation port.
- Game owns action resolution, `ChosenAmount` resolution, action/effect
  orchestration и NPC mutation request; Game не владеет storage shape.
- `GameNpcRepository::replaceVersion()` owns NPC conditional replacement.
- Read/projection layer owns visibility.
- Backfill/migration owner owns separate transaction boundary and idempotency.
- Replay/transaction owner owns command idempotency and rollback envelope.

### Dependency order

1. Закрыть typed value boundary, row parser/serializer и native
   scalar/dimensional arithmetic.
2. Добавить Character/NPC assembly, initializer, backfill и preserve-current
   migration с CAS.
3. Добавить generic resource spend service поверх обеих mutation ports.
4. Добавить `min_resource_cost` parsing, merge и explicit content migration.
5. Добавить open-path action resolver и server-side `ChosenAmount`
   validation.
6. Интегрировать spend + effect + CAS в single/wide Game orchestration.
7. Добавить replay/stale/conflict handling и acceptance coverage.
8. Отдельно проверить projection/visibility; automatic refresh/reset остаётся
   вне P2.

## C. Точные backend-файлы будущей реализации

Пути указаны относительно `www/mifrial/`.

### Resource value, Rule и persistence boundary

- `modules/Roleplay/Rule/Dto/Spec/ResourceSpec.php`
- `modules/Roleplay/Rule/Dto/Spec/ResourceLimit.php`
- `modules/Roleplay/Rule/Dto/Spec/Ability/Component/ResourceComponent.php`
- `modules/Roleplay/Rule/Spec/ActionComponents.php`
- `modules/Roleplay/Rule/Spec/ItemModifierOperationItems.php`
- `modules/Roleplay/Rule/Spec/ItemModifierOperationWhen.php`
- `modules/Roleplay/Rule/Dto/Spec/Item/WeaponBlock.php`
- `modules/Roleplay/Rule/Dto/Spec/Item/ShieldBlock.php`
- `modules/Roleplay/Character/Service/Save/CharacterSheetDocument.php`
- новый typed resource value/row DTO и parser/serializer в соответствующем
  `Roleplay/Character` или общем `Roleplay` boundary.

### Character

- `modules/Roleplay/Character/Dto/CharacterRecord.php`
- `modules/Roleplay/Character/Service/CharacterActualMutations.php`
- `modules/Roleplay/Character/Interface/Service/ICharacterActualMutations.php`
- `modules/Roleplay/Character/Repository/CharacterRepository.php`
- `modules/Roleplay/Character/Service/CharacterMigration.php`
- соответствующий Character read/assembly и visibility adapter.

### NPC

- `modules/Roleplay/Game/Dto/GameNpcRecord.php`
- `modules/Roleplay/Game/Repository/GameNpcRepository.php`
- `modules/Roleplay/Game/Service/GameStrikeSheetWrites.php`
- общий resource mutation adapter, использующий `replaceVersion()`.

### Action resolution и single/wide orchestration

- `modules/Roleplay/Game/Service/GameStrikeRules.php`
- `modules/Roleplay/Game/Service/GameStrikes.php`
- `modules/Roleplay/Game/Service/GameStrikeBody.php`
- `modules/Roleplay/Game/Service/GameStrikeSheetWrites.php`
- `modules/Roleplay/Game/Service/GameReplayTransaction.php`
- `modules/Roleplay/Game/Service/GameWideStrikes.php`
- `modules/Roleplay/Game/Service/GameWideStrikeCommit.php`
- `modules/Roleplay/Game/Service/GameWideStrikeResults.php`
- `modules/Roleplay/Game/Service/GameWideStrikeReplay.php`
- `modules/Roleplay/Game/Service/GameWideStrikeBody.php`
- `modules/Roleplay/Game/Service/GameWideStrikeSheetWrites.php`, если
  target resource writes останутся выделенным adapter.

Точный новый класс generic spend/resolution выбирается при реализации по
границе ответственности; прямое изменение JSON из Game запрещено.

## D. Atomic mutation, replay и conflict semantics

### Single accepted effect

Одна outer transaction выполняет:

```text
read authoritative entity/version
→ resolve/validate native resource amounts
→ spend all components
→ apply effect
→ conditional entity update with expected actual_version
→ close reaction/strike and advance battle
→ persist idempotency result
```

Любая ошибка после начала mutation откатывает resource spend, sheet effect,
reaction/strike close, battle version и command result.

Успешная entity mutation увеличивает её version ровно один раз. Character и
NPC используют одинаковые правила.

### Replay and stale

- Успешный replay с тем же command key и тем же body возвращает сохранённый
  complete result; второй spend/effect/version bump не выполняется.
- Тот же key с другим body даёт `GAME_CONFLICT`.
- Stale expected version даёт `GAME_CONFLICT` и fresh
  `currentVersion/currentSheet`, без spend и без auto-ignore.
- Single strike при stale остаётся pending/open.
- Confirmed insufficient current — это domain result, а не stale conflict:
  spend не выполняется, effect не применяется.
- Explicit ignore не требует resource component, не списывает resource и не
  меняет authoritative sheet.

### Wide action

- Attacker declaration списывает один resolved spend на весь wide strike.
- Defender target-level resource failure превращается в target-level
  auto-ignore без spend; остальные eligible targets могут продолжить.
- Stale/CAS или поздняя transaction failure любого target откатывает весь
  wide close; partial spends/effects не остаются.
- Replay wide command возвращает сохранённый результат без повторной mutation.

## E. Acceptance tests

### Storage and typed value

- Character current существует только в `character.sheet.resources`.
- NPC current существует только в `game_npc.version.sheet.resources`.
- Scalar round-trip сохраняет native integer.
- Dimensional round-trip сохраняет `{base,size}`.
- Missing `kind`, `limit`, `base`, bonuses и source metadata не вызывает
  implicit reconstruction из persisted row.
- Duplicate, malformed, unknown и shape-mismatch row дают `GAME_INVALID`.
- Scalar не принимается как dimensional object, dimensional value не
  принимается как integer.
- Client-provided current отклоняется.

### Initialization and migration

- Новый resource получает effective limit.
- Existing current сохраняется при росте limit.
- Current clamp-ится при уменьшении limit.
- Legacy отсутствующий row не становится zero.
- Повторный backfill идемпотентен и не дублирует row.
- Backfill CAS conflict не делает silent merge.
- Migration сохраняет current по code.
- Новый code инициализируется, removed code не становится zero.
- Scalar↔dimensional migration даёт explicit conflict.
- Backfill/migration rollback не оставляет partial document.

### Generic spend

- Один scalar component списывается при достаточном current.
- Несколько scalar/dimensional components списываются одной atomic mutation.
- Недостаток любого component откатывает все подготовленные spends.
- Dimensional comparison/subtraction/clamp не flatten-ит value.
- Scalar/dimensional mismatch даёт `GAME_INVALID`.
- Block использует тот же generic spend path, без отдельной variant logic.
- `ChosenAmount` не проходит в spend service unresolved.
- Missing/ambiguous/invalid chosen value даёт `GAME_INVALID` без mutation.

### Modifier and resolver

- `min_resource_cost` требует `resource_code`.
- Native scalar minimum повышает только matching scalar component.
- Native dimensional minimum сравнивается native dimensional `max`.
- Mismatch minimum/component даёт `GAME_INVALID`.
- Несколько minimum одного code объединяются через max, не sum.
- Minimum другого code не изменяет текущий component.
- Отсутствующий matching component не создаёт новый component и не меняет
  другой resource.
- Старый `min_action_cost` мигрируется только с explicit resource code;
  ambiguous mapping отклоняется.
- Нет fallback на hardcoded AP code/cost.

### Atomic Game/CAS/replay

- Character и NPC дают одинаковый spend/floor/version result.
- Accepted effect атомарно включает spend, effect и CAS.
- Stale не превращается в auto-ignore.
- Insufficient current не меняет sheet/effect.
- Explicit ignore не списывает resource.
- Wide attacker spend выполняется один раз.
- Wide target failure не оставляет partial mutation.
- Same-key same-body replay не списывает повторно.
- Same-key different-body replay даёт `GAME_CONFLICT`.
- Успешная mutation увеличивает entity version ровно один раз.
- Невидимый resource не появляется в projection/conflict envelope.

## F. Remaining decisions

Существенных design-блокеров не осталось: приняты и scalar/dimensional
native storage, и dimensional spend, и `ChosenAmount` boundary, и поведение
modifier/resource mismatch.

Implementation-level choices остаются открытыми только в пределах обычной
реализации:

- конкретные имена новых typed DTO/service классов;
- точная существующая точка read assembler для projection;
- транспортный формат public auto-ignore result, если он потребуется
  текущим Game API.

Эти пункты не меняют accepted storage/action-cost contract и не разрешают
вводить hardcoded resource/cost или обходить CAS.

## Verdict

**READY FOR P2 IMPLEMENTATION**

Причина: v3 фиксирует authoritative storage, native scalar/dimensional
boundary, parser/serializer, generic multi-component spend, shape validation,
dimensional arithmetic, `ChosenAmount`, `min_resource_cost`, initialization vs
runtime refresh, Character/NPC parity, atomic effect+CAS, replay/stale
semantics и acceptance matrix. Реализация должна следовать dependency order и
не включать перечисленные out-of-scope подсистемы.
