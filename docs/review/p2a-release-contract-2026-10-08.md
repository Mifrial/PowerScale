# P2a release contract и acceptance matrix

Дата: 2026-10-08

Документ фиксирует финальный P2a contract. Он не расширяет scope до P2b.

## A. Frozen contract

### A.1. Conflict

- Entity-stale conflict возвращает единый payload:
  `{currentVersion, choices, sheet}`.
- `sheet` всегда проходит существующий projection/visibility path.
- Закрытые `resources` никогда не попадают в payload.
- Raw current sheet запрещён во всех exception constructors и wrappers.
- Character, NPC, single strike и wide strike используют один projection path.
- Replay, idempotency-key и battle-version conflict могут не содержать `sheet`,
  если это не entity-stale conflict.

Принятый ранее P1 использует имя `currentSheet` и форму
`currentSheet: {choices, sheet}`. Это конкретное расхождение с финальным
P2a contract. При реализации P2a оно нормализуется к payload выше; третий
формат не вводится.

### A.2. Backfill

- Character owner/API:
  `ICharacterActualMutations::backfill(characterId, expectedActualVersion)`.
- NPC owner/API:
  `GameNpcs::backfill(record, game, expectedActualVersion)`.
- Typed result содержит актуальную typed record и статус:
  `initialized | changed | noop`.
- Новый row получает effective limit.
- Существующий current сохраняется; при уменьшении limit выполняется clamp.
- Рост limit не пополняет current.
- Backfill идемпотентен и не дублирует rows.
- Duplicate, unknown, malformed и shape-mismatch rows отклоняются.
- Backfill использует entity CAS и отдельную atomic transaction boundary.
- Stale CAS возвращает conflict; silent merge запрещён.
- Ошибка оставляет документ без частичной записи.

Текущий Character result документирует дополнительный `clamped`, но код
возвращает `changed`. Финальный статус `clamped` исключает.

Character и NPC могут иметь разные record-типы, но status/result API обязан
иметь одинаковую typed семантику. Различие конкретных entity record не является
contract blocker.

### A.3. P2b boundary

В P2a не входят и не считаются реализованными:

- action resolver;
- resource spend;
- `min_resource_cost`;
- разрешение `ChosenAmount`;
- combat AP orchestration.

Наличие существующих parser/DTO для `ChosenAmount` или
`min_action_cost` само по себе не является реализацией P2b.

## B. Acceptance matrix

### B.1. Conflict

- Character raw stale conflict — MySQL: да. PASS: `currentVersion`,
  projected `choices` и projected `sheet`; raw/hidden resources отсутствуют.
- Economy wrapper — MySQL: да. PASS: entity-stale payload сохраняет ту же
  projection semantics; shop/key conflict не получает fabricated sheet.
- Single stale — MySQL: да. PASS: top-level conflict, projection применён,
  roll/effect/close/replay mutation отсутствуют.
- Wide stale — MySQL: да. PASS: top-level conflict с target identity и
  projected payload; partial mutation отсутствует.
- Replay/battle-version conflict without sheet — MySQL: нет. PASS: sheet
  отсутствует.
- Visibility hidden/open resource — MySQL: нет. PASS: hidden `resources`
  отсутствуют, явно открытые `resources` присутствуют.

### B.2. Backfill

- Character initialized/changed/noop — MySQL: да. PASS: точный frozen status
  и ожидаемые persisted rows.
- NPC initialized/changed/noop — MySQL: да. PASS: те же status/result
  semantics, что у Character.
- Preserve-current — MySQL: нет. PASS: рост limit не меняет current.
- Clamp — MySQL: нет. PASS: current уменьшается только до effective limit.
- Idempotent second call — MySQL: да. PASS: `noop`, нет duplicate rows и
  version bump.
- Stale CAS — MySQL: да. PASS: conflict, нет записи и silent merge.
- Rollback — MySQL: да. PASS: partial resource/sheet mutation отсутствует.
- Character/NPC parity — MySQL: да. PASS: одинаковые status, preserve,
  clamp, CAS и rollback semantics.

## C. Scope classification

### C.1. Already accepted P1

- Generic SmartTable conditional CAS и fresh conflict reads.
- Character/NPC `actual_version` writes.
- P1 single/wide stale handling, transactional behavior и replay boundary.
- Связанные P1 exception, repository и MySQL test changes.

Наличие принятого P1 в рабочем дереве не является P2a blocker.

### C.2. P2a storage

- Typed resource value/row DTOs.
- Native scalar/dimensional parsing и serialization.
- Effective-limit calculation, preserve-current и clamp.
- Character resource assembly/backfill.
- NPC resource backfill и CAS replacement.
- Resource visibility coverage.

### C.3. P2a remediation

- Нормализация `currentSheet` к frozen payload
  `{currentVersion, choices, sheet}`.
- Projection во всех Character/NPC entity-stale construction/wrapping paths.
- Отсутствие sheet в non-entity-stale replay/battle-version conflicts.
- Нормализация Character/NPC backfill status/result semantics.
- Acceptance coverage из раздела B.

### C.4. Посторонние изменения

- Review/audit/plan documents и generated review artifacts.
- `tools/review/__pycache__`.
- Battleground stress JSON files.

Они не являются P2a implementation scope и не изменяются в рамках release.

## D. Remaining implementation changes

- Применить frozen conflict envelope во всех перечисленных construction и
  wrapping paths.
- Удалить raw current sheet из exception contract.
- Использовать общий projection path для single и wide strike.
- Убрать публичное состояние `clamped` из backfill contract.
- Привести Character/NPC typed result semantics к parity.
- Добавить обязательные acceptance tests из раздела B.

## E. Environment-only limitations

- До implementation session тесты и MySQL checks не запускались.
- Frontend, full suite и долгие MySQL tests не запускались.
- Код, тесты, документы, roadmap, readiness и status в рамках фиксации
  contract не изменялись.
- Existing untracked files сохранены.

## F. Final verdict

**READY FOR ONE IMPLEMENTATION SESSION**
