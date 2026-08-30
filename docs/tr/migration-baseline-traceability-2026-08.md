# Baseline полного прохода трассировки claims

**Дата:** 2026-08-30  
**HEAD:** `3ea94daf7921b91abc7d78c29cf603f4a73c6da6`

Этот baseline фиксирует рабочее дерево перед полной декомпозицией 56 parent claims и исправлением exact evidence.

## Pre-existing changes

- tracked: `docs/rule/skills/catalog.md`, `docs/specs/battlegroundIdeaContext.md`, `docs/tr/TR.md`;
- untracked: audit, decision log, specs и ранее созданные документы `docs/tr/**`.

Изменения вне `docs/tr/**` являются pre-existing и не входят в этот проход.

## Разрешённый scope

Новые изменения — только в `docs/tr/**`. Код, тесты, `frontend-rules.md`, `docs/specs/**`, audit и source decision log не изменяются. `history/TR-legacy-2026-08.md` должен оставаться byte-for-byte копией `HEAD:docs/tr/TR.md`.
