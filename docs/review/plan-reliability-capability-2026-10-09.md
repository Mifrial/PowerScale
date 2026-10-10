# Read-only implementation plan: production reliability capability

Дата: 2026-10-09

## Verdict

**READY FOR ONE RELIABILITY IMPLEMENTATION SESSION**

Решения по production handler, canonical payload и Rule binding зафиксированы.
Реализация должна выполнить одну reliability implementation session;
penetration остаётся следующим отдельным этапом.

Этот документ подготовлен в read-only режиме.
В рамках отдельной reliability implementation session разрешены изменения
production-кода и acceptance-тестов строго в указанном scope.
Roadmap/readiness/status, frontend, penetration и существующие
review-документы не изменяются.

## Evidence and current state

Проверены:

- `AGENTS.md`;
- `docs/tr/TR.md`;
- `docs/tr/combat-layers-prerequisite.md`;
- `docs/review/plan-p3-defender-check-block-2026-10-08.md`;
- `docs/review/plan-penetration-2026-10-09.md`;
- `Roleplay/Mechanic/Interface/Service/IMechanicEngine.php`;
- `Roleplay/Mechanic/Interface/IReliabilityCut.php`;
- `Roleplay/Mechanic/Service/MechanicEngine.php`;
- `Roleplay/Mechanic/Service/MechanicPortFactory.php`;
- все четыре текущих production handlers;
- `CharacterCombatLayers` и item-layer projection;
- `GameStrikeLayers`, `GameStrikeRules`, single/wide strike orchestration и
  `GameReplayTransaction`;
- `DamageTypeSpec`, item damage bindings, `RuleVersionBody`,
  `RuleVersionRecord`, `Rules`;
- текущие Mechanic, Character combat-layer, Rule durability и Game
  single/wide strike tests.

Зафиксировано для реализации:

- `IMechanicEngine::hasReliabilityCut()` и пустой marker
  `IReliabilityCut` существуют;
- `MechanicEngine` резолвит `mechanic_id` через
  `MechanicRecord(code, handler_version)` и проверяет capability через
  `instanceof IReliabilityCut`;
- canonical delivery key production handler —
  `reliability_cut@1.0.0`;
- handler — marker-only, реализует `IMechanicHandler` и `IReliabilityCut`,
  не имеет event side effects и не использует payload-driven fallback;
- canonical binding payload — `{}`; capability определяется marker handler,
  новые payload-поля не добавляются;
- отсутствующий catalog row и отсутствующий `code@version` handler в
  `MechanicEngine` дают `MechanicInvalidException`;
- Character строит read-only typed layers из authoritative `sheet`/`choices`;
  путь проекции одинаков для Character и NPC;
- `durability` у defense/resistance slot optional: отсутствие ключа даёт
  `null`, а `BlockProfile::defense` durability не имеет;
- текущий Game path уже применяет damage-type filter, затем durability cut,
  затем source collapse;
- replay возвращает сохранённый result без повторной domain work, а stale,
  CAS и outer transaction/rollback уже имеют отдельные acceptance tests.

Implementation session должна проверить и использовать существующий Rule
mock/content publication path для canonical близкого к production content:
binding capability в mocks для колющего, режущего и рубящего damage type.
Конкретные damage-type codes не становятся знанием Game и не зашиваются в
Game.

`RuleVersionBody` уже отвергает часть malformed mechanics на commit, а
`Rules::assertKnownMechanics()` проверяет существование catalog id. Однако
`RuleVersionRecord::requireMechanicList()` сейчас проверяет только list shape,
а `GameStrikeLayers::bindings()` отбрасывает невалидные rows вместо отказа.
Эту разницу нельзя трактовать как production fail-closed contract.

## Authoritative ownership

### Mechanic

Mechanic владеет:

- capability interface `IReliabilityCut`;
- semantic production handler;
- handler delivery key `code@version`;
- registry registration;
- `hasReliabilityCut()` resolution semantics.

В текущем registry зарегистрированы только:

- `plain_roll@1.0.0`;
- `purchase_surcharge@1.0.0`;
- `six_one_rule@4.5.0`;
- `advantage_disadvantage@2.1.0`.

Ни один из них не реализует `IReliabilityCut`. Production reliability
handler должен быть marker-capability handler: `IMechanicHandler` +
`IReliabilityCut`, без event side effect, без payload-driven fallback.
Canonical delivery key production handler — `reliability_cut@1.0.0`.

### Rule

Rule владеет:

- live `damage_type` rule в конкретной published revision;
- `mechanics[]` binding этой версии;
- catalog reference `mechanic_id`;
- published content row и его revision membership.

`DamageTypeSpec` хранит semantic damage-type properties, но не capability
boolean и не mechanic code. Binding остаётся в `RuleVersionBody` /
`RuleVersionRecord` как `{mechanic_id, mechanic_payload}`. Не добавлять новое
поле в `DamageTypeSpec`, не копировать mechanic code и не делать
frontend-only binding authoritative.

### Character

Character владеет projection input: authoritative `sheet` и `choices`,
equipped inventory resolution, live ItemSpec resolution и typed
`CharacterCombatLayer` DTO. Character не определяет, включён ли reliability
cut, не читает catalog mechanics и не хранит persistent layer list.

`durability` имеет точную semantics:

- `null` — слой абсолютно применим и никогда не снимается reliability cut;
- `durability > hitRating` — слой остаётся;
- `durability <= hitRating` при включённой capability — слой снимается;
- defense/resistance layers обрабатываются одинаково по threshold;
- `BlockProfile::defense` приходит с `durability = null`;
- durability предмета/оружия не подменяет slot threshold.

### Game

Game владеет:

- извлечением damage type из live weapon profile;
- передачей live damage-type bindings и catalog в
  `hasReliabilityCut()`;
- fail-closed adapter и преобразованием resolution failure в
  `GAME_INVALID`;
- filter/cut/source aggregation order;
- single/wide target execution, mutation boundary и result/replay behavior.

Game не сравнивает mechanic code, не читает client boolean, `pay_sr` или
frontend hook и не выводит capability из damage type code.

## Exact contract

### Production handler semantics

Целевой handler:

- реализует `IMechanicHandler` и `IReliabilityCut`;
- имеет delivery key `reliability_cut@1.0.0`;
- не подписывается на event и не мутирует event context;
- сообщает только semantic capability через marker;
- использует только canonical binding payload `{}`;
- не содержит hardcoded damage-type code, item code или client flag.

Capability сама по себе не является выполнением среза. Срез выполняется Game
только после успешного `hasReliabilityCut()` на bindings live damage type.

### Registry and resolution

Authoritative chain:

```text
live weapon profile damage_type_code
→ live damage_type Rule in game revision
→ Rule mechanics[]
→ mechanic_id
→ MechanicRecord(code, handler_version)
→ registry code@version
→ handler instanceof IReliabilityCut
```

Правила:

1. capability проверяется только через
   `IMechanicEngine::hasReliabilityCut()`;
2. отсутствие mechanics у корректного live damage type означает `false`;
3. binding с положительным `mechanic_id`, для которого нет catalog row, —
   отказ;
4. catalog row без зарегистрированного `code@version` handler — отказ;
5. malformed mechanics container, malformed row, отсутствующий
   `mechanic_id`, non-positive/non-integer id или неверный payload — отказ;
6. отсутствие capability не превращается в ошибку только потому, что
   mechanics list пуст;
7. resolution failure не превращается в `false`, «cut off» или silent skip;
8. `mechanic_id`, mechanic code, client boolean и `pay_sr` не являются
   authority по отдельности;
9. неизвестный/невалидный mechanic id не является «capability отсутствует».

Нужно сохранить существующее разделение: `mechanic_id === null` допустим
только как отсутствие binding на уровне данных, если такой shape разрешён
конкретным Rule contract; строка, претендующая на binding, обязана пройти
strict validation. Нельзя использовать `null` как fallback для сломанного
binding.

### Rule content

До implementation session должны быть зафиксированы:

- catalog row с `code = reliability_cut` и
  `handler_version = 1.0.0`;
- canonical binding payload `{}`;
- существующий Rule mock/content publication path, представляющий
  близкий к production binding для колющего, режущего и рубящего damage type;
- exact positive `mechanic_id`, ссылающийся на catalog row, для каждой
  опубликованной binding;
- отсутствие frontend-only binding и dangling reference.

Binding не добавляется автоматически ко всем damage types. Game не знает и не
хардкодит конкретные damage-type codes; он использует только binding,
указанный в live Rule `mechanics[]` через catalog `mechanic_id`. Published
`contentStatus` не определяет runtime capability.

### Game pipeline

Для accepted effect authoritative order:

```text
battle/sheet preflight
→ attacker/defender roll and accepted-hit gate
→ auto-fail / ignore / insufficient-resource boundaries
→ Character/NPC layer projection
→ live damage-type filter
→ hasReliabilityCut() resolution
→ durability filtering
→ source collapse
→ existing resistance/damage/injury pipeline
→ Character/NPC conditional mutation
→ close and persist result
```

Reliability cut:

- выполняется после damage-type filter;
- применяется до source collapse;
- применяется до penetration pipeline, если penetration будет реализован
  отдельной будущей задачей;
- не выполняется для auto-fail, explicit ignore или automatic
  insufficient-resource ignore;
- не читает reliability второй раз при replay;
- в wide выполняется независимо для каждой target projection;
- в single и wide использует одну semantics;
- Character и NPC используют одну projection semantics.

Penetration implementation не менять и в этот план не включать. Не добавлять
новые combat JSON fields: существующий `resistance` остаётся текущим
результатом pipeline; raw/effective reliability или penetration diagnostics
не сохраняются.

### Failure and transaction behavior

Malformed binding, missing catalog и missing handler — shared content/runtime
failure, который возвращается как `GAME_INVALID` и:

- не закрывает strike;
- не increment-ит battle version;
- не пишет mutation;
- не создаёт завершённую idempotency command record;
- в wide aborts whole transaction, а не становится per-target refusal.

Replay с тем же key и тем же body возвращает byte-equivalent stored result и
не выполняет resolution, projection, cut, rolls, formulas или mutation
повторно. Different body сохраняет `GAME_CONFLICT`. Stale/CAS conflict и
late rollback сохраняют существующую P1 semantics.

## Files for one implementation session

### Production

- `www/mifrial/modules/Roleplay/Mechanic/Service/Handler/ReliabilityCutHandler.php`
  — новый marker-only production handler;
- `www/mifrial/modules/Roleplay/Mechanic/Service/MechanicPortFactory.php`
  — registry wiring;
- `www/mifrial/modules/Roleplay/Rule/Dto/RuleVersionRecord.php`
  — strict hydration validation, если body-level validation не покрывает
  published/read path;
- `www/mifrial/modules/Roleplay/Game/Service/GameStrikeLayers.php`
  — strict binding adapter, без silent skip;
- existing live Rule/Mechanic catalog publication path — content operation,
  не frontend binding и не новый JSON field.

`IMechanicEngine`, `IReliabilityCut`, `MechanicEngine` и
`MechanicHandlerRegistry` менять только если acceptance tests докажут
несоответствие установленному contract. Новый Game fallback не создавать.

### Tests

- `www/mifrial/modules/Roleplay/Mechanic/tests/MechanicEngineTest.php`;
- `www/mifrial/modules/Roleplay/Mechanic/tests/MechanicEnginePortTest.php`;
- `www/mifrial/modules/Roleplay/Rule/tests/RuleVersionRecordTest.php`
  — новый тест или согласованное существующее Rule test suite;
- `www/mifrial/modules/Roleplay/Character/tests/CharacterCombatLayerTest.php`;
- `www/mifrial/modules/Roleplay/Game/tests/GameStrikeMysqlTest.php`;
- `www/mifrial/modules/Roleplay/Game/tests/GameWideStrikeMysqlTest.php`;
- pure `GameStrikeLayers` test для filter/cut/durability/source order, если
  текущие fixtures не позволяют изолировать path.

## Dependency order

1. В одной reliability implementation session используются существующий
   Rule mock/content publication path и canonical catalog binding для
   колющего, режущего и рубящего damage type; конкретные коды не переносятся
   в Game.
2. Добавляется marker-only handler `reliability_cut@1.0.0` и его registry
   wiring; content/catalog binding и handler registry являются одной
   implementation session.
3. Strict Rule hydration/commit/read validation закрывает malformed rows;
   existing `RuleVersionBody` и catalog-id validation остаются
   authoritative и не дублируются без необходимости.
4. Game adapter перестаёт пропускать malformed bindings и преобразует
   resolution failure в shared `GAME_INVALID`.
5. Pure Mechanic/Rule/Character tests подтверждают resolution, nullable
   durability и parity.
6. Game single path подтверждает filter → cut → durability → collapse и
   failure boundaries.
7. Wide path подтверждает независимость targets и abort on shared
   reliability-resolution failure.
8. Replay, stale, CAS и rollback tests подтверждают, что capability не
   выполняется повторно и не оставляет partial commit.
9. Published/mock content test подтверждает весь chain от live Rule
   `mechanics[]` через catalog `mechanic_id` до production handler.
10. После завершения reliability session penetration начинается следующим
    отдельным этапом.

## Acceptance matrix

| Area | Case | Expected result |
| --- | --- | --- |
| Production handler | Registered production handler implements `IReliabilityCut` | Marker is authoritative capability |
| Production handler | Handler runs with arbitrary event/payload | No event side effect; no payload fallback |
| Registry | `code@version` is registered in `MechanicPortFactory` | Production port resolves without external registration |
| Registry | Non-marker handler | `hasReliabilityCut()` is `false` |
| Registry | Marker handler with arbitrary code | `true`; no string-code comparison |
| Live binding | Empty mechanics on valid damage type | `false`, no failure |
| Live binding | Published damage type points to catalog row | `true` only after live chain resolves |
| Missing handler | Catalog row has no `code@version` handler | `MechanicInvalidException` → `GAME_INVALID` |
| Missing catalog | Binding id has no catalog row | Hard failure, not `false` |
| Malformed binding | Non-list mechanics, malformed row, missing payload/id, invalid id | Hard failure, never silent skip |
| Invalid mechanic id | Zero, negative, non-integer or dangling id | Rule/runtime refusal |
| Capability authority | Client boolean, `pay_sr`, mechanic code or damage-type literal | Never used as authority |
| Durability | Capability disabled | Thresholded layer remains |
| Durability | `durability = null` | Layer remains absolutely applicable |
| Durability | Positive threshold `> rating` | Layer remains |
| Durability | Positive threshold `<= rating` | Layer is removed |
| Durability | Zero threshold with positive/zero rating | Exact `<= rating` semantics; test both |
| Order | Matching damage type then reliability | Non-matching layers never reach cut |
| Order | Cut then source collapse | Removed layer cannot affect collapse |
| Auto-fail | Combat auto-fail | No reliability read, projection, damage or mutation |
| Ignore | Explicit ignore | No reliability read or applied effect |
| Resource | Automatic insufficient-resource ignore | No reliability read or applied effect |
| Replay | Same key/body | Stored result; capability not resolved twice |
| Replay | Same key/different body | `GAME_CONFLICT`; no second domain work |
| Stale | Battle or sheet stale | Existing conflict; strike remains open |
| CAS/rollback | Late Character/NPC CAS conflict | Whole close rolls back, no partial reliability result |
| Character/NPC | Equal authoritative documents | Equal projected layers and cut result |
| Single/wide | Same accepted target semantics | Same filter/cut/durability behavior |
| Wide independence | Targets have different layers/durability | Each target gets independent result |
| Wide failure | Shared malformed binding/catalog/handler | Whole wide close fails; not target-level refusal |
| Content | Published canonical damage type and catalog row | No dangling id; live chain resolves |
| Compatibility | Result JSON | No new combat fields; existing shape/replay unchanged |

## Explicit exclusions

- penetration implementation or penetration result fields;
- action-point implementation;
- defender-check redesign or new block semantics;
- frontend changes or frontend binding;
- armor durability depletion;
- new combat JSON fields;
- roadmap/readiness/status;
- changes to existing review documents;
- fallback mode where missing capability silently disables reliability.

## Implementation-session gate

The session is ready to implement the accepted contract. It must preserve:

- отсутствие binding у корректной Rule → capability `false`;
- dangling/malformed binding или missing handler → `GAME_INVALID`;
- no silent fallback;
- production registry wiring;
- Character/NPC и single/wide parity;
- durability/null semantics;
- replay/CAS/rollback behavior.

Penetration не входит в эту session и остаётся следующим отдельным этапом.
