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
- [Система правил](rule-system.md) — RuleType, ревизии, каталог, ресурсы и публикация.
- [Система персонажей](character-system.md) — версии, membership, validation и модерация.
- [Система игр](game-system.md) — игра, бой, движение, overlay, инвентарь и экономика.
- [Система Chat](chat-system.md) — host, attachments и plugin-контракты.
- [Пользователи и авторизация](auth-system.md) — профили, группы, права и сессии.
- [Интерфейс и уведомления](ui-system.md) — UI-разделы, frontend-паттерны и незавершённые backend-контракты.
- [Сквозные переходы](cross-domain.md) — границы Rule→Character→Game→Chat, события и concurrency.
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
