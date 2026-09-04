# План User 6 — страница членов группы

**Статус:** сделано, 2026-09-04. Нарезка — [`core-tails-roadmap.md`](core-tails-roadmap.md). Этап E [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md). HTTP групп — [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: `userGroup.getMembers` как `findPage`: не «до 500 id без total». JSON-вид GroupMember не менять (`id`, `name`, `login`).

Guest (Auth 4) не блокер: guard тот же `user_group.view`.

## Решения

### 1. Контракт HTTP

Action тот же: `userGroup.getMembers`. Вход и выход ломают эскиз (как User 5 для list).

**Input** — отдельный DTO (`GetGroupMembersInput`), **не** `FindPageInput` (там `q`/`active`). `IActionInput`:

| Поле | Правило |
|---|---|
| `groupId` | int, группа должна существовать иначе `USER_NOT_FOUND` как сейчас (`getMemberIds` уже зовёт `getById`) |
| `limit` | 1…500 |
| `offset` | ≥ 0 |

Границы страницы — `assertPageBounds` как у `UserGroupRepository` **до** ListQuery (`USER_INVALID`). Не пускать `MapInvalidException` ST наружу. Все три поля Input **required**, без default: нет ключа → `Missing parameter` (как `FindPageInput`). Vue всегда шлёт `limit`/`offset`.

Порядок: **id строки `user_group_member` asc** (явный `sort` в ListQuery). Сейчас `getUserIdsInGroup` sort не задаёт; для страницы не полагаться на молчаливый PK. `getMemberIds` (лимит 500, без offset) **не** переписывать.

**data:**

```json
{ "items": [ { "id", "name", "login" } ], "total": 0 }
```

- `total` — COUNT строк `user_group_member` этой группы, не `count(items)`. Совпадает по смыслу с `memberCount` на `userGroup.get`.
- Страница — срез членств `limit`/`offset`, затем `IUserAccounts::getByIds($ids)`. `getByIds` уже: порядок запроса, нет учётки — нет в результате (лимит пачки 500 = max limit страницы). HTTP собирает GroupMember с этого списка; skip = omit `getByIds`, не 404 всего ответа. Страница может быть короче `limit`. `total` дырки **считает**.
- **Mysql-дырка:** `user_id` required + default restrict. Сироту через `addMember` не вставить; delete учётки в v1 нет. Тест дырки **не требовать**.
- Нет группы — `USER_NOT_FOUND`.
- Guard: `user_group.view` / bypass (`requireKey` как сейчас). `csrf` у `userGroup.getMembers` **не** менять (`true` как остальные userGroup.*).

`GetGroupMembersAction`: больше не `handle(int $groupId)`.

### 2. Фасад

`IUserGroups::getMemberIds($groupId)` **оставить** (Auth, replaceMembership, тесты плана 2). Лимит 500 внутри него — как было.

Новый метод `findMemberPage(groupId, limit, offset): MemberIdPage` `{ ids: int[], total: int }`. **`ids` — `user_id`**, порядок = **id строки членства** asc. Не id членства в `getByIds`. Сначала `getById` группы (`USER_NOT_FOUND`). ListQuery в `UserGroupMemberRepository`: `countTotal`, filter `group_id`, `sort` `['id' => 'asc']`, `select` `user_id` (sort по `id` не обязан быть в select). HTTP не зовёт «все id». `countInGroups` не подменяет страницу (пачка групп без offset).

`IUserGroups` уже на `TooManyPublicMethods` — ещё один метод допустим, не второй порт.

Не тащить `ListQuery` в HTTP / `Interface`.

DTO страницы — рядом с `GroupRecordPage`, не массив из HTTP.

### 3. Vue

Не расширять `FindPageQuery` полем `q`: `getGroupMembers(groupId, { limit, offset }, signal?)` → `FindPageResult<GroupMember>`.

Стор: `groupMembers` + `groupMembersTotal`; `fetchGroupMembers(groupId, query)` как `findPage` — пишет items/total, отдаёт `FindPageResult<GroupMember>`. `clearCurrent` обнуляет total.

`GroupDetailPage`: не SmartGrid. `Pagination` + `GridFooter` (`totalItems` = `groupMembersTotal`), список `v-list`. Watch pagination → limit/offset (как `UsersListPage`). Пустой текст — по **total === 0**, не по пустой странице (offset за концом / дырки). Заголовок карточки может остаться `group.memberCount` (тот же COUNT, не `items.length`). Мок: slice массива + total (сейчас `getGroupMembers` игнор `groupId` — фильтр по группе не обещать).

### 4. Тесты

PHP mysql: total > limit; offset; пустая группа `{ items: [], total: 0 }`; нет группы; guard; `limit` 0 / 501 → `USER_INVALID`. Дырка — не mysql. Missing `limit`/`offset` — уже binder (`Missing parameter`), отдельный mysql не нужен.

Vue: api/мок unit (slice); lint. Страница — pager, не обязательный e2e.

### 5. Не входит

Поиск членов по login. Sort по имени. Keyset. N+1 getById. Смена GroupMember JSON. Пагинация `getGroupIdsOfUser`. Чинить `getMemberIds` без sort. Сироты членства без FK.

## Этапы

### A — PHP

Репа page+total; фасад; Input; HTTP; mysql.

### B — Vue

Api, стор, GroupDetailPage, мок.

### C — доки

Планы 4/5 ссылка; roadmap; TR; quality.

## Todo

- [x] **A-php** — page членств; `{ items, total }`; mysql.
- [x] **B-vue** — query + pager на карточке группы.
- [x] **C-gate** — доки; phpunit `user`; Vue User.

## Документы захода

этот файл; [`core-tails-roadmap.md`](core-tails-roadmap.md); [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md); [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md); [`user-roadmap.md`](user-roadmap.md); [`php-coding-standards.md`](php-coding-standards.md); [`TR.md`](TR.md).
