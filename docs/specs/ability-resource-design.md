# Дизайн типов правил «Способность» и «Ресурс» — принятые решения

> Дата: 31.07.2026. Живой документ обсуждения AI + автор. Финальные решения зафиксированы
> после полного обсуждения (см. историю чата). Отличается от наброска в ТЗ/ТР — теперь
> источник истины для реализации.

## 1. Общий принцип ссылок: `code`, не `id`

Все строковые ссылки между правилами — по семантичному коду (`code`), НЕ по внутреннему `id`.

- `Rule` получает поле `code: string` (как у тегов: `melee`, `magic`) — **глобальный семантический ключ правила**, общий для всех версий и пространств (в БД — `rules.code UNIQUE`). Задаётся при создании (или генерируется slug-ом из имени), после создания **не изменяется**. `RuleVersion` поле `code` НЕ несёт.
- Ссылки в спецификациях: `characteristic_code`, `resource_code`, `ability_code`,
  `damage_type_code`, `special_rule_codes`, `parent_ability_code` и т.д.
- Числовые внутренние id (`spaceId`, `keywordId`, `mechanicId`, `source_code`) — НЕ трогаем,
  это служебные ключи, а не ссылки на правила.
- При создании правила `code` генерится slug-ом из имени (утилита `slugify` из `mockSpaces`).

**Слои (зоны ответственности):**
- **Правила (пространство)** = *определения*: какие характеристики/ресурсы/способности/
  предметы/признаки существуют (с базами) и **шаблоны модификаторов** («эта способность даёт
  +N к характеристике X с источником `source_code`»). Редактор авторит только эту сторону.
- **Персонаж (модуль Character, позже)** = *экземпляр*: текущие значения характеристик,
  ресурсы (текущее/лимит), признаки; применяет grants как модификаторы «к этой конкретной
  характеристике этого персонажа» (`.modify(+N)` по ключу `code`). Правила при этом не меняются.
- Вычисляемые значения (Защита, Опыт волшебства/ближнего боя) — считаются на персонаже
  из агрегации его правил; в правилах их НЕТ.

**Источник модификатора — правило типа `source`** (решение 2026-08-02: набор источников меняется между версиями правил → источник — контент ревизии, а не глобальный справочник). Ссылка по `code` — `source_code` («Тренировка», «Доспех», «Щит», «Заклинание», «врождённый» и т.п.), а НЕ код способности
и НЕ «она сама». Правило:
**модификаторы одного источника не суммируются — берётся наибольший бонус/штраф**
(применяется и к Защите в §3, и к характеристикам/ресурсам).

## 2. «Ресурс» — отдельный тип правила

ОД, Ци Духа, Мана — НЕ характеристики и НЕ способности, а самостоятельный тип `resource`.

```ts
interface ResourceSpec {
  is_dimensional: boolean
  /** Авто-добавление ресурса персонажу (сейчас — только ОД). Рендерится всегда, даже при лимите 0. */
  auto_add?: boolean
  /** Базовый лимит ресурса: стартовое значение + условия его изменения (для auto_add). */
  limit: {
    base: DimensionalNumber | number          // число — безразмерный ресурс, размерное — размерный
    adjustments: { value: Formula; source_code: string }[]  // вычисляемое значение + источник (тип source)
  }
}
```

- `initial_value` удалён (2026-08-13): у не-авто ресурсов `limit.base` переносит его значение, `adjustments` пустые.
- **Условия изменения лимита** — связки {Формула, Источник} (источник — правило типа `source`, обязателен). Значения складываются/вычитаются напрямую, без размерных переходов (в отличие от модификаторов характеристик). У ОД три условия: размер Ловкости, размер Восприятия, разница Сила−Вес.
- Новые виды `Formula`: `characteristic_size { characteristic_code }` (размер характеристики, простое число) и `characteristic_size_gap { characteristic_code_from, characteristic_code_to }` (число полных размеров, на которое from выше to: `trunc(modifyDiffTo/3)`).
- Подтип `resource` из `CharacteristicEditor` **удаляется** (характеристика остаётся только
  характеристикой). Редактор ресурса — отдельный `ResourceEditor`.
- Правило хранит только *определение* ресурса. Текущее/максимум — зона Character (движок).
- Способность ссылается на ресурсы в `action_costs`, `resource_limit`-требованиях и
  grant-`resource`/`resource_limit_change`.

## 3. Открытые понятия: Характеристика / Защита / Опыт

- **Характеристика** — размерное число, база 3–5, мин. 0. Заточена под броски кубиков.
- **Защита** — НЕ характеристика и НЕ ресурс. Плоское число, мин. 0, без верхнего лимита.
  Не используется для бросков кубиков. Значение дают способности и итемы (база по умолчанию 0,
  остальное — модификаторы от источников; модификаторы одного источника не суммируются —
  берётся наибольший бонус/штраф). **Отдельное правило «Защита» НЕ вводим** — она появляется
  через итемы (`defense_slots` брони) и grants; как самостоятельное правило — позже, с Character.
- **Опыт волшебства** / **Опыт ближнего боя** — НЕ ресурсы. **Вычисляемые значения**: сумма
  потраченных ОР на способности с тегом «волшебство» / «ближний бой». Зона Character.

## 4. «Способность» — схема

### 4.1 Цена живёт в зонах (очки = зона)

Этапы закупки = зоны видимости. Очки определяются зоной, отдельно сущность очков не вводим:
- Зона `os` → Очки Создания (ОС) — расы и врождённые черты
- Зона `ol` → Очки Личности (ОЛ) — особенности личности
- Зона `or` → Очки Развития (ОР) — навыки и черты

**Очки — тип правила `points`** (с 30.48/30.49): `name`/`code`/`description`, спеки нет,
`code` = системное имя (`os`/`ol`/`or`). Ключ зоны = `code` очков-правила пространства;
редактор/карточка способности резолвят подписи зон из очков-правил. Термин: ОС/ОЛ/ОР —
«очки», не «валюта» (валюта/деньги — только монеты).

```ts
type ZoneId = string   // код очков-правила (type='points')

type AbilityCost =
  | { kind: 'array'; levels_cost: number[] }                         // длина = макс. уровень; отрицательные значения = даёт очки
  | { kind: 'progression'; max_level: number; base_cost: number; step: number } // cost(level) = base_cost + (level-1)*step
  | { kind: 'automatic' }                                            // авто-получение при выполнении требований (не покупается)
```

- Зоны нет в `zones` → там способность недоступна/не отображается.
- `zones: Partial<Record<ZoneId, AbilityCost>>` — каждая зона со своей ценой в своих очках.
- `levels`/`hard`/`automatic` как отдельные поля НЕ храним: макс. уровень выводится из
  `levels_cost.length` или `max_level`; авто-получение = вариант `automatic` цены.
- Бесплатная покупаемая: `{ kind: 'array', levels_cost: [0] }`.
- `"игра"` и `"всегда"` из ТР-шного `visibility` НЕ вводим (это про отображение в бою/справочно).

### 4.2 Требования (Requirements)

Список требований = **неявное И** (все должны выполниться). Явная логика — рекурсивные
группы `and`/`or` внутри. Редактор: `RequirementListEditor` (верхний список, без оператора)
+ рекурсивный `RequirementNodeEditor` (узел с выбором условия и группами И/ИЛИ).

```ts
type DimensionalNumber = { base: number; size: number }

type Requirement =
  | { type: 'has_ability'; ability_code: string; min_level?: number }                        // есть способность (>= уровня)
  | { type: 'has_ability_tag'; tag_code: string; min_count: number }                         // N способностей с тегом
  | { type: 'has_tag'; tag_code: string }                                                    // признак у персонажа (просто есть/нет, БЕЗ кол-ва)
  | { type: 'characteristic_value'; characteristic_code: string; min: DimensionalNumber }    // характеристика >= min (всегда размерное)
  | { type: 'resource_limit'; resource_code: string; min?: DimensionalNumber | number }      // ресурс есть / лимит >= min (адаптивно)
  | { type: 'and'; children: Requirement[] }
  | { type: 'or'; children: Requirement[] }
```

- `currency_value` из ТЗ → `resource_limit` (интересует лимит ресурса или его наличие).
- `has_tag` — булев признак, количество НЕ указывается (в отличие от `has_ability_tag`).
- `characteristic_value.min` — размерное число (характеристики всегда размерные).
- `resource_limit.min` — адаптивно: размерное число у размерного ресурса (`is_dimensional`),
  простое число у обычного.
- Логические `and`/`or` с вложенностью — нужны сразу. Пишем **свой рекурсивный компонент**.
- **Карта уровней** (с 30.44): `requirements: { level: number; requirements: Requirement[] }[]`
  в `AbilitySpec`. Уровень 1 = получение (бывшее поле `requirements`), уровни N = требования для
  взятия N-го уровня (бывшее `requirements_by_level`). **Требования накапливаются естественно**:
  «взял уровень N → все уровни < N уже удовлетворены», отдельного флага нет (в отличие от даров).
  Единая панель редактора (уровень 1 помечен чипом «получение»).

### 4.3 Дары (Grants) — «что даёт»

Постоянные эффекты способности. **Карта уровней** (с 30.44): уровень 1 = получение
(бывшие `general`), уровни N = достижение N-го уровня (бывшие `byLevel`). Отдельных
`general`/`byLevel` больше НЕТ.

```ts
type Formula =
  | { type: 'fixed'; value: number }
  | { type: 'characteristic'; characteristic_code: string; modifier: number }
  | { type: 'dimensional'; base: number; size: number }
  | { type: 'ability_level'; ability_code: string; multiplier?: number; offset?: number }

grants: { level: number; grants: Grant[] }[]        // уровень 1 = получение; уровень N = при достижении N

type Grant =
  | { type: 'characteristic'; characteristic_code: string; value: DimensionalNumber; permanent?: boolean }            // даёт характеристику со значением (появляется)
  | { type: 'characteristic_modify'; characteristic_code: string; amount: Formula; source_code: string; permanent?: boolean } // модификатор характеристики (Число или Уровень способности; источник обязателен)
  | { type: 'resource'; resource_code: string; limit: DimensionalNumber | number; permanent?: boolean }                // даёт ресурс с лимитом (адаптивно)
  | { type: 'resource_limit_change'; resource_code: string; amount: Formula; source_code: string; permanent?: boolean }  // меняет лимит ресурса на amount (источник обязателен)
  | { type: 'ability'; ability_code: string; permanent?: boolean }                                                     // даёт другую способность
  | { type: 'tag'; tag_code: string; remove?: boolean; permanent?: boolean }                                           // добавить/убрать признак
  | { type: 'item'; item_code: string; quantity?: number; permanent?: boolean }                                        // даёт предмет/врождённое; quantity — экземпляры (конечности)
```

- `characteristic` (дать) vs `characteristic_modify` (изменить) — разные дары.
  Симметрично ресурсу: `resource` (дать) vs `resource_limit_change` (изменить лимит).
- У модификатора характеристики (`characteristic_modify`) `amount` ограничен типами
  **«Число»** (`fixed`) и **«Уровень способности»** (`ability_level`) — UI-подпись «Модификатор
  характеристики». Другие типы формулы в этом даре недоступны.
- **Источник обязателен** у `characteristic_modify` и `resource_limit_change`: `source_code`
  из правил-источников (см. §1). У «дать»-даров источника нет.
- **Значение/лимит**: `characteristic.value` — размерное число (характеристика всегда
  размерная); `resource.limit` — адаптивно (размерное у размерного ресурса, число у обычного).
- **Постоянность** — `permanent?: boolean` на каждом даре (default true):
  - `true` (по умолчанию) — эффект **копится** на всех уровнях ≥ уровня дара;
  - `false` — действует **строго на своём уровне**, на уровни выше не распространяется
    (редкий случай, помечается в редакторе).
- **«Ближний бой х из 3»** — критично: один дар `ability_level` на **уровне 1**:
  `{ type: 'ability_level', ability_code: 'ближний-бой' }` (code обязателен — всегда явно,
  без «текущей по умолчанию»). Формула читает **текущий** уровень, поэтому на 3-м уровне
  даёт +3 сама, без дублирования по уровням. Редактор подсказывает: «формула уровня
  масштабируется сама — не дублируйте по уровням».
- **Естественное оружие/броня** — через **врождённые итемы** (grant `item`). У предмета
  признак «естественный» (`innate` в `ItemSpec`) — скрывает вес/стоимость при отображении.
- «Преимущества для определённых проверок» — сознательно отложено.

### 4.4 Действие и улучшения

```ts
action_costs: { resource_code: string; amount: DimensionalNumber | number; label?: string }[]  // траты при использовании (ОД и т.п.); amount адаптивно
parent_ability_code: string | null                          // цепочки улучшений (навык → улучшение → …)
```

- `amount` в `action_costs` — не формула, но **адаптивно**: размерное число у размерного
  ресурса, простое число у обычного (как `resource_limit.min`). Минимум у основания — 0.
- `label` — переопределение подписи стоимости при отображении. Используется в заклинаниях:
  ОД называется **«Сотворение»** (`{ resource_code: 'action-points', amount: 1, label: 'Сотворение' }`).
- У Процесса `action_costs` **пустой**: ресурсы тратятся на шаг (см. §4.7).

### 4.5 Итоговая схема AbilitySpec

```ts
type AbilityType = 'trait' | 'feature' | 'skill' | 'action' | 'process' | 'spell'

// Черновой слой — им оперирует редактор (type опционален, поля могут «висеть» при смене типа)
interface AbilitySpecDraft {
  type?: AbilityType
  zones: Partial<Record<ZoneId, AbilityCost>>
  requirements: { level: number; requirements: Requirement[] }[]  // карта уровней; ур. 1 = получение
  grants: { level: number; grants: Grant[] }[]                     // карта уровней; ур. 1 = получение
  action_costs: { resource_code: string; amount: DimensionalNumber | number; label?: string }[]  // пустой у process
  process?: ProcessSpec
  spell?: SpellSpec
  parent_ability_code: string | null
}

// Чистый слой — дискриминированный юнион, выдаётся на границе (эмит specToEmit)
type AbilitySpec =
  | (AbilitySpecBase & { type: 'trait' | 'feature' | 'skill' })
  | (AbilitySpecBase & { type: 'action'; action_costs: ActionCost[] })
  | (AbilitySpecBase & { type: 'process'; process: ProcessSpec })
  | (AbilitySpecBase & { type: 'spell'; action_costs: ActionCost[]; spell: SpellSpec })
```

- `requirements`/`grants` — **единые карты уровней** (с 30.44): уровень 1 = получение,
  уровни N = достижение N-го уровня. Бывшие `requirements`/`requirements_by_level` и
  `grants.general`/`grants.byLevel` слиты. Требования накапливаются естественно; у дара
  `permanent?: boolean` (default true). «Развитие интеллекта х из 3» = на каждом уровне
  требование `characteristic_value`, а дар уровня начисляет эффект (авто-прогрессия, когда
  способность растёт сама, а не покупается повторно).
- Два слоя: **Draft** (редактор, `type?`, поля не чистятся при смене типа) и **Clean**
  (юнион, `pruneAbilitySpecForType` на эмите). Чистка — только на границе эмита.

### 4.6 Тип способности (AbilityType)

Тип — **явное поле `spec.type`** (источник истины). Он определяет, какие блоки показывает
редактор и как рендерится карточка. Типообразующие признаки авто-синхронизируются
редактором при смене типа; для старых правил без `type` тип деривируется из тегов.

```ts
type AbilityType = 'trait' | 'feature' | 'skill' | 'action' | 'process' | 'spell'

const ABILITY_TYPE_LABELS: Record<AbilityType, string> = {
  trait: 'Черта', feature: 'Особенность', skill: 'Навык',
  action: 'Действие', process: 'Процесс', spell: 'Заклинание',
}

const ABILITY_TYPE_TAGS: Record<AbilityType, string[]> = {
  trait:   ['trait'],
  feature: ['feature'],
  skill:   ['skill'],
  action:  ['skill', 'action'],
  process: ['skill', 'action', 'process'],
  spell:   ['skill', 'magic', 'action', 'spell'],
}

// приоритет: заклинание > процесс > действие > навык > особенность > черта (по различающему тегу)
function resolveAbilityTypeFromKeywords(keywords: string[]): AbilityType | null
```

- Тип «Действие» (и производные process/spell) требует ОД-стоимость: **любое действие стоит
  минимум 1 ОД**. Валидация: у `action`/`spell` в `action_costs`, у `process` — у каждого шага
  есть запись с `resource_code === 'action-points'` и `amount >= 1`.
- Редактор при выборе action/process/spell авто-добавляет ОД-строку
  `{ resource_code: 'action-points', amount: 1 }` (у spell — с `label: 'Сотворение'`).
  Первая ОД-строка (в `action_costs` и в `costs` каждого шага процесса) **зафиксирована**:
  ресурс нельзя сменить или очистить, минимум — 1, удаление заблокировано.
- Видимость панелей редактора: «Действие» — только action/spell; «Процесс» — только process
  (у process общего «Действия» НЕТ); «Заклинание» — только spell.

### 4.7 Процесс (ProcessSpec)

Процесс — действие из связных шагов. Шаг = **Название + Описание + Растрачиваемые ресурсы**
(почти всегда только ОД, минимум 1 ОД на шаг). Игрок «стоит» на шаге; переходы определяют,
куда можно перейти; повтор шага = само-переход.

```ts
interface ProcessStep {
  code: string
  name: string
  description: string
  costs: { resource_code: string; amount: DimensionalNumber | number }[]  // обычно 1 ОД за шаг/повтор
}

type ProcessTransition =
  | { mode: 'chain'; max_shift: number; direction?: 'forward' | 'both' } // линейный порядок, ±max_shift; shift 0 = остаться (повтор всегда доступен)
  | { mode: 'free' }                                                      // любой шаг из любого (включая повтор)
  | { mode: 'custom'; edges: { from: string; to: string }[] }             // произвольный граф; self-loop = повтор

interface ProcessSpec {
  steps: ProcessStep[]
  start_step_code?: string        // шаг, с которого начинается процесс
  transition: ProcessTransition
  failure?: 'restart_from_first' | 'end_action' | null  // поведение при провале шага (карточка; логика — позже)
}
```

- **Повтор** выражается через переходы, отдельного флага НЕТ: в `chain`/`free` — само-переход
  доступен всегда (shift 0); в `custom` — явное ребро `{ from: x, to: x }`.
- Типичный процесс «Движение»: `chain` + `max_shift: 1`, `start_step_code: 'ходьба'`,
  шаги «Ходьба → Бег → Спринт» (все повторимы: `Шаг → Бег → Спринт → Спринт`).
- Скилл, смещающий на ±2 шага — будущий модификатор `max_shift` (зона Character).
- Комбо с порядком и сбросом («Сначала 1, потом 2, затем 3; провал → заново») = `custom`
  с рёбрами `1→2, 2→3` + `failure: 'restart_from_first'`.
- `action_costs` на уровне процесса пустой — ресурсы только в шагах.

### 4.8 Заклинание (SpellSpec)

Заклинание — волшебное действие. Наследует `action_costs` (ОД = «Сотворение», `label`),
добавляет сложность сотворения, длительность и компоненты.

```ts
type SpellDuration =
  | { type: 'instant' }
  | { type: 'refreshable' | 'sustained'
      difficulty: DimensionalNumber
      action_cost: DimensionalNumber | number
      limit?: { value: DimensionalNumber | number; unit: 'turn' | 'minute' | 'hour' } }

type SpellComponent =
  | { type: 'verbal' | 'somatic'; note?: string }   // «Вербальный (крик)» — уточняющая приписка
  | { type: 'material'; item_code?: string; description?: string }  // материальный компонент (Item)

interface SpellSpec {
  difficulty: DimensionalNumber        // Сложность сотворения (часто равна x — переменной из описания)
  duration: SpellDuration             // мгновенное / обновляемое / поддерживаемое
  components: SpellComponent[]        // Вербальный, Соматический, Материальный (+ Item)
}
```

- **Сложность сотворения** — размерное число; часто равна переменной `x`, используемой в описании.
- **Длительность**: `instant` (мгновенное) / `refreshable` (обновляемое) / `sustained`
  (поддерживаемое). У обновляемого/поддерживаемого — сложность, ОД на обновление/поддержание
  и опциональный предел (`limit`).
- **Компоненты** (ресурсы): обязательные ОД (в `action_costs`, подпись «Сотворение»),
  материальные (Item через `item_code`), а также Вербальный/Соматический с опциональной
  припиской — «Вербальный (крик)».

### 4.9 Манифест блоков и prune на эмите (с 30.43)

Единый паттерн для способностей и итемов: **манифест «дискриминант → поля»** + prune только
на границе эмита (`specToEmit`). Внутренние (черновые) данные редактора при смене
типа/подтипа НЕ чистятся — чистится только то, что уходит наружу (это защищает любые пути
сохранения, а не один `buildRule`).

```ts
// Способность: дискриминант = type (одиночный) → дискриминированный юнион
const ABILITY_SPEC_FIELDS: Record<AbilityType, readonly (keyof AbilitySpecDraft)[]> = {
  trait: [], feature: [], skill: [],
  action: ['action_costs'], process: ['process'], spell: ['action_costs', 'spell'],
}
function pruneAbilitySpecForType(spec: AbilitySpecDraft, type: AbilityType): AbilitySpec

// Итем: дискриминант = subtypes (мультивыбор) → без юниона, только манифест
const ITEM_SUBTYPE_FIELDS: Record<string, readonly (keyof ItemSpec)[]> = {
  weapon: ['weapon'], armor: ['armor'], shield: ['shield'],
}
function pruneItemSpecBySubtypes(spec: ItemSpecDraft, subtypes: string[]): ItemSpec
```

- У итемов **дискриминированный юнион не используется**: `subtypes` — мультивыбор
  (например `["weapon","shield"]`), нет единственного дискриминанта.
- Общие поля (`zones`/`requirements`/`grants` у способности, `category`/`cost_gm`/`weight` у
  итема) в манифест не попадают — они всегда сохраняются.
- Легаси без явного `type`: тип деривируется из тегов, prune применяется и к ним
  (по правилу «явный type ИЛИ деривация»).

## 5. Валидация ссылок при публикации

- Ссылки из спецификаций проверяются перед commit в `publishDraft`:
  `ruleValidationService.validateRuleReferences(effectiveRules, keywords)` из `Rule/Service/RuleValidationService.ts`.
- Извлекает все `*_code` из spec по типу правила (item: `damage_type_code`,
  `characteristic_code`, `special_rule_codes`; ability: все поля; characteristic: формула)
  и проверяет, что код существует и имеет нужный тип.
- При ошибке — диалог «правило X → ссылка на отсутствующий код Y (нужен тип Z)»,
  публикация блокируется. Срез пула всегда полный и консистентный.

## 6. План работ (порядок)

1. **`code` в Rule + рефактор строковых ссылок**: `types.ts` (`Rule.code`, `RuleVersion.code`,
   `CreateRuleData.code`, `RuleType += 'resource'`); мок-литералы; `FormulaInput`
   (`characteristic_code`, узел `ability_level` при prop `abilities`);
   `WeaponProfileEditor`, `ItemEditor`, `ResistanceSlotsEditor`, `CharacteristicLimitsEditor`,
   `BlockProfileEditor` (`damage_type_code`, `characteristic_code`, `special_rule_codes`);
   slugify в общую утилиту.
2. **Ресурс**: `ResourceEditor`; удалить подтип `resource` из `CharacteristicEditor`;
   ветка `resource` в `RuleEditPage` + запись «Ресурс»; таб «Ресурсы» и `typeLabels.resource`
   в `SpaceDetailPage`.
3. **Способность**: `RequirementEditor` (рекурсивный); `AbilityEditor` (панели: Общее /
   Зоны и стоимость / Требования / Дары (general + byLevel) / Действие / Улучшение);
   ветка `ability` в `RuleEditPage`.
4. **Item `innate`**: switch в `ItemEditor` → `spec.innate`.
5. **Валидация**: `ruleValidation.ts` + вызов в `publishDraft`.
6. **Моки и срез**: ресурсы (ОД, Ци Духа, Мана) и способности в пул `mockSpaces`;
   миграция `rule-4` «Двойной удар»; `generateRevisionRules` — замыкание ссылок;
   подтипы-теги в `mockTags`.
7. **Проверка**: `vue-tsc`, `vitest`, dev-сервер; обновить ТР.

## 6a. План работ 2-го цикла (правки интерфейса редактора способности)

1. `mockSpaces.generateRevisionRules` — ресурсы/способности попадают в срез всегда
   (независимо от `count`), чтобы не было «нет в наличии».
2. Документация (§1 источник `source_id`, §4.2/4.3/4.5 схемы) — сделано.
3. `Rule/Interface/abilityTypes.ts` — единая схема (Requirement, Grant, Formula,
   AbilitySpec с `requirements_by_level`, DimensionalNumber).
4. Зоны — `v-checkbox` по зонам (Создание/Личность/Развитие) вместо `v-select`.
5. Требования: `RequirementListEditor` (список, неявное И) + `RequirementNodeEditor`
   (рекурсивные И/ИЛИ); подписи-описания в селекторе условий; `has_tag` без кол-ва;
   `characteristic_value.min` → DimensionalNumber; `resource_limit.min` адаптивно.
6. Дары: `GrantEditor` вертикальный лэйаут, `source_id` из справочника, `value`/`limit`.
7. Моки и валидация: mock-spec (source_id, value/limit, requirements_by_level),
   `mockSources` (+ «Тренировка»), `ruleValidation` (проверка `requirements_by_level`,
   `source_id` существующего справочного источника).

## 6b. План работ 3-го цикла (типы способности, Процесс, Заклинание)

1. Схема `abilityTypes.ts`: `AbilityType`, `AbilitySpec.type`, `action_costs[].label`,
   `ProcessSpec`, `SpellSpec`, справочники `ABILITY_TYPE_LABELS`/`ABILITY_TYPE_TAGS`,
   `resolveAbilityTypeFromTags`.
2. `AbilityEditor`: селектор «Тип способности», авто-синхронизация типообразующих тегов,
   видимость панелей по типу, авто-добавление ОД-стоимости.
3. Новые редакторы `ProcessEditor` (шаги + переходы) и `SpellEditor` (сложность/длительность/
   компоненты).
4. Карточка `AbilityCard` по типу + подключение в `RuleDetailPage`.
5. Валидация: ОД у action/process/spell, шаги/рёбра процесса, `difficulty` и `item_code`
   заклинания.
6. Моки: теги (черта/особенность/навык/действие/процесс/волшебство/заклинание),
   процесс «Движение», пример заклинания, `type` у существующих способностей.
7. Тесты (резолвер типа, валидация), проверки vue-tsc/vitest/build, обновить ТР.

## 6c. План работ 4-го цикла (манифест блоков + карты уровней)

1. Доки: ТР 30.43/30.44, дизайн-док §4.2/4.3/4.5/4.9.
2. Схема `abilityTypes.ts`: `AbilitySpecBase`/`AbilitySpecDraft`/`AbilitySpec` (юнион),
   `requirements`/`grants` → карты уровней `{ level, ... }[]`, `Grant.permanent?`,
   `ABILITY_SPEC_FIELDS`, `pruneAbilitySpecForType`.
3. `AbilityEditor`: единые панели «Требования» и «Дары» (уровень 1 = получение, чип),
   чекбокс «Постоянный» на даре, `specToEmit` = prune (Draft → Clean), видимость панелей
   из манифеста; `buildRule` чистку убрать.
4. Итемы: `Interface/itemTypes.ts` (`ItemSpec`/`ItemSpecDraft`), `ITEM_SUBTYPE_FIELDS`,
   `pruneItemSpecBySubtypes`, `ItemEditor.specToEmit` на прунере.
5. Карточка/валидация: рендер карт уровней, обход новых структур.
6. Моки (карты уровней) и тесты (pruner, item pruner, валидация), проверки.

## 7. Известные нерешённые/отложенные

- «Преимущества для определённых проверок» — отложено.
- Вычисляемые значения (Защита, Опыт) в интерфейсе — зона Character.
- Скилл, смещающий на ±2 шага процесса — модификатор `max_shift`, зона Character.
- Логика переходов/провалов процесса на персонаже — зона Character; сейчас только карточка.
- id правил в `mockRules.ts` и ревизионном пуле `mockSpaces.ts` не совпадали — **разведено 2026-08-01**: единый каталог `ruleCatalog` в `mockRules.ts`, пул ревизий импортирует его (`mockSpaces.revisionRulePool = ruleCatalog`). Ссылки по `code`, ID согласованы по построению.
