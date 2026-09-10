# План Core/UI 3 — вложенная навигация

**Статус:** план, 2026-09-10. Канон текущего shell — [`ui-system.md`](ui-system.md). Архитектура — [`architecture.md`](architecture.md). Фронт — [`../../draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md). Соседние заходы UI: [`core-ui-grid-plan-01-server-mode.md`](core-ui-grid-plan-01-server-mode.md), [`core-ui-grid-plan-02-user-lists.md`](core-ui-grid-plan-02-user-lists.md).

Цель: одно дерево левого меню. Меню — структура. Кто видит пункт, решает модуль, который его кладёт. Роли, `isAdmin` и route guard в меню не входят.

## Как это устроено

Меню — реестр узлов. На узле нет прав, предикатов и ссылок на User.

- `menuSection` — группа. Может содержать секции и пункты. Секции вкладываются сколько угодно.
- `menuItem` — ссылка на маршрут. Детей нет. Живёт **в корне дерева или** в секции (`sectionId` необязателен).
- `getMenuTree()` не отдаёт секцию, в поддереве которой нет ни одного `menuItem`. Пустая «Администрирование» на старте в выдаче отсутствует.

Донор вызывает `registerMenuSection` / `registerMenuItem`. Не должен показывать пункт этому актору — **не кладёт**. Меню не спрашивает, «можно ли». Shell рисует дерево из реестра.

`isAdmin`, `meta.admin`, `accessService`, матрица групп — **User и роутер**, как сейчас. `AdminSection` с `to`/`icon` уходит (второй вход в sidebar). Узкий список ключей для `isAdmin` остаётся в User — не меню и не дубль экранов. Ключ вроде `keyword.view` может повториться во вкладе Keyword и в этом списке — это не два sidebar.

## Когда модуль кладёт пункт

`register*Module()` в `main.ts` бежит **до** сессии.

- Пункты как нынешний `navItems` (главная, мессенджер, уведомления, пространства, персонажи, игры, пользователи) и секция «Администрирование» — **сразу**, без актора.
- Пункты бывшего admin (группы, признаки, механики, журнал, шаблоны) — **вклад от актора**: модуль кладёт `menuItem`, только если сам считает актора подходящим.

Гость и `/users` vs `user.view` на роуте не чинятся.

## Смена актора

Меню не знает User. Крючок вклада — не поле узла и не фильтр в `SideBar`.

**Регистрация вклада** (Core/UI): `{ id: string, apply(actor: unknown): void }`. Тип `User` в UI не импортируется. `actor === null` — гость или выход. Донор в `apply` приводит аргумент к `User | null` (не читает Pinia) и зовёт `accessService` + `registerMenuItem` или ничего не кладёт. Донор **не** регистрирует заново секцию `administration`.

**Кто запускает.** `CurrentUserSessionService` после `setCurrent` / `setGuest` / `clearCurrent` зовёт порт `onActorChanged(actor)` из конструктора (как порт стора). `applyMenuContributions` вешается в `Service/Instance/currentUserSessionService.ts`, не импортом UI в класс сессии и не через `Core/UI/init.ts` (там не реэкспортировать `useMenuTree` — иначе Vue протечёт в unit-тесты). Auth пишет только сессию. Shell и роутер вкладов не гоняют. Sync до монтирования `AppShell` (логин на auth-layout).

**Снятие узлов вклада.** У вклада свой `id`. На время `apply` реестр ставит текущий вклад: в карту владения попадают только `registerMenu*` **внутри этого** `apply`. Перед следующим `apply` снимаются только эти узлы. Стартовая секция `administration` вкладу не принадлежит. Полный `resetMenuRegistry` — для тестов, не для логина.

Повтор того же `id` узла, пока он жив — ошибка. Повтор после снятия вклада — норма (второй логин).

**Реактивность.** Класс `MenuRegistry` без Vue: число `revision` и `subscribe`. `useMenuTree` в `Composables/` подписывается и отдаёт дерево. SideBar импортирует composable напрямую (UI публичен). `init.ts` реэкспортирует только `register*` / `apply` / `getMenuTree` / `reset` / `assert`, не composable.

## Контракт

```ts
interface MenuSection {
  kind: 'menuSection';
  id: string;
  title: string;
  icon?: string;
  parentId?: string;
  order: number;
}

interface MenuItem {
  kind: 'menuItem';
  id: string;
  title: string;
  to: string;
  icon?: string;
  exact?: boolean;
  order: number;
  sectionId?: string;
}
```

Дерево — отдельные типы: у секции `children`. Корень `getMenuTree()`: `MenuTreeNode[]` (секции и пункты).

Публично Core/UI (`init.ts` — первая точка `register*` у UI):

```text
registerMenuSection(menuSection)
registerMenuItem(menuItem)
registerMenuContribution({ id, apply })
applyMenuContributions(actor)
getMenuTree()
resetMenuRegistry()
assertMenuTree()
```

Сборка: любой порядок; уникальный `id` узла; указанный `parentId` / `sectionId` — существующая секция; без циклов; соседи по `order`, затем `id`. `assertMenuTree()` после стартовых вкладов и после `applyMenuContributions`.

Маршруты — `routes.ts`, не из меню.

Константа id секции `administration` — User. Доноры админ-пунктов импортируют id, секцию не создают повторно.

## Vue

`MenuTree` / `MenuTreeNode`: корень смешанный, `v-list-item` / `v-list-group`.

`SideBar.vue`: пользователь, logout, collapsed, `useMenuTree()`. Без `navItems` и `visibleAdminSections`.

Rail: клик раскрывает drawer, как сейчас.

## Кто что кладёт (текущая IA)

| Пункт / секция | Модуль | Когда |
|---|---|---|
| Главная | Home (`init.ts` + `registerHomeModule`) | старт |
| Мессенджер | Chat (`registerChatModule`) | старт |
| Уведомления | Notifications | старт |
| Пространства | RuleSpace | старт |
| Персонажи | Character | старт |
| Игры | Game | старт |
| Пользователи | User | старт |
| секция «Администрирование» | User | старт |
| Группы, признаки, механики, журнал, шаблоны | User, Keyword, Mechanic, Logger, Notifications | вклад, если модуль решил показать |

`main.ts` вызывает `register*Module`, дерево руками не собирает. `registerUiModule` не обязателен, если реестр создаётся импортом singleton.

## `isAdmin`

Не из меню. Имя `registerAdminSection` оставить. Из `AdminSection` убрать `to` / `icon` / `title` — остаются `id` и `permission`. Так не ломаются `evaluateRouteAccess`, Chat `usePermissions`, `UserProfileSlider`.

## Порядок работ

1. DTO, реестр, сборка, prune пустых секций, `revision`, тесты (корень, вложенность, дубль id, сирота, пустая секция не в дереве).
2. Вклады: id вклада, снятие узлов вклада, `applyMenuContributions`; тест повторного apply без дубля id.
3. `useMenuTree` (не из `init.ts`); `MenuTree`; SideBar.
4. Стартовые корневые пункты вместо `navItems`; Home/Chat `register*` в `main.ts`.
5. Секция `administration`; вклады доноров; `currentUserSessionService` вызывает apply.
6. Узкий реестр ключей `isAdmin`; убрать навигацию из `AdminSection`.
7. Тесты User, Logger, Keyword/Mechanic/Notifications вклада, router access; `resetMenuRegistry` в `beforeEach` рядом с permission-реестром.
8. [`ui-system.md`](ui-system.md), [`architecture.md`](architecture.md) (`init.ts` у UI и Home).

## Тесты и гейты

- сборка и prune;
- вклад кладёт / не кладёт (тест донора);
- второй `apply` того же вклада не бросает дубль id; стартовые пункты на месте;
- `useMenuTree` / revision меняется после apply (если тест без полного Vue — инкремент revision на реестре).

HMR: повтор `register*Module` стартовых пунктов может снова дать дубль id — не чинить отдельным идемпотентным merge в этом заходе; тесты всегда `resetMenuRegistry`.

Гейт: format / lint; `vue-tsc` по ошибкам захода; тесты UI, User, router, доноров с бывшим AdminSection. Dev-сервер агент не запускает.

## Не входит

- права и тип User внутри Core/UI;
- `isVisible` на пункте;
- фильтр дерева в shell;
- `isAdmin` из меню;
- новая IA;
- меню с backend;
- popover в rail;
- editor stages и вкладки игры;
- идемпотентная повторная регистрация стартовых пунктов для HMR.

## Критерий готовности

Один реестр. Пункты в корне или в секциях. Текущие экраны sidebar на месте. Админ-пункты есть только если донор положил их после sync актора. Пустые секции не в `getMenuTree()`. Смена актора не дублирует узлы и обновляет sidebar. Меню без ролей. `navItems` нет.

## Документы захода

Этот файл; [`ui-system.md`](ui-system.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); `frontend-rules.md`.
