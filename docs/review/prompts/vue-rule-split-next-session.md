# Хендофф: нужен ли сплит Vue Rule (Keyword / Mechanic)

Новая сессия. PHP Keyword / Mechanic / Rule / RuleSpace уже отдельные модули. На фронте Keyword и Mechanic **лежат в папке `Roleplay/Rule`** (`CODE_GAP` в `architecture.md`, пункт «Позже» в `rule-roadmap.md`).

**Сначала решить, стоит ли делить сейчас.** Если да — набросать план. Если нет — явно почему и что подождать. Код не писать, пока Андрей не скажет «да» / «поехали». Не коммитить, пока не попросят. Dev-сервер фронта не трогать.

## Кто ты и канон

PowerScale / Mifrial. Пользователь — Андрей (PHP + Vue).

Читать по задаче, не весь репозиторий:

- `docs/tr/architecture.md` — Vue DAG; `CODE_GAP`; Keyword → User (плагин админки); Mechanic Vue ↛ User; Rule → Keyword, Mechanic (публично); Character/Game → Rule, RuleSpace; «Позже — Mechanic (Engine)»
- `draft-front_1.2ds/frontend-rules.md` — анатомия модуля, locator, `no-foreign-module-internals`
- `docs/tr/rule-roadmap.md` — шаги 1–12 **закрыты**; вынос Vue — блок «Позже», не шаг 13 по умолчанию
- `docs/tr/rulespace-plan-10.md` — эталон **переезда папки** (Space → RuleSpace): URL без смены, клиент `ruleSpace.*`, DAG Rule ↛ RuleSpace
- `docs/tr/keyword-plan-02.md` — Vue Keyword остаётся в Rule; `/admin/keywords` не переезжает (`DEC-062`); `keyword.view` только роут
- `docs/tr/mechanic-plan-02.md` — Vue-клиент каталога в Rule; нет `/admin/mechanics`; Engine не этот шаг
- `docs/tr/rule-system.md` — механика = строка справочника; хендлеры — OPEN
- `AGENTS.md`

Не дублировать стандарты. `DEC-062` (UI-термины / URL). Линтер чужих внутренностей — канон рёбер, не «размазать папку».

## Цель сессии

1. **Вердикт:** сплит Vue сейчас / частично / отложить. Не «канон PHP ⇒ сразу пилить фронт».
2. Если осмысленно — **план-файл** (имя вроде `docs/tr/vue-rule-split-plan-01.md`): граница модулей, DAG, URL, locator, что переезжает в каком порядке, тесты, не входит. Статус: план.
3. Показать Андрею. **Код не писать**, пока не утвердит.

Эталон стиля плана — `rulespace-plan-10.md` / `keyword-plan-02.md`, короче если вердикт «не сейчас».

## Что уже так (не переоткрывать PHP)

- PHP: `Roleplay/Keyword`, `Roleplay/Mechanic`, `Roleplay/Rule`, `Roleplay/RuleSpace`. Keyword ↛ Rule. Mechanic ↛ Rule. Rule → Keyword + Mechanic. HTTP правил **нет**.
- Vue RuleSpace уже папка `Roleplay/RuleSpace`, клиент `ruleSpace.*`.
- Vue Keyword: `IKeywordApi` / страницы `/admin/keywords` / стор / мок — **внутри Rule**. `getKeywordApi()` из `Rule/init.ts`. Категория прав + `registerAdminSection`.
- Vue Mechanic: `IMechanicApi.getMechanics` → `mechanic.getList`; DTO `Mechanic.version`; **нет админки**. `MechanicEngine` + хендлеры + registry — **тоже в Rule**. Character/Game зовут `getMechanicApi()` из Rule init (ребро Character/Game → Mechanic каноном — «Позже»).
- Пустой мир: `revision === 0` → `/draft`, без HTTP `getRevision`.

## Зачем вообще CODE_GAP

Канон: отдельные Vue-модули как PHP, чтобы Character/Game не тащили «весь Rule», чтобы админка признаков не притворялась доменом правила, чтобы Engine механик жил в Mechanic.

Это **долг дерева**, не баг runtime. Сплит ради галочки дороже, чем жить с `CODE_GAP`, пока нет второго потребителя или пока Engine не переезжает.

## Вопросы, на которые ответить в вердикте (фактами из дерева)

Считать импорты и `init.ts`, не invent.

1. **Кто импортирует что из Rule:** только редактор правила, или Character/Game/Chat уже тянут Keyword/Mechanic/Engine через публичку Rule? Если почти всё — публичка Rule, сплит мало что закрывает, пока не сменим рёбра.
2. **Два разных груза в одной папке:**
   - справочник Keyword (CRUD UI + HTTP);
   - справочник Mechanic (тонкий getList) **плюс** клиентский Engine/хендлеры.
   Их можно выносить **не разом**.
3. **Связка с OPEN Engine PHP.** Roadmap: хендлеры PHP в модуле Mechanic — «Позже», блокер Character/Game runtime. Вынос Vue-Engine до PHP-Engine может создать третий дом (Vue Mechanic без PHP runtime) или наоборот правильно подготовить папку. Решить явно.
4. **URL.** Keyword: `/admin/keywords` не обязан переезжать (`DEC-062`, план 2). Mechanic админки нет. Сплит ≠ новый сайдбар.
5. **DAG после сплита.** Канон: Rule → Keyword, Mechanic (публично). Character/Game пока → Rule, не Mechanic. Если вынести только файлы, а `getMechanicApi` оставить реэкспортом Rule — `CODE_GAP` не закрыт. Если Character начнёт `getMechanicApi` из Mechanic — это новое ребро, в architecture «Позже». Не делать молча.
6. **Цена.** Объём: `Rule/init.ts` (толстый реэкспорт), eslint `filename`/foreign-internals, тесты Chat/Character на `registerKeywordApi` / `registerMechanicApi`. Эталон объёма — шаг 10 (папка Space), не весь PHP.
7. **Альтернативы «полный сплит»:**
   - отложить до HTTP Rule или до PHP Engine;
   - вынести **только Keyword** (есть UI и плагин User);
   - вынести **только транспорт Mechanic** (`IMechanicApi` + Dto + Mock), Engine оставить в Rule;
   - оставить как есть и сузить публичку Rule (`init` не реэкспортить Engine наружу), закрывая часть долга без новых папок.

## Если план — что в нём зафиксировать

- Какие папки появятся (`Roleplay/Keyword`, `Roleplay/Mechanic` или одна «Reference»).
- Locator-ключи, `register*Module`, кто зовёт `registerPermissionCategory` / admin section.
- Что остаётся в Rule (спеки, RuleApi-эскиз, чат-плагин правила).
- Порядок заходов (Keyword отдельно от Mechanic Engine — нормально).
- Тесты: eslint internals, access/admin, vitest затронутых модулей; не phpunit.
- Не входит: PHP, HTTP Rule, seed справочника, смена URL `/admin/keywords`, реализация Engine PHP.

## Что не входит в эту сессию даже если план «да»

- Код сплита до «поехали».
- HTTP правил.
- Хендлеры PHP Mechanic.
- Перенос `/admin` «в оператор контента» ради чистоты.

## Как работать

- Вердикт в чат Андрею **до** длинного плана. Если «не сейчас» — короткий текст + что триггернет пересмотр (например: старт PHP Engine; Character начинает зависеть от хендлеров напрямую; публичка Rule раздулась ещё раз).
- Если «да» — один план-файл, ждать утверждения.
- Не запускать фронт-dev. Коммит — только по просьбе.

## Якоря в дереве

`draft-front_1.2ds/src/modules/Roleplay/Rule/init.ts`, `Interface/IKeywordApi.ts`, `Interface/IMechanicApi.ts`, `Service/Mechanic/`, `routes.ts` (admin keywords), `eslint/no-foreign-module-internals.test.ts`, Character/Game `getKeywordApi` / `getMechanicApi` / `mechanicEngine`.
