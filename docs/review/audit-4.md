# Audit 4 — optimistic concurrency и DB CAS для Character/NPC

**Дата:** 2026-10-07  
**Режим:** read-only audit  
**Scope:** только optimistic concurrency и DB CAS для Character/NPC в боевом закрытии  
**Verdict:** `BLOCKED`

## Scope and constraints

Проверены:

- `AGENTS.md`
- `docs/tr/TR.md`
- `docs/tr/game-roadmap.md`
- `docs/tr/game-combat-readiness.md`
- `docs/tr/combat-layers-prerequisite.md`
- `docs/tr/game-plan-27.md`
- `docs/tr/game-plan-28.md`
- `docs/tr/php-coding-standards.md`
- `docs/tr/character-system.md`
- `docs/tr/game-system.md`
- `docs/tr/decisions.md`

Проверены код и тесты:

- `CharacterActualMutations.php`
- `ICharacterActualMutations.php`
- `Characters.php`
- `CharacterRepository.php`
- `CharacterTable.php`
- `GameStrikeSheetWrites.php`
- `GameNpcRepository.php`
- `GameNpcTable.php`
- `GameStrikes.php`
- `GameWideStrikeResults.php`
- `GameWideStrikes.php`
- `GameChecks.php`
- `GameEconomyApply.php`
- Character/Game MySQL tests и соответствующие схемы
- SmartTable transaction/update path

Tracked diff отсутствует. Existing untracked files не изменялись:

- `tools/review/__pycache__/review.cpython-312.pyc`
- `www/mifrial/var/battleground-attack-stress.json`
- `www/mifrial/var/battleground-move-stress.json`

PHPUnit/MySQL и frontend dev-server не запускались.

## 1. Подтверждённый контракт

Канон подтверждает:

- `actualCharacter` — единственный persisted-лист игрока.
- `npc.version` — authoritative-лист NPC.
- `actual_version` / `npc.actual_version` — optimistic version counters.
- Combat mutation принимает expected version.
- Applied effect, Game state, battle state и idempotency record должны
  коммититься в одной outer Game transaction.
- Replay по тому же idempotency key не должен повторно менять лист.
- В wide strike:
  - отказ одной цели возвращается в её `targetResults[]`;
  - уже принятые цели не откатываются из-за target-level refusal;
  - неперехваченная ошибка mutation/transaction должна откатывать весь
    wide strike.

Evidence:

- `docs/tr/character-system.md`: actual mutation через Character pipeline,
  optimistic guard и validation.
- `docs/tr/game-system.md`: authoritative Character/NPC storage и outer
  Game transaction.
- `docs/tr/decisions.md`, `DEC-041`, `DEC-084`.
- `docs/tr/game-roadmap.md`: G10, G13, G19.
- `docs/tr/game-plan-27.md`: запись `putDamageSplit` в одной транзакции.
- `docs/tr/game-combat-readiness.md`: текущий NPC/Character mutation path и
  необходимость invariant-теста.

## 2. Canonical owners

### Character

Canonical owner persisted player sheet:

- `Roleplay/Character`
- `ICharacters`
- `ICharacterActualMutations`
- `CharacterRepository`
- `CharacterTable`, таблица `character`

Game не должен писать Character напрямую.

### NPC

Canonical owner persisted NPC sheet:

- `Roleplay/Game`
- `GameNpcRepository`
- `GameNpcTable`, таблица `game_npc`
- authoritative payload: `version`
- technical counter: `actual_version`

`ICharacterActualMutations::applyToDocument()` является только общим
document-patching helper. Он не становится владельцем NPC storage.

### Outer transaction

Canonical owner multi-entity operation:

- `Roleplay/Game`
- `GameStrikes::close()`
- `GameWideStrikes::commitClose()`
- `GameChecks::commitSolo()` / `commitAnswer()`
- `GameEconomy::apply()`

## 3. Character mutation

### Что есть

`CharacterActualMutations::apply()`:

- читает строку через `locked()`;
- сравнивает `actual_version` с expected;
- строит patched document;
- вызывает `ICharacters::replacePayload()`.

Evidence:

- `www/mifrial/modules/Roleplay/Character/Service/CharacterActualMutations.php`
  - `apply()`
  - `locked()`
  - `applyToDocument()`

`Characters::replacePayload()` делегирует в
`CharacterRepository::replacePayload()`.

`CharacterRepository::writeGuarded()` повторно читает строку и проверяет
версию перед update:

- `www/mifrial/modules/Roleplay/Character/Repository/CharacterRepository.php`
  - `writeGuarded()`
  - `updateRow()`

### Что отсутствует

Это не DB-level conditional CAS.

`CharacterRepository::writeGuarded()` выполняет:

```text
SELECT character
check actual_version in PHP
UPDATE character WHERE id = ?
```

`TableRows::update()` использует только:

```text
where('id', $rowId)->update($payload)
```

Evidence:

- `www/mifrial/modules/Core/SmartTable/Service/Query/TableRows.php`
  - `update()`
- `www/mifrial/modules/Roleplay/Character/Repository/CharacterRepository.php`
  - `writeGuarded()`

В SQL отсутствует условие:

```text
actual_version = expectedVersion
```

Также не проверяется affected-row count для обнаружения проигравшего CAS.

### Verdict по Character

- API-level expected-version guard: **есть**.
- Sequential stale-version rejection: **есть**.
- Authoritative conditional DB CAS: **нет**.
- Lost-update race: **есть**.

Две транзакции могут прочитать одну версию `N`, обе пройти PHP-проверку и
записать `N + 1`. Последняя запись перезапишет первую.

Тесты:

- `CharacterActualMutationMysqlTest::testStaleVersionDoesNotWrite()`
- проверки stale version внутри
  `testPutStateWritesParsedList()`
- проверки stale version внутри
  `testDamageSplitWritesReplacementAndSum()`

проверяют только последовательный stale case. Race между двумя DB
connections не проверяется.

## 4. NPC `replaceVersion()`

`GameNpcRepository::replaceVersion()`:

1. читает NPC через `getById()`;
2. сравнивает `actual_version` в PHP;
3. вызывает обычный `update()` по `id`;
4. увеличивает версию до `expected + 1`.

Evidence:

- `www/mifrial/modules/Roleplay/Game/Repository/GameNpcRepository.php`
  - `replaceVersion()`
- `www/mifrial/modules/Roleplay/Game/Table/GameNpcTable.php`
  - `actual_version` — обычный `IntField`

Условного DB update нет. В `GameNpcTable` отсутствуют DB constraint или
механизм, который превращал бы `actual_version` в CAS.

`GameNpcRepository::save()` имеет ту же проблему: он также выполняет
unconditional update после проверки, сделанной выше в сервисе.

### Отдельный вывод

Существующий NPC CAS нельзя считать authoritative.

Это только read-check-update с PHP guard. При конкурентной записи возможен
lost update.

## 5. Race между read-check-update

Race присутствует для обоих владельцев:

- `CharacterRepository::writeGuarded()`
- `GameNpcRepository::replaceVersion()`
- `GameNpcs::change()` через `save()`
- `GameChecks::writeNpc()`
- `GameStrikeSheetWrites::writeNpc()`
- `GameEconomyApply::writeNpc()`

`TableRows::getById()` не использует `SELECT ... FOR UPDATE`, а
`TableRows::update()` не принимает ожидаемую версию.

Outer transaction сама по себе не исправляет проблему: она обеспечивает
rollback, но не делает update условным.

## 6. Преобразование конфликтов в `GameBattleConflictException`

### Character path

В combat path Character conflict преобразуется корректно:

- `GameStrikeSheetWrites::write()`
  - `CharacterConflictException`
  - → `GameBattleConflictException`
- `GameChecks::writeSheet()`
  - `CharacterConflictException`
  - → `GameBattleConflictException`

Evidence:

- `www/mifrial/modules/Roleplay/Game/Service/GameStrikeSheetWrites.php`
  - `write()`
- `www/mifrial/modules/Roleplay/Game/Service/GameChecks.php`
  - `writeSheet()`

### NPC path

NPC conflict преобразуется непоследовательно:

- `GameStrikes::assertSheet()` заранее создаёт
  `GameBattleConflictException`;
- `GameWideStrikeResults::refusal()` также ловит этот preflight conflict;
- но фактический `GameNpcRepository::replaceVersion()` выбрасывает
  `GameEconomyConflictException`;
- `GameStrikeSheetWrites::writeNpc()` его не преобразует;
- `GameChecks::writeNpc()` его также не преобразует;
- `GameWideStrikeResults::one()` не ловит конфликт во время фактической
  записи.

`GameEconomyConflictException` содержит код `GAME_CONFLICT`, но это не тот
combat exception type, который объявлен интерфейсами ударов.

Следовательно:

- preflight NPC conflict обычно выглядит как
  `GameBattleConflictException`;
- mutation-time NPC conflict может утечь как
  `GameEconomyConflictException`;
- при race authoritative conflict вообще может не возникнуть, потому что
  update unconditional.

## 7. Атомарность относительно outer Game transaction

### Подтверждено

Outer transactions существуют:

- `GameStrikes::close()`
- `GameWideStrikes::commitClose()`
- `GameChecks::commitSolo()`
- `GameChecks::commitAnswer()`
- `GameEconomy::apply()`

`SmartTableGateway::transaction()` при уже открытой транзакции не создаёт
вложенную независимую транзакцию, а выполняет work в текущем connection
transaction.

Evidence:

- `www/mifrial/modules/Core/SmartTable/Service/SmartTableGateway.php`
  - `transaction()`
- `www/mifrial/modules/Roleplay/Game/Service/GameStrikes.php`
  - `close()`
- `www/mifrial/modules/Roleplay/Game/Service/GameWideStrikes.php`
  - `commitClose()`
- `www/mifrial/modules/Roleplay/Game/Service/GameEconomy.php`
  - `apply()`

### Ограничение

`GameStrikeSheetWrites` и `GameNpcRepository::replaceVersion()` сами по себе
не владеют outer transaction. Они корректно атомарны только когда вызваны
внутри Game transaction.

Главный дефект остаётся:

- при обнаруженном исключении rollback работает;
- при lost update исключения нет;
- значит, transaction commit может сохранить ошибочно перезаписанное
  состояние.

## 8. Wide strike: refusal и rollback

### Target-level refusal

`GameWideStrikeResults::one()` сначала выполняет `refusal()`:

- проверяет roster;
- reaction;
- expected sheet version.

При конфликте конкретная цель получает:

```text
code = GAME_CONFLICT
sheetVersion = null
```

Запись этой цели не вызывается. Остальные цели продолжают обработку.

Evidence:

- `www/mifrial/modules/Roleplay/Game/Service/GameWideStrikeResults.php`
  - `one()`
  - `refusal()`
- `www/mifrial/modules/Roleplay/Game/tests/GameWideStrikeMysqlTest.php`
  - `testTwoTargetsKeepSeparateResults()`

Этот сценарий подтверждён тестом: одна NPC-цель получает отказ, другая
продолжает и увеличивает свою версию.

### Полный rollback

`GameWideStrikes::commitClose()` выполняет:

1. `results->build()`;
2. запись реакций;
3. закрытие strike;
4. увеличение battle version;
5. сохранение idempotency command;

в одной outer transaction.

Поэтому неперехваченная ошибка после записи предыдущей цели должна
откатить весь wide strike.

Но:

- отдельного теста на поздний отказ после успешной записи предыдущей цели
  нет;
- NPC lost update может не породить ошибку;
- поэтому rollback-модель существует структурно, но не защищает от race.

## 9. Replay и idempotency

### Подтверждено

`GameStrikes::replay()` и `GameWideStrikeReplay::find()`:

- ищут command по `(gameId, idempotencyKey)`;
- сравнивают canonical body;
- тот же body возвращают как сохранённый результат;
- не выполняют повторную mutation;
- другой body превращают в `GameBattleConflictException`.

Command record создаётся внутри той же транзакции, что и mutation.

Evidence:

- `GameStrikes::replay()`
- `GameWideStrikeReplay::find()`
- `GameStrikes::close()`
- `GameWideStrikes::commitClose()`
- `GameStrikeMysqlTest::testDeclareAndResolveLeavesSheet()`
- `GameWideStrikeMysqlTest::testTwoTargetsKeepSeparateResults()`

### Ограничение

Idempotency не заменяет CAS:

- она защищает повтор того же command key;
- не защищает два разных key с одной expected version;
- не исправляет lost update;
- concurrency-test для одновременного replay отсутствует.

## 10. Одинаковость Character и NPC semantics

Семантика сейчас не одинаковая.

Общая часть:

- expected version принимается;
- version увеличивается на успешной записи;
- оба пути используют `putDamageSplit`;
- оба вызываются из outer Game transaction.

Различия:

- Character:
  - `apply()`;
  - собирает новый sheet;
  - вызывает validation;
  - может выбросить `CharacterSaveRejectedException`;
  - конфликт — `CharacterConflictException`.
- NPC:
  - `applyToDocument()`;
  - только патчит document;
  - Character validation не вызывает;
  - пишет raw `version`;
  - конфликт — `GameEconomyConflictException`;
  - DB CAS отсутствует.

Evidence:

- `CharacterActualMutations::apply()`
- `CharacterActualMutations::applyToDocument()`
- `GameStrikeSheetWrites::writeNpc()`
- `GameChecks::writeNpc()`
- `GameEconomyApply::writeCharacter()`
- `GameEconomyApply::writeNpc()`
- `docs/tr/game-combat-readiness.md`, раздел известных расхождений
- `docs/tr/game-plan-27.md`, раздел записи Character/NPC

Текущий `putDamageSplit` имеет разную acceptance semantics для игрока и NPC.
Это прямо отмечено readiness-документом как требующее invariant-теста.

## 11. Gap

Критические gaps:

1. Character mutation не является DB conditional CAS.
2. NPC `replaceVersion()` не является authoritative DB conditional CAS.
3. Есть race read-check-update и возможность lost update.
4. NPC mutation-time conflict не стандартизирован как
   `GameBattleConflictException`.
5. Character и NPC имеют различную validation/acceptance semantics.
6. Wide-strike rollback не покрыт поздним failure test.
7. Race tests с двумя независимыми DB connections отсутствуют.
8. Replay проверен последовательно, но не конкурентно.
9. AP spend ещё не имеет подтверждённого authoritative storage/transaction
   contract.
10. Block effect остаётся неполным: нет defender check, auto-fail `{0|-1}`,
    успешной/неуспешной ветки, authoritative eligibility и AP spend.

## 12. Зависимости

До AP spend и block effect необходимы:

- authoritative storage CAS для Character;
- authoritative storage CAS для NPC;
- единый combat conflict adapter;
- подтверждённая parity semantics для applied effect;
- deterministic wide-strike rollback tests;
- подтверждение, где живёт AP и кто владеет его mutation;
- outer transaction, включающая:
  - AP spend;
  - Character/NPC effect;
  - battle/process transition;
  - idempotency record.

`game-plan-28` остаётся `PARTIAL`.

По канону `game-plan-29` не начинается до полного P1. В этот аудит и в
prerequisite не входит переход к `game-plan-29`.

## 13. Отдельный prerequisite-plan: storage/CAS

Это prerequisite для исправления подтверждённого storage-контракта, а не
implementation plan для неподтверждённого AP/block поведения.

### CAS prerequisite

Нужно подтвердить единый storage contract:

- запись допускается только при совпадении `expected actual_version`;
- проверка и increment выполняются внутри одного DB conditional update;
- проигравшая запись получает конфликт;
- payload проигравшей операции не может перезаписать payload победителя;
- следующий version формируется однозначно;
- Character и NPC используют одинаковую concurrency semantics.

### Acceptance tests

1. **Character concurrent write**
   - две независимые DB connections читают version `N`;
   - обе пытаются записать разные операции с expected `N`;
   - ровно одна запись успешна;
   - вторая получает conflict;
   - version равна `N + 1`;
   - payload проигравшей операции отсутствует.

2. **NPC concurrent `replaceVersion()`**
   - тот же сценарий для `game_npc`;
   - проверяется, что NPC не принимает вторую запись;
   - `actual_version` не возвращается к `N + 1` после двух победивших writes.

3. **Combat conflict mapping**
   - Character и NPC stale conflict в `1 → 1`;
   - Character и NPC stale conflict в `1 → N`;
   - результат — именно `GameBattleConflictException`;
   - `GameEconomyConflictException` не выходит из combat path.

4. **Wide target refusal**
   - stale expected version одной цели превращается в per-target
     `GAME_CONFLICT`;
   - другая цель успешно записывается;
   - wide command завершается;
   - версия отказавшей цели не меняется.

5. **Wide hard rollback**
   - первая цель успешно записана;
   - вторая цель получает mutation-time CAS conflict;
   - откатываются:
     - запись первой цели;
     - target reactions;
     - strike close;
     - battle version;
     - idempotency command.

6. **Outer transaction**
   - Character и NPC mutation выполняются внутри Game outer transaction;
   - любой CAS conflict или validation failure откатывает все связанные
     записи;
   - direct mutation без Game transaction не объявляется multi-entity
     atomic.

7. **Replay**
   - повтор того же key возвращает идентичный result;
   - version не увеличивается второй раз;
   - concurrent same-key execution оставляет ровно один applied effect;
   - другой body с тем же key получает conflict.

8. **Semantic parity**
   - одинаковый `putDamageSplit` проверяется для Character и NPC;
   - явно фиксируется, допускается ли NPC без Character validation;
   - ошибка и version behavior совпадают на storage/concurrency boundary.

## Final verdict

`BLOCKED`

Причина: ожидаемый optimistic concurrency контракт описан, но ни Character,
ни NPC mutation path не реализуют authoritative DB-level conditional CAS.
Для NPC существующий `replaceVersion()` отдельно нельзя считать
authoritative. До добавления AP spend и block effect необходимо закрыть
storage/CAS prerequisite и его acceptance tests.
