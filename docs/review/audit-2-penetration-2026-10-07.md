# Audit 2 — authoritative-контракт penetration

Дата: 2026-10-07

## Verdict: `BLOCKED`

Authoritative arithmetic/application-контракт penetration не принят полностью.
Implementation plan для неподтверждённого контракта не составляется.

## Git state на момент аудита

Tracked worktree diff и staged diff отсутствуют.

Untracked:

- `tools/review/__pycache__/`
- `www/mifrial/var/`

PHPUnit/MySQL и frontend dev-server не запускались.

## 1. Подтверждённый контракт

Penetration хранится:

- в JSON Rule: `item.weapon.weapon_profiles[].penetration`;
- в PHP как `WeaponProfile::$penetration`;
- тип значения — `DimensionalFormula`, не готовое число;
- отдельной колонки Character/Game для penetration нет.

Evidence:

- `www/mifrial/modules/Roleplay/Rule/Spec/ItemWeapons.php::profile()` читает
  `penetration` через `Formulas::dimensional()`;
- `www/mifrial/modules/Roleplay/Rule/Dto/Spec/Item/WeaponProfile.php::__construct()`
  принимает `DimensionalFormula $penetration`;
- `WeaponProfile::getPenetration()` возвращает формулу.

Семантически penetration должен уменьшать только defense, не typed resistance
и не damage. Это зафиксировано в:

- `docs/tr/game-plan-28.md`, раздел «Что остаётся для завершения P1»;
- `docs/tr/combat-layers-prerequisite.md`, приёмка будущего шага;
- `docs/tr/game-combat-readiness.md`, раздел защиты и сопротивления.

## 2. Item modifiers

Подтверждённая форма:

```text
item modifier operation
→ type: action_strength
→ field: penetration
→ delta
→ profiles / damage_type_codes
→ source_code
```

Evidence:

- `Rule/Spec/ItemModifiers.php::byType()` / `rest()` разбирает
  `action_strength`;
- `Rule/Dto/Spec/Item/Op/ActionStrengthOp`;
- `Rule/Spec/ItemModifierOperations::apply()` применяет операции к копии
  `ItemSpec`;
- `Rule/Spec/ItemModifierOperationActions::collect()` собирает
  action-операции;
- `Rule/Spec/ItemModifierOperationItems::profile()` при
  `field === 'penetration'` меняет только penetration, а при
  `field === 'damage'` — только damage;
- `Rule/tests/ItemModifierOperationTest.php::testActionStrengthPenetrationDoesNotChangeDamage()`
  подтверждает, что penetration получает modifier, а damage formula остаётся
  без изменений.

Дополнительный незакрытый edge: `ItemModifierOperationActions::key()`
группирует операции по `source`, `field` и `profiles`, но не учитывает
`damageTypeCodes`. Каноническое поведение для конфликтующих selectors типов
урона не подтверждено.

## 3. Canonical owner

- Форма, хранение и применение item modifier operations — Rule.
- Оценка формулы — Rule:
  `IFormulaEvaluations::evaluateDimensional()`,
  реализация `FormulaEvaluations`.
- Контекст характеристик атакующего — Character:
  `ICharacterFormulaContexts`, `CharacterFormulaContexts::build()` /
  `buildStored()`.
- Проекция защитных слоёв цели — Character:
  `ICharacterCombatLayers::project()`,
  `CharacterCombatLayers::project()`.
- Применение penetration к defense и расчёт боевого результата — Game.

Game evidence:

- `GameStrikeRules::evaluateDamage()`;
- `GameStrikeRules::evaluateResistance()`;
- `GameStrikeLayers::total()`;
- `GameStrikes::close()`;
- `GameWideStrikeResults::one()`.

Rule не должен собирать слои цели и считать полную атаку — это прямо указано
в `docs/tr/rule-plan-07.md`.

## 4. Текущий gap

В authoritative PHP penetration фактически не применяется:

- `GameStrikeRules::profile()` получает raw `ItemSpec` из Rule-среза;
- `GameStrikeRules::evaluateDamage()` оценивает только
  `getDamage()->getFormula()`;
- `GameStrikeRules::evaluateResistance()` не получает penetration;
- `GameStrikeLayers::total()` суммирует resistance layers без penetration;
- `GameStrikeAmounts::injury()` получает только
  `damage`, `resistance`, `success`, `soak`.

Game также не применяет modifiers атакующего оружия:

- `GameStrikeBody::attack()` принимает только `itemRuleCode` и профиль;
- attacker inventory/modifier choices в Game strike path не передаются;
- `ItemModifierOperations::apply()` вызывается в
  `CharacterCombatItemLayers` только для защитной проекции цели.

Следовательно, подтверждённый Rule-level modifier penetration сейчас не
доходит до authoritative Game strike.

## 5. Dimensional, floor, zero и negative

Подтверждено:

- dimensional values представлены как `{base, size}`;
- `docs/tr/rule-system.md` разрешает отрицательный результат на value-уровне;
- сложение и вычитание выравниваются по меньшему `size`;
- `GameStrikeAmounts::injury()` применяет `floorAtZero()` к итоговому injury.

Не подтверждено:

- как выравнивать penetration с defense в authoritative Game path;
- разрешены ли отрицательные значения penetration;
- должен ли penetration нормализоваться к нулю;
- должен ли floor применяться к effective defense;
- порядок penetration относительно source collapse, reliability cut и block
  layers.

Frontend `AttackDamageService::penetrationOf()` и `applyAttackDamage()`
используют scalar conversion и `Math.max(0, defense - penetration)`, но это
не authoritative-контракт и автоматически переносить его нельзя.

## 6. JSON-контракт

Подтверждено:

- penetration приходит из Rule spec как формула;
- клиент не может передать готовый penetration/damage:
  `GameStrikeBody::attack()` и `GameWideStrikeBody::attack()` принимают
  фиксированный набор ключей;
- текущий результат содержит размерные `damage`, `resistance`, `injury`;
  penetration в JSON результата отсутствует.

Не подтверждено, должен ли вычисленный penetration появляться в response.
Добавлять этот ключ по frontend-модели нельзя.

## 7. Гарантии неизменности

Подтверждена только Rule-level гарантия:

- penetration modifier не изменяет damage formula —
  `testActionStrengthPenetrationDoesNotChangeDamage()`.

Не подтверждено Game-level:

- damage остаётся неизменным при применении penetration;
- typed resistance остаётся неизменным;
- изменяется только defense component;
- исходный resistance JSON не подменяется effective resistance.

## 8. Зависимости

Необходимы решения по:

- источнику attacker item modifiers;
- вычислению penetration через существующие
  `ICharacterFormulaContexts` и `IFormulaEvaluations`;
- dimensional subtraction/alignment;
- отрицательным значениям и zero-floor;
- порядку относительно block, reliability cut и source collapse;
- сохранению damage и typed resistance;
- наличию или отсутствию penetration в response JSON;
- Game-тестам для `1 → 1`, `1 → N`, `defense_ignored` и modifier path.

Новые таблицы или колонки пока не обоснованы. Новые DTO/порты также нельзя
назначать до принятия этих решений.

## 9. Отдельный prerequisite-plan

1. Зафиксировать authoritative arithmetic contract penetration.
2. Зафиксировать authoritative источник применённых modifiers атакующего.
3. Зафиксировать порядок penetration относительно защитных слоёв.
4. Зафиксировать negative/zero/floor и dimensional JSON round-trip.
5. Зафиксировать invariants: damage и typed resistance не меняются.
6. Только после этого принять продолжение P1 в `game-plan-28` и определить
   необходимые тестовые доказательства.

До выполнения этих prerequisite контракт неполон.

## Итог

`BLOCKED`
