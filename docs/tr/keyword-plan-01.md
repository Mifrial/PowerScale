# План Keyword 1 — каркас справочника для Rule

**Статус:** каркас PHP сделан, 2026-09-05. **Не закрывает модуль.** Нарезка — [`rule-roadmap.md`](rule-roadmap.md). Канон — [`rule-system.md`](rule-system.md) (`DEC-017`, `DEC-022`, `DEC-062`). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель захода: ленивый **`Roleplay/Keyword`** как **каркас цели `linkset` у Rule** — таблица и узкий PHP-фасад, чтобы коммит правила мог указать id признака. Это не полноценный модуль признаков.

После зелёного suite `keyword` модуль **остаётся открытым**. Не считать шаг 1 «Keyword закрыт».

## Что даёт этот заход

Одна Basic-таблица, `add` / `get` / `getByCode` для тестов Rule и FK цели. Не часы, не Rule.

**Строки не удаляют.** На фасаде нет `delete`. «Удалить, если не используется» — не этот заход и не контракт: снимки правил хранят id, проверка по всем ревизиям хрупкая. Выключение — `active=false` (HTTP позже). Код unique после deactivate не освобождается.

## Что не закрыто (модуль не готов)

- HTTP / actions публички — [`keyword-plan-02.md`](keyword-plan-02.md) (сделано). Vue `IKeywordApi.deactivate` — выключение, не `DELETE` строки.
- Права `keyword.*` — шаг 11. Физического delete нет.
- Вынос Vue из папки Rule (`CODE_GAP`) — блок «Позже» нарезки.
- Связь со снимком правила — поле `linkset` на `rule_version` в модуле Rule ([`smarttable-plan-20-linkset.md`](smarttable-plan-20-linkset.md)), не join-таблица и не hop-`reference`.

## Модуль

Путь: `www/mifrial/modules/Roleplay/Keyword`. Неймспейс `Mifrial\Roleplay\Keyword`. **lazy**: `IKeywordContainer` → group `Roleplay`, name `Keyword`. Сосед: `$locator->get(IKeywordContainer::class)->get(IKeywords::class)`.

Suite `keyword`. Setup: `getTableClasses()` с картой `keyword`. Data-steps пустые.

**DAG PHP:** Keyword → SmartTable; HTTP — ещё User (`IUserAccess`). Не Versioning, не Rule, не Mechanic. Kernel ↛ Keyword. Не eager `Core/*`.

Ошибки: `KeywordException` extends `ActionException` (HTTP шаг 11). Коды `KEYWORD_INVALID`, `KEYWORD_NOT_FOUND`. Наружу не `MAP_*` / `UNIQUE_*`.

## Таблица `keyword`

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | Цель ссылок со снимка правила. |
| `code` | string 255, required, **unique** | Trim; пустой → `KEYWORD_INVALID`. |
| `name` | string 255, required | Trim; пустой → `KEYWORD_INVALID`. |
| `description` | text, required | `''` допустим. |
| `active` | bool, required, default true | Выключен в справочнике; id на снимках остаются. |

Не кластер часов. Не секция каталога.

## Фасад `IKeywords`

≤10 public. **Не публичка.** Список / update / deactivate — следующие планы Keyword, не этот заход.

| Метод | Смысл |
|---|---|
| `add(string $code, string $name, string $description = '', bool $active = true): int` | Trim code/name. Дубль `code` → wrap unique → `KEYWORD_INVALID`. |
| `get(int $id): KeywordRecord` | Нет строки → `KEYWORD_NOT_FOUND`. |
| `getByCode(string $code): KeywordRecord` | Trim; нет → `KEYWORD_NOT_FOUND`. |

`KeywordRecord`: id, code, name, description, active. Не мешок (`DEC-079`).

## Тесты

Mysql: add; unique `code` → `KEYWORD_INVALID`; пустой code/name; `get` / `getByCode`. Нет метода delete. Не phpunit Rule/User.

## Todo

- [x] **module** — lazy Keyword, карта, setup, suite `keyword`.
- [x] **facade** — `IKeywords`, wrap.
- [x] **gates** — phpunit `keyword`; cs/quality модуля.
