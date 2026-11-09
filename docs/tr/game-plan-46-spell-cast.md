# План 46 — серверный каст заклинания

**Статус:** ~ПЛАН~, 2026-11-09.

**Реализация:** ~PHP~, ~VUE~.

**Опираться на:** ~PHP~ (`Game`, `Character`, `Rule`), ~VUE~ (`SpellCastDialog`,
`GameApi`), [`character-plan-13-path-study.md`](character-plan-13-path-study.md),
[`spell-plan-07-runtime-slice.md`](spell-plan-07-runtime-slice.md),
[`spell-plan-08-runtime-expansion.md`](spell-plan-08-runtime-expansion.md),
[`spell-plan-09-magic-runtime.md`](spell-plan-09-magic-runtime.md),
[`game-plan-45-strike-phases.md`](game-plan-45-strike-phases.md).
Моки `SpellCastExecutionService`, `MockGameApi` и `mockSpellImport` — `~НЕ ОСНОВА~`:
они показывают желаемый результат, но не являются контрактом и не подставляются
в real-режиме.

**Родитель:** [`game-roadmap.md`](game-roadmap.md).
**Предусловие:** [`character-plan-13-path-study.md`](character-plan-13-path-study.md).

Цель — перенести каст заклинания из клиентского расчёта в серверную команду
`game.castSpell`. Первый срез — `lightning-strike`: сервер сам проверяет
доступность карточки, считает сложность и бросок, урон, делит повреждение,
пишет лист и чат, а повтор команды возвращает тот же итог.

## 1. Границы

Входят:

- `game.castSpell` для `lightning-strike`;
- `has_ability` с доменом пути — как предусловие каста;
- проверка доступности конкретного экземпляра заклинания на выбранном пути;
- ОД и компоненты из `action_components` карточки;
- сложность сотворения из `MagicPathSpec` (мощь/контроль/проверка пути);
- серверный бросок `check-spell-cast`;
- проверка формы: заклинание с `targeting: entity` без цели — ошибка запроса;
- урон из `SpellDamage` (тип, ступени по опыту, falloff), сопротивление
  существующими слоями, `putDamageSplit` и Шок;
- идемпотентность, CAS кастера и цели, запись в одной транзакции;
- real-путь фронта: `SpellCastDialog` вызывает `game.castSpell` и не считает
  результат сам.

Не входят:

- касание (`discharge`, `hit_resolution: attack`) и второй бросок попадания;
- `ActiveSpell`, Генератор, поддержание, `refreshable`/`sustained`;
- `spell_upgrade` на каст (`chain-lightning`, `charge-accumulation`,
  `careful-magic`), насыщение и `spell_saturation`;
- магическое отклонение, арканный взрыв, стабильность, дестабилизация;
- источники магии (ядро, кристалл, накопители, проводники);
- battleground, реальная дистанция и координаты;
- autoFail «цель перестала быть валидной в момент исполнения» — игровая
  логика зарезервирована, но в этом срезе не воспроизводится: для неё нужен
  battleground;
- жетоны концентрации, `pending_action_effects`, charge/generator,
  `ActiveSpell`, цепочки, отклонение и насыщение на каст — сервер их не
  считает, клиент в real-режиме не списывает;
- новые таблицы и второй боевой движок.

## 2. Решения

1. **`has_ability` с доменом пути делаем сразу.** Без него
   `lightning-strike` на Арканисте может увидеть `discharge` с другого пути.
   Это отдельный срез 46.1, но он входит в план как предусловие.
2. **В real-режиме нет клиентского фолбэка.** Если сервер отклонил каст,
   диалог показывает ошибку и не считает урон/ОД локально. Мок-режим
   (`VITE_API_MODE !== 'real'`) может сохранить прежнее поведение, но он не
   является контрактом и не включается автоматически.
3. **Изучение не пересчитывается на касте.** Источник истины — сохранённый лист
   игры: экземпляр заклинания на пути и его эффективный уровень. Повторная
   проверка `study`/`magic_study` — задача save, а не каста. В real-режиме
   локальный `MagicStudyUnlockService::isAvailableForUse` не является
   авторитетом: доступность решает сервер по сохранённому листу.
4. **Требования проверяются в контексте пути каста.** Для `lightning-strike`
   это `has_ability: discharge` на том же пути или на пути, который каст-путь
   включает (`MagicPathSpec::includes_path_codes`, транзитивно).
5. **Сложность считает сервер.** Клиентский `SpellCastDifficultyService` может
   остаться для предпросмотра, но итог берётся из ответа сервера.
6. **Чат пишет сервер.** Иначе replay вернёт итог, а клиент напечатает
   сообщения второй раз.
7. **Цель и autoFail — разные вещи.** Для `targeting: entity` цель обязательна;
   запрос без цели — `INVALID_PARAMS`. AutoFail из настольной логики (цель
   вышла из зоны/перестала быть валидной во время сотворения) появится, когда
   появится battleground; в этом срезе такого пути нет.
8. **Последствия — существующие.** `GameStrikeConsequences` для электричества
   выполняет проверки Воли и увечья; нокаут — только для `blunt`. Отдельной
   ветки «для заклинаний» нет, поэтому ответ и чат должны включать
   `will`/`willOutcome`/`maim`.
9. **Компоненты — это компоненты действия.** `action_components` живут на
   общей базе способности; заклинания просто часто их используют. Отдельной
   «траты заклинания» нет: `GameActionResourceComponents`/
   `GameActionResourceResolver` обобщаются до `AbilityWithComponents` (его
   реализуют `AbilityActionSpec` и `AbilitySpellSpec`), и каст идёт тем же
   путём.
10. **Параметры заклинания и используемая мощь — разные вещи.**
    `parameters` — карта `код → значение` для всех параметров спеки: один `x`
    может быть и мощью, и контролем. `usedPower` — конкретная мощь источника
    (характеристики или предмета) вместе с идентификатором источника. Мощь
    урона берётся из `parameters`, а не из `usedPower`.

## 3. Модель

```text
game.castSpell
  → проверка прав, сессии и replay
  → срез игры и листы кастера/цели
  → доступность экземпляра заклинания на пути
  → ОД и компоненты
  → сложность и бросок check-spell-cast
  → провал / успех (autoFail «цель стала невалидной» — будущая логика)
  → урон, сопротивление, РУ, деление повреждения
  → запись цели и кастера в одной команде
  → чат и итог
```

Каст — это действие, но не `game.runAction` и не удар. У него нет окна
реакции и фаз удара; объявление, оплата и эффект происходят в одной команде.
Поэтому отдельный вход `game.castSpell`, а не новый `kind` в `runAction`.

## 4. Контракт `game.castSpell`

### 4.1 Запрос

```text
gameId: int
battleId: int
idempotencyKey: string
expectedVersion: int            // версия боя; проверяется, не меняется
expectedSheetVersion: int       // CAS листа кастера
expectedTargetVersion: int      // CAS листа цели
actor: {type: character|npc, id: int}
spellCode: string               // lightning-strike
pathCode: string                // arcanist | psionic | shaman
parameters: { [code: string]: int | {base: int, size: int} }  // значения параметров спеки
usedPower: {
  source: {kind: 'characteristic', code: string}
        | {kind: 'item', inventoryId: int}
        | {kind: 'grant', abilityCode: string},
  value: {base: int, size: int}
}
target: {type: character|npc, id: int}
distanceIpari?: int             // 0; поле зарезервировано под battleground
```

Правила формы:

- `parameters` — значения параметров спеки по коду, а не готовый урон:
  скаляр или размерное число по `AbilityParameter::kind`. Сервер проверяет
  каждое значение по `min`/`max` и требует параметр, если на него ссылается
  `SpellValue` (`spell.power`, `spell.control`). Один и тот же `x` может
  обслуживать и мощь, и контроль;
- `usedPower.source` — конкретная характеристика, предмет инвентаря или
  грант-предмет кастера; `usedPower.value` не выше живой мощи этого источника
  и не ниже минимума шкалы характеристик. `usedPower` влияет только на
  сложность, но не на урон;
- `target` обязателен: `lightning-strike` имеет `targeting: entity`.
  Запрос без цели — `INVALID_PARAMS`, а не autoFail. AutoFail «цель стала
  невалидной во время исполнения» — будущая игровая логика, не этот срез;
- `distanceIpari` не может быть отрицательным; в этом срезе значение по
  умолчанию `0`, falloff не режет урон;
- неизвестные ключи отклоняются, как в `game.runAction`.

### 4.2 Ответ

```text
cast: {
  difficulty: {base, size},
  needsCheck: bool,
  roll: null | {difficulty, roll, success, rating, faces, ...}
}
spentAp: int
caster: {type, id, sheetVersion}
target: {type, id, sheetVersion}
autoFail: false                    // в этом срезе всегда false
spellSr: int | null
spellDamage: {base, size} | null   // урон после falloff, до слоёв
injury: {base, size} | null        // после сопротивления и РУ
consequence: object                // истощение, Шок, putDamageSplit, will, maim
```

`spellDamage` — число из `SpellDamage`; `injury` — то, что реально ушло в
`putDamageSplit`. Фронт не пересчитывает ни то, ни другое.

Ошибки не кодируются в ответе: `AUTH_DENIED`, `INVALID_PARAMS`, `GAME_INVALID`,
`GAME_NOT_FOUND`, `GAME_BATTLE_CONFLICT` — исключения, как в `game.runAction`.
`started`/`refuseReason` остаются во внутреннем клиентском сервисе и не
являются серверным контрактом.

### 4.3 Ошибки

- `AUTH_DENIED` — актор не владелец кастера и не `edit_all`;
- `INVALID_PARAMS` — форма запроса, в том числе нет `target` при
  `targeting: entity`;
- `GAME_INVALID` — карточка не spell, путь не magic_path, экземпляр не на
  пути, нет ОД, значение параметра вне `min`/`max`, `usedPower` выше источника
  или ниже минимума шкалы;
- `GAME_NOT_FOUND` — цель не в составе боя;
- `GAME_BATTLE_CONFLICT` — stale `expectedVersion`, `expectedSheetVersion`
  или `expectedTargetVersion`;
- replay с тем же `idempotencyKey` и тем же телом возвращает сохранённый итог;
  другое тело — конфликт.

## 5. Серверный расчёт

### 5.1 Доступность

1. Срез игры: `CharacterRuleSlice` по `(spaceId, rulesRevision)`.
2. `spellCode` — живая карточка `AbilitySpellSpec`.
3. `pathCode` — живая карточка `MagicPathSpec`, `cast_check_code` не пуст.
4. В `choices` кастера есть ровно один экземпляр с `ruleCode = spellCode` и
   `domainCode = pathCode`. Экземпляр уже прошёл study-гейт при save; каст его
   не пересчитывает.
5. Эффективный уровень карточки в сохранённом листе
   (`sheet.abilityLevels[spellCode]`) не ниже 1.
6. Требования экземпляра выполнены в контексте `pathCode` (срез 46.1):
   `has_ability` ищет экземпляр нужной карточки на этом пути или на пути,
   который он включает.

Пункт 6 обязателен: уровень по коду может быть поднят экземпляром с другого
пути, поэтому одного `abilityLevels` недостаточно.

`coveredPaths` не используется как гейт каста: в Character 13 он оставлен
потребителем масок видимости, а не study/каста. Ключ `coveredPaths` к тому же
включает отображаемое имя домена (`instanceKey`), что делает его хрупким.

Проверку уровня и требований не дублировать: `GameCardAvailability::
assertAvailable()` уже делает это для действий и принимает `AbilityWithBase`
(а `AbilitySpellSpec` его реализует). В 46.1 ему добавляется необязательный
`?string $domainContext`; NPC-лист внутри него пересчитывается через
`ICharacterSheets::compute`, а не берётся из сохранённого `GameStrikeSheets`.

### 5.2 ОД и компоненты

- Компоненты — общие `action_components` способности, а не отдельная
  «трата заклинания». Поэтому `GameActionResourceComponents` и
  `GameActionResourceResolver` обобщаются до `AbilityWithComponents`, а не
  получают второй сборщик.
- `AbilityWithComponents extends AbilityWithBase` объявляет
  `getComponents()`; его реализуют `AbilityActionSpec` и `AbilitySpellSpec`
  (у обоих метод уже есть). `AbilityPlainSpec`/`AbilityProcessSpec` остаются
  без компонентов.
- `GameActionResourceComponents::of()` принимает `AbilityWithComponents`.
  `GameActionResourceResolver::resolve()` ищет живую спеку по коду и
  принимает любую с компонентами; для заклинаний `chosenAmounts` и
  `minimums` пустые, поэтому action-специфика не меняет поведение.
- Отдельного `GameSpellCastComponents` нет.
- Траты проверяются `GameActionPerform::assertSpends()` и списываются
  `GameActionPerform::spend()` с `expectedSheetVersion`; это уже умеет CAS и
  запись только ресурсов.
- `lightning-strike`: 4 ОД, соматика `occupy_hands: 1` в этом срезе не
  проверяется (как в M7).
- Если ОД не хватает — отказ до броска и без записи.
- ОД списываются **до** проверки сотворения: компоненты тратятся независимо
  от успеха, как в каноне M7.

### 5.2.1 Почему без отдельного сборщика

Компоненты — это компоненты действия: заклинание — такое же действие, просто
у него компоненты встречаются часто. Отдельная трата заклинания дублировала бы
обход `ResourceComponent`, проверку выбранных сумм и минимумов. Общий путь
даёт одинаковое поведение для `runAction`, удара и каста и не требует
отдельной ветки по типу карточки. Регрессия действий проверяется прежними
тестами `GameActionResourceResolver`.

### 5.3 Сложность

Числа берутся из спеки, не из кода заклинания:

- `path.power_characteristic_code` и `path.control_characteristic_code`
  (`magic-power`, `magic-control`);
- `spell.spell.power` (`parameter x` или фиксированное значение);
- `spell.spell.control` (у `lightning-strike` — `4|-1`);
- база `3|0`, нижняя живая сложность `3|-1`.

Формула — та же, что в `SpellCastDifficultyService`:

```text
parameters      = request.parameters
requiredPower   = resolve(spell.power, parameters)
usedPower       = request.usedPower.value
requiredControl = resolve(spell.control, parameters)
availableControl = sheet[path.control_characteristic_code]

powerShortage   = points(requiredPower) - points(usedPower)
controlShortage = points(requiredControl) - points(availableControl)

delta = 0
if powerShortage > 0: delta += powerShortage
if controlShortage > 0: delta += controlShortage
if powerShortage < 0 and controlShortage < 0:
  delta -= min(-powerShortage, -controlShortage)

difficulty = 3|0 + delta
if difficulty < 3|-1: skip (проверка не бросается)
```

Сопротивление добавляется только если у типа урона
`DamageTypeSpec::isModifiesSpellDifficulty() === true`. Для `electricity` флага
нет — сопротивление Аркане на `lightning-strike` не влияет. Обучение/помехи
каста в этом срезе не применяются; `requiredPowerDelta` и
`trainingDifficultyDelta` из клиентского расчёта сервер не принимает.

### 5.3.1 `parameters` и `usedPower`

`SpellCastDialog` различает два разных понятия:

- `parameters` — значения параметров спеки. `spell.power` и `spell.control`
  могут быть фиксированным числом или параметром; один `x` может быть и тем,
  и другим. Поэтому в запрос идёт карта `код → значение`, а не поле `power`.
- `usedPower` — «Используемая мощь»: конкретная характеристика или предмет
  кастера и её живая мощь, которую можно уменьшить. Она влияет на сложность,
  но не на урон.

Сервер валидирует:

- каждый `parameters[code]` — по `AbilityParameter::kind` и `min`/`max`
  (скаляр не принимается вместо размерного и наоборот);
- `usedPower.source` принадлежит кастеру: характеристика есть в листе,
  предмет — в инвентаре кастера, грант — у активной способности;
- `usedPower.value` не ниже минимума шкалы характеристик и не выше живой мощи
  источника.

Урон считается от `resolve(spell.power, parameters)`, а не от `usedPower`.
`availableControl` берётся из `path.control_characteristic_code` и листа; если
`spell.control` — параметр, его значение берётся из `parameters`.

### 5.4 Бросок

- Код проверки — `path.cast_check_code` (`check-spell-cast`).
- Пул — характеристика из `path.check_code` (`check-intellect` →
  `intellect`), передаётся `GameCheckThrowOverride`, потому что
  `check-spell-cast` разрешает смену характеристики.
- Сложность — вычисленная выше; `GameCheckRoll::throwWithDifficulty` не
  требует `ask`-решения.
- Если сложность ниже живой — броска нет (`needsCheck = false`).
- Цель обязательна для `targeting: entity`; запрос без цели отклоняется до
  броска и до записи. AutoFail «цель стала невалидной во время исполнения» —
  будущая логика, в этом срезе не реализуется.
- При будущем `autoFail` ОД списываются, броска и урона нет.

### 5.5 Урон

- `SpellDamage::experience_keyword_code` (`electromancy`) → опыт признака.
  Расчёт уже есть приватно в `CharacterDerivedLevels::experience()`
  (строка 88); вынести его в общий `CharacterKeywordExperience` и оттуда
  кормить и `CharacterDerivedLevels`, и каст. Для `lightning-strike` ступень
  при опыте `0` даёт `+0`; `discharge` в этот срез не входит, но второй
  расчёт заводить не надо.
- Источник уровней для опыта: сохранённый лист (эффективные уровни), не
  повторный пересчёт study. `CharacterDerivedLevels` сохраняет своё прежнее
  поведение (купленные уровни), пока его не переключат отдельно.
- `weaponDamage = resolve(spell.power, parameters) + stepModify` по шкале
  характеристик: использовать `CharacteristicNumber`; в PHP у него сейчас нет
  `modifyWith`/`modifyDiffTo` — добавить их или считать `base + 3×size` явно.
  `usedPower` здесь не участвует.
- `falloff` применяется, если задан: `free_ipari`, `size_per_extra_ipari`,
  `min`; при `distanceIpari = 0` урон не режется.
- РУ доставки: `hit_resolution.rating` (`2`), но не выше
  `DamageTypeSpec::getMaxSuccessRating()` (`electricity` — `3`); готовый
  helper — `GameStrikeOutcome::successRatingCap()`.
- Сопротивление — `GameStrikeDefense::resistanceByType` по типу
  `damage_type_code`; он уже учитывает `defense_ignored` (электричество
  игнорирует броню, но не типизированные resistance-слои).
- `injury = max(0, (weaponDamage − resistance) × appliedSr)`; смягчение
  (`soak`) для заклинания не применяется.
- `GameStrikeOutcome::splitInjury` делит повреждение по стойкости цели;
  `GameStrikeConsequences::effects` собирает `putDamageSplit`, истощение и
  Шок из хука `electricity`.
- Воля и увечье **срабатывают** и для электричества: `willEffect()` не
  зависит от типа урона, а `maimEffect()` получает `delta = quotient` при
  отсутствии cutting-хука. Нокаут — только для `blunt`. Отдельной ветки для
  заклинаний нет: ответ и чат должны включать `will`/`willOutcome`/`maim`.

### 5.6 Запись и идемпотентность

- Граница — `GameReplayTransaction::execute` + `GameCommandRepository`
  (`source = 'spell'`, тело — все поля запроса).
- Цель: `GameStrikeSheetWrites::write` с `expectedTargetVersion` и
  операциями из 5.5.
- Кастер: `GameStrikeSheetWrites::write`/`GameActionPerform::spend` с
  `expectedSheetVersion` и `ResourceSpend` на ОД.
- Обе записи — в одной транзакции команды. MySQL-тест обязан проверить:
  stale-версия цели откатывает и списание ОД; replay не пишет второй раз.
- `expectedVersion` боя проверяется до записи через
  `GameActionBattle::battleOf()`; каст его не меняет. Открытое действие в бою
  (`findOpenAny`) блокирует каст так же, как `runAction`.
- После успешной записи — сообщения чата через новый `GameSpellCastChat`
  (объявление, бросок каста, урон/провал, Воля, увечье). Тред — turn-тред
  `GameCombatThread::buildTurn()`, как у `GameActionChat`; автор —
  `actorUserId`. Чат входит в команду, чтобы replay не дублировал его.

## 6. Срез 46.1 — `has_ability` с доменом пути

Сейчас `AbilityRequirementCheck::hasAbility` смотрит только
`RequirementNumbers::levels[ability_code]`. Этого мало: `discharge` на
Псионике не должен закрывать `lightning-strike` на Арканисте.

Изменения:

- `RequirementTraits` получает **статические** карты:
  `abilityDomains[code] = [domainCode, ...]` и
  `pathCovers[pathCode] = [covered...]`. Уровни остаются в
  `RequirementNumbers::levels`; так `RequirementValues::withLevels()` не
  оставляет устаревшие уровни экземпляров.
- `CharacterRequirementFacts` заполняет домены и покрытие из `choices` и
  `CharacterStudyPaths::covered` (включения транзитивны); уровни — из
  переданного `levels`.
- `AbilityRequirementCheck::check(..., ?string $domainContext = null)`:
  при заданном домене `has_ability` проходит, если `levels[code]` не ниже
  `min_level` и у кода есть экземпляр на пути `domainContext` или на
  включённом пути. Если у требуемой карточки нет домена (обычная
  способность), работает прежняя проверка по коду — иначе доменный контекст
  сломает `has_ability` вне путей.
- `CharacterAbilityActivity` передаёт домен текущего экземпляра; для листа по
  коду берётся **максимум** активного уровня по экземплярам
  (`$next[$code] = max(...)`), а не последний.
- `GameCardAvailability::assertAvailable()` получает тот же
  `?string $domainContext` и передаёт его в `AbilityRequirementCheck`, чтобы
  `runAction` и `castSpell` проверяли одинаково.
- `has_ability_keyword` и владение оружием не меняются; особый случай
  Письменности не ломаем.

Тесты:

- `discharge` на Арканисте закрывает `lightning-strike` на Арканисте;
- `discharge` на Псионике не закрывает `lightning-strike` на Арканисте;
- `discharge` на Псионике закрывает шаманскую карточку, если Шаман включает
  Псионику;
- обратное включение не работает;
- старая проверка без домена сохраняет прежний результат;
- не-доменная способность в требовании проходит при заданном домене;
- `CharacterAbilityActivity` с двумя экземплярами одного кода берёт максимум.

## 7. Срез 46.2 — серверный `game.castSpell`

Новые поверхности:

| Слой | Что |
| --- | --- |
| Action | `CastGameSpellAction`, `CastGameSpellInput` |
| HTTP | `GameSpellCastHttp` |
| Разбор | `GameSpellCastBody` (ровно названные ключи, формы сторон) |
| Доступность | `GameSpellCastFacts` (срез, листы, экземпляр, путь, параметры) |
| Компоненты | `AbilityWithComponents` + общий `GameActionResourceResolver` |
| Расчёт | `GameSpellCastCheck` (сложность + бросок), `GameSpellCastDamage` (опыт, falloff, сопротивление, РУ) |
| Запись | `GameSpellCastWrite` (CAS цели и кастера, replay) |
| Чат | `GameSpellCastChat` |
| Ответ | `GameSpellCastView` |
| Конфиг | маршрут `game.castSpell` в `Game/module.config.php` |
| Сборка | `GameSpellCastPortFactory`/`GameStrikeHttpFactory` |

Переиспользовать, не дублировать:

- `GameStrikeReady`, `GameStrikeAccess` — права, видимость, сессия, replay;
- `GameReplayTransaction`, `GameCommandRepository` — идемпотентность;
- `GameStrikeSheets` — снимок цели и версия её листа; для NPC-кастера
  уровень и требования берутся из `GameCardAvailability` с пересчётом листа;
- `GameStrikeDefense::resistanceByType` — слои и `defense_ignored`;
- `GameStrikeAmounts::injury`/`appliedSr` — арифметика;
- `GameStrikeOutcome::splitInjury`/`successRatingCap`,
  `GameStrikeConsequences` — деление, `putDamageSplit`, состояния,
  Воля/увечье;
- `GameStrikeSheetWrites` — запись листа;
- `GameCheckRoll`, `GameCheckChain` — бросок и характеристика проверки;
- `AbilityWithComponents` + `GameActionResourceComponents`/
  `GameActionResourceResolver` — общие компоненты действия;
- `GameActionPerform::assertSpends`/`spend` — проверка и списание ОД;
- `GameCardAvailability` (с доменным контекстом) — уровень и требования;
- `CharacterStudyPaths`/`CharacterAbilityCost` — пути и стоимости;
- вынесенный `CharacterKeywordExperience` — опыт признака для `SpellDamage`.

`CharacterKeywordExperience` (вынести из `CharacterDerivedLevels`):

```text
experienceOf(slice, choices, levels, keywordCode): int
  сумма cost->toLevel(base, levels[code], parameters)
  по карточкам с levels[code] > 0 и keywordCode в признаках правила
```

`CharacterDerivedLevels` продолжает передавать купленные уровни; каст
передаёт эффективные уровни сохранённого листа. Один расчёт, но разные
источники уровней остаются явными.

## 8. Срез 46.3 — фронт в real-режиме

- `IGameApi` + `GameApi`: метод `castSpell(request)` → `game.castSpell`.
- `SpellCastDialog` в real-режиме:
  - не вызывает `spellCastExecutionService.execute`/`applySpellDamage`;
  - не списывает ОД через `persistSpentAp` и не пишет состояния вручную;
  - не тратит жетоны концентрации, не пишет `pendingActionEffects`, не
    трогает charge/generator/`ActiveSpell`/цепочки/отклонение/насыщение;
  - не строит `spellAvailable` на `MagicStudyUnlockService`: доступность
    решает сервер, клиент только показывает ошибку;
  - собирает `parameters` из всех параметризованных `SpellValue`
    (`spell.power`, `spell.control`, будущие поля), а не только из мощности;
  - отправляет `spellCode`, `pathCode`, `parameters`, `usedPower` (источник +
    значение), `target`, версии листов;
  - показывает итог из ответа; при ошибке — сообщение, без локального
    пересчёта и без «попробовать локально»;
  - после успеха обновляет оверлеи/листы из сервера, а не патчит их сама;
  - не отправляет чат каста сам: чат пишет сервер.
- Предпросмотр сложности/стоимости может остаться клиентским, но он не
  является результатом: авторитетный бросок и урон — только сервер.
- `MockGameApi.castSpell`: мок-режим может сохранить прежний локальный расчёт
  для демо, но он не вызывается в real и не является контрактом. Если
  мок-режим не нужен — метод бросает `throwPhpUnsupported`, а не подставляет
  локальный результат.
- `HitLaunchDialog`/касание не трогаем: `lightning-strike` — auto.

## 9. Срезы

| Срез | Содержание | Результат |
| --- | --- | --- |
| 46.1 | `has_ability` с доменом пути | Character-тесты; `discharge` на пути не путается между путями |
| 46.2 | опыт признака и общие компоненты | `CharacterKeywordExperience` (вынести из `CharacterDerivedLevels`) + `AbilityWithComponents` в общем `GameActionResourceResolver` |
| 46.3 | `game.castSpell` + тесты | серверный каст, replay, CAS, `putDamageSplit`, Шок, Воля/увечье |
| 46.4 | фронт real | `SpellCastDialog` через `game.castSpell`, без локального расчёта |
| 46.5 | ручная проверка и гейт | НПС покупает `lightning-strike`, кастует в цель, save/повтор стабильны |

## 10. Тесты

Unit:

- сложность: дефицит мощи, дефицит контроля, оба дефицита, оба избытка,
  нижняя живая граница, skip;
- `lightning-strike`: значение параметра вне `min`/`max` — отказ;
  `usedPower.value` выше живой мощи источника — отказ; без цели при
  `targeting: entity` — `INVALID_PARAMS`;
- один параметр `x` используется и мощью, и контролем — сервер резолвит оба;
- `usedPower` влияет на сложность, но не на урон; урон берётся из
  `parameters`;
- компоненты: заклинание списывает 4 ОД через общий
  `GameActionResourceResolver`, регрессия действий не меняется;
- урон: ступень `SpellDamage` по опыту `electromancy`, falloff, cap РУ,
  `defense_ignored` для электричества, typed resistance;
- последствия: Воля и увечье для электричества, нокаута нет;
- доступность: экземпляр не на пути, уровень 0, требование на другом пути;
- `has_ability` с доменом: свои/включённые/чужие пути, не-доменная карточка.

MySQL:

- успешный каст: ОД списаны, цель получила `putDamageSplit` и Шок, версии
  выросли;
- попадание с `quotient > 0`: Воля и увечье отработали, нокаута нет;
- провал проверки: ОД списаны, урона нет;
- нет цели при `targeting: entity`: `INVALID_PARAMS`, ничего не записано;
- нет ОД: отказано, ничего не записано;
- stale battle version: конфликт до записи;
- stale target: откат, ОД не списаны;
- replay того же ключа: тот же итог, второй записи и второго чата нет;
- другое тело с тем же ключом: конфликт.

Frontend:

- `GameApi.castSpell` отправляет ровно контрактные поля и не вызывает
  локальный execute;
- ошибка сервера показывается без локального урона;
- успешный ответ обновляет лист/оверлей;
- в real-режиме диалог не тратит концентрацию и не пишет
  pending/charge/generator;
- мок-режим не влияет на real-путь.

## 11. Гейт

- `composer stan`;
- `composer test:unit`;
- Game/Character PHPUnit (включая MySQL каста);
- `npx vitest run` по Character/Game spellCast;
- `composer docs-check` и `composer maps-check`;
- ручная проверка на НПС: купить `lightning-strike` (опыт пути станет 4),
  скастовать в цель, проверить ОД, урон, Шок, повтор команды и save;
- полный `bash tools/test-full.sh` — после ручной проверки, по подтверждению
  пользователя.

## 12. Риски и открытые вопросы

- **Дистанция.** Battleground нет; `distanceIpari` пока `0`. Когда появится
  расстояние, falloff начнёт резать урон без изменения контракта.
- **Соматика.** `occupy_hands` в M7 не проверялась; в этом срезе сохраняем
  поведение и не добавляем проверку рук.
- **Чат.** Сервер должен писать его внутри команды; иначе replay напечатает
  сообщения повторно. Формат — минимальный: объявление, бросок, урон/провал,
  Воля, увечье.
- **Уровни экземпляров.** `RequirementTraits` хранит только домены, уровень
  берётся из `RequirementNumbers::levels` по коду. Если у одной карточки два
  экземпляра на разных путях с разными активными уровнями, код-уровень —
  максимум; доменный контекст различает требования, но не уровень. В этом
  срезе такого контента нет; per-instance уровни — отдельный шаг.
- **Legacy-ревизии.** Каст не пересчитывает `study`; ревизии ≤45 работают,
  если сохранённый лист уже содержит экземпляр заклинания на пути.
- **Опыт признака.** `CharacterKeywordExperience` выносится из
  `CharacterDerivedLevels`; каст передаёт эффективные уровни листа, редактор
  — купленные. Для `lightning-strike` ступень `0` не зависит от опыта.
- **NPC-кастер.** Проверка прав идёт через `GameStrikeAccess`; лист NPC
  пересчитывается `GameCardAvailability`/`ICharacterSheets::compute`, а не
  берётся из сохранённого `GameStrikeSheets`.
- **`usedPower`.** Передаётся как источник + значение; сервер проверяет
  принадлежность источника и потолок. Если источник — предмет, для 46.3
  достаточно самой характеристики/предмета из листа, без отдельного движка
  источников.
- **Компоненты.** Общий `GameActionResourceResolver` меняется с
  `AbilityActionSpec` на `AbilityWithComponents`; это затрагивает стабильный
  action-путь, поэтому обязательна регрессия существующих тестов действий.
- **Параметр контроля.** Текущий диалог строит `parameters` только для
  `spell.power`; для `lightning-strike` контроль фиксированный, но общий
  контракт требует собрать значения всех параметризованных `SpellValue`.
- **Воля/увечье.** Существующие последствия срабатывают и для электричества;
  это не отдельная ветка и не отключается.
- **AutoFail.** Игровая логика «цель перестала быть валидной» появится с
  battleground; сейчас запрос без цели — ошибка.

## 13. Журнал

| Дата | Результат |
| --- | --- |
| 2026-11-09 | План создан: `game.castSpell` для `lightning-strike`; `has_ability` с доменом пути — предусловие; real-фронт без локального фолбэка; урон через `SpellDamage`, `putDamageSplit` и Шок. |
| 2026-11-09 | Перепроверка по коду: цель и autoFail разделены; компоненты — общие компоненты действия, `AbilityWithComponents` вместо отдельного сборщика; Воля/увечье срабатывают; `coveredPaths` не гейт; домены вместо per-instance уровней; опыт вынести из `CharacterDerivedLevels`; `parameters` — карта код→значение, `usedPower` — источник+значение. |
