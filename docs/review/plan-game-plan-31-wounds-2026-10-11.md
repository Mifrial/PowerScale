# Implementation plan: `game-plan-31` — wounds

Дата inventory: 2026-10-11. Решения D1–D4 и Q1–Q9 зафиксированы 2026-10-12.  
Режим documentation-only closure: 2026-10-12. Production-код и тесты не
изменялись и не запускались.

## Verdict

**APPROVED FOR THE ACCEPTED BACKEND SCOPE; NOT A PROJECT-WIDE APPROVAL.**

Backend Game 31 принят для scope ниже. Прежний verdict
`READY FOR ONE GAME-31 IMPLEMENTATION SESSION` и контракт далее в файле
сохранены как decision trace и не являются текущим статусом.

## Final closure record

- **Backend Game 31 — APPROVED** для принятого scope: уникальный `id`
  строки `sheet.states`, sidecar раны (`bandage`, `clotting: 0`,
  `internal`, `aided`, `heldBy`), команды `game.woundAction` (зажать /
  отпустить / перевязать), дискриминатор `next_action_cost`, колонка
  `pending_action_effects` на открытом бою и снятие `heldBy` на `leave`.
  Character и NPC идут одним списком операций; CAS и replay в той же
  границе, что close Game 28–30.
- **Exclusions.** DOT, tick кровотечения и прибавка `clotting` (Game 32);
  frontend → PHP → UI E2E (Game 33); закрытие C11 и G22; константа
  `WOUND_STATE_CODE` и формулы `tick` фронта в PHP. `putState` не
  объявляется kind C11.
- **Evidence.** В этой documentation-only сессии PHPUnit, MySQL и frontend
  не запускались.
- **Следующий этап.** Game 32 остаётся отдельным этапом со статусом `TODO`
  и не переводится в `IMPLEMENTED`.
- Статусный канон — [`../tr/game-plan-31.md`](../tr/game-plan-31.md).
  Очередь — [`../tr/game-roadmap.md`](../tr/game-roadmap.md).

Это закрытие backend принятого scope, не project-wide approval и не
подтверждение frontend → PHP → delivery E2E или deployment/live
verification.

## Historical contract verdict

**READY FOR ONE GAME-31 IMPLEMENTATION SESSION** — historical trace.

D1–D4 и Q1–Q9 закрыты 2026-10-12. Q9: ожидание — карта эффектов на открытом бою, как `pendingActionEffects` на фронте. Конец боя её забирает. Это разрешение одной backend-сессии, не смена статусов Game 28 (`CLOSED`), Game 29 и Game 30 (`APPROVED` для принятого backend scope).

## Цель roadmap

Экземпляры ран и sidecar (`bandage`, `clotting`, `internal`, `aided`), actions зажать и перевязать, `heldBy`, CAS и запись для персонажа и NPC.

Frontend `CharacterWound`, `WoundInstanceService` и `WoundActionService` — inventory эскиза. PHP-каноном они не являются.

## Current inventory

### Что Game 30 уже пишет как рана-state

Close `1 → 1` и `1 → N` остаётся в одной transaction/replay boundary Game 28/29. Immediate consequences — `GameStrikeConsequences::effects`.

Режущий путь: mechanic `IDamageAsWounds` (`damage_as_wounds_by_size`) на живом `damage_type`. Ветки `if cutting` нет. Сила раны — частное `injury / (стойкость со size − 2)`. При силе `> 0` в список операций цели кладётся:

```text
kind: putState
stateRuleCode: payload.stateRuleCode этой mechanic
value: int
```

Обычный урон по-прежнему идёт `putDamageSplit` и в этот `putState` не попадает. `exhaustion_to_wound` — другой hook: он умножает quotient истощения и пишет свой `stateRuleCode`, не sidecar раны.

`CharacterActualStateRows::appendOne` применяет `StateSpec.aggregation`:

| aggregation | Запись |
|---|---|
| `independent` | новая строка `{ stateRuleCode, value }` в конец `sheet.states` |
| `sum` / `max` | одна строка кода, значение складывается или берётся max |
| иное | `CHARACTER_INVALID` |

`accept` принимает у `putState` только ключи `kind`, `stateRuleCode` и необязательный `value`. Строка состояния — только код и значение. Ключей `id`, `wound`, `bandage`, `clotting`, `internal`, `aided`, `heldBy` нет. `folded` при `sum`/`max` пересобирает строку из кода и значения и лежащие лишние ключи не переносит.

NPC идёт тем же списком операций через `GameStrikeSheetWrites` и CAS `npc.version`. Второго листа нет.

Чат close (`GameStrikeConsequences::post`) пишет цепочку attack: действие, нарратив, `roll`, `attack-calc`, при Воле ещё `roll` и текст, при увечье `roll`, текст и `injury-details`. Сообщений зажима и перевязки нет. `wound` в узком command result — целое этой силы, не экземпляр.

`GameCharacterDiff` при сравнении листа снимает ключ `heldBy` внутри объекта `wound`, если такой объект есть. Сам PHP его не пишет. Тест `GameCharacterDiffTest` фиксирует это снятие.

### Чего нет

- Идентификатор экземпляра раны. Повтор `independent` различим только индексом массива `sheet.states`. Индекс после вставки другой раны сдвигается.
- Sidecar `bandage` / `clotting` / `internal` / `aided`.
- `heldBy` в battle state и на листе.
- Команды зажать и перевязать, их ОД, replay и CAS.
- Очистка зажима на `endBattle`.
- Запись sidecar в чат.

### Frontend inventory, не PHP-канон

`CharacterWound`: `bandage`, `clotting`, `internal`, `aided`, `heldBy`. `WoundInstanceService` вешает этот объект на `state.wound`, силу держит в `value`, код сравнивает с константой `WOUND_STATE_CODE`. Зажим — `heldBy` на том же объекте. `stripHolds` обнуляет только `heldBy`. `closure` считает `min(сила, bandage + clotting)`, а при активном зажиме не ниже 1. `tick` = сила − закрытость — это формула конца хода, Game 32.

`WoundActionService` списывает ОД через `setCombatResource`, перевязку пишет `replaceCombatState` по индексу массива, зажим кладёт в combat overlay. Этого HTTP в PHP Game нет.

`docs/specs/character-actual-session-source-plan.md` хранил `heldBy` в battle state. Принятый D2 этого не делает: зажим — одно поле строки листа и переживает бой.

### Порты, которые можно переиспользовать

| Порт | Роль для Game 31 |
|---|---|
| `ICharacterActualMutations` | CAS actual. Каталог kind сейчас: inventory, money, `putState`, `putDamageSplit`, `replaceDecline`. Sidecar в эти формы не входит |
| `CharacterActualStateRows` | Единственный писатель `sheet.states`. Aggregation уже закрыта Game 30 |
| `GameStrikeSheetWrites` | Один write Character/NPC в одной transaction. Список операций расширяется, второй write на ту же версию в том же close запрещён Game 30 |
| `GameNpcRepository` / `npc.version` | NPC без второго листа |
| `GameReplayTransaction` | Повтор того же body возвращает сохранённый result и `work()` не вызывает |
| `GameCharacterDiff` | Уже выкидывает `wound.heldBy` из semantic diff, если ключ лежит в листе |
| `IChats::send` | Тот же `gameChatId`, автор `actorUserId`, thread. Новые типы вложений не выдуманы Game 30 |
| `game.endBattle` | Сессию не гасит. Зажим, перевязку и clotting не пишет |

Новый лист и прямой SQL в `sheet` не заводятся. `putState` по-прежнему не является kind C11. C11 и G22 эта сессия не закрывает.

### Границы 28–30, которые нельзя ломать

- Формула `injury`, слои resistance, penetration только в defense, reliability, block gate, `{0|-1}` auto-fail, resource/AP eligibility Game 28.
- Identity атаки Game 29: action, inventory instance, rule code, profile, ammunition. Клиентские damage, penetration и hit rating не принимаются. Cover, range, falloff и координаты не становятся отказом или модификатором.
- Pipeline Game 30: ordinary injury → `putDamageSplit`; режущий урон в рану-state, не в remainder; stun/shock/knockout; проверки Воли и увечья; чат и delivery в той же транзакции close. Сила раны и триггер увечья «рана этого удара» остаются числом, которое уже пишет `effects`.
- Character не импортирует Game. Rule не собирает слои цели и не исполняет боевые последствия. Game не создаёт второй лист и не выбирает поле листа в обход kind.
- Replay сверяет тело. Конфликт версии не оставляет частичной записи листа, чата и battle version.
- Статусные файлы `game-plan-28.md`, `game-plan-29.md`, `game-plan-30.md` и очередь в `game-roadmap.md` эта сессия планирования не меняет.

## Authoritative ownership

| Concern | Owner сегодня | Game 31 не забирает молча |
|---|---|---|
| Число injury | Game `GameStrikeAmounts` | Формулу не менять |
| Сила раны этого удара | Game `woundStrength` + `putState` | Код состояния берётся из payload mechanic, не из константы фронта |
| Aggregation строк | Character `StateSpec.aggregation` | Game числа не суммирует |
| Sidecar persistent | Не написан | Строка экземпляра D1. `bandage`, `clotting`, `internal`, `aided` переживают бой |
| `heldBy` | Не написан. Diff сейчас его выкидывает | Одно поле той же строки, D2. Бой его не снимает |
| Clotting как число вклада | Не написан | Поле `0` создаёт Game 31. Прибавку пишет Game 32 по mechanic карточки |
| Actions зажать/перевязать | Нет PHP-команды | Game. Адрес — id строки |
| ОД действия | Существующий resource write Game | Отдельный ресурс не заводить |
| Чат действия | Нет | D4, вариант B |
| DOT / конец хода | Game 32 | Тик, bleeding и автопроверка свёртывания вне сессии |
| E2E UI | Game 33 | Вне сессии |

## Decisions

D1–D4 закрыты. Открытых обязательных решений нет.

### D1. Экземпляр — закрыт, вариант A

Выбор 2026-10-12: **A.** Запись состояния — строка `sheet.states`. Отдельной коллекции и адреса по индексу массива нет.

Id есть у каждой строки, не только у раны. Его выдаёт Character в `appendOne` в момент появления строки. Game id не придумывает, клиент его не присылает. Правка и удаление, в том числе ручное в одной ревизии, адресуют id. `stateRuleCode` — карточка, не адрес. Ветки «по id, иначе по коду» нет.

`independent` — каждый `putState` даёт новую строку и новый id. `sum` и `max` держат одну строку кода: первый раз выдаётся id, дальше меняется `value` того же id. Для кода раны `sum` и `max` — отказ записи: свёртка не оставляет отдельные экземпляры и стирает лишние ключи строки.

Отклонены B (вторая коллекция рядом со списком состояний) и C (индекс массива). Kind операции, которым пишется id и sidecar, эта фиксация не выбирает.

### D2. Зажим — закрыт

Выбор 2026-10-12. `heldBy`, `bandage` и `clotting` — не отдельные состояния и не колонки таблицы. Это поля строки экземпляра в `sheet.states`.

`heldBy` — одно значение на строку: кто держит, либо пусто. Старт боя и `endBattle` его не пишут. Перевязка и clotting тем же границам не подчищаются. Повторное «зажать» ту же строку — отказ, не второй зажим. Занятые руки считаются по текущим строкам листа, отдельного счётчика на бой нет. ОД за уже стоящий зажим при входе в бой не списываются.

Снимает зажим действие «отпустил». Если держащего больше нет в игре, зажим снимается, рана и перевязка остаются.

`GameCharacterDiff::normalize` снимает `heldBy` только у вложенного объекта с ключом `wound`. Поля на самой строке состояния этот код не трогает. Принятая форма D2 — поля строки, не объект `wound`. Существующее снятие не удалять: оно про старую вложенную форму и до новой строки не доходит. Новый `heldBy` на строке в diff уже попадает.

Отклонены хранение только в battle state, обнуление на `endBattle` и запись зажима в два места сразу. Иначе цикл старта и конца боя либо теряет дорогу к помощи с зажатой раной, либо складывает зажимы.

### D3. `clotting` — закрыт

Выбор 2026-10-12. Game 31 создаёт на экземпляре раны `clotting: 0` вместе с остальным sidecar и больше его не меняет. Прибавку пишет Game 32 внутри конца хода.

Конец хода не ищет карточку «рана» и не принимает clotting от клиента. Команда хода — «ход закончен». Сервер сам берёт текущего персонажа, обходит его строки и читает mechanic карточки. Есть mechanic свёртывания — проверка и вклад в `clotting` этой строки, не выше силы. Нет mechanic в ревизии — шаг пустой. Та же mechanic на другой карточке работает без новой ветки Game.

Отклонены отложенное появление ключа в Game 32 и проведение проверки уже в Game 31. `bandage`, `internal` и `aided` пишет действие перевязки, не тик.

### D4. JSON и чат actions — закрыт, вариант B

Game 30 зафиксировал цепочку close удара и thread `attack`. Actions зажима и перевязки — отдельные команды, не шаг `effects`.

Выбор 2026-10-12: **B.** Команда пишет узкий command result (цель, id экземпляра, операция, версии) и одно текстовое сообщение в `gameChatId`. Автор — `actorUserId`. Новый тип вложения не заводится. Повтор того же body не шлёт второе сообщение.

Отклонённые ветки: A (только result, в ленте боя действия нет) и C (новый payload чата).

Клиент не присылает силу раны, итог перевязки и `heldBy` как уже принятый факт. Сервер считает вклад и отказ. Цепочка вложений close удара Game 30 не меняется.

## Exact contract

D1–D4 закрыты. Сессия сохраняет всё, что ниже:

1. Сила раны на close считается как сейчас: `woundStrength` и `stateRuleCode` из payload `IDamageAsWounds`. Формула injury не меняется. Режущая операция кроме `kind`, `stateRuleCode` и `value` несёт пустой sidecar. `CharacterActualStateRows::accept` сейчас пускает у `putState` только `kind`, `stateRuleCode` и необязательный `value`; сессия расширяет эту форму ровно ключами sidecar, чужой ключ по-прежнему отказ.
2. `putDamageSplit`, Воля, blunt unconscious и увечье Game 30 остаются в том же `effects` и в том же одном `apply` / `replaceVersion`.
3. Character и NPC идут одним списком операций. Ветки `if npc` нет.
4. Повтор того же body команды не применяет зажим или перевязку второй раз.
5. Конфликт `actual_version` или `npc.version` не оставляет частичной записи строки.
6. `endBattle` сессию не гасит и не меняет рану, sidecar и `heldBy`.
7. `putState` не объявляется kind C11. G22 не начинается.
8. Тик кровотечения, проверка свёртывания и передача хода не исполняются. Поле `clotting` на новом экземпляре равно 0.
9. Id строки выдаёт `appendOne`. Правка и удаление адресуют id. `sum`/`max` у кода раны — отказ.
10. `heldBy` пуст, пока рану не зажали. Форма — `{ kind: "character"|"npc", id: int }`. Повторный зажим той же строки — отказ. Внутреннюю (`internal: true`) зажать нельзя. Свободные руки = число рук с `RaceSpec` − `min` у `occupy_hands` надетого − уже зажатые этим держателем внешние раны. `choices.raceCode` указывает на `species` — число берётся с `parent_race_code`, потому что поля нет на `SpeciesSpec`. Нет расы или поля — 2. Строка инвентаря хранит только код, `equipped` и количество, выбранной занятости нет: вычитается `min`. Не хватает руки — отказ. «Отпустил» снимает только `heldBy`. `leave` сейчас делает только `saveLeft`. Снятие идёт по листам раненых этой игры, где `kind` — `character` и `id` — уходящий персонаж. Лист уходящего сам по себе не источник зажима. NPC через `leave` не выходит.
11. `folded`, `putRow` и новая строка `declineOne` собирают запись заново без id. Уже лежащие JSON-строки id не имеют, отдельной SQL-миграции нет: `states` — ключ `sheet`. Проход, который пересобирает список, сохраняет лежащий id, а строке без id выдаёт новый. Иначе следующий `putDamageSplit` или `replaceDecline` сотрёт id.
12. Зажим и перевязка пишут узкий result и одно текстовое сообщение в `gameChatId`, без нового типа вложения.
13. Пустой sidecar на новую строку раны кладёт режущий путь Game ключами своей операции: `bandage: 0`, `clotting: 0`, `internal: false`, `aided: false`, `heldBy: null`. `appendOne` сохраняет эти ключи на новой `independent`-строке и не выводит рану из `StateSpec`. Остальным строкам sidecar не выдаёт.
14. Перевязка прибавляет к `bandage` 2, не выше силы. Пока `aided` ложно, `bandage` не выше 4. Способность ищется в `choices.abilities` как `CharacterAbilityChoice` с этим `ruleCode` и `level >= 1`. Внутреннюю не перевязывать. Команда называет карточку действия. Код ресурса и цена — её `ResourceComponent`, все такие компоненты списываются. `isAutoAdd()` ресурс не выбирает. `GameActionResourceResolver::resolve` для этого не вызывать: он требует id предмета. Нет resource-компонента у названной карточки — отказ. Срез цены дают гранты купленных навыков, чей уровень блока не выше `CharacterAbilityChoice.level`: каждый называет действие и сколько снять с названного ресурса. Срез безусловный. Game не зашивает коды способностей. `AbilityGrants::branch` незнакомый `type` отвергает. `ResourceGrant` поднимает лимит ресурса и срезом не является. Сессия добавляет вид среза. Потолок 4 снимает отдельный грант Q7 и ставит `aided`. Нет этого гранта — потолок остаётся. Скидка на следующее действие — Q8. Пол 2 относится к ресурсу, который назвали срез или ожидание, после их применения. Компонент, который они не трогали, списывается как на карточке. Следующее действие актора здесь — зажим, отпускание, перевязка или удар. Чат и `leave` ожидание не снимают. Ожидание лежит отдельной JSON-колонкой строки `game_battle`, не ключом внутри `turn_order`. `GameBattleMutator::end` удаляет строку боя, отдельной очистки карты нет. Запись карты и листа — одна транзакция, `state_version` боя сдвигается вместе с ней. Нет открытого боя — действие выполняется, карта не пишется. Удар, зажим, отпускание и перевязка читают и снимают ожидание актора. Поле рук на расе необязательное: `RaceSpecs::race` иначе отвергнет уже лежащие карточки без нового ключа. Силу зажим и перевязка не меняют. Зажим в `bandage` не пишет. Суточное падение силы и тик крови этой сессией не исполняются. Режущий путь пишет `internal: false` и больше его не меняет: запрет внутренней раны не на чем сработать, пока другая операция не поставит `true`.

## Dependency order

```text
Game 28 CLOSED
→ Game 29 APPROVED
→ Game 30 APPROVED (рана-state без sidecar)
→ D1–D4 закрыты
→ Q1–Q6 закрыты
→ Q7 = A, Q8 = A
→ Q9 закрыт: карта на открытом бою
→ одна implementation-сессия Game 31
→ Game 32 DOT / clotting process / конец хода
→ Game 33 E2E
```

Обязательные решения закрыты. Код этой правкой не начинается.

## Files

Читать в сессии после решений. Не менять в этой planning-сессии.

Уже пишут рану-state:

- `Game/Service/GameStrikeConsequences.php` — `cutting`, `woundStrength`, `putState`
- `Character/Service/CharacterActualStateRows.php` — `accept`, `appendOne`, `row`, `folded`
- `Character/Service/CharacterActualMutations.php` — каталог kind
- `Game/Service/GameStrikeSheetWrites.php` — один write Character/NPC
- `Game/Service/GameCharacterDiff.php` — снятие вложенного `wound.heldBy`
- `Game/Service/GameCharacterMemberships.php` — `leave` пишет только `left`
- `Game/Service/GameActionResourceResolver.php` — списание ресурса с карточки действия и id предмета
- `Game/Service/GameReplayTransaction.php` — повтор тела команды; у зажима своего вызова ещё нет
- `Game/Table/GameBattleTable.php` — JSON-колонка только `turn_order`
- `Game/Service/GameBattleMutator.php` — `end` удаляет строку боя
- `Rule/Dto/Spec/ResourceSpec.php` — `isAutoAdd()`, для цены действия не используется
- `Rule/Spec/RaceSpecs.php` — `RaceSpec` без числа рук; `SpeciesSpec` его тоже не имеет
- `Rule/Spec/AbilityGrants.php` — разбор `type`, чужой тип отвергается
- `Character/Dto/CharacterInventoryChoice.php` — код, `equipped`, количество; выбранных рук нет
- `Character/Dto/CharacterAbilityChoice.php` — `ruleCode` и `level` в `choices.abilities`
- `Mechanic/Service/Handler/DamageAsWoundsHandler.php` — маркер, без записи листа

Чат и replay команд зажима и перевязки, D4 уже закрыт:

- `Game/Service/GameStrikes.php`
- `Game/Service/GameWideStrikeResults.php`
- `Game/Service/GameWideStrikeCommit.php`
- `Messages/Chat` send, уже принятый Game 30

Inventory фронта, не переносить как канон:

- `draft-front_1.2ds/.../Character/Dto/CharacterWound.ts`
- `draft-front_1.2ds/.../Game/Service/WoundInstanceService.ts`
- `draft-front_1.2ds/.../Game/Service/WoundActionService.ts`

## Acceptance matrix

Проверяется только после закрытия D1–D4 и одной implementation-сессии. Сейчас это критерии, не выполненный прогон.

| Путь | Ожидание |
|---|---|
| Режущий удар, сила > 0 | Та же сила, что считает `woundStrength`; новая строка с id и sidecar, `clotting` равен 0, `heldBy` пуст |
| Второй режущий удар | Второй id. Первая строка и её sidecar не сливаются |
| `sum`/`max` на коде раны | Отказ записи. Второй строки и затёртого sidecar нет |
| Обычный урон | По-прежнему только `putDamageSplit`; sidecar не появляется |
| Зажать | Пишет `heldBy` `{ kind, id }` на id строки. Повтор той же строки и внутренняя рана — отказ. Цена карточки минус безусловные гранты среза. Руки: `RaceSpec`, с вида — через `parent_race_code`, нет поля значит 2, минус `min` надетого и уже зажатое. `bandage` не меняется |
| Отпустить | Снимает только `heldBy`. Сила и `bandage` остаются |
| Перевязать | `bandage` +2, не выше силы. Внутреннюю не пишет. Цена карточки минус гранты среза и ожидание Q8, итог не ниже 2. Грант Q7 снимает потолок и ставит `aided` |
| Старт боя и `endBattle` | Рана, sidecar и уже стоящий `heldBy` те же. Сессия после `endBattle` жива. ОД за старый зажим не списываются |
| `leave` держащего | Снят только `heldBy` с `kind: character` и id уходящего на листах раненых этой игры. Сила и `bandage` остаются |
| NPC | Тот же pipeline и тот же CAS `npc.version` |
| Stale version | Строка без частичной записи |
| Чат | Узкий result и одно текстовое сообщение в `gameChatId`. Цепочка close удара Game 30 не меняет набор вложений |
| Clotting tick | Не исполняется. На новой строке поле есть и равно 0 |
| Id у любой новой строки | В том числе не-раны. Отдельного редактора листа в PHP Character нет, эта сессия его не заводит |

## Exclusions

- DOT, bleeding tick, автопроверка `rule-blood-clotting`, continuation/cancellation долгого действия, передача хода — Game 32.
- Frontend → PHP → UI E2E, delivery второго клиента — Game 33.
- Cover, range, falloff, battleground.
- Закрытие C11 и G22.
- Смена статусов `game-plan-28.md`, `game-plan-29.md`, `game-plan-30.md` и production-кода.
- Перенос константы `WOUND_STATE_CODE` и формул `WoundInstanceService.tick` в PHP. Конец хода ищет mechanic карточки, не этот код.

## Q1. Пустой sidecar — закрыт, вариант A

Выбор 2026-10-12: **A.** Пустой sidecar кладёт режущий путь Game ключами операции. Character сохраняет их на новой `independent`-строке и не выводит рану из `StateSpec`.

Отклонены B (новый признак спеки) и C (sidecar на всякой строке).

## Q2. Снятие зажима при выходе — закрыт, вариант A

Выбор 2026-10-12: **A.** `GameCharacterMemberships::leave` снимает `heldBy`, когда уходящий персонаж совпал с держащим. Силу и `bandage` не меняет. NPC этим методом не выходит, и сессия для NPC такое снятие не добавляет.

Отклонены B (не снимать в Game 31) и C (считать зажим пустым при чтении, не переписывая строку).

## Q3. Форма `heldBy` — закрыт, вариант A

Выбор 2026-10-12: **A.** `heldBy` — `{ kind: "character"|"npc", id: int }`. `leave` снимает только `kind = character` и id уходящего. NPC-держатель этим методом не снимается.

Отклонён B: голый int персонажа, NPC не зажимает.

## Q4. Числа перевязки — закрыт, вариант A

Выбор 2026-10-12: **A.** Числа из [`../tr/rule-content-plan-04-knowledge.md`](../tr/rule-content-plan-04-knowledge.md) §4.2–4.3 входят в контракт пункта 14. Суточное −2 силы и тик крови остаются Game 32.

Отклонён B: не менять `bandage` в этой сессии.

## Q5. Ресурс действия — закрыт

Выбор 2026-10-12. Зажим и перевязка — действия. Код ресурса и базовая цена читаются из `ResourceComponent` названной карточки. `isAutoAdd()` не используется. Все resource-компоненты карточки списываются. Нет компонента — отказ.

`GameActionResourceResolver::resolve` требует id предмета и для этих команд не используется. Скидки Q4 (8, 4, 2) и цена `ResourceComponent` — два источника. Сессия не выбирает между ними, пока это не сказано.

## Q6. Число рук — закрыт, вариант B

Выбор 2026-10-12: **B.** Число рук — поле расы. В `RaceSpec` его сейчас нет, сессия его добавляет. Нет поля на старой карточке — 2. Свободно = это число − `occupy_hands` надетого − зажатые этим держателем внешние раны.

Отклонена константа 2 на все персонажи.

## Цена перевязки — закрыта

Выбор 2026-10-12. Перевязка стоит столько, сколько написано в её `ResourceComponent`. Скидки — гранты навыков: действие и срез названного ресурса. Game эти гранты применяет и не содержит веток чужих способностей. Вида «срез цены действия» среди `AbilityGrant` нет. `ResourceGrant` поднимает лимит ресурса и этим срезом не является.

## Q7. Потолок `bandage` — закрыт, вариант A

Выбор 2026-10-12: **A.** Отдельный грант снимает потолок 4 и ставит `aided` на перевязанную строку. Срез цены этого не делает. Кода способности в Game нет.

Отклонён B: не снимать потолок в этой сессии.

## Q8. Скидка на следующее действие — закрыт, вариант A

Выбор 2026-10-12: **A.** После перевязки навык, а не карточка перевязки, ставит ожидание на актора. Эффект — `NextActionCostEffect`, дискриминатор `next_action_cost`. Фронтовое имя `next_action_attack_cost` в PHP не разбирается. Фильтр карточки — ровно один: непустой список `keywords` или `ruleCode`. Оба поля сразу и ветка `kind: attack` — отказ разбора. Удар совпадает по `keywordCodes` карточки действия: все коды фильтра есть на карточке. Отдельной ветки «это атака» нет. Совпавшая команда применяет `delta` и снимает список актора; несовпавшая тоже снимает список и цену не меняет. Пол 2 — только у ресурса, которого коснулись срез или это ожидание на командах раны. На ударе общего пола 2 нет.

Отклонён B: не делать ожидание в Game 31.

## Q9. Где хранить ожидание — закрыт

Выбор 2026-10-12. Как фронтовый `pendingActionEffects`: одна карта на открытом бою, ключ — актор `character|npc` и id, значение — список `{ sourceRuleCode, effect }`. В лист и в semantic diff не входит. Отдельного поля «скидка перевязки» на участнике нет. `endBattle` забирает карту вместе с боем. Вне боя карты нет, и ожидание не ставится.

Сессия добавляет колонку `JsonField` на `game_battle`. Класть карту в `turn_order` нельзя: это отдельная колонка порядка хода. `GameBattleMutator::end` делает `battles->delete`, поэтому карта исчезает вместе со строкой. Тот же список принимает `next_action_cost`, не второй механизм. Запись карты сдвигает `state_version` в той же транзакции, что и лист.

Отклонены поле на листе и поле участника, которое переживает конец боя. Бой пока единственный режим, где такие ожидания исполняются. Потерю на `endBattle` позже переносят вместе с этим режимом, а не отдельной пометкой.

## Следующий шаг

Game 32 — отдельный этап (`TODO`, не `IMPLEMENTED`): DOT, tick кровотечения,
прибавка `clotting`, автопроверка свёртывания и конец хода. Эта
documentation-only правка код не меняет.
