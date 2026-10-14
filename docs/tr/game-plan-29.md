# План Game 29 — физические профили атаки

**Статус:** `APPROVED` для backend принятого scope Game 29, 2026-10-11.
Приняты профили `strike`, `throw` и `shoot` внутри уже закрытого контура
Game 28: identity профиля и выбранного экземпляра, атомарный расход
бросаемого предмета и боеприпаса, parity персонажа и NPC, `1 → 1` и
`1 → N`, CAS, replay и rollback. Закрытие не является project-wide
approval.

Вне принятого scope и вне этого статуса:

- cover;
- координаты, карта и battleground;
- authority дальности и falloff: значения Rule остаются информативными и
  не отклоняют атаку и не меняют hit, block, damage или resource path;
- Game 30–33.

Frontend → PHP → delivery E2E, deployment/live verification и полный
production UI-контур не подтверждены. Evidence принятого backend scope:
`GamePhysicalProfileMysqlTest` 13 tests / 768 assertions; Game 28
strike/wide 26 tests / 1608 assertions; полный suite ранее 794 tests /
11776 assertions с одним environment-only Redis skip. В этой
documentation-only сессии тесты не запускались.

Game 30 остаётся следующим отдельным этапом со статусом `TODO` и
автоматически не переводится в `IMPLEMENTED`. Контракт и decision record —
[`../review/plan-game-plan-29-physical-profiles-2026-10-11.md`](../review/plan-game-plan-29-physical-profiles-2026-10-11.md).
Граница Game 28 — [`game-plan-28.md`](game-plan-28.md). Очередь —
[`game-roadmap.md`](game-roadmap.md). Стандарты —
[`php-coding-standards.md`](php-coding-standards.md).
