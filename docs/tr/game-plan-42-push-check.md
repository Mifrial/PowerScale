# План 42 — Толчок как проверка

**Статус:** ~СДЕЛАНО~

**Реализация:** ~PHP~, ~VUE~, ~МОК~

**Опираться на:** ~PHP~, ~МОК~

## Границы

- Толчок — не второй боевой движок, а удар с другим кодом проверки, другой
  базой урона и добавочным сдвигом. Объявление, профиль, точность, уклонение,
  блок, сопротивление, смягчение, лист и чат — общие с ударом.
- Проверка — `check-push` («Проверка на Толчок»), совместная, как попадание:
  `melee-combat` даёт кубы, точность профиля — эффективность. Боевой минимум
  `{0|-1}` — автофейл, реакция списывается.
- `R` — знаковая маржа проверки.
- Устойчивость защитника — эффективная Сила для толчка. Минимум —
  `вес.modify(-3)`, ниже не опускается.
- Игнор: Устойчивость = минимум. Уклон: успех — срыв, провал — минимум +
  смягчение. Блок: провал — `max(F, S_d − R)`, успех —
  `max(F, S_d + min(−R, 6))`.
- Урон: база профиля + `damage_modifier` (`simple-push` −3,
  `tolkayuschiy-udar` −1), множитель — `R` (на успешном блоке 1, на успешном
  уклоне 0).
- Неустойчивость = `max(0, S_a − S_d_eff)`; рубящий делитель режет её;
  отброс = `⌊Неустойчивость / 4⌋`.
- `push-procedure`, `simple-push`, `tolkayuschiy-udar` переписаны; ревизия 27.
- Контент `check-push` не помечается `hit_check: true` — иначе `findHitCode`
  найдёт две проверки.

## Механика

```text
R = РУ проверки на толчок (маржа, знаковая)
F = вес.modify(-3)

S_d_eff =
  игнор:          F
  уклон (R > 0):  F
  блок (R > 0):   max(F, S_d − R)
  блок (R <= 0):  max(F, S_d + min(−R, 6))

base = профиль.урон + damage_modifier

damage =
  уклон (R <= 0):  0
  блок (R <= 0):   max(0, base − сопротивление)              // 1 РУ контакта
  иначе:           max(0, (base − сопротивление) × min(R, srCap) − смягчение)

unstable  = max(0, value(S_a) − value(S_d_eff))              // value = base + 3×size
unstable  = ⌊unstable / делитель⌋                             // рубящий: 2
knockback = ⌊unstable / 4⌋
```

## Чат

- Исход толчка называет, чем толкают: `руками`, `щитом «…»` или
  `оружием «…»` (по `item_rule_code` и спеке предмета).
- Отброс выводится отдельной строкой «цель отбрасывается на N ипари», чтобы
  не терялся в длинном исходе.
- Дробящий нокаут добавляет в исход «Попадание дробящим уроном вызвало
  потерю сознания», даже если проверка истощения дала только Слабость.

## Что переиспользуется

- `GameStrikeOpen`, `GameStrikes`, `GameStrikeRules::assertAttack` — объявление,
  профиль, точность, стоимость.
- `GameStrikeResolution::rollOf` — проверка (уже принимает код проверки,
  мастерство и точность профиля).
- `dodgeRollOf`, `blockRollOf` — защиты (уже принимают код проверки).
- `rerateHit` — совместная маржа.
- `damageOf` / `evaluateDamage` + `actionCharacteristicModifier` — база урона.
- `penetrationOf`, `resistanceOf`, `soakOf`, `durabilityShave` — слои защиты.
- `GameStrikeAmounts::injury` — итог.
- `prepareSheet` + `GameStateIncreaseChecks` — состояния.
- `GameStrikeSingleChat`, `AttackCalcAttachment` — чат и расчёт.

## Срезы

1. Спека и парсинг: `PushSpec` (`check`, `damageModifier`,
   `stabilityBonusCap`), `AbilityCombat::push`, `GamePushRules`
   (`checkCode`, `damageModifier`, `stabilityFloor`), unit-тесты.
2. Бэкенд: `GameStrikeSingleClose` на общем потоке, `GamePushRules`
   (`stability`, `unstable`, `knockback`), удаление `contest`; чат; Game MySQL.
3. Контент: `check-push`, спеки, описания, `tools/rule-migrate-25.php`,
   ревизия 27, игра на 27.
4. Мок и расчёт: `PushMathService`, `PushContestService`,
   `PushResolutionService`, calc, vitest.
5. Доки: планы 40/41, readiness, melee-power, `docs-map`/`maps-check`.

## Тесты

- `GamePushRulesTest` — Устойчивость (игнор/уклон/блок), Неустойчивость,
  отброс, модификатор урона.
- `GameStrikeMysqlTest` — ignore, dodge win/fail, block win/loss, weapon push,
  auto-fail, чат.
- `pushMath.test.ts`, `pushResolution.test.ts`.
- Полный `bash tools/test-full.sh` — после ручной проверки.

## Риски

- `check-push` без `hit_check: true`.
- `check` по умолчанию `check-push`; если карточки нет — ошибка, не тихий
  фолбэк.
- `damage_modifier` — через `actionCharacteristicModifier`; для фиксированного
  урона — сдвиг базы.
- Разница Сил — по шкале характеристик (`base + 3×size`).
- Блок толчка — `rerateHit`, в отличие от обычного мили-блока.
