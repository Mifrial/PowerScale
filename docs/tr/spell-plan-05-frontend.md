# План M5 — отображение и редактирование среза магии

**Статус:** DONE, 2026-09-08. Родительская нарезка — [`spell-roadmap.md`](spell-roadmap.md). Контракт — [`spell-plan-03-contract.md`](spell-plan-03-contract.md). Контент — [`spell-plan-04-import.md`](spell-plan-04-import.md). Правила фронта — [`draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md).

## Цель

Показать и править **уже импортированный** срез M4 в Rule, Character overview и RuleSpace. Runtime сотворения не входит: это отображение контента, не симуляция игры (`spell-roadmap` фаза D).

После M5:

- карточка и редактор заклинания не врут относительно M3 (мощь/контроль/duration/`hit_resolution`/ОД на сотворение);
- у типа урона в UI виден и правится `modifies_spell_difficulty`;
- редакционный `contentStatus` (`needs_work` | `ready`) виден отдельно от описания; поля `runtime-support` **нет**;
- в пространстве срез попадает в list/detail, export, preview импорта и сводку publish;
- на персонаже-фикстуре видны путь/ядро и хотя бы одно заклинание среза.

Это не новый DTO SpellSpec и не парсер HTML.

## Что не делать в M5

- `SpellCastService`, бросок сложности, выбор source/path в Game, диалог каста;
- Шок-хук на `electricity`, `apply_damage`, ActiveSpell, отклонение;
- поле `runtime-support` на `Rule` (DEC-055: отдельный контракт; M2/M3/M4 его сознательно не вводили);
- значения `contentStatus` кроме `needs_work` | `ready`; не писать `text_only` (это disposition inventory, не статус правила);
- секцию каталога «Волшебство» (M4: spell → `abilities-acquired-other`);
- PHP / persist / `vite build` / запуск dev-сервера;
- полный редактор `action_effects` / `apply_state` (его нет и у обычного action);
- HTML-парсер и новые школы.

Строка M2 «SpellEditor + диалог выбора source/path» относится к **Game** и уходит в M6. M3 уже отдал «полный UX выбора source/path в Game» в M5/M6 — в M5 остаётся только Rule/Character/RuleSpace.

Roadmap D «показывать runtime-support» выполняется так: **не смешивать** его с `contentStatus`; неисполняемые эффекты остаются в `description`. Не выдумывать статус, которого нет в DTO.

## Сверка с кодом

1. **SpellEditor уже M3.** [`SpellEditor.vue`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Component/Editors/SpellEditor.vue): мощь/контроль (DN | parameter), `hit_resolution`, четыре duration, ОД только у `refreshable`. Патчи через `AbilitySpecService`. Не переписывать контракт. Единицы предела duration захардкожены в шаблоне — вынести в `Constant/Ability/`. `AbilityEditor` на [`RuleEditPage`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Page/RuleEditPage.vue) с `:key="routeKey"` — при смене правила панель **размонтируется**; отдельный `watch` на `modelValue` не блокер. Не раздувать SpellEditor.

2. **AbilityCard уже показывает срез.** Мощь/контроль, попадание, duration, компоненты у spell, parent «Улучшение». Секция `abilities-acquired-other` — ожидаемо. `contentStatus` **не** класть в AbilityCard: деталка — [`RuleDetailPage`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Page/RuleDetailPage.vue) + [`RuleSpecView`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Component/RuleSpecView.vue); чип статуса — на странице правила, один на все типы.

3. **Overview.** [`AbilityOverview`](../../draft-front_1.2ds/src/modules/Roleplay/Character/Dto/Overview/AbilityOverview.ts) + [`AbilityTab.vue`](../../draft-front_1.2ds/src/modules/Roleplay/Character/Component/Detail/AbilityTab.vue): чипы «Сотворение» и duration есть; сложности из спеки нет. Нет чипов мощи/контроля. Подписи из `SpellSpec` считать в `CharacterOverviewService` (там уже `spellDurationLabel`), не в шаблоне. `abilitySpecService` **не** экспортирован из `Rule/init` — не импортировать внутренности Rule; `SpellValue` — Dto, ок. Не тащить `AbilitySpecService` в Character.

4. **`contentStatus` дыра и цепочка сохранения.** Поле на [`Rule`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/Rule.ts) и в revision file; [`RULE_CONTENT_STATUSES`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Constant/RULE_CONTENT_STATUSES.ts). [`RuleDiffService.samePayload`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Service/RuleDiffService.ts) уже сравнивает статус. **Недостаточно** положить селект в `RuleEditorBase`: `simple`/`source` идут через `SimpleRuleEditor`; `CatalogPlacementEditor` на edit-странице **скрыт для `ability`**. Черновик собирает [`RuleDraftService.createDraft`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Service/RuleDraftService.ts) из [`CreateDraftParams`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/CreateDraftParams.ts) / [`RuleFormState`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/RuleFormState.ts) / `ruleToForm` / `applyForm`+`save` на `RuleEditPage`. Без этих четырёх точек статус не попадёт в draft. [`CreateRuleData`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/CreateRuleData.ts) / `UpdateRuleData` / mock HTTP `createRule` статуса не имеют — в M5 **не** расширять HTTP API (черновик пространства не через них). `RuleVersion` тоже без поля — история версий не редактирует статус. Селект — на `RuleEditPage` рядом с типом, для всех типов. Карточка — чип на `RuleDetailPage`.

5. **`arcane` в UI неполный.** Флаг в DTO/`createEmpty`; в DamageType editor/card нет. Редактор уже биндит `draft.forms` / `defense_ignored` напрямую — тот же паттерн для чекбокса. Не выдумывать отдельный setter в сервисе без нужды.

6. **RuleSpace.** `isAlwaysIncluded` покрывает ability/item/characteristic/state/damage_type — срез M4 уже в ревизии mock-пространства. Export/import UI есть. M4 закрыл assemble/parse/materialize. M5: `generateRevisionRules` содержит коды среза; `RevisionFileImportService.prepare` на `assemble(slice)` при `removeMissing: false` (иначе preview «удалит» остальной каталог). Не hand-built JSON.

7. **Персонаж.** Overview читает `version.characteristics` / `abilities` / `inventory` как есть: гранты пути **не** материализуют мощь/контроль на листе. [`RacialInnateGearService`](../../draft-front_1.2ds/src/modules/Roleplay/Character/Service/RacialInnateGearService.ts) кладёт innate item **только** с automatic-грантов расы/вида, не с `magic-core-capacity`. Ядро — **явная** строка `inventory` (`magic-core`, equipped). Способности на одном персонаже: `arcanist`, `becoming-arcanist`, `magic-core-capacity` с `parameters.x` (purchase), `discharge` без activation-параметра. В `characteristics` явно `magic-power` и `magic-control` (базы как у грантов: мощь с X ядра, контроль `3↓`). Не добавлять эти характеристики всем персонажам. `osSpent` фикстур не пересчитывать под MAGIC_COSTS, если тесты бюджета не требуют.

8. **Publish.** `PublishDialog` блокирует публикацию при **любых** `summary.problems` / `spaceErrors` по всему effective-каталогу. Стерилизовать чужой каталог в M5 нельзя. Гейт: blocking по **кодам среза** пуст; preview/diff среза честный. «Нажать Опубликовать в UI на всём моке» — не критерий M5, если мешают старые ошибки.

9. **Шок.** [`StateCard`](../../draft-front_1.2ds/src/modules/Roleplay/Rule/Component/Cards/StateCard.vue) уже рисует `characteristic_modify` + `per_unit`. Не трогать, если подпись Ловкости сходится.

10. **Одна задача на SFC.** `contentStatus` не в SpellEditor. Source/path не в SpellEditor.

## Состав работ

| Поверхность | Что сделать |
| --- | --- |
| SpellEditor | Constant единиц duration; sync `modelValue` только если без `:key` реально залипает |
| RuleDetailPage | чип `contentStatus` (не AbilityCard) |
| DamageType editor/card | чекбокс/строка `modifies_spell_difficulty` |
| RuleEditPage + RuleFormState + ruleToForm + CreateDraftParams + RuleDraftService | `contentStatus`; тест `ruleDraft.test.ts` |
| CharacterOverview + AbilityTab | мощь/контроль |
| mockCharacters | один персонаж: abilities + characteristics + inventory ядра |
| RuleSpace tests | коды среза в `generateRevisionRules`; `prepare(..., { removeMissing: false })` на assemble(slice) |
| StateCard / ItemCard | не трогать без бага |

## Порядок

1. `contentStatus`: форма → draft params → createDraft → деталка. Все типы, не только RuleEditorBase.
2. Флаг `modifies_spell_difficulty` в damage type UI.
3. Constant duration units; overview power/control.
4. Character fixture + тест overview (сервис, не mount).
5. RuleSpace: slice в ревизии; preview `removeMissing: false` поверх M4.
6. Browser: detail/edit `discharge`, `lightning-generator`, `chain-lightning`, `arcane`, `magic-core`; вкладка способностей персонажа; export/import preview в пространстве. AI не стартует vite.
7. `format` → `lint` → `vue-tsc` → `npm run test`.

## Гейт M5

- Редактор/карточка spell: нет stored difficulty; есть мощь, контроль, duration (у sustained — мощь поддержания), `hit_resolution`, ОД сотворения.
- `arcane`: в карточке и редакторе виден флаг влияния на сложность.
- `contentStatus` редактируется из `RULE_CONTENT_STATUSES`; нет `runtime-support`.
- Overview персонажа-фикстуры: spell-чипы + мощь/контроль; в инвентаре `magic-core`; leftover `magic` не удалены.
- Ревизия mock-пространства содержит коды среза; preview assemble(slice) с `removeMissing: false` сохраняет `chain-lightning.parent_ability_code` и generator `sustained`/`{4|-1}`.
- Draft с изменённым `contentStatus` проходит `samePayload` как изменение.
- Нет каста, нет PHP, нет HTTP-расширения Create/UpdateRuleData, нет новой секции «Волшебство».
- Гейт фронта зелёный. UI проверен в браузере по изменённым экранам.

## Журнал

| Дата | Изменение |
| --- | --- |
| 2026-09-08 | План M5: Rule/Character/RuleSpace без Game-каста; contentStatus без runtime-support; preview поверх M4. |
| 2026-09-08 | Перепроверка: contentStatus через draft/RuleEditPage, не RuleEditorBase; ядро — явный inventory; preview без removeMissing; publish не стерилизует чужой каталог; SpellEditor `:key` уже размонтирует. |
| 2026-09-08 | M5 закрыт: contentStatus в draft/деталка, флаг arcane в UI, overview мощь/контроль, фикстура Торвина, preview среза; гейт format/lint/vue-tsc/tests. UI в браузере не гонялся (нет поднятого vite). |
| 2026-09-09 | Карточка/редактор показывают мощь поддержания отдельно от мощи сотворения. |
| 2026-09-09 | После M5: редактор персонажа — изучение по `path_code`, попап путей, `includes_path_codes`; карточка ability — `has_magic_path` / опыт пути / `spell_upgrade.check_advantage`. Game-каст не в M5. |
