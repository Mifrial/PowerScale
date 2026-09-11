# План M4 — mock catalog и revision fixture среза магии

**Статус:** DONE, 2026-09-08. Родительская нарезка — [`spell-roadmap.md`](spell-roadmap.md). Канон среза — [`spell-plan-02-slice.md`](spell-plan-02-slice.md). Контракт DTO — [`spell-plan-03-contract.md`](spell-plan-03-contract.md). Правила фронта — [`draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md).

## Цель

Посадить representative slice M2 в mock-каталог Rule так, чтобы ссылки среза замыкались, `validateCatalog` не ругал **коды среза**, а revision round-trip шёл по `code` (`assemble` → serialize/parse → `materializeRules`), без внутренних `id`/`spaceId` в файле.

Это **одна замкнутая контентная партия среза**, не весь порядок C2 из roadmap и не весь `AI.html`. Preview/publish в UI пространства — M5; в M4 достаточно сервисного round-trip и catalog validation.

После M4:

- в моках есть ядро-item, две характеристики сотворения, путь Арканиста, простое правило поддержания, `arcane`, `shock`, keyword `electromancy`, два instant-spell (касание и автопопадание-луч), duration-spell, upgrade-skill;
- `magic-resistance`: грант resistance на `arcane` с `per_unit: 1` (цена ОС `zones.os` **не** менять — там по-прежнему `2×X`);
- спеки заклинаний соответствуют M3;
- есть тест assemble/parse/materialize без потери кодов среза.

## Что не делать в M4

- парсер `AI.html` и генерация правил из HTML;
- остальные школы, Псионик, Шаман, кристалл, мана, простейшее волшебство, `charge-accumulation`;
- `SpellCastService`, урон в Game, Шок-хук, ActiveSpell, отклонение;
- `apply_damage` в `ActionEffect`; формулы урона — в `description` до M7;
- `attached_rule_codes` у `electricity` на Шок, пока нет mechanic-handler (иначе `validateDamageTypeStructure` потребует `mechanicId`);
- поле `runtime-support`; значение `contentStatus`, которого нет в `RULE_CONTENT_STATUSES` (`needs_work` | `ready`);
- удалять `magic`, `magic-potential`, `magic-damage` (расы, моды, тесты);
- PHP / persist / UI publish;
- персонажа с ядром в Character mock;
- dev-сервер и `vite build`.

Неполный текст AI.html — в `description`. Disposition inventory `text_only` ≠ `contentStatus`.

## Сверка с кодом

1. Каталог: [`mockRules.ts`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Mock/mockRules.ts) склеивает `mockRuleImport`, races, `mockDevelopmentImport`, `mockItemImport`, … Эталон партии — `mockDevelopmentImport`. M4 — `mockSpellImport.ts` в тот же `sanitizeCatalog([...])`. **Диапазон id:** 200+ development, 400+ items, 9100 хуки, 9201+ в `mockRules`. Срез — **9400+**, не пересекать. Keyword: `nextId = 227` → `electromancy` id **227**.
2. `syncTypeTags` в моке не вызывается: у spell явно `keywordIds` skill+magic+action+spell (+ `electromancy`). Коды: 13, 3, 14, 16.
3. Revision: не собирать `RevisionFile` руками по полям. Взять [`revisionFileService.assemble`](../../draft-front_1.2ds/src/modules/Roleplay/RuleSpace/Service/RevisionFileService.ts) (формат `powerscale.revision` v3) на срезе + keywords + mechanics, затем `serialize` / `parse` / `materializeRules`. Тест в **RuleSpace** `__tests__`; mock — из `Rule/Mock`.
4. `validateCatalog` на всём `ruleCatalog` уже может иметь чужие ошибки. Гейт: `blockingMessagesForRule` пуст **для кодов среза**, не «каталог стерилен».
5. Item: обязательны `category`, `cost_gm`, `weight`, `special_rule_codes`. Innate как [`ruka`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Mock/mockItemImport.ts): `cost_gm: null`, `weight: null`. **Не** вешать keyword `crystal` — миграция утащит в `items-consumables-magic-crystals`. Без него секция — `items-other`.
6. `magic-resistance`: грант `per_unit: 2` на `magic-damage` и **отдельная** цена `zones.os.per_unit: 2`. M2 меняет только грант (`arcane`, `per_unit: 1`) и текст «+X», не стоимость покупки. `magic-damage` остаётся для модов. Тест черт править по гранту; Character-тесты на одну цену ОС не обязаны падать.
7. `magic` / `magic-potential` не `automatic`: база с гранта. То же для `magic-power`. `magic-control` не automatic. `{3|-1}` — это **`3↓`** (`DimensionalNumber.parse('3↓')`), `toNumber()` = 1, не «целое 2». Грант пути: `Grant.type: 'characteristic'` внутри `grants: [{ level: 1, grants: [...] }]`, как `traitRule`.
8. `DAMAGE_TYPE_FORMS` не создаёт Rule. Нужны и формы, и правило `arcane`. `createEmpty` даёт `modifies_spell_difficulty: false` — у `arcane` выставить `true`.
9. Нет Естествознания (SR-018) — не ссылаться.
10. [`MockRuleCatalogMigrationService`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Mock/MockRuleCatalogMigrationService.ts): `type: 'spell'` → `abilities-acquired-other`. Trait → `abilities-innate-common`. State → `scenes-states`. Characteristic → `basic-characteristics`. Секцию «Волшебство» в M4 не вводить.
11. Inventory: `chain-lightning` → **`lightning-strike`**, не `discharge`.
12. `{3|-1}.toNumber()` = 1, `toString()` = `3↓`. Целое 2 при базе 3–5 — `{4|-1}` (`4↓`). Не писать «мощность 2 = {3|-1}».
13. Id правил среза **9400+**. Keyword `electromancy` = **227**. Rule id 227 уже занят development (`pravilnoe-proiznoshenie`) — это другой namespace, коллизии нет, но не путать.
14. `magic-core-capacity`: признаки как у `magic-potential` — 11/44/45/47 (trait, innate, characteristic, gift).

## Состав партии

| Код | Тип | Новый? | Содержание M4 |
| --- | --- | --- | --- |
| `magic-power` | `characteristic` | да | «Магическая мощь», `group: 'primary'`, **не** `automatic` |
| `magic-control` | `characteristic` | да | «Контроль магии», `group: 'primary'`, не automatic |
| `magic-core` | `item` | да | `innate: true`, `category: 'equipment'` (как рука/тело), без weapon/armor, без keyword crystal |
| `magic-core-capacity` | `ability` / `trait` | да | «Магическое ядро X». Уровень 1: Grant `item` + `characteristic_parameter` на `magic-power`. Параметр как у `magic-potential`. Цена ОС — `MAGIC_COSTS`. Keywords 11/44/45/47 |
| `arcanist` | `magic_path` | да | Check Интеллект, `study_cost` скидка 1/2 и пара за 1 ОР |
| `becoming-arcanist` | `ability` | да | Grant пути, `magic_study` spell ≤ 2, Контроль 3↓ |
| `discharge` | `ability` / `spell` | да | Instant, 4 ОД, `hit_resolution: attack`. Мощь: parameter `x` activation. Контроль `{3\|-1}` (`3↓`). Keywords 13/3/14/16 + 227. Урон — description |
| `spell-sustaining` | `simple` | да | «Поддержание заклинаний»: источник занят, без ОД на тик, своя мощь, первый ход = мощь сотворения, смена с начала следующего хода. Секция `magic-rules-casting`. |
| `lightning-strike` | `ability` / `spell` | да | Instant, 4 ОД, `hit_resolution: auto` РУ 2. Контроль **`{4\|-1}`** (`4↓`). **Без** keyword `ranged`. Соматика `occupy_hands: 1`. Falloff в `spell.damage`. Требует `discharge`. |
| `lightning-generator` | `ability` / `spell` | да | `sustained`, 4 ОД сотворения, `duration.power` = parameter `x`. Контроль `{5\|-1}` (`5↓`). Мощь сотворения `{4\|-1}`. Эффект зарядов — description (сопутствующее 2 ОД / 1 электрозаряд / известная электромансия). Общая механика поддержания **не** в description. |
| `chain-lightning` | `ability` / `skill` | да | Не spell. `spell_upgrade`: +1 ОД и `chain`. `parent_ability_code: 'lightning-strike'`, `multiple: true`. Keywords skill+magic+electromancy, без action/spell. |
| `arcane` | `damage_type` | да | `modifies_spell_difficulty: true` + формы |
| `shock` | `state` | да | Как другие states: `value_type: 'number'`, `aggregation: 'sum'`, effect `characteristic_modify` на `dexterity` с `per_unit: true` (M2 E5). Без mechanicId / Game-handler |
| `electromancy` | keyword | да | Опыт школы. `derived_level`-ability в M4 не обязателен |
| `electricity`, `intellect`, `action-points` | reuse | нет | |
| `magic-resistance` | правка | — | `arcane`, `per_unit: 1`; description +X |
| `magic` / `magic-potential` / `magic-damage` | leftover | нет | Не удалять |

`lightning-strike` в срезе нужен как родитель upgrade. `discharge` остаётся representative касания.

## Нормализация

Источник истины — typed `Rule[]` в `mockSpellImport.ts`, не параллельный JSON inventory.

В тесте (не обязательно в каждом объекте) для кодов среза: locator из inventory, что в spec, что в description.

`contentStatus`: `needs_work` (default assemble). Не писать `text_only`.

## Revision

1. `assemble` на правилах среза (после миграции секций — как в каталоге, или сырой импорт + те же keywords).
2. В JSON rules нет `id`/`spaceId`; есть `keywordCodes`.
3. `parse` того же JSON.
4. `materializeRules(spaceId, keywords, mechanics)` — коды и spec (power/control/duration/hit_resolution/parent) совпадают.

Не дублировать руками `.json` в git, пока round-trip из мока зелёный.

## Validation

- `validateCatalog(ruleCatalog, keywords)` → для каждого кода среза нет blocking.
- Spell: power, control, duration, `hit_resolution`, ≥1 ОД; база DN 3–5.
- Activation-parameter мощи есть в `parameters`.
- Parent `chain-lightning` → `lightning-strike`.
- Grant ядра → существующий `magic-core`.
- `shock.effects[0].characteristic_code` → `dexterity`.
- Не ждать `apply_state` на Разряде.

Правка `magic-resistance`: обновить грант в [`mockRuleImport.test.ts`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/__tests__/Mock/mockRuleImport.test.ts). `zones.os.per_unit: 2` оставить.

## Файлы

- `Rule/Mock/mockSpellImport.ts`
- `Keyword/Mock/mockKeywords.ts` — `electromancy` id 227
- `Rule/Constant/DAMAGE_TYPE_FORMS.ts` — `arcane`
- `Rule/Mock/mockRules.ts` — spread импорта
- `Rule/Mock/mockRuleImport.ts` — только `magic-resistance`
- `Rule/__tests__/Mock/mockSpellImport.test.ts`
- `RuleSpace/__tests__/Service/revisionFileSpellSlice.test.ts` (имя любое в этой папке)

Санитизация имён: как development, через `importedRuleNameService.sanitizeCatalog` на всём каталоге, не отдельным проходом только среза (иначе расхождение с `ruleCatalog`).

## Порядок работ

1. Keyword + формы `arcane`.
2. Характеристики, item, trait ядра, путь / becoming.
3. Правило `arcane`, `shock`, правка resistance + тест мока черт.
4. Четыре spell-карточки по M3.
5. Подключить импорт, уникальность id/code, catalog validation по кодам среза.
6. Revision `assemble` round-trip.
7. `format` → `lint` → `vue-tsc` → vitest.

## Гейт M4

- Коды таблицы есть в `ruleCatalog`.
- Срез не даёт blocking в `validateCatalog`.
- Нет stored `difficulty`; generator — `sustained` без ОД на duration; мощь генератора `{4|-1}`.
- `chain-lightning.parent_ability_code === 'lightning-strike'`; тип `skill` + `spell_upgrade`, не SpellSpec.
- `spell-sustaining` в каталоге; description Генератора не дублирует правило первого хода.
- Ядро — innate item, не `RuleType.source`, не секция кристаллов.
- Грант `magic-resistance` → `arcane` ×1; цена ОС по-прежнему `2×X`.
- Keyword `electromancy`.
- Round-trip через `assemble`.
- Нет HTML-парсера, каста, хука Шока на `electricity`.
- Leftover `magic` / `magic-potential` / `magic-damage` на месте.

## Журнал

| Дата | Изменение |
| --- | --- |
| 2026-09-08 | План M4: ручной mockSpellImport, revision по code. |
| 2026-09-08 | Перепроверка: parent Цепной — `lightning-strike`; DN «мощность 2»; item DTO/innate/секции; `assemble`; id 9400+/keyword 227; `contentStatus` ≠ text_only; гейт validation только по кодам среза; Grant контроля не modify. |
| 2026-09-08 | Вторая перепроверка: `{3|-1}` = `3↓` (toNumber 1), не целое 2; грант vs цена `magic-resistance`; grants с `level`; контроль удара `{4|-1}`; keyword 227 ≠ rule 227. |
| 2026-09-08 | M4 закрыт: `mockSpellImport` в каталоге, revision assemble/parse/materialize, гейт format/lint/vue-tsc/1420 tests. |
| 2026-09-09 | Актуализация: `spell-sustaining`; Цепная — skill; Удар — auto-hit без ranged; Генератор — `duration.power` и расход зарядов в тексте. |
| 2026-09-09 | После M4: `psionic` / `shaman` (`includes_path_codes: ['psionic']`), `spirituality`, входные навыки путей, `careful-magic` как экземпляр пути + `spell_upgrade.check_advantage`. Исторический scope M4 («не Псионик/Шаман») не переписывать — это гейт закрытия партии. |
