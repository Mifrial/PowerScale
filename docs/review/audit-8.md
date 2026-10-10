# P3 — defender hit-check/block semantics

Статус исходного состояния:

- `HEAD`: `26048f3778555fbf4646c7eb861ce92fd7b23c7f`, branch `dev`.
- Tracked changes отсутствуют.
- Все существующие untracked сохраняются без изменений.
- PHPUnit/MySQL и frontend dev-server не запускались.

## Owners

- Rule:
  - существующий live `CheckSpec::isHitCheck()`;
  - существующий `BlockProfile`;
  - `weapon_mastery.profiles`;
  - `ItemSpec::proficiency_family_code`;
  - `weapon_family` ladder.
- Character:
  - read-only projection derived weapon mastery;
  - разрешение equipped weapon instances;
  - Character/NPC document parity.
- Mechanic:
  - существующий `IMechanicRolls`;
  - `RollMechanicPayload`;
  - уже зарегистрированные roll modifiers.
- Game:
  - выбор live `hit_check`;
  - attacker/defender orchestration;
  - block branching;
  - target-level result handling;
  - replay и propagation конфликтов.
- AP spend и authoritative DB CAS — внешние prerequisites; в P3 не реализуются.

## Backend contract

### Hit-check

Используется ровно одна живая `CheckSpec` с `isHitCheck()`:

- битые specs пропускаются;
- ноль или несколько live hit-check → `GAME_INVALID`;
- новая карточка, marker и rule code не добавляются;
- `GameCheckRoll::checkOf()` продолжает запрещать `hit_check` для обычных checks и initiative.

### Derived mastery

Mastery не приходит от клиента.

Для каждого участника строится derived value:

```text
mastery = base combat characteristic
        modified by scalar weapon-family proficiency
```

Base combat characteristic выбирается по профилю атаки:

- `strike` — характеристика с `weapon_mastery.profiles` containing `strike`;
- `throw`/`shoot` — соответствующая ranged-характеристика.

Attacker:

- используется именно выбранный equipped weapon instance;
- текущего backend-поля instance id во входе нет, поэтому P3 должен добавить обязательный `itemInventoryId`;
- instance должен быть equipped, live и содержать выбранный weapon profile.

Defender:

- используется ровно выбранный в transport equipped weapon instance/profile;
- его `proficiency_family_code` разрешается сервером;
- автоматический выбор максимального mastery запрещён;
- отсутствие или неоднозначность выбранной строки/profile — `GAME_INVALID`;
- неизвестная weapon family или malformed ability domain — `GAME_INVALID`, не fallback.

Уровень proficiency берётся из `choices.abilities`:

- только ability с live `domain_ref = weapon-family`;
- family определяется через `domainCode`, либо однозначно разрешаемый `domain`;
- отсутствующая matching ability означает нулевой scalar proficiency;
- duplicate matching ability — `GAME_INVALID`;
- proficiency применяется как scalar modify по characteristic scale, без `toInteger()`-flattening.

### Defender pool

Для defender roll:

- `diceCount` — base derived mastery; нулевой base отклоняется как `GAME_INVALID` до вызова roll engine;
- `dieSize` — размер mastery плюс размер эффективности;
- `dieFaces` — существующий `RollMechanicPayload`;
- difficulty — фактический sized roll attacker, не его уже рассчитанный rating;
- defender `CheckRating::isPassed()` определяет успех блока.

### Efficiency precedence

Для `reaction = block`:

1. базовое значение — `BlockProfile.efficiency`;
2. explicit `CheckSpec` efficiency заменяет threshold base;
3. explicit `RollMechanicPayload.efficiency` имеет высший приоритет;
4. активные roll mechanics применяются через существующий `IMechanicRolls`.

Размер `BlockProfile.efficiency` сохраняется в sized roll; integer payload/check efficiency меняет threshold, но не сплющивает dimensional size.

Обычный attacker roll не получает `BlockProfile.efficiency`.

## Orchestration

### 1 → 1

Порядок:

1. проверить battle version;
2. проверить attacker instance и сохранить его identity/version при declaration;
3. проверить target sheet version;
4. найти единственный live `hit_check`;
5. выполнить один attacker roll;
6. если attacker rating `0` или `-1`:
   - не выполнять defender roll;
   - не читать BlockProfile;
   - не вызывать Character combat projection;
   - не вызывать reliability engine;
   - не писать target sheet;
7. для `ignore`/`dodge` defender roll не выполнять;
8. для `block`:
   - проверить выбранный equipped block instance и `BlockProfile`;
   - выполнить defender roll;
   - при defender success применить block branch;
   - при defender failure использовать обычный attacker rating;
9. выполнить resistance/injury и существующую mutation pipeline.

### 1 → N

Порядок:

1. один раз выполнить attacker roll;
2. для каждой цели отдельно:
   - проверить target membership/version;
   - при target-level stale вернуть `GAME_CONFLICT`;
   - при attacker auto-fail не выполнять defender/profile/layer/reliability reads;
   - при `block` выполнить отдельный defender roll;
   - независимо вычислить resistance/injury/mutation.

Не допускается передавать один attacker rating как готовый результат защиты всем целям.

### Block failure

- обычный attacker rating сохраняется в `success`;
- block defense/resistances не входят;
- обычные armor/grant layers остаются;
- failure не считается invalid и не является auto-ignore.

### Block success

- `success` принудительно равен `1`;
- добавляются block defense и block resistances;
- block defense участвует во всех damage types, кроме `defense_ignored`;
- source и reliability обрабатываются существующим `GameStrikeLayers`;
- block layers читаются только после успешного defender roll.

### Auto-fail

Для attacker rating `0` или `-1`:

- полный промах;
- defender roll отсутствует;
- block не применяется;
- target sheet не изменяется;
- `damage`, `resistance`, `injury` возвращаются нулевыми;
- `sheetVersion` остаётся `null`;
- stale/CAS conflict всё равно возвращается как conflict, а не маскируется под auto-fail.

## JSON contract

Новых result DTO, marker и card не вводить. Текущий envelope сохраняется.

### 1 → 1

```json
{
  "battleId": 12,
  "strikeId": 34,
  "version": 5,
  "success": 1,
  "damage": {"base": 6, "size": 0},
  "resistance": {"base": 4, "size": 0},
  "injury": {"base": 2, "size": 0},
  "sheetVersion": 9
}
```

Для `dodge` сохраняется существующий optional `S`. Для `block` отдельные `blockRoll`/`blockSuccess` поля не добавляются.

При attacker auto-fail:

```json
{
  "battleId": 12,
  "strikeId": 34,
  "version": 5,
  "success": 0,
  "damage": {"base": 0, "size": 0},
  "resistance": {"base": 0, "size": 0},
  "injury": {"base": 0, "size": 0},
  "sheetVersion": null
}
```

### 1 → N

```json
{
  "battleId": 12,
  "strikeId": 34,
  "version": 5,
  "targetResults": [
    {
      "target": {"type": "npc", "id": 51},
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

Target-level conflict сохраняет текущую форму: `code = "GAME_CONFLICT"`, остальные result fields `null`. Shared attack/mastery/hit-check failure остаётся whole-command `GAME_INVALID`.

## Files

Изменяемая authoritative область:

- `GameCheckRoll.php`
- `GameStrikeRules.php`
- `GameStrikeLayers.php`
- `GameStrikes.php`
- `GameWideStrikes.php`
- `GameWideStrikeResults.php`
- `GameStrikeBody.php`
- `GameWideStrikeBody.php`
- `GameWideStrikeOpening.php`
- `GameStrikeTable.php`
- `GameWideStrikeTable.php`
- `GameStrikePortFactory.php`
- `GameWideStrikePortFactory.php`

Character projection:

- `CharacterCombatLayerPortFactory.php`
- `CharacterCombatLayers.php`
- `CharacterCombatItemLayers.php`
- `ICharacterCombatLayers.php`
- новый typed mastery DTO/port/service либо эквивалентное расширение существующего combat projection;
- `Character/module.config.php`.

Тесты для реализации:

- `CharacterCombatLayerTest.php` или отдельный `CharacterCombatMasteryTest.php`;
- `GameStrikeMysqlTest.php`;
- `GameWideStrikeMysqlTest.php`;
- отдельный pure/unit suite для call-order и отсутствия block/layer reads при auto-fail;
- `GameCheckMysqlTest.php` для сохранения запрета обычного `hit_check`.

Не изменять:

- `CheckSpec` marker model;
- Rule cards/content;
- `BlockProfile` schema;
- frontend;
- AP/resource storage;
- Character/NPC CAS implementation;
- roadmap, readiness, status и audit-файлы.

## Replay/stale/conflict semantics

- Тот же idempotency key и тот же body → точный сохранённый JSON, без повторных rolls, projection или mutation.
- Тот же key с другим body → `GAME_CONFLICT`.
- Stale battle version:
  - `1 → 1`: whole-command `GAME_CONFLICT`, удар остаётся open;
  - `1 → N`: existing battle-level conflict, без `targetResults`.
- Stale target sheet:
  - `1 → 1`: whole-command `GAME_CONFLICT`, удар остаётся open;
  - `1 → N`: target-level `targetResults[].code = GAME_CONFLICT`.
- Mutation-time CAS conflict не превращать в block failure или ignore; после закрытия внешнего CAS prerequisite он должен откатывать outer transaction по существующему combat contract.
- Auto-fail не отменяет и не скрывает stale/CAS conflict.

## Acceptance test matrix

- Единственный live `hit_check`; отсутствие и дубликаты.
- Broken hit spec пропускается, живая spec выбирается.
- `hit_check` остаётся недопустимым для ordinary check и initiative.
- Attacker mastery использует выбранный instance, а не только `itemRuleCode`.
- Ненадетый, неизвестный или неправильного profile instance отклоняется.
- Defender mastery выбирает максимум среди допустимых equipped instances.
- Weapon-family proficiency берётся из ability domains и не попадает в общий base stat.
- Character и NPC с одинаковыми documents дают одинаковый mastery.
- `BlockProfile.efficiency` без overrides.
- Precedence: profile → CheckSpec → payload.
- Dimensional mastery/efficiency сохраняет roll size.
- `1 → 1`: ровно attacker roll + один defender roll для block.
- `1 → N`: ровно один attacker roll + отдельный defender roll каждой block-цели.
- Defender fail оставляет attacker rating и не добавляет block layers.
- Defender success выставляет `success = 1` и добавляет block layers.
- Auto-fail `0` и `-1` не вызывает defender roll, profile projection или reliability read.
- Auto-fail не пишет лист и возвращает zero damage/resistance/injury.
- `ignore` и `dodge` не выполняют defender roll.
- Невалидный block profile после положительного attacker roll даёт `GAME_INVALID`, а не defender failure.
- Replay не повторяет roll/mutation.
- Stale battle, stale target и mutation-time CAS сохраняют описанную семантику.
- JSON exact-shape tests для `1 → 1`, auto-fail и `1 → N`.
