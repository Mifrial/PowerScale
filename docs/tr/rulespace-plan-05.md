# План RuleSpace 5 — права HTTP

**Статус:** каркас PHP сделан, 2026-09-05. Нарезка — [`rule-roadmap.md`](rule-roadmap.md) шаг **8**. HTTP — [`rulespace-plan-04.md`](rulespace-plan-04.md). Актор — [`user-plan-03-http.md`](user-plan-03-http.md). Vue-категория — `SPACE_PERMISSION_CATEGORY`. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: закрыть HTTP оператора **ключами групп** и **владельцем мира**. Без Vue, без object-ACL (`space_moderators` / per-object ключи), без секций. Фасад `IRuleSpaces` без ключей (как `IChats` / `IUserAccounts`: ACL на HTTP). Owner — колонка sidecar, аргумент `add`.

## Термины

| Термин | Смысл |
|---|---|
| Action | `ruleSpace.getList` — имя маршрута. |
| Ключ права | `space.view_all` — строка на группе User. Не префикс action. |
| Глобальный ключ | На все миры (`*_all`). |
| Владелец | `owner_id` sidecar → `user.id`. Создатель HTTP = актор `create`. |

## Решения

### 1. Свои vs все

Sidecar **`owner_id` required**, FK restrict на `UserTable`. Не колонка часов `RuleSpaceTable` (план 1). `space_moderators` / `space_permissions` из [`data-model.md`](data-model.md) — эскиз, не физика этого захода.

Владелец **читает и правит свой мир** без `view_all` / `edit_all`. Глобальные ключи — чужие миры и полный список.

| Ключ | Смысл |
|---|---|
| `space.create` | Создать мир; owner = актор |
| `space.view_all` | Читать любой мир / ленту / срез; `getList` всех active |
| `space.edit_all` | Мета, deactivate, commitDraft любого мира |

Не `space.view` / `space.edit` / `space.comment` (per-object — позже). Не `ruleSpace.create`: админка Vue клеит `${category}.${action}` = `space.create`. Action `ruleSpace.*` и ключ `space.*` — разные оси.

`IRuleSpaces::add($code, $name, $ownerUserId, $description = '', ?$inheritFromSpaceId = null)`. Owner &lt; 1 или нет строки user → `RULESPACE_INVALID`. Смена владельца / Patch owner — не этот заход.

Bypass (`hasBypass`) — пропуск ключа, как User HTTP. Пустые `permissions` у bypass-группы ок.

### 2. Guards

`create`: `IUserAccess::requireKey(space.create)`. Чтение/запись конкретного мира: **сначала актор**, потом sidecar, потом `requireSelfOrKey(ownerId, view_all|edit_all)`. Нет актора → `AUTH_REQUIRED` (в т.ч. id 999). Не владелец и без ключа → `AUTH_DENIED`. Не маскировать DENIED под `NOT_FOUND`. Нет строки **после** актора → `NOT_FOUND`.

`create` + `inheritFrom`: родитель как `get` — владелец или `view_all`; нет родителя → `NOT_FOUND`.

| Action | Правило |
|---|---|
| `getList` | Актор. `view_all` / bypass — все active. Иначе свои active (может `[]`) |
| `get`, `getByCode`, `getRevisions`, `getRevision` | Владелец **или** `view_all` |
| `create` | `space.create`; ответ без `view_all`. `inheritFrom` — виден как `get` |
| `update`, `deactivate`, `commitDraft` | Владелец **или** `edit_all` |

`edit_all` без `view_all` может писать, если знает id. `getList` без ключей — не DENIED.

JSON Space: **`ownerId`**.

### 3. Что не трогаем

Нет PHP-реестра категорий. Нет seed ключей на «Администраторы». Нет порта `IRuleSpaceAccess`. Нет смены owner. `IRules` / часы без ACL. Character не импортируем. Per-object таблицы — нет.

Константы ключей — `RuleSpacePermissionKeys`. HTTP не дублирует `hasKey` на create (только `requireKey` / `requireSelfOrKey` / `hasKey` для ветки списка).

### 4. Тесты

`RuleSpaceHttpMysqlTest`: актор с ключами / bypass на happy-path.

- Нет актора → `AUTH_REQUIRED` (getList и get без мира).
- Актор без ключей → `getList` `[]`; чужой `get` / `update` → `AUTH_DENIED`.
- Только `create`: свои в списке, get/update своего ок; inherit чужого → `AUTH_DENIED`; с `view_all` inherit ок.
- `view_all`: чужой мир в списке и get; без `edit_all` update DENIED.
- `edit_all`: чужой update ок.
- CRUD + commitDraft: набор create+view_all+edit_all (или bypass).
- get без мира при акторе → `NOT_FOUND`.

`RuleSpaceMysqlTest`: User schema + owner на `add`; owner 0 / нет user → INVALID.

Не phpunit User suite. Не Vue `permissions.ts`.

### 5. Quality

cs/quality RuleSpace. HttpService без +1 public. `IUserAccess` без новых методов. ctor Record sidecar — disable «dependencies» (поля, не порты).

## Todo

- [x] **keys** — константы трёх ключей.
- [x] **owner** — `owner_id` sidecar, `add` обязательный owner.
- [x] **guard** — свои vs `*_all`.
- [x] **tests** — DENIED / свои / NOT_FOUND / FK owner.
- [x] **docs** — роудмап шаг 8, TR.

## Не входит

Per-object. Смена owner. Seed ключей Auth. PHP permission plugin/registry. Vue категория `space` → `ruleSpace`. `space.comment`. Права на `IRules` напрямую.

## Документы захода

этот файл; [`rule-roadmap.md`](rule-roadmap.md); [`rulespace-plan-04.md`](rulespace-plan-04.md); [`user-plan-03-http.md`](user-plan-03-http.md); [`php-coding-standards.md`](php-coding-standards.md).
