# Read-only implementation plan: defender-check и block semantics

Дата: 2026-10-08

## Verdict

**BLOCKED**

Нужна независимая проверка исправленного контракта результата проверки.


- Новые решения Андрея закрывают contract decisions по выбору защиты,
  dimensional efficiency, joint rolls/auto-fail и authoritative result/chat.
Существующий P1 transaction boundary и его stale/CAS semantics сохраняются.

## Владельцы контрактов

- **Rule:** live `CheckSpec::isHitCheck()`, `BlockProfile`, `weapon_mastery.profiles`, `proficiency_family_code`, weapon-family ladder.
- **Character:** read-only derived mastery, equipped inventory resolution, Character/NPC document parity.
- **Mechanic:** `RollSpec`, `RollMechanicPayload`, roll/rating и активные roll modifiers.
- **Game:** выбор hit-check, attacker/defender orchestration, block branching, JSON, replay/conflict propagation.
- **CAS/replay/rollback:** существующий P1 transaction boundary; P3 не создаёт отдельный CAS path.

## Defense selection

Защита не выбирает автоматически максимум mastery. Игрок выбирает конкретный
доступный профиль конкретного экипированного инстанса.

Transport обязан содержать `blockItemInventoryId` и идентификатор выбранного
defensive profile. `blockItemRuleCode` не является authoritative
идентификатором инстанса: сервер разрешает его из выбранных inventory
instance/profile.

```text
mastery = base combat characteristic + weapon-family proficiency
```

- `strike` использует characteristic с `weapon_mastery.profiles` containing `strike`.
- `throw`/`shoot` используют соответствующую ranged-характеристику.
- Defender-кандидаты: общий base combat characteristic и допустимые equipped weapon instances.
- Для каждого weapon instance вычисляется mastery с его `proficiency_family_code`.
- Максимальный dimensional mastery автоматически не выбирается.
- Proficiency берётся из live `choices.abilities` с weapon-family domain.
- Неизвестная family, malformed domain, отсутствие или неоднозначность characteristic дают `GAME_INVALID`.
- Fallback на фиксированное mastery запрещён.

Для выбранного профиля weapon/shield family берётся из live Rule; mastery
считается по base combat characteristic и proficiency этой family. Character и
NPC используют одинаковую semantics. Недоступный instance/profile, malformed
family или ambiguous resolution дают `GAME_INVALID`.

Для attacker необходим обязательный `itemInventoryId`; одного `itemRuleCode` недостаточно.

## Roll semantics

Defender roll должен использовать:

- `diceCount` — `mastery.base`;
- `dieFaces` — существующий roll payload;
- `dieSize` — размер mastery плюс размер efficiency;
- difficulty — фактический sized attacker roll, не только attacker rating;
- `CheckRating::getRating()` — целочисленный РУ проверки после приведения
  размеров, а не combat auto-fail state.

Результат проверки обязан разделять значения:

- `roll` — authoritative размерное количество успехов броска, `{base, size}`;
- `rating` — целочисленный РУ после приведения размеров.

При dimensional comparison отрицательная база успехов сначала сворачивается
в `{0, size + base}`. При приведении к меньшему размеру нулевая база успехов
перед обычным сдвигом заменяется на единицу, а размер уменьшается на единицу.
Например, attacker `{0|3}` против defender difficulty `{2|-1}` приводится как
`{0|3} → {1|2} → {8|-1}`. РУ атакующего равен `8 - 2 = 6`.
`defenderRoll.difficulty` получает именно фактический attacker `roll`, а не
целое `rating`.

Mastery не приходит от клиента и не сплющивается в scalar.

## Efficiency semantics

`mastery {3|1}` означает 3 кубика. `efficiency {4|1}` означает threshold 4:
результаты 4 и ниже успешны. Число кубиков берётся из `mastery.base`; размер
кубиков увеличивается на размер mastery и размер efficiency. Conversion
выполняется через существующую general roll/size ladder. `efficiency.base` не
является числом кубиков, а `efficiency.size` не является threshold.

Сохраняется подтверждённая кодом precedence `RollSpec`: non-neutral значение
efficiency имеет приоритет; только neutral значение допускает подстановку
`RollMechanicPayload.efficiency`. Active roll mechanics обрабатываются через
существующий `IMechanicRolls` pipeline.
Для block reaction `BlockProfile.efficiency` является источником efficiency
выбранного live-профиля и проходит описанную dimensional conversion; новая
precedence между источниками не вводится.

<!-- Precedence is described above; no additional source ordering is introduced. -->


## Orchestration

### 1 → 1

```text
battle/version checks
→ attacker instance validation
→ target sheet/version validation
→ find exactly one live hit_check
→ attacker roll
→ optional defender roll for block (independently of attacker auto-fail)
→ attacker auto-fail outcome boundary
→ resistance/injury
→ existing mutation/CAS pipeline
```

- `ignore` и `dodge` defender roll не выполняют.
- `block` выполняет отдельный defender roll.
- Для обычного attacker roll defender difficulty равна фактическому sized
  attacker roll; существующие success/damage/resistance/injury semantics
  сохраняются.
- Defender fail сохраняет обычный attacker rating и не добавляет block layers.
- Defender success устанавливает `success = 1` и добавляет `BlockProfile.defense` и resistances.

### 1 → N

- один attacker roll;
- отдельный независимый defender roll каждой block-цели;
- собственные block result, resistance и injury для каждой цели;
- один attacker rating нельзя использовать как готовый результат защиты всех целей.

### Auto-fail

Auto-fail определяется только по размерному attacker `roll`, не по
`rating === 0` и не по `rating === -1`. Минимальное combat-check значение —
`{0|-1}`; значения ниже него приводятся к `{0|-1}`. `{0|0}` и `{0|3}` не
являются auto-fail, `{0|-1}` является auto-fail.

Эта dimensional normalization общая для сравнений всех проверок; lower bound
и auto-fail boundary задаются конкретной проверкой. Для обычной проверки без
нижней границы новая auto-fail semantics не появляется.

Для `block` attacker и defender rolls всё равно выполняются. Defender roll
сохраняется, а его difficulty равна фактическому attacker `roll`, включая
`{0|-1}` после combat normalization. В wide выполняются один attacker roll и
отдельный defender roll каждой block-цели. Итоговое попадание принудительно
неуспешно независимо от defender roll. Block layers,
resistance/effect/injury попадания не применяются; reaction и списание ОД
сохраняются. `sheetVersion` равна версии после CAS-списания ОД/реакции.
Stale/CAS semantics остаются прежними.

Для `ignore` и `dodge` сохраняется существующая semantics: defender block
roll не выполняется.

## Authoritative result and chat

Используется shape существующего `GameCheckRoll`:

```json
{
  "difficulty": {"base": 4, "size": 0},
  "roll": {"base": 3, "size": 1},
  "success": true,
  "rating": 1
}
```

`roll` и `difficulty` всегда являются размерными значениями. `rating` —
целочисленный РУ; `CheckRating::getRating()` возвращает именно его. Ни одно
из этих integer-значений не используется для определения auto-fail.

Single result содержит `attackerRoll`, `defenderRoll` для block reaction и
существующие `success`/`damage`/`resistance`/`injury`/`sheetVersion` поля.
Wide result содержит один top-level `attackerRoll`, `defenderRoll` внутри
каждого block target result и независимый result каждой цели.

Auto-fail result содержит оба выполненных roll results для block reaction,
но итоговые hit/effect fields отражают полный miss. Chat/delivery использует
сохранённые authoritative roll results; повторный roll при replay или
отображении запрещён. Replay возвращает сохранённые attacker/defender rolls
без повторного броска.

### 1 → 1

```json
{
  "battleId": 12,
  "strikeId": 34,
  "version": 5,
  "attackerRoll": {"difficulty": {"base": 4, "size": 0}, "roll": {"base": 3, "size": 1}, "success": true, "rating": 1},
  "defenderRoll": {"difficulty": {"base": 3, "size": 1}, "roll": {"base": 4, "size": 0}, "success": true, "rating": 1},
  "success": 1,
  "damage": {"base": 6, "size": 0},
  "resistance": {"base": 4, "size": 0},
  "injury": {"base": 2, "size": 0},
  "sheetVersion": 9
}
```

Для `dodge` сохраняется существующий optional `S`.

### Auto-fail

```json
{
  "battleId": 12,
  "strikeId": 34,
  "version": 5,
  "attackerRoll": {"difficulty": {"base": 4, "size": 0}, "roll": {"base": 0, "size": -1}, "success": false, "rating": -1},
  "defenderRoll": {"difficulty": {"base": 0, "size": -1}, "roll": {"base": 2, "size": 0}, "success": true, "rating": 1},
  "success": 0,
  "damage": {"base": 0, "size": 0},
  "resistance": {"base": 0, "size": 0},
  "injury": {"base": 0, "size": 0},
  "sheetVersion": 9
}
```

### 1 → N

```json
{
  "battleId": 12,
  "strikeId": 34,
  "version": 5,
  "attackerRoll": {"difficulty": {"base": 4, "size": 0}, "roll": {"base": 3, "size": 1}, "success": true, "rating": 1},
  "targetResults": [
    {
      "target": {"type": "npc", "id": 51},
      "defenderRoll": {"difficulty": {"base": 3, "size": 1}, "roll": {"base": 4, "size": 0}, "success": true, "rating": 1},
      "success": 1,
      "damage": {"base": 6, "size": 0},
      "resistance": {"base": 8, "size": 0},
      "injury": {"base": 0, "size": 0},
      "sheetVersion": 4,
      "code": null
    }
  ]
}
```

Wide stale после P1 — top-level `GAME_CONFLICT`; обычные non-CAS refusals остаются в `targetResults[].code`.

## Dependency order

1. Разрешить выбранный inventory instance и defensive profile из transport.
2. Завершить shared effective-item/mastery projection для Character/NPC.
3. Реализовать attacker/defender roll API на одной live `hit_check`.
4. Реализовать block success/failure, сохранение обоих roll results и
   `sheetVersion` после CAS-списания ОД/реакции.
5. Подключить layer projection только после успешного defender roll.
6. Проверить интеграцию после существующего P1 CAS/replay/rollback gate.

## Файлы

Основная Game-область:

- `Game/Service/GameCheckRoll.php`
- `Game/Service/GameStrikeRules.php`
- `Game/Service/GameStrikeLayers.php`
- `Game/Service/GameStrikes.php`
- `Game/Service/GameWideStrikes.php`
- `Game/Service/GameWideStrikeResults.php`
- `Game/Service/GameStrikeBody.php`
- `Game/Service/GameWideStrikeBody.php`
- `Game/Service/GameWideStrikeOpening.php`
- `Game/Table/GameStrikeTable.php`
- `Game/Table/GameWideStrikeTable.php`
- `Game/Service/GameStrikePortFactory.php`
- `Game/Service/GameWideStrikePortFactory.php`

Character projection:

- `Character/Interface/Service/ICharacterCombatLayers.php`
- `Character/Service/CharacterCombatLayers.php`
- `Character/Service/CharacterCombatItemLayers.php`
- `Character/Service/CharacterCombatLayerPortFactory.php`
- новый typed mastery DTO/port/service либо согласованное расширение combat projection
- `Character/module.config.php`

Тесты:

- `CharacterCombatLayerTest.php` или отдельный mastery test;
- `GameStrikeMysqlTest.php`;
- `GameWideStrikeMysqlTest.php`;
- отдельный pure test порядка вызовов;
- `GameCheckMysqlTest.php`.

Не изменять:

- `CheckSpec` marker model;
- `BlockProfile` schema;
- frontend;
- penetration;
- production reliability;
- battleground;
- wounds/DOT;
- game-plan-29;
- roadmap/readiness/status;
- P1 CAS implementation.

## Replay, stale и rollback

- same idempotency key + same body → точный сохранённый JSON без повторных rolls/projection/mutation;
- same key + different body → `GAME_CONFLICT`;
- stale battle → top-level `GAME_CONFLICT`, strike остаётся open;
- stale target в wide → top-level conflict после P1 gate;
- mutation-time CAS conflict → rollback всего close;
- block failure не является `ignore`, `GAME_INVALID` или CAS conflict.

## Acceptance matrix

- ровно один live `hit_check`;
- отсутствие/дубликаты → `GAME_INVALID`;
- broken spec пропускается;
- `hit_check` запрещён для ordinary check/initiative;
- attacker использует выбранный inventory instance;
- defender использует выбранный instance/profile, а не автоматически максимум mastery;
- Character/NPC дают одинаковый результат на одинаковых документах;
- mastery сохраняет dimensional size;
- efficiency precedence подтверждён и покрыт тестами;
- 1 → 1: один attacker + один defender roll;
- 1 → N: один attacker + независимый defender roll каждой block-цели;
- обычный block: defender difficulty — фактический размерный attacker `roll`,
  а не integer `rating`;
- defender fail сохраняет attacker rating;
- defender success даёт `success = 1` и block layers;
- `{0|0}` не auto-fail;
- `{0|3}` не auto-fail;
- `{0|-1}` auto-fail;
- значение ниже `{0|-1}` clamp-ится к `{0|-1}`;
- `{0|3}` против `{2|-1}` даёт РУ `6`;
- auto-fail определяется по attacker `roll`, а не по integer `rating`;
- auto-fail использует фактический attacker `roll` как defender difficulty,
  сохраняет отдельные attacker/defender roll results, нулевые
  damage/resistance/injury и post-CAS `sheetVersion`, но не применяет block
  layers;
- single и wide используют одинаковую semantics размерного `roll`, integer
  `rating`, auto-fail и defender difficulty;
- `ignore`/`dodge` не выполняют defender roll;
- invalid block profile после положительного attacker roll → `GAME_INVALID`;
- exact JSON single/auto-fail/wide;
- replay без повторного roll;
- stale и rollback сохраняют P1 semantics;
- Character/NPC parity.

**Итог: BLOCKED до независимой проверки исправленного контракта.**
