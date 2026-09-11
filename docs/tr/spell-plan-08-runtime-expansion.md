# План M8 — duration, `spell_upgrade` на каст, параметры активации

**Статус:** DONE, 2026-09-11. Родительская нарезка — [`spell-roadmap.md`](spell-roadmap.md). Срез — [`spell-plan-02-slice.md`](spell-plan-02-slice.md) E7. Контракт — [`spell-plan-03-contract.md`](spell-plan-03-contract.md). Check — [`spell-plan-06-cast-check.md`](spell-plan-06-cast-check.md). Эффект instant — [`spell-plan-07-runtime-slice.md`](spell-plan-07-runtime-slice.md). Канон — [`spell-review-01.md`](spell-review-01.md) SR-004, SR-076, SR-084, SR-130, SR-133, SR-135, SR-160. Правило поддержания — `spell-sustaining`. Правила фронта — [`draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md).

## Цель

M7 доставляет **мгновенный** эффект (Разряд касанием / Удар молнии auto). M8 подключает то, что живёт **после** успешного сотворения, и то, что **меняет этот каст**. Формулу сложности не трогать. Ману и отклонение не открывать.

После M8:

- успешный каст `lightning-generator` (`duration.type: 'sustained'`, `hit_resolution: none`) создаёт **ActiveSpell**: выбранный источник **этого** кастера занят; в первый ход мощь поддержания = мощь сотворения;
- в начале **следующего** хода кастера — одна операция: продолжить / оборвать, опционально сменить мощь; ОД на тик **не** брать; drop, если источник этого кастера недоступен;
- выбранные `spell_upgrade` входят в **этот** каст: дельта ОД в `max(...)` и `check_advantage` в бросок `check-spell-cast` (не постоянный грант);
- `chain-lightning` на Ударе: после **повреждений** — hop с −1 размером урона от последней цели, в том же треде атаки;
- `parameters` с `resolution: 'activation'` живут в контексте каста и на ActiveSpell, **не** в `CharacterVersion`.

Это не M9 и не backend.

## Representative

| Код | Роль в M8 |
| --- | --- |
| `lightning-generator` | Единственный duration end-to-end: `sustained`, `hit_resolution: none`, 4 ОД, `duration.power` = parameter `x`. Нет `spell.damage`. |
| `spell-sustaining` | Канон текстом. Runtime **не** парсит это правило как spec. |
| `careful-magic` | Upgrade без родителя, экземпляр пути (`domainCode` = код пути): +1 ОД, +1 преимущество, если отмечен. |
| `chain-lightning` | Upgrade с `parent_ability_code: 'lightning-strike'`: +1 ОД и hops. Канал — auto-тред `SpellCastDialog`, не HitLaunch. |
| `discharge` | Instant + касание: ActiveSpell не создавать; Аккуратное применяется через оферту HitLaunch. |

`refreshable` / `lingering` в DTO уже есть, карточек в срезе нет. **Не** реализовывать ветки refreshable/lingering «на вырост».

Расход электрозарядов Генератора (сопутствующие 2 ОД) остаётся текстом (`description-example`). Typed charge-effect не выдумывать.

## Два канала каста (факт кода)

Это не чинить «хвостом M7» внутри M8, но **опираться**:

| `hit_resolution` | Старт | Чат |
| --- | --- | --- |
| `attack` (Разряд) | Оферта `check-hit` + `proposal.spellCast` → `HitLaunchDialog` | тред атаки: объявление → касание → check → эффект |
| `auto` (Удар) | `SpellCastExecutionService.execute` из `SpellCastDialog` | тот же kind треда: объявление → check → «бьёт по» + [i] |
| `none` (Генератор) | тот же `execute` | тот же тред: объявление → check → «поддерживает», без урона |

Upgrade и source/path должны попасть в **оба** старта. Сейчас `sourceKey` / `pathCode` есть только в UI диалога и **не** уходят в `SpellCastExecutionInput` / `SpellCastOfferContext` — для occupy это дыра, её закрывает M8.

`SpellCastDialog.vue` уже раздувает форму + оркестрацию чата. M8 **не** дописывать туда lifecycle и hops inline: чекбоксы upgrade — дочерний кусок рядом; sustain-prompt — отдельный диалог с трека инициативы; hops — сервис + короткий выбор цели.

## Что не делать в M8

- Мана, частичная оплата, формула стабильности, отклонение, аркан — M9. Поле `stability` не считать и не класть.
- Activation в `CharacterVersion`.
- Постоянный грант `check_advantage`.
- ActiveSpell в `GameCombatOverlay.states` или внутри `ProcessSession`.
- Копировать HitLaunch / Process UI.
- Расширять `asActionAbilitySpec` на `spell`.
- `occupy_hands`, «Проводник магии», PHP, persist, vite.
- Менять `SpellCastDifficultyService` (upgrade не слагаемое сложности).
- Импортировать внутренности Character кроме `Character/init`.
- Учить `SpellCastOptionsService` списку ActiveSpell (он смотрит лист, не сессию). Occupied — отдельный сервис + UI disabled.
- Глобальный occupy по `magic-core` на всю игру: ядро чужого персонажа — другой overlay.
- Реализацию refreshable «на всякий случай».
- Переписывать тред Разряда/Удара; только дополнить объявление upgrade и ветку Генератора/hop.

## Сверка с кодом

1. **ОД.** [`SpellCastExecutionService.actionPointCost`](../../draft-front_1.2ds/src/modules/Roleplay/Game/Service/SpellCastExecutionService.ts) = `max(spellOd, touchOd)`. Станет `max(spellOd + Σ отмеченных action_point_delta, touchOd)`. Тот же вход нужен превью стоимости в диалоге и списанию до оферты касания. Неотмеченные дельты не входят.

2. **Преимущество.** [`SpellCastService.rollForSpell`](../../draft-front_1.2ds/src/modules/Roleplay/Game/Service/SpellCastService.ts) → `characteristicRollSpec` сейчас с `advantages: []`. Вход: `AdvantageModifier[]` с `source_code` = код навыка. Проброс и в `execute`, и в `completeAfterHit`. Не голое `adv` в Vue.

3. **Список upgrade.** Класс-сервис Game: abilities кастера + выбранный `pathCode` + `spellCode`:
   - `parent_ability_code === spellCode` (Цепная);
   - без родителя, `domain_ref: 'magic-path'`, `domainCode === pathCode` (Аккуратное).
   Не предлагать экземпляр чужого пути. Не применять невыбранные.

4. **Activation.** `parameterValues` уже на оферте. На ActiveSpell — копия. Для Генератора `x` задаёт мощь поддержания после первого хода, пока игрок не сменил. Instant — только в контексте каста.

5. **Source/path на касте.** Добавить `sourceKey` и `pathCode` в `SpellCastExecutionInput` и `SpellCastOfferContext`. Ключ источника — как в [`SpellCastOptionsService.listSources`](../../draft-front_1.2ds/src/modules/Roleplay/Game/Service/SpellCastOptionsService.ts): `inventory:{id}` или `grant:{abilityRuleCode}`. Без этого occupy не к чему привязать.

6. **ActiveSpell — сосед ProcessSession.** Dto + mock store + `IGameApi` (`getActiveSpells` / `upsert` / `drop`). Не state листа. Минимум полей:

   ```text
   id
   casterKey
   spellCode
   sourceKey               // ключ listSources этого кастера
   pathCode
   durationType            // в срезе только sustained
   usedPower
   sustainPower
   parameterValues
   appliedUpgradeCodes
   startedRound
   startedParticipantId    // пропуск тика в ход сотворения
   ```

   Instant успех **не** создаёт запись. Генератор — только если check успешен (или skip) и не auto-fail.

7. **Занятость источника.** `ActiveSpellService.isSourceOccupied(casterKey, sourceKey)`. Диалог: occupied option disabled / каст не стартует. Не фильтровать чужие ядра. Недоступность: inventory-ключ исчез или `equipped === false`; grant-ключ — способность-донор снята. Тогда drop сразу.

8. **Тик хода.** [`InitiativeTrack.nextTurn`](../../draft-front_1.2ds/src/modules/Roleplay/Game/Component/InitiativeTrack.vue): bleed у **уходящего**; sustain у **входящего**, если это не ход создания эффекта. Сервис решает continue/drop; Vue — диалог «продолжить / оборвать / сменить мощь». Check сотворения на тике нет. ОД тика нет.

9. **Смена мощи.** Clamp как у мощи каста (не выше текущей Магической мощи). Без нового броска. «Эффект жив» = запись ActiveSpell; заряды не считаем.

10. **Цепная.** Канал Удара (`SpellCastDialog` после `announceSpellApply`), тот же `beginAttack`. Критерий hop: **повреждения** `raw > 0` (не `remainingHpDamage` — это остаток после стойкости). Дальше: другая цель (`same_target: 'via_other'`), дистанция от последней (`from_last_hit`), `applyFalloff` + `− chain.damage_size_per_hop` к размеру, тот же `hit_resolution.rating`. Обрыв: урон ниже `chain.min` или `raw === 0`. ОД hops не берут (уже в стартовом max). Дистанция: как у M7 auto сейчас часто `0` ипари; для hop нужен ввод дистанции до цели (число ипари, без сетки). Короткий селект цели, не полный SpellCastDialog.

11. **Чат.** Дополнить `formatSpellCastBeginMessage` отмеченными upgrade (чипы правил). Генератор: «поддерживает …», drop: «перестаёт поддерживать» / «источник недоступен, эффект спал». Hop: «Цепная бьёт по …» + [i]. Тред не плодить второй.

## Состав работ

| Поверхность | Что сделать |
| --- | --- |
| Dto | `ActiveSpell`; `sourceKey` / `pathCode` / `appliedUpgradeCodes` на execute и оферте |
| Game Service | применимые upgrade; ОД с дельтами; advantage на бросок; occupy/drop; hops; upsert Генератора |
| IGameApi / Mock | хранение ActiveSpell как ProcessSession |
| SpellCastDialog | дочерний UI upgrade; occupied source; Генератор → upsert; hops после Удара |
| HitLaunch / оферта | те же upgrade и source/path на Разряде |
| Initiative | sustain-диалог на входе хода кастера |
| Тесты | гейт |

## Порядок

1. Сервисы + unit: фильтр upgrade, ОД, advantage, occupy, skip тика в ход создания, hop math (`raw`).
2. Mock API ActiveSpell; проброс `sourceKey`/`pathCode`/`appliedUpgradeCodes`.
3. UI upgrade + occupied source (оба канала).
4. Успех Генератора → upsert; `nextTurn` → lifecycle-диалог.
5. Hops Удара в том же треде.
6. Browser: Торвин — Генератор, Аккуратное на Разряд и на Удар, Цепная. AI не стартует vite.
7. `format` → `lint` → `vue-tsc` → `npm run test`.

## Гейт M8

- Разряд и Удар: тред M7 жив; без ActiveSpell; отмеченное Аккуратное даёт +1 ОД в max и преимущество на check; неотмеченное — нет.
- Генератор: успех → ActiveSpell на `(casterKey, sourceKey)`; тот же source этого кастера нельзя взять на второй каст; чужое ядро свободно; ручной drop освобождает.
- В ход сотворения sustain-prompt нет. Со следующего хода кастера: смена мощи / drop; check нет; ОД тика нет.
- Снят/снята экипировка источника кастера → drop + чат.
- Цепная неотмеченная — без hops. Отмеченная: +1 ОД на старте; hop только при `raw > 0`; обрыв по `min` / нулю повреждений / повтор цели без «через другую».
- Activation не в листе.
- Нет маны, нет stability, нет refreshable-runtime.
- Vue без `modifyDiffTo` и без суммы дельт в шаблоне.
- format / lint / `vue-tsc` / полный `npm run test`.

## Журнал

| Дата | Изменение |
| --- | --- |
| 2026-09-10 | Черновик: sustained = Генератор; upgrade = Аккуратное + Цепная; activation на касте/ActiveSpell. |
| 2026-09-10 | Перепроверка: два канала каста; `sourceKey` сейчас не в execute; occupy per caster; не refreshable; hop по `raw`; не раздувать SpellCastDialog; пропуск тика в ход создания. |
| 2026-09-10 | Код: upgrade/ОД/advantage, ActiveSpell mock API, Генератор, sustain-диалог хода, Цепная hops, чекбоксы на касте. |
| 2026-09-11 | Сверх плана: электрозаряды Генератора (state + spend 2 ОД в тот же SpellCastDialog); список процессов; группировка целей; upgrade popup; hops `via_other` + −1 размер от предыдущего hop; чат hop/обрыв и «повреждения» при нуле истощения. |
| 2026-09-11 | **M8 закрыт.** UI/скрытые баги — позже, не блокер. Дальше — [`spell-plan-09-magic-runtime.md`](spell-plan-09-magic-runtime.md). |
