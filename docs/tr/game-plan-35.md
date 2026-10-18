# План Game 35 — один журнал команд

**Статус:** implementation plan, 2026-10-16.

Журнал идемпотентности — не лист и не процесс. Семь одинаковых таблиц
`game_*_command` свёрнуты в `game_command` с колонкой `source`. HTTP-сценарии
не сливаются.

## Схема

`game_command`: `game_id`, `session_id`, `source`, `idempotency_key`, `body`,
`result`. Unique `(game_id, source, idempotency_key)`. Источники: `strike`,
`wide_strike`, `turn`, `wound`, `battle`, `check`, `manual_sheet`.

Вне скоупа: `game_strike`, цели wide, `game_check`, `game_economy_operation`.

## Delivery

Контракт outbox не меняется. Wide по-прежнему пишет `source: strike`. Catch-up
догоняет `strike` и `wide_strike` как `strike`; `turn`, `wound`, `manual_sheet`
своими именами. `check` и `battle` в outbox не догоняются.

CLI-шаг `Roleplay/Game:fold-command-journals` уже исполнен: legacy-столы сняты,
PHP-карты журналов удалены. `game_manual_sheet_command` не создавалась.

## Тесты

Focused MySQL: catch-up wide как strike, check в outbox нет. Существующие
strike/wound/turn/check/battle/manualSheet mysql считают `game_command` по
`source`.
