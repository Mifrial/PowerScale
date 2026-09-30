# План перехода на `actualCharacter` как источник листа во время сессии

**Статус:** план для архитектурного обсуждения  
**Дата:** 2026-09-28  
**Тип:** implementation plan; код не изменён  

## 1. Цель

Перенести persisted-состояние листа игрового персонажа из полного
`gameOverlay.sheet` в `actualCharacter`, сохранив `gameOverlay` для состояния
самой игровой сессии.

Целевая модель:

```text
actualCharacter
  = единственный persisted CharacterVersion персонажа

approvedCharacterVersion
  = immutable baseline для moderation и допуска следующей сессии

gameOverlay / gameState
  = initiative, process/action state, offers и другие transient game-данные
```

Во время активной сессии игровые действия могут атомарно изменять
`actualCharacter`. После первого изменения membership получает
`reviewState = changes_pending`, но текущая сессия продолжается. До approve
следующая сессия запрещена.

Здесь важно различать игровую сессию и отдельный бой. Одна сессия в статусе
`playing` может содержать несколько независимых боёв; окончание боя не
закрывает сессию, не запускает approve и не является основанием принимать
персонажа из `changes_pending`. Actual должен продолжать использоваться во
всех последующих боях той же сессии. Только остановка всей игровой сессии
(`stopGameSession`) завершает этот игровой период.

## 2. Основание и текущая модель

Текущий frontend/mock-контракт использует:

```text
sessionCharacterVersion =
  resolve(approvedCharacterVersion, gameOverlay)
```

Evidence:

- `docs/tr/character-system.md:30–110`;
- `docs/tr/game-system.md:80–90, 125–140, 178–185`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Service/SessionCharacterService.ts:8–48`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Sheet/inGameSheetSource.ts:1–13`.

`GameCombatOverlay` сейчас смешивает два разных слоя:

1. состояние игры/боя;
2. полную рабочую копию `CharacterVersion`.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Game/Dto/GameCombatOverlay.ts:8–28`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Mock/mockGameCombatOverlays.ts:140–166`.

В `CharacterVersion` уже есть структурный блок:

```text
states: CharacterStateValue[]
```

наравне с `abilities` и `inventory`.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Character/Dto/CharacterVersion.ts:16–27`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Utils/membershipDiff.ts:360–370`.

Раны, увечья, отравления, истощение и длительные состояния относятся к
`actualCharacter.states`, должны переживать reload/stop и участвовать в
moderation diff.

Этот файл является proposal до синхронизации канона. Перед любым build
обязателен отдельный documentation gate: новый контракт должен быть перенесён
в canonical `docs/tr`, а старые утверждения об immutable actual во время
session, stop-time overlay commit и approve-after-stop должны быть явно
superseded, а не оставлены рядом с этим планом.

### 2.1. Каноническое сравнение CharacterVersion

Должна быть одна каноническая функция:

```text
getCharacterDiff(approved, actual) → CharacterDiff
```

Она используется одновременно для:

- moderation tab;
- `needsModeration`;
- `reviewState`;
- проверки допуска следующей session;
- отображения changed sections.

`isCharacterChanged(approved, actual)` допустима только как тонкий helper,
эквивалентный проверке `getCharacterDiff(...).hasChanges`; отдельного
алгоритма сравнения у неё быть не должно.

`CharacterDiff` должен сравнивать все semantic поля `CharacterVersion`,
включая полный normalized state instance: `stateRuleCode`, numeric и
dimensional values, poison/DOT/source bindings, maim fields и все persistent
поля wound (`bandage`, `clotting`, `internal`, `aided`). Только operational
`wound.heldBy` исключается из этого normalized state.
Identity/revision guards (`spaceCode`, `rulesRevision`) могут иметь отдельную
машинную секцию и не обязаны попадать в обычный визуальный diff.
Нельзя использовать одновременно независимый deep comparator,
fingerprint и `membershipDiff` с расходящейся семантикой.

Текущий `membershipDiff` является presentation-oriented реализацией и не
может остаться машинным источником истины без переработки:

- он сравнивает rendered values;
- states идентифицируются по `stateRuleCode + array index`;
- optional semantic fields `CharacterVersion` должны быть включены явно;
- повторяющиеся states должны сохраняться как multiset экземпляров: порядок
  массива не является semantic, но все normalized instances сохраняются; для
  detailed UI pairing используется occurrence index после deterministic
  sorting по canonical state data.

При реализации `getCharacterDiff` его `hasChanges` должен основываться на
нормализованном semantic data; operational battle markers, включая
`wound.heldBy` при compatibility fallback, в это data не входят. Moderation
UI может использовать тот же result для labels/details. `membershipDiff`
должен быть удалён либо стать
presentation adapter-ом над `CharacterDiff`, но не вторым comparator.

До появления `CharacterDiff` текущий `membershipDiff` допускается только как
временный UI compatibility adapter. Его нельзя использовать как новый
authoritative machine predicate для `canStartSession`, `needsModeration` или
`reviewState`; существующее использование в mock-коде должно быть заменено в
Этапе 1.

В плане не вводится ещё одна пользовательская «версия персонажа». Нужно
различать только:

- `CharacterVersion` — содержимое листа;
- editor draft — несохранённое локальное состояние редактора;
- backend `character.actual_version` — технический optimistic-lock counter
  существующей actual-строки, не отдельный лист и не moderation snapshot.

В frontend/API этот counter может передаваться как `actualVersion` или
непрозрачный `concurrencyToken` только для write/approve/conflict handling.
Он не должен отображаться пользователю и не является третьей моделью
персонажа.

## 3. Границы ответственности

### 3.1. Character-owned persisted state

В `actualCharacter` должны храниться:

- characteristics;
- resources и их persisted current values;
- abilities;
- inventory и equipment;
- `states`:
  - poison;
  - wounds;
  - injuries;
  - exhaustion;
  - unconsciousness;
  - длительные эффекты;
- senses;
- custom rules;
- остальные поля `CharacterVersion`.

Любое изменение этих данных GM модерирует через diff
`approvedCharacterVersion → actualCharacter`.

Наличие в `states` runtime-поля не делает само состояние transient. Например,
трёхлетнее увечье, отравление или wound остаются в actual после `endBattle`,
 stop/reload и участвуют в moderation diff. Текущий `wound.heldBy` — особый
 операционный атрибут: он нужен для расчёта удерживания и кровотечения, но не
 является semantic Character state. В первом release его следует хранить в
 battle state. Временное хранение внутри actual допустимо только как
 compatibility fallback; в этом случае `getCharacterDiff` обязан исключать
 `heldBy` из normalized semantic data, а `endBattle` очищает только это поле
 без удаления или отката самой раны.

### 3.2. Game-owned transient state

В `gameOverlay`/`gameState` остаются:

- initiative и turn order;
- battle lifecycle/identity и battle-scoped initiative state;
- pending/committed action state;
- process sessions для конкретных боевых/игровых процессов;
- unresolved check/attack offers;
- session-only markers;
- `concentrationUsedInCycle`;
- `woundBandagedOnce`, если это именно ограничитель текущего цикла;
- временные данные восстановления UI и action processing.

Transient state не должен быть вторым источником `CharacterVersion`.

Game state должен быть расширяемым по режимам игры. В первом release
нужно различать как минимум такие lifecycle:

```text
game session (`playing`)
  ├── battle 1
  │     ├── initiative
  │     └── attack/check processes
  ├── battle 2
  │     ├── initiative
  │     └── attack/check processes
  └── ...
```

Окончание battle закрывает его unresolved offers и process sessions согласно
явному terminal policy, очищает initiative и battle-only markers
(`woundBandagedOnce`, а при compatibility fallback — `wound.heldBy`).
Окончание game session очищает оставшийся session state. Сам Character state
не удаляется и не откатывается, и ни одно из этих действий не меняет
`approvedCharacterVersion`.

В первом release terminal policy конкретна: незавершённые offers/processes
переходят в `cancelled` с причиной `battle_ended`, pending effects этого
battle удаляются, а уже применённые authoritative effects не откатываются.
`stopGameSession` аналогично переводит оставшиеся session processes в
`cancelled` с причиной `session_stopped`; retry закрытой команды получает
conflict и не применяет effect повторно.

Если начинается новый battle, его process/offer/initiative state создаётся
заново и состояние предыдущего battle ему не требуется. Если пользователь
выбирает продолжение текущего battle, это не `endBattle`: тот же battle
identity и его незавершённые process state остаются authoritative и
перечитываются из Game state.

В текущем frontend отдельная persisted battle сущность ещё не выделена:
`GameInitiative` имеет `gameId`, а `mockGameInitiative.endInitiative()` сейчас
сбрасывает шкалу при остановке всей сессии. Это подтверждает необходимость
будущего battle boundary, но не означает, что весь `playing` нужно считать
одним боем. Exploration и последующие режимы являются explicit non-goal
этого плана; они добавят свои typed namespaces отдельным решением, не
помещая их в CharacterVersion.

Для каждого namespace нужно зафиксировать lifecycle:

```text
owner
persistent or transient
survives reload/crash?
survives stop?
terminal cleanup
```

Это относится и к initiative, process sessions, pending effects, committed
actions, active spells, movement state и check offers.

Это распространяется на все текущие battle-scoped операции `IGameApi`:
initiative, combat overlays, pending effects, process sessions, committed
action sessions, active spells, current speed, check offers и combat quick
rolls. Их новые contracts должны принимать `battleId` либо однозначный
current-battle token; одного `gameId` недостаточно.

### 3.3. NPC

NPC использует тот же принцип хранения листа и `states` в `npc.version`.
Различие с player character относится к ownership и lifecycle:

- player character имеет approved snapshot и moderation;
- NPC не проходит тот же player membership moderation flow и не получает
  approved snapshot;
- persisted sheet NPC всё равно является единым authoritative state.

Текущий mock уже мутирует NPC напрямую:

- `mockGameCombatOverlays.ts:200–222`;
- `mockGameCombatOverlays.ts:270–319`;
- `mockGameCombatOverlays.ts:385–453`.

NPC не откладывается на отдельную второстепенную модель: authoritative NPC
state и его combat mutations входят в тот же Game backend scope, что и
player combat effects. Отличается только player moderation lifecycle.
У NPC есть только актуальный `npc.version` и технический
`npc.actual_version` для optimistic concurrency. Approved snapshot, draft и
moderation baseline у NPC отсутствуют. `npc.actual_version` не является
второй `CharacterVersion`. NPC read projection возвращает этот token, а
любая editor/runtime/migration mutation принимает
`expectedNpcActualVersion`; полная замена `npc.version` без CAS не допускается.

## 4. Инварианты целевой модели

1. Один player character состоит не более чем в одной игре.
2. `approvedCharacterVersion` не изменяется игровой mutation.
3. `actualCharacter` — единственный persisted лист персонажа.
4. `canStartSession` проверяет:
   - active membership;
   - наличие approved и actual;
   - отсутствие изменений по `getCharacterDiff(approved, actual)`;
   - совместимость с game revision;
   - validation и отсутствие блокирующего repair state;
   - отсутствие `returned` decision.
5. `isActiveSessionParticipant` проверяет активное участие в уже начатой
   сессии, не проверяет `actual == approved`, но не допускает membership,
   переведённое в `returned`.
6. Первая успешная игровая mutation с semantic diff actual относительно
   approved переводит membership в `changes_pending`.
7. `changes_pending` не блокирует текущую сессию.
8. `changes_pending` блокирует следующую сессию до approve.
9. Разрешённое Character edit может изменить actual и во время active
   session; оно проходит обычную Character validation/CAS и вызывает
   CharacterChanged notification only after successful commit.
10. Game action и Character edit имеют разные command semantics, но могут
    использовать общий Character mutation service. Authentication,
    permissions и role policy проверяются общим security/authorization
    boundary; они не становятся частью storage-модели Character или Game
    command types.
11. Любая actual mutation использует optimistic version.
12. Повтор mutating command с тем же idempotency key не применяет mutation
    повторно.
13. Game revision не меняется для active session.
14. Migration и leave не обходят active session guard; approve разрешён во
    время active session при атомарном CAS actual и membership revision.
15. Завершение battle не закрывает game session и не требует approve.
16. Stop закрывает всю game session и очищает session/battle transient state,
    но не коммитит полный Character sheet.
17. Владелец transaction определяется command aggregate:
    Character-owned edit открывает transaction в Character, а Game-owned
    action, затрагивающий несколько Character/NPC/Game entities, открывает
    outer transaction в Game и вызывает Character-owned mutation ports внутри
    неё. Эти порты используют тот же transaction-bound gateway/connection;
    независимый gateway с отдельным connection не считается вложенной
    transaction. Game не пишет Character storage напрямую.
18. Каждый backend transition атомарен в пределах своей операции; если
    transition меняет несколько authoritative entities, они изменяются
    одной транзакцией.
19. Frontend отправляет решения пользователя, но не является источником
    истины для результата боя или состояния противника.
20. Backend возвращает command result и публикует authoritative updates;
    frontend принимает последующие данные через response/SSE.

Пункты 6–8 означают semantic state, а не безусловную реакцию на любой write:
после mutation/retry membership state пересчитывается через
`getCharacterDiff(approved, actual)`. No-op mutation и повтор idempotent
command не должны создавать `changes_pending`. `returned` является отдельным
membership decision: вернуть можно только персонажа, который уже находится
в moderation flow; returned membership не становится active participant-ом
новой session. `returnForRework` разрешён и во время active session; после
него персонаж не принимает новые session/battle commands, удаляется из
будущих turn transitions, а его unresolved offers/processes переходят в
`cancelled` с причиной `character_returned`. Уже применённые actual effects
не откатываются.
Обычное `changes_pending` само по себе active participant-ом не управляет.

Пункт 13 является жёстким backend-инвариантом: любая попытка изменить
`spaceCode` или `rulesRevision` игры при `status = playing` отклоняется, даже
если новый status также равен `playing`. Текущий mock
`mockGames.updateGame()` это пока позволяет; это текущий gap, который должен
быть закрыт в Game update/start contract и отрицательным тестом.

## 5. План изменений по слоям

### Этап 0. Зафиксировать решения

Архитектурные решения уже зафиксированы в этом плане:

- полный список Character-owned и Game-owned полей;
- recovery после crash;
- формат action idempotency;
- правила multi-entity combat transaction;
- поведение при изменении game revision.

Обязательный documentation gate до написания production/mock mutation code:

- перенести эти решения в canonical `docs/tr` и явно supersede старые
  утверждения overlay/stop-commit;
- обновить `docs/tr/contract-status.md`;
- обновить `docs/tr/decisions.md`, включая supersession старых решений
  overlay/stop-commit;
- обновить `docs/tr/character-system.md`;
- обновить `docs/tr/game-system.md`;
- проверить `docs/tr/character-roadmap.md` и
  `docs/tr/rule-content-plan-06-wounds.md`;
- при затрагивании экономики синхронизировать `DEC-047`–`DEC-050` и
  соответствующий раздел `game-system.md`;
- отдельно пометить EventManager MVP и Character/Game outbox integration как
  разные scope, чтобы `DEC-083` не противоречил этому плану.

До прохождения этого gate план не считается разрешением на build: active
implementation должна следовать одному canonical contract.

### Этап 1. Разделить predicates

Текущий `isEligibleForSession` одновременно используется как проверка
допуска и как фактическая доступность персонажа.

Нужно разделить:

- `canStartSession`;
- `isActiveSessionParticipant`;
- `needsModeration`;
- `reviewState`.

`actual == approved` должен применяться только при start и следующем start,
но не перед каждым action.

Затронуты:

- `Game/Service/SessionCharacterService.ts`;
- `Game/Service/GameMembershipReviewService.ts`;
- `Game/Utils/membershipDiff.ts` как presentation adapter/compatibility
  boundary до появления `CharacterDiff`;
- `Game/Mock/mockGameMemberships.ts`;
- `Game/Service/GameStatusTransitionsService.ts`;
- `Game/Service/GameApi.ts` и `Game/Interface/IGameApi.ts` start-session
  contract;
- `Game/Component/Detail/GameChatTab.vue`;
- `Game/Component/Detail/ModerateTab.vue`.

### Этап 2. Разделить DTO и storage semantics

Из `GameCombatOverlay` убрать full-sheet Character semantics для player и NPC:

- `sheet`;
- `states`;
- `resources`;
- полную рабочую копию inventory.

Оставить только transient session/game fields. NPC actual также не должен
оставаться в `GameCombatOverlay`: его текущий лист находится в
authoritative `npc.version`, а технический concurrency token принадлежит
Game/NPC storage boundary.

Кандидаты:

- `Game/Dto/GameCombatOverlay.ts`;
- `Game/Dto/GameCharacterMembership.ts`;
- `Game/Interface/IGameApi.ts`;
- `Game/Service/GameApi.ts`;
- `Game/Mock/mockGameApi.ts`.

Финальное имя DTO следует определить после разделения:
`GameSessionState`, `GameCombatState` или сохранение имени
`GameCombatOverlay` для узкого слоя.

### Минимальный battle boundary

Полноценный отдельный Battle-модуль в первом release не нужен. Нужен
persisted battle namespace/identity:

```text
GameSessionState
├── gameId
├── activeBattleId: string | null
└── session status/revision guard

GameBattleState
├── gameId
├── battleId
├── status: active | ended
├── initiative
├── process sessions
├── offers/pending effects
└── battle markers
```

Новая проверка инициативы может атомарно создать новый `battleId` и открыть
battle. Отдельная пользовательская кнопка `startBattle` не обязательна, если
она не нужна UI. `endBattle` закрывает текущий identity. Продолжение боя не
создаёт новый identity и не очищает process state.

Все battle-scoped commands и reads должны нести `battleId` или однозначный
current-battle token. `GameInitiative.gameId` и методы вида
`getCombatOverlays(gameId)` недостаточны для различения двух боёв после
reload. Завершённый battle принимает только reads/history, а mutating command
получает closed-battle conflict.

### Authoritative combat response

Текущие методы, возвращающие `GameCombatOverlay` с `states/resources/sheet`,
нельзя сохранять как response contract после перехода. Рекомендуемая граница:

```text
Game command response
├── commandId/status
├── battleId/process transition
├── authoritative effect result
├── changed entity keys + actual/concurrency tokens
└── projection or projection cursor for the requesting client

SSE event
├── eventId
├── gameId/battleId
├── entityKey
├── changed sections
├── visibility-filtered projection/delta
└── battle-state delta/invalidation
```

Полный sheet не рассылается всем участникам. Игрок получает доступный ему
projection, а authoritative backend resolution работает по entity IDs.
Командный response и SSE delivery остаются разными контрактами.

`CombatCardModel` должен перейти от
`approved/effectiveVersion + GameCombatOverlay` к
`authoritative entity projection + transient GameBattleState`. Методы
`ActionExecutionService`, `EndOfTurnDotsService`, `BloodLossService`,
`InjuryCheckService`, `WoundActionService` и spell/action consumers должны
возвращать/обрабатывать этот command result, а не `GameCombatOverlay`.

### Этап 3. Перенести Character mutation boundary

Character editor остаётся обычным Character mutation flow. Он не обязан
переходить на отдельный Game API только потому, что персонаж находится в
активной игре.

Целевой flow:

```text
Character editor / Character command
  → common security/authorization check
  → Character validation
  → optimistic actual version check
  → actual mutation
  → commit
  → outbox row in the same transaction
  → post-commit CharacterChanged fast-path notification
```

Этот flow нельзя реализовать поверх текущего `ICharacters::replacePayload`
как есть: текущий backend принимает готовые `choices` и `sheet`, но не
предоставляет server-side build/validation или runtime mutation port.
Runtime actual mutation является prerequisite Character backend, а не только
изменением Game DTO.

Если существует active session, Game подписывается на
`CharacterChanged` и:

```text
CharacterChanged
  → найти active memberships
  → обновить session/read projections
  → отправить authoritative SSE update
  → при принятом UX-решении отправить system message в Game Chat
```

Затронуты:

- `Character/Mock/mockCharacterUpdate.ts`;
- `Character/Service/CharacterApi.ts`;
- `Character/Page/CharacterEditPage.vue`.

Character не должен импортировать Game. Event payload должен быть общим
domain fact, например `characterId`, `actualVersion`, actor/source и
changed sections. Game сам проверяет active session, visibility и policy
доставки.

Общий EventManager описан отдельным планом
`docs/specs/event-manager-backend-plan.md:45–75, 176–185, 409–447`.
Он является generic in-process delivery boundary и не должен сам искать
участников, решать permissions, писать SSE или становиться transaction
manager. Character публикует notification event после успешной mutation,
Game подписывается и выполняет orchestration.

EventManager fast path вызывается только на согласованной integration boundary
после успешного commit. Для Character/Game integration outbox row создаётся
в той же transaction, что и actual mutation; outbox delivery является
отдельным boundary и не добавляется в `Core/Event`. EventManager сам не
получает `afterCommit` semantics.

`CharacterChanged` по умолчанию является notification для актуализации
projection/SSE, а не командой «написать в Chat». Outbox также не должен
превращаться в поток Chat-сообщений. Chat message создаётся только отдельной
явной политикой:

- combat result/message — существующим combat-chat flow;
- значимое GM direct edit — отдельным решением о системном уведомлении,
  потенциально сгруппированным по одной операции;
- обычная Character mutation или каждый SSE retry — без автоматического
  Chat spam.

Outbox для обязательной Character/Game integration доставляет
`CharacterChanged`/projection notification. Он не является пользовательским
журналом и не обязан хранить
каждое поле или каждую повторную доставку как Chat entry.

### Этап 3a. Approve во время active session

Approve разрешён во время active session:

```text
atomic CAS actual + membership revision
→ approvedCharacterVersion = snapshot(actual)
→ reviewState = clean
```

Approve не изменяет actual и не прерывает текущую session. Если actual или
membership revision устарели, approve получает conflict и не меняет ни одну
сторону. Следующая actual mutation снова создаёт diff и возвращает
`changes_pending`.

Текущий запрет approve при live overlay в
`Game/Mock/mockGameMemberships.ts:274–280` относится к старой модели и
должен быть заменён на атомарный CAS actual + membership revision и проверку
текущей membership/session политики.

### Этап 4. Перенести combat mutations в actual

В actual должны идти:

- `states`;
- damage;
- wounds/injuries;
- poison;
- exhaustion;
- resource current values;
- money;
- inventory/equipment;
- loot and item quantity changes;
- healing и прочие persisted effects.

Затронуты:

- `Game/Mock/mockGameCombatOverlays.ts`;
- `Game/Service/ActionExecutionService.ts`;
- `Game/Service/EndOfTurnDotsService.ts`;
- `Game/Service/InjuryCheckService.ts`;
- `Game/Service/BloodLossService.ts`;
- `Game/Service/UnstableApplyService.ts`;
- `Game/Service/WoundActionService.ts`;
- `Game/Mock/mockGameLoot.ts`;
- `Game/Utils/combatStateWrite.ts`;
- соответствующие API methods в `IGameApi`/`GameApi`.

Loot и item quantity не являются отдельным хранилищем рядом с Character:
результат выдачи меняет обычные поля `actualCharacter.money` и
`actualCharacter.inventory`. Для этих изменений Game использует канонический
typed `EconomyOperation` с idempotency key, expected versions и атомарной
записью операции вместе с изменением листа. `EconomyOperation` — не другая
модель листа и не общий combat action log. Любой release, включающий
money/inventory/loot mutation, обязан включать этот EconomyOperation contract;
иначе соответствующие mutation исключаются из release scope. Их нельзя
оставлять скрытым `overlay.sheet` mutation.
В частности, `handoutLoot`/`distributeLoot` и будущие buy/sell/transfer
commands должны принимать command/idempotency key и expected versions; legacy
метод, напрямую меняющий `versions` или `npc.version`, не является
совместимым implementation path.

Каждая пользовательская команда должна проходить через backend или его mock
adapter. Frontend может оркестрировать UI-flow из нескольких команд, но не
вычисляет authoritative combat outcome.

Текущие `ActionExecutionService`, `EndOfTurnDotsService`,
`ActionEffectService`, injury/blood-loss/exhaustion services могут остаться
frontend calculation/presentation helpers только до появления
authoritative Game command path. После этого они не должны самостоятельно
последовательно мутировать несколько Game/Character records и объявлять
полученный результат истинным; их mutation calls должны быть заменены
command requests и обработкой authoritative response/SSE.

Пример протокола атаки:

```text
1. frontend отправляет attack decision/form
2. backend проверяет его и создаёт/обновляет attack process внутри текущего
   battle
3. frontend показывает следующий ожидаемый шаг
4. defender отправляет defense decision
5. backend проверяет defense относительно актуального server state
6. backend разрешает результат и атомарно применяет все affected effects
7. backend возвращает command result и публикует authoritative events/state
8. frontend обновляет UI по response и SSE
```

Не каждый шаг attack flow изменяет `actualCharacter`. Решение игрока может
только создать или продвинуть process/offer. `actualCharacter` изменяется
тогда, когда backend применяет authoritative effect: damage, wound, poison,
resource spend, inventory change и т.п.

Если один transition меняет несколько characters/NPCs и game state, эти
изменения выполняются одной backend-транзакцией. Это не означает, что вся
многошаговая атака является одним HTTP-запросом или одной транзакцией.

Результат каждой mutation-команды должен содержать authoritative result
либо ссылку/версию, по которой frontend получает его через SSE. Frontend не
должен считать локальную рассчитанную версию противника authoritative.

### Этап 5. Перестроить read paths

`inGameSheetSource.ts` должен читать actual, а не:

```text
resolve(approved, overlay.sheet)
```

`CombatCardModelService.ts` должен использовать actual как base и накладывать
только transient game state.

Рекомендованный read boundary:

- standalone `/characters/:id` продолжает использовать
  `CharacterApi.getCharacter`;
- `getGameCharacters()` остаётся membership/roster read и не должен
  превращаться в выдачу полных actual sheets для всех персонажей;
- frontend Game не делает N+1 вызовов `CharacterApi.getCharacter` для каждого
  membership;
- первичная загрузка Game получает player/NPC summaries: id, name, kind,
  status, visibility summary и updated/version metadata, но не полные sheets;
- backend Game получает actual player sheets через Character-owned server
  port/batch lookup только для нужных `entityKeys` или активных combat
  participants; это server-to-server lookup, не цикл frontend HTTP-запросов;
- NPC summaries загружаются пагинацией/фильтром, а NPC projection — для
  открытой карточки, выбранного участника или активного combat subset;
- для карточки, расчёта UI и editor response запрашиваются только нужные
  sections; authoritative combat resolution всё равно выполняется backend
  по entity IDs и не зависит от полного sheet, присланного клиентом;
- visibility и membership policy применяются до сериализации projection.

Таким образом, Character остаётся владельцем actual storage/read semantics,
а Game остаётся владельцем состава участников, NPC и контекстной видимости.
Точный transport может быть разделён на:

```text
game.getRosterSummaries(gameId, cursor/filter)
game.getEntityProjections(gameId, entityKeys, sections?)
```

или эквивалентные endpoints. Полные sheets не должны автоматически
загружаться для 500/10000 NPC. Для chat/search используется pagination или
server-side search summaries; для combat prefetch-ятся активные участники,
а остальные карточки загружаются по открытию. Если в инициативе реально
участвуют сотни NPC, backend всё равно работает по IDs, а frontend получает
компактную combat projection/страницы; это не требует загрузки всех
`GameNpc.version` целиком.

Эта рекомендация непосредственно следует текущим consumers:

- `GameChatTab.vue:227–237` и `CharactersTab.vue:168–182` делают отдельный
  `CharacterApi.getCharacter()` для каждого membership;
- `NpcsTab.vue:118–125` получает полный список `GameNpc`;
- `GameNpc.version` сейчас входит в DTO и используется
  `CombatCardModelService`, поэтому будущий DTO нужно разделить на summary и
  projection, а не просто заменить один full-list endpoint другим;
- новый NPC roster contract должен поддерживать cursor/page, search/filter и
  summary без `version`; полный projection запрашивается только для выбранной
  карточки или combat subset;
- `GameCombatOverlay` сейчас содержит player `sheet`, `states` и resources;
  после перехода он должен быть только game-state read, а actual/projection
  приходит отдельным boundary.

Обычная Character detail должна показывать тот же actual, который использует
Game. UI должен отдельно отображать:

- active session;
- `changes_pending`;
- запрет следующей session;
- результат Character edit и его доставку active session участникам.

Для этого одного `CharacterDetail.version` недостаточно: текущий DTO не
содержит технический `actual_version` и не содержит membership/session
review context. Read contract должен отдельно вернуть concurrency token и
доступный текущему пользователю список game contexts/review states; это не
делает membership частью Character storage.

Затронуты:

- `Game/Sheet/inGameSheetSource.ts`;
- `Game/Service/CombatCardModelService.ts`;
- `Game/Component/Detail/CombatCardPanel.vue`;
- `Character/Page/CharacterDetailPage.vue`;
- `Character/Page/CharacterEditPage.vue`.

### Этап 6. Упростить stop

Текущий stop всей игровой сессии:

```text
resolve approved + overlay
→ validate full sheet
→ update actual
→ clear overlay
```

Целевой stop всей игровой сессии:

```text
validate game-session transition
→ close active game session
→ clear session/battle transient game state
→ preserve actual
→ preserve changes_pending
```

Отдельный `endBattle`/эквивалентный transition должен:

```text
validate current battle
→ close only current battle/processes
→ clear battle-scoped initiative/offers
→ preserve active game session
→ preserve actual and changes_pending
```

Это не должно вызывать `approveMembership` или переводить персонажа в
допустимое состояние для следующей game session.

Затронуты:

- `Game/Mock/mockGameMemberships.ts:360–400`;
- `Game/Mock/mockGames.ts:388–421`;
- backend `stopSession` transaction.

Rollback transient game state остаётся необходимым. Rollback полного
Character sheet после stop больше не является основной операцией.

### Этап 7. Реализовать backend command/state contract

Нужны authoritative backend commands и state/event boundaries:

- `startSession`;
- `startBattle`/`endBattle` или эквивалентные transitions текущего battle
  boundary;
- commands для attack/check/action/defense decisions;
- commands для process/offer transitions;
- backend resolution commands;
- commands для character effects;
- commands для game-state effects;
- `stopSession`;
- `approveMembership`.

Каждая команда должна:

1. проверить actor, membership и active participant;
2. проверить game revision;
3. проверить command/process state и допустимость перехода;
4. проверить expected versions там, где они относятся к изменяемым данным;
5. проверить idempotency/correlation key;
6. построить и провалидировать следующий authoritative state;
7. атомарно записать затронутые Character/Game entities;
8. обновить moderation/review metadata, если изменён actual;
9. сохранить command result/event identity;
10. вернуть authoritative result или version/cursor для его получения.

Backend должен сам разрешать результат относительно своего актуального
состояния. Frontend может отправлять выбранную цель, бросок, уклонение или
другое решение пользователя, но не должен присылать готовый результат
damage/effect как источник истины.

Если command затрагивает несколько Character/NPC/Game entities, Game является
владельцем outer transaction. Текущий SmartTable уже поддерживает нужную
семантику: если transaction открыта на том же gateway/соединении, вложенный
`transaction()` выполняет работу без собственного commit/rollback
(`docs/tr/smarttable.md:117`, `SmartTableGateway.php:82–96`). Поэтому
Character предоставляет mutation port, использующий тот же application
gateway, а Game не пишет Character storage напрямую. Отдельный UoW или
изменение SmartTable для этого не требуется.

Обязательный negative test: Game outer transaction записывает Character,
NPC и Game state, затем получает exception; все записи откатываются
совместно. Отдельно проверить, что Character-owned single-entity edit
самостоятельно открывает и commit-ит свою transaction.

Multi-target combat должен менять все affected characters/NPCs и transient
game state одной транзакцией в момент применения authoritative effect.
Предыдущие steps процесса (offer, accept, defense decision) могут быть
отдельными командами и отдельными транзакциями.

Backend должен публиковать authoritative events/state updates через
SSE/realtime boundary. SSE является каналом доставки server state, а не
заменой command response и не переносит authority на frontend.

### Этап 8. Перенести persistence и schema

Если backend full-sheet overlay ещё не создан, не добавлять отдельную
persisted модель для player Character sheet.

Нужны persisted concepts:

- actual Character state;
- технический `character.actual_version`/concurrency token для CAS; это не
  дополнительная CharacterVersion и не пользовательская история;
- технический membership revision/version для CAS approve/review/session
  transitions;
- актуальный NPC sheet `npc.version` и отдельный `npc.actual_version`;
- approved membership snapshot;
- membership review metadata;
- active session participation;
- game transient state;
- command idempotency/result record;
- durable Game process state;
- optimistic versions.

Для money/inventory/loot нужен канонический typed `EconomyOperation` с
idempotency key и expected versions. Это отдельный экономический журнал
операций, а не общий immutable combat action log; он обязателен для любого
release, который выполняет такие mutation.

### Command idempotency

`commandId` создаётся клиентом для каждого mutating user intent как UUID.
Backend проверяет его уникальность, связывает с actor/game/process и
сохраняет request fingerprint вместе с:

- command type;
- command status;
- result или authoritative version/cursor;
- created/finished timestamps.

Запись idempotency принадлежит persistence владельца команды, а не
`CharacterVersion` и не character overlay: Game command хранится в Game,
Character edit/migration — в Character либо в общем command-idempotency
adapter, а EconomyOperation хранит свою операционную запись.
`commandId` и request fingerprint должны иметь уникальное ограничение в
выбранной области; повтор с тем же id должен вернуть прежний result, а
повтор с другим fingerprint — конфликт параметров.
Для Economy command рекомендуется использовать тот же клиентский
`commandId` как `EconomyOperation.idempotencyKey`; если backend разделяет эти
значения, он обязан хранить их явную one-to-one связь в одной transaction.

Для команды, которая меняет actual, idempotency record и actual mutation
должны фиксироваться в одной backend transaction. Для process decision
запись command и переход process state также должны быть атомарными.

Рекомендуемое хранение: до terminal result соответствующего process или
battle command scope. Это не означает хранение до конца всей game session:
завершение одной атаки не должно требовать хранения idempotency records всех
предыдущих атак до следующей недели. После terminal transition запись можно
удалить или передать короткому техническому retention job; новые commands
получат closed-process/closed-battle conflict, а authoritative state
перечитается отдельно.
Очистка выполняется в terminal transition или коротким retention job после
него; обязательный часовой или суточный grace period не нужен.

Если конкретной команде понадобится выдавать прежний result после закрытия
process или battle, это будет отдельный контракт command-result retention, а
не общее требование idempotency.

Отдельный immutable action log для GM в первый scope не нужен: пользовательский
видимый результат может идти в Chat, а для retry достаточно command record.

### Durable Game process state

Attack/check/defense process state относится к Game/battle, а не к Character:

- он может включать нескольких участников;
- он существует даже если Character sheet не меняется;
- его lifecycle определяется battle, а верхняя граница — game session;
- его нужно восстановить после reload/crash.

Он может физически находиться в общей persisted модели `gameOverlay`, но
должен быть отдельным typed namespace/process record, а не полями
`CharacterVersion` или `GameCombatOverlay.states`.

Пример:

```text
game session state
├── battles
│   ├── initiative
│   ├── attack/check processes
│   ├── offers and decisions
│   └── pending effects
└── other transient game data
```

`CharacterVersion.states` хранит только persisted Character effects:
poison, wound, injury, exhaustion и аналогичные состояния.

### API для in-game editor

На текущем уровне нет обязательства использовать отдельный `GameApi` для
in-game editor. Предпочтительная модель:

- одна Character mutation boundary для обычного и in-game editor;
- Character отвечает за permission/validation/CAS actual;
- после commit `CharacterChanged` передаётся через integration/outbox
  boundary;
- Game подписывается на событие и обновляет active session/SSE/Chat.

Game-specific command остаётся нужен для combat action, потому что action
изменяет process, targets и effects. Editor изменяет Character и не должен
становиться combat command только из-за того, что открыт из Game UI.

Character runtime mutation не должен быть сырым `replacePayload` из Game по
прямому доступу к таблице. Character-owned port должен уметь применить
разрешённый runtime patch к actual, сохранить совместимость с `choices` и
server-side build/validation pipeline и участвовать во внешней Game
transaction, когда mutation является частью Game-owned multi-entity action.
Обычный editor save не должен случайно пересобирать sheet из старых choices и
терять уже применённые runtime states/resources/inventory.

Открытый технический выбор:

1. отправлять из editor полный validated candidate sheet;
2. отправлять typed patch/field changes.

Предпочтительный первый вариант для editor — typed patch/field changes:
меньше payload и меньше риск отправить не изменённые поля. Это не означает
автоматический merge: patch всё равно несёт `expectedActualVersion`, а
устаревшая версия блокирует всю операцию через CAS.

Минимальная политика первого релиза:

```text
expectedActualVersion не совпал
→ CHARACTER_CONFLICT
→ actual не изменён
→ frontend перечитывает actual и пересобирает draft
```

Per-field merge/rebase policy не входит в первый релиз. Для массивов, где
элементы не имеют стабильного идентификатора (например, повторяющиеся
`CharacterStateValue`), patch должен использовать явную operation semantics
или замену соответствующего блока, а не ненадёжный array index.

Backend в любом варианте повторно валидирует итоговый actual и не доверяет
client-calculated result.

### Этап 3b. Migration boundary

Должен существовать один authoritative Character migration mutation path.
Character owns migration/build/validation and actual CAS; Game context may
поставить session/revision guard и передать membership context, но не должен
иметь вторую независимую запись actual.

Game UI и standalone Character UI могут вызывать разные adapters, но оба
должны прийти к одному domain operation:

```text
load actual with expected version
→ build migration candidate
→ validate target revision
→ CAS actual
→ publish CharacterChanged through post-commit integration/outbox boundary
→ Game listener recalculates membership review state
```

Migration во время active session запрещается отдельным Game/session guard.
Оба текущих пути должны быть сведены к этой семантике:

- `Game/Mock/mockGameMemberships.ts:437–460`;
- `Character/Service/CharacterApi.ts:75–88`;
- `Character/Mock/mockCharacters.ts:603–635`.

Если full-sheet или combat-field overlay уже появится до перехода, потребуется
одноразовая migration. Она должна покрывать не только optional `sheet`, но и
все существующие overlay writes:

```text
load player actual/approved or NPC actual, plus overlay, with expected versions
→ resolve overlay.sheet, states, resources and item/equipment changes
   against the persisted actual according to the old allowlist semantics
→ validate complete resulting actual
→ CAS-write actual and membership/review metadata atomically
→ mark changes_pending when semantic diff exists
→ clear all migrated character-sheet fields from overlay
```

Для money/loot migration используется `EconomyOperation`, а не скрытая
запись листа. NPC migration выполняется по `npc.actual_version`, без
approved/review state. Конфликт actual, membership или незавершённой session
не разрешается last-write-wins: migration останавливается с explicit conflict
и требует повторного чтения authoritative state.

### Этап 9. Удалить obsolete full-sheet code

После готовности нового backend path удалить или переименовать:

- `writeOverlaySheet`;
- `readSheet`;
- `ICharacterSessionOverlay` sheet methods;
- `GameCombatOverlay.sheet`;
- `GameCombatOverlay.states/resources` as Character/NPC sheet storage;
- full-sheet `SessionCharacterService.resolve`;
- stop-time full-sheet commit;
- старые comments о `approved + overlay` как листе персонажа.

Не удалять overlay-механику для initiative, process, action и других
transient game state.

## 6. Тестовая матрица

### Character state

- combat damage изменяет actual в момент применения authoritative effect;
- wound сохраняется после reload;
- трёхлетнее увечье сохраняется после `endBattle` и stop;
- очистка `wound.heldBy` не удаляет wound и не меняет его persistent
  strength/bandage/clotting fields;
- poison хранится в `actual.states`;
- несколько одинаковых состояний не теряются;
- длительное увечье переживает stop и новую загрузку;
- resource current value сохраняется как часть actual;
- inventory/equipment mutation сохраняется как actual.

### Moderation

- первая actual mutation выставляет `changes_pending`;
- diff показывает states, resources, inventory и structural changes;
- diff показывает persistent wound fields (`bandage`, `clotting`, `internal`,
  `aided`) и не показывает operational `heldBy`;
- GM может просмотреть боевой diff;
- approve обновляет approved snapshot;
- reject по-прежнему невозможен для active membership;
- changes_pending не отключает active participant;
- changes_pending блокирует следующую session.

### Session predicates

- clean membership проходит `canStartSession`;
- pending membership не проходит `canStartSession`;
- returned membership не проходит `canStartSession`;
- pending active participant проходит `isActiveSessionParticipant`;
- returned active membership перестаёт принимать новые commands после
  `returnForRework`;
- active participant не исчезает из chat/combat после первого action;
- stale actual version отклоняет mutation;
- game revision mismatch отклоняет start/action;
- попытка изменить `rulesRevision`/`spaceCode` во время `playing` отклоняется.

### Errors and retries

- validation failure не меняет actual;
- stale version не меняет actual;
- timeout после successful mutation до terminal process/battle безопасен для retry:
  command record возвращает прежний result;
- retry после terminal process/battle получает closed-process/closed-battle
  conflict и перечитывает authoritative state; прежний command result после
  cleanup не обязателен;
- duplicate idempotency key не удваивает damage/resource spend;
- EconomyOperation и изменение money/inventory имеют один idempotency
  contract и не выполняются без expected versions;
- multi-target effect failure откатывает всех affected participants;
- Character-owned edit не открывает вторую transaction внутри уже открытой
  Game transaction;
- ошибка Chat delivery после committed effect не делает mutation failed и не
  применяет её повторно;
- повторная доставка SSE не создаёт повторную mutation;
- устаревший локальный opponent snapshot не позволяет принять
  невалидное решение;
- reload получает actual и transient game state согласованно.

### NPC

- NPC хранит states в `npc.version`;
- NPC не получает approved snapshot или отдельный moderation version;
- параллельная NPC mutation защищена `npc.actual_version`, а command
  принимает `expectedNpcActualVersion`;
- NPC combat mutation не требует player approve;
- NPC и player используют одинаковую форму persisted state;
- различия permissions/moderation не смешиваются с DTO CharacterVersion.

### Stop/recovery

- `endBattle` не повторяет character mutation и не закрывает game session;
- `endBattle` переводит unresolved offers/processes в `cancelled`,
  очищает pending effects, initiative и battle markers;
- `stopGameSession` переводит оставшиеся session processes в `cancelled` и
  очищает initiative, offers, effects, spells, movement и другие session
  markers;
- actual и `changes_pending` сохраняются после `endBattle` и
  `stopGameSession`;
- crash/reconnect не теряет actual;
- незавершённый action не применяется повторно;
- game revision нельзя сменить в active session.

## 7. Порядок rollout

Рекомендуемый порядок:

1. принять ownership и predicate contract;
2. разделить `canStartSession` и `isActiveSessionParticipant`;
3. определить game-session/battle/process lifecycle и command/event protocol
   для multi-step combat;
4. реализовать backend command processing, actual CAS/idempotency и SSE
   authoritative updates;
5. перевести `states` и persistent combat effects в actual;
6. перевести in-game editor в actual mutation path;
7. оставить battle initiative и process state в game overlay;
8. перевести combat/detail read paths на actual;
9. изменить stop на session finalization;
10. провести migration незавершённых sheet overlays, если они существуют;
11. удалить obsolete full-sheet overlay code;
12. включить integration/concurrency/recovery/realtime tests.

## 8. Оценка “сейчас или позже”

### Если принять решение сейчас

Не требуется создавать backend persistence и публичные API для
`overlay.sheet`. Нужно сразу спроектировать правильную actual mutation
boundary и narrow game-state overlay.

Это дешевле на уровне schema/API и не требует migration production data.

### Если принять решение после реализации Overlay backend

Потребуются:

- dual-read/dual-write или compatibility period;
- migration активных `overlay.sheet`;
- изменение stop transaction;
- изменение optimistic locking;
- переход старых клиентов на actual mutation API;
- recovery конфликтующих или незавершённых sessions;
- повторное тестирование всей combat/moderation цепочки.

Поэтому решение желательно принять до backend implementation, даже если
реализация будет выполнена поэтапно.

## 9. Критерий готовности

Переход можно считать завершённым, если:

1. нет второго persisted полного player Character sheet в overlay;
2. `states` и persistent combat effects находятся в actual;
3. Game overlay содержит только game/session state;
4. GM видит diff всех изменений, включая боевые;
5. active participant продолжает действовать при `changes_pending`;
6. следующая session блокируется до approve;
7. разрешённый Character edit во время session проходит обычные permission,
   validation и CAS checks и публикует CharacterChanged;
8. actual mutations защищены CAS и idempotency;
9. stop не выполняет повторный character commit;
10. reload/crash/retry не теряют и не дублируют изменения;
11. NPC использует ту же модель persisted sheet state без player moderation
    lifecycle.

## 10. Зафиксированные решения

1. **Battle boundary.** В первом release вводится явная логическая граница
   `startBattle`/`endBattle` (конкретное имя API может отличаться).
   Battle является дочерним процессом `playing`; его завершение не вызывает
   moderation approve и не разрешает следующую game session. Минимальная
   реализация может быть `battleId`/battle namespace в Game state, без
   отдельного сложного Battle-модуля.
2. **Crash recovery.** Durable state хранится на уровне Game/battle/process.
   После restart backend читает последний committed state; committed actual не
   откатывается. Ошибка до commit откатывает transaction, потерянный response
   восстанавливается по `commandId`. Recovery/status endpoint является
   техническим API этой уже принятой семантики.
3. **Post-commit delivery.** Используется комбинация synchronous EventManager
   для process-local fast path и outbox для обязательной доставки после
   commit. Outbox не создаёт Chat messages автоматически; Chat остаётся
   отдельной explicit output policy.
4. **Editor payload.** Используется typed patch с
   `expectedActualVersion` и whole-operation CAS без merge/rebase policy. Для
   `states` без stable IDs используется replace всего блока. Ответ обычного
   editor save может содержать полный detail одного персонажа; Game получает
   lightweight projection/delta.
5. **Transient markers.** Markers распределяются по `session`, `battle` и
   `process` namespaces по lifecycle, а не по удобству конкретного UI. Сам
   Character effect не становится transient: wound/poison/injury сохраняются
   в `CharacterVersion.states`. `wound.heldBy` хранится в battle state; при
   compatibility fallback во вложенном actual-поле он исключается из
   semantic diff и очищается при `endBattle`/stop.
6. **Transaction owner.** Single-entity Character edit транзакционен на
   Character boundary. Multi-entity Game action транзакционен на Game
   boundary; Character/NPC mutation выполняется через внутренние порты
   внутри этой transaction и использует тот же transaction-bound
   gateway/connection. NPC не получает отдельный moderation version.
7. **Character runtime mutation.** `sheet` остаётся единственным actual
   листом, `choices` — build inputs/provenance; editor mutation и runtime
   mutation используют общий Character validation/CAS pipeline, а editor save
   сохраняет runtime-owned effects вместо пересборки их из stale choices.
8. **Battle DTO.** Battle имеет durable `battleId`/namespace внутри active
   Game session; новый battle получает новый identity, продолжение сохраняет
   текущий. Initiative/process/offers не используют только `gameId`.
9. **Combat response.** Command response и SSE являются разными boundary;
   `GameCombatOverlay` не переносит actual `sheet/states/resources`, а
   authoritative entity projection и transient `GameBattleState` обновляются
   отдельными DTO.
10. **NPC state.** У NPC нет approved/draft/moderation baseline: есть только
    актуальный `npc.version` (`CharacterVersion`) и технический
    `npc.actual_version` для optimistic concurrency.
11. **Terminal process state.** `endBattle` cancels unresolved battle
    offers/processes and pending effects with `battle_ended`; `stopGameSession`
    cancels remaining session processes with `session_stopped`;
    `returnForRework` cancels participant-owned unresolved processes with
    `character_returned`. Applied authoritative effects are never rolled back.
12. **Idempotency ownership.** Game commands, Character commands and
    `EconomyOperation` own their idempotency records within their respective
    persistence boundaries. A repeated command id returns its prior result;
    a fingerprint mismatch is a conflict. Economy may reuse the same id as
    `EconomyOperation.idempotencyKey`, or must persist an explicit one-to-one
    mapping.

Точная схема таблиц, названия DTO и формат recovery endpoint остаются
implementation details после этих решений. Integration decisions ниже
зафиксированы и не являются открытыми архитектурными вопросами.

## 11. Зафиксированные integration decisions

### 11.1. Outbox и post-commit delivery

Одна outbox-запись создаётся в transaction вместе с
authoritative mutation; запись содержит typed event code, aggregate/entity
key, mutation version, serialized payload/version и delivery status. После commit
локальный EventManager используется как fast path, а worker повторяет
доставку outbox. Chat не является consumer-ом по умолчанию.

`pending/processing` запись хранится до успешной доставки; `processing`
защищается lease/timeout. После успешной доставки запись хранится ещё 24
часа как техническое окно диагностики, но это не idempotency grace period.
Окончательно failed-записи не удаляются молча и остаются доступными для
recovery/manual resolution.

### 11.2. SSE cursor и восстановление projection

Используется monotonic integer stream cursor на уровне game session.
`eventId` является строкой формата:

```text
<gameId>.<cursor>
```

Например: `42.187`. Timestamp для ordering не используется. Событие содержит
`gameId`, `battleId`, `entityKey`, changed sections и visibility-filtered
projection/delta. Response команды остаётся непосредственным результатом
command и не заменяется SSE.

Для reload/reconnect используется `game.sync` с `lastCursor`. Если cursor
доступен, сервер возвращает missed events и current cursor; если cursor
устарел, возвращается полный доступный snapshot и новый cursor. После этого
клиент открывает SSE с `afterCursor = currentCursor`. Повторная доставка
события с уже применённым cursor не создаёт mutation.

### 11.3. Typed patch editor-а

Используется typed patch с `commandId`, `expectedActualVersion` и
`operations[]`, без per-field merge/rebase. Минимальные операции:

- `setField`;
- `replaceSection`;
- `setResourceCurrent`;
- `setInventoryQuantity`;
- `setInventoryEquipped`.

Скалярные и адресуемые блоки изменяются отдельными операциями; `states`,
inventory и другие массивы без stable id заменяются целым нормализованным
блоком. Backend применяет patch к копии actual, выполняет полную validation и
делает один CAS-write. Stale version отклоняет всю операцию.

