# Очередь действий после baseline-ревью

Документ содержит только задачи, которые можно сформировать по текущему
evidence. Исправления кода в этой кампании не выполняются автоматически.

## Requirement — backend Game

### REV-BE-001 — зафиксировать backend Game boundary

- определить владельца PHP Game module;
- зафиксировать membership, NPC, overlay, session commit и combat storage;
- определить action/API boundary;
- определить authorization и optimistic concurrency;
- отдельно решить сцену/battleground и SSE transport;
- не объявлять Game backend реализованным до появления evidence.

Это ожидаемый `REQUIREMENT / NOT_IMPLEMENTED`, а не дефект текущего кода.
Зависимости: backend foundation, Character validation/membership contract.

## Decided concurrency contract

### REV-CON-001 — добавить expected revision в RuleSpace publish — выполнено

Публикация должна использовать optimistic concurrency:

- клиент передаёт ревизию, на которой строил изменения;
- backend сравнивает её с актуальной ревизией пространства;
- при расхождении возвращает машиночитаемый conflict error;
- новая ревизия не создаётся;
- пользователь перечитывает актуальный срез и пересматривает изменения.

Реализовано в commit `c3a1428`: expected/base revision передаётся через
frontend и backend, CAS выполняется внутри transaction boundary, stale publish
возвращает machine-readable conflict и не создаёт новую ревизию. Добавлены
MySQL HTTP-тест stale publish и frontend-тест передачи conflict details.

Настоящий two-connection concurrency test и проверка атомарности catalog
binding остаются отдельными hardening-задачами. Текущая latest-wins формулировка
в RuleSpace plan требует отдельного обновления canonical-документа.

## P1 — сначала исправить или принять отдельным решением

### REV-FE-004 — единый Formula evaluator

Вынести или использовать один владелец оценки Formula для runtime и editor.
Добавить parity tests для всех поддерживаемых вариантов.

### REV-RULE-001 — закрыть Formula editor capability gap

Добавить в `FormulaInput` полноценные create/edit controls для всех Formula,
которые понимает DTO/runtime (`parameter`, `parameter_floor_div`,
`characteristic_size`, `characteristic_size_gap`), и parity tests:
create → serialize → view → runtime.

### REV-FE-001 — вынести combat/action domain logic из Vue

Сначала зафиксировать публичный headless-контракт, затем переносить
eligibility, requirements, costs, modifiers и process resolution в Game
services. Не дублировать orchestration между dialogs.

### REV-FE-002 — убрать silent async fallbacks

Для загрузок в launch dialogs определить F17 state model: loading, error,
retry и корректное поведение при неполном контексте.

### REV-PHP-001 — исправить Dto → Service dependency

Убрать создание Service из `RuleSpaceCatalog` и вернуть Dto к
definition/value responsibility.

## P2 — после стабилизации владельцев и контрактов

### REV-CON-002 — generation guard для RuleSpace context — выполнено

Реализовано в `useSpaceContextResolve`: generation и отдельный
`AbortController` делают старый route request неактуальным даже если
транспорт не уважает abort. Добавлены тесты поздних ответов/ошибок, retry,
смены draft/revision и unmount. Отдельная hardening-задача по записи
`revisionsMeta` вынесена за пределы finding.

### REV-CON-003 — сохранить backend error details во frontend contract — выполнено

Backend и frontend приведены к единому `error.details` envelope в коммите
`47b7f69`. Добавлены backend и frontend parity tests для details, включая
RuleSpace conflict и Character error parsing.

### REV-DATA-001/002 — атомарность Auth workflows

Обернуть регистрацию и password/session reset flows в транзакционные границы,
добавив rollback tests.

### REV-FE-005 — унифицировать source modifier aggregation

Сначала принять семантику `null`-источника, затем убрать локальные
агрегаторы или доказать различие контрактов тестами.

### REV-FE-006 — убрать неразрешённые combat hardcodes

Централизовать state codes, endurance concept и mastery fallback либо
зафиксировать их как data/spec contracts с тестами.

### REV-RULE-002/003 — восстановить rule editor/view parity

- дать editor и detail view доступ к `mechanicPayload`, включая typed
  payload controls либо явно документированный generic inspector;
- отображать `CharacteristicSpec.formula` через общий label service;
- добавить tests на mechanic payload round-trip и на каждую Formula type card.

### REV-MAGIC-001 — убрать concrete spell ability coupling

Зафиксировать relationship contract для spell efficiency/saturation/cost и
получать его из rule/mechanic spec. Если concrete codes являются сознательной
частью первого slice, вынести их в versioned capability/spec boundary и
проверить behavior при другой revision.

### REV-UI-001/002/003 — исправить edit affordances и async lifecycle

- permission/ownership guards на edit UI;
- ожидание async save;
- F17 load/error/retry в NPC editor.

## P3 — после P1/P2

- вынести named types и static constants из Vue;
- заменить JSON clone в mocks;
- не включать generic VersionRecord field bag в исправления без отдельного
  решения о смене generic Versioning contract;
- устранить дублирование derived formula parsing;
- унифицировать advantage/disadvantage implementation;
- добавить targeted tests для UI affordances и save lifecycle;
- добавить concurrency tests для Character/Rule/SmartTable;
- добавить mock/real AbortSignal и API parity tests;
- проверить оставшиеся duplication candidates после AST-aware анализа.

## Coordinator checkpoint — 2026-09-26 stage 2

Уточнены статусы по bounded read-only pass:

- `REV-FE-004` подтверждён как `P1 / CODE_GAP / HIGH`: локальные Formula
  evaluator-ы не покрывают все варианты общего evaluator-а.
- `REV-FE-005` подтверждён как `P2 / CODE_GAP / HIGH` для concrete divergence;
  политика `null`-источника остаётся `OPEN`.
- `REV-CON-001` остаётся `DECIDED / CODE_GAP / HIGH` относительно этого
  queue: ожидаемая ревизия и CAS обязательны. Канонический
  `rulespace-plan-04.md` всё ещё описывает latest-wins; finding остаётся
  незакрытым до отдельной задачи по обновлению канона и реализации.
- `REV-BE-001` подтверждён как `REQUIREMENT / NOT_IMPLEMENTED`, не как
  неожиданный дефект: frontend mocks и `IGameApi` не доказывают PHP Game.
- Character readiness: actual storage и RuleSpace slice — `PARTIAL`/узкий
  `IMPLEMENTED`; authoritative validation/save, membership, approved snapshot,
  overlay, moderation и migration — `CODE_GAP` или `REQUIREMENT`.
- `REV-UI-001/002/003` подтверждены статически как `P2` с высокой
  уверенностью. Component-тесты edit permissions, duplicate save и NPC load
  failure отсутствуют.

Ограничения стадии: targeted Vitest не дал bounded pass/fail и классифицирован
как `TIMED_OUT`; PHPUnit/full frontend diagnostics остаются
`BLOCKED_BY_ENVIRONMENT`/неподтверждёнными. Production code, tests,
configuration, schema и canonical TR не изменялись.

## Security/concurrency checkpoint — 2026-09-26

Добавлены в coordinator evidence следующие незакрытые зоны:

- `REV-CON-002` — generation guard для RuleSpace context реализован и закрыт;
  hardening записи `revisionsMeta` остаётся отдельной задачей.
- `REV-DATA-001/002` — общие transaction boundaries и rollback tests для Auth
  остаются `CODE_GAP / HIGH`.
- Character owner actor binding, связь `rulesRevision` с `space_id`, DB CAS и
  stale-response guard остаются readiness `CODE_GAP` до появления публичного
  API.
- RuleSpace idempotency/retry contract — `REQUIREMENT / OPEN`; exact retry
  identity не подтверждён.
- Object/actor authorization и SmartTable root transaction — `IMPLEMENTED`
  статически, но MySQL-backed tests `SKIPPED`/`BLOCKED_BY_ENVIRONMENT`.
- CSRF для публичных registration/password-reset routes — `OPEN` contract
  question, поскольку explicit `csrf: false` найдено в текущем config; это не
  классифицировано как подтверждённая security vulnerability без требования.

Не добавлялись remediation changes. Не проверены две независимые DB
connections, rollback injection, idempotency retry и Game security boundary.

## Data integrity/performance checkpoint — 2026-09-27

Добавлены в coordinator evidence следующие findings:

- `REV-DATA-003` — отклонён как false-positive для текущего scope:
  versioned snapshot invariants и application-owned validation пока
  принимаются без дополнительного DB-level enforcement;
- `REV-DATA-004` — `P2 / DATA_ERROR`: versioned catalog composition
  допускает формально валидные, но доменно несобираемые cross-code/tree
  состояния при обходе application writer;
- `REV-DATA-005` — `P2 / ARCHITECTURE_ERROR`: schema setup не является
  версионированной migration/backfill/rollback policy;
- `REV-PERF-001` — `P2 / PERFORMANCE_ERROR`: catalog placements читаются
  отдельным SQL-запросом на каждую секцию;
- `REV-PERF-002` — `P2 / PERFORMANCE_ERROR`: GameChatTab делает отдельный
  Character request на каждый membership;
- `REV-PERF-003` — `P3 / PERFORMANCE_ERROR`: combat/read models повторяют
  линейные `rules.find()` вместо общего indexed context.

Vertical checkpoint:

- Character — `PARTIAL / BACKEND_OPEN`;
- Game/session/combat — `REQUIREMENT / NOT_IMPLEMENTED`;
- NPC read-only — `PARTIAL / UI_GAP`, исходный просмотр без боя уже возможен;
- Rule/RuleSpace — `PARTIAL / CODE_GAP`, sequential happy path есть, но
  Formula/editor/view parity, CAS и catalog performance не закрыты.

Production fixes, schema changes and test changes по-прежнему запрещены
рамками этой кампании.

## Vertical-agent checkpoint — 2026-09-27

Независимые read-only проходы по вертикалям подтвердили и уточнили:

- Character: frontend/mock path есть, PHP public actions отсутствуют;
  backend storage optimistic guard не образует сквозной HTTP contract;
- Game: frontend/mock path есть, PHP Game не реализован; visibility,
  eligibility и atomic mutation policies требуют server-side boundary;
- NPC: просмотр через game tab без боя уже возможен, но GM/moderation
  read-only preview surface отсутствует (`REV-UI-004`);
- Rule: `mechanicPayload` сохраняется при edit → draft → publish
  (`REV-RULE-004` resolved); полноценный editor/view surface всё ещё
  отсутствует (`REV-RULE-002`);
- public-game `all` visibility соответствует принятому контракту;
  frontend/mock GM resolver не считается текущей vulnerability, но при
  реализации backend Game должен быть заменён на проверку membership
  конкретной игры (`REV-BE-001`);
- permission-aware chat/chronicle entity resolution не выделяется в отдельную
  задачу: это часть отсутствующего backend Game boundary (`REV-BE-001`);
- session/speaker eligibility используется непоследовательно
  (`REV-GAME-001`, `P2 / CONTRACT_ERROR`);
- последовательные stop-session и settings mutations не имеют общей
  operation boundary (`REV-GAME-002`, `P2 / CONCURRENCY_ERROR`).

Агенты не редактировали файлы. Их результаты должны пройти общий
deduplication/evidence-registry pass; повторное совпадение с уже имеющимися
finding не считать новой проблемой.

## Prioritized remediation queue — after final coordinator pass

Эта очередь описывает будущие отдельные задачи. Она не разрешает выполнять
их в рамках текущей read-only кампании.

### Gate A — решения и authoritative boundaries

1. Принять `DEC-REVIEW-001..010`, прежде всего:
   RuleSpace CAS, Character public boundary, Game ownership,
   RuleType unknown-data policy, CSRF и migration/idempotency policies.
2. Синхронизировать canonical docs с принятыми решениями отдельным reviewable
   изменением; не оставлять latest-wins wording рядом с CAS contract.
3. Зафиксировать Character owner/revision/actual-version contract
   (`REV-CHAR-001..003`) и Game state ownership (`REV-BE-001`).
4. Зафиксировать security matrix для owner, GM, member, observer, moderator,
   NPC proposal и public game в рамках `REV-BE-001`; явно записать, что observer
   публичной игры может видеть `all`-данные согласно visibility листа, а GM
   определяется только в контексте конкретной игры (`REV-BE-001`).

### Gate B — P1 до реализации backend Game

5. Исправить Formula owner/editor parity:
   `REV-FE-004`, `REV-RULE-001`; добавить exhaustive Formula parity tests.
6. Добавить typed/generic editor and view для `REV-RULE-002`.
   Backend payload validation обязательна, но относится к отдельной более
   поздней backend Rule contract task.
7. Вынести combat/action authoritative logic из Vue
   (`REV-FE-001`) и убрать silent async fallbacks (`REV-FE-002`).
8. Реализовать Character public actions only after Gate A:
   actor binding, revision lookup, server validation, expectedVersion,
   ACL and HTTP round-trip tests.
9. Реализовать Game module only after explicit boundaries:
    membership/approved snapshot, overlay/session transaction, NPC,
    moderation, chat attachments, combat resolver, chronicle and economy.

### Gate C — P2 correctness and maintainability

10. Унифицировать source modifier aggregation and decide null semantics
    (`REV-FE-005`).
11. Свести duplicated combat orchestration
    (`REV-FE-003`) после фиксации общего headless contract.
12. Устранить or spec-bound combat/magic hardcodes
    (`REV-FE-006`, `REV-MAGIC-001`).
13. Add RuleSpace expected revision/CAS and conflict details
    (`REV-CON-001`, `REV-CON-003`), then generation guards
    (`REV-CON-002`, `REV-CHAR-004`).
14. Make Auth registration atomic (`REV-DATA-001`) and password/session
    reset atomic (`REV-DATA-002`) with
    failure-injection tests.
15. Decide and enforce Character revision binding
    (`REV-CHAR-002`) and DB lost-update semantics (`REV-CHAR-003`).
16. Decide and enforce catalog/snapshot integrity
    (`REV-DATA-003/004`) and create migration policy (`REV-DATA-005`).
17. Add NPC GM/moderation read-only preview and complete load/error/retry
    lifecycle (`REV-UI-003/004`); align edit affordances/save lifecycle
    (`REV-UI-001/002`).
   `REV-UI-002` specifically requires awaiting the host save operation before
   clearing `saving` and a duplicate-click test.
18. Preserve the rejected intentional generic contract (`REV-PHP-002`) without
    reopening it.

### Gate D — performance and evidence closure

20. Batch catalog placements (`REV-PERF-001`) and Character reads in chat
    (`REV-PERF-002`).
21. Measure worst-case rule/catalog/combat sizes; only then optimize indexed
    rule context (`REV-PERF-003`).
22. Add two-connection CAS, rollback, idempotency/retry, permission,
    editor-view-runtime, mock/real API and vertical scenario tests.
23. Re-run diagnostics in an environment with frontend toolchain and MySQL,
    recording bounded pass/fail rather than inferring green status.

`REV-SEC-003` remains an open decision in
`codebase-review-2026-09-open-decisions.md`; `REV-PHP-002` is explicitly
rejected and has no remediation task.

### Closure rule

Finding closes only when its criterion in
`var/review/finding-registry.json` is met, evidence is attached, relevant
tests pass and the decision/owner is recorded. No finding closes merely
because a frontend mock or a sequential unit test passes.
