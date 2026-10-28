# План Game 33 — frontend → PHP E2E (gate P4)

**Статус:** ~СДЕЛАНО~ (2026-10-28; P4 подтверждён, восстановление SSE — отдельный остаток)

**Реализация:** ~PHP~, ~VUE~

**Опираться на:** ~PHP~ (команды и тесты), ~НЕ ОСНОВА~ (моки фронта — контракт UX, не образец реализации)

## Цель

Подтвердить сквозной сценарий readiness P4:

```text
пользователь A
  → реальный HTTP
  → Game command
  → PHP transaction
  → Character actual / NPC version
  → delivery
  → пользователь B видит новую версию
```

## Что подтверждено

Прогон на двух независимых cookie-профилях при `VITE_API_MODE=real`:

- `1 → 1` и `1 → N`;
- реакции `dodge` / `block` / `ignore`;
- stale version и конфликт версии;
- replay и idempotency;
- обновление actual Character и `npc.version`;
- delivery/SSE и обновление второго клиента.

Подтверждено пользователем 2026-10-28. Inventory, решения D1–D4 и Q1–Q2 —
[`../review/plan-game-plan-33-e2e-2026-10-12.md`](../review/plan-game-plan-33-e2e-2026-10-12.md).

## Остаток

- Восстановление SSE после обрыва в real-режиме: `GameChatTab.recoverRealtime()`
  зовёт `IGameRealtimePort::sync()`, а `GameRealtimeApi::sync()` бросает
  `GAME_REALTIME_UNAVAILABLE`; ошибка глотается. Переподключение должно идти
  повторным `openSse` с `lastCursor`, без `game.sync`.
- Магия и всплеск по-прежнему пишут часть состояний локально
  (`SpellCastDialog`, `persistBurstDamage`); это вне P4.

## Границы

Backend 28–32 не переоткрывается. G22 закрыт отдельно через Character C11
(`putDamageSplit`). Deployment/live verification и полный production UI-контур
в этот статус не входят.
