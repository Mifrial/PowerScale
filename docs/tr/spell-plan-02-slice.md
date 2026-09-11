# План M2 — representative vertical slice магии

**Статус:** в работе, 2026-09-07. Родительская нарезка — [`spell-roadmap.md`](spell-roadmap.md). Входные результаты — [`spell-inventory-01.md`](spell-inventory-01.md), [`spell-review-01.md`](spell-review-01.md), [`spell-plan-01-inventory.md`](spell-plan-01-inventory.md).

Канон кода: `AbilitySpec` / `SpellSpec` / `AbilityParameter`, `ActionExecutionService`, `AttackDamageService`, `DamageTypeSpec`, `CheckSuccessRatingService`. Не вводить параллельные имена, если в коде уже есть контракт.

## Цель

Зафиксировать состав среза и его посадку на текущую модель. M2 не подменяет M3–M8: он говорит, **какие сущности входят**, **какие поля уже есть**, **какие расширения обязательны** и **как их делать**. Реализация контракта — M3, импорт — M4, редактор — M5, check — M6, effects — M7, lifecycle — M8.

Срез должен покрыть:

- явный выбор магического источника и пути;
- Магическую мощь и Контроль магии;
- расчёт Сложности сотворения;
- успех и провал проверки;
- электрический урон и состояние Шок;
- Арканный урон и Магическое отклонение;
- один duration-lifecycle (refreshable или sustained) плюс instant.

## Что не является этим срезом

- кристалл, простейшее волшебство, мана;
- несколько источников в одном cast и per-item мощь/контроль для второго ядра;
- Battleground и геометрия;
- дестабилизация как исполняемая механика, мутации;
- Псионик/Шаман как runtime;
- backend persist/publish всей магии;
- поле `runtime-support` на Rule (в DTO его нет; `DEC-055` закрывается отдельным контрактом, не в M2).

## Посадка на код: reuse без новых имён

| Канон среза | Уже есть | Как использовать |
| --- | --- | --- |
| Заклинание | `AbilityType = 'spell'`, `SpellSpec`, `action_components` | Не делать отдельный RuleType. Создание = resource-компонент ОД, как у action. |
| Улучшение | `parent_ability_code` | Редактор уже синтезирует `has_ability` на родителя. |
| Параметр `X` | `AbilitySpec.parameters`: `code`, `label`, `resolution`, `default`/`min`/`max`, `linked` | Cast-time `X` — `resolution: 'activation'`. Не вводить `action_parameters`, `value_type`, `selection_timing`, `affects`. |
| Ссылка эффекта на `X` | `Formula.type = 'parameter'` | Урон/мощь/контроль ссылаются на `parameter_code`. Валидатор проверяет, что код есть в `parameters`. |
| Выбор при активации | `AbilityParameter.resolution = 'activation'` объявлен, runtime пустой | M6/M7 заполняют значение в cast context, не в Character build. Повторный выбор на обновлении/поддержании — состояние активного эффекта, не новое поле параметра. |
| Неразмерный `X` | `default`/`min`/`max` уже `DimensionalNumberValue \| number` | Для Времени сотворения — `number`. Для Мощи/Контроля — размерное с базой 3–5. |
| Опыт электромантии | `derived_level.source_keyword` + `experienceOf()` | Keyword `electromancy`. Новой модели опыта нет. |
| РУ | `CheckSuccessRatingService` | Не дублировать. |
| Проверка | `check` + roll services | Путь Арканиста: проверка Интеллекта против вычисленной сложности. |
| ОД / ход | `ACTION_POINTS_*`, лимит ОД | `1 ход = лимит ОД`. Duration lifecycle опирается на начало хода, не на ProcessSpec. |
| Электрический урон | `damage_type` `electricity`, `ApplyAttackDamageInput`, `defense_ignored` | Применять через `AttackDamageService`, не через `ActionEffect`. |
| Сопротивление | Grant `resistance` + отображение как защита | Трейт «Сопротивление магии X» уже есть; сменить цель на `arcane` и `per_unit: 1` (сейчас мок: `magic-damage` и `2X`). |
| Состояние | `RuleType = 'state'`, `state_modify`, aggregation | Шок — новое state-правило, не новый движок. |
| Хуки типа урона | `DamageTypeSpec.attached_rule_codes`, `DamageTypeHook` phases `attack`/`apply`/`injury` | Шок вешается на `electricity` в phase `apply`, по образцу stun/wounds. |
| Innate item | `ItemSpec.innate`, Grant `item` | Магическое ядро — item, как руки/ноги. |
| Ипари | `distance_ipari: number` в `RangedHitDifficultyBreakdown` | Те же входные данные, что у ДБ. Геометрию не считать. Размерность дистанции — отдельный долг Battleground, не блокер M2. |
| Редакционный статус | `contentStatus` | Неподдержанный runtime оставлять в description / inventory `text_only`. |
| Источник модификатора | `RuleType = 'source'` (`innate`, `armor`, код `spell`) | **Не использовать** для магического источника. Коллизия имён. |

`ActionEffect` — только боевые модификаторы точности/проверки/стоимости. `prune()` **удаляет** `action_effects` у `type: 'spell'`. Не класть туда урон, Шок и отклонение.

`combatActions` запускает только `type: 'action'`. Заклинание само по себе в бой не попадает — это реальное расширение запуска, не новый engine.

`ProcessSpec` / `ProcessSession` — многошаговые действия (перезарядка и т.п.). Duration заклинания туда не маппить.

## Состав fixture

Рабочие коды — inventory-коды, не публичный каталог. Перед импортом сверить ссылки.

| Роль | Код | RuleType / AbilityType | Зачем |
| --- | --- | --- | --- |
| Источник | `magic-core` | **`item`**, `innate: true` | Выбираемый экземпляр источника. Не `type: 'source'`. |
| Trait источника | `magic-core-capacity` | `ability` / `trait` | Grant item + мощь. В моках сейчас «Врождённая Магия X» на характеристику `magic` — в срезе заменить/расщепить. |
| Мощь / Контроль | `magic-power`, `magic-control` | `characteristic` | Две новые характеристики. Старая `magic` в срезе не используется. |
| Путь | `arcanist` / `becoming-arcanist` | `ability` | Открывает изучение и задаёт check (Интеллект). |
| Instant | `discharge` или `lightning-strike` | `ability` / `spell` | Электрический урон + Шок. |
| Duration | `lightning-generator` | `ability` / `spell` | `sustained` + `duration.power`. Общая механика — simple `spell-sustaining`. |
| Upgrade | `chain-lightning` | `ability` / `skill` + `spell_upgrade` + `parent_ability_code` | Не SpellSpec и не отдельный RuleType. |
| Урон | `electricity` | `damage_type` | Уже есть. |
| Урон | `arcane` | `damage_type` | Новый; не путать с `magic-damage`. |
| Состояние | `shock` | `state` | Новое. |
| Keyword | `electromancy` | keyword | Опыт школы. |
| Сопротивление | `magic-resistance` | существующий trait | Перенацелить grant. |

Fixture: один персонаж с одним ядром и одним путём. UI всё равно показывает выбор источника и пути (автоподстановка единственного значения).

На M2 **не** хранить путь на инстансе способности. Для одного пути достаточно: изученные spell + наличие ability пути + выбор пути в cast context. Поле `learned_via` на Character ability — расширение M8/M9, когда появится второй путь.

На M2 мощь/контроль читаются как характеристики персонажа. Per-item значения при втором ядре — M9, не M2: иначе пришлось бы расширять `ItemSpec` без второго экземпляра в fixture.

## Расширения: нужно ли и как

Каждое расширение проверено: без него канон среза нельзя выразить существующим полем.

### E1. Тонкий `SpellSpec` и эффекты действия

**Зачем.** Сложность сотворения считается при cast, не хранится. Создание — `action_components`. Стабильность — у экземпляра эффекта. Заклинание — действие, но **не каждая доставка — атака**.

Типичный (пока не в выборке) случай: «цель получает X защиты от отражающего поля», длительность 1 минута = 10 ходов. Это не удар и не РУ. После успешного сотворения на цель вешается **состояние с длительностью**; бонус защиты живёт на этом состоянии. Instant здесь ни при чём: эффект висит заданное время (длительное), источник не держит.

Атака — один из режимов доставки, не умолчание:

- касание — **компонент** и **выбранная ближняя атака**; ОД каста = max(ОД заклинания, ОД атаки). Промах / воздух → молоко (сотворение живое, эффект в пустоту). Нет обязательной **цели заклинания** → автопровал сотворения, не молоко. Цель касания не даёт resistance на сложность. 0 РУ атаки для самой атаки остаётся 0; для эффекта касания заклинания — 1 РУ;
- луч → дальняя атака, тот же контур попадания;
- автопопадание → без броска, фиксированный РУ из текста («с 1РУ» / «с 3РУ»);
- без атаки → нет hit-компонента; после компонентов сразу проверка сотворения и эффекты действия (состояние, защита и т.д.).

**Как.**

```text
SpellSpec {
  power
  control
  duration  // instant | lingering | refreshable | sustained
}
```

Манифест `spell` = поля `action` + `spell`. Не prune `action_effects`.

Текущий `ActionEffect` слишком узкий (модификаторы этой/следующей атаки). Его нужно расширить типами **применения к цели**, как минимум повесить состояние со сроком. Стабильность и оставшаяся длительность — на экземпляре состояния/эффекта, не в SpellSpec.

Если бонус защиты ещё нельзя выразить существующим `StateEffect`, это расширение state, а не поле SpellSpec.

Доставка: M3 манифест + union эффектов.

### E2. Duration — длительность эффекта, не атаки

**Зачем.** Instant = эффект отработал, висящего волшебства нет (Разряд; Шок после него — от типа урона). Длительное = висящее состояние на время без удержания источника (поле защиты на 10 ходов). Refreshable / sustained — как уже зафиксировано. В коде нет длительного режима. У `refreshable|sustained` сейчас лишние обязательные `difficulty` и (для sustained) `action_cost`.

**Как.**

```text
SpellDuration =
  | { type: 'instant' }
  | { type: 'lasting'; limit?: { value; unit } }
  | { type: 'refreshable'; action_cost: number | DimensionalNumberValue; limit?: ... }
  | { type: 'sustained'; limit?: ... }
```

Поле `duration.difficulty` **не использовать в срезе** (в review его нет). Решение удалять или оставить — M3; до решения не писать в него логику.

M2 runtime: instant (Разряд) + один refreshable или sustained (Генератор). Длительное поле — в union duration и в модели apply-state; карточка «отражающее поле» в fixture не обязательна. Limit `minute` уже есть; 1 минута = 10 ходов.

Не использовать `ProcessSpec`.

Доставка: M3 контракт, M8 lifecycle.

### E3. Запуск spell как действия

**Зачем.** Бой запускает только `type: 'action'`. Заклинание входит в тот же запуск. Дальше ветка **не обязана** быть атакой.

**Как.** Не копировать engine.

1. Списать компоненты **до** проверки: ОД = max(заклинание, атака касания); соматика; касание-атака если есть.
2. Промах / воздух → молоко: эффект в пустоту; проверка сотворения всё равно.
3. Нет обязательной цели заклинания → автопровал сотворения (эффекты fail; отклонение — M9).
4. Проверка `check-spell-cast`. Провал → эффекты цели не ставятся.
5. Успех + доставка: без атаки — `action_effects`; касание — эффект на цель касания, РУ атаки (0 → 1 только для эффекта заклинания); автопопадание — фиксированный РУ.
6. UI: цель касания и цель заклинания — разные входы.

Доставка: M6 сотворение, M7 эффекты (state и/или атака).

### E4. `arcane` и сопротивление, влияющее на каст

**Зачем.** Канон — тип Арканный. В каталоге `magic-damage`. Сопротивление магии должно быть обычным resistance на этот тип **и** увеличивать Сложность сотворения: `(difficulty).modify(resistance)`.

**Как.**

1. Новое правило `arcane` (`damage_type`). Формы в `DAMAGE_TYPE_FORMS`.
2. На `DamageTypeSpec` флаг `modifies_spell_difficulty?: boolean` (для `arcane` = true). Не плодить grant-тип и не хардкодить имя в сервисе каста дальше чтения спеки.
3. Трейт `magic-resistance`: grant `resistance` на `arcane`, `value: { type: 'parameter', parameter_code: 'x', per_unit: 1 }`. Текст мока «+2X устойчивости» считается устаревшим.
4. `magic-damage` в срезе не использовать; миграция старых модов — не блокер M2.

Доставка: M3/M4 контент, M6 чтение resistance цели.

### E5. Шок

**Зачем.** У `electricity` нет apply-хука на шок. Keyword `concentration` — признак зелья, не эта проверка.

**Как.** По образцу `DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_STUN`:

1. State `shock`: `value_type: 'number'` или `'dimensional'` по карточке AI.html; aggregation `sum`; effect `characteristic_modify` на Ловкость.
2. Ability/mechanic, привязанная к `electricity` через `attached_rule_codes`.
3. Handler phase `apply`: после электрического урона накладывает/усиливает Шок.
4. Проверка концентрации — существующий check-контур в точке, которую задаст карточка (не новый RollEngine). Если в fixture точка не однозначна — `text_only` до уточнения, но state Шок всё равно применяется.

Доставка: M7.

### E6. Cast fail, отклонение, арканный взрыв

**Зачем.** Провал атаки ≠ провал сотворения. Отклонение — отдельное событие на fail, не строка UI.

**Как.**

1. `SpellCastService` возвращает дискриминант: `blocked` (нет source/path) | `success` | `failed`.
2. На `failed` — отдельный сервис отклонения (минимум: бросок/таблица из выгрузки, насколько она однозначна; иначе типизированный `deviation` + один representative исход «взрыв»).
3. Взрыв: `AttackDamageService` с `damageTypeCode: 'arcane'`. Falloff: вход `distance_ipari` как у ДБ, линейное уменьшение, не ниже 0; без `ISpatialResolver` сетки. Список целей для M2 — явный массив entity + дистанция, как цели атаки.
4. Каталог мутаций не делать.

Доставка: M7.

### E7. Активный эффект duration

**Зачем.** Нет агрегата «заклинание продолжает действовать и держит источник». `PendingActionEffect` — про другое.

**Как.** Новый Game-агрегат рядом с `ProcessSession`, не внутри него:

```text
ActiveSpell:
  spell_code
  source_item_code        // экземпляр/код item
  path_ability_code
  used_power
  duration_type
  stability
  caster / targets
```

- refreshable: в начале хода списать `action_cost` ОД или drop;
- sustained: в начале хода подтвердить, что источник свободен; ОД не брать; drop сразу, если источник занят/потерян;
- смена `used_power` — пересчёт impact и (для sustained) stability; новых check нет.

Доставка: M8. Instant-only путь M7 не должен ждать этот агрегат.

### E8. Не делать в этом срезе

- `ItemSpec.max_power` / `volume` / `mana` / `owner_bound` — кристалл отложен.
- `action_parameters` / `affects`.
- Spell как `RuleType.source`.
- `runtime-support` на Rule до отдельного DEC-055 контракта.
- Вычислять ипари геометрически; менять `distance_ipari` на DimensionalNumber в M2 (долг Battleground: дистанции канонически размерные, в ДБ сейчас `number`).

## Порядок работ относительно roadmap

| Шаг | Делает | Гейт |
| --- | --- | --- |
| M2.1 fixture | этот план | Карточки, коды, disposition, замкнутые ссылки, явный source=item и path=ability |
| M3 | DTO E1–E2, E4 flag, validation, prune | SpellSpec не теряет base difficulty; lasting в union; spell не несёт ActionEffect-урон |
| M4 | mock/fixture import | `arcane`, `shock`, item-ядро, две характеристики, keyword electromancy |
| M5 | SpellEditor + диалог выбора | Выбор source/path виден при одном варианте; activation parameters |
| M6 | SpellCastService | Сложность; бросок `check-spell-cast`; стат с пути |
| M7 | компоненты до check, касание=атака, damage, Шок; fail без отклонения | Через HitRoll + AttackDamageService |
| M8 | ActiveSpell, `spell_upgrade` на каст | Один duration mode end-to-end |
| M9 | fail + отклонение + arcane; stability | E6; кристалл/мана — не этот шаг |

## Тестовая матрица

Покрывается по мере M3–M8; критерии среза те же:

1. выбор source item и path ability;
2. нет выбранного источника / пути;
3. spell без владения путём;
4. дефицит мощи / контроля;
5. успех check + РУ;
6. fail + отклонение + arcane;
7. electricity + Шок;
8. Formula ссылается на неизвестный parameter;
9. upgrade без parent;
10. `distance_ipari` уменьшает арканный урон, минимум 0;
11. instant не ломается duration-агрегатом;
12. continuation/drop выбранного duration;
13. dimensional arithmetic без `toNumber()` для мощи/сложности;
14. `RuleType.source` не используется как ядро.

## Критерии завершения M2 как документа

- fixture-состав зафиксирован и не конфликтует с `RuleType.source`;
- каждое расширение E1–E7 имеет «зачем» и модуль доставки;
- roadmap M3–M8 ссылаются на эти расширения, а не на вымышленные `action_parameters` / `runtime-support` / `supported`;
- inventory больше не требует `type: source` для ядра.

Реализация кода начинается с M3 по E1/E2/E4.

## Журнал

| Дата | Изменение |
| --- | --- |
| 2026-09-07 | Создан план M2 после закрытия блокирующих вопросов M1. |
| 2026-09-07 | Переписан по коду: reuse существующих DTO/сервисов; источник = item; параметры = `AbilityParameter`; урон = `AttackDamageService`; явные расширения E1–E7. |
| 2026-09-09 | Цепная — skill+`spell_upgrade`; Генератор — мощь поддержания в duration; простое правило поддержания. |
| 2026-09-10 | Касание: компонент до check, та же атака; молоко при промахе; 0 РУ атаки → 1 РУ только для эффекта касания. Отклонение остаётся M9. |
| 2026-09-10 | Цель касания vs цель заклинания; молоко ≠ auto-fail; ОД = max; касание = выбранное оружие. |
| 2026-09-11 | M8 закрыт. E6 уходит в [`spell-plan-09-magic-runtime.md`](spell-plan-09-magic-runtime.md). |
