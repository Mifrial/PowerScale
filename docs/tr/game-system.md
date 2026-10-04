# Система игр

**Статус:** текущий frontend/domain канон, 2026-08-30. Незавершённые backend-контракты явно помечены. Battleground — [`battleground-system.md`](battleground-system.md), не этот файл.

## Контекст и lifecycle

Игра живёт в пространстве правил и использует выбранную ревизию. Frontend Game открывает контекст игры вкладками внутри карточки/маршрута, а не набором независимых устаревших страниц.

Жизненный цикл игры, вступление, видимость, роли и выдача прав являются отдельными состояниями и permission-проверками. Точные backend transition guards и storage — `OPEN`.

Роли игры должны учитываться вместе с глобальными и object-level permissions. Наличие роли не отменяет проверку права на конкретную операцию.

## Membership, NPC и overlay

Игровой персонаж может состоять только в одной игре одновременно. Membership хранит ссылку на персонажа, `approvedCharacterVersion` как immutable baseline moderation и технические review/concurrency metadata. `actualCharacter` остаётся единственным persisted листом; `gameOverlay/gameState` хранит только session/battle/process state, а не второй полный `CharacterVersion`. Истории CharacterVersion нет.

NPC имеют актуальный `npc.version` и могут участвовать в игровых действиях. NPC не получает approved snapshot, draft или player moderation baseline; `npc.actual_version` используется для optimistic concurrency. Его authoritative state изменяется тем же Game backend path, что и player effects, с отличающейся только moderation/ownership policy.

При смене `rulesRevision` игры все несовместимые player characters проходят migration. Миграцию выполняет владелец персонажа; после успешной миграции membership снова требует moderation. До migration и approve запуск следующей сессии такого персонажа запрещён. Игра целиком не блокируется: ограничение применяется только к несовместимым персонажам.

Game overlay является частью общего игрового состояния. В нём остаются initiative, `battleId`, process/action state, offers, pending effects и transient markers. Полный player/NPC sheet, `states`, resources, money, inventory и equipment не являются его authoritative storage; они читаются из Character/NPC actual projection.

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

Абстрактное движение представлено action/process-контуром и `ISpatialResolver`, включая разрешение движения и валидацию целей атаки без координат сцены (`DEC-026`).

Пространственная сцена — [`battleground-system.md`](battleground-system.md) (`DEC-069`): `REQUIREMENT`, `NOT_IMPLEMENTED`. Токен на overlay листа не смешивать с токеном карты без явного инварианта.

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

Каждая backend-операция, меняющая money, inventory, item quantity или loot, проходит через typed `EconomyOperation` с idempotency key и expected versions. Во время сессии player-мутация меняет actual Character в момент authoritative effect; Game overlay хранит только связанный process/battle state. Для NPC authoritative state — `npc.version`. Состояние операции и затронутые authoritative records меняются атомарно в одной DB-транзакции. Общий immutable combat action log не является обязательным контрактом.

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

`ActionEffect` is a partial runtime contract for resource changes, states, damage and process transitions. Effects may be immediate or remain for a defined turn/session lifetime. State aggregation follows the rule (`sum`, `max`, `independent`); DOT consumes turns and, when it applies an authoritative effect, updates actual Character/NPC state. Only process/turn markers remain in Game state.

### Movement

Movement is resolved through `ISpatialResolver` and typed horizontal/vertical directions. A movement operation must validate direction, distance, current speed and action-point cost before mutating Game session state. When a current `GameScene` exists, path, collision and portal crossing follow [`battleground-system.md`](battleground-system.md); that domain is not implemented yet.

### Loot and stores

Loot changes state from prepared/available to distributed. An item has one recipient (`character`, `npc` or `nowhere`); money can be split by interested recipients with an explicit remainder. A normal permitted buy/sell/transfer does not require manual GM approval, but each mutation still checks permission, balance, quantity and optimistic version.

### Public Game API and session contract

`IGameApi` предоставляет публичные операции для контекста игры, membership, сессии, проверок, боя, loot и typed action/process flow. Точные TypeScript unions остаются в `Dto/` и `Interface/`; этот документ фиксирует только границы и инварианты.

Во время сессии combat читает authoritative Character/NPC projection из actual storage и накладывает только transient Game state. `approvedCharacterVersion` используется для moderation и следующего `canStartSession`, но не заменяет actual внутри уже начатой session. Полный лист не хранится в `GameCombatOverlay`.

Session transitions:

```text
start       → validate canStartSession against current Game.spaceId/spaceCode/rulesRevision
startBattle → create battleId and battle/process namespace
action      → validate participant, process, target, versions and effect
effect      → atomically update actual Character/NPC plus Game state
endBattle   → cancel unresolved battle processes/offers and clear battle markers
stopSession → cancel remaining session processes and clear transient Game state
```

Остановка сессии — один action `stopSession` (`IGameApi.stopGameSession`); `updateGame` сессию не запускает и не останавливает. Старт и stop статус не меняют. Stop не принимает `targetStatus` и не ставит `completed`.

Пока запущена текущая сессия, `Game.spaceId`, `Game.spaceCode` и `Game.rulesRevision`
неизменяемы. Это invariant самого Game aggregate, а не отдельный
`gameRevision` или runtime CAS token. R3-FE добавляет только opt-in
frontend/mock snapshot boundary; его internal lifecycle fixtures не меняют
`Game.status` и не заменяют `stopGameSession`.

R4-FE может добавить opt-in `IGameApi.submitCombatCommand` для узкого
single-target attack/defense vertical slice. Frontend отправляет только
decisions, `sessionId`/`battleId`/process identity и expected entity versions;
damage, resource spend и state outcome вычисляет authoritative mock/backend.
Decision commands меняют Game process/offer state, а Character/NPC actual
изменяется только в applied transition. Result и будущая SSE delivery остаются
разными boundaries. R4-FE расширяет mock command records, CAS и rollback
fixtures, но не реализует backend transaction, SSE, outbox, Chat delivery или
read projections; `GameCombatOverlay` и текущие combat controls остаются
compatibility path.

Одна текущая сессия может содержать несколько независимых battles. Продолжение текущего battle сохраняет его `battleId` и process state; новый battle получает новый identity. `endBattle` не завершает session и не запускает approve. Applied authoritative effects не откатываются при endBattle/stopSession. Combat resources, states, ActionEffect, movement, initiative, checks, chronicle and loot остаются отдельными capability-контрактами. Ошибка или stale version не приводит к частичной мутации. Модерация использует diff `approvedCharacterVersion` ↔ `actualCharacter`; `changes_pending` не блокирует уже активного participant, но блокирует следующую session. Старые A/L/O/P и three-way reconcile в этот контракт не входят.

## Backlog и release blockers

- `N → 1` attack;
- реализация battleground по [`battleground-system.md`](battleground-system.md);
- полная backend-модель GameTime/летописи;
- торговля, обмен, loot и модерация в реальном backend;
- интеграция runtime магии после выгрузки контента.

## Подробный текущий frontend-контракт

### Game routes и tabs

Frontend-контур включает `/games`, `/games/new`, `/games/:id` и `/games/:id/edit`. В карточке игры используются вкладки Overview, Members, Characters, Moderation, NPC, Discussion и live Game Chat. Старые отдельные URL `/members`, `/characters`, `/moderate`, `/invitations`, `/loot` и `/chronicle` являются логическими разделами карточки; фактическая маршрутизация должна сверяться с `Game/routes.ts`.

Создание игры выбирает пространство и ревизию, статус, visibility, join policy, лимиты ОС/ОЛ/ОР/денег и описания. Персонаж, созданный «через игру», получает правила и лимиты игры и создаёт membership со статусом `submitted`.

Статусы игры:

```text
draft → recruiting → in_process → paused → completed
```

`visibility` и `join_policy` независимы от lifecycle. `in_process` — фаза кампании, не признак живой сессии. `paused` — заморозка кампании, не пауза сессии. `completed` терминален и делает данные read-only. Старт и остановка сессии статус не меняют. Признак ответа `sessionRunning` истинен ровно когда есть текущая сессия; это не значение `status` и не колонка строки игры.

### NPC и листы

NPC — персонаж игры без владельца-игрока. Ведущий может добавить NPC inline; дальнейшие editor/runtime mutations выполняются участниками с соответствующим Game permission и применяются к `npc.version` без player moderation flow. Видимость задаётся scope (`all`, `gm`, selected players) и секциями листа. Имя видно при доступности NPC, а характеристики, ресурсы, способности и inventory могут быть скрыты.

NPC использует переиспользуемый `CharacterSheetEditor` без обязательной расы и лимитов; версия NPC применяется сразу. Перевод NPC на новую ревизию использует тот же migration engine и применяется к `npc.version` с `expectedNpcActualVersion`. NPC не проходит player approve и не имеет второй moderation version.

### Session, initiative и checks

Game Chat — общий чат live-сессии. Автор сообщения выбирается из персонажей, NPC или ведущего. GM запускает/останавливает сессию; authoritative combat effects записываются в actual в момент их применения и могут перевести player membership в `changes_pending`. Остановка сессии не выполняет character commit и сбрасывает transient session/battle state; ту же шкалу после stop продолжить нельзя — только новый бросок в новой сессии.

Initiative использует тот же RollEngine и может быть характеристикой с дефолтом, свободным броском или фиксированным значением. Результат нужен для порядка и не хранится как отдельный листовой показатель. Шкала поддерживает передачу хода, добавление участника и сохранение/продолжение данных.

Check имеет solo и pairwise flow. В pairwise flow offer ждёт ответов целей; броски строятся после согласия. Wide attack расширяет это до нескольких target proposals и per-target results.

### Game state и Character actual

Пока запущена текущая сессия, Game state содержит только session/battle/process data. Player/NPC projections читаются через соответствующие authoritative boundaries; Game не пишет Character storage напрямую. Любая multi-entity game action открывает outer transaction в Game и вызывает Character/NPC mutation ports на том же transaction-bound gateway.

Многошаговая атака может состоять из нескольких command requests: decision, offer/defense, process transition и authoritative resolution. Frontend отправляет решения, backend сам проверяет актуальные версии, права и допустимость перехода. Command response и SSE delivery являются разными границами; SSE доставляет authoritative updates, но не заменяет response и не является источником authority.

`GameStateSnapshot` не содержит полный roster или sheets. Participant keys и
runtime projections читаются отдельными capability boundaries; roster должен
использовать summaries, batch lookup и lazy/paged detail без N+1.

R6-FE добавляет mock/frontend realtime boundary для этих projections:
изменение доставляется как affected `entityKey`/version/invalidation, а не
как Chat message или полный roster. Cursor монотонен в пределах Game stream,
`eventId = <gameId>.<cursor>`, reconnect использует `lastCursor`, а stale
cursor получает bounded snapshot. Game Chat, Characters, Moderation и NPC UI
перечитывают только затронутые projections; full projection загружается
только для открытой карточки или активного combat subset. Это frontend/mock
контракт и не означает готовность production SSE, outbox, EventManager
listener или visibility enforcement.

R7-FE добавляет frontend/mock readiness для публичных session/battle
transitions, разделения admission и active participation, moderation CAS,
terminal cleanup и recovery/idempotency fixtures. `changes_pending` не
блокирует следующий battle уже начатой session; approve active membership
не меняет actual и не останавливает session. Новый authoritative path не
выполняет stop-time full-sheet commit. Legacy overlay mutators и их
compatibility commit сохраняются до R8 и не являются canonical Character
source; production Game transactions, durable process state, SSE, outbox и
EventManager delivery остаются backend scope.

Модерация требуется, если approved snapshot отсутствует или `getCharacterDiff(approvedCharacterVersion, actualCharacter).hasChanges` равно `true`. Return for rework сохраняет membership и переводит participant-owned unresolved processes в `cancelled`; applied effects не откатываются. Reject удаляет только submitted-заявку; active-персонажа reject нельзя. Новая сессия блокируется при semantic diff, `changes_pending`, `returned`, несовместимой revision или repair state.

### Loot

GM готовит loot из предметов ревизии или денег, игроки проявляют интерес. Предмет выдаётся одному персонажу, NPC или «вникуда». Деньги распределяются поровну между заинтересованными либо вручную по долям; остаток можно отправить «вникуда».

Выдача в character записывает деньги/inventory и синхронизирует latest; выдача NPC инициализирует минимальный лист и пишет в `npc.version`. «Вникуда» фиксирует результат без изменения листа.
