# Нарезка Roleplay/Character

**Статус:** план, 2026-09-09. Канон — [`character-system.md`](character-system.md). Границы — [`architecture.md`](architecture.md). Стандарты PHP — [`php-coding-standards.md`](php-coding-standards.md). Engine справочника — [`rule-roadmap.md`](rule-roadmap.md) (блок «Позже»), Vue-контракт Binding — [`mechanic-plan-03.md`](mechanic-plan-03.md).

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

Хендлеры PHP Engine — отдельный план модуля Mechanic (сейчас OPEN). C3 Character без него не начинать.

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

### C3. Порт Mechanic Engine — `TODO`

План: [`character-plan-04.md`](character-plan-04.md).

Зависимость: закрытый (или согласованный к интеграции) PHP Engine в Mechanic. Character собирает Binding, зовёт `runEvent`, не тащит grants/budgets в Mechanic. Цикла Character ↔ Mechanic нет.

### C4. Построение листа и validation — `TODO`

План: [`character-plan-05.md`](character-plan-05.md).

Серверный build + **тот же** валидатор, что validate-only: раса, лестницы, `grantedBy`, явный `studyPairId` пар пути, `magic_study.path_code` и покрытие `includes_path_codes`, свои caps, `equipped`, customRules, `active`. Спеки Rule **не готовы** без отмашки. Typed hydrator — блокер, не разрешение считать spec закрытым.

### C5. Authoritative create/update — `TODO`

План: [`character-plan-06.md`](character-plan-06.md).

Единственный пункт, который может закрыть базовое сохранение. Pipeline целиком. HTTP `character.create` / `character.update` / `character.validate`. Без C3 и C4 не закрывать. Discussion chat — не блокер этого пункта (тип `character_discussion` в Chat — позже).

### C6. Read, visibility, notes — `TODO`

План: [`character-plan-07.md`](character-plan-07.md).

После C1 допустим **owner-only** read тестового aggregate (не выдавать за валидный лист). Публичный list/detail: `is_public` + `character_viewer`, маска секций из JSON-грантов (не Vue-массив `SheetVisibility` как хранение), `ownerNotes`, NotFound без утечки существования — после C5. `customRules` уже во входе create/update (C0/C5), отдельных роутов не плодить без нужды.

### C7. Миграция ревизии — `TODO`

План: [`character-plan-08.md`](character-plan-08.md).

По `code`; actual до успеха не портить; `ok` / `resolved` / `conflicts`; повтор C4+C5 на target revision.

### C8. Граница Game membership — `TODO`

План: [`character-plan-09.md`](character-plan-09.md).

После контракта Game: `approvedCharacterVersion`, overlay, moderation diff, запрет actual во время сессии (сессию знает Game). Не таблицы membership внутри Character, если канон оставит их Game.

### C9. Runtime листа вне create — `TODO`

План: [`character-plan-10.md`](character-plan-10.md).

Не дубль C4. Decay, DOT, каст, session overlay — вертикали с Game; Character только если меняется actual вне сессии и снова проходит C5.

## Порядок

Параллелить: каркас C1 и план PHP Engine в Mechanic после закрытия C0; контент Rule отдельно.

Нельзя закрыть:

- C3 без PHP Engine (план Mechanic);
- C4 без C2; C4 без fail-closed Engine (C3 или эквивалентный порт);
- C5 без C3 и без C4;
- C7 без C4/C5;
- C8 без Game;
- C9 как обход C5.

## Гейты

**Partial storage (C1):** карты и mysql-фикстуры; статус `BACKEND_OPEN`.

**Полноценное сохранение (C5):** детерминизм; фронт не подменяет derived; битая ссылка / лимит / requirement / неизвестный handler отклоняются; нет частичной записи; stale version — conflict в той же TX, что write; create и update — integration tests; срез ревизии не «дочитывается» после publish.

**Миграция (C7):** source actual цел при conflict; `code`; полный pipeline на target.

## Не входит

История `CharacterVersion`, server-side editor draft, Game combat, battleground, полный каталог магии, HTTP файла ревизии. Vue Character API — отдельным заходом после C5, не в C1. Заморозка формы `*Spec` — только по отмашке, см. [`character-plan-01.md`](character-plan-01.md) и [`rule-roadmap.md`](rule-roadmap.md).
