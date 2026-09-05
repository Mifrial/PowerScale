# План User 8 — memberCount через aggregate

**Статус:** сделано, 2026-09-05. Хвост User 4 после [`smarttable-plan-17-aggregate.md`](smarttable-plan-17-aggregate.md). HTTP — [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Chat 5 этот метод **не** трогал.

Цель: `memberCount` без `getList` страницами по 500 и инкремента в PHP. JSON Group и `IUserGroups` не менять.

## Сейчас

`getCountsByGroupIds`: один `aggregate` (`filter group_id in`, `group group_id`, `CountField('member_count')`, `limit` 500). Пустой `in` → `[]` без ST. Промах группы → 0 (нулевая карта до SQL). Вызов: `GroupHttpService` findPage/get (страница групп ≤ 500).

Было (до этого файла): `getList` страницами по 500 и `++` в PHP.

## Решения

Один `aggregate` на той же карте: `filter group_id in`, `group group_id`, `select group_id` + `CountField('member_count')`, `limit` 500. **Без TTL** (HTTP групп кэш не включает). Пустой `in` → без ST. Промах группы по-прежнему 0 в репозитории, не в ST.

Сигнатура и имя метода те же. Не публичный порт COUNT. Не `countTotal` getList. Не JOIN. Не `SubqueryValue`. `getGroupIdsByUserIds` страницами — не этот файл.

`MapInvalidException` агрегата → `USER_INVALID` как у остальных map в `write()`. phpcs:disable на классе не снимать.

Тесты: mysql HTTP — пустая группа 0, группа с членами — COUNT; не раздувать `UserGroupsMysqlTest`. Не phpunit Chat.

## Todo

- [x] **repo** — `aggregate`; нули; пустой `in`; без TTL; выкинуть page/`++`.
- [x] **http-mysql** — miss 0 и ненулевой COUNT на findPage/get.
- [x] **canon** — этот файл; roadmap User; User 4; ST 17/roadmap/`smarttable.md`; `TR.md`.
- [x] **gates** — phpunit `user`; cs/quality User. Не `cs-fix` чужое дерево.

## Не входит

Vue. Смена JSON. 11-й метод фасада. TTL. `getGroupIdsByUserIds`. `countInGroups` / bypass COUNT. Chat unread. `COUNT(col)`.

## Слои

| Тип | Задача | Не делает |
|---|---|---|
| `UserGroupMemberRepository` | один `aggregate` | HTTP, `ListQuery` COUNT-страниц |
| `GroupHttpService` | как есть | `AggregateQuery` |

## Документы захода

этот файл; [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md); [`user-roadmap.md`](user-roadmap.md); [`smarttable-plan-17-aggregate.md`](smarttable-plan-17-aggregate.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).
