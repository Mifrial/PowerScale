# Контекст ревью 17 — Roleplay/Game + Roleplay/Character + Roleplay/Home (быстрое ревью)

Волна 2026-08-05. Быстрое ревью оставшихся модулей `Roleplay` — `Game`, `Character`, `Home` —
на `frontend-rules.md` + общее ревью качества. Режим: разбор по пунктам, правки — только
после явного решения пользователя. База — context-11/12/14/15/16 (F17, Service/Instance,
один-экспорт-на-файл, плагинная модель Chat, hosting-контекст, ревизии — домен Space).

Game и Character фактически ещё не разрабатывались (стабы); Home — заглушка.

## Зависимости (декларация пользователя)
- Зависимость от группы Core — всегда ок.
- `Roleplay/Space` → зависит от `Roleplay/Rule`.
- `Roleplay/Character` → от `Roleplay/Space` и группы Messages.
- `Roleplay/Game` → от `Roleplay/Space`, `Roleplay/Character` и группы Messages.

## Проверка зависимостей (по факту) — ОК
- **Game**: импорты только `@/modules/Core/*`, `@/modules/Messages/Chat/*`, `@/modules/Roleplay/Game/*`.
  Space/Character не импортирует (разрешено, не обязано). `Game/init.ts` подключается к Chat
  только через публичные точки `Messages/Chat/init`. Чисто.
- **Character**: только Core + Messages/Chat + self.
- **Home**: Core + Messages/Notifications + Core/UI. ОК.

## Находки

### P1
- **P1-1. Инверсия преимуществ/помех в `computeRollResult`** (`Game/Service/RollService.ts:151-158`).
  При `adv>0`: `adjusted.sort((a,b)=>b-a)` + `splice(0, adv)` — убираются **наибольшие** кубы,
  остаётся заниженный итог. При `adv<0`: `sort asc` + `splice(0, |adv|)` — убираются наименьшие,
  итог завышен. Т.е. «преимущество» ослабляет, «помеха» усиливает.
  При этом UI-подписи (`Component/DiceRollResult.vue:32,38-42`) утверждают обратное:
  `adv>0` → «убрано как худшее». Код и подписи противоречат друг другу; вероятно, сортировки
  надо развернуть (преимущество: оставляем большие, помеха: оставляем малые).
  **Требует решения пользователя** по игровому правилу (читать ТР §3 / волны 4-5).

### P2
- **P2-1. Дубликат границ броска в `DiceRollForm.vue`** (`:12-16, 64-111`): хардкод
  `:max="6"` у efficiency против `ROLL_EFFICIENCY_MAX = 20`; кубы/грани/adv/dieSize тоже
  хардкод. Форма быстрого броска ограничивает сл до 6, макросы — до 20. Макро-редактор
  (`MacroRollEditor.vue`) использует `ROLL_*` константы — быстро-форма должна так же.
  → импортировать `Constant/Roll/*`. Заметка: файла `ROLL_DIE_SIZE_MIN.ts` нет (только `MAX`).
- **P2-2. `MacroBarExtension.vue:76`** хардкод лимита `Math.max(-10, Math.min(10, ...))`
  вместо `ROLL_ADV_MAX` (=10, совпадает сейчас, но source расходится).
- **P2-3. `Store/macros.ts:10,18`**: `loading` не потребляется (тот же кейс, что P3-4 в Rule) +
  `console.error` мимо паттерна (в других сторах — `error` ref). → убрать `loading` или завести
  `error` ref.
- **P2-4. Home `DashboardPage.vue:20,28`**: хардкод-бейджи `count: 2/1` показываются как реальные
  цифры-заглушки → вводят в заблуждение. Убрать или выводить из данных.

### P3
- **P3-1. `MacroBarExtension.macroChipTitle`** (`:24-35`) дублирует форматирование
  `rollService.formatRollSpecText` (риск расхождения).
- **P3-2.** Стабы Character/Game/Home-страниц — ожидаемо, реализуются волнами 4-5 (ТР §7-8, F10).

## Сильные стороны
- Game — образцовая плагинная интеграция в Chat через `init.ts` (`registerCommandHandler`
  command `roll`, `registerContentRenderer` рендер `roll`, `registerAttachmentProcessor`
  spec→result, `registerToolbarExtension` roll-form/macro-bar, `registerProfileSection` macros).
  Сервисы-классы (`RollService`, `MacroApi`), синглтон `Service/Instance/rollService.ts`.
  `ROLL_*` константы в `Constant/Roll/`, типы в `Dto/`. Зависимости чистые.
- `parseRollCommand` согласован с `ChatInput.handleSend`: input приходит с ведущим `/`,
  regex `parseRollCommand` ждёт `\/roll|бросок` — ок. `command: 'roll'` у хендлера только
  информационное (диспетчер ChatInput пробегает все хендлеры через `parse`).
- Character/Home — аккуратные стабы без нарушений (init через публичные точки Core/User,
  Messages/Chat).

## План (шаги)
1. P1-1 — решение по игровому правилу преимуществ/помех (см. ТР §3); при подтверждении
   инверсии — развернуть сортировки `computeRollResult`.
2. P2-1 — `DiceRollForm` на `ROLL_*` константы.
3. P2-2 — `ROLL_ADV_MAX` в `MacroBarExtension.confirmAdv`.
4. P2-3 — `Store/macros.ts`: убрать `loading` ИЛИ завести `error` ref.
5. P2-4 — Home `DashboardPage`: убрать/вывести count-бейджи.
6. P3-1 — дедуп форматирования броска в `macroChipTitle`.
7. Верификация — `npm run format` → `npm run lint` → `npx vue-tsc --noEmit` → `npm run test`
   (не трогать порт 3000).

## Решения (заполняется по ходу)

### P1-1 — инверсия adv/помех в computeRollResult. [решено: НЕ-БАГ]

Игровое правило (подтверждено пользователем): **чем ниже кубик — тем лучше** (`1` → 2 успеха,
`6` → −1 успех). Под этим правилом текущий код корректен:

- `adv>0` (преимущество): `sort desc` + `splice(0,adv)` убирает **наибольшие** (= худшие, `6`),
  оставляет малые (`1` → успехи) → **итог выше** — преимущество усиливает. ✓
- `adv<0` (помеха): `sort asc` + `splice(0,|adv|)` убирает **наименьшие** (= лучшие, `1`),
  оставляет `6` → **итог ниже** — помеха ослабляет. ✓

UI-подписи `DiceRollResult.vue:32,38-42` («убрано как худшее/лучшее») согласованы с кодом и ТР §3
(строки 1896-1898, 1918). Находка context-17 сделана под неверным стандартным допущением
«большой куб = хорошо». **Изменений в `RollService.ts` не требуется.** Остаётся как есть.
Зависимости не затронуты.

### P2-1 — DiceRollForm на ROLL_* константы. [решено]

`DiceRollForm.vue` переведён на `Constant/Roll/*`: diceCount `ROLL_DICE_COUNT_MIN/MAX` (1..30),
dieFaces `ROLL_DIE_FACES_MIN/MAX` (2..100), efficiency `ROLL_EFFICIENCY_MIN/MAX` (1..20 —
**диапазон расширен с 6 до 20**, соответствует ТР §3 «эффективность ≤ 20» и парсеру/макро-редактору),
adv `±ROLL_ADV_MAX`, dieSize `±ROLL_DIE_SIZE_MAX`. `ROLL_DIE_SIZE_MIN.ts` не создавался: нижняя
граница выводится как `-ROLL_DIE_SIZE_MAX` (тот же приём, что в `MacroRollEditor.vue`).

### P2-2 — MacroBarExtension.confirmAdv. [решено]

Хардкод `Math.max(-10, Math.min(10, …))` и `v-text-field min/max="-10/10"` заменены на
`-ROLL_ADV_MAX / ROLL_ADV_MAX`.

### P2-3 — Store/macros.ts. [решено]

Убран неиспользуемый `loading`; добавлен `error = ref<string | null>(null)`, в `fetchMacros`
`console.error` заменён на `error.value = 'Не удалось загрузить макросы'` (паттерн сестёр
`spaces.ts`/`notifications.ts`/`auth.ts`; сброс error в начале fetch).

### P2-4 — Home DashboardPage. [решено]

Хардкод-бейджи `count: 2/1` убраны (карточки «Персонажи»/«Игры» — стабы, реальных данных нет,
цифры вводили в заблуждение). `v-chip` удалён из шаблона.

### P3-1 — макрочип title. [решено]

`macroChipTitle` (`MacroBarExtension.vue`) дублировал `RollService.formatRollSpecText`, но с
расхождениями (size без суперскриптов, без `rollLabel`). Теперь `rolls` форматируются через
`rollService.formatRollSpecText(roll)` (`UserMacro.rolls` = `MacroRollSpec[]`), дедуп источника
истины.

## Верификация

`npm run format` + `npm run lint` + `npx vue-tsc --noEmit` + `npm run test` (30 файлов, 274 теста)
— **все зелёные**. Порт 3000 не трогался.