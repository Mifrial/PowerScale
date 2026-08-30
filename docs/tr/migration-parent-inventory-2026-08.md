# Inventory parent claims ТР

**Статус:** generated inventory for the full traceability pass, 2026-08-30.

Источник формулировок — `migration-claims.md`; parent records сохраняются для обратной трассировки, atomic children являются рабочими claims.

Всего parent claims: `56`.

Текущий atomic inventory: `94` child claims. Рабочие статусы находятся в [`migration-claims-status.md`](migration-claims-status.md), а evidence — в [`migration-evidence-inventory-2026-08.md`](migration-evidence-inventory-2026-08.md).

- `CLM-001` [33–51] — глобальный rule identity, immutable `code`, версия правила `(rule_global_id, space_id, created_at)`
- `CLM-002` [52–60] — snapshot-copy inheritance
- `CLM-003` [61–75] — publish diff and marker-version deletion
- `CLM-004` [78–101] — ModuleManager, lazy modules, module config, ServiceLocator, string codes, aliases and factories
- `CLM-005` [102–111] — SmartTable fields, references, `multiple`, hydrators, repository access, query/transaction boundaries
- `CLM-006` [112–311] — frontend anatomy, layers, stores, services, module DAG and Chat plugins
- `CLM-007` [322–336] — files owner, original name, MIME, size, path and access
- `CLM-008` [337–357] — authentication/session entities and password reset tokens
- `CLM-009` [358–399] — users, groups, profile fields, super-admin and group membership
- `CLM-010` [877–966] — permission key catalog and permission scopes
- `CLM-011` [967–1036] — owner/member/role/effective permission algorithm and batch checks
- `CLM-012` [2292, 3668] — deactivation UI route versus dialog
- `CLM-013` [400–479] — spaces, rules, rule versions and revision snapshot
- `CLM-014` [480–505] — Rule Sets and game rule-set links
- `CLM-015` [1039–1081] — `SpaceRevision`, numeric scoped revision and immutable `publishedAt`
- `CLM-016` [1082–1104] — draft state, persistence and stale revision context
- `CLM-017` [1105–1227] — URL context, effective rules, diff, selected publication, removed codes and commit flow
- `CLM-018` [507–548] — nation/race, species, languages and writing systems
- `CLM-019` [1230–1316] — simple rules and dimensional values, normalization, floor and arithmetic
- `CLM-020` [1317–1358] — race/species and characteristic contracts
- `CLM-021` [1359–1395] — resources, limits, adjustments and points
- `CLM-022` [1396–1516] — ability levels, groups, zones, requirements, grants and references
- `CLM-023` [1517–1595] — items, prices, equipment profiles, weapons, armor, shields and modifiers
- `CLM-024` [1596–1627] — damage types, forms, sources, stacking and state interaction
- `CLM-025` [1628–1680] — keywords, specifications, formula conversion and editor pruning
- `CLM-026` [1230–1680] — validation of rule references and type-specific forms
- `CLM-027` [1691–1728] — race, characteristic points, personality, development and start purchase
- `CLM-028` [1729–1754] — save draft, ready state, post-creation and in-game editing
- `CLM-029` [1755–1770] — modifiers and character states
- `CLM-030` [1784–1837] — calculations, filters, copy-on-write, points, attack values and weapon ownership
- `CLM-031` [623–696] — character versions, inventory, membership fields and history
- `CLM-032` [1838–1851] — character routes, visibility, versions, migration and deactivate actions
- `CLM-033` [549–622] — game fields, limits, invitations, members and permissions
- `CLM-034` [1856–1887] — game lifecycle, visibility, joining and roles
- `CLM-035` [1888–1937] — object permissions, Game tabs, NPC and game chat
- `CLM-036` [1938–1946] — GameTime and chronicle
- `CLM-037` [1929–1980] — initiative, actions, combat flow, known bugs and unfinished features
- `CLM-038` EXTRA vs [258–276]; contract source CODE/DEC-012 — wide attack
- `CLM-039` EXTRA vs [270–300]; contract source CODE/DEC-029 — ActionEffect, DOT, movement
- `CLM-040` [1517–1595, 1721–1728] — item cost, start budget, equipment and modifiers
- `CLM-041` [1947–1980] — loot, inventory operations, discard, trade and GM permissions
- `CLM-042` [313–380, 549–696] — money fields, game store and membership economy state
- `CLM-043` [313–380, 1947–1980] — shop positions, buy/sell price, stock and game-scoped stores
- `CLM-044` EXTRA vs [1947–1980]; contract source DEC-047—050 — EconomyOperation/journal
- `CLM-045` [723–775, 1983–2060] — chat entities, message fields, attachments, ownership and unread state
- `CLM-046` [2083–2135] — `/roll` syntax, efficiency, advantage/disadvantage, labels and result payload
- `CLM-047` [2136–2162] — macros, stored rolls and variable advantage
- `CLM-048` [2163–2227] — polling/SSE sync, `since`, heartbeat, reconnect, ordering and delivery semantics
- `CLM-049` EXTRA vs [2228–2235] as Chat contract; loot UI owner Game, Chat stays plugin host
- `CLM-050` [697–722, 2236–2250] — notification templates, buttons, event types, deduplication and unread UI
- `CLM-051` [2251–2388] — route catalog, guest screens, layout, filters, editor panels and expansion state
- `CLM-052` [2421–3253] — implementation waves and their acceptance criteria
- `CLM-053` [3258–3526] — frontend decisions: cookies, CSRF, batch, error handling, abort, debounce, virtualization, draft and Chat plugins
- `CLM-054` [3527–3545] — deferred features and known gaps
- `CLM-055` [3546–3697] — old summary of decisions
- `CLM-056` [3698–3748] — first-release and post-verification queues
