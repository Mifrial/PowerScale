# Дизайн типа правила «Состояние» (state) — принятые решения

> Дата: 06.08.2026. Живой документ обсуждения AI + автор. Финальные решения зафиксированы
> после обсуждения. Источник истины для реализации (Phase 1 — тип правила, Phase 2 —
> состояния персонажа на правилах).

## 0. Решение «быть типом правила» (отмена #24)

В ТР «Сводка решений» п.24: «Состояние — не тип правила, runtime-агрегат». **Отменяется**:
вводится тип правила `state` (Состояние). Основания — нужна объявляемая ГМ-ом механика
(эффекты на характеристики, урон со временем, иконки), а не просто runtime-метки.

Текущее жёсткое хранилище `CharacterVersion.states: CharacterStates
{ fatigue, wounds, maim, burning, poisoned }` (в `data_json`) мигрирует на ссылочную модель
`CharacterStateValue[]` (ruleId + значение), как инвентарь. `CHARACTER_STATE_LABELS` удаляется —
имена/подписи резолвятся из ревизии. Терминология — из списка автора (см. §4).

## 1. Принципы (наследуются из ability-resource-design.md / race-design.md)

- Ссылки между правилами — по семантичному `code`, не по `id`.
- Один тип `state` с опциональными блоками — как `item` (weapon/armor/shield) и
  `ability` (grants/zones/requirements/process).
- Эффект на характеристику — **стандартный модификатор**, не новая сущность:
  «уменьшение Силы на размер» = `Сила.modify(-3)` (шаг `max−min+1 = 3` пункта базы = 1 размер,
  автоперенос через `CHARACTERISTIC_BASE_RANGE`). Никакого отдельного `size_delta`.
- Урон со временем — только профиль в спеке; тиканье по ходам — боевой поток, отложено.
- Триггеры (Истощение → проверка Воли → группа Упадок сил) — боевой поток: `applyExhaustionCheck`.
  Рост в бессознательности проверку не вызывает; снижение — вызывает.
- Конец хода: сумма ран (`independent`) → прирост кровопотери; leftover/HP не трогает.

## 2. Схема StateSpec (тип `state`) — 06.08.2026, модель value

> **Решение по модели value (06.08.2026):** множественность = **повторы одного ruleId в списке
> состояний персонажа** (паттерн инвентаря), а не «мега-состояние» с экземплярами. `instance_fields`
> и ссылки «из поля экземпляра» удалены.
>
> **Яды вынесены в отдельный тип правила `poison` (см. §2а).** Яд = шаблон отравления (правило),
> а на персонаже отравление — это запись состояния «Отравление» (rule `poisoning`, flag/
> independent) с блоком `poison`, несущим фактические параметры (Сила/Периодичность/Затухание).
> Так предмет/способность и GM могут навесить произвольные значения, а правило-яд даёт имя,
> иконку и тип урона.

```ts
interface StateSpec {
  icon_code?: string | null                // mdi-иконка для ориентации (напр. 'mdi-fire')

  value_type: 'flag' | 'number' | 'dimensional'
  // flag        — наличие (есть/нет)
  // number      — целое число
  // dimensional — одно размерное число

  aggregation: 'sum' | 'max' | 'independent'
  // как объединяются повторы одного правила в списке состояний персонажа:
  // sum         — значения суммируются (Горение)
  // max         — берётся наибольшее (Слабость)
  // independent — каждая запись действует отдельно (Раны, каждая со своим значением)

  effects?: StateEffect[]
}

type StateEffect =
  | {
      type: 'characteristic_modify'
      characteristic_code: string
      amount: number            // применяется через modify(amount, CHARACTERISTIC_BASE_RANGE): ±3 = ±1 размер
      per_unit?: boolean        // true — умножается на текущее значение состояния (Оглушение)
    }
  | {
      type: 'damage_over_time'
      damage: { kind: 'value' } | { kind: 'fixed'; amount: number }
      //              урон из значения состояния | фиксированное число из спеки
      periodicity?: StatePeriodicity
      decay?: StateDecay
    }
  | { type: 'resource_limit_modify'; resource_code: string; amount: number; per_unit?: boolean }
  | { type: 'resource_limit_set'; resource_code: string; value: number }
  | { type: 'check_advantage'; amount: number; per_unit?: boolean; includes_hit?: boolean; characteristic_codes?: string[] }

// Периодичность: собственный период (значение + шаг). Ссылок на поля экземпляра нет.
type PeriodStep = 'turn' | 'minute' | 'hour' | 'day' | 'month' | 'year'
type StatePeriodicity = { kind: 'literal'; value: number; step: PeriodStep }

// Затухание: число, характеристика или проверка.
type StateDecay =
  | { kind: 'fixed'; value: number }                      // простое число
  | { kind: 'dimensional'; base: number; size: number }   // размерное число
  | { kind: 'characteristic'; characteristic_code: string; modifier?: number }  // значение характеристики
  | { kind: 'check'; characteristic_code: string }        // результат проверки по характеристике
```

- `icon_code` — опциональная mdi-иконка состояния (лучшая ориентация в блоке «Состояния»
  карточки и в списках правил). Без валидации имени (любая строка, рендер `v-icon`).

## 2а. Тип правила `poison` (шаблон яда) — 06.08.2026

Отравление — особый случай состояния: параметры применения (Сила/Периодичность/Затухание)
мастер задаёт на ходу, а правило-яд выступает шаблоном (имя, иконка, тип урона, значения по
умолчанию). Поэтому `poison` — отдельный тип правила, а на персонаже отравление хранится как
запись состояния `poisoning` (rule `poisoning`, `value_type: 'flag'`, `aggregation: 'independent'`)
с блоком `poison`.

```ts
interface PoisonSpec {
  icon_code?: string | null              // mdi-иконка яда (mdi-skull-outline)
  damage_type_code: string               // тип урона яда — ОБЯЗАТЕЛЕН (poison-1 / poison-2 / poison-3 / spirit-1 …)
  default_strength?: { base: number; size: number }  // Сила по умолчанию (урон за тик) — РАЗМЕРНОЕ число
  default_periodicity?: StatePeriodicity // периодичность по умолчанию
  default_decay?: StateDecay             // затухание по умолчанию
}
```

> **06.08.2026 (доводка):** у яда урон — **размерное число** (`DimensionalNumberValue`), в редакторе
> `DimensionalNumberInput`. Секция «Эффекты яда» (в т.ч. «Урон со временем») **удалена** — она дублировала
> Урон/Периодичность/Затухание, которые у яда и так лежат отдельными полями спеки.
>
> **06.08.2026 (доводка 2):** урон — **это просто урон**, привязки к ресурсу нет. `resource_code` удалён
> из `PoisonSpec` и из `damage_over_time` у состояний; редакторы/карточки больше не спрашивают ресурс,
> профили урона пишутся «Урон: …» без «по Здоровью».

Хранение на записи персонажа:

```ts
interface CharacterStateValue {
  stateRuleId: string
  value?: number
  dimensionalValue?: { base: number; size: number }
  poison?: CharacterPoisonValue           // заполняется для состояния «Отравление»
}

interface CharacterPoisonValue {
  poisonRuleId?: string | null           // правило-яд (type='poison'); null = придумано мастером
  damage_type_code?: string              // тип урона; не задан — берётся из правила-яда
  strength?: { base: number; size: number }  // Сила (урон за тик) — размерное число
  periodicity?: StatePeriodicity
  decay?: StateDecay
}
```

Отображение:
- имя/иконка строки — из правила-яда, если `poisonRuleId` задан; иначе имя из правила состояния
  («Отравление»).
- `valueLabel` = Сила (размерное число), `dotLabel` = «Урон: 3↑ яда 1 типа, каждые 2 хода,
  затухание 1».
- каждое навешенное отравление — своя строка (id `poisoning#poison-N`), независимо от других.

## 3. Хранение на персонаже (Phase 2)

```ts
interface CharacterStateValue {
  stateRuleId: string
  value?: number                              // number: целое значение; flag не заполняется
  dimensionalValue?: { base: number; size: number }   // dimensional
  poison?: CharacterPoisonValue               // см. §2а — блок отравления
}
```

- `CharacterVersion.states: CharacterStateValue[]` — список с повторами (как инвентарь).
- Резолв имени/подписи/иконки/эффектов — из ревизии (`CharacterReferenceService.ruleById`).
- Объединение повторов в Обзоре — по `aggregation` правила:
  `sum`/`max` → одна строка с объединённым значением (и `count` повторов),
  `independent` → строка на каждый повтор (каждая Рана со своим кровотечением).
- Эффекты `characteristic_modify`: вливаются в модификаторы характеристики
  (источник = правило состояния); вычисленное значение характеристики — через
  `DimensionalNumber.modify(delta, range)`.
- Эффекты `damage_over_time`: профиль на карточке; тик ран в конце хода пишет в кровопотерю
  (не leftover). Лимит ОД в бою: эффективные характеристики → формулы `buildResources` →
  Σ `resource_limit_modify` → `resource_limit_set` → floor 0 → кламп current.
- Помехи: Σ `check_advantage`. Без scope — все проверки; иначе только `includes_hit` и характеристики из `characteristic_codes` плюс их производные (`formula` min/max). Увечье: попадание + Сила + Ловкость, не Воля и не увечье.

## 4. Маппинг типовых состояний

| Правило | value_type | aggregation | effects | icon |
|---|---|---|---|---|
| Истощение | number | sum | авто Воля; рост в бессознательности — без проверки | `mdi-weather-sunny` |
| Слабость | flag | max | −1 к лимиту ОД (`resource_limit_modify`) | `mdi-hand-back-left-outline` |
| Обессилен | flag | max | −3 к Силе, Восприятию, Интеллекту | `mdi-emoticon-sick-outline` |
| Потеря сознания | flag | max | лимит ОД = 0 (`resource_limit_set`) | `mdi-head-sync-outline` |
| Рана | number | independent | конец хода: сумма → +кровопотеря | `mdi-knife` |
| Увечье | number | independent | −сила к лимиту ОД; помехи на попадание, Силу, Ловкость и производные | `mdi-bone` |
| Кровопотеря | number | sum | резерв ⌊n/10⌋; прирост резерва +к истощению; при ≥4 увечье DC 2^(r−4) | `mdi-water` |
| Горение | dimensional | sum | DOT health из `value` | `mdi-fire` |
| Оглушение | number | sum | modify intellect −3 и perception −3, `per_unit` | `mdi-star-four-points-outline` |

> Яды — не правила-состояния, а правила типа `poison` (§2а): `Яд скорпиона` (poison-scorpion,
> damage_type poison-1, сила {base:3, size:1} — «3 больших», каждые 2 хода, затухание 1) и `Яд гадюки`
> (poison-viper, damage_type poison-3, сила {base:5, size:0} — «5 средних», каждые 3 хода, затухание 2).
> На персонаже отравление живёт
> на записи состояния `poisoning` (rule-65) с блоком `poison`, который ссылается на правило-яд.
> «На персонаже 4 яда» = 4 записи состояния `poisoning` с `poison`-блоком (повторы независимы).

## 5. Объём работ

**Phase 1 — тип правила `state`:**
- `RuleType` += `'state'`; `RULE_TYPES`, `RULE_TYPE_LABELS`.
- `RuleSpec` union += `StateSpec`.
- `Dto/State/`: `StateSpec.ts`, `StateEffect.ts`, `Periodicity.ts`, `StateDecay.ts`.
- `StateEditor.vue` (icon_code, value_type, aggregation, effects) → RuleEditPage.
- `StateCard.vue` (иконка, вид значения, объединение, эффекты) → RuleDetailPage.
- `RuleValidationService`: target-характеристика/ресурс резолвятся; decay по
  characteristic/check резолвится.
- Моки: правила состояний в каталог; `mockSpaces.isAlwaysIncluded` += `'state'`.

**Phase 1а — тип правила `poison` (яды):**
- `RuleType` += `'poison'`; `RULE_TYPES`, `RULE_TYPE_LABELS` += `poison: 'Яд'`.
- `RuleSpec` union += `PoisonSpec`; `Dto/Poison/PoisonSpec.ts`
  (icon_code, damage_type_code, default_strength/periodicity/decay).
- `PoisonEditor.vue` (иконка, тип урона через `damageTypeOptions`, Сила,
  Периодичность/Затухание) → RuleEditPage; `PoisonCard.vue` → RuleDetailPage.
- `RuleReferenceService.damageTypeOptions`; `PoisonSpecService`;
  `RuleValidationService` case `poison` (резолв damage_type, characteristic/check decay).
- `mockSpaces.isAlwaysIncluded` += `'poison'`.
- Моки: damage_type-правила ядов (`poison-1/2/3`, `spirit-1`), `DAMAGE_TYPE_FORMS` для них,
  правило-состояние `poisoning` (rule-65), правило-яды `poison-scorpion`, `poison-viper`.

**Phase 2 — состояния персонажа на правилах:**
- `CharacterVersion.states` → `CharacterStateValue[]`; удалить `CharacterStates`,
  `CHARACTER_STATE_LABELS`.
- `CharacterOverviewService`: `buildStates` — объединение повторов по aggregation,
  профиль DOT в `dotLabel`; ветка отравлений — по записи состояния `poisoning` с `poison`-блоком.
- Вкладки Обзор/Описание: блок «Состояния» из правил (иконка, имя, значение, ×повторы, профиль);
  на отравлениях — имя/иконка из правила-яда и профиль из параметров записи.
- Мок-данные персонажей на ruleId-ссылки (Торвин/Гаррик — записи `poisoning` с `poison`-блоками).

## 6. Открытые мелочи

- **Упадок сил** — одновременно один исход: перед проверкой Воли снимаются слабость /
  обессилен / потеря сознания, затем ставится флаг по РУ (−1 / −2 / −3+).
- **Кровопотеря ≥ 4 резерва** — одно увечье по шкале крови, не формула leftover; иначе
  обычное автоувечье, если прирост истощения его бы вызвал. Не оба.
