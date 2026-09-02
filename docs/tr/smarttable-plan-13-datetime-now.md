# План 13 — DateTime default «сейчас»

**Статус:** сделано, 2026-09-02. Канон — [`smarttable.md`](smarttable.md). Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Поля — [`smarttable-plan-02-fields.md`](smarttable-plan-02-fields.md) (`default`, если ключа нет). User — [`user.md`](user.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: на add без ключа datetime-поле может получить **текущий** момент. Не SQL `DEFAULT` / `CURRENT_TIMESTAMP`. Не clock-порт. Не `updated_at`.

Сейчас `UserAccounts` / `UserGroups` зовут `DateTime::now()` в фасаде: `default => DateTime::now()` в карте заморозил бы момент на сборке определения.

## Решения (закрыты здесь)

**Тот же ключ `default`, не новый флаг `FieldSettings`.** Три режима для `datetime`:

| `default` | Смысл |
|---|---|
| ключа нет | нет ключа на add → `null` / `FIELD_REQUIRED` |
| объект `DateTime` ядра | фиксированный момент |
| sentinel «сейчас» | на **add**, если ключа нет → `DateTime::now()` |

**Sentinel — тип SmartTable**, не строка `'now'` в общем `default` (у `string` значение `"now"` законно). Один экземпляр, без состояния. PHP-карта: `['default' => DateTimeNow::instance()]` (имя типа уточнить в коде, не `Now` в Kernel).

**«Пусто» = нет ключа.** Явный `null` при `required` — `FIELD_REQUIRED`, не «поставь сейчас». Update без ключа поле **не** трогает (уже так в `RowAssembler`). `created_at` / `registered_at` сами не едут при update.

**Не SQL.** Колонка INT unix. Не `ON UPDATE`. Не системное `updated_at`.

**Словарь:** для `type=datetime` в json `default: "now"` → sentinel; `default: <int unix>` → `DateTime::fromUnix`; иной default на datetime → `MAP_INVALID`. Sentinel на не-datetime → `MAP_INVALID` (в `getMap` / assembler).

**`DateTime::now()`** по-прежнему единственная точка «сейчас». Поле зовёт её в **cast** после подстановки default, не в `fromOptions`.

**Assembler до `fromOptions`.** Сейчас `FieldSettings::fromOptions($fieldSpec)` заберёт `default: "now"` как строку — `castPresent` её не ест. Для `type=datetime` assembler **подменяет** default: `"now"` → sentinel; `int` unix → `DateTime::fromUnix`; объект ядра (если кто-то собрал спеку в PHP) — как есть; иначе `MAP_INVALID`. PHP-карта таблицы пишет sentinel-объект, **не** строку `'now'`. JSON-число не int (float из decode) → `MAP_INVALID`.

**Маркер не API add/update.** Сосед передаёт объект ядра или не передаёт ключ. Если маркер всё же попал во вход — `castPresent` может трактовать как now (один путь, без ветки `keyPresent`); контракт соседа на это не опирается.

**SQL default не пишем** (ColumnSchema и сейчас не берёт PHP `default`). Sentinel в БД не уходит: insert всегда `cast` → `extract` → unix int.

**Проверка карты:** `getMap` / `indexFields` — sentinel только на `datetime`; на string/int/… → `MAP_INVALID`. Юнит без MySQL.

**Тест «два вызова»:** два `cast` без ключа → **разные экземпляры** (`!==`), unix в ту же секунду **может совпасть**. Это отличает sentinel от `default => DateTime::now()` в `fromOptions` (один объект навсегда).

**User в том же заходе:** `registered_at` / `created_at` — `required` + sentinel. Фасады и репозитории **не** передают дату на add (сигнатура `UserRepository::add` без `DateTime`). DTO New* без этих полей — как сейчас. Patch по-прежнему запрещает эти ключи.

## Todo

- [x] **sentinel** — тип-маркер в `SmartTable` (`Value/` или рядом с полем, не Kernel). Не в карте портов. `DateTimeField::castPresent`: маркер → `DateTime::now()`, объект ядра — как сейчас. `multiple` datetime по-прежнему нет.
- [x] **map** — sentinel на другом типе → `MAP_INVALID`. `default => DateTime::now()` в карте **не** поощрять (не ломать, если кто-то так напишет: это застывший момент, не sentinel).
- [x] **catalog** — assembler datetime: переписать default **до** `fromOptions`; `"now"` / unix int. Юнит без MySQL (в т.ч. `"now"` на `string` → отказ карты).
- [x] **tests-st** — юнит cast: нет ключа + sentinel → два экземпляра `!==`; ключ задан — его значение; явный `null` + required → `FIELD_REQUIRED`. MySQL: add без ключа пишет unix ≈ `time()`; update без ключа дату не меняет. cs/quality/phpunit smarttable.
- [x] **user** — карты `user` / `user_group`; убрать `now()` из `UserAccounts::add` / `UserGroups::add` и аргумент даты у репозиториев. Тесты User: `registered_at` / `created_at` всё ещё объект в окне секунд.

## Не входит

Clock-порт / подмена `time()` в тестах. `updated_at`. Auth `last_login`. SQL DEFAULT. Sentinel для других типов. Versioned.

## Документы захода

этот файл; [`smarttable.md`](smarttable.md) § Datetime / default; roadmap; [`TR.md`](TR.md); [`user.md`](user.md) (кто ставит `registered_at`).

## Следующий заход

Auth **или** CLI setup Kernel. Не Versioned.
