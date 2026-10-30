# План 39 — проверка прироста состояния

**Статус:** ~СДЕЛАНО~

**Реализация:** ~PHP~, ~VUE~

**Опираться на:** ~PHP~

## Границы

- Проверка прироста задаётся спекой, а не кодом: `StateSpec.increase_check_code`
  ссылается на карточку проверки, её `difficulty_input` — `from_state` с кодом
  этого же состояния, `outcomes` — исход.
- Сложность — значение состояния после прироста. Целое состояние приводится к
  размерному как `x|0` (`{base: x, size: 0}`) — то же правило, что у проверки
  истощения (`GameStrikeConsequences::will()`).
- Решает, применилось ли состояние, Character: его лист, его правило подавления.
  Game не смотрит строки листа и не повторяет правило подавления.
- Сначала предпросмотр без записи, затем один apply с состоянием и исходом:
  лишних строк в базе и второй версии листа нет.
- Срез включает действия (`apply_state`) и ручные правки ГМ. Удары и тики не
  подключаются: там уже есть своя проверка истощения, общий перехват дал бы
  двойной бросок. Унификация — шаг вместе с Толчком.

## Форма

```text
state.unstable.increase_check_code = "check-unstable"

check-unstable:
  parent_check_code = "check-dexterity"
  allowed_modes     = "solo"
  difficulty_input  = { "kind": "from_state", "state_code": "unstable" }
  outcomes          = [{ "max_rating": 0, "state_rule_code": "lying" }]
```

Порядок рантайма:

```text
операции действия/правки
  → Character.previewDocument: что применилось и сколько стало (без записи)
  → после > до и есть increase_check_code → бросок {base: после, size: 0}
  → исход проверки (первый подходящий по рейтингу) → putState(исход)
  → один apply: состояние + исход
```

Если состояние подавлено (на листе уже лежачее), Character не пишет строку,
отчёт показывает `after == before`, и проверки нет.

## Что сделано

- `Rule/Dto/Spec/StateSpec.php` — поле `increase_check_code`; `RootSpecs` его
  разбирает. `check_code` остаётся проверкой, которую вызывает продюсер
  (истощение, увечье), и к приросту не привязан.
- `Character/Interface/Service/ICharacterActualMutations.php` и
  `CharacterActualMutations` — `previewDocument`: чистый предпросмотр и отчёт
  `{before, after, check_code}` по строкам состояний. Подавление считает
  Character.
- `Game/Service/GameStateIncreaseRule.php` — проверка связки: живая карточка,
  `from_state` на это состояние, `number`/`sum`, выбор исхода по рейтингу.
- `Game/Service/GameStateIncreaseChecks.php` — бросок и операция исхода.
- `Game/Service/GameActionPerform.php` — предпросмотр и проверка в подготовке
  действия; броски уходят в итог и в чат.
- `Game/Service/GameManualSheetActions.php` — то же для ручных правок ГМ.
- `Game/Service/GameActionChat.php` — сообщение броска в тред хода.
- `RuleSpace/Service/RuleStateReferences.php` + `RuleRevisionSpecGuard` —
  публикация проверяет ссылки всего состава: `increase_check_code` → живая
  проверка, её сложность → это состояние, состояние `number`/`sum`, состояния
  исходов → живые. Битый состав не публикуется.
- Frontend: поле в `StateSpec.ts`, «Проверка прироста» в `StateEditor.vue`,
  подпись на `StateCard.vue`, ссылки `increase_check_code` и состояний исходов
  в `RuleValidationService`.
- `tools/rule-migrate-20.php` — ревизия 21: `unstable.increase_check_code`,
  `lying.suppressed_state_codes = ["unstable"]`, карточка `check-unstable`,
  флаг `unstable_check` переезжает с `check-dexterity` на новую проверку.

## Тесты

- `GameStateIncreaseRuleTest`: связка, сложность, исход; отказы на чужую
  сложность, флаг, `max`, отсутствующую проверку и отсутствующее состояние исхода.
- `CharacterActualStateTest`: отчёт предпросмотра; подавленный прирост виден как
  `after == before`.
- `GameStrikeMysqlTest::testActionIncreaseCheckFallsAndSuppressesUnstable`:
  действие набирает Неустойчивость, провал кладёт лежачее и снимает
  Неустойчивость; пока лежит — прироста и броска нет; бросок есть в итоге и в чате.
- `GameStrikeMysqlTest::testManualIncreaseCheckFallsAndSuppressesUnstable`:
  ручная правка ГМ идёт тем же правилом.
- `RuleRevisionSpecGuardTest`: отказ публикации на отсутствующую проверку, чужую
  сложность и отсутствующее состояние исхода; живая связка проходит.
- Frontend: `ruleValidation.test.ts` на ссылки `increase_check_code` и исходов.

## Остаток

- Удары и тики: подключить проверку прироста и унифицировать проверку истощения
  (`GameStrikeConsequences::will()`, `GameTurnTicks::willForDelta`). Отдельно
  решить, как выразить в спеке «не бросать, пока худший исход уже активен»
  (сейчас это `!$wasUnconscious` в PHP и `shouldSkipExhaustionCheck()` в моке).
- Толчок и Толкающий удар: продюсеры Неустойчивости (`rule-content-plan-10`).
- Проверка Ловкости на прирост из карточки удара — после Толчка.
