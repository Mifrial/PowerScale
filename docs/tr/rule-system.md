# Система правил и пространств

**Статус:** текущий канон, 2026-08-30. Подробности подтверждаются frontend DTO, сервисами, моками и тестами; backend-only утверждения помечены `OPEN`.

## Модель правила

Rule — контентная сущность, существующая в пространстве и представленная неизменяемыми версиями. Текущая форма правила разделяет общие поля (`code`, имя, описание, keywords и тип) и type-specific `spec`.

Текущий `RuleType` содержит:

```text
simple
race
species
characteristic
resource
points
ability
item
damage_type
source
state
poison
sense
age
language
weapon_family
item_modifier
item_modifier_type
check
```

DTO-слой также содержит связанные модели для:

- race/species, наследования и возрастных диапазонов;
- characteristic и derived formulas;
- resource, points и ограничений;
- ability, групп, зон, requirements, grants и keyword refs;
- item, weapon/shield/armor profiles, modifiers, prices и resistance/defense slots;
- damage, state, poison, sense, age и language;
- check, difficulty, success rating, roll и mechanic payload;
- movement/action/process и их transition/cost/effect контекстов.

Наличие DTO или `RuleType` не означает готовность backend или контента. Для каждого типа отдельно фиксируются readiness доменной модели, frontend, backend и реального контента.

## Общие контракты

### Ссылки

Межправильные ссылки используют семантические коды: `characteristic_code` и аналогичные `*_code`. Числовые IDs — внутренние ключи хранения и не являются внешней семантикой.

### Keywords

`keyword` — канонический термин для признака правила. Справочник keywords отделён от type-specific ссылок и участвует в фильтрации каталога и редакторе.

### Размерные и производные значения

Размерные числа и derived formulas являются отдельными доменными механизмами. Их вычисление, сравнение и применение модификаторов не следует заменять строковой арифметикой. Конкретные операции должны оставаться в доменном сервисе, а не в Vue-компоненте.

### Resources и points

Ресурс использует модель:

```text
auto_add
limit.base
limit.adjustments
```

`points` — самостоятельный тип правила. Коды зон способностей (`os`, `ol`, `or`) являются ссылками зон, а не заменой сущности points. `initial_value` — историческая модель.

### State, poison, sense и age

Отравление персонажа является состоянием, так же как рана и истощение. `poison` — rule-контент, описывающий такой тип состояния, а не отдельная lifecycle-модель персонажа.

`sense` и `age` не являются состояниями. Чувства — отдельная группа. Возраст/раса и связанные возрастные диапазоны относятся к контенту расы/возраста и не сводятся к state.

### Ability и mechanics

Ability поддерживает requirements, grants, references на characteristic/resource/source, группы, зоны, процессы, действия, стоимость, проверки и эффекты. `ActionEffect` и mechanic payload — рабочий частичный канон; незавершённые варианты не описываются как полностью реализованные.

Способности и предметы могут задавать модификаторы характеристик, бросков, действий, оружия и защиты. Порядок и backend-персистентность полного Rule Engine не подтверждены — `OPEN`.

## Пространство и ревизии

Пространство изолирует набор правил. Наследование использует snapshot-copy: в момент наследования актуальные версии копируются в новое пространство, после чего пространства независимы (`DEC-002`). Копирование пространства является атомарной транзакцией-коммитом: либо создаётся целостная независимая копия, либо изменения не применяются. Отдельная очередь копирования и progress UI не являются текущим контрактом.

`SpaceRevision` — опубликованный набор правил. `revision` — числовой номер, уникальный внутри пространства. Frontend передаёт `(spaceId, revision)`. Backend разрешает пару, получает неизменяемый `publishedAt` и строит срез правил по времени публикации.

`RuleVersion` immutable. Коммит ревизии атомарен. Timestamp коммита не должен включать версии будущей ревизии.

### Draft и контекст

Draft — редакторское состояние до публикации. Контекст пространства и ревизии передаётся через URL/route или публичный context API; Rule не импортирует Space и не читает его store напрямую.

Draft stores отвечают за локальное редактирование и persistence. Они не заменяют `SpaceRevision` и не делают публикацию автоматически.

### Публикация

Публикация создаёт новую ревизию и отображает diff относительно целевого пространства. Выборочная публикация сохраняется как требуемая возможность: пользователь должен иметь возможность опубликовать выбранное подмножество правил и удалений. Текущая отправка полного draft без набора выбора — незавершённый implementation gap, а не отмена требования.

Удаление правила — версия с неактивным состоянием, чтобы исторический срез оставался воспроизводимым.

## Каталог

Каталог имеет собственные секции и порядок отображения (`AbilitySection`, `catalogSortOrder`). Секция каталога не тождественна `parent_ability_code` или `parent_race_code`; seed migration должна сохранять это различие.

## Редакционный и runtime-статусы

Общий редакционный `contentStatus` — расширяемый enum, первоначально `needs_work | ready`. В моках новые правила по умолчанию требуют доработки. Метка показывается только пользователю с правом редактирования.

`contentStatus` не влияет на runtime, validation, публикацию или доступность. Runtime-support моделируется отдельно; его форма и полный набор значений требуют согласования с фактическим контентом и имеют статус `OPEN`.

## Технические спецификации

### DimensionalNumber

Размерное число хранится как plain DTO `{ base, size }` и интерпретируется как `base × 2^size`. Для UI доступны `toNumber()` (с округлением вниз), `modify(delta, range?)`, `add/subtract(other)` и `toString()`.

Для характеристик используется диапазон базы 3–5. При переходе через границу базы значение нормализуется переносом размера:

```text
{4|0}.modify(+1) = {5|0}
{5|0}.modify(+1) = {3|+1}
{3|0}.modify(-1) = {5|-1}
```

Для ресурсов и веса без диапазона выполняется сдвиг базы. `add/subtract` выравнивают числа по меньшему размеру; отрицательный результат допустим на value-уровне и проверяется вызывающим сервисом.

### Race, characteristic и resource

`species` образует цепочку `вид → подвид`, а `race` является терминальной точкой. У species есть `parent_race_code` и наследуемые abilities; race дополнительно содержит стоимость ОС, стартовые characteristics и abilities.

Characteristic всегда dimensional с базой 3–5. Производная характеристика может иметь формулу `min()` или `max()` двух базовых характеристик, выбранных из текущего пространства.

Resource — отдельный тип, не подтип characteristic. Текущая модель ресурса использует `auto_add`, `limit.base` и `limit.adjustments`; ресурс определяет лимит, а текущее значение живёт на персонаже.

### Ability

Ability использует уровневые карты `requirements` и `grants`, где уровень 1 означает получение. Цена хранится в зонах, ключ которых — код `points`-правила:

```typescript
type AbilityCost =
  | { kind: 'array'; levels_cost: number[] }
  | { kind: 'progression'; max_level: number; base_cost: number; step: number }
  | { kind: 'automatic' };
```

Карта ability содержит `type`, `zones`, `requirements`, `grants`, `action_components`, optional `process`, optional `spell` и `parent_ability_code`. Редактор допускает широкий `AbilitySpecDraft`; перед сохранением `prune` удаляет поля, несовместимые с type.

Канонические requirement types frontend DTO: `has_ability`, `has_ability_keyword`, `has_keyword`, `characteristic_value`, `resource_limit`, `and`, `or`, `min_weapon_mastery`, `current_speed`, `characteristic_parameter`, `resistance`, `sense_modify`, `state_modify`, `check_advantage` и `money`. Grants включают `characteristic`, `characteristic_modify`, `resource`, `resource_limit_change`, `ability`, `keyword` и `item`. Исторические `has_ability_tag`/`has_tag` заменены keyword-вариантами (`DEC-017`) и не являются current contract.

`permanent` у grant по умолчанию true: эффект действует на последующих уровнях; false ограничивает эффект уровнем получения. `source_code` у модификаторов ссылается на правило типа `source`.

`Formula` дополнительно поддерживает `parameter`, `parameter_floor_div`, `characteristic_size`, `characteristic_size_gap` и `actionCharacteristic`. Последний строит силу strike/throw/shoot из характеристики действия и массива action modifiers; он не является прямым уроном оружия.

### Item, damage и modifiers

Item имеет `category` (`money`, `equipment`, `other`), `cost_gm`, optional `weight`, `innate` и `special_rule_codes`. Equipment может иметь subtype `weapon`, `armor` и `shield`.

Weapon содержит minimum strength, block profile и `weapon_profiles` для `strike`, `throw`, `shoot`. Профиль включает distance, range, damage/formula, damage type, penetration и accuracy.

Armor содержит defense slots, resistance slots и characteristic limits. Shield содержит minimum strength и block profile. Слоты хранят durability и optional `source_code`.

Damage type требует forms `genitive` и `dative`; редактор и publication не пропускают неполную spec. Resistance ссылается на damage type по `code`.

Source — versioned RuleType для происхождения модификаторов. Модификаторы одного source не суммируются: выбираются максимальный положительный и максимальный отрицательный вклад.

### Mechanics, state и check

Mechanic имеет code, name, description и version. `rule_versions.mechanic_id` связывает правило с обработчиком; зависимость механик и mapping `(code, version) → handler` остаются кодовой ответственностью.

State описывает хранение, объединение повторов, decay и эффекты. Poison является контентным описанием типа state. Check — именованное действие над RollEngine; коды attached roll rules не являются кодами механик.

`CheckSpec` содержит `parent_check_code`, optional `characteristic_code`, `allow_characteristic_override`, `default_efficiency`, `difficulty_input`, `allowed_modes` (`solo | joint | both`) и `attached_rule_codes`. Если `attached_rule_codes` задан явно, он не наследуется автоматически от родителя.

`ProcessSpec` содержит `steps`, `start_step_code`, `exit_step_codes`, `transition`, optional `failure` и `completion_effects`. Transition может быть chain/free/custom; завершение и failure должны быть различимы в runtime.

### Description и validation

Описание правила — HTML, проходящий sanitization. Structured `spec` хранится JSON-блоками (`text`, `cost_table`, `requirements`, `action_cost`, `level_scaling` и аналогичные подтверждённые блоки).

Формулы поддерживают преобразования `[formula]` (floor), `[formula|ceil]` и `[formula|min=N]`. Validation должна возвращать структурированные problems с code/path/stage, а не локализованный текст как идентификатор.

## Readiness matrix

Готовность RuleType оценивается независимо по четырём осям: domain DTO/service, frontend UI, backend contract и real content. Наличие типа в `RuleType.ts` не закрывает остальные оси. Для каждого типа отдельно фиксируются evidence и один из статусов `IMPLEMENTED`, `PARTIAL`, `MOCK_ONLY`, `BACKEND_OPEN`, `NOT_IMPLEMENTED` и `DEFERRED`; итоговый типовой статус не должен скрывать слабейшую ось.

Особый release gate: `spell`, magic source/path/branch/skill и полный runtime mechanics остаются `DEFERRED` до реальной выгрузки контента согласно `DEC-023`, `DEC-031`—`DEC-033`, `DEC-039`, `DEC-046` и `DEC-056`.

| RuleType | Domain DTO/service | Frontend UI | Backend | Content | Evidence/disposition |
| --- | --- | --- | --- | --- |
| `simple` | `IMPLEMENTED` | `IMPLEMENTED` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |
| `race` | `IMPLEMENTED` | `IMPLEMENTED` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |
| `species` | `IMPLEMENTED` | `IMPLEMENTED` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |
| `characteristic` | `IMPLEMENTED` | `IMPLEMENTED` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |
| `resource` | `IMPLEMENTED` | `IMPLEMENTED` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |
| `points` | `IMPLEMENTED` | `IMPLEMENTED` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |
| `ability` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |
| `item` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |
| `damage_type` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |
| `source` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `DEFERRED` |
| `state` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |
| `poison` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `DEFERRED` |
| `sense` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `DEFERRED` |
| `age` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `DEFERRED` |
| `language` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `DEFERRED` |
| `weapon_family` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `DEFERRED` |
| `item_modifier` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `DEFERRED` |
| `item_modifier_type` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `DEFERRED` |
| `check` | `IMPLEMENTED` | `PARTIAL` | `OPEN` | `OPEN` | shape evidence: `RuleType.ts:1–20`; backend `OPEN`; content `OPEN` |

Источник списка — `Rule/Enum/RuleType.ts`; domain evidence перечислена в DTO families выше. `IMPLEMENTED` в Domain DTO/service означает наличие и обработку frontend/domain-контракта, а не backend и не полноту реального контента.

## Применённые решения аудита

Этот документ отражает `DEC-002`, `DEC-004`, `DEC-005`, `DEC-006`, `DEC-007`, `DEC-010`, `DEC-015`, `DEC-017`, `DEC-018`, `DEC-022`, `DEC-028`, `DEC-039`, `DEC-042`, `DEC-053`, `DEC-054`, `DEC-055` и `DEC-056`. Полные формулировки и даты находятся в [`decisions.md`](decisions.md).

## Открытые вопросы

- физическая схема backend Rule/RuleVersion/RuleSet;
- точный контракт Rule Sets и их связь с каталогом;
- backend Rule Engine, порядок обработчиков и детерминизм;
- полная backend-реализация выборочной публикации;
- точная классификация магических источников, путей, веток и навыков — `DEFERRED` до выгрузки;
- состав runtime-механик заклинаний — `DEFERRED` до выгрузки.
