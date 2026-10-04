# Roadmap реализации `actualCharacter` как источника листа в session

**Статус:** implementation roadmap, 2026-09-28.  
**Контракт:** [`character-actual-session-source-plan.md`](character-actual-session-source-plan.md).  
**Канонические владельцы:** [`../tr/character-system.md`](../tr/character-system.md), [`../tr/game-system.md`](../tr/game-system.md), [`../tr/cross-domain.md`](../tr/cross-domain.md).

Этот документ описывает порядок реализации уже принятого контракта. Он не вводит
новую модель данных и не означает, что backend Game, SSE, outbox или runtime
mutation Character уже реализованы.

## Цель и ограничения

Целевой результат:

```text
actualCharacter / npc.version
  = единственный persisted authoritative sheet

approvedCharacterVersion
  = immutable player moderation baseline

gameOverlay / gameState
  = session, battle, process, offer и transient state
```

Одна текущая сессия может содержать несколько battles. `endBattle` не
завершает session и не запускает approve. `stopGameSession` очищает Game state,
но не выполняет повторный полный commit Character. NPC проходит тот же
authoritative mutation path без player moderation lifecycle.

В roadmap не входят exploration runtime и полный battleground. Их будущие
режимы должны получать собственные typed namespaces в Game state.

## Общий порядок

```text
R1 contract seam
        │
        ├─ R2-FE Character contract/mock readiness ──┐
        │                                             ├─ R3 Game state foundation
        └─ R2 Character backend C3–C5 ────────────────┘
                                                      ├─ R4 combat vertical slice
                                                      ├─ R5 read projections
                                                      ├─ R6 editor/realtime completion
                                                      └─ R7 lifecycle/recovery
                                                                   │
                                                                   └─ R8 legacy removal
```

R1 можно начать сразу и он завершён до R2-FE. R2-FE — frontend-only
подготовка typed patch, validation/conflict responses, actualVersion и
compatibility seams; она не является server-side authoritative mutation и не
заменяет backend R2 C3–C5. R2-FE и backend R2 могут идти параллельными
workstream-ами после R1, но R3 допускает только подготовку state boundary;
R4–R7 нельзя считать завершёнными без backend R2 и transaction-bound
Character/NPC ports.

## R0 — documentation gate

**Статус:** `DONE`.

- canonical Character/Game/cross-domain contracts синхронизированы;
- `DEC-084` добавлен;
- old full-sheet overlay и stop-time commit помечены superseded;
- evidence/status boundaries сохранены;
- dated migration history не используется как текущий контракт.

Gate: дальнейшая реализация не должна возвращать `approved + overlay` как
источник текущего Character sheet.

## R1 — contract seam и predicates

**Владелец:** frontend/domain adapter, затем общий Character/Game contract.  
**Зависимости:** нет.  
**Не удаляет:** `GameCombatOverlay.sheet` и старые mock paths до R4.

### Работы

1. Вынести единый `getCharacterDiff(approved, actual) → CharacterDiff`.
   - сравнивать все semantic поля;
   - нормализовать `states` как multiset экземпляров;
   - включить resources, inventory/equipment и persistent wound fields;
   - исключить `heldBy` и другие operational battle markers.
2. Сделать `isCharacterChanged` только thin helper над `diff.hasChanges`.
3. Разделить:
   - `canStartSession`;
   - `isActiveSessionParticipant`;
   - `needsModeration`;
   - `reviewState`.
4. Сделать `membershipDiff` presentation adapter-ом над `CharacterDiff`.
5. Зафиксировать `gameRevision` и stale-version errors как отдельные guards.

### Acceptance

- `changes_pending` не удаляет active participant;
- `changes_pending`, `returned`, diff или incompatible revision блокируют
  `canStartSession`;
- `returned` не принимает новые commands;
- no-op и повтор idempotent command не создают новый moderation diff;
- одна semantic comparator используется moderation UI и machine predicates.

## R2 — Character authoritative backend

**Владелец:** `Roleplay/Character`.  
**Зависимости:** текущие C0–C2; Mechanic Engine для C3.

Это существующая линия `C3–C5` в
[`../tr/character-roadmap.md`](../tr/character-roadmap.md):

1. **C3 — Mechanic Engine port.**
   Character получает узкий `runEvent` port без импорта Game.
2. **C4 — server build и validation.**
   Сервер принимает build choices, резолвит `(spaceId, revision)`,
   строит actual candidate и fail-closed отклоняет неизвестные handlers,
   broken references и invalid requirements.
3. **C5 — authoritative create/update.**
   Вводится `actual_version`, CAS write, typed validation problems и
   transaction-bound Character mutation service.
4. Добавляется runtime mutation port для typed Character patch.
5. Character read возвращает actual и concurrency token; token не является
   дополнительной версией листа.

### Acceptance

- create/update/validate используют один server-side pipeline;
- stale `actual_version` не меняет actual;
- validation error не меняет actual;
- runtime patch не пересобирает лист из stale `choices` и не теряет
  states/resources/inventory;
- Character не импортирует Game;
- single-entity Character edit самостоятельно транзакционен, а внутри Game
  transaction использует переданный gateway/connection.

## R2-FE — frontend contract и mock readiness

**Владелец:** frontend `Roleplay/Character` и compatibility adapters.  
**Зависимости:** R1.  
**Не заменяет:** backend R2 C3–C5 и не считается server-side validation.

R2-FE нужен, чтобы будущий backend можно было подключить без переписывания
editor и Game consumers. Он фиксирует frontend boundary, но не переводит
active-session combat/editor на actual до готовности Game authoritative
effect/read projection.

### Работы

1. Перевести Character write contract с client-derived `CharacterVersion` на
   typed patch:
   - `commandId`;
   - `expectedActualVersion`;
   - `operations[]`;
   - `replaceSection` для массивов без стабильных идентификаторов;
   - без automatic merge/rebase.
2. Разделить входы:
   - create принимает `choices: CharacterBuild`;
   - validate принимает choices для нового персонажа или patch для existing;
   - update принимает patch с `commandId` и `expectedActualVersion`.
     Patch применяется к candidate actual `CharacterVersion`, а не к
     `CharacterBuild`; derived result остаётся ответом backend/mock.
3. Добавить `actualVersion` в Character detail/runtime projection и typed
   validation/conflict mapping. Не использовать сериализованный лист как CAS
   token.
4. Синхронизировать real/mock API и editor adapter. `CharacterBuild` остаётся
   локальным editor input, `CharacterVersion` — response/preview; mock не
   выдаётся за server-side Mechanic/build validation.
5. Убрать `status` из mutation requests, но временно оставить его в response
   как read-only compatibility field. `gameId` оставить только
   route/navigation context: он не выбирает Character storage path и не
   создаёт отдельный in-game endpoint.
6. Перевести custom-rule commands на тот же `commandId`/CAS envelope; разные
   внешние commands не означают разные storage mechanisms.
7. Зафиксировать Game boundary:
   - multi-entity action отправляется через Game API;
   - Character API не вызывается несколько раз для одной combat operation;
   - NPC остаётся Game-owned;
   - `GameCombatOverlay` и текущие sheet compatibility fields не удаляются
     до R4–R8.
8. Добавить Game-owned `GameRuntimeEntityProjection` и только DTO/fixture для
   post-commit `CharacterChanged`. Реальные
   EventManager, outbox, SSE, Game listener и Chat policy остаются R6.

### Acceptance

- frontend не отправляет derived sheet, owner, status или game limits как
  authority input;
- stale `actualVersion` и validation failure не меняют mock state;
- typed patch для повторяющихся states не зависит от array index;
- custom-rule mutation использует тот же CAS/idempotency envelope;
- standalone и in-game editor используют совместимый request shape, но
  active-session storage migration не объявлена завершённой;
- multi-entity combat принадлежит Game API, а не Character API;
- Character не импортирует Game и не владеет NPC;
- текущий overlay combat mock продолжает работать как compatibility path;
- R2-FE не утверждает готовность backend build, Mechanic Engine, Game
  transaction или SSE.

## R3 — Game session, battle и process foundation

**Владелец:** `Roleplay/Game`.  
**Зависимости:** R1; R2 нужен для effect mutation.

### R3-FE — frontend/mock state foundation

R3-FE вводит opt-in typed `GameSessionState`, `GameBattleState` и
`GameStateSnapshot` с отдельными `sessionId`/`battleId`. Rules context
читается из текущих `Game.spaceId`, `Game.spaceCode` и `Game.rulesRevision`;
отдельный технический `gameRevision` или дублирующий `rulesContext` не
хранится. Пока запущена текущая сессия, эти три поля Game immutable.

Internal mock lifecycle используется только в contract fixtures и не меняет
`Game.status`, не заменяет `IGameApi.stopGameSession` и не переключает legacy
combat UI. В одной session одновременно существует не более одного active
battle; `endBattle` закрывает только battle, а `stopSession` очищает session
state. Повторный command с тем же fingerprint replay-ит result, другой
fingerprint получает conflict, stale versions не мутируют state.

Snapshot не содержит полный roster или sheets. Participant admission использует
batch lookup по entity keys; NPC остаётся Game-owned с `npc.version` и
техническим `npc.actual_version`. Serialize/restore проверяет fixture reload
semantics, но не объявляет browser persistence или production crash recovery.

### R3-FE acceptance

- active session/battle имеют отдельные IDs и monotonic CAS versions;
- второй active battle не создаётся;
- новый battle не наследует transient state предыдущего;
- continuation сохраняет тот же `battleId`;
- closed battle/session и stale versions отклоняются;
- legacy `stopGameSession`, overlay и Game.status остаются compatibility path;
- full Character/NPC sheet не входит в snapshot и roster не вызывает N+1;
- backend multi-entity transactions, Character/NPC mutations, SSE и crash
  recovery остаются R4–R7 scope.

## R4-FE — frontend/mock command readiness

**Статус:** подготовительный workstream; не заменяет production R4.  
**Зависимости:** R2-FE contract seams и R3-FE Game state foundation.

Подготовить opt-in `IGameApi.submitCombatCommand` для одного
single-target attack/defense flow:

```text
decision → process/offer state → authoritative mock resolution
         → actual Character/NPC mutation → command response
```

Frontend отправляет только decisions и expected session/battle/process/entity
versions. Mock result возвращает process transition, typed effects, changed
entity keys и новые actual tokens. Decision commands не меняют actual;
resource spend, damage и persistent state применяются только в applied
transition. Mock command records, idempotency, CAS, rollback и fixture
serialize/restore подготавливают boundary будущего backend.

В этот workstream не входят backend Game, DB transactions, production
Character/NPC mutation ports, SSE, outbox/EventManager, Chat delivery, read
projections, wide/multi-target attack, spells, loot или удаление
`GameCombatOverlay`. Legacy overlay combat остаётся compatibility path.

## R4 — первый authoritative combat vertical slice

**Зависимости:** R2 и R3.

Реализовать только один end-to-end сценарий:

```text
attack decision
  → backend process/offer transition
  → defense decision
  → authoritative resolution
  → atomic Character/NPC actual mutation
  → command response
  → projection update
```

Frontend отправляет решения, но не присылает authoritative damage/effect.
Каждый decision может быть отдельной command; вся атака не обязана быть одним
HTTP-запросом или одной транзакцией. Одной транзакцией является transition,
который фактически применяет несколько effects/entities.

В первый vertical slice входят player и NPC:

- damage;
- wound/injury;
- poison/state;
- resource current values;
- optimistic versions;
- idempotent retry;
- error до commit и timeout после commit.

### Acceptance

- authoritative effect меняет actual в момент применения;
- applied effect не повторяется при retry;
- stale opponent projection не позволяет принять невалидное решение;
- failure multi-target effect откатывает всех affected entities;
- NPC использует `npc.actual_version`, но не approve.

## R5 — read projections и Character detail

**Зависимости:** R2–R4.

**R5-FE status:** frontend/mock read boundary implemented: Game-owned
summary/full projections, moderation batch, paged NPC summaries,
on-demand reads и stale-response guards. Backend projection transport,
production visibility enforcement, SSE и legacy combat read migration
остаются последующими scope.

Frontend delta, 2026-09-30: combined participant candidate search keeps
`query`/`cursor`/`limit` at the mock source boundary; full runtime
projections remain a separate batch request for selected `entityKeys`.

Frontend acceptance evidence, 2026-09-30: manual dev verification confirms
the initiative picker order (characters, separator, NPCs), batch loading of
selected full projections, characteristic checks for characters and
free/fixed rolls for NPCs without a sheet. R2-FE–R7-FE initiative changes
are frozen at this frontend/mock handoff boundary; backend transport and R8
legacy-overlay migration remain separate workstreams.

### Read boundary

- standalone Character detail читает actual через Character API;
- Game roster отдаёт summaries, а не все полные sheets;
- Game запрашивает batch projections только для нужных `entityKeys`;
- NPC roster поддерживает pagination/search/filter;
- полный NPC projection загружается для открытой карточки или active combat
  subset;
- visibility и membership policy применяются до serialization;
- frontend не делает N+1 `CharacterApi.getCharacter()` для roster.

Для игры с сотнями или тысячами NPC первичная загрузка остаётся компактной:
summary list, pagination и on-demand projections. Authoritative backend
работает по entity IDs и не требует загрузки всех `npc.version` на клиента.

### Acceptance

- Character detail и Game combat показывают один actual;
- player/NPC full sheet не находится в `gameOverlay`;
- roster не загружает полный лист 500/10000 NPC;
- parallel detail read не перезаписывает stale local state;
- optimistic conflict заставляет перечитать actual, а не выполнять merge
  неизвестных полей.

## R6-FE — editor и realtime readiness

**Статус:** `PARTIAL/FRONTEND-MOCK IMPLEMENTED`, 2026-09-29.  
R6-FE закрывает frontend/mock boundary, но не является backend Game,
production SSE, outbox или EventManager delivery.

Frontend delta, 2026-09-30: real `game.sync` and subscription transport report
an explicit unsupported error when the future backend is unavailable; mock
realtime remains the only delivery implementation in this scope.

Реализованы:

- единый standalone/in-game Character typed patch flow с `commandId`,
  `expectedActualVersion`, CAS, idempotent retry и conflict mapping;
- post-commit `CharacterChanged` integration seam без импорта Game в Character;
- Game realtime DTO, numeric cursor, `eventId = <gameId>.<cursor>`, bounded
  sync/snapshot fallback и dedupe;
- targeted projection refresh в Game Chat, Characters, Moderation и NPC UI;
- full actual projection для загруженных combat participants без полного roster;
- Character detail invalidation с сохранением dirty local draft и явным выбором
  актуального листа или черновика;
- regression fixtures для CAS, idempotency, actual projection, rollback,
  cursor/snapshot и post-commit event delivery.

Compatibility boundaries, которые не мигрированы R6-FE:

- `GameCombatOverlay`, `mockGameLoot`, DOT/blood-loss и granular overlay
  mutators;
- production Game listener, EventManager after-commit/outbox worker, SSE
  broker, visibility enforcement и database/schema changes.

После R6-FE новые Game consumers не загружают полный roster или все NPC
sheets. Они используют summary/batch/on-demand projections. Production
delivery и durable recovery остаются backend scope.

## R6 — backend EventManager, outbox и realtime integration

**Зависимости:** R2–R5. EventManager sync MVP не расширяется.

### Editor

Обычный Character editor и in-game editor используют одну Character mutation
boundary:

```text
typed patch
  → expectedActualVersion
  → Character validation
  → whole-operation CAS
  → actual mutation
  → CharacterChanged integration fact
```

Per-field merge/rebase не входит в первый release. Для states и других массивов
без stable IDs применяется normalized block replacement.

### Delivery

- authoritative mutation и outbox row создаются в одной transaction;
- EventManager используется как process-local fast path после успешного commit;
- outbox worker обеспечивает повторную post-commit delivery;
- outbox не создаёт Chat message автоматически;
- Game listener обновляет active projections и SSE;
- Chat message для GM direct edit — отдельная explicit policy.

SSE contract:

- monotonic integer cursor на уровне Game session;
- `eventId = <gameId>.<cursor>`;
- command response не заменяется SSE;
- `game.sync(lastCursor)` возвращает missed events или snapshot при stale cursor;
- повторная SSE delivery не выполняет mutation повторно.

### Acceptance

- Character edit во время active session меняет actual через обычный path;
- Game получает `CharacterChanged` только после успешного commit;
- ошибка SSE/Chat не откатывает уже committed mutation;
- outbox retry не создаёт Chat spam;
- duplicate command/event не удваивает effect.

## R7 — lifecycle, moderation и recovery

**Зависимости:** R3–R6.

### R7-FE — frontend/mock readiness

**Статус:** `PARTIAL/FRONTEND-MOCK IMPLEMENTED`, 2026-09-29.

R7-FE добавляет публичную lifecycle boundary для session/battle, разделяет
admission и active participation, разрешает approve active membership через
CAS, отменяет unresolved process state при terminal transitions и покрывает
mock restore/idempotent retry/timeout-after-commit fixtures. Новый
authoritative path не выполняет повторный full-sheet commit при stop.

Legacy `GameCombatOverlay` producers и их compatibility stop commit сохраняются
до R8. Это не backend durable recovery, не production transaction, не SSE,
не outbox и не EventManager delivery.

### Session lifecycle

- `approve` во время active session: CAS actual + membership revision;
- `changes_pending` сохраняется в текущей session;
- `endBattle` отменяет unresolved battle processes/offers и чистит battle markers;
- `stopGameSession` отменяет remaining session processes и чистит transient state;
- applied Character/NPC effects не откатываются;
- новый battle не зависит от процессов предыдущего;
- rules revision update, пока запущена текущая сессия, отклоняется;
- migration active participant отклоняется отдельным session guard.

### Recovery

- last committed actual читается после restart;
- ошибка до commit откатывает transaction;
- timeout после commit восстанавливает result по commandId;
- retry terminal command получает closed-process/closed-battle conflict;
- stale actual/membership/NPC token не допускает last-write-wins.

### Acceptance

- reload/crash не теряет actual;
- незавершённый process не применяется повторно;
- `changes_pending` не исчезает при reconnect;
- approve conflict не меняет actual и approved частично;
- endBattle не закрывает session;
- authoritative stop не вызывает повторный character commit; legacy
  compatibility stop commit остаётся до R8.

## R8 — migration и удаление legacy overlay

**Зависимости:** R4–R7; production overlay migration только если overlay
storage уже существует.

### Migration

Если persisted full-sheet overlay уже появился:

```text
load actual/approved + overlay + expected versions
  → resolve sheet/states/resources/inventory/equipment
  → validate complete actual
  → CAS actual + membership/review metadata
  → mark changes_pending if semantic diff exists
  → clear migrated sheet fields from overlay
```

NPC мигрируется по `npc.actual_version`, без approved/review state. Conflict
не разрешается last-write-wins.

### Frontend/mock cutover (2026-09-30)

В текущем проекте persisted legacy overlay и production database ещё нет,
поэтому отдельная migration job для mock fixtures не создаётся. `Character`
actual (`versions`/runtime mutation seam) и `npc.version` стали единственным
источником листа. `GameCombatOverlay` оставлен только для transient game
markers; editor, combat и loot mock writers больше не сохраняют в него
`sheet`/partial resources/states. Stop не выполняет full-sheet commit.
Granular combat/editor/loot writes используют typed `CharacterPatch`/command
seam с CAS и idempotent replay; UI после mutation обновляет affected runtime
projection, а не transient overlay.

Это подтверждает только R8-FE/mock schema cutover. Backend/PHP/БД,
production migration, authoritative transport и SSE остаются отдельными
открытыми границами.

### Legacy removal (production boundary)

Для текущего frontend/mock cutover эти элементы уже удалены из mock runtime.
Оставшийся список относится только к persisted production backend:

- `GameCombatOverlay.sheet`;
- Character/NPC sheet fields в Game overlay;
- full-sheet `SessionCharacterService.resolve`;
- stop-time full-sheet commit;
- overlay-based machine predicates;
- comments и adapters, объявляющие `approved + overlay` текущим листом.

Сохраняются overlay/state structures для initiative, battles, processes,
offers, pending effects и transient markers.

## R9 — release gate

Переход готов к release review, если:

1. нет второго persisted player Character sheet в Game overlay;
2. actual mutation защищена CAS и idempotency;
3. player и NPC effects проходят через authoritative backend path;
4. `getCharacterDiff` единственный semantic comparator;
5. active participant продолжает session при `changes_pending`;
6. следующая session блокируется до approve;
7. Character detail и Game projection согласованы;
8. endBattle/stop/reload/crash/retry имеют проверенные semantics;
9. SSE/outbox не создают duplicate mutation или Chat spam;
10. rules revision не меняется, пока запущена текущая сессия;
11. migration и legacy cleanup имеют explicit rollback/conflict policy.

## Рекомендуемый ближайший work package

Начать с R1:

1. `getCharacterDiff`;
2. четыре разделённых predicates;
3. mock/frontend tests для pending active participant, returned participant,
   stale version и no-op mutation;
4. после frontend/mock cutover не возвращать `GameCombatOverlay.sheet`;
   production migration остаётся отдельной backend-задачей при появлении
   persisted legacy storage.

Параллельно открыть C3–C5 Character backend как обязательный backend workstream.
