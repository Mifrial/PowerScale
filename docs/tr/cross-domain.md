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
**Output:** membership with `activeVersion`, `latestVersion`, `pendingVersion` projection and optional `overlay`.  
**Owner:** `character-system.md`.  
**Visibility:** membership is filtered by game role and sheet audience before serialization.  
**Version invariant:** `activeVersion` is immutable during a running session; approve/reject carries the expected projection token.  
**Error/concurrency:** version mismatch is a retriable conflict, not validation success.  
**Backend boundary:** membership persistence and moderation authorization remain `OPEN`.

## GameMembership → GameOverlay → ChatAttachment

**Input:** effective `activeVersion + overlay` combat state.  
**Output:** generic Chat `Attachment` with domain type and opaque payload.  
**Owner:** Game owns payload; Chat owns transport and render registries.  
**Visibility:** Game filters payload visibility before handing it to Chat; rendering cannot widen access.  
**Version invariant:** overlay mutations require the expected game/member version.  
**Error/concurrency:** conflicting overlay versions return `currentVersion` for a retriable read.  
**Backend boundary:** delivery, persistence and SSE are backend `OPEN`; frontend mock/polling is not SSE implementation.

## GameEvents → Notifications

**Input:** typed event fact with event key, recipient scope and object version.  
**Output:** notification DTO resolved from a template, recipient, buttons and deduplication key.  
**Owner:** Game event producers emit facts; `ui-system.md` owns notification presentation.  
**Visibility:** recipient and object visibility are checked before generation and serialization.  
**Version invariant:** duplicate `(recipient, event key, object version)` updates the existing notification.  
**Error/concurrency:** producer failure must not create a partially presented notification; duplicate delivery is idempotent.  
**Backend boundary:** event persistence, generation and deduplication remain backend `OPEN`.

## Economy → Overlay

**Input:** `EconomyOperation` with actor, source, target, quantity, balance, idempotency key and expected versions.  
**Output:** validated overlay mutation and operation result; journal entry is append-only evidence.  
**Owner:** `game-system.md` owns gameplay economy and overlay semantics.  
**Visibility:** only authorized actor and visible source/target are eligible.  
**Version invariant:** balance and overlay versions must match the expected versions atomically.  
**Error/concurrency:** insufficient balance, duplicate idempotency key or stale version returns typed error without partial mutation.  
**Backend boundary:** transaction, journal and idempotency persistence remain `OPEN`.

## Permissions → Routes/Actions

**Input:** actor, route/action, object context and required permission keys.  
**Output:** route decision or typed action authorization result.  
**Owner:** `auth-system.md` owns ordered authorization; domain documents own object-specific policy.  
**Visibility:** route visibility is coarse; object and sheet visibility are checked before response serialization.  
**Version invariant:** mutating actions use the current object version where optimistic concurrency applies.  
**Error/concurrency:** ordered checks are super-admin bypass, global permission, object permission/role, ownership, then deny; stale version returns conflict.  
**Backend boundary:** server-side enforcement and effective permission resolution remain `OPEN`.

## Shared error and concurrency envelope

Every transition returns either a typed result or `{ code, message, field?, currentVersion? }`. Optimistic conflicts are retriable reads, not validation success. Visibility filtering happens before serialization, and backend-open transitions are never labelled `IMPLEMENTED`.

## Evidence boundary for transition cards

For all six cards the evidence is separated into three layers:

- **Shape:** frontend DTOs, interfaces and attachment payloads prove field names and discriminated forms only.
- **Behavior:** frontend services, stores and route guards prove only the listed client-side orchestration; they do not prove server authorization, persistence or atomicity.
- **Backend requirement:** version checks, visibility filtering, idempotency, journal, transactions, SSE delivery and server-side authorization remain `REQUIREMENT`/`OPEN` until backend evidence is available.

Consequently, a complete card documents the contract boundary, but does not by itself establish implementation readiness.
