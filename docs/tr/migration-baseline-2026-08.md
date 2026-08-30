# Baseline миграции ТР

**Дата:** 2026-08-30  
**Коммит:** `3ea94da` (`dev`, `origin/dev`)

Этот файл фиксирует состояние рабочего дерева до текущего прохода полноценной миграции. Изменения ниже существовали до начала миграции и не должны приписываться ей.

## Уже изменённые tracked-файлы

- `docs/rule/skills/catalog.md`
- `docs/specs/battlegroundIdeaContext.md`
- `docs/tr/TR.md`

## Уже существовавшие untracked-файлы

- `docs/review/tr-audit-2026-08.md`
- `docs/review/tr-decisions-2026-08.md`
- `docs/specs/battlegroundImplementationPlan.md`
- `docs/tr/architecture.md`
- `docs/tr/auth-system.md`
- `docs/tr/character-system.md`
- `docs/tr/chat-system.md`
- `docs/tr/decisions.md`
- `docs/tr/game-system.md`
- `docs/tr/history.md`
- `docs/tr/migration-map.md`
- `docs/tr/rule-system.md`
- `docs/tr/ui-system.md`

## Важное ограничение сравнения

`docs/tr/TR.md` уже был сокращён до индекса до начала этого прохода. Исходным legacy-источником миграции остаётся `HEAD:docs/tr/TR.md`, а не рабочая копия из baseline.
