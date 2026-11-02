# План 43 — Процессы как тип действия

**Статус:** ~ПЛАН~

**Реализация:** ~PHP~, ~VUE~, ~МОК~

**Опираться на:** ~PHP~, ~НЕ ОСНОВА~

## Границы

- Процесс — не второй боевой движок, а сессия шагов поверх уже принятых
  `game.runAction`, удара, списания ОД и записи листа. Удар внутри процесса —
  обычный удар с довеском шага.
- Работают шаги, стоимости, переходы, `failure`, `interruption`,
  `completion_effects` и счётчики сессии.
- Не входят: операции `movement` (`run`, `sprint`), battleground и координаты,
  поддержание заклинаний, `N → N`.
- Frontend `ProcessSessionService` и `ComboProcessService` — мок, не контракт:
  сервер считает шаги, стоимости, счётчики и модификаторы удара сам.

## Что уже есть

- Контент ревизии 27: `kombinatsiya-udarov`, `seriya-udarov`,
  `mnozhestvo-udarov`, `run`, `sprint` — шаги, стоимости, `transition`
  (`chain`/`custom`), `failure`, `interruption`, `completion_effects`,
  `repeat_weapon_circumstance`.
- `ProcessSpec`, `ProcessStep`, `StepCost`, `ProcessTransition` в Rule и
  `ProcessSpecs::read` — разбор контента.
- `game_process` — строка `session/battle/participant/status`, без кода
  правила, шага и счётчиков. `open/resolved/cancelled` и очистка на
  `endBattle`/`stopSession`/`leave` уже есть.
- Долг ОД (`GameActionDebt`), удары (`GameStrikeOpen`/`GameStrikeSingleClose`),
  `pending_action_effects` и эффект
  `after_action_until_resource_spent_check_modifier`.
- Фронт: панель процессов, `ProcessSession`, диалоги старта/продолжения,
  мок `getProcessSessions`/`setProcessSession`. Реальный `GameApi` возвращает
  пустой список и `throwPhpUnsupported` на запись.

## Контракт правила

`ProcessSpec` расширяется типизированными полями, без веток под карточку:

```text
process.counters:      [{ code, initial }]                  // состояние сессии
process.target_mode:   "any" | "single" | "last"            // default any
process.steps[].strike:
  advantage:           Formula                              // к помехам/преимуществам попадания
  extra_successes:     Formula                              // успехи размера броска
process.steps[].requires_counters: [{ counter_code, min }]   // шаг недоступен ниже порога
process.steps[].counter_on_success: [{ counter_code, delta }]
process.steps[].counter_on_failure: [{ counter_code, delta }]

Formula = { type: "fixed", value }
        | { type: "counter", counter_code, factor?, divisor?, cap?, floor? }
```

- Счётчики живут только в сессии процесса, не в листе и не в `states`
  (`rule-content-plan-09-melee-speed.md`).
- `requires_counters` фильтрует `availableSteps`; сервер отдаёт список
  доступных шагов, клиент его не вычисляет.
- `target_mode: single` — цель фиксируется первым ударом процесса; смена цели
  отклоняется.
- `repeat_weapon_circumstance` уже типизирован: счётчики использований
  оружия ведёт сессия, а `GameStrikeOpen` получает помехи обстоятельств.
- Старые ревизии без новых полей читаются с умолчаниями: нет счётчиков, нет
  `strike`, `target_mode: any`.

## Контракт сессии

`game_process` получает поля:

```text
process_rule_code      string(128)
current_step_code      string(64)
step_status            pending | completed
counters               json
weapon_use_counts      json
last_target_type       string(16) | null
last_target_id         int | null
```

Правила:

- Один открытый процесс на участника в бою. Второй `start` — `GAME_CONFLICT`.
- Процесс несовместим с открытым долгом ОД: при висящем слоте `start`
  отклоняется.
- Стоимость шага списывается сразу и целиком. Не хватило ОД — отказ, долг на
  шаг не растягивается (долг остаётся механизмом одиночного действия).
- Счётчики не пишутся в Character/NPC; applied effects не откатываются при
  прерывании.
- Переход считается по `transition` из спеки: `chain` (радиус и направление),
  `free`, `custom` (рёбра `from → to`).
- `failure`:
  - `end_action` — сессия `cancelled`, шаг не завершён;
  - `restart_from_first` — сессия остаётся открытой, шаг сбрасывается на
    `start_step_code`;
  - на промахе удара внутри шага действует тот же `failure`.
- `exit_step_codes` — завершённый шаг из списка переводит сессию в
  `resolved` и применяет `completion_effects`.
- `interruption`:
  - `normal` — прерывание разрешено, применяются `completion_effects` процесса;
  - `emergency` — прерывание разрешено только при непустых `effects` шага,
    применяются они;
  - очистка на `endBattle`/`stopSession`/`leave` эффектов не применяет.

## Команды

Отдельные команды вместо клиентского `setProcessSession` — сервер сам решает,
что доступно:

```text
game.getProcesses       чтение: сессии по участникам, шаги, стоимости,
                        счётчики, готовые модификаторы удара
game.startProcess       карточка + шаг (по умолчанию start_step_code) + цель
game.continueProcess    следующий шаг для процесса без удара
game.abortProcess       normal | emergency
```

- Удар внутри процесса объявляется обычным `game.runAction` (вид `strike`) с
  `processId` и `processStepCode`; open проверяет шаг, цель и доступность,
  close двигает счётчики и шаг.
- Для шага с `strike` вызов `continueProcess` отклоняется: шаг закрывает удар.
- `expectedVersion` и идемпотентность — как у остальных боевых команд;
  изменение строки процесса входит в версию боя.
- Ответ команды и `game.get`/`game.getRoster` отдают сессию процесса, чтобы
  панель и чат не собирали её из мока.

## Чат

- Старт: «X начинает процесс «…», шаг «…» — N ОД».
- Продолжение и завершение шага — отдельными строками с ценой.
- Провал: «… процесс «…» сорван» с причиной (`failure`).
- Прерывание: «… прерывает процесс «…»».
- Завершение: «… завершает процесс «…»» с эффектами завершения.

## Срезы

1. **Жизненный цикл и боевые процессы без счётчиков** — `seriya-udarov`,
   `mnozhestvo-udarov`: поля `game_process`, `get/start/continue/abort`,
   списание ОД, переходы, `failure`, `completion_effects`, помехи повтора
   оружия, очистка, чат, тесты, реальный API на фронте. Контент не меняется.
2. **Комбо** — `ProcessSpec` v2 (`counters`, `target_mode`, `strike`,
   `requires_counters`, `counter_on_*`), парсер и редактор, ревизия 28,
   рантайм счётчиков и одной цели, серверные модификаторы удара, снятие
   жёстких констант `ComboProcessService`.
3. **Движение** — `run`/`sprint` после контракта движения; здесь только явный
   отказ на операции `movement`, чтобы карточки не запускались молча.

## Тесты

- Unit: `ProcessRules` — переходы (`chain`/`free`/`custom`), `exit`,
  `failure`, стоимости, формулы счётчиков; парсер `ProcessSpec` v2 и умолчания
  старых ревизий.
- Game MySQL: старт/продолжение/прерывание, нехватка ОД, второй процесс,
  процесс при долге, шаг-удар (успех/промах), счётчики и цель комбо, повторы
  оружия, `completion_effects`, очистка на `endBattle`/`stopSession`,
  идемпотентность, CAS, строки чата.
- Frontend: реальные методы API вместо мока, панель и диалоги на серверных
  шагах и модификаторах; `npx vitest run` по затронутым файлам.
- Полный `bash tools/test-full.sh` — после ручной проверки.

## Риски

- Спека v2 и старые ревизии: без умолчаний `process` перестанет читаться.
- Счётчики легко утащить в `states` или лист — это ломает канон сессии.
- `completion_effects` серии — `pending_action_effects`; проверить, что хвост
  срабатывает после завершения процесса, а не только после удара.
- Очистка процесса на `endTurn` не делается: конец хода процесс не рвёт,
  диалог «Продолжить/Бросить» — начало своего хода.
- Поддержание заклинаний пока мок: взаимное исключение процесса и магии
  закрывается вместе с рантаймом магии, а не здесь.
- `movement`-шаги отклоняются явно; иначе `run`/`sprint` спишут ОД без эффекта.
