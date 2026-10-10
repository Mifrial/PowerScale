# Independent read-only verification: P1 Conditional CAS

Дата: 2026-10-08

## Verdict

# BLOCKED

P1 имеет рабочее CAS-ядро и проходящий функциональный тестовый срез, но
реализация не может считаться полностью verified или готовой к переходу к
AP prerequisite.

## Подтверждённые acceptance criteria

### SmartTable CAS

- `updateConditional()` использует typed filter с `id` и CAS-полем.
- CAS увеличивается на `expectedValue + 1`.
- CAS-поле запрещено в пользовательском payload.
- Обычный `update()` сохраняет unconditional semantics.
- При `false` multiple/sidecar не обновляются.
- Основной payload и sidecar выполняются в одной внешней transaction.
- Cache invalidation вызывается только после успешного CAS.
- SmartTable primitive не импортирует Character/Game.
- Все текущие реализации `IOpenedRecords` обновлены.
- Публичного произвольного mass-update или raw SQL пути не добавлено.

### Fresh conflict read

- `getCurrentById()` не использует cache.
- Используется `SELECT ... FOR UPDATE`.
- Multiple-данные гидратируются полностью.
- После failed CAS различаются missing row и existing row.
- `currentSheet` ограничен формой `{choices, sheet}` и не содержит
  metadata, id, visibility, timestamps или отдельный `actual_version`.

### Character/NPC

- `CharacterRepository::writeGuarded()` больше не использует
  `SELECT → PHP check → unconditional UPDATE` как защиту.
- `GameNpcRepository::replaceVersion()` использует настоящий DB CAS.
- `actual_version` не входит в guarded payload.
- Character и NPC используют одинаковую success/stale модель.
- Существующие validation и transaction boundaries сохранены.
- `GameChecks.php` не изменён и остался вне P1.
- Изменения `GameEconomyApply.php` ограничены прямыми Character/NPC
  conflict branches; shop conflict не получает current sheet.

### Combat и wide strike

- Character/NPC conflicts преобразуются в единый
  `GameBattleConflictException`.
- `currentVersion`, `currentSheet` и wide `target` доходят до error details.
- `GameEconomyConflictException` не выходит через combat adapter.
- Single-target stale проверяется до damage и roll.
- Wide preflight всех версий выполняется до damage и attacker roll.
- Wide stale не продолжает обработку остальных targets и не представляется
  как `targetResults[].code`.
- Mutation-time conflict второй цели выходит из per-target flow и должен
  откатывать outer transaction.
- Stale не создаёт replay command.
- Single-strike delivery/announce вызывается после transaction.

### Scope

- В изменённом diff нет AP storage/spend, defender arithmetic,
  penetration или reliability handler/content.
- Frontend, roadmap, readiness и status-файлы не изменялись.
- Обычные unrelated CRUD callers не переводились на CAS.

## Нарушения и блокеры

### 1. Wide preflight меняет обычную non-CAS semantics

`GameWideStrikes::close()` вызывает `GameWideStrikeResults::assertSheets()`
до `refusal()`. В `assertSheets()`/`assertSheet()` ошибки missing,
foreign или invalid target могут выйти top-level до per-target refusal.

Ранее эти случаи обрабатывались `refusal()` и становились
`targetResults[].code`. В результате изменена заявленная в плане обычная
non-CAS validation/not-found semantics.

Затронутые symbols:

- `modules/Roleplay/Game/Service/GameWideStrikes.php::close`
- `modules/Roleplay/Game/Service/GameWideStrikeResults.php::assertSheets`
- `modules/Roleplay/Game/Service/GameWideStrikeResults.php::refusal`
- `modules/Roleplay/Game/Service/GameWideStrikeResults.php::assertSheet`

### 2. Concurrent same-key execution не перечитывает сохранённый result

`GameStrikeCommandRepository::add()` и
`GameWideStrikeCommandRepository::add()` преобразуют unique conflict в
`GameBattleConflictException`.

После rollback проигравшая transaction не выполняет повторный `findByKey()`.
Поэтому concurrent same-key + same-body не гарантирует возврат сохранённого
result. Она может завершиться безопасным конфликтом, но acceptance
«same body возвращает сохранённый result» и требуемый replay read-back
не реализованы.

Затронутые symbols:

- `modules/Roleplay/Game/Repository/GameStrikeCommandRepository.php::add`
- `modules/Roleplay/Game/Repository/GameWideStrikeCommandRepository.php::add`
- `modules/Roleplay/Game/Service/GameStrikes.php`
- `modules/Roleplay/Game/Service/GameWideStrikes.php`

### 3. Отсутствует конкурентный CAS acceptance test

В репозитории не найден MySQL-тест с двумя независимыми DB connections,
фиксирующими один expected version и проверяющими, что успешна ровно одна
запись.

Существующие `ConditionalUpdateMysqlTest` проверяют match, mismatch,
payload rejection и unconditional update, но не sidecar rollback,
repeatable-read race или две независимые connections.

### 4. Новые P1 quality regressions

Текущий `phpcs --standard=phpcs-quality.xml.dist` для изменённых P1-файлов
сообщает:

- `IOpenedRecords.php`: 11 public methods вместо максимум 10.
- `OpenedRecords.php`: 12 public methods вместо максимум 10.
- `TableRows.php`: class complexity 44, `updateConditional()` 33 lines,
  неправильный method order.
- `CharacterConflictException.php`: отсутствует `@param $currentSheet`.
- `GameBattleConflictException.php`: отсутствуют `@param $currentSheet`,
  `$target`.
- `GameEconomyConflictException.php`: отсутствует `@param $currentSheet`.
- `GameNpcRepository.php::replaceVersion()`: 31 line.
- `GameStrikeRules.php`: 507 lines и 11 public methods.
- `GameStrikeSheetWrites.php::write()`: 36 lines.
- `GameWideStrikeResults.php`: неправильный method order; изменённый
  `one()` вырос с baseline 56 до 57 lines.
- `GameWideStrikes.php`: class вырос с baseline 608 до 609 lines;
  `close()` — с 31 до 32 lines.
- `GameEconomyApply.php::writeNpc()`: вырос с baseline 34 до 44 lines.

## Фактические проверки

### PHPUnit

Запущено из `www/mifrial/`:

```text
vendor/bin/phpunit
  modules/Core/SmartTable/tests/ConditionalUpdateMysqlTest.php
  modules/Core/SmartTable/tests/CrudMysqlTest.php
  modules/Roleplay/RuleSpace/tests/RuleSpaceCatalogPlacementQueryTest.php
  modules/Core/Auth/tests/AuthWorkflowAtomicityMysqlTest.php
  modules/Roleplay/Character/tests/CharacterMysqlTest.php
  modules/Roleplay/Character/tests/CharacterActualMutationMysqlTest.php
  modules/Roleplay/Game/tests/GameNpcMysqlTest.php
  modules/Roleplay/Game/tests/GameEconomyMysqlTest.php
  modules/Roleplay/Game/tests/GameStrikeMysqlTest.php
  modules/Roleplay/Game/tests/GameWideStrikeMysqlTest.php
```

Результат:

```text
OK (65 tests, 1825 assertions)
```

### Diff and quality

- `git diff --check`: PASS.
- `composer cs-check`: FAIL, exit code 8. PHP CS Fixer нашёл baseline
  style drift в 20 файлах и завершился до PHPCS.
- `composer quality`: FAIL, exit code 2.

`composer quality` также содержит baseline failures в неизменённых файлах,
включая:

- `modules/Core/SmartTable/Service/Schema/TableSchema.php`;
- `modules/Core/Auth/Service/PasswordResetService.php`;
- `modules/Roleplay/Game/Dto/GameDeliveryRecord.php`;
- `modules/Roleplay/Game/Dto/GameBattleCommandRecord.php`;
- `modules/Roleplay/Game/Dto/GameShopPositionRecord.php`;
- `modules/Roleplay/Game/Dto/GameCharacterRecord.php`;
- `modules/Roleplay/Game/Dto/GameJoinRequestRecord.php`;
- `modules/Roleplay/Game/Dto/GameInvitationRecord.php`;
- `modules/Roleplay/Game/Service/GameDonorChats.php`;
- `modules/Roleplay/Game/Service/GamePortFactory.php`;
- `modules/Roleplay/Game/Service/GameDeliveryListener.php`;
- `modules/Roleplay/Game/Service/GameBattleMutator.php`;
- `modules/Roleplay/Game/Service/GameBattleInitiatives.php`;
- `modules/Roleplay/Game/Service/GameDelivery.php`;
- `modules/Roleplay/Game/Service/GameBattles.php`;
- `modules/Roleplay/Game/Service/GameProjections.php`;
- `modules/Roleplay/Game/Service/GameEconomyPortFactory.php`;
- `modules/Roleplay/Game/Service/GameNpcs.php`;
- `modules/Roleplay/Game/Service/GameDeliverySync.php`;
- `modules/Roleplay/Game/Repository/GameEconomyOperationRepository.php`;
- `modules/Roleplay/Game/Repository/GameBattleRepository.php`;
- `modules/Roleplay/Game/Repository/GameDeliveryRepository.php`;
- `modules/Roleplay/Character/Dto/CharacterCustomRule.php`;
- `modules/Roleplay/Character/Dto/CharacterChoices.php`;
- `modules/Roleplay/Character/Service/CharacterSave.php`;
- `modules/Roleplay/Character/Service/CharacterSaveAssembly.php`;
- `modules/Roleplay/Character/Service/Sheet/CharacterCharacteristicPurchases.php`;
- `modules/Roleplay/Character/Service/CharacterSheetEngine.php`;
- `modules/Roleplay/Character/Service/CharacterOsSteps.php`;
- `modules/Roleplay/Character/Service/CharacterActualStateRows.php`;
- `modules/Roleplay/Rule/Dto/RuleVersionBody.php`;
- `modules/Roleplay/Rule/Dto/RuleVersionRecord.php`;
- `modules/Roleplay/Rule/Dto/Spec/*`;
- `modules/Roleplay/Rule/Spec/*`;
- `modules/Roleplay/Rule/Service/Rules.php`;
- `modules/Roleplay/Rule/Service/RulePortFactory.php`;
- `modules/Roleplay/Mechanic/Service/Handler/AdvantageDisadvantageHandler.php`;
- `modules/Roleplay/Mechanic/Service/Handler/SixOneRuleHandler.php`;
- `modules/Roleplay/RuleSpace/Service/RuleSpaceCommitDraftMapper.php`.

HEAD baseline comparison confirmed that the quality errors listed above as
P1 regressions were absent in the corresponding unchanged versions, except
for pre-existing errors explicitly marked baseline.

## Scope and working tree

`git diff --name-only` содержит только backend-файлы P1:

- Core SmartTable CAS DTO/service/interface/test;
- Character conflict/repository/actual mutation;
- Game NPC/conflict/combat/wide strike;
- ожидаемые SmartTable test doubles.

`GameChecks.php`, frontend, roadmap, readiness, status и существующие
audit-файлы в diff отсутствуют. Все untracked-файлы, присутствовавшие до
проверки, сохранены. `reset`, `checkout`, `stash` и frontend dev-server не
использовались.

## Final decision

P1: **BLOCKED**.

До AP prerequisite необходимо отдельно устранить:

1. wide preflight regression обычных non-CAS refusal semantics;
2. concurrent same-key replay read-back или формально зафиксировать
   безопасный conflict как допустимую semantics;
3. отсутствующее concurrent CAS acceptance coverage;
4. новые P1 quality errors.
