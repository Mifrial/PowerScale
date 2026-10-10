# Read-only reconciliation audit

Дата: 2026-10-08

Проверены `AGENTS.md`, канон `docs/tr/*`, audit-6..10, упомянутые P3/P5,
актуальный PHP backend. PHPUnit/MySQL, destructive tests и frontend
dev-server не запускались. Рабочее дерево не изменялось, существующие
untracked сохранены.

## A. Расхождения

| Область | Расхождение | Вывод |
| --- | --- | --- |
| P1 CAS | `TableRows::update()` делает обычный update; `CharacterRepository::writeGuarded()` и `GameNpcRepository::replaceVersion()` используют `SELECT → PHP check → UPDATE` | Conditional DB CAS ещё отсутствует |
| Conflict payload | `CharacterConflictException`, `GameEconomyConflictException`, `GameBattleConflictException` возвращают только `currentVersion` | Нужно добавить свежий `currentSheet` |
| Wide stale | P3, P5, P9, P10 и текущий `GameWideStrikeResults::refusal()` допускают target-level continuation | Противоречит обязательному whole-close conflict |
| Wide rollback | Транзакция уже существует, но stale превращается в обычный target result и close коммитится | Stale должен выбрасывать conflict до close; mutation-time conflict — откатывать всё |
| Replay | Сохранённый same-key result возвращается без повторного roll/effect | Базовая replay-семантика есть; нужна проверка конкурентного same-key execution |
| AP Rule | Код парсит `AbilityActionSpec`, `combat_action`, `ResourceComponent`; Game проверяет только live code | Action Rule `block` и AP component ещё не разрешаются |
| AP storage | В Character есть `choices` и `sheet`, в NPC — `version`; текущего AP/resource value нет | `sheet.resources` в audit-7 не подтверждён кодом |
| Cost `2` | В PHP-константе не найден; `WeaponBlock::minActionCost` и `MinActionCostOp` существуют, но merge modifier не реализован | `2` допустимо только как content/test fixture |
| Block | Есть `hit_check`, `BlockProfile`, projection; defender roll отсутствует | P3 остаётся незакрытым |
| Efficiency | `BlockProfile::efficiency` существует, но defender path его не использует; `GameCheckRoll` задаёт efficiency `3` | Precedence profile → CheckSpec → payload не доказан |
| Item identity | Transport содержит `itemRuleCode`/`blockItemRuleCode`, но не inventory instance id | P3 и P9 пересекаются; нужен единый effective-item context |
| Penetration | `WeaponProfile` парсит penetration, Game его не вычисляет | P9 действительно нужен |
| Penetration JSON | P9 добавляет `penetration`, `effectivePenetration`, `rawResistance` | Противоречит требованию не расширять JSON без решения |
| Reliability | Marker/API есть, production handler и registry binding отсутствуют | P5/P10 не завершены |
| Reliability binding | `RuleVersionBody` валидирует вход, но `RuleVersionRecord` и `GameStrikeLayers::bindings()` допускают silent drop malformed rows | Runtime должен fail-closed |
| Live content | Есть `IMechanics::add()` и `IRuleSpaces::commit()`, но production catalog row и опубликованный damage type binding не найдены | Нужен отдельный content prerequisite |

### Уточнённый CAS/currentSheet контракт

Минимальный retryable payload:

```text
currentVersion: int
currentSheet: {
  choices: map,
  sheet: map
}
target: optional wide-target identity
```

Это должно быть единообразно для Character и NPC:

- Character: `CharacterRecord::getChoices()` + `getSheet()`;
- NPC: `GameNpcRecord::getVersion()['choices']` + `['sheet']`.

Только `sheet` недостаточен: inventory instance, modifiers и часть combat
context находятся в `choices`. DB metadata и полный row record в
`currentSheet` не входят.

SmartTable primitive должен быть концептуально таким:

```text
conditionalUpdate(table, rowId, casField, expectedVersion, scalarAndMultipleValues): bool
```

- SQL содержит `WHERE id = ? AND casField = expectedVersion`;
- CAS field записывается как `expectedVersion + 1`, не принимается из payload;
- `false` означает mismatch/not found и не изменяет sidecar;
- multiple/sidecar fields обновляются в той же внешней транзакции после
  успешного SQL CAS;
- conflict read выполняется свежим `getById(..., cacheTtl: null)` с полной
  hydration;
- Character/NPC repository после `false` маппит свежие
  `currentVersion/currentSheet`.

## B. Dependency order

Циклов нет:

1. **Prerequisites/решения**
   - authoritative AP representation;
   - block efficiency precedence;
   - live reliability damage type и content owner;
   - форма auto-ignore без нового публичного поля.
2. **P1: common conditional CAS**
   - SmartTable primitive;
   - Character/NPC CAS;
   - unified conflict envelope;
   - whole-close wide conflict и rollback;
   - replay/concurrent same-key semantics.
3. **Shared combat item context**
   - выбранный `itemInventoryId`;
   - effective item после modifiers;
   - Character/NPC parity.
4. **P2: AP/action cost**
   - live action Rule с `combat_action=block`;
   - cost из action components;
   - authoritative resource read/spend;
   - eligibility и atomic spend.
5. **P3: defender/block**
   - attacker/defender roles;
   - mastery;
   - defender roll;
   - block success/failure/autofail;
   - 1→N independent defender rolls.
6. **P10: reliability production path**
   - handler, registry;
   - strict binding validation;
   - catalog row и published Rule binding.
7. **P9: penetration/resistance integration**
   - damage/penetration evaluation;
   - raw/effective resistance internally;
   - existing JSON only;
   - common whole-close stale semantics.
8. **Integrated acceptance**
   - Character/NPC, 1→1/1→N, replay, stale, rollback, parity.

## C. Consolidated plans and exclusive owners

| Owner | Ответственность | Файлы |
| --- | --- | --- |
| P1 / SmartTable CAS | Conditional update и sidecar atomicity | `Core/SmartTable/Service/Query/TableRows.php`, `OpenedRecords.php`, `IOpenedRecords.php` |
| P1 / Character-NPC CAS | Actual version и conflict details | `CharacterRepository.php`, `CharacterConflictException.php`, `GameNpcRepository.php` |
| P1 / combat transaction | Whole-close, rollback, replay, conflict mapping | `GameStrikeSheetWrites.php`, `GameStrikes.php`, `GameWideStrikes.php`, `GameWideStrikeResults.php`, `GameBattleConflictException.php` |
| P2 / Rule+resource | Action Rule resolver, AP component, min-cost merge, resource mutation adapters | `AbilityActionSpec.php`, `ActionComponents.php`, `ItemModifierOperationItems.php`, новый cost/resource service и соответствующие resource mutation interfaces |
| P3 / transport+block | Inventory IDs в transport/opening, hit-check, mastery, defender roll | `GameStrikeBody.php`, `GameWideStrikeBody.php`, opening/table DTOs, `GameCheckRoll.php`, новый mastery port/DTO |
| P9 / effective item+resistance | Единый effective-item context и penetration arithmetic | новый `CharacterCombatItemContext` port/service, `CharacterCombatItemLayers.php`, `CharacterCombatLayers.php`, `GameStrikeLayers.php`, `GameStrikeAmounts.php` |
| P10 / reliability | Production capability и fail-closed binding | новый `ReliabilityCutHandler.php`, `MechanicPortFactory.php`, `RuleVersionRecord.php`, strict binding validator |

P2/P3/P9/P10 не должны отдельно изменять центральную stale/transaction
orchestration. Она принадлежит P1.

## D. Acceptance-test matrix

| Срез | Acceptance |
| --- | --- |
| CAS | Matching version обновляет ровно одну строку и увеличивает version на 1 |
| CAS | Mismatch не пишет payload и возвращает свежие `currentVersion/currentSheet` |
| CAS | Character и NPC имеют одинаковую stale semantics |
| Wide | Stale любой цели оставляет весь close pending/open |
| Wide | Ни одна цель, reaction, battle version или command result не коммитятся |
| Wide rollback | Mutation-time conflict второй цели откатывает первую mutation |
| Replay | Same key/body возвращает сохранённый JSON без spend/effect/roll |
| Replay | Same key/different body даёт `GAME_CONFLICT` |
| AP | Action Rule живой revision имеет `type=action`, `combat_action=block`, ровно один AP component |
| AP | Cost читается из Rule revision; разные revisions могут иметь разные costs |
| AP | Несколько `min_action_cost` объединяются через `max` |
| AP | Shield без floor использует cost action Rule |
| AP | Client не передаёт current AP/resource |
| AP | Explicit ignore не списывает AP и не меняет authoritative sheet |
| AP | Confirmed current AP failure даёт internal auto-ignore; stale всегда conflict |
| Block | Ровно один live `hit_check`; zero/duplicate — `GAME_INVALID` |
| Block | Attacker autofail `0/-1` не читает defender profile/layers/reliability |
| Block | Block failure сохраняет attacker rating и не добавляет block layers |
| Block | Block success даёт `success=1` и добавляет block layers |
| Wide block | Один attacker roll и отдельный defender roll каждой цели |
| Item | Modifier выбранного inventory instance применяется один раз |
| Penetration | raw resistance `8`, penetration `4`, effective resistance `4` |
| Penetration | Damage `10` остаётся `10` до вычитания resistance |
| Penetration | Zero/negative penetration, floor-at-zero и `defense_ignored` |
| Penetration | В JSON остаются только существующие `damage`, `resistance`, `injury` и прочие текущие поля |
| Reliability | Capability определяется только через `IReliabilityCut` |
| Reliability | Missing catalog/handler/malformed binding — `GAME_INVALID`, не `false` |
| Reliability | `durability=null` остаётся применимым |
| Reliability | Character/NPC используют одинаковую projection semantics |
| Content | Опубликованный live damage type содержит валидный binding на production handler |

Матрица не запускалась согласно ограничениям задачи.

## E. Решения, которые нельзя додумывать

1. Где хранится текущий AP: существующее поле Character actual/NPC version
   либо новая модель. `sheet.resources` не подтверждён.
2. Инициализация/backfill AP для уже существующих Character/NPC.
3. Каноническое имя JSON action components: backend сейчас читает
   `components`, тогда как audit использует `action_components`. Второй
   параллельный формат вводить нельзя.
4. Precedence `BlockProfile.efficiency`, `CheckSpec.defaultEfficiency`,
   payload и roll modifiers.
5. Представление confirmed AP auto-ignore без добавления публичного
   `autoIgnored`.
6. Конкретный live `damage_type`, который включает reliability cut.
7. Production catalog code/version reliability handler и способ поставки
   опубликованного content.
8. Точная позиция reliability cut относительно damage-type filtering; код
   сейчас сначала выполняет `matches()`, затем `shaved()`, а P9 описывает
   другой порядок.
9. Полный eligibility block: strength, hands и прочие item constraints.

## F. Verdict

# BLOCKED

Точные prerequisites:

- authoritative AP storage/spend contract для Character и NPC;
- production reliability handler, registry entry, catalog row и published
  live damage-type binding;
- подтверждённая efficiency precedence defender roll;
- исправление P3/P5/P9/P10 с переходом на единый whole-close stale conflict;
- отдельное решение по auto-ignore JSON representation.

P1 CAS можно реализовывать независимо после принятия уточнённой формы
`currentSheet`, но весь консолидированный combat plan пока не approved for
implementation.
