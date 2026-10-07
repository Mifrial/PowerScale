# План Rule — разделить уже существующий evaluate

**Статус:** правка в коде, 2026-10-05. Нарезка в roadmap по-прежнему не вписана. Нарезка в [`rule-roadmap.md`](rule-roadmap.md) не вписывается. [`rule-plan-03.md`](rule-plan-03.md) и [`game-plan-20.md`](game-plan-20.md) не переписываются. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Сейчас `IFormulaEvaluations::evaluate(ScalarFormula|DimensionalFormula, FormulaContext): int` в `FormulaEvaluations` делает две разные вещи. Скаляр считает `int` в `evaluateScalar`. Размерный узел считает пару в `walkDimensional` и тут же теряет её через `toInteger()`. Единственный вызов снаружи — `GameStrikeRules::evaluateDamage`, строка с `->evaluate(...)`: формула урона уже `DimensionalFormula`, метод возвращает `int`.

Правка: убрать общий метод и вынести наружу те две ветки, которые уже есть. Новых веток, порта и обхода нет.

## Правки

### 1. `IFormulaEvaluations`

Удалить `evaluate`. На его месте два метода.

`evaluateScalar(ScalarFormula $node, FormulaContext $context): int`

`evaluateDimensional(DimensionalFormula $node, FormulaContext $context): DimensionalNumber`

### 2. `FormulaEvaluations`

Публичный `evaluate` удалить.

Приватный `evaluateScalar` становится публичным методом интерфейса. Тело не меняется: `FixedScalar`, параметр, уровень, размер, `to_scalar`.

`evaluateDimensional` — публичная оболочка уже существующего `walkDimensional`. Возвращает его `DimensionalNumber`. Вызова `toInteger()` в классе не остаётся.

`walkDimensional`, `walkCharacteristic`, `walkAction`, `shiftCharacteristic`, `mediumBase`, `floorQuotient` остаются. Неизвестный узел, нет характеристики, нет параметра, делитель 0 — те же `RULE_INVALID`. Нулевые края скаляра те же.

### 3. `GameStrikeRules::evaluateDamage`

Заменить вызов `evaluate` на `evaluateDimensional(...)->toInteger()`. Подпись `evaluateDamage(): int` не меняется. Остальной расчёт удара, фабрики портов и `module.config.php` не трогать.

### 4. `FormulaEvaluationTest`

Каждый `evaluate` скалярного узла заменить на `evaluateScalar`, ожидаемые целые не менять. Каждый `evaluate` размерного узла заменить на проверку пары из `evaluateDimensional`: `getBase()` и `getSize()` такие, какие узел уже строит; `toInteger()` этой пары равен целому, которое тест ждал раньше. Новых узлов не добавлять.

## Чего нет

Сопротивление, смягчение, injury, состояния, лист. Новый расчёт удара. Правка Character. Правка `rule-roadmap.md`, `rule-plan-01.md` … `rule-plan-04.md`, `game-plan-20.md`.
