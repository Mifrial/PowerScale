# План 3 — импорт файла в черновик

**Статус:** каркас Vue сделан, 2026-09-06. Нарезка — [`revision-file-roadmap.md`](revision-file-roadmap.md) шаг **3**. Формат — [`revision-file-plan-01.md`](revision-file-plan-01.md). Экспорт — [`revision-file-plan-02.md`](revision-file-plan-02.md). Срез — [`rulespace-plan-04.md`](rulespace-plan-04.md), секции — [`rulespace-plan-06.md`](rulespace-plan-06.md). Diff/publish — [`DEC-015`](decisions.md). Фронт — `draft-front_1.2ds/frontend-rules.md`.

Цель: JSON v3 становится **черновиком** целевого мира: preview (added/changed/unchanged/`removedCodes` + warnings + секции) → apply в `useRuleDrafts` и `sectionCatalog.saveDraft`. Не `commitDraft`. Не HTTP файла. Не создание keyword/mechanic. Публикация остаётся PublishDialog / selective commit.

Сейчас импорт уже parse → `materializeRules` → `diffAgainstPublished` → `saveRules`. Дыры: секции файла теряются; `samePayload` не видит `active` / `contentStatus` / placement; база diff — URL-срез, не latest; preview нет; «импорт как новый мир» несёт только `Rule[]`.

## Термины

| Термин | Смысл |
|---|---|
| Latest | Последняя опубликованная ревизия **целевого** пространства (`resolveLatestRevision` + `getRevision`), не номер в URL. |
| Overlay | Файл накладывается на latest: коды из файла — put в draft; коды latest, которых нет в файле, — в `removedCodes` только при флаге `removeMissing`. |
| Tombstone в файле | Правило **есть** в файле с `active: false`. Это changed/unchanged, не `removedCodes`. |
| Warning | Не блокирует apply. Неизвестный `type` / `contentStatus`. Объект остаётся. |
| Выгрузка | Файл ревизии v3 (дамп), который накладываем. |
| Занятый код | code уже в draft правил (`changedRules` или `removedCodes`) **или** черновик секций dirty относительно latest. |

## Что уже есть (не переписывать)

- Envelope v3, parse/serialize, `assemble`, `materializeRules` (live `getList`, UNRESOLVED при дыре code).
- `RevisionFileCatalogService` (шаг 2). Не `fetchTags`.
- Диалог: parse, цель current/new, флаг `removeMissing`.
- `AbilitySectionTreeService.validate` / `validateRuleSections` / `sameCatalog` / `sectionCatalog.saveDraft`.
- PublishDialog и `commitDraft` не трогать контрактом.

## Решения

### 1. База — latest, не URL ctx

`commitDraft` всегда садится на последнюю ревизию. Импорт, пока в селекторе открыта старая ревизия, всё равно сравнивает и пишет draft **против latest** того же `spaceId`. Исторический срез в URL не база overlay.

Мир без ревизий (`revision < 1`): published = пустой состав и пустые секции (как `unpublishedSlice`).

### 2. Payload diff включает статус и каталог

Расширить `RuleDiffService.samePayload` (и тем самым publish-diff): плюс `active`, `contentStatus`, `catalogSection`, `catalogSortOrder`. По-прежнему без `id` / `spaceId` / `createdAt`. Сравнение после materialize — по `keywordIds` / `mechanicId` (коды уже сведены к id живого каталога).

Нормализация до сравнения, иначе HTTP-срез без ключа и файл с явным полем дадут ложный changed: `active` как `!== false`; `catalogSection` нет/`undefined` = `null`; `contentStatus` нет = одинаково «не задан». `catalogSortOrder` нет = нет (не `0`).

Это намеренно чинит и PublishDialog: смена только `active` или только полки каталога больше не «тихий no-op». Тесты в `ruleDiff.test.ts` и импорт-фикстуре tombstone.

Имена в снимках `file.keywords` / `file.mechanics` **не** применяются и **не** сравниваются. Identity — `code` / `code+version`. Расхождение имени снимка и live — не warning этого шага.

### 3. Секции и placement

После materialize:

1. `abilitySectionTreeService.validate(file.sections)` — цикл, дырявый parent, пустой/дубль code → ошибка, не apply. Контракт дерева (массив строк) не менять: импорт-сервис мапит в `RevisionFileProblemError` (`stage: format`, path `/sections` или `/sections/{i}`). Дубль code parse уже ловит.
2. `validateRuleSections(rules, file.sections)` — то же: `catalogSection` не null и нет в дереве файла → ошибка. `null` / omit — «без полки», норма.
3. Если дерево валидно и `!sameCatalog(file.sections, latest.sections)` — в preview «секции изменятся»; apply: `sectionCatalog.saveDraft(spaceId, normalize(file.sections))`. Если деревья равны — draft секций не трогать (не создавать ложный dirty).
4. Пустой `sections: []` в файле — явный пустой каталог, не «оставить как было».

`isEmptyDiff` учитывает секции: empty только если нет added/changed, нет `removedCodes` к записи, и секции не dirty.

### 4. Preview, потом apply

После parse диалог **не** закрывается сразу в apply. Preview **async** (list + `getRevision` latest): в диалоге loading/ошибка F17, кнопка apply неактивна пока нет успешного preview.

Пайплайн — класс RuleSpace `Service/` (**prepare**, не запись Pinia): каталоги → materialize → validate секций → latest slice → `diffAgainstPublished` + dirty секций → warnings. Именованный DTO preview — в `Dto/`. Apply — страница: `saveRules` / `setRemovedCodes` / `saveDraft`.

Показать счётчики: added / changed / unchanged / к удалению / «секции да/нет» / список warnings (type, contentStatus). Confirm применяет; Cancel не пишет draft.

Неизвестный `type`: нет ключа в `RULE_TYPE_LABELS` (импорт с `Rule/init`, не чужой `Constant/`) — warning, правило в diff как есть. Неизвестный `contentStatus`: не `needs_work` и не `ready` — warning, поле сохранить; набор известных статусов — константа Rule, не магические строки в RuleSpace. Extra ключи корня/правила по-прежнему ошибка parse (шаг 1). Extra внутри `spec` / `mechanicPayload` — keep, без warning.

UNRESOLVED keyword/mechanic и сбой list — ошибка, F17, не preview «частично». Не создавать строки справочника.

### 5. Конфликт с существующим draft

Целевой мир, `intoCurrent`, и уже есть черновик: `hasDraft(spaceId)` **или** `sectionCatalog.isDirty(spaceId, latest.sections)`. Тогда **до apply** в том же диалоге три действия (не отдельный скрытый default):

| Действие | Смысл |
|---|---|
| Отмена | Сторы не трогать. |
| Приоритет в черновике | Занятые коды и dirty-секции не переписывать выгрузкой. Незанятое из файла — наложить. |
| Приоритет в выгрузке | Выгрузка побеждает пересечение, в том числе unchanged-коды с локальной правкой. |

Нет черновика — одного «Применить» достаточно (политика выгрузки: писать только дельту к latest). Новый мир / `intoCurrent: false` — вопроса нет: целевого draft ещё нет.

Политика — `Enum/` RuleSpace (string union). Три кнопки **сами** подтверждают apply (отдельного «Применить» при draft нет). Счётчики: показать overlay к latest сразу; после наведения/выбора не обязательно второй запрос — merge политики считает клиент по снимку draft (prepare чистый). Отмена = сторы не трогать, диалог закрыть.

**Черновик.** Occupied code: не `saveRules` из файла, не добавлять в `removedCodes` из `removeMissing`. `removedCodes` цели: прежние removed остаются, плюс removeMissing только по **незанятым** кодам latest, которых нет в файле. Секции dirty — не `saveDraft`. Секции не dirty и дерево файла ≠ latest — `saveDraft` из файла. Unchanged относительно latest и код не occupied — не писать.

**Выгрузка.** Каждый code из файла: локальная правка сбрасывается. Если payload файла = latest (`unchanged`) — `removeRule(code)` (вернуть к published, не класть в `changedRules` копию 1:1). Если added/changed — `saveRules`. `removeMissing`: файл побеждает, даже если code был в draft (как сейчас `setRemovedCodes` вычищает `changedRules`). Секции: дерево файла ≠ latest → `saveDraft`; дерево = latest → `discardDraft` секций (локальные полки сбросить).

Empty относительно latest **и** нет draft — snackbar, сторы не трогать. Empty **и** есть draft — вопрос всё равно нужен: «выгрузка» может сбросить локальные правки unchanged-кодов / секций; «черновик» при empty = no-op после подтверждения.

### 6. Apply только draft

- Никогда `commitDraft` / `publish`.
- `setRemovedCodes` — итоговый список после политики. Не вызывать, если apply отменён или полный no-op (empty + черновик / нет draft).
- Только секции (после политики) dirty: `saveRules([])` no-op ок; `saveDraft` или `discardDraft` по §5.
- После apply — `/space/{code}/draft`, snackbar summary.

Повтор файла без публикации зависит от политики. После публикации состава и пустого draft — empty, без тройки.

Tombstone в файле vs published `active: true` → **changed**, в draft правило с `active: false`. Код, которого нет в файле, при `removeMissing` → `removedCodes`, не путать с tombstone.

### 7. Новый мир

`pendingImportedRules: Rule[]` недостаточно. Pending — целиком разобранный файл **или** `{ rules, sections }` после materialize+validate (каталоги уже резолвлены). На `SpaceNewPage` после `createSpace` — те же `saveRules` + `saveDraft` секций. `removeMissing` на пустом latest бессмыслен: `false`. Inherit-from по-прежнему снимается, если есть файл.

Диалог New-page (свой file input) — тот же пайплайн, что Detail.

### 8. Слои

| Кто | Задача |
|---|---|
| `RuleDiffService.samePayload` | active, contentStatus, placement |
| `RevisionFileService` | warnings type/status; empty с секциями; валидация дерева → Problem |
| RuleSpace import-сервис (`Service/` + `Instance/`) | prepare: catalogs + latest + validate + diff; ctor-зависимости list/`getRevision`; **без Pinia** |
| `RevisionImportDialog` | parse; async preview (F17); счётчики; тройка при draft; confirm |
| `SpaceDetailPage` / `SpaceNewPage` | apply в сторы, abort, pending с секциями |
| `sectionCatalog` | только `saveDraft` / не трогать если sameCatalog |

Страница не ходит в Store keywords. Character/Game не импортируют этот сервис.

## Тесты

- Повтор файла, совпадающего с latest (в т.ч. секции) → empty; snackbar; store draft не меняется.
- Tombstone в файле → changed с `active: false`, код не в `removedCodes`.
- `removeMissing`: код только в latest → `removedCodes`; тот же код tombstone в файле → не removed.
- Секции файла → `saveDraft`; цикл / placement на чужой code → ошибка, draft чист.
- Неизвестный `type` → warning + правило в added/changed.
- Нет keyword code в list → UNRESOLVED, не apply.
- `samePayload`: правка только `active` или только `catalogSection` → changed (и для classifyDraftDiff публикации).
- New-space pending несёт секции; после create они в `getDraftSections`.
- База: сервису подложен latest N при «текущем» N−1; overlay против N. Не mount страницы (F8).
- Только секции отличаются → не empty; `saveDraft`; `saveRules` не обязателен.
- `samePayload`: срез без `active` и файл с `active: true` — равны.
- Нет draft — тройки нет, дельта к latest.
- Черновик: occupied code в файле как changed — в apply не попадает; незанятый added — попадает.
- Выгрузка: локальная правка unchanged-кода снимается (`removeRule`); секции = latest → discard секций.
- Отмена при draft — сторы как были.

Не Character/Game. Не phpunit. Не HTTP файла. Не UI-mount.

## Todo

- [x] **payload** — `samePayload` + тесты tombstone/status/shelf.
- [x] **base** — latest slice, не `activeRevision` URL.
- [x] **sections** — validate + saveDraft + pending new space.
- [x] **preview** — async F17; счётчики и warnings до apply; empty не пишет сторы.
- [x] **conflict** — тройка при draft; prepare с policy; тесты occupied vs file-win.
- [x] **apply** — только draft; не commitDraft.
- [x] **tests** — список выше; vue-tsc / vitest модуля.
- [x] **docs** — этот файл; роудмап шаг 3.

## Не входит

HTTP файла. Автосоздание keyword/mechanic. Смена JSON `commitDraft` на codes. Режим «файл = полный состав» без флага `removeMissing`. Закрытый enum `RuleType`. Vue-сплит Keyword/Mechanic. PHP. Вычищение мёртвого `Roleplay/Space`. Сверка имён снимков справочника с live.

## Документы захода

этот файл; [`revision-file-plan-01.md`](revision-file-plan-01.md); [`revision-file-plan-02.md`](revision-file-plan-02.md); [`revision-file-roadmap.md`](revision-file-roadmap.md); [`rulespace-plan-06.md`](rulespace-plan-06.md).
