# P1: общий authoritative conditional-CAS для Character/NPC

## Базовая точка

- HEAD: `26048f3778555fbf4646c7eb861ce92fd7b23c7f`.
- Tracked diff отсутствует.
- Все существующие untracked остаются без изменений.
- Scope только CAS boundary. Defender roll, penetration, reliability и AP-storage не реализуются.

## 1. Backend contract

Успешная запись:

```text
UPDATE <table>
SET <payload>, actual_version = expectedVersion + 1
WHERE id = :id
  AND actual_version = :expectedVersion
```

Требования:

- проверка версии и increment выполняются одним SQL conditional update;
- отсутствие предварительного PHP read-check как механизма защиты;
- `affected rows = 1` — запись принята;
- `affected rows = 0` — конфликт или отсутствие строки;
- после `0` выполнить свежий read без cache:
  - нет строки → `NotFound`;
  - строка есть → conflict с актуальными `currentVersion` и `currentSheet`;
- проигравший payload не записывается;
- `actual_version` увеличивается ровно один раз.

Табличные схемы не меняются: `character.actual_version` и
`game_npc.actual_version` уже существуют.

## 2. Owners

- SmartTable Core — SQL conditional-update primitive.
- Character:
  - `CharacterRepository::writeGuarded`;
  - `CharacterActualMutations` остаётся доменным mutation port;
  - `character.actual_version` — authoritative CAS counter.
- NPC:
  - `GameNpcRepository::replaceVersion`;
  - `game_npc.version` — authoritative sheet;
  - `game_npc.actual_version` — CAS counter.
- Game:
  - `GameStrikeSheetWrites` — единый combat adapter и mapping конфликтов;
  - `GameStrikes::close` — outer transaction для `1 → 1`;
  - `GameWideStrikes::commitClose` — outer transaction для `1 → N`.

## 3. Exact files

SmartTable:

- `www/mifrial/modules/Core/SmartTable/Service/Query/TableRows.php`
- `www/mifrial/modules/Core/SmartTable/Service/OpenedRecords.php`
- `www/mifrial/modules/Core/SmartTable/Interface/Service/IOpenedRecords.php`

Добавить узкий метод conditional update, например `updateIfEquals(...)`,
возвращающий `bool`. Обычный `update()` оставить с прежней unconditional
semantics для прочих CRUD-сценариев.

Character:

- `www/mifrial/modules/Roleplay/Character/Repository/CharacterRepository.php`
- `www/mifrial/modules/Roleplay/Character/Exception/CharacterConflictException.php`

`writeGuarded()` должен убрать correctness-зависимость от
`SELECT → PHP check → UPDATE`; при конфликте передавать актуальный sheet в
exception details.

NPC:

- `www/mifrial/modules/Roleplay/Game/Repository/GameNpcRepository.php`

`replaceVersion()` должен использовать тот же conditional primitive. Его
repository-native exception можно сохранить, но combat adapter обязан
преобразовывать её единообразно.

Combat mapping и orchestration:

- `www/mifrial/modules/Roleplay/Game/Service/GameStrikeSheetWrites.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameStrikes.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameWideStrikes.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameWideStrikeResults.php`
- `www/mifrial/modules/Roleplay/Game/Exception/GameBattleConflictException.php`
- `www/mifrial/modules/Roleplay/Game/Service/GameStrikeRules.php`

`GameBattleConflictException` должен поддерживать:

- `currentVersion`;
- `currentSheet`;
- для wide target-level диагностики — `target`.

Combat boundary не должен пропускать `GameEconomyConflictException`.

## 4. Conflict semantics

Общее отображение Character/NPC в combat:

```text
CharacterConflictException
GameEconomyConflictException
        ↓
GameBattleConflictException
        ↓
GAME_CONFLICT
```

Error details:

```text
currentVersion
currentSheet
target          // только wide conflict
```

`currentSheet` — свежий authoritative sheet конфликтующей цели, без
повторного применения операции.

Stale command:

- не закрывает strike;
- не меняет sheet;
- не меняет battle version;
- не пишет reaction;
- не создаёт idempotency result;
- не публикует effect;
- не списывает AP;
- не возвращает roll как принятый результат;
- оставляет команду retryable после обновления `expectedSheetVersion`.

Предварительный stale-check должен происходить до authoritative roll. При
mutation-time race условный update откатывается outer transaction; вычисленный,
но не committed результат не публикуется.

## 5. Replay/idempotency

Успешный close и запись command result остаются в одной outer transaction.

Для того же `(gameId, idempotencyKey)` и того же body:

- вернуть сохранённый result;
- не выполнять CAS;
- не применять effect;
- не выполнять повторный spend;
- не выполнять повторный authoritative roll;
- версии Character/NPC не увеличивать.

Тот же key с другим body:

- `GAME_CONFLICT`;
- никаких mutation.

CAS conflict до записи command:

- command row не появляется;
- strike остаётся pending/open;
- повтор с тем же key после обновления expected version может быть выполнен
  заново, поскольку успешного replay record ещё нет.

Для конкурентного same-key execution acceptance должна подтвердить: одна
команда коммитится, вторая не создаёт вторую mutation и получает сохранённый
result либо безопасный retryable conflict без side effects.

## 6. Transaction boundaries

`1 → 1`, `GameStrikes::close`:

```text
sheet CAS
→ sheet effect
→ strike close
→ battle version increment
→ idempotency command
→ commit
```

`1 → N`, `GameWideStrikes::commitClose`:

```text
validate all target versions
→ all target CAS/effects
→ target reactions
→ wide strike close
→ battle version increment
→ idempotency command
→ commit
```

`GameStrikeSheetWrites` и `GameNpcRepository::replaceVersion` не владеют
отдельной multi-entity transaction. Они должны присоединяться к уже открытой
transaction SmartTable connection.

## 7. Wide close semantics

Текущую модель, где stale target превращается в per-target refusal и остальные
цели коммитятся, для этого P1 заменить:

- stale одной цели делает весь wide close `GAME_CONFLICT`;
- close остаётся pending/open;
- ни одна цель не получает effect;
- ни одна reaction не закрывается;
- strike и battle version не меняются;
- idempotency result не записывается;
- актуальный sheet и target возвращаются в conflict details.

Если CAS-конфликт возникает после успешной записи предыдущей цели, outer
transaction откатывает:

- предыдущие Character/NPC writes;
- reactions;
- strike close;
- battle version;
- command result.

## 8. Acceptance-test matrix

1. `TableRows` conditional update:
   - matching version → ровно одна строка обновлена;
   - mismatch → `false`, payload и version неизменны;
   - SQL содержит `id` и expected-version predicate;
   - отдельный read-check не является защитой.

2. Character success:
   - expected `N` записывает payload;
   - version становится `N+1`;
   - возвращается свежий record.

3. NPC success:
   - аналогичная семантика для `version` и `actual_version`.

4. Character stale:
   - `GAME_CONFLICT`;
   - возвращены `currentVersion` и `currentSheet`;
   - payload, effect, roll и version не изменились.

5. NPC stale:
   - тот же результат;
   - `GameEconomyConflictException` не выходит из combat path.

6. Concurrent Character writes:
   - две независимые DB connections используют expected `N`;
   - ровно одна запись успешна;
   - вторая получает conflict;
   - version равна `N+1`;
   - проигравший payload отсутствует.

7. Concurrent NPC `replaceVersion`:
   - идентичные гарантии;
   - отсутствует двойное увеличение version.

8. Single-target stale close:
   - strike остаётся open;
   - sheet, battle, strike и command unchanged;
   - roll/effect/AP отсутствуют.

9. Wide preflight stale:
   - одна stale target оставляет весь close pending;
   - ни одна цель не изменена;
   - актуальный sheet stale target возвращён.

10. Wide mutation-time conflict:
    - поздний CAS conflict второй цели;
    - запись первой цели полностью откатана;
    - reactions, strike, battle и command также откатаны.

11. Same-key replay:
    - result идентичен исходному;
    - version не увеличивается;
    - повторного effect/spend/roll нет.

12. Same-key different body:
    - `GAME_CONFLICT`;
    - mutation отсутствует.

13. Character/NPC parity:
    - одинаковые expected-version, increment, stale и conflict semantics;
    - различия Character validation и NPC `applyToDocument()` не расширяются
      в этот P1.

## 9. Test files

- `www/mifrial/modules/Core/SmartTable/tests/ConditionalUpdateMysqlTest.php`
- `www/mifrial/modules/Roleplay/Character/tests/CharacterActualMutationMysqlTest.php`
- `www/mifrial/modules/Roleplay/Game/tests/GameNpcMysqlTest.php`
- `www/mifrial/modules/Roleplay/Game/tests/GameStrikeMysqlTest.php`
- `www/mifrial/modules/Roleplay/Game/tests/GameWideStrikeMysqlTest.php`

Конкурентные проверки должны использовать две независимые DB connections и
barrier между read и conditional update. Эти тесты являются acceptance plan и
не запускались в текущем read-only проходе.

## 10. Границы

Не менять:

- `docs/`;
- roadmap/readiness/audit-файлы;
- frontend;
- defender roll и block;
- penetration;
- reliability handler/content;
- AP owner/storage/spend contract;
- database schema и новые таблицы.

`GameNpcRepository::save`, economy/check-specific error mapping и прочие
non-combat callers требуют отдельного аудита; их нельзя использовать как
доказательство combat CAS, хотя вызовы `replaceVersion()` получат исправленный
storage-level guard.
