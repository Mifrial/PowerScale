# План Mechanic 1 — каркас справочника для Rule

**Статус:** каркас PHP сделан, 2026-09-05. HTTP справочника — [`mechanic-plan-02.md`](mechanic-plan-02.md). **Не закрывает модуль** (нет Engine). Нарезка — [`rule-roadmap.md`](rule-roadmap.md). Канон — [`rule-system.md`](rule-system.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель захода: ленивый **`Roleplay/Mechanic`** как **каркас под `Reference` у Rule** — строка справочника, без которой нет `mechanic_id`. Это не полноценный модуль механик.

После зелёного suite `mechanic` модуль **остаётся открытым**. Не считать шаг 1 «Mechanic закрыт».

## Что даёт этот заход

Одна плоская Basic-таблица (не часы, не пространства). Строки **не удаляют**: старый `mechanic_id` на снимке правила должен жить. `add` / `get` / `getByCodeVersion`. Не Rule, не Keyword.

## Версии механики (не Versioning)

Механика — семейство хендлеров с одним `code` («Правило 6 и 1» = `six_one_rule`). Каждая поставка контракта — **новая строка** с тем же `code` и новым `handler_version` (Vue `Mechanic.version`, резолв `code@version`). Отдельный кластер часов и `group`/`type` не нужны: семейство = `code`, поставка = `handler_version`.

**Unique `(code, handler_version)`**, не unique на одном `code`. Иначе вторая версия «6 и 1» не вставится.

Селектор в UI (не этот заход): сначала семейство по `code` (подпись с любой строки семейства), потом список `handler_version` этого `code`. «Последняя» для удобства — отдельный запрос/сорт по семейству, когда появится публичка; в каркасе не угадывать semver в SQL. Правило всегда хранит **id строки** (конкретная поставка), не «code без версии».

`getByCode` без версии — запрещён: неоднозначно.

## Что не закрыто (модуль не готов)

- HTTP / публичка справочника — [`mechanic-plan-02.md`](mechanic-plan-02.md) (шаг 12 сделан).
- `MechanicEngine`, hydrator `mechanic_payload`, хендлеры — блок «Позже» нарезки.
- Вынос Vue-движка из папки Rule (`CODE_GAP`) — тот же блок «Позже».
- Versioning. Поле `mechanic_id` на `rule_version` — карта Rule.

## Модуль

Путь: `www/mifrial/modules/Roleplay/Mechanic`. Неймспейс `Mifrial\Roleplay\Mechanic`. **lazy**: `IMechanicContainer` → group `Roleplay`, name `Mechanic`. Сосед: `$locator->get(IMechanicContainer::class)->get(IMechanics::class)`.

Suite `mechanic`. Setup: `getTableClasses()` с картой `mechanic`.

DAG PHP: Mechanic → SmartTable + User (`IUserAccess` на HTTP, план 2). Не Versioning, не Rule, не Keyword. Kernel ↛ Mechanic. Не eager `Core/*`.

Ошибки: `MechanicException`. Коды `MECHANIC_INVALID`, `MECHANIC_NOT_FOUND`.

## Таблица `mechanic`

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | Цель `mechanic_id` у правила. |
| `code` | string 255, required | Код семейства хендлера. Trim; пустой → `MECHANIC_INVALID`. Не unique один. |
| `name` | string 255, required | Trim; пустой → `MECHANIC_INVALID`. Может повторяться у версий одного `code`. |
| `description` | text, required | `''` допустим. |
| `handler_version` | string 64, required | Поставка контракта. Trim; пустой → `MECHANIC_INVALID`. Не номер ревизии часов. Колонку не звать `version`. |

Составной unique `(code, handler_version)` — `defineUniqueKeys()`. Без этой таблицы `Reference` на снимке правила не объявить.

## Фасад `IMechanics`

≤10 public. **Не публичка** и не Engine.

| Метод | Смысл |
|---|---|
| `add(string $code, string $name, string $description, string $handlerVersion): int` | Trim; пустое → `MECHANIC_INVALID`. Дубль пары code+version → wrap unique. |
| `get(int $id): MechanicRecord` | Нет → `MECHANIC_NOT_FOUND`. |
| `getByCodeVersion(string $code, string $handlerVersion): MechanicRecord` | Trim; нет пары → `MECHANIC_NOT_FOUND`. |

`MechanicRecord`: id, code, name, description, handlerVersion.

## Тесты

Mysql: add двух версий одного `code`; дубль пары → ошибка; пустые поля; `get` / `getByCodeVersion`. Не вызов хендлеров. Не phpunit Rule.

## Todo

- [x] **module** — lazy Mechanic, карта, setup, suite `mechanic`.
- [x] **facade** — `IMechanics`, wrap.
- [x] **gates** — phpunit `mechanic`; cs/quality модуля.
