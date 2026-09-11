# План M3 — контракт SpellSpec / Ability

**Статус:** сделано, 2026-09-08. Родительская нарезка — [`spell-roadmap.md`](spell-roadmap.md). Канон среза — [`spell-plan-02-slice.md`](spell-plan-02-slice.md) (E1–E4, не E5–E8 runtime). Правила фронта — [`draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md).

## Цель

Зафиксировать в DTO, prune и validation то, без чего нельзя честно редактировать и импортировать заклинание. Runtime сотворения, урона, отклонения и ActiveSpell **не входят** (M6–M8).

После M3:

- `SpellSpec` не хранит Сложность сотворения;
- у spell те же поля действия, что у `action` (spell — подтип действия);
- ключевые слова уже канон: Навык + Волшебство + Действие + Заклинание;
- четыре режима длительности эффекта;
- мощь и контроль — значение или ссылка на parameter;
- доставка попадания отделена от «наложи состояние»;
- тип урона может помечать влияние на сложность сотворения.

## Что не делать в M3

- `SpellCastService`, броски, отклонение, Шок-хук, ActiveSpell;
- каталог ядра/путей/Разряда (M4);
- полный UX выбора source/path в Game (M5/M6);
- PHP / persist;
- `runtime-support` на Rule;
- поля кристалла на `ItemSpec`;
- считать сложность в Vue;
- запускать dev-сервер и `vite build` (build — веха пользователя).

Минимально поправить редактор и подписи карточки, чтобы типы и экраны не врали. Это не замена M5.

## Сверка с кодом

1. `RuleValidationService` **запрещает `action_effects`, если тип не `action`**. Манифеста мало: проверку расширить на `spell`.
2. `operations` **нет** в `ABILITY_SPEC_FIELDS` у action, поэтому prune их не трогает. Добавить `operations` только в манифест spell → поле попадёт в `ABILITY_TYPE_SPECIFIC_FIELDS` и prune **вырежет `operations` у action**. В M3 `operations` в манифест не добавлять; на ветке spell оставить optional как у action, вне списка prune.
3. Весь `Formula` слишком широкий. Мощь/контроль — не `actionCharacteristic` и не `per_unit × X`.
4. `hit_resolution` не класть в манифест prune (та же ловушка). Отдельный Dto-файл.
5. `ActionEffectLabelService.describe` заканчивается fallback «последний тип». Без ветки `apply_state` сломаются подпись и типы. `effectsAfterAction` не должен заворачивать `apply_state` в pending-модификаторы атаки.
6. `spell.difficulty` / `duration.difficulty` ещё в `SpellEditor.vue`, `AbilityCard.vue`, `CharacterOverviewService` (`spellDifficulty`; non-instant сейчас только refreshable vs sustained).

7. Заклинание в коде уже **подтип действия**, не соседний вид. `ABILITY_TYPE_KEYWORDS.spell = ['skill', 'magic', 'action', 'spell']` (Навык, Волшебство, Действие, Заклинание). Различительный тег — `spell`; precedence ставит spell выше action. Атака — тоже действие (keyword атаки поверх `skill`+`action`). M3 не вводит отдельную «магическую сущность»: только `SpellSpec` поверх полей действия.

8. Редактора `action_effects` нет (только карточка). M3 не обязан делать полный редактор apply_state; достаточно типа, validation и подписи. **`hit_resolution` обязателен — без поля в SpellEditor и без default в createEmpty новый спелл нельзя сохранить.**
9. `AbilityOverview.spellDifficulty` и чип «Сложность» в `AbilityTab.vue` — Character, не только Rule. Либо убрать поле/чип, либо не заполнять из спеки. `asActionAbilitySpec` в Game по-прежнему `type === 'action'` — это M6/M7, не M3.
10. База 3–5 — это `DimensionalNumber.base`, не целое min=3 без размера.

## Контракт

### 1. `SpellSpec`

```text
SpellValue = DimensionalNumberValue | { type: 'parameter'; parameter_code: string }

SpellSpec {
  power: SpellValue
  control: SpellValue
  duration: SpellDuration
}
```

`SpellValue` — отдельный файл в `Dto/Ability/`. Не использовать весь `Formula`. Значение параметра и есть мощь/контроль.

- Удалить `difficulty`.
- `parameter_code` есть в `parameters`. Если у параметра заданы min/max — база 3–5 для мощи/контроля.
- Не класть `stability`, `impact`, Создание.

База сложности `{3|0}` — константа M6.

### 2. `SpellDuration`

```text
| { type: 'instant' }
| { type: 'lingering'; limit?: { value; unit: 'turn' | 'minute' | 'hour' } }
| { type: 'refreshable'; action_cost: DimensionalNumberValue | number; limit?: ... }
| { type: 'sustained'; power: SpellValue; limit?: ... }
```

- UI: Мгновенное / Длительное / Обновляемое / Поддерживаемое. Код длительного — `lingering`.
- Убрать `duration.difficulty`.
- `action_cost` только у `refreshable`.
- У `sustained` обязательна **своя** мощь поддержания (`power`), отдельно от `spell.power` сотворения. Правило «в первый ход равна мощи сотворения» — текст `spell-sustaining`, не runtime.
- `createEmptySpellDuration` для lingering/sustained не заполняет difficulty/action_cost.
- 1 минута = 10 ходов — подпись, не автоконвертация.

### 3. Манифест: spell как действие + SpellSpec

Заклинание — навык-действие-волшебство. Поля запуска те же, что у action; отличия карточки — в `spell`.

```text
action: ['action_components', 'action_effects', 'attack_mode', 'max_targets']
spell:  ['action_components', 'action_effects', 'attack_mode', 'max_targets', 'spell']
```

Теги не трогать: `syncTypeTags` уже вешает skill/magic/action/spell. Не требовать отдельный RuleType.

Ветка `AbilitySpec` `type: 'spell'`: все action-поля + `spell`.

Минимум 1 ОД — как у любого действия.

Validation: всё, что сейчас считается «свойством действия» (`action_effects`, attack_mode, max_targets, operations, ОД), применять к `action` **и** `spell`. Формулировку «только для Действие» заменить на «для действия, включая заклинание».

### 4. `HitResolution`

Файл `Dto/Ability/HitResolution.ts`. Поле `hit_resolution?` на `AbilitySpecBase`, не в prune-манифесте.

```text
| { type: 'none' }
| { type: 'attack' }
| { type: 'auto'; rating: number }
```

- У spell обязательно (действие может быть без атаки — `none`).
- У существующих action без поля M3 бой не меняет (атака по-прежнему keyword). Позже то же поле может покрыть и не-магические действия; в M3 не мигрировать все action.
- Melee/ranged — keyword, не дублировать.
- `auto.rating` — целое ≥ 1, это РУ атаки.

### 5. `ActionEffect.apply_state`

```text
| { type: 'apply_state'; state_code: string; amount?: DimensionalNumberValue | number }
```

Срок — `SpellSpec.duration`. Стабильность — экземпляр в M8.

- Validation: `state_code` → `type: 'state'`.
- Ветка в `ActionEffectLabelService`.
- `effectsAfterAction` пропускает этот тип.
- `apply_damage` нет. Новый StateEffect «защита» — не M3.

### 6. `DamageTypeSpec.modifies_spell_difficulty`

Optional boolean, в `createEmpty` — `false`. Правило `arcane` — M4.

### 7. Validation

- Лишние `spell.difficulty` / `duration.difficulty` — ошибка.
- Обязательны power, control, duration, `hit_resolution` у spell.
- Инварианты duration из §2.
- Ссылки parameter.
- ОД ≥ 1.
- `action_effects` у spell допустимы: spell — действие.
- `apply_state` → state.
- `hit_resolution` у action не требовать.

### 8. UI

Патч спеки — методы `AbilitySpecService` (`createEmptySpellSpec`, `createEmptySpellDuration`, `ensureHitResolution`), не логика в шаблоне.

- SpellEditor: мощь/контроль; `hit_resolution` (none / attack / auto+rating); duration без сложности сотворения/обновления; ОД только у refreshable.
- Новый spell: default `hit_resolution: { type: 'none' }`, power/control с разумным DN (например `{3|0}` / `{3|-1}` — уточнить при реализации по Арканисту, не блокирует DTO).
- AbilityCard: мощь/контроль вместо сложности сотворения; duration знает `lingering`.
- Overview + `AbilityTab`: не показывать «Сложность» из спеки.
- Редактор apply_state — не M3 (в AbilityEditor его нет и для обычных action).

## Порядок работ

1. DTO.
2. Манифест + ветка AbilitySpec + empty duration. Не ломать prune operations у action.
3. Validation, включая разрешение `action_effects` на spell.
4. Labels, overview, card, editor.
5. `format` → `lint` → `vue-tsc` → vitest.

## Гейт M3

- Нет `SpellSpec.difficulty`.
- Spell сохраняет `action_effects`; validation это позволяет.
- Action не теряет `operations` после prune.
- Четыре duration; OD на тик только у refreshable.
- Power/control — DN или parameter, не полный Formula.
- `hit_resolution` обязателен у spell.
- `apply_state` типизирован и подписывается, Game не исполняет.
- Редактор не редактирует сложность сотворения.
- Нет runtime каста/урона.

## Журнал

| Дата | Изменение |
| --- | --- |
| 2026-09-08 | План M3: тонкий SpellSpec, lingering, манифест, hit_resolution, apply_state, флаг damage type. |
| 2026-09-08 | Spell — подтип действия: keywords skill/magic/action/spell уже в коде; validation/манифест трактуют spell как действие + SpellSpec. |
| 2026-09-08 | Реализован контракт: SpellSpec без difficulty, lingering, hit_resolution, apply_state, validation/UI без runtime каста. |
| 2026-09-09 | `sustained.power`: мощь поддержания в duration; подпись «Поддержание(Мощь: …)». |
