# План Logger 5 — даты на экране журнала

**Статус:** TODO, 2026-09-10. Предыдущий заход: [`logger-plan-04-view.md`](logger-plan-04-view.md) (HTTP и `/admin/logs` сделаны). Фронт — `draft-front_1.2ds/frontend-rules.md`. Канон HTTP не менять.

Цель: оператор видит `createdAt` как дату/время и может отфильтровать журнал по периоду так, чтобы это было понятно с экрана. Сейчас JSON — unix UTC (`int`); ячейка `datetime` и `formatDatetime` ждут ISO-строку, поэтому в гриде торчит сырое число. Фильтр периода в FilterBar подключён, но связан с тем же datetime-слоем UI.

Это хвост **отображения и фильтра на Vue**, не новый HTTP и не audit.

## Минимум

1. Колонка «Время» на `/admin/logs` — локальная дата и время, не unix и не `Invalid Date`.
2. Фильтр по дате/периоду на том же экране реально задаётся из UI (equals / from / to / interval) и уходит в уже существующие `from`/`to` `logger.findPage` (маппер плана 4).
3. Диалог деталей показывает тот же человеческий момент записи.

Предпочтение: починить `DateCell` / `formatDatetime` (и при необходимости datetime-фильтр) в `Core/UI`, чтобы unix из JSON журнала был штатным значением типа `datetime`. Отдельную ячейку только в Logger не плодить, пока общий слой справляется. HTTP `createdAt` / `from` / `to` **не** переводить на ISO.

## Не входит

Новый PHP-контракт; sort по дате; индексы ST; live-stream; цветные чипы уровня; audit; seed `logger.view`.

## Документы захода

этот файл; [`logger-plan-04-view.md`](logger-plan-04-view.md); [`TR.md`](TR.md); `frontend-rules.md`.
