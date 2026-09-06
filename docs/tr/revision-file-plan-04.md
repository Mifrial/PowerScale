# План 4 — upsert справочника из снимков файла

**Статус:** каркас Vue сделан, 2026-09-06. Нарезка — [`revision-file-roadmap.md`](revision-file-roadmap.md) шаг **4**. Импорт черновика — [`revision-file-plan-03.md`](revision-file-plan-03.md). Формат — [`revision-file-plan-01.md`](revision-file-plan-01.md). HTTP справочников — [`keyword-plan-02.md`](keyword-plan-02.md), [`mechanic-plan-02.md`](mechanic-plan-02.md). Фронт — `draft-front_1.2ds/frontend-rules.md`.

Цель: импорт ревизии **сам заводит и подтягивает** used-снимки `file.keywords` / `file.mechanics` в глобальный каталог (`keyword.create|update|deactivate`, `mechanic.create|update`), затем прежний overlay в черновик. Не отдельный файл «всего справочника». Не HTTP `ruleSpace.import*`. Не реактивация keyword (её нет в API). Не DELETE лишних mechanic. Не админка `/admin/mechanics`. Не Vue-сплит.

Сейчас дыра code → `REVISION_FILE_UNRESOLVED`, снимки имён игнорируются. Без этого шага перенос мира на пустой стенд требует ручного каталога.

## Термины

| Термин | Смысл |
|---|---|
| Снимок | Строка в `file.keywords` / `file.mechanics` (used, шаг 2). |
| Upsert | Нет identity → create; есть → update подписи; keyword `active: false` → deactivate. |
| Identity | Keyword: `code`. Mechanic: `code` + `version`. |
| Виртуальный каталог | Live list, поверх которого наложены снимки (для preview materialize). HTTP ещё нет. |

## Что уже есть (не переписывать)

- Envelope v3, used-снимки, `assemble` / parse.
- Импорт шага 3: preview, latest, тройка draft, секции, не `commitDraft`.
- PHP `keyword.*` / `mechanic.create|update` как планы 11–12. JSON не менять.
- Vue `IKeywordApi` уже create/update/deactivate. Vue `IMechanicApi` сейчас **только** `getList` — в этом заходе дописать create/update под уже существующие actions, не новый контракт PHP.

## Решения

### 1. Не весь каталог, но подписи — да

Пишем только identity из снимков файла (used). Строки стенда, которых нет в файле, не удаляем и не выключаем.

Снимок **обновляет** глобальный каталог: `name`, `description`; у keyword ещё выключение. Это общий справочник всех миров — в preview явно показать список create/update/deactivate.

Пропуск HTTP, если live уже совпадает: keyword — `name`, `description`, `active`; mechanic — `name`, `description` (code/version не часть patch).

### 2. Keyword: create / update / односторонний active

| Файл \ live | Действие |
|---|---|
| нет code | `createKeyword({ code, name, description })`; `active: false` сразу после create → `deactivate` |
| есть, имя/описание другие | `updateKeyword` |
| есть, `active: true` live и `false` в файле | `deactivate` (ключ `keyword.delete`) |
| есть, `active: false` live и `true` в файле | **нельзя** включить. Warning в preview, строка остаётся выключенной. Materialize всё равно резолвит id (ссылки на inactive keyword законны). |
Одна строка keyword может сразу `update` (подпись) и `deactivate`. Create с `active: false` — `create` (как в API, обычно active true) затем `deactivate`.

Виртуальный каталог для preview: live id сохраняем; новым строкам — синтетический id (отрицательный), только чтобы `materialize` не UNRESOLVED. В draft эти id не класть: apply всегда `getList` после HTTP и `prepare` заново.

`planApply.isNoOp` шага 3 **не** отменяет upsert каталога. No-op всего импорта — пустой план каталога **и** пустой overlay.

### 3. Mechanic: create / update, без DELETE

| Файл \ live | Действие |
|---|---|
| нет пары code+version | `createMechanic({ code, name, version, description })` |
| есть, имя/описание другие | `updateMechanic` |
| есть, совпало | no-op |

`code` / `version` не патчить. Лишние поставки на стенде не трогать. Нет права create/edit — как у keyword, стоп до overlay.

Дописать Vue: `IMechanicApi.createMechanic` / `updateMechanic`, DTO в `Rule/Dto/`, мок `mockMechanics`. PHP не трогать.

### 4. Порядок apply

1. Preview (без HTTP записи): list → diff снимков → виртуальный каталог → `prepare` шага 3 (materialize не UNRESOLVED из-за дыр, которые create закроет).
2. Confirm (кнопка шага 3, в т.ч. тройка): **сначала** HTTP upsert по плану каталога → свежий `getList` → **заново** `prepare` / `planApply` (реальные id) → overlay черновика как шаг 3.

Не сохранять в draft `keywordIds` из preview до upsert: id после create другие.

Тройка «черновик / выгрузка» **только про правила и секции**. Каталог всегда с файла (глобальный). Отмена — каталог не писать.

Пустой overlay правил **и** пустой план каталога — no-op. Каталог dirty при empty-правилах — upsert выполнить, draft по политике шага 3.

Новый мир: upsert в момент apply на `createSpace` (вместе с draft), не в диалоге «уйти на /spaces/new». Иначе Отмена формы уже испортит каталог. Pending несёт `file` (parsed v3), не только materialized `Rule[]`.

Обрыв на середине upsert (нет транзакции браузера): остановиться, F17, overlay не делать. Повтор импорта идемпотентен (остаток — update/create). Откат уже созданных строк не делаем.

### 5. Preview UI

В диалоге (и на New-page после выбора файла) помимо счётчиков шага 3:

- признаков: создано / обновлено / выключено / без изменений / «нельзя включить»;
- механик: создано / обновлено / без изменений.

Warnings шага 3 (type/contentStatus) плюс «нельзя включить keyword X».

Кнопка apply неактивна, пока preview каталога+правил не готов (как шаг 3, F17 на list).

### 6. Слои

| Кто | Задача |
|---|---|
| Rule Vue | `IMechanicApi` create/update + DTO + мок; Keyword API не расширять |
| RuleSpace `RevisionFileCatalogSyncService` (имя по смыслу) | diff снимков vs list; план upsert; apply HTTP; ctor — keyword/mechanic API. Без Pinia |
| `RevisionFileImportService` | prepare по **виртуальному** каталогу; не ходить в create сам |
| Диалог / New / Detail | показать план каталога; apply: sync затем prepare+overlay |
| PHP Keyword / Mechanic | не этот заход |

RuleSpace по-прежнему только `Rule/init` (`getKeywordApi` / `getMechanicApi`). Не Store keywords. После upsert Pinia админки не обязан invalidate (YAGNI).

Новых `keyword.getByCode` HTTP нет: identity из уже загруженного list.

## Тесты

- Нет keyword → create вызван; повтор с тем же снимком → update не вызван, если подпись совпала.
- Имя в файле другое → update, не второй create.
- Keyword live inactive, файл `active: true` → warning, deactivate/create не «включают»; overlay идёт.
- Файл `active: false`, live true → deactivate.
- Нет mechanic version → create; совпавшая поставка → нет HTTP.
- UNRESOLVED только если после виртуального merge всё ещё дыра (снимок не в `file.keywords`, а правило ссылается) — как шаг 1/2, не create «с пустым именем».
- Apply: мок create вернул id → повторный prepare, в `saveRules` уже этот id, не placeholder.
- Стоп на throw create → `saveRules` не вызван.
- Не Character/Game. Не phpunit. Не UI-mount. Не смена PHP JSON.

## Todo

- [x] **mechanic vue** — create/update на `IMechanicApi` + мок, как PHP план 12.
- [x] **diff** — план upsert по снимкам; skip если equal; cannot-reactivate warning.
- [x] **preview** — виртуальный каталог + счётчики в диалоге.
- [x] **apply** — HTTP затем re-prepare; pending = file; новый мир — upsert на createSpace.
- [x] **tests** — список выше.
- [x] **docs** — этот файл; роудмап шаг 4.

## Не входит

Отдельный dump всего справочника. Реактивация keyword / колонка active у mechanic. DELETE mechanic. Админка механик. Vue-сплит. HTTP файла ревизии. Смена `commitDraft` на codes. Engine.

## Документы захода

этот файл; [`revision-file-plan-03.md`](revision-file-plan-03.md); [`revision-file-roadmap.md`](revision-file-roadmap.md); [`keyword-plan-02.md`](keyword-plan-02.md); [`mechanic-plan-02.md`](mechanic-plan-02.md).
