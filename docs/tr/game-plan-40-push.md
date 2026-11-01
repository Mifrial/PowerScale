# План 40 — Толчок и общая проверка прироста

**Статус:** ~СДЕЛАНО~

**Реализация:** ~PHP~, ~VUE~, ~МОК~

**Опираться на:** ~PHP~, ~МОК~

## Границы

- `simple-push` — обычный Толчок руками или щитом, 3 ОД. Оружие без щита
  сервер отклоняет.
- Толчок не делает бросок попадания: `ignore` — Сила против пола, `block` —
  совместная Сила, `dodge` — обычный `check-hit`, при промахе толчок сорван.
- Пол толчка — `weight.modify(-3)`: вес минус один размер. Он не равен полу
  попадания.
- Успех: база дробящего урона — сырые успехи Силы минус один размер шкалы
  (`modify(-3)`, то есть `{2|2}` → `{2|1}`), множитель — `appliedSr = РУ`
  (не 1), `Неустойчивость += РУ`, отброс `⌊РУ / 4⌋` строкой в чат, только если
  РУ ≥ 4.
- `РУ <= 0` — нет урона, Неустойчивости и отброса.
- Проверка прироста Неустойчивости — общая `GameStateCheck`; провал кладёт
  лежачее, лежачий подавляет Неустойчивость.
- `tolkayuschiy-udar` — следующий срез, план 41: пул урона оружия, база урона —
  урон оружия, рубящий делитель позы.

## Механика

Толчок — один контест и одна формула урона. РУ толчка и РУ попадания — разные
величины. Меняются пул и база:

```text
pushSR = РУ проверки на толчок (не бросок попадания)
hitSR  = РУ попадания

base =
  simple-push:        сырые успехи Силы − один размер шкалы (modify(-3))
  tolkayuschiy-udar:  урон профиля оружия

injury = max(0, (base − сопротивление) × min(hitSR, потолок типа урона) − смягчение уклона)

posture =
  simple-push:        pushSR
  tolkayuschiy-udar:  ⌊pushSR / делитель⌋
```

- `ignore` — пул против пола `weight.modify(-3)`, без броска попадания.
  `hitSR = pushSR`.
- `block` — совместная проверка: атакующий бросает свой пул, защитник — Силу;
  успехи защитника поднимаются до пола. Успешный блок оставляет контакт 1 РУ
  независимо от успехов атакующего; защита блока входит в сопротивление.
  `hitSR = 1`.
- `dodge` — сначала `check-hit`; при промахе толчок сорван, иначе контест.
  `hitSR` — РУ проверки на попадание, `pushSR` — РУ проверки на толчок.
- Сопротивление — слои защиты цели; при блоке входит защита щита; пробитие
  оружия уменьшает защиту. Смягчение уклона вычитается после умножения.
- `posture` режет только отброс и Неустойчивость. Урон умножается на `hitSR`
  с потолком типа урона.
- Отброс — `⌊posture / 4⌋`; при `posture < 4` не пишется.

## Форма

```text
simple-push.push:
  pool           = "strength"
  damage         = "crush_from_strength"
  profiles       = "hands_or_shield"
  block_profiles = "hands_or_shield"

exhaustion.increase_check_code = "check-exhaustion"
```

Блок против толчка читает `block_profiles`: руки (`innate`) и щит проходят,
меч — нет. У Толкающего удара своё значение — `slashing_or_blunt_strike`, потому
что это удар, который толкает, а не Толчок.

## Что сделано

- `GameStateCheck` — общий бросок проверки прироста: связка из спеки, сложность
  из значения после прироста, первый подходящий исход.
- `GameStateDecline` — общая шкала слабость/недееспособность/бессознательность:
  снятие старых строк, повышение только вверх, проверка без броска при уже
  максимальном ранге.
- `GameStrikeConsequences::will()` / `willForDelta()` переведены на
  `GameStateCheck` + `GameStateDecline`; `GameStateIncreaseChecks::plan()` умеет
  и `putState`, и движение шкалы.
- `GamePushRules` — пол, контест пула (Сила или урон оружия), база урона,
  рейтинг позы, отброс, поиск Неустойчивости. Отброс не пишется, если
  `posture < 4`.
- `GameCheckRoll` — размер успехов берётся из пула проверки, а не из
  эффективности: `{5|2}` бросается как 5 кубов размера 2.
- `GameStrikeRules` — проверка профиля толчка и блока против толчка, тип урона
  профиля щита, характеристика листа и поиск проверки по характеристике.
- `GameStrikeSingleClose` — ветка Толчка: реакции, сила/блок, урон, запись
  Неустойчивости и проверка прироста, один пакет операций. Множитель
  повреждения — РУ контеста для обоих толчков; у Толкающего удара база — урон
  оружия, пробитие и износ прочности как у обычного удара.
- `GameStrikeSingleChat` — нарратив толчка, совместные броски, отброс и
  дополнительные проверки. В расчёт атаки толчка добавлен `pushRaw` — пара
  сырых успехов: панель показывает «Сырые успехи 2↑2», а урон — «2↑», поэтому
  сдвиг на один размер виден без догадок.
- Чат толчка с уклонением идёт в порядке: действие → нарратив → бросок
  попадания и уклона → «не успевает уклониться» и бросок Силы → итог толчка →
  проверка Неустойчивости (текст и кубы одним сообщением) → исход проверки.
  Текст «Сила (толчок)» не пишется, итог не опережает бросок Силы.
- `GameStrikeOpen` / `GameStrikeSpend` / `GameStrikes` — Толчок не списывает
  боеприпас и не требует профиля оружия; щит допустим.
- `PushSpec.block_profiles`, `RuleValidationService`, редактор действия,
  фильтр блок-профилей в `HitRollService`, выбор рук по умолчанию в
  `HitLaunchDialog`.
- `tools/rule-migrate-21.php` — ревизия 22: `exhaustion.increase_check_code`,
  `simple-push.block_profiles = "hands_or_shield"`,
  `tolkayuschiy-udar.block_profiles = "slashing_or_blunt_strike"`.
- `tools/rule-migrate-22.php` — ревизия 23: описания `simple-push` и
  `tolkayuschiy-udar` с полом, формулой урона, Неустойчивостью и отбросом.
- `tools/rule-migrate-23.php` — описания переписаны HTML-блоками (`<p>`,
  `<ul>`, `<strong>`), формула урона объяснена через «минус один размер шкалы».
  В живой БД это ревизия 25.
- `tools/rule-migrate-24.php` — ревизия 26: простое правило `push-procedure`
  («Толчок»), `simple-push` переименован в «Простой толчок», оба описания
  ссылаются на общее правило через `data-rule-code`; добавлен признак `push`.
- `RuleRevisionSpecGuard` для keep-записей читает сырой документ спеки, поэтому
  ссылки проверок из прошлой ревизии не теряются.
- `GameCheckRoll` читает характеристику только из живого листа через
  `ICharacterFormulaContexts::build($sheet)->findCharacteristic()`; ручной
  разбор `characteristicPurchases`, `formula` и `automatic` из Game удалён.
  `GameStrikeRules::weaponFamilyBase()` переведён на тот же контекст.

## Тесты

- `GameStateIncreaseRuleTest` — связка, сложность, исход, отказы.
- `GamePushRulesTest::testDamageShiftsSizeWithoutCharacteristicNormalization` —
  `{2|2}` → `{2|1}`, база успехов не нормализуется к 3..5.
- `GamePushRulesTest::testKnockbackDividesPostureByFour` — `3` → нет отброса,
  `4` → `1`, `13` → `3`.
- `GameStrikeMysqlTest::testPushIgnoreUsesStrengthAndAddsUnstable` — Сила против
  пола, урон `{4|0}` → `{4|-1}`, `appliedSr = РУ`, Неустойчивость, 3 ОД.
- `GameStrikeMysqlTest::testWeaponPushUsesWeaponDamageAndHalvesPosture` — пул
  урона оружия, урон профиля × РУ, рубящий делитель позы, отброс и Неустойчивость.
- `GameStrikeMysqlTest::testWeaponPushBlockKeepsContactDamage` — успешный блок:
  контакт 1 РУ независимо от успехов атакующего, защита блока в сопротивлении,
  урон проходит.
- `GameStrikeMysqlTest::testPushBlockUsesJointStrengthAndHands` — совместная
  Сила руками, 2 ОД, бросок защиты в итоге.
- `GameStrikeMysqlTest::testPushRejectsWeaponProfile` — меч без щита отклонён.
- `GameStrikeMysqlTest::testPushAllowsShieldProfile` — щит допустим.
- `GameCheckMysqlTest::testCharacteristicCheckUsesSheetValueWithoutPurchases` —
  проверка Силы берёт значение из `sheet.characteristics`, если закупок нет.
- `GameStrikeMysqlTest::testPushDodgeChatOrder` — порядок сообщений толчка с
  уклонением: Сила до итога, проверка Неустойчивости после, исход «падает!».
- `GameCheckRollTest::testCheckUsesCharacteristicFromLiveSheet` — пул берётся из
  живого листа, снимок закупок не участвует.
- `GameCheckRollTest::testCheckRejectsMissingCharacteristic` — нет
  характеристики в живом листе — `GAME_INVALID`.
- `CharacterFormulaContextTest::testLiveCharacteristicsWinOverPurchaseSnapshot` —
  `characteristics` важнее `characteristicPurchases`.
- `composer test:unit`; Game MySQL; RuleSpace; Character; frontend
  `Roleplay/Rule` + `Roleplay/Game`; `vite build`; eslint; prettier.

## Остаток

- `tolkayuschiy-udar` вынесен в [`game-plan-41-tolkayuschiy-udar.md`](game-plan-41-tolkayuschiy-udar.md).
- `GameWoundActions::handCount()` — заглушка 2.
- `pay_sr_vs_reliability`, SSE, `ignore_defense`.
