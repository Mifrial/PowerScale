# План RuleSpace 11 — Vue: редактор секций каталога

**Статус:** каркас Vue сделан, 2026-09-08. Следующий шаг после [`rulespace-plan-10.md`](rulespace-plan-10.md). Канон секций — [`rulespace-plan-06.md`](rulespace-plan-06.md), [`rule-system.md`](rule-system.md), [`architecture.md`](architecture.md). Правила фронтенда — [`../../draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md).

Цель: добавить страницу редактирования дерева секций каталога в существующий Vue-модуль **`Roleplay/RuleSpace`**. Отдельный модуль `Roleplay/RuleSection` не создаётся: секции принадлежат пространству, входят в снимок его ревизии, а их черновик публикуется через `ruleSpace.commitDraft`.

## 1. Архитектурные решения

### 1.1. Владелец секций

Владельцем остаётся `Roleplay/RuleSpace`.

Секции не являются глобальным справочником уровня Keyword/Mechanic:

- у секции нет независимого глобального CRUD;
- секция идентифицируется внутри снимка каталога пространства;
- опубликованный каталог привязан к конкретной ревизии;
- разные ревизии одного пространства могут ссылаться на разные снимки секций;
- сервер не предоставляет отдельное сохранение секций;
- изменение каталога публикуется как новая ревизия через `commitDraft`.

Внутри `RuleSpace` допускается выделить отдельный UI/domain-срез `Section`, но без нового прикладного модуля и нового DAG-рёбра.

### 1.2. Связь с ревизиями

Это **две поверхности**, не два режима одной страницы:

- `/space/:code/:ctx/sections` — дерево секций выбранного контекста (`draft` = pending-каталог, число = снимок ревизии, read-only);
- `/space/:code/sections` — редирект на `/space/:code/draft/sections` (старые закладки);
- `/space/:code/:revision` (`SpaceDetailPage`) — правила этой ревизии.

Редактор не меняет каталог выбранной старой ревизии. Мутации только в `/draft/sections` и только если база = `space.revision` (последняя опубликованная). Черновик сравнивается с каталогом последней опубликованной ревизии. При публикации:

- каталог без изменений шарится с latest;
- изменённый каталог создаёт новый `section_version`;
- старая ревизия продолжает читать свой прежний снимок;
- каталог-only публикуется через существующий all-keep путь.

## 2. Маршруты и навигация

Добавить страницу редактора в контекст пространства:

```text
/space/:code/:ctx/sections
```

Это ребёнок `/:ctx`, рядом с `SpaceDetailPage` (правила). Sibling `settings` остаётся без ревизии: настройки пространства не принадлежат снимку. Старый `/space/:code/sections` редиректит на `draft/sections`, чтобы `sections` не перехватывался как `:ctx`.

Ожидаемое поведение:

- `/space/:code/draft/sections` открывает редактор pending-каталога;
- `/space/:code/5/sections` показывает дерево секций ревизии 5 read-only;
- ссылка «Секции» в шапке детали сохраняет текущий `:ctx`;
- с опубликованной ревизии правки не пишутся; «Черновик» в селекте ведёт на редактор;
- при `revision === 0` дерево пустое: каталог-only на HTTP недоступен, пока нет состава (как план 6).

Маршрут с `:ctx`: layout вызывает `syncFromContext`. На `draft` страница дополнительно подмешивает локальный `sectionCatalog` draft и зовёт `syncFromContext(..., 'draft', latest)` до `PublishDialog`. Рабочая копия на исторической ревизии — `activeRevision.sections`, без draft.

Доступ к редактору: владелец или `space.edit_all` (bypass как у User). Новых ключей прав нет. `view_all` без владения — не редактор; дерево latest можно показать read-only на детали пространства. Гость не ходит в оператор.

## 3. Контракт операций дерева

Не вводить отдельные доменные команды `moveSection` и `changeParent`.

Минимальный набор операций:

- `addSection(section)` — добавить узел; `code` задаётся здесь и дальше не патчится;
- `updateSection(code, patch)` — имя, `parentCode`, `sortOrder`, `catalogRootFor`; не `code`;
- `removeSection(code)` — удалить лист; узел с детьми — отказ;
- `normalizeSortOrder(sections)` — канонический порядок среди братьев;
- `validate(sections)` — дерево (цикл, parent, дубль code, уникальность непустого `catalogRootFor`).

Смена родителя и порядка — поля того же `updateSection`. Отдельных `moveSection` / `changeParent` нет. DnD одного узла может затронуть **несколько** строк: у переносимого — новый `parentCode`, у старых и новых братьев — пересчёт `sortOrder` через `normalizeSortOrder` (дыры и равный sort на сервере допустимы, клиент нормализует канонично). Drop в потомка — `validate` (цикл), persist нет.

`validate` дерева дополняет уже существующий `validateRuleSections(rules, sections)`: удаление папки, на которую ссылаются published или draft rules (`catalogSection`), должно падать на клиенте до persist, иначе `commitDraft` получит `INVALID`.

Операции — в `AbilitySectionTreeService` (расширить текущий класс, не плодить второй хозяин дерева). Store только persistence.

## 4. UX редактора

Страница `/space/:code/:ctx/sections` — **сворачиваемое дерево**, не таблица и не SmartGrid. Боковой формы и маршрута `…/sections/:code` нет.

### 4.1. Дерево

- Узлы раскрываются/сворачиваются (дети скрыты, пока родитель свёрнут).
- Строка: иконка карандаша **перед** названием, затем имя; у узла с детьми — шеврон раскрытия.
- Перетаскивание узла меняет `parentCode` и/или `sortOrder` среди новых братьев (в том числе сделать корнем или вложить в другого). Поддерево едет вместе с узлом.
- Drop в собственного потомка — отказ (цикл), дерево не менять.
- DnD только в редакторе (`canEdit`). Read-only на `SpaceDetailPage`: те же раскрытия, без карандаша и без drag.
- Логика drop — в сервисе, не в шаблоне: патч переносимого узла + нормализация братьев. Новую библиотеку DnD не тащить, пока хватает HTML5 + Vuetify. `v-treeview` в проекте есть у `TreeSelectFilter`, но без reorder — редактор каталога свой, не обёртка фильтра.

Добавление корня — кнопка на странице. Добавление ребёнка — из диалога родителя или компактное действие на строке, не отдельный экран.

### 4.2. Диалог редактирования

Карандаш открывает **компактный диалог** (не страницу): имя, `catalogRootFor`, удаление (с теми же запретами: нелист / есть ссылки из правил). `code` в диалоге создания задаётся, в диалоге правки — только показ, без патча.

Родитель и порядок в диалоге не дублировать обязательными полями: их меняет DnD. Показ текущего пути (readonly) допустим.

Черновик пишется в `sectionCatalog` **на каждую успешную мутацию** (drop, сохранение диалога, удаление), как черновик правил. «Сбросить» — `discardDraft` + копия latest.

«К публикации» — существующий `PublishDialog` на этой странице, без второго commit.

`catalogRootFor`: селект `RuleCatalogArea` плюс неизвестная строка с сервера. Один непустой код на значение — как PHP план 6.

## 5. Предлагаемая структура файлов

```text
Roleplay/RuleSpace/
├── Page/
│   └── SectionCatalogPage.vue
├── Component/
│   └── Section/
│       ├── SectionTreeEditor.vue
│       ├── SectionTreeRow.vue
│       └── SectionEditorDialog.vue
├── Composables/
│   └── useSectionCatalogEdit.ts
├── Service/
│   └── AbilitySectionTreeService.ts
└── Store/
    └── sectionCatalog.ts
```

Разделение ответственности:

- `SectionCatalogPage` — контекст пространства, права, загрузка, ошибки и навигация;
- `SectionTreeEditor` — раскрываемое дерево, DnD (если не readonly);
- `SectionTreeRow` — шеврон, карандаш, название;
- `SectionEditorDialog` — создание/правка/удаление в одном компактном диалоге;
- тот же editor в `readonly` на `SpaceDetailPage` (без карандаша и DnD);
  группировку `RuleListPanel` по секциям **не** делать;
- `useSectionCatalogEdit` — рабочая копия, вызов сервиса, persist в store;
- `AbilitySectionTreeService` — нормализация, мутации, validation;
- `sectionCatalog` — localStorage, dirty относительно published latest.

Новые типы формы, если они понадобятся, разместить в `Dto/`, а не объявлять в `.vue` или `Service/`.

## 6. Жизненный цикл данных

При открытии редактора:

1. загрузить текущее пространство через существующий `SpaceContextLayout`;
2. `resolveLatestRevision` + `fetchRevision`, затем `syncFromContext(..., 'draft', latest)` — база для `PublishDialog`;
3. проверить локальный draft по `spaceId`;
4. взять draft при наличии, иначе клонировать опубликованный каталог;
5. нормализовать и валидировать рабочую копию.

При мутации:

- прогнать `validate` + `validateRuleSections` по effective rules;
- при ошибке не писать store;
- иначе `sectionCatalog.saveDraft` (localStorage, без HTTP).

При сбросе: `discardDraft`, рабочая копия = latest.

При публикации:

- не новый API;
- существующий `PublishDialog` / `commitDraft`;
- ключ `sections` только если `isDirty` относительно latest;
- `spaceRevision.commitDraft` уже вызывает `sectionCatalog.discardDraft`; `PublishDialog` отдельно сбрасывает draft правил — не дублировать discard секций в UI.

## 7. Работа со старыми ревизиями

`/space/:code/:revision/sections` — read-only снимок `activeRevision.sections`. Pending-каталог только на `draft`.

Ссылка «Секции» в шапке детали сохраняет `:ctx`.

Список правил на детали по секциям не группировать в этом шаге.

## 8. Тесты

### Доменный сервис

- добавление корневого узла;
- добавление дочернего узла;
- `updateSection` с изменением имени;
- `updateSection` со сменой `parentCode`;
- `updateSection` со сменой `sortOrder`;
- drop: новый parent у узла + normalize братьев (не одна строка, если сдвинулись соседи);
- отказ drop в собственного потомка (цикл);
- отказ патчить `code`;
- нормализация порядка;
- дублирующийся code;
- неизвестный parent;
- цикл;
- дубль `catalogRootFor`;
- удаление листового узла;
- отказ удалить узел с детьми;
- отказ удалить секцию, на которую ссылается правило;
- сохранение неизвестного `catalogRootFor`.

### Store/composable

- без draft рабочая копия = latest;
- восстановление draft по `spaceId`;
- мутация пишет store и `isDirty`;
- `discardDraft` возвращает published;
- пространства не пересекаются;
- битый localStorage — существующий persist (`storageDiscarded`).

### Интеграция публикации

- не dirty → в `commitDraft` нет ключа `sections` (шаринг latest);
- dirty → ключ `sections` есть;
- catalog-only: dirty + пустые put/removed — существующий `PublishDialog` / mock HTTP;
- `SpaceDetail` с `:ctx` = номер не подмешивает draft-дерево в список правил;
- `/space/:code/:revision/sections` показывает снимок ревизии без draft;
- `/space/:code/draft/sections` делает `syncFromContext(..., 'draft')` до `PublishDialog`.

Не обещать новые PHP-тесты иммутабельности снимка: это уже план 6.

UI-mount тесты с Vuetify не требуются.

## 9. Не входит

- отдельный `Roleplay/RuleSection`;
- backend action `ruleSpace.updateSections`;
- серверный рабочий draft;
- изменение модели `section_version`;
- редактирование исторической ревизии;
- отдельная страница/роут правки одной секции;
- боковая форма вместо диалога;
- таблица / SmartGrid как основной вид каталога;
- обязательная новая DnD-библиотека (сначала HTML5);
- автоматическое каскадное удаление дерева;
- группировку списка правил по секциям на `SpaceDetailPage`;
- отдельную кнопку «сохранить черновик» как второй слой persist;
- смену `code` после `addSection`;
- изменение `AbilitySection` в `CatalogSection` без отдельного решения;
- новый каталог глобальных секций и отдельные права секций.

## 10. Порядок реализации и гейт

1. Расширить `AbilitySectionTreeService` (add/update/remove + unique `catalogRootFor` + ссылки правил) и тесты.
2. `useSectionCatalogEdit`: latest + `syncFromContext('draft')`, persist на мутацию.
3. Раскрываемое дерево, DnD → `updateSection`, диалог по карандашу; read-only дерево на `SpaceDetailPage`.
4. `SectionCatalogPage`, sibling-маршрут `sections`, права canEdit.
5. Ссылка с детали пространства, dirty-чип (уже есть логика `isDirty` для publish).
6. Подключить `PublishDialog` без второго commit-пути.

Гейт завершения:

```text
npm run format
npm run lint
npx vue-tsc --noEmit
npm run test
```

## 11. Связанные документы

- [`rulespace-plan-06.md`](rulespace-plan-06.md) — серверная модель снимков секций;
- [`rulespace-plan-10.md`](rulespace-plan-10.md) — существующий Vue-модуль RuleSpace;
- [`rule-system.md`](rule-system.md) — каталог и ревизии;
- [`architecture.md`](architecture.md) — границы модулей;
- [`frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md) — правила реализации.
