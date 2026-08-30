# Журнал reconciliation старого ТР — 2026-08

**Дата:** 2026-08-30  
**HEAD:** `3ea94daf7921b91abc7d78c29cf603f4a73c6da6`  
**Источник:** `docs/tr/history/TR-legacy-2026-08.md` (= `HEAD:docs/tr/TR.md`, 3748 строк)  
**Правило:** наличие claim не доказывает перенос. Фрагменты 33–3748 классифицированы ниже. Backend requirements не считаются frontend-реализацией. Классификация отражает решения Андрея от 2026-08-30.

Классы: `MIGRATED` | `REPLACED` | `HISTORICAL` | `DEFERRED` | `MISSING` | `CURRENT_EXTRA` | `CODE_GAP`.

## Known findings

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-001 | 78–111, 2421–2630 | architecture.md | MIGRATED | — | EventManager, error hierarchy, QueryBuilder, tagged cache — backend foundation | DECIDED 2026-08-30: сохранить как backend requirement; implementation OPEN |
| REC-002 | 1051–1053 | rule-system.md | MIGRATED | DEC-002 | snapshot-copy независимых пространств | DECIDED 2026-08-30: выполнять атомарной транзакцией-коммитом |
| REC-003 | 866–873 | auth-system.md | MIGRATED | — | group anti-escalation permission invariant | DECIDED 2026-08-30: требуется permission на назначение прав группам; назначаемые права ⊆ прав оператора; admin bypass неизменяем |
| REC-004 | 623–696 | data-model.md, character-system.md | MIGRATED | — | `owner_notes` есть в CharacterDetail/API/mock/tests; game notes — `(gameId, userId)` | DECIDED 2026-08-30: личные заметки персонажа и игры — текущий frontend-контракт; backend storage OPEN |
| REC-005 | 258–276 | game-system.md | CURRENT_EXTRA | DEC-012 | wide attack — код, не anatomy | APPLIED |
| REC-006 | 270–300 | game-system.md | CURRENT_EXTRA | DEC-029 | ActionEffect/DOT — код, не wave text | APPLIED |
| REC-007 | 1947–1980 | game-system.md | CURRENT_EXTRA | DEC-047–050 | EconomyOperation — decision, не chronicle | APPLIED |
| REC-008 | 2228–2235 | game-system.md / chat-system.md | CURRENT_EXTRA | DEC-003 | loot UI vs Chat plugin split | APPLIED |
| REC-009 | Game internals | architecture.md | CODE_GAP | DEC-013 | Game→Rule/Character internals | APPLIED |
| REC-010 | ChatSyncService catch | chat-system.md | CODE_GAP | — | silent catch vs error/retry | APPLIED |

## §1 Обзор (33–75)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S1-01 | 33–36 | TR.md | MIGRATED | — | назначение системы | APPLIED |
| REC-S1-02 | 37–46 | TR.md, domain docs | MIGRATED | DEC-006 | сущности; персонаж/игра к `(spaceId, revision)` | APPLIED |
| REC-S1-03 | 48–50 | history.md | REPLACED | DEC-006 | timestamp `created_at` selector | historical |
| REC-S1-04 | 52–59 | rule-system.md | MIGRATED | DEC-002 | snapshot-copy независимость | APPLIED |
| REC-S1-05 | 61–66 | rule-system.md | MIGRATED | DEC-015 | publish + diff; selective publish requirement | APPLIED |
| REC-S1-06 | 68–72 | rule-system.md | MIGRATED | DEC-006 | marker-version deletion | APPLIED |

## §2 Архитектура (76–311)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S2-01 | 78–83 | architecture.md | MIGRATED | — | ModuleManager two-level, lazy non-Core | REQUIREMENT backend / FE DAG current |
| REC-S2-02 | 85–100 | architecture.md | MIGRATED | — | ServiceLocator backend+frontend | FE current; BE OPEN |
| REC-S2-03 | 102–110 | architecture.md | MIGRATED | — | SmartTable CRUD/transactions | REQUIREMENT, not FE implemented |
| REC-S2-04 | 112–257 | architecture.md | MIGRATED | DEC-013 | Shell modules, anatomy | current; DAG CODE_GAP |
| REC-S2-05 | 258–276 | game-system.md | CURRENT_EXTRA | DEC-012 | anatomy text ≠ wide attack source | REC-005 |
| REC-S2-06 | 270–300 | game-system.md | CURRENT_EXTRA | DEC-029 | FE rules ≠ ActionEffect source | REC-006 |
| REC-S2-07 | 2421–2630 | architecture.md | MIGRATED | — | EventManager/QueryBuilder/cache backend requirements | REC-001 |

## §3 Схема БД (312–804)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S3-01 | 322–336 | data-model.md | MIGRATED | — | files storage | OPEN backend |
| REC-S3-02 | 337–399 | data-model.md, auth-system.md | MIGRATED | — | users/groups/permissions SQL | REQUIREMENT |
| REC-S3-03 | 400–479 | data-model.md, rule-system.md | MIGRATED | DEC-006, DEC-017 | spaces/rules; tags→keywords historical | APPLIED |
| REC-S3-04 | 480–506 | data-model.md, rule-system.md | MIGRATED | — | Rule Sets | OPEN |
| REC-S3-05 | 507–548 | data-model.md, rule-system.md | MIGRATED | — | nations/languages/writing | OPEN/content |
| REC-S3-06 | 549–622 | data-model.md, game-system.md | MIGRATED | DEC-006 | games; `rules_version_at` HISTORICAL/REQUIREMENT | APPLIED |
| REC-S3-07 | 623–640 | data-model.md, character-system.md | MIGRATED | — | characters fields | APPLIED |
| REC-S3-08 | 634 | character-system.md | MIGRATED | — | owner_notes frontend/API contract; storage backend OPEN | REC-004 |
| REC-S3-09 | 641–696 | history.md | HISTORICAL | DEC-001 | active/pending/draft_json, character_moderation | not current |
| REC-S3-10 | 697–722 | data-model.md, ui-system.md | MIGRATED | — | notifications schema | generators OPEN |
| REC-S3-11 | 723–775 | data-model.md, chat-system.md | REPLACED | DEC-003 | `dice_result` historical; attachments current | APPLIED |
| REC-S3-12 | 776–804 | data-model.md, game-system.md | REPLACED | — | chronicle event_time/sort_order historical; GameTime current | APPLIED |

## §4 Auth (805–1036)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S4-01 | 807–865 | auth-system.md | MIGRATED | DEC-057 | registration, profile, deactivation dialog | APPLIED |
| REC-S4-02 | 866–875 | auth-system.md | MIGRATED | — | anti-escalation groups and immutable admin bypass | REC-003 |
| REC-S4-03 | 877–966 | auth-system.md | MIGRATED | DEC-017 | permission catalog keyword.* | APPLIED |
| REC-S4-04 | 967–1036 | auth-system.md | MIGRATED | — | algorithm, own vs other, batch | APPLIED; BE OPEN |

## §5 Пространства (1037–1227)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S5-01 | 1039–1049 | history.md / rule-system.md | REPLACED | DEC-006 | A.B.C rule version vs space revision | revision current; A.B.C historical as space selector |
| REC-S5-02 | 1051–1053 | rule-system.md | MIGRATED | DEC-002 | independent snapshot | APPLIED |
| REC-S5-03 | 2976–2979 | history.md, rule-system.md | REPLACED | — | legacy async queue + progress UI execution strategy | REC-002: заменено атомарной transaction-коммитом без progress UI |
| REC-S5-04 | 1055–1081 | rule-system.md | MIGRATED | DEC-006 | SpaceRevision `(spaceId, revision)`, publishedAt | APPLIED |
| REC-S5-05 | 1082–1227 | rule-system.md | MIGRATED | DEC-015 | draft, URL context, publish, stores | APPLIED |

## §6 Правила (1228–1680)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S6-01 | 1230–1246 | rule-system.md | MIGRATED | — | mechanics | APPLIED / BE OPEN |
| REC-S6-02 | 1247–1270 | rule-system.md | MIGRATED | — | 19 RuleType axes | APPLIED |
| REC-S6-03 | 1271–1310 | rule-system.md | MIGRATED | — | DimensionalNumber | APPLIED |
| REC-S6-04 | 1311–1385 | rule-system.md | REPLACED | DEC-004/005 | resource `initial_value` historical; auto_add/limit current | APPLIED |
| REC-S6-05 | 1386–1516 | rule-system.md | MIGRATED | DEC-017 | ability; tags→keywords | APPLIED |
| REC-S6-06 | 1517–1627 | rule-system.md | MIGRATED | — | item/damage/source | APPLIED |
| REC-S6-07 | 1628–1651 | rule-system.md | MIGRATED | DEC-017/022 | keywords | APPLIED |
| REC-S6-08 | 1652–1680 | rule-system.md | MIGRATED | — | description/spec | APPLIED |
| REC-S6-09 | spell/magic | rule-system.md | DEFERRED | DEC-023/031–033/039/046/056 | magic | DEFERRED |

## §7 Персонажи (1681–1851)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S7-01 | 1681–1753 | character-system.md | MIGRATED | DEC-024/040 | creation, validation | APPLIED |
| REC-S7-02 | 1753–1816 | history.md | HISTORICAL | DEC-001/008 | active_json moderation model | not current |
| REC-S7-03 | 1755–1851 | character-system.md | MIGRATED | DEC-016/036–038/051–052 | modifiers, states, A/L/O/P | APPLIED |

## §8 Игры (1852–1980)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S8-01 | 1852–1946 | game-system.md | MIGRATED | DEC-009/011/018 | lifecycle, tabs, NPC, overlay | APPLIED |
| REC-S8-02 | 1947–1980 | game-system.md | MIGRATED | DEC-026/030 | chronicle/GameTime notes | APPLIED |
| REC-S8-03 | Economy typed ops | game-system.md | CURRENT_EXTRA | DEC-047–050 | not this range | REC-007 |
| REC-S8-04 | combat extras | game-system.md | MIGRATED | DEC-012/029/034–035/041/044–045 | combat/DOT/wide | APPLIED from CODE |

## §9–10 Chat и уведомления (1981–2250)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S9-01 | 1981–2227 | chat-system.md | MIGRATED | DEC-003 | host, plugins, attachments, loading | APPLIED |
| REC-S9-02 | SSE | chat-system.md | MIGRATED | — | target protocol OPEN, not implemented | APPLIED |
| REC-S9-03 | silent catch | chat-system.md | CODE_GAP | — | error/retry target | REC-010 |
| REC-S9-04 | 2228–2235 | game-system.md | MIGRATED | — | loot UI принадлежит Game; это не Chat contract | REC-008 |
| REC-S10-01 | 2236–2250 | ui-system.md | MIGRATED | — | notification UI; generators OPEN | APPLIED |

## §11 UI (2251–2388)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S11-01 | 2251–2380 | ui-system.md | MIGRATED | DEC-020 | routes, shell, editors | APPLIED vs code routes |
| REC-S11-02 | cancelled footer/top menu | history.md | HISTORICAL | DEC-020 | not current layout | APPLIED |

## §12 Волны (2389–3253)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S12-01 | 2389–3253 | history.md | HISTORICAL | — | wave roadmap | not current domain contract |
| REC-S12-02 | surviving contracts inside waves | domain owners | MIGRATED | — | already in architecture/auth/rule/… | no wave-as-roadmap in current docs |

## §13 Frontend decisions (3254–3526)

| REC-ID | Legacy item | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-S13-00 | frontend-rules pointer | architecture.md | MIGRATED | — | AGENTS/frontend-rules | APPLIED |
| REC-S13-01 | ServiceLocator | architecture.md | MIGRATED | — | FE code | APPLIED |
| REC-S13-02 | httpOnly cookie | auth-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-03 | CSRF | architecture.md, auth-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-04 | password policy API | auth-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-05 | batch / markChatRead | chat-system.md, ui-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-06 | generic Row | ui-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-07 | error boundaries | ui-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-08 | AbortController/debounce | ui-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-09 | no double v-app | ui-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-10 | restore chats after login | chat-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-11 | virtualization | ui-system.md, chat-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-12 | per-object permissions | auth-system.md | MIGRATED | — | REQUIREMENT BE OPEN | APPLIED |
| REC-S13-13 | draft persistence | rule-system.md | MIGRATED | — | FE | APPLIED |
| REC-S13-14 | Chat plugins/inline | chat-system.md | MIGRATED | DEC-003 | FE | APPLIED |
| REC-S13-15 | unresolved backend in §13 | domain OPEN | MISSING | — | not implemented because it was in monolith | OPEN |

## Отложенное и сводка (3527–3697)

| REC-ID | Legacy range | Destination | Class | DEC | Evidence | Решение |
| --- | --- | --- | --- | --- | --- | --- |
| REC-D-01 | combat card page | game-system.md | MIGRATED | — | combat card current FE; separate page HISTORICAL | APPLIED |
| REC-D-02 | DiffViewer UI | history.md | DEFERRED | — | not current | DEFERRED |
| REC-D-03 | admin action journal | history.md | DEFERRED | — | not current | DEFERRED |
| REC-D-04 | runtime effects type | rule-system.md, game-system.md | MIGRATED | DEC-029 | ActionEffect partial current | PARTIAL |
| REC-D-05 | race_mix | history.md | DEFERRED | — | future | DEFERRED |
| REC-D-06 | spells | rule-system.md | DEFERRED | DEC-023… | magic | DEFERRED |
| REC-D-07 | bulk import AI | history.md | DEFERRED | — | | DEFERRED |
| REC-D-08 | auto notification triggers | ui-system.md | MISSING | — | generators OPEN | OPEN |
| REC-SUM | 3546–3697 | decisions.md, history.md | REPLACED | DEC-001–058 | superseded checklist not current | APPLIED |

## §14–15 Release queues (3698–3748)

| REC-ID | Item | Destination | Class | Evidence |
| --- | --- | --- | --- | --- |
| REC-Q-14-1 | modifier tails | rule-system.md | MIGRATED | keywords/flags current |
| REC-Q-14-2 | source advantages | rule-system.md | MIGRATED | max+/min− |
| REC-Q-14-3 | check type | rule-system.md, game-system.md | MIGRATED | RuleType check |
| REC-Q-14-4 | sheet inventory | character-system.md | MIGRATED | |
| REC-Q-14-5 | 3704–3708 | character-system.md, game-system.md | MIGRATED | — | character owner notes и game personal notes разделены по владельцу/игре | REC-004 |
| REC-Q-14-6 | combat card | game-system.md | MIGRATED | |
| REC-Q-15-1 | combat tail + DOT | game-system.md | MIGRATED | DEC-029; SSE still OPEN |
| REC-Q-15-2 | exhaustion stepper | game-system.md | MIGRATED | |
| REC-Q-15-3 | docs polish | history.md | HISTORICAL | this pass |
| REC-Q-15-4 | commit/push | history.md | HISTORICAL | |
| REC-Q-15-5 | frontend-rules | architecture.md | MIGRATED | |
| REC-Q-15-6 | DAG phase | architecture.md | CODE_GAP | DEC-013 |
| REC-Q-15-7–8 | module/global review | history.md | HISTORICAL | process |
| REC-Q-15-9 | revision import/export | rule-system.md | MIGRATED | FE |
| REC-Q-15-10 | notifications module | ui-system.md | MISSING | stub + generators OPEN |
| REC-Q-15-11 | 3724–3725 | architecture.md | DEFERRED | — | backend start remains a future work queue item |
| REC-Q-15-12 | content polish/magic | rule-system.md | DEFERRED | magic DEFERRED |
| REC-Q-15-13 | playtest pages | history.md | DEFERRED | process |
| REC-Q-15-14 | initiative UX | game-system.md | MIGRATED | |
| REC-Q-15-15 | automoderation empty diff | character-system.md | MIGRATED | |
| REC-Q-15-16 | click-attack | game-system.md | MIGRATED | |
| REC-Q-15-17–18 | moderation revision/resubmit | character-system.md, game-system.md | MIGRATED | |
| REC-Q-15-19 | chat folding | chat-system.md | MIGRATED | thread folding current FE |
| REC-Q-15-20 | AP in scenes | game-system.md | MIGRATED | |
| REC-Q-15-20.1 | wait/interrupt | game-system.md | MIGRATED | PARTIAL emergency |
| REC-Q-15-21 | natural weapons | rule-system.md, character-system.md | MIGRATED | |
| REC-Q-15-22 | NPC versioning | game-system.md | MIGRATED | |
| REC-Q-15-23 | attack reliability | game-system.md | MIGRATED | |
| REC-Q-15-24 | armor sets | rule-system.md, game-system.md | MIGRATED | |
| REC-Q-15-25 | chat folding threads | chat-system.md | MIGRATED | |
| REC-Q-15-26 | initiative all players | game-system.md | MIGRATED | |
| REC-Q-15-27–28 | rule editor/spec view | rule-system.md | MIGRATED | |
| REC-Q-15-29 | slider go-to-page | ui-system.md | DEFERRED | far queue |
| REC-Q-15-30 | page header vertical | ui-system.md | DEFERRED | queue |
| REC-Q-15-31 | ActionEffect | game-system.md | MIGRATED | DEC-029 PARTIAL |

## Coverage statement

Ranges 33–3748 are classified. Coverage is this journal, not claim count. Archive unmodified. Code unmodified.
