# Implementation plan: `game-plan-33` — frontend → PHP E2E (gate P4)

Дата inventory: 2026-10-12. Режим read-only planning. Production-код, тесты
и прочая документация не изменялись. PHPUnit, frontend и dev-server не
запускались.

## Verdict

**READY FOR IMPLEMENTATION.**

D1–D4 и Q1–Q2 закрыты 2026-10-12. Это не `IMPLEMENTED`, не `DONE` боя,
не закрытие C11 и G22. Backend 28–32 не переоткрывается.

## Цель

Подтвердить сценарий readiness P4 на двух независимых login:

```text
пользователь A
  → реальный HTTP
  → Game command
  → PHP transaction
  → Character actual / NPC version
  → delivery
  → пользователь B видит новую версию
```

Канон очереди — [`../tr/game-roadmap.md`](../tr/game-roadmap.md), пункт
`game-plan-33` (`TODO`): два аккаунта, `1 → 1` / `1 → N`, реакции защиты,
stale versions, replay/idempotency, actual/NPC updates, delivery/SSE и
обновление второго клиента. Гейт — [`../tr/game-combat-readiness.md`](../tr/game-combat-readiness.md)
§ Gate P4. Стандарты — [`../../draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md),
[`../tr/php-coding-standards.md`](../tr/php-coding-standards.md).

Backend 28–32 остаётся принятым только для своего backend scope
([`../tr/game-plan-28.md`](../tr/game-plan-28.md) … [`../tr/game-plan-32.md`](../tr/game-plan-32.md)).
Этот файл не повышает их статус до E2E.

## Exclusions

- Магия: каст, duration, отклонение, `SpellCastDialog`.
- Сцена: battleground, координаты, cover, range, falloff.
- `N → N`, сутки, хирургия.
- Закрытие C11 и G22. Бой не помечается `DONE`.
- Смена статусов `game-plan-28.md`–`game-plan-32.md` и `game-roadmap.md`.
- Перенос `WOUND_STATE_CODE` в PHP и `blood_clotting` в `runEvent`.
- Новый боевой расчёт. Game 33 подключает уже принятые команды, не
  переоткрывает pipeline 28–32.
- Остальные локальные записи состояний не переводятся на PHP в этом
  шаге: `FuriousRushService`, `BloodLossService`, `CombatCardPanel`,
  `InitiativeTrack`, `SpellCastDialog`. Прогон P4 их не нажимает.

## Current inventory

### PHP-команды 28–32, которые уже есть

Реестр — `www/mifrial/modules/Roleplay/Game/module.config.php`.

| Команда | Handler | Шаг |
| --- | --- | --- |
| `game.declareStrike` | `Action/DeclareGameStrikeAction.php` | 28–30, долг ОД 32 |
| `game.resolveStrike` | `Action/ResolveGameStrikeAction.php` | защита и close |
| `game.declareWideStrike` | `Action/DeclareGameWideStrikeAction.php` | `1 → N` |
| `game.resolveWideStrike` | `Action/ResolveGameWideStrikeAction.php` | `1 → N` |
| `game.woundAction` | `Action/GameWoundAction.php` | 31 |
| `game.endTurn` | `Action/EndGameTurnAction.php` | 32 |
| `game.continueAction` | `Action/ContinueGameAction.php` | 32 |
| `game.abortAction` | `Action/AbortGameAction.php` | 32 |

Входов `game.submitCombatCommand`, `game.mutateRuntimeEntity`,
`game.addCombatState`, `game.replaceCombatState`, `game.removeCombatState`
в `module.config.php` нет.

Replay/CAS на сервере: `expectedVersion` / `expectedBattleVersion` /
`expectedSheetVersion` и `idempotencyKey` (`Dto/Action/DeclareGameStrikeInput.php`,
`Dto/Action/GameTurnInput.php`, `Dto/Action/GameWoundInput.php`).

Запись actual/NPC на close и на тике хода — сервисы
`Service/GameStrikes.php`, `Service/GameWideStrikes.php`,
`Service/GameStrikeConsequences.php`, `Service/GameTurns.php`,
`Service/GameWoundActions.php`. Это backend evidence, не браузерный P4.

### Runtime actions, которые фронт ещё шлёт в обход PHP

`VITE_API_MODE !== 'real'` регистрирует `mockGameApi`
(`draft-front_1.2ds/src/main.ts`). В режиме `real` регистрируется
`Service/GameApi.ts`, но боевые методы зовут имена, которых в PHP нет:

- `game.submitCombatCommand` — `GameApi.submitCombatCommand`,
  `Service/GameCombatCommandAdapter.ts`;
- `game.mutateRuntimeEntity` — `GameApi.mutateRuntimeEntity`,
  mock `Mock/mockGameCombatOverlays.ts`;
- `game.addCombatState` / `game.replaceCombatState` /
  `game.removeCombatState` — `GameApi.ts` и mock overlays.

Живые вызовы записи состояния из UI/сервисов (не тесты):

- `Component/HitLaunchDialog.vue` — `addCombatState` / `replaceCombatState` /
  `removeCombatState`;
- `Component/ActionLaunchDialog.vue`, `Service/WoundActionService.ts` —
  `replaceCombatState` для зажима и перевязки;
- `Service/EndOfTurnDotsService.ts`, `Service/BloodLossService.ts`,
  `Service/InjuryCheckService.ts`, `Service/FuriousRushService.ts`;
- `Component/InitiativeTrack.vue`, `Component/Detail/CombatCardPanel.vue`,
  `Utils/combatStateWrite.ts`.

`GameCombatCommand` (`Dto/GameCombatCommand.ts`: `attackDecision` /
`defenseDecision`, `actorKey`, `commandId`) не совпадает с PHP-входом
`declareStrike` / `resolveStrike` (`battleId`, `idempotencyKey`,
`expectedVersion`, `attack` / `defense`). Вызовов
`gameCombatCommandAdapter` из компонентов нет. Имени `declareStrike` во
фронте нет.

### Delivery / SSE

Сервер:

- `API/game-sync.php` → `Service/GameDeliverySync.php`, путь
  `/api/game/sync`, кадр `sync` с `{ cursor, events }`.
- Событие: `eventId`, `cursor`, `source`, `sourceId`, `keys`
  (`Service/GameDelivery.php`).
- `GameStrikes` / `GameWideStrikes` пишут `source=strike` с пустым
  `keys`. `GameDeliveryKeys::fromStrike` тоже возвращает `[]` (в итоге
  удара нет type/id листа). `GameTurns` пишет `source=turn` с `keys=[]`.
- `GameWoundActions` сигнал delivery не пишет.
- Видимость NPC фильтруется в `eventsOf`. Тело листа в кадр не входит.

Клиент:

- Чат уже слушает SSE: `ChatSyncService` → `engine.openSse('/chat/sync')`.
- Бой: `GameRealtimeApi.subscribe` сразу зовёт `onError`
  (`GAME_REALTIME_UNAVAILABLE`) и не открывает поток.
- `GameRealtimeApi.sync` вызывает action `game.sync`. Такой action в
  `module.config.php` нет.
- `Component/Detail/GameChatTab.vue` слушает `getGameRealtimePort()`.
  В mock это in-memory порт; в `real` — заглушка выше.

### Два независимых login в этом репозитории

- `auth.login` есть: `draft-front_1.2ds/src/modules/Core/Auth/Service/AuthApi.ts`.
- Сессия — cookie (`mifrial-session` в kernel request context). Один
  браузерный профиль держит одного актора.
- Режим API — `VITE_API_MODE=real` в `main.ts`. Иначе весь Game API —
  mock, и второй клиент PHP не увидит.
- Готовой пары аккаунтов, общего боя и двух персонажей под браузерный
  P4 в этом inventory нет. Как завести второго пользователя и второй
  контекст браузера — D4.

### Чего нет для браузерного P4

- Жест атаки и реакции dodge/block/ignore не вызывает
  `declareStrike` / `resolveStrike` / wide.
- Жесты раны и конца хода не вызывают `woundAction` / `endTurn` /
  `continueAction` / `abortAction`. В приёмку они входят по Q1.
- Второй клиент не подписан на `/api/game/sync`. Подписка — D3.
- Кадр удара не содержит ключей листа. Перечитывание — D2.
- Stale version и replay из браузера не прогнаны: UI не шлёт
  `expectedVersion` и `idempotencyKey` PHP-команд.
- `changes_pending` в браузере не проверялся. Проверка чтения — Q2.

## Решения

Закрыты 2026-10-12. Варианты ниже — выбранные, не открытые вопросы.

### D1 — жест вызывает уже принятые PHP-команды — **B**

Фронт вызывает `game.declareStrike`, `game.resolveStrike`,
`game.declareWideStrike`, `game.resolveWideStrike`. Адаптер перекладывает
поля диалога в тело этих команд (`battleId`, `idempotencyKey`,
`expectedVersion`, `attacker` / `defender` как `{type, id}`,
`itemInventoryId`, `profileType`, `profileIndex`). Для `reaction=block`
`GameStrikeBody::defense` требует `blockItemInventoryId` и
`blockItemProfileIndex`; одного `blockItemRuleCode` недостаточно.
Action `game.submitCombatCommand` на PHP не заводится. Локальный расчёт
диалога показывает намерение до ответа. `HitLaunchDialog` после этого
не вызывает `addCombatState` / `replaceCombatState` / `removeCombatState`
и не вызывает `injuryCheckService.applyInjuryCheck`: увечье уже пишет
close PHP. Лист пишет транзакция PHP.

### D2 — второй клиент перечитывает видимые листы — **C**

Кадр SSE по-прежнему несёт `source` / `sourceId` / `keys`, не тело листа.
`keys` удара можно не заполнять. На событие `strike`, `turn` или `wound`
клиент заново вызывает `game.getBattleSheets` (`gameId`, `battleId`).
Ответ — `characters`, `npcs`, `sheets`; NPC режутся маской видимости в
`GameProjections::battleSheets`. Это единственное уже зарегистрированное
чтение листов состава боя. `game.getRuntimeEntity` в `module.config.php`
нет, для P4 его не заводить. Экран, на котором B смотрит лист персонажа
или NPC, рисует этот ответ. Полная перезагрузка страницы для этого не
нужна.

`game.getSheets` требует непустой список ключей и для этого шага не нужен.

Экран, на котором B смотрит лист в бою, уже есть: `GameChatTab.vue`
монтируется только на вкладке игрового чата (`GameDetailPage.vue`,
`v-if="activeTab === 'game-chat'"`). Внутри — `InitiativeTrack` и
`CombatCardPanel`. Они рисуют `runtimeByKey` / проекции. После кадра
SSE эта вкладка вызывает `game.getBattleSheets` с числовым `battleId`
текущего боя и подставляет `sheets` в то же состояние, с которого
рисуется карточка. Элемент `sheets[]` — `{type, id, name, actualVersion,
choices, sheet}` (`GameProjections::characterSheet` / `npcSheet`), не
`GameRuntimeEntityProjection`. Карточка читает `choices` и `sheet`.
`game.getRuntimeEntity` не вызывается: его нет в реестре PHP.

Следствие: `game.woundAction` сейчас delivery не пишет. Команда раны
после записи вызывает тот же сигнал, что `GameStrikes::announce`:
`source=wound` (колонка `source` длиной 16, рядом уже есть `strike` и
`turn`), `sourceId` — id строки `game_wound_command`. Расчёт раны не
меняется. `GameDelivery::catchUp` уже дописывает economy, strike и
turn из журналов. Туда же входит журнал `game_wound_command`: иначе
повторное подключение с `lastCursor` (D3) не увидит рану, хотя удар
и ход после обрыва канала догоняются.

### D3 — подписка тем же SSE, что у чата — **A**

`GameRealtimeApi.subscribe` открывает `engine.openSse('/game/sync', { gameId, lastCursor })`.
Правило уже есть: `www/.htaccess` ведёт `/api/game/sync` на
`mifrial/API/game-sync.php`. Action `game.sync` не добавляется. Повторное
подключение передаёт последний курсор.

Кадр читает серверные `source`, `sourceId`, `keys`. `GameRealtimeApi.sync`
и action `game.sync` не используются: восстановление — повторный
`openSse` с `lastCursor`. `GameChatTab.recoverRealtime` / `syncRealtime`
сейчас зовут `port.sync()` и затем `getRuntimeEntity`; этот путь в P4
не оставлять.

`CharactersTab.vue`, `NpcsTab.vue`, `ModerateTab.vue` при кадре без
`entityKey` не вызывают `getRuntimeEntity` и не падают. Прогон P4
смотрит лист на вкладке игрового чата. Gzip для `/api/game/sync` уже
выключен в `www/.htaccess`, как у чата.

### D4 — два профиля браузера — **A**

Два cookie-контекста (два профиля или обычное окно и приватное) на одном
стенде при `VITE_API_MODE=real`. Оба входят через `auth.login`, оба в одной
игре. Имена пользователей в план не зашиты: на стенде нет постоянной пары.

### Q1 — раны и конец хода входят в прогон — **B**

Приёмка включает зажать / отпустить / перевязать (`game.woundAction`) и
конец хода (`game.endTurn`). Жесты экрана:

- удар и защита — `HitLaunchDialog.vue` (сейчас пишет состояния сам);
- рана — `ActionLaunchDialog.vue` и `WoundActionService` (сейчас
  `replaceCombatState`);
- конец хода — `InitiativeTrack.vue` `confirmWaitAndPass` (сейчас локально
  сбрасывает процесс и зовёт `endOfTurnDotsService` через кровь);
- долг ОД после `declareStrike` / wide — `game.continueAction` и
  `game.abortAction` с тем же `GameTurnInput`, что у `endTurn`. Это карта
  долга боя (`GameActionDebt`), не локальная `CommittedActionSession`.
  `committedActionFlowService` остаётся для растягиваемых не-атак и в
  прогон P4 не входит.

Магия и сцена не входят. Расчёт 31–32 не переоткрывается.

### Q2 — `changes_pending` проверяется чтением — **A**

После удара по персонажу чтение `game.getCharacterList` показывает
`changes_pending`, если до удара снимок совпадал с actual (`clean`).
Бой это поле не пишет: его считает `GameCharacterReview::reviewState`.
Пустой снимок уже даёт `changes_pending` до удара, такая фикстура
проверку не доказывает. Для NPC проверки нет. Фронтовый
`GameApi.getGameCharacters` сейчас зовёт `game.getCharacters`; этого
имени в реестре нет. Экран проверки вызывает `game.getCharacterList`.

## Что менять в коде (не новый расчёт)

PHP:

- `GameWoundActions` после успешной записи — `GameDeliverySignal` с
  `source=wound` и id `game_wound_command`.
- `GameDelivery::catchUp` — цикл по журналу ран, как у turn.
- Тест: `GameDeliveryMysqlTest` (или focused wound) — живой сигнал и
  catchUp после раны. Расчёт раны не дублировать.

Фронт (`VITE_API_MODE=real`):

- `GameApi`: методы `declareStrike`, `resolveStrike`, `declareWideStrike`,
  `resolveWideStrike`, `woundAction`, `endTurn`, `continueAction`,
  `abortAction`, `getBattleSheets`, `getCharacterList`. Не вызывать
  `game.submitCombatCommand`, `game.getRuntimeEntity`, `game.getCharacters`,
  `game.sync`.
- `GameRealtimeApi.subscribe` — `engine.openSse('/game/sync', …)`.
- `GameChatTab` применяет кадр через `getBattleSheets`.
- Жесты выше перестают звать `addCombatState` / `replaceCombatState` /
  `removeCombatState` / `injuryCheckService.applyInjuryCheck` /
  локальный тик DOT на пути P4.

Устаревшая версия боя — `GAME_CONFLICT` (`GameBattleConflictException`).
Повтор того же `idempotencyKey` с тем же телом возвращает сохранённый итог
без второй записи.

## Критерии приёмки E2E

Только браузер, `VITE_API_MODE=real`, две cookie-сессии. Зелёный PHPUnit
этот гейт не закрывает.

1. A объявляет и закрывает `1 → 1` через `declareStrike` / `resolveStrike`,
   с реакцией dodge, block и ignore — каждая отдельно.
2. A объявляет и закрывает `1 → N` через `declareWideStrike` /
   `resolveWideStrike`.
3. Цель-персонаж и цель-NPC получают actual/version только транзакцией PHP.
   Повтор того же `idempotencyKey` не пишет второй исход. Устаревшая version
   боя отвергается без частичной записи, и браузер это показывает.
4. B без перезагрузки страницы видит новый лист персонажа и видимого ему
   NPC после удара, раны и конца хода. Экран берёт данные из ответа
   `game.getBattleSheets` после кадра SSE. Скрытый NPC в этот ответ не
   входит.
5. A зажимает, отпускает и перевязывает через `game.woundAction`. A
   заканчивает ход через `game.endTurn`. Если удар оставил долг очков
   действия, A продолжает его через `continueAction` и отдельно обрывает
   через `abortAction`.
6. До удара `game.getCharacterList` даёт `clean`. После удара по этому
   персонажу то же чтение даёт `changes_pending`. Отдельной записи поля нет.
7. Магия и сцена в прогоне отсутствуют.
8. C11 и G22 этим прогоном не закрываются. Статус боя не становится `DONE`.
   Game 33 этим планом не переводится в `IMPLEMENTED`.

## Что эта сессия не трогала

Production PHP и Vue, тесты, `docs/tr/*`, прочие `docs/review/*`.
PHPUnit, frontend и dev-server не запускались. `reset`, `checkout`,
`stash` и `commit` не выполнялись.
