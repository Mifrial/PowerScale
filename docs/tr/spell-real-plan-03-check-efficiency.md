# План M3 — эффективность проверки сотворения

**Статус:** ~ПЛАН~, 2026-11-10.

**Реализация:** ~PHP~, ~VUE~.

**Опираться на:** ~PHP~ (`Rule/Dto/Spec/Ability/CheckEfficiencyGrant`,
`Rule/Spec/AbilityGrants`, `Character/Dto/CharacterDonorGrant`,
`Character/Service/Sheet/Spec/CharacterDonorGrants`,
`Character/Service/Sheet/Spec/CharacterAbilityGrantReader`,
`Game/Service/GameCheckRoll`, `Game/Dto/GameCheckThrowOverride`,
`Game/Service/GameSpellCastFacts`, `Game/Service/GameSpellCastCheck`,
`Game/Service/GameSpellCastDamage`, `Game/Service/GameStrikeDefense`,
`Game/Dto/GameSpellCastPrepared`), ~VUE~ (`SpellCastDialog`,
`SpellCastOptionsService`, `PhpChatRollService`, `DiceRollResult`),
документы [`spell-real-roadmap.md`](spell-real-roadmap.md) (M3),
[`magic-content-design-02-arcanist-advanced.md`](magic-content-design-02-arcanist-advanced.md)
(§6), [`magic-content-design-03-arcanist-mastery.md`](magic-content-design-03-arcanist-mastery.md)
(§4), [`spell-inventory-01.md`](spell-inventory-01.md),
[`spell-review-01.md`](spell-review-01.md) (SR-102/106/108/109).

**Не основа:** `SpellCastExecutionService`, `SpellCastEfficiencyService`,
`AbilityCheckAdvantagesService`, `mockSpellImport` — ~НЕ ОСНОВА~. Это
предпросмотр/мок; real-бросок и real-контент считает сервер.

**Родитель:** [`spell-real-roadmap.md`](spell-real-roadmap.md), шаг M3.
**Связанные:** [`spell-real-plan-02-spell-upgrades.md`](spell-real-plan-02-spell-upgrades.md)
(M2), [`game-plan-46-spell-cast.md`](game-plan-46-spell-cast.md).

M2 подключил улучшения каста. M3 подключает к проверке сотворения
`check_efficiency` — бонус к порогу грани от изученных навыков. Шаг узкий:
только проверка `check-spell-cast`, без насыщения (M4), отклонения (M5) и
длительностей (M6). Срез 3.0 остаётся внутри M3: он закрывает долг M2 по
сопротивлению магии до подключения эффективности.

## 0. Срез 3.0 — долг M2: сопротивление магии

Канон (`spell-inventory-01.md` 187–189, `spell-review-01.md`
SR-102/106/108/109):

- сопротивление магии — это сопротивление к `arcane`;
- оно **понижает только арканный урон**;
- оно **повышает Сложность сотворения только целевых заклинаний**
  (`targeting: entity`);
- «Разряд» — касание (`targeting: none`), поэтому сопротивления к сложности
  он не получает;
- «Удар молнии» уже `targeting: entity`, поэтому повышение сложности от
  сопротивления магии проверяемо на нём; снижение арканного урона — когда
  появится арканное заклинание;
- одно и то же значение сопротивления используется и против арканного
  урона, и для повышения Сложности (SR-109).

Сейчас на бэке:

- `GameSpellCastCheck::difficultyResistance` требует `spell.damage` и
  смотрит на тип урона **заклинания**; `electricity` не имеет
  `modifies_spell_difficulty`, поэтому сопротивление аркане на «Удар
  молнии» не влияет;
- `GameSpellCastDamage::apply` вычитает пробитие из сопротивления **типа
  урона заклинания** независимо от того, магический это тип или нет;
- `GameSpellCastCheck` вручную собирает слои сопротивления
  (`layerResistance`/`keepLayer`), а `GameSpellCastDamage` считает через
  `GameStrikeDefense::resistanceByType`. Два расчёта одного значения могут
  разойтись.

### 0.1. Решение

1. **Новый сервис `GameSpellMagicResistance`** в `Game/Service`.
   Зависимость — `GameStrikeRules`.
   - `sumResistance(GameRecord, CharacterRuleSlice, sheet, choices): int` —
     перебирает live-правила типа `damage_type` с
     `DamageTypeSpec.modifies_spell_difficulty === true` и суммирует
     `GameStrikeDefense::resistanceByType(...)->toInteger()`.
   - `isMagicType(CharacterRuleSlice, string $damageTypeCode): bool` —
     проверяет флаг у live-типа урона.
   Один расчёт используется и сложностью, и уроном, поэтому SR-109
   выполняется по построению.
2. **`GameSpellCastCheck`.** Заменить `ICharacterCombatLayers` на
   `GameSpellMagicResistance`; `difficultyResistance` возвращает `0`, если
   `prepared->target === null`, иначе `max(0, sumResistance − max(0,
   penetration))`. Проверка `spell.damage` и `layerResistance`/`keepLayer`
   удаляются.
3. **`GameSpellCastDamage`.** Пробитие применяется только если
   `isMagicType(prepared->slice, $damage->getDamageTypeCode())`; иначе
   сопротивление типа остаётся как есть. Для `electricity` пробитие урон не
   меняет.
4. **Фронт.**
   - `SpellCastOptionsService` получает
     `targetSpellResistanceFromOverview(target, rules): number`: выбирает
     строки сопротивления, чей `damageTypeCode` входит в типы с
     `modifies_spell_difficulty`, и агрегирует их по источникам.
   - Существующий `targetResistanceFromOverview(target, damageTypeCode)`
     остаётся только как явный выбор типа там, где тип уже известен.
   - В `SpellCastDialog` вычисление `targetResistance` больше не требует
     `spell.damage` и не использует `ARCANE_DAMAGE_TYPE_CODE`; остаётся
     `hasTarget`.
   - Оставшиеся упоминания `ARCANE_DAMAGE_TYPE_CODE` в путях взрыва
     отклонения — **долг M5**, а не допустимый паттерн. M5 обязан выбрать
     тип урона из контента (рекомендуется флаг `magic_deviation` у
     `DamageTypeSpec`, ровно один тип) и удалить константу. M3 не добавляет
     новых hardcode-обходов.
5. **Фабрики.** `GameStrikeHttpFactory` и `GameStrikePortFactory` создают
   один `GameSpellMagicResistance` и передают его в `GameSpellCastCheck` и
   `GameSpellCastDamage`. Прямых unit-тестов, создающих эти классы, нет.

### 0.2. Тесты 3.0

- Цель с `magic-resistance` + «Удар молнии» → сложность выросла на `X`.
- Та же цель + `piercing-magic` → сложность выросла на `max(0, X − 2×x)`.
- «Разряд» с той же целью → сопротивления к сложности нет.
- Целевое заклинание без урона (синтетическая карточка) → сопротивление к
  сложности применяется.
- Синтетическое арканное заклинание → урон уменьшен на сопротивление
  магии; с `piercing-magic` — уменьшен меньше.
- `electricity`-урон пробитием не меняется.
- Front (`vitest`): новый метод берёт только типы с флагом, игнорирует
  `electricity`, агрегирует источники; предпросмотр не зависит от наличия
  `spell.damage`.

## 1. Границы M3

Входят:

- чтение `check_efficiency` у изученных способностей кастера (включая
  расовые гранты, если они есть);
- применимость по `check_codes`: грант действует, если список содержит
  `path.cast_check_code` («`check-spell-cast`»);
- сложение по общему правилу источников: от одного `source_code` —
  сильнейший плюс и сильнейший минус, между источниками — сумма;
- применение к эффективности проверки сотворения: база `default_efficiency`
  проверки или `{3|0}`, затем `CharacteristicNumber::modify(delta)`;
- передача эффективности в серверный бросок и возврат её в результате
  (`efficiency`, `efficiencyAdjustments`);
- показ в деталях броска (чат/ответ) без нового UI;
- контент: явный `permanent: true` у двух грантов `check_efficiency`
  (новая ревизия);
- тесты PHP и фронта.

Не входят:

- `spell_saturation` и `next_cast_difficulty` (M4);
- `magic_deviation` (M5);
- `check_efficiency` для других проверок как отдельная миграция: сервис
  должен быть пригоден для повторного использования, но в M3 подключается
  только каст;
- `check_advantage` и `spell_upgrade` — уже M2;
- новые таблицы, вторая формула проверки, клиентский расчёт как авторитет;
- изменение глобального дефолта `permanent` в PHP-парсере;
- изменение глобального сложения сопротивлений боевого конвейера;
- удаление `ARCANE_DAMAGE_TYPE_CODE` из путей взрыва отклонения — это
  M5; в M3 константа убирается только из расчёта сложности и предпросмотра.

## 2. Что уже есть

| Место | Что делает | Чего не делает |
| --- | --- | --- |
| `Rule/Dto/Spec/Ability/CheckEfficiencyGrant.php` | хранит `amount`, `check_codes`, `source_code`, `permanent` | не решает применимость |
| `Rule/Spec/AbilityGrants::efficiency` | разбирает грант; отсутствие `permanent` даёт `false` | не знает контентного намерения |
| `CharacterDonorGrants` / `CharacterAbilityGrantReader` | отдают гранты выбранных и расовых способностей по уровню | не различают тип гранта, не агрегируют, DTO не несёт уровень блока |
| `GameCheckRoll` | `default_efficiency`/payload, `GameCheckThrowOverride.efficiency`, бросок | не читает гранты; результат не содержит пары `efficiency` |
| `GameCheckThrowOverride` | `efficiency` как int | нет размера эффективности; `size` — только размер пула при `diceCount` |
| `GameSpellCastCheck` | сложность, сопротивление, бросок | сопротивление считается по типу урона заклинания, без эффективности грантов |
| `GameSpellCastDamage` | урон, сопротивление, пробитие | пробитие применяется к любому типу урона |
| `GameSpellCastPrepared` | все факты каста | нет эффективности проверки |
| `SpellCastExecutionService` (мок) | `checkEfficiencyDeltasFromAbilities` + `SpellCastEfficiencyService.apply` | ~НЕ ОСНОВА~ |
| `PhpChatRollService`, `DiceRollResult` | уже читают `efficiency` и `efficiencyAdjustments` из payload | ждут эти поля от сервера |
| Контент | `basic-element-properties` `+1`, `magic-structure-interaction` `+2`, `check_codes: [check-spell-cast]`, `source_code: mastery` | гранты не имеют `permanent` — исправляется в 3.1 |

## 3. Решения

1. **База и явность.** Эффективность — размерное число с базой 3–5. Старт —
   `CheckSpec.default_efficiency` прямой карточки проверки, если задан,
   иначе `{3|0}`. Бонусы применяются через `CharacteristicNumber::modify`,
   без отдельного числового потолка: база сама переходит на следующий размер
   после 5. Это закрывает открытый вопрос роудмапа №7.
   `GameCheckEfficiencyResolution` хранит признак `explicit`: он истинен, если
   `default_efficiency` задан или итоговая дельта не нулевая. Если признак
   ложен, `GameSpellCastCheck` **не** передаёт `efficiency` в override, чтобы
   не отменить payload-дефолт правила «Бросок». Если признак истинен, порог
   и размер передаются явно.
2. **Источники.** Группировка по `source_code`; если он пуст — по коду
   способности-донора (`grant.source_code ?? donorCode`). В группе остаются
   сильнейший плюс и сильнейший минус, нули отбрасываются. Сумма групп —
   итоговая дельта. Это зеркало `AggregateSourceDeltasService` и
   `GameStrikeModifiers::netSourceDeltas`; отдельный общий PHP-сервис в M3 не
   выделяется, чтобы не менять боевые модификаторы.
   `source_label` — имя способности-донора из среза, иначе `source_code`.
3. **Применимость.** Уровень способности `>= 1`; блок гранта не выше
   купленного уровня. `permanent: true` действует на последующих уровнях;
   `permanent: false` — только на уровне блока (`rule-system.md`). В
   `CharacterDonorGrant` добавляется уровень блока (обратно совместимое
   поле), заполняется в `CharacterDonorGrants::rows`; `GameCheckEfficiency`
   отбрасывает `permanent: false`, если уровень блока не равен купленному.
   Для расовых способностей купленного уровня нет: `CharacterAbilityGrantReader`
   отдаёт все блоки, поэтому их гранты считаются действующими. Это
   фиксируется тестом и не создаёт отдельной ветки под контентный код.
4. **Размер эффективности.** Если `modify` ушёл за базу 5, размер
   эффективности входит в `dieSize` броска, как в `GameCheckRoll::rollMastery`
   и `DiceRollSpec` («Scoring: dieSize = poolSize + efficiencySize»). Для
   этого `GameCheckThrowOverride` получает `efficiencySize`, а
   `GameCheckRoll::rollPool` прибавляет его к `pool['size']`. Порог остаётся
   `efficiency.base`. Для контентных `+1/+2` размер равен 0; `+3` даёт
   `{3|1}`, а не `{6|0}`.
5. **Отображение.** `GameCheckRoll::thrown` добавляет в результат
   `efficiency: {base, size}`: база — финальная из `RollResult` (после
   payload/механик), размер — из переданной эффективности или override.
   `GameSpellCastCheck` добавляет `efficiencyAdjustments` из
   `GameCheckEfficiencyResolution` (только ненулевые вклады).
   `GameChecks::VIEW_KEYS` уже содержит оба ключа; `PhpChatRollService` и
   `DiceRollResult` уже умеют их показывать. Сервер остаётся авторитетом.
6. **Где считать.** Новый доменный сервис `GameCheckEfficiency` в
   `Game/Service`:
   `resolve(CharacterRuleSlice $slice, CharacterChoices $model,
   array $abilityLevels, ?string $checkCode): GameCheckEfficiencyResolution`.
   Внутри — `CharacterAbilityGrantReader` и `GameCheckChain` (для
   `default_efficiency`). `GameSpellCastFacts` вызывает его для
   `path.cast_check_code` и кладёт результат в `GameSpellCastPrepared`;
   `GameSpellCastCheck` передаёт эффективность в `GameCheckThrowOverride` и
   добавляет поправки в результат. Это не даёт `GameCheckRoll` знать
   Character и сохраняет одну точку расчёта.
7. **Код карточки не зашивается.** Ни `basic-element-properties`, ни
   `magic-structure-interaction`, ни `mastery`, ни `arcane` не встречаются в
   Game. Единственный фильтр — `check_codes`, форма гранта и флаг
   `modifies_spell_difficulty`. Оставшийся `ARCANE_DAMAGE_TYPE_CODE` во
   фронте — долг M5: тип взрыва отклонения должен приходить из спеки, а не
   из константы.
8. **Сопротивление магии — один расчёт.** `GameSpellMagicResistance` не
   дублирует формулы слоёв, а вызывает боевой
   `GameStrikeDefense::resistanceByType`. `GameStrikeLayers::collapse` уже
   выполняет правило источника: в одном `source` остаются сильнейший плюс и
   сильнейший минус, разные `source` складываются. Поэтому это тот же
   контракт, а не второе правило; сложность и урон не расходятся.

## 4. Срезы

| Срез | Что сделать | Результат |
| --- | --- | --- |
| 3.0 | `GameSpellMagicResistance`; сложность только по `prepared->target`; пробитие только магии; фронт по флагу | M2 закрыт по смыслу |
| 3.1 | Миграция контента `permanent: true`; `GameCheckEfficiency`, DTO результата, уровень блока в `CharacterDonorGrant`; unit-тесты | сервер умеет посчитать бонус без броска |
| 3.2 | `GameSpellCastFacts`/`GameSpellCastPrepared`/`GameSpellCastCheck`, `GameCheckThrowOverride.efficiencySize`, `GameCheckRoll::rollPool` | бросок каста идёт с эффективностью |
| 3.3 | `efficiency` в `GameCheckRoll::thrown`, `efficiencyAdjustments` в касте; проверка фронта | детали броска показывают эффективность |
| 3.4 | Тесты, документация, роудмап, карты | гейт шага |

## 5. Контент

Ревизия 46 хранит у `basic-element-properties` и
`magic-structure-interaction` гранты `check_efficiency` без `permanent`.
Фронт трактует отсутствие поля как `true` (`permanent !== false`), а
PHP-парсер `AbilityGrants::one` — как `false` (`SpecShape::bool`). По
`rule-system.md` контент обязан задавать поле явно.

Срез 3.1:

- добавить `tools/rule-migrate-47.php`: из ревизии 46 выставить
  `permanent: true` только у грантов `check_efficiency` этих двух карточек,
  остальные версии сохранить, опубликовать ревизию 47;
- dry-run, затем `--commit`;
- переключить dev-игру на ревизию 47 (иначе real-проверка останется на 46);
- мок-каталог `mockSpellImport` не является основой; при желании обновить
  его для согласованности предпросмотра, но доказательством контента он не
  считается.

## 6. Точки проверки

| Вход | Где | Что делает |
| --- | --- | --- |
| тип урона с флагом | `GameSpellMagicResistance::isMagicType` | решает, магический ли тип |
| сопротивление магии | `GameSpellMagicResistance::sumResistance` | сумма по боевому расчёту |
| цель каста | `GameSpellCastCheck::difficultyResistance` | только `prepared->target`, без `spell.damage` |
| пробитие | `GameSpellCastDamage::apply` | только для магического типа урона |
| грант `check_efficiency` | `CharacterDonorGrants`/`CharacterAbilityGrantReader` | отбор по уровню способности и блоку |
| каст | `GameSpellCastFacts::prepare` | эффективность для `path.cast_check_code` |
| бросок | `GameSpellCastCheck` | `GameCheckThrowOverride.efficiency`/`efficiencySize`, только при `explicit` |
| результат | `GameCheckRoll::thrown`, `GameSpellCastCheck` | `efficiency`, `efficiencyAdjustments` в `roll` |
| чат | `GameSpellCastChat` | передаёт `roll` как есть |
| фронт | `PhpChatRollService`, `DiceRollResult` | отображение без нового расчёта |

## 7. Тесты

Unit:

- `GameCheckEfficiencyTest`:
  - нет грантов — `{3|0}`, `explicit = false`;
  - `+1` — `{4|0}`, `explicit = true`, одна запись `mastery`/`+1`;
  - `+2` — `{5|0}`;
  - два гранта с `source_code: mastery` (`+1` и `+2`) — только `+2`;
  - два гранта с разными источниками — сумма;
  - грант без `source_code` — отдельная группа на каждую запись;
  - грант с чужим `check_codes` — не применяется;
  - `permanent: false` на уровне 1 и способность уровня 2 — не
    применяется; `permanent: true` — применяется;
  - синтетический грант с произвольным кодом и той же формой — работает;
    карточка с «контентным» кодом без `check_efficiency` — не работает;
  - `+3` — `{3|1}`, размер уходит в `efficiencySize`, порог остаётся
    базовым;
  - `default_efficiency = 4` без грантов — `{4|0}`, `explicit = true`.
- `GameSpellMagicResistanceTest` или покрытие в MySQL-тесте:
  - тип с флагом и без флага;
  - сумма нескольких флаговых типов;
  - отсутствие live-типа не роняет расчёт сложности.

MySQL (`GameStrikeMysqlTest` или отдельный тест):

- каст с грантом `check_codes: ['cast-check']` `+1`:
  `roll.efficiency = {4|0}`, `efficiencyAdjustments` содержит источник и
  `+1`;
- каст с обоими грантами: `{5|0}`, в поправках только `+2`;
- каст без навыка: `{3|0}` или payload-дефолт, поправок нет;
- «Разряд»/«Удар молнии» — одинаково, если грант про check code пути;
- цель с `magic-resistance` + «Удар молнии» → сложность `+X`;
- та же цель + `piercing-magic` → `max(0, X − 2×x)`;
- «Разряд» с `magic-resistance` → без сопротивления к сложности;
- синтетическое арканное заклинание → урон уменьшен на сопротивление;
  пробитие снижает только магическое сопротивление;
- `electricity`-урон пробитием не меняется;
- replay и гость без навыка не получают бонус.

Front (`vitest`):

- `targetSpellResistanceFromOverview` берёт только флаговые типы и
  агрегирует источники;
- `targetResistance` в `SpellCastDialog` не зависит от `spell.damage` и не
  использует `ARCANE_DAMAGE_TYPE_CODE`;
- существующие `PhpChatRollService`/`DiceRollResult` принимают
  `efficiency` и `efficiencyAdjustments` без изменений;
- если поле пришло как пара, отображается итоговая эффективность и строки
  источников.

Гейт шага:

- `composer stan`;
- `composer test:unit`;
- `composer test:module game`;
- `composer test:module character` — если затронут Character;
- `npx vitest run` по затронутым файлам;
- `composer docs-check`, `composer maps-check`;
- полный `bash tools/test-full.sh` — только по подтверждению пользователя.

## 8. Риски и открытые вопросы

1. **`permanent` в контенте.** Правится ревизией 47 (срез 3.1). До
   публикации и переключения игры real-каст на уровне 2 остаётся на старом
   поведении. Глобальный дефолт парсера не меняется: `rule-system.md`
   требует явного поля.
2. **Уровень блока в `CharacterDonorGrant`.** Добавляется обратно
   совместимое поле и заполняется в `CharacterDonorGrants::rows`; отдельная
   ветка чтения блоков в Game не нужна. Для расовых грантов уровень блока
   не сравнивается с купленным — их блоки считаются действующими.
3. **`default_efficiency` и цепочка проверок.** `GameCheckRoll` и
   `GameCheckEfficiency` берут `default_efficiency` только у прямой
   карточки, без предков. Для `check-spell-cast` поле не задано; в M3
   повторяется текущее поведение. Цепочка — отдельный шаг.
4. **Размер эффективности.** Контент даёт максимум `+2`, поэтому переход
   за 5 сейчас недостижим. Поле `efficiencySize` добавляется, чтобы не
   закреплять ошибку «размер потерян»; тест на `+3` — синтетический.
5. **Общая механика проверок.** M3 подключает сервис только к касту.
   Перенос на все проверки (удары, процессы) — отдельное решение: он
   изменит существующие броски и требует своих тестов.
6. **Источник без `source_code`.** Если грант не задаёт источник, группы
   не схлопываются: каждая запись считается отдельным источником, как в
   `AggregateSourceDeltasService`. Проверяется тестом.
7. **Расовые гранты.** `CharacterAbilityGrantReader` включает расовые
   способности; если у расы появится `check_efficiency`, он заработает без
   отдельной ветки. Поведение `permanent` для них фиксируется тестом.
8. **Real-проверка `piercing-magic`.** Сложность от сопротивления магии
   проверяется на «Ударе молнии» (`targeting: entity`) при цели с
   `magic-resistance`; снижение арканного урона — только когда появится
   арканное целевое заклинание. До этого тест — на фикстуре; real-проверка
   остаётся открытым пунктом.
9. **Payload-дефолт эффективности.** Если грантов и `default_efficiency`
   нет, `GameCheckEfficiency` возвращает нейтральные `{3|0}` с
   `explicit = false`, и правило «Бросок» может подставить свой дефолт.
   Если `default_efficiency` задан явно (даже 3), значение передаётся как
   явное, как и раньше в `GameCheckRoll`.
10. **Hardcode `ARCANE_DAMAGE_TYPE_CODE`.** В M3 константа убирается из
    расчёта сложности и предпросмотра. В путях взрыва отклонения она
    остаётся долгом M5: нужен контентный маркер типа урона (рекомендуется
    флаг `magic_deviation` у `DamageTypeSpec`) и удаление константы.
    Оправданием для нового hardcode это не является.

## 9. Журнал

| Дата | Изменение |
| --- | --- |
| 2026-11-10 | План заведён: эффективность проверки сотворения по `check_efficiency`, агрегация по источникам, `efficiency`/`efficiencyAdjustments` в результате; долг M2 по сопротивлению магии вынесен в 3.0. |
| 2026-11-10 | Перепроверка по коду: 3.0 остаётся внутри M3; сопротивление считается через `GameStrikeDefense` (`GameStrikeLayers::collapse` уже группирует по `source`: лучший плюс и худший минус, между источниками — сумма); фронт получает отдельный метод для флаговых типов; `ARCANE_DAMAGE_TYPE_CODE` убирается из сложности, остаток во взрыве — долг M5 с контентным маркером; `permanent` правится миграцией 47; уточнены `explicit`-эффективность, размер `+3 → {3|1}`, уровень блока и гейт `composer maps-check`. |
