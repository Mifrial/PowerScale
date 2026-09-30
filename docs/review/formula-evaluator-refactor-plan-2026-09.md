# План переработки Formula и единого FormulaEvaluator

**Статус:** готов к билду по §10. Разделы 3 и 5 — история обследования, не очередь работ.  
**Дата:** 2026-09-30  
**Ограничение:** этот документ описывает будущую реализацию. Сам по себе он не
разрешает менять production code, tests, schema или canonical docs.

Семантика записана в §7 и поправлена в §10. Раздельный evaluator, kind поля,
множитель силы действия с сохранением размера и размерные дистанция/дальность
в коде уже есть. Не закрыты тихие подмены вне `switch`, дыра размерного
`parameter` и неполная validation.

## 1. Цель

Устранить разрозненные реализации вычисления Formula и сделать контракт
формул пригодным для наполнения базы правил без изменения кода под каждое
новое правило.

Целевая модель должна:

- иметь один владелец вычисления Formula;
- различать scalar и dimensional result по контракту поля;
- использовать каталог узлов без общего `modify`/`add` и без скалярной
  подформулы внутри размерной Formula; единственное вложение — `to_scalar`;
- ограничивать не наборы правил вручную в каждом сервисе, а только
  математически несовместимые result kinds;
- валидировать Formula при сохранении;
- не превращать неизвестную или несовместимую Formula в молчаливый `0`;
- сохранять возможность добавлять новые правила через editor/data;
- не привязывать runtime к конкретным rule ids/codes/names.

## 2. Зафиксированная модель

Решения ниже закрыты. Evidence и формулировки — в §7.

### 2.1 Result kind

Ожидаемый результат — свойство поля, не узла и не отдельной версии payload:

- `scalar` — обычное число;
- `dimensional` — `DimensionalNumberValue`.

Старый `{ type: "fixed", value: N }` читается по контракту поля (§7.5).
Дистанция и дальность размерные: обзор и карточка оружия вызывают
`evaluateDimensional`. Обзор затем переводит результат в число через
`toNumber()` для досягаемости и штрафа дистанции.

### 2.2 Modifier

Общий узел `modify(base, scalar)` не вводится.

- `characteristic.modifier` — целое число пунктов шкалы характеристики;
- `actionCharacteristic.modifier` — цепочка таких же пунктов, затем опциональный
  `multiplier` базы (§7.6);
- поправка ресурса — скалярная дельта к `base.base`, её применяет ресурсный
  сервис;
- `ability_level.multiplier` — скалярное `level * multiplier + offset`, это не
  умножение базы размерного числа.

### 2.3 Композиция

Общего вложения нет. Единственное допустимое вложение — скалярный узел
`to_scalar`, внутри которого лежит `DimensionalFormula`. Он нужен скалярным
полям, которым нужна база характеристики в среднем размере, а не `toNumber()`.
Подтверждённый consumer — `magic_study.max_cost` у
`magic-structure-construction`: база Интеллекта.

Скаляр внутри размерной Formula не добавляется. Сложение размерных значений
остаётся в movement AST. Неявный `toNumber()` запрещён.

### 2.4 Fixed

Один JSON-узел `{ type: "fixed", value: N }`. Kind задаёт поле:

- скалярные поля оставляют число;
- размерные поля читают его как `{ base: N, size: 0 }`.

Неявная подмена одного kind другим запрещена.

### 2.5 Parameter

`parameter` — ссылка на именованный вход способности, а не третий result kind.
Каждый параметр строго scalar или строго dimensional. Kind объявляется явно в
описании параметра и не выводится из формы `default`. `resolution` задаёт только
момент выбора: purchase или activation.

- scalar Formula ссылается только на scalar parameter;
- dimensional Formula на parameter не ссылается (§4.2, §10.3);
- `per_unit` есть только у скалярного параметра;
- несовместимая ссылка — ошибка спеки.

### 2.6 Action characteristic

`actionCharacteristic` возвращает размерную силу действия. Дистанция и
дальность — тоже размерные величины; игроку их обычно показывают в среднем
размере.

Поле `multiplier` есть в спеках дистанции и дальности: ручной арбалет
умножает силу выстрела на 10, луки — на 2, 3 или 5. Умножение размерного
значения меняет только базу и сохраняет размер: `{4|2} * 5 = {20|2}`.
Evaluator уже считает так. В этом билде множитель не переписывать.

### 2.7 Resources

Resource — не отдельный result kind Formula. `is_dimensional` описывает базу
ресурса, а не формулу поправки.

- база ресурса — число или размерное значение согласно `is_dimensional`;
- `limit.adjustments[].value` и `resource_limit_change.amount` — скалярные
  дельты, которые прибавляются к `base.base`; `size` базы сохраняется;
- размерная Formula в поле поправки — ошибка спеки.

## 3. Текущее состояние, которое нужно исследовать

### 3.1 Общий Formula union

Текущий `Formula` содержит, среди прочего:

- `fixed`;
- `characteristic`;
- `ability_level`;
- `dimensional`;
- `parameter`;
- `parameter_floor_div`;
- `characteristic_size`;
- `characteristic_size_positive`;
- `characteristic_size_gap`;
- `actionCharacteristic`.

Нужно классифицировать каждый node по:

- result kind;
- необходимому context;
- сериализуемому DTO;
- editor support;
- runtime consumers.

### 3.2 Разрозненные evaluator-ы

Проверить и сопоставить:

- `FormulaEvaluationService`;
- `AbilityCheckAdvantagesService.formulaValue()`;
- `EditorCheckBonusesService.formulaValue()`;
- все остальные методы, содержащие собственный `formulaValue`,
  `evaluateFormula`, `resolveFormula` или эквивалентную логику.

Отдельно выяснить, где молчаливый fallback `0` означает:

- реальный нулевой результат;
- неподдержанный Formula;
- отсутствующий вход;
- ошибку данных.

### 3.3 Formula consumers

Составить полный список полей и ожидаемых result kinds:

- `ResourceSpec`;
- `ResourceLimitAdjustment`;
- `CharacteristicLimit`;
- `WeaponProfile.distance`;
- `WeaponProfile.range`;
- `WeaponProfile.damage.formula`;
- `WeaponProfile.penetration`;
- `ActionCharacteristicValue.value`;
- `Grant.characteristic_modify.amount`;
- `Grant.resource_limit_change.amount`;
- `Grant.resistance.value`;
- `Grant.sense_modify.amount`;
- `Grant.state_modify.amount`;
- `Grant.magic_study.max_cost`;
- spell power/control и другие Formula-подобные поля;
- editor-only и migration-only поля.

Для каждого consumer создать запись:

```text
field
expected result kind
allowed node types
context providers
missing input policy
validation owner
runtime owner
editor/view owner
existing tests
```

### 3.4 Formula-like структуры, которые не следует автоматически объединять

Inventory должен отдельно классифицировать структуры, уже имеющие собственный
expression/value contract:

- `SpellValue` для spell power/control и sustained power:
  `Rule/Dto/Ability/SpellValue.ts`,
  `Game/Service/SpellCastDifficultyService.ts`;
- строковые derived-characteristic formulas `min/max`:
  `Rule/Dto/CharacteristicSpec.ts`,
  `Rule/Service/DerivedCharacteristicService.ts`;
- recursive `MovementDistanceExpression` с `add` и `change_size`:
  `Rule/Dto/Ability/MovementDistanceExpression.ts`,
  `Rule/Service/MovementDistanceExpressionService.ts`;
- `StateDecay`, где editor поддерживает characteristic/check, а
  `Game/Service/DotTickMathService.ts` реализует только fixed/dimensional;
- scalar/dimensional/chosen `ActionCostAmount`;
- numeric `StateEffect` fields и dimensional state magnitude.

Эти структуры остаются отдельными языками (§7.1). Похожее поле или слово
`formula` не переносит их в FormulaEvaluator.

### 3.5 Подтверждённые current-state gaps

Сверено с кодом 2026-09-30. Закрыто и не является оставшейся работой:

- `FormulaEvaluationService` разделяет `evaluateScalar` и `evaluateDimensional`;
  `evaluate()` больше не проецирует размерный результат;
- `actionCharacteristic.multiplier` умножает `base` и сохраняет `size`;
- локальные `formulaValue()` вызывают `evaluate()` и не содержат собственного
  `switch`;
- `DIMENSIONAL_FORMULA_MODES` уже не предлагает узел `parameter`;
- `RuleValidationService.walkFormula` собирает ссылки характеристик,
  `characteristic_size*`, `ability_level`, `actionCharacteristic` и обходит
  `to_scalar`.

Остаётся:

- `DimensionalFormula` всё ещё содержит `{ type: "parameter" }`, а evaluator
  на нём только бросает ошибку «отдельный контракт»;
- `resolveGrant` заранее заменяет `characteristic_modify` с `parameter` и
  `parameter_floor_div` на `fixed`; делитель `0` становится `1`;
  отсутствующий параметр становится `1` через `toNumber()`;
- check-адаптеры читают `.base` размерного параметра и передают пустую карту
  характеристик, поэтому `characteristic_size` там даёт `0`;
- `characteristic_size`, `characteristic_size_positive` и
  `characteristic_size_gap` при отсутствии характеристики возвращают `0`;
- `formulaContext` сборки персонажа не передаёт `parameterValues`: параметры
  живут на экземпляре способности и подставляются в `resolveGrant` до
  callback;
- `MagicStudyUnlockService.maxCostOf` без snapshot возвращает `0` для
  формульного `max_cost`;
- `FormulaValidationService` проверяет kind поля, делитель и kind скалярного
  параметра, но не ссылки; ссылки проверяет `RuleValidationService`, без
  structured Formula error;
- PHP Rule по-прежнему хранит `spec` как JSON без Formula semantics.

## 4. Целевая архитектура

### 4.1 Data model

Поля типизируются как `ScalarFormula` или `DimensionalFormula`. JSON остаётся
дискриминированным по `type`. Отдельная версия Formula, параметризованный
`Formula<Kind>` и пара wire AST / validated AST не вводятся (§7.15, §7.17).

Объявление параметра получает явное поле kind: `scalar | dimensional`. Форма
`default`, `min` и `max` это поле не заменяет.

### 4.2 Expression nodes

Минимально проверить необходимость следующих узлов:

```text
ScalarFormula:
  fixed
  parameter
  parameter_floor_div
  ability_level
  to_scalar          # value: DimensionalFormula
  characteristic_size
  characteristic_size_positive
  characteristic_size_gap

DimensionalFormula:
  fixed
  dimensional
  characteristic
  actionCharacteristic
```

Общий узел `modify` не входит в каталог (§7.7). `to_scalar` входит (§2.3).
`actionCharacteristic.multiplier` умножает базу и сохраняет размер (§7.6).
Отдельный оператор умножения для этого не нужен.

Узел `{ type: "parameter" }` в размерной Formula не вычисляется: мощь
заклинания остаётся на `SpellValue`, а «дать характеристику параметром» —
грант `characteristic_parameter`, не Formula. Пока нет потребителя Formula
с размерным параметром, узел в каталог не входит. Добавление его позже
требует evaluator, validation и editor вместе.

Если текущие игровые операции требуют сложения размерных значений,
пересмотра размера или другого действия, добавить их только после
подтверждения реальным consumer-ом и каноном.

### 4.3 FormulaEvaluator

Центральный evaluator должен:

- иметь один владелец evaluation semantics;
- вычислять узлы текущего каталога, без общего рекурсивного вложения Formula;
- принимать typed evaluation context;
- возвращать typed value по контракту поля;
- отличать invalid formula от zero result;
- не зависеть от конкретного правила;
- не получать целый глобальный service locator;
- не принимать неограниченный raw `CharacterVersion`, если нужен узкий
  context provider.

Контекст предоставляет lookup-и, которые читают узлы Formula:

- characteristic values;
- ability levels;
- parameter values с kind из объявления параметра;
- action characteristic base.

Значения ресурсов в контекст Formula не входят: дельту применяет ресурсный
сервис.

### 4.4 Validation

При сохранении правила validator обязан проверить:

- известен ли node type;
- корректна ли структура payload;
- есть ли referenced characteristic/ability/parameter;
- совместим ли объявленный kind параметра;
- совпадает ли ожидаемый result kind поля;
- корректны ли divisor, multiplier и другие числовые ограничения;
- не образует ли Formula циклическую ссылку, если такие ссылки возможны.

Ошибка сохранения остаётся записью `{ ruleCode, ruleName, message }`,
которую уже возвращает `FormulaValidationService`. Отдельный объект с
`stage` и `actualResultKind` в этом билде не вводится. Runtime по-прежнему
бросает `Error` с текстом; тесты узлов проверяют текст, не новый класс.

Runtime может повторять защитную проверку, но не должен быть единственным
местом обнаружения ошибки.

### 4.5 Editor

Редактор должен получать schema/capability contract поля и показывать
только математически совместимые варианты:

- scalar field → узлы ScalarFormula;
- dimensional field → узлы DimensionalFormula;
- parameter selector → параметры объявленного совместимого kind.

Это ограничение не должно быть списком конкретных rule ids/codes. Новая
формула должна быть доступна через data-driven editor, если она соответствует
общему Formula contract.

## 5. Поэтапный план реализации

Фазы 0–7 описывают обследование, которое уже сделано. Типы, kind параметра,
раздельный evaluator, множитель с сохранением размера и режимы редактора
в коде есть. Билд выполняет только §10 и не повторяет эти фазы.

### Phase 0 — contract inventory

- собрать все Formula DTO fields;
- собрать всех evaluator consumers;
- построить matrix field → kind → context → validation;
- найти Formula-like структуры, не использующие Formula;
- определить compatibility с backend Rule DTO и `mechanicPayload`;
- отдельно inventory parameter purchase/activation flows.

Deliverable: reviewed Formula contract matrix.

### Phase 0.5 — inventory перед кодом

Семантика закрыта в §7. Этот этап только собирает матрицу потребителей и
список JSON, который маппится по пути поля. Новых решений он не принимает.

- собрать matrix field → kind → allowed nodes → context → missing-input policy;
- отдельно отметить `CharacterOverviewService`: дистанция и дальность сейчас
  считаются скалярно и должны перейти на размерный результат;
- перечислить Formula JSON по реальным мокам для fixtures;
- отметить, что PHP Rule хранит `spec` как opaque JSON и validator появится
  вместе с backend Rule.

Deliverable: consumer matrix и список fixtures.

### Phase 1 — типы

- ввести `ScalarFormula` и `DimensionalFormula` по каталогу §4.2;
- добавить в объявление параметра явное поле kind `scalar | dimensional`;
- типизировать поля потребителей;
- описать чтение старого `fixed` по контракту поля;
- оставить `to_scalar`; не добавлять `modify`, скаляр внутри размерной Formula
  и сложение размерных значений.

Deliverable: типы Formula и объявление kind параметра.

### Phase 2 — central evaluator

- реализовать typed evaluation context;
- перенести семантику узлов каталога;
- `actionCharacteristic.multiplier` умножает базу и сохраняет размер;
- `ability_level.multiplier` остаётся скалярным;
- убрать silent fallback;
- добавить структурированные evaluation errors;
- покрыть node-level tests.

Deliverable: central evaluator with independent tests.

### Phase 3 — validation boundary

- добавить validation для Formula payload;
- добавить expected result kind consumer-а;
- проверить parameters and references;
- проверить resource kind;
- проверить actionCharacteristic;
- проверить Formula-like boundaries, которые остаются отдельными;
- определить, какие проверки обязательны при draft save, Rule publish и
  runtime defense;
- добавить negative tests.

Deliverable: save-time Formula validation.

### Phase 4 — consumer migration

- заменить локальные evaluator-ы;
- удалить дублирующую формульную арифметику;
- перевести resource calculations;
- перевести characteristic/check/attack/magic consumers;
- проверить dimensional arithmetic;
- оставить локальные adapters только если они выражают отдельную
  domain policy, а не повторяют evaluation.

Deliverable: consumers use central evaluator or documented adapters.

### Phase 5 — editor/view/mocks

- сделать editor result-kind aware;
- выбирать параметр только объявленного совместимого kind;
- показывать dimensional values корректно;
- синхронизировать mock/runtime/editor/view;
- проверить create → save → reload → view → runtime.

Deliverable: complete editor/runtime parity.

### Phase 6 — migration and compatibility

- отдельную версию Formula не вводить;
- классифицировать старый `fixed` по пути поля;
- проставить явный kind существующим параметрам по тому, что они задают;
- `actionCharacteristic.multiplier` в JSON не мигрировать: меняется вычисление;
- неразбираемый payload сохранить и выдать ошибку миграции;
- запретить silent reinterpretation;
- добавить migration fixtures;
- проверить старые rule revisions.

Deliverable: explicit backward compatibility policy.

### Phase 7 — cleanup and architectural verification

- удалить неиспользуемые local evaluators;
- проверить module boundaries and DI;
- проверить отсутствие concrete rule hardcodes;
- обновить dependency/public-surface evidence;
- повторить tests и static checks;
- провести review of all Formula consumers.

## 6. Критичные тестовые сценарии

### Scalar

- scalar fixed;
- parameter with scalar value;
- parameter with dimensional value rejected;
- ability level;
- characteristic size;
- floor division: `Math.floor`, divisor zero rejected;
- unsupported node rejected.

### Dimensional

- dimensional fixed, включая чтение старого `fixed` как `{ base: N, size: 0 }`;
- direct characteristic со сдвигом шкалы;
- dimensional parameter;
- scalar Formula rejected where dimensional output is required.

### Resources

- scalar resource: скалярная база и скалярные adjustments;
- dimensional resource: размерная база, adjustments — скалярные дельты к
  `base.base`, `size` сохраняется;
- dimensional Formula в поле поправки отклоняется.

### Action characteristic

- strike/throw/shoot;
- default characteristic base;
- explicit dimensional Formula base;
- modifier chain;
- multiplier умножает базу и сохраняет размер: `{4|2} * 5 = {20|2}`;
- результат остаётся размерным;
- invalid characteristic/action rejected.

### End-to-end

- create rule;
- edit rule;
- save with validation;
- reload;
- view;
- runtime calculation;
- migration from old payload;
- unsupported Formula produces visible structured error.
- old Formula JSON is classified by field context before mapping;
- migration preserves ambiguous payloads without silent reinterpretation;
- TypeScript and backend conformance fixtures produce the same result/errors.

## 7. Зафиксированные решения

Каждый вопрос ниже проверен по текущему коду. Evidence показывает, почему
ответ нельзя вывести только из DTO или названия поля.

### 7.1 Scope Formula — решение

Formula нужна, чтобы записывать в спеках правил вычисляемые значения.
`FormulaEvaluator` используется там, где значение уже выражено Formula и его
семантика совпадает с контрактом evaluator-а. Отдельный типизированный
вычислитель остаётся там, где нужен собственный typed input/result и Formula
не является естественной записью. Не переносить в FormulaEvaluator все
числовые расчёты только из-за похожего результата.

`SpellValue`, derived `min/max`, movement AST, `StateDecay`, action cost и
state effects пока остаются отдельными контрактами. Повторное использование
FormulaEvaluator для конкретного поля допустимо только после проверки, что
поле является вычисляемым значением спеки и не требует чужого typed context.

Evidence:

- `SpellValue` имеет отдельную dimensional/parameter модель и отдельный
  `SpellCastDifficultyService`;
- derived characteristics разбираются отдельным
  `DerivedCharacteristicService`;
- movement уже имеет recursive evaluator с `add`;
- `StateDecay` имеет расхождение editor/runtime;
- action costs допускают `chosen`, чего нет в Formula.

Риск решения: слишком широкая унификация создаст глобальный expression engine;
слишком узкая оставит несколько silent/duplicate evaluators.

### 7.2 Parameter kind — решение

Каждый отдельный параметр строго scalar или строго dimensional. Kind
объявляется явным полем в описании параметра. Форма `default`, `min` и `max`
его не заменяет: activation-параметр может получить значение только при
исполнении. `resolution` задаёт момент выбора и kind не меняет. Утверждение
«стоимость равна Интеллекту» при размерном Интеллекте является ошибкой спеки:
нужна явная проекция. Для базы характеристики в среднем размере это
`to_scalar` (§2.3). `toNumber()` ею не является.

Текущий DTO хранит и scalar, и dimensional параметры как
`number | DimensionalNumberValue`. В моках scalar-параметры часто записаны
`{base, size: 0}`: врождённые модификаторы, сопротивление магии, физическое
развитие. Размерный пример — activation-параметр мощи заклинания, у которого
`min/max` имеют ненулевой `size`. Это ошибка контракта DTO, а не доказательство
смешанного kind одного параметра.

Evidence:

- `AbilityParameter.default/min/max` допускают `number | DimensionalNumberValue`;
- `FormulaContext.parameterValues` принимает только `number`;
- local evaluator-ы берут `.base` у dimensional parameters;
- `CharacterEditorService.toNumber()` делает dimensional projection;
- spell activation использует `Record<string, DimensionalNumberValue>` в
  `SpellCastDifficultyService`.

Риск решения: одинаковый parameter JSON будет давать разные costs, grants и
runtime results.

### 7.3 Parameter в Formula — решение

Scalar Formula ссылается только на scalar parameter. Несовместимая ссылка —
ошибка спеки, а не повод для неявного `toNumber()` или отбрасывания `size`.
Явная проекция размерной величины в скаляр — узел `to_scalar`: база после
переноса на средний размер. Он есть в спеке изучения магии и не заменяется
`toNumber()`. Размерный parameter в Formula не входит в каталог (§4.2).
`per_unit` подтверждён только у скалярных параметров.

Evidence:

- current evaluator превращает parameter только в `{base, size: 0}`;
- `Grant.resistance` вручную строит dimensional result;
- spell parameter уже передаётся dimensional, но не через Formula.

Риск решения: silent loss of size или несовместимость со SpellValue.

### 7.4 `parameter_floor_div` — решение

Операция скалярная: скалярный параметр, результат — скалярные пункты.
Единственный текущий пример — `floor(Сила / 2)` в физическом развитии.
Размерный параметр в этом узле — ошибка спеки. Делитель `0` — ошибка данных,
а не подмена единицей. Округление — `Math.floor`, как в текущем evaluator.
Отрицательных спек с этим узлом нет.

### 7.5 `fixed` — решение

Kind старого `{ type: "fixed", value: N }` определяется полем, а не узлом.

Скаляр: `characteristic_modify.amount`, `sense_modify.amount`,
`state_modify.amount`, `magic_study.max_cost`, ресурсные adjustments и
`resource_limit_change.amount`.

Размерное значение `{ base: N, size: 0 }`: урон, пробитие, дистанция, дальность
и потолок характеристики. Отдельная версия Formula payload для этого различия
не нужна.

### 7.6 `actionCharacteristic.multiplier` — решение

Дистанция и дальность — размерные величины. Игроку их обычно пишут в среднем
размере. Умножение размерного значения меняет только базу и сохраняет размер:

```text
{4|2}            = 4 * 2^2
{4|2} * 5        = {20|2} = 4 * 5 * 2^2
```

Реальные спеки:

- `ruchnoy-arbalet`: `distance` = сила выстрела × 10;
- `korotkiy-luk`: `range` = сила выстрела × 2;
- `rekursivnyy-luk`: `range` = сила выстрела × 3;
- `blochnyy-luk`: `range` = сила выстрела × 5.

Урон и пробитие этих профилей множитель не используют. Отдельный оператор
умножения не нужен: поле `multiplier` остаётся на `actionCharacteristic`.

Текущий evaluator делает `modified.toNumber() * multiplier` и возвращает
`{ base: результат, size: 0 }`. Это неверная семантика и подлежит замене на
умножение базы с сохранением размера. Kind узла силы действия не меняется.

### 7.7 Общий узел `modify` — решение

Общий `modify(base, scalar)` не вводить. Сдвиг характеристики — операция шкалы
характеристики. Поправка ресурса — скалярная дельта к `base.base` с сохранением
`size`, и её применяет ресурсный сервис.

### 7.8 Сложение размерных значений — решение

В `Formula` такого потребителя нет. Сложение остаётся в movement AST. В
`Formula` операцию не добавлять.

### 7.9 Ресурс — решение

`is_dimensional` относится к базе ресурса. Adjustments и
`resource_limit_change` — скалярные дельты. Размерная Formula в поле поправки
недопустима.

### 7.10 Нуль и ошибка — решение

Настоящий нуль остаётся нулём: уровень 0, размер 0. Неизвестный узел,
несовместимый kind, делитель 0 и битая ссылка — ошибка валидации, не число.
Пропуск потолка экипировки при отсутствии характеристики — политика этого
потребителя, а не подстановка нуля формулой.

### 7.11 Ссылки и циклы — решение

Валидация проверяет ссылки параметров и `actionCharacteristic`, а также циклы
вложенности, повторного вычисления `actionCharacteristic` через профиль и
`linked` параметров. Цикл — ошибка, не значение.

### 7.12 Runtime и editor — решение

Поле, которое runtime читает, входит в контракт. То, что редактор записывает,
а runtime молча игнорирует, валидация отклоняет. Сейчас это `StateDecay` видов
`characteristic` и `check`. У сопротивления в текущих спеках значение — скалярный параметр, записанный
после резолва как `{ base: x * per_unit, size: 0 }`. Тот же параметр задаёт
скалярную стоимость. Ненулевого `size` у слотов сопротивления в моках нет.
Прочая Formula в этом поле — ошибка, пока поле явно не переведено на
`FormulaEvaluator`.

### 7.13 Где валидировать — решение

Редактор показывает ошибку при сохранении. Backend Rule, когда появится,
повторяет проверку. Runtime — защитная проверка, не единственное место
обнаружения ошибки.

### 7.14 Где вычислять — решение

При сохранении правила персонажа нет, поэтому Rule хранит проверенный payload
и не хранит вычисленное число как часть спеки. Считает сборка персонажа и игра.
Будущий backend Character/Game повторяет этот расчёт.

### 7.15 Wire format — решение

Отдельная версия Formula не вводится. Старый узел классифицируется по полю.
Неразбираемый payload не переинтерпретируется и даёт ошибку миграции.

### 7.16 Редактор — решение

Допустимые узлы определяются kind поля и операциями этого поля. Список
конкретных rule ids/codes для этого не используется.

### 7.17 TypeScript — решение

Поля типизируются как `ScalarFormula` или `DimensionalFormula`. JSON остаётся
дискриминированным по `type`. Объявление параметра хранит явный kind
`scalar | dimensional`; ссылка проверяется по этому полю. Отдельная пара wire
AST и validated AST не требуется.

### 7.18 Новые правила — решение

Новые данные на существующих операциях не требуют изменения кода. Новый
математический оператор требует evaluator, валидации, редактора и тестов.

## 8. Критерии успеха

Переработка считается успешной, если:

- нет production local evaluator-ов с неполным `switch`;
- Formula result kind известен по контракту поля до evaluation;
- consumer contract не разрешает математически несовместимые Formula;
- вложение Formula ограничено узлом `to_scalar`; общего `modify` нет;
- база ресурса следует `is_dimensional`, поправки остаются скалярными дельтами;
- `actionCharacteristic.multiplier` сохраняет размер;
- kind параметра объявлен явно и проверяется по ссылке;
- editor не ограничен конкретными rule ids/codes;
- invalid Formula не превращается в `0`;
- save-time validation выявляет ошибки;
- mock/editor/view/runtime round-trip проходит;
- все существующие Formula consumers перечислены и классифицированы;
- scope Formula-like languages и их boundaries зафиксирован;
- semantics и migration policy утверждены до изменения DTO;
- backend implementation сможет использовать тот же semantic contract;
- production code не зависит от конкретного наполнения rule database;
- backend validation/evaluation responsibility и conformance contract
  документированы.

## 9. Ограничения scope

Ниже — ограничение подготовки плана, не запрет на билд §10. При подготовке
плана не выполнять:

- изменения production code;
- изменения tests;
- изменения schema;
- изменения canonical `docs/tr`;
- автоматическую миграцию данных;
- исправление текущих evaluator-ов.

Все findings и решения сначала должны пройти отдельное обсуждение и
evidence-backed implementation planning.

## 10. Оставшаяся работа

Разбор `REV-FE-004` подтвердил модель §2–§7 с правками §2.3 и §4.2.
Локальные `formulaValue()` уже делегируют в `FormulaEvaluationService`.
Дальше убираются обходные пути, которые подменяют отсутствующий вход и размер
вне `switch`.

### 10.1 Политика отсутствующего входа

| Узел | Нет входа |
| --- | --- |
| `fixed`, `dimensional` | входа нет |
| `parameter`, `parameter_floor_div` | ошибка, не `0` и не `1` |
| `parameter_floor_div` с делителем `0` | ошибка, не подмена единицей |
| `ability_level` | уровень `0`, если способность не взята |
| `characteristic` | ошибка |
| `characteristic_size`, `characteristic_size_positive` | размер `0`, если характеристики нет у персонажа; размер `0` — и настоящее значение |
| `characteristic_size_gap` | `0`, если нет одной из характеристик |
| `actionCharacteristic` | ошибка, если нет базы |
| `to_scalar` | ошибка вложенной размерной формулы |

Настоящий нуль остаётся нулём: уровень 0, размер 0, дельта 0.

### 10.2 Один путь параметра

`FormulaContext.parameterValues` возвращает скаляр только для параметра
`kind: scalar`. Размерное значение в скалярный контекст не проецируется.

Удалить предрасчёт в `CharacterEditorService.resolveGrant` для
`characteristic_modify` (`parameter` и `parameter_floor_div`): эти узлы
считает evaluator. Сейчас `divisor === 0` там становится `1`, а
`parameterValueOf` при отсутствии параметра возвращает `1` и вызывает
`toNumber()`.

Параметры принадлежат экземпляру способности, а `formulaContext` один на
сборку и не знает «текущую» способность. `parameterValues` передаётся в
вызов `evaluate` из `forEachActiveGrant`, где уже есть `ability` и `spec`,
а не в общий контекст персонажа. Иначе гранты разных способностей смешают
одноимённые коды.

`AbilityCheckAdvantagesService` и `EditorCheckBonusesService` передают в
контекст число скалярного параметра. Ветку `raw.base` убрать. Контекст
характеристик у проверок пустой: формула размера характеристики в этом
адаптере — ошибка контекста, не `0`.

`resistance` со скалярным `parameter` и грант `characteristic_parameter`
остаются политикой гранта, не узлами Formula. `resistance` по-прежнему
записывает `{ base: x * per_unit, size: 0 }` только для скалярного параметра.
Размерный параметр в этом поле — ошибка спеки. Их предрасчёт в
`resolveGrant` не переносится в Formula.

`costWithinLimit` без snapshot уже не считает формулу и считает грант
открытым. `maxCostOf` / `resolvedMaxCost` при формульном `max_cost` и без
snapshot всё ещё возвращают `0`. Этот ноль убрать: без снимка метод не
вызывается, либо бросает ту же ошибку, что evaluator при отсутствии
характеристики. Политику гейта `costWithinLimit` не менять.

### 10.3 Каталог

Убрать `{ type: "parameter" }` из `DimensionalFormula` и ветку evaluator,
которая бросает «отдельный контракт». `DIMENSIONAL_FORMULA_MODES` этот узел
уже не показывает; менять список режимов не нужно.

`to_scalar` остаётся. Существующий тест: Интеллект `{ base: 4, size: 1 }`
даёт `4`. На шкале 3–5 целый размер базу не меняет. `toNumber()` того же
значения — `8`. Новый пример с другим числом не добавлять.

### 10.4 Validation

Ссылки характеристик, размеров, `ability_level` и `actionCharacteristic`
уже собирает `RuleValidationService.walkFormula`. В
`FormulaValidationService` их не дублировать.

`FormulaValidationService` дополнить:

- размерный `parameter` в любом поле — ошибка, пока узла нет в каталоге;
- `resistance.value`, если это Formula, — только скалярный `parameter`;
- `magic_study.max_cost` — число или `ScalarFormula`, без размерного узла
  снаружи `to_scalar`;
- structured-ошибку там, где `walkFormula` сейчас только копит ссылку, а
  kind поля не проверен.

Новые проверки пишут в тот же `{ ruleCode, ruleName, message }`. Поля
`code` / `path` / `stage` не добавлять.

Повторный `to_scalar` внутри `to_scalar` — ошибка сообщения. Циклы `linked`
и повтор `actionCharacteristic` через профиль оружия в этот билд не входят:
отдельного подтверждающего payload нет.

### 10.5 Порядок

1. Политика отсутствующего входа и тесты узлов, включая `to_scalar`.
2. Контекст параметров в сборке персонажа; удаление предрасчёта
   `characteristic_modify` и подмены делителя.
3. Убрать `.base` в check-адаптерах.
4. Удалить размерный `parameter` из каталога Formula.
5. Дополнить save-time validation и негативные тесты.
6. Прогнать mock → editor → runtime для изучения магии, физического
   развития (`parameter_floor_div`), оружия с `multiplier` и лимита ОД.

Не делать в этих шагах: фазы 0–7 заново, общий `modify`, сложение размерных
Formula, `Formula<T>`, перенос `SpellValue` и movement AST, переписывание
`multiplier`, снятие `toNumber()` у досягаемости в обзоре атаки, проекцию
лимита в снимке требований (`toNumber(existing) + delta`). Это не баги
каталога Formula.
