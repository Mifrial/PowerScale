# Implementation plan: `game-plan-30` — immediate consequences

Дата inventory: 2026-10-11. Решения D1–D9 зафиксированы 2026-10-11.  
Режим documentation-only closure: 2026-10-11. Production-код и тесты не
изменялись и не запускались.

## Verdict

**APPROVED FOR THE ACCEPTED BACKEND SCOPE; NOT A PROJECT-WIDE APPROVAL.**

Backend Game 30 принят для scope ниже. Прежний verdict
`READY FOR ONE GAME-30 IMPLEMENTATION SESSION` и контракт далее в файле
сохранены как decision trace и не являются текущим статусом.

## Final closure record

- **Backend Game 30 — APPROVED** для принятого scope: единый атомарный
  pipeline immediate consequences внутри close `1 → 1` и `1 → N` для
  Character и NPC — ordinary injury и `putDamageSplit`, state hooks по
  aggregation, stun/shock/knockout, проверки Воли и увечья, чат и delivery
  в той же транзакции close.
- **Exclusions.** Sidecar ран и `heldBy` (Game 31); DOT, clotting, bleeding
  и конец хода (Game 32); frontend → PHP → UI E2E (Game 33); cover, range и
  falloff; закрытие C11 и G22. `putState` не объявляется kind C11.
- **Residual evidence.** Focused MySQL не покрывает рану-state, пропуск
  проверки Воли, увечье, blunt AND и chat thread/replay. Эти пути закрыты
  чтением кода. В этой documentation-only сессии PHPUnit, MySQL и frontend
  не запускались.
- **Следующий этап.** Game 31 остаётся отдельным этапом со статусом `TODO`
  и не переводится в `IMPLEMENTED`.
- Статусный канон — [`../tr/game-plan-30.md`](../tr/game-plan-30.md).
  Очередь — [`../tr/game-roadmap.md`](../tr/game-roadmap.md).

Это закрытие backend принятого scope, не project-wide approval и не
подтверждение frontend → PHP → delivery E2E или deployment/live
verification.

## Historical contract verdict

**READY FOR ONE GAME-30 IMPLEMENTATION SESSION** — historical trace.

D1–D9, уточнения чата и цепочка сообщений с thread `attack` закрыты ниже. Это разрешение одной backend-сессии, не смена статусов Game 28/29 и не Game 33.

## Цель

Единый атомарный pipeline immediate consequences для Character и NPC внутри уже существующего close `1 → 1` и `1 → N`:

```text
принятый расчёт Game 28/29
→ ordinary injury: putDamageSplit
→ cutting: mechanic типа урона уводит повреждения в рану-state, не в remainder
→ прочие immediate states по mechanic типа урона и aggregation
→ проверка истощения, если оно изменилось и цель ещё не без сознания
→ одна проверка увечья, если этот удар дал истощение и/или рану
→ состояния исходов по связи Rule, не по литералам Game
→ chat + delivery + узкий command result
→ close strike и battle version
```

DOT, sidecar ран, конец хода и frontend E2E в этот pipeline не входят.

## Current inventory

### Что strike close уже пишет

Оба close живут в одной transaction/replay boundary:

- `1 → 1`: `GameStrikes::executeCloseTransaction` считает damage, resistance, soak, injury и вызывает `writeSheet` или `writeResources`.
- `1 → N`: `GameWideStrikeResults` делает то же по каждой цели; `GameWideStrikeCommit::close` коммитит через `GameReplayTransaction`.

Формула injury не менялась с Game 28:

```text
max(0, (damage − resistance) × success − soak)
```

`GameStrikeAmounts::injury`. Soak только при реакции `dodge` (`evaluateSoak`). Resistance и penetration — закрытый контур Game 28. Профиль `strike|throw|shoot`, расход предмета и боеприпаса — принятый контур Game 29; distance, range, cover и falloff в расчёт не входят.

Запись листа — одна операция `putDamageSplit`:

```text
kind, remainder {base, size}, quotient
```

`GameStrikeSplits` делит injury на единственную живую характеристику `isDamageEndurance()`. `CharacterActualStateRows` кладёт remainder в состояние `isDamageRemainder()` и прибавляет quotient к `isDamageExhaustion()`. Character идёт через `ICharacterActualMutations::apply`. NPC идёт через `GameStrikeSheetWrites::writeNpc` и CAS `npc.version`. Resource spend и inventory decrement той же операции могут ехать рядом. Auto-fail без spend лист не пишет (`sheetVersion: null`).

Итог JSON сейчас: `battleId`, `strikeId`, `version`, `attackerRoll`, optional `defenderRoll`, `success`, dimensional `damage` / `resistance` / `injury`, optional `S`, `sheetVersion`. Wide: общий `attackerRoll` и `targetResults[]`. Отдельных полей stun, shock, knockout, will, maim нет.

### State hooks

Character умеет `putState` (флаг, number, dimensional). `CharacterActualStateRows::appendOne` всегда дописывает новую строку в `sheet.states` и не читает `StateSpec::getAggregation()`: в PHP этот getter больше нигде не вызывается. Замена одной строки и прибавление числа есть только у `putDamageSplit` для маркированных `isDamageRemainder()` / `isDamageExhaustion()`, и `onlyRow` отвергает вторую строку того же кода. Game на close производит только `putDamageSplit`.

PHP `DamageTypeSpec` хранит genitive, dative, `defenseIgnored`, `modifiesSpellDifficulty`, `maxSuccessRating`. Полей hook/phase/`attached_rule_codes` у типа урона в PHP нет.

Frontend (не канон PHP) уже знает mechanic codes: `exhaustion_to_stun`, `exhaustion_to_shock`, `exhaustion_to_wound`, `damage_as_wounds_by_size`, `blunt_unconscious`, плюс `injury_extra_dice_from_sr`, `injury_efficiency`, `pay_sr_vs_reliability`. `AttackDamageService` из exhaustion пишет stun/shock/wound amounts и knockout при blunt и пороге `remainingSr >= 6` или `hpDamage >= endurance`. Это не authoritative Game path.

`pay_sr` / reliability cut уже закрыты Game 28 и в Game 30 не переоткрываются.

### Что относится к Game 31 и Game 32

| Тема | Этап |
|---|---|
| Экземпляр раны, sidecar `bandage` / `clotting` / `internal` / `aided`, actions зажать/перевязать, `heldBy` в battle state | Game 31 |
| Clotting, bleeding/DOT, автоматические проверки после тика, continuation/cancellation долгого действия, передача хода | Game 32 |
| Два аккаунта, HTTP, delivery/SSE второго клиента | Game 33 |

Режущий путь Game 30 пишет рану как state по `StateSpec.aggregation`. Sidecar экземпляра, `heldBy` и actions зажать/перевязать остаются Game 31. `GameDonorChats::postReturn` пишет только return; close удара сейчас вызывает `GameDeliverySignal` и чат боя не создаёт.

### Порты, которые можно переиспользовать

| Порт | Роль |
|---|---|
| `ICharacterActualMutations` | CAS actual, kinds `putDamageSplit`, `putState`, resource/inventory |
| `GameStrikeSheetWrites` | Один write Character/NPC в close transaction |
| `GameNpcRepository` / `npc.version` | NPC без второго листа |
| `ICharacterFormulaContexts` | Стойкость и будущий контекст проверки |
| `GameCheckRoll` + `IMechanicRolls` | Сборка `CheckSpec` и бросок попадания/блока. Отдельного входа для Воли и увечья нет |
| `GameReplayTransaction` | Повтор того же body возвращает сохранённый `result` и `work()` не вызывает (`find`) |
| `game_delivery` | Сигнал уже принятой команды |

Новый лист и прямой SQL в `sheet` не заводятся. `putDamageSplit` уже пишет остаток и истощение, но [`character-plan-10.md`](../tr/character-plan-10.md) прямо говорит, что `putState` не является kind C11, а C11 в [`character-roadmap.md`](../tr/character-roadmap.md) остаётся `TODO`. Game 30 не закрывает C11 и G22 и не объявляет `putState` этим kind.

### Границы Game 28 и Game 29, которые нельзя нарушать

- Формула `injury`, слои resistance, penetration только в defense, reliability cut, block gate, `{0|-1}` auto-fail, resource/AP eligibility.
- Identity атаки Game 29: action, inventory instance, rule code, profile type/index, ammunition для `shoot`. Клиентские damage, penetration, hit rating не принимаются.
- Distance, range, cover, falloff, координаты и battleground не становятся отказом или модификатором.
- Character не импортирует Game. Rule не собирает слои цели и не исполняет боевые последствия. Game не создаёт второй лист и не выбирает поле листа в обход kind.
- Replay сверяет тело запроса. Mutation и battle CAS остаются в одной transaction. Ошибка mutation откатывает close целиком; target-level refusal wide не затирает чужие уже посчитанные результаты только внутри успешного расчёта, а отказ порта по-прежнему откатывает весь wide strike.

## Authoritative ownership

| Concern | Owner | Game 30 |
|---|---|---|
| Injury number обычного урона | Game `GameStrikeAmounts` | Формулу не менять |
| Remainder + дельта истощения | Character `putDamageSplit` | Спецkind остаётся |
| Запись прочих состояний | Character по `stateRuleCode` и `StateSpec.aggregation` | Game числа не суммирует |
| Каталог хуков типа урона | Rule mechanic на live `damage_type` | Game не сравнивает литералы типов |
| Потеря сознания | Одно состояние, карточка по флагу `decline_unconscious` | Два пути ниже; не отдельная система knockout |
| Пороги пути 2 и шкала РУ→состояние | Rule: `CheckSpec` или mechanic payload | Game пороги и −1/−2/−3 не хардкодит |
| Проверка истощения | `StateSpec.check_code` состояния истощения | Сложность = истощение на листе после изменения |
| Проверка увечья | `check_code` единственного `isMaim()` | Одна проверка на событие |
| `maim` | Состояние с `StateSpec` `isMaim()` | Не sidecar |
| Рана этого удара | State по aggregation | Sidecar и `heldBy` — Game 31 |
| Воля | Единственная живая характеристика `CharacteristicSpec::isWillpower()` | Нет единственной — `GAME_INVALID` |
| Character / NPC | Один pipeline | Нет ветки `if npc` |
| Чат клиента | Цепочка в `gameChatId` от `actorUserId`, общий thread `attack` | Обсуждение не писать; ход и раунд не создавать |
| Узкий command result | Game, сохранённый `complete` | Idempotency бросков, не замена чата |
| Replay | `GameReplayTransaction` | Повтор не пересчитывает и не дублирует чат |
| DOT / turn / E2E UI | Game 32 / Game 33 | Вне сессии |

## Exact contract

1. Последствия исполняются внутри текущего close strike и wide strike. Отдельной HTTP-команды нет.
2. Клиент не присылает состояния, сложность, РУ, injury и итоги проверок.
3. Обычный урон считает injury формулой Game 28. `putDamageSplit` пишет remainder и прибавляет quotient. Quotient — дельта истощения этого удара. Коды `stunned` / `exhaustion` в Game не пишутся: остаток и истощение находятся по `isDamageRemainder()` / `isDamageExhaustion()`.
4. Режущий урон не кладёт эти повреждения в remainder. Mechanic или spec типа урона уводит их в рану-state. Ветки `if cutting` в Game нет. Рана этого удара — значение, записанное этим путём. Sidecar не создаётся.
5. Прочие immediate states пишутся Character по `stateRuleCode` из Rule. Канон записи — `StateSpec.aggregation`. Во frontend-типе значения уже названы `sum`, `max`, `independent` (`StateAggregation` в `StateSpec.ts`); PHP строку не проверяет. `independent` и есть «каждая запись отдельно». Чужое значение — отказ записи, четвёртый режим не выдумывается. Текущий `putState` только дописывает строку и не читает aggregation. Этот gap закрывается в той же сессии до stun/shock. Game числа не суммирует. `putDamageSplit` по-прежнему держит одну строку remainder и одну строку exhaustion через `onlyRow`: aggregation не должна плодить вторую строку этих двух флагов, иначе следующий удар получит `CharacterInvalidException`.
6. Потеря сознания — одно состояние, найденное по `decline_unconscious`, не отдельный knockout. Путь 1: проверка Воли против истощения. Путь 2: mechanic на типе урона (мок `blunt_unconscious`): `success >= порог` и `injury >= стойкость цели`. Канон — AND. Пороги лежат в mechanic payload, не в Game.
7. Изменение состояния истощения запускает live check из `StateSpec.getCheckCode()` этого состояния. Сложность равна истощению на листе после изменения только если `CheckDifficulty` этой проверки имеет kind `from_state`, а `state_code` указывает на то же состояние истощения. Иной kind (`ask`, `none`) или другой `state_code` — `GAME_INVALID`, close не подменяет spec числом. Исходы слабость / обессилен / бессознательность — состояния с флагами `decline_weakness`, `decline_disabled`, `decline_unconscious`. Связь РУ с состоянием задаёт Rule (`CheckSpec` или mechanic). Game не выбирает форму таблицы и не хранит пороги.
8. Уже бессознательная цель при росте истощения проверку не проходит. Из слабости или без упадка исход может сразу стать бессознательностью. При росте исход может только ухудшиться по шкале Rule, при падении — только улучшиться. Из бессознательности при росте броска нет.
9. Увечье: одна проверка на событие. Код проверки — `check_code` единственной живой карточки с `isMaim()`. Нет единственной такой карточки или `check_code` пуст — `GAME_INVALID`, когда триггер есть. Триггеры — единовременные истощение и/или рана этого удара. Оба есть — одна проверка, сложность равна max источников. Формула сложности — live Rule этой проверки, не литерал Game. Исход пишется в состояние `isMaim()`. Нет раны этого удара — рановый триггер не запускать.
10. Нет единственной живой характеристики Воли — `GAME_INVALID`, тем же смыслом, что отсутствие единственной стойкости в `GameStrikeSplits`. Character и NPC идут одним путём.
11. Одна цель — один список операций в один `apply` или `replaceVersion`, вместе со spends. Второй `write` на ту же версию в том же close запрещён.
12. В той же транзакции close PHP пишет цепочку сообщений в чат игры `GameRecord::getGameChatId()` (тип `game`) и пишет delivery. `discussionChatId` и чат строки персонажа не используются. `gameChatId === null` — `GAME_INVALID`. Автор каждого сообщения — `actorUserId`. Если он не член этого чата, close не пишет от владельца и не закрывает удар. Фронт не считает последствия и не пишет чат вместо сервера. Узкий command result хранит серверные броски и исходы для idempotency.
13. Тексты и вложения копируют уже выверенные форматтеры фронта, а не новый пересказ. Для одной цели `1 → 1`, шаг пропускается, если его условие ложно: `formatAttackActionMessage`; `formatStrikeNarrativeMessage`; пустой текст и вложения `type: "roll"`; `formatAttackResultMessage` и вложение `type: "attack-calc"`; при росте истощения — пустой `roll` и `formatExhaustionCheckMessage`; при запуске увечья — пустой `roll`, затем `formatInjuryReceivedMessage` и вложение `type: "injury-details"`. У `1 → N` действие и текст удара пишутся один раз, на первую цель (`announce` только при `index === 0` в `HitLaunchDialog`). Дальше у каждой цели свои броски, результат, Воля и увечье. Заклинание, толчок и касание в эту цепочку не входят. Пустой `content` допустим, если вложения непустые: так делает `ChatInputNormalizer::normalizeSend`.
14. Все сообщения одного close несут один thread `{ id, kind: "attack" }`. `parentId` ставится только если сервер уже знает id хода или раунда. Game 30 ход и раунд не создаёт. Поле thread в `SendMessageInput` и `ChatMessageTable` сейчас отсутствует; сессия добавляет его в сообщение чата, иначе свёртка не переживает перечитывание. Подпись speaker в send не пишется: текущий `chat.sendMessage` её отвергает.
15. Повтор того же body возвращает сохранённый result и не вызывает `work()` (`GameReplayTransaction::find`): броски не повторяются, сообщения чата не дублируются. Отдельный seed не нужен.
16. Конфликт версии листа не оставляет частичной записи состояний, чата и battle version.

## Dependency order

```text
Game 28 CLOSED
→ Game 29 APPROVED
→ Character aggregation write (prerequisite в той же сессии, до stun/shock)
→ Game 30 close pipeline
→ Game 31 sidecar ран
→ Game 32 DOT / turn
→ Game 33 E2E
```

C11 и G22 эта сессия не закрывает. `putState` не объявляется kind C11.

## Technical details, не product choices

- `RootSpecs::state` уже читает `aggregation` и `check_code` в `StateSpec`. Применение aggregation при записи в PHP отсутствует.
- `DamageTypeSpec` полей mechanic не содержит. Список mechanics читается у живого правила `damage_type`, как `GameStrikeLayers::bindings`. Этот метод кладёт в `MechanicBinding` payload `null` и для среза надёжности payload не нужен. Пороги Game 30 из такой копии не прочитать: сессия берёт массив `mechanic_payload` самой строки привязки.
- `CheckDifficulty` знает только kind `ask`, `from_state` и `none`. Отдельного числового поля сложности в spec нет.
- `GameCheckRoll::throwCheck` и `checkOf` не принимают эту проверку. `isLaterStep` отвергает `CheckSpec::isWillpower()` и kind `from_state`. Вызов `throwCheck` для D4 даст `GAME_INVALID` до броска. Кубы по-прежнему считает `IMechanicRolls`; вход `throwCheck` для Воли не используется.
- `IChats::send` требует `userId` члена чата (`requireMember`). `assertWriter` пускает в close владельца, gm и `editAll`; членство в чате из этого не следует. `getGameChatId()` может быть `null`. `postReturn` — только return. Пустой текст с непустыми вложениями `normalizeSend` принимает.
- Типы вложений, которые уже рисует фронт: `roll`, `attack-calc`, `injury-details`. Форма payload — та, которую собирают `buildAttackCalcPayload` и бросок проверки. Второй JSON для тех же карточек не заводится.
- Несколько операций одной цели расширяют список `GameStrikeSheetWrites::operations`, не второй CAS.

## Files

Оркестрация, без смены формулы ordinary injury:

- `Game/Service/GameStrikes.php`
- `Game/Service/GameWideStrikeResults.php`
- `Game/Service/GameWideStrikeCommit.php`
- `Game/Service/GameStrikeSheetWrites.php` — `write` / `operations` принимают одну effect-операцию плюс spends и inventory. Несколько `putState` и один `putDamageSplit` одной цели должны уйти одним списком в один `apply` / `replaceVersion`. Второй `write` на ту же цель в том же close увидит уже увеличенную версию и даст conflict.
- новый Game-класс producer операций рядом с close, не второй бросок внутри `GameCheckRoll` и не рост `GameStrikeRules` (класс уже помечен превышением порога длины и сложности)

Prerequisite той же сессии, без отдельного этапа:

- `Character/Service/CharacterActualStateRows.php` — запись по `aggregation`: `sum`, `max`, `independent`.
- `Character/Service/CharacterActualMutations.php` — тот же список операций, без импорта Game.

`GameStrikeSplits` и `GameStrikeAmounts::injury` для обычного урона не меняют смысл.

Тесты той сессии, не этого документа: MySQL Character и NPC, single и wide, aggregation, режущий путь в рану-state, пропуск проверки у уже бессознательного, одна проверка увечья на два триггера, replay без второго чата, version conflict, auto-fail без exhaustion.

## Acceptance matrix

| # | Условие |
|---|---|
| A1 | Обычный урон пишет remainder и дельту истощения формулой Game 28 и `putDamageSplit` |
| A2 | `sum` / `max` / `independent` применяются Character по `StateSpec.aggregation`; повторный stun при `sum` не плодит вторую строку. Маркированные remainder и exhaustion остаются одной строкой для `onlyRow` |
| A3 | Режущий урон не попадает в remainder; рана — state. В коде Game нет литерала типа урона и ветки cutting |
| A4 | Путь 2 бессознательности срабатывает только при AND порога и injury ≥ стойкости; пороги читаются из mechanic payload |
| A5 | Рост истощения у цели без `decline_unconscious` запускает check из `check_code`. Сложность — истощение после записи, только при `from_state` на это состояние; иначе `GAME_INVALID` |
| A6 | Уже бессознательный при росте истощения бросок не получает. Рост не улучшает исход, падение не ухудшает |
| A7 | Истощение и рана одного удара дают одну проверку. Код — `check_code` единственного `isMaim()`. Сложность — max источников. Исход пишется в это состояние |
| A8 | Нет единственной живой Воли — `GAME_INVALID` и для Character, и для NPC |
| A9 | Цепочка пишется в `gameChatId` от `actorUserId`, у всех сообщений close один thread `attack`. Обсуждение не пишется. Replay не добавляет сообщения и не перебрасывает кубы |
| A10 | Command result содержит серверные броски и исходы и не заменяет чат |
| A11 | Stale version не оставляет частичных состояний и чата |
| A12 | Auto-fail без spend не создаёт истощение и не запускает проверки |
| A13 | Контуры Game 28 и Game 29 не меняют resistance, block, profile identity и spend |
| A14 | В diff нет sidecar, `heldBy`, DOT, конца хода, дальности, cover и falloff |

## Exclusions

- Game 31: экземпляр раны, sidecar, зажать/перевязать, `heldBy`.
- Game 32: clotting, bleeding, DOT, конец хода, передача хода, долгое действие.
- Game 33: frontend → PHP → UI E2E. Чат и delivery в транзакции close — часть Game 30, не этот E2E.
- Cover, карта, дальность, falloff.
- Смена формулы ordinary injury и слоёв Game 28.
- Закрытие C11 и G22.
- Магия и cast.
- Хардкод кодов состояний, типов урона, порогов и таблицы РУ в Game.
- Правка `game-plan-28.md`, `game-plan-29.md` и roadmap.

## Decision record

| ID | Принято |
|---|---|
| D1 | Каталог хуков — mechanic на live `damage_type`, как reliability. Game не сравнивает литералы типов урона |
| D2 | Состояния пишет Character по `stateRuleCode`. `aggregation` — канон `sum` / `max` / `independent`. Gap текущего append-only `putState` закрывается в этой сессии до stun/shock. Game не суммирует. `putDamageSplit` остаётся спецkind. Коды не хардкодить |
| D3 | Потеря сознания — одно состояние по флагу Rule. Путь 1: Воля против истощения. Путь 2: mechanic типа урона, канон AND `success >= порог` и `injury >= стойкость`. Пороги в payload. Не отдельный knockout. Frontend OR не канон |
| D4 | Изменение истощения запускает check из `StateSpec.check_code`. Сложность — истощение на листе после изменения. Исходы — состояния упадка. Связь РУ→состояние принадлежит Rule (`CheckSpec` или mechanic); Game форму таблицы не выбирает и пороги не хранит. Правила пропуска и монотонности шкалы — в контракте выше |
| D5 | Одна проверка увечья на событие. Триггеры — истощение и/или рана этого удара; оба — max. Identity и формула сложности — live Rule. `maim` — состояние. Режущий путь без authoritative раны-state не запускает рановый триггер |
| D6 | Обычный урон: формула Game 28 и `putDamageSplit` без изменений. Quotient — дельта истощения удара. Режущий уводит повреждения в раны через mechanic/spec, не через `if cutting` |
| D7 | Нет единственной живой Воли — `GAME_INVALID`. Один путь для Character и NPC |
| D8 | Authoritative chat и delivery пишет PHP close в той же транзакции. Узкий command result хранит броски и исходы для idempotency, не как чат для клиента |
| D9 | Отдельный seed не нужен. Первый `work()` сохраняет result и чат; replay их не пересчитывает и не дублирует |

## Уточнения 2026-10-11

| # | Принято |
|---|---|
| Чат | Только чат игры, `GameRecord::getGameChatId()`. Обсуждение и чат строки не трогать. Пустой id — `GAME_INVALID` |
| Увечье | Код проверки — `check_code` состояния `isMaim()`. Поздние конфликты этого поля не решаются заранее |
| Сложность Воли | Не `from_state` на состояние истощения — `GAME_INVALID`. Бросок не идёт через `GameCheckRoll::throwCheck` |

## Чат, 2026-10-11

| # | Принято |
|---|---|
| Автор | `actorUserId`. Не член `gameChatId` — close не пишет от владельца и не завершается |
| Полотно | Отдельные сообщения форматтерами фронта. У `1 → N` действие и текст удара один раз; броски, результат, Воля и увечье — на каждую цель |
| Thread | Один `{ id, kind: "attack" }` на весь close. `parentId` только к уже известному ходу или раунду. Ход и раунд не создаются. Поле хранится на сообщении чата. Speaker не пишется |
