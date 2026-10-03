# Сквозное ревью кодовой базы — baseline 2026-09

**Статус:** `COMPLETE_WITH_OPEN_QUESTIONS`  
**Baseline:** текущее рабочее дерево, включая WIP-файлы  
**HEAD:** `75639f9cf9fe4e9ddb76a05b836cca7035966520`  
**Дата запуска:** 2026-09-26

Этот документ является текущим evidence-backed журналом кампании. Он не
заменяет канонический ТР и не означает, что перечисленные проблемы уже
исправляются.

## Executive Summary

Содержательный read-only pass завершён по Character, Game/session/combat,
NPC, Rule/RuleSpace, Core boundaries, security, concurrency, data integrity и
performance. Production code, tests, configuration, schema и canonical
technical docs не изменялись.

В registry зафиксировано 40 нормализованных записей:

- `6` активных записей `P1`, `22` — `P2`, `1` — `P3`;
- `20` активных подтверждённых findings, `2` resolved findings и
  `2` rejected findings после coordinator recheck;
- остальные записи — `READINESS_GAP`, `REQUIREMENT`,
  `OPEN_DECISION`, `OPEN_REVIEW`, `DECIDED` или `REJECTED`;
- `P0` evidence не найдено.

Главные blockers перед backend Character/Game:

- backend Game отсутствует как ожидаемый `REQUIREMENT /
  NOT_IMPLEMENTED`; frontend mock и real adapter не являются его
  authoritative implementation;
- Character backend имеет storage и узкий RuleSpace slice, но не имеет
  public actions, actor-bound owner, authoritative validation, revision
  binding, membership/approved snapshot и complete migration contract;
- RuleSpace publish не имеет принятого expected/base revision + CAS
  enforcement, а current canonical documents противоречат принятому решению;
- одна и та же domain logic остаётся в Vue dialogs и локальных evaluator-ах;
  source modifier aggregation имеет подтверждённое расхождение;
- Rule editor edit → save сохраняет `mechanicPayload` losslessly;
- public-game visibility semantics соответствуют канону; permission-aware
  entity resolution для chat/chronicle входит в ещё не реализованный backend
  Game boundary;
- есть N+1 catalog reads и per-membership Character requests.

Вертикальный итог:

- Character — `PARTIAL / BACKEND_OPEN`;
- Game/session/combat — `REQUIREMENT / NOT_IMPLEMENTED`;
- NPC — базовый просмотр без боя уже есть, но GM/moderation read-only
  preview отсутствует;
- Rule/RuleSpace — sequential happy path есть, но editor/view/runtime,
  data-driven и concurrency parity не закрыты.

Confidence итогов `MEDIUM`: статический evidence и targeted agent
cross-check собраны, но frontend diagnostics/Vitest и MySQL-dependent
backend tests не дали bounded green результата. Отдельно остаются
two-connection CAS, rollback injection, authoritative HTTP round-trips,
Game backend security/storage и browser/load profiling.

## 1. Объём baseline

В baseline обнаружены:

- изменённый `draft-front_1.2ds/.env`;
- новые документы в `docs/specs/` и `docs/tr/`;
- WIP backend benchmark-файлы;
- новые review tools и artifacts, созданные этой кампанией.

Первичный inventory собрал 3051 текстовый файл в scope:

- production: 171642 строк;
- tests: 58137 строк;
- fixtures/mocks: 26448 строк;
- documentation: 44473 строки;
- configuration: 15475 строк.

Эти числа являются результатом текущего read-only индексатора и требуют
отдельной проверки классификации. Они не являются оценкой только runtime-кода:
моки, документация и конфигурация показаны отдельно.

## 1.1. Scope и источники критериев

В scope входят frontend `draft-front_1.2ds`, backend `www/mifrial`,
относящиеся к ним тесты, mocks/fixtures, public contracts, configuration и
документы, необходимые для проверки текущих контрактов. Полный inventory
охватывает текстовые файлы вне зависимостей и generated-каталогов:
`node_modules`, `vendor`, `dist`, `build`, `coverage`, `.vite` и
`__pycache__` исключены индексатором.

Каноническими источниками считаются `docs/tr/TR.md`,
`docs/tr/architecture.md`, `docs/tr/php-coding-standards.md`,
`draft-front_1.2ds/frontend-rules.md` и `docs/tr/decisions.md`, с приоритетом,
заданным в `TR.md`. Исторические материалы из `docs/review/` используются
только как evidence и история решений; они не заменяют актуальный канон.
Новые материалы из `docs/specs/` учитываются только при прямой ссылке из
активного ТР или текущего проверяемого контракта.

Кампания остаётся read-only по отношению к production code, tests,
configuration, schema и canonical technical docs. Review tooling, `var/review/`
artifacts и отчётные документы являются единственными разрешёнными
изменениями в рамках этой кампании.

## 1.2. Таксономия findings

Severity: `P0` — немедленный критический риск, `P1` — блокирующий или
высокорисковый дефект перед Character/Game, `P2` — существенная проблема,
`P3` — неблокирующее улучшение. Типы разделяют `CODE_ERROR`, `CODE_GAP`,
`ARCHITECTURE_ERROR`, `CONTRACT_ERROR`, `SECURITY_ERROR`, `DATA_ERROR`,
`SECURITY_CONTRACT`, `TEST_GAP`, `PERFORMANCE_RISK`, `DOC_ERROR`, `OPEN_DECISION` и
`OBSERVATION`.

Статусы `OPEN`, `CONFIRMED`, `DECIDED`, `FIXED` и `DEFERRED` не смешиваются.
Автоматические совпадения остаются candidate records до ручной проверки.
Подтверждённая запись должна содержать finding ID, severity, type, status,
владельца, точное evidence (путь, символ/метод, строки или воспроизводимую
команду), ожидаемый контракт, риск, границы проблемы и критерий закрытия.

## 1.3. Toolchain baseline

Зафиксировано в текущем окружении:

- Node.js `v20.20.2`;
- npm `10.8.2`;
- PHP `8.3.33`;
- Composer `2.7.1`.

Frontend-проект объявляет `eslint ^10.8.0`, `typescript ^5.3.0`,
`vitest ^4.1.10` и `vue-tsc ^2.2.12`. Запрос версий через `npx` не дал
bounded результата и был остановлен как зависший; локальные
`node_modules/.bin` в доступном scope не обнаружены.

Backend-проект объявляет `phpunit/phpunit ^10.5`,
`squizlabs/php_codesniffer ^4.0` и `friendsofphp/php-cs-fixer ^3.95`.
В текущем shell `vendor/bin/phpunit` и `vendor/bin/phpcs` отсутствуют,
поэтому backend executable versions в этом запуске не подтверждены.
Ранее зафиксированные результаты `composer quality`, `composer cs-check` и
PHPUnit остаются evidence предыдущего окружения и не подменяют текущую
проверку доступности инструментов.

## 2. Артефакты

Индексаторы находятся в [`tools/review/review.py`](../../tools/review/review.py),
описание запуска — в [`tools/review/README.md`](../../tools/review/README.md).

Созданы:

- `inventory.json`;
- `dependencies.json`;
- `public-surface.json`;
- `rule-references.json`;
- `duplication.json`;
- `ui-inventory.json`;
- `compliance.json`;
- `report.json`;
- `manifest.json`.

Артефакты находятся в `var/review/` и не относятся к production runtime.
Автоматические результаты являются кандидатами и не считаются подтверждёнными
находками без ручной проверки.

Первичный объём кандидатов:

- dependency edges: 2086;
- public-surface records: 768;
- rule-reference candidates: 2079;
- duplication candidates: 2910;
- Vue inventory records: 306;
- compliance candidates: 252;
- объединённые records: 6315.

Большой объём candidate records подтверждает необходимость дедупликации и
кластеризации. Эти числа нельзя трактовать как количество проблем.

## 2.1. Coverage skeleton

`CHECKED_WITH_OPEN_QUESTIONS` означает, что содержательный pass выполнен, но
runtime gates, backend implementation или отдельные contract decisions ещё
ограничивают confidence. Текущий skeleton:

- frontend `Core/Auth`, `Core/User`, `Core/UI`, `Core/Engine`,
  `Core/Logger` — `IN_PROGRESS`;
- frontend `Roleplay/Character`, `Roleplay/Game`, `Roleplay/Rule`,
  `Roleplay/RuleSpace` — `CHECKED_WITH_OPEN_QUESTIONS`;
- frontend `Roleplay/Mechanic`, `Roleplay/Keyword` — `IN_PROGRESS`;
- frontend `Messages/Chat`, `Messages/Notifications`, `Roleplay/Home` —
  `NOT_STARTED`;
- backend `Core/Kernel`, `Core/Auth`, `Core/User`, `Core/SmartTable`,
  `Core/Cache`, `Core/Logger`, `Core/Mail`, `Core/Agent` — `IN_PROGRESS`;
- backend `Roleplay/Character`, `Roleplay/Rule`, `Roleplay/RuleSpace`,
  `Roleplay/Mechanic`, `Roleplay/Keyword`, `Messages/Chat`,
  `Versioning/Space` — `CHECKED_WITH_OPEN_QUESTIONS`;
- backend `Roleplay/Game` и battleground persistence — `CHECKED_WITH_OPEN_QUESTIONS`
  только в части отсутствия реализации; readiness и security не завершены;
- runtime quality gates frontend и MySQL-dependent backend tests —
  `BLOCKED`/`BLOCKED_BY_ENVIRONMENT` до получения bounded результата.

Отдельные review tracks: baseline/inventory, tooling, diagnostics,
rules compliance, architecture/contracts, domain duplication, data-driven
rules, UI, editor/view/runtime parity, backend readiness, security,
concurrency, data integrity, performance и vertical scenarios —
`CHECKED_WITH_OPEN_QUESTIONS`; candidate deduplication, evidence registry,
coverage synthesis и final decision/queue pass завершаются после этого
checkpoint. Этот skeleton является картой progress, а не утверждением
production readiness.

### Frontend inventory checkpoint

В inventory вошли 1977 текстовых файлов frontend scope, распределённых по 16
распознанным module buckets; отдельные root-level routes, конфигурация и
служебные файлы остаются с `module: null`/unresolved и не теряются. Наиболее
крупные области: `Roleplay/Game` — 565 файлов, `Roleplay/Rule` — 463,
`Roleplay/Character` — 319, `Core/UI` — 140 и `Messages/Chat` — 106.

Для каждого файла зафиксированы category, module, layer, LOC, imports,
exports и heuristic `relatedTests`. Public contracts вынесены в
`public-surface.json`, Vue views/routes/props/emits/read-model imports и
UI-clusters — в `ui-inventory.json`, а mock/fixture records отделены от
production. Связь с real API пока является candidate inventory signal и
требует отдельного contract-parity pass; наличие mock не считается evidence
backend implementation.

### Backend inventory checkpoint

Backend inventory содержит 821 текстовый файл в 16 распознанных module
buckets. Крупнейшие области: `Core/SmartTable` — 212 файлов,
`Core/Kernel` — 107, `Core/Auth` — 56, `Messages/Chat` — 55 и
`Roleplay/RuleSpace` — 55. По каждому модулю зафиксированы Action, Container,
Dto, Interface, Repository, Schema, Service, Table, Setup и test layers,
если они присутствуют.

`public-surface.json` включает backend Actions, Interfaces, DTOs, Containers и
frontend entrypoints; `inventory.json` позволяет отдельно сопоставлять
production/test/fixture files. Backend `Roleplay/Game` в inventory отсутствует
как implementation module и остаётся `REQUIREMENT / NOT_IMPLEMENTED`, а не
скрыто считается готовым по frontend mock/API.

## 3. Состояние инструментальных проверок

### Frontend

- `npm run lint:check` — `TIMED_OUT`/exit 124 после выдачи 6 ошибок
  `padding-line-between-statements`; auto-fix не запускался.
- `npm exec --no -- vue-tsc --noEmit` — `TIMED_OUT`/exit 124, выдано
  множество TS errors, включая nullable values, invalid action payloads и
  Formula-related union access.
- `npm run test` — `TIMED_OUT`/exit 124; Vitest `4.1.10` столкнулся с
  `vitest-pool`/`kill EACCES` при завершении fork workers. Зелёный результат
  не подтверждён.

Запуски выполнялись из `draft-front_1.2ds`; первоначальные вызовы из root с
`Missing script` не используются как evidence состояния frontend.

### Backend

- `composer quality` — `PASSED`, PHPCS `590/590`.
- `composer cs-check` — exit 3: PHP CS Fixer dry-run нашёл `0/800`
  автоисправляемых файлов, затем PHPCS сообщил 6 ошибок и 5 предупреждений:
  PHPDoc capitalization, одна whitespace-ошибка, unused parameter и
  превышение длины строк.
- `vendor/bin/phpunit` — `PASSED`: 451 тест, 690 assertions, 230 skipped.
  Два диагностических CLI/kernel error log events были обработаны тестами и
  не привели к падению suite.

В окружении PHP 8.3 при проектном требовании `^8.2`; PHP CS Fixer сообщил
предупреждение о запуске на версии выше минимальной. Предыдущие результаты
другого запуска PHPUnit не смешиваются с этим текущим bounded baseline.

## 3.1. Test-quality checkpoint

Статический inventory frontend содержит 231 test file, 1811 `it/test`
declarations и 4816 `expect` calls. Поиск `skip/only` patterns в frontend
tests совпадений не дал; это не заменяет runtime execution. 28 frontend test
files относятся к Mock-контурам, поэтому их assertions отдельно требуют
проверки parity с real transport.

Backend inventory содержит 118 test files, 451 test methods и 2133 assertion
calls; 39 файлов имеют `MysqlTest` в имени, а 56 skip/incomplete markers
найдены статически. Текущий PHPUnit run подтвердил 451 test, но 230 были
skipped, поэтому green process exit не означает полную проверку MySQL-backed
инвариантов.

Проверка независимости fixtures, mutation resistance и соответствия assertions
критичным domain invariants требует содержательного прохода по тестовым
наборам; на этом этапе зафиксированы только структурные сигналы и runtime
ограничения.

Предварительная проверка критичных инвариантов уже показывает gaps:

- `formulaEvaluation.test.ts` покрывает общий evaluator, но отдельный parity
  matrix для `EditorCheckBonusesService` и `AbilityCheckAdvantagesService`
  отсутствует; `editorCheckBonuses.test.ts` содержит только два сценария.
- `CharacterMysqlTest.php` проверяет `expectedVersion` в последовательном
  сценарии, но не содержит двух независимых connections или lost-update
  scenario для DB CAS.
- unit-тесты `CommitEntry` проверяют DTO validation, но не transaction
  rollback/idempotency границы.

## 3.2. Frontend rules checkpoint

Проверка сопоставлена с `frontend-rules.md`, а формальные нарушения отделены
от архитектурных выводов. Текущий lint baseline содержит 6 formatting-rule
errors. Статический compliance scanner собрал candidate records по `any`,
JSON cloning, relative imports, runtime exception/SQL patterns; они требуют
фильтрации по production/mock/test scope и не считаются findings
автоматически.

Ручная проверка критичного scope подтвердила:

- `.vue` launch dialogs содержат domain calculations, requirements,
  mutations и resolution orchestration (`REV-FE-001`);
- async loading в этих dialogs использует silent fallbacks без полного F17
  loading/error/retry state (`REV-FE-002`);
- Formula evaluation частично дублируется и теряет варианты, поддержанные
  общим evaluator (`REV-FE-004`);
- combat orchestration повторяется между dialogs (`REV-FE-003`);
- `SpaceContextLayout`/related stores и NPC editor требуют отдельных
  generation/F17 проверок, уже отражённых как `REV-CON-002` и `REV-UI-003`.

Таким образом, frontend rules pass уже имеет подтверждённые findings в
критичном scope, но весь низкорисковый UI ещё не получил индивидуального
семантического review; его автоматическое покрытие зафиксировано inventory и
compliance artifacts.

## 3.3. Backend rules checkpoint

Из 816 PHP-файлов в scoped backend inventory не найдено файлов без
`declare(strict_types=1);`. `composer quality` проходит полностью, но
`composer cs-check` остаётся красным из-за шести ошибок и пяти предупреждений
PHPCS; это отдельные formal findings, не автоматически архитектурные дефекты.

Содержательный pass подтвердил:

- `RuleSpaceCatalog` содержит snapshot invariants внутри immutable value-like
  DTO; прежняя Dto → Service зависимость (`REV-PHP-001`) устранена;
- locator usage в Application/AgentTickCli находится на разрешённой
  composition/CLI boundary (`REV-PHP-003` rejected);
- локальные complexity suppressions имеют принятое локальное обоснование
  (`REV-PHP-004` rejected);
- SQL/SmartTable и Record/New/Patch boundaries требуют отдельной проверки
  по модульным слоям, даже при зелёном `composer quality`.

PHPDoc, naming, exception и layer checks закрыты формальным scanner +
targeted manual evidence в критичных модулях; оставшиеся warnings/errors и
неподтверждённые runtime boundaries переходят в findings/contract passes.

## 3.4. Dependency graph checkpoint

После исключения `var/review/` artifacts dependency graph содержит 3608
межмодульных edges и 3 candidate strongly-connected components:

- Core/Agent, Auth, Cache, Kernel, Logger, Mail, SmartTable, User и
  Messages/Chat;
- Roleplay/Character и Roleplay/Game;
- Roleplay/Rule и Roleplay/RuleSpace.

Также найдено 296 candidate suspicious frontend internal edges, включая один
`Dto → Core/Engine/Service` edge из `ChatSyncConfig.ts`, соответствующий
классу frontend boundary violation.
Граф строится эвристически и включает test/mock imports, поэтому SCC и
internal-edge candidates требуют сверки с разрешёнными plugin/public-facade
исключениями; автоматически findings из них не создаются.

## 3.5. Public-surface checkpoint

`public-surface.json` содержит 1081 records: 29 entrypoints,
790 contract records и 262 candidate internal-API bypasses. Проверка по канону
подтверждает, что public surface должна включать frontend `init/routes`,
`Dto/Interface/Enum/Constant/Value/Mock`, динамические Vue imports и backend
Actions/ports/DTOs/Containers.

Число bypass candidates не равно числу нарушений: в него попадают разрешённые
Core/Engine и Core/UI imports, plugin registration paths, tests и mock-контуры.
Например, `ChatSyncConfig → Core/Engine/Service/Engine` требует ручной
классификации как допустимый Core exception, а не автоматического finding.
Confirmed surface findings должны появиться только после сверки конкретного
ребра с `architecture.md` и фактическим public facade.

## 3.6. Contract-parity checkpoint

Статическое сопоставление строковых action codes нашло 142 уникальных
frontend `runAction` calls против 50 backend route keys. 99 frontend codes не
имеют backend route в текущем дереве, а 7 backend routes не имеют найденного
frontend consumer. Это не один finding: множество несовпадений ожидаемо для
mock/REQUIREMENT/deferred контуров, но граница должна быть явно
классифицирована.

Крупные группы unmatched frontend calls:

- `game.*` — backend Game module отсутствует и остаётся requirement;
- `character.*` — frontend real/mock API есть, authoritative Character HTTP
  actions отсутствуют;
- `rule.*` — текущий PHP Rule module имеет ports, но `routes: []`;
- notifications и user macro/template контуры требуют отдельного статуса
  mock/backend availability.

`ruleSpace.*` имеет backend routes, включая `commitDraft`, но текущий контракт
не передаёт expected/base revision, что подтверждено `REV-CON-001`. Frontend
DTO, mock API или наличие `runAction` не считаются доказательством backend
реализации. Полный parity по response JSON, error codes, nullability,
identity ids и revision semantics остаётся следующим содержательным pass.

## 3.7. Storage-boundary checkpoint

Action binding and JSON envelope имеют явные границы: `ActionParameterBinder`
принимает плоский object payload, гидрирует `IActionInput`, а
`ActionResponse` мапится в отдельный JSON shape. SmartTable остаётся
единственным шлюзом таблиц, а transaction wrapper и rollback-oriented tests
найдены в Core/SmartTable.

RuleSpace commit выполняется внутри SmartTable transaction, а Versioning
публикует immutable revision/items через repository boundary. `Record/New/Patch`
контуры в критичных модулях разделены; предыдущий `VersionRecord field bag`
классифицирован как intentional generic contract, не как прикладной
boundary defect.

Оставшиеся storage gaps:

- Character `writeGuarded()` делает read/compare/write внутри transaction, но
  не conditional DB update и не подтверждён двумя connection;
- RuleSpace commit transaction не содержит expected/base revision CAS;
- MySQL-backed rollback, lost-update и migration scenarios не закрыты текущим
  runtime baseline из-за skipped/environment-limited tests.

## 3.8. Domain ownership inventory

Начальная карта критичных domain concepts и фактических владельцев:

- Formula evaluation — `Roleplay/Character/Service/FormulaEvaluationService.ts`;
  локальные consumers `AbilityCheckAdvantagesService` и
  `EditorCheckBonusesService` содержат частичные копии.
- Source delta aggregation — `Rule/Service/AggregateSourceDeltasService.ts`
  и отдельная реализация в `CharacterOverviewService.ts`; exact
  `null-source` policy пока не закрыта.
- Character revision/validation — frontend `CharacterVersionIntegrity`/
  `CharacterOverviewService`, backend `CharacterInputNormalizer`,
  `Characters` и `CharacterRepository`; authoritative HTTP owner отсутствует.
- RuleSpace revision/publish — frontend `spaceRevision`/`RuleSpaceApi`,
  backend `RuleSpaces`, `RuleSpaceWorldWriter`, `RevisionPublisher`; CAS
  contract отсутствует в текущем publish input.
- Combat resolution — frontend `Roleplay/Game/Service` family и launch
  dialogs; backend Game owner/storage отсутствует.
- Permission/access — frontend `CharacterAccessService`/sheet access and
  backend User/Auth access ports; object-level cross-domain parity требует
  отдельного pass.
- Transaction/storage — `Core/SmartTable` gateway and module repositories;
  higher-level Auth/Character/RuleSpace transaction boundaries проверяются
  отдельно.

Этот список фиксирует ownership candidates, а не утверждает, что каждая
формула уже имеет единственного согласованного владельца.

## 3.9. Modifier aggregation map

Проверенная цепочка модификаторов:

1. Ability grants и item effects создают entries с `source_code` и `delta`.
2. `AggregateSourceDeltasService` группирует по source, отбрасывает нули,
   оставляет strongest positive и strongest negative, затем суммирует их.
3. `AbilityCheckAdvantagesService` использует этот агрегатор для ability
   grants.
4. `EditorCheckBonusesService` повторно агрегирует уже собранные modifiers,
   после чего строит editor read model.
5. `CharacterOverviewService` имеет собственную реализацию группировки.
6. Combat/SpellCast consumers используют центральный `netSourceDelta` только
   в отдельных путях; часть action-effect обработки имеет собственные
   `sourceNet`/source filtering helpers.

Подтверждённые расхождения:

- `AbilityCheckAdvantagesService` уважает `grant.source_code` с fallback на
  rule code, тогда как `EditorCheckBonusesService.characteristicModifiers...`
  записывает source как `rule.code` и теряет explicit source;
- центральный агрегатор игнорирует zero entries и сохраняет первый equal
  candidate, а `CharacterOverviewService` допускает zero entry, преобразует
  `null` в `'прочее'` и имеет order-dependent результат для penalty-only
  группы;
- часть combat action effects группирует source отдельно от общего
  агрегатора; equivalence этой политики тестами не доказана;
- формульное получение `delta` дублируется в ability/runtime и editor
  consumers, а неподдержанный variant silently превращается в `0`
  (`REV-FE-004`).

Канонический инвариант strongest-per-source из ТР совпадает с центральным
агрегатором, но не доказан для всех consumers. До исправления нельзя считать
editor, overview, combat и будущий backend Character семантически
эквивалентными.

## 3.10. Combat-logic map

Фактическая цепочка combat:

- check hierarchy и `attached_rule_codes` разрешаются
  `CheckResolutionService`; ancestor matching используется для grants и
  check characteristic;
- dice pool и итог проверки строятся `CheckRollService`/`rollEngine`, затем
  `CheckSuccessRatingService` добавляет outcome;
- action effects и state effects добавляют check modifiers, cost/resource
  changes и prepared defense reactions; часть обработки выполняется
  `ActionEffectService`, часть — отдельными launch services;
- attack damage проходит через `AttackDamageService`: damage type hooks,
  defense/resistance layers, durability/SR filtering, same-source collapse,
  penetration, accumulated damage и exhaustion;
- injury строится `InjuryCheckService` → `InjuryRollService`, а результат
  записывается как combat state через `IGameApi`;
- periodic damage имеет отдельную state/DOT ветку
  (`StateRuntimeEffectsService`, `DotTickMathService`), а damage-type
  behavior подключается через hook services.

Проверенные invariants:

- атака учитывает resistance и defense отдельно, применяет penetration только
  к defense и не применяет defense при `defenseIgnored`;
- same-source resistance collapse использует центральный strongest-per-source
  aggregator;
- injury запускается только при фактическом HP damage/wound, а exhaustion
  берётся с overlay после записи;
- check modifiers наследуют check ancestors, но explicit source policy и
  агрегирование остаются неодинаковыми между consumers.

Риски и пробелы:

- `CheckResolutionService`, `AttackDamageService`, `InjuryCheckService` и
  связанные services напрямую знают коды `roll`, `action-points`,
  `accumulated-damage`, `endurance` и state/injury constants. Это
  `REV-FE-006`/data-driven risk: механика частично parametrized правилами,
  частично привязана к конкретной vocabulary;
- damage-type hooks data-driven только до зарегистрированного hook/mechanic
  boundary; новый hook без регистрации в коде не исполняется;
- DOT, attack, injury и state modifier paths имеют раздельные calculation
  entry points, а cross-path parity tests для одинакового source/check
  policy не найдены;
- backend authoritative combat/Game implementation отсутствует, поэтому
  frontend combat services и mocks не доказывают серверную корректность.

## 3.11. Character/Game lifecycle map

### Character

Существующий backend Character scope состоит из storage/kernel pieces:
`Characters`, `CharacterRepository`, `CharacterInputNormalizer` и
`CharacterVersionIntegrity`. Они обеспечивают сохранение части версии и
проверку отдельных формальных полей, но не образуют завершённый
authoritative HTTP/application boundary.

Не подтверждены или отсутствуют:

- actor-bound create/update/delete actions;
- проверка `rulesRevision` против конкретного `space_id` и доступного
  published snapshot;
- approved/public snapshot и membership lifecycle;
- overlay/state persistence и server-side derived read model;
- migration/version compatibility;
- frontend/backend DTO round-trip и object-level authorization на HTTP edge.

### Game

Frontend Game имеет API ports, mocks, combat overlays, action execution,
check/attack/injury services и UI read models. Backend `Roleplay/Game` action,
repository, membership/session, combat persistence, moderation, economy и
battleground boundaries в inventory не обнаружены. Поэтому frontend Game
поток нельзя считать серверным контрактом; он является prepared consumer и
частично executable mock/runtime.

### Cross-cutting lifecycle risks

- Character owner и actor должны выводиться из authenticated context, а не
  приниматься доверенным аргументом application service;
- version guard сейчас PHP read/compare/write, не database CAS;
- RuleSpace revision должен быть зафиксирован в Character и проверяться как
  принадлежащий выбранному space;
- Game snapshot, overlay и action result должны иметь единый authoritative
  lifecycle; текущая frontend-модель допускает отдельные mock/API semantics;
- membership, approved version, NPC visibility и moderation должны быть
  отдельными persisted concepts, а не boolean-предположениями в UI.

Итоговая классификация перед backend Character/Game:
Character — `PARTIAL / BACKEND_OPEN`; Game — `REQUIREMENT /
NOT_IMPLEMENTED`. Это readiness evidence, а не предлагаемый сейчас
implementation scope.

## 3.12. Domain-owner verification

Фактические boundaries проверены против imports, service composition и
consumer paths:

- `FormulaEvaluationService` и `AggregateSourceDeltasService` выглядят как
  intended shared owners, но critical consumers обходят их локальными
  helpers;
- `CharacterOverviewService` одновременно собирает read model и повторяет
  domain aggregation, поэтому является ошибочным владельцем части modifier
  semantics;
- launch dialogs являются UI orchestration owners, но сейчас содержат
  eligibility, cost, modifier и resolution logic, которая должна принадлежать
  Game/Rule services;
- `ActionEffectService` владеет большой частью effect application, однако
  отдельные launch/check/attack services дублируют подготовку контекста;
- RuleSpace revision ownership находится на backend `RuleSpaces`/
  `RevisionPublisher`, но expected-revision policy не проходит от публичного
  input до write boundary;
- Character persistence ownership разделён между normalizer, service и
  repository без завершённого HTTP actor boundary;
- Game authoritative ownership отсутствует backend-side; frontend API ports
  и mocks не являются владельцами состояния.

Таким образом, часть найденных проблем — не отсутствие отдельных helper-ов,
а отсутствие единой authoritative owner цепочки. Это повышает риск того, что
добавление Character/Game создаст ещё одну интерпретацию уже существующей
игровой логики.

## 3.13. Domain-invariant checkpoint

Положительно подтверждены тестами:

- strongest positive/negative per source и суммирование разных sources;
- check ancestor matching, characteristic inheritance и attached mechanics;
- damage formula, SR durability filtering, penetration, same-source defense
  collapse, endurance/exhaustion, cutting wounds и blunt knockout;
- injury package splitting по damage layers и collapse remainder;
- DOT turn-period math и decay transitions.

Недостаточно проверены или имеют открытый контракт:

- `null` source: должен ли он быть общим источником, отдельным entry на
  каждый effect или недопустимым состоянием;
- equal positive/negative candidates и стабильность winner selection;
- mixed positive/negative layers одного source после penetration;
- cycle/invalid-parent behavior не только на graceful return, но и в
  validation/API contract;
- отсутствие rule, state, damage type или hook registration в production
  runtime;
- dimensional negative/base-zero boundaries в каждом consumer-е;
- idempotent повторное применение action/injury/DOT;
- cross-consumer parity: overview/editor/combat/backend должны давать один
  результат на одинаковом snapshot.

Следовательно, базовые unit tests подтверждают локальные формулы, но не
подтверждают единство доменного инварианта через все точки входа.

## 3.14. Rule-reference classification

Literal/reference scan не трактуется как автоматический defect. Привязки
разделены так:

### Допустимые generic/runtime references

- `rule.code`, `*_code` и `source_code`, пришедшие из snapshot/catalog;
- `spec.type`, effect/grant/hook discriminators и enum-like mechanic values,
  являющиеся частью schema contract;
- централизованные constants для устойчивой vocabulary, если они только
  выбирают canonical rule и не подменяют его runtime spec.

### Допустимые fixtures/migrations

- mock rule catalogs, seed/import data и `MockRuleCatalogMigrationService`;
- тестовые literal codes, когда тест явно описывает выбранный fixture;
- compatibility mapping, если он ограничен migration/import boundary и не
  попадает в production runtime.

### Подозрительные production references

- `CheckResolutionService` имеет отдельный literal `roll` fallback вместо
  связи через общий Rule/Mechanic contract;
- combat services используют literal concepts вроде `endurance` рядом с
  уже существующими constants для других identity rules;
- `KnowledgeDefenseService` и часть combat helpers знают конкретные
  ability/state identities, которые должны быть либо canonical mechanic
  contract, либо приходить из rules/spec;
- `MockRuleCatalogMigrationService` содержит большое число content-specific
  section mappings — это допустимо только как migration policy и не должно
  использоваться для runtime behavior;
- отдельные UI/editor helpers ветвятся по конкретному rule code вместо
  `RuleType/spec` capability.

### Классификационный вывод

Основной риск не в самом наличии `code`, а в production branching, которое
меняет механику при замене или удалении rule из snapshot. Подтверждённый
combat subset уже отражён в `REV-FE-006`; остальные literal candidates
остаются candidates до проверки call path и отсутствия data/spec alternative.

## 3.15. Rule-independence checkpoint

Работает независимо от конкретного наполнения snapshot:

- большая часть Character calculations получает `rules[]` и разрешает rule
  по semantic code;
- check hierarchy, attached mechanics, item profiles, damage types и state
  effects в основном следуют переданным specs;
- revision files идентифицируют правила по `code`, а не по storage `id`.

Не является полностью content-independent:

- отсутствие canonical `roll`, `simple`, `endurance`, action-points или
  accumulated-damage rules вызывает fallback/нулевое значение либо меняет
  ветку, вместо явного capability error;
- fixed assumptions о `melee-combat`, `ranged-combat`, отдельных
  characteristic/state identities и outcome labels находятся в production
  services;
- неизвестный Formula variant в локальных evaluator-ах даёт `0`, поэтому
  новая реализация правила может выглядеть как валидный нулевой модификатор;
- разные revisions с одинаковым code, но разным spec/attached mechanic могут
  пройти DTO-level загрузку, но получить различный или неполный результат
  в consumers, которые используют лишь часть spec;
- editor и view не обязаны reject/display capability, которую runtime
  понимает только через новый mechanic/hook registration.

Итог: rule catalog действительно является главным источником контента, но
runtime contract не везде data-driven. Для backend Character/Game требуется
явный distinction между missing rule, unsupported spec и valid zero result;
иначе смена состава/версии правил может быть silent semantic break.

## 3.16. UI-view inventory

Текущие presentation clusters:

- Character list/create/detail/edit/migrate:
  `CharactersPage`, `CharactersNewPage`, `CharacterDetailPage`,
  `CharacterEditPage`, `CharacterMigratePage`;
- shared sheet/detail read model:
  `SheetCard`, `OverviewTab`, `UniqueRulesTab`, detail tiles and
  `CharacterOverviewService`;
- Game list/create/detail/edit:
  `GamesPage`, `GameNewPage`, `GameDetailPage`, `GameEditPage`;
- Game character membership and moderation:
  `CharactersTab`, `ModerateTab`, `CharacterSheetEditor`;
- NPC management and read path:
  `NpcsTab`, `NpcCard`, `NpcEditPage`, `NpcMigrationDialog`;
- combat-specific read model:
  `CombatCardPanel`, `CombatCardCharacteristicTile`, combat state/process
  tiles and launch dialogs;
- rule management:
  `RuleEditPage`, `RuleDetailPage`, per-spec editors, cards and
  `RuleReferenceService`.

`SheetCard` уже используется в Character detail, Game characters tab,
Chronicle и NPC card, поэтому тезис о полном отсутствии shared sheet не
подтверждается текущим деревом. При этом это shared renderer с
`visibleSections`, а не единый универсальный user-facing page.

Наблюдаемая особенность NPC flow: `NpcsTab` открывает `NpcCard`; игроку
показывается `SheetCard`, а ведущему показываются поля управления и ссылка
«Редактировать лист». Это подтверждает отдельный read-only gap именно для
GM/NPC management scenario, но не доказывает, что read-only NPC view
отсутствует для всех ролей.

### UI gaps and inconveniences

Зафиксированы как отдельные non-blocking UX/UI tasks:

- GM должен иметь preview того же видимого NPC sheet без перехода в editor;
- moderation queue должна позволять inspect proposed NPC перед решением;
- access/visibility/capability policy следует показывать единообразно во всех
  sheet wrappers;
- error/loading/retry состояния для NPC и async save должны быть частью
  общего host contract, а не различаться между route и modal;
- различие `SheetCard` и `CombatCardPanel` нужно сохранить только при
  наличии подтверждённых action/overlay требований; иначе поддержка двух
  read models будет неоправданной.

Текущий evidence не позволяет объявить все character-card варианты
дублирующимися: существенная часть различий объясняется контекстом доступа,
membership, moderation и combat state.

## 3.17. RuleType inventory checkpoint

`RuleType.ts` содержит 21 тип:
`simple`, `race`, `species`, `characteristic`, `resource`, `points`,
`ability`, `item`, `damage_type`, `source`, `state`, `poison`, `sense`,
`age`, `language`, `script`, `ethnicity`, `weapon_family`, `item_modifier`,
`item_modifier_type`, `check`, `magic_path`.

Каноническая readiness matrix в `docs/tr/rule-system.md` отражает почти
тот же набор и фиксирует:

- domain DTO/service — `IMPLEMENTED` для всех перечисленных типов;
- frontend UI — `IMPLEMENTED` для базовых типов и `PARTIAL` для большинства
  complex/spec types;
- backend — `OPEN` для всех;
- real content — `OPEN`, а для source/poison/sense/age/language и связанных
  deferred areas — `DEFERRED`; `magic_path` — `MOCK_ONLY`.

Обнаружен documentation parity gap: `script` и `ethnicity` присутствуют в
текущем `RuleType.ts` и имеют editor components, но отсутствуют отдельными
строками в readiness table. Это не доказывает runtime defect, однако делает
невозможным считать matrix исчерпывающей без документального решения.

Rule editor surface включает base editor и специализированные editors для
ability, item subtypes, damage type, state/poison, check, process, magic
path, race/species/ethnicity/age/language/script, modifiers и formula. Наличие
компонента не подтверждает field parity — это проверяется следующими trace
проходами.

## 3.18. Rule-data trace checkpoint

Проверенная трасса rule data:

`Rule API/catalog` → `ruleToForm`/`RuleFormState` → specialized editor →
`RuleDraftService`/draft store → RuleSpace publish payload →
`RuleSpaceCommitDraftMapper` → generic PHP `RuleVersionBody` →
`RuleSpaceViewAssembler` → frontend `Rule`/runtime consumers.

На этой трассе:

- базовые fields (`code`, `type`, `name`, `description`, keywords,
  mechanicId/payload, content metadata и catalog placement) явно проходят
  через frontend form, mapper и backend snapshot view;
- `spec` и `mechanicPayload` сохраняются как generic JSON arrays и не
  теряются на PHP RuleSpace boundary;
- `RuleDiffService` исключает локальные ids/дату из diff и сравнивает
  semantic payload;
- `AbilitySpecService.prune()` и `ItemSpecService.prune()` намеренно
  удаляют type/subtype-inactive fields на emit boundary;
- backend `RuleVersionBody` проверяет форму общих полей, но не выполняет
  type-specific validation того же объёма, что frontend
  `RuleValidationService`.

Главный trace risk: поле может пройти generic storage и вернуться в view, но
быть удалено editor prune, проигнорировано локальным runtime consumer-ом или
принято backend без семантической проверки. Generic round-trip поэтому не
равен editor/runtime parity.

## 3.19. Rule-editor capability checkpoint

Подтверждены следующие editor gaps:

- `FormulaEvaluationService` и `Formula` DTO поддерживают
  `characteristic_size` и `characteristic_size_gap`, но `FORMULA_TYPE_LABELS`
  не содержит их;
- `FormulaTypeItemsService` показывает `parameter` и
  `parameter_floor_div`, когда они переданы в modes/current, но
  `FormulaInput.updateType()` не создаёт эти варианты, для них нет editor
  template/update handlers, и fallback фактически создаёт dimensional
  formula;
- `FormulaInput` умеет отображать/создавать только часть formula union;
  сохранённая unsupported formula может пережить round-trip как current
  value, но не может быть полноценно создана или изменена;
- runtime reads `mechanicPayload` и backend generic snapshot его сохраняет,
  однако Rule editor/page/base editor передают `mechanicId`, но не имеют
  surface для просмотра/редактирования `mechanicPayload`;
- `AbilitySpecService.prune()` и `ItemSpecService.prune()` зависят от
  manifests; поле, добавленное в DTO/runtime без обновления manifest, будет
  потеряно на emit.

Это прямое нарушение требуемого editor/runtime parity для capability
surfaces, не просто stylistic difference. Уже зарегистрированный
`REV-FE-004` покрывает Formula runtime/editor gap; mechanic payload и
prune-manifest gaps требуют отдельных finding records после дедупликации.

## 3.20. Rule-view capability checkpoint

`RuleSpecView` маршрутизирует специализированные cards для всех complex
RuleType, включая `script` и `ethnicity`; `simple` не имеет отдельного spec
блока, что допустимо только если у него действительно нет значимых payload
fields.

Подтверждённые view gaps:

- `RuleDetailPage` показывает mechanic name/version/description, но не
  `mechanicPayload`, хотя payload сохраняется и runtime его читает;
- `CharacteristicCard` выводит `spec.formula` напрямую вместо
  `RuleViewLabelService.formula()`. Для object-form Formula это не даёт
  человекочитаемого представления и может скрывать существенную семантику;
- cards используют неодинаковые label paths: часть Formula проходит через
  `RuleViewLabelService`, часть отображается локально;
- readiness/status metadata отображается на detail page, но не является
  частью каждой type card или списка, поэтому partial/mock/deferred
  capability может быть неочевидной пользователю.

Таким образом, наличие card для каждого типа не равно полной visibility
сохранённого rule contract.

## 3.21. UI responsibility comparison

Разделение ответственности сейчас выглядит так:

- `SheetCard` — общий read-only renderer character version по секциям;
- `CharacterDetailPage` — route-level loading, access, editing affordances и
  extensions;
- `CharactersTab` — membership list, submit/leave actions и modal wrapper
  вокруг `SheetCard`;
- `NpcCard` — modal wrapper, visibility, NPC CRUD и conditional sheet
  rendering;
- `CombatCardPanel` — action-ready combat read model, overlay/process state и
  combat affordances; его отличие от `SheetCard` семантически оправдано;
- `ModerateTab`/moderation queue — moderation actions, но не inspection
  surface.

Кандидаты на упрощение поддержки:

- унифицировать route/modal wrappers через общий character-sheet read model и
  capability-based actions, не смешивая его с combat card;
- выделить NPC read-only sheet в отдельный user-facing entry point, доступный
  GM и moderation flow;
- добавить inspection affordance в moderation queue до approve/reject;
- не дублировать access/visibility decisions в каждом wrapper-е;
- оставить CombatCard отдельным только если его action/overlay semantics
  остаются отличными от character sheet.

Это UI architecture opportunities, а не автоматически подтверждённое
«лишнее число карточек»: для каждого кластера нужна проверка пользовательской
задачи и read model contract.

### NPC read-only result

В текущем дереве NPC можно открыть из `NpcsTab` без запуска боя:
`openCard()` выбирает NPC, после чего `NpcCard` показывает `SheetCard` при
обычном пользовательском доступе. Значит, общий read-only путь для игрока
уже есть.

Для ведущего тот же flow не является полноценным read-only просмотром:
`NpcCard` переключается в management form и предлагает редактирование,
а moderation queue содержит только approve/reject. Поэтому подтверждён
не абсолютное отсутствие NPC view, а role-specific gap:
GM/moderator не получает полноценный preview листа до edit/approve.

## 3.22. Editor/view/mock/runtime parity checkpoint

Сводный результат parity pass:

- mock catalogs содержат Formula, damage hooks, state effects и spell/action
  shapes, которые generic DTO/runtime способны прочитать;
- editor покрывает большую часть RuleType и common spec paths, но не все
  runtime Formula variants и не mechanic payload;
- Rule cards покрывают почти все RuleType и используют rule references для
  labels, но имеют локальные omissions и прямой object rendering;
- runtime имеет более широкую capability surface, чем editor, и в отдельных
  consumers silently returns zero для неизвестной Formula;
- backend stores generic JSON, но не гарантирует type-specific validation и
  не является доказательством того, что capability можно создать/view через
  UI.

Ложный claim, который нужно исключить из последующих документов:
`Domain DTO/service = IMPLEMENTED` не означает «rule полностью поддержан
editor/view/runtime/backend». Для каждого RuleType должны быть раздельно
показаны field matrix, supported variants, editor controls, view labels,
runtime consumers, mock evidence и backend status.

До закрытия `REV-RULE-001/002/003` и `REV-FE-004` editor/view/runtime
round-trip остаётся `PARTIAL`, а не `IMPLEMENTED`.

## 3.23. Magic/spell trace checkpoint

Что реально присутствует:

- `AbilityType = spell`, structured `SpellSpec`, duration, components,
  damage, hit resolution и spell upgrades;
- `magic_path` и `magic_study` character/editor services, включая
  path inclusion и cost limits;
- frontend spell cast slice: difficulty, upgrades, cast roll, touch delivery,
  typed damage и damage-type hooks;
- spell editor/card/overview и mock import fixtures.

Что остаётся `PARTIAL/DEFERRED/OPEN`:

- полный magic source/catalog и all cast mechanics;
- authoritative backend Rule/Game/Character integration;
- server-side validation и persistence of cast/session effects;
- полный parity для source selection, spell difficulty, failure,
  sustaining/lingering/refreshable effects и moderation;
- formal capability status surfaced to editor/view instead of inferred from
  mock availability.

Отдельный data-driven risk: `SpellCastEfficiencyService` и
`SpellCastExecutionService` знают concrete ability/action codes
(`basic-element-properties`, `magic-structure-interaction`,
`dynamic-energy-saturation`, `action-points`) вместо получения этих
relationships from spell/mechanic specs. Это допустимо только как временный
canonical slice contract; для различающихся rule revisions это silent
semantic coupling.

Также `SpellCastExecutionService` имеет разные entry points для cast до touch
и complete-after-hit. Их общий damage/cost/injury contract не подтверждён
единой cross-path parity matrix.

## 3.24. Backend-foundation checkpoint

Подтверждены как рабочие foundation boundaries:

- `ApplicationFactory` собирает config, ModuleManager, locator, dispatcher,
  request context, logger и response emitter;
- ModuleManager/containers разделяют eager core и lazy module loading;
- Dispatcher разрешает только зарегистрированные action routes и публичные
  `IActionHandler`, затем применяет typed parameter binder;
- `Application` отдельно обрабатывает HTTP route/CSRF, actor binding,
  ActionResponse и unhandled Throwable;
- `RequestContext`/`AuthSessionBinder` являются источником request actor, а
  object access в User/Auth использует actor permissions;
- response envelope и HTTP status mapping имеют отдельный kernel boundary.

Открытые вопросы foundation:

- auth login/register/password-reset routes имеют явный `csrf: false`; это
  оставлено `OPEN_DECISION` до решения о допустимом cookie/CSRF threat model;
- lazy module actor binders должны быть проверены отдельно при добавлении
  модулей с `request_bind` — текущая конфигурация использует binder только в
  eager Auth;
- Dispatcher превращает только `ActionException` в domain error, остальные
  exceptions уходят в generic internal response/log; action error details
  не полностью доходят до frontend (`REV-CON-003`);
- service locator остаётся разрешённой composition/runtime boundary;
  запрет касается его использования в обычных domain services
  (`REV-PHP-003` rejected);
- error/logging tests не заменяют production checks на secret redaction,
  correlation/idempotency и actor propagation в будущих Character/Game
  routes.

## 3.25. SmartTable-readiness checkpoint

Подтверждены как usable prerequisites:

- table definitions дают schema/records boundary с колонками, JSON/MFV,
  indexes и references;
- `TableSchema` отдельно реализует create/update/force-update/delete и
  проверяет FK targets, index names и schema mismatch;
- `SmartTableGateway` предоставляет transaction boundary на одном DB
  connection;
- row/list/aggregate operations имеют optional cache с invalidation notes;
- cache mutations settle only after transaction commit and discard pending
  invalidations after rollback;
- repositories уже используют SmartTable вместо прямого SQL в проверенных
  Character/RuleSpace/Auth paths.

Ограничения перед Character/Game:

- transaction helper намеренно не создаёт nested savepoints: inner call
  присоединяется к outer transaction; это канон, но требует дисциплины
  rollback tests;
- gateway не предоставляет conditional update/CAS primitive, поэтому
  optimistic version checks, реализованные read/compare/write выше, не дают
  DB-level lost-update guarantee;
- cache correctness зависит от field tags и dependent-table discovery;
  cross-table invalidation не подтверждён для будущих Game overlays/session
  queries;
- schema DDL/migration behavior и MySQL-backed cache/rollback tests в текущем
  baseline ограничены окружением; один PHPUnit/MySQL setup test конфликтует
  с уже существующей таблицей.

## 3.26. Character-readiness checkpoint

Что готово как foundation:

- Character schema имеет actual row и viewer table;
- `Characters` умеет add/get/replacePayload/setActive;
- input normalizer проверяет имя, positive ids, JSON-array payload и
  visibility field list;
- repository делает version guard и возвращает typed `CharacterRecord`;
- narrow RuleSpace slice и sequential Character tests существуют.

Что отсутствует до authoritative Character API:

- HTTP actions/routes, actor-bound owner resolution и object-level access;
- server-side validation choices/sheet против актуального Rule catalog;
- проверка `rulesRevision` принадлежности выбранному `space_id` и наличие
  published/approved snapshot;
- Character version migration и compatibility policy;
- memberships, approved version, public visibility policy, moderation и
  overlay/state relation;
- derived authoritative read model вместо доверия frontend-calculated sheet;
- DB CAS/lost-update guarantee и two-connection test.

Критичный контрактный риск: `Characters::add()` получает `ownerUserId` из
`NewCharacter` и проверяет лишь существование user. Пока нет public action,
это не доказанный внешний exploit, но для будущего HTTP edge такой API нельзя
выставлять без actor binding.

## 3.27. Game-readiness checkpoint

Frontend/domain prerequisites уже представлены:

- Game lifecycle/status transitions, access and membership services;
- approved character + overlay resolution model;
- NPC visibility/migration/moderation DTOs;
- session/process/action/check/attack/injury/DOT services;
- combat overlay, chat attachments, chronicle/loot UI boundaries;
- typed `IGameApi` surface for game, membership, session, combat, loot and
  moderation operations.

Backend prerequisites отсутствуют или не подтверждены:

- Game module/actions/routes/repositories и object authorization;
- game membership with approved immutable snapshot and overlay persistence;
- session start/action/stop atomic commit;
- authoritative combat/action resolution and state transitions;
- chronicle persistence and ordering;
- NPC storage/history/moderation;
- economy operations, immutable ledger, idempotency and optimistic versions;
- battleground scene/spatial persistence;
- server-side security boundary for opaque chat/combat attachments.

Канонический stop flow (`resolve → validate → update actualCharacter →
clear overlay`) и rule revision migration нельзя реализовать безопасно,
полагаясь на существующие frontend services/mocks. `REV-BE-001` остаётся
`REQUIREMENT / NOT_IMPLEMENTED`.

## 3.28. Character/Game boundary checkpoint

Каноническая граница должна быть:

- Character владеет actual version, owner, rule-space/revision reference и
  character-level edits;
- Game владеет membership, immutable approved snapshot, session overlay,
  session actions, NPC state, moderation и game-scoped permissions;
- RuleSpace владеет immutable rule revision/publish/CAS;
- Chat владеет messages/attachments и не должен сам интерпретировать
  Character/Game domain state;
- Game resolves Character snapshot through an explicit membership contract,
  а не через повторный произвольный Character read.

Frontend partially отражает эту границу: `GameCharacterMembership` несёт
approved version, `SessionCharacterService` резолвит overlay, `IGameApi`
разделяет membership/session/combat/loot/moderation операции, а chat UI
рендерит combat attachments. Однако `GameChatTab` одновременно загружает
Character API details, membership и NPC data, поэтому host orchestration
остаётся смешанной.

Не подтверждены backend-side:

- invariant «character состоит только в одной игре»;
- immutable approved snapshot и migration→moderation transition;
- binding game rules revision к Character/RuleSpace snapshot;
- visibility policy для owner/GM/selected players на каждом read path;
- separation actualCharacter mutation, overlay mutation и session commit;
- Chat attachment authorization и opaque payload validation;
- capability checks для NPC versus player character.

До появления этих boundaries нельзя переносить frontend service names
непосредственно в PHP API: это разные уровни контракта.

## 3.29. Security checkpoint

Статически подтверждены controls:

- backend actor берётся из request context/AuthSessionBinder, а User/Auth
  services проверяют permission keys и object ownership в проверенных путях;
- HTTP routes по умолчанию требуют double-submit CSRF cookie/header;
- frontend HTML descriptions проходят DOMPurify allowlist, запрещены
  произвольные data attributes и сохраняется только `data-rule-code`;
- semantic rule references используют `code`, а public DTO не должны
  использовать storage id как fallback resolution;
- error response отделён от debug formatter и generic internal errors
  логируются через kernel logger.

Открытые security risks/decisions:

- login/register/password-reset routes opt out of CSRF; допустимость зависит
  от cookie/SameSite/threat model и не должна считаться автоматически
  безопасной;
- Character `ownerUserId`, `spaceId` и `rulesRevision` ещё не связаны с
  actor/object authorization на public action;
- Game/NPC/membership/overlay/combat/economy authorization backend отсутствует;
- future opaque chat/combat attachments требуют schema, size, type and
  authorization validation, а не только frontend rendering;
- secret redaction, rate limiting, replay/idempotency, audit trail и
  production log policy требуют отдельного backend pass.

Это не обнаружило P0 security vulnerability в существующих public PHP
Character/Game actions — таких actions нет; отсутствие edge само является
readiness gap перед реализацией.

## 3.30. Vertical scenario checkpoints

### Character: `PARTIAL / BACKEND_OPEN`

Проверенный путь:

1. `CharacterEditPage` загружает Character detail и выбранную RuleSpace
   revision;
2. `CharacterSheetEditor` строит draft, выполняет client validation и
   сериализует `CharacterVersion`;
3. `CharacterApi` отправляет create/update/migration/visibility commands;
4. mock implementation сохраняет latest или in-game overlay;
5. backend storage уже имеет `CharacterTable`, `CharacterRepository`,
   optimistic `actual_version` и RuleSpace slice loader.

Разрыв пути:

- PHP public `character.create/update` actions намеренно отсутствуют
  (`CharacterPortBootTest` это фиксирует);
- owner actor binding, server validation against revision, approved snapshot,
  membership, overlay and migration are not a complete backend path;
- frontend mock therefore proves UX/domain assumptions, not backend contract.

Критерий готовности Character не выполнен до появления public action,
authoritative validation и round-trip/integration tests.

### Game/session/combat: `REQUIREMENT / NOT_IMPLEMENTED`

Проверенный путь frontend/mock:

1. Game detail loads game, memberships, NPCs and RuleSpace revision;
2. membership resolves `approvedCharacterVersion + overlay`;
3. chat/combat dialogs calculate offers, actions, checks and effects;
4. session stop commits overlay to actual and moderation state is recalculated
   in mocks;
5. chronicle/loot/moderation DTOs represent expected read/write semantics.

Разрыв пути:

- no PHP Game actions, repositories, persistence or authoritative resolver;
- no atomic `resolve → validate → commit actual → clear overlay`;
- no backend ordering/idempotency/authorization contract for combat/chat/economy;
- combat calculation remains reachable from Vue orchestration and cannot be
  treated as a server implementation.

Эта цепочка является requirement backlog, а не найденным дефектом
существующего backend.

### NPC read-only: `PARTIAL / UI_GAP`

`NpcsTab` уже позволяет открыть NPC без запуска боя: игрок получает
`SheetCard`, GM получает management form. Это закрывает исходный узкий
сценарий просмотра листа без combat start и без обязательного перехода в
editor.

Остаётся неудобство: отдельный GM/moderator preview полного листа,
read-only/error/retry path и единый read model до edit/approve не выделены.
Это `P2` usability/maintenance gap, а не blocker.

Evidence:

- `Roleplay/Game/Component/Detail/NpcsTab.vue`;
- `Roleplay/Game/Component/Detail/CombatCardPanel.vue`;
- `Roleplay/Game/Page/NpcEditPage.vue`.

### Rule/RuleSpace: `PARTIAL / CODE_GAP`

Проверенный путь:

1. Rule editor создаёт draft для RuleType;
2. draft store и `RuleDraftService` нормализуют/prune spec;
3. publish input передаёт rules, removals и catalog;
4. backend mapper/assembler создаёт immutable rule versions/revision;
5. Rule detail card and runtime consumers read the revision.

Разрывы:

- Formula editor не создаёт все варианты, поддерживаемые runtime;
- `mechanicPayload` сохраняется/используется, но не имеет полноценного
  typed editor/view surface;
- string-based derived-characteristic formula is a separate legacy/current
  contract, not an object-valued Formula view defect;
- publish flow не имеет expected/base revision + CAS;
- catalog repository имеет N+1 placement reads.

Поэтому RuleSpace нельзя считать полностью data-driven и
concurrency-ready, даже если последовательный happy path работает.

## 4. Подтверждённые или высоковероятные находки

### REV-FE-001 — доменная логика в combat/action Vue dialogs

**Severity:** `P1`  
**Type:** `ARCHITECTURE_ERROR`  
**Confidence:** `HIGH`

`ActionLaunchDialog.vue`, `AttackLaunchDialog.vue` и `CheckLaunchDialog.vue`
содержат eligibility, requirements, cost calculation, modifier/pool
resolution, state mutation и process resolution.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/ActionLaunchDialog.vue:130-240,440-700`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/AttackLaunchDialog.vue:121-670`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/CheckLaunchDialog.vue:426-670`.

Это противоречит разделению `.vue` как UI и доменной логики в `Service/`.

### REV-FE-002 — silent async fallback в launch dialogs

**Severity:** `P1`  
**Type:** `CODE_ERROR`  
**Confidence:** `HIGH`

Ошибки загрузки превращаются в пустые объекты, массивы, `null` или пустые
карты без F17 error/retry state.

Evidence:

- `AttackLaunchDialog.vue:526-537`;
- `ActionLaunchDialog.vue:397-418`;
- `HitLaunchDialog.vue:824-834`;
- `CheckLaunchDialog.vue:250-260,379-383`.

### REV-FE-003 — дублирование combat orchestration

**Severity:** `P2`  
**Type:** `DUPLICATED_DOMAIN_LOGIC`  
**Confidence:** `HIGH`

`speakerFor`, combat hydration, process stopping и fallback handling повторяются
в `AttackLaunchDialog.vue`, `ActionLaunchDialog.vue` и `HitLaunchDialog.vue`.

### REV-FE-004 — локальные evaluator-ы теряют поддерживаемые Formula

**Severity:** `P1`  
**Type:** `CONTRACT_ERROR`  
**Confidence:** `HIGH`

`AbilityCheckAdvantagesService.formulaValue()` и
`EditorCheckBonusesService.formulaValue()` поддерживают только часть Formula.
Остальные варианты возвращают `0`, хотя общий evaluator поддерживает больше
вариантов.

Evidence:

- `Character/Service/AbilityCheckAdvantagesService.ts:82-109`;
- `Character/Service/EditorCheckBonusesService.ts:86-108`;
- общий кандидат-владелец:
  `Character/Service/FormulaEvaluationService.ts:17-87`.

Это одновременно runtime/editor parity defect и duplication candidate.

### REV-FE-005 — разная агрегация source modifiers

**Severity:** `P2`  
**Type:** `DUPLICATED_DOMAIN_LOGIC`  
**Confidence:** `MEDIUM`

`CharacterOverviewService.aggregateModifiers()` реализует собственную
агрегацию и объединяет `null`-источники в искусственный источник
`прочее`, тогда как `AggregateSourceDeltasService` использует отдельную
семантику.

Evidence:

- `Character/Service/Overview/CharacterOverviewService.ts:356-377`;
- `Rule/Service/AggregateSourceDeltasService.ts:8-31`.

Семантика `null`-источника требует подтверждения каноном или отдельного
решения, но две реализации нельзя считать эквивалентными без теста.

### REV-FE-006 — production hardcodes для combat rule concepts

**Severity:** `P2`  
**Type:** `DATA_DRIVEN_GAP`

Найдены:

- `CombatCardModelService.ts:216,230` — literal state codes вместо
  `Rule/Constant/State/STATE_CODES.ts`;
- `AttackDamageService.ts:48-59,210` — hardcoded `endurance` и fallback
  `{ base: 1, size: 0 }`;
- два production fallback mastery `{ base: 3, size: -1 }` в
  `HitRollService.ts:38-51` и `Utils/strikeCharacteristicMods.ts:5-28`.

Намеренные числовые константы `PushMathService` и `DodgeSoakService` пока не
считаются ошибками: для них найдено документальное основание в текущих specs.

### REV-MAGIC-001 — spell runtime связан с concrete ability vocabulary

**Severity:** `P2`  
**Type:** `DATA_DRIVEN_GAP`  
**Confidence:** `HIGH`

`SpellCastEfficiencyService` и `SpellCastExecutionService` проверяют
конкретные ability/resource/action codes для spell behavior. При изменении
состава или реализации правил в другой revision relationship не извлекается
из spec/mechanic и может silently перестать применяться.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Game/Service/SpellCastEfficiencyService.ts:12-34`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Service/SpellCastExecutionService.ts:42-70,125-135`;
- `docs/tr/spell-roadmap.md:40-63`.

### REV-RULE-001 — Formula editor не покрывает runtime union

**Severity:** `P1`  
**Type:** `CONTRACT_ERROR`  
**Confidence:** `HIGH`

`Formula`/`FormulaEvaluationService` поддерживают
`parameter_floor_div`, `characteristic_size` и `characteristic_size_gap`, но
`FormulaInput` не имеет полноценного create/edit surface для этих вариантов.
Для `parameter` и `parameter_floor_div` selector может иметь label, однако
`updateType()` не создаёт соответствующий object и template/update handlers
отсутствуют. `characteristic_size` и `characteristic_size_gap` отсутствуют
даже в labels.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/Ability/Formula.ts:3-49`;
- `draft-front_1.2ds/src/modules/Roleplay/Character/Service/FormulaEvaluationService.ts:17-87`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Component/FormulaInput.vue:50-70,280-320`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Constant/Ability/FORMULA_TYPE_LABELS.ts:3-13`.

Это отдельный editor capability gap поверх `REV-FE-004`: существующая
сохранённая Formula может отображаться в runtime, но не может быть
надёжно создана/изменена тем же редактором.

### REV-RULE-002 — mechanic payload не имеет editor/view surface

**Severity:** `P2`  
**Type:** `CONTRACT_ERROR`  
**Confidence:** `HIGH`

`mechanicPayload` сохраняется в Rule DTO, commit mapper, version body и
read assembler и читается runtime (`CheckResolutionService`), но
`RuleEditPage`/`RuleEditorBase` связывают только `mechanicId`, а
`RuleDetailPage` показывает только mechanic name/version/description.
Payload нельзя полноценно увидеть или отредактировать через rule UI.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/Rule.ts:1-30`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Page/RuleEditPage.vue:60-70,290-610`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue:1-69`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Page/RuleDetailPage.vue:130-146`;
- `www/mifrial/modules/Roleplay/RuleSpace/Service/RuleSpaceViewAssembler.php:110-128`.

### REV-RULE-003 — Characteristic card и Formula presentation (rejected claim)

**Status:** `REJECTED`  
**Type:** `INTENTIONAL_CONTRACT`  
**Confidence:** `HIGH`

Первоначальная формулировка ошибочно считала `spec.formula` объектным
экземпляром общего Formula union. Фактический контракт
`CharacteristicSpec.formula` — `string | null`; текущие значения имеют вид
`min(memory, reasoning)`, а `CharacteristicEditor` разбирает и сохраняет
именно эту строковую модель.

Поэтому `CharacteristicCard` корректно выводит строку производной
характеристики. `RuleViewLabelService.formula()` относится к другим полям,
которые используют типизированный Formula DTO, и не является обязательным
для этой карточки.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/CharacteristicSpec.ts:10-18`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Component/Cards/CharacteristicCard.vue:42-50`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Component/Editors/CharacteristicEditor.vue:38-54`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Service/RuleViewLabelService.ts:41-75`.

Переход строковых derived-characteristic formulas на typed Formula может быть
отдельным архитектурным решением, но не является closure этого finding.

### REV-RULE-004 — редактирование существующего правила теряло mechanicPayload

**Status:** `RESOLVED` (2026-10-01)  
**Historical severity:** `P1`  
**Type:** `DATA_ERROR`  
**Confidence:** `HIGH`

Первоначально `mechanicPayload` терялся на пути `Rule → RuleFormState →
RuleDraftService`. Исправление добавило lossless перенос payload, deep clone,
явную фиксацию загруженного `mechanicId` и сброс payload при смене или
очистке механики.

Evidence and closure:

- `draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/Rule.ts:1-30`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/RuleFormState.ts:4-14`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Utils/Rule/ruleToForm.ts:6-18`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/Service/RuleDraftService.ts:7-21`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/__tests__/Service/ruleDraft.test.ts`;
- `draft-front_1.2ds/src/modules/Roleplay/Rule/__tests__/Utils/ruleToForm.test.ts`.

No-op edit preservation is closed by the mapper/service tests, including
nested payload, clone isolation, `null`/`undefined` and mechanicId changes.
Backend payload validation remains a separate later Rule contract task.

### REV-SEC-001 — public-game visibility semantics (rejected claim)

**Status:** `REJECTED`  
**Type:** `INTENTIONAL_CONTRACT`  
**Confidence:** `HIGH`

Первоначальная формулировка ошибочно трактовала `audience: all` как
«только участники игры». По принятому контракту публичная игра допускает
просмотр наблюдателем имён персонажей и данных, разрешённых настройкой
видимости самого листа. NPC также виден согласно собственной настройке
видимости, а ведущий конкретной игры получает полный доступ к персонажам и
NPC этой игры.

`GameAccessService`, `SheetAccessService` и `NpcsTab` следует проверять именно
относительно этого контракта. Это не подтверждённая privacy vulnerability и
не требует отдельного исправления. Однако при появлении backend Game сервер
должен authoritative образом проверить public/private game, visibility листа
и GM-роль в контексте конкретной игры; frontend/mock resolver не заменяет
эту проверку.

Evidence:

- `docs/tr/game-system.md`;
- `docs/tr/character-system.md`;
- `docs/tr/ui-system.md`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Service/GameAccessService.ts:14-27`;
- `draft-front_1.2ds/src/modules/Roleplay/Character/Service/SheetAccessService.ts:54-62`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/Detail/NpcsTab.vue:54-57`;
- отсутствие backend Game authorization boundary зафиксировано в
  `REV-BE-001`.

### REV-SEC-002 — chat/chronicle entity resolution (rejected as current finding)

**Status:** `REJECTED`  
**Type:** `BACKEND_NOT_IMPLEMENTED`  
**Confidence:** `MEDIUM`

Первоначальная формулировка превращала отсутствие authoritative backend
resolution для chat/chronicle entity references в текущую security-находку.
Это неверно в рамках текущего этапа: backend Game и его read paths ещё не
реализованы, а Chronicle уже использует `SheetAccessService` при построении
карточки ссылки.

Permission-aware разрешение ссылок остаётся частью будущего контракта
`REV-BE-001`, но отдельная remediation task по этому finding не создаётся.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/Detail/GameChatTab.vue:132-166`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/Detail/ChronicleTab.vue:139-147`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/Detail/ChronicleEntryContent.vue:24-41`;
- отсутствие backend Game boundary: `REV-BE-001`.

### REV-GAME-001 — session/speaker eligibility применяется непоследовательно

**Severity:** `P2`  
**Type:** `CONTRACT_ERROR`  
**Confidence:** `HIGH`

`GameChatTab` вычисляет `eligibleMemberships`, но start/stop и speaker
selection используют только `membershipStatus === 'active'`; eligibility
по approved snapshot, actual diff и game rules revision не является
обязательной предпосылкой. Backend Game должен повторять invariant, иначе
клиент может начать session или отправить действие от неподходящего
membership.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/Detail/GameChatTab.vue:86-110`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/Detail/GameChatTab.vue:174-215`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Service/SessionCharacterService.ts`.

### REV-GAME-002 — настройки игры и stop-session не атомарны на frontend boundary

**Severity:** `P2`  
**Type:** `CONCURRENCY_ERROR`  
**Confidence:** `HIGH`

`GameEditPage` выполняет остановку сессии и сохранение настроек двумя
последовательными mutation calls. Ошибка второй операции оставляет первую
изменённой; retry не имеет общей operation identity. Для backend Game это
требование к command boundary, transaction scope и idempotency, а не
основание автоматически объединять независимые mutations в UI.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Game/Page/GameEditPage.vue:60-66`;
- общие ограничения backend Game зафиксированы в `REV-BE-001` и
  concurrency checkpoint.

### REV-PHP-001 — Dto зависел от Service (resolved)

**Status:** `RESOLVED` (2026-10-01)  
**Historical severity:** `P1`  
**Type:** `ARCHITECTURE_ERROR`  
**Confidence:** `HIGH`

Изначально `RuleSpaceCatalog::fromParts()` импортировал и создавал
`RuleSpaceCatalogGuard` из `Service`. В коммите `4e4d5b6` guard удалён, а
проверки инвариантов перенесены в private static methods самого immutable
snapshot. `Dto` больше не зависит от `Service`.

Evidence and closure:

- `www/mifrial/modules/Roleplay/RuleSpace/Dto/RuleSpaceCatalog.php:1-220`;
- удалённый `RuleSpaceCatalogGuard`;
- `www/mifrial/modules/Roleplay/RuleSpace/tests/RuleSpaceUnitTest.php:90-116`.

Существующие проверки cycle и unknown placement сохраняют
`RuleSpaceInvalidException`; отдельная application-service абстракция для
stateless guard не добавлена.

### REV-PHP-002 — VersionRecord field bag (reclassified)

**Status:** `REJECTED`  
**Type:** `INTENTIONAL_CONTRACT`  
**Confidence:** `HIGH`

`VersionRecord` действительно хранит произвольные `$fields` и публикует
`getFields()`, но это generic Versioning contract, а не прикладной Record.
`RuleVersionRecord` адаптирует generic fields в semantic getters.

Evidence:

- `www/mifrial/modules/Versioning/Space/Dto/VersionRecord.php:30,46-53,93-96`.
- `docs/tr/versioning-plan-01.md:131-160`;
- `www/mifrial/modules/Roleplay/Rule/Dto/RuleVersionRecord.php:67-86`.

Наблюдение изначального прохода закрыто как false positive. DEC-079 для
прикладных Records не распространяется автоматически на generic Versioning
Record без отдельного решения.

### REV-PHP-003 — runtime locator usage (rejected claim)

**Status:** `REJECTED`  
**Type:** `INTENTIONAL_CONTRACT`  
**Confidence:** `HIGH`

Первоначальная формулировка смешивала runtime service locator с разрешённым
composition/CLI boundary. `Application` является composition root, а
`AgentTickCli` вызывается напрямую из `bin/agent.php` и динамически связывает
agent handlers из module config. Locator в этих местах используется как
каталог модульных контейнеров, а не как скрытая зависимость обычного
domain/application service.

Evidence:

- `www/mifrial/modules/Core/Kernel/Service/Application.php:34-50,210-240`;
- `www/mifrial/modules/Core/Agent/Service/AgentTickCli.php:29-111`;
- `www/mifrial/bin/agent.php:12-17`;
- `docs/tr/architecture.md:44-49,228`.

Отдельного исправления не требуется. Ограничение сохраняется: не переносить
locator-доступ в обычные runtime/domain services.

### REV-PHP-004 — локально подавленная сложность (rejected claim)

**Status:** `REJECTED`  
**Type:** `INTENTIONAL_CONTRACT`  
**Confidence:** `HIGH`

Проверенные suppressions имеют локальное обоснование и относятся к cohesive
границам:

- `RuleSpaceHttpService` — единый HTTP-фасад RuleSpace;
- `UserGroupMemberRepository` — одна коллекция членств, списков id и count;
- `UserRepository` — одна коллекция учёток и её read/write operations.

Нет evidence, что классы смешивают несвязанные домены. Дробить их только ради
метрик противоречит KISS и прямому правилу проекта не ухудшать дизайн ради
quality-gate. Suppression следует пересмотреть только при добавлении новой
несвязанной ответственности.

### REV-BE-001 — backend Game отсутствует

**Status:** `REQUIREMENT / NOT_IMPLEMENTED`  
**Type:** `CODE_GAP`, но не unexpected defect

PHP namespace/module/config/tables/services/actions/repositories/tests для
Roleplay/Game не обнаружены. Это согласуется с текущим `OPEN`/`REQUIREMENT`
статусом в architecture и game-system документах, поэтому не является
неожиданной ошибкой текущего прототипа. Это blocker для утверждения backend
Game как уже готового.

### REV-UI-001 — несогласованные edit affordances

**Severity:** `P2`  
**Type:** `CODE_GAP`

Evidence:

- `Rule/Component/RuleDetailPage.vue:112-116`;
- `Rule/routes.ts:20-26`;
- `Game/Component/Detail/CharactersTab.vue:535-548`;
- `Character/Page/CharacterEditPage.vue:80-84,147-149`.

UI показывает возможность редактирования без достаточной проверки права или
ownership, оставляя отказ на поздний слой.

### REV-UI-002 — async save state очищается до завершения save

**Severity:** `P2`  
**Type:** `CODE_ERROR`

`CharacterSheetEditor.vue:182-201,286-290` эмитит async save без ожидания
завершения host operation, что допускает повторное действие и преждевременное
снятие saving state.

### REV-UI-003 — NPC editor без F17 load state

**Severity:** `P2`  
**Type:** `CODE_ERROR`

`Game/Page/NpcEditPage.vue:108-143` выполняет несколько await без
`try/catch/finally`, retry и видимого состояния ошибки. Сопоставимый
ожидаемый паттерн есть в `CharacterEditPage.vue:66-120`.

### REV-UI-004 — NPC read-only/moderation preview surface отсутствует

**Severity:** `P2`  
**Type:** `UI_GAP`  
**Confidence:** `HIGH`

NPC можно открыть без боя через `NpcsTab`, но игрок получает `SheetCard`,
тогда как GM получает management form. Отдельного NPC detail/read-only route
нет; combat-card read mode не открывается из текущего chat path для
не-редактирующего пользователя; moderation queue показывает имя/автора и
approve/reject без preview перед решением. Это оставляет несколько
частично пересекающихся UI surfaces и вынуждает ведущего использовать
редактор или combat context для полноценного просмотра.

Evidence:

- `draft-front_1.2ds/src/modules/Roleplay/Game/routes.ts:53-58`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/Detail/NpcsTab.vue:316-317,388-405`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/Detail/NpcCard.vue:114-152`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Component/Detail/GameChatTab.vue:331-334`;
- `draft-front_1.2ds/src/modules/Roleplay/Game/Page/NpcEditPage.vue:110-143`.

### REV-CON-001 — публикация RuleSpace без expected revision

**Status:** `RESOLVED`  
**Type:** `CONCURRENCY_ERROR`  
**Confidence:** `HIGH`

Исходная проблема была подтверждена: `CommitDraftInput` не содержал
expected/base revision, а публикация читала актуальное состояние и затем
commit-ила без compare-and-swap внутри транзакции. Это позволяло потерять
изменения второго редактора.

Evidence:

- `RuleSpace/Dto/Action/CommitDraftInput.php:10-29`;
- `RuleSpace/Service/RuleSpaceHttpService.php:303-323`;
- `RuleSpace/Service/RuleSpaces.php:311-322`;
- `Versioning/Space/Repository/RevisionPublisher.php`.

Исправление реализовано:

- клиент передаёт expected revision;
- backend выполняет CAS внутри транзакционной границы;
- stale publish возвращает `RULESPACE_CONFLICT`;
- новая ревизия при конфликте не создаётся;
- `expectedRevision` и `actualRevision` доходят до frontend `ActionFailure`;
- MySQL HTTP-тест проверяет stale publish и отсутствие новой ревизии;
- frontend-тест проверяет сохранение деталей конфликта.

Finding закрыт 2026-10-01. Настоящий two-connection concurrency test оставлен
отдельной задачей hardening и не является незакрытым дефектом текущего
контракта. Атомарность последующего catalog binding также требует отдельного
анализа и не входит в этот finding.

### REV-CON-002 — устаревший frontend response может изменить текущий context

**Status:** `RESOLVED`

**Severity:** `P2`  
**Type:** `CONCURRENCY_ERROR`  
**Confidence:** `HIGH`

Исходная проблема подтверждалась: `SpaceContextLayout` и `spaceRevision`
после `await` без generation check записывали результат в state. Если
транспорт не уважал abort, старый route request мог перезаписать более новый
context.

Evidence:

- `RuleSpace/Component/SpaceContextLayout.vue:28-40,83`;
- `RuleSpace/Store/spaceRevision.ts:61-65,111-124`;
- контрастный паттерн:
  `Core/UI/Composables/useGridData.ts:100-133`.

Исправление реализовано в `useSpaceContextResolve`: каждая загрузка получает
generation и собственный `AbortController`, а устаревшие ответы не могут
изменить `currentSpace`, `activeContext`, `loadedCode`, `loading` или `error`.
`syncContext` работает со снимком загруженного пространства. Добавлены тесты
для позднего ответа, поздней ошибки, retry, смены draft/revision и unmount.

Finding закрыт 2026-10-01. Отдельная hardening-задача по защите записи
`revisionsMeta` от устаревшего ответа не влияет на текущий context и не входит
в этот finding.

### REV-CON-003 — frontend ActionError теряет backend error details

**Status:** `RESOLVED`  
**Severity:** `P2`  
**Type:** `CONTRACT_ERROR`  
**Confidence:** `MEDIUM`

Backend `ActionResponse::fail()` и frontend `ActionError` использовали
несовместимые форматы дополнительных машиночитаемых полей: backend добавлял
их в корень `error`, а frontend consumers ожидали `error.details`.

Evidence:

- `www/mifrial/modules/Core/Kernel/Dto/ActionResponse.php:62-70`;
- `draft-front_1.2ds/src/modules/Core/Engine/Dto/ActionError.ts:1-5`;
- `draft-front_1.2ds/src/modules/Core/Engine/Dto/ActionResponse.ts:1-8`.

Исправлено в коммите `47b7f69`: backend теперь формирует единый
`error.details` envelope, RuleSpace и Character consumers используют этот
формат, а Kernel/RuleSpace/Character tests проверяют его сохранение.
Finding закрыт 2026-10-01. Дополнительные concurrency observations ниже
остаются отдельными findings и не входят в этот контрактный fix.

Дополнительный concurrency pass:

- Character payload writes уже используют application-level
  `expectedVersion`, но `CharacterRepository::writeGuarded()` сначала читает
  запись и только затем пишет её внутри transaction; DB-level conditional
  update и двух независимых connections не доказаны;
- RuleSpace publish не принимает idempotency key и не имеет retry-safe
  operation identity; повтор запроса после network timeout может создать
  повторную публикацию, если клиент не знает результат первой операции;
- mutation actions не имеют общей replay/idempotency policy для будущих
  membership, overlay, combat и economy операций;
- frontend применяет AbortSignal неоднородно: часть mock/API методов
  принимает сигнал формально, но не гарантирует отмену; therefore abort не
  является concurrency correctness proof;
- rollback на repository transaction boundary есть, но compound Auth и
  будущие Game flows пересекают несколько сервисных операций без
  подтверждённой общей транзакции.

До реализации Game нужны явные решения для conditional writes, idempotency
scope, повторяемости command endpoints, ordering событий и поведения после
timeout/retry. Эти gaps не исправляются в рамках read-only review.

### REV-DATA-001 — регистрация пользователя неатомарна

**Status:** `RESOLVED`  
**Severity:** `P2`  
**Type:** `DATA_ERROR`  
**Confidence:** `HIGH`

Исходная проблема подтверждалась: профиль, identity, memberships и login
создавались последовательными операциями без общей транзакции.

Evidence:

- `Core/Auth/Service/AuthService.php:90-97`;
- `Core/Auth/Service/UserCreateService.php:53-70`.

Исправлено: регистрация и admin-create используют общий
`ITransactionRunner`, включая создание DB-сессии. Добавлены failure/rollback
tests. Finding закрыт 2026-10-01; фактический MySQL rollback остаётся
pending verification, поскольку тесты в текущем окружении были пропущены без
MySQL.

### REV-DATA-002 — reset password/session invalidation неатомарны

**Status:** `RESOLVED`  
**Severity:** `P2`  
**Type:** `DATA_ERROR`
**Confidence:** `HIGH`

Исходная проблема подтверждалась: token consumption, смена password hash и
удаление старых сессий выполнялись отдельными шагами без SmartTable
transaction.

Evidence:

- `Core/Auth/Service/PasswordResetService.php:99-113`;
- `Core/Auth/Service/SetPasswordService.php:72-80`.

Исправлено: reset и set password используют общий `ITransactionRunner`.
Reset notification теперь ставится в очередь до commit и доставляется после
commit. Добавлены rollback, one-time token и enqueue-failure tests. Finding
закрыт 2026-10-01; DB rollback pending verification в MySQL-enabled
окружении.

### REV-DATA-003 — часть snapshot/catalog invariants существует только в application validation

**Status:** `REJECTED / FALSE_POSITIVE_FOR_CURRENT_SCOPE`  
**Severity:** `P2`  
**Type:** `DATA_ERROR`  
**Confidence:** `HIGH`

`character.rules_revision` хранит только положительный integer и не имеет
ссылки на конкретную `rule_revision`; `rulespace_revision_catalog` хранит
`space_id + revision + section_version`, но не имеет FK на фактический
catalog snapshot. `rulespace_catalog_item.rule_code` также не связан
составным ограничением с составом соответствующей rule revision. Поэтому
удалённая/повреждённая или ошибочно записанная ссылка может быть обнаружена
только при последующей сборке среза.

Evidence:

- `Roleplay/Character/Table/CharacterTable.php:58-68`;
- `Roleplay/RuleSpace/Table/RuleSpaceRevisionCatalogTable.php:35-60`;
- `Roleplay/RuleSpace/Table/RuleSpaceCatalogSectionTable.php:35-62`;

Finding отклонён 2026-10-01 как false-positive для текущего scope. Для
append-only versioned storage отсутствие полного набора cross-snapshot DB
constraints само по себе не доказывает текущую ошибку: основной контракт
опирается на application-owned validation, а backend Character/Game ещё не
является реализованным production write path.

Изменения сейчас не требуются. Вопрос можно переоткрыть при реализации
backend Character/Game, если появится обязательный контракт DB-level
enforcement или evidence writer-а, обходящего существующие guards.
- `Roleplay/RuleSpace/Table/RuleSpaceCatalogItemTable.php:35-58`;
- runtime guard: `Roleplay/Character/Service/CharacterRuleSlices.php:77-105`.

Это может быть допустимой сознательной моделью для immutable/versioned
storage, но тогда требуются явный ownership/retention policy и интеграционные
проверки orphan/cross-space references. Сейчас это не доказано.

### REV-DATA-004 — versioned rule composition недостаточно защищён от дублирования и расхождения кодов

**Severity:** `P2`  
**Type:** `DATA_ERROR`  
**Confidence:** `MEDIUM`

DB unique keys защищают `revision_id + version_id` и
`space_id + section_version + code`, но не выражают полностью доменные
инварианты: один rule code в revision, существование item code в выбранном
revision, существование parent section и отсутствие циклов в parent tree.
Эти проверки выполняются в assembler/catalog guard. Их обход через другой
writer или неполную будущую миграцию способен создать snapshot, который
формально проходит таблицы, но не собирается как единый rule space.

Evidence:

- `Roleplay/Rule/Table/RuleRevisionItemTable.php:35-58`;
- `Roleplay/RuleSpace/Table/RuleSpaceCatalogSectionTable.php:45-62`;
- `Roleplay/RuleSpace/Table/RuleSpaceCatalogItemTable.php:45-58`;
- `Roleplay/RuleSpace/Service/RuleSpaceCommitAssembler.php:51-140`;
- `Roleplay/RuleSpace/Dto/RuleSpaceCatalog.php:120-220`.

### REV-DATA-005 — schema setup не является версионированной migration policy

**Severity:** `P2`  
**Type:** `ARCHITECTURE_ERROR`  
**Confidence:** `HIGH`

Roleplay module setup в основном возвращает table classes, а schema
`install()` выбирает `createTable()` или `updateTable()` по факту наличия
таблицы. Это обеспечивает self-healing setup для текущей карты, но не
фиксирует последовательность миграций, backfill, rollback и совместимость
старых данных. Для Character/Game, где появятся snapshots, membership и
session state, такой механизм не предоставляет доказуемой upgrade policy.

Evidence:

- `Roleplay/Character/Setup/CharacterModuleSetup.php:15-30`;
- `Roleplay/Character/Schema/CharacterSchema.php:50-70`;
- `Roleplay/Rule/Schema/RuleSchema.php:60-85`;
- `Roleplay/RuleSpace/Schema/RuleSpaceSchema.php:59-80`;
- `Roleplay/RuleSpace/Setup/RuleSpaceModuleSetup.php:15-30`.

Это readiness gap, а не требование немедленно переписывать существующий
schema runner.

### REV-PERF-001 — загрузка catalog placements выполняет N+1 запросов

**Severity:** `P2`  
**Type:** `PERFORMANCE_ERROR`  
**Confidence:** `HIGH`

`RuleSpaceCatalogRepository::getBySectionVersion()` сначала получает все
sections, а затем `placementsForSections()` выполняет отдельный
`getList()` для каждого `section_id`. Каталог с большим числом секций
создаёт линейный burst SQL-запросов вместо одного batch-запроса по
`section_id IN (...)`; это особенно важно, потому что каталог читается при
загрузке revision и участвует в publish/read flows.

Evidence:

- `Roleplay/RuleSpace/Repository/RuleSpaceCatalogRepository.php:70-88`;
- `Roleplay/RuleSpace/Repository/RuleSpaceCatalogRepository.php:220-247`.

### REV-PERF-002 — GameChatTab создаёт отдельный Character request для каждого membership

**Severity:** `P2`  
**Type:** `PERFORMANCE_ERROR`  
**Confidence:** `HIGH`

После загрузки memberships chat делает `Promise.all`, но каждый membership
вызывает отдельный `getCharacter(characterId)`. Это устраняет
последовательное ожидание, но не N+1: растёт число HTTP/database операций,
нагрузка на сервер и вероятность частично устаревшего набора actual versions.
Потребность chat в actual versions должна быть выражена batch/read-model
контрактом.

Evidence:

- `Roleplay/Game/Component/Detail/GameChatTab.vue:220-239`.

### REV-PERF-003 — repeated linear rule lookup в combat calculations

**Severity:** `P3`  
**Type:** `PERFORMANCE_ERROR`  
**Confidence:** `HIGH`

Combat paths многократно ищут rules и modifier rules через
`Array.prototype.find()` внутри циклов inventory/attacks/targets. Для
небольшого mock-состава это незаметно, но Character/Game runtime строит
несколько derived models на один render и может повторять O(items × rules)
работу. Отсутствует единый memoized/indexed `code → Rule` context на
границе read model/calculation.

Evidence:

- `Roleplay/Game/Service/HitRollService.ts:89-109`;
- `Roleplay/Game/Component/Detail/CombatCardPanel.vue:216-248`;
- `Roleplay/Game/Component/Detail/GameChatTab.vue:250-255`.

### Performance pass: additional observations

- pagination существует для SmartTable list contracts, но revision/catalog
  snapshots сознательно используют `ListQuery::MAX_LIMIT`; необходимо
  подтвердить лимиты и memory budget для worst-case rule spaces;
- `Promise.all` используется как burst без concurrency limit в нескольких
  frontend flows; это не всегда дефект, но требует budget/abort/error policy;
- отдельного bundle-size, browser profiling и production-like load test в
  текущем окружении не выполнено, поэтому выводы о render cost имеют
  confidence только по статическому evidence;
- benchmark artifacts в `www/mifrial/var` не являются заменой профилирования
  реальных Character/Game сценариев.

## 5. Тестируемость и confidence gaps

Тесты покрывают значительную часть последовательных инвариантов Character,
Rule и SmartTable, но не подтверждают:

- concurrent writes через две транзакционные connection;
- lost-update protection в Character/Rule/SmartTable;
- backend Game API parity;
- mock/real AbortSignal behavior;
- полный Formula parity editor/runtime;
- frontend/backend round-trip для Character DTO;
- UI edit affordances и async save lifecycle.

Фактические запуски в текущем окружении:

- frontend lint: 6 ошибок formatting rule;
- `vue-tsc`: множественные TS errors, включая nullable values, invalid action
  payloads и Formula-related union access;
- Vitest: завершился с `kill EACCES` при остановке worker-ов;
- backend `composer quality`: успешно;
- backend `composer cs-check`: ошибки PHPCS;
- PHPUnit: завершился с 1 ошибкой из 451 теста (6129 assertions):
  `SmartTable\Tests\AddManyMysqlTest::testInsertsTwoRowsAndRejectsUniqueAndMultiple`
  получил `TableExistsException` при создании таблицы
  (`SmartTable/Service/Schema/TableSchema.php:63`,
  `SmartTable/tests/AddManyMysqlTest.php:85`).

Это снижает confidence итогового ревью до `MEDIUM`: статический evidence доступен,
но runtime/test gates не закрыты.

## 6. Результат coordinator-pass

Проведена независимая повторная проверка критичных findings по исходному коду,
тестам и каноническим документам.

### Подтверждены

- `REV-FE-001` — доменная логика в launch dialogs;
- `REV-FE-002` — silent async fallbacks;
- `REV-FE-004` — Formula parity defect;
- `REV-FE-005` — дублирующаяся source aggregation с расхождением для
  penalty-only и zero cases;
- `REV-PHP-001` — resolved: Dto → Service dependency устранена;
- `REV-DATA-001` — неатомарная регистрация;
- `REV-DATA-002` — неатомарный password reset/session invalidation.

### Переклассифицированы

- `REV-CON-001` — решение принято: expected revision + CAS; текущее
  latest-wins описание канона стало устаревшим и требует отдельного
  документального обновления;
- `REV-PHP-002` — rejected false positive: generic Versioning Record имеет
  отдельный контракт field bag;
- `REV-BE-001` — ожидаемый `REQUIREMENT / NOT_IMPLEMENTED`, а не неожиданная
  ошибка текущей реализации;
- Character optimistic concurrency и server validation — `PARTIAL / BACKEND_OPEN`,
  а не доказанный дефект C1 storage kernel без уточнения целевого контракта.

### Подтверждены как отдельные quality/UI findings

- `REV-FE-003` — duplication combat orchestration;
- `REV-FE-006` — hardcoded combat rule concepts;
- `REV-UI-001` — edit affordances;
- `REV-UI-002` — async save lifecycle;
- `REV-UI-003` — NPC editor load error state.

## 7. Что пока не считать подтверждённым

- 6315 автоматических records не являются 6315 проблемами.
- Дублирование по одной строке требует AST/семантического подтверждения.
- Literal rule reference в fixture/seed не является production defect без
  доказательства.
- Контекстное отличие combat card от character detail не является лишним UI
  автоматически.
- Backend Game отсутствует как реализация, но это ожидаемый `REQUIREMENT`, а не
  неожиданное расхождение с каноном.

## 8. Исторический список следующих проходов

Пункты этого списка выполнены или заменены финальными артефактами ниже:
дедупликация, P1 verification, source/Formula pass, Character/Game matrix,
evidence registry, open decisions и remediation queue. Оставшиеся runtime
проверки перечислены в разделе residual gaps и не объявлены пройденными.

## 9. Coordinator checkpoint — 2026-09-26 stage 2

Проведён bounded read-only coordinator-pass по приоритетным зонам; production
код, тесты, конфигурация и канонические документы не изменялись.

### Обновлённые статусы

- `REV-FE-004` — `CODE_GAP`, `P1`, `HIGH`: общий
  `FormulaEvaluationService` поддерживает десять вариантов Formula, но
  `AbilityCheckAdvantagesService.formulaValue()` и
  `EditorCheckBonusesService.formulaValue()` поддерживают только четыре и
  молча возвращают `0` для остальных. Паритетного теста runtime/editor нет.
- `REV-FE-005` — `CODE_GAP`, `P2`, `HIGH` для расхождения поведения;
  политика `null`-источника остаётся `OPEN`: центральный агрегатор
  пропускает нули и выбирает сильнейший плюс/штраф, а
  `CharacterOverviewService.aggregateModifiers()` преобразует `null` в
  `'прочее'`, допускает zero-entry и order-dependent penalty-only результат.
- `REV-CON-001` — `CODE_GAP` относительно принятого в очереди CAS-контракта,
  `DECIDED`, `HIGH`: frontend и PHP input не передают expected/base revision,
  publish читает latest и не выполняет CAS. Канонический
  `rulespace-plan-04.md` всё ещё содержит latest-wins/no-base-revision; это
  противоречие документов требует отдельного решения/обновления канона и не
  закрывается изменением отчёта.
- `REV-BE-001` — `REQUIREMENT / NOT_IMPLEMENTED`: backend Game-модуль,
  membership, NPC, overlay, session commit, combat, moderation, economy и
  battleground persistence не обнаружены. Frontend API и mocks не являются
  evidence PHP readiness.
- Character backend — `PARTIAL`: actual storage и узкий RuleSpace slice
  реализованы; authoritative C4 validation, C5 HTTP create/update,
  membership/approved snapshot, overlay, moderation и migration остаются
  `CODE_GAP`/`REQUIREMENT`. Character version guard не является DB CAS.
- `REV-UI-001`, `REV-UI-002`, `REV-UI-003` — `CONFIRMED`, `P2`, `HIGH` для
  статического UI evidence: edit affordances не везде защищены, parent async
  save не удерживает `saving`, NPC editor не имеет F17 load/error/retry.

### Evidence и ограничения

Ключевое evidence:

- Formula: `draft-front_1.2ds/src/modules/Roleplay/Rule/Dto/Ability/Formula.ts:3-49`,
  `Character/Service/FormulaEvaluationService.ts:17-87`,
  `Character/Service/AbilityCheckAdvantagesService.ts:82-109`,
  `Character/Service/EditorCheckBonusesService.ts:86-108`.
- Source aggregation: `Rule/Service/AggregateSourceDeltasService.ts:8-31`,
  `Character/Service/Overview/CharacterOverviewService.ts:356-377`,
  `docs/tr/rule-system.md:160-174`.
- CAS: `RuleSpace/Dto/Action/CommitDraftInput.php:17-29`,
  `RuleSpace/Service/RuleSpaceHttpService.php:303-323`,
  `docs/tr/rulespace-plan-04.md:83-89`.
- Character/Game readiness: `Roleplay/Character/Service/CharacterInputNormalizer.php:10-149`,
  `Roleplay/Character/Interface/Service/ICharacters.php:17-70`,
  `docs/tr/character-roadmap.md:63-98`,
  `docs/tr/game-system.md:5-22,70-90,120-138`.
- UI parity: `Character/Component/Editor/CharacterSheetEditor.vue:182-201`,
  `Game/Page/NpcEditPage.vue:108-157`,
  `Rule/Page/RuleDetailPage.vue:112-116`.

Targeted Vitest exploration did not produce a bounded pass/fail result
(process remained active beyond the allowed observation window), so this is
recorded as `TIMED_OUT`; the existing PHPUnit and full frontend gate remain
`BLOCKED_BY_ENVIRONMENT`/unconfirmed. Missing evidence includes two-connection
Character CAS, concurrent RuleSpace publish, authoritative Character HTTP
round-trip, Game API/storage/authorization, editor-view-runtime round-trip,
UI component lifecycle tests, and full diagnostics.

## 10. Coordinator checkpoint — security/concurrency stage

Проведён отдельный bounded read-only pass по security, concurrency, data
integrity и качеству тестов. Production code, tests, configuration, schema и
канонические документы не изменялись.

### Подтверждённые статусы

- `REV-CON-001` — `CODE_GAP`, `HIGH`: RuleSpace publish не принимает
  expected/base revision и не выполняет CAS; `RevisionPublisher` защищает
  номер ревизии, но не lost update. Контракт противоречив между
  `docs/tr/architecture.md:206-210` и `docs/tr/rulespace-plan-04.md:83-90`.
- `REV-CON-002` — `CODE_GAP`, `HIGH`: `SpaceContextLayout` и
  `spaceRevision` не имеют request-generation guard; последовательные тесты
  не покрывают out-of-order response.
- `REV-DATA-001/002` — `CODE_GAP`, `HIGH`: регистрация и password
  reset/session invalidation выполняют несколько записей без общей
  transaction boundary и rollback tests.
- `CHARACTER-OWNER` — `CODE_GAP`, `HIGH`, пока не public: `Characters::add()`
  принимает `ownerUserId` от вызывающего слоя без actor binding. До появления
  Character HTTP action это readiness blocker, но не подтверждённая внешняя
  exploit.
- `CHARACTER-REVISION-REFERENCE` — `CODE_GAP`, `HIGH`: Character проверяет
  только положительность `rulesRevision`; evidence связи revision с
  выбранным `space_id` не найдено.
- `CHARACTER-CAS` — `CODE_GAP`, `HIGH`: `writeGuarded()` читает и сравнивает
  version в PHP, затем пишет по id; DB conditional update/lost-update test
  отсутствуют.
- `CHARACTER-STALE-RESPONSE` — `CODE_GAP`, `MEDIUM`: Character store
  принимает любой завершившийся `fetchCharacter()` result; generation guard и
  out-of-order test отсутствуют.

### Controls и ограничения

RuleSpace object authorization и actor authorization подтверждены как
`IMPLEMENTED` статически и тестами, но MySQL-backed tests skipped because DB
недоступна. Kernel CSRF control — `PARTIAL`: default protected routes требуют
CSRF, но registration/password reset routes явно `csrf: false`; это оставлено
как `OPEN` contract question, не как автоматически подтверждённая уязвимость.
SmartTable root transaction — `IMPLEMENTED`, nested no-savepoint behaviour
соответствует текущему канону, однако inner-exception test отсутствует.

Тестовый baseline: `composer quality` — `PASSED (590/590)`; MySQL-dependent
Character/RuleSpace/SmartTable/Auth tests — `BLOCKED_BY_ENVIRONMENT` или
`SKIPPED`; frontend Vitest worker termination — `TIMED_OUT`/неподтверждённый
gate. Не проверены две независимые DB connections, rollback injection,
idempotency retry, Character HTTP authorization и Game security boundary.

## 11. Текущий статус кампании

Read-only кампания завершена со статусом
`COMPLETE_WITH_OPEN_QUESTIONS`, confidence `MEDIUM`. P0 evidence не найдено;
существенные P1 gaps остаются в Formula parity, combat/action ownership,
Rule payload preservation, security boundaries, RuleSpace CAS, silent async
error handling и Dto → Service boundary. Backend Game имеет статус
`REQUIREMENT / NOT_IMPLEMENTED`; Character backend готов только частично для
storage/RuleSpace slice.

Не завершены: полный frontend/backend diagnostic gate, MySQL-backed tests,
two-connection CAS/lost-update scenarios, Auth rollback/idempotency tests,
authoritative Character HTTP/API round-trip, Game security/storage/API,
editor-view-runtime round-trip и UI component lifecycle tests. Причины:
отсутствует Game backend или публичный Character API, MySQL недоступен, а
Vitest/PHPUnit runs не дали подтверждённого bounded green результата.
Все эти ограничения явно отражены в `finding-registry.json`,
`codebase-review-2026-09-open-decisions.md` и remediation queue. Исправления
кода в этой кампании не выполнялись.

## 12. Deduplication и нормализация agent findings — 2026-09-27

Независимые вертикальные проходы не породили отдельные полные копии
находок. Совпадения сведены так:

- Rule editor payload: агентское наблюдение о «нет surface» объединено с
  `REV-RULE-002`; более узкое edit → save data-loss вынесено отдельно в
  `REV-RULE-004`, поскольку у него другой impact и criterion of closure.
- RuleSpace expected revision/CAS подтверждает уже существующий
  `REV-CON-001`; новая запись не создаётся.
- NPC editor load failure совпадает с `REV-UI-003`; отсутствие
  read-only/moderation preview — отдельный `REV-UI-004`.
- Character backend owner/revision/CAS/stale-response claims не являются
  четырьмя независимыми storage bugs. Они нормализованы как readiness
  records `REV-CHAR-001..004`, потому что принадлежат разным boundary и
  имеют разные closure criteria.
- Backend Game absence остаётся единственным `REV-BE-001` с
  `REQUIREMENT / NOT_IMPLEMENTED`; отсутствие backend не дублируется
  отдельными «missing implementation» findings для каждой Game feature.
- `REV-SEC-001` отклонён как false positive: public-game `all` visibility
  соответствует канону.
- `REV-SEC-002` отклонён как current finding: permission-aware entity
  resolution относится к отсутствующему backend Game, а не к отдельной
  текущей проблеме.
- Использование frontend/mock resolver-а для GM является не текущей
  vulnerability, а обязательным prerequisite `REV-BE-001`: backend Game
  должен разрешать GM по membership конкретной игры.

Неподтверждённые regex/agent suggestions не повышены до findings без
конкретного code path. `REV-PHP-002` оставлен `REJECTED`; generic
VersionRecord field bag не является нарушением прикладного DTO rule.

### Normalized Character readiness records

#### REV-CHAR-001 — Character owner не связан с actor boundary

`Characters::add()` принимает `ownerUserId` из application input, а
`ICharacters` не принимает actor context. Это `P1 / CODE_GAP / HIGH` до
появления public action; внешний exploit пока не доказан, поскольку
Character HTTP routes отсутствуют.

#### REV-CHAR-002 — rulesRevision не проверяется как revision выбранного space

Storage проверяет положительность `rules_revision`, но не принадлежность
конкретной опубликованной ревизии `space_id`. Это `P1 / CONTRACT_ERROR /
HIGH`; closure требует authoritative revision lookup и negative tests.

#### REV-CHAR-003 — Character optimistic guard не доказан как DB CAS

`writeGuarded()` выполняет read/compare/write в PHP transaction, но нет
conditional update и двух-connection lost-update test. Это `P2 /
CONCURRENCY_ERROR / HIGH`; не объединять с RuleSpace `REV-CON-001`.

#### REV-CHAR-004 — Character store не имеет generation guard

Out-of-order завершившийся `fetchCharacter()` может записать stale detail в
store. Это `P2 / CONCURRENCY_ERROR / MEDIUM`; подтверждение closure требует
request-generation test при transport, который игнорирует abort.

### Open security contract

`REV-SEC-003` — registration/password-reset routes явно отключают CSRF.
Это `OPEN_DECISION / MEDIUM`, а не подтверждённая vulnerability: решение
зависит от cookie/SameSite, authentication flow и threat model.

## 13. Evidence registry и итоговые критерии

Машиночитаемый registry сохранён в
`var/review/finding-registry.json`. Для каждой записи зафиксированы claim,
document section, code evidence, test evidence, status, severity, type,
confidence, owner и criterion of closure. Registry намеренно не превращает
candidate records индексаторов в подтверждённые findings.

## 14. Coordinator recheck — 2026-09-27

Повторно проверены все P1 и спорные boundary по исходному коду:

- `REV-FE-001`, `REV-FE-002`, `REV-FE-004` — подтверждены;
- `REV-RULE-001` подтверждён; `REV-RULE-004` после targeted recheck закрыт
  как исправленный payload-loss, отдельно от отсутствия editor/view surface
  `REV-RULE-002`;
- `REV-PHP-001` — закрыт после удаления прямого DTO → Service import и
  сохранения snapshot invariant tests;
- `REV-SEC-001` — отклонён: public-game/non-member `all` visibility
  соответствует принятому контракту;
- `REV-SEC-002` — отклонён как следствие отсутствующего backend Game, а не
  отдельный текущий finding;
- `REV-CHAR-001`, `REV-CHAR-002` — подтверждены как pre-public readiness
  gaps, не как уже эксплуатируемые HTTP vulnerabilities;
- `REV-CON-001` — подтверждён; CAS decision остаётся обязательным.

Корректировка по результату recheck: `REV-SEC-002` исключён из подтверждённых
findings как следствие отсутствующего backend Game. Точный CSRF evidence уточнён до
`Core/Auth/module.config.php` и `Kernel/Application.php`.

Сверки артефактов:

- registry содержит 40 уникальных IDs;
- каждый registry ID присутствует в report;
- каждый finding с remediation action присутствует в queue; `REJECTED`
  `REV-PHP-002` и `OPEN_DECISION` `REV-SEC-003` явно исключены из обычной
  remediation;
- JSON registry проходит структурную валидацию и `git diff --check` не
  обнаруживает ошибок.

Новые production changes не вносились. Оставшиеся ограничения не изменились:
нет MySQL-backed concurrent/rollback proof, authoritative Character HTTP
round-trip, Game backend и bounded frontend diagnostic green run.

## 15. Решение по REV-RULE-004 — 2026-10-01

Принято: существующий `mechanicPayload` обязан сохраняться без потери данных
через edit → draft → publish. После реализации lossless transfer,
`mechanicId`-aware reset, deep clone и targeted tests
`REV-RULE-004` имеет статус `RESOLVED`.

Backend-проверки структуры и допустимости payload также обязательны, но их
реализация сознательно отложена до отдельной backend Rule contract task.
