# Нарезка Roleplay/Character

**Статус:** план, 2026-10-05. Канон — [`character-system.md`](character-system.md). Границы — [`architecture.md`](architecture.md). Стык Game — [`game-roadmap.md`](game-roadmap.md) (G1–G19 `DONE`, G20 ждёт шаг C10). Стандарты PHP — [`php-coding-standards.md`](php-coding-standards.md). Engine справочника — [`rule-roadmap.md`](rule-roadmap.md) (блок «Позже»), Vue-контракт Binding — [`mechanic-plan-03.md`](mechanic-plan-03.md). Обход формулы — [`rule-plan-03.md`](rule-plan-03.md).

Цель линии: серверный модуль **`Roleplay/Character`**. Create/update actual state — только после серверного построения листа и полной validation. Таблицы, HTTP и mock сами по себе save не закрывают.

## Кто что считает

Сохранение оркестрирует **Character**, не модуль Mechanic.

```text
выборы (build: раса, purchases характеристик, abilities, inventory, свои limits)
  → RuleSpace: срез (spaceId, revision)
  → Character: inheritance, budgets, grants, requirements, derived
  → Mechanic Engine: только runEvent по Binding
  → один валидатор (create / update / validate-only)
  → optional сверка expectedSheet
  → при записи: optimistic guard + TX → actualCharacter
```

Mechanic не импортирует Character и не импортирует Rule. Контекст хендлера — узкий снимок (как Vue `CharacterMechanicContext`), не DTO листа. Mapping `Rule[]` → `MechanicBinding[]` делает Character.

PHP Character → SmartTable, User, RuleSpace, Rule, Mechanic (порт Engine), Keyword только если без него не резолвятся условия на снимке. **Character ↛ Versioning/Space** (ревизия через RuleSpace). **Character ↛ Game**. Mechanic ↛ Character.

## Главный критерий готовности

Create/update считаются `IMPLEMENTED` только если backend:

1. принимает **выборы**, не derived и не лимиты игры с клиента;
2. резолвит правила строго в `(spaceId, revision)`;
3. строит лист на сервере (Character + нужные `runEvent`);
4. неподдержанный / упавший handler — **fail closed**, записи нет;
5. один валидатор на create, update и validate-only; типизированный `problems`;
6. атомарно пишет `actualCharacter` с optimistic guard;
7. имеет отрицательные тесты (ссылки, budgets, requirements, stale version, handler).

До этого любая строка в MySQL — `BACKEND_OPEN` / `PARTIAL`. Промежуточный editor draft — только браузер ([`character-system.md`](character-system.md)); серверного «дырявого actual» нет.

## Todo

План-файл пишем, когда шаг начинается.

### C0. Серверный контракт — `DONE`

План: [`character-plan-01.md`](character-plan-01.md).

Вход выборов, свои лимиты, `grantedBy` + `studyPairId`, customRules, `active`, общий валидатор, спеки без отмашки не готовы.

### C1. Каркас модуля и actual storage — `DONE` (`BACKEND_OPEN`)

План: [`character-plan-02.md`](character-plan-02.md).

Lazy-модуль, `character` + `character_viewer`, `ICharacters` add/get/replacePayload/setActive. `space_id` → `rule_space` (forTable). JSON `choices`/`sheet`; видимость = гранты секций (`visibility_fields`+`is_public`, viewer.`fields`). Не валидатор, не HTTP, не `IRuleSpaces`. После кода — `BACKEND_OPEN`.

### C2. Контекст ревизии — `DONE` (`BACKEND_OPEN`)

План: [`character-plan-03.md`](character-plan-03.md).

Immutable `(spaceId, revision)` через `IRuleSpaces` (не часы в обход мира). Резолв `code`, тип, keyword codes (снимок сейчас — ids). Не «latest». Tombstone не живое правило.

### C3. Порт Mechanic Engine — `DONE` (`BACKEND_OPEN`)

План: [`character-plan-04.md`](character-plan-04.md).

Зависимость: закрытый (или согласованный к интеграции) PHP Engine в Mechanic. Character собирает Binding, зовёт `runEvent`, не тащит grants/budgets в Mechanic. Цикла Character ↔ Mechanic нет.

### C4. Построение листа и validation — `DONE` (`BACKEND_OPEN`)

План: [`character-plan-05.md`](character-plan-05.md).

Серверный build + **тот же** валидатор, что validate-only: раса, лестницы, `grantedBy`, явный `studyPairId` пар пути, `magic_study.path_code` и покрытие `includes_path_codes`, свои caps, `equipped`, customRules, `active`. Спеки Rule **не готовы** без отмашки. Typed hydrator — блокер, не разрешение считать spec закрытым.

### C5. Authoritative create/update — `DONE`

План: [`character-plan-06.md`](character-plan-06.md).

Единственный пункт, который может закрыть базовое сохранение. Pipeline целиком. HTTP `character.create` / `character.update` / `character.validate`. Без C3 и C4 не закрывать. Discussion chat — не блокер этого пункта (тип `character_discussion` в Chat — позже).

### C6. Read, visibility, notes — `DONE`

План: [`character-plan-07.md`](character-plan-07.md).

После C1 допустим **owner-only** read тестового aggregate (не выдавать за валидный лист). Публичный list/detail: `is_public` + `character_viewer`, маска секций из JSON-грантов (не Vue-массив `SheetVisibility` как хранение), `ownerNotes`, NotFound без утечки существования — после C5. `customRules` уже во входе create/update (C0/C5), отдельных роутов не плодить без нужды.

### C7. Миграция ревизии — `DONE`

План: [`character-plan-08.md`](character-plan-08.md).

По `code`; actual до успеха не портить; `ok` / `resolved` / `conflicts`; повтор C4+C5 на target revision.

### C8. Граница Game membership — `PARTIAL`

Character Game не импортирует ([`architecture.md`](architecture.md)). Membership и сессия живут в Game ([`game-roadmap.md`](game-roadmap.md)). Спека [`../specs/character-actual-session-source-roadmap.md`](../specs/character-actual-session-source-roadmap.md) порядок стыка не заменяет.

Уже закрыто Game:

- G3–G5: membership, `approvedCharacterVersion`, semantic diff, `canStartSession`, `isActiveSessionParticipant`, запрет migrate активного участника. `character.migrate` зовёт порт «участник в сессии».
- G10: порт мутации actual — typed patch и ожидаемая `actual_version`.
- G13: удар `1 → 1` пишет лист через этот порт вместе с версией боя.
- G15: доставка уже принятого итога — outbox игры `game_delivery` и `/api/game/sync`, не общий хаб. Повтор кадра лист не пишет.

TODO, в линии G1–G16 этого нет:

- process: закрыт Game G17.
- crash recovery: G15 не восстанавливает оборванную команду и не откатывает уже применённый итог. Отдельного шага Game нет.
- production authorization: каркас прав G2 — `BACKEND_OPEN`, не production-контур C8. Отдельного шага Game нет.

Battleground и `ISpatialResolver` в этот хвост не входят.

### C9. Runtime листа вне create — `TODO`

Не дубль C4 и не хвост C8. Decay, DOT и каст линия G1–G15 не делает: G10 — порт мутации, G13 — один удар `1 → 1`. `changes_pending` не блокирует текущего participant и блокирует следующую session — это допуск G4, не runtime-эффект. Отдельные implementation tasks остаются `TODO`.

### C10. Контекст формулы — `DONE`

Зависимость: C4. Обход узлов уже есть: `IFormulaEvaluations` шага 14 Rule. G20 его ждёт и этим шагом не начинается.

Порт Character по уже записанному листу собирает `FormulaContext`. В листе уже лежат `abilityLevels` и `characteristicPurchases` (`characteristicCode`, `value.base`, `value.size`). Характеристики и уровни способностей берутся оттуда. Параметров и баз характеристик действий в этом документе нет: в контексте они пустые, второй лист под них не строится. `IFormulaEvaluations` этот шаг не вызывает.

Тот же разбор принимает документ листа, не только id персонажа. Game сможет отдать им JSON версии NPC, не заводя в Character чтение `npc`. Character Game не импортирует. HTTP нет. Удар, проверка и урон не считаются.

### C11. Патч исхода удара — `TODO`

Зависимость: порт мутации G10, уже лежащий в Character. Не C9 и не шаг Mechanic.

Порт `ICharacterActualMutations` получает kind, которым удар записывает уже посчитанное число. Какое поле листа меняется, выбирает план этого шага. Game поле не выбирает и kind не заводит. Хендлеры `injury_efficiency`, `exhaustion_wound` и `state_write` в реестр Mechanic не ставятся. DOT и каст не входят.

Пока этот kind не назван, Game G22 не начинается.

### C12. Проекция боевых слоёв — `DONE` (`BACKEND_OPEN`)

План: [`character-plan-12.md`](character-plan-12.md). Чтение листа игрока и
документа NPC в typed список слоёв на стеке вызова. Проекция включает
надетые item layers, resistance grants и выбранный надетый block item. Лист и
бой список не хранят. Это разблокировало базовый resistance path
`game-plan-28`; полная блокирующая ветка и P1 ещё не закрыты.

## Порядок

Параллелить: каркас C1 и план PHP Engine в Mechanic после закрытия C0; контент Rule отдельно.

Нельзя закрыть:

- C4 без C2; C4 без fail-closed Engine (C3 или эквивалентный порт);
- C5 без C3 и без C4;
- C7 без C4/C5;
- C8 целиком, пока открыты process, crash recovery и production authorization;
- C9 как обход C5 или как закрытие хвостов C8;
- C10 без C4; C10 как обход формулы в Game или как шаг G20;
- C11 как хендлер ран в Mechanic или как шаг G22.

## Гейты

**Partial storage (C1):** карты и mysql-фикстуры; статус `BACKEND_OPEN`.

**Полноценное сохранение (C5):** детерминизм; фронт не подменяет derived; битая ссылка / лимит / requirement / неизвестный handler отклоняются; нет частичной записи; stale version — conflict в той же TX, что write; create и update — integration tests; срез ревизии не «дочитывается» после publish.

**Миграция (C7):** source actual цел при conflict; `code`; полный pipeline на target.

## Не входит

История `CharacterVersion`, server-side editor draft, Game combat, battleground, полный каталог магии, HTTP файла ревизии. Vue Character API — отдельным заходом после C5, не в C1. Заморозка формы `*Spec` — только по отмашке, см. [`character-plan-01.md`](character-plan-01.md) и [`rule-roadmap.md`](rule-roadmap.md).
