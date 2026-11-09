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
- автопровал при `hit_resolution: auto` без обязательной цели;
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
   проверка `study`/`magic_study` — задача save, а не каста.
4. **Требования проверяются в контексте пути каста.** Для `lightning-strike`
   это `has_ability: discharge` на том же пути или на пути, который каст-путь
   включает (`MagicPathSpec::includes_path_codes`, транзитивно).
5. **Сложность считает сервер.** Клиентский `SpellCastDifficultyService` может
   остаться для предпросмотра, но итог берётся из ответа сервера.
6. **Чат пишет сервер.** Иначе replay вернёт итог, а клиент напечатает
   сообщения второй раз.

## 3. Модель

```text
game.castSpell
  → проверка прав, сессии и replay
  → срез игры и листы кастера/цели
  → доступность экземпляра заклинания на пути
  → ОД и компоненты
  → сложность и бросок check-spell-cast
  → автопровал / провал / успех
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
power: {base: int, size: int}   // x заклинания
target: {type: character|npc, id: int}
distanceIpari?: int             // 0; поле зарезервировано под battleground
```

Правила формы:

- `power` — размерное число, а не готовый урон; сервер проверяет его по
  `AbilityParameter::min`/`max` карточки;
- `target` обязателен: `lightning-strike` имеет `targeting: entity` и
  `hit_resolution: auto`, без цели каст автопроваливается после оплаты;
- `distanceIpari` не может быть отрицательным; в этом срезе значение по
  умолчанию `0`, falloff не режет урон;
- неизвестные ключи отклоняются, как в `game.runAction`.

### 4.2 Ответ

```text
started: true
refuseReason: null | not_enough_ap | unavailable_spell | invalid_target
cast: {
  difficulty: {base, size},
  needsCheck: bool,
  roll: null | {difficulty, roll, success, rating, faces, ...}
}
spentAp: int
caster: {type, id, sheetVersion}
target: {type, id, sheetVersion}
milk: bool
autoFail: bool
spellSr: int | null
spellDamage: {base, size} | null   // урон после falloff, до слоёв
injury: {base, size} | null        // после сопротивления и РУ
consequence: object                // узкий итог: истощение, Шок, putDamageSplit
```

`spellDamage` — число из `SpellDamage`; `injury` — то, что реально ушло в
`putDamageSplit`. Фронт не пересчитывает ни то, ни другое.

### 4.3 Ошибки

- `AUTH_DENIED` — актор не владелец кастера и не `edit_all`;
- `INVALID_PARAMS` — форма запроса;
- `GAME_INVALID` — карточка не spell, путь не magic_path, экземпляр не на
  пути, нет ОД, `x` вне диапазона, цель не в бою;
- `GAME_BATTLE_CONFLICT` — stale `expectedVersion`, `expectedSheetVersion`
  или `expectedTargetVersion`;
- replay с тем же `idempotencyKey` и тем же телом возвращает сохранённый итог;
  другое тело — конфликт.

## 5. Серверный расчёт

### 5.1 Доступность

1. Срез игры: `CharacterRuleSlice` по `(spaceId, rulesRevision)`.
2. `spellCode` — живая карточка `AbilitySpellSpec`.
3. `pathCode` — живая карточка `MagicPathSpec`, `cast_check_code` не пуст.
4. В `choices` кастера есть экземпляр с `ruleCode = spellCode` и
   `domainCode = pathCode`; `sheet.coveredPaths` для этого экземпляра
   содержит `pathCode`.
5. `sheet.abilityLevels[spellCode] >= 1`.
6. Требования уровня экземпляра выполнены в контексте `pathCode` (срез 46.1):
   `has_ability` ищет экземпляр нужной карточки на этом пути или на пути,
   который он включает.

Пункт 6 обязателен: уровень по коду может быть поднят экземпляром с другого
пути, поэтому одного `abilityLevels` недостаточно.

### 5.2 ОД и компоненты

- Ресурсные компоненты карточки (`ResourceComponent`, `amount`) складываются
  через `GameActionResourceResolver`.
- `lightning-strike`: 4 ОД, соматика `occupy_hands: 1` в этом срезе не
  проверяется (как в M7).
- Если ОД не хватает — отказ до броска и без записи.
- ОД списываются **до** проверки сотворения: компоненты тратятся независимо
  от успеха, как в каноне M7.

### 5.3 Сложность

Числа берутся из спеки, не из кода заклинания:

- `path.power_characteristic_code` и `path.control_characteristic_code`
  (`magic-power`, `magic-control`);
- `spell.spell.power` (`parameter x` или фиксированное значение);
- `spell.spell.control` (у `lightning-strike` — `4|-1`);
- база `3|0`, нижняя живая сложность `3|-1`.

Формула — та же, что в `SpellCastDifficultyService`:

```text
requiredPower  = resolve(spell.power, {x: power})
usedPower      = sheet.magic-power
requiredControl = spell.control
availableControl = sheet.magic-control

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
`DamageTypeSpec::modifiesSpellDifficulty() === true`. Для `electricity` флага
нет — сопротивление Аркане на `lightning-strike` не влияет. Обучение/помехи
каста в этом срезе не применяются.

### 5.4 Бросок

- Код проверки — `path.cast_check_code` (`check-spell-cast`).
- Пул — характеристика из `path.check_code` (`check-intellect` →
  `intellect`), передаётся `GameCheckThrowOverride`, потому что
  `check-spell-cast` разрешает смену характеристики.
- Сложность — вычисленная выше; `GameCheckRoll::throwWithDifficulty` не
  требует `ask`-решения.
- Если сложность ниже живой — броска нет (`needsCheck = false`).
- `hit_resolution: auto` без цели — `autoFail`, броска нет, но ОД уже
  списаны.

### 5.5 Урон

- `SpellDamage::experience_keyword_code` (`electromancy`) → опыт признака
  через новый `CharacterKeywordExperience` (сумма стоимостей активных
  карточек с этим признаком). Для `lightning-strike` ступень при опыте `0`
  даёт `+0`; для `discharge` — `+3`, поэтому сервис нужен уже сейчас.
- `weaponDamage = power + stepModify` по шкале характеристик.
- `falloff` применяется, если задан: `free_ipari`, `size_per_extra_ipari`,
  `min`; при `distanceIpari = 0` урон не режется.
- РУ доставки: `hit_resolution.rating` (`2`), но не выше
  `DamageTypeSpec::maxSuccessRating()` (`electricity` — `3`).
- Сопротивление — `GameStrikeDefense::resistanceByType` по типу
  `damage_type_code`; он уже учитывает `defense_ignored` (электричество
  игнорирует броню, но не типизированные resistance-слои).
- `injury = max(0, (weaponDamage − resistance) × appliedSr)`; смягчение
  (`soak`) для заклинания не применяется.
- `GameStrikeOutcome::splitInjury` делит повреждение по стойкости цели;
  `GameStrikeConsequences::effects` собирает `putDamageSplit`, истощение и
  Шок из хука `electricity`. Нокаут/увечье/Воля для электричества не
  срабатывают, но передаются существующим механизмам, а не отдельной ветке.

### 5.6 Запись и идемпотентность

- Граница — `GameReplayTransaction::execute` + `GameCommandRepository`
  (`source = 'spell'`, тело — все поля запроса).
- Цель: `GameStrikeSheetWrites::write` с `expectedTargetVersion` и
  операциями из 5.5.
- Кастер: `GameStrikeSheetWrites::write`/`GameActionPerform::spend` с
  `expectedSheetVersion` и `ResourceSpend` на ОД.
- Обе записи — в одной транзакции команды. MySQL-тест обязан проверить:
  stale-версия цели откатывает и списание ОД; replay не пишет второй раз.
- `expectedVersion` боя проверяется до записи; каст его не меняет.
- После успешной записи — сообщения чата через новый `GameSpellCastChat`
  (объявление, бросок, урон/молоко/провал). Чат входит в команду, чтобы replay
  не дублировал его.

## 6. Срез 46.1 — `has_ability` с доменом пути

Сейчас `AbilityRequirementCheck::hasAbility` смотрит только
`RequirementNumbers::levels[ability_code]`. Этого мало: `discharge` на
Псионике не должен закрывать `lightning-strike` на Арканисте.

Изменения:

- `RequirementTraits` получает карту экземпляров:
  `abilityInstances[code] = [{domainCode, level}, ...]` и карту покрытия
  путей `pathCovers[pathCode] = [covered...]`;
- `CharacterRequirementFacts` заполняет их из `choices`, эффективных уровней
  и `CharacterStudyPaths::covered` (включения транзитивны);
- `AbilityRequirementCheck::check(..., ?string $domainContext = null)`:
  при заданном домене `has_ability` проходит, если есть экземпляр нужного
  кода на этом пути или на включённом пути с уровнем не ниже `min_level`;
  без домена поведение прежнее;
- `CharacterAbilityActivity` передаёт домен текущего экземпляра при проверке
  его требований; для листа по коду остаётся максимум активного уровня;
- `has_ability_keyword` и владение оружием не меняются; особый случай
  Письменности не ломаем.

Тесты:

- `discharge` на Арканисте закрывает `lightning-strike` на Арканисте;
- `discharge` на Псионике не закрывает `lightning-strike` на Арканисте;
- `discharge` на Псионике закрывает шаманскую карточку, если Шаман включает
  Псионику;
- обратное включение не работает;
- старая проверка без домена сохраняет прежний результат.

## 7. Срез 46.2 — серверный `game.castSpell`

Новые поверхности:

| Слой | Что |
| --- | --- |
| Action | `CastGameSpellAction`, `CastGameSpellInput` |
| HTTP | `GameSpellCastHttp` |
| Разбор | `GameSpellCastBody` (ровно названные ключи, формы сторон) |
| Доступность | `GameSpellCastFacts` (срез, листы, экземпляр, путь, `x`) |
| Расчёт | `GameSpellCastCheck` (сложность + бросок), `GameSpellCastDamage` (опыт, falloff, сопротивление, РЛ) |
| Запись | `GameSpellCastWrite` (CAS цели и кастера, replay) |
| Чат | `GameSpellCastChat` |
| Ответ | `GameSpellCastView` |
| Конфиг | маршрут `game.castSpell` в `Game/module.config.php` |
| Сборка | `GameSpellCastPortFactory`/`GameStrikeHttpFactory` |

Переиспользовать, не дублировать:

- `GameStrikeReady`, `GameStrikeAccess` — права, видимость, сессия, replay;
- `GameReplayTransaction`, `GameCommandRepository` — идемпотентность;
- `GameStrikeSheets` — снимки листов и CAS-конфликты;
- `GameStrikeDefense::resistanceByType` — слои и `defense_ignored`;
- `GameStrikeAmounts::injury`/`appliedSr` — арифметика;
- `GameStrikeOutcome::splitInjury`, `GameStrikeConsequences` — деление и
  `putDamageSplit`/состояния;
- `GameStrikeSheetWrites` — запись листа;
- `GameCheckRoll`, `GameCheckChain` — бросок и характеристика проверки;
- `GameActionResourceResolver` — компоненты ОД;
- `CharacterStudyPaths`/`CharacterAbilityCost` — пути и стоимости;
- новый `CharacterKeywordExperience` — опыт признака для `SpellDamage`.

`CharacterKeywordExperience`:

```text
experienceOf(slice, choices, levels, keywordCode): int
  сумма cost->toLevel(base, effectiveLevel, parameters)
  по активным карточкам, у правила которых есть keywordCode
```

Его же позже использует редактор/лист, чтобы не считать опыт признака второй
раз.

## 8. Срез 46.3 — фронт в real-режиме

- `IGameApi` + `GameApi`: метод `castSpell(request)` → `game.castSpell`.
- `SpellCastDialog` в real-режиме:
  - не вызывает `spellCastExecutionService.execute`/`applySpellDamage`;
  - не списывает ОД через `persistSpentAp` и не пишет состояния вручную;
  - отправляет `spellCode`, `pathCode`, `power`, `target`, версии листов;
  - показывает итог из ответа; при ошибке — сообщение, без локального
    пересчёта и без «попробовать локально»;
  - после успеха обновляет оверлеи/листы из сервера, а не патчит их сама.
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
| 46.2 | `CharacterKeywordExperience` | опыт `electromancy` для `SpellDamage` |
| 46.3 | `game.castSpell` + тесты | серверный каст, replay, CAS, `putDamageSplit`, Шок |
| 46.4 | фронт real | `SpellCastDialog` через `game.castSpell`, без локального расчёта |
| 46.5 | ручная проверка и гейт | НПС покупает `lightning-strike`, кастует в цель, save/повтор стабильны |

## 10. Тесты

Unit:

- сложность: дефицит мощи, дефицит контроля, оба дефицита, оба избытка,
  нижняя живая граница, skip;
- `lightning-strike`: `power` вне `min`/`max` — отказ; без цели — autoFail;
- урон: ступень `SpellDamage` по опыту `electromancy`, falloff, cap РУ,
  `defense_ignored` для электричества, typed resistance;
- доступность: экземпляр не на пути, уровень 0, требование на другом пути;
- `has_ability` с доменом: свои/включённые/чужие пути.

MySQL:

- успешный каст: ОД списаны, цель получила `putDamageSplit` и Шок, версии
  выросли;
- провал проверки: ОД списаны, урона нет;
- autoFail без цели: ОД списаны, урона нет;
- нет ОД: отказано, ничего не записано;
- stale target: откат, ОД не списаны;
- replay того же ключа: тот же итог, второй записи и второго чата нет;
- другое тело с тем же ключом: конфликт.

Frontend:

- `GameApi.castSpell` отправляет ровно контрактные поля и не вызывает
  локальный execute;
- ошибка сервера показывается без локального урона;
- успешный ответ обновляет лист/оверлей;
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
  сообщения повторно. Формат — минимальный: объявление, бросок, урон/молоко/
  провал.
- **Уровни экземпляров.** Для `has_ability` с доменом берём уровень
  экземпляра из эффективных уровней по коду. Если у одной карточки два
  экземпляра на разных путях с разными активными уровнями, понадобится
  отдельная карта уровней экземпляров; в этом срезе такого контента нет,
  вопрос фиксируем.
- **Legacy-ревизии.** Каст не пересчитывает `study`; ревизии ≤45 работают,
  если сохранённый лист уже содержит экземпляр заклинания на пути.
- **Опыт признака.** `CharacterKeywordExperience` считает по эффективному
  уровню карточки; для `lightning-strike` ступень `0` не зависит от опыта,
  но для `discharge` это уже важно.
- **NPC-кастер.** Проверка прав идёт через `GameStrikeAccess`; NPC-лист
  читается тем же `GameStrikeSheets`.

## 13. Журнал

| Дата | Результат |
| --- | --- |
| 2026-11-09 | План создан: `game.castSpell` для `lightning-strike`; `has_ability` с доменом пути — предусловие; real-фронт без локального фолбэка; урон через `SpellDamage`, `putDamageSplit` и Шок. |
