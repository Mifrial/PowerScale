# План 40 — Толчок и общая проверка прироста

**Статус:** ~СДЕЛАНО~

**Реализация:** ~PHP~, ~VUE~, ~МОК~

**Опираться на:** ~PHP~, ~МОК~

## Границы

- `simple-push` — обычный Толчок руками или щитом, 3 ОД. Оружие без щита
  сервер отклоняет.
- Толчок не делает бросок попадания: `ignore` — Сила против пола, `block` —
  совместная Сила, `dodge` — обычный `check-hit`, при промахе толчок сорван.
- Пол толчка — `weight.modify(-6)`. Он не равен полу попадания.
- Успех: дробящий урон от сырых успехов Силы (`.modify(-3)`), `appliedSr = 1`,
  `Неустойчивость += РУ`, отброс `РУ.modify(-6)` строкой в чат.
- `РУ <= 0` — нет урона, Неустойчивости и отброса.
- Проверка прироста Неустойчивости — общая `GameStateCheck`; провал кладёт
  лежачее, лежачий подавляет Неустойчивость.
- `tolkayuschiy-udar` в срез не входит: `weapon_times_sr` даёт понятный
  `GAME_INVALID` до следующего среза.

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
- `GamePushRules` — пол, контест Силы, урон, отброс, поиск Неустойчивости.
- `GameStrikeRules` — проверка профиля толчка и блока против толчка, тип урона
  профиля щита, характеристика листа и поиск проверки по характеристике.
- `GameStrikeSingleClose` — ветка Толчка: реакции, сила/блок, урон, запись
  Неустойчивости и проверка прироста, один пакет операций.
- `GameStrikeSingleChat` — нарратив толчка, совместные броски, отброс и
  дополнительные проверки.
- `GameStrikeOpen` / `GameStrikeSpend` / `GameStrikes` — Толчок не списывает
  боеприпас и не требует профиля оружия; щит допустим.
- `PushSpec.block_profiles`, `RuleValidationService`, редактор действия,
  фильтр блок-профилей в `HitRollService`, выбор рук по умолчанию в
  `HitLaunchDialog`.
- `tools/rule-migrate-21.php` — ревизия 22: `exhaustion.increase_check_code`,
  `simple-push.block_profiles = "hands_or_shield"`,
  `tolkayuschiy-udar.block_profiles = "slashing_or_blunt_strike"`.
- `RuleRevisionSpecGuard` для keep-записей читает сырой документ спеки, поэтому
  ссылки проверок из прошлой ревизии не теряются.

## Тесты

- `GameStateIncreaseRuleTest` — связка, сложность, исход, отказы.
- `GameStrikeMysqlTest::testPushIgnoreUsesStrengthAndAddsUnstable` — Сила против
  пола, урон, `appliedSr`, Неустойчивость, 3 ОД.
- `GameStrikeMysqlTest::testPushBlockUsesJointStrengthAndHands` — совместная
  Сила руками, 2 ОД, бросок защиты в итоге.
- `GameStrikeMysqlTest::testPushRejectsWeaponProfile` — меч без щита отклонён.
- `GameStrikeMysqlTest::testPushAllowsShieldProfile` — щит допустим.
- `GameCheckMysqlTest::testCharacteristicCheckUsesSheetValueWithoutPurchases` —
  проверка Силы берёт значение из `sheet.characteristics`, если закупок нет.
- `composer test:unit`; Game MySQL; RuleSpace; Character; frontend
  `Roleplay/Rule` + `Roleplay/Game`; `vite build`; eslint; prettier.

## Остаток

- `tolkayuschiy-udar`: пул урона оружия, урон профиля × РУ, рубящий делитель
  позы.
- `GameWoundActions::handCount()` — заглушка 2.
- `pay_sr_vs_reliability`, SSE, `ignore_defense`.
