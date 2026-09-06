# План 2 — экспорт опубликованной ревизии

**Статус:** каркас Vue сделан, 2026-09-06. Нарезка — [`revision-file-roadmap.md`](revision-file-roadmap.md) шаг **2**. Формат — [`revision-file-plan-01.md`](revision-file-plan-01.md). Срез — [`rulespace-plan-04.md`](rulespace-plan-04.md), секции — [`rulespace-plan-06.md`](rulespace-plan-06.md). Справочники — [`keyword-plan-02.md`](keyword-plan-02.md), [`mechanic-plan-02.md`](mechanic-plan-02.md). Фронт — `draft-front_1.2ds/frontend-rules.md`.

Цель: кнопка **Экспорт** на опубликованном срезе даёт файл v3, достаточный для переноса: все пункты состава (включая tombstone), секции этой ревизии, снимки **использованных** keyword/mechanic по code. Каталоги читаются **свежим** `getList`, ошибка справочника не маскируется пустым store. Не импорт-preview, не `commitDraft`, не создание строк справочника, не PHP, не HTTP файла.

Шаг 1 уже дал `assemble` / parse v3 и проводку кнопки. Этот заход закрывает **продуктовый** экспорт: источник данных, полнота среза, кап 500, F17, тесты на живом мок-срезе.

## Термины

| Термин | Смысл |
|---|---|
| Срез | `SpaceRevision<Rule>` из `getRevision` / кэш стора. Внутренние id. |
| Использованные | Признаки и механики, на которые ссылается хотя бы одно правило среза. |
| Tombstone | Пункт состава с `active: false`. В файле остаётся правило с `active: false`. |

## Что уже есть (не переписывать)

- Envelope v3, запрет внутренних id, сортировка, `assemble(revision, keywords, mechanics)`.
- Кнопка скрыта в `ctx=draft`.
- Нет id в карте → `REVISION_FILE_UNRESOLVED`, не частичный JSON.

## Решения

### 1. Только опубликованный срез

Экспорт берёт `revisionStore.activeRevision` при URL `ctx` = номер ревизии (`kind === 'rev'`). Это **любая** опубликованная ревизия из селектора, не обязательно latest. Не `effectiveRules`. Не localStorage. Контекст `draft` — кнопки нет (уже).

Срез иммутабелен: повторно `getRevision` перед экспортом не обязателен, достаточно кэша стора.

Состав = все `revision.rules`, в том числе `active === false`. Не фильтровать tombstone перед `assemble`. У правила без ключа `active` шаг 1 уже считает `true` (`!== false`); фикстура tombstone должна явно ставить `false`. Мок `generateRevisionRules` tombstone не обязан содержать — юнит-фикстура достаточна, seed Character не трогать.

Пустой состав (`rules: []`, мир без ревизий / revision 0 в моке): `assemble` уже режет `format` `/rules`. UI показывает сообщение, файл не качается.

Снимки keyword/mechanic — **текущий** справочник, не имена на момент публикации (справочники не версионируются). Это принятый семантический зазор, не баг захода.


### 2. Свежие справочники, не Pinia

`useKeywords().fetchTags` глотает ошибку в `store.error` и оставляет `[]` → ложный UNRESOLVED. Экспорт **не** опирается на этот store.

Новый узкий сервис в RuleSpace (класс, `Service/` + `Instance/`): в ctor — зависимости `getKeywords` / `getMechanics` (в Instance — `getKeywordApi` / `getMechanicApi` из Rule `init`). `Promise.all` двух list, один `AbortSignal`. Успех — два list в `assemble`. Сбой транспорта — проброс (HttpClient уже throw с `message`); страница показывает `error.message` (F17). Не мапить коды `KEYWORD_INVALID` отдельно. Не создавать keyword/mechanic. Unit: мок list; мок throw.

Кап **500**: сервер при переполнении отдаёт INVALID, не обрезку. Клиент не угадывает «ровно 500 = обрезано». Нет строки на id ссылки → UNRESOLVED (как шаг 1). Новых `keyword.getByCode` / `mechanic.getByCodeVersion` HTTP **не** добавлять.


### 3. Снимки только used

`file.keywords` / `file.mechanics` — объединение ссылок правил, не весь глобальный справочник. Выключенный keyword (`active: false`), если на него есть ссылка, **входит** в файл как есть. Неиспользуемые строки каталога не пишем.

После `assemble` инвариант: каждый `keywordCodes[]` и каждый `mechanic {code, version}` имеет строку в соответствующем блоке. Проверка тестом (и при желании assert в `assemble`).

`mechanic === null` → в `mechanics[]` эту поставку не кладём; payload `[]`.

### 4. Секции и placement

`sections` — дерево **этой** ревизии (`revision.sections`), как пришло с `getRevision`. Сортировка dump как шаг 1 (`sortOrder`, затем `code`). `catalogSection` / `catalogSortOrder` на правиле — как в срезе HTTP, без второго массива placements.

Циклы дерева и «placement на code не из состава» — не этот заход (шаг 3, импорт). Экспорт копирует срез как есть.

### 5. UI

`SpaceDetailPage.exportRevision`: загрузка каталогов новым сервисом + `assemble` + `downloadJson`. `AbortSignal` со страницы (`useAbortable`). Сообщение ошибки — текст исключения (Problem или транспорт). Не тихий no-op при пустом каталоге.

Имя файла по-прежнему `{spaceCode}-v{revision}.json`.

Импорт: `materialize` / diff / диалог не менять. Загрузку каталогов на Detail/New **переключить** на тот же сервис, иначе останется глотающий `fetchTags` и импорт снова соберёт `[]`. Preview, секции-draft, warnings — шаг 3.


### 6. Слои

| Тип | Задача |
|---|---|
| `RevisionFileCatalogService` (имя по смыслу) | getList keyword + mechanic |
| `RevisionFileService.assemble` | id→code, used-снимки, tombstone, сортировка (уже) |
| `SpaceDetailPage` | кнопка, abort, snackbar |
| тесты Service | dump без id; tombstone; used-only; catalog throw |

RuleSpace → Rule только `init` / Dto Keyword и Mechanic. Не Store keywords.

### 7. Тесты

- `assemble` среза с `active: false` → в JSON есть это правило, `active: false`, нет `id`.
- ссылка на inactive keyword → снимок с `active: false`.
- keyword/mechanic, не использованные правилами, отсутствуют в блоках файла.
- мок-срез `generateRevisionRules` + mock keyword/mechanic: дамп без `keywordIds`/`mechanicId`/`spaceId`; round-trip `parse(JSON.stringify(assemble(...)))`. Если UNRESOLVED — чинить рассинхрон фикстур среза и справочника **в этом заходе**, не omit ссылок.
- нет keyword id в list → UNRESOLVED, файла нет.
- catalog-сервис: throw с мока API пробрасывается.

Не Character/Game. Не phpunit. Не автосоздание справочника.

## Todo

- [x] **catalogs** — свежий getList в сервисе RuleSpace; страница экспорта не через глотающий store.
- [x] **slice** — все пункты состава включая tombstone; секции ревизии.
- [x] **used** — только использованные снимки; инвариант ссылка↔блок.
- [x] **ui** — abort + F17; не менять контракт импорта.
- [x] **tests** — tombstone, used-only, мок-срез без внутренних ключей.
- [x] **docs** — этот файл; роудмап шаг 2.

## Не входит

Шаг 3 (preview, `removeMissing`, секции в draft, warnings type/contentStatus, `samePayload` по codes). Создание/update keyword и mechanic из файла. HTTP файла. Смена `commitDraft`. Кап обходить новым API. Вычищение `Roleplay/Space`. Экспорт draft.

## Документы захода

этот файл; [`revision-file-plan-01.md`](revision-file-plan-01.md); [`revision-file-roadmap.md`](revision-file-roadmap.md); [`keyword-plan-02.md`](keyword-plan-02.md); [`mechanic-plan-02.md`](mechanic-plan-02.md).
