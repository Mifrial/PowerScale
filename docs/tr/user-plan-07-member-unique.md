# План User 7 — unique членства без member_key

**Статус:** сделано, 2026-09-04. Блокер ST 16 **сделан**. Канон — [`user.md`](user.md). Группы — [`user-plan-02-groups.md`](user-plan-02-groups.md) (история: unique одной колонкой). Force leftover — [`smarttable-plan-07-force-ddl.md`](smarttable-plan-07-force-ddl.md). CLI — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md): `updateTable`, **не** force. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: `UNIQUE (user_id, group_id)` на `user_group_member`. Колонка **`member_key` уходит**. Фасад `IUserGroups` / HTTP / LAST_BYPASS / Auth — без смены смысла.

## Сейчас

`UserGroupMemberTable`: два `reference` required + `member_key` string unique required `{userId}:{groupId}`. Пишет только `UserGroupMemberRepository::add`. `findId` — `getUnique` по `member_key`. Сосед и DTO поля не видят. Дубль → 1062 → `USER_DUPLICATE` (`write()`).

`UserSchema::install` и Kernel setup: стол есть → `updateTable`. `forceUpdateTable` в установке **нет**.

## Решения

**Карта.** Убрать `StringField member_key`. Добавить:

```php
protected function defineUniqueKeys(): array
{
    return [
        ['user_id', 'group_id'],
    ];
}
```

Имя DDL: `user_group_member_user_id_group_id_unq` (длина ок). Оба поля `reference` required — в allowlist ST 16; NULL в ключе нет.

**Репозиторий.** `add` только `user_id` + `group_id`. Удалить `memberKey()`. `findId`: `getUnique` с `filter` `user_id` + `group_id` (AND как остальные getList модуля), `limit` 1, `select` `id`. `write()` unique → `USER_DUPLICATE` без смены. phpcs:disable на классе не снимать «заодно».

**Живая физика.** `updateTable` (install / `bin/setup.php`) **добавит** составной `_unq` и **оставит** `member_key` + `user_group_member_member_key_unq`. Колонка leftover **NOT NULL** без default: `add` без ключа → не `USER_DUPLICATE`, а ошибка записи MySQL. Снять колонку и старый `_unq` — только `forceUpdateTable` на **этой** карте. Порядок force уже правильный: `addMissing` (составной unique) → leftover index → leftover колонка.

Kernel / `UserModuleSetup` **не** расширять force. Глобальный `forceSetup` — не этот заход. Мусор разработки снимается **пересозданием** физики (drop модуля / тестовой БД → `setup.php`), не force всех столов.

В этом заходе:

- пустой стол / текущие тесты: `drop` + `install` → `createTable` без ключа;
- leftover: как ForceDdl — **вторая карта с тем же физ. именем** (`user_group_member` + `member_key`), `createTable` legacy → строка с ключом → `open(UserGroupMemberTable::class)->schema()->forceUpdateTable()`. Не ALTER после новой карты. После force: колонки `member_key` нет, индекс `…_member_key_unq` нет, есть `…_user_id_group_id_unq`, повтор `addMember` той же пары → `USER_DUPLICATE`;
- если живой стол с данными не дропаете: тот же force **только** `user_group_member`. Если стенд пересоздаёте — force не нужен.

`UserSchema::install` не переводить member на force.

**Тесты.** `testDuplicateNameAndMember` уже ловит дубль — оставить. Leftover **не** в `UserGroupsMysqlTest` (~492 строки, лимит класса 500) — отдельный mysql-класс. Auth/HTTP mysql сами `install` на пустом drop — без смены сценария.

**Не** трогать `IUserGroups` (`addMember` по-прежнему не ищет дубль заранее — ловит unique на insert), DTO, HTTP, Auth seed. `removeMember` → `findId` по двум FK.

## Todo

- [x] **table** — `UserGroupMemberTable` без `member_key`; `defineUniqueKeys`.
- [x] **repo** — add/findId без суррогата.
- [x] **tests** — дубль `addMember` как сейчас; отдельный mysql leftover (legacy-карта, то же имя); cs/quality/phpunit suite user (+ auth, если схема member).
- [x] **docs** — `user.md`; roadmap §2/§9; план 2 — указатель «хвост закрыт 7».

## Не входит

`pair_key` Chat. Force в Kernel. Словарь ST. Другие таблицы User.

## Следующий заход

Chat 1: `defineUniqueKeys [['chat_id', 'user_id']]`, без `member_key`.
