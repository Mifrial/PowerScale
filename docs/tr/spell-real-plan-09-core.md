# План M9 — ядро и мощь источника

**Статус:** ~СДЕЛАНО~, 2026-11-14. Срез M9 делает источник каста
полноценным: мощь живёт у экземпляра предмета, `magic-power` выводится из
ядра, «Оптимизация потоков» даёт бонус к потолку мощи источника по общей
форме `spell_source_power`. Ручная проверка в dev пройдена: карточка,
редактор, detail и каст показывают `Магическая мощь 5`. Полный
`bash tools/test-full.sh` пройден 2026-11-14: phpstan без ошибок, бэкенд
1680 (3 skip), фронтенд 2593. Заёмная мощь (`+x` мощь, `−2x` контроль)
остаётся вне M10 вместе с магическими накопителями — у неё нет
производителя в текущем контенте. Отложено: выбор конкретного предмета для
области `source` в редакторе персонажа (сервер и форма уже принимают
`targets`, но ни одна карточка не использует `scope: source`) — вернуться,
когда появится первый потребитель. Выдача предметов при
миграции идёт через редактор: миграция только показывает diff, состав
утверждает владелец.

**Реализация:** ~PHP~, ~VUE~. Моки источников и `usedPower` — ~НЕ ОСНОВА~
как контракт; берём только семантику «источник даёт свою мощь, выбор
источника влияет на каст».

**Опираться на:** ~PHP~ (`CharacterInventoryChoice`,
`CharacterChoiceAssembler`, `CharacterLiveBases`, `CharacterGrantParameters`,
`CharacterEquippedLive`, `CharacterInputChecks`, `ItemSpecs`, `ItemGrant`,
`GameSpellCastFacts`, `GameSpellPowerCap`, `GameSpellSource`,
`GameSpellDeviation`, `GameActiveSpellSustain`), ~VUE~
(`CharacterEditorService`, `SpellCastOptionsService`, `SpellCastDialog`,
`SpellCastSourceOption`, `ActiveSpellService`), контент
(`magic-core`, `magic-core-capacity`, `magic-core` item spec,
`core-flow-optimization`).

**Родитель:** [`spell-real-roadmap.md`](spell-real-roadmap.md), шаг M9.
Канон контента — [`spell-inventory-01.md`](spell-inventory-01.md),
[`spell-plan-09-magic-runtime.md`](spell-plan-09-magic-runtime.md) (источник
не тратит ману). Источник правил — `[AI] Волшебство mini v1.html`.

M8 закончил заряды. Каст всё ещё берёт потолок мощи из общей
характеристики `magic-power`, а «Оптимизация потоков» не существует в
контенте. M9 разводит источники: у каждого ядра своя мощь, каст читает её
у выбранного предмета.

## 1. Границы

Входят:

- строка инвентаря как экземпляр источника: `parameters.power` и
  `grantedByRuleCode` (от какой способности получена);
- серверный пересчёт `power` из живого параметра донора при сохранении
  персонажа;
- производная `magic-power` из owned-ядер: грант предмета
  `characteristic_parameter` от `parameters.power`, лучшая база;
- общая форма `spell_source_power` с областями `any` / `innate` / `item` /
  `source` и флагом `path_scoped`;
- карточка `core-flow-optimization` (derived level по опыту волшебства) и
  ревизия 50;
- каст и поддержание читают потолок мощи источника, а не только
  характеристику;
- real-фронт: мощность источника и бонус в диалоге каста, выбор предмета
  для `scope: source` в редакторе;
- repair-скрипт персонажей без строки ядра.

Не входят:

- заёмная мощь и магические накопители (M10);
- кристалл, мана, объём, запись заклинаний (M10);
- несколько источников в одном касте (решение: один источник на каст);
- цены зон `parameter_table` на сервере — отдельный долг
  `CharacterAbilityCost`;
- `created_in_turns` и прочие поля длительности (M15).

## 2. Решения

| Вопрос | Решение |
| --- | --- |
| Где хранится мощь ядра | В строке инвентаря: `parameters.power`, плюс `grantedByRuleCode` |
| Откуда берётся значение | Из живого параметра способности-донора; сервер пересчитывает и сверяет |
| Связь предмета с донором | Явная ссылка в строке (`grantedByRuleCode`), не по коду предмета |
| Откуда `magic-power` | Из гранта предмета `characteristic_parameter` от `parameters.power` |
| Несколько ядер | База `magic-power` — лучшая среди owned-ядер; каст — мощь выбранного |
| Источники каста | Только предметы инвентаря; `characteristic:`/`grant:` отклоняются |
| Область бонуса `spell_source_power` | `any` — все; `innate` — врождённые; `item` — каждый с кодом; `source` — конкретный предмет из листа |
| Конкретный предмет для `source` | Игрок выбирает при получении способности; id пишется в `targets` выбора способности |
| Заёмная мощь | M10 вместе с накопителями |

## 3. Срезы

### 3.1. Экземпляр предмета

- `CharacterInventoryChoice`: `parameters` (размерные значения) и
  `grantedByRuleCode`.
- `CharacterChoiceAssembler`: разбор `parameters` (объект `{base,size}`
  или число) и ссылки на донора; обратная запись в документ без потерь.
- `ItemGrant`: привязка параметров экземпляра к параметрам способности:
  `parameters: {"power": "x"}`.
- При сохранении персонажа сервер находит донора по `grantedByRuleCode`,
  проверяет, что его грант предмета связывает `power`, и пересчитывает
  строку; расхождение — отказ `CHARACTER_ITEM_POWER`.
- Repair-скрипт: персонажам с трейтом без строки ядра добавить строку с
  `power` из `x` и `grantedByRuleCode`, пересчитать лист.

### 3.2. Производная `magic-power`

- Спека `magic-core` объявляет `parameters` и грант
  `characteristic_parameter` от `power`.
- `CharacterLiveBases` применяет гранты предметов с параметрами
  экземпляра; база `magic-power` — лучшая среди ядер, не сумма.
- Сразу после характеристик `CharacterSheetSourcePowers` считает
  эффективную мощь каждого источника: база экземпляра + `spell_source_power`.
  Результат — `sheet.sourcePowers`; `magic-power` поднимается до лучшего
  эффективного источника (база 4 + бонус 1 = 5).
- Каст читает `sheet.sourcePowers`, а не складывает бонус заново: один
  расчёт на лист и каст.
- Трейт «Врождённое магическое ядро» перестаёт давать `magic-power`
  напрямую (ревизия 50).

### 3.3. Форма `spell_source_power`

- DTO `SpellSourcePower` + парсер: `scope`, `path_scoped`, `amount`.
- `scope.kind`: `any`, `innate`, `item` (`item_code`), `source`
  (цель из `targets` выбора способности).
- `amount`: формула (у «Оптимизации потоков» — `ability_level`).
- `CharacterAbilityChoice` получает `targets` (`ключ → id строки
  инвентаря`), валидатор проверяет существование строки и фильтр
  `item_code`, если задан.
- Сервис сбора бонусов у кастера: живые способности → подходящие блоки →
  сумма бонусов для выбранного источника.

### 3.4. Каст и поддержание

- `GameSpellSourcePower` (или расширение `GameSpellPowerCap`): мощность
  берётся из `sheet.sourcePowers` (база + бонус формы уже сложены) →
  штраф отклонения. Если лист старый и `sourcePowers` нет — прежний путь
  «мощь экземпляра + бонус».
- `GameSpellCastFacts::usedPower` и `GameSpellPowerCap::cap` читают этот
  сервис; потолок поддержания — тот же.
- Ошибки: `Game spell source is missing`, `Game spell source has no power`,
  `Game spell used power is above the cap` (существующая).
- Ответ каста несёт мощь и бонус источника для фронта.

### 3.5. Контент

- `magic-core`: параметр `power` (`3↓`…`5↑`), грант `magic-power`.
- `magic-core-capacity`: item grant с привязкой `parameters.power = x`,
  без прямого `characteristic_parameter` в `magic-power`.
- `core-flow-optimization`: `derived_level`
  `{source_keyword: magic, thresholds: [15, 30, 50]}`, требование «есть
  магическое ядро», блок `spell_source_power` с `scope.kind: item`
  (`magic-core`), `path_scoped: true`, `amount` от уровня способности.
- Ревизия 50 — `tools/rule-migrate-50.php`; dev-БД получает её скриптом.

### 3.6. Фронт

- Редактор персонажа: покупка ядра создаёт строку `magic-core` с `power` и
  `grantedByRuleCode`; для `scope: source` — выбор предмета.
- `SpellCastSourceOption` получает `power` и `bonus`; дефолтная мощь и
  клампы считаются от источника, в диалоге видно «Ядро `4↓` (+1)».
- Предпросмотр сложности использует мощь источника.
- Миграция на новую ревизию не выдаёт предметы сама: diff показывает
  «Инвентарь: → Магическое ядро 4», отчёт получает `conflicts`, состав
  утверждается в редакторе.

## 4. Тесты

- строка инвентаря хранит `power` и ссылку на донора; сервер пересчитывает
  и отвергает чужое значение;
- `magic-power` выводится из ядра, лучшее ядро побеждает, без ядра
  характеристики нет;
- каст через ядро `4|0` и `3↓`; мощь выше потолка отклоняется;
- нет строки инвентаря — нет источника;
- `spell_source_power`: `item` действует на все предметы с кодом, `source`
  — только на выбранный, `path_scoped` не даёт бонус без пути;
- поддержание видит ту же мощь источника и бонус;
- replay не дублирует бонус;
- vitest: мощность источника, бонус, выбор предмета;
- миграция: предпросмотр выдачи (создание/подхват/идемпотентность),
  грант без строки → `conflicts` + diff и без записи, diff предмета в
  отчёте.

## 5. Журнал

| Дата | Изменение |
| --- | --- |
| 2026-11-12 | План заведён: мощь у экземпляра предмета, `magic-power` из источника, форма `spell_source_power`, заёмная мощь отложена в M10. |
| 2026-11-12 | 9.1-9.2: `CharacterInventoryChoice` получил `parameters` и `grantedByRuleCode`; `ItemGrant` связывает параметр экземпляра с параметром донора; `CharacterItemInstances` пересчитывает мощь при сохранении и пишет отказы `CHARACTER_ITEM_DONOR`/`CHARACTER_ITEM_POWER`. Лист выводит `magic-power` из гранта предмета (лучшая база среди ядер), трейт больше не даёт характеристику напрямую. |
| 2026-11-12 | 9.3: форма `spell_source_power` (области `any`/`innate`/`item`/`source`, `path_scoped`, `amount`), DTO и парсер в `AbilityBase`, `targets` в выборе способности; `GameSpellSourceBonus` собирает бонусы, включая производные уровни по опыту. |
| 2026-11-12 | 9.4: `GameSpellSourcePower` читает мощь строки инвентаря, применяет бонус и штраф отклонения; `GameSpellCastFacts` и `GameSpellPowerCap` переведены на него. Новые отказы: `Game spell source has no power`. |
| 2026-11-12 | 9.5: ревизия 50 — `magic-core` объявляет параметр `power` и грант `magic-power`; «Врождённое магическое ядро» связывает `parameters.power = x`; добавлена карточка `core-flow-optimization` (derived level 15/30/50, бонус к мощи ядра). Repair-скрипт `tools/character-repair-50.php` (в dev: 2 персонажа проверено, 0 исправлений). |
| 2026-11-12 | 9.6: фронт `sourceCapOf` берёт мощь экземпляра и бонус формы, клампы и предпросмотр используют её; `GrantedItemService` создаёт/обновляет строку ядра из гранта; список источников больше не предлагает `grant:` без предмета. |
| 2026-11-12 | Фронт подхватывает уже добавленную строку ядра без ссылки на донора и записывает её как выданную; имя источника в диалоге показывает мощь и бонус (`Магическое ядро 4 (+1)`). |
| 2026-11-12 | Тесты: `CharacterItemInstancesTest` (4), `GameSpellSourceBonusTest` (3), MySQL-тесты M9 (мощь экземпляра, бонус, предел поддержания), vitest `sourceCapOf` (4) и `GrantedItemService` (3). `composer stan`, `test:unit` (1022), `test:module character` (181), `test:module rule` (217), `test:module game` (654), полный vitest (2488) — зелёные. |
| 2026-11-13 | Миграция на ревизию 50 больше не меняет состав молча: `CharacterItemInstances::preview` показывает выдачу предметов из грантов, но не пишет; `CharacterMigration` считает лист с предпросмотром, возвращает `conflicts` с `diffs` и не сохраняет, если состав меняется. Строки без `grantedBy` больше не теряют свои `parameters`. |
| 2026-11-13 | Frontend `CharacterMigrationService` добавляет в diff выданные/потерянные предметы и появившиеся/исчезнувшие характеристики, поэтому отчёт не говорит «чистый перенос», а ведёт в редактор. |
| 2026-11-13 | Тесты после правки миграции: `CharacterItemInstancesTest` (7, включая предпросмотр), `CharacterMigrationTest` (10, грант → `conflicts` + diff без записи), vitest `characterMigration` (19), `composer stan`, `test:unit` (1029), `test:module character` (185), `test:module game` (654), полный vitest (2504) — зелёные. |
| 2026-11-13 | Проверка на реальной ревизии 50: `CharacterItemInstances::preview` выдаёт `magic-core 4`, сборка листа даёт `magic-power=4`, `becoming-arcanist=1`, `magic-control=3↓`, путь арканиста открывает `discharge=1`, problems пуст. |
| 2026-11-13 | NPC-перевод: `CharacterHttpSaveService.inventoryRow` терял `parameters` и `grantedBy` при отправке версии, поэтому сервер не выводил `magic-power` и снова падал на требованиях. Поля теперь передаются; проверка на реальном «Колдунце» (rev 49→50) с привязанным ядром даёт `kind=ok`, problems пуст, `becoming-arcanist=1`, `magic-power=4`, `magic-control=3↓`. |
| 2026-11-13 | Тесты: `characterHttpSaveService` (4, включая передачу параметров экземпляра), полный vitest (2540) — зелёные. |
| 2026-11-13 | Каст падал с `Game spell used power is above the cap`: карточка «Оптимизация потоков» в ревизии 50 была опубликована с типом правила `skill`, а `RuleSpecs` разбирает `spec` только у типа `ability`. Сервер не видел `derived_level` и бонуса `spell_source_power`, клиент видел (мощь 5 против 4). Тип правила в `tools/rule-migrate-50.php` исправлен на `ability` (spec остаётся `skill`, как у остальных навыков); в dev строка `rule_version` идемпотентно переведена в `ability`. Проверка на «Колдунце» по боевому пути (`CharacterSheets::compute` + `GameSpellSourcePower`): `core-flow-optimization=1`, потолок мощи ядра `5`. |
| 2026-11-13 | Тесты после правки: `composer stan`, `test:unit` (1039, 1 skip). |
| 2026-11-13 | Диалог каста считал стартовую мощь до загрузки каталога признаков: производный «Оптимизация потоков» не находился, бонус `spell_source_power` не применялся, и в шапке оставалось 4 вместо 5. Загрузка диалога теперь ждёт каталог признаков перед расчётом `usedPower`. |
| 2026-11-13 | `CharacterHttpViewService` терял `parameters` и `grantedBy` строк инвентаря NPC (а также `targets` способностей), поэтому источник в карточке NPC падал на общую `magic-power` и показывал «Магическое ядро» без мощи. Поля снова читаются; сохранение (`abilityRow`) передаёт `targets`. |
| 2026-11-13 | Тесты: `characterHttpViewService` (+1: мощь ядра, донор и цели формы в снимке NPC), `characterHttpSaveService` (+1: `targets` в теле), полный vitest (2555) — зелёные. |
| 2026-11-13 | Единый расчёт мощи: `CharacterSheetSourcePowers` складывает мощь экземпляра и `spell_source_power` в `sheet.sourcePowers`, поднимает `magic-power` до лучшего источника (лист показывает 5: база 4 + «Оптимизация потоков» 1). `GameSpellSourcePower` читает `sourcePowers` и не добавляет бонус повторно. Карточка NPC при чтении пересчитывает лист через `CharacterSheetEngine::build`, как и detail персонажа. |
| 2026-11-13 | Фронт: `CharacterVersion.sourcePowers` из `sheet.sourcePowers`; `SpellCastOptionsService` берёт эффективную мощь из листа; редактор считает тот же вклад `spell_source_power` через общий `SpellSourcePowerService` (лист редактора тоже 5). |
| 2026-11-13 | Тесты: `CharacterLiveComputeTest` (+1: бонус поднимает `magic-power` и `sourcePowers`), `characterEditor` (+1: лист редактора 5), `characterHttpViewService` (+1: `sourcePowers`), `spellCastDifficulty` (+1: нет повторного бонуса); `composer stan`, `test:unit` (1040), `test:module character` (186), `test:module game` (654), полный vitest (2558) — зелёные. |
| 2026-11-14 | Ручная проверка M9 в dev: карточка, редактор, detail и каст показывают «Магическая мощь 5»; база ядра `{4|0}` + «Оптимизация потоков» 1 = `{5|0}` везде. Полный `bash tools/test-full.sh` пройден: phpstan без ошибок, бэкенд 1680 (3 skip), фронтенд 2593. Срез закрыт; выбор предмета для `scope: source` отложен до первого контентного потребителя. |
