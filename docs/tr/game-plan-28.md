# План Game 28 — слои цели на закрытии удара

**Статус:** `PARTIAL`, 2026-10-07. Закрыты проекция слоёв цели и базовый resistance path для `1 → 1` и `1 → N`; полный P1 и полный эффект блока не закрыты. В [`game-roadmap.md`](game-roadmap.md) шаг не вписывается. Канон состава — [`combat-layers-prerequisite.md`](combat-layers-prerequisite.md). Проекция — [`character-plan-12.md`](character-plan-12.md): `ICharacterCombatLayers::project`. Capability среза — [`mechanic-plan-07.md`](mechanic-plan-07.md): `IMechanicEngine::hasReliabilityCut` и маркер `IReliabilityCut`; production handler/content отдельно не подтверждены. Nullable threshold — [`rule-plan-08.md`](rule-plan-08.md): нет `durability` — `null`. Итог удара уже размерный — [`game-plan-26.md`](game-plan-26.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: закрытие `1 → 1` и `1 → N` считает `resistance` из проекции слоёв цели, а не из суммы слотов по `equippedModifiers`. Формула `injury` не меняется. `durabilityShave` не входит. Имена типов урона не сравниваются.

## Физическая схема

Новых таблиц и колонок нет. JSON итога те же ключи. `success` остаётся целым рейтингом `rateHit`. Меняется только пара `resistance`, а через уже существующую формулу — `injury`.

## Откуда слои

`evaluateResistance` больше не вызывает `sumArmor` и не читает `equippedModifiers`.

Вход проекции — `spaceId`, `rulesRevision` игры, `sheet` и `choices` цели, и `id` строки блока или `null`. Персонаж: `ICharacters::get` уже даёт оба документа. NPC: оба лежат в `version` строки, как читает `GameNpcs`. Сейчас в `evaluateResistance` приходит только `sheet`. Вызовы в `GameStrikes` и `GameWideStrikeResults` передают ещё `choices`, реакцию и рейтинг.

Пустой `getDamageTypeCode()` профиля по-прежнему сразу даёт `zero()`: проекция и срез не вызываются. Так уже делает `evaluateResistance`.

`CharacterInvalidException` проекции снаружи — `GAME_INVALID`. Удар не закрывается. Порт лист не пишет.

Слои `kind === defense` не входят, если у живого `DamageTypeSpec` типа урона профиля `isDefenseIgnored()`. Остальные слои входят, когда `getDamageTypeCode()` пуст или равен типу урона профиля. Слой `defense` без типа входит во все типы, пока флаг его не выключил.

## Срез

Рейтинг — целое, которое закрытие уже получило бы в `success`: у `1 → 1` это `rateOf`, у `1 → N` это аргумент `one`. Сейчас `GameStrikes` считает `resistanceOf` до `rateOf`. Шаг меняет порядок: рейтинг, затем сопротивление. В `one` рейтинг уже есть, но в `resistanceOf` не передаётся. Срез включён, только если `hasReliabilityCut` истинен для привязок живого правила `damage_type` с кодом типа урона профиля. `findLive` по этому коду один: повтор кода срез не собирает.

Привязки собираются из `CharacterResolvedRule::getMechanics()` этого правила, тем же смыслом, что `GameCheckRoll`: строка с целым `mechanic_id` становится `MechanicBinding`. Каталог — `IMechanics::getList()`. `ResolveActiveOptions` без фильтра. Код механики и `pay_sr` не сравниваются.

Включённый срез снимает слой, у которого `getDurability()` не `null` и это число не больше рейтинга. Слой с `null` остаётся. Выключенный срез не снимает ни один слой по надёжности.

Нет живого правила типа, spec битый или это не `DamageTypeSpec` — `GAME_INVALID`. Привязки нет — срез выключен, исключения нет. Привязка с id без каталога или хендлера — `MechanicInvalidException` из движка, снаружи `GAME_INVALID`. У `1 → N` `resistanceOf` вызывается снаружи `refusal`, поэтому такой отказ по-прежнему не становится `code` одной цели и не закрывает широкий удар.

## Source

После среза и фильтра типа остаётся общее сложение. У одного и того же непустого `sourceCode` остаются сильнейший бонус и сильнейший штраф. Пустой `sourceCode` уникален: такие слои не схлопываются. Сравнение пар — к меньшему размеру, как разность в `GameStrikeAmounts`, без `toInteger()`. Сумма оставшихся `getValue()` — та же `GameStrikeAmounts::sum`. Пустой список — `zero()`.

Source блока проекция уже заполнила. Этот шаг литерал «От блокирования» не подставляет и код предмета в source не пишет.

## Блок

Реакция не `block` — `project` вызывается с `blockInventoryId = null`. Слоёв блока нет.

Реакция `block`: среди надетых строк `choices.inventory` ищется строка с целым `id`, чьё `ruleCode` равно уже сохранённому `blockItemRuleCode`. Одна такая строка — её `id` уходит в `project`. Ноль или больше одной — `GAME_INVALID`. Код в id не превращается угадыванием.

Успех блока этот шаг не бросает отдельно: отдельного броска защитника в `GameStrikes` нет, `rateHit` отдаёт целое и пары автопровала `{0|-1}` не несёт. Пока броска защитника нет, слои блока входят при реакции `block` и найденном id. Ветка «автопровал атакующего отменяет блок» этим шагом не появляется. Поэтому это только подключение projection и базового resistance path, а не завершение block effect.

## Куда кладётся код

`GameStrikeRules` уже на пороге размера и числа зависимостей: в конструкторе пять портов. Новый класс считает срез, фильтр и source и вызывает `project`. Его создают `GameStrikePortFactory` и `GameWideStrikePortFactory` и передают в `GameStrikeRules` шестой зависимостью. Оба места уже делают `new GameStrikeRules`. `sumArmor`, `equippedCodes` и `armorSlots` удаляются. Публичный метод к `GameStrikeRules` не добавляется: остаётся `evaluateResistance`.

`injury`, `splitInjury`, `evaluateSoak` и запись `putDamageSplit` не меняются.

## Тесты

Suite `game`. Character и Mechanic не расширяются, кроме уже лежащих портов.

- Надетая броня даёт слои spec после модификаторов строки, не копию `equippedModifiers`.
- Снятый предмет в сумму не входит.
- Срез выключен — слой с порогом остаётся. Срез включён и порог не больше рейтинга — слой не входит. Порог `null` остаётся при включённом срезе.
- `isDefenseIgnored()` выкидывает слои `defense` и оставляет подходящие `resistance`.
- Два слоя с одним source оставляют сильнейший бонус и сильнейший штраф. Пустые source суммируются оба.
- `block` с одной надетой строкой этого кода добавляет слои профиля. Две надетые строки с тем же кодом — `GAME_INVALID`.
- `ignore` и `dodge` слои блока не добавляют.
- Персонаж и NPC с теми же документами дают одну пару `resistance`.

## Что остаётся для завершения P1

- Проверка блокирующего и отдельная ветка успешного/проваленного блока.
- Автопровал атакующего `{0|-1}` до чтения слоёв блока.
- `success = 1` только при успешном блоке.
- Penetration профиля и его применение только к защите.
- Authoritative eligibility и списание ОД реакции.
- Реальный production handler/content для capability среза надёжности.
- Сквозные тесты блока, penetration, reliability cut и delivery.
