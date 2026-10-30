# План 38 — состояния из действия и подавление состояний

**Статус:** ~СДЕЛАНО~

**Реализация:** ~PHP~, ~VUE~

**Опираться на:** ~PHP~

## Согласованные границы

- Сервер исполняет состояния из формы карточки действия, а не по кодам правил:
  `action_effects[].type = apply_state`, операция `operations[].type = posture`
  и семантика `combat_action = recover-stability`.
- Операции `movement` и `turn` остаются вне серверного сценария (координаты и
  фазинг — `OPEN`). Неизвестные эффекты по-прежнему отвергаются: сервер честно
  отказывает, а не игнорирует карточку молча.
- Подавление состояний — свойство спеки, а не связка кодов: у состояния есть
  `suppressed_state_codes`. Пока состояние активно, перечисленные коды
  снимаются при его получении и не могут быть получены.
- Подавление исполняет `CharacterActualStateRows` — единственный писатель строк
  состояний. Поэтому правило действует для всех продюсеров: действий, ударов,
  проверок и тиков, — а не только для нового сценария действия.
- Живая ревизия контента этим планом не меняется. Карточки `lying`,
  `unstable`, `stand-up` и `recover-stability` — отдельный шаг.

## Форма

```text
apply_state { state_code, amount? }   → putState: int → value, {base,size} → value, нет amount → флаг
posture { posture: standing_or_prone } → есть строка lying → removeState по id; нет → putState(lying)
combat_action: recover-stability      → removeState всех строк состояния с флагом unstable
```

Подавление:

```text
putState(X) при активном Y, у которого X ∈ Y.suppressed_state_codes → строка не пишется
putState(Y), у которого Y.suppressed_state_codes = [X, …]          → строки X снимаются, затем пишется Y
```

## Что сделано

- `Rule/Dto/Spec/StateSpec.php` и `Rule/Spec/RootSpecs.php` — поле
  `suppressed_state_codes` (нет ключа — пустой список, старые ревизии читаются).
- `Character/Service/CharacterActualStateRows.php` — снятие подавляемых строк
  при получении подавителя и отказ писать подавленное состояние, пока подавитель
  активен.
- `Game/Service/GameActionStates.php` — операции листа из `apply_state`,
  `posture` и `recover-stability`. Флаги `lying`/`unstable` ищутся по спеке
  состояний (`StateSpec::isLying()` / `isUnstable()`), кодов правил в Game нет.
- `Game/Service/GameActionCards.php` — гейт пропускает `PostureOperation` и
  `ApplyStateEffect`, остальные операции и эффекты отвергает.
- `Game/Service/GameActionPerform.php` — операции собираются в `prepare()` и
  пишутся тем же `apply`, что и списание ресурсов.
- Frontend: поле в `StateSpec.ts`, мультиселект в `StateEditor.vue`, подпись на
  карточке состояния, проверка ссылок `suppressed_state_codes` в
  `RuleValidationService`.

## Тесты

- `CharacterActualStateTest`: подавитель снимает строки; подавленное состояние
  не пишется; после снятия подавителя пишется снова.
- `GameActionStatesTest`: флаг, число и размерное значение; отсутствие `amount`;
  переключение `posture`; снятие Неустойчивости; отказы на чужую операцию,
  неизвестную позу, отсутствующий флаг и не-live состояние.
- `GameStrikeMysqlTest::testActionWritesStatesAndSuppressesUnstable`: полный
  сценарий действия — набрать Неустойчивость, лечь (она снимается), попытка
  набрать при лежачем (не пишется), `recover-stability`, встать, набрать снова;
  проверяются строки листа, ОД и версии.
- Frontend: `ruleValidation.test.ts` — живая и отсутствующая ссылка
  `suppressed_state_codes`.

## Остаток

- Карточки `lying`, `unstable`, `stand-up` (3 ОД) и `recover-stability` (2 ОД)
  уже были в ревизии 20; подавление и проверка прироста добавлены миграцией
  `tools/rule-migrate-20.php` (ревизия 21) в
  [`game-plan-39-state-increase-check.md`](game-plan-39-state-increase-check.md).
- Проверка Ловкости на прирост Неустойчивости — там же.
- Редактор `action_effects` и `operations` у способности — существующая дыра,
  общая с уже посаженным `action_modifier`.
