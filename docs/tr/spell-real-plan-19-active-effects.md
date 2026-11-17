# План M19 — длительные эффекты: цель, срок и тик

**Статус:** ~СДЕЛАНО~, 2026-11-16. Срез делает каркас длительного эффекта:
отдельная строка эффекта, цель, срок в ходах цели и истечение. Применение
эффекта к листу цели — следующий срез.

**Реализация:** ~PHP~, ~VUE~ (read-only список эффектов в боевой карточке).
`getActiveSpells` по-прежнему отдаёт только поддержание; эффекты читаются
отдельным `getActiveEffects`.

**Опираться на:** ~PHP~ (`GameSpellDurationRules`, `GameSpellTouchFinish`,
`GameSpellCast`, `GameTurnEnd`, `GameActiveSpell*`, `GameStrikeRepositories`,
`GameBattleMutator`, `GameBattleCleanup`), контент ревизии 60
(`tearing` … `accelerated-metabolism`).

**Родитель:** [`spell-real-roadmap.md`](spell-real-roadmap.md), шаг M18 и
[`spell-nolomancy-design-01.md`](spell-nolomancy-design-01.md).

## 1. Границы

Входит:

- общая таблица `game_active_effect` — не только для заклинаний;
- создание строки при успешном касте `lingering`;
- цель эффекта: цель касания, явная цель каста или сам кастер;
- срок в тиках и уменьшение в конце хода цели;
- истечение с удалением строки и сообщением в чат;
- очистка при конце боя, сессии и выбытии цели.

Не входит:

- применение эффекта к листу цели: состояния, проверки, модификаторы;
- эффект-специфичный тик (проверка, урон, снятие концентрации);
- постоянные эффекты без срока;
- «Дистанция» и улучшения школы.

Read-only список эффектов в боевой карточке добавлен отдельной правкой
2026-11-17: цель видит, что на ней лежит, без применения эффекта к листу.

Все карточки Ноломансии остаются `needs_work`: срок теперь работает, но
эффект ещё не реализован.

## 2. Решения

1. **Контейнер — отдельная таблица.** `game_active_effect` не привязана к
   заклинаниям: `source_type` (`spell` сейчас), `source_rule_code`, `strength`
   и `context` позволяют позже писать эффекты предметов, действий и окружения.
2. **Состояние на листе — вычислительный слой, а не контейнер срока.** В M16
   эффект не пишется в лист: источник истины — строка эффекта, проекция
   пересчитывает его на чтении. Это же правило сохраняется для `lingering`.
   Когда эффект по смыслу станет состоянием, проекция добавит временную строку
   состояния, не записывая её в лист.
3. **Тик — конец хода цели.** «1 ход» = один собственный ход цели,
   «1 минута» = 10 тиков, «1 час» = 600. Это совпадает с текстами карточек
   «в конце каждого своего хода». Эффект-специфичный тик идёт до уменьшения
   срока: последний ход тоже должен отработать.
4. **Пропуск хода создания.** Если эффект наложен на себя в свой ход, первый
   конец хода не тикает: сравниваются `started_round`, `started_participant`
   и цель.
5. **Срок обязателен в этом срезе.** `lingering` без `limit` или с неизвестной
   единицей — отказ до записи. Постоянные эффекты — отдельный срез.
6. **Поддержание не трогаем.** `game_active_spell` и его инварианты (источник,
   предел пути, вопросы поддержания) остаются как есть. `lingering` не занимает
   источник после каста и не входит в предел активных волшебств.
7. **UI — только чтение.** Строка эффекта видна в чате и в ответе каста
   (`activeEffect`); в боевой карточке показывается read-only список
   `game.getActiveEffects` (название карточки и остаток ходов). Применение
   к листу и редактор эффектов — следующий срез.

## 3. Срезы

### 19.1. Таблица и DTO

- `GameActiveEffectTable` / `GameActiveEffectSchema`;
- `GameActiveEffectNew`, `GameActiveEffectRecord`, `GameActiveEffectView`;
- `GameActiveEffectRepository`: `add`, `findById`, `findForBattle`,
  `findForSession`, `findForTarget`, `updateTurnsLeft` (CAS),
  `deleteWithVersion`, `deleteForBattle`, `deleteForSession`,
  `deleteForTarget`;
- схема в `GameModuleSetup`, `GameMysqlFixture`, `GameMysqlTables`.

Колонки: `source_type`, `source_rule_code`, `applier_kind`, `applier_id`,
`target_kind`, `target_id`, `duration_type`, `duration_unit`, `turns_total`,
`turns_left`, `strength`, `context`, `started_round`, `started_participant`,
`state_version`.

### 19.2. Срок

- `GameSpellDurationRules`: `lingering` поддержан;
- `lingerTicks(SpellDuration)`: `turn` / `minute` / `hour` → тики;
- нет лимита или единица неизвестна — `GameInvalidException`.

### 19.3. Создание эффекта

- `GameActiveEffectCreate::fromCast`;
- вызов из `GameSpellTouchFinish::finish` и `GameSpellCast::finish`;
- цель: цель удара касания, иначе явная цель каста, иначе кастер;
- в ответ каста и удара добавляется `activeEffect`;
- чат наложения — `GameActiveEffectChat::appliedAt`.

### 19.4. Тик и очистка

- `GameActiveEffectTick::tick` в `GameTurnEnd::end` для актора, чей ход
  закончился;
- уменьшение `turns_left`, удаление на нуле, чат `expiredAt`;
- `GameBattleMutator::end` и `GameBattleCleanup::deleteWithSession` снимают
  строки;
- выбытие цели из боя снимает эффекты на ней;
- выбытие наложившего эффект строку не снимает.

### 19.5. Контент и документы

- `tools/rule-migrate-61.php`: у `lingering`-карточек из `content_note`
  убирается фраза о неподдержанной длительности; «эффект не реализован»
  остаётся;
- обновление роудмапа и концепции;
- `composer docs-check`, `composer maps-check`.

### 19.6. Проверки

- юнит: срок и единицы;
- юнит: тик, пропуск хода создания, истечение;
- MySQL: каст `tearing` по NPC, цель и срок в строке, конец хода цели,
  истечение, свободный источник после каста, очистка боя;
- `composer stan`, `composer test:unit`;
- полный `bash tools/test-full.sh` — после ручной проверки и подтверждения.

## 4. Журнал

| Дата | Изменение |
| --- | --- |
| 2026-11-16 | План заведён: отдельная таблица `game_active_effect`, цель, срок в ходах цели, тик и истечение; применение эффекта к листу — следующий срез. |
| 2026-11-16 | 19.1–19.4: таблица, DTO, репозиторий, `GameActiveEffectCreate`, `GameActiveEffectTick`, чат; `lingering` поддержан; очистка боя и выбывшей цели. Тесты: срок и единицы, касание по NPC, каст на себя с пропуском хода создания. `composer stan` без ошибок, `composer test:unit` — 1179 тестов / 3452 проверки (1 skip), `GameStrikeMysqlTest` — 203 теста / 9692 проверки. |
| 2026-11-16 | 19.5: ревизия 61 убрала из 15 `lingering`-карточек оговорку о неподдержанной длительности; статус `needs_work` и «эффект не реализован» сохранены. Полный прогон не запускался — после ручной проверки. |
| 2026-11-17 | Ревизия 62 исправляет форму `requirements` всех карточек Ноломансии: плоский список обёрнут в блок `{level: 1, requirements}`. До этого `AbilityRequirements` читал плоские строки как пустые блоки уровня 0, поэтому требование «Физиология 1» не работало, а карточка в UI падала на `requirementsSummary`. `tools/rule-migrate-60.php` исправлен для новых выгрузок. |
| 2026-11-17 | Ревизия 63 переводит требование `fiziologiya` в `has_knowledge physiology`: «Физиология» — шаблон знания `znanie`, а не покупаемый навык. В редакторе причина недоступности заклинания теперь видна в строке; цепочка требований ведёт к «Знание о…». Знание сохраняется в choices (`fieldCode`/`slots`) и учитывается в гейте каста. |
| 2026-11-17 | Сохранение знания: `CharacterSaveKeys` пропускает `fieldCode` и `slots` в строке способности; без этого NPC/персонаж падал с `INVALID_PARAMS: Unknown parameter: abilities.N.fieldCode`. Добавлен регрессионный `CharacterSaveKeysTest`. |
| 2026-11-17 | Исправлена цель отложенного касания: при паузе на насыщение `GameSpellCast::resolveWork` брал `prepared->target` (у касания он null) и создавал эффект на кастере. Теперь эффект создаётся на `prepared->damageTarget` — цели касания. Добавлен `testTouchLingeringEffectAfterSaturationTargetsTouchTarget`. |
| 2026-11-17 | «Простое касание» больше не наносит урон оружием: `GameStrikeSingleLoad` читает `spell_touch` действия, `GameStrikeSingleHits` обнуляет урон/травмы и не пишет операции листа цели, `attack_sr_bonus` идёт в РУ; чат касания — «попадает/промахивается» без оружия и истощения. Добавлен `testSimpleTouchDealsNoWeaponDamage`. |
| 2026-11-17 | Read-only список эффектов: `game.getActiveEffects`, `GameActiveEffectQueryHttp`, `GET` в `CombatCardPanel` и подпись «карточка (осталось ходов: N)». Применение эффекта к листу по-прежнему вне среза. |
