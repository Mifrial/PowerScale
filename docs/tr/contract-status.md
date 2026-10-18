# Статусы и владельцы контрактов

**Статус:** канонический документ классификации ТР, 2026-10-18.

## Оси статусов

Статусы не объединяются в один lifecycle:

- `sourceStatus`: `CODE_CONFIRMED`, `TEST_CONFIRMED`, `DECISION_CONFIRMED`, `LEGACY_ONLY`, `CONFLICT`;
- `implementationStatus`: `IMPLEMENTED`, `PARTIAL`, `MOCK_ONLY`, `BACKEND_OPEN`, `NOT_IMPLEMENTED`;
- `documentStatus`: `CURRENT`, `REQUIREMENT`, `OPEN`, `DEFERRED`, `BACKLOG`, `HISTORICAL`.

`sourceStatus` описывает основание утверждения, `implementationStatus` — состояние реализации, `documentStatus` — роль записи в ТР.

Пример: принятое решение о журнале экономики имеет `sourceStatus: DECISION_CONFIRMED`, `implementationStatus: BACKEND_OPEN`, `documentStatus: REQUIREMENT`.

## Canonical owners

Один контракт имеет одного владельца:

- `architecture.md` — модули, DAG, слои, ServiceLocator, инфраструктурные границы и общие frontend-правила;
- `smarttable.md` — серверный доступ к MySQL (Basic);
- `user.md` — серверная учётка и группы модуля User;
- `data-model.md` — legacy-backed backend schema requirements; при конфликте с `user.md` / `smarttable.md` побеждает канон модуля;
- `auth-system.md` — users, groups, sessions, permissions, security и user routes;
- `rule-system.md` — Rule DTO, RuleType, Rule Engine, Space, revisions, catalog и publication;
- `character-system.md` — Character, versions, creation, validation, inventory и membership;
- `game-system.md` — Game, sessions, combat, abstract movement, chronicle, economy и loot;
- `battleground-system.md` — SceneTemplate, GameScene, SceneSpace, Enclosure, SupportSurface, Token, Obstacle, Watercourse, Opening, occupancy, projection, sceneVersion, spatial combat context, SSE сцены;
- `chat-system.md` — Chat protocol, attachments, commands, sync и visibility;
- `ui-system.md` — routes/UI behavior, notifications и frontend acceptance criteria;
- `decisions.md` — индекс решений, без повторения доменных контрактов;
- `history.md` — только superseded/historical material;
- `migration-map.md` и `migration-claims.md` — только трассировка миграции, не канонические контракты;
- `../review/tr-reconciliation-2026-08.md` — классификация фрагментов legacy 33–3748;
- `migration-claims-status.md` — нормализованная матрица трёх осей статусов для claims;
- `TR.md` — индекс, glossary и правила чтения.

Если контракта нет в списке владельцев, его нельзя добавлять в произвольный документ без обновления этой карты.

## Карта доверия: стабильные модули Core

Раздел для агентов и разработчиков: что читать про шесть стабильных модулей
Core и что читать как контракт нельзя.

`README.md` внутри модуля — карта входа, а не владелец контракта. Он описывает
«как пользоваться» и ссылается на канон. При расхождении побеждает канон, затем
код и тесты (порядок из `TR.md`).

Колонка «Сверено» — дата, на которую карта входа проверена по коду: типы и их
пути, границы значений, маршруты, рёбра зависимостей. Канон-документы этим
разделом **не** проверялись сплошняком: для `smarttable.md` ключевые утверждения
сверены с кодом и совпали, для остальных зафиксирована только роль (канон /
вспомогательное / исторический протокол). Сплошная сверка канона — отдельная
задача, а не этот раздел.

Колонка «Не читать как контракт» не означает «мусор»: это реализованные или
отменённые протоколы работ. Их читают как историю решений, а не как требование.

| Модуль | Карта входа | Читать как канон | Вспомогательное | Не читать как контракт | Сверено |
|---|---|---|---|---|---|
| Core/Kernel | `www/mifrial/modules/Core/Kernel/README.md` | [`architecture.md`](architecture.md) — «Контракт фундамента сервера», «PHP: локатор, контейнер модуля, фабрика» (`DEC-070`) | [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md) — накат DDL и CLI setup | [`kernel-plan-02-action-input.md`](kernel-plan-02-action-input.md) — `сделано`, исторический протокол | 2026-10-18 |
| Core/SmartTable | `www/mifrial/modules/Core/SmartTable/README.md` | [`smarttable.md`](smarttable.md) (`DEC-078`) | [`smarttable-roadmap.md`](smarttable-roadmap.md) — нарезка | `smarttable-plan-01…20` — реализованы, исторический протокол | 2026-10-18 |
| Core/Event | `www/mifrial/modules/Core/Event/README.md` | [`architecture.md`](architecture.md) — «EventManager (sync MVP реализован, async DEFERRED)» | [`../specs/event-manager-backend-plan.md`](../specs/event-manager-backend-plan.md) — план, сверять с кодом | — | 2026-10-18 |
| Core/Cache | `www/mifrial/modules/Core/Cache/README.md` | [`cache-plan-01.md`](cache-plan-01.md) (`DEC-081`); политику TTL консьюмера держит [`smarttable.md`](smarttable.md) §«Кэш» | [`smarttable-plan-08-cache.md`](smarttable-plan-08-cache.md) — кэш SmartTable | — | 2026-10-18 |
| Core/Auth | `www/mifrial/modules/Core/Auth/README.md` | [`auth-system.md`](auth-system.md) — поведение, инварианты и права; backend-детали внутри помечены `OPEN`; канон учётки — [`user.md`](user.md) (модуль `Core/User`) | [`auth-plan-01-session.md`](auth-plan-01-session.md) — backend seed и сессия (ссылается сам канон) | `auth-plan-02-password-flow.md` … `auth-plan-04-guest.md` — `сделано`, исторический протокол | 2026-10-18 |
| Core/Logger | `www/mifrial/modules/Core/Logger/README.md` | отдельного канон-документа нет; контракт описан картой входа | `logger-plan-01.md` … `logger-plan-04-view.md` — `сделано`; [`logger-plan-05-vue-datetime.md`](logger-plan-05-vue-datetime.md) — активный `TODO` (даты в UI, HTTP не трогать) | — | 2026-10-18 |

Правило: новый документ про эти модули не добавляется, пока не обновлена эта
таблица. Отсутствие у модуля канон-документа (Core/Logger) — сигнал для шага 3,
а не разрешение писать контракт в произвольный файл.

## Evidence

Каждый claim в migration registry должен ссылаться на:

- файл и диапазон строк;
- тест или fixture, если они подтверждают поведение;
- commit, если важна дата или историческая граница;
- `DEC-ID`, если основание — решение.

Ссылка считается проверенной только если целевой файл и диапазон существуют.

## Термины

Ключевые термины должны использоваться единообразно:

- `revision` и `publishedAt`;
- `actualCharacter`, `approvedCharacterVersion`, `sessionCharacterVersion`;
- `gameOverlay`;
- `canStartSession`, `isActiveSessionParticipant`, `needsModeration`, `reviewState`;
- `keyword`;
- `contentStatus`;
- `contentNote`;
- `OPEN`, `REQUIREMENT`, `BACKEND`, `BACKLOG`, `DEFERRED`, `HISTORICAL`.

`revision` — numeric publication number scoped by `spaceId`; `publishedAt` — immutable timestamp of that publication. `code` — semantic stable reference used between domain objects; database `id`/UUID — internal storage identifier. `actualCharacter` — the single persisted current character state. `approvedCharacterVersion` — immutable membership baseline for moderation and the next session. `sessionCharacterVersion` is a superseded derived sheet projection; current-session reads use actual plus transient `gameOverlay/gameState`, not `approved + overlay`.

`canStartSession` проверяет active membership, approved/actual availability, `getCharacterDiff`, validation, game-rule revision and blocking repair/review states. `isActiveSessionParticipant` checks already-started participation and does not require `actualCharacter == approvedCharacterVersion`; `returned` blocks new commands for that participant. `needsModeration`/`reviewState` describe moderation state and do not replace either session predicate.

`contentStatus` describes editorial readiness (`broken` | `needs_work` | `ready`) and must not be confused with `runtime-support`, which describes whether the engine can execute the content. `contentNote` is a developer comment on the rule, not player-facing text. `validation` is a structured result (`valid` plus `problems[]`), not a lifecycle status. `visibility` controls delivery/access and is independent from lifecycle.

`draft` is reserved for local editor state or legacy storage labels. It is not the canonical in-game mutation layer. In-game mutations that apply Character/NPC effects update authoritative actual state; `gameOverlay/gameState` remains the canonical location for initiative, battle/process state, offers, pending effects and transient markers. `sessionCharacterVersion` is retained only as a historical/superseded term and must not be used as a new machine predicate.

R3-FE добавляет только frontend/mock readiness boundary: typed `GameSessionState`,
`GameBattleState` и `GameStateSnapshot` с отдельными `sessionId`/`battleId`.
Это не подтверждает backend persistence, Game transactions, SSE, EventManager,
outbox или production crash recovery. Rules context читается из
`Game.spaceId`/`spaceCode`/`rulesRevision`; отдельный runtime `gameRevision`
не является контрактом.

R4-FE является отдельной frontend/mock readiness boundary для
`IGameApi.submitCombatCommand`: его single-target command/effect DTO, mock CAS,
idempotency, process state и rollback fixtures не переводят Game combat,
Character/NPC runtime mutation, transactions, Chat delivery, SSE или read
projections в `IMPLEMENTED`. `GameCombatOverlay` остаётся
`LEGACY_ONLY`/compatibility path до последующих этапов.

R5-FE добавляет frontend/mock read boundary для Game runtime projections:
summary/full batch lookup по `entityKey`, on-demand actual projection,
moderation batch approved/actual diff, visibility-safe NPC summaries,
pagination/search и stale-read protection. Это не подтверждает backend
projection API, production visibility serialization, SSE/realtime delivery
или перевод legacy combat controls с overlay на actual.

R6-FE добавляет frontend/mock readiness для единого Character editor flow и
Game realtime consumers: typed patch с `commandId`/CAS и idempotent retry,
post-commit `CharacterChanged` seam, Game cursor/eventId
`<gameId>.<cursor>`, sync/snapshot fallback, targeted Character/NPC
projection refresh и сохранение dirty local draft. Это не подтверждает
production Game listener, EventManager after-commit/outbox, SSE broker,
server-side visibility/authorization или schema persistence. R8-FE/mock
replaces the legacy full-sheet overlay with actual Character/NPC runtime
storage; production backend migration and transport remain open.

R7-FE добавляет frontend/mock readiness для публичного session/battle
lifecycle, active-participant guards, approve/return CAS, terminal cleanup и
recovery fixtures. `changes_pending` не блокирует следующий battle уже
начатой session; terminal command records и mock restore покрывают
idempotent retry/timeout-after-commit semantics. Это не подтверждает
production Game transactions, durable process state, backend recovery,
authorization, SSE, outbox или EventManager delivery. Legacy full-sheet
overlay commit удалён из frontend/mock stop flow; actual mutations are
authoritative in the mock. This does not confirm the production backend.

Frontend delta evidence, 2026-09-30:

- `MockGameParticipantCandidateSource` использует bounded query/cursor/limit
  pages; full projections для инициативы запрашиваются отдельным batch только
  для выбранных `entityKeys`.
- `GameRealtimeApi` сохраняет real transport без SSE и сообщает недоступность
  будущего `game.sync`/subscription через typed `GAME_REALTIME_UNAVAILABLE`;
  mock cursor/snapshot path остаётся отдельным.
- Ручная dev-проверка инициативы подтверждена: кандидат-поиск показывает
  персонажей перед НПС с разделителем, выбранные сущности получают full
  projection одним batch-запросом, характеристика персонажа и free/fixed
  бросок НПС доступны.
- Эта evidence подтверждает только frontend/mock readiness и не меняет
  production backend статусы R2–R7 или production R8 migration boundary.

R8-FE/mock evidence, 2026-09-30:

- `GameCombatOverlay` содержит только transient game markers; `sheet`,
  `resources` и `states` удалены.
- Character editor, combat resource/state/equipment и loot mock writers
  изменяют actual Character/NPC через typed `CharacterPatch`/command
  envelope и CAS storage seams; distribution loot требует expected token для
  каждого реального получателя и выполняется атомарно; stop не делает
  full-sheet commit.
- runtime/full sheet reads идут через actual projection; legacy merge и
  full-sheet session resolver удалены. После granular mutation UI запрашивает
  affected runtime projections, а command result не используется как overlay.
- Проверены serial mock suites для Character editor, loot, membership/session
  stop и combat actual-source behavior. Backend/PHP/БД/migration/SSE остаются
  вне этой evidence boundary.

Game 34 (`docs/tr/game-plan-34.md`): ручная правка листа в сессии через
`game.manualSheet` пишет actual и чат. Character HTTP не принимает
`setResourceCurrent`. Это не E2E Game 33.
