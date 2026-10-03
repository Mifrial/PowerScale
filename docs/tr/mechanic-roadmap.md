# Нарезка PHP Engine Roleplay/Mechanic

**Статус:** план, 2026-10-02. Границы — [`architecture.md`](architecture.md). Стандарты PHP — [`php-coding-standards.md`](php-coding-standards.md). Каталог и HTTP — [`mechanic-plan-02.md`](mechanic-plan-02.md). Vue-контракт Binding и `runEvent` — [`mechanic-plan-03.md`](mechanic-plan-03.md). Потребитель листа — [`character-roadmap.md`](character-roadmap.md), шаг C3.

Цель линии: PHP Mechanic выполняет `runEvent` в процессе. Публичный фасад. Хендлер `purchase_surcharge` на событии `character.osSteps`. Снимок — `MechanicBinding` и узкий контекст (`abilityLevels`, `abilityKeywords`, `racialAbilityCodes`, `osSurchargeTotal`, `surchargeItems`). Не лист и не `Rule`.

Каталог, HTTP `mechanic.*` и админка уже в real-режиме. В эту линию не входят.

Отдельного HTTP `mechanic.run` нет. Character на C3 зовёт фасад в том же процессе. Mechanic не импортирует Character, Rule и Game. `MechanicBindingList.fromRules` в PHP не переносить: маппинг `Rule[]` → `MechanicBinding[]` делает Character.

Vue `resolveActive` молча пропускает строку без хендлера. Fail closed на save — критерий Character C4/C5, не этой линии.

Вне линии, в реестр `runEvent` не ставить: `injury_efficiency`, `exhaustion_wound`, `state_write`, `blood_clotting`, `roll`, `roll_score_adjust`; хендлеры `six_one_rule`, `critical_strike`, `advantage_disadvantage`, `movement_state`.

## Сессии

План-файл шага пишем, когда шаг начинается. Рабочих сессий две: ядро, затем публичный вызов.

### 1. Роадмап — `DONE`

Этот файл. Кода нет.

Блокируется ничем. Разблокирует сессию 2.

### 2. Ядро Engine — `DONE`

[`mechanic-plan-04.md`](mechanic-plan-04.md). `resolveActive`, `runEvent`, реестр `code@version`, binding, снимок, хендлер `purchase_surcharge` на `character.osSteps`. Phpunit по образцу `draft-front_1.2ds/src/modules/Roleplay/Mechanic/__tests__/Service/mechanicEngine.test.ts`: резолв id → `code@version`, `includeCodes` и `extraRuleCodes`, порядок приоритета, мутация доплаты.

Не трогает фасад контейнера, HTTP, schema, каталог, Character. Без импортов Character, Rule и Game. Без `fromRules`.

Блокируется сессией 1 (этот файл). Разблокирует сессию 3.

### 3. Публичный вызов — `DONE`

[`mechanic-plan-05.md`](mechanic-plan-05.md). Публичный интерфейс движка и проводка в контейнере. Тест снаружи внутренних классов: binding и снимок на входе, `character.osSteps`, на выходе `osSurchargeTotal` и `surchargeItems`. `purchase_surcharge` уже в реестре; сосед его не регистрирует.

Не трогает HTTP, schema, каталог, игровые хендлеры и payload кроме `purchase_surcharge`. Модуль Character, сборку листа и fail closed на save не начинает.

Блокируется кодом сессии 2. Разблокирует Character C3 по [`character-roadmap.md`](character-roadmap.md). C3 — не шаг этого файла: Character собирает Binding и зовёт фасад; Mechanic по-прежнему не импортирует Character.

После сессии 3 линия Mechanic для листа закрыта.
