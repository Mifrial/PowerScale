# Хендофф: User 3 HTTP / актор / Auth create

Новая сессия. Сначала добить хвосты кода, потом с Андреем спланировать следующий заход. Не коммитить, пока не попросят. Dev-сервер фронта не трогать.

## Кто ты и канон

PowerScale / Mifrial, бэкенд `www/mifrial/`. Имя пользователя — Андрей.

Читать по задаче, не весь репозиторий:

- `docs/tr/php-coding-standards.md` — PHP
- `docs/tr/TR.md` — канон
- `docs/tr/user-plan-03-http.md` — план захода (статус: сделано)
- `docs/tr/user-roadmap.md` — нарезка
- `docs/tr/auth-plan-01-session.md` — сессия/seed
- `AGENTS.md` — источники правил

Не дублировать стандарты в ответы. Vue / `user_group.*` HTTP в этом хвосте не делать, если Андрей явно не скажет иначе.

## Где остановились

План User 3 реализован: HTTP `user.getList|get|getByIds|update|deactivate` в User; `user.create` только в Auth; актор на `IRequestContext` через `IRequestBinder` (`AuthSessionBinder`, ключ `request_bind` ∈ `ports`); User не импортирует Auth. Quality phpcs + phpunit suites `kernel,user,auth` зелёные после пачки JSON.

Чат разросся; решения ниже уже приняты — не переоткрывать без новой причины.

## Сделано по делу

- Kernel: `RequestActor`, `getActor`/`setActor`, `reset`/`bindIncoming` сбрасывают актора. `Application::dispatchFromRequest`: CSRF в `httpRouteError`, затем `bindRequestActors`, затем `dispatch`. Голый `dispatch()` binder не зовёт.
- User: `IUserAccess`, `IUserViews` (`assemble` + `assembleMany`), `UserHttpService`, `UserMembershipSync`, `UserException extends ActionException` → HTTP 400. CSRF на всех `user.*`.
- Сборщик списка: `UserGroupMemberRepository::getGroupIdsByUserIds` (страницы 500) + `UserGroupRepository::getByIds` (один IN). `UserViewAssembler` зависит от репозиториев, не раздувает `IUserGroups`. Ключи `permissions` в JSON сортируются (`sort`), потому что JSON-поле группы из батча может прийти объектом.
- Bypass-членство: в `replace` guard на **весь целевой набор имён** + на **remove**. На `addMember` второй раз не проверять — эскалации нет.
- Auth: `UserCreateService` + `UserCreateAction`; пустой `groups` → константа `'Игрок'`; сессию созданному не открывать.

## Решения (не спорить)

1. Тонкие action с 7 параметрами — запах формы HTTP. Следующий шаг ядра (не этот хвост, пока не попросят): binder гидрирует DTO, `handle(UserCreateInput $input)`. Пока `?T = null` не отличает «ключа нет» от JSON `null`.
2. Не плодить `RegisterAction` + `VkRegisterAction`. VK — другой протокол (`auth.vk.*`). Класс register можно позже переименовать в `PasswordRegisterAction`, маршрут `auth.register` оставить.
3. Папки модуля: lib + `Setup/` + `tests/` в `modules/Core/{Name}/` — ок.
4. Не Bitrix-`CurrentUser`. Актор уже request-scoped на контексте.
5. **Политика пароля** (minLength и флаги) — **таблица Auth**, сайт целиком. Не колонка группы. Сейчас хардкод `4` в `AuthService` и `UserCreateService`. Отдельный заход Auth.
6. **Автовыдача при register/create с пустым `groups`** — bool **`assign_on_register` на таблице `user_group` (модуль User)**. Не таблица Auth. Auth читает через `IUserGroups` (группы с флагом). Seed ставит флаг на группу, которую сейчас зовут «Игрок». Имя — контент seed, не runtime-ключ. Несколько групп с флагом — ок (выдать все). Константы `'Игрок'` выкинуть из `AuthService::register` и `UserCreateService`.
7. HTTP 401 на `AUTH_REQUIRED` не в этом хвосте (фронт редиректит с 401; без сессии сетка получит 400 — как в плане).

## Сначала правки кода (хвост User 3)

Делать по порядку, с тестом где поведение меняется. `phpcs-quality.xml.dist` + `phpunit --testsuite kernel,user,auth`.

### Обязательно (баг относительно плана §5)

`user.update` с `active=true` **не обнуляет** `deactivate_reason` / `deactivated_until`. План: включить обратно = `active=true` **и** обнуление reason/until. Сейчас `UserHttpService::profilePatch` пишет только `active`. Починить в HTTP-слое (когда в patch есть `active === true`). Mysql-тест: deactivate → update active true → поля пустые.

### Стоит поправить в том же заходе, если дёшево

- **`assign_on_register`**: колонка на `user_group`, нормализатор/карта поля, seed Auth (`BootstrapGroupsStep` на «Игрок»), метод на `IUserGroups` вроде `getAssignOnRegisterNames()` / записей (не раздувать интерфейс ради десятого «на всякий случай» — один метод под сценарий). Register и create с `groups=[]` используют флаг. Нет ни одной такой группы → как сейчас нет «Игрок»: `AUTH_INVALID`. Тесты Auth+User.
- Дубль политики пароля не трогать, пока нет таблицы Auth.

### Не чинить «заодно», только знать

- Транзакции на create/register нет (план). Обрыв после `add` профиля оставляет сироту без identity.
- Чекбокс плана «фейковый binder пишет id через handle» был преувеличен; `dispatch()` binder не зовёт — так и задумано.
- `phpcs:disable` ClassComplexity на `AuthService` из‑за `resolveActor`.
- `IUserViews::assembleMany` на публичном порте; Auth зовёт только `assemble`. ISP терпим: HTTP и Auth делят один порт.
- `UserHttpService` в карте `ports` ради action-фабрик, не для соседей.
- JSON `ids` в `getByIds`: только `is_int` (json_decode целых ок; строки с фронта — `USER_INVALID`).
- Чекбокс плана про Kernel test binder vs `handle` не гонять заново без нужды.

## После правок — спланировать следующий заход

Не начинать большой новый план в коде, пока Андрей не выберет. Кандидаты из roadmap/плана 3:

1. **HTTP групп** — `IGroupApi` / `user_group.*`, `memberCount`, «назначать только свои ключи» (`user-roadmap` §4, `auth-system.md`).
2. **Auth: таблица политики пароля** + один читатель для register/create/`getPasswordPolicy`.
3. **Kernel: DTO-in-binder** для action (снятие 7 nullable параметров).
4. Проводка `remember` во `IAuthApi` / Vue — только если спросят.

Сверить с `user.md` / `auth-system.md` / `architecture.md`, написать короткий план в стиле `user-plan-03-http.md` (решения, слои, todo, не входит), **не кодить**, пока Андрей не утвердит.

## Как работать

- Менять только то, что в хвосте или в утверждённом следующем плане.
- Не импортировать чужой `Service/`. User ↛ Auth. Kernel ↛ Auth.
- Не запускать фронт-dev.
- Коммит — только по просьбе.

## Файлы якоря

`UserHttpService.php`, `UserMembershipSync.php`, `UserViewAssembler.php`, `UserAccess.php`, `UserGroupMemberRepository.php`, `UserGroupRepository.php`, `UserCreateService.php`, `AuthService.php`, `BootstrapGroupsStep.php`, `Application.php`, `RequestContext.php`, `AuthSessionBinder.php`, тесты `UserHttpMysqlTest`, `AuthMysqlTest`, `UserAccessTest`.
