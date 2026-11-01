# План 41 — Толкающий удар

**Статус:** ~СДЕЛАНО~

**Реализация:** ~PHP~

**Опираться на:** ~PHP~, ~МОК~

## Границы

- `tolkayuschiy-udar` — тот же Толчок, а не отдельный боевой удар. Стоимость
  4 ОД; пул и база урона заменяются на урон профиля оружия.
- `ignore` — пул урона против пола `weight.modify(-6)`, без броска попадания.
- `block` — совместная проверка: атакующий бросает урон оружия, защитник —
  Силу; успехи защитника поднимаются до пола. Успешный блок оставляет контакт:
  РУ 1, если у атакующего были успехи; защита блока при этом входит в
  сопротивление, а не обнуляет удар.
- `dodge` — сначала обычный `check-hit`; при промахе толчок сорван, иначе
  контест пула урона против пола.
- Профили атаки и блока — `slashing_or_blunt_strike`. Рубящий делитель
  `posture_rating_divisor_by_damage_type = { slashing: 2 }` режет только
  отброс и Неустойчивость, но не урон.
- Это удар оружием: пробитие и износ прочности работают как у обычного удара.

## Механика

```text
SR = РУ контеста (не бросок попадания)

base   = урон профиля оружия
injury = max(0, (base − сопротивление) × min(SR, потолок типа урона) − смягчение уклона)
posture = ⌊SR / делитель⌋

Неустойчивость += posture
Отброс = posture − 6, только если положительный
```

- Пул проверки — та же пара, что и урон: `{base|size}` даёт `base` кубов
  размера `size`.
- Сопротивление — слои защиты цели: доспех, щит при блоке, типизированные
  сопротивления; пробитие оружия уменьшает защиту.
- `appliedSr` — РУ контеста с потолком типа урона. Для обычного Толчка
  множитель тоже РУ, а база — сырые успехи Силы минус 3.
- Отброс не пишется, если `posture − 6` неположителен.

## Форма

```text
tolkayuschiy-udar.push:
  pool           = "weapon_damage"
  damage         = "weapon_times_sr"
  profiles       = "slashing_or_blunt_strike"
  block_profiles = "slashing_or_blunt_strike"
  posture_rating_divisor_by_damage_type = { slashing: 2 }
```

## Что сделано

- `GamePushRules::assertSupported()` принимает пару
  `weapon_damage/weapon_times_sr`; `isWeaponDamagePool()` отличает пул оружия.
- `GamePushRules::contest()` принимает урон оружия и тип урона, бросает пул
  через `GameCheckThrowOverride` и возвращает `posture`.
- `GamePushRules::getPostureRating()` читает делитель из спеки; `knockback()`
  возвращает `null` для неположительного результата.
- `GameStrikeSingleClose` считает урон оружия до контеста, ставит
  `appliedSr = РУ` для обоих толчков, пишет Неустойчивость значением `posture`
  и применяет пробитие/износ прочности для пула оружия.
- Успешный блок в контесте оставляет `success = 1` при успехах атакующего;
  защита блока суммируется в сопротивлении.
- `GameCheckRoll` передаёт в `RollSpec` размер пула, а не размер
  эффективности; это закрывает находку с `{5|2}` → `5|0`.
- Чат: проверка пула оружия подписана «Урон (толчок)»; итог и проверка
  прироста идут в общем порядке Толчка.

## Тесты

- `GameStrikeMysqlTest::testWeaponPushUsesWeaponDamageAndHalvesPosture` — урон
  профиля × РУ, `posture = ⌊РУ/2⌋`, отброс и Неустойчивость, подпись проверки.
- `GameStrikeMysqlTest::testWeaponPushBlockKeepsContactDamage` — успешный блок:
  контакт 1 РУ, защита блока в сопротивлении, урон проходит.
- `GameStrikeMysqlTest::testPushIgnoreUsesStrengthAndAddsUnstable` — обычный
  Толчок: `appliedSr = РУ`, урон по общей формуле.
- `GameCheckRollTest::testCheckUsesCharacteristicSizeForSuccessSize` — размер
  пула характеристики становится размером успехов броска.
- `composer test:unit`; Game MySQL; RuleSpace; Character.

## Остаток

- `GameWoundActions::handCount()` — заглушка 2.
- `pay_sr_vs_reliability`, SSE, `ignore_defense`.
- `GameCheckRoll::thrown()` больше не теряет размер пула; отдельно остаётся
  вопрос эффективности с ненулевым размером.
