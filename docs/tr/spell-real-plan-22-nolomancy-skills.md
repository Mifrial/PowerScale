# План M22 — навыки Ноломансии: опыт, мастерство, дальность и постоянство

**Статус:** ~ЧАСТИЧНО~, 2026-11-17. Ревизия 68 выгрузила пять навыков школы;
«Опытный ноломант», «Контроль нервов», «Проклятие взглядом» и постоянные
состояния «Разложения нервов» работают. Намеренное снятие состояний,
задержка «Преобразования нервов», скидка и «Потоп» — следующие срезы.

**Реализация:** ~PHP~, ~VUE~, контент ревизии 68.

**Опираться на:** ~PHP~ (`SpellUpgrade`, `SpellUpgradeRange`,
`AbilityCombat::spellUpgrade`, `GameSpellCastUpgrades`, `GameSpellCastFacts`,
`GameActiveEffectCreate`, `GameActiveEffectTick`, `AbilityRequirements`,
`AbilityRequirementCheck`, `CharacterKeywordExperience`,
`CharacterRequirementFacts`), ~VUE~ (`SpellCastUpgradeService`,
`SpellCastDialog`, `SpellCastUpgradeList`, `SpellCastDifficultyService`,
`RequirementEvaluator`, `CharacterEditorService`,
`RequirementNodeEditor`, `AbilityCard`, `ActiveEffectService`).

**Родитель:** [`spell-real-roadmap.md`](spell-real-roadmap.md), шаг M22.
**Связанные:** [`spell-nolomancy-design-01.md`](spell-nolomancy-design-01.md)
§8 и §10, [`spell-real-plan-18-nolomancy.md`](spell-real-plan-18-nolomancy.md),
[`spell-real-plan-21-magic-resistance.md`](spell-real-plan-21-magic-resistance.md).

## 1. Границы

Входит:

- опыт по признакам как требование карточки;
- ограничение улучшения каста признаками заклинания;
- общий источник преимущества у нескольких улучшений;
- дальность вместо касания;
- постоянное состояние после срока заклинания.

Не входит:

- источники и переносчики как отдельная модель («Закрепление в организме»,
  «Заразность», «Закрепление магии», «Инкубационный период») — отброшены;
- «Болезнетворчество» — конструктор из трёх составляющих отменён, эффект
  остаётся полноценным заклинанием с касанием и созданием;
- намеренное снятие постоянных состояний и задержка «через ход»;
- числовые улучшения эффектов (усиление силы/срока) и «Потоп»;
- «Опытный электромант» — карточка электромансии, отдельная выгрузка.

## 2. Решения

1. **Опыт признаков — общая механика.** Требование
   `keyword_experience {keyword_codes, min}` суммирует цены взятых
   способностей со **всеми** перечисленными признаками:
   `["nolomancy"]` — опыт ноломансии, `["nolomancy","nervous-system"]` —
   опыт ноломансии нервной системы. Отдельного поля «опыт школы» нет.
2. **Признаки улучшения.** `spell_upgrade.keyword_codes` — улучшение
   применимо, только если заклинание имеет все эти признаки. Сервер
   отказывает до списания ОД; фронт фильтрует список по тем же признакам.
3. **Один источник преимущества.** `spell_upgrade.check_advantage_source`
   подменяет код источника в `RollAdvantage`. «Опытный ноломант» (+1) и
   «Контроль нервов» (+2) идут источником `mastery`: на заклинании нервной
   системы действует сильнейшее +2, а не сумма +3.
4. **Дальность вместо касания.** `spell_upgrade.range` с
   `power_size_per_step` и `control_base`. Значение улучшения — N ипари:
   Требуемая мощь растёт на `N × power_size_per_step` размеров, Требуемый
   контроль — на `control_base` пунктов. Каст с дальностью принимает цель
   даже у `targeting: none`; касание в том же касте запрещено.
5. **Постоянное состояние.** `spell_upgrade.state_permanence` создаёт
   строку `game_active_effect` без срока (`turns_total = turns_left = 0`),
   тик её пропускает: эффект заклинания заканчивается, состояние остаётся.
6. **«Дистанция» стала «Проклятием взглядом».** Отдельного переносчика
   «Дистанция» нет: это улучшение любого заклинания ноломансии.

## 3. Срезы

### 22.1. Карточки школы — сделано (ревизия 68)

| Код | Карта | ОР | Механика |
| --- | --- | --- | --- |
| `experienced-nolomant` | Опытный ноломант | 1 | +1 преимущество, источник `mastery`; требует 15 опыта ноломансии |
| `nerve-control` | Контроль нервов | 1 | +2 преимущества, тот же источник; требует «Опытного ноломанта» и навык нервной системы |
| `gaze-curse` | Проклятие взглядом | 1 | дальность N ипари вместо касания |
| `nerve-decay` | Разложение нервов | 4 | постоянные состояния блокировки нервной системы |
| `nerve-transformation` | Преобразование нервов | 6 | постоянные состояния; снятие и «через ход» — нет |

Каталог: новая секция «Ноломансия» под «Волшебством» (`abilities-acquired-magic-nolomancy`).

### 22.2. Осталось

- «Опытный электромант» — без него требование «Контроля нервов» неполное;
- снятие постоянного состояния и задержка «Преобразования нервов»;
- скидка 2 на «Преобразование нервов» при «Разложении нервов»;
- «Потоп» — объединение двух эффектов в один каст;
- улучшения эффектов («Нестерпимый зуд», «Резкая боль», «Наружу»,
  «Слабоумие», «Частичный паралич», «Зональное/Тотальное разложение»).

## 4. Проверки

- unit: `AbilityRequirementCheckTest::testKeywordExperience` — опыт по одному
  и нескольким признакам, отсутствие модели;
- unit: `GameSpellCastUpgradesTest::testUpgradeKeywordFilter`,
  `testAdvantageSourceIsShared`, `testRangeUpgrade`, `testStatePermanence`;
- mysql: `GameStrikeMysqlTest::testGazeCurseCastsNolomancySpellAtRange` —
  целевой каст касательного заклинания на расстоянии;
- mysql: `testNolomancySpellRejectsTargetWithoutRange` — без улучшения цель
  запрещена;
- mysql: `testNerveDecayKeepsStateAfterExpiry` — строка без срока, состояние
  остаётся после конца хода цели;
- фронт: `spellCastUpgrade.test.ts` (дальность, источник преимущества),
  `requirementEvaluator.test.ts` (опыт признаков);
- `composer stan`, `composer test:unit`, `GameStrikeMysqlTest`,
  `GameEndOfTurnMysqlTest` + `GameNpcMysqlTest` + `GameWideStrikeMysqlTest`,
  фронтовые тесты редактора и каста.

## 5. Журнал

| Дата | Изменение |
| --- | --- |
| 2026-11-17 | Ревизия 68: пять навыков школы; требование `keyword_experience`; `spell_upgrade.keyword_codes`, `check_advantage_source`, `range`, `state_permanence`; «Дистанция» заменена «Проклятием взглядом». «Болезнетворчество» и источники отброшены. |
