# Сквозные переходы и границы контрактов

**Статус:** канонический cross-domain контракт, 2026-08-30.

## RuleRevision → CharacterBuild

**Input:** `spaceId`, scoped `revision`, resolved `Rule[]`.  
**Output:** `CharacterBuild` with semantic `code` references, calculated budgets and validation context.  
**Owner:** `rule-system.md` resolves; `character-system.md` consumes.  
**Visibility:** only rules visible to the selected space and actor are resolved.  
**Version invariant:** the build records the selected `(spaceId, revision)`; later publication cannot mutate it silently.  
**Error/concurrency:** stale revision or failed validation returns a typed error and no build mutation.  
**Backend boundary:** revision loading and backend snapshot persistence remain `OPEN`.

## CharacterBuild → GameMembership

**Input:** validated character version and game context.  
**Output:** membership with `characterId`, immutable `approvedCharacterVersion`, review/concurrency metadata and Game-owned session state.
**Owner:** `character-system.md`.  
**Visibility:** membership is filtered by game role and sheet audience before serialization.  
**Version invariant:** `approvedCharacterVersion` остаётся immutable baseline, а `actualCharacter` может изменяться authoritative game effects и разрешённым Character edit во время running session. `canStartSession` использует `getCharacterDiff`; active participant определяется отдельно и не требует `actualCharacter == approvedCharacterVersion`.
**Error/concurrency:** version mismatch is a retriable conflict, not validation success.  
**Backend boundary:** membership persistence and moderation authorization remain `OPEN`.

## GameMembership → GameState → ChatAttachment

**Input:** authoritative Character/NPC projection из actual storage плюс Game-owned `gameOverlay/gameState` для initiative, battle/process/offers/effects и transient markers.
**Output:** generic Chat `Attachment` with domain type and opaque payload.  
**Owner:** Game owns payload; Chat owns transport and render registries.  
**Visibility:** Game filters payload visibility before handing it to Chat; rendering cannot widen access.  
**Version invariant:** battle/process mutations require `battleId` and expected Game/process version; Character/NPC mutations require their expected actual version. Applied authoritative effects обновляют actual в той же transaction, что и затронутый Game state.
**Error/concurrency:** conflicting Game/process or Character/NPC actual versions return `currentVersion` for a retriable read.

R3-FE может предоставить frontend/mock `GameStateSnapshot` и internal lifecycle
contract для session/battle fixtures. Эти DTO не заменяют backend Game command,
transaction или SSE boundary; legacy `stopGameSession` и granular overlay API
остаются compatibility path до последующих этапов.
R4-FE добавляет только opt-in Game command contract для решений attack/defense:
`IGameApi.submitCombatCommand` принимает decisions и expected versions, а
authoritative mock effect возвращает process transition, typed effects,
changed entity keys и актуальные tokens. Actual Character/NPC mutation
происходит только в applied transition; новый path не вызывает Character API,
legacy overlay mutators, Chat API или read projections. Это frontend/mock
readiness, не backend transaction или SSE implementation.
**Backend boundary:** delivery, persistence and SSE are backend `OPEN`; frontend mock/polling is not SSE implementation.

R6-FE реализует только client/mock seam поверх этой границы: Character
publishes post-commit `CharacterChanged`, Game сопоставляет его с active
membership и создаёт affected entity event, а consumers делают targeted
projection refresh. `eventId` имеет формат `<gameId>.<cursor>`; command
response, realtime event и Chat message не являются взаимозаменяемыми.
Production after-commit/outbox, SSE delivery, authorization и visibility
filtering остаются backend requirements.

## GameScene → spatial combat / PlayerSceneProjection

**Input:** `current` GameScene, actor tokens, occupancy, openings, выбранный активный персонаж.  
**Output:** `PlayerSceneProjection` (токены и области по vis); для атаки — дистанция, LOS, flank.  
**Owner:** [`battleground-system.md`](battleground-system.md). Combat читает spatial context; атака не является командой сцены.  
**Visibility:** сервер фильтрует projection; fog клиента не расширяет доступ.  
**Version invariant:** мутации сцены несут `sceneVersion`.  
**Error/concurrency:** stale version — conflict, не успех валидации.  
**Backend boundary:** blob, SSE, stamp occupancy/navmesh — `OPEN`; реализация `NOT_IMPLEMENTED`.

## События игры → уведомления

**Вход:** типизированный факт события, ключ, круг получателей, версия объекта.  
**Выход:** DTO уведомления по шаблону: получатель, кнопки, ключ дедупликации.  
**Владелец:** на фронте Character и Game вызывают публичный API `Messages/Notifications` (`init` / порт); Notifications не импортирует Roleplay. Отображение — [`ui-system.md`](ui-system.md). Генерация на сервере — `OPEN`. Отправка с фронта в текущем коде не сделана (долг кода).  
**Видимость:** получатель и объект проверяются до генерации и сериализации.  
**Версии:** повтор `(получатель, ключ события, версия объекта)` обновляет существующее уведомление.  
**Ошибки:** сбой продюсера не оставляет частично показанное уведомление; доставка идемпотентна.  
**Граница backend:** хранение событий, генерация и дедупликация — `OPEN`.

## Economy → Actual/GameState

**Input:** `EconomyOperation` with actor, source, target, quantity, balance, idempotency key and expected versions.  
**Output:** validated actual-state mutation and operation result; operation record is the typed idempotency/concurrency boundary. Frontend Economy API is `NOT IMPLEMENTED`.
**Owner:** `game-system.md` owns gameplay economy; Character/NPC own their persisted sheet mutation ports.
**Visibility:** only authorized actor and visible source/target are eligible.  
**Version invariant:** balance, Character/NPC actual versions and Game state versions must match expected versions atomically.
**Error/concurrency:** insufficient balance, duplicate idempotency key or stale version returns typed error without partial mutation.  
**Backend boundary:** transaction, journal and idempotency persistence remain `OPEN`; existing frontend `distributeLoot` is a separate loot flow, not the complete `EconomyOperation` API.

## Permissions → Routes/Actions

**Input:** actor, route/action, object context and required permission keys.  
**Output:** route decision or typed action authorization result.  
**Owner:** `auth-system.md` owns ordered authorization; domain documents own object-specific policy.  
**Visibility:** route visibility is coarse; object and sheet visibility are checked before response serialization.  
**Version invariant:** mutating actions use the current object version where optimistic concurrency applies.  
**Error/concurrency:** ordered checks are group bypass, global permission, object permission/role, ownership, then deny; stale version returns conflict.  
**Backend boundary:** server-side enforcement and effective permission resolution remain `OPEN`.

## Shared error and concurrency envelope

Every transition returns either a typed result or `{ code, message, field?, currentVersion? }`. Optimistic conflicts are retriable reads, not validation success. Visibility filtering happens before serialization, and backend-open transitions are never labelled `IMPLEMENTED`.

## Evidence boundary for transition cards

For the transition cards the evidence is separated into three layers:

- **Shape:** frontend DTOs, interfaces and attachment payloads prove field names and discriminated forms only.
- **Behavior:** frontend services, stores and route guards prove only the listed client-side orchestration; they do not prove server authorization, persistence or atomicity.
- **Backend requirement:** version checks, visibility filtering, idempotency, journal, transactions, SSE delivery and server-side authorization remain `REQUIREMENT`/`OPEN` until backend evidence is available.

Consequently, a complete card documents the contract boundary, but does not by itself establish implementation readiness.
