# План M9 — провал каста: отклонение, аркан, стабильность

**Статус:** `DONE`, 2026-09-11. Родительская нарезка — [`spell-roadmap.md`](spell-roadmap.md). Срез E6 — [`spell-plan-02-slice.md`](spell-plan-02-slice.md). Формула сложности — [`spell-plan-06-cast-check.md`](spell-plan-06-cast-check.md). Instant — [`spell-plan-07-runtime-slice.md`](spell-plan-07-runtime-slice.md). Duration — [`spell-plan-08-runtime-expansion.md`](spell-plan-08-runtime-expansion.md). Канон — [`spell-review-01.md`](spell-review-01.md) SR-080…099, SR-128…140, SR-148. Источник формулы взрыва — [`docs/rule/spell/AI.html`](../rule/spell/AI.html) («последствия провала»). Правила фронта — [`draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md).

## Цель

После M8 успешный каст живёт. Провал проверки сотворения **ещё ничего не делает**.

M9 закрывает **fail check → 2d6 отклонение → (при дубле) взрыв** и тонкий контур **стабильность Генератора**. Ядро **не тратит ману** (SR-053). Кристалл, накопители, проводники, path-каталог, отдельный экшен «разрушить заклинание» — не этот срез.

После M9:

- провал **броска** `check-spell-cast` вызывает отклонение: две грани d6, сила `сумма − 7`; сила ≤ 0 → эффекта отклонения нет;
- дубль граней → взрыв **дополнительно**; формула из HTML, не выдуманный `f()`;
- skip `{0|-1}`, auto-fail без броска, отказ до старта (нет ОД) — **без** отклонения;
- промах касания (**молоко**) при **успешном** check — без отклонения; молоко **и** провал check — отклонение есть (магия сорвалась);
- у Генератора на ActiveSpell — `stability` по SR-081; смена мощи пересчитывает (SR-138);
- дестабилизация как **вход каста / UI** в M9 нет (нет экшена). На ActiveSpell — поле и правило drop `сила >= toNumber(stability)` для unit/вызова с процесса, когда сила откуда-то придёт.

Это не M10 и не M11.

## Representative

| Что | Роль |
| --- | --- |
| Провал Удара / Разряда / Генератора **после броска check** | Отклонение + опциональный взрыв |
| `arcane` | Тип урона взрыва; выставить `defense_ignored: true` в моке (сейчас `createEmpty` даёт `false`) |
| `lightning-generator` | `stability` на ActiveSpell |
| Ядро | Без маны |

Карточки `magic-deviation` / `spell-disruption` в каталог не импортировать.

## Что не делать в M9

- Кристалл / мана / простейшее волшебство / накопители / проводники / несколько источников в одном cast.
- Мутации (SR-099), `core-magic-deviation` как decay ядра.
- Refreshable / lingering.
- Боевое действие «дестабилизировать» и `disruptionStrength` в формуле сложности. Не крутить skip `{0|-1}`. Не спиннер на касте.
- Battleground / сетка.
- Менять формулу дефицита мощи/контроля.
- PHP, persist, vite, M11-навыки.
- Таблицу эффектов силы отклонения (кроме числа в чате).
- Прогонять отклонение через `characteristicRollSpec` / `rollNamedCheck` / подсчёт **успехов** движка.

## Канон, который режем в код

### Когда звать отклонение

HTML: заключительный шаг **после провала сотворения** — «Бросьте 2d6». M7 явно: auto-fail без отклонения.

| Исход | Отклонение |
| --- | --- |
| `cast.roll.check.passed === false` (бросок был) | да, даже если `milk` |
| Skip `{0|-1}` (`needsCheck: false`) | нет |
| Молоко и check успешен / skip | нет |
| Auto-fail: `needsCheck: true`, `roll: null` | нет |
| `started === false` | нет |

Оба канала: `SpellCastDialog` и `HitLaunchDialog` (у последнего при milk/fail сейчас **ранний return** — хук и диалог взрыва **до** return).

**Не копировать** `needsCheck && !roll?.check?.passed` из диалогов: для auto-fail `roll === null`, `!undefined === true`, сработает ложный 2d6. Нужно строго `roll?.check?.passed === false`.

### Отклонение (SR-087…091)

- Две **независимые грани 1…6**. Сила = `d1 + d2 − 7`. Дубль = `d1 === d2`.
- Сила > 0 → в чате сила (таблица эффектов — не M9). Сила ≤ 0 → «отклонения нет».
- Дубль → взрыв даже при силе ≤ 0 (`2+2` сила −3). `3+4` сумма 7 — не дубль, отклонения нет, взрыва нет.

**Движок кубов.** Не `characteristicRollSpec` / `rollNamedCheck` / `RollEngine` ревизии (правило 6 и 1 испортит грани). Две грани из `DiceRng`.

**Чат — тот же `ROLL_ATTACHMENT_TYPE`, не новый тип вложения.** На spec поле `scoring` (`'pool' | 'face_sum'`, default `'pool'`): тип — `Enum/DiceScoring.ts`, не union в `.vue`. Для отклонения сервис собирает полный `DiceRollResult` с `scoring: 'face_sum'` и **уже посчитанной** суммой граней (`faceSum` на результате, не писать сумму в шаблоне). Рейтинг в `DiceRollResult.vue` читает `faceSum`; popup без пула/эффективности/успехов; грани без раскраски 6-и-1. Сила отклонения — строка того же блока, не `check`. Как `injury` на том же payload.

Постить в чат готовый result, не голый spec: иначе `processAttachments` прогонит `rollSimpleCheckZero`. Если spec всё же придёт с `face_sum` — не звать simple-check.

### Взрыв — формула HTML однозначна

> радиус **[Магия для сотворения] ипари**; каждому **[(Магия для сотворения − Расстояние) × Цифра на кубиках]** урона излучением.

Канон: «Магия для сотворения» = **используемая мощь** (`usedPower`, SR-090), не Мощь заклинания. «Цифра на кубиках» при дубле `n+n` = `n` (SR-093). Излучение = `arcane` (SR-092).

Число мощи: `DimensionalNumber.from(usedPower).toNumber()` (`floor(base × 2^size)`), как уже у урона оружия. Дистанция hop/каста — целое ипари.

```text
powerN = usedPower.toNumber()
radius = powerN
amount(target) = max(0, (powerN − distanceIpari) * n)
```

`distance > powerN` → 0 (SR-095).

**Цели.** HTML: все в радиусе, **включая заклинателя**. Диалог как hops, но кастера **не exclude**: дистанция 0 по умолчанию, остальных игрок добавляет с ипари. Список не заменить геометрией.

**Apply.** [`AttackDamageService`](../../draft-front_1.2ds/src/modules/Roleplay/Game/Service/AttackDamageService.ts): `raw = (weapon.toNumber() − resistance) * sr`. Нельзя `sr = n` и `weapon = powerN − dist` — resistance умножится на `n`. Нужно `weaponDamage = { base: amount, size: 0 }`, **`sr: 1`**, `damageTypeCode: 'arcane'`. `max_success_rating` у мока `arcane` сейчас `null` — не ставить как у электричества (3).

Защита: `defense_ignored` на типе (мок сейчас `false` — выставить `true`). Линии resistance из **брони** apply уже умеет при `includeDefense: false`.

**Грант `magic-resistance` в overview.defense нет.** Вычесть **один раз** через `targetResistanceAmount(..., 'arcane')` (в `amount` до apply или синтетическая линия resistance — не оба). Плюс линии брони из apply.

После apply — тот же persist overlay + истощение/увечье, что у эффекта заклинания (`persistDamage` / `announceSpellApply`), не только [i].

Чат урона: существующий `formatSpellEffectMessage` с кодом `arcane`, не новый formatter в Utils.

### Стабильность и дестабилизация

HTML: «сила **больше** стабильности»; «стабильность = сложность, уменьшенная на размер». Авторские ответы **перебивают**: SR-085 равенство дропает (`>=`); SR-081 sustained = `{3|0}.modify(превышение используемой мощи над Мощью заклинания)`; SR-078 `{3|0}` — для lasting/refreshable, карточек нет. Контроль в стабильность не входит (SR-140).

- Поле `stability: DimensionalNumberValue` на `ActiveSpell`; пересчёт при смене `sustainPower`.
- Drop: `disruptionStrength >= DimensionalNumber.from(stability).toNumber()` (SR-085, сила — целое как в HTML). Unit на `{3|0}` vs 3.
- `SpellCastDifficultyService` **не** трогать в M9.
- Sustain-диалог не раздувать. UI «сломать Генератор» — когда появится экшен.

### Мана

Ядро без маны. Заголовок roadmap «Mana» = кристалл; в M9 это non-scope.

## Сверка с кодом

1. Хук: `cast.roll?.check?.passed === false`. `usedPower` — `resolve.usedPower` / оферта, на `SpellCastExecutionResult` поля нет.
2. SpellCastDialog: `resolveTargetedCast` при fail **не** зовёт hops (нет `spellApply`). Отклонение и диалог взрыва — в том же `try` с `beginAttack`, **до** `close()`. Иначе каст закроется без 2d6.
3. HitLaunch: хук **до** `return` при milk/fail; оружие касания по-прежнему применяется.
4. RNG — тот же `DiceRng`, что у execute (сейчас `Math.random` в диалоге). Не `RollEngine`.
5. Слои: Enum scoring; Dto result+ActiveSpell; сервисы + Instance. Vue без `modifyDiffTo` и без суммы граней.
6. `createSustained` сегодня без `stability` — посчитать при upsert и при смене мощи. Не overlay state.

## Состав работ

| Поверхность | Что сделать |
| --- | --- |
| Mock `arcane` | `defense_ignored: true` |
| Constant | порог 7; база стабильности `{3\|0}` |
| Dto / Enum | `DiceScoring`; `faceSum` на результате; исход отклонения; `stability` |
| Service | грани+сила+result; `amount` (−грант); стабильность; drop |
| Chat UI | ветка `face_sum` в `DiceRollResult.vue` |
| Cast UI | хук до close; дочерний диалог взрыва (кастер dist 0); Difficulty не менять |
| Тесты | 7; 4+4; 2+2; dist; sr=1; грант один раз; auto-fail не хук; milk+fail; milk+успех; `processAttachments` не simple-check на face_sum; стабильность; drop |

## Порядок

1. Сервисы + unit (грани, взрыв, грант resistance, стабильность/drop).
2. Хук из обоих каналов (в HitLaunch — до early return).
3. Диалог взрыва + apply + чат.
4. `stability` на upsert/смене мощи Генератора.
5. Browser: Торвин, провал check (не auto-fail). Дубль — unit, если не выпадет. AI не стартует vite.
6. `format` → `lint` → `vue-tsc` → `npm run test`.

## Гейт M9

- Успех, skip, auto-fail, молоко при успешном check — без 2d6 и без аркана.
- Проваленный check — `roll` с `face_sum` (сумма граней, не успехи); сила > 0 текстом; сила ≤ 0 без эффекта.
- Дубль → кастер dist 0; формула; Защита не режет; грант+броня `arcane` один раз; `sr: 1`; persist как у урона заклинания.
- `arcane.defense_ignored === true`.
- Стабильность Генератора по SR-081; drop по `toNumber`.
- Ядро без маны. Сложность каста без disruption. Нет спиннера на касте.
- Vue без формулы кубов / `modifyDiffTo`.
- format / lint / `vue-tsc` / полный `npm run test`.

## Журнал

| Дата | Изменение |
| --- | --- |
| 2026-09-11 | Черновик после M8. |
| 2026-09-11 | Перепроверка: формула взрыва из HTML; 2d6 = грани не успехи; auto-fail без отклонения (как M7); apply sr=1; `arcane.defense_ignored`; disruption без UI каста; стабильность SR-081 не HTML; drop по SR-085 `>=`. |
| 2026-09-11 | Вторая перепроверка: хук `passed === false`; milk+fail; кастер dist 0; грант resistance не в overview; без правки Difficulty; HitLaunch early return. |
| 2026-09-11 | 2d6 в чат: тот же `roll`, scoring `face_sum` на spec; не новый attachment type. |
| 2026-09-11 | Финальная сверка: хук до `close()`/early return; `faceSum` в payload; грант один раз; persist увечья; hops при fail нет. |
| 2026-09-11 | В коде: 2d6 + диалог взрыва в обоих каналах; `stability` на upsert/смене мощи. Гейт: `vue-tsc` ок; полный test — 1 посторонний fail (`magicPathStudyCost`); browser не прогнан. |
| 2026-09-11 | Эффект отклонения: состояние `core-magic-deviation` на ядре (`boundSourceKey`); −сила к мощи этого источника; ниже маленького размера → 0; −1 силы в конец хода кастера. |
| 2026-09-11 | `DONE`: провал check → 2d6 + состояние на ядре; дубль → аркан (unit + диалог); `stability` Генератора; ядро без маны. Disruption UI и кристалл — не этот срез. |
