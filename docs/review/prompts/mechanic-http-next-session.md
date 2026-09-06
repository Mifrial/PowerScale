# Хендофф: Mechanic HTTP (шаг 12 нарезки Rule)

Новая сессия. Чат Keyword HTTP разросся — **здесь только план, потом код после явного «да» / «поехали»**. Не коммитить, пока не попросят. Dev-сервер фронта не трогать.

## Кто ты и канон

PowerScale / Mifrial. Пользователь — Андрей (PHP + Vue). Код только после утверждения плана.

Читать по задаче, не весь репозиторий:

- `docs/tr/TR.md`
- `docs/tr/php-coding-standards.md` — PHP
- `draft-front_1.2ds/frontend-rules.md` — Vue
- `docs/tr/rule-roadmap.md` — шаг **12** (последний шаг этой нарезки)
- `docs/tr/mechanic-plan-01.md` — каркас PHP (таблица + `IMechanics`)
- `docs/tr/keyword-plan-02.md` — **эталон публички справочника** (шаг 11, сделан). Не копировать слепо: у Mechanic другая физика.
- `docs/tr/architecture.md` — DAG; Vue Keyword/Mechanic в папке Rule (`CODE_GAP`)
- `docs/tr/rule-system.md` — механика = строка справочника, правило хранит `mechanic_id`
- `docs/tr/data-model.md` — `mechanic`: unique `(code, handler_version)`; **строки не удаляют**
- `docs/tr/user-plan-03-http.md` — актор / `IUserAccess`
- `docs/tr/kernel-plan-02-action-input.md` — Input-DTO
- `AGENTS.md`

Не дублировать стандарты в ответы. `DEC-079` (JSON ≠ Record), `DEC-062` (UI-термины).

## Цель сессии

1. Написать **`docs/tr/mechanic-plan-02.md`** в стиле `keyword-plan-02.md`: граница, фасад additive, actions, JSON, Vue, тесты, todo, не входит. Статус: план.
2. Сверить с каркасом и эскизом Vue, **явно решить расхождения** (ниже кандидаты — не догмат, пока Андрей не утвердит).
3. Показать план Андрею. **Код не писать**, пока не скажет «да» / «поехали».
4. После «поехали» — реализовать этот файл; обновить `rule-roadmap.md` шаг 12, `TR.md`, `architecture.md` (ребро PHP Mechanic → User), `mechanic-plan-01.md` (HTTP закрыт). Engine хендлеров не трогать.

## Что уже сделано (не переоткрывать)

- Шаги 1–11 нарезки закрыты каркасами. Keyword HTTP: `keyword.*` в `Roleplay/Keyword`; чтение любому актору; запись по ключам; Vue в папке Rule; нет DELETE строк.
- PHP Mechanic каркас: `www/mifrial/modules/Roleplay/Mechanic`, suite `mechanic`, `routes` **пустые**. Фасад: `add` / `get` / `getByCodeVersion`. Таблица: `id`, `code`, `name`, `description`, `handler_version`; unique пара; **нет `active`**.
- `MechanicException` сейчас extends `MifrialException` — для HTTP нужен `ActionException`, иначе `MECHANIC_*` → 500 (как Keyword).
- Vue-движок (`MechanicEngine`, хендлеры) живёт в `Roleplay/Rule` — **не этот шаг** (`CODE_GAP` / «Позже»).
- Эскиз каталога: `IRuleApi.getMechanics` → action **`rule.getMechanics`**. Отдельного `IMechanicApi`, админки `/admin/mechanics` и ключей `mechanic.*` **нет**. Мок `mockMechanics.ts` (~18 строк), JSON поле **`version`**, не `handler_version`.

## Не копировать с Keyword без причины

| Keyword (шаг 11) | Mechanic (ожидание каркаса) |
|---|---|
| unique `code`; есть `active`; `deactivate` | unique `(code, handler_version)`; **нет active**; строки не DROP |
| `getByCode` для Rule, не HTTP | `getByCodeVersion`; `getByCode` **запрещён** (неоднозначно) |
| JSON без дат; `description` всегда string | то же; версия поставки в JSON — **решить имя ключа** |
| Админка `/admin/keywords`, `keyword.view` только роут | Админки нет; каталог нужен редактору правила / Game |
| Кап getList 500, клиентский `useGridPage` | Мок крошечный; тот же dump+кап уместен, `findPage` не этот заход |

Семейство = `code`, поставка = новая **строка** с тем же `code` и новым `handler_version`. Правило хранит **id строки**, не «code без версии».

## Кандидаты в план (решить в тексте плана, не в коде заранее)

1. **Actions в модуле Mechanic**, ключи `mechanic.*`. Не `rule.getMechanics`. PHP Mechanic ↛ Rule. Новое ребро: Mechanic → User (`IUserAccess`) + SmartTable. Не Auth, не Versioning, не Keyword, не RuleSpace, не Engine.
2. **Чтение** getList/get — любой актор (как Keyword: снимки и редактор). Гость → `AUTH_REQUIRED`. Не аноним.
3. **Запись** — ключи (`mechanic.create` / `edit`, …). Bypass пропускает ключ. Нет строки при акторе → `MECHANIC_NOT_FOUND`, не DENIED.
4. **Нет DELETE.** Нет колонки `active` в этом шаге, если не появится сильная причина (не тащить deactivate «для симметрии с Keyword»).
5. **Фасад additive** ≤10 public: как минимум `getList()` (sort `id` ASC, все поставки). `update` — только если нужен (name/description; **code и handler_version иммутабельны**). Новая поставка семейства = `add`, не patch version.
6. **JSON-вид** (assembler): `id`, `code`, `name`, `description`, и версия. Эскиз Vue: `version`. Колонка PHP: `handler_version`. Канон JSON согласовать (скорее `version` как эскиз **или** `handlerVersion` camelCase как Record — выбрать одно, поправить клиент).
7. **Кап getList** как Keyword: потолок HTTP (500), сверх → `MECHANIC_INVALID`, не тихая обрезка. Не `findPage`.
8. **Vue:** убрать `rule.getMechanics` с `IRuleApi` / `RuleApi` / мока. Клиент `mechanic.getList` (Api в папке Rule, не сплит модуля). Не seed PHP из мока. Не вынос Engine. Оболочка `/admin` для механик **не обязательна** в этом шаге (у Keyword URL не переезжал; тут UI списка может не быть вовсе — каталог для селектора правила). Если заведёте ключ `mechanic.view` — не маскировать им «это админка User».
9. **Тесты:** `MechanicHttpMysqlTest` + suite `mechanic`; фасад в `MechanicMysqlTest`. Не phpunit Keyword/RuleSpace. Фронт: format / lint / tsc / test.
10. CSRF true. Лишний ключ корня → `INVALID_PARAMS`.

## Что не входит (OPEN / другие шаги)

- `MechanicEngine`, hydrator payload, хендлеры PHP.
- Вынос Vue Mechanic/Keyword из `Roleplay/Rule`.
- HTTP правил в `Roleplay/Rule`.
- RuleSpace, seed справочника, semver-«последняя» в SQL.
- `findPage`, реактивация, смена `code` / `handler_version`.

## Как работать

- Сначала план-файл. Потом ждать Андрея.
- После кода: `vendor/bin/phpunit --testsuite mechanic`; phpcs + quality модуля Mechanic; фронт `format` → `lint` → `vue-tsc` → `test`.
- Vitest в агентской среде может долго молчать на MCP SSE, потом всё же зеленеть (десятки минут) или зависать. Если не едет — попросить Андрея прогнать фронт у себя, не крутить тот же прогон без новых данных.
- Менять только Mechanic HTTP + тонкий Vue-клиент каталога + документы захода.
