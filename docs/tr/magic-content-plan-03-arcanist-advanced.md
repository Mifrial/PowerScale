# План реализации магии 03 — углублённое образование Арканиста

**Статус:** `READY`, 2026-09-25.  
**Дизайн:** [`magic-content-design-02-arcanist-advanced.md`](magic-content-design-02-arcanist-advanced.md).  
**Секция:** `Арканист → Образование → Углублённое`.

Исполнять срезы по порядку. Не переходить к следующему, пока проверки текущего зелёные. Тексты описаний копировать из дизайна, не пересказывать.

Не делать в этом плане:

- накопление часов анализа и запрет повтора этапа;
- автоподстановку сложности `check-magic-analysis`;
- временное чувство на время поддержания `magic-perception`;
- боевое распознавание знакомого волшебства;
- глобальную миграцию всех `type: 'check'` из `basic-checks`;
- скрытие всех способностей без зон покупки, кроме явно пустого `zones` у анализа.

## 0. Общие факты кода

- Покупаемые навыки магии живут в `draft-front_1.2ds/src/modules/Roleplay/Rule/Mock/mockSpellImport.ts`. Новый id брать из `nextId++`, он начинается с `9400`.
- `spellRule()` в этом файле всегда ставит секцию электромансии и признак `227`. Для `magic-perception` его не вызывать. Собирать правило через локальную `rule()`.
- Признаки в этом файле: `SKILL_KEYWORD = 13`, `MAGIC_KEYWORD = 3`, `ACTION_KEYWORD = 14`, `SPELL_KEYWORD = 16`, `METHOD_INTELLECT_KEYWORD = 57`, `MAGIC_PATH_KEYWORD = 228`, `ARCANIST_KEYWORD = 229`.
- Стоимость ОР: `zones: orCost(n)`.
- Явная `catalogSection` на правиле побеждает миграцию: `MockRuleCatalogMigrationService.migrateRules` пишет `rule.catalogSection ?? sectionForRule(...)`.
- Каталог тестов — `ruleCatalog` из `mockRules.ts`. Он уже включает `mockSpellImport` и `mockChecks`.
- Проверка требований — `RequirementEvaluator`. Снимок `CharacterSnapshot` содержит `abilityLevels` и `abilityKeywords`. Типа способности в снимке нет.
- Грант `{ type: 'ability', ability_code, level }` уже поднимает уровень через `CharacterEditorService.giftedAbilityLevels`. ОР за этот уровень не списываются.
- Бросок сотворения: `SpellCastService.rollForSpell` → `CharacteristicRollService.characteristicRollSpec` → `CheckRollService.rollNamedCheck`. Эффективность ставит `characteristicRollSpec`, не `namedCheckSpec`.
- Урон заклинания читает `SpellCastExecutionInput.parameterPower` в `SpellCastExecutionService.spellDamageAmount`.
- Диапазон базы размерного числа характеристик: `CHARACTERISTIC_BASE_RANGE` = `{ min: 3, max: 5 }` в `Rule/Value/CharacteristicNumber.ts`. Сдвиг с переносом размера — `DimensionalNumber.modify(delta, CHARACTERISTIC_BASE_RANGE)`.

## 1. Срез A — разворачиваемый блок описания

### 1.1 Санитайзер

Файл `draft-front_1.2ds/src/modules/Core/UI/Constant/Description/DESCRIPTION_HTML_CONFIG.ts`.

В `ALLOWED_TAGS` добавить `details`, `summary`, `div`. `ALLOWED_ATTR` не расширять: остаются `class`, `colspan`, `data-rule-code`, `rowspan`.

### 1.2 Тест санитайзера

Файл `draft-front_1.2ds/src/modules/Core/UI/__tests__/Service/descriptionHtmlSanitizer.test.ts`.

Добавить проверку:

- вход с `details.description-expanded-block`, `summary`, двумя `span` и `div.description-expanded-block__body` сохраняется;
- `onclick` и тег `script` по-прежнему удаляются.

### 1.3 Отображение

Файл `draft-front_1.2ds/src/modules/Core/UI/Component/DescriptionHtml.vue`.

В `<style scoped>` добавить правила для:

- `.description-expanded-block` — рамка и вертикальный отступ;
- `.description-expanded-block__header` — строка, курсор pointer;
- `.description-expanded-block__title` — слева;
- `.description-expanded-block__difficulty` — справа;
- `.description-expanded-block__body` — отступ внутри.

Блок по умолчанию закрыт: у `details` не ставить атрибут `open`.

### 1.4 Редактор

Файл `draft-front_1.2ds/src/modules/Roleplay/Rule/Component/Editors/RuleDescriptionEditor.vue`.

StarterKit не описывает `details` / `summary`. Добавить один TipTap node, например `DescriptionExpandedBlockExtension.ts` рядом с `DescriptionMarkExtension.ts` в `Core/UI/Service/Description/`.

Node:

- имя `descriptionExpandedBlock`;
- группа `block`;
- содержимое: заголовок и тело;
- `parseHTML` читает `details.description-expanded-block`;
- `renderHTML` пишет ту же разметку, что в разделе 1.2.

В тулбар редактора добавить кнопку «Блок». Она вставляет пустой блок с заголовком «Вопрос», правой подписью «Базовая Сложность» и одним абзацем в теле.

Игровую логику в `.vue` не переносить.

### 1.5 Проверка среза

```bash
npx vitest run src/modules/Core/UI/__tests__/Service/descriptionHtmlSanitizer.test.ts
```

Затем `npm run format` и `npm run lint` в `draft-front_1.2ds`.

## 2. Срез B — признаки и секция проверок

### 2.1 Признаки

Файл `draft-front_1.2ds/src/modules/Roleplay/Keyword/Mock/mockKeywords.ts`.

Последний id сейчас `232`. Добавить:

- id `233`, code `sense`, name `Чувство`;
- id `234`, code `activity`, name `Занятие`.

### 2.2 Существующие чувства

Файл `draft-front_1.2ds/src/modules/Roleplay/Rule/Mock/mockRuleImport.ts`.

У правил `sense-hearing` и `sense-vision` в `keywordIds` добавить `233`. Другие правила `type: 'sense'` в этом файле обработать так же.

### 2.3 Секция

Файл `draft-front_1.2ds/src/modules/Roleplay/RuleSpace/Mock/mockAbilitySectionTree.ts`.

Рядом с `magic-rules-casting` добавить:

```text
code: magic-rules-checks
name: Проверки
parentCode: magic-rules
sortOrder: 60
```

Не вкладывать её в `magic-rules-casting`.

### 2.4 Существующая проверка сотворения

Файл `draft-front_1.2ds/src/modules/Roleplay/Rule/Mock/mockChecks.ts`.

У правила `check-spell-cast` поставить `catalogSection: 'magic-rules-checks'`. Спеку не менять.

`MockRuleCatalogMigrationService` не менять. Ветка `if (rule.type === 'check') return 'basic-checks'` остаётся запасной для проверок без явной секции.

## 3. Срез C — требование составных структур

### 3.1 Тип требования

Файл `draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/Ability/Requirement.ts`.

У варианта `has_ability_keyword` добавить необязательное поле:

```ts
exclude_keyword_codes?: string[]
```

Поле `exclude_ability_types` не добавлять: снимок не хранит тип способности.

### 3.2 Оценка

Файл `draft-front_1.2ds/src/modules/Roleplay/Character/Service/RequirementEvaluator.ts`, ветка `has_ability_keyword`.

Считать способность только если:

- её уровень больше `0`;
- у неё есть `requirement.keyword_code`;
- ни один код из `exclude_keyword_codes` не входит в её набор признаков.

Пустой или отсутствующий `exclude_keyword_codes` означает прежнее поведение.

### 3.3 Тест

Файл `draft-front_1.2ds/src/modules/Roleplay/Character/__tests__/Service/requirementEvaluator.test.ts`.

Добавить случай: две способности с признаком `magic`; у одной также есть `spell`. Требование `keyword_code: 'magic'`, `min_count: 1`, `exclude_keyword_codes: ['spell', 'magic-path']` истинно. Если осталась только способность с `spell`, требование ложно.

## 4. Срез D — карточки

Описания брать дословно из дизайна.

Подсказка в HTML — `<span class="description-hint">...</span>`. Маркеры `#Подсказка` не писать.

### 4.1 Составные структуры волшебства

В `mockSpellImport.ts` добавить ability `composite-magic-structures`.

- Имя: `Составные структуры волшебства`.
- Секция: `abilities-acquired-magic-paths-arcanist-education-advanced`.
- `zones: orCost(2)`.
- Признаки: `13`, `3`, `57`, `228`, `229`.
- Требования уровня 1:
  - `{ type: 'has_ability', ability_code: 'structure-substitution', min_level: 1 }`;
  - `{ type: 'magic_path_experience', path_code: 'arcanist', min: 10 }`;
  - `{ type: 'has_ability_keyword', keyword_code: 'magic', min_count: 1, exclude_keyword_codes: ['spell', 'magic-path'] }`.
- Гранты уровня 1, оба:

```ts
{ type: 'magic_study', scope: 'spell', max_cost: 4, path_code: 'arcanist' }
{ type: 'magic_study', scope: 'non_spell', max_cost: 4, path_code: 'arcanist' }
```

`becoming-arcanist` и `structure-substitution` не менять.

Описание и подсказка — раздел 3.2 дизайна.

### 4.2 Ощущение магии

Это не ability. Добавить в `mockRuleImport.ts` рядом со `sense-vision`.

- code `magic-sensation`;
- type `sense`;
- имя `Ощущение магии`;
- секция `basic-senses`;
- spec `{ type: 'sense', status: 'imprecise', radius: { base: 0, size: 0 } }`;
- признаки `233` и `3`;
- описание — цитата из раздела 2.1.1 дизайна.

Поле предела постижения не добавлять ни в `SenseSpec`, ни в `CharacterSenseValue`.

Радиус `0` — заглушка карточки. Боевое поддержание его не читает.

### 4.3 Восприятие магии

В `mockSpellImport.ts` через `rule()`, не через `spellRule()`.

- code `magic-perception`;
- type `ability`;
- имя `Восприятие магии`;
- секция `abilities-acquired-magic-spells-other`;
- признаки `13`, `3`, `14`, `16`;
- `zones: orCost(2)`;
- spec type `spell`;
- `spell.power`: `{ base: 4, size: -1 }`;
- `spell.control`: `{ base: 4, size: 0 }`;
- `spell.duration`: `{ type: 'sustained', power: { base: 4, size: -1 } }`.

Отдельного поля «минимум Контроля поддержания» в `SpellDuration` нет. Не выдумывать его. Число остаётся в тексте описания.

Компонент действия ОД — фиксированное число, как у существующих заклинаний. Фразы «стоимость равна лимиту ОД персонажа» в спеке нет. В `action_components` поставить ресурс `action-points` с `label: 'Сотворение'`. Число ОД для этой карточки взять равным одному ходу в текстовом описании; в спеке записать `amount`, которое показывает карточка создания. Не добавлять новый вид компонента.

Описание — раздел 2.2 дизайна. Ссылка на чувство: `<a data-rule-code="magic-sensation">Ощущение магии</a>`.

`hit_resolution`: `{ type: 'none' }`. Урона нет.

### 4.4 Обзор магических структур

В `mockSpellImport.ts`.

- code `magic-structure-overview`;
- имя `Обзор магических структур`;
- секция углублённого образования;
- `zones: orCost(2)`;
- признаки `13`, `3`, `229`;
- требования уровня 1:
  - `has_ability` `composite-magic-structures`;
  - `has_ability` `matematika`;
  - `has_ability` `magic-perception`;
- грант `{ type: 'ability', ability_code: 'magic-structure-analysis', level: 1 }`.

Описание и подсказка — раздел 4.1 дизайна. Ссылки: `magic-sensation`, `magic-structure-analysis`.

### 4.5 Анализ магических структур

В `mockSpellImport.ts`.

- code `magic-structure-analysis`;
- имя `Анализ магических структур`;
- секция та же, `abilities-acquired-magic-paths-arcanist-education-advanced`, чтобы правило открывалось в каталоге;
- `zones: {}`;
- признаки `13`, `3`, `229`, `234`;
- требований и грантов нет;
- description — HTML из раздела 4.2.1 дизайна целиком, включая все `details`.

Файл `CharacterEditorService.ts`, метод `buildAbilities`.

Сразу после проверки `spec.type === 'group'` добавить: если `spec.zones` пустой объект, `continue`. В текущих моках пустых `zones` нет, поэтому существующие строки редактора не исчезнут. Анализ не показывается как покупка. Уровень для требований всё равно приходит из гранта обзора через `giftedAbilityLevels`.

Не фильтровать способности по секции и не вводить флаг «непокупаемый».

### 4.6 Проверка анализа

Файл `draft-front_1.2ds/src/modules/Roleplay/Rule/Constant/Check/CHECK_CODES.ts`.

Добавить константу `CHECK_MAGIC_ANALYSIS_CODE = 'check-magic-analysis'`.

Файл `mockChecks.ts`. Добавить правило по образцу `checkRule`:

- code `check-magic-analysis`;
- имя `Проверка анализа волшебства`;
- spec: `type: 'check'`, `parent_check_code: 'check-simple'`, `characteristic_code: 'intellect'`, `difficulty_input: { kind: 'ask' }`, `allowed_modes: 'solo'`;
- `catalogSection: 'magic-rules-checks'`.

В Game кнопку запуска этой проверки не добавлять. В бою нет общего списка проверок. Карточка нужна как правило каталога.

### 4.7 Динамическое энергонасыщение

В `mockSpellImport.ts`.

- code `dynamic-energy-saturation`;
- имя `Динамическое энергонасыщение`;
- секция углублённого образования;
- `zones: orCost(2)`;
- признаки `13`, `3`, `57`, `228`, `229`;
- требования: `composite-magic-structures` и `{ type: 'magic_path_experience', path_code: 'arcanist', min: 15 }`;
- `grants: []`.

Описание и подсказка — раздел 5.3 дизайна.

### 4.8 Свойства базовых элементов

В `mockSpellImport.ts`.

- code `basic-element-properties`;
- имя `Свойства базовых элементов`;
- секция углублённого образования;
- `zones: orCost(3)`;
- признаки те же, что у энергонасыщения;
- требования: `composite-magic-structures` и опыт `arcanist` не меньше `20`;
- `grants: []`.

Описание и подсказка — раздел 6.3 дизайна.

### 4.9 Тесты карточек

Файл `draft-front_1.2ds/src/modules/Roleplay/Rule/__tests__/Mock/mockSpellImport.test.ts`.

`byCode` уже построен по `ruleCatalog`, поэтому проверка из `mockChecks` там видна. Добавить ожидания:

- секции четырёх покупаемых навыков и `magic-perception`;
- `magic-sensation.catalogSection === 'basic-senses'` и `status === 'imprecise'`;
- у составных структур ровно два `magic_study` с `max_cost: 4` и разными `scope`;
- у обзора грант `ability` на `magic-structure-analysis`;
- у анализа `zones` пустой и description содержит `description-expanded-block`;
- `check-magic-analysis.catalogSection === 'magic-rules-checks'`;
- `check-spell-cast.catalogSection === 'magic-rules-checks'`.

Коды новых правил добавить в `SLICE_CODES`, если тест требует присутствия всего списка.

Запуск:

```bash
npx vitest run src/modules/Roleplay/Rule/__tests__/Mock/mockSpellImport.test.ts src/modules/Roleplay/Character/__tests__/Service/requirementEvaluator.test.ts
```

## 5. Срез E — эффективность сотворения

### 5.1 Где менять

Не менять `CheckRollService.namedCheckSpec`. Сотворение до него уже несёт готовую спецификацию.

Файл `draft-front_1.2ds/src/modules/Roleplay/Game/Service/CharacteristicRollService.ts`, метод `characteristicRollSpec`.

Сейчас он ставит:

```ts
efficiency: defaults.efficiency
efficiencySize: 0
```

Этого метода недостаточно, чтобы отличить сотворение от другой проверки: сюда не приходит код проверки. Поэтому бонус применять в `SpellCastService.rollForSpell` сразу после `characteristicRollSpec` и до `rollNamedCheck`.

### 5.2 Сервис бонуса

Новый класс `SpellCastEfficiencyService` в `Game/Service/`. Синглтон — `Game/Service/Instance/spellCastEfficiencyService.ts`.

Метод принимает базовую спецификацию броска, код проверки и список модификаторов `{ sourceCode: string; delta: number }`.

Алгоритм:

1. Если код проверки не `check-spell-cast`, вернуть спецификацию без изменений.
2. Пропустить дельты через `aggregateSourceDeltasService.aggregateSourceDeltas`. От одного `sourceCode` остаётся наибольший плюс и наибольший минус. Результаты разных источников складываются через `netSourceDelta`.
3. База — `{ base: spec.efficiency, size: spec.efficiencySize ?? 0 }`.
4. Применить сумму через `new DimensionalNumber(base).modify(sum, CHARACTERISTIC_BASE_RANGE)`.
5. Записать `efficiency` и `efficiencySize` из результата.

Для `basic-element-properties` модификатор один: `{ sourceCode: 'mastery', delta: 1 }`, если у заклинателя есть эта способность уровня больше `0`. Способность читать из `SpellCastExecutionInput.casterAbilities`, не из каталога правил.

`SpellCastService.rollForSpell` сам список способностей сейчас не получает. Добавить аргумент `efficiencyDeltas` и передавать его из `SpellCastExecutionService`, где `casterAbilities` уже есть. Диалог дельту не считает.

### 5.3 Тесты

Рядом с `spellCastDifficulty.test.ts` или отдельным файлом сервиса:

- база `3`, один бонус `mastery +1` → эффективность `4`, размер `0`;
- два бонуса `mastery +1` → всё ещё `4`;
- `mastery +1` и другой источник `+1` → база сдвигается на `2`;
- код проверки не `check-spell-cast` → спецификация не меняется;
- персонаж без `basic-element-properties` → дельта не создаётся.

## 6. Срез F — динамическое энергонасыщение

Сейчас `execute` и `completeAfterHit` бросают проверку и сразу считают урон. Между броском и уроном выбора нет. Касание доходит до броска сотворения только в `completeAfterHit`, его вызывает `HitLaunchDialog.finishSpellAfterHit`.

### 6.1 Поле ввода

Файл `Game/Dto/Spell/SpellCastExecutionInput.ts`.

Добавить `saturationSteps?: number`. Отсутствие поля значит `0`.

### 6.2 Расчёт в сервисе

Новый метод класса `SpellCastExecutionService`, приватный, имя `applySaturation`.

Вызывать его в `execute` и в `completeAfterHit` сразу после `rollForSpell` и до `spellDamageAmount` / `pendingEffectsAfterSuccessfulCast`.

Вход: input, результат броска. Выход: число шагов, которое реально применено, и Мощь после сдвига.

Правила:

1. Шаги — целое `x`. Отрицательное или дробное отвергать.
2. Способность есть, если в `casterAbilities` есть `dynamic-energy-saturation` с `level > 0`.
3. Без успешной проверки `cast.roll.check.passed` допустимо только `x = 0`.
4. РУ — `cast.roll.check.rating`. Потратить можно не больше этого числа. Условие: `2 * x <= rating`. Иначе метод сигналит об ошибке, урон не применяется.
5. Остаток РУ для энергоперехода: `rating - 2 * x`. `pendingEffectsAfterSuccessfulCast` сравнивает с порогом `2` этот остаток, не исходный `rating`.
6. Мощь эффекта: `new DimensionalNumber(input.parameterPower).modify(x, CHARACTERISTIC_BASE_RANGE).value`. Именно это значение передать в расчёт урона вместо исходного `parameterPower`. `spellDamageAmount` сейчас читает `input.parameterPower`; передать ему уже сдвинутое значение аргументом, не мутировать input в компоненте.
7. Повторного броска нет.

`interstructure-energy-transfer` больше не читает сырой `cast.roll.check.rating` для порога. Сигнатуру `pendingEffectsAfterSuccessfulCast` расширить аргументом `remainingRating`.

### 6.3 Интерфейс

Игрок выбирает `x` после броска и до урона. Один вызов `execute`, который сам бросает и сразу бьёт, этого не позволяет.

Сделать так:

1. Вынести бросок в метод `rollCast` сервиса исполнения. Он вызывает существующий `spellCastService.rollForSpell` и ничего не тратит и не наносит.
2. `execute` и `completeAfterHit` получают необязательный уже сделанный бросок. Если он передан, `rollForSpell` внутри них не вызывается.
3. `SpellCastDialog.vue` после подготовки input вызывает `rollCast`. Если проверка успешна и способность есть, показывает число шагов. Затем вызывает `execute` с этим броском и `saturationSteps`. ОД по-прежнему списывает текущий `persistSpentAp` после `execute`.
4. `HitLaunchDialog.vue`, функция `finishSpellAfterHit`: перед `completeAfterHit` получить бросок через `rollCast`, показать тот же выбор, затем передать бросок и `saturationSteps` в `completeAfterHit`.

Компонент только хранит введённое число и передаёт его в сервис. Проверку `2 * x <= rating` делает сервис.

Если бросок не нужен (`needsCheck === false`), выбор насыщения не показывать.

### 6.4 Тесты

Файл `draft-front_1.2ds/src/modules/Roleplay/Game/__tests__/Service/spellCastExecution.test.ts`.

- 4 РУ, `x = 1`: Мощь сдвинута на `+1`, остаток 2, pending энергоперехода создаётся;
- 4 РУ, `x = 2`: Мощь сдвинута на `+2`, остаток 0, pending не создаётся;
- `x = 3` при 4 РУ: ошибка, урон не применён;
- провал проверки и `x > 0`: ошибка;
- нет способности и `x > 0`: ошибка;
- касание использует тот же `applySaturation` через `completeAfterHit`.

## 7. Закрытие

Из каталога `draft-front_1.2ds`:

```bash
npm run format
npm run lint
npx vue-tsc --noEmit
npm run test
```

Полный прогон — один раз в конце, не после каждого среза. После каждого среза достаточно точечного `vitest` из этого плана.
