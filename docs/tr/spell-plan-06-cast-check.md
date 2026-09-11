# План M6 — контекст сотворения и Сложность сотворения

**Статус:** DONE, 2026-09-09. Родительская нарезка — [`spell-roadmap.md`](spell-roadmap.md). Формула — [`spell-inventory-01.md`](spell-inventory-01.md) (213–222), [`spell-review-01.md`](spell-review-01.md) SR-050…056, SR-102, SR-106. Контракт спеки — [`spell-plan-03-contract.md`](spell-plan-03-contract.md). Фикстура — [`spell-plan-04-import.md`](spell-plan-04-import.md). Правила фронта — [`draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md).

## Цель

Считать **Сложность сотворения** в чистом сервисе Game и дать игроку выбрать источник и путь на этот cast. Бросок — стандартная **проверка на сотворение** (`check-spell-cast`); характеристику пула задаёт путь. Эффекты урона, Шок, ActiveSpell и трата ОД **не входят**.

После M6:

- сложность не хранится в `SpellSpec` и не считается в Vue;
- контекст: заклинание + выбранные source/path + используемая мощь + контроль персонажа + опциональное сопротивление цели;
- дефицит/превышение — шаги шкалы 3–5 (`CharacteristicNumber.modifyDiffTo`), не `toNumber()`;
- ниже минимума `{3|-1}` результат `{0|-1}` — проверка не требуется;
- UI показывает выбор source/path даже если в срезе по одному варианту.

Это не M7: успешный/провальный эффект через `ActionExecutionService` / `AttackDamageService` не запускать.

## Что не делать в M6

- урон, Шок-хук, `apply_damage` как исполнение, отклонение, взрыв, ActiveSpell, ProcessSpec для duration;
- кристалл, мана, несколько источников в одном cast, source-specific усиления;
- штраф «используемая мощь выше текущей Магической мощи» — в inventory `needs_decision`; в M6 используемая мощь — вход контекста, без второй формулы;
- поле `runtime-support` на Rule (`DEC-055`);
- PHP / persist / `vite build` / запуск dev-сервера;
- расширять `asActionAbilitySpec` на `type === 'spell'` — иначе заклинания смешаются со списком атак (`listAttackActions`);
- класть формулу в Rule (Rule не знает Game; сложность — runtime);
- импортировать внутренности Character (`Service/` кроме того, что уже реэкспортирует `Character/init`); `CharacterEditorService` не звать из Game — это редактор листа, не runtime;
- писать параметры активации в `CharacterVersion` (M8);
- применять `spell_upgrade` к броску/ОД каста (поле есть в Rule, диалог ещё не читает);
- дестабилизацию как слагаемое сложности (M9).

Строка M2 «полный UX source/path» закрывается здесь. M7 зависит от этого сервиса, не дублирует формулу.

## Сверка с кодом

1. **База `{3|0}`.** В `SpellSpec` сложности нет. Константа — `Game/Constant/Spell/`, не хардкод в Vue и не поле каталога.

2. **Шаги шкалы уже есть.** [`CharacteristicNumber.modifyDiffTo`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Value/CharacteristicNumber.ts): `{4|1}` vs `{3|-1}` = `+7`. [`modifyWith`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Value/CharacteristicNumber.ts) даёт `{3|0}.modifyWith(+7) = {4|2}`. Game может импортировать `Rule/Value/` (публичный слой). Не считать дефицит через `toNumber()` и не писать второй алгоритм переноса базы.

3. **Формула (канон inventory 215–217).**  
   Пусть `p = requiredPower.modifyDiffTo(usedPower)`, `c = requiredControl.modifyDiffTo(availableControl)` (плюс = нехватка).  
   Старт: `{3|0}`.  
   Если `p > 0`, прибавить `p`; если `c > 0`, прибавить `c`.  
   Если `p < 0` и `c < 0`, вычесть `min(-p, -c)`.  
   Предела дефицита нет.  
   Минимум «живой» сложности `{3|-1}`; если очередное снижение ушло бы ниже — результат `{0|-1}` (проверка не нужна). Не путать с [`SIMPLE_CHECK_ZERO_DIFFICULTY`](../../draft-front_1.2ds/src/modules/Roleplay/Game/Constant/Check/SIMPLE_CHECK_ZERO_DIFFICULTY.ts) `{0|0}`.

4. **Сопротивление.** Флаг [`DamageTypeSpec.modifies_spell_difficulty`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/Damage/DamageTypeSpec.ts) (у среза — `arcane`). SR-102: `difficulty.modifyWith(N)`, где `N` — целое число сопротивления (обычно `toNumber()` гранта `{X|0}`), не вторая размерная «мощь». Накладывать, только если у цели есть resistance на тип с флагом **и** этот cast считается направленным на цель (вход контекста: цель задана). Срез `discharge` / `lightning-strike` бьёт электричеством — флага нет, сопротивление Аркане на них **не** влияет. Тесты resistance — синтетический спек или явный `damage_type_code` в контексте, не выдумывать arcane-урон у Разряда.

5. **Сбор resistance.** Overview защиты читает только `armor.resistance_slots` предметов, не гранты способностей. `magic-resistance` живёт грантом на ability. Game читает `CharacterVersion` (Dto) + `Rule.spec.grants` типа `resistance`; параметр `X` резолвить локально (`per_unit ×` выбранного параметра). Не ходить в `CharacterEditorService.resolveGrant`.

6. **Мощь/контроль заклинания.** `SpellValue`: DN или `{ type: 'parameter'; parameter_code }`. Значение параметра — в **cast context**, не в build (M2). Фикстура Торвина: `discharge` без activation-параметра — фиксированные DN из спеки.

7. **Используемая мощь.** Источник даёт временную мощь (inventory 221). Дефолт — итог характеристики `magic-power` кастера (overview `value`). Игрок может выбрать другое размерное значение. Сравнение — used vs required power, control персонажа vs required control. Ядро ману не тратит.

8. **Source / path.** Списки из snapshot + каталога (Арканист / псионик / шаман). Путь → `MagicPathSpec.check_code` только как **стат** пула (Сила воли / Интеллект / Духовность), не как код броска. Бросок — `check-spell-cast`. Если шаман кастует псионический спел **выбранным путём шамана** — пул Духовности. Применение `spell_upgrade` (ОД, преимущество) **не входит** — M8.

9. **Запуск из боя.** [`asActionAbilitySpec`](../../draft-front_1.2ds/src/modules/Roleplay/Game/Utils/combatActions.ts) режет `type !== 'action'`. Не расширять. Отдельный метод сервиса: owned spells (`overview.abilities` ∩ `spec.type === 'spell'`). [`ActionLaunchDialog.vue`](../../draft-front_1.2ds/src/modules/Roleplay/Game/Component/ActionLaunchDialog.vue) уже перегружен — не добавлять туда source/path. Новый `SpellCastDialog.vue` (одна задача). Точка входа: узкий вызов с боевой карточки / трека, **без** `actionExecutionService.execute`.

10. **Бросок.** [`CharacteristicRollService.characteristicRollSpec`](../../draft-front_1.2ds/src/modules/Roleplay/Game/Service/CharacteristicRollService.ts) + [`CheckRollService.rollNamedCheck`](../../draft-front_1.2ds/src/modules/Roleplay/Game/Service/CheckRollService.ts)(`check-spell-cast`, difficulty). При `{0|-1}` бросок не вызывать. Vue только показывает outcome. РУ — существующий `CheckSuccessRatingService`, не дублировать.

11. **Слои.** Game → Rule Dto/Value/Constant/`init`, Character Dto/`init` (overview). Формула — класс в `Game/Service/`, константы в `Game/Constant/Spell/`, контекст — `Game/Dto/Spell/` (именованные типы не в файле сервиса). Один экспорт на файл; синглтон в `Service/Instance/`. `Utils/` для этой формулы не использовать.

12. **Publish / каталог.** Не стерилизовать. Гейт — unit-тесты формулы и listing, не «нажать Опубликовать».

## Состав работ

| Поверхность | Что сделать |
| --- | --- |
| Constant | `SPELL_CAST_BASE_DIFFICULTY` `{3\|0}`, минимум `{3\|-1}`, skip `{0\|-1}`; коды `magic-power` / `magic-control` / `magic-core` / `becoming-arcanist` если ещё не живут в Constant |
| Dto | контекст каста: spell code, source item ref, path code, used power, control value, parameter map, optional target resistance / «есть цель» |
| `SpellCastDifficultyService` | resolve SpellValue; дефицит/surplus; clamp; resistance; skip flag |
| `SpellCastOptionsService` (или методы того же класса, если не раздуется) | доступные sources (equipped `magic-core`) и paths (грант `magic_path` на листе); дефолт — единственный / лучшая мощь; check = spec пути |
| Listing | список изученных spell без изменения `combatActions` |
| `CheckRollService` | вызвать из сервиса каста при non-skip; не копировать engine |
| `SpellCastDialog.vue` | селекты source/path, используемая мощь, подпись сложности, кнопка проверки; математики в шаблоне нет |
| Точка входа UI | открыть диалог со списка заклинаний, не через execute action |
| Тесты | inventory-пример `+7` → `{4\|2}`; surplus `min(x,y)`; clamp → skip; resistance только с флагом; skip не зовёт rng |

## Порядок

1. Константы + Dto контекста.
2. `SpellCastDifficultyService` и табличные тесты формулы (без Vue).
3. Списки source/path/spell из snapshot.
4. Обвязка броска `check-spell-cast` при non-skip (стат с пути).
5. Диалог + точка входа; диалог только биндит сервис.
6. Browser: диалог на фикстуре Торвина (`discharge`, ядро, Арканист). AI не стартует vite.
7. `format` → `lint` → `vue-tsc` → `npm run test`.

## Гейт M6

- `{4\|1}` vs `{3\|-1}` даёт модификатор `+7` и сложность `{4\|2}` от базы `{3\|0}`.
- Одновременное превышение снижает на `min(x, y)`; одностороннее превышение сложность не снижает.
- Снижение ниже `{3\|-1}` → `{0\|-1}`, `needsCheck === false`.
- Resistance с `modifies_spell_difficulty` увеличивает сложность через `modifyWith`; без флага — нет.
- Vue не содержит шагов `modifyDiffTo` / `modifyWith`.
- `asActionAbilitySpec` по-прежнему только `action`.
- Эффекты заклинания в бою не применяются.
- format / lint / vue-tsc / полный `npm run test`.

## Журнал

| Дата | Изменение |
| --- | --- |
| 2026-09-08 | План после сверки: Game-сервис, шкала `CharacteristicNumber`, отдельный диалог, без execute и без расширения `asActionAbilitySpec`. |
| 2026-09-09 | Редактор уже знает псионика/шамана и `includes_path_codes`. M6: списки путей + `check_code` выбранного пути. `spell_upgrade` — M8, не этот план. |
| 2026-09-09 | M6 закрыт: `SpellCastDifficultyService` / options / dialog; точка входа с боевой карточки (вкладка «Способности»). |
| 2026-09-10 | После UX-правок: бросок всегда `check-spell-cast`; `check_code` пути — только характеристика пула. Эффекты/ОД по-прежнему M7. |
