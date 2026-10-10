# Audit 1 — defender-check и полная семантика block

## Уточнение модели mastery

Этот аудит фиксировал baseline до реализации P3. Для текущего P3-контракта
формула читается не как сложение двух размерных чисел:

```text
mastery = готовая боевая characteristic из sheet
          modified by scalar proficiency выбранной weapon family
```

Связь `strike`/`throw`/`shoot` с боевой characteristic берётся из live
`CharacteristicSpec::weapon_mastery.profiles`. Боевые characteristics уже
собраны в `sheet` обычным character pipeline и не пересобираются в Game.
Proficiency разрешается из выбранного inventory instance и matching
`choices.abilities` с `domainCode`/`domain`; отсутствие matching proficiency
даёт scalar `0`, duplicate matching choice — `GAME_INVALID`.

Применение proficiency выполняется immutable `DimensionalNumber::modify()`
по встроенной шкале `CharacteristicNumber` с базой `3..5`; `toInteger()`
для этого пути не используется. Нулевой итоговый dice pool отклоняется как
`GAME_INVALID` до вызова общего roll engine.

## Git

Tracked diff отсутствует (`git diff --name-status` пуст). Есть только untracked:

- `tools/review/__pycache__/review.cpython-312.pyc`
- `www/mifrial/var/battleground-attack-stress.json`
- `www/mifrial/var/battleground-move-stress.json`

Тесты и dev-server не запускались.

## Подтверждённый контракт

- Выбирается ровно одна живая `CheckSpec` с `isHitCheck()`.
  - Owner marker: Rule.
  - Owner выбора и броска: Game.
  - Evidence: `GameCheckRoll::findHitCode()`, `GameStrikeRules::findHitCode()`, `CheckSpec::isHitCheck()`.
  - Битые specs пропускаются; ноль или более одной карточки дают `GAME_INVALID`.

- Новая карточка или новый marker не требуются.
  - Используется существующий JSON marker `hit_check`.
  - `GameCheckRoll::checkOf()` намеренно отвергает `hit_check` для обычных G18-checks.

- Требуемая семантика ролей:
  - одна карточка;
  - роли — attacker и defender;
  - `1 → 1`: один бросок атакующего и один независимый бросок защитника;
  - `1 → N`: один бросок атакующего и по одному независимому броску каждого защитника.
  - Это зафиксировано в `docs/tr/combat-layers-prerequisite.md`, §1.

- Block:
  - attacker rating `{0|-1}` — auto-fail, block-ветка не выполняется;
  - defender fail — обычный attacker rating, block layers не добавляются;
  - defender success — `success = 1`, добавляются block defense/resistance;
  - non-block и auto-fail не читают block layers.
  - `BlockProfile::defense` и `BlockProfile::resistances` входят в projection; пустой source блока получает `От блокирования`.

## Что реально делает код

### Hit check

`GameCheckRoll::throwHit()` бросает только одну карточку в режиме `solo` и получает только один `sheet`.

`GameStrikes::rateOf()` и `GameWideStrikes::rateOf()` вызывают `GameStrikeRules::rateHit()` только для attacker.

Defender sheet не передаётся в `GameCheckRoll` и не участвует в hit rating.

### 1 → 1

`GameStrikes::close()` выполняет:

1. `findHitCode()`;
2. расчёт damage;
3. `rateOf()` только атакующего;
4. `evaluateResistance()`;
5. injury и запись листа.

Defender бросок отсутствует. Block success/fail не различаются.

### 1 → N

`GameWideStrikes::close()` один раз вызывает `rateOf()` для атакующего. Затем `GameWideStrikeResults::build()` передаёт один и тот же `$rating` каждой цели.

Независимы только:

- expected sheet version;
- resistance projection;
- soak;
- injury;
- mutation цели.

Независимого defender roll для каждой цели нет.

## Источники pool, characteristic, mastery и efficiency

- Attacker characteristic берётся из цепочки `CheckSpec`:
  - `GameCheckRoll::chain()`;
  - `GameCheckRoll::firstCharacteristic()`.
- Attacker pool берётся из `sheet['characteristicPurchases']` в `GameCheckRoll::diceCount()`.
- Roll payload ищется `GameCheckRoll::bindings()` / `rollPayload()`. Сейчас обход собирает bindings всех live rules, а не отдельный authoritative defender context.
- `mastery` в проверенных Game/Rule/Mechanic классах не используется.
- `CheckSpec::getDefaultEfficiency()` в hit path не используется.
- Обычный `RollSpec` создаётся с efficiency `3` в `GameCheckRoll::thrown()`.
- `BlockProfile::getEfficiency()` нигде не вызывается.
- `BlockProfile::efficiency` имеет тип `DimensionalNumber`, но нет контракта преобразования в integer roll efficiency.

Следовательно, authoritative defender pool, characteristic/mastery source и применение `BlockProfile.efficiency` не подтверждены.

## Block layers и Character/NPC parity

Подтверждено:

- `GameStrikeLayers::blockId()` ищет ровно одну надетую inventory-row по `ruleCode` и integer `id`.
- `CharacterCombatLayers::project()` используется для обоих типов документов.
- Character документы берутся через `ICharacters::get()`.
- NPC документы берутся из `GameNpcRepository::getById()->getVersion()`.
- `CharacterCombatItemLayers::block()` добавляет defense/resistance из `BlockProfile`.
- `GameStrikeLayers::total()` применяет source collapse, damage-type filtering и reliability cut.

Не подтверждено:

- `GameStrikeLayers::total()` не проверяет attacker rating перед добавлением block layers.
- Поэтому текущий auto-fail всё ещё может читать block layers.
- `GameStrikeRules::assertBlock()` проверяет только weapon/shield, но не экипировку и не допустимость профиля защитником.
- `BlockProfile.efficiency` не применяется.
- Defender check отсутствует, поэтому Character/NPC parity существует только для projection, не для block roll.

## JSON и ошибки

Общий envelope задаётся `ActionResponse::toArray()`:

```json
{"success":true,"data":{}}
```

или:

```json
{"success":false,"data":null,"error":{"code":"GAME_INVALID","message":"..."}}
```

Текущий `1 → 1` результат формируется в `GameStrikes::close()`:

- `success` — текущий attacker rating;
- `damage`, `resistance`, `injury` — `{base,size}`;
- `sheetVersion`;
- `S` добавляется только для dodge.

`1 → N` результат формируется в `GameWideStrikeResults::row()`:

- `target`;
- `success`;
- `damage`;
- `resistance`;
- `injury`;
- `sheetVersion`;
- `code`;
- `S` только для dodge.

Ошибки:

- malformed input — `INVALID_PARAMS` (`GameStrikeBody`, `GameWideStrikeBody`);
- отсутствующая/множественная hit-check, битый pool, invalid block/projection — `GAME_INVALID`;
- stale battle/sheet version — `GAME_CONFLICT`;
- wide target-level refusal сохраняется в `targetResults[].code`, остальные цели не затираются.

Профильные тесты проверяют envelope, resistance, idempotency и target-level conflict, но не defender roll, block success/fail или auto-fail:

- `GameStrikeMysqlTest::testResolveRejectsHitSlice()`
- `GameStrikeMysqlTest::testResolveOmitsSoakUnlessDodge()`
- `GameWideStrikeMysqlTest::testTwoTargetsKeepSeparateResults()`
- `GameWideStrikeMysqlTest::testTargetsKeepOwnResistance()`

## Canonical owner

- `CheckSpec.hit_check` — Rule.
- `BlockProfile` data — Rule.
- Defender/attacker role orchestration, roll order, block branching, transaction and JSON — Game.
- Кубики, rate и roll mechanics — Mechanic.
- Sheet/choices и combat-layer projection — Character.
- Player actual / NPC version persistence — Character/Game NPC storage.

Главный owner неполной capability — Game, но он зависит от незакрытого Rule/Mechanic решения об efficiency и defender pool.

## Текущий gap

`game-plan-28` и `game-combat-readiness.md` корректно остаются `PARTIAL`:

1. defender check не реализован;
2. defender pool и characteristic/mastery source не имеют authoritative-контракта;
3. `BlockProfile.efficiency` не имеет семантики применения;
4. auto-fail не подавляет block projection;
5. block fail/success не разделяются;
6. `success = 1` при успешном block отсутствует;
7. eligibility/action-point spend блока не подтверждены;
8. нет сквозных тестов полной block-ветки.

## Prerequisite-plan

До любого implementation work необходимо зафиксировать межмодульный prerequisite.

**Owner:** Game совместно с Rule, Mechanic и Character.

Acceptance criteria:

- существующая единственная `CheckSpec` с `hit_check`, без нового marker/card/rule code;
- явно определён источник defender sheet/choices для Character и NPC;
- явно определены pool, characteristic, mastery и порядок применения modifiers;
- явно определено, как `BlockProfile.efficiency` влияет на defender roll и как dimensional value преобразуется в roll efficiency;
- `1 → 1`: два броска, attacker затем defender;
- `1 → N`: один attacker roll, затем независимый defender roll каждой цели;
- attacker rating `{0|-1}` не запускает defender roll и block projection;
- defender fail сохраняет обычный attacker rating;
- defender success даёт `success = 1` и добавляет существующие block layers;
- non-block не добавляет block layers;
- Character и NPC проходят одинаковую семантику;
- JSON использует существующие поля и существующие коды ошибок, без новых markers/cards;
- eligibility и списание ОД блока имеют отдельный authoritative owner и acceptance criteria.

## Baseline verdict

**BLOCKED at audit time**

На момент исходного аудита контракт defender pool и применение
`BlockProfile.efficiency` не были подтверждены. Этот вывод относится к
baseline, а не к текущему P3 correction pass.
