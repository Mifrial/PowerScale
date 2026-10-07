# План Game 20 — исход удара

**Статус:** исход удара в PHP сделан. `BACKEND_OPEN`. Suite `game` по `GameStrikeMysqlTest` и `GameWideStrikeMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G20. Удар `1 → 1` — [`game-plan-13.md`](game-plan-13.md): готовый урон во входе не принимается; список операций пуст; персонаж через порт G10, NPC через `npc.version` уже лежащей строки; повтор ключа эффект второй раз не применяет. Широкий удар — [`game-plan-19.md`](game-plan-19.md): `targetResults[]` уже есть; у принятой цели `success` и `damage` — `null`; отказ одной цели остаётся в её записи. Проверка — [`game-plan-18.md`](game-plan-18.md): соло и pairwise; этот шаг их не заменяет и `game_check` не пишет. Обход формулы — [`rule-plan-03.md`](rule-plan-03.md): `IFormulaEvaluations::evaluate` считает узел в число. Контекст листа — [`character-plan-09.md`](character-plan-09.md): `ICharacterFormulaContexts`. Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. Закрытие уже открытого удара `1 → 1` и `1 → N` подставляет успех и урон в итог команды. Вход расчёта — уже проверенные решения этих шагов и `WeaponProfile` того же среза. Клиент по-прежнему шлёт решения и ожидаемые версии. Готовые успех и урон во входе не принимаются. Проверка G18 и `CheckSpec` этот расчёт не заменяют. Если список операций цели не пуст, лист пишется тем же commit, что закрытие удара: персонаж через порт G10, NPC через `npc.version` существующей строки. В этом заходе поле листа под урон не появляется, список операций остаётся пустым, порт не вызывается. Повтор команды с тем же ключом эффект второй раз не применяет. `endBattle` сессию не гасит.

Зависимость — G13, G19, шаг 14 Rule и Character C10. `N → 1`, `N → N`, инициатива, DOT, каст и сцена не входят.

## Физическая схема

Новых таблиц и колонок нет. Успех и урон живут в JSON итога уже существующих `game_strike_command` и `game_wide_strike_command`. В строки удара и целей они не пишутся.

## Сверка G10, G13, G18 и G19

- `declareStrike` и `declareWideStrike` числа не считают. Подписи атаки не меняются. Готовые успех и урон во входе по-прежнему `INVALID_PARAMS`.
- `GameStrikeRules::operations` возвращает пустой список. Этот шаг метод не наполняет: в каталоге G10 нет `kind` урона, фиктивный `kind` не заводится. Ветка «список не пуст → порт в том же commit» в `GameStrikes` и `GameWideStrikes` уже есть и не переписывается.
- `IGameChecks`, `CheckSpec` и `IMechanicRolls` закрытие удара не вызывает. `rate` остаётся `CheckRating` проверки.
- `IFormulaEvaluations` — порт Rule. Game передаёт уже разобранный узел и `FormulaContext`. Rule лист не читает.
- `ICharacterActualMutations::apply` и `GameNpcRepository::replaceVersion` этот заход не вызываются: списки пусты. Подписи не меняются.
- Character модуль Game не импортирует.

## Зафиксировано (модель и права)

**Один расчёт на оба закрытия.** Метод `GameStrikeRules`, не новый порт. Его вызывают закрытия `resolveStrike` и `resolveWideStrike` после сверки решения. Объявление удара расчёт не вызывает. Строка цели широкого удара хранит только защитника. Атакующий, предмет и профиль лежат на строке `game_wide_strike`, её отдаёт `findOpen`. Число считается по этой строке один раз и передаётся в `GameWideStrikeResults::build`. Репозиторий NPC у `GameStrikeRules` не появляется: фасад уже держит `GameNpcRepository` и для атакующего NPC передаёт в метод массив `sheet`.

**Профиль.** `assertAttack` профиль не возвращает. На строке удара уже лежат `item_rule_code`, `profile_type` и `profile_index`. Закрытие читает тот же профиль ещё раз и берёт `WeaponDamage::getFormula()`. Нет ключа `damage.formula` в спеке — узел `FixedNode(0)`, как `Formulas::dimensional`.

**Контекст.** Один на закрытие, от атакующего со строки удара. Персонаж — `ICharacterFormulaContexts::buildStored`. NPC — `build` от `sheet` внутри `GameNpcRecord::getVersion()`: версия NPC — объект с ключом `sheet`, не сам снимок. Порт Character таблицы NPC не читает. Параметры и базы действий в контексте пустые. Защитник, реакция, `dodgeBenefit`, точность, пробитие и дистанция в формулу не подставляются. `CharacterInvalidException` и `CharacterNotFoundException` наружу не выпускаются: на оба сценария это `GAME_INVALID`, удар не закрывается. Это не отказ одной цели: формула общая.

**Урон.** Один вызов `IFormulaEvaluations::evaluate(WeaponDamage::getFormula(), context)` на закрытие. Это число слота `damage` у принятой цели. У `1 → N` оно одно на все принятые цели. Второй обход дерева в Game не пишется.

**Успех.** Слот `success` — то же число, что `damage`, у принятой цели. Это не бросок, не `CheckRating` и не сравнение с защитой. Отдельной формулы попадания у профиля нет: точность — уже `DimensionalNumber`, не узел. Реакции `ignore`, `dodge` и `block` число не меняют.

**Отказ обхода.** `RULE_INVALID` Game наружу не выпускает. Формула одна на атакующего, поэтому отказ обхода не делит цели: удар не закрывается, `GAME_INVALID`, лист прежний, `targetResults` нет. Отказ одной цели по версии листа, составу и реакции остаётся как в G19.

**Слоты.** У `1 → N` числа встают в уже существующие `success` и `damage` принятой записи `targetResults`. `sheetVersion` и `code` у принятой цели остаются `null`. У `1 → 1` итог закрытия получает те же два поля рядом с `sheetVersion`. Атака обоих сценариев числа не возвращает.

**Запись листа.** Список операций по-прежнему пуст, поэтому `apply` и `replaceVersion` не вызываются, версии листов те же. Когда у цели список не пуст, её лист пишется в том же commit, что закрытие удара и одна новая версия боя. Этот заход такой список не производит.

**Повтор.** Журналы команд те же. Повтор с тем же телом возвращает сохранённый итог уже с числами и второй раз формулу не считает. Тот же ключ с другим телом — `GAME_CONFLICT`.

**Чего шаг не делает.** Не меняет тела атаки и защиты. Не пишет `game_check` и process. Не вызывает `IMechanicRolls`. Не считает защиту, пробитие, точность и `dodgeBenefit`. Не заводит `kind` урона. Не считает `N → 1`, `N → N`, инициативу, DOT, каст и сцену. Не останавливает сессию.

## Что даёт этот заход

Закрытие `1 → 1` возвращает `success` и `damage` — одно число формулы урона профиля. Закрытие `1 → N` пишет это число в оба слота каждой принятой цели. Отказ цели по-прежнему без чисел. Лист не меняется. Сессия остаётся запущенной.

## Что не закрыто

- Поле листа под урон и непустой список операций каталога G10.
- Защита, пробитие, точность, `dodgeBenefit`.
- `N → 1`, `N → N`, инициатива, DOT, каст, сцена.
- `personalNotes`.

## Точки кода

Старые планы не переписываются. Новых таблиц, action и ключей прав нет. `declareStrike` и `declareWideStrike` не меняются. Character Game не импортирует.

- Расчёт — метод `GameStrikeRules`. Конструкторы `GameStrikes` и `GameWideStrikes` не растут. В `GameStrikeRules` добавляются `IFormulaEvaluations` и `ICharacterFormulaContexts`: сейчас там срез и `ICharacters`.
- `GameStrikeRules::operations` остаётся пустым списком.
- `GameStrikePortFactory` и `GameWideStrikePortFactory` обе делают `new GameStrikeRules(...)`. Обе передают эти порты из контейнеров Rule и Character. Ребра Game → Mechanic нет. Character Game не импортирует.
- `GameWideStrikeMysqlTest` сейчас ждёт `success` и `damage` равными `null`. Эти проверки меняются на число закрытия. Новый suite для того же факта не заводится.

## Модуль

Нового порта Game нет. `events` остаётся `[]`. Новый код ошибки не заводится. `RULE_INVALID`, `CHARACTER_INVALID` и `CHARACTER_NOT_FOUND` на закрытии становятся `GAME_INVALID`, удар остаётся открытым.

## HTTP

Новых action нет. `game.resolveStrike` в успехе добавляет `success` и `damage`. `game.resolveWideStrike` заполняет эти поля у принятой цели. Ключи входа те же. Готовые числа во входе — `INVALID_PARAMS`.

## Тесты

Suite `game`. Suite `character` и `rule` не расширяются.

Mysql `1 → 1`. Текущая фикстура меча — профиль `type: strike` без `damage.formula`, то есть `FixedNode(0)`. Закрытие возвращает `success` и `damage` равными 0. `apply` и `replaceVersion` не вызываются. `IMechanicRolls` и `IGameChecks` не вызываются. Урон во входе — `INVALID_PARAMS`. Повтор ключа возвращает те же числа и версию боя второй раз не увеличивает.

Mysql `1 → N`. У двух принятых целей оба слота — одно и то же число, `code` — `null`. Чужая версия листа одной цели оставляет у неё `success` и `damage` пустыми и не затирает число второй. Битый лист атакующего не закрывает удар и `targetResults` не возвращает.

Mysql сессии. `endBattle` сессию не останавливает. Колонки удара под успех и урон не появляются.

## Todo

- [x] **resolve** — один расчёт на `resolveStrike` и `resolveWideStrike`. `damage` и `success` — число `evaluate` формулы урона. Список операций пуст. `IMechanicRolls` не вызывается.
- [x] **wide** — число только у принятой цели. Отказ одной цели не затирает число другой.
- [x] **gates** — phpunit `game`. Готовые числа во входе отвергаются. Повтор не считает формулу второй раз. Character не импортирует Game. Проверка G18 не становится моделью удара.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| расчёт удара | `GameStrikeRules` | `ICharacterFormulaContexts`, `IFormulaEvaluations`, число слотов |
| сборка | `GameStrikePortFactory`, `GameWideStrikePortFactory` | порты Rule и Character |
| закрытие `1 → 1` | `GameStrikes` | `success` и `damage` рядом с `sheetVersion` |
| закрытие `1 → N` | `GameWideStrikes` | те же числа в `targetResults` |

## Acceptance G20

- Сервер считает успех и урон одного удара для уже существующего `1 → 1` и `1 → N`.
- Вход расчёта — проверенные решения, `WeaponProfile` и `FormulaContext` атакующего из `ICharacterFormulaContexts`. Число даёт `IFormulaEvaluations`, не свой обход дерева в Game.
- Готовые успех и урон во входе не принимаются.
- Проверка G18 и `CheckSpec` этот расчёт не заменяют.
- Список операций пуст, лист не меняется. Ветка записи в том же commit остаётся на непустой список: персонаж через порт G10, NPC через `npc.version` существующей строки.
- Повтор ключа эффект второй раз не применяет. Инициатива, DOT, каст и сцена не появляются.
- Character не импортирует Game.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G20; [`game-plan-13.md`](game-plan-13.md); [`game-plan-19.md`](game-plan-19.md); [`game-plan-18.md`](game-plan-18.md); [`rule-plan-03.md`](rule-plan-03.md); [`character-plan-09.md`](character-plan-09.md); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md).
