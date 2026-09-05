# План RuleSpace 6 — секции каталога

**Статус:** каркас PHP сделан, 2026-09-05. Нарезка — [`rule-roadmap.md`](rule-roadmap.md) шаг **9**. Оператор — планы 1–3. HTTP/права — [`rulespace-plan-04.md`](rulespace-plan-04.md), [`rulespace-plan-05.md`](rulespace-plan-05.md). Канон — [`rule-system.md`](rule-system.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). `DEC-079`.

Цель: дерево секций и порядок карточек **версионируются отдельно от тел правил**, но **замораживаются на ревизии мира** указателем. Ревизию не update. Черновик секций **не в БД** (клиент / шаг 10). Можно опубликовать **только каталог** (новый номер ревизии, тот же состав `version_id`). Не колонки `rule_version`. Не seed (`parent_ability_code` ≠ секция). Не `changedCount`. Не Vue.

Набросок Vue `AbilitySection` — эскиз UI, не идентичность таблиц.

## Термины

| Термин | Смысл |
|---|---|
| Секция | Узел дерева: `code`. Не keyword, не `parent_ability_code`. |
| `section_version` | Номер снимка каталога **внутри мира**, ≥1. Не номер ревизии часов. |
| Снимок каталога | Дерево + размещения для `(space_id, section_version)`. |
| Указатель | Иммутабельная связь `(space_id, revision) → section_version`. Insert вместе с ревизией, никогда update. |
| Черновик | Правка дерева до commit: только клиент. Нет action «сохранить секции». |
| Шаринг | Несколько ревизий мира → один `section_version` (нет копии строк на каждый commit правил). |

## Решения

### 1. Ревизия не мутирует

Строка часов и sidecar указателя после insert не меняются. `getRevision(N)` всегда тот же каталог, что приклеили в момент публикации N.

Нет правила «refcount=1 → UPDATE строк секций». Нет UPDATE `section_version` на уже существующей ревизии.

### 2. Черновик локальный

Пока нет commit — сервер каталог не пишет. Нет рабочего указателя на `rulespace` мета. Шаг 10: draft UI / localStorage, не этот заход.

### 3. Карты (столбцы)

Не `Roleplay/Rule` (кроме узкого хвоста часов, §7). Не колонка на `rule_revision`. Не JSON-blob всего дерева.

`space_id` везде → `Rule\Table\RuleSpaceTable` (часы), `onDelete` restrict. `section_version` и `revision` — int ≥1, не PK чужой строки и не FK на `id` sidecar.

Заголовок «одна строка на версию каталога» не заводим. Новый номер снимка = `1 + max(section_version)` по **указателям** этого `space_id` (`rulespace_revision_catalog`). Не `max` по `catalog_section`: пустой снимок (указатель есть, узлов нет) иначе снова выдаст `1` и сломает unique. Указателей нет → первый номер `1`.

Пустой снимок: строка указателя с номером, **ноль** строк в `catalog_section` / `catalog_item`. Чтение — `sections: []`, размещений нет.

Потолок: ≤500 узлов и ≤10000 размещений на один `section_version`.

Нет FK на `rule` / `rule_version`. «`rule_code` есть в составе публикуемой ревизии» — фасад.

#### `rulespace_catalog_section` — узлы дерева одного снимка

Папки каталога. Не карточки правил.

| Колонка | Тип ST | Смысл |
|---|---|---|
| `id` | IdField | PK строки; не identity секции, не JSON. |
| `space_id` | Reference → часы, required | Мир. |
| `section_version` | int, required | Номер снимка каталога в мире. |
| `code` | string, required | Ключ узла в этом снимке. |
| `name` | string, required | Подпись. |
| `parent_code` | string, optional | `code` родителя **в этом же** `(space_id, section_version)` или null (корень). Не id. |
| `sort_order` | int, required | Порядок среди братьев. |
| `catalog_root_for` | string, optional | Корень области UI или null. |

Unique: `(space_id, section_version, code)`.

#### `rulespace_catalog_item` — карточка в секции

Размещение identity правила в дереве + порядок в папке (`catalogSection` / `catalogSortOrder` эскиза). Без этой таблицы снимок знает только папки. Не дубль тела `rule_version`. Ссылка на узел — **`section_id`**, не `(space_id, section_version, section_code)`.

| Колонка | Тип ST | Смысл |
|---|---|---|
| `id` | IdField | PK строки снимка. |
| `section_id` | Reference → `rulespace_catalog_section`, required, restrict | Узел **этого** снимка. |
| `rule_code` | string, required | Identity правила (`code` часов), не `version_id`. |
| `sort_order` | int, required | Порядок карточки внутри секции. |

Unique: `(section_id, rule_code)` — нет дубля в одной папке.

«Одно правило — не больше одной папки **снимка**» — не unique таблицы (два `section_id` одного `(space_id, section_version)`). Проверка в сервисе при insert, как цикл `parent_code`.

Нет unique на `(section_id, sort_order)`: дырки и равный sort допустимы; при равенстве tie-break `rule_code` ASC в assembler.

Inherit: сначала узлы (новые PK, те же `code`), карта старый `id` → новый, потом item с новым `section_id`. Не копировать старые id.

#### `rulespace_revision_catalog` — указатель ревизии на снимок

Заморозка. Одна строка на опубликованную ревизию мира. Insert вместе с ревизией часов, **никогда update**.

| Колонка | Тип ST | Смысл |
|---|---|---|
| `id` | IdField | PK. |
| `space_id` | Reference → часы, required | Мир. |
| `revision` | int, required | Номер ревизии **часов** (1, 2, …), не `id` строки `{t}_revision`. |
| `section_version` | int, required | Какой снимок каталога видит эта ревизия. |

Unique: `(space_id, revision)`.

Несколько ревизий мира могут иметь один `section_version`. Между мирами номер не шарится.

### 4. Инварианты снимка

Перед insert нового `section_version`:

- `code` trim, непустой; дубль → `RULESPACE_INVALID`.
- `parent_code` null или в этом дереве; цикл → `INVALID`.
- `catalog_root_for`: пусто → null; иначе trim. Один не-null корень не чаще раза. Набор Vue областей **не** закрываем в PHP. Неизвестная строка ок.
- Размещение: `section_id` указывает на узел публикуемого снимка; `rule_code` — в составе **публикуемой** ревизии (включая tombstone). Дубль `rule_code` в двух папках снимка → `INVALID`. Нет размещения — вне секций, ок.
- Неизвестная секция у put (нет `code` в дереве) → `INVALID`.

Пустое дерево `[]` допустимо. Нет снимка (мир без ревизий) → чтение `sections: []`.

Сравнение снимков для шаринга: канонический смысл дерева + размещений (коды, имена, parent, sort, root, rule→section/sort). Равенство → тот же `section_version`, **ноль** новых строк секций.

### 5. Фасад: приклеить указатель в TX commit

После успешного insert ревизии часов: insert **одной** строки указателя. Не update старых.

| Вход каталога | Что пишем |
|---|---|
| `null` (оператор / HTTP без ключа `sections`) | Указатель = `section_version` **latest** этого мира; нет latest → пустой снимок номер `1` (только указатель, без узлов). |
| Явный DTO, **равен** снимку latest | Шаринг: указатель на тот же номер. |
| Явный DTO, **отличается** | Insert узлов/item с новым номером (правило max, §3), указатель на него. |
| Явный DTO, latest нет | То же insert; номер `1` (в т.ч. пустое дерево — указатель `1` без узлов, если DTO пустой). |
| Явный пустой `[]`, latest тоже пуст | Шаринг того пустого номера. |

`add` inherit: keep состава; узлы каталога **скопировать** в `space_id` ребёнка с `section_version=1` (новые PK, те же `code`); item — с подставленным новым `section_id`. Не шарить номер и id между мирами. Нет ревизии у родителя — нет каталога.

Имя DTO — `RuleSpaceCatalog`. Не менять return `getRevision()` часов. HTTP читает указатель → снимок репозиторием.

`IRuleSpaces` без нового public «saveCatalog». Аргумент на `commit` / `commitSelected`: `?RuleSpaceCatalog $catalog = null` как в таблице выше.

### 6. HTTP

Права шага 5. Новых action нет. Нет `ruleSpace.updateSections`.

**SpaceRevision:** `sections` — list `{ code, name, parentCode, sortOrder, catalogRootFor? }`. Нет `depth` / `path`.

**Rule:** `catalogSection` (`string`\|`null`), `catalogSortOrder` (int или omit).

**`commitDraft`:** ключ корня `sections` опционален.

| Тело | Смысл |
|---|---|
| нет `sections`, есть put и/или removed | Состав меняется; каталог шарится с latest. |
| нет `sections`, пустые put и removed | Нет публикации → `RULESPACE_INVALID` (как сейчас all-keep). |
| есть `sections` (в т.ч. `[]`), состав меняется | Новый или шаренный снимок по равенству. |
| есть `sections`, пустые put и removed, **нет** ревизии состава | `RULESPACE_INVALID`: часы не принимают пустой list; каталог-only не с чего keep. |
| есть `sections`, пустые put и removed, снимок **≠** latest | Каталог-only: новая ревизия, keep всего состава, новый `section_version`. |
| есть `sections`, пустые put/removed, снимок **=** latest | No-op → `INVALID`. |

Каталог-only на HTTP: `commit(keep всего latest)`, не `commitSelected` с пустыми put/removed (тот путь по-прежнему `INVALID` как шаг 3).

Размещения:

- Put: поля `catalogSection` / `catalogSortOrder` этого объекта. Без ключа или `null` — без размещения (даже если на latest было).
- Omit-keep и tombstone: взять размещение с снимка **latest**; оставить, если секция с тем же **`code`** есть в **новом** дереве (новый `section_id`); иначе снять.
- Каталог-only (`rules` пустой list): все коды состава — как omit-keep (latest ∩ новое дерево). Клиент не обязан присылать карточки, чтобы сохранить раскладку.

### 7. Хвост часов: all-keep для каталог-only

Сейчас `RevisionPublisher::assertCompositionChanged` режет то же множество `version_id`. Каталог-only иначе не опубликовать без мутации ревизии.

Узко: `IVersionedRepository::commit` / `IRules::commit` — флаг **разрешить тот же состав** (имя в коде захода, не `force`). Default `false`: все нынешние тесты часов и RuleSpace 3 без изменения. RuleSpace вызывает `true` **только** когда каталог изменился, а собранный list — все keep (и не пустой).

Не колонка на `{t}_revision`. Не обход через `open` кластера из RuleSpace.

Пустой list по-прежнему `INVALID`. Каталог-only **только** если уже есть ревизия (keep ≥1 пункта). All-keep **без** смены каталога — `INVALID` на фасаде, флаг часов не звать.

Тесты Versioning/Rule: флаг default; явный same-composition + флаг → новый номер, те же version_id.

### 8. Что не трогаем

Vue, seed, keyword `section-*`, `RuleVersionBody`, `changedCount`, рабочий указатель на мета мира, PATCH секций, per-object, owner.

Не тащить дерево на `getList` мира.

### 9. Тесты

Фасад:

- commit правил без каталога: указатель шарит latest; строк секций не прибавилось.
- commit с другим деревом: новый `section_version`, старые ревизии читают старый снимок (строки не update).
- каталог-only: +1 к `revision` часов, те же `version_id`, новый снимок; на мире без ревизии → `INVALID`.
- no-op (тот же состав и то же дерево) → `INVALID`.
- пустой снимок: указатель без узлов; следующий max номера с указателя, не с пустых section-строк.
- inherit: ребёнок `section_version=1`, новые PK узлов, item на новые `section_id`; правка родителя не меняет ребёнка.
- цикл / неизвестная секция → `INVALID`.

HTTP: `sections` на срезе; commitDraft каталог-only с `edit` владельца; без ключа `sections` шаринг; аноним / чужой — как шаг 5.

Suite `rulespace` + узкий хвост `versioning`/`rule` на флаг commit.

### 10. Quality

cs/quality RuleSpace и хвост Versioning/Rule. `ListQuery` каталога в Repository/. Insert снимка — `addMany`. Указатель — одна строка, не update.

## Todo

- [x] **clock** — флаг same-composition на commit часов / `IRules`.
- [x] **ddl** — три карты + setup.
- [x] **dto** — `RuleSpaceCatalog`.
- [x] **write** — TX: часы + указатель; шаринг vs insert `max+1`; inherit copy в новый `space_id`.
- [x] **http** — JSON; каталог-only `commitDraft`.
- [x] **tests**
- [x] **docs** — этот файл, роудмап, TR.

## Не входит

Vue шаг 10. Seed. `parent_ability_code`. Колонки на `rule_version` / `rule_revision`. UPDATE ревизии. Серверный черновик секций. `changedCount`.

## Документы захода

этот файл; [`rule-roadmap.md`](rule-roadmap.md); [`versioning-plan-01.md`](versioning-plan-01.md); [`rule-system.md`](rule-system.md); [`php-coding-standards.md`](php-coding-standards.md).
