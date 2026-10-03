# План Mechanic 4 — ядро Engine

**Статус:** ядро PHP, сессия 2 [`mechanic-roadmap.md`](mechanic-roadmap.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Контракт Binding и `runEvent` — [`mechanic-plan-03.md`](mechanic-plan-03.md). Образец поведения — Vue `Roleplay/Mechanic/Service/MechanicEngine.ts`, не переписывать.

Цель: `resolveActive` и `runEvent` в процессе. Реестр `code@version`. Binding и узкий снимок. Хендлер `purchase_surcharge` на `character.osSteps`.

Публичный фасад и контейнер — сессия 3 этого роадмапа. HTTP, schema, каталог, Character, `fromRules` не входят. Импортов Character, Rule и Game нет.

## Поведение

Как Vue `MechanicEngine`:

- `mechanicId === null`, нет строки каталога, нет хендлера — строка молча пропускается.
- `includeCodes` фильтрует `MechanicRecord::getCode()`.
- `extraRuleCodes` — коды правил, проход с обходом фильтра. Дедупа нет: binding может попасть дважды.
- `runEvent` вызывает подписчиков события по возрастанию приоритета и мутирует контекст на месте.

Каталог на входе — уже существующий `MechanicRecord` (`getHandlerVersion` = Vue `version`). Отдельный DTO механики и чтение БД не заводить.

`PurchaseSurchargeHandler`: `null` payload — выход. Уровень `< 1` пропускается. Совпадение по признаку или по наличию кода в `racialAbilityCodes` (значение `raceCode` с расой не сравнивается). Порядок совпадений — порядок вставки уровней. Доплата — хвост после `freeCount`.

В реестр не ставить: `injury_efficiency`, `exhaustion_wound`, `state_write`, `blood_clotting`, `roll`, `roll_score_adjust`; хендлеры `six_one_rule`, `critical_strike`, `advantage_disadvantage`, `movement_state`.

## Модуль

Путь: `www/mifrial/modules/Roleplay/Mechanic`.

- `Dto/MechanicBinding`, `PurchaseSurchargePayload`, `SurchargeItem`, `MechanicState`, `CharacterMechanicContext`, `ResolveActiveOptions`, `ResolvedMechanic`
- `Constant/PurchaseSurchargeEvent` = `character.osSteps`
- `Interface/IMechanicHandler`
- `Service/MechanicHandlerRegistry`, `Service/MechanicEngine`
- `Service/Handler/PurchaseSurchargeHandler`

Снимок — массивы, не `Map`. Контейнер не меняется: хендлер регистрирует тест и, позже, сессия 3.

## Тесты

`tests/MechanicEngineTest.php` по `mechanicEngine.test.ts`: резолв id → `code@version`, `includeCodes` и `extraRuleCodes`, порядок приоритета, мутация доплаты (признак, раса, пустой набор), молчаливый пропуск строки без хендлера. Suite `mechanic`, без MySQL.
