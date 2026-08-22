# Контекст ревью 11 — Messages/Notifications (повторное ревью после волны 10)

Волна 2026-08-04. Ревью модуля `Messages/Notifications` на
`frontend-rules.md` + общее ревью. Модуль уже проходил ревью в волне 10
(спека-форма, F17, бул-фильтр, двойной fetch); здесь — проверка текущего
состояния и точечные правки по остаточным находкам.

## Решения пользователя

- **Действия уведомлений (action.key) — оставить как есть**: `actions` — только
  отображение кнопок + маркировка прочитанным по `id`; ключ игнорируется до
  появления реальных серверных действий. `markAsRead(id)` без мёртвого `key`
  (решение волны 10) остаётся.
- **P1 — payload кнопок шаблона**: `buttonsJson` всегда слать массивом `form.buttons`
  (пустой `[]` допустим), не сводить к `undefined` при отсутствии кнопок.
- **P3 — ошибки действий отдельно от ошибки загрузки**: новое поле `actionError`
  в `notificationsStore`; `store.error` остаётся только для загрузки списка.

## Что проверялось и подтверждено

- **Анатомия/слои**: `Dto/`, `Interface/`, `Enum/`, `Service/` (+`Service/Spec/`,
  `Service/Instance/`), `Constant/` (+подпапки), `Component/`, `Page/`, `Store/`,
  `Mock/`; в корне только `init.ts`/`routes.ts`. Соответствует правилу 22.
- **Зависимости**: только группа Core (Engine, UI, User) + публичные точки
  (`useNotificationStore()` из shell и `Roleplay/Home`). Межмодульных нарушений
  нет. Правило 28 соблюдено.
- **Один экспорт на TS-файл** ✓ (точки `init.ts`/`routes.ts` — исключение;
  мультиэкспорт в `Mock/` — принятая конвенция, context-10).
- **Спека** `TemplateSpecService` — stateless class-service, синглтон в
  `Service/Instance/templateSpecService` ✓ (правила 24/27).
- **API-сервисы через ServiceLocator + `getXxxApi()`** в сторах ✓ (правило 26).
- **Типизация по слоям**, string-union `NotifFilter`, `import type`, алиас `@/`,
  без `any` ✓ (правила 43–46).
- **Кросмодульный рефакторинг фильтра** (волна 10): `TemplatesListPage` на
  `useGridPage` + `FilterBar` + `SmartGrid`; сторы без `filterXxx`/`filteredXxx`.

## Findings и что сделано

- **P1 (баг реального бэка)**: `TemplateSpecService.buildCreatePayload/`
  `buildUpdatePayload` отдавали `buttonsJson: buttons.length>0 ? buttons : undefined`.
  Нельзя было (а) создать шаблон без кнопок, (б) очистить все кнопки при
  редактировании (update с `undefined` не обнуляет). **Фикс**: всегда `form.buttons`
  (массив, пустой допустим); типы `Create/UpdateTemplateData.buttonsJson` остались
  опциональными по сигнатуре.
- **P2 (доменный разрыв, отложено по решению)**: `action.key` эмитится
  компонентами, но не используется потребителями (`NotificationsPage`,
  `NotificationSlider` → `store.markAsRead(id)`). Решение: оставить как есть.
- **P3 (F17)**: `markAsRead`/`markAllAsRead` писали ошибку в `store.error` —
  поднимался алерт всего списка с кнопкой «Повторить» (нецелевой повтор
  действия). **Фикс**: новое поле `actionError` в сторе; отображается отдельным
  алертом с `closable` в `NotificationsPage` и `NotificationSlider`.
- **P4 (тесты)**: в модуле не было `__tests__/`. Добавлен
  `__tests__/Service/templateSpecService.test.ts` (10 тестов: createEmpty/button,
  add/remove, fill, buildCreate/UpdatePayload, регрессия P1 на пустых кнопках,
  round-trip). Критичная логика сборки payload — под стратегию F8.

## Отмечено, не блокировало

- `CreateTemplateData`/`UpdateTemplateData` типизируют `buttonsJson` через
  `NotificationTemplate['buttonsJson']` — сцепление с источником; оставлено.
- Валидация regex ключа шаблона инлайн в `:rules` `TemplateEditPage` — допустимо
  для Vuetify, не вынесено в `Utils`.
- `NotificationSlider` показывает `store.items` (первая страница) — без
  индикатора загрузки; слабый пункт, не рефакторили.

## Выполнено

**Документация:** этот файл.

**Код (модуль Notifications):**
- `Service/Spec/TemplateSpecService.ts`: `buttonsJson` всегда массивом.
- `Store/notifications.ts`: новое поле `actionError`, ошибки действий не пишут в
  `store.error`.
- `Page/NotificationsPage.vue`, `Component/NotificationSlider.vue`: отображение
  `actionError` отдельным алертом.
- `__tests__/Service/templateSpecService.test.ts`: новый (10 тестов).

## Закрытие волны

**Состояние:** `vue-tsc --noEmit` чисто, `vitest run` 213/213 (было 203),
`npm run lint` чисто, `npm run format:check` чисто.

Ссылки: `frontend-rules.md`, `docs/review/context-10.md` (предыдущая волна,
этот модуль + универсальный фильтр), `src/modules/Messages/Notifications/`.
