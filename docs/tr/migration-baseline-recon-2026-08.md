# Baseline полного переноса содержания ТР

**Дата:** 2026-08-30  
**HEAD:** `3ea94daf7921b91abc7d78c29cf603f4a73c6da6`

Разрешённый scope этого прохода: `docs/tr/**` и `docs/review/tr-reconciliation-2026-08.md`. Код, тесты, `frontend-rules.md`, спеки, audit и `docs/review/tr-decisions-2026-08.md` не изменяются. `docs/tr/history/TR-legacy-2026-08.md` не редактируется.

Решения reconciliation от 2026-08-30: backend foundation из REC-001 сохраняется как REQUIREMENT с implementation OPEN; snapshot copy из REC-002 выполняется атомарной transaction-коммитом, legacy async queue/progress вариант заменён; anti-escalation групп из REC-003 — текущий authorization invariant; notes из REC-004 уже имеют frontend-контракт для персонажа и игры, backend storage OPEN.

Pre-existing вне этого прохода: `docs/rule/skills/catalog.md`, `docs/specs/battlegroundIdeaContext.md`, `docs/specs/battlegroundImplementationPlan.md`, `docs/review/tr-audit-2026-08.md`, `docs/review/tr-decisions-2026-08.md`.
