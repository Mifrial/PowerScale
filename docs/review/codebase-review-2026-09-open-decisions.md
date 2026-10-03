# Open decisions после сквозного ревью — 2026-09

Документ содержит только вопросы, которые нельзя безопасно решить
статическим ревью. Это не разрешение менять production code. До отдельного
решения разработчика все пункты остаются открытыми.

## DEC-REVIEW-001 — RuleSpace publish concurrency — resolved

**Evidence:** `REV-CON-001`, `docs/tr/architecture.md:206-210`,
`docs/tr/rulespace-plan-04.md:83-90`.

Контракт подтверждён и реализован: клиент передаёт `expected/base revision`,
backend выполняет CAS внутри transaction, stale publish возвращает conflict
и не создаёт новую revision. Frontend сохраняет `expectedRevision` и
`actualRevision` в `ActionFailure.details`.

**Решение:** expected revision + CAS. Finding `REV-CON-001` закрыт
2026-10-01. Обновление противоположной latest-wins формулировки в canonical
RuleSpace plan — отдельная задача; two-connection concurrency test и
атомарность catalog binding также вынесены в отдельный hardening scope.

## DEC-REVIEW-002 — Null/source semantics для modifiers — resolved

**Evidence:** `REV-FE-005`,
`AggregateSourceDeltasService.ts`,
`CharacterOverviewService.ts`, `docs/tr/rule-system.md:160-174`.

**Решение 2026-10-01:** если source не указан, каждый такой modifier — свой
уникальный источник. Два `+1` без source дают `+2`, они не схлопываются.
Модификаторы с одним и тем же указанным source по-прежнему дают strongest
plus и strongest minus. Отсутствие source — дыра в данных или в правиле, но
пока от неё нельзя избавиться, и её нельзя молча склеивать.

Пачка 5 может идти: один агрегатор с этой семантикой. Текущий код делает
наоборот: все `null` — одна группа, overview складывает их в «прочее».

## DEC-REVIEW-003 — Data-driven boundary для concrete rule vocabulary

**Evidence:** `REV-FE-006`, `REV-MAGIC-001`,
`REV-CHAR-002`, `REV-BE-001`.

Нужно решить, какие identifiers являются допустимым versioned vocabulary
contract, а какие являются ошибочной привязкой production logic к конкретным
экземплярам правил. Особенно это касается spell ability/resource/action,
endurance и combat state codes.

**Рекомендуемое решение:** concrete identifiers разрешать только внутри
versioned spec/capability adapter, не в generic runtime service.

## DEC-REVIEW-004 — Character public boundary

**Evidence:** `REV-CHAR-001..004`,
`www/mifrial/modules/Roleplay/Character/tests/CharacterPortBootTest.php`.

Нужно определить public action contract: actor-derived owner, object
permissions, server-side version validation, `expectedVersion`, revision
binding, authoritative sheet validation and migration semantics.

**Рекомендуемое решение:** сначала contract/API decision, затем PHP actions и
integration tests; не переносить frontend mock input напрямую.

## DEC-REVIEW-005 — Game authoritative scope

**Evidence:** `REV-BE-001`, `REV-GAME-001/002`, `REV-SEC-002`.

Нужно решить владельцев membership, approved snapshot, overlay, session
commit, NPC state, chat attachments, combat resolution, chronicle ordering,
economy ledger, idempotency and battleground persistence.

**Рекомендуемое решение:** Game owns game-scoped state and permissions;
Character owns actual version; RuleSpace owns immutable revision; Chat owns
message/attachment transport and does not resolve domain permissions alone.
Public-game observers may see `all` data according to the sheet's own
visibility setting; a GM is resolved from membership in the concrete game and
not from frontend/mock data.

## DEC-REVIEW-006 — RuleType validation and unknown types

**Evidence:** `REV-RULE-001/002/004`,
`RuleVersionBody.php`, `RuleSpaceCommitDraftMapper.php`,
`RuleType.ts`.

**Decision recorded 2026-09-27:** existing `mechanicPayload` must be
preserved losslessly through edit/draft/publish. Backend validation of payload
data is mandatory, but is intentionally deferred to the later backend Rule
contract task; this decision does not authorize implementing it in the review
campaign.

Нужно определить, должен ли backend принимать unknown `type/spec` as generic
forward-compatible data, либо валидировать registry of supported RuleTypes.
В обоих случаях editor/view должен честно показывать unsupported status and
preserve data without silent loss.

**Рекомендуемое решение:** сохранить unknown payload losslessly, но не
заявлять runtime support; editor must use generic inspector or block unsafe
edit-save.

## DEC-REVIEW-007 — NPC read-only surface

**Evidence:** `REV-UI-004`,
`NpcsTab.vue`, `NpcCard.vue`, `NpcEditPage.vue`.

Нужно выбрать единый permission-aware read model для player, GM and moderator:
route/detail, modal or reusable card. Отдельно решить preview proposed NPC
до approve/reject и доступ к combat card в read-only режиме.

**Рекомендуемое решение:** общий read-only NPC sheet surface, а edit,
moderation and combat actions — отдельные capabilities.

## DEC-REVIEW-008 — CSRF exceptions — resolved

**Evidence:** `REV-SEC-003`, `CsrfGuard.php`, `AuthCookieIssuer.php`,
`OutgoingCookie` (`SameSite=Lax`, session cookie `HttpOnly`).

**Решение 2026-10-01:** код не менять. `csrf: false` остаётся на
неаутентифицированных auth-маршрутах: login, register, guest, password
policy и оба шага password reset. `csrf: true` остаётся на logout,
user create и set password.

Причина: сессионная cookie — `HttpOnly` и `SameSite=Lax`. Чужой сайт не
отправит её с POST, поэтому классический CSRF по уже открытой сессии закрыт
браузером. На маршрутах без сессии CSRF-токен не защищает от прямого вызова
сервера.

Переоткрыть, если cookie станет `SameSite=None` или мутирующий маршрут с
живой сессией получит `csrf: false`.

## DEC-REVIEW-009 — Schema migration policy

**Evidence:** `REV-DATA-005`.

Нужно решить, достаточно ли текущего `create/updateTable` setup для проекта
с immutable revisions, backfill and Game state, либо требуется versioned
migration ledger with rollback/compatibility policy.

**Рекомендуемое решение:** до Game schema зафиксировать migration version,
backfill, deploy ordering and rollback guarantees.

## DEC-REVIEW-010 — Idempotency and retry scope

**Evidence:** `REV-CON-001`, `REV-GAME-002`,
`var/review/checkpoint-2026-09-security-concurrency.json`.

Нужно определить, какие command endpoints retry-safe, где нужен idempotency
key, как обрабатывается timeout после commit и как client получает conflict
or already-applied result.

**Рекомендуемое решение:** command-specific idempotency policy before
membership, combat and economy writes.

