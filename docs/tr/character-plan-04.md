# План Character 4 — порт Mechanic Engine (C3)

**Статус:** PHP сделан. `BACKEND_OPEN`. Нарезка — [`character-roadmap.md`](character-roadmap.md). Срез — [`character-plan-03.md`](character-plan-03.md). Движок — [`mechanic-plan-04.md`](mechanic-plan-04.md), порт — [`mechanic-plan-05.md`](mechanic-plan-05.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: Character собирает `MechanicBinding` и узкий снимок и вызывает `IMechanicEngine` на `character.osSteps`. Доплата остаётся в аккумуляторах контекста. Записи персонажа нет.

Mechanic не менять. `purchase_surcharge` уже в реестре `MechanicPortFactory::createEngine`. Character хендлер не регистрирует. `fromRules` внутри Mechanic нет. Character не импортирует `MechanicEngine`, реестр и хендлер.

После зелёного suite: по-прежнему **`BACKEND_OPEN`**. C4 и C5 не начинать.

## Зафиксировано

- Порт `ICharacterOsSteps::runOsSteps`. Сосед: `$locator->get(ICharacterContainer::class)->get(ICharacterOsSteps::class)`.
- Срез уже загружен. `ICharacterRuleSlices::get` из шага не звать.
- Binding только из `CharacterRuleSlice::getLiveRules()`, порядок состава, внутри правила — порядок `getMechanics()`. Tombstone не входит.
- Строка binding: `ruleCode` = `getCode()`, `mechanicId`, payload. Не один id и не весь Rule. `getSpec()` не читать.
- Payload `purchase_surcharge`: обязательны `type`, массив `filter`, int `free_count`, int `surcharge`. `filter.keyword_code` и `filter.race_code` необязательны: нет ключа или не строка → `null`. Иная форма или `mechanic_id` не int ≥ 1 — строку не превращать в binding.
- Каталог — `IMechanics::get` по уникальным id. `MechanicNotFoundException` — id не класть в каталог, шаг не ронять и не мапить в `CHARACTER_*`.
- Снимок: `abilityLevels` и `racialAbilityCodes` — аргументы. Признаки — `findLive($code)?->getKeywordCodes()`, иначе пустой список. Gifted и derived не считать.
- Вызов: `resolveActive` с `ResolveActiveOptions` без фильтра, затем `runEvent(PurchaseSurchargeEvent::NAME, ...)`. Вернуть тот же `CharacterMechanicContext`.
- В Engine не уходят grants, budgets и DTO листа.

## Что не закрыто

- Построение листа, валидатор, create/update, HTTP `character.*`.
- Fail closed всего save.
- Игровые хендлеры и разбор чужих payload.

## Тест

Suite `character`, без MySQL. Мок `IMechanics`, движок из контейнера.

Уровни `a`, `b`, `c` по 1, признак `common` у `a` и `b`, `free_count` 1, `surcharge` 2, каталог `purchase_surcharge` / `1.0.0`. Итог 2, строка доплаты на `b`. Правило с непустым spec и пустым `getMechanics()` итог не меняет.
