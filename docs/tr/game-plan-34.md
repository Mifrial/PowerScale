# План Game 34 — ручная правка листа в сессии

**Статус:** implementation plan, 2026-10-16. Не заменяет Game 33 (E2E `TODO`).

HTTP `game.manualSheet`: ведущий или владелец цели выставляет current ресурса и
добавляет / меняет / снимает состояние на actual Character / `npc.version`.
Каждый успех пишет одно текстовое сообщение в игровой чат. Overlay не
authoritative storage.

G10 запретил клиентский `setResourceCurrent` как кусок полного патча. Этот
заход вводит **серверный** kind с клампом по `ResourceSpec` / лимиту. Текст
G10 не переписывается.

## HTTP

`game.manualSheet`, csrf. Тело: `idempotencyKey`, `target: {kind, id}`,
`expectedSheetVersion`, `operation`, `details`. Сессия обязательна, бой нет.
Журнал `game_manual_sheet_command`, уникальность `(game_id, idempotency_key)`,
replay через `GameReplayTransaction`. Stop сессии снимает строки
(`GameBattleCleanup`). Delivery source `manual_sheet`; ключи листа как у
экономики. Ответ `{ actualVersion }`.

Запись листа — `GameStrikeSheetWrites::write`. Видимость —
`GameCardAccess::isVisible`.

ACL цели: owner / `gm` / `game.edit_all` — любой персонаж и NPC в игре;
владелец active-membership — только свой персонаж; иначе `AUTH_DENIED`.

## Порт

- `setResourceCurrent` `{ resourceCode, current }`: native `int` или
  `{base, size}`; кламп 0..limit; не `spendResources`.
- `putState` / `patchState` / `removeState` как сейчас; **каждый** `putState`
  выдаёт `id` строки.
- Sidecar `maim` на put/patch: только если `StateSpec.maim`; иначе invalid.
  На порте ключ опционален (удар/`endTurn` пишут одну силу). HTTP ручной
  правки при add/patch увечья срок требует: `permanent` или
  `healTotal` ≥ 1 и `healUnit` `days|decades|months|years`.

`character.applyActualPatch` отвергает `setResourceCurrent`. Прочие kinds
порта этим заходом не сужаются.

## Вне scope

- Яд в строке состояния.
- Кнопка `launch-injury`, шкала инициативы, `refillActionPoints`.
- Срок увечья с удара и `endTurn`. Боевое увечье дырявое: в лист уходит
  сила без срока; таблица `healSpec` на фронте не спека и не механика.
  Перенос таблицы и запись срока с удара — отдельная доработка.
- Заполнение `IGameApi.setCombatResource` в PHP `GameApi`.

## UI

Боевая карточка: попап задания current ресурса (не ±1). Состояния по `id`.
`applyRealtimeEvent` знает source `manual_sheet`.
