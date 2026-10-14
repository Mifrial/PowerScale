# План Game 30 — immediate consequences

**Статус:** `APPROVED` для backend принятого scope Game 30, 2026-10-11.
Принят единый атомарный pipeline immediate consequences внутри уже
закрытого close `1 → 1` и `1 → N`: ordinary injury и `putDamageSplit`,
state hooks, stun/shock/knockout и проверки Воли/увечья для игрока и NPC,
чат игры и delivery в той же транзакции close. Закрытие не является
project-wide approval.

Вне принятого scope и вне этого статуса:

- sidecar экземпляра раны и `heldBy` — Game 31;
- DOT, clotting, bleeding и конец хода — Game 32;
- frontend → PHP → UI E2E — Game 33;
- cover, range и falloff;
- закрытие C11 и G22; `putState` не является kind C11.

Frontend → PHP → delivery E2E, deployment/live verification и полный
production UI-контур не подтверждены. Residual evidence: focused MySQL не
покрывает рану-state, пропуск проверки Воли, увечье, blunt AND и chat
thread/replay; эти пути закрыты чтением кода. В этой documentation-only
сессии PHPUnit, MySQL и frontend не запускались.

Game 31 остаётся следующим отдельным этапом со статусом `TODO` и
автоматически не переводится в `IMPLEMENTED`. Контракт и decision record —
[`../review/plan-game-plan-30-immediate-consequences-2026-10-11.md`](../review/plan-game-plan-30-immediate-consequences-2026-10-11.md).
Границы — [`game-plan-28.md`](game-plan-28.md),
[`game-plan-29.md`](game-plan-29.md). Очередь —
[`game-roadmap.md`](game-roadmap.md). Стандарты —
[`php-coding-standards.md`](php-coding-standards.md).
