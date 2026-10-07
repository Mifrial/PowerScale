# План Rule — деление размерного числа на размерное

**Статус:** сделано, 2026-10-06. В [`rule-roadmap.md`](rule-roadmap.md) шаг не вписывается. [`rule-plan-01.md`](rule-plan-01.md) … [`rule-plan-04.md`](rule-plan-04.md) не переписываются. Канон чисел — [`rule-system.md`](rule-system.md): размер считает домен, не строковая арифметика. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Сейчас у `DimensionalNumber` есть `toInteger()` и `shift()`. Метода, который делит пару `{base, size}` на другую пару и возвращает частное вместе с остатком-парой, нет. Vue `divideFloor` отдаёт только целое и в этот шаг не копируется.

Цель: один метод на `DimensionalNumber`. Character и Game не меняются.

## Решения

### 1. Метод

`DimensionalNumber::divide(DimensionalNumber $divisor): DimensionalQuotient`.

Делимое — сам объект. Делитель — вторая пара. `DimensionalQuotient` в `Value/`: `getQuotient(): int` и `getRemainder(): DimensionalNumber`. Порта, фабрики и HTTP нет.

Выравнивание по меньшему размеру — то же, что у сложения и вычитания в [`rule-system.md`](rule-system.md): обе базы приводятся к `min(size)`, сдвиг размера неотрицательный. Для неотрицательных баз частное — целая часть этого отношения, остаток — недоделённая база на том же общем размере.

`{7|1}` разделить на `{3|1}` даёт частное 2 и остаток `{1|1}`. Отрицательный размер этим правилом уже покрыт: разность размеров после выравнивания не меньше нуля. База меньше 0 в текущих правилах не используется, но метод её не пропускает: база делимого или делителя меньше 0 — `RuleInvalidException` (`RULE_INVALID`). Знак частного для отрицательной базы не вычисляется.

Степень двойки — целый сдвиг базы, не `toInteger()` и не `2 **`. `toInteger()` уже считает через float и остаток не хранит. Если сдвинутая база не остаётся в диапазоне `int`, тот же `RULE_INVALID`: ни float, ни строка.

### 2. Ноль

База делителя `0` — значение нуль при любом размере. Метод бросает `RuleInvalidException` (`RULE_INVALID`). Бесконечности нет. Частного 0 с исходным делимым в остатке нет.

## Тест

`www/mifrial/modules/Roleplay/Rule/tests/DimensionalNumberTest.php`, `PHPUnit\Framework\TestCase`, без MySQL — как `RuleUnitTest`. Сценарии: `{7|1}` / `{3|1}` → частное 2, остаток `getBase() === 1` и `getSize() === 1`; `{7|0}` / `{3|1}` → частное 1, остаток `{1|0}` — размеры разные, сдвиг не нулевой; делитель с базой 0 и любая база меньше 0 → `RuleInvalidException`, `getErrorCode() === 'RULE_INVALID'`. Миграции нет: у `Value/` нет таблицы.

## Чего нет

Удар, стойкость, состояния, `putState`, лист. Правка `FormulaEvaluations`, Character и Game. Строка в `rule-roadmap.md`.
