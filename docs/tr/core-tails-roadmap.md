# Нарезка хвостов Core: Guest и страница членов

**Статус:** 2026-09-04. Guest и страница членов **сделаны**. Канон гостя — [`auth-system.md`](auth-system.md) § Guest. Члены — [`user-plan-06-members-page.md`](user-plan-06-members-page.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Два независимых захода (нет FK друг на друга). Guest сделан. Дальше — страница членов.

## 1. Auth — гостевая сессия — сделано

Подробно: [`auth-plan-04-guest.md`](auth-plan-04-guest.md). Cookie без `user_id`, Vue `kind === 'guest'`, `currentUser === null`. Не VK, не права гостя на `user.*`.

## 2. User — страница членов группы — сделано

Подробно: [`user-plan-06-members-page.md`](user-plan-06-members-page.md). `userGroup.getMembers` как `{ items, total }` + limit/offset. Не фильтр q, не sort колонок.

## Параллелить нельзя

- Гостевую сессию с User#0 / группой «Гость» как учётка.
- Страницу членов с dump «все id до 500 без total».
