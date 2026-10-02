# Хардкод-долг конкретных кодов правил — 2026-10

Разбор production-кода Game, Character и Rule. Тесты и моки не входят.
Это список долга, не разрешение менять код и не выбор адаптера.

Связано с `DEC-REVIEW-003`, `REV-FE-006`, `REV-MAGIC-001`.

Способность и состояние живут в ревизии пространства. В соседней игре их
может не быть. Сервис, который ищет навык или состояние по конкретному коду,
этому противоречит. То, что без правила текущая ревизия развалится, строку
законной не делает.

Ожидаемая модель ресурса: сколько и какого ресурса стоит действие, написано
в правиле. У персонажа этот ресурс есть. Действие тратит его. Имя ресурса
не зашивается в generic service, если правило уже называет `resource_code`.

## Долг

### Данные уже называют код, сервис ищет свой

| Код | Место |
| --- | --- |
| `action-points` | `actionOdCost` в `Game/Utils/combatActions.ts` |
| `action-points` | `CharacterOverviewService.actionPointsCost` |
| `action-points` | `EditorAbilityRow.actionOdCostLabel`, `SpellParamsViewService` |
| `action-points` | `ActionLaunchDialog`, `AttackLaunchDialog`, `HitLaunchDialog` передают код в `ProcessSessionService.stepCost`. Сам `stepCost` код не зашивает |
| `action-points` | `AttackDamageService.actionPointsResource`, его вызывает `ActionExecutionService.execute` |
| `action-points` | `ActionExecutionService.execute` и `ActionEffectService.afterDeclaredAction`: `consumeResource(..., 'action-points')` |
| `action-points` | `SpellCastExecutionService.actionPointCost` — лимит выбранного количества |
| `dexterity` | `ActionEffectService.resolveForNextAction`: сумма `targetDexterityMasteryDelta` только при `characteristic_code === 'dexterity'`. У атаки в аргументе нет другого мастерства. `check_code` эффекта больше не сравнивается. `UnstableApplyService.apply`: `effectiveCharacteristicValues.get('dexterity')` и код характеристики помехи |
| `melee-combat`, `ranged-combat` | `EditorCharacteristicPopup`, `CombatCharacteristicPopup`: список владения оружием только для этих кодов |
| `melee-combat` | `CharacterOverviewService` (`statOf('melee-combat')`), `WeaponProficiencyService` |

Удаление `action-points` из ревизии останавливает исполнение действия
(«ОД не найдено») и обнуляет цену там, где она читается из компонентов.

### Конкретная способность, в spec поведения нет

| Код | Место |
| --- | --- |
| `magic-structure-interaction`, `basic-element-properties` | `SpellCastEfficiencyService.deltasForAbilities` |
| `dynamic-energy-saturation` | `SpellCastExecutionService.needsSaturationChoice`, `applySaturation` |
| `interstructure-energy-transfer` | `SpellCastExecutionService.pendingEffectsAfterSuccessfulCast` |
| `prikrytie` | `CoveringService.hasSkill` |
| `zaschita-znaniem` | `KnowledgeDefenseService` |
| `predelnaya-kontsentratsiya`, `sosredotochenie-voli`, `dlitelnoe-napryazhenie` | `ConcentrationTokenService`: покупка способности включает уровень жетонов |

Spec этих навыков — `skill` с пустыми `grants` либо без поля, которое
описывает эффект. Поведение сидит только в сервисе. `sourceRuleCode`
эффекта энергоперехода — подпись источника, не описание механики.

### Конкретное состояние или характеристика, куда писать результат

| Код | Место |
| --- | --- |
| `endurance` | `AttackDamageService.enduranceValueOf` / `enduranceOf`. Вызывают диалоги удара и сотворения, `EndOfTurnDotsService`, `CombatCardPanel`, `InitiativeTrack` |
| `accumulated-damage` | `AttackDamageService.accumulatedDamageOf`, запись в `HitLaunchDialog` и `SpellCastDialog`, `InitiativeTrack`, сумма в `CombatCardModelService` |
| `exhaustion`, `wound`, `stunned`, `shock` | Запись результата удара в `HitLaunchDialog` и `SpellCastDialog`. `WoundInstanceService` для `wound`. `CombatCardModelService.combatExhaustion` |
| `blood-loss`, `exhaustion` | `BloodLossService`, пороги в `CombatCardPanel` |
| `weakness`, `disabled`, `unconscious` | `ExhaustionCheckService` |
| `maim` | `CombatCardModelService.combatMaim`, `InjuryCheckService` |
| `burning`, `poisoning` | `DotTickMathService`, ветки карточки в `CombatCardPanel` и `CombatCardModelService` |
| `lying`, `unstable` | `PostureStateService`, `UnstableApplyService`, `PushResolutionService`, `ActionExecutionService` |
| `core-magic-deviation` | `SpellDeviationService` |
| `willpower` | `ExhaustionCheckService.willpowerOf`, `ConcentrationTokenService` (`meetsMinimum` и сравнение характеристики проверки), `FuriousRushService` (запасной код характеристики) |
| `fire` | `DotTickMathService.burningDot`: тип урона тика горения |
| `intellect`, `perception` | `ConcentrationTokenService.hasIntellectOrPerception` |
| `perception`, `attention`, `reaction`, `intellect`, `memory`, `reasoning`, `communication` | `CheckResolutionService`: `CONCENTRATION_TOKEN_CHARACTERISTIC_CODES`, на чьи проверки можно потратить жетон |
| `check-perception`, `check-attention`, `check-reaction`, `check-intellect`, `check-memory`, `check-reasoning`, `check-communication` | `CheckResolutionService.isConcentrationTokenCheck`: `CONCENTRATION_TOKEN_ANCESTOR_CODES` |
| `check-hit` | `CheckResolutionService.isConcentrationTokenCheck`: предок попадания открывает трату жетона |
| `perception` | `InitiativeDialog`: запасной код характеристики инициативы |

Хук типа урона называет механику (`exhaustion_wound` и соседние), не код
состояния. У яда экземпляр уже несёт `poisonRuleCode`; код состояния-носителя
всё равно зашит. Удаление `endurance` не останавливает удар: сервис
подставляет `{ base: 1, size: 0 }`.

### Ресурс и проверка ревизии без текущего действия

| Код | Место |
| --- | --- |
| `action-points` | `ElectrochargeService`, `WoundActionService`, `combatStateWrite` |
| `action-points` | `CombatCardModelService.combatActionPoints`, `LiveActionPointsLimitService.liveActionPointsLimit`, `InitiativeTrack` |
| `action-points` | `AbilitySpecService.ensureActionPointCost`, `AbilityEditor`, `ActionComponentsEditor`, `RuleValidationService.hasActionPointCost` |
| `concentration` | `ConcentrationTokenService`; запас в `CharacterEditorService` |
| `check-spell-cast` | `SpellCastService.rollForSpell`, фильтр в `SpellCastEfficiencyService.apply` |
| `magic-power`, `magic-control` | `SpellCastOptionsService.defaultUsedPower` / `defaultControl`; имена в `SpellCastDialog` |
| `simple-touch` | `SpellCastExecutionService.resolveAirTouch`, `HitLaunchDialog`, старт в `SpellCastDialog` |
| `dodge`, `block`, `turn`, `wait` | `reactionAction`, `turnAction`, `AttackDamageService.defenseApCost`, `InitiativeTrack` для `wait` |
| `check-dexterity` | `UnstableApplyService.apply` |
| `check-willpower` | `ConcentrationTokenService.isWillpowerCheck`: предок проверки |
| `recover-stability` | `PostureStateService.isRecoverStability` |
| `check-exhaustion` | `ExhaustionCheckService`, допуск траты в `ConcentrationTokenService` |
| `check-blood-clotting` | `BloodLossService`, фильтр в `CheckLaunchService` |
| `check-simple` | `CheckResolutionService.resolveCheckCodeFromRuleCode` |

Путь уже может нести `check_code` (у псионика это `check-willpower`).
Заклинание этот код не называет, `SpellCastService` путь не читает.
`reactionAction` берёт цену из `action_components`, если правило найдено;
сам код реакции spec не называет. `defenseApCost` компоненты не читает.
Список атак `simple-melee-attack` / `simple-ranged-attack` по коду не ищет:
атака отбирается по ключевому слову.

### Вход движка в карточку

`strike-procedure`, `throw-procedure`, `shoot-procedure`
(`resolveStrikeProcedure`), `injury-procedure` (`resolveInjuryProcedure`),
`roll` (`CheckResolutionService.resolveCheckEfficiency`).

Это не экземпляры способностей. Нет карточки — остаётся mechanic v1 или
fallback эффективности. В эту пачку не входят.

### Подписи

`ActionEffectLabelService`: `action-points` → «ОД», словари проверок и
характеристик. На исход не влияют. В тексте останется сырой код.

## С чего начинать

Первая пачка — два бонуса эффективности. `AbilityCheckAdvantagesService`
уже обходит `grants` любого навыка и применяет `check_advantage`, если в
гранте есть `check_codes` и `source_code`. Код способности там не
упоминается. `SpellCastEfficiencyService` делает то же числом, но только
для `basic-element-properties` и `magic-structure-interaction`, и кладёт
это в эффективность броска, не в преимущество. Это разные величины:
преимущество spec уже умеет, эффективности в грантах нет.

Правка: грант того же вида, что `check_advantage`, с полем эффективности
(`check_codes`, `amount`, `source_code`). Обход грантов один.
`deltasForAbilities` удаляется. В данные этих двух навыков дописывается
грант: `check-spell-cast`, +1 и +2, источник `mastery`. Чужое пространство
без этих навыков бонус не получит.

Вторая пачка — куда класть истощение, рану, шок и оглушение. Тип урона уже
указывает `attached_rule_codes`, хук уже читает `mechanicPayload`
(`exhaustion_wound.multiplier`). Диалог после этого пишет в константы
`WOUND_STATE_CODE`, `SHOCK_STATE_CODE`, `STUNNED_STATE_CODE`. Механика хука
остаётся типом движка. Код состояния — поле payload прикреплённой карточки.
Диалог пишет этот код и пропускает хук, если кода нет.

`dynamic-energy-saturation` и `interstructure-energy-transfer` грантом
числа не закрыть: это процедура каста. Рядом уже есть образец —
`spell_upgrade` у «Пронзающего волшебства»: сервис читает поля spec, а не
код навыка. Им нужно такое же поле на навыке. Это следующая пачка: она
затрагивает исполнение каста.

`action-points` в `actionOdCost` и в трате короче, чем шкала ОД на карточке.
Компонент уже содержит `resource_code`. Сервис суммирует и списывает
названные ресурсы. Карточка боя и редактор, которые считают ОД ресурсом
хода, когда действия в руках нет, остаются отдельно.

`endurance` и `accumulated-damage` в первые пачки не ставить. Делителя и
аккумулятора в spec нет.

## Остаток, 2026-10-02

Решения на хвост. Строки выше не зачёркиваются.

- Нет флага — путь не срабатывает. Порог, цена из `action_components` и порядок шагов остаются в сервисе.
- Бонусы эффективности и насыщение с энергопереносом не клеятся к флагам. Два отдельных захода после флаговых.
- Подписи (`ActionEffectLabelService` и словари) в этот проход не входят.
- Поиск `action-points` по `auto_add`, который уже читает код из правила, заново не планируется.

Десять заходов, по порядку. Параллельные сессии — только из разных групп ниже: внутри группы одни и те же спека и редактор.

1. Выполнен. Жетон, воля и бросок при неустойчивости не ищут проверку по константе. На карточке проверки флаги `concentration_token`, `willpower`, `unstable_check`. Жетон и воля смотрят предков: нет флага — путь не открывается. Неустойчивость берёт первую карточку с `unstable_check`; если её нет, значение всё равно растёт, броска и падения нет. В каталоге флаги на попадании, общении, семействе восприятия и интеллекта, воле и ловкости. `CONCENTRATION_TOKEN_ANCESTOR_CODES` удалён. `check-simple`, сотворение, истощение и свёртывание не менялись: код уже приходит из данных. Оставшийся прод-хардкод `check-hit` и `check-simple` дописан ниже, старые строки не зачёркнуты. Повторно не открывать.
2. Выполнен. `dodge`, `block`, `turn`, `wait`, `recover-stability`, `simple-touch` ищутся только по `combat_action` на спеке способности. Цена по-прежнему из `action_components`. Первые четыре уже работали так и не менялись. У `recover-stability` и `simple-touch` флаг дописан в моках, `RECOVER_STABILITY_CODE` удалён. Снятие неустойчивости только если у выполненного правила `combat_action === 'recover-stability'`. Стартовое касание — `defaultTouchAction` среди атак ближнего боя с ролью `simple-touch`; без флага код пустой, запасной атаки нет. Диалог сотворения только вызывает эту функцию. Строки долга не зачёркнуты. Повторно не открывать.
3. Выполнен. `prikrytie` и `zaschita-znaniem` уже включались полями `cover_ally` и `known_attack_defense`, сервисы не менялись. `predelnaya-kontsentratsiya`, `sosredotochenie-voli` и `dlitelnoe-napryazhenie` ищутся по флагам `peak_concentration`, `will_focus`, `long_tension`: первый навык с флагом, нет флага — путь не срабатывает. Пороги и «уровень + 1» остались в сервисе. Константы кодов этих трёх навыков удалены. Строки долга не зачёркнуты. Повторно не открывать.
4. Выполнен. Очки действий больше не ищутся прямым кодом на карточке, в лимите и в редакторе способности. Строки долга не зачёркнуты. Повторно не открывать.
5. Закрыт без правок. Попапы характеристики в редакторе и в бою и обзор персонажа уже читают `CharacteristicSpec.weapon_mastery`. Коды `melee-combat` и `ranged-combat` там не сравниваются. Нет поля — списка владения нет. `WeaponProficiencyService` код характеристики не ищет. Строки долга не зачёркнуты. Повторно не открывать.
6. Выполнен. Бросок при неустойчивости и запасной код инициативы не подставляют `dexterity` и `perception`. Флаги `unstable_roll` и `initiative` в каталоге на ловкости и восприятии. Поиск — `unstableRollRule` и `initiativeRule`. Нет `unstable_roll`: неустойчивость растёт, броска и падения нет, имя в чате с этой характеристики. В диалоге инициативы стартовый код — характеристика с `initiative`; нет флага — код пустой и с листа не подставляется. Если флаг есть, а на листе такой характеристики нет, берётся первая с листа. Суммы `targetDexterityMasteryDelta` в `ActionEffectService` уже нет. `DodgeSoakService` с константой ловкости дописан в долг и не менялся. Строки долга не зачёркнуты. Повторно не открывать.
7. Не нужен, код не менять. Запись раны, истощения, шока и оглушения уже идёт из `state_code` хука. Строки долга не зачёркнуты. Повторно не открывать.
8. Выполнен. `strike` / `throw` / `shoot` и `injury` берутся с первой карточки, у которой есть эта механика; в реестр идут её code и version. Нет карточки или механики — прежний v1, код не подставляется. Эффективность броска — первый payload `roll`; нет payload — прежний fallback. Код правила не ищется. Разрешение проверки не переписывалось. Строки долга не зачёркнуты. Повторно не открывать.
9. Выполнен. Эффективность сотворения идёт тем же проходом по грантам, что и преимущество проверки, отдельным результатом. `forEachApplicableGrant` один. `checkAdvantageModifiersFromAbilities` берёт только `check_advantage`. `checkEfficiencyDeltasFromAbilities` берёт только `check_efficiency` и возвращает `{ sourceCode, delta }`. Нет гранта на код проверки — дельт нет. `deltasForAbilities` удалён, `SpellCastEfficiencyService` только сдвигает грань в `apply`. Гранты `basic-element-properties` (+1) и `magic-structure-interaction` (+2) на `check-spell-cast` с источником `mastery` уже были в моках. Строки долга не зачёркнуты. Повторно не открывать.
10. Выполнен. Насыщение и энергоперенос — поля процедуры на навыке. `SpellCastExecutionService` читает `spell_saturation` и `next_cast_difficulty` и не ищет коды `dynamic-energy-saturation` и `interstructure-energy-transfer`. Нет поля — этот шаг не выполняется. Пороги и сдвиг мощи остаются в сервисе. Грант `check_efficiency` не менялся. Строки долга не зачёркнуты. Повторно не открывать.

Заходы 1–10 закрыты. Подписи по-прежнему не входят.

## Хвост после десяти заходов

Строки таблиц не зачёркивать. Нет флага — этот путь не срабатывает. Закрытые заходы 1–10 не открывать.

11. Выполнен. Пять мест из «Дописано при заходе 1» ищут первую карточку `type === 'check'` с `hit_check`. В каталоге флаг на «Проверке на попадание» рядом с `concentration_token`. Нет флага — проверка не прячется из диалога, бросок и оферта не выбирают отдельный код, пол успеха не применяется, чат не открывает диалог удара, снижение помехи не срабатывает. Пока ревизия чата не загружена, оферта не классифицируется. Пол размера успеха пишется в исход броска (`min_success_size`): вложение чата каталог не видит. Старые вложения без поля рисуются без пола. `concentration_token` не менялся. Алгоритм попадания, `HIT_MIN_SUCCESS_SIZE` и формула снижения помехи остались в сервисах. Падение `covering.test.ts` (цена 2, получено 0) — фикстура без флага боевого действия, не этот заход. Строки долга не зачёркнуты. Повторно не открывать.
12. Выполнен. `withSimpleCheckZero` больше не подставляет `check-simple`. Код берётся с первой проверки, у которой на спеке `ordinary_root`. Нет флага — исход проверки не навешивается. Сложность `{0|0}` осталась в сервисе. Процессор вложения каталога не видит и передаёт пустой список: сырой бросок в обсуждении персонажа остаётся без исхода. Игровой чат считает бросок раньше через `rollSimpleCheckZero` и не менялся. `hit_check` не переписывался. Падение `covering.test.ts` не чинилось. Строки долга не зачёркнуты. Повторно не открывать.
13. Выполнен. Поглощение уклонения читает первую характеристику с `dodge_soak`. Нет флага — характеристика не читается, поглощение 0. Формула `baseSoakValue` и срезы остались в сервисе. `CHARACTERISTIC_DEXTERITY_CODE` удалён. Флаг в каталоге на ловкости, в редакторе и на карточке. `reactionToNumber` и `CHARACTERISTIC_REACTION_CODE` не менялись, строка дописана ниже. Флаги `unstable_roll` и `initiative` не переписывались. Строки долга не зачёркнуты. Повторно не открывать.

Заходы 11–13 закрыты. Открытых заходов хвоста нет. `reaction` в `reactionToNumber` не брать.

## Дописано при заходе 1, 2026-10-02

В пачку не входило. Строки выше не зачёркнуты.

| Код | Место |
| --- | --- |
| `check-hit` | `CheckLaunchService` прячет проверку из диалога |
| `check-hit` | `HitRollService`, `HitLaunchDialog`, `SpellCastDialog` — код проверки удара |
| `check-hit` | `CheckRollService` и `DiceRollResult`: `HIT_MIN_SUCCESS_SIZE` только для этого кода |
| `check-hit` | `GameChatTab` отличает оферту удара |
| `check-hit` | `ActionEffectService`: снижение помехи |
| `check-simple` | `SimpleCheckRollService.withSimpleCheckZero`, код по умолчанию |

## Дописано при заходе 6, 2026-10-02

В пачку не входило. Строки выше не зачёркнуты.

| Код | Место |
| --- | --- |
| `dexterity` | `DodgeSoakService` читает `CHARACTERISTIC_DEXTERITY_CODE` |

## Дописано при заходе 13, 2026-10-02

В пачку не входило. Строки выше не зачёркнуты.

| Код | Место |
| --- | --- |
| `reaction` | `DodgeSoakService.reactionToNumber` читает `CHARACTERISTIC_REACTION_CODE` |
