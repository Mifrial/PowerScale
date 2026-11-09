# План M2 — улучшения каста

**Статус:** ~ПЛАН~, 2026-11-10.

**Реализация:** ~PHP~, ~VUE~.

**Опираться на:** ~PHP~ (`Rule/Dto/Spec/Ability/SpellUpgrade`,
`Rule/Spec/AbilityCombat::spellUpgrade`, `Game/Service/GameSpellCastFacts`,
`GameSpellCastCheck`, `GameSpellCastDamage`, `GameSpellCastView`,
`Game/Service/GameStrikeSpendCosts`, `Game/Dto/GameSpellCastPrepared`),
~VUE~ (`SpellCastDialog`, `SpellCastUpgradeList`, `SpellCastUpgradeService`,
`GameApi`, `GamePhpSpellCastRequest`, `GamePhpSpellCastPayload`),
[`spell-real-roadmap.md`](spell-real-roadmap.md),
[`spell-real-plan-01-action-components.md`](spell-real-plan-01-action-components.md),
[`game-plan-46-spell-cast.md`](game-plan-46-spell-cast.md),
[`spell-roadmap.md`](spell-roadmap.md),
[`magic-content-design-01-arcanist-progression.md`](magic-content-design-01-arcanist-progression.md)
(§7 «Пронзающее волшебство»).

**Не основа:** `SpellCastExecutionService`, `MockGameApi`,
`SpellCastDifficultyService` и `SpellCastUpgradeService` в мок-режиме —
~НЕ ОСНОВА~. Это UI-предпросмотр и мок; серверный каст ими не проверяется.

**Родитель:** [`spell-real-roadmap.md`](spell-real-roadmap.md), шаг M2.
**Связанные:** [`spell-roadmap.md`](spell-roadmap.md) (§12, `careful-magic`),
[`magic-content-plan-01-arcanist-entry.md`](magic-content-plan-01-arcanist-entry.md),
[`magic-content-plan-04-arcanist-mastery.md`](magic-content-plan-04-arcanist-mastery.md).

M1 закрыл общий слой компонентов действия. M2 впервые подключает к касту
`spell_upgrade`: улучшение выбирает игрок, но применяет сервер. Шаг узкий:
только три скалярных поля — `action_point_delta`, `check_advantage`,
`resistance_penetration_per_step`. Цепь и заряды остаются на M13 и M8.

## 1. Границы

Входят:

- чтение `spell_upgrade` у **изученных** способностей кастера;
- применимость улучшения к выбранному заклинанию и пути:
  `parent_ability_code`, `domain_ref: 'magic-path'`, `any_path`, экземпляр
  `multiple`-навыка;
- выбор улучшений в диалоге каста и передача их в запрос;
- `action_point_delta` — добавка к стоимости сотворения **до** `max` с
  ударом касания;
- `check_advantage` — преимущество проверки сотворения (сумма источников);
- `resistance_penetration_per_step` — увеличение Требуемой мощи на `x` шагов
  и снижение сопротивления магии на `per_step × x` (для сложности и урона);
- эффективное сопротивление не ниже нуля;
- отказ до списания ОД, броска, записи листа и чата при чужом/неизученном
  улучшении;
- серверный ответ с применёнными улучшениями, строка чата об улучшениях;
- тесты Rule/Game/Character-фикстур и фронта.

Не входят:

- `spell_upgrade.chain` (M13) и `spell_upgrade.charge_cap` (M8);
- `check_efficiency` (M3), насыщение (M4), отклонение (M5), активные
  заклинания (M6), источники и ядро (M9–M10);
- пересчёт изучения (`magic_study`) на касте: источник истины — сохранённые
  `choices` и пересчитанный лист, как в плане 46;
- новые таблицы, вторая модель сложности, клиентский расчёт как авторитет;
- цепочки, многоцелевые касты и дистанция;
- изменение стоимости ударного профиля касания, кроме уже существующего
  `max` с заклинанием.

## 2. Что уже есть

| Место | Что делает | Чего не делает |
| --- | --- | --- |
| `Rule/Dto/Spec/Ability/SpellUpgrade.php` | хранит `action_point_delta`, `charge_cap`, `check_advantage`, `any_path`, `resistance_penetration_per_step`, `chain` | не решает применимость и не считает эффект |
| `Rule/Spec/AbilityCombat::spellUpgrade` | разбирает `spell_upgrade` в `AbilityBase` | — |
| Контент | `careful-magic` (`+1 ОД`, `+1` преимущество, `domain_ref: magic-path`), `piercing-magic` (`any_path`, `resistance_penetration_per_step: 2`), `chain-lightning`, `charge-accumulation` | первые две карточки не читаются Game-кастом |
| `GameSpellCastFacts::prepare` | доступность, параметры, мощь, контроль, траты, руки | `spell_upgrade` не читает; `upgrades` в запросе нет |
| `GameSpellCastCheck` | сложность, сопротивление к сложности, бросок | не получает преимущества от улучшений и пробитие |
| `GameSpellCastDamage` | урон от `resolve(spell.power)`, сопротивление, falloff, последствия | не различает «мощь заклинания» и «Требуемую мощь»; пробитие не применяет |
| `GameStrikeSpendCosts` | `max` трат удара и заклинания для касания | улучшения не знает |
| `GameSpellCastBody`, `GameRunActionSpellBody`, `GameSpellTouchBody` | строгий разбор запроса | поля `upgrades` нет |
| `SpellCastUpgradeService` (фронт) | список применимых, выбор, `chipLabel`, `actionPointDelta`, `requiredPowerDelta`, `resistancePenetration` | мок/UI; серверного контракта не задаёт |
| `SpellCastUpgradeList` (фронт) | чекбоксы и поле `х` | показывает и `chain`/`charge`, которые M2 не поддерживает |
| `SpellCastDialog` (фронт) | считает предпросмотр с улучшениями | в real-запрос улучшения не уходят |

Вывод: Rule уже умеет разбирать `spell_upgrade`, но Game не подключён.
Фронт умеет выбирать улучшения, но только для мока; real-каст их теряет.

## 3. Модель

### 3.1. Один объект разрешения

Новый `Game/Service/GameSpellCastUpgrades` читает срез и `CharacterChoices`,
проверяет выбранные коды и возвращает `GameSpellCastUpgradeResolution`.
Он не пишет лист и не зависит от диалога.

```php
final class GameSpellCastUpgrades
{
    public function resolve(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $abilityLevels,
        string $spellCode,
        string $pathCode,
        array $selected,          // list<array{code: string, value: int|null}>
    ): GameSpellCastUpgradeResolution;
}
```

`GameSpellCastUpgradeResolution` — неизменяемый результат:

```php
final class GameSpellCastUpgradeResolution
{
    /** @return list<GameSpellCastAppliedUpgrade> */
    public function getApplied(): array;
    public function getActionPointDelta(): int;
    public function getRequiredPowerDelta(): int;
    public function getResistancePenetration(): int;
    /** @return list<RollAdvantage> */
    public function getAdvantages(): array;
    public function isEmpty(): bool;
}
```

`GameSpellCastAppliedUpgrade` хранит `ruleCode`, `name`, `value`,
`actionPointDelta`, `checkAdvantage`, `resistancePenetration`,
`requiredPowerDelta`. Этого достаточно для чата, ответа и тестов.

### 3.2. Применимость

Для каждого выбранного кода:

1. Живая карточка в срезе; `getSpec()` реализует `AbilityWithBase`;
   `getBase()->getSpellUpgrade()` не `null`.
2. Уровень: `abilityLevels[code]` не ниже 1 **и** у кастера есть
   `CharacterAbilityChoice` этого кода с уровнем не ниже 1.
3. Если задан `parent_ability_code` — он должен совпадать с `spellCode`.
   Путь не проверяется.
4. Если родителя нет, `spell_upgrade.domain_ref === 'magic-path'`:
   - `any_path: true` — подходит любой изученный экземпляр;
   - иначе нужен экземпляр с `domainCode === pathCode`.
5. Если родителя нет и `domain_ref` другой/пустой — улучшение не применимо.
6. Один код можно выбрать один раз за каст. `multiple` — это признак
   экземпляра пути (`domainCode`), а не счётчик повторений. Повтор кода в
   запросе — отказ.

`careful-magic` — экземпляр пути: `+1 ОД` и `+1` преимущество только на
касте того пути, где он изучен. `piercing-magic` — `any_path`: изучен на
Арканисте, но применяется к касту любого пути. Это же поведение уже описано
в `spell-roadmap.md` §12.

Изучение и требования на касте повторно не считаются: карточка уже куплена,
её уровень есть в листе. Проверяется только принадлежность и применимость.

### 3.3. Значение `x`

`resistance_penetration_per_step` — единственное поле M2, которому нужно
число. Контракт:

- `value` — целое `>= 0`, по умолчанию `1`, если поле не передано;
- `value` разрешено только у улучшения с `resistance_penetration_per_step`;
- у улучшения без этого поля `value` должно отсутствовать или быть `0`;
- верхнего предела сервер не вводит; `999` в UI — только удобство поля.

Шаги и сопротивление:

```text
requiredPowerDelta      = Σ value
resistancePenetration   = Σ value × resistance_penetration_per_step
```

Сумма по всем выбранным улучшениям. Для текущего контента это одна карточка.

### 3.4. Применение эффекта

**ОД.** `action_point_delta` складывается и добавляется к трате ресурса хода
в тратах заклинания:

```text
spellSpends = resolve(action_components заклинания)
spellSpends = spellSpends + {turnResource: ΔОД}
```

Для касания это происходит **до** `GameStrikeSpendCosts::maxSpends`, поэтому
итог остаётся `max(удар с надбавками, заклинание + улучшения)`. При касте без
касания те же траты идут в `GameActionPerform::assertSpends`.

**Требуемая мощь и урон.** Это два разных числа, и их нельзя склеивать:

```text
spellPower       = resolve(spell.power, parameters)      // урон
requiredPower    = spellPower.modify(requiredPowerDelta) // сложность
```

`requiredPower` участвует в дефиците мощи и сложности; `spellPower` — в
уроне (`GameSpellCastDamage`). Иначе «Пронзающее волшебство» увеличивало бы
и урон, чего канон не говорит.

**Преимущество.** `check_advantage` суммируется и передаётся в проверку
сотворения как список `RollAdvantage` с кодом и именем улучшения. Бросок
касания это не затрагивает.

**Сопротивление.** `resistancePenetration` уменьшает:

- сопротивление к сложности в `GameSpellCastCheck::difficultyResistance`
  (после флага `modifies_spell_difficulty`);
- сопротивление урону в `GameSpellCastDamage::apply` перед `injury`.

В обоих местах эффективное сопротивление — `max(0, сопротивление −
пробитие)`. Если сопротивления нет, пробитие не даёт эффекта, но
`requiredPowerDelta` сохраняется.

### 3.5. Что M2 не принимает

Выбранное улучшение с `chain` или `charge_cap` отклоняется
(`GAME_INVALID`), а не применяется частично: иначе игрок увидит «Цепную
молнию» с оплатой ОД, но без цепи. Фронт в real-режиме такие улучшения не
показывает; в мок-режиме они остаются. Это временное ограничение до M13/M8.

## 4. Контракт

### 4.1. Запрос

В `game.castSpell` и в блок `spellCast` касания добавляется необязательное
поле:

```json
{
  "upgrades": [
    { "code": "careful-magic" },
    { "code": "piercing-magic", "value": 2 }
  ]
}
```

- отсутствие поля — пустой список;
- список, не карта; каждый элемент — объект ровно с `code` и необязательным
  `value`;
- `code` — непустая строка; повтор кода запрещён;
- `value` — целое `>= 0`.

Разбор один для трёх входов: `GameSpellCastBody`, `GameRunActionSpellBody`,
`GameSpellTouchBody`. Для этого вводится
`Game/Service/GameSpellCastUpgradeBody`, который возвращает
`list<array{code: string, value: int|null}>`. Дублировать правила в трёх
парсерах нельзя.

`CastGameSpellInput` получает `array $upgrades = []` (в конце, чтобы не
ломать существующие позиционные вызовы). `GameRunActionSpellDeclare`
передаёт `context['upgrades']`.

### 4.2. Подготовка

`GameSpellCastPrepared` получает:

- `DimensionalNumber $spellPower` — мощь заклинания для урона;
- `GameSpellCastUpgradeResolution $upgrades` — разрешённые улучшения.

`requiredPower` остаётся Требуемой мощью для сложности. `spent` уже включает
`action_point_delta`.

### 4.3. Ответ

В `cast` добавляется список применённых улучшений:

```json
{
  "upgrades": [
    {
      "code": "piercing-magic",
      "name": "Пронзающее волшебство",
      "value": 2,
      "actionPointDelta": 0,
      "checkAdvantage": 0,
      "resistancePenetration": 4,
      "requiredPowerDelta": 2
    }
  ]
}
```

`GamePhpSpellCastResult.cast.upgrades` — необязательное поле, чтобы старые
ответы и моки не ломались. Фронт может показывать его в деталях, но итог
каста по-прежнему только серверный.

### 4.4. Ошибки

- неизученное/чужое улучшение — `GameInvalidException` с понятным текстом;
- неподдержанное поле (`chain`, `charge_cap`) — отказ;
- неверная форма `upgrades` — `ActionException INVALID_PARAMS` на разборе
  тела (как остальные поля запроса).

Ошибки домена не пишут чат и не меняют лист. Фронт показывает свой текст,
серверный — как запасной.

## 5. Точки проверки

| Вход | Где | Что делает |
| --- | --- | --- |
| `game.castSpell` | `GameSpellCastBody` → `GameSpellCastUpgradeBody` | форма списка |
| `game.runAction`, `kind: 'spell'` | `GameRunActionSpellBody` → тот же парсер | форма списка |
| касание, `spellCast` | `GameSpellTouchBody` → тот же парсер | форма списка |
| подготовка каста | `GameSpellCastFacts::prepare` | разрешение улучшений, ОД, Требуемая мощь |
| проверка | `GameSpellCastCheck` | преимущества, пробитие сопротивления к сложности |
| урон | `GameSpellCastDamage` | `spellPower`, пробитие сопротивления урону |
| стоимость касания | `GameStrikeSpendCosts` | `max` уже учитывает ОД улучшений |
| ответ | `GameSpellCastView` | список применённых улучшений |
| чат | `GameSpellCastChat` | строка об улучшениях в треде заклинания |

## 6. Фронт

- `GamePhpSpellCastRequest` и `GamePhpSpellCastPayload` получают
  `upgrades?: GamePhpSpellCastUpgrade[]`.
- `GameApi.castSpell` прокидывает `upgrades` в `context`; касание идёт через
  `spellCast`-блок и существующий `runAction`, поэтому отдельного API не
  нужно.
- `SpellCastUpgradeService` получает `isRuntimeSupported(option)` и
  `listRuntimeApplicable(...)`: в real-режиме фильтруются `chain` и
  `charge_cap`. В мок-режиме список остаётся полным.
- `SpellCastDialog`:
  - `upgradeOptions` в real-режиме — отфильтрованный список;
  - при выборе `piercing-magic` значение `x` по умолчанию `1`; в запрос
    уходит `value` только для улучшений с `resistance_penetration_per_step`;
  - стоимость и предпросмотр уже считают `action_point_delta`; менять
    формулу не нужно, только источник списка;
  - `runCast` и `runPhpTouchCast` добавляют `upgrades` в запрос/`spellCast`.
- `SpellCastUpgradeList` не переписывается: он уже умеет чекбоксы и поле `x`.
- `HitLaunchDialog` и `GameChatTab` не меняют логику: они передают payload
  как есть, тип расширяется.
- Сервер остаётся авторитетом: локальные `requiredPowerDelta` и
  `resistancePenetration` — только предпросмотр.

## 7. Срезы

| Срез | Содержание | Результат |
| --- | --- | --- |
| 2.1 | `GameSpellCastUpgradeBody`, `GameSpellCastUpgrades`, DTO разрешения; unit-тесты применимости | сервер умеет проверить выбор без записи |
| 2.2 | `GameSpellCastFacts` + `GameSpellCastCheck` + `GameSpellCastDamage`: ОД, `spellPower`/`requiredPower`, преимущество, пробитие | каст с `careful-magic`/`piercing-magic` считает всё |
| 2.3 | касание: `GameSpellTouchBody`/`GameSpellTouchFacts`; `max` с ударом | улучшения работают в обоих каналах каста |
| 2.4 | `GameSpellCastView` и чат | ответ и тред заклинания показывают улучшения |
| 2.5 | фронт: типы, `GameApi`, диалог, real-фильтр | выбор уходит на сервер, мок не ломается |
| 2.6 | документация и гейт | план/роудмап/46 актуализированы, проверки зелёные |

## 8. Тесты

Unit:

- `GameSpellCastUpgradeBodyTest`: пустое поле; список с `value`; отсутствие
  `value`; список вместо карты; лишний ключ; повтор кода; отрицательный
  `value`.
- `GameSpellCastUpgradesTest`:
  - `careful-magic` на своём пути — `+1 ОД`, `+1` преимущество;
  - `careful-magic` на чужом пути — отказ;
  - `piercing-magic` с `any_path` на чужом пути — разрешён;
  - `parent_ability_code` совпадает/не совпадает;
  - неизученный код — отказ;
  - повтор кода — отказ;
  - `value` `0`, `1`, `2`: `requiredPowerDelta` и `resistancePenetration`
    считаются по формуле;
  - `chain`/`charge_cap` — отказ;
  - `multiple` на двух доменах: выбирается экземпляр нужного пути, второй не
    даёт права.
- `GameCheckRoll`/`GameCheckThrowOverride`: список преимуществ улучшений
  складывается с преимуществами состояний; порядок и подписи источников.
- `GameSpellCastBodyTest`: `upgrades` доходят до нормализованного тела.

Game/MySQL:

- `careful-magic`: каст `lightning-strike` тратит `4 + 1 = 5` ОД, в броске
  есть преимущество с источником `careful-magic`, `cast.upgrades` заполнен;
- без улучшения те же 4 ОД и броска-преимущества нет (регрессия);
- `piercing-magic` при `x = 2`: Требуемая мощь +2, сложность растёт на
  ожидаемую величину, сопротивление к сложности уменьшается (если у цели
  есть сопротивление), эффективное сопротивление не ниже нуля;
- тот же каст без улучшения: `spellDamage` не меньше, чем с пробитием;
  `piercing-magic` не увеличивает урон от `spellPower`;
- чужой путь: `careful-magic`, изученный на Арканисте, на Псионике —
  `GAME_INVALID`, ОД не списаны, лист и цель не изменены, чат пуст;
- неизученное улучшение — тот же отказ;
- касание: `spellCast` с `careful-magic` даёт `max(удар, 4 + 1)`, при
  завершении ОД не списываются второй раз;
- replay с тем же ключом и теми же улучшениями возвращает тот же результат;
  тот же ключ с другим набором улучшений — конфликт;
- `chain-lightning`/`charge-accumulation` в `upgrades` — отказ.

Фронт (`vitest`):

- `listRuntimeApplicable` оставляет `careful-magic`/`piercing-magic` и
  убирает `chain`/`charge`;
- `careful-magic` не появляется на чужом пути, `piercing-magic` появляется
  при `any_path`;
- запрос собирает `value` по умолчанию `1` для пронзающего улучшения и не
  отправляет `value` для остальных;
- при снятии улучшения `appliedUpgradeCodes` очищается существующим
  `pruneSelected`.

Гейт шага:

- `composer stan`;
- `composer test:unit`;
- `composer test:module game`;
- `composer test:module character` — если фикстуры затронут Character;
- `npx vitest run` по `spellCastUpgrade` и затронутым компонентам;
- `composer docs-check`, `composer map-check`;
- полный `bash tools/test-full.sh` — только по подтверждению пользователя.

## 9. Риски и открытые вопросы

- **Требуемая мощь против урона.** Решение M2: пробитие поднимает только
  `requiredPower`, урон остаётся от `spellPower`. Если канон скажет иначе,
  меняется одна строка в `GameSpellCastDamage`, но контракт запроса — нет.
- **Какие слои считаются «сопротивлением магии».** В M2 пробитие вычитается
  из итога `resistanceByType` (и из сопротивления к сложности). Если
  понадобится различать броню, типизированное сопротивление и магическое
  сопротивление, это отдельный слой, а не второй расчёт в касте.
- **Сопротивление к сложности и урону — разные величины.** Пробитие
  применяется к обоим, но формулы остаются разными, как в плане 46.
- **`chain`/`charge_cap`.** M2 их отклоняет; фронт в real-режиме их не
  показывает. Это осознанно: частичное применение хуже отказа.
- **`check_advantage` и `GameCheckThrowOverride`.** DTO расширяется списком
  `RollAdvantage`; старые поля `advantage`/`source` сохраняются для
  существующих вызовов.
- **`multiple` и путь.** M2 сопоставляет `domainCode` точно с `pathCode`, без
  транзитивных `includes_path_codes`. Если включение путей должно работать и
  для улучшений, это отдельное решение.
- **`value` по умолчанию.** UI и сервер считают отсутствие `value` у
  пронзающего улучшения единицей. Если канон потребует явного выбора,
  сервер начнёт отклонять отсутствие `value` — это совместимое already
  расширение, но потребует правки фронта.
- **Локализация.** Ошибки домена английские; окна показывают свой текст.
- **Старые ревизии.** Улучшения читаются только если карточка живая и у
  кастера есть экземпляр; старые листы без `domainCode` не получат
  path-wide улучшения. Это согласуется с ограничением плана 46.

## 10. Журнал

| Дата | Изменение |
| --- | --- |
| 2026-11-10 | План заведён: `spell_upgrade` для `action_point_delta`, `check_advantage`, `resistance_penetration_per_step`; применимость по родителю/пути/`any_path`; `spellPower` отделён от `requiredPower`; `chain`/`charge_cap` отложены. |
