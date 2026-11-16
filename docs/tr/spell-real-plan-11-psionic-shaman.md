# План M11–M12 — Псионик и Шаман

**Статус:** ~СДЕЛАНО~, 2026-11-16. Код срезов 11.1–11.3 и 12.1–12.3
готов: спека предела активных волшебств, проверка на сервере и в диалоге
каста, агрегат Духовности в листе и редакторе, потолок изучения формулой,
включение псионика Шаманом при касте. Миграция 55 опубликована в dev-БД
(ревизия 55), игра переведена, ручная проверка в бою пройдена, полный
прогон зелёный: phpstan без ошибок, бэкенд 1789 (2 skip), фронтенд 2765.
Псионик и Шаман уже есть
в контенте ревизии 54 (пути, проверки, характеристики, карточки
`psionic-awakening`, `psionic-control`, `otherworldly-contact`), но три
правила пути не исполнялись: «одно активное волшебство» Псионика, формула
Духовности и асимметричное включение псионика Шаманом при касте. Плюс
потолок изучения Шамана был зафиксирован числом 3 вместо расчёта от
Духовности. План закрывает эти правила через спеку, без хардкода кодов
путей и карточек в механике.

**Реализация:** ~PHP~, ~VUE~. Мок-режим (`mockSpellImport`,
`mockGameCombatOverlays`, мок-сервисы каста) — допустимая замена для
редактора и стенда, но не контракт: форму правки можно менять, конечное
поведение задаёт спека и сервер.

**Опираться на:** ~PHP~ (`MagicPathSpec`, `MagicPathSpecs`,
`RootSpecs::characteristic`, `CharacteristicSpec`,
`CharacterLiveCharacteristics`, `CharacterStudyPaths`, `CharacterPathStudy`,
`MagicStudyGrant`, `FormulaEvaluations`, `GameSpellCastFacts`,
`GameSpellCastUpgrades`, `GameActiveSpellGuard`, `GameActiveSpellCreate`,
`GameActiveSpellRepository`), ~VUE~ (`MagicPathSpecService`,
`FormulaEvaluationService`, `CharacterEditorService`,
`MagicStudyUnlockService`, `SpellCastOptionsService`,
`SpellExecutionService`, `SpellCastUpgradeService`, `ActiveSpellService`,
`SpellCastDialog`), контент (`psionic`, `shaman`, `psionic-awakening`,
`psionic-control`, `otherworldly-contact`, `spirituality`,
`check-willpower`, `check-spirituality`).

**Родитель:** [`spell-real-roadmap.md`](spell-real-roadmap.md), шаги M11 и
M12. Канон контента — [`spell-inventory-01.md`](spell-inventory-01.md),
[`spell-review-01.md`](spell-review-01.md) (SR-025, SR-067…SR-071),
[`spell-roadmap.md`](spell-roadmap.md) §12.

## 1. Границы

Входит:

- предел активных волшебств как поле спеки пути и серверная проверка при
  создании поддерживаемого эффекта;
- Духовность как агрегат трёх характеристик, описанный в спеке
  характеристики, с пересчётом в листе и в редакторе;
- потолок изучения Шамана формулой от Духовности (узел скалярной формулы);
- включение псионика Шаманом при касте: экземпляр на включённом пути
  доступен через включающий путь, улучшения проверяются так же;
- фронт: видимость предела активных волшебств, выбор пути для
  псионического заклинания у Шамана, вклады Духовности в попапе
  характеристики;
- контентная миграция на ревизию 55 и тесты обеих полос.

Не входит:

- новые эффекты заклинаний и школы, кроме уже существующих карточек;
- «Пробуждение псионических сил» как исполняемая механика: карточка
  прямо говорит, что немедленное сотворение и риск отданы игроку и
  мастеру; бесплатное изучение одной карточки уже выражено грантом
  `magic_study` (`paid_cost: 0`, `max_instances: 1`);
- формы общения (речь/музыка/танец) и `will-incarnation` — у них нет
  исходного текста и механики в репозитории (см. §6);
- мутации от Арканного урона и прочие хвосты роудмапа.

## 2. Что уже есть

- Пути `psionic` (`check-willpower`, Контроль и мощь через
  `magic-power`/`magic-control`) и `shaman` (`check-spirituality`,
  `includes_path_codes: ["psionic"]`); обе карточки `magic_path`.
- Проверки `check-willpower` (характеристика `willpower`) и
  `check-spirituality` (характеристика `spirituality`).
- Карточки: `psionic-awakening` (0 ОР, мощь ≥ 4↓, грант изучения ≤ 1 на
  Псионике за 0 ОР), `psionic-control` (2 ОР, Контроль 3↓, изучение ≤ 1),
  `otherworldly-contact` (2 ОР, путь Шамана, Духовность 3↑, изучение ≤ 3;
  требования — путь Псионика, 6 опыта псионика, Воля/Внимательность/
  Общение ≥ 5).
- Включения путей уже работают в Character: покрытие грантов изучения
  (`CharacterGrantCoverage`), опыт пути (`CharacterStudyPaths::covered`),
  доменные требования `has_ability` (`AbilityRequirementCheck`).
- M6 создаёт `game_active_spell` только для `sustained`, запрещает второй
  эффект на тот же источник и хранит `pathCode` строки; «одно активное
  волшебство на кастера» сознательно отложено до M11.

## 3. Решения

### 3.1. Предел активных волшебств — поле спеки пути

Новое необязательное поле `MagicPathSpec.active_spell_limit` (целое > 0
или null). `null` — ограничения нет. Смысл: сколько строк
`game_active_spell` кастер может держать одновременно, независимо от
источника.

- Спека — единственный источник ограничения; коды путей в механике не
  упоминаются. Контент Псионика ставит `1`.
- Проверка на сервере — при создании поддерживаемого эффекта
  (`GameActiveSpellCreate::fromCast`), потому что только там известна
  длительность. Мгновенные заклинания лимит не проверяют.
- Проверка считает все активные строки кастера в бою, не только строки
  этого пути: «держать» — свойство кастера.
- Ранний отказ до трат повторяет M6: `GameSpellCast::work` проверяет
  лимит, если длительность карточки `sustained`.
- Репозиторий получает счётчик строк кастера; `GameActiveSpellGuard`
  получает `assertCasterLimit`.
- Фронт: `activeSpellLimit` в `SpellCastPathOption`;
  `ActiveSpellService.isCasterLimitReached` блокирует подтверждение каста
  с сообщением «путь держит только N активных волшебств».
- Редактор пути: числовое поле «Предел активных волшебств (0 — нет)».

### 3.2. Духовность — агрегат в спеке характеристики

`CharacteristicSpec` получает необязательный блок `aggregate`:

```json
{
  "base": {"base": 3, "size": 1},
  "sources": ["communication", "willpower", "attention"],
  "reference": {"base": 5, "size": 0},
  "points_per_step": 2,
  "step": 1
}
```

Правила расчёта (SR-025):

- вклад источника — `toInteger(значение) − toInteger(reference)`;
- вклады складываются, результат `floor(сумма / points_per_step) × step`
  сдвигает базу через `CharacteristicNumber.modify` (шкала 3–5, шаг 3);
- при отрицательной сумме округление вниз (`floor`), чтобы недобор был
  строже;
- агрегат считается после `derived` (min/max) и до потолков снаряжения;
- характеристика без базы (нет гранта/покупки) агрегатом не появляется:
  Духовность даёт только «Потусторонний контакт»;
- база агрегата авторитетна; грант характеристики задаёт ту же пару,
  чтобы лист не расходился с формулой.

Лист отдаёт готовое значение в `sheet.characteristics`, как сейчас. Фронт
редактора повторяет расчёт (`CharacterEditorService`) и показывает в
попапе вклады: «Общение +3 + Воля +2 + Внимательность +2 = 7 →
Духовность {3|2}». Подписи вкладов — из `sources`, без хардкода кодов.

### 3.3. Потолок изучения Шамана — формула от Духовности

`magic_study.max_cost` уже принимает `ScalarFormula`. Добавляется узел
`characteristic_floor_div`:

```json
{"type": "characteristic_floor_div", "characteristic_code": "spirituality", "divisor": 2}
```

Он возвращает `floor(toInteger(характеристика) / divisor)` — «уменьшить на
один размер, затем привести к простому числу» (размер — это ×2).
`otherworldly-contact` заменяет `max_cost: 3` на этот узел; при
Духовности `{3|1}` потолок остаётся 3, при `{3|2}` — 6.

Узел реализуется в PHP (`FormulaEvaluations`) и на фронте
(`FormulaEvaluationService.evaluateScalar`), плюс `readsCharacteristics`.

### 3.4. Шаман включает псионика при касте

Канон SR-067…SR-071: экземпляр, изученный на Псионике, кастуется через
Шамана без повторного изучения; обратное неверно; при каждом касте путь
выбирается, для псионического заклинания у Шамана подставляется Шаман.

- Сервер: `GameSpellCastFacts::assertInstance` принимает экземпляр, путь
  которого покрыт выбранным путём (транзитивно, через
  `CharacterStudyPaths::covered`). Приоритет: экземпляр точно на
  выбранном пути; иначе единственный покрытый экземпляр; несколько
  покрытых — отказ (неоднозначность).
- `GameSpellCastUpgrades::assertApplicable` проверяет домен экземпляра
  тем же покрытием, а не равенством.
- Фронт: `isSpellAvailableForUse` требует экземпляр заклинания, чей домен
  покрыт выбранным путём; `listApplicable` улучшений — так же.
- Фронт: при выборе заклинания подставляется путь-владелец; если
  заклинание изучено на включённом пути, подставляется включающий путь
  (Шаман), но игрок может переключиться на Псионика.
- `listPaths` отдаёт `includesPathCodes`, чтобы подстановка и проверки
  работали без повторного чтения правил в компоненте.

### 3.5. Пробуждение и формы общения

`psionic-awakening` остаётся контентным грантом бесплатного изучения;
немедленный каст и повышенная Сложность без `psionic-control` — по
карточке, за игроком и мастером. Механика не добавляется, пока карточка
сама это исключает.

`will-incarnation` и `communication-form` не добавляются: в репозитории
нет ни исходного текста, ни согласованной механики (см. §6).

## 4. Срезы

### 11.1. Спека предела и контент

- `MagicPathSpec.active_spell_limit` + `MagicPathSpecs` + TS DTO и
  `MagicPathSpecService.resolve`/`createEmpty`.
- Редактор пути: поле предела.
- Миграция 55: `psionic.active_spell_limit = 1`; мок-контент — то же.

### 11.2. Сервер: одно активное волшебство

- `GameActiveSpellRepository::countForCaster`.
- `GameActiveSpellGuard::assertCasterLimit`.
- `GameActiveSpellCreate::fromCast` и ранний отказ в
  `GameSpellCast::work` для `sustained`.
- Тест MySQL: два поддерживаемых заклинания с разных источников у
  Псионика — второй отказ; у Арканиста/Шамана — проходит; мгновенное
  второго не блокирует.

### 11.3. Фронт: предел в диалоге каста

- `activeSpellLimit` в опциях пути, `isCasterLimitReached`.
- Блокировка подтверждения и сообщение; vitest сервиса.

### 12.1. Спека агрегата и расчёт

- DTO `CharacteristicAggregate`, разбор в `RootSpecs`, параметр
  `CharacteristicSpec`.
- `CharacterLiveCharacteristics::aggregated` после `derived`.
- `CharacterEditorService`: тот же расчёт и вклады для попапа.
- Миграция 55: `spirituality.aggregate`.
- Тесты: PHP `CharacterLiveComputeTest` (рост и недобор, отсутствие без
  гранта), vitest `characterEditor`.

### 12.2. Узел `characteristic_floor_div`

- DTO, `Formulas::scalarNode`, `FormulaEvaluations`, TS тип и
  `evaluateScalar`, `readsCharacteristics`.
- Миграция 55: `otherworldly-contact.magic_study.max_cost`.
- Тесты: PHP `MagicStudyGrant`/`CharacterPathStudy`, TS
  `formulaEvaluation`.

### 12.3. Шаман кастует псионика

- Сервер: покрытие домена в `GameSpellCastFacts` и
  `GameSpellCastUpgrades`.
- Фронт: покрытие в `isSpellAvailableForUse` и `listApplicable`,
  подстановка пути в `SpellCastDialog`.
- Тесты: MySQL — каст псионического заклинания через Шамана с проверкой
  Духовности; отказ Псионику кастовать шаманское; улучшение с доменом
  псионика через Шамана; vitest — выбор пути и доступность.

### 12.4. Документы и ручная проверка

- Обновить роудмап M11/M12, карточки модулей, индекс документации.
- Ручная проверка: Шаман/Псионик в редакторе (Духовность, потолок
  изучения), два поддерживаемых у Псионика в бою, псионическое
  заклинание через Шамана.

## 5. Тесты

- PHP: `MagicPathSpecTest` (поле), `CharacterLiveComputeTest` (агрегат),
  `CharacterPathStudyTest` (потолок-формула), `GameSpellCastUpgradesTest`
  (включение), `GameStrikeMysqlTest` (лимит и каст через Шамана).
- TS: `magicPathSpec`, `formulaEvaluation`, `characterEditor`,
  `spellCastDifficulty`/`spellCastUpgrade`, `activeSpell`.
- Гейт итерации: `composer stan`, `composer test:unit`, затронутый
  MySQL-тест, точечный `vitest`; полный прогон — после ручной проверки.

## 6. Открытые вопросы

1. **Карточки без исходников.** `will-incarnation` («Открывает
   заклинания стоимости до 2») и `communication-form` («Выбор одной
   формы: общение, музицирование или танец») есть только в inventory,
   без описаний и механики. Решение 2026-11-15: делать только карточки,
   чей текст есть в ревизии базы данных; эти две не добавляются.
2. **Немедленный каст пробуждения.** Карточка отдаёт его мастеру.
   Решение 2026-11-15: механика не добавляется; бесплатное изучение
   остаётся грантом `magic_study`.

## 7. Журнал

| Дата | Изменение |
| --- | --- |
| 2026-11-15 | План заведён: предел активных волшебств, агрегат Духовности, потолок изучения формулой, включение псионика Шаманом. Пробуждение и формы общения оставлены контенту (§6). |
| 2026-11-15 | 11.1–11.3: `MagicPathSpec.active_spell_limit`, поле в редакторе пути, счётчик активных строк кастера, проверка в `GameActiveSpellCreate` и ранний отказ в `GameSpellCast`/`GameSpellTouchFacts`, блокировка в диалоге каста. Тесты: `MagicPathSpecTest`, `ActiveSpellService`, `GameStrikeMysqlTest` (предел и путь без предела). |
| 2026-11-15 | 12.1: `CharacteristicSpec.aggregate` (база, источники, опора, шаг), расчёт в `CharacterLiveCharacteristics::aggregated` после формул и в `CharacterEditorService`; попап характеристики показывает вклады, сумму и шаг. Тесты: `CharacterLiveComputeTest` (+2), `characterEditor` (+1). |
| 2026-11-15 | 12.2: узел `characteristic_floor_div` в PHP и Vue, проверка каталога и подпись формулы; `otherworldly-contact.max_cost` считается от Духовности. Тесты: `FormulaEvaluationTest`, `CharacterPathStudyTest`, `formulaEvaluation`. |
| 2026-11-15 | 12.3: `assertInstance` и проверка улучшений принимают экземпляр на включённом пути; фронт требует такой экземпляр для выбранного пути и подставляет включающий путь (Шаман) по умолчанию. Тесты: `GameSpellCastUpgradesTest` (+2), `spellCastDifficulty` (+2), MySQL — каст псионического через Шамана и отказ обратного. |
| 2026-11-16 | Миграция 55 опубликована в dev-БД (ревизия 55, 3 карточки, 748 без изменений). Коммит кода и документов — `0caa3bf7`. |
| 2026-11-16 | M11 и M12 закрыты: игра переведена на ревизию 55, ручная проверка в бою пройдена, полный `tools/test-full.sh` зелёный (static 0, бэкенд 1789/2 skip, фронтенд 2765). Открытые вопросы §6 остаются решениями по контенту, а не долгом кода. |
