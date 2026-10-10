# Audit 9 — authoritative penetration pipeline

Дата: 2026-10-08

## Baseline

- `HEAD`: `26048f3778555fbf4646c7eb861ce92fd7b23c7f`
  (`26048f3 Обновить frontend боевого контура`).
- Tracked/staged diff отсутствует на момент подготовки плана.
- Существующие untracked-файлы не изменяются.
- PHPUnit/MySQL, frontend dev-server и frontend-формула не являются частью
  этого плана.

Это план backend-среза penetration, а не закрытие readiness Gate P4 из
`game-combat-readiness.md` (Gate P4 требует реального frontend → PHP →
delivery E2E).

## Владельцы

- Rule:
  - хранит `WeaponProfile::$penetration`;
  - парсит `ItemWeapons::profile()`;
  - применяет `action_strength → field=penetration`;
  - определяет merge нескольких modifier operations.
- Character:
  - разрешает выбранный inventory instance;
  - применяет его modifiers ровно один раз;
  - возвращает typed effective item context;
  - строит `FormulaContext` атакующего.
- Game:
  - выбирает inventory instance и профиль;
  - проверяет актуальность attacker context;
  - применяет evaluated penetration к итоговой resistance;
  - формирует JSON и выполняет replay/stale/conflict orchestration.
- Mechanic:
  - только reliability capability; penetration к Mechanic не переносится.
- `GameStrikeLayers`:
  - фильтр damage type, reliability и source collapse.
- `GameStrikeAmounts`:
  - dimensional subtraction и zero-floor.

## Backend contract

### Attack input

Добавить обязательный `itemInventoryId` в `GameStrikeBody` и
`GameWideStrikeBody`.

`itemRuleCode` можно временно сохранить в transport contract, но authoritative
selector — `itemInventoryId`. Backend обязан проверить:

- строка существует в `choices.inventory`;
- строка `equipped === true`;
- `ruleCode` совпадает с заявленным `itemRuleCode`;
- выбранный item — живой `ItemSpec`;
- выбранный `profileType/profileIndex` существует в effective item;
- custom/неэкипированный/неизвестный item отклоняется.

Готовые `damage`, `penetration`, `resistance`, `injury` во входе запрещены.

### Effective item context

Добавить Character-порт:

- `Character/Interface/Service/ICharacterCombatItems.php`;
- `Character/Dto/CharacterCombatItemContext.php`;
- `Character/Service/CharacterCombatItems.php`;
- `Character/Service/CharacterCombatItemPortFactory.php`;
- регистрацию в `Character/module.config.php`.

Контекст содержит только typed runtime data:

- `inventoryId`;
- `ruleCode`;
- effective `ItemSpec`.

`CharacterCombatItems` переиспользует текущую логику
`CharacterCombatItemLayers`:

```text
live ItemSpec
→ modifiers из choices.inventory[id].modifiers
→ ItemModifierOperations::apply()
→ effective ItemSpec
```

Game не вызывает `ItemModifierOperations` напрямую и не читает
frontend-модель.

### Formula context

Расширить `ICharacterFormulaContexts::build()` и `buildStored()` optional
action-characteristic map.

Для выбранного effective profile Game:

1. строит базовый context атакующего;
2. оценивает `ActionCharacteristicBase`;
3. передаёт результат в enriched `FormulaContext`;
4. через `IFormulaEvaluations::evaluateDimensional()` оценивает damage и
   penetration.

`CharacterFormulaContexts` не вычисляет penetration и не знает о профиле
оружия.

### Arithmetic order

Authoritative порядок:

```text
effective attacker item
→ evaluate damage/penetration
→ resolve hit/block outcome
→ project defender layers
→ reliability cut
→ damage-type filtering
→ defense_ignored filtering
→ source collapse
→ raw resistance
→ clamp negative penetration to zero
→ raw resistance - effective penetration
→ floor effective resistance at zero
→ injury / soak
```

Penetration не участвует в source collapse.

Пример:

```text
armor defense 3
armor typed resistance 6
body defense 2
collapse: max(3, 6) + 2 = 8
penetration 4
effective resistance = 4
damage 10 → injury basis 6
```

### Floor, zero, negative

- Размерные значения выравниваются по меньшему `size`; scalar conversion не
  используется.
- `penetration = 0` — no-op.
- Отрицательный evaluated penetration нормализуется к нулю.
- Effective resistance ниже нуля нормализуется к нулю.
- `rawResistance` может остаться отрицательным для диагностики.
- Existing `GameStrikeAmounts::injury()` продолжает применять собственный
  final zero-floor.
- Penetration не меняет damage напрямую.

### `defense_ignored`

Сохраняется текущий backend damage-type contract:

- `defense_ignored=true` исключает слои `kind=defense`;
- typed `resistance` остаётся применимой по `damageTypeCode`;
- после этого penetration применяется к оставшейся effective resistance;
- block defense также исключается этим правилом, block resistance — нет.

Не вводить новую frontend-семантику и не сравнивать коды типов вручную вне
`DamageTypeSpec`.

### Multiple modifiers

Для penetration действует существующая Rule merge-семантика:

- один непустой `source` — сильнейший положительный и сильнейший отрицательный
  delta;
- разные sources складываются;
- пустой source — уникальный;
- `field=damage` и `field=penetration` никогда не смешиваются;
- selectors `profiles` и `damage_type_codes` сохраняются независимо.

Исправить группировочный ключ `ItemModifierOperationActions::key()` так,
чтобы он учитывал нормализованный `damageTypeCodes` и порядок selector-ов не
менял результат.

## JSON contract

Не сериализовать formula AST или raw `ItemSpec`.

Для `1 → 1` результат содержит:

- `damage` — evaluated damage;
- `penetration` — raw evaluated penetration;
- `effectivePenetration` — penetration после zero-floor;
- `rawResistance` — resistance после filtering/collapse, до penetration;
- `resistance` — effective resistance после penetration;
- `injury`.

Для `1 → N`:

- penetration-поля общие на уровне результата атаки;
- `rawResistance` и `resistance` — внутри каждого `targetResults[]`;
- target refusal сохраняет все числовые поля `null`.

## Replay и stale/conflict semantics

При declaration сохраняются:

- `attacker_item_inventory_id`;
- `attacker_actual_version`;
- `item_rule_code`;
- profile selection.

Полный sheet/choices в Game не сохраняются.

При resolve:

- attacker version должна совпадать с сохранённой;
- stale attacker даёт `GAME_CONFLICT`;
- `1 → 1`: strike остаётся open, mutation и command result не создаются;
- `1 → N`: весь resolve отклоняется, target results не применяются;
- stale defender в wide strike остаётся target-level `GAME_CONFLICT`,
  остальные цели продолжают обработку;
- malformed effective item/profile/formula даёт `GAME_INVALID`;
- неперехваченная ошибка projection/mutation откатывает весь wide
  transaction.

Replay:

- тот же idempotency key и canonical body возвращает сохранённый JSON без
  повторного расчёта;
- тот же key с другим `itemInventoryId`, profile или body даёт
  `GAME_CONFLICT`;
- replay не повторяет mutation;
- stale failure не создаёт replay record.

## Планируемые файлы

Rule:

- `Rule/Spec/ItemModifierOperationActions.php`;
- `Rule/tests/ItemModifierOperationTest.php`.

Character:

- новый effective-item DTO/порт/service/factory;
- `CharacterCombatItemLayers.php`;
- `CharacterCombatLayers.php`;
- `CharacterFormulaContexts.php`;
- `ICharacterFormulaContexts.php`;
- `Character/module.config.php`;
- unit/port tests.

Game:

- `GameStrikeBody.php`;
- `GameWideStrikeBody.php`;
- `GameStrikeOpening.php`;
- `GameWideStrikeOpening.php`;
- `GameStrikeTable.php`;
- `GameWideStrikeTable.php`;
- `GameStrikeRules.php`;
- `GameStrikeLayers.php`;
- `GameStrikeAmounts.php`;
- `GameStrikes.php`;
- `GameWideStrikes.php`;
- `GameWideStrikeResults.php`;
- `GameStrikePortFactory.php`;
- `GameWideStrikePortFactory.php`;
- `GameStrikeMysqlTest.php`;
- `GameWideStrikeMysqlTest.php`;
- новые unit-тесты dimensional arithmetic/layer collapse.

`ItemWeapons.php` и `WeaponProfile.php` менять не требуется: их typed Rule
contract уже существует.

## Acceptance tests

1. Effective inventory instance с modifier penetration изменяет penetration,
   но не damage formula.
2. Два экземпляра одного `ruleCode` используют modifiers только выбранного
   `itemInventoryId`.
3. Неэкипированный, custom или отсутствующий instance отклоняется.
4. Formula context корректно обрабатывает характеристику, ability level и
   action-characteristic bases.
5. Один source с `+3` и `+1` даёт `+3`.
6. Разные sources складываются.
7. Positive и negative modifier одного source merge-ятся по установленному
   правилу.
8. Selector по `damage_type_codes` не влияет на другой damage type.
9. Source collapse выполняется до penetration.
10. Несколько defense/resistance sources дают ожидаемый collapse.
11. Пример `3 + 6 + 2`, penetration `4`, damage `10` даёт raw resistance
    `8`, effective resistance `4`, injury basis `6`.
12. `defense_ignored` исключает defense, но сохраняет typed resistance.
13. Reliability cut выполняется до source collapse; penetration не влияет на
    durability threshold.
14. Penetration больше resistance даёт effective resistance `0`.
15. Отрицательный penetration становится `effectivePenetration=0`.
16. Нулевая penetration не меняет resistance.
17. Damage JSON не меняется при изменении penetration; меняются только
    resistance/injury.
18. `1 → N` использует одну evaluated penetration, но отдельные
    raw/effective resistance для каждой цели.
19. Replay возвращает идентичный сохранённый результат без второй mutation.
20. Stale attacker inventory/version даёт `GAME_CONFLICT` и не закрывает
    strike.
21. Target-level stale в wide strike не затирает результаты других целей.
22. Hard failure после записи одной wide target откатывает весь wide
    transaction.

План не включает defender roll, полную block-ветку, action-point
eligibility/spend, production reliability handler, `throw/shoot`, frontend и
readiness E2E Gate P4.
