# План Core/UI 1 — режимы client/server для грида

**Статус:** сделано, 2026-09-09. Дальше — [`core-ui-grid-plan-02-user-lists.md`](core-ui-grid-plan-02-user-lists.md), затем [`logger-plan-04-view.md`](logger-plan-04-view.md). Канон фронта — `draft-front_1.2ds/frontend-rules.md`. Общая архитектура — [`architecture.md`](architecture.md).

Цель: общий data-layer для `FilterBar` + `SmartGrid` с режимом `client | server`. Компоненты остаются controlled UI и не знают HTTP. Переключение — параметр composable, не проп `SmartGrid`.

## Текущее состояние

`SmartGrid` принимает `rows`, `total`, `pagination`, `loading`, `sort` и эмитит `update:pagination` / `update:sort`. Footer считает страницы из `total / perPage`. Пустого каталога у грида нет: `v-data-table` просто без строк. Сообщение «пусто» — на странице по `total === 0`, не по `rows.length === 0` (короткая последняя страница валидна).

`FilterBar` коммитит `Record<string, FilterValue>` через `v-model`. Поля popup — по Apply. Строка поиска `q` коммитится с debounce 200 мс внутри `useFilterBuffer` уже сейчас. Глобальный debounce в server-layer не дублировать.

`useGridPage` — полный массив в браузере. Три call site грида: Keyword, Mechanic, Notifications templates. Ни один не читает `filteredRows` из него — только `pageRows` / `appliedFilters`. `useFilteredRows` отдельно живёт на карточных списках (Characters/Games/Spaces и вкладки Character) — без pagination, этот план их не трогает.

`UsersListPage` / `GroupsListPage` уже ходят на backend `findPage` вручную (`limit`/`offset`, `watch` на pagination+filters, `useAbortable`). Сортировка колонки у них **локальная по текущей странице** и **не** уходит на сервер. Это не эталон server-режима и не мигрируется этим планом.

`DataProvider` нигде не используется. Не расширять «на будущее». Не удалять в этом заходе.

`useAbortable` в Engine — один `AbortController` на жизнь компонента (abort при unmount). Для смены query его недостаточно: нужен свой controller **на каждый** `loadPage`.

`SmartGrid.onColumnSort` уже эмитит `update:sort` и следом `update:pagination` с `page: 1`. Server-composable не должен из-за этого делать два fetch.

Именованные типы — только `Dto/` / `Interface/` / `Enum/`, не внутри composable (`frontend-rules.md` §3).

## Решения

### 1. Слой данных

```text
src/modules/Core/UI/
  Composables/useGridData.ts          # единственный вход грида
  Enum/GridDataMode.ts                # 'client' | 'server' — string-literal union, не TS-enum
  Dto/Grid/GridQuery.ts
  Dto/Grid/GridPage.ts
  Dto/Grid/GridDataOptions.ts         # ClientGridOptions | ServerGridOptions
```

`useGridPage.ts` **удалить** в этом заходе. Двух публичных входов не держать: адаптер «старое имя без mode» хуже, чем три правки call site. `useFilteredRows` остаётся — это фильтр без грида, не второй вход pagination. Отдельный `ServerGrid.vue` не делать. Loader — поле options, не сервис-интерфейс. Типы options не объявлять в composable.

```ts
interface GridQuery {
  page: number;
  perPage: number;
  sort: Sort | null;
  filters: Record<string, FilterValue>;
}

interface GridPage<T> {
  rows: T[];
  total: number;
}
```

`useGridData` отдаёт одну форму для обоих режимов:

```ts
{
  rows,          // текущая страница (бывший pageRows)
  total,
  pagination,
  sort,
  filters,       // бывший appliedFilters
  loading,       // client: false (спиннер каталога остаётся у store страницы)
  error,         // client: null
  reload,        // client: no-op; server: повтор текущего query
  onPaginationChange,
  onSortChange,
  onFilterChange,
}
```

`filteredRows` (полный отфильтрованный массив) из публички грида **не** отдаём: трём потребителям он не нужен; карточки как брали `useFilteredRows`, так и берут.

### 2. Discriminated union

```ts
type ClientGridOptions<T> = {
  mode: 'client';
  getItems: () => T[];
  fields: FilterField[];
  columns: ColumnDefinition[];
  searchFields?: string[];
};

type ServerGridOptions<T> = {
  mode: 'server';
  loadPage: (query: GridQuery, signal: AbortSignal) => Promise<GridPage<T>>;
};
```

`fields` / `columns` в server-options не держать: фильтрация и интерпретация sort — на backend / в adapter модуля. FilterBar и колонки грида остаются на странице.

Не optional-мешок. Не `any`. Call site client **обязан** передать `mode: 'client'` — это цена одного входа, не скрытый default.

При fetch вызывать **актуальный** `loadPage` с options (не кэшировать стрелку с первого setup). Watch — только содержимое query.

### 3. Client и миграция трёх списков

Логика как у нынешнего `useGridPage`: `useFilteredRows`, локальный sort по interpreter колонки, slice страницы, `total` = длина отфильтрованного массива. `getItems()` читается из computed — реактивность store сохраняется.

В этом заходе перевести:

- `Keyword/Composables/useKeywordList.ts`
- `Mechanic/Composables/useMechanicList.ts`
- `Notifications/Page/TemplatesListPage.vue`

на `useGridData({ mode: 'client', getItems, fields, columns })`. В шаблоне `:rows="rows"` вместо `pageRows`; `filters` вместо `appliedFilters`. `store.loading` / `store.error` каталога не подменять флагами composable (client их не грузит). Поведение фильтра/sort/page — без регрессии.

### 4. Server

Composable **не** фильтрует, **не** сортирует и **не** slice'аит `rows`. Иначе повторится ловушка Users: sort только текущей страницы.

Поведение:

1. Хранит `filters`, `pagination`, `sort` отдельными refs. Fetch — `watch` на computed `GridQuery`, не на identity `loadPage`.
2. Первый `loadPage` сразу (`immediate: true`). Стартовые значения как у `useGridPage`: `page: 1`, `perPage: 10`, `sort: null`, `filters: {}`.
3. Один in-flight на актуальный query.
4. Результат → `rows` / `total`. Поле `Pagination.total` не использовать: total уже отдельный проп `SmartGrid`.
5. `loading` true на время актуального запроса; предыдущие `rows` оставить на экране (не вспышка пустой таблицы).
6. Смена filters: `page = 1` в том же тике, один load.
7. `onSortChange` сам ставит `page = 1`. Два синхронных emit грида (`update:sort` + `update:pagination` с page 1) попадают в один flush Vue → один fetch. Не debounce «на всякий».
8. Смена только `page` или `perPage`: filters/sort те же. `GridFooter` при смене perPage уже шлёт `{ page: 1, perPage }`.
9. `perPage` из существующих `perPageOptions` (5…100). Потолок HTTP конкретного API — в adapter модуля, не в UI.
10. `Core/UI` не знает `limit`/`offset`/action names. Adapter: `offset = (page - 1) * perPage`.
11. Пусто: страница смотрит на `total === 0`. `rows.length < perPage` при `total > 0` — норма.
12. Если `total` сжался и текущая `page` за концом — **не** авто-прыгать на последнюю (client сейчас тоже отдаёт пустой slice). Retry — кнопка `reload`.
13. `loadPage` reject: `error` = `Error.message` или короткая общая строка; не класть trace. Abort (`DOMException` name `AbortError`, как в `HttpClient`) — не `error`.
14. `loading` / `error` / `reload` живут в composable. Pinia модуля не обязана дублировать те же флаги, если страница сидит на `useGridData`.

### 5. Гонки

На каждый новый query: abort предыдущего `AbortController`, новый `signal` в `loadPage`. Generation token как страховка, если loader проглотил abort.

- Устаревший resolve не пишет `rows` / `total` / `error`.
- Abort и `AbortError` — не ошибка UI.
- Unmount — abort.
- Повтор того же query (сравнение сериализованного `GridQuery`, не identity `filters`) — только через `reload`.
- Смена ссылки `loadPage` без смены query — не fetch.

`signal` в loader **обязателен** (не optional): иначе легко забыть abort.

### 6. Фильтры

`FilterBar` без режима. Server получает уже committed `FilterValue`. Mapping в DTO модуля — в `loadPage` того модуля.

Строка `q` уже debounce 200 мс в FilterBar. Server-layer не добавляет второй debounce. Popup Apply — как сейчас: HTTP только после commit, не на каждый клик в меню.

### 7. SmartGrid

Без `loadPage` / `mode`. HTTP только из обработчиков страницы → composable.

Минимальная правка грида — только если без неё нельзя отличить «идёт запрос» от пустых `rows` (уже есть `loading`). Визуал footer/колонок не менять.

### 8. Sort

Контракт несёт `Sort | null`. Модуль решает, уходит ли sort на backend. Запрещённые ключи — 400/INVALID на API, не тихий client-sort. Фиксированный порядок (Users/Groups сейчас, Logger потом): колонки `sortable: false`, в query всегда `sort: null`. Не подписываться на `update:sort` зря.

### 9. Границы

`Core/UI` не импортирует User/Logger/Rule. Pinia грида в UI нет. Состояние списка — composable на странице или store модуля (как Users `findPage`).

Этот план **не** пишет Logger и **не** переводит Users/Groups. Очередь: план 1 (этот файл) → план 2 (Users/Groups на server-режим) → Logger 4.

## Тесты

Без mount Vuetify.

Client (регресс бывшего `useGridPage`):

- фильтр / sort / page как сейчас;
- `getItems` подхватывает смену массива;
- `rows` — slice страницы, не весь отфильтрованный массив.

Server:

- mount → один `loadPage` с page 1 и пустыми filters;
- filter change → page 1 и один вызов;
- `onSortChange` + `onPaginationChange({page:1})` подряд в одном тике → один вызов;
- новая стрелка `loadPage` без смены query → нет fetch;
- reject пишет `error`, abort — нет;
- pagination change не меняет filters;
- `{ rows, total }` в модели;
- `loading` / `error` / `reload`;
- второй ответ раньше первого не побеждает;
- abort не ставит `error`;
- тот же query сам не refetch;
- `reload` повторяет текущий query;
- `rows` после load не пересортировываются composable.

## Порядок работ

1. `Enum/GridDataMode`, DTO `GridQuery` / `GridPage` / `GridDataOptions`.
2. `useGridData` + тесты client-ветки (перенос логики `useGridPage`).
3. Три call site → `mode: 'client'`; удалить `useGridPage.ts`.
4. Server-ветка: load, coalesce, loading/error/reload.
5. Abort + generation.
6. Users/Groups и Logger в этом заходе не писать.

## Гейты

```text
npm run format
npm run lint
npx vue-tsc --noEmit
npm run test
```

Dev-сервер не запускать.

## Не входит

- HTTP, PHP, SmartTable;
- URL query string;
- `ServerGrid.vue`;
- миграция Users/Groups — [`core-ui-grid-plan-02-user-lists.md`](core-ui-grid-plan-02-user-lists.md);
- карточки на `useFilteredRows`;
- Logger;
- сохранение `useGridPage` как второго входа;
- кэш, polling, infinite scroll;
- редизайн грида/бара;
- второй debounce поиска;
- трактовка datetime/contains (это adapter модуля).

## Документы захода

этот файл; [`core-ui-grid-plan-02-user-lists.md`](core-ui-grid-plan-02-user-lists.md); [`logger-plan-04-view.md`](logger-plan-04-view.md); [`architecture.md`](architecture.md); `frontend-rules.md`.
