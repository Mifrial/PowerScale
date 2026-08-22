# Контекст ревью 10 — Messages/Notifications + эскиз универсального фильтра

Волна 2026-08-03. Ревью модуля `Messages/Notifications` на
`frontend-rules.md` + общее ревью. Отдельно проработан дизайн
универсального фильтра-бара (кросмодульный рефакторинг — отложен).

## Решения пользователя

- **Спека-форма «Шаблон» → класс-сервис (stateless)**
  `Service/Spec/TemplateSpecService`: `createEmpty*`, `createEmptyButton`,
  апдейтеры кнопок (`addButton`/`removeButton`), `fill`, `buildCreatePayload`,
  `buildUpdatePayload`. Файлов функций в `Service/` нет; синглтон —
  `Service/Instance/templateSpecService`.
- **Бул-фильтр**: строковый round-trip (`'' | 'true' | 'false'` + `=== 'true'`)
  — запах. В модуле — raw `boolean | undefined` + сравнение `===`. Кросмодульная
  унификация — в конце сессии.
- **`extractFilterValue` — костыль**: без контекста поля (тип+options),
  сводит всё к строке, обслуживает три разные семантики (string/bool/datetime),
  datetime-диапазоны в `UsersListPage` молча не работают (для from/to/interval
  у `DateTimeFilterValue` нет `value` → `''`).
- **Универсальный фильтр-бар**: модуль объявляет `FilterField[]`, Core/UI сам и
  фильтрует, и отображает (чипы). Сейчас фильтрация размазана по сторам
  (`filterName`/`filterActive` + `filteredXxx`), дисплей уже generic
  (`FilterChipService`).
- **Расширяемость — дизайн «Гибрид»** (выбран):
  `Interface/Filter/IFilterHandler` + `Service/Filter/BaseFilterHandler`
  (базовые типы) + `Service/Instance/filterHandler` (дефолт) +
  `registerFilterHandler(type, handler)` через плагинную точку `init.ts`
  донора. Consumers (`useGridPage`, чипы) резолвят по `field.type`.
  DimensionalNumber-параметр — критерий приёмки.
- **Реактивность в гибриде**: не теряется, если фильтрация живёт в `computed`,
  читающем `appliedFilters.value` и `getItems()` (стор) внутри вычисляемого
  значения; резолв обработчика по типу — статический; обработчик stateless
  (`private readonly`), в reactive state не кладём.
- **Кросмодульный рефакторинг фильтра → отложен на конец сессии**.
- **Mock-файлы**: множественные экспорты — существующая конвенция
  (Core/User mockUsers и др.); точечную правку не делаем, ре-экспорт типов убран.

## Проблемы модуля Notifications (обнаружено)

- `TemplatesListPage`: row-menu `view-profile`/`Посмотреть профиль` — копия
  user-grid, не по смыслу.
- F17: `save`/`handleDelete`/`fetchTemplates` — ошибки только `console.error`,
  тихие провалы, нет состояния ошибки/повтора; `notificationsStore.fetchData`
  вообще без catch.
- `TemplateEditPage` перегружен: форма + сборка payload + глубокие мутации
  `buttons` inline; редактор кнопок — вторая задача (дочерний компонент).
- `notificationsStore`: двойной fetch (watcher filter/search + watcher page)
  при page>1; `SLIDER_LIMIT=8` vs `pageSize=6` (слайдер показывает ≤6).
- `NotificationAction.color: 'outlined'` — вариант, не цвет; в
  `NotificationItem` маппится к primary.
- `notificationsStore.markAsRead(payload)` — `key` не используется.
- `mockNotifications.ts` — лишний `export type { ... }` ре-экспорт.

## Выполнено

**Документация:** этот файл.

**Код (модуль Notifications):**
- A: `TemplatesListPage` row-menu → `edit`/`Редактировать`/`mdi-pencil`;
  `onRowAction` → `open`/`edit`.
- E-loc: бул-фильтр → raw `boolean`; `Store/templates` сравнение `===`.
- D: один источник fetch в `notificationsStore`; убран мёртвый
  `SLIDER_LIMIT`/`notificationSliderConfig.ts`.
- C: `Service/Spec/TemplateSpecService` + `Service/Instance/templateSpecService` +
  `Dto/TemplateForm` + `Component/TemplateButtonsEditor`; `TemplateEditPage` —
  только UI.
- B: F17 — `error`-состояния в сторах и страницах, повтор; не-тихие
  `save`/`handleDelete`.
- E: убран `export type` ре-экспорт; `NotificationAction.color` →
  `'primary' | 'error'`; `markAsRead(id)` без мёртвого `key`.

## Кросмодульный рефакторинг (универсальный фильтр, гибрид)

Реализован по дизайну «дескриптор типа поля — одна регистрация на тип».

**Core/UI — слой интерпретации:**
- `Interface/Field/IFieldTypeInterpreter.ts` (isActive/predicate/compare/format);
  `Interface/Field/FieldTypeDescriptor.ts` (interpreter + cell? + filterWidget?).
- `Service/Field/BaseFieldTypeInterpreter.ts` — базовые типы (string/number/
  boolean/select/active/date/datetime); в т.ч. починён datetime-фильтр
  (раньше в `UsersListPage` диапазоны по `lastLogin` молча не работали).
- `Service/Field/FieldTypeRegistry.ts` (register/get) + `Service/Instance/*`
  (`fieldTypeRegistry`, `baseFieldTypeInterpreter`).
- `Service/Field/initBaseFieldTypes.ts` — регистрация базовых типов
  (ячейка + виджет + интерпретатор). Заменил `initBaseRenderers` +
  `initBaseFilterHandlers`; удалены `rendererRegistry` и `filterHandlerRegistry`.
- `Composables/useFilteredRows.ts` — универсальная фильтрация (поля по типу +
  `q`-поиск по `searchFields`); реактивность в `computed` (appliedFilters + getItems).
- `Composables/useGridPage.ts` — фильтр + типозависимая сортировка (compare) + пагинация.
- `FilterChipService` — тонкий, делегирует интерпретатору.
- Удалены `extractFilterValue.ts`, `extractStringFilter.ts`.

**Миграция модулей** (убраны `filterXxx`/`filteredXxx` из сторов):
- Templates → `useGridPage({ getItems, fields, columns })`.
- Users → `useGridPage(..., searchFields: [name,surname,nickname,login,email])`.
- Groups, Keywords → `useGridPage`.
- Spaces (карточки) → `useFilteredRows({ getItems, fields, searchFields: [name,description] })`.

**Правка frontend-rules.md:** добавлен буллет «Склонность к ООП», уточнён
буллет про generic-хелперы.

**Тесты** (+20): `BaseFieldTypeInterpreter`, `FieldTypeRegistry`, кастомный
`dimensionalNumber` (доказательство плагинного механизма: модуль регистрирует
свой тип — фильтр и сортировка по величине через `DimensionalNumber.toNumber()`).

## Закрытие волны

**Состояние:** `vue-tsc --noEmit` чисто, `vitest run` 203/203 (было 183),
`npm run lint` чисто, `npm run format:check` чисто.

Ссылки: `frontend-rules.md`, `docs/review/context-9.md` (предыдущая волна),
`src/modules/Messages/Notifications/`, `src/modules/Core/UI/`.
