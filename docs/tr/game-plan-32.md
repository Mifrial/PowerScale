# План Game 32 — конец хода и продолжение действия

**Статус:** `APPROVED` для backend принятого scope Game 32, 2026-10-12.
Приняты конец хода и продолжение действия внутри уже принятого close
`1 → 1` и `1 → N`: `endTurn` (clotting, кровь, DOT, проверки, refill F1
уходящего и сдвиг курсора), долг ОД на `declareStrike` /
`declareWideStrike`, `continueAction` / `abortAction`, снятие слота долга
на `leave` и выбывании из roster, replay и CAS для персонажа и NPC.
Закрытие не является project-wide approval.

Вне принятого scope и вне этого статуса:

- магия: каст, duration заклинания, отклонение;
- сцена: battleground, координаты, cover, range, falloff;
- `N → N`;
- сутки;
- frontend → PHP → UI E2E — Game 33;
- закрытие C11 и G22;
- константа `WOUND_STATE_CODE` в PHP;
- `blood_clotting` в реестре Mechanic `runEvent`.

Frontend → PHP → delivery E2E, deployment/live verification и полный
production UI-контур не подтверждены. Residual evidence: focused MySQL не
assert’ит `delivery.source` и увечье DOT; эти пути закрыты чтением кода.
Полный suite в verification не запускался. В этой documentation-only
сессии PHPUnit, MySQL и frontend не запускались.

Game 33 остаётся следующим отдельным этапом со статусом `TODO` и
автоматически не переводится в `IMPLEMENTED`. Контракт и decision record —
[`../review/plan-game-plan-32-end-of-turn-2026-10-12.md`](../review/plan-game-plan-32-end-of-turn-2026-10-12.md).
Границы — [`game-plan-28.md`](game-plan-28.md),
[`game-plan-29.md`](game-plan-29.md),
[`game-plan-30.md`](game-plan-30.md),
[`game-plan-31.md`](game-plan-31.md). Очередь —
[`game-roadmap.md`](game-roadmap.md). Стандарты —
[`php-coding-standards.md`](php-coding-standards.md).
