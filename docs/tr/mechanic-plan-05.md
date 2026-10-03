# План Mechanic 5 — публичный вызов Engine

**Статус:** публичный порт, сессия 3 [`mechanic-roadmap.md`](mechanic-roadmap.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Ядро — [`mechanic-plan-04.md`](mechanic-plan-04.md).

Цель: сосед получает движок из контейнера. Реестр уже содержит `purchase_surcharge` на `character.osSteps`. Сосед хендлер не регистрирует.

HTTP `mechanic.run`, schema, каталог, Character, `fromRules` не входят. Импортов Character, Rule и Game нет. Тела `resolveActive`, `runEvent` и `PurchaseSurchargeHandler` не меняются.

## Порт

`Interface/Service/IMechanicEngine`, отдельно от `IMechanics`. `MechanicEngine implements IMechanicEngine`.

- `resolveActive` — binding и каталог `MechanicRecord`, опции фильтра.
- `runEvent` — имя события, контекст, активные механики. Контекст мутируется на месте.

`MechanicPortFactory` собирает `MechanicHandlerRegistry`, регистрирует `PurchaseSurchargeHandler` и отдаёт `MechanicEngine`. Карта портов: `IMechanicEngine`. Новых маршрутов нет.

## Тест

`tests/MechanicEnginePortTest.php`, suite `mechanic`, без MySQL. Порт из контейнера, без импорта `MechanicEngine`, реестра и хендлера.

Вызов: `resolveActive`, затем `runEvent` с `PurchaseSurchargeEvent::NAME`. Вход — `MechanicBinding`, `MechanicRecord`, `CharacterMechanicContext`. Выход — `osSurchargeTotal` и `surchargeItems`.
