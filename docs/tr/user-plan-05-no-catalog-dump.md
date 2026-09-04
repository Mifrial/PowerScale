# План User 5 — выправить контракт (бэк + Vue)

**Статус:** сделано, 2026-09-03. **Следующий продукт** (таблица политики пароля, `remember`, чужие модули) не стартуем, пока этот трек закрыт. Канон — [`user.md`](user.md), [`auth-system.md`](auth-system.md). Стандарты PHP — [`php-coding-standards.md`](php-coding-standards.md) (§ эскиз Vue). Фронт — [`draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md). Предыдущие HTTP — [`user-plan-03-http.md`](user-plan-03-http.md), [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md).

Цель: один контракт API. Эскиз подтягивается к серверу, не наоборот. Каждый этап — **PHP + Vue + mock + тесты** в одном заходе, без окна «бэк уже другой, UI ещё dump».

## Правила трека

1. `getList` — только SmartTable `IOpenedRecords`. Фасад/HTTP так не называются.
2. JSON-вид — словарь API (`IUserViews` / сборка Group), не Record и не Vue-эскиз.
3. Даты в JSON — **unix int UTC** (как Auth 1). Отображение — Vue `DateTime` / форматтер, не строка «27.07.2026» в DTO.
4. Bypass — флаг (`bypass` / `hasBypass`), не `super_admin`.
5. Display (инициалы, ФИО) — клиент. Сервер не считает `initials`.
6. Нет строки `user` для гостя. Нет `avatar_file_id`, пока нет Files.
7. `ListQuery` только в `Repository/`. HTTP — свой Input-DTO.
8. Dev-сервер Vue по-прежнему запускает пользователь.

## Карта дыр (что выправляем)

| Дыра | Где сейчас | Этап |
|---|---|---|
| Dump «первые 500» без запроса | `user.getList` / `userGroup.getList`; Vue `getUsers` / `getGroups` + клиентский `useGridPage` на всём массиве | A |
| Сетка `lastLogin` как поле User | колонка/фильтр Vue; last login — Auth, на `user` нет | A (убрать с сетки; в JSON профиля — только где Auth отдал) |
| `ensureUsers` → dump, если кэш пуст | `users.ts` | A |
| `super_admin` | JSON User, `AccessService`, моки, тесты | B |
| `email: ""` вместо null | сборщик / Vue `email: string` | B |
| Смесь camel/snake | `lastLogin` vs `deactivated_until` | B |
| `Group.createdAt` string vs unix | DTO/моки vs PHP | B |
| `assign_on_register` snake vs camel JSON | PHP ответ vs Vue может не знать ключ | B |
| `GroupMember.initials` на сервере; у профиля другая формула | PHP vs `Utils/initials` | B |
| `avatar_file_id` на Vue User | нет на бэке | B (снять с DTO) |
| Guest `id: 0` как User | `setGuest` | C |
| `IAuthApi.findUser` / `resetPassword` | Vue и UI-канон есть; PHP в Auth 1 не входил | не этот трек (Auth forgot/reset) |
| Редирект сессии только с HTTP 401 | `HttpClient`; бэк `AUTH_REQUIRED` → 400 | C |
| `CreateUserData.email` required | бэк email не required | B |
| Моки дат «дд.мм.гггг» | `mockUsers` / `mockAuth` | B |
| `getMembers` без страницы | до 500, ок для этапа; пагинация — E если останется боль | E (не блокер A–D) |

Уже верно, не ломать: `groups: int[]`; CSRF; `AUTH_*`→400 как код; subset ключей групп; HTTP не пишет `bypass` группы; `user.create` в Auth.

## Этап A — страница вместо dump

Снять цепочки dump до репы (учётки и группы). Auth их не зовёт.

Взамен **не** пустой список. HTTP:

| Action | Input | `data` |
|---|---|---|
| `user.findPage` | `FindPageInput`: `limit` (1…500), `offset` (≥0), `OptionalString $q`, `OptionalBool $active` | `{ items: User[], total: int }` |
| `userGroup.findPage` | то же + без `active` или с `OptionalBool $active` | `{ items: Group[], total: int }` |

- `q` после trim: пустой/absent — без LIKE; иначе подстрока по **своим** полям (учётка: login, name, surname, nickname, email; группа: name). Не lastLogin.
- Sort фиксированный `id` asc (как сейчас). Произвольный sort сетки — не A (колонки Vue могут сортировать текущую страницу или sort отключаем).
- `total` — `countTotal` ST на том же фильтре, не `count(items)`.
- Фасад: `findPage(...)`, не `getList`. Репа собирает `ListQuery`.
- Guards как у старого list: `user.view` / `user_group.view`.
- `user.getByIds` без изменения.

Vue: `IUserApi`/`IGroupApi` — `findPage` вместо `getUsers`/`getGroups`. Сторы не держат «все учётки мира». `UsersListPage` / `GroupsListPage`: pager → offset/limit сервера; FilterBar в A — `q` + active, **без** `lastLogin`. `ensureUsers` — только `getByIds`, без fallback dump. Combobox групп на форме учётки — `findPage` с `q` (debounce), не предзагрузка 500.

Моки — тот же контракт `{ items, total }`.

Тесты PHP: нет маршрута `user.getList`; findPage total/q/active; cap 500. Vue: стор/api unit по возможности; lint/tsc/test модуля User.

Документы планов 3–4: dump снят, ссылка сюда.

## Этап B — JSON-вид и DTO Vue = сервер

Ломающий контракт, один заход бэк+фронт.

**User JSON** (get / findPage items / getByIds / create / update / getCurrentUser / login / register):

| Ключ | Тип | Заметки |
|---|---|---|
| `id`, `name`, `login`, `active` | как сейчас | |
| `surname`, `nickname` | string или ключ отсутствует | как сейчас optional |
| `email` | `string \| null` | не `""` |
| `groups` | `int[]` | id |
| `permissions` | `string[]` | |
| `bypass` | bool | живой bypass; **без** `super_admin` |
| `registered` | unix int | |
| `lastLogin` | unix int, ключ только если Auth передал | HTTP User find/get — **опускать** (нет денорма). Login/getCurrentUser — как Auth 1 |
| `deactivatedUntil` | unix int или null | не snake |
| `deactivateReason` | string или null | |
| нет | `avatar_file_id` | |

**Group JSON:** `id`, `name`, `active`, `memberCount`, `permissions` (сорт), `createdAt` unix, `bypass`, `assignOnRegister` (camel).

**GroupMember:** `id`, `name`, `login`. Без `initials`. Vue считает инициалы тем же `initials()` что профиль (имя + фамилия; у члена фамилии нет — имя/login, **одна** утилита, не две формулы).

Вход deactivate: `deactivatedUntil` как ключ JSON (binder). Значение по-прежнему **`Y-m-d`** с `<input type="date">` → UTC 00:00 (не путать с unix в ответе). Документировать два контура: календарная дата на входе, unix на выходе.

Vue: переписать `Dto/User`, `Dto/Group`, `Dto/GroupMember`, `CreateUserData.email` как `string | null` опционально; AccessService — `user.bypass`; сетки/карточки форматят unix; моки unix int; тесты access/permissionRegistry.

PHP: `UserViewAssembler`, Group HTTP assemble, тесты JSON.

## Этап C — сессия, гость, дыры Auth на эскизе

- **HttpClient / Engine:** нет сессии = `success: false` + `AUTH_REQUIRED` при HTTP 400. Редирект на login по **коду**, не только status 401. CSRF 403 не считать «вылогинило».
- **Guest:** не объект User с `id: 0`. `currentUser: User | null`; UI гостя без фейковой строки. `setGuest` убрать или заменить флагом «нет актора».
- **`findUser` / `resetPassword` не вырезать.** Это не утечка эскиза. Канон UI — [`auth-system.md`](auth-system.md); Auth 1 сознательно не делал маршруты (`auth.findUser` — enumeration, reset — токены). Страницы остаются на mock, как в Auth 1. Реализация — отдельный заход Auth после D, не этап C.

Тесты: клиент на 400+AUTH_REQUIRED; PHP без новых маршрутов reset.

## Этап D — ворота трека

Чеклист закрытия (всё должно быть да):

- нет `user.getList` / `userGroup.getList` в PHP и Vue;
- нет `super_admin` / `avatar_file_id` / `initials` в JSON и DTO;
- email null; даты unix в ответах; camelCase ключей вида;
- сетка не фильтрует lastLogin на User;
- гость не User#0; AUTH_REQUIRED на 400 ведёт на login;
- phpcs-quality + phpunit `kernel,user,auth`; Vue `format` + `lint` + `vue-tsc` + `test` (гейт фронта).

После D можно: таблица политики пароля Auth; `remember`; пагинация `getMembers` — [`user-plan-06-members-page.md`](user-plan-06-members-page.md); гость — [`auth-plan-04-guest.md`](auth-plan-04-guest.md).

## Этап E — не блокер (после D)

`userGroup.getMembers`: вынесено в [`user-plan-06-members-page.md`](user-plan-06-members-page.md) (`limit`/`offset`/`total`).

Произвольный sort сетки → поля Input. Фильтры сверх `q`/`active`. Keyset.

## Слои (все этапы)

| Слой | Делает |
|---|---|
| Kernel | без изменений, кроме если Engine/HttpClient на фронте |
| User HTTP / Auth JSON | вид и findPage |
| Репозиторий | `ListQuery` для findPage |
| Vue Api/Dto/Store/Page/Mock | тот же контракт |
| Не делает | политика пароля в БД; запись bypass с HTTP; Files/avatar |

## Todo (по этапам)

- [x] **A-php** — снять dump; `findPage` учёток и групп; mysql.
- [x] **A-vue** — Api/стор/сетки/ensureUsers/моки; без lastLogin-фильтра.
- [x] **B-php** — JSON-вид.
- [x] **B-vue** — DTO, AccessService, даты, initials, email, CreateUser.
- [x] **C-vue-auth** — 400 AUTH_REQUIRED; guest не User#0. Reset/findUser не трогать.
- [x] **D-gate** — доки (`user.md`, `auth-system.md` JSON, roadmap, планы 3–4, этот файл, `TR.md`); quality PHP+Vue.

## Не входит в трек (после D)

Таблица политики пароля. `remember`. HTTP 401 как статус (канон 400). Транзакции. Object-ACL. Каталог ключей enum. Vue-имена action ради «как в IUserApi.getUsers».

## Документы захода

этот файл; [`user.md`](user.md); [`user-roadmap.md`](user-roadmap.md); [`user-plan-03-http.md`](user-plan-03-http.md); [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md); [`auth-plan-01-session.md`](auth-plan-01-session.md); [`auth-system.md`](auth-system.md); [`php-coding-standards.md`](php-coding-standards.md); [`TR.md`](TR.md); `draft-front_1.2ds/frontend-rules.md`.

## Альтернативы, если этот план не берём

1. Только снять dump без findPage — админка без списка, фронт «выправлен» в никуда. Нет.
2. Оставить dump «пока <500» — уже отвергнуто.
3. Полный ListQuery в JSON (произвольный filter/sort как ST) — рано; A даёт q/active/page.
4. Идти в политику пароля параллельно — нет, пока D не зелёный.
