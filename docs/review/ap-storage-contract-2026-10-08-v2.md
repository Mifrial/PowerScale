# Read-only AP storage contract v2

Дата: 2026-10-08

Основание: `ap-storage-contract-2026-10-08.md`, решения Андрея и
`plan-p2-ap-action-cost-prerequisite-2026-10-08.md`.

Проверены код и контракты:

- `Roleplay/Rule/Dto/Spec/ResourceSpec.php`;
- `Roleplay/Rule/Dto/Spec/ResourceLimit.php`;
- `Roleplay/Rule/Dto/Spec/Ability/Component/ResourceComponent.php`;
- `Roleplay/Rule/Spec/ActionComponents.php`;
- `Roleplay/Character/Service/Save/CharacterSheetDocument.php`;
- `Roleplay/Character/Service/CharacterActualMutations.php`;
- `Roleplay/Character/Service/CharacterMigration.php`;
- `Roleplay/Character/Repository/CharacterRepository.php`;
- `Roleplay/Game/Repository/GameNpcRepository.php`;
- `Roleplay/Character/Service/Read/CharacterSectionMask.php`;
- frontend `ResourceValue`, resource-limit и migration services — только как
  corroborating evidence.

Документ является design/review artifact. Реализация AP spend, resolver и
Game orchestration сюда не входит.

## A. Accepted contract

### Authority and owners

- Character AP хранится только в `character.sheet`.
- NPC AP хранится только в `game_npc.version.sheet`.
- `choices` — build/declaration document и не содержит authoritative AP.
- Game overlay, `gameState` и `choices` не являются AP storage.
- Character и NPC используют одну и ту же форму `sheet.resources`.
- Запись выполняется через существующие entity mutation/CAS paths:
  `actual_version` Character и `actual_version` NPC. Отдельный resource
  version не вводится.

### Resource rows

- `resources` — список typed rows, каждая строка имеет `ruleCode` и
  persisted `current`.
- `ruleCode` — код живого resource Rule; код `ap` и numeric cost в PHP не
  зашиваются.
- Тип значения определяется live resource Rule (`ResourceSpec::isDimensional()`),
  а не persisted `kind`.
- Scalar resource хранит native scalar. Его нельзя превращать в
  `{base, size}` только для унификации JSON.
- Dimensional resource хранит `{base, size}` и не сплющивается в integer.
- `current` — единственное persisted authoritative value.
- `limit`, `base`, bonuses и прочие источники лимита — derived.
- Duplicate rows одного `ruleCode`, malformed row, unknown resource или
  несоответствие persisted shape live Rule дают `GAME_INVALID`; первый ряд
  выбирать нельзя.

### Initialization and current semantics

- Effective limit означает base плюс все применимые Rule
  grants/adjustments/effects, а не только `ResourceLimit::getBase()`.
- При создании ресурса `current = effective limit`.
- При увеличении effective limit current не пополняется.
- При уменьшении limit ниже current current clamp-ится к новому limit.
- Refresh/reset AP в этот prerequisite не входит.
- Legacy document без AP не трактуется как `current = 0`.

## B. Proposed typed resource model

### Evidence

Backend Rule model уже является union-моделью:

- `ResourceLimit::base`: `int|DimensionalNumber`;
- `ResourceGrant::limit`: `int|DimensionalNumber`;
- `ResourceComponent::amount`: `int|DimensionalNumber|ChosenAmount`;
- `DimensionalNumber` имеет собственную арифметику `shift`, `divide` и
  `toInteger`.

Это не поддерживает единый always-dimensional storage: scalar и dimensional
значения различаются семантически. Frontend `ResourceValue` всегда
dimensional, но это corroborating UI DTO, а не обязательный backend storage
contract; его `size: 0` нельзя использовать как доказательство backend
scalar representation.

### Recommendation

Рекомендуется typed `ResourceValue` boundary с двумя concrete value variants:

```text
ResourceValue =
  ScalarResourceValue(native int)
  | DimensionalResourceValue(DimensionalNumber)
```

Имена PHP-классов — implementation detail и не закрепляются этим документом.
Допустим общий boundary/interface для идентификации и сериализации, но не
единый арифметический метод, который заставляет scalar и dimensional значения
притворяться одним типом. Арифметика должна переиспользовать существующий
`DimensionalNumber` и отдельные scalar операции через один resource
calculation service, а не копироваться в каждом DTO.

Правила value boundary:

- live Rule выбирает variant;
- scalar принимается только как native integer;
- dimensional принимается только как object `{base: int, size: int}`;
- `kind` не сохраняется;
- shape mismatch — `GAME_INVALID`;
- отрицательные current/limit запрещаются на storage boundary, если это
  соответствует уже принятой resource floor policy; clamp выполняется до
  записи, а не оставляет отрицательный authoritative current.

Этот вариант соответствует backend Rule union types, сохраняет native scalar,
не копирует frontend DTO и позволяет строго проверять shape до арифметики.

## C. Exact persisted/projection boundary

### Persisted shape

Character:

```json
{
  "resources": [
    { "ruleCode": "live-scalar-resource", "current": 3 },
    { "ruleCode": "live-dimensional-resource", "current": { "base": 2, "size": 1 } }
  ]
}
```

NPC:

```json
{
  "choices": {},
  "sheet": {
    "resources": [
      { "ruleCode": "live-resource", "current": 3 }
    ]
  }
}
```

Точный persisted row contract:

- обязательны ровно `ruleCode` и `current`;
- `ruleCode` — непустая строка;
- scalar `current` — native integer;
- dimensional `current` — объект ровно с integer `base` и `size`;
- persisted `kind`, `limit`, `base`, `bonuses`, source labels и display names
  отсутствуют;
- список не допускает duplicate `ruleCode`;
- неизвестный Rule code, битый JSON или wrong scalar/dimensional shape не
  нормализуются молча и не превращаются в zero.

Resource row может быть добавлен backfill-операцией только после разрешения
его live Rule и effective limit. Client не передаёт authoritative current.

### Derived and projection

Derived calculation получает persisted current, live resource Rule, grants,
adjustments, formulas, states и другие применимые modifier sources. Он
возвращает runtime resource value с:

- `current` из storage, после clamp к effective limit;
- derived `limit/base/bonuses` в типе live Rule;
- source metadata только для projection, не для authority.

API/UI projection использует существующие `resources` visibility rules.
Сейчас backend `CharacterSectionMask` для секции `resources` пропускает только
`money`, а `CharacterSheetDocument` строит только текущие sheet keys. Поэтому
projection contract потребует отдельного изменения read assembler/mask:
AP не должен становиться видимым обходом `SheetVisibility`. Невидимый ресурс
не должен утекать через overlay или unfiltered conflict envelope.

## D. Initialization, backfill and migration semantics

### Creation and backfill

Создание и отдельный legacy backfill используют один typed resource
initializer:

1. загрузить live Rule revision;
2. разрешить resource rows и их type по Rule;
3. вычислить effective limit без integer flattening;
4. добавить отсутствующие auto/resource rows идемпотентно;
5. для нового row записать `current = effective limit`;
6. для существующего row сохранить current и применить только clamp;
7. записать authoritative document через CAS в отдельной transaction
   boundary.

Обычный backfill не перезаписывает существующий current, даже если новый
limit больше. Повторный вызов не добавляет rows и не создаёт вторую запись.
CAS conflict возвращается как conflict и требует повторного чтения, а не
silent merge.

Старый документ без AP не получает молчаливый zero. До успешного backfill
AP-dependent operation возвращает `GAME_INVALID`/migration-required result.

### Rule revision migration

Migration использует preserve-current/clamp policy:

- тот же `resourceCode` сохраняет current;
- новый limit выше current не пополняет current;
- новый limit ниже current clamp-ит current;
- новый resource code инициализируется effective limit;
- scalar↔dimensional смена не конвертируется молча;
- несовместимый resource shape/code даёт explicit migration conflict;
- removed resource code не превращается в неизвестный persisted row и не
  получает zero;
- migration write атомарен и защищён expected entity version/CAS.

Это дополняет существующую Character migration, которая сейчас пересобирает
sheet через `CharacterSheetDocument` и явно сохраняет только деньги; NPC
migration использует `game_npc.version` и `replaceVersion`. Оба текущих пути
требуют AP-aware assembly до того, как migration можно считать AP-safe.

### Refresh

Automatic refresh сейчас не реализуется. Battle start, turn start,
`endBattle`, `stopSession` и explicit effects должны быть отдельным будущим
refresh/reset contract. Они не смешиваются с persisted storage и spend.

## E. Action Rule/resource resolution

### What code proves

`ActionComponents::read()` читает канонический JSON key `components`.
`ResourceComponent` разрешает resource code и amount:
`int|DimensionalNumber|ChosenAmount`. `AbilityActionSpec` exposes components,
а `AbilityBase` exposes semantic `combatAction`, включая runtime role such as
`block`. `GameStrikeRules::assertBlock()` пока проверяет только live weapon или
shield item; resolver AP/resource component там ещё отсутствует.

Следовательно:

- resource, используемый для AP, выбирается из `ResourceComponent` конкретного
  live action Rule;
- `auto_add` может участвовать в проверке candidate resource, но не является
  самостоятельной AP identity;
- action Rule code, resource Rule code и numeric amount берутся из live Rule;
- fallback на hardcoded AP code/cost запрещён;
- отсутствие компонента, duplicate AP components, unknown resource или
  ambiguous mapping — `GAME_INVALID`;
- `ChosenAmount` нельзя принять как фиксированную стоимость spend;
- dimensional amount нельзя silently cast в integer.

### AP type and block amount

Код доказывает, что dimensional resource в Rule возможен, а
`ResourceComponent` dimensional amount синтаксически допустим. Код не
доказывает, что block AP всегда scalar или что любой block action обязан
использовать integer amount. Поэтому scalar-only AP нельзя объявить
каноном на основании текущего `assertBlock()` или frontend DTO.

Для реализации resolver должен явно выбрать и проверить совместимую пару
`resource Rule shape` / `component amount shape`; несовместимая пара — 
`GAME_INVALID`. До отдельного решения о runtime semantic AP amount нельзя
утверждать поддержку dimensional spend или заменять его integer conversion.

## F. Owners and dependencies

- Rule owns live `ResourceSpec`, `ResourceLimit`, action components and
  `DimensionalNumber` semantics.
- Character owns player authoritative `sheet`, initialization/migration
  adapter and Character CAS mutation port.
- Game owns action resolution, process/battle orchestration and NPC mutation
  request, но не storage.
- `GameNpcRepository::replaceVersion()` owns NPC conditional replacement.
- Character read projection/visibility owns API/UI resources output.
- Backfill/migration owner owns separate transaction boundary and
  idempotency.
- Future refresh owner must be a separate Game/effect contract.

Ни AP spend, ни action resolver, ни Game orchestration этим artifact не
реализуются.

## G. Acceptance criteria

- Character current хранится только в `character.sheet.resources`.
- NPC current хранится только в `game_npc.version.sheet.resources`.
- Rows — список с `ruleCode` и native scalar либо `{base,size}` current.
- Persisted `kind`, derived limit/base/bonuses отсутствуют.
- Live Rule определяет value variant и action resource identity.
- Duplicate, malformed, unknown и shape-mismatch rows дают `GAME_INVALID`.
- Scalar не оборачивается в dimensional object.
- Dimensional value не сплющивается в integer.
- Effective limit учитывает base и applicable grants/adjustments/effects.
- Новый row получает effective limit; existing current не пополняется.
- Limit decrease вызывает clamp.
- Legacy missing AP не становится zero.
- Backfill idempotent, CAS-protected и имеет отдельную transaction boundary.
- Migration сохраняет current по code, clamp-ит инициализирует новые rows;
  incompatible scalar/dimensional change требует explicit conflict.
- Projection применяет существующие visibility rules.
- Refresh/reset не появляется в storage/spend implementation.
- Action resolver читает `components`, не hardcode-ит codes/costs и отвергает
  `ChosenAmount` либо иное несовместимое runtime amount согласно принятому
  amount policy.
- Character и NPC используют одинаковые validation, CAS и conflict semantics.

## H. Remaining decisions

Остаётся одно существенное решение, которое текущий код не разрешает:

1. **Runtime amount для AP block action.** Разрешается ли dimensional AP
   amount, если live resource dimensional, и какая именно арифметика/проверка
   достаточности применяется к dimensional current? Либо для block action
   принимаются только fixed integer amounts, при этом dimensional resource
   должен быть explicit conflict, а не implicit conversion?

`ChosenAmount` уже не является подходящим fixed spend amount: он означает
«сколько выбрано» и не даёт числовой стоимости сам по себе. Остальные
решения из исходного контракта приняты выше.

## I. Verdict

**BLOCKED**

Причина: storage boundary, typed scalar/dimensional representation,
persisted/projection shape, initialization/backfill, preserve-current/clamp
migration и отсутствие refresh semantics зафиксированы. Однако код не
разрешает, должен ли AP amount block action быть dimensional или только fixed
integer. До этого решения нельзя безопасно реализовывать AP resolver/spend и
утверждать scalar-only behavior.
