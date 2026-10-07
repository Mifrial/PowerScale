# План Character 10 — состояние на actual

**Статус:** PHP сделан. Канон — [`character-system.md`](character-system.md) (состояние на персонаже), [`rule-system.md`](rule-system.md) («Экземпляр может отсутствовать», тип `state`). Нарезка — [`character-roadmap.md`](character-roadmap.md): этот документ шаг туда не вписывает. C9 остаётся decay, DOT и кастом. C11 этим документом не переопределяется и не закрывается. Текущий каталог патча — [`game-plan-10.md`](game-plan-10.md), [`game-plan-11.md`](game-plan-11.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Сверка кода до плана. `CharacterSheetDocument::build` пишет девять ключей, среди них `money`; ключа `states` в снимке нет. Колонка `sheet` — `JsonField`, отдельной колонки под состояние нет. `ICharacterActualMutations` принимает `setInventoryQuantity`, `putInventoryQuantity` и `setMoney`. `StateSpec` — spec правила типа `state` (`value_type` — строка без перечня в `RootSpecs::state`, `aggregation`, флаги остатка повреждений, истощения, увечья, отравления и прочие). `CharacterResolvedRule::getSpec()` бывает `null`, если документ в тип не лёг. Kind, которым состояние пишется в actual, в каталоге нет.

Цель: тот же порт получает kind, которым в actual записывается уже названное состояние. Урон — число итога удара; в персонажа не пишется. Повреждение, рана, увечье, истощение и отравление — состояние на листе и различаются флагами `StateSpec` загруженной карточки. Какое это состояние, выбирает карточка среза, не зашитый код. Game этим шагом не меняется. G22 не пишется. Character Game не импортирует.

## Зафиксировано

- Kind явно: `putState`. Набор ключей операции, как у `setMoney` и количества: у `flag` ровно `kind` и `stateRuleCode`; у `number` и `dimensional` ровно `kind`, `stateRuleCode` и `value`. Других ключей нет. Поле листа, которое меняется: список `sheet.states`. Строка списка — `{ stateRuleCode }` для `flag` и `{ stateRuleCode, value }` для остальных. `value` у `number` — int. У `dimensional` — массив с ключами `base` и `size`, оба int. Первый `putState` создаёт список, если ключа не было. Ключ есть, но это не список — `CHARACTER_INVALID`, без записи. Повтор того же `stateRuleCode` дописывает ещё одну строку в порядке операций. Объединение повторов по `aggregation` этот шаг не делает. В одном списке `putState` идёт рядом с прежними kind: каждый меняет только своё.
- Живая карточка берётся срезом ревизии листа, `findLive`. Тип правила — `state`, `getSpec()` — `StateSpec`. Код конкретной карточки не зашивается. Нет карточки, tombstone, тип не `state`, spec не `StateSpec` или `value_type` не `flag`, `number` и не `dimensional` — обычный отказ этого среза, `CHARACTER_INVALID`, без записи. Форма значения не совпала с `value_type` — тот же отказ. Форма операции проверяется до среза. Карточка типа `poison` состоянием листа не является.
- Флаги `StateSpec` (`damage_remainder`, `damage_exhaustion`, `maim`, `poisoning` и остальные) шаг не читает и по ним ветку не выбирает. Они остаются на карточке. Вложенные `wound`, `maim`, `poison`, срок DOT, привязка каста и `wound.heldBy` в строку не пишутся.
- `apply` по-прежнему собирает девять производных ключей валидатором и пишет sheet из `nextSheet`. Возвращённый `applyToDocument` `sheet` сейчас в строку не попадает: `keepUnknown` копирует на сборку ключи снимка строки до патча, которых нет в `build`. `sheet.states` в сборку не входит. В строку должен попасть список после разбора операций, а не копия `states` до патча: иначе `putState` на `apply` не запишется. Не было операций `putState` — лежащий список не меняется; ключа не было — ключ не появляется. `applyToDocument` возвращает тот же список в своём `sheet` и строку `character` не пишет. `choices` эта операция не меняет. Проверка владельца в `CharacterActualPatch` не меняется.
- Прежние kind остаются. Полный лист, `replaceSection`, смена `equipped`, деньги и количество этим kind не пишутся. Урон, остаток после сопротивлений и РУ в операцию не входят и в лист не пишутся.
- Фасад прежний: `ICharacterActualMutations::apply` и `applyToDocument`. Нового метода и нового HTTP нет. Владелец по уже существующему `character.applyActualPatch` передаёт тот же массив операций; отдельного фильтра kind нет.
- Game поле не выбирает и `putState` не заводит. Код Game и Rule не меняется. Хендлеры `injury_efficiency`, `exhaustion_wound` и `state_write` в Mechanic не ставятся.

## Не закрыто

- C9: decay, периодичность, DOT и каст.
- C11: kind, которым удар пишет уже посчитанное число. Пока тот kind не назван, G22 не начинается. `putState` этим kind не является.
- Расчёт повреждения из урона. Вложенные поля раны, увечья и отравления. Объединение повторов по `aggregation` и влияние состояния на проверки, характеристики и бой.
- Ресурсы, экипировка и прочие будущие kind того же хода.

## Ошибки

- Пустой список операций, чужой `kind`, лишний ключ, пустой `stateRuleCode`, значение не по `value_type` — `CHARACTER_INVALID`, лист прежний.
- Нет живой карточки `state` в срезе, spec не `StateSpec`, ключ `states` есть и это не список — `CHARACTER_INVALID`. Нет ревизии — `CHARACTER_NOT_FOUND`, как у среза. Нет строки персонажа — `CHARACTER_NOT_FOUND` из `ICharacters::get`. Чужой владелец на HTTP — по-прежнему `CHARACTER_NOT_FOUND` из `CharacterActualPatch`, порт владельца не проверяет.
- Устаревшая `actual_version` — `CHARACTER_CONFLICT` до разбора операций, строка не меняется.
- `validate` по-прежнему может вернуть `CharacterSaveRejectedException` на `apply`. Отказ `putState` до записи до этого не доходит.

## Тест

Suite `character`. Документ: `putState` дописывает строку в `sheet.states`; `flag` без `value`, `number` и `dimensional` с разобранным значением; повтор кода — вторая строка; девять ключей снимка те же, что собрал валидатор; без `putState` список не создаётся и уже лежащий не стирается; `states` не список — `CHARACTER_INVALID`. Отказ среза: нет карточки, tombstone, тип не `state`, spec не `StateSpec`, чужой `value_type`, битое значение, лишний ключ — `CHARACTER_INVALID`, документ прежний. Чужой `kind` по-прежнему отказ. MySQL: `apply` пишет список после разбора, а не список снимка до патча, и поднимает `actual_version`; устаревшая версия строку не меняет; нет строки — `CHARACTER_NOT_FOUND`. Карточка в тесте — фикстура типа `state`, не именованный код правила. Урон в снимок не попадает. `IFormulaEvaluations`, код Game и реестр Mechanic не вызываются.
