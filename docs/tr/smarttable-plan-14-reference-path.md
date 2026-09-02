# План 14 — путь через `reference` в getList

**Статус:** сделано, 2026-09-02. Канон — [`smarttable.md`](smarttable.md) § getList / типы / Reference. Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Reference — [`smarttable-plan-05-reference.md`](smarttable-plan-05-reference.md). Multiple — [`smarttable-plan-05-multiple.md`](smarttable-plan-05-multiple.md). Кэш — [`smarttable-plan-08-cache.md`](smarttable-plan-08-cache.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Потребитель-пример — [`user-plan-02-groups.md`](user-plan-02-groups.md) (`hasBypass` / LAST_BYPASS).

Цель: в `getList` / `getUnique` / `getFirst` ключ может быть путём через свои `reference` к полям достигнутой карты. Не полный Bitrix JOIN, не fluent `query()`, не гидрация родителя объектом.

Имена полей карты — `[a-z][a-z0-9_]*`. Точка в имени колонки не бывает: путь не конфликтует с `getMap()`.

Сейчас `reference` в filter/sort/select — INT своей колонки. `group_id => 3` и `select: ['group_id']` **не** меняются.

## Решения

### 0. Поправить тип `reference` в этом заходе

Сейчас `ReferenceField` знает **стол** цели (class-string или физ. имя) и `onDelete`. DDL уже пишет `REFERENCES …(id)`. В типе это не контракт: нет API «колонка цели всегда `id`», словарь теоретически может обрасти `targetField`, hop не на чем завязать кроме договорённости.

В этом плане меняем поле, не оставляем «как будто id».

- Цель FK — **только** поле `id` с `type() === 'id'` целевой таблицы. Не login, не unique-string, не произвольная колонка.
- API поля: стол цели (как сейчас) + явное «колонка цели = `id`» (метод/константа, не аргумент ctor «какое поле»). Параметра «ссылайся на X» нет и не будет.
- Словарь: ключи как сейчас (`target`, `onDelete`). **`targetField` не заводить** — лишний ключ спеки уже `MAP_INVALID` (`extraKeys`).
- DDL уже `references('id')` — не менять семантику; сверка с новым API, чтобы не разъехалось.
- Ctor **не** зовёт `getMap()` цели (self-ref, план 5b). Инвариант «у definition есть `id`» — база SmartTable. Walker открывает карту цели в **запросе**.
- PHP in/out поля по-прежнему `int|null` (id строки), не объект.
- Канон [`smarttable.md`](smarttable.md) § типы / § Reference: «INT = `id` цели», не «INT другой таблицы вообще».
- План 5b «без JOIN родителя в getList»: **JOIN по-прежнему нет.** Снимается только «поля родителя в getList недоступны» — они доступны **путём** (EXISTS / подзапрос / догрузка). Не план 5c.

Без этой правки hop «только по reference» в компиляторе — комментарий, не опора.

### 1. Три закона пути

**Hop с reference всегда на `id` цели** (после правки типа). Не unique-string. Hop N:1: одна наша строка, одна строка цели.

**Hop только по `reference`.** Сегменты пути кроме последнего — поля текущей карты с `type() === 'reference'`. После hop текущая карта = definition цели. Multiple, string, bool в середине — `MAP_INVALID`. Нет hop «через множество»: `permissions.foo`, `group_id.permissions.x` — отказ.

**В SQL только упомянутое.** Компилятор смотрит filter ∪ sort ∪ select, не «у таблицы есть reference». Сосед, его колонка, его mfv — только если путь их назвал.

Глубину не режем: `a.b`, `a.b.c` — один walker. Путь конечен (строка запроса).

**Повтор стола в пути законен.** Запрещать `a.b.a` / «уже видели эту таблицу» нельзя: self-ref и цепочка через другую таблицу могут указать на **другую строку** той же таблицы. Алиасы EXISTS на одну физическую таблицу дважды — норма. Бесконечной рекурсии компилятора нет: длина = число точек.

Лист (последний сегмент) — любое поле достигнутой карты: скаляр, `reference` как INT, **multiple цели**. Операторы листа — те же, что у этого типа на **своей** таблице (в т.ч. json/html/text/`%`). Multiple в hop — нет; multiple на листе — да.

Пустые куски, точка с краю, неизвестное имя — `MAP_INVALID`, не тихий skip.

**Карта цели в walker.** PHP-класс — `(new $targetClass())->getMap()` (в запросе, не в ctor поля). `ReferenceField::forTable` / словарь — карта по физ. имени через каталог (`RuntimeDefinition` / `openByName`), иначе hop со словаря слепой. Компилятор getList получает резолвер карт, не SQL.

### 2. Своя строка не размножается

**FROM списка — только наша таблица.** JOIN в FROM нет (ни к родителю, ни к mfv). Иначе 1:N (mfv, ошибка JOIN) раздует и страницу, и `countTotal`.

| Что в запросе | SQL |
|---|---|
| `select: ['id', 'group_id']` или `select: null` (вся **своя** карта) | Только своя таблица. `group_id` — INT. Соседа нет. |
| `filter`: `group_id => 5` | Своя колонка, как сейчас. |
| `filter`: путь к скаляру (`group_id.active => true`) | Вложенный **EXISTS** по цепочке reference→id, предикат на листе. SELECT — свои колонки. |
| `filter`: путь к multiple цели (`group_id.permissions @=> …`) | EXISTS до строки цели, дальше тот же contains/`=` что `ListMultipleFilter`, но `owner_id` — id цели, mfv цели. |
| `select`: `'group_id.active'` | Скаляр цели: **подзапрос в SELECT-списке** (как sort), не JOIN и не вторая стратегия «на выбор». В ряду ключ с точкой. Не `parent.*`. |
| `select`: `'group_id.permissions'` | Как свой multiple: **не** JOIN mfv. После страницы `loadByOwners` по id целей → массив в ключе с точкой. |
| `sort`: путь к скаляру | Скалярный подзапрос в `ORDER BY`, не JOIN. |
| `sort`: путь к multiple | Отказ, как sort по своему multiple. |

`select: null` = все поля **своей** карты, **без** путей. Родитель только явным путём в select.

Несколько путей в одном запросе: EXISTS/подзапросы/догрузка **только** для названных префиксов.

Пустой FK на hop: filter-путь — строка **не** входит (EXISTS ложен). Select-путь: ключ с точкой → `null`; multiple-лист → `[]`, как свой mfv без рядов (`?? []`).

`getUnique` / `getFirst` — оболочки над `getList`: пути те же, отдельного API нет.

`getById` путей не имеет. Кто хочет родителя — `getList` / `getFirst` с select.

JOIN к PK родителя формально N:1 и не размножает; **не используем**: один закон (FROM = мы), без ветки «JOIN можно, если PK».

### 3. DTO

Публичные типы не плодятся под путь. Меняется смысл **строк** ключей.

**`ListQuery`.** Те же шесть слотов. `filter` / `sort` / `select` — строки. Без точки — поле своей карты. С точками — путь. Не седьмой аргумент ctor, не ключ `fromOptions`. `select` unique: `'group_id'` и `'group_id.active'` — разные имена, оба законны вместе.

**`FilterGroup` / `FilterCondition`.** `field()` может быть путём. Операторы — как у **листа** (bool → `=`; datetime → `<`/`><`; multiple → `@` / `=`). Операнд биндится типом листа, не как int FK, пока путь не сведён к своему `reference` без хвоста.

**`ListResult`.** Плоский assoc. Своё `group_id` всегда **int|null**, не объект и не вложенный массив. Путь — отдельный ключ с точками: `'group_id.active' => true`, `'group_id.permissions' => ['a', 'b']`. Гидрация листа — **поле достигнутой карты**, не `RowAssembler` своей карты (имя с точкой там неизвестно). Прикладной hydrator, который ждёт int FK, ключ с точкой не читает.

Не делать: `'group_id' => ['id' => 5, 'active' => true]`. Не делать `'group' => GroupRecord` — SmartTable не знает прикладные DTO.

**User DTO и фасад этот план не трогает.** `IUserGroups` не составляет `ListQuery`. Счётчик bypass и `hasBypass` — путь на членстве (`group_id.active` / `group_id.bypass`), см. [`user-plan-02-groups.md`](user-plan-02-groups.md).

**Кэш.** Ключ getList уже сериализует filter/sort/select. Теги **по сегментам пути**: для `group_id.active` — `st:{child}:group_id` **и** `st:{user_group}:active` (плюс столы). Смена FK ребёнка и смена `active` родителя бьют такой getList. `select: ['group_id']` без пути цель не тегает. Не тегать ключ `st:{child}:group_id.active` как имя поля — такой колонки нет.

**Качество.** Разбор пути — маленький тип (`FieldPath` или рядом), walker по картам. SQL EXISTS/подзапрос/догрузка — в `TableList` / binder / `MfvRows`, не в `OpenedRecords`. Сосед не пишет SQL.

## Todo

- [x] **field-ref** — API `ReferenceField`: колонка цели всегда `id`; DDL как сейчас; словарь без нового ключа. Юнит. Канон § типы.
- [x] **path** — walker: hop = `reference` → `id`; лист = поле достигнутой карты; повтор стола законен; multiple в hop — отказ; резолвер PHP-класса и каталога. Юнит без MySQL.
- [x] **filter-exists** — путь к скаляру и к multiple цели; FROM только своя; `countTotal` = число своих строк. MySQL child×parent (+ mfv на родителе).
- [x] **select-load** — скаляр: подзапрос в SELECT; ключ с точкой; hydrate типом листа; свой `group_id` остаётся int; `select: null` без соседа; multiple-лист `loadByOwners` по id целей, не JOIN.
- [x] **sort-subquery** — sort по скаляру пути; sort по multiple-листу — отказ.
- [x] **cache-tags** — теги по сегментам (свой FK + поле цели), не dotted-имя как колонка.
- [x] **tests-gates** — cs/quality/phpunit smarttable. Канон § getList после кода. User — не этот PR.

## Не входит

Полный Bitrix JOIN, runtime-поля, группировка, fluent `query()`. Hop через multiple. Гидрация reference объектом. `getById` с путями. Смена PHP-типа своего `group_id`. Versioned. HTTP. Переписывать User сверх пути bypass (`listActiveBypassIds` снят).

## Документы захода

этот файл; после кода — [`smarttable.md`](smarttable.md) § типы / § getList / § Reference («INT = id цели»; путь без JOIN). roadmap; [`TR.md`](TR.md). User-план не менять, пока нет кода ST.

## Следующий заход

Auth **или** CLI setup Kernel ([`kernel-plan-01-setup.md`](kernel-plan-01-setup.md)). HTTP групп — после Auth.
