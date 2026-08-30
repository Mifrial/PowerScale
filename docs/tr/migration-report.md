# Отчёт миграции ТР

**Дата:** 2026-08-30  
**Статус:** verification report; не является источником текущих доменных контрактов.
**Verification context:** baseline HEAD `3ea94daf7921b91abc7d78c29cf603f4a73c6da6`; рабочее дерево содержит uncommitted document migration changes.
**Scope:** документальная миграция; код, тесты, `frontend-rules.md`, audit и дизайн-спеки не изменялись в рамках этого прохода. Этот проход: `docs/tr/**` и `docs/review/tr-reconciliation-2026-08.md`.

## Результат покрытия

- Legacy archive: `3748/3748` строк, byte-for-byte совпадение с `HEAD:docs/tr/TR.md`.
- Reconciliation coverage: journal [`../review/tr-reconciliation-2026-08.md`](../review/tr-reconciliation-2026-08.md) classifies ranges 33–3748; coverage is the journal, not claim count. EXTRA attribution for CLM-038/039/044/049 corrected to CODE/DECISION.
- Claim registry: `56` parent claim-групп и `94` atomic child claims (`150` записей всего) с диапазонами, disposition, destination и owner.
- Status matrix: `56` parent summary rows и `94/94` atomic rows имеют `sourceStatus`, `implementationStatus` и `documentStatus`; backend-only claims не помечены `IMPLEMENTED`.
- Evidence classification: `72` `EXACT`, `22` `DISPOSITION_ONLY`, `0` `INCOMPLETE` из `94` atomic claims. `EXACT` означает существующий path, inclusive range и подтверждённый symbol/test/fixture; `DISPOSITION_ONLY` — явный `LEGACY_ONLY` или backend-open gap без runtime claim. `EXACT` не означает полноту runtime-поведения: evidence kind отдельно различает `SHAPE`, `BEHAVIOR`, `TEST` и `DECISION`.

Формулы metrics: `EXACT` — запись с валидными path/range и подтверждённым symbol/test/fixture; `DISPOSITION_ONLY` — запись без runtime-утверждения, явно помеченная `LEGACY_ONLY` или `BACKEND_OPEN`; `INCOMPLETE` — любая запись, не попавшая в первые две категории. `total = EXACT + DISPOSITION_ONLY + INCOMPLETE = 72 + 22 + 0 = 94`; runtime/release readiness всё равно требует behavior, backend и content evidence.
- Decisions: `DEC-001`—`DEC-058` присутствуют в каноническом реестре и source decision log; `DEC-058` фиксирует numeric canonical IDs и thematic aliases.
- RuleType readiness matrix: `19/19` типов из `RuleType.ts` имеют четыре независимые оси; magic/content deferred остаются явными.
- Local Markdown links в `docs/tr`: `0` broken links.
- Linter diagnostics для мигрированных документов: `0`.

## Проверенные cross-domain связи

- Rule ↔ Character: Character references rules by `code`/revision and consumes Rule-derived characteristics, abilities, requirements and resources.
- Character ↔ Game: membership owns active/latest/pending/overlay semantics; game limits and GM bonuses participate in validation and migration.
- Space revision ↔ membership: game/character context carries space and revision; published revision is resolved by `(spaceId, revision)` and `publishedAt`.
- Game ↔ Chat: Game owns game chat and roll/check attachments; Chat remains an opaque plugin host and does not import Game internals.
- Economy ↔ overlay: player gameplay changes go through overlay; NPC changes go to `npc.version`; typed economy operations and immutable journal remain backend requirements.
- Permissions ↔ routes/actions: auth document maps user/group/object permissions to the route and action families; backend effective-permission evaluation remains `OPEN`.
- Full transition cards are in [`cross-domain.md`](cross-domain.md), including payload, owner, visibility and concurrency/error boundaries.

## Незакрытые области

`OPEN`/`REQUIREMENT` claims intentionally remain visible in the registry. Главные группы:

- backend physical schema and endpoints;
- SSE authorization, reconnect, retention and server-side visibility filtering;
- economy transaction/journal implementation;
- full NPC backend lifecycle;
- runtime magic mechanics and content classification until the real content export.

## Раздельный readiness verdict

- **Documentation-ready:** `YES` — canonical owners, registry, status matrix, evidence inventory и local links согласованы.
- **Frontend-contract-ready:** `CONDITIONAL` — основные DTO/API и UI contracts описаны, но зафиксирован `CODE_GAP` прямых внутренних импортов Game и Chat polling silent-catch.
- **Backend-ready:** `NO` — schema, endpoints, persistence, transactions, idempotency, server-side authorization и SSE implementation остаются `OPEN`/`REQUIREMENT`.
- **Release-ready:** `NO` — backend gaps, deferred magic/content и environment limitation `FETCH_ERROR` не позволяют объявить release readiness.

Это не новые решения и не скрытая корректировка кода. Claims сохранены с исходным legacy range и ссылкой на evidence; спорный backend-контракт не был повышен до current.

## Документы и canonical owners

`TR.md` остаётся индексом. Технические контракты распределены по `architecture.md`, `data-model.md`, `auth-system.md`, `rule-system.md`, `character-system.md`, `game-system.md`, `chat-system.md` и `ui-system.md`; решения и исторические материалы находятся в `decisions.md` и `history.md`. Трассировка переноса находится в `migration-map.md`, `migration-parent-inventory-2026-08.md`, `migration-claims.md`, `migration-claims-status.md`, `migration-evidence-inventory-2026-08.md` и `migration-evidence-index.md`.

Второй проход дополнительно зафиксирован в [`migration-baseline-second-2026-08.md`](migration-baseline-second-2026-08.md), корректирующий проход — в [`migration-baseline-correction-2026-08.md`](migration-baseline-correction-2026-08.md), полный проход трассировки — в [`migration-baseline-traceability-2026-08.md`](migration-baseline-traceability-2026-08.md), точечная доработка — в [`migration-baseline-pass2-2026-08.md`](migration-baseline-pass2-2026-08.md); cross-domain owner — [`cross-domain.md`](cross-domain.md).

## Ограничения результата

Диапазоны evidence в claim registry относятся к immutable legacy archive. Для части backend-only claims путь к runtime implementation отсутствует по определению; такие записи отмечены `OPEN`, `REQUIREMENT` или `BACKEND_OPEN`, а не выданы за реализованные. Все pre-existing changes из [`migration-baseline-2026-08.md`](migration-baseline-2026-08.md) остаются отделёнными от изменений этого прохода.

Targeted Vitest verification could not start because the environment could not fetch `vitest` from the configured npm mirror (`FETCH_ERROR`); this is an environment limitation, not a test pass.
