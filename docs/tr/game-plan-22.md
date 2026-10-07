# План Game 22 — попадание одного удара

**Статус:** попадание удара в PHP сделано. `BACKEND_OPEN`. Suite `game` по `GameStrikeMysqlTest`, `GameWideStrikeMysqlTest`, `GameInitiativeMysqlTest` и `GameCheckMysqlTest` зелёный. В [`game-roadmap.md`](game-roadmap.md) заход не вписывается. G22 роадмапа — запись числа в лист после Character C11; этот файл её не начинает и исход в лист не пишет. Бой находит единственную живую проверку по признаку и считает её путём G18 — [`game-plan-21.md`](game-plan-21.md). Бросок и рейтинг — [`game-plan-18.md`](game-plan-18.md): `IMechanicRolls::roll` и `IMechanicRolls::rate`, итог `throwCheck` несёт `success: bool` и `rating: int`. Сейчас слот удара `success` равен числу урона — [`game-plan-20.md`](game-plan-20.md). Тело атаки кода проверки не содержит — [`game-plan-13.md`](game-plan-13.md). Канон отсутствия экземпляра — [`rule-system.md`](rule-system.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. Закрытие уже открытого удара `1 → 1` и `1 → N` ищет в срезе ревизии игры ровно одну живую `CheckSpec` с `isHitCheck()`. Эта карточка проходит в `roll` и `rate`. В слот `success` пишется `rating` этого рейтинга. `damage` остаётся числом формулы урона профиля. Код карточки в Game не зашивается. Нет карточки, их две или payload `roll` не из чего бросить — `GAME_INVALID`, удар не закрывается. Отказ `isHitCheck` у соло и pairwise G18 и у `throwCheck` инициативы остаётся.

Зависимость — G20 и G21. Признак в коде есть: JSON `hit_check`, геттер `CheckSpec::isHitCheck()`. Rule и Character этим шагом не меняются. Сопротивление, смягчение S, повреждение, `putState`, лист, C11 и запись исхода роадмапа не входят. Хендлеры `injury_efficiency`, `exhaustion_wound` и `state_write` в Mechanic не ставятся.

## Физическая схема

Новых таблиц и колонок нет. `success` и `damage` по-прежнему живут в JSON итога уже существующих `game_strike_command` и `game_wide_strike_command`. В строки удара и целей они не пишутся. Строка `game_check` и process не открываются.

## Сверка закрытия и GameCheckRoll

- `GameStrikes::close` кладёт в `success` число `damageOf`, затем копирует его в `damage`. `GameWideStrikeResults::row` пишет одно и то же число в оба слота принятой цели. Это не `CheckRating`.
- `GameCheckRoll::isLaterStep` возвращает истину на `isHitCheck()`, и `checkOf` бросает `GAME_INVALID`. `assertRule` и `throwCheck` идут через `checkOf`. Соло, предложение и ответ G18 поэтому карточку попадания не принимают. `findInitiativeCode` отбирает `isInitiative()`, затем зовёт `throwCheck`: карточка с `isHitCheck()` до кубов не доходит. Этот фильтр шаг не ослабляет и в список «чужого шага» ничего не добавляет.
- `findInitiativeCode` обходит `getLiveRules()` и считает живые `CheckSpec` по геттеру. Битая версия и чужой тип в счёт не входят. Ровно одна — её код. Ноль или больше одной — отказ до броска. Попадание повторяет этот обход с `isHitCheck()`, не с литералом кода.
- `throwCheck` после `checkOf` берёт поток, цепочку предка, binding, пул, `IMechanicRolls::roll` и `rate`. Второй вызов порта и своя формула кубов не пишутся. `operations()` по-прежнему пустой список.
- `declareStrike` и `declareWideStrike` числа не считают. Подписи атаки не меняются. Кода проверки во входе нет. Готовые успех и урон — `INVALID_PARAMS`.
- `GameStrikeRules::evaluateDamage` остаётся числом `IFormulaEvaluations::evaluate` формулы урона. Реакции `ignore`, `dodge` и `block` это число не меняют.
- `GameStrikes` уже держит семь аргументов конструктора, `GameWideStrikes` — шесть. Седьмой и восьмой запрещены. `GameStrikeRules` держит четыре: срез, `ICharacters`, контекст формулы, обход. Пятая — уже существующий `GameCheckRoll`.
- `ICharacterActualMutations::apply` и `GameNpcRepository::replaceVersion` не вызываются. Character модуль Game не импортирует. Rule и Mechanic каталог хендлеров не растут.

## Зафиксировано (модель и права)

**Поиск.** Метод на `GameCheckRoll` рядом с `findInitiativeCode`. Берутся элементы `getLiveRules()`, у которых spec не битая и `getSpec()` — `CheckSpec` с `isHitCheck() === true`. Ровно одна — её код. Ноль или две и больше — `GAME_INVALID` до броска и до формулы урона, удар остаётся открытым, версия боя не растёт. Строка кода правила в PHP не появляется. `checkOf` этот поиск не вызывает: иначе единственная карточка попадания умерла бы в `isLaterStep`.

**Тот же roll и rate.** Отдельный метод `GameCheckRoll` считает уже найденную карточку. Внутри — тот же поток `solo`, лист атакующего, `$asked = null`, цепочка предка, binding, пул, `IMechanicRolls::roll` и `IMechanicRolls::rate`, что у `throwCheck`. Общий приватный хвост после отбора карточки; своего броска в `GameStrikeRules` нет. Сама карточка в `checkOf` не заходит: `checkOf` отвергает любой `isHitCheck()`. Остальные причины `isLaterStep` на ней остаются: `isConcentrationToken`, `isWillpower`, `isUnstableCheck` и вид `from_state`. `isLaterStep` для `throwCheck` не меняется. Виды трудности в `CheckDifficulty` — `ask`, `from_state` и `none`. `ask` при `$asked = null` уже отвергает `difficulty()`. После этих отказов остаётся вид `none`. `chain` по-прежнему зовёт `checkOf` для предка: предок с причиной `isLaterStep`, включая `isHitCheck`, даёт `GAME_INVALID`. Режим не `solo` и не `both`, нет `RollMechanicPayload` среди binding среза (`rollPayload`), пул без целого числа кубов или граней — `GAME_INVALID` из того же хвоста. Удар не закрывается. Трудность и кубы клиент не присылает. `declareCheck` и `proposeCheck` не вызываются. `throwCheck`, `assertRule` и `findInitiativeCode` подпись не меняют.

**Кого бросать.** Один раз на закрытие, атакующий со строки удара. Персонаж — `GameCheckRoll::characterSheet`. NPC — `GameNpcRepository::getById`, затем массив `sheet` внутри `getVersion()`. У `1 → N` бросок один на атаку, не на каждую цель. Защитник в пул не входит.

**Слоты.** `damage` — прежнее число `evaluateDamage`. `success` — `rating` итога попадания, целое `CheckRating::getRating()`. Bool `passed` в слот не пишется. Копия урона в `success` снимается. У `1 → 1` оба поля рядом с `sheetVersion`. У `1 → N` `GameWideStrikeResults::build` и `GameWideStrikes::commitClose` получают рейтинг рядом с уже передаваемым `damage`. `row` пишет их в разные слоты принятой цели. Отказ одной цели оставляет у неё `success` и `damage` пустыми. `sheetVersion` и `code` принятой цели остаются `null`. Атака обоих сценариев числа не возвращает.

**Отказ среза.** Нет карточки, их две, карточка не бросается, пул пуст или обход формулы урона отказал — удар не закрывается, `GAME_INVALID`, лист прежний, `targetResults` нет. Это не отказ одной цели G19. `RULE_INVALID` и `CHARACTER_INVALID` наружу не выпускаются.

**Порядок до записи.** Поиск карточки — отдельный вызов до `evaluateDamage`. Бросок — второй вызов, уже с найденным кодом. Версия боя читается до обоих: `battleOf` у `1 → 1` и у `1 → N`. Чужая версия боя — `GameBattleConflictException`, поиск и `roll` не вызываются. У `1 → 1` `damageOf` остаётся до транзакции. `assertSheet` сейчас внутри неё и позже `damageOf`. `roll` — после `assertSheet` и до `commands->add`: чужой `expectedSheetVersion` не вызывает `roll`, удар не закрывается, код по-прежнему `GAME_CONFLICT`. У `1 → N` поиск и `roll` оба до `commitClose`. Версия листа цели проверяется в `GameWideStrikeResults::refusal` и удар не отменяет: один `roll` атакующего уже выполнен, даже если у цели потом `code` — `GAME_CONFLICT`. Повтор с тем же телом возвращает сохранённый итог и второй раз `roll` не вызывает. Тот же ключ с другим телом — `GAME_CONFLICT`.

**Чего шаг не делает.** Не пишет лист, `putState`, сопротивление, смягчение S и повреждение. Не наполняет список операций. Не ставит хендлеры `injury_efficiency`, `exhaustion_wound` и `state_write`. Не меняет `CheckSpec` и каталог карточек. Не ослабляет отказ G18 и инициативы. Не открывает `game_check`. Не считает `N → 1`, `N → N`, DOT, каст и сцену. Не начинает запись исхода роадмапа.

## Что даёт этот заход

Закрытие `1 → 1` возвращает `damage` формулы урона и `success` — рейтинг единственной живой карточки `isHitCheck()`. Закрытие `1 → N` пишет ту же пару в каждую принятую цель. Нет такой карточки, их две или бросать нечего — удар остаётся открытым. Лист не меняется.

## Что не закрыто

- Запись числа в лист, C11 и G22 роадмапа.
- Сопротивление, смягчение S, повреждение, `putState`.
- Хендлеры `injury_efficiency`, `exhaustion_wound`, `state_write`.
- `N → 1`, `N → N`, DOT, каст, сцена.
- `personalNotes`.

## Точки кода

Старые планы не переписываются. Новых портов, action и ключей прав нет. `declareStrike`, `declareWideStrike`, `GameCheckRoll::throwCheck`, `checkOf`, `isLaterStep` и `findInitiativeCode` не меняются. Character Game не импортирует. Rule не меняется.

- `GameCheckRoll` — поиск одной живой `CheckSpec` с `isHitCheck()` и метод броска этой карточки в уже существующие `roll` и `rate`. Конструктор не растёт.
- `GameStrikeRules` — пятая зависимость `GameCheckRoll`. Рядом с `evaluateDamage` метод отдаёт рейтинг. `operations` остаётся пустым. Конструкторы `GameStrikes` и `GameWideStrikes` не растут: оба уже зовут `GameStrikeRules`.
- `GameStrikes::close` и `GameWideStrikeResults::row` — `success` больше не присваивается из `damage`.
- `GameStrikePortFactory` и `GameWideStrikePortFactory` собирают `GameCheckRoll` так же, как `GameCheckPortFactory` и `GameBattlePortFactory`: `ICharacterRuleSlices`, `ICharacters`, `IMechanics`, `IMechanicRolls`. Сейчас ударные фабрики контейнер Mechanic не берут. Нового ребра модуля нет: проверка и инициатива эти порты уже получают.

## Модуль

Нового порта Game нет. `events` остаётся `[]`. Новый код ошибки не заводится.

| Ситуация | Код |
|---|---|
| ноль или больше одной живой `CheckSpec` с `isHitCheck()` | `GAME_INVALID`, удар открыт |
| на карточке попадания жетон, воля, неустойчивость или `from_state`; предок не проходит `checkOf` | `GAME_INVALID`, удар открыт |
| режим не `solo`/`both`; нет payload `roll`; пул без числа кубов или граней | `GAME_INVALID`, удар открыт |
| отказ обхода формулы урона | `GAME_INVALID`, удар открыт |
| чужая версия боя; чужой `expectedSheetVersion` у `1 → 1` | `GAME_CONFLICT`, удар открыт |
| чужая версия листа одной цели `1 → N` | удар закрывается, у этой цели `code` — `GAME_CONFLICT`, слоты пустые |
| готовые успех и урон во входе | `INVALID_PARAMS` |

## Фасад

`IGameStrikes` и `IGameWideStrikes` не растут. Закрытия после сверки решения зовут рейтинг и урон. Бросок — метод `GameCheckRoll`, не `throwCheck`.

## HTTP

Новых action нет. `game.resolveStrike` по-прежнему отдаёт `success` и `damage`. `game.resolveWideStrike` заполняет эти поля у принятой цели. Ключи входа те же. `success` — рейтинг попадания. `damage` — число формулы урона.

## Тесты

Suite `game`. Suite `rule`, `character` и `mechanic` не расширяются. `worldWithWeapon` в `GameStrikeMysqlTest` и `GameWideStrikeMysqlTest` сейчас без карточки `check` и без механики `roll`. Без обеих существующее закрытие станет `GAME_INVALID` из `rollPayload`. В срез этих миров добавляются живой `check` с `hit_check: true` и правило с `mechanic_payload.type = roll`. Литерал кода в утверждении поиска не служит признаком: признак читается геттером. `GameCheckMysqlTest` уже держит карточку `strike` с `hit_check: true` и ждёт отказ `game.declareCheck`. Этот отказ остаётся.

Mysql `1 → 1`. Карточка попадания в фикстуре без `characteristicCode`: `diceCount()` иначе требует закупку, а `testDeclareAndResolveLeavesSheet` зовёт `admit` без неё. Payload — `diceCount: 1`, `dieFaces: 1`, без `dieSize` и без своей `efficiency`. `throwCheck` ставит эффективность 3 и размер 0; размер 0 — нейтральная точка `RollSpec`. Грань при `dieFaces: 1` всегда 1 (`floor(rng * 1) + 1`). `scoreBase` считает успех, если грань не больше эффективности, поэтому успех один. `rate` против трудности `{ base: 0, size: 0 }` даёт `getRating() === 1`. `testDeclareAndResolveLeavesSheet` сейчас ждёт `success` 0. После шага `success` — 1, `damage` меча без формулы — 0. `testResolveUsesProfileFormula` сейчас ждёт оба слота равными 4. После шага `damage` остаётся 4, `success` — 1. `apply` не вызывается. `game_check` не пишется. Повтор ключа возвращает те же числа и `roll` второй раз не вызывает.

Mysql отказа среза. Срез без `isHitCheck()` и срез с двумя такими карточками не закрывают удар, версии боя и листа те же. Карточка попадания без числа кубов или граней — тот же отказ.

Mysql `1 → N`. Та же фикстура броска. `GameWideStrikeMysqlTest` сейчас ждёт у принятой цели `success` и `damage` равными 0. После шага `damage` остаётся 0, `success` — 1. У двух принятых целей одна и та же пара. Чужая версия листа одной цели оставляет у неё оба слота пустыми и не затирает пару второй.

Mysql инициативы. `game.rollInitiative` по карточке с одним только `isHitCheck()` порядок не пишет. `isLaterStep` в тесте G18 на `hit_check: true` остаётся отказом.

## Todo

- [x] **find** — ровно одна живая `CheckSpec` с `isHitCheck()`. Иначе отказ до броска. Код правила литералом не зашит.
- [x] **roll** — эта карточка через `roll` и `rate`. `throwCheck` и `isLaterStep` отказ G18 и инициативы не теряют. `game_check` не пишется.
- [x] **slots** — `damage` остаётся формулой урона. `success` — рейтинг попадания. `build` и `commitClose` широкого удара принимают рейтинг отдельно от `damage`. Список операций пуст.
- [x] **gates** — phpunit `game`. Character не импортирует Game. Rule не меняется. Хендлеры ранения не появляются. Запись в лист не начинается.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| поиск и бросок | `GameCheckRoll` | одна живая `CheckSpec` с `isHitCheck()`, тот же `roll` и `rate` |
| расчёт удара | `GameStrikeRules` | рейтинг рядом с числом урона |
| закрытие `1 → 1` | `GameStrikes` | `success` — рейтинг, `damage` — формула |
| закрытие `1 → N` | `GameWideStrikes` | та же пара в `targetResults` |

## Acceptance

- Game находит проверку попадания по `CheckSpec::isHitCheck()` в срезе ревизии игры. Код карточки не зашит.
- Ровно одна живая карточка проходит в `IMechanicRolls::roll` и `rate`. Ноль, две и больше, или payload `roll` не из чего бросить — отказ, удар не закрывается.
- `GameCheckRoll::throwCheck` по-прежнему отвергает `isHitCheck` у проверки G18 и у инициативы.
- `damage` — число формулы урона. `success` — рейтинг попадания, не копия урона.
- Лист, `putState`, сопротивление, смягчение S и повреждение не появляются. Хендлеры `injury_efficiency`, `exhaustion_wound` и `state_write` не ставятся.
- Запись исхода роадмапа не начинается. Character не импортирует Game. Rule не меняется.

## Документы захода

этот файл; [`game-plan-21.md`](game-plan-21.md); [`game-plan-18.md`](game-plan-18.md); [`game-plan-20.md`](game-plan-20.md); [`game-plan-13.md`](game-plan-13.md); [`rule-system.md`](rule-system.md); [`php-coding-standards.md`](php-coding-standards.md).
