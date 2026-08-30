# Baseline корректирующего прохода ТР

**Дата:** 2026-08-30  
**HEAD:** `3ea94daf7921b91abc7d78c29cf603f4a73c6da6`

Этот baseline фиксирует состояние после предыдущей миграции и перед исправлением claims, evidence, статусов и границ current/legacy.

## Уже существующие изменения

- tracked: `docs/rule/skills/catalog.md`, `docs/specs/battlegroundIdeaContext.md`, `docs/tr/TR.md`;
- untracked: предыдущие audit, decisions, specs и документы `docs/tr/**`.

Изменения вне `docs/tr/**` являются pre-existing и не относятся к корректирующему проходу.

## Scope прохода

Новые изменения разрешены только внутри `docs/tr/**`. Код, тесты, `frontend-rules.md`, `docs/specs/**` и audit не изменяются. Legacy archive должен оставаться byte-for-byte копией `HEAD:docs/tr/TR.md`.
