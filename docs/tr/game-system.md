# Система игр

**Статус:** текущий frontend/domain канон, 2026-08-30. Незавершённые backend и battleground-контракты явно помечены.

## Контекст и lifecycle

Игра живёт в пространстве правил и использует выбранную ревизию. Frontend Game открывает контекст игры вкладками внутри карточки/маршрута, а не набором независимых устаревших страниц.

Жизненный цикл игры, вступление, видимость, роли и выдача прав являются отдельными состояниями и permission-проверками. Точные backend transition guards и storage — `OPEN`.

Роли игры должны учитываться вместе с глобальными и object-level permissions. Наличие роли не отменяет проверку права на конкретную операцию.

## Membership, NPC и overlay

Игровой персонаж может состоять только в одной игре одновременно. Membership хранит ссылку на персонажа, `approvedCharacterVersion` как полную immutable-копию принятого actual character и `gameOverlay` как сессионные изменения. `sessionCharacterVersion` вычисляется как `resolve(approvedCharacterVersion, gameOverlay)`. Истории CharacterVersion нет.

NPC имеют игровую версию и могут участвовать в игровых действиях. Полная модель хранения, истории и модерации NPC — `OPEN`; операции с NPC применяются сразу к `npc.version`, в отличие от player overlay.

При смене `rulesRevision` игры все несовместимые player characters проходят migration. Миграцию выполняет владелец персонажа; после успешной миграции membership снова требует moderation. До migration и approve запуск следующей сессии такого персонажа запрещён. Игра целиком не блокируется: ограничение применяется только к несовместимым персонажам.

Combat overlay является частью общего игрового состояния. `GameCombatOverlay.sheet` может содержать полный лист, поэтому combat fields не следует описывать как независимую persisted модель без отдельного решения.

## Session и летопись

Игровая сессия связывает активную игру, участников, действия, проверки и сообщения. Летопись использует структурный `GameTime` и смещение для сортировки событий.

Backend-схема летописи, формат сохранения `event_time`, `sort_order`, редактирование и права на события не полностью подтверждены — `OPEN`. Нельзя смешивать историческое строковое поле времени из старой схемы с текущим frontend value-контрактом.

## Проверки и бой

Рабочий контур включает:

- соло-проверку;
- pairwise-предложение проверки;
- `1 → N` wide attack;
- расчёт попадания, урона, увечья и истощения;
- периодические эффекты состояния (DOT);
- завершение действия/хода.

Одна широкая атака может проверяться против нескольких защит. Вариант `N → 1` — несколько одновременных бросков атаки против одной защиты — пока `BACKLOG` и не должен выдаваться за реализованный контракт.

`ActionEffect`, check payload, difficulty, success rating и текущие process/action DTO — рабочий частичный канон. Сложные расширения, reactions, interrupt, поддержание и исключения требуют отдельной реализации/проверки.

## Движение и battleground

Абстрактное движение представлено action/process-контуром и `ISpatialResolver`, включая разрешение движения и валидацию целей атаки.

Battleground — отдельный незавершённый контур. Координаты, сцены, токены, препятствия, порталы, видимость, realtime-позиции и тактическая карта не входят в уже реализованный контракт движения.

## Инвентарь

В игре инвентарь должен поддерживать:

- просмотр, изменение количества и свойств;
- экипирование;
- custom items;
- item modifiers и влияние на характеристики/бой;
- получение и выдачу loot;
- discard;
- передачу предметов между участниками;
- покупку и продажу через магазины;
- перенос при миграции ревизии.

Loot UI живёт в карточке игры (запас ГМ, интерес игроков, раздача предметов/денег). Chat показывает результаты через opaque attachments/plugin; граница Chat→Game не возвращается. Источник loot UI — Game frontend, не legacy Chat range 2228–2235. Wide attack / ActionEffect / EconomyOperation — `DEC-012`/`DEC-029`/`DEC-047`–`DEC-050` и код, не anatomy/chronicle ranges 258–276 / 270–300 / 1947–1980.

Loot может требовать модерации согласно правам и настройкам игры; текущий overlay не должен обходиться неаудируемым прямым изменением.

## Экономика

Деньги хранятся одним числом в минимальных единицах; номиналы — только форматирование. `moneyBudget` — исторический стартовый бюджет создания персонажа. `moneyLimit` не является постоянным текущим балансом.

Магазин — game-scoped набор позиций. Позиция хранит предмет, цену покупки, опциональную `sellPrice` и остаток. По умолчанию цена покупки берётся из `ItemSpec.cost_gm`; без `sellPrice` магазин не выкупает предмет.

Операции:

- buy;
- sell;
- discard;
- transfer_item;
- transfer_money;
- loot;
- обмен как согласованная комбинация передачи предметов/денег.

Ведущий настраивает права и режимы магазина. Обычная разрешённая операция не требует ручного approve.

Каждая backend-операция записывается в immutable append-only `EconomyOperation`. Во время сессии player-мутация направляется в `gameOverlay` и становится частью `sessionCharacterVersion`; вне сессии после проверки меняется `actualCharacter`. Для NPC authoritative state — `npc.version`. Состояние и журнал меняются атомарно в одной DB-транзакции.

Typed operation содержит игру, инициатора, источники, цели, предметы/деньги, `idempotencyKey` и ожидаемые версии. Backend проверяет права, баланс, количество, остаток и все версии; конфликт optimistic version check отклоняет операцию. Физические таблицы и endpoint — `OPEN`.

Frontend Economy API/UI для buy, sell, transfer, discard и общего `EconomyOperation` пока `NOT IMPLEMENTED`. Существующий `distributeLoot` — отдельный frontend loot flow; его наличие не означает готовность полного экономического API.

## Contract cards

### GameTime and chronicle

Current frontend `GameTime` uses fixed units: `1 year = 10 months`, `1 month = 3 decades`, `1 decade = 10 days`, `1 day = 30 hours`, `1 hour = 60 minutes`. Minutes are the smallest stored/displayed unit. Chronicle entries are sorted by normalized offset from the epoch, not by creation time or manual `sort_order`. The current UI allows the GM to create/update/delete entries; backend persistence remains `OPEN`.

### Combat flow

The combat pipeline resolves an action in this order:

```text
action input → check/attack roll → defense resolution → damage → injury/exhaustion/DOT → ActionEffect/state update
```

`1 → 1` is the ordinary attack. `1 → N` resolves one attacker against each target and returns a `targetResults[]` entry per defender. `N → 1` remains backlog. `N → N` is not a current contract.

Wide attack requires at least one target and a defender key for every target. Each target result carries its own defense, damage and state outcome; aggregation must not collapse target-specific failures.

### ActionEffect and states

`ActionEffect` is a partial runtime contract for resource changes, states, damage and process transitions. Effects may be immediate or remain for a defined turn/session lifetime. State aggregation follows the rule (`sum`, `max`, `independent`); DOT consumes turns and updates the effective overlay, not the global active snapshot.

### Movement

Movement is resolved through `ISpatialResolver` and typed horizontal/vertical directions. A movement operation must validate direction, distance, current speed and action-point cost before mutating the session overlay. Tactical coordinates, obstacles, portals and realtime token positions belong to the separate battleground backlog.

### Loot and stores

Loot changes state from prepared/available to distributed. An item has one recipient (`character`, `npc` or `nowhere`); money can be split by interested recipients with an explicit remainder. A normal permitted buy/sell/transfer does not require manual GM approval, but each mutation still checks permission, balance, quantity and optimistic version.

### Public Game API and session contract

`IGameApi` предоставляет публичные операции для контекста игры, membership, сессии, проверок, боя, loot и typed action/process flow. Точные TypeScript unions остаются в `Dto/` и `Interface/`; этот документ фиксирует только границы и инварианты.

Во время сессии combat читает `sessionCharacterVersion = resolve(approvedCharacterVersion, gameOverlay)`. `GameCombatOverlay.sheet` может быть полной рабочей копией листа для редактора, но при записи применяется allowlist игровых полей: владелец, `characterId`, `spaceId`, `rulesRevision`, права и `visibility` неизменяемы.

Session transitions:

```text
start  → resolve approvedCharacterVersion + gameOverlay
action → validate permission, target, version and effect
stop   → atomic resolve → validate → update actualCharacter → clear gameOverlay
```

Остановка сессии — один action `stopSession` (`IGameApi.stopGameSession`); `updateGame` не снимает статус `playing`.

Combat resources, states, ActionEffect, movement, initiative, checks, chronicle and loot остаются отдельными capability-контрактами. Ошибка или stale version не приводит к частичной мутации. Модерация запускается только после stop/session commit и использует diff `approvedCharacterVersion` ↔ `actualCharacter`; старые A/L/O/P и three-way reconcile в этот контракт не входят.

## Backlog и release blockers

- `N → 1` attack;
- полноценный battleground;
- полная backend-модель GameTime/летописи;
- торговля, обмен, loot и модерация в реальном backend;
- интеграция runtime магии после выгрузки контента.

## Подробный текущий frontend-контракт

### Game routes и tabs

Frontend-контур включает `/games`, `/games/new`, `/games/:id` и `/games/:id/edit`. В карточке игры используются вкладки Overview, Members, Characters, Moderation, NPC, Discussion и live Game Chat. Старые отдельные URL `/members`, `/characters`, `/moderate`, `/invitations`, `/loot` и `/chronicle` являются логическими разделами карточки; фактическая маршрутизация должна сверяться с `Game/routes.ts`.

Создание игры выбирает пространство и ревизию, статус, visibility, join policy, лимиты ОС/ОЛ/ОР/денег и описания. Персонаж, созданный «через игру», получает правила и лимиты игры и создаёт membership со статусом `submitted`.

Статусы игры:

```text
draft → recruiting → in_process → paused → playing → completed
```

`visibility` и `join_policy` независимы от lifecycle. `completed` терминален и делает данные read-only. Остановка live-сессии возвращает игру в `in_process`, а не ставит `completed`.

### NPC и листы

NPC — персонаж игры без владельца-игрока. Ведущий может добавить NPC inline; игрок создаёт предложение на модерацию. Видимость задаётся scope (`all`, `gm`, selected players) и секциями листа. Имя видно при доступности NPC, а характеристики, ресурсы, способности и inventory могут быть скрыты.

NPC использует переиспользуемый `CharacterSheetEditor` без обязательной расы и лимитов; версия NPC применяется сразу. Перевод NPC на новую ревизию использует тот же migration engine и применяется к `npc.version`.

### Session, initiative и checks

Game Chat — общий чат live-сессии. Автор сообщения выбирается из персонажей, NPC или ведущего. GM запускает/останавливает сессию; боевые изменения при остановке коммитятся в actual character одной транзакцией и могут отправить membership на модерацию. Остановка сессии сбрасывает шкалу инициативы; ту же шкалу после stop продолжить нельзя — только новый бросок в новой сессии.

Initiative использует тот же RollEngine и может быть характеристикой с дефолтом, свободным броском или фиксированным значением. Результат нужен для порядка и не хранится как отдельный листовой показатель. Шкала поддерживает передачу хода, добавление участника и сохранение/продолжение данных.

Check имеет solo и pairwise flow. В pairwise flow offer ждёт ответов целей; броски строятся после согласия. Wide attack расширяет это до нескольких target proposals и per-target results.

### Game overlay и reconciliation

Во время `playing` approved membership читает эффективный лист `approvedCharacterVersion + gameOverlay`. Overlay может менять только игровые поля и не может менять владельца, `characterId`, `spaceId`, `rulesRevision`, права или `visibility`. Actual character и approved snapshot неизменяемы до завершения сессии.

Session commit атомарно разрешает все overlays, валидирует результаты, обновляет actual characters, очищает overlays и отмечает изменённые memberships для модерации. Ошибка одного участника откатывает весь commit; optimistic guard отклоняет устаревшее состояние.

Модерация требуется, если approved snapshot отсутствует или отличается от actual character. Return for rework сохраняет membership и отправляет сообщение в обсуждение. Reject удаляет только submitted-заявку; active-персонажа reject нельзя. Новая сессия блокируется при расхождении approved/actual или `needs_fix`.

### Loot

GM готовит loot из предметов ревизии или денег, игроки проявляют интерес. Предмет выдаётся одному персонажу, NPC или «вникуда». Деньги распределяются поровну между заинтересованными либо вручную по долям; остаток можно отправить «вникуда».

Выдача в character записывает деньги/inventory и синхронизирует latest; выдача NPC инициализирует минимальный лист и пишет в `npc.version`. «Вникуда» фиксирует результат без изменения листа.
