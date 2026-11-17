# План M20 — эффект на лист цели: состояния

**Статус:** ~В РАБОТЕ~, 2026-11-17. Срез 20.1 закрыт на одной карточке
(«Слепота живого»): форма `spell.effects[].type = state`, состояние на срок
`lingering` приходит в лист цели. В 20.2 состояние научилось менять статус
чувства: «Слепота живого» лишает цели зрения. Остальные состояния, проверки
цели и тик-эффекты — следующие срезы.

**Реализация:** ~PHP~, контент ревизии 64. ~VUE~ не менялся: состояние
появляется в `sheet.states` и рисуется существующей карточкой.

**Опираться на:** ~PHP~ (`SpellSpecs`, `SpellStateEffect`, `GameSpellEffect`,
`GameActiveEffectStates`, `CharacterSheetLive`, `GameCharacterProjection`,
`GameNpcProjection`, `GameNpcView`, `GameStrikeSheets`,
`GameStrikeParticipants`, `GameStrikeResolutionActors`), контент ревизии 64
(`blindness`, `living-blindness`).

**Родитель:** [`spell-real-roadmap.md`](spell-real-roadmap.md), шаги M18–M19 и
[`spell-nolomancy-design-01.md`](spell-nolomancy-design-01.md) §7.

## 1. Границы

Входит:

- форма эффекта-состояния в карточке заклинания;
- проекция состояния из строки `game_active_effect` в лист цели;
- влияние состояния на характеристики, лимиты ресурсов и проверки;
- снятие состояния вместе с истечением строки эффекта.

Не входит:

- проверки цели при наложении и по ходам («Чесотка», «Тошнота», «Боль»);
- тик-урон и затухание («Разложение живого»);
- сопротивление и поглощение («Поглотители магии»);
- постоянные эффекты без срока;
- редактирование эффектов из UI;
- «Дистанция» и улучшения школы.

## 2. Решения

1. **Эффект — форма карточки, а не отдельный код Game.** В
   `spell.effects[]` добавлен тип `state`:
   `{type: "state", state_code, value?: {from: "power"|"parameter"},
   max?: N}`. Значение по умолчанию — Мощь каста; `max` ограничивает единицы.
2. **`lingering` — единственная длительность для `state`.** `sense` остаётся
   только у `sustained`. Отказ до записи — в `GameSpellEffect::assertSupported`.
3. **Лист не хранит срок.** Строка `game_active_effect` — источник истины.
   Проекция добавляет временную строку состояния на чтении и не пишет её в
   сохранённый лист. Снятие эффекта ничего не откатывает: строки просто нет.
4. **Строка состояния помечается `runtime: true` и `runtimeId`.** Повторный
   пересчёт листа не дублирует состояние: `CharacterSheetLive` пропускает уже
   добавленный `runtimeId`.
5. **Применение — на всех путях чтения листа:** карточки персонажа и NPC,
   боевые снимки (`GameStrikeSheets`), атакующий в проверке попадания
   (`GameStrikeTarget::actorRuntimeStates` → `GameStrikeParticipants`).
6. **Кеш листа в бою не смешивает версии.** Снимок с состояниями не
   кешируется и не читает кеш контекста; листы без состояний кешируются как
   раньше.
7. **Сохранение NPC очищает runtime-строки.** `GameNpcSheetRefresh::mergeRuntime`
   переносит только сохранённые состояния; временные строки эффектов
   отбрасываются.
8. **Лишение чувства — эффект состояния `sense_status`.** Форма
   `{type: "sense_status", sense_code, status}` со статусами `precise`,
   `imprecise`, `vague`, `absent`; статус только усиливается, поэтому
   `absent` не перебивается другим грантом. Для флаговых состояний единицы
   не нужны: `value_type: flag`, строка эффекта даёт значение 1.

## 3. Срезы

### 20.1. «Слепота живого» сквозным путём — сделано

- `SpellStateEffect`, ветка `state` в `SpellSpecs`;
- `GameSpellEffect::runtimeStates` — строки состояний из строки эффекта;
- `GameActiveEffectStates` — поиск по сессии и цели, применение к листу;
- `ICharacterSheets::compute` / `CharacterSheetLive` — параметр
  `runtimeStates`;
- `GameCharacterProjection`, `GameNpcProjection`, `GameNpcView` — состояние в
  карточке;
- `GameStrikeSheets`, `GameStrikeTarget`, `GameStrikeParticipants`,
  `GameStrikeResolutionActors` — состояние в боевых проверках;
- ревизия 64: state-карточка `blindness`, `spell.effects` у
  `living-blindness`.

### 20.2. Остальные чистые состояния

- лишение зрения у «Слепоты живого» — сделано: `sense_status` и флаговое
  состояние `blindness`;
- глухота, паралич, неуклюжесть, слабость (существующая `weakness`);
- штраф зрения у «Слезоточивости»;
- отдельная находка: группа проверок «требует зрения» (сейчас `check_codes`
  не участвуют в отборе помех, работают `characteristic_codes` и
  `includes_hit`). Отсутствие чувства уже видно в листе, но само по себе не
  запрещает проверки: нужна связь проверки с чувством.

### 20.3. Эффекты с проверками цели

- «Чесотка живого», «Тошнота гуманоида», «Боль живого»: проверка при
  наложении и по ходам, эффект-специфичный тик до уменьшения срока.

### 20.4. Тик-эффекты и сопротивление

- «Разложение живого» — урон по ходам;
- «Поглотители магии» — сопротивление и поглощение;
- затухание силы состояния.

## 4. Проверки

- unit: `SpellSpecsTest` — разбор `state` и пустой `state_code`;
- unit: `GameNpcSheetRefreshTest` — runtime-строки не переносятся в снимок;
- unit: `CharacterLiveSensesRuntimeTest` — `sense_status` убирает чувство из
  активных и из бонуса Внимательности;
- mysql: `testLingeringStateEffectReachesTargetSheet` — состояние на цели,
  зрение исчезает из листа и возвращается после истечения срока;
- `composer stan`, `composer test:unit`, `GameStrikeMysqlTest`,
  `GameNpcMysqlTest` + `GameNpcSheetRefreshTest`, `GameWideStrikeMysqlTest`,
  `GameEndOfTurnMysqlTest`;
- полный `bash tools/test-full.sh` — после ручной проверки и подтверждения.

## 5. Журнал

| Дата | Изменение |
| --- | --- |
| 2026-11-17 | Срез 20.1: форма `spell.effects[].type = state`, `GameActiveEffectStates`, проекция в карточки и боевые снимки, ревизия 64 (`blindness`, `living-blindness`). Состояние влияет на проверки и снимается вместе со сроком. |
| 2026-11-17 | Срез 20.2 (начало): эффект состояния `sense_status`; `blindness` стал флаговым состоянием и лишает `sense-vision`. Ревизия 65. Единицы состояния больше не показываются у флага. |
