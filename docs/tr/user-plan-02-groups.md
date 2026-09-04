# План 2 — группы и членство

**Статус:** сделано, 2026-09-02. Канон — [`user.md`](user.md). Нарезка — [`user-roadmap.md`](user-roadmap.md). SmartTable — [`smarttable.md`](smarttable.md). Права UI — [`auth-system.md`](auth-system.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). План 1 — [`user-plan-01-account.md`](user-plan-01-account.md).

Цель: в том же `Core/User` — группы, членство, глобальные ключи прав, bypass и «нельзя снять последнего». **Нет** HTTP, Auth/cookie, object-ACL, seed «Администраторы»/«Игрок» в проде.

Сосед по-прежнему не зовёт `open`. Порты контейнера: `IUserAccounts` (как есть) + **`IUserGroups`**.

## Решения (закрыты здесь)

**Членство — таблица, не mfv.** Нужны FK restrict на `user` и на группу, поиск «кто в группе» / «группы человека», инвариант последнего bypass. mfv int без FK это не даёт.

**Составной unique** — [`user-plan-07-member-unique.md`](user-plan-07-member-unique.md) (**сделано**): `UNIQUE (user_id, group_id)`, колонки `member_key` нет.

**Ключи прав — mfv string на группе**, не третья таблица. Дубль ключа у одной группы уже режет PK sidecar `(owner_id, value)` и cast списка. Флаг **`unique` на multiple ставить нельзя** (`MAP_INVALID`, план 6; фикстура `UniqueMultipleTable` — отказ карты, не образец). Каталог ключей фронта **не** валидируем (как RFC email): паттерн на нормализаторе, неизвестный ключ Rule/Chat допустим. Пустой ключ / дубль в одном patch → `USER_INVALID`. Object-level — не этот план.

**Bypass — bool на группе** (`bypass` required, default false), не колонка `user` и не магическое имя. **Не больше одной** группы с `bypass = true`, в том числе неактивной: вторая — `USER_INVALID`. Имя вроде «Администраторы» не магия; seed только кладёт туда первого оператора. Живой обход ACL — эта группа и `active`. Permissions у неё могут быть `[]`: bypass не каталог ключей. «Последний админ» = ноль членств в активной bypass-группе (при одном флаге это она). Считаем строки членства, не `user.active`. Флаг `protected` из `data-model.md` **не** вводим (DEFERRED).

**Деактивация группы** — `active` false, строки не delete. Снять bypass/деактивировать группу с единственными remaining bypass-членами — тот же `USER_LAST_BYPASS`, если после этого не останется ни одного членства в активной bypass-группе.

**`memberCount` / список всех групп** — не колонка и не обязательный метод фасада плана 2 (это HTTP-сетка). Тесты ходят `getById` / члены / ключи.

**Seed «Администраторы» / «Игрок»** — не этот заход (нет журнала наката; Auth назначит «Игрок» при register позже). Тесты сами создают bypass-группу.

**Проверка «оператор выдаёт только свои права»** — HTTP + текущий пользователь Auth, не репозиторий.

**`hasBypass` / `getPermissionKeys`** смотрят только **активные** группы (`active === true`). Членство в выключенной bypass-группе ключи и bypass не даёт. Само членство при `addMember` в неактивную группу **можно** (потом включат группу).

**Инвариант LAST_BYPASS** — только членство / `bypass` / `active`. `name` / `permissions` — нет. **Проверка до записи.** `removeMember`: если членство в активной bypass-группе и `countActiveBypassMemberships() === 1` → бросить, строку не delete. `update` группы: если снимаем `bypass` или `active` с группы, которая сейчас активный bypass, и без её членов счётчик стал бы 0, а сейчас > 0 → бросить, `update` не звать. Пустая БД / выключить пустую bypass-группу — ок. Гонка двух `removeMember` как unique login: без `transaction()` (шлюз в фасад не тащим).

**Списки членов/групп пользователя** — `int[]` id, не `UserRecord`/`GroupRecord` (нет N×`getById`). `getList` limit ≤ 500: больше 500 в одном вызове **не** обещаем; страница/keyset — HTTP позже. Репозиторий режет limit 500.

**`ListQuery` и имена колонок** — только `Repository/`. Фасад не составляет фильтр. Счётчик LAST_BYPASS и `hasBypass` — `getList` / `getFirst` членства с путём `group_id.active` / `group_id.bypass` и `countTotal`, не список id групп. `countInGroups([$groupId])` — сколько членов этой группы.

**`UserSchema::install()`** — **каждая** карта: нет физики → `createTable`, есть → `updateTable`. Не «если `user` есть — update всех». Иначе после плана 1 на живой `user` группы никогда не создадутся. Порядок create: `user` → `user_group` → `user_group_member`. Drop в тестах наоборот (иначе restrict). Существующий `UserMysqlTest` после этого захода тоже drop тройки, даже если сценарий только учётка: `install()` ставит все карты.

**mfv `permissions`:** `multiple`, **без** `unique`/`indexed`. Нормализатор: trim, пусто, паттерн, дубль в массиве → `USER_INVALID`.

**Фабрики:** closure `IUserGroups` отдельно от Accounts. Accounts не зависит от Groups. `UserGroups` внутри модуля — два репозитория групп/членства + `UserRepository` (есть ли user), **не** `get(IUserAccounts)` (порт-на-порт не нужен). `open`: группа и членство в фабрике Groups; `user` уже открывает фабрика Accounts — Groups при необходимости открывает `user` ещё раз (дешёвая сумка) или тесты передают тот же `records()`. `UserSchema` не в карте.

**Возвраты фасада:** `getMemberIds` / `getGroupIdsOfUser` → `int[]`; `getById` группы → `GroupRecord`; нет группы/нет user на методах, где нужен существующий субъект → `USER_NOT_FOUND` (`getById` учётки / группы). `removeMember` без строки членства → `USER_NOT_FOUND`.

## Todo

- [x] **tables** — `UserGroupTable` имя `user_group`: `name` string unique required; `active` bool required default true; `bypass` bool required default false; `created_at` datetime required; `permissions` string multiple, без unique (не required: `[]` ок). `UserGroupMemberTable` имя `user_group_member`: `user_id` `new ReferenceField(..., UserTable::class, 'restrict')` required; `group_id` → `UserGroupTable::class` restrict required; `member_key` string unique required. Create: `user` → `user_group` → `user_group_member`.
- [x] **schema** — `UserSchema` ctor: три `IOpenedSchema`. `install()` независимо по каждой карте. Не `open` внутри. Не seed. Не в локаторе.
- [x] **facade** — `IUserGroups`: `getById`; `add` / `update`; `getMemberIds`; `addMember` / `removeMember`; `getGroupIdsOfUser`; `getPermissionKeys`; `hasBypass`. 9 public, без `install`. Нет `IUser`. Снаружи модуля репозитории не торчат.
- [x] **factory** — второй closure на `IUserGroups`. `open` только фабрики и тесты.
- [x] **invariant** — проверка до write (см. решения). Пустая БД: первая bypass-группа и первый член ок.
- [x] **tests-gates** — MySQL skip как ping. Drop member→group→user в setUp/tearDown учётки и групп. Две группы; дубль `name`; дубль членства; сумма ключей двух групп; неактивная не даёт ключи и bypass; член в неактивной остаётся в `members`; hasBypass; вторая bypass-группа → `USER_INVALID`; пустые permissions + bypass; снять последнего bypass-члена → `USER_LAST_BYPASS` и строка на месте; выключить `bypass`/`active` единственной живой bypass-группы при членах → отказ, группа не изменилась; второй bypass-член — первого снимаем; `install()` на уже существующем `user` создаёт две новые таблицы. phpunit suite `user` + cs/quality.

## DTO

Группа: `NewGroup` / `GroupPatch` — `fromNormalized` / `fields()` как учётка. `GroupRecord` — геттеры (`getId`, `isBypass`, `getPermissionKeys`), не `values()`. Отдельный `GroupInputNormalizer` (не раздувать `UserInputNormalizer`). `NewGroup`: `name` required; нет ключа `active`/`bypass`/`permissions` → true / false / `[]`. Не содержит `created_at`. Patch: непустой набор; `permissions` целиком (замена, не merge); пустой patch → `USER_INVALID`. `getMemberIds(groupId)` / `update` / `getById`: нет группы → `USER_NOT_FOUND`. `addMember`: нет user или группы → `USER_NOT_FOUND` (репозиторий учётки / группы, не ждать FK). Неактивный user — членство можно (как неактивная группа).

Членство с фасада: `userId` + `groupId`, не `member_key`.

Нормализатор: trim `name` и каждого ключа права; пустой name → `USER_INVALID`; ключ права после trim пуст → `USER_INVALID`; паттерн ключа — узкий (латиница/цифры/`_`/`.`, есть точка: `user.view`). Имена свойств = колонки, кроме `member_key`.

`created_at` на add группы: `DateTime::now()` в фасаде групп, как `registered_at` у учётки.

## Ошибки

Листья: существующие `USER_*` + **`USER_LAST_BYPASS`**. Unique имя группы / пара членства → `USER_DUPLICATE`. Reference на несуществующего user/group → не маскировать в LAST_BYPASS: нет строки user/group заранее `USER_NOT_FOUND`; гонка FK → `REFERENCE_CONSTRAINT` SmartTable **не** маскируем (как DDL). `write()` членства: unique → DUPLICATE; row not found → NOT_FOUND; field/map → INVALID — тот же кривой, но локальный, перевод, что у учётки.

## Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `UserGroupTable` / `UserGroupMemberTable` | `Table/` | карты | HTTP |
| DTO групп | `Dto/` | имя, bypass, ключи | `member_key` |
| `IUserGroups` | `Interface/Service/` | фасад для Auth/HTTP позже | `open`, схема |
| `UserGroups` | `Service/` | trim, now, инвариант bypass, объединение ключей | `ListQuery`, `member_key` |
| репозитории группы и членства | `Repository/` | records, `member_key`, getUnique | публичный порт |
| `UserSchema` | `Schema/` | все карты User | seed |

## Не входит

HTTP `user_group.*`. Auth/cookie. Object permissions / space / game. Каталог ключей как закрытый enum PHP. Seed install-local. `protected` членство. `memberCount` на записи. Delete группы. Назначение «Игрок» при register. CLI setup Kernel (план отдельно). Составной UNIQUE SmartTable.

## Документы захода

этот файл; [`user.md`](user.md); roadmap; [`TR.md`](TR.md); [`auth-system.md`](auth-system.md) (bypass — группа, не `super_admin`).

## Следующий заход

Auth 1 — [`auth-plan-01-session.md`](auth-plan-01-session.md). HTTP учётки — [`user-plan-03-http.md`](user-plan-03-http.md). HTTP групп — после плана 3.
