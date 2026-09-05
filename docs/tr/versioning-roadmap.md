# Нарезка Versioning/Space

**Статус:** план, 2026-09-05. Конвейер — [`architecture.md`](architecture.md). Стандарты PHP — [`php-coding-standards.md`](php-coding-standards.md). Setup — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). SmartTable Basic закрыт; **VersionedSmartTable в ST нет** — [`smarttable.md`](smarttable.md). Домен правил — [`rule-system.md`](rule-system.md), нарезка продукта — [`rule-roadmap.md`](rule-roadmap.md). `DEC-080`.

Порядок сверху вниз. PHP-модуль **`Versioning/Space`** (ленивый, не `Core/*`). Не Roleplay, не SQL: только Basic ST.

Один модуль в группе, пока нет второго агрегата. Связка нескольких репозиториев — не этот модуль.

## 0. Сделано / блокеры

Kernel `action.php`, lazy-слоты (`Messages/Chat`). SmartTable Basic, setup, `powerscale_test`. User/Auth не блокер (Versioning ↛ User).

[`smarttable-plan-18-slice-batch.md`](smarttable-plan-18-slice-batch.md), [`cache-plan-01.md`](cache-plan-01.md), [`versioning-plan-01.md`](versioning-plan-01.md) — **сделаны**.

## 1. Кластер и фасад

[`versioning-plan-01.md`](versioning-plan-01.md). Фикстура `vt_note`. Сделано.

## 2. Хвост часов для второго потребителя

Сделано в коде: generic body `CommitEntry` и `openCluster(ClusterSpec)`. Продукт Rule — [`rule-roadmap.md`](rule-roadmap.md).

## Дальше продукт

RuleSpace — [`rule-roadmap.md`](rule-roadmap.md) шаги 4–10; хвост `findLatestRevision` — [`rulespace-plan-01.md`](rulespace-plan-01.md); лента и `updateSpace` — [`rulespace-plan-02.md`](rulespace-plan-02.md); HTTP `ruleSpace.*` — [`rulespace-plan-04.md`](rulespace-plan-04.md). HTTP Keyword/Mechanic — шаги 11–12.

## Позже (этот модуль)

Второй Versioning-репозиторий. Оркестратор нескольких репозиториев. Не валидация Rule spec.

## Параллелить нельзя

- Rule 1 без п.2 (generic body + `openCluster`).
- Черновик в таблицах Versioning.
- `Versioned*` внутри `Core/SmartTable`.
- Eager `Core/*` для Versioning / Rule.
- `open(class)` ST, который создаёт кластер сам.
- Часы commit внутри Rule.
