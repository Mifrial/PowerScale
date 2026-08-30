# Baseline точечной доработки ТР

**Дата:** 2026-08-30  
**HEAD:** `3ea94daf7921b91abc7d78c29cf603f4a73c6da6`

Этот baseline фиксирует состояние перед исправлением exact evidence, остаточной atomic decomposition, cross-domain cards и evidence metrics.

## Pre-existing changes

- `docs/rule/skills/catalog.md`;
- `docs/specs/battlegroundIdeaContext.md`;
- `docs/tr/TR.md`;
- ранее созданные audit, decision log, specs и документы `docs/tr/**`.

Изменения вне `docs/tr/**` не входят в этот проход и должны сохраниться без изменений.

## Разрешённый scope

В рамках прохода разрешены только изменения в `docs/tr/**`. Код, тесты, `frontend-rules.md`, `docs/specs/**`, audit и source decision log не изменяются. `docs/tr/history/TR-legacy-2026-08.md` не редактируется и должен оставаться byte-for-byte копией `HEAD:docs/tr/TR.md`.
