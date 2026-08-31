# Статусы и владельцы контрактов

**Статус:** канонический документ классификации ТР, 2026-08-30.

## Оси статусов

Статусы не объединяются в один lifecycle:

- `sourceStatus`: `CODE_CONFIRMED`, `TEST_CONFIRMED`, `DECISION_CONFIRMED`, `LEGACY_ONLY`, `CONFLICT`;
- `implementationStatus`: `IMPLEMENTED`, `PARTIAL`, `MOCK_ONLY`, `BACKEND_OPEN`, `NOT_IMPLEMENTED`;
- `documentStatus`: `CURRENT`, `REQUIREMENT`, `OPEN`, `DEFERRED`, `BACKLOG`, `HISTORICAL`.

`sourceStatus` описывает основание утверждения, `implementationStatus` — состояние реализации, `documentStatus` — роль записи в ТР.

Пример: принятое решение о журнале экономики имеет `sourceStatus: DECISION_CONFIRMED`, `implementationStatus: BACKEND_OPEN`, `documentStatus: REQUIREMENT`.

## Canonical owners

Один контракт имеет одного владельца:

- `architecture.md` — модули, DAG, слои, ServiceLocator, инфраструктурные границы и общие frontend-правила;
- `data-model.md` — legacy-backed backend schema requirements, поля, связи и индексы;
- `auth-system.md` — users, groups, sessions, permissions, security и user routes;
- `rule-system.md` — Rule DTO, RuleType, Rule Engine, Space, revisions, catalog и publication;
- `character-system.md` — Character, versions, creation, validation, inventory и membership;
- `game-system.md` — Game, sessions, combat, abstract movement, chronicle, economy и loot;
- `battleground-system.md` — SceneTemplate, GameScene, SceneSpace, Enclosure, SupportSurface, Token, Obstacle, Watercourse, Opening, occupancy, projection, sceneVersion, spatial combat context, SSE сцены;
- `chat-system.md` — Chat protocol, attachments, commands, sync и visibility;
- `ui-system.md` — routes/UI behavior, notifications и frontend acceptance criteria;
- `decisions.md` — индекс решений, без повторения доменных контрактов;
- `history.md` — только superseded/historical material;
- `migration-map.md` и `migration-claims.md` — только трассировка миграции, не канонические контракты;
- `../review/tr-reconciliation-2026-08.md` — классификация фрагментов legacy 33–3748;
- `migration-claims-status.md` — нормализованная матрица трёх осей статусов для claims;
- `TR.md` — индекс, glossary и правила чтения.

Если контракта нет в списке владельцев, его нельзя добавлять в произвольный документ без обновления этой карты.

## Evidence

Каждый claim в migration registry должен ссылаться на:

- файл и диапазон строк;
- тест или fixture, если они подтверждают поведение;
- commit, если важна дата или историческая граница;
- `DEC-ID`, если основание — решение.

Ссылка считается проверенной только если целевой файл и диапазон существуют.

## Термины

Ключевые термины должны использоваться единообразно:

- `revision` и `publishedAt`;
- `actualCharacter`, `approvedCharacterVersion`, `sessionCharacterVersion`;
- `gameOverlay`;
- `keyword`;
- `contentStatus`;
- `OPEN`, `REQUIREMENT`, `BACKEND`, `BACKLOG`, `DEFERRED`, `HISTORICAL`.

`revision` — numeric publication number scoped by `spaceId`; `publishedAt` — immutable timestamp of that publication. `code` — semantic stable reference used between domain objects; database `id`/UUID — internal storage identifier. `sessionCharacterVersion` — derived from immutable `approvedCharacterVersion` and mutable `gameOverlay`; `actualCharacter` — the single current character state. Character history and A/L/O/P are not part of the target contract.

`contentStatus` describes editorial readiness and must not be confused with `runtime-support`, which describes whether the engine can execute the content. `validation` is a structured result (`valid` plus `problems[]`), not a lifecycle status. `visibility` controls delivery/access and is independent from lifecycle.

`draft` is reserved for local editor state or legacy storage labels. It is not the canonical in-game mutation layer. In-game mutations use `gameOverlay`; `sessionCharacterVersion` is its derived projection from `approvedCharacterVersion`; `actualCharacter` is the single current state outside the active session.
