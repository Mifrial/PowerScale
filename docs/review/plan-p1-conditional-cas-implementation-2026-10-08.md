# План реализации P1: общий authoritative conditional CAS для Character/NPC

**Статус:** `PLAN ONLY`  
**Дата:** 2026-10-08  
**Область:** только backend P1; frontend, roadmap, readiness, статусы и
существующие audit-файлы не изменяются.

## 1. Жёсткие границы

- Единственный путь к MySQL на всём проекте — публичный API SmartTable.
  Репозитории, Game services и тесты не получают Query Builder/PDO.
- SmartTable расширяется generic-примитивом работы с открытой картой. Он не
  знает названия таблиц Character/NPC, структуру `choices`/`sheet`,
  `game_npc.version`, combat и потребности соседних модулей.
- Обычный `IOpenedRecords::update()` и его unconditional semantics не меняются.
- Новые таблицы, колонки, миграции и frontend не нужны.
- Не входят в P1: AP, eligibility, defender roll, block arithmetic,
  penetration, reliability, auto-ignore и изменение JSON-контракта этих функций.

## 2. SmartTable: generic update-by-filter и CAS-sugar

### 2.1. Generic execution base

Внутри SmartTable заложить переиспользуемую операцию вида:

```text
updateByFilter(filter, assembledScalarValues): affectedRows
```

В текущем P1 filter представлен существующим typed `FilterGroup`; для CAS
операция строит локальную группу `id = rowId AND casField = expectedValue`.
Общий execution base работает только с собственной открытой картой и
подготовленными scalar значениями. Это generic-основа set-based update, а не
знание о Character, NPC или combat. Она должна:

- использовать typed filter текущей карты, а не пользовательский SQL;
- требовать непустой filter и отвергать случайный update всей таблицы;
- возвращать количество затронутых строк;
- не записывать multiple/mfv sidecar сама;
- не интерпретировать `0` как domain conflict;
- не менять внешний контракт обычного `update()`.

Для применения существующего `ListQueryCompiler` к update-builder добавить
его зависимость в `TableRows` и собрать её в
`SmartTableSupport::tableRows()`. Нельзя обращаться к компилятору напрямую
из репозитория или Game service. P1 использует только локальные
`id`/CAS-фильтры и не расширяет write-фильтры до reference paths, subquery
или multiple.

В P1 эта операция остаётся внутренней частью SmartTable service layer.
Публичный массовый `updateByFilter()` для прикладных callers требует
отдельного контракта: поддерживаемые write-фильтры, семантика affected rows,
ограничения reference/multiple и правила sidecar. Его нельзя молча добавить
как произвольный аналог `getList`.

### 2.2. Публичный CAS-sugar

Добавить в `IOpenedRecords` типизированную обёртку:

```text
updateConditional(rowId, ConditionalCas, values): bool
```

`ConditionalCas` — SmartTable DTO, содержащий только имя CAS-поля и
ожидаемое целое значение. Он не содержит таблиц, доменных сущностей или
произвольного SQL.

Смысл параметров:

- `rowId` — стандартный `id` строки;
- `ConditionalCas.casField` — поле текущей открытой карты, а не имя таблицы
  или доменный alias;
- `ConditionalCas.expectedValue` — ожидаемый CAS-счётчик;
- `values` — patch всех остальных полей, включая несколько scalar и
  multiple-полей.

Это не операция обновления одного поля: CAS-поле является единственным
управляемым счётчиком, а `$values` может содержать весь допустимый patch.
`updateConditional` — безопасный single-row sugar над generic
`updateByFilter`, а не отдельная таблицезависимая реализация.

Перед SQL primitive обязан:

1. проверить, что `casField` есть в текущей карте и является допустимым
   scalar integer field;
2. отвергнуть `casField` внутри `values`, чтобы пользовательский payload не
   мог задавать или подменять CAS;
3. использовать существующие `RowAssembler`-правила для JSON, обычных полей и
   multiple-полей;
4. не выполнять отдельный `SELECT` как часть защиты от гонки.

Принимаемые ошибки должны следовать обычному `update()`: invalid map/field,
required field, schema/row write, reference и unique constraint. `false` —
это только результат отсутствия matching rows, а не исключение SmartTable.

### 2.3. SQL и результат

Для любой карты `updateConditional` строит filter для generic base:

```text
UPDATE <opened table>
SET <assembled scalar payload>, <casField> = expectedValue + 1
WHERE id = rowId
  AND <casField> = expectedValue
```

Имя таблицы, имя поля и filter берутся только из проверенной
`SmartTableDefinition` и typed DTO; пользовательский SQL и raw-выражения
наружу не выдаются. Значение `expectedValue + 1` вычисляется как
контролируемое значение CAS-sugar, а не принимается из payload.

- `affected rows = 1` — CAS принят;
- `affected rows = 0` — `false`, без вывода о том, отсутствует строка или
  версия устарела;
- результат больше одной строки — ошибка записи, поскольку `id` обязан быть
  уникальным.

Счётчик меняется на `expectedValue + 1` только внутри этого SQL. Так как
значение действительно меняется, matching update обязан давать ровно одну
затронутую строку.

### 2.4. Multiple, transaction и cache

`updateConditional` переиспользует generic base и существующую atomic-write
механику `TableRows`:

1. подготовить scalar и multiple payload;
2. выполнить conditional SQL;
3. если SQL вернул `false`, немедленно завершить без
   `replaceMultiple`;
4. если SQL успешен, записать sidecar/mfv-поля;
5. вернуть `true`.

Основной payload, CAS и sidecar должны находиться в одной внешней
транзакции. При уже открытой транзакции SmartTable не делает собственный
commit/rollback; при самостоятельном вызове сохраняется текущая
`writeAtomic`-семантика.

`OpenedRecords` вызывает инвалидацию cache только после успешного primitive.
В список изменённых полей для `noteUpdate()` входят patch-поля и CAS-поле:
это нужно для get-кэша и list-тегов, фильтрующих по счётчику. На mismatch
не должно быть cache-write или cache-invalidation. Свежий read для разрешения
конфликта выполняется через `getCurrentById()`.

Обычный `update()` не переводится на `updateConditional`, не получает
conditional semantics и сохраняет прежнюю проверку существования строки.

### 2.5. Fresh read после failed CAS

В текущем коде `cacheTtl = null` отключает cache, но не задаёт MySQL
transaction isolation. `SmartTableGateway` переиспользует уже открытую
транзакцию, а Game single/wide flows читают target до conditional write.
При стандартном InnoDB snapshot повторный обычный `getById()` внутри той же
транзакции может не увидеть commit конкурирующей connection. Это нарушает
требование вернуть свежий `currentVersion/currentSheet`.

Принятое решение: добавить generic SmartTable
`IOpenedRecords::getCurrentById()` для current read. Реализация выполняет
`SELECT ... FOR UPDATE`, не использует cache, гидратирует multiple и работает
в текущей внешней транзакции. Repository вызывает его после
`affected rows = 0`; при отсутствии строки получает `NotFound`, при наличии
строит conflict из этой актуальной строки.

`READ COMMITTED` глобально не включается: текущая transaction semantics
проекта не меняется. Отдельное соединение после rollback также не требуется.

### 2.6. SmartTable-файлы

Планируемые backend-изменения:

- `modules/Core/SmartTable/Dto/ConditionalCas.php`;
- `modules/Core/SmartTable/Interface/Service/IOpenedRecords.php`;
- `modules/Core/SmartTable/Service/OpenedRecords.php`;
- `modules/Core/SmartTable/Service/Query/TableRows.php`;
- `modules/Core/SmartTable/Service/SmartTableSupport.php` — wiring
  `ListQueryCompiler` в `TableRows`.

Нельзя добавлять в SmartTable импорты Character/Game, таблиц соседних
модулей или узкие имена их полей.

## 3. Единый conflict payload

### 3.1. Доменный лист

Для stale CAS конфликт несёт:

```text
currentVersion: int
currentSheet: {
  choices: map,
  sheet: map
}
```

`currentSheet` строится только из свежей authoritative строки. В него не
входят `id`, owner/game metadata, `actual_version` как отдельное поле,
timestamps, visibility и другие колонки строки.

Если строка после `affected rows = 0` не найдена, репозиторий возвращает
свой `NotFound`, а не conflict.

### 3.2. Исключения

Изменить:

- `CharacterConflictException`;
- `GameEconomyConflictException`;
- `GameBattleConflictException`.

Дополнительные поля должны быть optional там, где исключение используется
для конфликтов idempotency key или battle version:

- `currentVersion`;
- `currentSheet`, если конфликт относится к authoritative листу;
- `target`, только для wide combat conflict.

Старые callers, которым нужен только `currentVersion` или конфликт ключа,
не получают фиктивный лист.

## 4. Character CAS

### 4.1. `CharacterRepository::writeGuarded()`

Файл:

- `modules/Roleplay/Character/Repository/CharacterRepository.php`.

Переделать общий guarded write так, чтобы его correctness не зависела от
цепочки `SELECT → PHP version check → unconditional UPDATE`:

1. сохранить текущую domain validation и сборку patch;
2. убрать `actual_version` из передаваемого пользовательского payload;
3. вызвать `characterRecords->updateConditional(...)` с
   `ConditionalCas('actual_version', $expectedVersion)`;
4. при `true` прочитать результат через обычный SmartTable read;
5. при `false` выполнить `getCurrentById()`;
6. отсутствие строки преобразовать в `CharacterNotFoundException`;
7. существующую строку преобразовать в `CharacterConflictException` с
   актуальными `actual_version`, `choices` и `sheet`.

`actual_version` нигде не вычисляется caller-ом и не проходит через
`update()` как обычное поле. `updated_at`, имя, active, choices, sheet и
rules revision сохраняют нынешнюю domain validation semantics.

Guarded write остаётся участником внешней транзакции через
`ISmartTableGateway::transaction`. После исключения конфликт должен
откатывать все изменения этой операции.

### 4.2. Другие источники Character conflict

Проверить все места создания `CharacterConflictException`, включая
`CharacterActualMutations::locked()` и
`GameEconomyApply::writeCharacter()`. В ветке economy без операций сейчас
исключение создаётся напрямую, поэтому она тоже должна прочитать свежий
Character record и передать `{choices, sheet}`. Если такой конфликт доходит
до combat adapter, он также должен иметь свежий `currentSheet`; нельзя
оставить второй, урезанный формат только для предварительного read.

Предварительное чтение в `CharacterActualMutations` допустимо как источник
документа, необходимого для вычисления операции. Оно не является механизмом
защиты: окончательную авторитетность гарантирует только repository CAS.

Планируемые файлы:

- `modules/Roleplay/Character/Repository/CharacterRepository.php`;
- `modules/Roleplay/Character/Exception/CharacterConflictException.php`;
- `modules/Roleplay/Character/Service/CharacterActualMutations.php` — только
  для согласования conflict details, если это требуется текущим потоком.
- `modules/Roleplay/Game/Service/GameEconomyApply.php` — только для прямых
  Character/NPC conflict branches без вызова CAS repository; shop conflict
  остаётся без `currentSheet`.

## 5. NPC CAS

Файлы:

- `modules/Roleplay/Game/Repository/GameNpcRepository.php`;
- `modules/Roleplay/Game/Exception/GameEconomyConflictException.php`.

`replaceVersion()` должен:

1. не использовать предварительный PHP version check как защиту;
2. отправлять новый документ `version` через тот же
   `updateConditional(..., ConditionalCas('actual_version', expected), payload)`;
3. не принимать `actual_version` из payload;
4. при success вернуть свежий `GameNpcRecord`;
5. при `false` выполнить `getCurrentById()`;
6. вернуть `GameNotFoundException`, если NPC отсутствует;
7. вернуть `GameEconomyConflictException`, если строка существует.

В NPC authoritative sheet — `version`. Conflict details получают:

```text
currentVersion = game_npc.actual_version
currentSheet = {
  choices: version.choices,
  sheet: version.sheet
}
```

`GameEconomyConflictException` сохраняется на repository boundary для
существующих economy callers. Пустой `currentSheet` не синтезируется при
конфликтах ключа, shop или других economy-операциях, не относящихся к NPC
листу. Прямая NPC conflict branch в `GameEconomyApply::writeNpc()` также
должна перечитать NPC и передать свежий `version.choices`/`version.sheet`.

Существующий `save()` не изменять без необходимости для этого P1 и не
использовать как доказательство combat CAS. Его callers требуют отдельного
решения, если они должны получить тот же guard.

## 6. Combat adapter и single-target flow

### 6.1. Единое преобразование исключений

Файл:

- `modules/Roleplay/Game/Service/GameStrikeSheetWrites.php`.

`write()` должен одинаково преобразовывать:

- `CharacterConflictException`;
- `GameEconomyConflictException`;

в `GameBattleConflictException`, перенося `currentVersion` и
`currentSheet`. Для wide вызов передаёт identity цели:

```text
target = { type: character|npc, id: int }
```

`GameEconomyConflictException` не должен выходить наружу из combat path.
Invalid/not-found mapping сохраняется отдельно и не маскируется под CAS.

### 6.2. Preflight и race

В `GameStrikes` свежий version/sheet preflight выполняется до
`damageOf`/authoritative roll. Текущий код сначала вычисляет `damageOf`, а
`assertSheet()` выполняется только внутри close-транзакции перед `rateOf`;
порядок нужно изменить так, чтобы известный stale был обнаружен раньше
обоих вычислений. При stale preflight сразу выбрасывается retryable
`GAME_CONFLICT` с листом; roll, effect, reaction, close и command result ещё
не создавались.

Preflight не заменяет CAS. Если другая DB connection изменит лист между
preflight и записью, repository conditional update выбросит тот же conflict,
а outer transaction откатит всё вычисленное внутри неё.

В single-target close stale обязан:

- оставить strike open/pending;
- не менять sheet или battle version;
- не писать reaction;
- не создавать idempotency result;
- не публиковать effect;
- не возвращать roll как принятый;
- не списывать AP.

Изменять только backend orchestration:

- `modules/Roleplay/Game/Service/GameStrikes.php`;
- `modules/Roleplay/Game/Service/GameStrikeRules.php` — вернуть для
  Character свежий snapshot `{actualVersion, choices, sheet}`, используя уже
  имеющийся `ICharacters`; не добавлять новый DB path;
- `modules/Roleplay/Game/Service/GameStrikeSheetWrites.php`;
- `modules/Roleplay/Game/Exception/GameBattleConflictException.php`.

## 7. Wide strike: all-or-nothing close

### 7.1. Preflight

Файлы:

- `modules/Roleplay/Game/Service/GameWideStrikes.php`;
- `modules/Roleplay/Game/Service/GameWideStrikeResults.php`.

До первого roll/effect выполнить отдельный CAS-preflight всех target rows.
Текущий `GameWideStrikes::close()` вычисляет `damageOf()` и `rateOf()` до
`commitClose()`, а `GameWideStrikeResults::build()` сейчас проверяет каждую
цель только внутри per-target `refusal()`. Поэтому preflight нельзя просто
добавить внутрь `one()`: его нужно вызвать до `damageOf()`/`rateOf()`, а
`refusal()` больше не должен превращать stale CAS в target-level code.

CAS-preflight проверяет:

- структурно валидную identity цели, достаточную для чтения её authoritative
  строки;
- ожидаемую version каждой цели;
- свежий sheet каждой цели для conflict response.

Для Character snapshot должен приходить через уже имеющийся
`GameStrikeRules::$characters` (новый метод snapshot без нового DB-доступа).
Для NPC snapshot читается через уже имеющийся `GameNpcRepository` с
проверкой `game_id`. В обоих случаях preflight read идёт через текущий
SmartTable read boundary без cache; если он выполняется внутри внешней
транзакции, используется `getCurrentById()`.

Если target не проходит существующую non-CAS проверку принадлежности или
имеет invalid/not-found форму, эта проверка остаётся в текущем per-target
`refusal()`-контуре. Она не должна превращаться в новый whole-close конфликт
без отдельного решения. При этом stale CAS валидной target должен быть
выявлен отдельным preflight до начала roll.

Для этого `GameWideStrikeResults::refusal()` нужно разделить: убрать из него
CAS-проверку версии, вынести её в общий preflight, а обработку обычных
non-CAS refusal сохранить.

Stale любой цели немедленно прерывает close с
`GameBattleConflictException(currentVersion, currentSheet, target)`.
Текущую модель, в которой stale превращается в per-target refusal и
остальные цели продолжают close, удалить.

Не менять несвязанные semantics обычного per-target invalid/not-found
refusal. Но stale/CAS conflict не может быть представлен как `ignore`,
`GAME_CONFLICT` внутри `targetResults` или автоматически принятый результат.

### 7.2. Mutation-time race и rollback

В одной outer SmartTable transaction порядок остаётся:

```text
preflight all targets
→ conditional CAS/effect каждой цели
→ reactions
→ strike close
→ battle version
→ command result
→ commit
```

`GameWideStrikeResults` передаёт target identity в `GameStrikeSheetWrites`.
Conflict второй или последующей цели не перехватывается как обычный
target result и выходит наружу. Rollback outer transaction обязан отменить:

- mutations ранее записанных Character/NPC;
- reactions;
- close strike;
- battle version;
- idempotency command result.

После rollback strike остаётся pending/open, а ответ содержит актуальные
`currentVersion`, `currentSheet` и конфликтующую `target`.

## 8. Replay и конкурентный same-key execution

Сохранить текущую semantics:

- тот же key и тот же body возвращают сохранённый result;
- CAS/effect/roll/spend второй раз не выполняются;
- version второй раз не увеличивается;
- тот же key с другим body даёт `GAME_CONFLICT`;
- stale до вставки command не создаёт replay record.

Проверить оба strike close path (`GameStrikes` и `GameWideStrikes`):

1. replay lookup остаётся до mutation;
2. mutation и запись command result находятся в одной outer transaction;
3. unique conflict на конкурентной вставке command откатывает проигравшую
   transaction;
4. после rollback проигравший execution перечитывает command:
   - одинаковое body → возвращает сохранённый result;
   - другое body → `GAME_CONFLICT`;
5. delivery/announce выполняется только после подтверждённого commit.

Нельзя записывать command result до CAS или создавать replay record для
stale failure.

## 9. Acceptance tests

Добавить или обновить только backend MySQL tests.

### SmartTable

- matching `updateConditional` обновляет ровно одну строку и увеличивает CAS на
  один;
- mismatch возвращает `false`, не меняет scalar payload и version;
- CAS field нельзя передать в пользовательском payload;
- mismatch не меняет multiple/sidecar;
- основной payload и sidecar откатываются вместе при ошибке внутри TX;
- обычный unconditional `update()` сохраняет прежнюю semantics.

### Character/NPC

- success и stale для Character;
- success и stale для NPC;
- stale details содержат полный `{choices, sheet}` без metadata/full row;
- две независимые DB connections читают один expected version;
- ровно одна запись успешна;
- проигравший payload отсутствует;
- проигравший после failed CAS получает через current read именно
  version/sheet победившей записи, а не repeatable-read snapshot;
- version увеличивается ровно один раз;
- Character и NPC имеют одинаковую CAS/conflict parity.
- прямые no-op conflict branches в `GameEconomyApply` также возвращают
  свежий лист для Character/NPC, а shop conflict не получает лист.

### Combat

- `GameEconomyConflictException` не выходит из `GameStrikeSheetWrites`;
- single-target stale происходит до roll/effect и оставляет strike open;
- single-target stale не меняет battle, reaction, command и sheet;
- single-target и wide `GAME_CONFLICT` возвращают актуальный
  `currentVersion` и `currentSheet` в error details;
- wide preflight stale не меняет ни одну цель;
- wide mutation-time conflict второй цели откатывает mutation первой цели;
- rollback также отменяет reactions, strike close, battle version и command;
- wide conflict возвращает identity stale target;
- stale не становится explicit ignore или auto-ignore.

### Replay

- same key + same body возвращает сохранённый result без повторной mutation;
- same key + different body даёт conflict без mutation;
- stale failure не создаёт replay row;
- конкурентный same-key execution создаёт не более одной mutation и одного
  сохранённого result;
- после replay delivery не дублируется.

Предпочтительные существующие suite-файлы:

- `modules/Core/SmartTable/tests/ConditionalUpdateMysqlTest.php` —
  новый suite при отсутствии подходящего;
- `modules/Core/SmartTable/tests/CrudMysqlTest.php` — regression обычного
  unconditional `update()`;
- `modules/Roleplay/RuleSpace/tests/RuleSpaceCatalogPlacementQueryTest.php` —
  добавить no-op реализации `updateConditional()` и `getCurrentById()` в
  `CatalogOpenedRecords`, потому что этот test double непосредственно
  implements `IOpenedRecords`;
- `modules/Core/Auth/tests/FailingOpenedRecords.php` — проксировать
  `updateConditional()` и `getCurrentById()` в test double, который также
  непосредственно implements `IOpenedRecords`;
- `modules/Roleplay/Character/tests/CharacterMysqlTest.php` — все публичные
  repository guarded writes (`replacePayload`, `replaceSaved`,
  `replaceMigrated`, `setActive`);
- `modules/Roleplay/Character/tests/CharacterActualMutationMysqlTest.php`;
- `modules/Roleplay/Game/tests/GameNpcMysqlTest.php`;
- `modules/Roleplay/Game/tests/GameEconomyMysqlTest.php` — обновить проверки
  прямых Character/NPC economy conflict branches;
- `modules/Roleplay/Game/tests/GameStrikeMysqlTest.php`;
- `modules/Roleplay/Game/tests/GameWideStrikeMysqlTest.php`.

Конкурентный тест обязан использовать две независимые SmartTable DB
connections и фиксировать одинаковый expected version до conditional update.
SQL в тестах напрямую не вызывается.

В `GameWideStrikeMysqlTest.php` нужно заменить существующую проверку, где
устаревшая первая цель становится `targetResults[0].code =
GAME_CONFLICT`, а вторая цель продолжает close. Новый acceptance должен
проверять top-level conflict, отсутствие mutations обеих целей и сохранение
wide strike open/pending.

## 10. Проверки после реализации

Запустить только релевантные backend checks из `www/mifrial/`:

```text
vendor/bin/phpunit modules/Core/SmartTable/tests/ConditionalUpdateMysqlTest.php
vendor/bin/phpunit modules/Core/SmartTable/tests/CrudMysqlTest.php
vendor/bin/phpunit modules/Roleplay/RuleSpace/tests/RuleSpaceCatalogPlacementQueryTest.php
vendor/bin/phpunit modules/Core/Auth/tests/AuthWorkflowAtomicityMysqlTest.php
vendor/bin/phpunit modules/Roleplay/Character/tests/CharacterMysqlTest.php
vendor/bin/phpunit modules/Roleplay/Character/tests/CharacterActualMutationMysqlTest.php
vendor/bin/phpunit modules/Roleplay/Game/tests/GameNpcMysqlTest.php
vendor/bin/phpunit modules/Roleplay/Game/tests/GameEconomyMysqlTest.php
vendor/bin/phpunit modules/Roleplay/Game/tests/GameStrikeMysqlTest.php
vendor/bin/phpunit modules/Roleplay/Game/tests/GameWideStrikeMysqlTest.php
composer cs-check
composer quality
```

Frontend dev-server не запускать. Не выполнять `reset`, `checkout` или
`stash`. Все существующие untracked-файлы сохранить.

## 11. Отдельный follow-up: GameChecks

`modules/Roleplay/Game/Service/GameChecks.php` не входит в P1.

Фактические причины вынесения:

- `sheet()` напрямую создаёт `GameBattleConflictException` только с
  version для Character и NPC;
- `writeSheet()` преобразует `CharacterConflictException`, но сейчас
  передаёт только `currentVersion`;
- `writeNpc()` вызывает `GameNpcRepository::replaceVersion()`, поэтому
  после P1 может получить `GameEconomyConflictException`, который этот
  path сейчас не преобразует.

Отдельная задача должна определить и реализовать envelope/currentSheet и
mapping для GameChecks целиком. P1 меняет только объявленный
`GameStrikeSheetWrites` combat boundary и не смешивает эти решения с
conditional CAS Character/NPC.

## 12. Итог реализации

После выполнения кода итоговый отчёт должен содержать:

1. список фактически изменённых backend-файлов;
2. краткое описание CAS boundary: generic SmartTable primitive,
   repository conflict read и outer transaction;
3. выполненные тесты и quality checks с результатом;
4. оставшиеся проблемы, явно помеченные как находящиеся вне P1.

В рамках текущего шага backend не изменяется, тесты и quality checks не
запускаются: создаётся только этот файл-план.
