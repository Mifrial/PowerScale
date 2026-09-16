# C2 — план: улучшения удара (`strike_upgrade`)

**Статус:** `READY`, 2026-09-15.  
**Родитель:** [`rule-content-roadmap.md`](rule-content-roadmap.md).  
**Очередь:** [`rule-content-queue.md`](rule-content-queue.md).  
**Канон знаний (не переписывать):** [`rule-content-plan-04-knowledge.md`](rule-content-plan-04-knowledge.md) §4.4 Смертоносные удары.  
**Образец UI:** [`spell-plan-08-runtime-expansion.md`](spell-plan-08-runtime-expansion.md) — `spell_upgrade` на касте.  
**Первая карточка:** `smertonosnye-udary`.  
**Не этот срез:** уход, хирургия-операция, фармация, питание, −2/сутки, Game-речь, PHP, свойства оружия «Смертоносное» на секире.

Долгих сцен в Game нет — практику «на дни» не открывать. Этот срез — **походовый удар**.

## 0. Зачем не диалог после попадания

Улучшений удара будет много. Спрашивать после каждого попадания — игрок умается.

Как у заклинаний: на **запуске** удара список галок «входит в этот удар». Автоувечье после попадания **только читает** уже выбранное. Отдельного оффера «помеха или преимущество?» нет.

Не брать `spell_upgrade` как есть: родитель Смертоносных — `khirurgiya`, не код действия-атаки; фаза — проверка **увечья**, не `check-spell-cast` / не попадание.

## 1. Что уже есть

- Автоувечье с удара: три вызова `applyInjuryCheck` в `HitLaunchDialog` (обычный удар; касание+слой заклинания; пакет слоёв/взрыва). Модификаторы этого среза вешать **во все три**, одним helper’ом — не только в `applyAttackConsequences`.
- Помеха рубящего на увечье уже внутри `InjuryRollService.slashingHindrance` из `damageTypeCode`, не из диалога. Strike-моды класть в `InjuryRollInput.advantages`; рубящий не дублировать.
- Состояния цели в ту же проверку докидывает `InjuryCheckService` через `stateRuntimeEffectsService.checkAdvantageModifiers`.
- `spell_upgrade`: `SpellCastUpgradeService.listApplicable` матчит `parent_ability_code` **коду заклинания** (или путь без родителя). `pruneSelected` exclusive-групп **не** знает. Список каста — чекбоксы во вложенном диалоге (`SpellCastUpgradeList`). Strike это не копирует один в один.
- Физиология: `knowledgeCheckService` из `Character/init` (`effectiveLevel` / близкий вид). `practiceApplies` читает только `KNOWLEDGE_PRACTICE_GATE_CODES` (`ukhod`, `khirurgiya`, `farmatsiya`, `poisk-trav`, `pitanie`). **`smertonosnye-udary` туда не добавлять** — иначе практика «нельзя без якоря» размажется на удар. Гейт: `effectiveLevel(..., 'physiology', { species }, rules) >= 1`.
- Вид цели: `CharacterVersion.raceRuleCode` (раса сводится к виду по `parent_race_code`) → слот `{ code, text: '' }`. Нет кода расы — опций нет.
- Карточка `smertonosnye-udary`: 1 ОР, `parent_ability_code: 'khirurgiya'` — **только покупка**. В `listApplicable` родителя с кодом атаки **не** сравнивать (иначе галок никогда не будет). `contentNote` «не исполняется». Операция хирургии Game не нужна, чтобы купить навык.
- `AbilityEditor` уже редактирует `spell_upgrade` — соседнее поле `strike_upgrade` туда же, не «если появится форма».
- Свойство оружия «Смертоносное» (`sekira_smertelnie_uvechya`) — другие коды, две помехи по тексту, без знания вида, runtime нет. **Не** смешивать.

Проверка увечья: больше успехов → тяжелее увечье. Преимущество на **эту** проверку калечит; помеха — щадит. Текст карточки: «можете» — ни один режим не обязателен.

## 2. Контракт `strike_upgrade`

На `AbilitySpec` (skill), рядом со `spell_upgrade`, не внутри него.

```text
strike_upgrade: {
  exclusive_group: string            // радио: один пункт группы на удар
  modes: {
    code: string                     // суффикс опции, уникален в карточке
    label: string                    // «Калечить» / «Щадить»
    injury_check_advantage: number   // +1 преимущество / −1 помеха
  }[]
  requires_physiology?: true         // цель: эффективная physiology ≥ 1 (близкий вид −1)
}
```

Идентификатор опции в UI и в выбранных кодах: `ruleCode + ':' + mode.code` (пример: `smertonosnye-udary:cripple`). Не плодить две карточки каталога.

YAGNI этого среза: нет дельты ОД, нет хука попадания, нет `chain`. Появятся — поля рядом, не второй тип спеки.

Редактор: мок + поле рядом со `spell_upgrade` в `AbilityEditor` + валидация спеки (`modes` непусты, `exclusive_group`, целые `injury_check_advantage`).

PHP / `rule-system.md` — по факту посадки, C3 не открывать.

## 3. Runtime

Сервис `StrikeUpgradeService` (Game). Образец — `SpellCastUpgradeService`, не тот же класс и не тот же UI.

1. `listApplicable(actorAbilities, targetVersion, rules)` — навыки актора со `strike_upgrade`. Родитель карточки **не** фильтр применимости. `requires_physiology`: слот вида с `targetVersion.raceRuleCode`; нет цели / нет кода / уровень 0 — эту карточку не показывать. Параметр «код действия» не нужен (YAGNI).
2. `pruneSelected` — выкинуть снятые гейтом; в одной `exclusive_group` **не больше одного**; **ноль допустим**.
3. `injuryAdvantageModifiers(selected)` → `AdvantageModifier[]` в `input.advantages` автоувечья **этого** удара (все три вызова в HitLaunch).

UI: дочерний блок на теле `HitLaunchDialog`, не второй вложенный диалог как у каста (HitLaunch уже модалка). Переключатель с «выкл»: два режима одной группы, снять оба можно. Не `SpellCastUpgradeList` (там независимые чекбоксы).

Несколько целей одного запуска: гейт и `applyInjuryCheck` **по цели**. Выбор общий; на цели без физиологии модификатор не применять (prune на этот вызов).

Куда **не** вешать: `InjuryLaunchDialog`, кровь конца хода, DOT, auto-увечье `SpellCastDialog` без Hit.

Выбор живёт в состоянии запуска удара, умирает с закрытием диалога. Не на `CharacterVersion`.

## 4. Первая карточка

`smertonosnye-udary`:

| mode | label | `injury_check_advantage` |
| --- | --- | --- |
| `cripple` | Калечить | `1` |
| `spare` | Щадить | `-1` |

`exclusive_group: 'smertonosnye-udary'`. `requires_physiology: true`. Родитель хирургии на покупку не трогать. `contentNote` снять или сузить до «ручное увечье / кровь без удара не читают».

Не требовать первую помощь. Не требовать живой Game-операции.

## 5. Срез реализации

1. DTO `StrikeUpgrade` + поле на `AbilitySpecBase`; валидация; сосед в AbilityEditor.
2. Мок `smertonosnye-udary`; unit: гейт по `raceRuleCode` и близкому виду; без кода расы пусто; exclusive + ноль выбранных; `practiceApplies('smertonosnye-udary')` по-прежнему false.
3. `StrikeUpgradeService` + дочерний блок на HitLaunch; выбранное → `input.advantages` во всех трёх `applyInjuryCheck`.
4. Тест: без физиологии галок нет; «калечить» → `delta: 1` в advantages броска увечья; «щадить» → `-1`; оба сразу после prune — один.

## 6. Не входят

Уход, хирургия внутренних ран, фармация, питание, сутки. Свойства оружия «Смертоносное». Помеха/преимущество на **попадание**. Оффер после раны. PHP persist выбора. Новые культуры/языки.

## 7. OPEN

- Дельта ОД на `strike_upgrade` (как `action_point_delta` у каста) — когда появится карточка.
- Тот же список на auto-увечье сотворения без Hit.
- Свести оружейное «Смертоносное» к тому же полю (сейчас отдельный текст на мастерстве, runtime нет).

Перепроверка 2026-09-15: контракт сверён с HitLaunch (3 вызова), `InjuryRollService`, `KnowledgeCheckService`, каноном §4.4. Посадка: `strike_upgrade` на карточке, сервис, блок на запуске, коды в оферте удара.
