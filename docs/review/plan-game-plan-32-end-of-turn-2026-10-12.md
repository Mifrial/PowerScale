# Implementation plan: `game-plan-32` — конец хода и продолжение действия

Дата inventory: 2026-10-12. Решения D1–D8, Q1–Q6, R1–R6, S1–S4 и T1–T6
зафиксированы 2026-10-12.  
Режим documentation-only closure: 2026-10-12. Production-код и тесты не
изменялись и не запускались.

## Verdict

**APPROVED FOR THE ACCEPTED BACKEND SCOPE; NOT A PROJECT-WIDE APPROVAL.**

Backend Game 32 принят для scope ниже. Прежний verdict
`READY FOR IMPLEMENTATION` и контракт далее в файле сохранены как decision
trace и не являются текущим статусом.

## Final closure record

- **Backend Game 32 — APPROVED** для принятого scope: `endTurn` (clotting,
  кровь, DOT, проверки, refill F1 уходящего, сдвиг курсора), долг ОД на
  `declareStrike` / `declareWideStrike`, `continueAction` / `abortAction`,
  снятие слота долга на `leave` и выбывании из roster, replay и CAS.
  Character и NPC идут одним pipeline.
- **Exclusions.** Магия (каст, duration, отклонение); сцена (battleground,
  координаты, cover, range, falloff); `N → N`; сутки; frontend → PHP → UI
  E2E (Game 33); закрытие C11 и G22; константа `WOUND_STATE_CODE`;
  `blood_clotting` в реестре Mechanic `runEvent`.
- **Residual evidence.** Focused MySQL не assert’ит `delivery.source` и
  увечье DOT; эти пути закрыты чтением кода. Полный suite в verification
  не запускался. В этой documentation-only сессии PHPUnit и frontend не
  запускались.
- **Следующий этап.** Game 33 остаётся отдельным этапом со статусом `TODO`
  и не переводится в `IMPLEMENTED`.
- Статусный канон — [`../tr/game-plan-32.md`](../tr/game-plan-32.md).
  Очередь — [`../tr/game-roadmap.md`](../tr/game-roadmap.md).

Это закрытие backend принятого scope, не project-wide approval и не
подтверждение frontend → PHP → delivery E2E или deployment/live
verification.

## Historical contract verdict

**READY FOR IMPLEMENTATION** — historical trace.

Все D/Q/R, S1–S4, T1–T6 закрыты. T5 = **B**, T6 = увечье при получении
раны или истощения от DOT + **удаление** строки при силе ≤ 0. S4 = **P1**.
Это не `IMPLEMENTED` и не C11/G22.

## Цель roadmap

Clotting (прибавка к полю, которое Game 31 пишет как `0`), bleeding/DOT,
автоматические проверки после тика, continuation/cancellation долгого
действия и передача хода.

Канон очереди — [`../tr/game-roadmap.md`](../tr/game-roadmap.md) шаг
`game-plan-32` (`TODO`). Граница ран — [`../tr/game-plan-31.md`](../tr/game-plan-31.md)
и D3 в [`plan-game-plan-31-wounds-2026-10-11.md`](plan-game-plan-31-wounds-2026-10-11.md):
конец хода — команда «ход закончен»; сервер сам берёт текущего персонажа,
обходит строки и читает mechanic карточки; клиент clotting не присылает;
тик = сила − закрытость. Готовность P3 —
[`../tr/game-combat-readiness.md`](../tr/game-combat-readiness.md).
Формула вкладов — [`../tr/rule-content-plan-04-knowledge.md`](../tr/rule-content-plan-04-knowledge.md)
§4.2, посадка эскиза — [`../tr/rule-content-plan-06-wounds.md`](../tr/rule-content-plan-06-wounds.md)
§1.1 (inventory фронта, не PHP-канон идентичности). Стандарты —
[`../tr/php-coding-standards.md`](../tr/php-coding-standards.md).

## Exclusions

- Магия: каст, duration заклинания, отклонение. Spell runtime не входит.
- Сцена: battleground, координаты, `ISpatialResolver`, cover, range, falloff.
- `N → 1` и `N → N`.
- Frontend → PHP → UI E2E, delivery второго клиента — Game 33.
- Закрытие C11 и G22; `putState` / `patchState` / `removeState` / refill
  kind не объявляются закрытием C11.
- Перенос `WOUND_STATE_CODE` и формул `WoundInstanceService` как PHP-канона.
  Формулу тика брать из плана-04 §4.2, не копировать сервис фронта.
- Суточное −2 силы раны (`OPEN` в readiness; Game 31 явно оставил вне боя).
- Хирургия, внутреннюю рану сделать внешней, уход.
- Смена статусов `game-plan-28.md`–`game-plan-31.md` и `game-roadmap.md`.
- `blood_clotting` в реестр Mechanic `runEvent` (запрет
  [`../tr/mechanic-roadmap.md`](../tr/mechanic-roadmap.md)). Маркер-хендлер
  на карточке состояния (D3-A) в `runEvent` не подписывается.

## Current inventory

### Что сервер уже умеет

**Sidecar раны на close.** Режущий путь `GameStrikeConsequences::effects`
кладёт `putState` с `clotting: 0`, `bandage: 0`, `internal: false`,
`aided: false`, `heldBy: null`. Сила — `woundStrength`. Код состояния —
payload `IDamageAsWounds`, не константа фронта.

- `www/mifrial/modules/Roleplay/Game/Service/GameStrikeConsequences.php`
- `www/mifrial/modules/Roleplay/Mechanic/Interface/IDamageAsWounds.php`
- `www/mifrial/modules/Roleplay/Mechanic/Service/Handler/DamageAsWoundsHandler.php`

**Строка `sheet.states`.** `CharacterActualStateRows` выдаёт `id` в
`appendOne`, sidecar на `independent`. `putState` и `sidecarOf` уже
принимают `clotting`. **`acceptPatch` allow-list — только `aided`,
`bandage`, `heldBy`.** `clotting` / `dotTurnsLeft` в patch не проходят,
пока D8 не расширит список. Повторный `putState` на уже лежащую
`independent` строку с `isDamageRemainder()` / `isDamageExhaustion()`
бросает; фикстуры Game 30 пишут истощение `aggregation: sum`. Кровь
и пол истощения нельзя «добавить второй независимой строкой».

- `www/mifrial/modules/Roleplay/Character/Service/CharacterActualStateRows.php`
- `www/mifrial/modules/Roleplay/Character/Service/CharacterActualMutations.php`

**`heldBy`.** Форма `{ kind: "character"|"npc", id: int }`. Зажим /
отпускание / перевязка — `game.woundAction`
(`GameWoundAction` → `GameWoundActions`). Replay: таблица
`game_wound_command`, `GameReplayTransaction`. Руки: `RaceSpec`, иначе 2.
Внутреннюю не зажать и не перевязать. Перевязка: `bandage +2`, потолок 4,
грант `WoundBandageAidGrant` снимает потолок и ставит `aided`.

- `www/mifrial/modules/Roleplay/Game/Action/GameWoundAction.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameWoundActions.php`
- `www/mifrial/modules/Roleplay/Game/Table/GameWoundCommandTable.php`
- `www/mifrial/modules/Roleplay/Game/Dto/Action/GameWoundInput.php`

**`pending_action_effects`.** JSON-колонка `game_battle` (карта:
`GameBattleTable` — ещё `turn_order` и `state_version`, курсора нет).
Карта актора `character|npc` + id → список `{ sourceRuleCode, effect }`.
Пишут close удара и `woundAction` через `GamePendingActionCosts`.
`endBattle` удаляет строку боя, карта исчезает. DDL колонок боя —
`GameBattleSchema::updateTable` (тот же путь, что Game 31 для pending).

- `www/mifrial/modules/Roleplay/Game/Table/GameBattleTable.php`
- `www/mifrial/modules/Roleplay/Game/Dto/GameBattleRecord.php`
- `www/mifrial/modules/Roleplay/Game/Repository/GameBattleRepository.php`
  (`replacePending`)
- `www/mifrial/modules/Roleplay/Game/Service/GamePendingActionCosts.php`

**`leave` strip.** `GameCharacterMemberships::leave` в одной транзакции
с `saveLeft` снимает `heldBy` с `kind: character` и id уходящего на листах
персонажей и NPC этой игры. Силу и `bandage` не трогает. NPC через `leave`
не выходит.

- `www/mifrial/modules/Roleplay/Game/Service/GameCharacterMemberships.php`

**`endBattle`.** `GameBattleMutator::end` гасит open process боя,
удаляет строку, сессию не останавливает. Рану, sidecar и `heldBy` не
меняет (принятый D2 Game 31). Отдельной команды конца хода нет.

- `www/mifrial/modules/Roleplay/Game/Service/GameBattleMutator.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameBattleCleanup.php`
  (stop session: cancel process, delete battles и wound commands)

**Порядок хода.** G21 пишет `turn_order`. Курсора нет.
`GameBattleInitiatives::roll` делает `advanceVersion` + `replaceTurnOrder`
(`replaceTurnOrder` версию **не** двигает). `start` бой курсор и порядок
не ставит. `setRoster` пересчитывает порядок через `nextOrder`, курсора
не трогает. HTTP: `game.rollInitiative`, `game.startBattle`,
`game.setBattleRoster`, `game.endBattle`. Нет `game.endTurn` /
`game.passTurn`. `endTurn` до `rollInitiative` (нет порядка/курсора) —
`GAME_INVALID`.

- `www/mifrial/modules/Roleplay/Game/Service/GameBattleOrder.php`
- `www/mifrial/modules/Roleplay/Game/module.config.php`

**Process G17.** Таблица `game_process`: `session_id`, `battle_id`,
`participant_type`, `participant_id`, `status` `open|resolved|cancelled`.
Эффект листа и долг ОД не считает. `open` / `resolve` / `cancel`;
return / `endBattle` / `stopSession` гасят open. Это не
`CommittedActionSession`.

- `www/mifrial/modules/Roleplay/Game/Service/GameProcesses.php`
- `www/mifrial/modules/Roleplay/Game/Table/GameProcessTable.php`
- `www/mifrial/modules/Roleplay/Rule/Dto/Spec/Ability/AbilityProcessSpec.php`
  (карточка процесса; Game её как долг ОД не исполняет)

**Проверки.** G18: `game.declareCheck` / `proposeCheck` / `answerCheck`.
Бросок — `GameCheckRoll` + `IMechanicRolls`. Immediate Воля/увечье на
close — `GameStrikeConsequences` (флаг `StateSpec::isMaim()` и т.п.).
Автотик свёртывания и кровопотери эти пути не вызывают.

- `www/mifrial/modules/Roleplay/Game/Service/GameChecks.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameCheckRoll.php`

**Кровопотеря как карточка.** `StateSpec::isBloodLoss()` уже есть в Rule.
Game этот флаг на конце хода не читает.

- `www/mifrial/modules/Roleplay/Rule/Dto/Spec/StateSpec.php`

**DOT в Rule.** `DamageOverTimeEffect` (`damage_over_time`, тип урона,
период, decay). PHP Game тик не исполняет.

- `www/mifrial/modules/Roleplay/Rule/Dto/Spec/State/DamageOverTimeEffect.php`
- `www/mifrial/modules/Roleplay/Rule/Spec/StateEffects.php`

**Каталог Mechanic.** Зарегистрированы: plain roll, surcharge, six-one,
advantage, reliability, exhaustion stun/shock/wound, damage_as_wounds,
blunt unconscious. Хендлера свёртывания нет.
`mechanic-roadmap.md` явно не ставит `blood_clotting` в `runEvent`.

- `www/mifrial/modules/Roleplay/Mechanic/Service/MechanicPortFactory.php`

**Ресурс хода.** `ResourceSpec::isAutoAdd()` есть. `spendResources` только
вычитает и при нехватке бросает `Resource current is insufficient`. Kind,
который **ставит** current к лимиту, в `CharacterActualMutations` нет.
Фронт refill: первый `type=resource` с `auto_add` и
`setCombatResource` на max — этого HTTP/kind в PHP нет.

**CAS / replay close и ран.** Один `GameStrikeSheetWrites` (персонаж
через `ICharacterActualMutations`, NPC через `npc.version`). Конфликт
версии не оставляет частичной записи. Повтор того же body возвращает
сохранённый result. Outbox: `GameStrikes` / `GameWideStrikes` зовут
`GameDeliverySignal` с `source = 'strike'` и `sourceId` строки
`game_strike_command` / wide-команды; `GameDelivery::catchUp` читает
только economy и strike-команды; `GameDeliveryKeys::fromStrike` —
пустой список ключей (событие всё равно уходит: пустые keys не
фильтруются). `GameWoundActions` доставку **не** пишет. Поле
`game_delivery.source` — `StringField` длина **16**.
`GameReplayTransaction` для удара и раны — `reserve` / `complete` на
своей таблице команд, не `game_battle_command` (`add` после мутации у
start/roster/initiative).

### Что делает только frontend (эскиз, не канон PHP)

- `WoundInstanceService.tick` / `bleedTotal` / `closure` (зажим даёт
  закрытость не ниже 1); код раны = `WOUND_STATE_CODE`; sidecar вложен
  в `state.wound`, не поля строки как в PHP Game 31.
  `draft-front_1.2ds/src/modules/Roleplay/Game/Service/WoundInstanceService.ts`
- `BloodLossService.applyTurnWoundBleed`: clotting-проверки по ранам
  (`payload.type === 'blood_clotting'`), затем Σ тик → `blood-loss`,
  затем `EndOfTurnDotsService`. Версия листа на фронте после replace
  свёртывания **не** обновляется перед `bleedTotal` (зафиксировано в
  `game-combat-readiness.md`).
  `draft-front_1.2ds/src/modules/Roleplay/Game/Service/BloodLossService.ts`
- `EndOfTurnDotsService` / `DotTickMathService`: горение и яд по
  `damage_over_time`, period/wait, decay; затем локальный
  `attackDamageService.applyAttackDamage` и runtime
  `addCombatState` / `replaceCombatState` / `removeCombatState`.
  Этих HTTP в PHP нет.
- `CommittedActionService` + mock
  `game.getCommittedActionSessions` / `game.setCommittedActionSession`.
  Долг: `remainingOd`, `totalOd`, `actionRuleCode`, `stateIndices`
  (индексы массива, не id строки Game 31).
  `draft-front_1.2ds/src/modules/Roleplay/Game/Dto/CommittedActionSession.ts`
  `draft-front_1.2ds/src/modules/Roleplay/Game/Service/CommittedActionService.ts`
- UI передачи хода / continue:
  `InitiativeTrack.vue`, `CommittedActionContinueDialog.vue`.

### Чего нет (порты, таблицы, mechanic)

| Пробел | Следствие |
|---|---|
| HTTP конца хода / передачи хода | Клиент нечем вызвать authoritative P3 |
| Курсор текущего участника на `game_battle` | «Сервер сам берёт текущего» не на чем сработать |
| `patchState.clotting` | Прибавка свёртывания не проходит accept |
| Mechanic/маркер свёртывания в PHP | D3-A: хендлер + привязка на карточке состояния; в PHP-контенте `blood_clotting` нет |
| Таблица/колонка долга ОД | `game_process` без remaining OD; mock sessions не persistence |
| Kind записи current ресурса к max | Q6/D1 refill нельзя сделать через `spendResources` |
| Исполнитель `DamageOverTimeEffect` в Game | DOT только на фронте; ключа периода на строке PHP нет |
| Команда replay конца хода | `game_battle_command` без reserve; нужен журнал как strike/wound |
| Автопроверки после кровопотери | Воля при Δ истощения (S2); увечье крови R2, не формула раны |
| Outbox `endTurn` | `catchUp` не знает нового `source`; чужой `strike` сломает журнал удара |
| Открытый `1→1` / `1→N` | `findOpen` есть; T1: `endTurn` отказ, пока эффект отыгрывается |
| HTTP долга кроме continue/abort | Game 32: только declare удара (T2-A); общий declare — позже |

Новый полный лист и прямой SQL в `sheet` не заводятся. Character не
импортирует Game.

### Граница транзакции / CAS / replay сегодня

```text
close 1→1 / 1→N:
  GameReplayTransaction
  → лист цели (G10 / npc.version) + battle version + pending + чат
  → затем outbox delivery

woundAction:
  тот же replay
  → patchState цели + spend актора + pending (если бой) + одно текстовое сообщение

leave:
  saveLeft + patchState heldBy=null по раненым; без battle version

endBattle:
  cancel process + delete battle (pending уходит вместе) + command row
```

Конец хода в эту границу не входит. D6 закрыт: один commit на лист
текущего (DOT в этом ходе — его же состояния, не чужие цели).

Границы 28–31, которые нельзя ломать: injury/resistance/penetration/block
Game 28; identity атаки Game 29; immediate pipeline Game 30; id строки,
sidecar, `heldBy`, `woundAction`, pending, leave strip Game 31. Cover и
карта по-прежнему не отказ. Replay сверяет тело.

## Authoritative ownership

| Concern | Owner сегодня | Game 32 не забирает молча |
|---|---|---|
| `clotting: 0` на новой ране | Game 31 close | Не пересоздавать ключ |
| Прибавка `clotting` | Нет | Конец хода, mechanic карточки, не клиент |
| Тик крови | Фронт `tick` | Формула плана-04, не `WOUND_STATE_CODE` |
| Накопленная кровь | `StateSpec::isBloodLoss()` есть, запись нет | `putState` / `patchState` / `putDamageSplit`; T6 добавляет `removeState` |
| DOT | Rule effect + фронт | Исполнитель Game; Rule слои цели не собирает |
| Долг ОД | Фронт mock | Не выдавать G17 process за долг |
| Текущий ход | `turn_order` без курсора | Не класть курсор в `pending_action_effects` |
| C11/G22 | TODO | Не закрывать |

## Decisions

### D1 — закрыт, A (2026-10-12)

Одна HTTP-команда `game.endTurn`: тики уходящего (clotting, кровь, DOT,
проверки) + восстановление ОД уходящего до потолка карточки хода + сдвиг
курсора. Клиент не шлёт clotting, tick, bleedTotal, итог DOT. Вход:
`battleId`, `idempotencyKey`, ожидаемые версии боя и листа текущего.
Вызов: игрок своего текущего персонажа или GM (NPC и чужой лист).
Continue долга — **не** эта команда (начало следующего хода, D5).

Отклонены B (tick и pass раздельно) и C (числа с клиента).

### D2 — закрыт, A (2026-10-12)

На бое отдельные поля курсора: `active_index` и `round`. Колонку
`turn_order` (порядок G21) не смешивать с «кто ходит». Клиент не является
источником текущего; сверка актора с курсором допустима как fingerprint.
После `rollInitiative` первый ход: `active_index = 0`, `round = 1`.
Сдвиг: индекс +1; за концом списка — `0` и `round + 1`. Писать курсор и
карту долга тем же CAS, что `replacePending` (версия + JSON), не через
`replaceTurnOrder`. `roll` / `setRoster` после `advanceVersion` должны
записать курсор в том же update, что порядок, без второго bump.

Отклонены B (клиент присылает текущего как факт) и C (флаг внутри
элементов `turn_order`).

### D3 — закрыт, A (2026-10-12)

Mechanic свёртывания живёт на карточке **состояния**. `StateSpec` списка
mechanic не имеет. Bindings — `CharacterResolvedRule::getMechanics()`
(`mechanic_id` + `mechanic_payload`), как `GameStrikeConsequences::hooks`
читает тип урона. Game: `slice->findLive(stateRuleCode)`, маркер =
хендлер `instanceof` нового интерфейса (образец `IDamageAsWounds` +
пустой `run()` / пустые `getSubscriptions()`). `check_code` — из
payload маркера (R6), не `StateSpec::getCheckCode()` (то поле у увечья
и Воли). Есть маркер — бросок и вклад в `clotting` этой строки, не выше
силы. Нет маркера на строке — эту строку не свёртывают. Та же mechanic на
другой карточке состояния работает без ветки Game. `WOUND_STATE_CODE` нет.

Эскиз сейчас держит `blood_clotting` на sibling `rule-blood-clotting`, а у
`wound` `mechanics: []`. Implementation-сессия переносит привязку на
карточку состояния (контент + маркер-хендлер с пустым `run()`, не
`runEvent`). Гейт «по всей ревизии есть rule-blood-clotting» не копировать.

Отклонён D3-B (каталожный гейт + отдельный детектор раны).

### D4 — закрыт, B (2026-10-12)

Тот же `endTurn`, после крови: состояния с `damage_over_time`, кроме строк
с sidecar раны. Урон через pipeline Game 30 (`putDamageSplit` / hooks).
T3-A: тик только если period пустой или `step` пустой/`turn`; иной step
— skip строки, не отказ команды. Нет `damage_type_code` — skip. После
выстрела decay только `fixed` и `dimensional`; `characteristic`/`check`
силу не меняют. Period > 1: счётчик `dotTurnsLeft` на строке, если его
ещё нет — завести; period 1 / пустой — тик каждый ход.

### D5 — закрыт, A (2026-10-12)

Долг ОД — JSON-карта на `game_battle` (как `pending_action_effects`), не
G17 `game_process`. `endBattle` сносит карту. Адрес состояний — `id`
строки, не индексы фронта. Continue / abort — отдельные команды в начале
хода следующего, не внутри `endTurn` уходящего. Перенос любого
не-реакции, **включая атаку**, — R3 (эта строка D5 «атаки не stretch»
устарела и снята).

### D6 — закрыт, A (2026-10-12)

Один commit/replay на `endTurn` одного текущего листа (персонаж или NPC):
clotting в памяти → Σ тик → кровь → проверки → DOT → refill ОД → курсор
→ чат. Конфликт версии — ноль частичной записи. Повтор ключа не тикает
повторно. Continue — другой ключ и другой tx.

### D7 — закрыт, A + формула владельца (2026-10-12)

Helper бросков. Воля — не «фишка удара»: при **любом** изменении
состояния истощения (`StateSpec::isDamageExhaustion()`), источник не
важен (удар, кровь, DOT). Уже без сознания — как сейчас close, не
дублировать. Чат — существующее вложение `roll`.

Увечье **раны** — в момент **получения** раны или истощения от close
удара или от DOT (T6). Тик шкалы крови эту формулу не кидает (R2), даже
если пол истощения вырос: там Воля (S2) и при пороге — увечье крови.
Формула при получении раны/Δ от удара или DOT:

```text
max(Δ истощения этого события, floor(сила этой раны / 2))
```
Нет новой раны (только Δ истощения) — DC = Δ. Тик крови как шкалы —
не эта формула (R2).

Сегодня PHP шлёт `max($delta, $wound)` без `/2`; helper close чинит `/2`.
Σ всех ран листа в DC не входит.

Увечье **кровопотери** — другой контур (R2), не эта формула. Эскиз
`2^(reserved−4)` отвергали как подмену формулы **раны**; для крови ≥ 40
она совпадает с `2^floor((кровь−40)/10)`.

### D8 — закрыт, A (2026-10-12)

В `patchState` разрешить ключ `clotting`. Сервер пишет **итоговое** целое
(старое + РУ, не выше силы). Клиент clotting не присылает. Тот же
allow-list: `dotTurnsLeft` (R6) и `value` (затухание DOT, пока сила > 0).
Клиент `value` не присылает. Game не шлёт `value` на sidecar раны.
T6: kind **`removeState`** `{ id }` — снять строку по id (сила после
decay ≤ 0). Это новая операция Character, не C11. Рану Game 31 этим
kind не чистит.

## Вопросы — закрыты 2026-10-12

### Q1 — да

NPC: тот же pipeline, CAS `npc.version`. Курсор D2 несёт `type+id`.

### Q2 — отказ вне боя

`endTurn` без открытого боя — `GAME_INVALID`. Это действие боя, не сессии.

### Q3 — бросать и на закрытых

Закрытая свёртыванием рана остаётся (не заживление). Свёртывание бросать;
тик 0; строку не удалять.

### Q4 — порядок эскиза

clotting → Σ тик → кровь (+ проверки) → DOT. Счёт в памяти одного apply.

### Q5 — бросок на рану + сумма крови

Существующий тип вложения `roll` close. Сообщение свёртывания на каждую
рану; кровь — суммарно.

### Q6 — закрыт, лист + refill в конце хода

ОД восстанавливаются **в конце хода уходящего** (после тиков, в том же
`endTurn`). «Доступно» для continue / защиты — текущее значение ресурса
на листе, не отдельный пул хода. Если к началу своего хода ОД уже ушли
на защиту — так и задумано (вынудили). Continue читает лист как
`woundAction`.

## Критерии приёмки backend (после закрытия D/Q)

Не выполненный прогон. PHPUnit в planning-сессии не запускать.

| Путь | Ожидание |
|---|---|
| Команда хода текущего | Сервер сам обходит строки текущего (D2); клиент не шлёт clotting/tick |
| Нет mechanic свёртывания | Поле `clotting` не меняется; дальше тик крови всё равно считается |
| Есть mechanic на карточке состояния строки | Проверка этой строки (включая internal); вклад +РУ, не выше силы; `patchState.clotting` |
| Тик | max(0, сила − min(сила, bandage+clotting)), при ненулевом `heldBy` закрытость не ниже 1; Σ по строкам текущего |
| Кровь | Прирост в состояние `isBloodLoss()`, не сумма сил; Δ≤0 не добавляет кровь. Истощение от шкалы крови — R2 |
| Тик без mechanic свёртывания | Σ по строкам с sidecar Game 31 (`bandage`/`clotting` на строке). Бросок свёртывания — только строки, у чьей карточки есть маркер (D3-A) |
| Зажим | Не меняет `clotting`/`bandage`; влияет только на тик этого хода |
| DOT | D4 + T3-A; x из `damage.kind`; тип из `damage_type_code`; увечье при ране/Δ истощения; сила ≤ 0 — `removeState` |
| Автопроверки close | DC увечья раны `max(Δ истощения удара, floor(сила раны удара / 2))` |
| Автопроверки `endTurn` | Кровь: R2. DOT: увечье при получении раны или Δ истощения, формула D7 |
| Долг ОД | Один на актора; F1; прочее и инвентарь при remaining = 0; continue/abort только на курсоре (T4-A) |
| Передача хода | Курсор сдвигается; refill ОД уходящего; сессия жива |
| NPC | Тот же pipeline и CAS `npc.version` |
| Вне боя | `endTurn` отказ |
| Replay | Тот же body не тикает кровь/clotting повторно |
| CAS | Конфликт версии листа или боя — ноль частичной записи |
| Leave / endBattle | Sidecar как Game 31; leave и выбывание из roster снимают слот долга (T4-A) |
| C11/G22 | Не закрыты |

## Files

Читать в implementation-сессии после D/Q. В этой planning-сессии не менять.

Уже есть:

- `Game/Service/GameStrikeConsequences.php`
- `Game/Service/GameWoundActions.php`
- `Game/Service/GamePendingActionCosts.php`
- `Game/Service/GameCharacterMemberships.php`
- `Game/Service/GameBattleMutator.php`
- `Game/Service/GameBattleInitiatives.php`
- `Game/Service/GameBattleOrder.php`
- `Game/Service/GameBattleCleanup.php`
- `Game/Service/GameDelivery.php`
- `Game/Service/GameDeliveryKeys.php`
- `Game/Service/GameDeliverySignal.php`
- `Game/Service/GameActionResourceResolver.php`
- `Game/Service/GameReplayTransaction.php`
- `Game/Service/GameStrikePortFactory.php`
- `Game/Service/GameWideStrikeCommit.php`
- `Rule/Dto/Spec/Ability/AbilityBase.php` (`getCombatAction`)
- `Rule/Dto/Spec/Ability/Component/ResourceComponent.php`
- `Character/Dto/CharacterResolvedRule.php` (`getMechanics`)
- `Character/Interface/Service/ICharacterActualMutations.php`
- `Game/Service/GameProcesses.php`
- `Game/Service/GameChecks.php`
- `Game/Service/GameCheckRoll.php`
- `Game/Service/GameStrikeSheetWrites.php`
- `Game/Table/GameBattleTable.php`
- `Game/Schema/GameBattleSchema.php`
- `Game/Setup/GameModuleSetup.php`
- `Game/Table/GameProcessTable.php`
- `Game/Table/GameWoundCommandTable.php`
- `Character/Service/CharacterActualStateRows.php`
- `Character/Service/CharacterActualMutations.php`
- `Character/tests/CharacterActualStateTest.php`
- `Character/Service/Resource/CharacterResourceStorage.php`
- `Character/Service/Resource/CharacterResourceLimits.php`
- `Game/tests/GameMysqlFixture.php`
- `Game/Service/GameHttp.php` / `module.config.php` (новые action)
- `Mechanic/Service/MechanicPortFactory.php`
- `Rule/Dto/Spec/StateSpec.php`
- `Rule/Dto/Spec/ResourceSpec.php`
- `Rule/Spec/RootSpecs.php`
- `Rule/Dto/Spec/State/DamageOverTimeEffect.php`
- `Game/module.config.php`
- `Game/Service/GameStrikes.php` (`spendAttacker` сейчас ОД+инвентарь на declare)

Эскиз фронта, не переносить как канон:

- `draft-front_1.2ds/.../WoundInstanceService.ts`
- `draft-front_1.2ds/.../BloodLossService.ts`
- `draft-front_1.2ds/.../EndOfTurnDotsService.ts`
- `draft-front_1.2ds/.../DotTickMathService.ts`
- `draft-front_1.2ds/.../CommittedActionService.ts`
- `draft-front_1.2ds/.../Dto/CommittedActionSession.ts`

## Dependency order

```text
Game 28 CLOSED
→ Game 29–31 APPROVED (принятый backend scope)
→ D1–D8, Q1–Q6, R1–R6, S1–S4, T1–T6 закрыты
→ одна implementation-сессия Game 32
→ Game 33 E2E (не этот план)
```

G22 и C11 в этот порядок не входят.

## Review decisions (2026-10-12)

### R1 — kind A, поиск F1 (2026-10-12)

Запись refill — новый kind Character (current → эффективный лимит).
Ресурс хода — **новый булев** на `ResourceSpec` (смысл «пул, который
восстанавливается в конце хода»), ровно одна живая карточка, иначе
`GAME_INVALID`. Не `isAutoAdd()` (это «положить на лист»). Не литерал
`action-points`. `check_token` остаётся жетоном проверки, другим флагом.
Имя JSON-ключа флажка — на реализацию рядом с `auto_add` / `check_token`.
`SpecShape::bool` при отсутствии ключа уже даёт `false`: старые карточки
ресурса без поля не ломаются (`RootSpecs::resource`).

### R2 — закрыт, логика владельца

Кровь и рана — разные контуры.

- Получение раны (close): увечье `max(Δ истощения удара, floor(сила/2))`.
  Тик крови это увечье **не** кидает.
- Кровопотеря: состояние `isBloodLoss()` += тик. Истощение от крови =
  `floor(кровь / 10)`, не снимается ниже этого пола (то же число, что
  эскиз `reserved`).
- Увечье от крови: только при крови ≥ 40, DC =
  `2 ^ floor((кровь − 40) / 10)`. Это то же, что эскиз
  `2^(floor(кровь/10) − 4)` при ≥ 40; ранее отвергали её как формулу
  **раны**, не как формулу **крови**.

### R3 — закрыт, порядок + ammo A (2026-10-12)

Для любого action, **кроме реакции**: декларация (удар `1→1` и `1→N`:
цель(и), профиль, улучшения, `chosenAmounts`) → списание с переносом
по ходам → когда remaining = 0, эффект. Реакции не переносятся.
Эскиз `canStretch` атаки не копировать.

PHP сейчас списывает ОД в том же `declareStrike`, что открывает ожидание
реакции. Цель: open/реакция только после полной оплаты ОД.

**R3-ammo A.** Предмет и боеприпас Game 29 жрутся в **старте эффекта**
(remaining = 0), не с первого куска ОД. Abort после частичной оплаты
инвентарь не трогает.

### R4 — закрыт, A+A

`game.continueAction` / `game.abortAction`. `endTurn` и эти команды
пишут `GameDeliverySignal`, как strike, не как `woundAction`. Не
`source = 'strike'`: `catchUp` берёт id из strike-журнала.
Один новый журнал команд (`reserve`/`complete`, unique `game_id` +
ключ, как `GameWoundCommandTable`) на `endTurn` / `continueAction` /
`abortAction`; `source` ≤ 16, например `turn`; `sourceId` = id строки
журнала; keys пустые, как `fromStrike`; `GameDelivery::catchUp` и
`GameBattleCleanup` / `GameMysqlFixture` знают таблицу.

### R5 — закрыт, C

После `setBattleRoster` найти прежнюю пару `type+id` в новом порядке;
нет в составе — `active_index = 0`.

### R6 — закрыт, A+A

Payload маркера свёртывания: `check_code` (живая карточка, не литерал в
Game). Счётчик DOT на строке: ключ `dotTurnsLeft`, пустить в accept/patch.

## Review S* (2026-10-12)

### S1 — закрыт, A

Пока висит долг, выполняется это действие. Одновременно одно действие
на актора. Вторая декларация, пока remaining > 0 — отказ.

### S2 — закрыт

Воля при любом Δ истощения, не при «ударе». Источник (удар, кровь, DOT)
не важен. Это свойство изменения состояния истощения. Close уже зовёт
Волю при `quotient !== 0`; `endTurn` — при Δ от крови/DOT. Без сознания
не дублировать.

### S3 — закрыт, A

Увечье крови: этот ход увеличил кровь **и** итог ≥ 40. Тик 0 — не кидать.

### S4 — закрыт, P1 (2026-10-12)

P2 снят: второй булев «это растягиваемые ОД» дублировал бы F1. Частично
платится `ResourceComponent`, чей код — единственная живая карточка с
флагом F1 (пул, который refill в конце хода). Прочие компоненты и
инвентарь — в старте эффекта (R3-ammo A). Не `isAutoAdd`, не литерал
`action-points`. Нет флага — этот расход не растягивается (отказ, если
не хватает целиком, как сейчас).

`SpecShape::bool` без ключа F1 → `false`.

### Запись крови и пола истощения (фактура Character, не развилка)

- Кровь: единственная живая `StateSpec::isBloodLoss()` (как
  `singleFlag` у увечья); прирост тика — `putState` (fold `sum`/`max`).
- Пол истощения `floor(кровь/10)`: Δ = max(0, пол − текущее). Δ = 0 —
  не писать. Δ > 0: `putState` на единственную `isDamageExhaustion()`,
  если aggregation не `independent` с уже лежащей строкой. Иначе
  `putDamageSplit` с `remainder` = текущий остаток листа и
  `quotient` = Δ (`splitOne` **заменяет** remainder; чужой remainder
  сотрёт Hurt). Новый kind нет.
- После Δ истощения от пола крови — Воля (S2). Формулу увечья раны (D7)
  не кидать: это не получение раны и не DOT, это R2.

### Результат на существующих тестах удара

При T2, если `declareStrike` / `declareWideStrike` при нехватке F1
открывают долг вместо отказа: сценарии
`Game strike attacker resource is insufficient` /
wide-аналог меняют ожидание. При полной оплате F1 в том же запросе
поведение open+инвентарь (remaining = 0) должно совпасть с сегодняшним.
DC увечья close: `max($delta, $wound)` → `max($delta, intdiv($wound, 2))`
ломает числовые assert Game 30, их чинить в той же сессии.

### Открытый удар

`GameStrikes::open` / `GameWideStrikes` отказывают, если `findOpen`
уже не null. T1 закрыт: `endTurn` в этом окне — отказ.

## Review T* (plan-review 2026-10-12)

### T1 — закрыт, A (2026-10-12)

Отказ `endTurn`, когда ресурсы **уже уплачены** и нужно **отыгрывать
эффект**: в бою есть open `1→1` или `1→N` (`findOpen`). Тик и сдвиг
хода при незакрытой защите не делаются.

Долг с remaining > 0 (ещё не доплатили F1, open нет, инвентарь не
съеден) **не** блокирует `endTurn`: это как раз перенос на следующий
ход. Когда последний кусок F1 закрыт, эффект стартует в
`continueAction` / том же `declare*`, что добил remaining, не внутри
`endTurn`.

### T2 — закрыт, A на эту сессию (2026-10-12)

Канон продукта (R3) не меняется: растягивается **любое** действие,
кроме реакции (`combat_action` dodge/block и т.п.).

В **Game 32** вход долга — только уже существующие `declareStrike` и
`declareWideStrike`: нехватка F1 → карта долга; open и инвентарь когда
remaining = 0. `woundAction` — полная оплата и сразу patch, как Game 31.
Прочий `AbilityActionSpec` в этом заходе HTTP не получает
(`game.declareAction` не заводится). Это урезание сессии, не отмена R3:
общий declare не-реакции — отдельный последующий план, когда появится
исполнитель эффекта.

### T3 — закрыт, A (2026-10-12)

Нужно выбрать два связанных правила. Без них D4 («после крови обойти
состояния с `damage_over_time`») нельзя закодировать.

**Что есть в PHP.** У эффекта состояния `DamageOverTimeEffect`
(`StateEffects::damage`): вид урона, фиксированное `amount`, опциональный
`damage_type_code`, период `periodicity.value` + `periodicity.step`
(строка, без enum в spec), опциональный `decay`. Виды decay в том же
парсере: `fixed`, `dimensional`, `characteristic`, `check`. Game этот
эффект не исполняет. Счётчика на строке листа нет, пока D8 не пустит
`dotTurnsLeft` в `patchState`.

D4 уже сказал: рана с sidecar в DOT не стреляет; урон — pipeline
Game 30 (`putDamageSplit` / hooks); «магический decay» в exclusions
Game 32. Period 1 / пустой — каждый ход; period > 1 — счётчик на строке.

**Что делает эскиз** (`DotTickMathService`, не канон PHP).
`turnPeriod`: тик **только** если `step === 'turn'` и value ≥ 1; иначе
строка в этом ходе **пропускается** (не ошибка). Нет periodicity у
горения — подставляют `{value: 1, step: 'turn'}`. Счётчик: взять
`dotTurnsLeft` или period; минус 1; при ≤ 0 — выстрел и сброс счётчика
на period. После выстрела `decayStrength`: `fixed` вычитает из base;
`dimensional` вычитает пару; любой другой kind — силу не трогают (не
ошибка). Яд на эскизе живёт во вложенном `state.poison`; в PHP Game 31
яда-sidecar нет — отравленная строка, если появится, это обычное
состояние с эффектом DOT, не второй контур.

**Зачем развилка.** `periodicity.step` в PHP — свободная строка. Кроме
`turn` на эскизе бывают другие шаги (ход vs раунд vs сутки). Если
сервер будет тикать любой step как ход, суточное/магическое затухание
уедет в `endTurn`. Если сервер будет бросать `GAME_INVALID` на
незнакомый step, лист с карточкой «раз в раунд» сломает конец хода
целиком (все clotting/кровь тоже откатится — D6 один commit). Skip
строки оставляет кровь и остальные DOT.

Decay `characteristic` / `check` — как раз то, что план назвал
магическим exclusion: формула от характеристики или проверки, не
вычитание числа. Их тик в Game 32 означал бы новый контур броска
внутри конца хода.

Варианты (шаг и decay выбираются вместе, это одна политика «что
считает боевой конец хода»):

- **A.** Как эскиз + exclusion: пустой period и `step` пустой/`turn` —
  счётчик ходов. Иной step — эту строку skip, `endTurn` жив. После
  выстрела decay только `fixed` и `dimensional`; `characteristic` и
  `check` силу не меняют. Нет `damage_type_code` — skip строки (эскиз
  горения так делает).
- **B.** Любой step трактовать как ход `endTurn`. Тогда «раз в раунд»
  станет «раз в ход», пока нет отдельного счётчика раунда.
- **C.** Неизвестный step или decay `characteristic`/`check` —
  `GAME_INVALID` на всю команду.

**Выбрано A.** B и C отклонены. Тик «раз в раунд» в этот план не входит.
Критерий строки — эффект `damage_over_time` на живой карточке, минус
sidecar раны (D4), не литералы эскиза.

### T4 — закрыт, A (2026-10-12)

Речь не про формулу ОД, а про **жизненный цикл слота** на бое. Без
этого `continueAction` / `abortAction` / `leave` / отказ инвентаря
пишутся вразнобой.

**Где лежит слот.** D5: JSON-карта на `game_battle`, ключ как у
`pending_action_effects` (`character:id` / `npc:id`), не G17 process.
Один слот на актора (S1). `endBattle` удаляет строку боя — карта
исчезает вместе с ней. Это уже закрыто.

**Что есть в PHP сегодня.** `leave` в одной транзакции с `saveLeft`
снимает только `heldBy` с ран; pending-карту и process чужого актора
не чистит. NPC через `leave` не выходит. `setBattleRoster` (R5) ищет
прежнюю пару `type+id` в новом порядке; слота долга ещё нет. Открытый
удар — отдельная таблица, не эта карта.

**Что делает эскиз.** `CommittedActionSession` висит на entity key;
UI abort есть и с карточки боя (`CombatCardPanel.abortCommittedAction`),
не только из диалога «твой ход». Continue — диалог на треке инициативы
в начале хода. Инвентарь эскиз у атаки не растягивал (`canStretch`
атаки = false); у нас R3-ammo A: предмет жрётся только при remaining = 0.

Три отдельных стыка, их нельзя оставить «как получится».

**1. Чей ход.** D5: continue / abort — команды **начала хода** этого
актора, не кусок `endTurn` уходящего. Курсор D2 тогда указывает на
него. Если разрешить abort, пока ходит другой: игрок срывает недоплаченную
атаку в чужой ход, слот пустеет, S1 снова свободен — можно тут же
декларировать другое, хотя «выполняется это действие, пока висит долг».
Если запретить abort с чужого хода — пока очередь не дошла, выход только
через leave / endBattle.

**2. Leave и выбывание из состава.** Персонаж ушёл: ОД уже сняты с
листа, слот на бое остаётся на мёртвый ключ. `endTurn` этого ключа больше
не будет. Аналог зажима: Game 31 на leave снимает `heldBy`, потому что
держателя нет. Pending-срезы следующего действия Game 31 **не** снимает —
другой смысл (скидка, не долг исполнения). Roster (R5): актора нет в
новом порядке → курсор на `0`; слот долга выбывшего снова сирота.

**3. Последний платёж, эффект не стартовал.** Remaining стал 0, сервер
должен открыть удар и съесть боеприпас (R3-ammo A). Боеприпаса нет /
предмет сняли между ходами: либо отказать в continue и **оставить**
долг (можно экипировать и повторить, S1 всё ещё занят), либо снести слот
как abort (уже уплаченные ОД сгорели, действие исчезло). Это не про
нехватку F1: F1 как раз добит.

Варианты (политика на все три стыка):

- **A.** Continue и abort — только если курсор на этом акторе (свой
  ход; GM — тот же курсор, чужой лист). `leave` этого character id
  снимает его слот (как `heldBy`). Выбывание из roster — тот же strip
  слота. Отказ старта эффекта (нет боеприпаса и т.п.) — `GAME_INVALID`,
  слот и remaining = 0 остаются, пока не abort на своём ходе или не
  получится эффект.
- **B.** Abort можно не в свой ход (владелец листа / GM), как кнопка на
  карточке эскиза. Leave и roster слот не трогают до `endBattle`.
- **C.** Как A для хода и leave, но неудачный старт эффекта сам сносит
  слот (уплаченные ОД сгорают, S1 свободен).

**Выбрано A.** B и C отклонены. Abort в чужой ход не делается. Сирота
после leave не висит. Отказ инвентаря при remaining = 0 слот не сносит.

### Очевидное с фактуры (не развилка продукта)

- **`continueAction` не принимает «сколько ОД».** Сервер снимает
  `min(current F1, remaining)` — как эскиз `pay`, в духе D1 (клиент не
  шлёт итог). Нулевой current — no-op слота, не abort.
- **Тело долга.** В слоте: remaining, total, `actionRuleCode`, вид
  `1→1`/`1→N`, уже разобранное body declare (то, что `GameStrikeBody`
  кладёт в `attack`). Иначе remaining = 0 нечем открыть удар. Адреса
  состояний — `id` строки (D5), не индексы эскиза.
- **`leave` без версии боя.** Как `heldBy`: в транзакции `saveLeft`
  снять слот во **всех** открытых боях сессии (`findIdsBySession`), без
  `expectedBattleVersion`. HTTP leave боя не знает. Гонка с `endTurn`
  того же класса, что pending. Несколько боёв сессии — readiness.
- **`declareStrike` без инициативы.** Фикстуры удара не зовут
  `rollInitiative`. T5-B: курсор на declare не смотреть; тесты 28–31 из‑за
  очереди не ломать.
- **DOT не проходит `injury()` попадания.** x из `damage.kind` →
  `splitInjury` → hooks. T6: Воля при Δ; увечье при ране/Δ; blunt нет.
  x брать **до** decay. Один бросок увечья на один выстрел строки (как
  close: `max(Δ, floor(рана/2))`), не два. Сила ≤ 0: `removeState`.
- **`removeState` в Character.** Сейчас `applyOperation` неизвестный kind
  бросает; `append` не снимает строки. Нужны `accept` + ветка в `append`
  по `id`, как `patchState`. `GameStrikeSheetWrites` уже прокидывает
  effect operations списком — новый kind туда входит. Тесты:
  `CharacterActualStateTest`, NPC `applyToDocument`.
- **`patchState.value`.** Форма как у `putState` (int или `{base,size}`
  по `value_type` карточки). `patchOne` сегодня сливает только sidecar —
  писать `value` явно. На sidecar раны Game не шлёт.

### T5 — закрыт, B (2026-10-12)

Уточнение к фразе «сервер не знает, чей ход»: это про **сегодняшний**
PHP, до Game 32. Команды передачи хода нет. `rollInitiative` пишет
только список `turn_order`. Клиент сам рисует трек и сам решает, кому
кнопка «ход». Сервер ход **никому не передаёт** — доверять фронту тут
нечего, нечего передавать.

Game 32 как раз заводит курсор (`active_index` + `round`). `endTurn`
сам берёт текущего, тикает его лист и двигает индекс. Это не доверие к
клиенту. Continue/abort (T4) тоже смотрят курсор. Реакции уже чужой
ход: `resolveStrike` отвечает на open удар, не на очередь.

**Выбрано B:** объявить удар (и долг) можно не в свой ход; стол
полагается на игроков. Иногда по столу «нельзя» — это клиент/договор,
сервер не режет. A (только свой ход, кроме реакций) отложен: это было
исходное желание, в эту сессию не входит.

### T6 — закрыт (2026-10-12): спека x/тип; увечье на получение; ноль — удалить

**`amount` — не число экземпляров.** В PHP и на эскизе у эффекта
`damage_over_time` поле `damage` — **откуда взять урон тика**:

- `kind: 'value'` — x = `value` этой строки листа (то, что вы назвали
  входящим параметром). `amount` в парсере тогда 0 и не читается.
- `kind: 'fixed'` + `amount` — x = константа **карточки**, одинаковая
  для всех строк этого состояния. Это не «сколько копий на листе».
  Копии — `aggregation: independent` (несколько строк) или `sum`
  (одна сложенная).

Горение в эскизе: `damage: { kind: 'value' }`. Яд-карточка на эскизе
живёт отдельно; в PHP Game 31 яда-sidecar нет.

**Тип урона** — не флаг `StateSpec::isBurning()` (это ярлык карточки,
как `isBloodLoss`). Код типа лежит на том же эффекте:
`damage_type_code` (у горения эскиза — `'fire'`). По этому коду Game
как на close достаёт mechanic типа: режущий → рана, иначе remainder +
истощение, плюс stun/shock. Нет кода — T3-A, строку skip. Флаг
`burning` сам по себе урон не задаёт.

Дальше без второго контура попадания: x как `DimensionalNumber`
(`value` пара или `{base: n, size: 0}` / fixed amount) →
`splitInjury` → операции листа. `injury()` удара (× success − soak)
не звать: броска нет.

**Выбрано (2026-10-12).** Источник раны/истощения не важен: увечье
триггерится на **получение**. DOT, который положил рану или Δ
истощения, кидает ту же DC, что close. Тупой нокаут не звать (нет
success попадания). Воля — S2.

Сила после decay ≤ 0 — **удалить** строку. Kind Character
`removeState` `{ kind, id }`, срез по id как `patchState`. Пока сила
> 0 — `patchState.value`. Тесты Character: accept + apply; Game не
удаляет рану Game 31 этим kind.

## Следующий шаг

Game 33 — отдельный этап (`TODO`, не `IMPLEMENTED`): frontend → PHP → UI
E2E. Эта documentation-only правка код не меняет.
