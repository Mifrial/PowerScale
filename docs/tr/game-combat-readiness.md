# Готовность боевой модели Game

**Статус:** контрольный срез, 2026-10-29.
**Назначение:** сверять канон боевого процесса с frontend, backend PHP и
сквозными тестами. Этот файл не заменяет `game-system.md`, `rule-system.md`,
`character-system.md` и профильные планы.

## Как читать документ

Приоритет подтверждения:

1. код и тесты;
2. действующие правила frontend;
3. принятые решения;
4. плановые и исторические документы.

Статусы реализации:

- `DESIGN` — поведение описано, реализации нет;
- `FRONTEND` — есть frontend/domain-код, но нет authoritative PHP;
- `MOCK` — есть только mock или compatibility path;
- `BACKEND` — есть PHP-код и профильные тесты;
- `BACKEND_MISSING` — frontend или модель есть, authoritative PHP нет;
- `E2E_MISSING` — отдельный слой уже есть, но сквозного теста нет;
- `E2E` — подтверждено реальным frontend → HTTP → PHP → actual/NPC;
- `TODO` — запланировано, но работа не начата;
- `IN_PROGRESS` — работа начата, но capability ещё не закрыта;
- `OPEN` — намеренно не входит в текущий срез;
- `MISSING` — требуется реализация.

`BACKEND` не означает готовность production-контура целиком. Для этого нужны
права, реальные HTTP-вызовы, доставка и сквозной тест.

Одна capability может иметь несколько статусов одновременно, например
`FRONTEND + BACKEND_MISSING + E2E_MISSING`. Это точнее, чем сводить всё к
одному `MISSING`. Если ниже у `BACKEND` явно не указано иное, для него
подразумевается `E2E_MISSING`.

## Канонический поток

```text
действие
  → проверка/бросок атаки
  → рейтинг попадания
  → реакция защиты
  → срез надёжности и пробитие
  → сопротивление
  → смягчение S
  → повреждение
  → запись состояний
  → автоматические последствия
  → конец хода и DOT
```

Authoritative-источники:

- actual лист игрока хранит Character;
- `npc.version` хранит лист NPC;
- Game хранит сессию, бой, process, offers, initiative и transient markers;
- изменение листа проходит через Character mutation port или Game-owned NPC CAS;
- решение клиента не является числом урона, успеха или состояния.

## Состояние общего боевого контура

### Сессия и бой

- Создание игры, membership, NPC, moderation и запуск сессии — `BACKEND`.
  Доказательство: G1–G8 в `game-roadmap.md` и соответствующие `game-plan-*.md`.
- Battle `battleId`, состав и CAS версии — `BACKEND`.
  Доказательство: G12 / `game-plan-12.md`.
- Несколько независимых боёв в одной сессии — `BACKEND` на уровне контракта.
- Процесс, предложения и очистка незавершённых процессов — `BACKEND`.
  Доказательство: G17 / `game-plan-17.md`.
- Доставка уже принятой команды через outbox/SSE — `BACKEND`, production
  multi-user acceptance подтверждён как `E2E` на двух аккаунтах
  ([`game-plan-33.md`](game-plan-33.md), P4).
- Battleground, координаты и `ISpatialResolver` — `OPEN`.

### Проверки и порядок хода

- Соло-проверка — `BACKEND`.
- Pairwise-проверка — `BACKEND`.
- Поиск инициативы по `CheckSpec::isInitiative()` и бросок — `BACKEND`.
- Persistence порядка участников — `BACKEND` по G21.
- Сортировка инициативы по убыванию `roll.base`, а при равенстве — по
  исходному порядку состава, — `BACKEND` по канону G21. `roll.size` в порядок
  инициативы намеренно не входит.
- Frontend и полный сквозной тест инициативы требуют отдельной проверки.
- Проверка попадания по единственной живой
  `CheckSpec::isHitCheck()` — `BACKEND`.
- Клиент не передаёт готовый `CheckRating` — подтверждено backend-тестами.

### Удар

- `1 → 1` — `BACKEND`.
- `1 → N` — `BACKEND`.
- `N → 1` — `BACKEND`: «Сдвоенный удар» (один бросок, N пакетов одного
  оружия) и «Обоерукая атака» (свой бросок и реакция на каждый удар разным
  оружием).
- `N → N` — `OPEN`.
- Публичный вход действий — `game.runAction` с фазой `declare`/`respond` и
  видом `action`/`strike`/`wound`; отдельные `declareStrike`, `resolveStrike`,
  `declareWideStrike`, `resolveWideStrike`, `declareAction`, `woundAction`
  удалены.
- Идемпотентность команд и повтор без повторной записи — `BACKEND`.
- Конфликт версии боя и листа — `BACKEND`.
- Персонаж и NPC как цели — `BACKEND`.

Вход допускает `profileType` `strike`, `throw` и `shoot`. Специальные правила
броска и выстрела — `BACKEND` в срезе дистанции/боеприпасов (ревизия 15):
размерная дистанция, `falloff`, укрытие, условия цели, боеприпасы профиля и
`dodge_ranged_penalty`; E2E — в P4 ([`game-plan-33.md`](game-plan-33.md)).
Остаток — контент `ammunition_requirements` и профили атаки щитом.

## Расчёт атаки

### Урон и попадание

- Формула урона профиля — `BACKEND`.
  Порт: `IFormulaEvaluations::evaluateDimensional`.
- Размерный `damage` — `BACKEND`.
- Рейтинг попадания `success` — `BACKEND`.
  Это `CheckRating`, а не копия урона.
- `damage` и `success` в `1 → N` общие для атаки, а защитные значения
  считаются отдельно для каждой цели — `BACKEND`.

Доказательства: `game-plan-20.md`, `game-plan-22.md`,
`GameStrikeRules.php`, `GameStrikeMysqlTest.php`,
`GameWideStrikeMysqlTest.php`.

### Защита, сопротивление и смягчение

- Реакции `ignore`, `dodge`, `block` — `BACKEND` как варианты ответа.
- `dodge` создаёт слой `S` — `BACKEND`.
- `ignore` и `block` не создают `S` — `BACKEND`.
- `S` берётся из единственной живой характеристики
  `CharacteristicSpec::isDodgeSoak()` и сдвигается на
  `WeaponProfile::getDodgeBenefit()` или fallback `-3` — `BACKEND`.
- `resistance` собирается из projection надетых item layers и resistance grants
  Character/NPC по совпадающему типу урона — `BACKEND` для базового path.
- Размерные значения `damage`, `resistance`, `S` и `injury` в JSON результата
  команды представлены как `{base, size}` — `BACKEND`.
- В actual-лист не записываются `damage`, `resistance`, `S` или исходный
  `injury`. В actual записывается только остаток через состояние
  `isDamageRemainder()`, а частное добавляется в `isDamageExhaustion()`.
- Формула повреждения
  `max(0, (damage - resistance) * success - S)` — `BACKEND`.

Доказательства: `game-plan-23.md`, `game-plan-24.md`,
`game-plan-25.md`, `game-plan-26.md`, `GameStrikeAmounts.php`,
`GameStrikeSoaks.php`.

### Что пока не считается реализованным

- Проекция числовых `defense_slots` брони и сопротивлений из Character/NPC —
  `BACKEND` для базового resistance path. По канону defense-слой входит в
  effective resistance, если тип урона не помечен `defense_ignored`.
  Penetration вычитается из защиты (`GameStrikeAmounts::effectiveDefense`),
  block-ветка считает успех (`GameStrikeResolution::successOf`).
- Числовой эффект `block` — `BACKEND`: успех защитника режет успех атаки до 1
  (`GameStrikeResolution::successOf`), E2E — в P4 ([`game-plan-33.md`](game-plan-33.md)).
- `ResistanceSlot::durability` / nullable threshold — `BACKEND` в Rule и
  projection; фактическая production capability среза ещё не подтверждена.
- Инфраструктура semantic reliability capability — `BACKEND`; живой handler и
  content, который её включает, — `BACKEND_MISSING`.
- `pay_sr_vs_reliability` — `BACKEND_MISSING`: handler в Mechanic
  зарегистрирован (`PaySrVsReliabilityHandler`), но боевой путь Game его не
  вызывает.
- `source_code` и source collapse для базового resistance path —
  `BACKEND`; semantic provenance блока и сквозное подтверждение ещё требуют
  отдельного гейта.
- Специальные срезы надёжности для оружейных действий — `BACKEND`:
  `GameStrikeRules::durabilityShave()` читает `current_action_durability_shave`,
  учитывает `scope`, `damage_type_codes` и `short_extra_on_first_one`
  (коммит `d87d961`, тесты `GameStrikeMysqlTest`, `GameStrikeLayersTest`).
- Дальность, укрытие, `falloff` и особые правила `throw`/`shoot` —
  `BACKEND` в принятом срезе: размерная дистанция профиля `{min,max}`,
  `falloff` как дальнобойность, условия цели `distance`/`cover`/`flank`/`turn`,
  боеприпасы профиля (`none`/`self`/`item`), штраф уклонения от выстрела
  `dodge_ranged_penalty` и накопление укрытия в сложности. Хранится в
  `game_action_strike.conditions`; чат и окно защитника показывают разбор.
  Frontend отправляет и отображает условия; E2E поверх реального HTTP
  подтверждён в P4 ([`game-plan-33.md`](game-plan-33.md)). Доказательства:
  `GameStrikeConditions`,
  `GameStrikeRules::distanceMetrics`/`rollDodge`, `GameProjections`,
  `GamePhysicalProfileMysqlTest`, `GameWideStrikeMysqlTest`
  (`testWideStrikeKeepsPerTargetConditions`), `GameStrikeConditionsTest`,
  ревизия 15.

Frontend `AttackDamageService` уже содержит более полную модель: defense и
resistance layers, durability threshold, `pay_sr`, source collapse,
penetration, `defenseIgnored` и damage-type hooks. Это полезный источник
контрактов, но не доказательство PHP authoritative-контура.

Следовательно, базовая сопротивляемость теперь строится из projection
Character/NPC, но полный P1 ещё не закрыт: нет defender block check,
penetration, action-point eligibility/spend и production reliability handler.
Проверка Rule расчёта слоёв по-прежнему остановилась на границе:
[`rule-plan-07.md`](rule-plan-07.md).

## Запись непосредственного результата

- Деление `injury` на стойкость повреждений — `BACKEND`.
- Остаток `{base, size}` записывается в состояние
  `isDamageRemainder()` — `BACKEND`.
- Частное добавляется в состояние `isDamageExhaustion()` — `BACKEND`.
- Персонаж и NPC записываются в той же транзакции закрытия удара —
  `BACKEND`.
- Для `1 → N` каждая принятая цель получает собственное деление и собственную
  запись — `BACKEND`.
- Отказ конкретной цели `1 → N` не затирает результаты других — `BACKEND`.
  Это относится к target-level refusal. Ошибка расчёта сопротивления,
  деления, mutation port или внешней транзакции может откатить весь wide
  strike.

Доказательства: `character-plan-11.md`, `game-plan-27.md`,
`CharacterActualMutations.php`, `GameStrikeSplits.php`,
`GameStrikeSheetWrites.php`.

Это закрывает только два состояния: повреждения и истощение. Это не означает
готовность всех последствий удара.

## Состояния и последствия

### Непосредственные состояния

- Повреждения как размерный остаток — `BACKEND`.
- Истощение как целое частное — `BACKEND`.
- Произвольная запись состояний через общий Character mutation port —
  `BACKEND` как возможность Character, но не как боевой producer.
- Автоматические state hooks у типов урона —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.

### Рана

- Состояние `wound` и базовая модель экземпляра — `FRONTEND` / `MOCK`.
- Sidecar раны: `bandage`, `clotting`, `internal`, `aided` — `FRONTEND`.
- Сервис зажима и перевязки — `FRONTEND` / `MOCK`.
- Кровопотеря и тик раны — `FRONTEND` / `MOCK`.
- Authoritative PHP persist экземпляра раны —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.
- Реальные Game actions зажать/перевязать —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.
- Свёртывание в конце хода —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.
- Суточное снижение силы раны — `OPEN`.

Доказательства frontend-среза: `rule-content-plan-06-wounds.md`,
`WoundInstanceService.ts`, `WoundActionService.ts`.

Frontend-срез хранит `heldBy` внутри wound sidecar и использует его при
расчёте тика. Канон требует держать этот operational marker в Game battle
state; поэтому это не только отсутствие PHP, но и зафиксированное
frontend-расхождение, которое нельзя переносить в authoritative storage
без отдельного решения.

### Оглушение и шок

- Rule-карточки и state-коды — `FRONTEND` / `MOCK`.
- Frontend damage hooks `exhaustion_to_stun` и
  `exhaustion_to_shock` — `FRONTEND`.
- Authoritative запись `stunned` и `shock` из PHP Game —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.
- Серверная проверка влияния этих состояний на действия —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.

### Увечье

- Модель и формула проверки увечья описаны в `docs/specs/check-design.md`.
- Frontend процедура проверки увечья — `FRONTEND`.
- Автоматический запуск после атаки — `FRONTEND`.
- PHP `check-injury`, бросок и запись `maim` —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.
- Поля тяжести увечья и постоянных последствий в authoritative листе — не
  закрыты backend-контуром.

### Оглушение, потеря сознания и воля

- Frontend-модель knockout и проверки истощения — `FRONTEND`.
- PHP producer состояния и проверка Воли — `BACKEND`
  (`GameStrikeConsequences`, `GameTurnTicks`); полный E2E — `E2E_MISSING`.
- Дробящий урон может напрямую вызвать потерю сознания через
  `blunt_unconscious`; это разрешённый второй producer, не отменяющий
  `check-exhaustion.outcomes`.
- Порядок «сначала записать истощение/рану/оглушение, затем проверить Волю и
  увечье» — канон требует закрепить backend-сценарием.

### DOT и конец хода

- Frontend DOT-сервисы и UI — `FRONTEND` / `MOCK`.
- PHP end-turn action с кровопотерей, свёртыванием и DOT —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.
- Авторитетная запись результата DOT в Character/NPC —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.
- Автоматический вызов проверок после DOT —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.

Frontend blood-clotting сейчас заменяет состояния через runtime API, но
локальный `version` после таких замен не обновляется перед расчётом
`bleedTotal`. Поэтому даже frontend-конец хода пока нельзя считать
полностью подтверждённым.

## Ожидание, process и продолжение

- Ожидание ответа на pairwise-check и защитную реакцию — `BACKEND`.
- Незавершённые generic process и их очистка при end battle/stop session —
  `BACKEND`.
- Frontend `CommittedActionSession` для частичного действия — `FRONTEND` /
  `MOCK`.
- Authoritative PHP «долг действия»: списать доступное ОД, ждать продолжения,
  продолжить или оборвать сессию —
  `FRONTEND + BACKEND_MISSING + E2E_MISSING`.
- Передача хода как отдельное полноценное боевое действие — требует проверки
  backend-контракта и пока не считается `E2E`.

Generic PHP process имеет только `open/resolved/cancelled` и сам не считает
эффект листа. Его нельзя принимать за поддержку долгого действия с частичным
списанием ОД или за combat wait.

## Магия

Магия намеренно не входит в гейты P1–P4 физического боя, но её готовность
нужно отслеживать отдельно:

- Spell-контракт, импорт representative slice, редактор и изучение —
  `FRONTEND` / `MOCK` по M1–M5b.
- Frontend runtime каста: сложность, source/path, компоненты, касание,
  первый эффект, duration, upgrades и deviation — `FRONTEND`.
- Authoritative PHP Rule contract, publish и revision round-trip — `TODO`
  по M10 `spell-roadmap.md`.
- Authoritative Game cast action и запись эффекта в Character/NPC — `MISSING`.
- Контентный проход каталога — `IN_PROGRESS` по M11; полный каталог и
  runtime-режимы за representative slice — `OPEN` / `DEFERRED`.

Наличие frontend `SpellCastService` и `SpellCastExecutionService` не означает
готовность серверного сотворения.

## Frontend и сквозная готовность

Frontend Game содержит страницы, боевые диалоги, расчётные сервисы, mock API
и mock realtime. Подключение к authoritative PHP идёт через единый вход
`game.runAction` (фазы `declare`/`respond`, виды `action`/`strike`/`wound`),
`game.endTurn`, `game.continueAction`, `game.abortAction`,
`game.getBattleSheets` и SSE `/game/sync`. Отдельные `game.declareStrike`,
`game.resolveStrike`, `game.declareWideStrike` и `game.resolveWideStrike`
удалены.

Сквозной сценарий подтверждён на двух аккаунтах
([`game-plan-33.md`](game-plan-33.md), P4):

```text
пользователь A
  → реальный HTTP
  → Game command
  → PHP transaction
  → Character actual / NPC version
  → delivery
  → пользователь B видит новую версию
```

Подтверждены: реальный frontend Game API поверх боевых действий, два
независимых login/account, multi-user delivery результата атаки, перерисовка
actual Character/NPC после mutation, stale-version конфликт в браузере и повтор
команды из браузера. Переход membership в `changes_pending` после боевого
изменения отдельно не проверялся.

Остаток — восстановление SSE после обрыва: `GameChatTab.recoverRealtime()`
идёт через `port.sync()`, который в real-режиме недоступен.

## Гейты полноценного физического боя

### Gate P1 — вычисление атаки

Закрыто:

- defense slots;
- resistance layers;
- durability/reliability;
- срез надёжности;
- penetration;
- block effect;
- отдельные правила `strike`, `throw`, `shoot` (срез дистанции/боеприпасов);
- legality выбранного block item: `GameStrikeRules::assertBlockSelection`
  проверяет строку инвентаря и профиль;
- action-point spend и eligibility реакции.

Остаётся `pay_sr_vs_reliability`: handler в Mechanic зарегистрирован, боевой
путь Game его не вызывает (`BACKEND_MISSING`).

### Gate P2 — прямые последствия

Нужно закрыть:

- producer произвольных состояний;
- рану;
- оглушение;
- шок;
- knockout;
- автоматическую проверку Воли;
- автоматическую проверку увечья.
- единый атомарный порядок immediate state effects и derived effects.

Порядок должен быть атомарным:

```text
расчёт удара
→ запись всех непосредственных состояний
→ проверка производных последствий
→ запись производных состояний
→ закрытие команды
```

### Gate P3 — ход

Нужно закрыть:

- завершение хода;
- свёртывание;
- кровопотерю;
- DOT;
- продолжение/срыв долгого действия;
- передачу хода.
- dimensional parity PHP ↔ frontend, включая отрицательные размеры,
  floor-at-zero, division и JSON round-trip.

### Gate P4 — реальная доставка

Подтверждено на двух независимых cookie-профилях при `VITE_API_MODE=real`
([`game-plan-33.md`](game-plan-33.md), 2026-10-28):

- frontend над PHP API;
- два пользователя;
- персонаж и NPC;
- `1 → 1` и `1 → N`;
- dodge/block/ignore;
- stale versions;
- replay/idempotency;
- обновление листов после атаки.

Остаток — восстановление SSE после обрыва: `GameChatTab.recoverRealtime()`
идёт через `port.sync()`, который в real-режиме недоступен.

## Ближайший порядок работ

Гейты P1–P4 закрыты, кроме `pay_sr_vs_reliability` и восстановления SSE.
Дальше — бэклог атак вне гейтов:

1. Требования оружия: статический `weapon.min_action_cost` в контенте и
   редакторе не задан, поэтому пол удара берётся из `min_total_action_cost`
   карточки, `MIN_STRIKE_AP` и модификаторов предмета (`MinActionCostOp` →
   `GameActionResourceResolver::minimums`). `min_strength` Game по-прежнему не
   отклоняет атаку generically — работает только `throw` reach; контентные
   свойства проверяются по карточкам.
2. Фильтр характеристического модификатора: `min_occupy_hands` и
   `damage_type_codes` применяются в
   `GameStrikeRules::actionCharacteristicModifier` через необязательный
   `$attackContext` (`attackFilterContext`, `occupyHands`); учитываются эффекты
   самой карточки и купленных дочерних навыков (`parent_ability_code`,
   уровень ≥ 1). Контекст и уровни прокинуты в open, close и resolution.
3. `max_success_rating` типа урона — в чате `srCap => null`.
4. `push` («Толчок», «Толкающий удар») — кода нет, нужен дизайн.
5. Модель рук — удержание задаётся `occupy_hands` (`min`/`max`) предмета,
   дополнительная рука действия — `weapon_profiles[].extra_hands`; итог =
   удержание + `extra_hands`. `GameWoundActions::handCount()` по-прежнему
   возвращает 2 как заглушку.
6. Контент `ammunition_requirements` для `shoot`; профили атаки щитом.
7. Модификаторы действия: контентный эффект `action_modifier` (trigger,
   scope, необязательная проверка, последствия). Список считает сервер
   (`game.getActionOptions`), выбор объявляется вместе с ударом и хранится на
   строке (`action_modifiers`); при закрытии `GameActionModifierRunner`
   исполняет последствия по их типам (`self_damage`, `skip_after_action`).
   Не поддержаны серия и широкий удар; флага «защита источника защищает от
   внутреннего урона» в спеке и редакторе ещё нет, поэтому `ignore_defense`
   игнорирует все слои.

Экономика, battleground, `N → 1`, `N → N` и полный каталог магии не входят в
минимальный гейт P1–P4.

## Отдельные известные расхождения и блокеры

- `GameBattleOrder` сортирует по `roll.base`; это не пропуск размера, а
  зафиксированный порядок G21. Отдельного tie-break по `roll.size` текущий
  канон не требует.
- `GameStrikeRules::assertBlock()` проверяет только, что rule code является
  weapon/shield; экипировку строки инвентаря и допустимость block profile
  проверяет `assertBlockSelection`.
- `putDamageSplit` пишет только обычные state rows `{stateRuleCode, value}`;
  sidecar раны и `heldBy` закрыты [`game-plan-31.md`](game-plan-31.md).
- NPC mutation идёт через `applyToDocument()` и `replaceVersion()`, тогда как
  player mutation идёт через Character `apply()` с его validation path.
  Это требует отдельного invariant-теста для одинаковой приемки боевого
  эффекта.

## Доказательства текущего среза

- [`game-system.md`](game-system.md)
- [`game-roadmap.md`](game-roadmap.md)
- [`character-system.md`](character-system.md)
- [`game-plan-20.md`](game-plan-20.md)
- [`game-plan-22.md`](game-plan-22.md)
- [`game-plan-23.md`](game-plan-23.md)
- [`game-plan-24.md`](game-plan-24.md)
- [`game-plan-25.md`](game-plan-25.md)
- [`game-plan-26.md`](game-plan-26.md)
- [`game-plan-27.md`](game-plan-27.md)
- [`character-plan-11.md`](character-plan-11.md)
- [`rule-content-plan-06-wounds.md`](rule-content-plan-06-wounds.md)
- [`rule-plan-07.md`](rule-plan-07.md)
- [`docs/specs/check-design.md`](../specs/check-design.md)
- [`spell-roadmap.md`](spell-roadmap.md)
