# Baseline следующего этапа доработки ТР

**Дата:** 2026-08-30  
**HEAD:** `3ea94daf7921b91abc7d78c29cf603f4a73c6da6`

Этот baseline фиксирует состояние перед corrective pass по parent-status aggregation, evidence semantics, frontend DAG gaps, backend boundaries, Chat sync и RuleType readiness.

## Scope

Разрешены только изменения внутри `docs/tr/**`. Код, тесты, `frontend-rules.md`, `docs/specs/**`, audit, source decision log и plan file не изменяются. `docs/tr/history/TR-legacy-2026-08.md` не редактируется и должен оставаться byte-for-byte копией `HEAD:docs/tr/TR.md`.

Изменения вне `docs/tr/**` являются pre-existing и не относятся к этому этапу:

- `docs/rule/skills/catalog.md`;
- `docs/specs/battlegroundIdeaContext.md`;
- `docs/review/tr-audit-2026-08.md`;
- `docs/review/tr-decisions-2026-08.md`;
- `docs/specs/battlegroundImplementationPlan.md`.
