# План Character 9 — контекст формулы (C10)

**Статус:** PHP сделан. Нарезка — [`character-roadmap.md`](character-roadmap.md) шаг C10. Форма контекста — [`rule-plan-03.md`](rule-plan-03.md), класс `FormulaContext`. Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Лист — [`character-plan-06.md`](character-plan-06.md).

Цель: порт `Roleplay/Character` из уже записанного листа собирает `FormulaContext`. Обход `IFormulaEvaluations` не вызывается. G20 не начинается. Код Game и Rule не меняется. HTTP нет. Удар, проверка и урон не считаются.

## Зафиксировано

- Порт `ICharacterFormulaContexts`, в локаторе Character рядом с остальными портами модуля. Один разбор документа листа. Второй вход — id: `ICharacters::get`, затем колонка `sheet` в тот же разбор. Отдельного разбора под id нет.
- Документ — массив снимка `sheet`, не choices. Game позже отдаст JSON версии NPC этим же входом. Character таблицы NPC не читает и Game не импортирует.
- `abilityLevels` — карта код → int, как в снимке валидатора. Ключ проверяется как строка и не пустой: числовой ключ списка к строке не приводится. В контекст попадают только эти ключи. Остальные ключи снимка (`money`, `active`, `racialAbilityCodes`, `equippedModifiers`, `coveredPaths`, `characteristicPurchaseOs` и прочие) не читаются.
- `characteristicPurchases` — список строк. В контекст идут `characteristicCode` (непустая строка) и `value.base`, `value.size` как `DimensionalNumber`. `cost` и прочие ключи строки и `value` не читаются. Повтор `characteristicCode` — `CHARACTER_INVALID`, контекст не собирается.
- `parameters` и `actionCharacteristics` всегда пустые. Второго листа и второго снимка под них нет.
- Битый документ — `CharacterInvalidException` (`CHARACTER_INVALID`) до сборки контекста. Нет массива `abilityLevels` или `characteristicPurchases`. Уровень не int. Ключ уровня не непустая строка. Строка закупки не массив, нет непустого `characteristicCode`, код уже встречался, нет массива `value`, `base` или `size` не int. Пустые массивы — пустые карты, это не ошибка.
- Нет строки по id — `CharacterNotFoundException` из `ICharacters::get`. Владельца этот порт не проверяет: у `get` проверки владельца нет, HTTP в шаге нет.
- Порт не вызывает `IFormulaEvaluations` и не импортирует Game. `FormulaContext` и `DimensionalNumber` — типы Rule.

## Тест

Suite `character`. Сверка через уже существующие `findAbilityLevel`, `findCharacteristic`, `findParameter`, `findActionCharacteristic`: у `FormulaContext` нет отдачи карт, а Rule этот шаг не меняет. Документ: уровни и закупки попадают в контекст; параметры и базы действий пустые; прочие ключи снимка не меняют контекст; битый документ и повтор кода характеристики дают `CHARACTER_INVALID`. MySQL: id через `ICharacters::get` даёт тот же контекст, что разбор записанного `sheet`; нет строки — `CHARACTER_NOT_FOUND`. `IFormulaEvaluations` и код Game не вызываются.
