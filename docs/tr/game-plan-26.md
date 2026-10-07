# План Game 26 — размерные урон, сопротивление и смягчение в итоге удара

**Статус:** размерные урон, сопротивление, смягчение и повреждение в PHP сделаны. `BACKEND_OPEN`. Suite `game` по `GameStrikeMysqlTest` и `GameWideStrikeMysqlTest` зелёный. В [`game-roadmap.md`](game-roadmap.md) шаг не вписывается. Повреждение уже в итоге — [`game-plan-25.md`](game-plan-25.md): `injury` считается при закрытии `1 → 1` и `1 → N`. Смягчение — [`game-plan-24.md`](game-plan-24.md): ключ `S` есть только при реакции `dodge`. Сопротивление — [`game-plan-23.md`](game-plan-23.md). Урон — [`game-plan-20.md`](game-plan-20.md). Обход размерного узла — [`rule-plan-03.md`](rule-plan-03.md): `evaluateDimensional` возвращает `DimensionalNumber`, скалярный обход — отдельный метод. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. `damage`, `resistance`, `S` и `injury` в JSON итога той же команды остаются парой `{base, size}`. `success` остаётся целым рейтингом попадания. `toInteger()` на этих четырёх величинах не вызывается. Ключ `S` по-прежнему только у `dodge`. Лист, `putState`, стойкость, истощение, увечье и конец хода не входят.

Сверка до плана. `IFormulaEvaluations::evaluateDimensional` уже возвращает `DimensionalNumber`. `GameStrikeRules::evaluateDamage` сразу вызывает `toInteger()` и отдаёт `int`. Сумма сопротивления складывает `ResistanceSlot::getValue()->toInteger()`. `GameStrikeSoaks::shift` после `shift` вызывает `toInteger()`. `GameStrikes::close` и `GameWideStrikeResults::one` считают `injury` целым `max` из этих целых. В JSON итога `damage`, `resistance`, `S` и `injury` — целые, не `{base, size}`. План пишется.

Зависимость — G25. Rule и Character этим шагом не меняются. Хендлеры Mechanic не ставятся.

## Физическая схема

Новых таблиц и колонок нет. В JSON уже существующих `game_strike_command` и `game_wide_strike_command` ключи `damage`, `resistance`, `injury` и при `dodge` ключ `S` меняют значение с целого на объект `base` и `size`. `success` остаётся целым. В строки удара и целей колонка не пишется. Строка `game_check` и process не открываются. Новый kind не заводится. Ключ `sheet` не растёт.

## Сверка входов

- `evaluateDimensional` уже отдаёт пару. Шаг убирает `toInteger()` с результата. В массив итога и в JSON команды кладётся `['base' => getBase(), 'size' => getSize()]`. Сам объект `DimensionalNumber` в итог не кладётся: полей для `json_encode` у него нет, приватные свойства дают пустой объект.
- Слот брони уже хранит `DimensionalNumber`. Сумма совпавших слотов — одна пара, не сумма целых.
- `DimensionalNumber::shift` уже возвращает пару. Шаг убирает `toInteger()` после сдвига закупки.
- У `DimensionalNumber` в Rule есть конструктор, `getBase`, `getSize`, `shift` и `toInteger`. Методов сложения, вычитания и умножения нет. Rule их не получает. Арифметика формулы живёт в Game и собирает новую пару тем же смыслом, что сложение и вычитание к меньшему размеру и умножение базы у размерного числа движка: размер суммы и разности — меньший из двух, базы приводятся множителем `2^(свой размер − общий)`; умножение на целый рейтинг меняет базу и оставляет размер.
- Нет ключа `S` — из формулы вычитается пара `{base: 0, size: 0}`. Ноль в ключ `S` не подставляется.
- Пол нуля — сравнение пар к меньшему размеру, без `toInteger()`. Отрицательная разность пишется как `{base: 0, size: 0}`.
- `GameStrikeRules` уже держит пять зависимостей и девять публичных методов, файл длиннее порога 500 строк. Десятый публичный метод не добавляется. Один новый класс Game считает сумму слотов, разность, умножение на рейтинг и пол. `evaluateResistance` зовёт его для суммы. Закрытия зовут его для `injury`. Конструкторы `GameStrikes` и `GameWideStrikes` не растут: объект создаётся внутри уже существующего сервиса, как `GameStrikeSoaks` внутри `GameStrikeRules`.
- `ICharacterActualMutations::apply` и `GameNpcRepository::replaceVersion` не вызываются. Character модуль Game не импортирует сверх уже подключённых портов. Rule не меняется. `operations()` остаётся пустым списком.

## Зафиксировано (модель и права)

**Урон.** `evaluateDamage` возвращает `DimensionalNumber` из `evaluateDimensional`. `toInteger()` не вызывается. В JSON — `damage: {base, size}`.

**Сопротивление.** Сумма `getValue()` слотов, чей `getDamageTypeCode()` совпал с типом урона профиля. Каждый слот входит парой. Нет подходящего слота, нет брони, пустой список надетых кодов или тип урона профиля пуст — явная пара `{base: 0, size: 0}`. В JSON — `resistance: {base, size}`.

**Смягчение.** `shift` закупки на пользе профиля, без `toInteger()`. Ключ `S` только при `dodge`. У `ignore` и `block` ключа нет. В JSON при `dodge` — `S: {base, size}`.

**Повреждение.** Та же формула: `max(0, (damage − resistance) × success − S)`. `damage`, `resistance` и `S` — пары. `success` — целое. Нет ключа `S` — вычитается `{base: 0, size: 0}`. Результат — `DimensionalNumber`. В JSON — `injury: {base, size}`. Свёртка операндов в `int` до формулы не используется.

**Куда пишется.** У `1 → 1` массивы `base` и `size` рядом с целым `success`. У `1 → N` `damage` и `success` общие на атаку, `resistance`, `injury` и наличие `S` — свои у принятой цели. Отказ цели оставляет `success`, `damage`, `resistance` и `injury` пустыми и ключ `S` не ставит. `sheetVersion` и `code` принятой цели остаются `null`. Уже лежащие команды не переписываются: повтор отдаёт сохранённый JSON, в том числе прежние целые.

**Порядок.** Поиск карточки попадания, `evaluateDamage`, сборка `resistance`, запись `S` и `rateOf` не меняют свой порядок. `injury` считается из уже полученных пар и целого рейтинга до записи команды. Чужая версия боя — `GameBattleConflictException`, формула не вызывается. У `1 → 1` чужой `expectedSheetVersion`, нет карточки и нет закупки по-прежнему не вызывают `roll` и не пишут `injury`. У `1 → N` расчёт остаётся в `one` после `refusal() === null`, снаружи `try` метода `refusal`. Повтор уже сохранённого итога возвращает JSON команды как есть.

**Чего шаг не делает.** Не пишет лист и не заводит ключ `sheet`. Не меняет смысл `success`. Не наполняет список операций. Не зовёт `putState`. Не считает стойкость, истощение, увечье и конец хода. Не режет S картами. Не ставит хендлеры. Не начинает запись исхода роадмапа. Не считает `N → 1`, `N → N`, DOT, каст и сцену.

## Что даёт этот заход

Закрытие возвращает целый `success` и пары `damage`, `resistance`, `injury`. При `dodge` ещё пару `S`. Формула повреждения читает эти пары. Лист не меняется.

## Что не закрыто

- Резка S картами Направленный и Стремительный.
- Запись числа в лист, C11 и G22 роадмапа.
- Стойкость, истощение, увечье, конец хода.
- `putState` и список операций.
- `N → 1`, `N → N`, DOT, каст, сцена.
- `personalNotes`.

## Точки кода

Старые планы не переписываются. Новых портов, action и ключей прав нет. `declareStrike` и `declareWideStrike` не меняются. Character Game не импортирует. Rule не меняется: у `DimensionalNumber` новых методов нет.

- `GameStrikeRules::evaluateDamage` — возвращает пару `evaluateDimensional`. `toInteger()` снимается.
- `GameStrikeRules::evaluateResistance` и разбор слота — сумма пар совпавших слотов через новый класс. Пустая сумма — `{base: 0, size: 0}`. `toInteger()` слота снимается. Десятый публичный метод не добавляется.
- `GameStrikeSoaks::shift` — возвращает пару после `shift`. `toInteger()` снимается.
- Новый класс Game — сумма, вычитание, умножение базы на целый рейтинг и пол нуля. `evaluateResistance` и оба закрытия зовут его. Конструкторы фасадов не растут.
- `GameStrikes::damageOf`, `resistanceOf`, `soakOf` — тип возврата пара, не `int`. В `close` поле `resistance` до транзакции сейчас целое `0`; до `commands->add` оно заменяется массивом пары, как `damage`, `injury` и при `dodge` `S`.
- `GameWideStrikes::damageOf` и аргумент `damage` у `commitClose` — пара, не `int`. `GameWideStrikeResults::one` принимает эту пару и передаёт в `row` массивы `base` и `size`. `success` остаётся `int`. Отказ цели по-прежнему пишет пустые слоты без ключа `S`.

## Модуль

Нового порта Game нет. `events` остаётся `[]`. Новый код ошибки не заводится.

| Ситуация | Код |
|---|---|
| реакция `dodge` | пары `damage`, `resistance`, `S`, `injury`; `success` целое |
| реакция `ignore` или `block` | тех же пар нет ключа `S`; из формулы вычитается `{base: 0, size: 0}` |
| разность ниже нуля | `injury` равен `{base: 0, size: 0}`, удар закрывается |
| нет подходящего слота брони | `resistance` равен `{base: 0, size: 0}` |
| чужая версия боя; чужой `expectedSheetVersion` у `1 → 1` | `GAME_CONFLICT`, удар открыт |
| чужая версия листа одной цели `1 → N` | удар закрывается, у этой цели слоты пустые |
| готовые успех, урон или S во входе | `INVALID_PARAMS` |

## Фасад

`IGameStrikes` и `IGameWideStrikes` не растут. Закрытие после уже собранных входов зовёт формулу на парах. Объявление число не считает.

## HTTP

Новых action нет. `game.resolveStrike` и `game.resolveWideStrike` отдают `damage`, `resistance`, `injury` и при `dodge` ключ `S` объектами `{base, size}`. `success` остаётся целым рейтингом. Отказ цели `1 → N` оставляет четыре слота пустыми и ключ `S` не ставит. Ключи входа те же.

## Тесты

Suite `game`. Меняются утверждения целых `damage`, `resistance`, `S` и `injury` в `GameStrikeMysqlTest` и `GameWideStrikeMysqlTest`. Suite `rule`, `character` и `mechanic` не расширяются. Ожидание нового итога — массивы `base` и `size`. Свёртка формулы через `toInteger()` в утверждениях не используется.

Mysql `1 → 1`, `dodge`. `damage` равен паре `evaluateDimensional`. `resistance` равен сумме пар совпавших слотов. `S` равен паре после `shift` без `toInteger()`. `injury` — пара той же формулы. `success` — целое. `apply` не вызывается. Повтор ключа возвращает те же пары.

Mysql без слоя. `ignore` и `block` закрывают удар без ключа `S`. `injury` считается с вычитаемым `{base: 0, size: 0}`.

Mysql пола. Набор, где разность пар отрицательна, закрывает удар с `injury` `{base: 0, size: 0}`.

Mysql нуля сопротивления. Нет подходящего слота — `resistance` равен `{base: 0, size: 0}`.

Mysql `1 → N`. Две принятые цели с разной бронёй или разным `S` получают разные пары `resistance` и `injury` при общих `success` и `damage`. Цель с `ignore` ключа `S` не получает. Отказ одной цели пишет у неё пустые слоты и не затирает пары второй.

Mysql листа. Ключ `sheet` после закрытия тот же. `operations()` пуст. `putState` не вызывается.

## Todo

- [x] **pair** — `damage`, `resistance` и `S` — `DimensionalNumber` без `toInteger()`. Пустое сопротивление — `{base: 0, size: 0}`. Ключ `S` только у `dodge`.
- [x] **injury** — та же формула на парах и целом `success`. Нет ключа `S` — вычитается `{base: 0, size: 0}`. Пол нуля без свёртки в `int`. В JSON у четырёх величин `base` и `size`, `success` остаётся целым.
- [x] **gates** — phpunit `game`. Character не импортирует Game. Rule не меняется. Лист, `putState`, стойкость, истощение, увечье и конец хода не входят. Шаг в `game-roadmap.md` не вписывается.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| урон, сопротивление, S | `GameStrikeRules`, `GameStrikeSoaks` | пары без `toInteger()` |
| формула | новый класс Game | сумма, разность, умножение на рейтинг, пол нуля |
| закрытие `1 → 1` и `1 → N` | `GameStrikes`, `GameWideStrikeResults` | `{base, size}` в итоге, `success` целое |

## Acceptance

- `damage` — результат `evaluateDimensional` без `toInteger()`.
- `resistance` — сумма совпавших слотов как `DimensionalNumber`. Нет слота — `{base: 0, size: 0}`.
- `S` — `shift` закупки без `toInteger()`. Ключ только у `dodge`.
- `injury` считается из этих пар и целого `success` по той же формуле и остаётся `DimensionalNumber`.
- В JSON итога у `damage`, `resistance`, `injury` и при `dodge` у `S` есть `base` и `size`. `success` остаётся целым рейтингом.
- Отказ цели `1 → N` оставляет слоты пустыми и ключ `S` не ставит.
- Лист, `putState`, стойкость, истощение, увечье и конец хода не входят.
- Character не импортирует Game. Rule не меняется. Шаг в `game-roadmap.md` не вписывается.

## Документы захода

этот файл; [`game-plan-25.md`](game-plan-25.md); [`game-plan-24.md`](game-plan-24.md); [`game-plan-23.md`](game-plan-23.md); [`game-plan-20.md`](game-plan-20.md); [`rule-plan-03.md`](rule-plan-03.md); [`php-coding-standards.md`](php-coding-standards.md).
