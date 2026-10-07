# План Mechanic 6 — бросок

**Статус:** порт броска, сессия 4 [`mechanic-roadmap.md`](mechanic-roadmap.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Фасад — [`mechanic-plan-05.md`](mechanic-plan-05.md). Реестр и `runEvent` — [`mechanic-plan-04.md`](mechanic-plan-04.md). Vue-контракт — [`mechanic-plan-03.md`](mechanic-plan-03.md). Формула — `draft-front_1.2ds` `Game/Service/Roll/RollEngine.ts` и `Rule/Service/CheckSuccessRatingService.ts`. Потребитель — Game G18, [`game-plan-18.md`](game-plan-18.md): он зовёт этот порт и сохраняет итог.

Цель: бросок на уже существующем `runEvent`. Сосед по-прежнему берёт `IMechanicEngine` из контейнера и хендлеры не регистрирует. Отдельного движка нет.

HTTP `mechanic.run`, schema, каталог, Character, Game, `fromRules` не входят. Импортов Character, Rule и Game нет. `critical_strike`, `movement_state`, раны, истощение, свёртывание и запись состояний не входят. Упрощённый пул с зашитыми числом кубов и эффективностью не писать.

## Порт

`Interface/Service/IMechanicRolls`, отдельно от `IMechanicEngine` и `IMechanics`. `Service/MechanicRolls` принимает уже собранный `IMechanicEngine`.

Новые DTO, не массивы: `RollSpec` (`diceCount`, `dieFaces`, `efficiency`, `dieSize`, список `RollAdvantage`), `RollAdvantage` (`sourceCode`, `delta`), `RollMechanicContext`, `RollResult` (спека, `rolls`, `successes`, `adjustedRolls`, `droppedRolls`, `totalSuccesses`, `appliedNames`: `null`, когда `applied` пуст), `SizedBase` (`base`, `size`), `CheckRating` (`passed`, `rating`). `RollMechanicPayload` хранит те же поля, что Vue: `diceCount`, `dieFaces`, `efficiency`, `adv`, `dieSize`, `sub_mechanics`. Контекст мутируется на месте, как `CharacterMechanicContext`.

- `roll` — спека, источник граней, binding, каталог `MechanicRecord`, опции фильтра. Возврат — спека после дефолтов, кубы, успехи, сброшенные, сумма, имена сработавших механик.
- `rate` — два `SizedBase` и необязательный `minSize`. Возврат — `CheckRating`.

`MechanicPortFactory::createEngine` по-прежнему отдаёт `IMechanicEngine`. `createRolls` вызывает `createEngine` и передаёт этот экземпляр в `MechanicRolls`. Карта портов: ключ `IMechanicRolls` отдельным замыканием, как `IMechanicEngine`. Новых маршрутов нет. Общего синглтона у двух ключей нет: у каждого свой экземпляр с тем же набором хендлеров.

Потребитель собирает `MechanicBinding` сам. Дефолты читаются с payload `roll` уже переданного binding, не с `Rule`.

## Поток

Как `RollEngine.roll`. Движок между событиями не подменяется. Имена событий — `Constant/RollEvent`: `roll.pool`, `roll.drop`, `roll.score`.

Контекст до событий: `poolSize` равен `diceCount`, `rolls`, `adjustedRolls`, `droppedRolls`, `successes` и `applied` пустые, сумма `0`.

1. Дефолты. Нейтральные точки: эффективность `3`, размер куба `0`, пустой список преимуществ. Первый по порядку binding с `RollMechanicPayload` подставляет `efficiency`, `dieSize` и `adv` только в этих точках. `diceCount` и `dieFaces` payload не меняет. `adv` становится одной записью `{ sourceCode: 'roll', delta }`; ноль записей не даёт.
2. Фильтр — уже существующий `ResolveActiveOptions`. `includeCodes === null` заменяется на `sub_mechanics` этого payload, если список задан. `null` у `sub_mechanics` фильтра не включает. Пустой массив — фильтр задан и никого не пропускает. В `resolveActive` уходит новый `ResolveActiveOptions`, аргумент вызывающего не мутируется. `extraRuleCodes` переносятся как есть.
3. `runEvent(roll.pool)`. Грань: `floor(rng() * dieFaces) + 1`, длина `max(1, poolSize)`. `rng` — `callable(): float` в `[0, 1)`.
4. `runEvent(roll.drop)`. Если `adjustedRolls` всё ещё пуст, копируется из `rolls`. Успех куба пишется в `successes` той же длины: грань `<= efficiency` → `1`, иначе `0`.
5. `runEvent(roll.score)`. Сумма `successes`. Имена — `MechanicRecord::getName()` по коду из `applied` в порядке записи. Повтор `code` в каталоге оставляет имя последней строки. Нет строки — сам код. Пустой `applied` даёт `appliedNames === null`.

`rate` — то же сравнение, что `CheckSuccessRatingService.checkSuccessRating`, без `formatPreparedMagnitude`. Своя пара целых в Mechanic; `Roleplay\Rule\Value\DimensionalNumber` не импортируется.

- Отрицательная база сворачивается: `{ base, size }` при `base < 0` → `{ 0, size + base }`.
- `minSize` задан и размер меньше — размер растёт, база `floor(base / 2)` за каждый шаг. Вниз `minSize` размер не ведёт, ветка ×2 у `withSize` здесь не вызывается.
- Общий размер — меньший из двух уже подготовленных. База к нему: × `2^(size − target)`. Показатель не отрицательный.
- `rating` — разность приведённых баз. `passed` — левая приведённая база не меньше правой.

Успехи броска в это сравнение: `base` — сумма успехов, `size` — `dieSize` спеки. Свёртку делает `rate`, не вызывающий.

## Хендлеры

Реестр `code@version`. Payload `roll` хендлером не является. Payload `roll_score_adjust` — DTO `{ oneDelta, faceDelta }`; хендлера под него в этой сессии нет, строка каталога без хендлера молча пропускается.

| Хендлер | Версия | События | Поведение |
|---|---|---|---|
| `six_one_rule` | `4.5.0` | `roll.score` → 10 | Payload не читает. Идёт по `adjustedRolls` и прибавляет дельту к `successes` того же индекса. `1` даёт `+1`. Грань куба даёт `−1`, только если грань `>` эффективности. Код в `applied` только если дельта была. |
| `advantage_disadvantage` | `2.1.0` | `roll.pool` → 10, `roll.drop` → 10 | Payload не читает. Нетто `0` — выход, в `applied` не писать. `pool`: `poolSize += abs(нетто)`. `drop`: копия `rolls` сортируется, преимущество по убыванию, помеха по возрастанию. Голова длиной `min(abs(нетто), длина)` — `droppedRolls`, хвост — `adjustedRolls`. Код в `applied`. |

Нетто, как `AggregateSourceDeltasService`: нулевая дельта не входит; один `sourceCode` оставляет самый большой плюс и самый большой минус; `null` — отдельный источник на каждую запись; нетто — сумма оставшихся дельт. Тип записи — `sourceCode` и `delta` в Mechanic, не DTO Rule.

## Точки сессий 2–3

`MechanicEngine`, алгоритм `resolveActive` и `runEvent`, расчёт доплаты, HTTP, schema и каталог не меняются. Без пяти точек payload броска в существующий `run` не проходит, и сосед не видит хендлеры.

- `Interface/MechanicPayload.php`, пустой интерфейс. `PurchaseSurchargePayload`, `RollMechanicPayload` и `RollScoreAdjustPayload` его реализуют. `IMechanicHandler::run` принимает `?MechanicPayload`.
- `PurchaseSurchargeHandler::run`. Сигнатура следует интерфейсу. Перед `collectMatchedCodes` — `instanceof PurchaseSurchargePayload`, иначе выход. Считающий код доплаты не меняется. В `tests/MechanicEngineTest.php` две анонимные реализации `run` меняют только тип аргумента: иначе они не реализуют интерфейс.
- `MechanicBinding`. Третий аргумент и `getMechanicPayload` — `?MechanicPayload`.
- `ResolvedMechanic`. Второй аргумент и `getPayload` — `?MechanicPayload`. `MechanicEngine` по-прежнему только передаёт payload в `run`.
- `MechanicPortFactory::createEngine`. Рядом с `PurchaseSurchargeHandler` регистрируются `SixOneRuleHandler` и `AdvantageDisadvantageHandler`.

## Тест

`tests/MechanicRollTest.php`, suite `mechanic`, без MySQL. Порт из контейнера. `MechanicEngine`, реестр и хендлеры тест не импортирует.

`rng` в тесте — как `rngFromDice`: очередная грань `f` даёт `(f - 1) / dieFaces`. По умолчанию шесть граней; кейс восьми граней передаёт `8`.

Строки каталога в фикстуре: `six_one_rule` с `handler_version` `4.5.0`, `advantage_disadvantage` с `2.1.0`. Иная версия даёт `null` из реестра, и хендлер не вызывается.

Случаи — те же, что `rollEngine.test.ts`, кроме блока «Критический удар»: чистая спека; преимущество и «6 и 1» (`[1,6,5,6]` → сброс `[6]`, `adjustedRolls` `[6,5,1]`, успехи `[-1,0,2]`, сумма `1`, имена `['Помехи и преимущества', 'Правило 6 и 1']`); грань при эффективности 3 и 6; без `six_one_rule`; нулевое преимущество не в `applied`; явный `includeCodes` подменяет `sub_mechanics`; дефолты `roll` не трогают число кубов и граней; явные эффективность и преимущество не затираются; два источника не схлопываются в один, один источник берёт крайние дельты.

`rate`, числа из `checkSuccessRating.test.ts`: `{3, 1}` против `{2, 1}` → `passed` true, `rating` `1`; `{3, 1}` против `{4, 0}` → `passed` true, `rating` `2`; `{1, -1}` против `{5, 0}` → `passed` false, `rating` `-9`; `{5, 0}` против `{1, -1}` → `passed` true, `rating` `9`; `{-2, 0}` против `{0, 0}` → `passed` true, `rating` `0`; `{5, -1}` против `{8, -2}` при `minSize` `-1` → `passed` true, `rating` `1`.
