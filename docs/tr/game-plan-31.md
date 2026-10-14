# План Game 31 — wounds

**Статус:** `APPROVED` для backend принятого scope Game 31, 2026-10-12.
Приняты экземпляры ран внутри уже принятого close `1 → 1` и `1 → N`:
уникальный `id` строки `sheet.states`, sidecar, `heldBy`, команды
`game.woundAction`, `next_action_cost`, карта `pending_action_effects` на
открытом бою и снятие зажима на `leave` для персонажа и NPC. Закрытие не
является project-wide approval.

Вне принятого scope и вне этого статуса:

- DOT, tick кровотечения и прибавка `clotting` — Game 32;
- frontend → PHP → UI E2E — Game 33;
- закрытие C11 и G22; `putState` не является kind C11;
- константа `WOUND_STATE_CODE` и формулы `tick` фронта в PHP.

Frontend → PHP → delivery E2E, deployment/live verification и полный
production UI-контур не подтверждены. В этой documentation-only
сессии PHPUnit, MySQL и frontend не запускались.

Game 32 остаётся следующим отдельным этапом со статусом `TODO` и
автоматически не переводится в `IMPLEMENTED`. Контракт и decision record —
[`../review/plan-game-plan-31-wounds-2026-10-11.md`](../review/plan-game-plan-31-wounds-2026-10-11.md).
Границы — [`game-plan-28.md`](game-plan-28.md),
[`game-plan-29.md`](game-plan-29.md),
[`game-plan-30.md`](game-plan-30.md). Очередь —
[`game-roadmap.md`](game-roadmap.md). Стандарты —
[`php-coding-standards.md`](php-coding-standards.md).
