# План Core/UI 2 — Users/Groups на `useGridData`

**Статус:** сделано, 2026-09-10. Зависит от [`core-ui-grid-plan-01-server-mode.md`](core-ui-grid-plan-01-server-mode.md). HTTP страниц уже есть: [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md). Следующий потребитель журнала — [`logger-plan-04-view.md`](logger-plan-04-view.md), **после** этого файла. Фронт — `draft-front_1.2ds/frontend-rules.md`.

Цель: оставшиеся `SmartGrid` (сейчас только Users и Groups) перевести на тот же `useGridData`, что Keyword/Mechanic/templates после плана 1. Ручной `watch` + локальный sort текущей страницы убрать. PHP `findPage` не расширять.

## Текущее состояние

После плана 1 на `useGridData({ mode: 'client' })` уже сидят Keyword, Mechanic, templates.

Единственные гриды вне контракта:

- `UsersListPage.vue`
- `GroupsListPage.vue`

Оба уже зовут `user.findPage` / `userGroup.findPage` (`limit`/`offset`, `q`, `active`). Сортировка колонки — `localeCompare` **по текущей странице**, на сервер не уходит. Sort HTTP в User 5 сознательно фиксированный `id` asc.

Карточки Characters/Games/Spaces и вкладки Character остаются на `useFilteredRows` без грида.

## Решения

### 1. Server-режим, без нового HTTP

```ts
useGridData({
  mode: 'server',
  loadPage: (query, signal) => userApi.findPage(toFindPageQuery(query), signal),
})
```

То же для групп. Mapping в странице или тонком методе API:

- `limit` = `query.perPage`, `offset` = `(page - 1) * perPage`;
- `q` из `filters.q` (строка FilterBar / search);
- `active` из boolean-фильтра, если есть.

`Core/UI` по-прежнему не знает имён action.

### 2. Sort

Колонки `sortable: false`. `update:sort` не подключать. В `GridQuery.sort` всегда `null`.

Не добавлять `sort` в `FindPageInput`. Не оставлять локальный sort страницы — это и есть баг, который план закрывает.

### 3. Состояние списка

Pagination / loading / error / reload — `useGridData`. Стор по-прежнему может хранить `items` текущей страницы и `total` **если** остальные экраны (профиль, combobox) читают тот же кэш; не дублировать pager в сторе, если страница уже сидит на composable.

`useAbortable` на unmount можно оставить для **других** запросов страницы; fetch списка abort’ит composable своим `signal`.

Двойной `watch(pagination, filters)` на странице снести: его работу делает `useGridData`.

### 4. Поведение

Регресс: фильтр `q`/`active`, смена page/perPage, `total`, пустой список по `total === 0`, retry. Один load на смену фильтра (page → 1). Не вспышка пустой таблицы на loading.

Моки `findPage` без смены контракта.

### 5. Тесты

- mapping `GridQuery` → `{ limit, offset, q, active }`;
- стор/API: страница не сортирует `items` локально;
- unit composable уже в плане 1, здесь не дублировать гонки.

Без mount Vuetify. Гейт фронта как в плане 1. PHP не трогать, phpunit User не обязателен.

## Порядок работ

1. Закрыт план 1 (`useGridData` есть, `useGridPage` нет).
2. UsersListPage → server-режим.
3. GroupsListPage → server-режим.
4. Гейт Vue.
5. Logger **не** начинать, пока оба списка на composable.

## Не входит

- PHP sort/фильтры сверх `q`/`active`;
- Logger;
- карточки без SmartGrid;
- combobox `findPage` на форме учётки (уже свой debounce, не грид);
- смена лимита 500 на 100.

## Документы захода

этот файл; [`core-ui-grid-plan-01-server-mode.md`](core-ui-grid-plan-01-server-mode.md); [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md); [`logger-plan-04-view.md`](logger-plan-04-view.md); `frontend-rules.md`.
