# Технические требования PowerScale

**Статус:** канонический индекс, 2026-08-30.

## Назначение

Этот файл задаёт карту текущего ТР. Подробные контракты разделены по доменам; старый монолитный текст и отменённые варианты находятся в [`history.md`](history.md).

## Приоритет источников

При расхождении используем следующий порядок:

1. код и тесты;
2. действующие правила frontend в `draft-front_1.2ds/frontend-rules.md`;
3. принятые решения в [`decisions.md`](decisions.md);
4. исторические спеки и старый текст ТР.

`OPEN` — контракт не подтверждён и не должен выдаваться за реализованный. `DEFERRED` — вопрос сознательно отложен. Ни код, ни спеки, ни этот индекс не изменяются автоматически из-за находки аудита.

## Канонические документы

- [Архитектура](architecture.md) — модули, таблица рёбер DAG, поверхность и CODE_GAP границ.
- [SmartTable](smarttable.md) — доступ к данным, Basic/Versioned, `DEC-078`.
- [Нарезка SmartTable](smarttable-roadmap.md) — планы реализации SmartTable.
- [План 1: соединение](smarttable-plan-01-connection.md) — Illuminate MySQL из `local.php`.
- [План 2: поля Basic](smarttable-plan-02-fields.md) — типы, getMap, hydrator-заготовка.
- [План 3: CRUD](smarttable-plan-03-crud.md) — open, строка, транзакция, DDL.
- [План 4: getList](smarttable-plan-04-getlist.md) — filter / sort / page / select.
- [План 5: multiple](smarttable-plan-05-multiple.md) — mfv и contains; reference отдельно.
- [План 5b: reference](smarttable-plan-05-reference.md) — INT+FK, restrict / setNull / none.
- [План 6: индексы](smarttable-plan-06-indexes.md) — indexed/unique из FieldSettings.
- [План 7a: force DDL](smarttable-plan-07-force-ddl.md) — forceUpdateTable / deleteTable.
- [План 7b: словарь](smarttable-plan-07-dictionary.md) — мета-таблицы и runtime DDL.
- [План 8: кэш](smarttable-plan-08-cache.md) — TTL getById/getList и теги после commit.
- [План 9: DDL vs прогон](smarttable-plan-09-migrations.md) — прогон модулей не в SmartTable.
- [План Kernel 1: setup](kernel-plan-01-setup.md) — установка/обновление модулей, граф `reference`, CLI.
- [План Kernel 2: DTO action](kernel-plan-02-action-input.md) — сделано; `IActionInput` в binder, Optional-поля.
- [План 10: ворота Basic](smarttable-plan-10-gates.md) — сверка канона v1, Basic закрыт.
- [План 13: DateTime now](smarttable-plan-13-datetime-now.md) — `default` sentinel «сейчас» на datetime.
- [План 14: путь reference](smarttable-plan-14-reference-path.md) — `reference` на `id` цели; путь в getList без JOIN.
- [План 15: BIGINT и cascade](smarttable-plan-15-bigint.md) — `IdField::big()`, `type: bigint`, `onDelete: cascade`.
- [План 16: составной unique](smarttable-plan-16-composite-unique.md) — `defineUniqueKeys()`; словарь `unique_keys`; не флаг поля.
- [User (backend)](user.md) — учётка; сессия в Auth.
- [Нарезка User](user-roadmap.md) — планы модуля User.
- [План User 1: учётка](user-plan-01-account.md) — таблица `user`, фасад `IUserAccounts`, без HTTP.
- [План User 2: группы](user-plan-02-groups.md) — членство, ключи прав, bypass.
- [План Auth 1: сессия](auth-plan-01-session.md) — cookie, `user_identity`, login/register, seed групп.
- [План Auth 2: политика и сброс](auth-plan-02-password-flow.md) — сделано; remember, политика в БД, start/final reset, `setPassword`, право `auth.user.edit`.
- [План Auth 3: сброс через Mail](auth-plan-03-mail-reset.md) — сделано; `MailPasswordResetNotifier` + seed `auth.password_reset`; не SMTP.
- [Нарезка хвостов Guest / члены](core-tails-roadmap.md) — Guest и страница `getMembers` сделаны.
- [План Auth 4: гость](auth-plan-04-guest.md) — сделано; сессия без `user_id`.
- [План User 6: страница членов](user-plan-06-members-page.md) — сделано; `userGroup.getMembers` `{ items, total }`.
- [План User 7: unique членства](user-plan-07-member-unique.md) — `UNIQUE (user_id, group_id)`; без `member_key`.
- [План Agent 1: тик](agent-plan-01-tick.md) — таблица `agent`, `IAgents`, CLI `bin/agent.php`.
- [План Mail 1: очередь](mail-plan-01-queue.md) — `mail_event` / `mail_template` / `mail_job`, плейсхолдеры, flush.
- [План Logger 1: узкий логер](logger-plan-01.md) — сделано; таблица `log`, адаптер `ILogger`; не audit.
- [План Logger 2: куда писать](logger-plan-02.md) — сделано; mail job failed; CLI setup/agent.
- [Нарезка Chat](chat-roadmap.md) — `Messages/Chat`: фасад → HTTP → SSE → Vue → visibility.
- [План Chat 1: таблицы и фасад](chat-plan-01.md) — `IChats`; без HTTP/SSE.
- [План User 3: HTTP учётки](user-plan-03-http.md) — `user.*`, актор запроса, `user.create` в Auth.
- [План User 4: HTTP групп](user-plan-04-groups-http.md) — `userGroup.*`, `memberCount`, subset ключей.
- [План User 5: выправить контракт](user-plan-05-no-catalog-dump.md) — сделано; `findPage`, JSON-вид, Vue.
- [Система правил](rule-system.md) — RuleType, ревизии, каталог, ресурсы и публикация.
- [Система персонажей](character-system.md) — версии, membership, validation и модерация.
- [Система игр](game-system.md) — игра, бой, абстрактное движение, overlay, инвентарь и экономика.
- [Система battleground](battleground-system.md) — тактическая сцена (`REQUIREMENT`, не реализована).
- [Система Chat](chat-system.md) — host, attachments и plugin-контракты. PHP — [`chat-roadmap.md`](chat-roadmap.md).
- [Пользователи и авторизация](auth-system.md) — профили, группы, права и сессии.
- [Интерфейс и уведомления](ui-system.md) — UI-разделы, frontend-паттерны и незавершённые backend-контракты.
- [Сквозные переходы](cross-domain.md) — границы Rule→Character→Game→Chat и GameScene→бой; события и concurrency.
- [Актуальные решения](decisions.md) — реестр решений с уникальными ID.
- [История](history.md) — отменённые модели, старые планы и отложенные вопросы.
- [Карта миграции](migration-map.md) — соответствие разделов старого монолита новым документам.
- [Статусы и владельцы контрактов](contract-status.md) — классификация, canonical owners и evidence.
- [Модель данных](data-model.md) — legacy-backed schema requirements и backend status.
- [Матрица статусов claims](migration-claims-status.md) — независимые статусы источника, реализации и документа.
- [Индекс evidence](migration-evidence-index.md) — exact source paths и symbols для claim verification.
- [Inventory parent claims](migration-parent-inventory-2026-08.md) — полный список 56 исходных parent claims.
- [Inventory atomic evidence](migration-evidence-inventory-2026-08.md) — проверяемый перечень evidence для atomic claims.
- [Реестр claims](migration-claims.md) — legacy ranges, owners, evidence и dispositions.
- [Отчёт миграции](migration-report.md) — покрытие, cross-domain проверки и исключения.

## Обзор сущностей (DEC-006)

Текущие сущности: пользователь и группы (права суммируются); правило с глобальным ID; пространство с scoped numeric `revision` и immutable `publishedAt`; персонаж, привязанный к `(spaceId, revision)`; игра на той же ревизии. Старые формулировки версии как `created_at` timestamp, A.B.C пространства и `rules_version_at` как внешний selector — [`history.md`](history.md).

Наследование пространств — snapshot-copy независимых копий. Публикация создаёт новую версию того же `rule_id` в целевом пространстве с diff. Удаление — marker-version `active=false`. Выборочная публикация — `DEC-015`.

Полная классификация фрагментов legacy: [`../review/tr-reconciliation-2026-08.md`](../review/tr-reconciliation-2026-08.md).

## Общие ограничения

- Документы описывают подтверждённый текущий контракт, а не желаемую архитектуру.
- Backend-гипотезы помечаются `OPEN`.
- Магия не уточняется до реальной выгрузки: источники, пути, ветки и навыки имеют статус `DEFERRED`.
- Редакционные статусы контента и runtime-поддержка независимы.
- Исправления кода, frontend-правил и дизайн-спек выполняются отдельными задачами после явного решения.

## Связанные материалы

- [Legacy archive](history/TR-legacy-2026-08.md) — неизменяемая копия исходного монолитного ТР.
- [Baseline первого прохода](migration-baseline-2026-08.md).
- [Baseline второго прохода](migration-baseline-second-2026-08.md).
- [Baseline корректирующего прохода](migration-baseline-correction-2026-08.md).
- [Baseline полной трассировки](migration-baseline-traceability-2026-08.md).
- [Baseline точечной доработки](migration-baseline-pass2-2026-08.md).
- [Baseline следующего этапа](migration-baseline-next-pass-2026-08.md).
- [Baseline полного переноса содержания](migration-baseline-recon-2026-08.md).
- [Журнал reconciliation](../review/tr-reconciliation-2026-08.md).

- [Аудит кода против ТР](../review/tr-audit-2026-08.md)
- [Журнал решений аудита](../review/tr-decisions-2026-08.md)
- [Frontend rules](../../draft-front_1.2ds/frontend-rules.md)
