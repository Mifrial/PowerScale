# План Game 24 — смягчение S одного удара

**Статус:** смягчение S в PHP сделано. `BACKEND_OPEN`. Suite `game` по `GameStrikeMysqlTest` и `GameWideStrikeMysqlTest` зелёный. В [`game-roadmap.md`](game-roadmap.md) шаг не вписывается. Сопротивление уже в итоге — [`game-plan-23.md`](game-plan-23.md): `resistance` — сумма слотов брони, `damage` — число формулы урона, `success` — рейтинг попадания; вычитание, умножение на РУ и S этот итог не делает. Реакция — [`game-plan-13.md`](game-plan-13.md): `ignore`, `dodge`, `block`. Канон слоя — [`rule-content-plan-12-dodge-benefit.md`](rule-content-plan-12-dodge-benefit.md) и [`rule-content-plan-11-melee-accuracy.md`](rule-content-plan-11-melee-accuracy.md): `S = Ловкость.modify(dodge_benefit)`, пустое поле — fallback `−3`, слой только у Избегать. Отсутствие экземпляра — [`rule-system.md`](rule-system.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. При закрытии уже открытого удара `1 → 1` и `1 → N` реакция `dodge` пишет одно целое `S` в JSON итога той же команды. В лист оно не пишется, новый ключ `sheet` не заводится. `success`, `damage` и `resistance` остаются как в G23. Код карточки Ловкости в Game не зашивается. Формула повреждения, `putState` и список операций не входят. Запись исхода из нарезки G22 не пишется.

Сверка до плана. Закрытие `S` не пишет: итог `1 → 1` несёт `success`, `damage`, `resistance` и `sheetVersion`; запись цели `1 → N` — те же три числа. `WeaponProfile::getDodgeBenefit()` уже отдаёт `?int`. `CharacteristicSpec::isDodgeSoak()` уже читается из `dodge_soak`. `ICharacterFormulaContexts::build` уже кладёт `characteristicPurchases[].value` в `DimensionalNumber`, и `GameStrikeRules` этот порт уже держит. `DimensionalNumber::shift` сдвигает базу по шкале, `CharacteristicNumber::BASE_MIN` и `BASE_MAX` — та же шкала, что `modify` характеристики. Связь «единственная живая карточка `isDodgeSoak()` → закупка цели → `shift` на пользе профиля» в срезе есть, поэтому план — запись одного целого из этих входов.

Зависимость — G23. Rule и Character этим шагом не меняются. Хендлеры Mechanic не ставятся.

## Физическая схема

Новых таблиц и колонок нет. Рядом с `success`, `damage` и `resistance` в JSON итога уже существующих `game_strike_command` и `game_wide_strike_command` при реакции `dodge` появляется ключ `S`. У `ignore` и `block` ключа нет. В строки удара и целей колонка не пишется. Строка `game_check` и process не открываются. Новый kind не заводится. Ключ `sheet` не растёт.

## Сверка входов

- `characteristicPurchases` листа уже разбирает `ICharacterFormulaContexts`. Пара — `value.base` и `value.size`. Закрытие не дописывает закупку в лист и не заводит ключ.
- Поиск единственной карточки — тот же обход `getLiveRules()`, что `findHitCode`: живая spec, `CharacteristicSpec`, `isDodgeSoak()`. Код правила в сравнение не входит. Битая spec в отбор не попадает.
- Профиль, который уже читают `evaluateDamage` и `evaluateResistance`, несёт `getDodgeBenefit()`. Второй поиск профиля не пишется. Реакции тип профиля не подменяют.
- Шкала сдвига — константы `CharacteristicNumber`, не литералы в Game. `shift` уже есть у `DimensionalNumber`. Новый тип числа не заводится.
- `GameStrikeRules` держит пять зависимостей. Шестая не добавляется. Конструкторы `GameStrikes` и `GameWideStrikes` не растут.
- `ICharacterActualMutations::apply` и `GameNpcRepository::replaceVersion` не вызываются. Character модуль Game не импортирует сверх уже подключённых портов. Rule не меняется. `operations()` остаётся пустым списком.

## Зафиксировано (модель и права)

**Когда считать.** Только реакция `dodge`. `ignore` и `block` карточку Ловкости и закупку не читают, ключ `S` в итог не кладут, удар закрывается. Ноль в это поле не подставляется: отсутствие ключа и есть «слоя нет».

**Откуда Ловкость.** Единственная живая характеристика среза ревизии игры с `isDodgeSoak()`. Нет такой карточки или их две — обычный отказ этого среза, `GAME_INVALID`, удар не закрывается. Запасной код характеристики не подставляется. `RULE_INVALID` и `CHARACTER_INVALID` наружу не выпускаются.

**Откуда число.** Лист цели. Персонаж — `ICharacters::get`, тот же объект, что у сверки версии: у него есть `getSheet()`. NPC — `getVersion()['sheet']`, как уже читает сопротивление. Контекст — `ICharacterFormulaContexts::build` этого снимка. `build` принимает документ только если в нём есть массивы `abilityLevels` и `characteristicPurchases`; нет ключа, `null` или битая строка — `CharacterInvalidException`, наружу `GAME_INVALID`, удар не закрывается. Значение — `FormulaContext::findCharacteristic` кода найденной карточки. `null` — закупки этой характеристики нет, тот же `GAME_INVALID`. У `1 → N` отказ среза — отказ всего закрытия, не `code` одной цели.

**Модификатор.** `WeaponProfile::getDodgeBenefit()` уже найденного профиля. `null` — fallback `−3`. Явный `0` остаётся `0`: ноль — польза, не пустое поле. Предупреждение и alert плана-12 в PHP не зовутся.

**Само S.** `DimensionalNumber` закупки, `shift(модификатор, CharacteristicNumber::BASE_MIN, CharacteristicNumber::BASE_MAX)`, затем `toInteger()`. Это `Ловкость.modify` на шкале характеристики. Карты Направленный и Стремительный это число не режут. `max(0, …)` к S не применяется. Формула `max(0, (урон − сопротивление) × РУ − S)` не выполняется.

**Куда пишется.** Ключ `S` итога, целое. `damage`, `success` и `resistance` не переприсваиваются. У `1 → 1` ключ рядом с этими тремя, только при `dodge`. У `1 → N` число своё у каждой принятой цели с реакцией `dodge`: урон и рейтинг по-прежнему общие на атаку, закупка читается с её листа. Принятая цель с `ignore` или `block` ключа `S` не получает. Отказ одной цели пишет `success`, `damage` и `resistance` как `null` и ключ `S` не ставит. `sheetVersion` и `code` принятой цели остаются `null`.

**Порядок.** Поиск карточки попадания, `evaluateDamage` и сборка `resistance` не меняют свой порядок. Чужая версия боя — `GameBattleConflictException`, смягчение не вызывается. У `1 → 1` расчёт `S` стоит после `assertSheet` и до `rateOf`: чужой `expectedSheetVersion`, нет карточки и нет закупки не вызывают `roll`. У `ignore` и `block` этот расчёт пропускается, `rateOf` идёт как сейчас. У `1 → N` `rateOf` уже вызван в `GameWideStrikes` до `commitClose`. Расчёт — в `GameWideStrikeResults::one`, когда `refusal` вернул `null` и реакция `dodge`, и снаружи `try` этого метода: `refusal` ловит `GameInvalidException` и пишет его в `code` одной цели, а нет карточки или нет закупки должно сорвать всё закрытие. Чужая версия листа одной цели по-прежнему не отменяет удар: у этой цели слоты пустые, смягчение для неё не вызывается. Отказ среза на другой цели откатывает транзакцию. Команда не пишется, повтор закрытия вызывает `roll` снова. Повтор уже сохранённого итога возвращает JSON команды как есть и закупку второй раз не читает. Тот же ключ с другим телом — `GAME_CONFLICT`.

**Чего шаг не делает.** Не пишет лист и не заводит ключ `sheet`. Не меняет `success`, `damage` и `resistance`. Не считает повреждение и `(урон − сопротивление) × РУ`. Не наполняет список операций. Не зовёт `putState`. Не режет S картами. Не ставит хендлеры. Не начинает запись исхода роадмапа. Не считает `N → 1`, `N → N`, DOT, каст и сцену.

## Что даёт этот заход

Закрытие с реакцией `dodge` возвращает прежние `success`, `damage` и `resistance` и новое `S`: `toInteger()` закупки единственной живой характеристики `isDodgeSoak()` после `shift` на `getDodgeBenefit()` или на `−3`. `ignore` и `block` закрывают удар без ключа `S`. Нет карточки, их две или закупки нет — удар остаётся открытым. Лист не меняется.

## Что не закрыто

- Формула повреждения: вычитание сопротивления, умножение на РУ, вычитание S, пол нуля.
- Резка S картами Направленный и Стремительный.
- Запись числа в лист, C11 и G22 роадмапа.
- `putState` и список операций.
- `N → 1`, `N → N`, DOT, каст, сцена.
- `personalNotes`.

## Точки кода

Старые планы не переписываются. Новых портов, action и ключей прав нет. `declareStrike` и `declareWideStrike` не меняются. Character Game не импортирует. Rule не меняется.

- `GameStrikeRules` — метод рядом с `evaluateResistance`. Вход: уже найденный профиль или те же предмет, вид и индекс, лист цели, реакция не входит: метод зовут только для `dodge`. Выход: одно целое. Конструктор не растёт. `operations` остаётся пустым.
- `GameStrikes::close` — после сверки листа цели и только при `dodge` пишет `S` в итог команды до `rateOf`. `success`, `damage` и `resistance` не переприсваиваются. При `ignore` и `block` ключ не добавляется.
- `GameWideStrikeResults` — после `refusal() === null`, вне его `try`, при реакции `dodge` считает `S` и передаёт его в `row`. Иная реакция и отказ цели ключ не ставят. Исключение не превращается в `code` одной цели. `GameWideStrikes` конструктор не растит: `rateOf` остаётся до `commitClose`.

## Модуль

Нового порта Game нет. `events` остаётся `[]`. Новый код ошибки не заводится.

| Ситуация | Код |
|---|---|
| реакция `dodge`, карточек `isDodgeSoak()` нет или две; закупки этой характеристики нет; список закупок битый | `GAME_INVALID`, удар открыт |
| `getDodgeBenefit()` равен `null` | модификатор `−3`, удар закрывается |
| `getDodgeBenefit()` равен `0` | модификатор `0`, не fallback |
| реакция `ignore` или `block` | ключа `S` нет, карточка не ищется, удар закрывается |
| чужая версия боя; чужой `expectedSheetVersion` у `1 → 1` | `GAME_CONFLICT`, удар открыт |
| чужая версия листа одной цели `1 → N` | удар закрывается, у этой цели слоты пустые, ключа `S` нет |
| готовые успех, урон или S во входе | `INVALID_PARAMS` |

## Фасад

`IGameStrikes` и `IGameWideStrikes` не растут. Закрытие `dodge` после сверки листа зовёт расчёт. Объявление число не считает.

## HTTP

Новых action нет. `game.resolveStrike` по-прежнему отдаёт `success`, `damage` и `resistance` и при `dodge` добавляет `S`. `game.resolveWideStrike` заполняет `S` у принятой цели с реакцией `dodge`. Ключи входа те же. `success` — рейтинг попадания. `damage` — число формулы урона. `resistance` — собранное целое. `S` — целое смягчения, не поле листа.

## Тесты

Suite `game`. Suite `rule`, `character` и `mechanic` не расширяются.

Mysql `1 → 1`, `dodge`. В срез мира удара добавляется одна живая характеристика с `dodge_soak` и без зашитого кода в утверждении поиска. На листе цели в `characteristicPurchases` есть `value` этой характеристики с известными `base` и `size`. Профиль меча несёт `dodge_benefit`. После закрытия `damage`, `success` и `resistance` те же, что до шага, `S` равен `toInteger()` после `shift` на эту пользу и шкалу `CharacteristicNumber`. `apply` не вызывается. Повтор ключа возвращает то же `S` и закупку второй раз не читает.

Mysql fallback. Профиль без `dodge_benefit` при `dodge` даёт `S` от модификатора `−3`. Профиль с `dodge_benefit` `0` не подменяет его на `−3`. `damage` не уменьшается.

Mysql без слоя. `ignore` и `block` закрывают удар без ключа `S`. Карточка `isDodgeSoak()` в этих сценариях может отсутствовать: удар всё равно закрывается.

Mysql отказа. Нет карточки, две карточки с `isDodgeSoak()`, нет строки закупки её кода — удар открыт, версии боя и листа те же. Литерал кода характеристики в утверждении поиска не служит признаком: признак — единственный `isDodgeSoak()`.

Mysql `1 → N`. Две принятые цели с реакцией `dodge` и разной закупкой получают разные `S` при общих `success` и `damage`. Цель с `ignore` в том же закрытии ключа `S` не получает. Чужая версия листа одной цели оставляет у неё прежние слоты пустыми, без ключа `S`, и не затирает `S` второй. Нет карточки в срезе не закрывает удар целиком.

Уже зелёный `GameWideStrikeMysqlTest::testTwoTargetsKeepSeparateResults` закрывает вторую цель с `dodge` и ждёт `success` `1`. Мир этого теста сейчас без характеристики `isDodgeSoak()`, лист NPC — без закупки. После шага этот вызов ищет карточку и закупку и без них станет `GAME_INVALID`. В мир теста добавляется одна такая характеристика, в лист второй цели — `abilityLevels` и `characteristicPurchases` с её `value`, утверждение принятой цели дополняется `S`. Первая цель с чужой версией листа остаётся без ключа `S`. `GameStrikeMysqlTest` шлёт `dodge` только в закрытие с чужой версией боя: `battleOf` отказывает раньше расчёта, фикстуру Ловкости этому вызову не добавлять.

Mysql листа. Ключ `sheet` после закрытия тот же. `operations()` пуст.

## Todo

- [x] **soak** — одно целое: закупка единственной живой характеристики `isDodgeSoak()`, `shift` на `getDodgeBenefit()` или `−3`, шкала `CharacteristicNumber::BASE_MIN` / `BASE_MAX`, затем `toInteger()`. Код карточки не зашит. Карты S не режут.
- [x] **layer** — ключ `S` только при `dodge`. `ignore` и `block` ключ не пишут и карточку не ищут. Нет карточки, их две или нет закупки: `GAME_INVALID`, удар открыт.
- [x] **slot** — `S` в итоге `1 → 1` и у принятой цели `1 → N` с реакцией `dodge`. `success`, `damage` и `resistance` не меняются. В лист не пишется. Список операций пуст.
- [x] **gates** — phpunit `game`. Character не импортирует Game. Rule не меняется. Повреждение и запись исхода не начинаются.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| расчёт | `GameStrikeRules` | закупка цели и польза профиля |
| закрытие `1 → 1` | `GameStrikes` | `S` рядом с прежними `success`, `damage` и `resistance` |
| закрытие `1 → N` | `GameWideStrikes` | своё `S` у каждой принятой цели с `dodge` |

## Acceptance

- Game при реакции `dodge` пишет `S` при закрытии: единственная живая `CharacteristicSpec::isDodgeSoak()`, размерное `value` из `characteristicPurchases` листа цели, `DimensionalNumber::shift` на `WeaponProfile::getDodgeBenefit()` или на `−3`, шкала `CharacteristicNumber`. Код карточки не зашит.
- Число — `toInteger()` после сдвига. Пустое поле пользы — `−3`. Явный `0` остаётся `0`.
- Нет карточки, их две или закупки нет — обычный отказ среза, удар не закрывается.
- `ignore` и `block` ключ `S` в итоге не ставят.
- `damage` остаётся формулой урона. `success` остаётся рейтингом попадания. `resistance` остаётся суммой слотов. `S` в лист не пишется, новый ключ `sheet` не заводится.
- Формула повреждения, резка картами, `putState` и список операций не появляются. Новый kind не заводится.
- Запись исхода роадмапа не начинается. Character не импортирует Game. Rule не меняется. Шаг в `game-roadmap.md` не вписывается.

## Документы захода

этот файл; [`game-plan-23.md`](game-plan-23.md); [`game-plan-13.md`](game-plan-13.md); [`rule-content-plan-12-dodge-benefit.md`](rule-content-plan-12-dodge-benefit.md); [`rule-content-plan-11-melee-accuracy.md`](rule-content-plan-11-melee-accuracy.md); [`rule-system.md`](rule-system.md); [`php-coding-standards.md`](php-coding-standards.md).
