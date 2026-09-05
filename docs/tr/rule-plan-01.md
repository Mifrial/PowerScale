# План Rule 1 — кластер на часы

**Статус:** каркас PHP сделан, 2026-09-05. Нарезка — [`rule-roadmap.md`](rule-roadmap.md). Канон — [`rule-system.md`](rule-system.md). Часы — [`versioning-plan-01.md`](versioning-plan-01.md). Справочники — [`keyword-plan-01.md`](keyword-plan-01.md), [`mechanic-plan-01.md`](mechanic-plan-01.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). `DEC-080`, `DEC-060`.

Цель: ленивый **`Roleplay/Rule`**. Identity/экземпляр правила на Versioning. Ссылки на **чужие** таблицы Keyword и Mechanic. Без HTTP, Vue, Engine, RuleSpace. Без карт `keyword`/`mechanic` в этом модуле.

Порядок: **после Keyword 1 и Mechanic 1**, раньше RuleSpace. ST 19–20 закрыты. Vue `Rule ↛ Versioning` не трогаем. **PHP Rule → Versioning + Keyword + Mechanic.** PHP Rule ↛ RuleSpace.

## OPEN в этом заходе (persist живёт)

- Закрытый PHP-enum всех `RuleType` и валидация/hydrator `spec` по типу — блокер Engine.
- Хендлеры Mechanic — модуль Mechanic, не этот заход.
- Inherit, `code` пространства, секции каталога — RuleSpace.
- HTTP, Vue, файл ревизии, `update` identity, eager.

Не тащить тестовые колонки `vt_note` (`title`/`body`) в Rule.

## Блокер в часах — сделан

Фикстура `vt_note` только в тестах Versioning. `CommitEntry` — карты колонок, не `title`/`body`. `openCluster(ClusterSpec)` есть. Кэш среза — конверт + поля карты версии.

Фасад Rule trim `code`/`name` до commit. Пустая строка required в ST проходит — отсев на фасаде.

## Термины

| Термин | Смысл |
|---|---|
| Identity `rule` | Одна строка на семантический `code` на всю систему. |
| Экземпляр `rule_version` | Иммутабельный снимок тела. |
| Срез | Состав ревизии часов; правило «есть в мире», если его `version_id` в составе. |
| Резолв в мире | В срезе найти identity с этим `code`; нет в составе — в этом мире правила нет. |
| Резолв identity | До часов: `put(code)` → строка `rule` с этим `code` или новая identity. |

Признак и механика — сущности модулей Keyword и Mechanic, не этого файла.

## Решения

### 1. Модуль

Путь: `www/mifrial/modules/Roleplay/Rule`. Неймспейс `Mifrial\Roleplay\Rule`. **lazy**: `IRuleContainer` → group `Roleplay`, name `Rule`. Сосед: `$locator->get(IRuleContainer::class)->get(IRules::class)`.

PSR-4 + Tests; suite `rule`. Setup **с** `getTableClasses()`: пять карт `rule*` (не keyword/mechanic). Data-steps пустые. Mysql-тест Rule **сам** `createTable` карт Keyword и Mechanic (фикстуры соседа), затем свои `rule*`. Не полагаться на suite `keyword`/`mechanic`.

Порт наружу: `IRules` (не `IOpenedRecords`, не `IVersionedRepository`, не `IKeywords`). Фабрика: `ISpaceContainer` → `openCluster(ClusterSpec)` один раз на контейнер. **Не** реестр `open(class)` (прод-каталог часов пустой). **Не** `IKeywords` / `IMechanics` в `Rules`: id проверяет FK. Соседние контейнеры — в mysql-тесте для фикстур. ST gateway — Schema setup и `open` identity для батча `code`, не SQL.

`modules.php` lazy: Keyword, Mechanic, **потом** Rule. CLI setup сортирует карты графом FK (`TableSetupOrder`): и `reference`, и `linkset` restrict. Mysql-сценарий всё равно сначала строки Keyword/Mechanic, потом commit правил.

**DAG PHP:** Rule → Versioning + SmartTable + Keyword + Mechanic (class-string карт в `ReferenceField` / `LinkSetField`). Не User, не Chat, не RuleSpace. Kernel ↛ Rule. Не eager `Core/*`.

Ошибки: `RuleException` extends `MifrialException`. Два листа: `RULE_INVALID`, `RULE_NOT_FOUND`. Наружу не `SPACE_*` / `KEYWORD_*` / `MECHANIC_*` / `MAP_*` / `UNIQUE_*`.

### 2. Кластер `{t}` = `rule`

Тот же конверт Versioning 1. Имена: `rule`, `rule_version`, `rule_space`, `rule_revision`, `rule_revision_item`. Нет join-таблицы признаков.

DDL: `gateway.open(class)->schema()->createTable()`. Порядок: Keyword и Mechanic уже есть → `rule` и `rule_space` → `rule_version` и `rule_revision` → `rule_revision_item`. Снятие наоборот по FK.

Конверт часов как у `vt_note`: identity + version (`entity_id`, `created_at`, `active`) + space (`title`) + revision + item. Unique часов: `(space_id, revision)`, состав `(revision_id, version_id)`. `entity_id` / пункты состава — `reference` restrict, без лишнего `indexed`. `mechanic_id` — **restrict**, не cascade; **не** required. `keywords` — **linkset** restrict, **не** required (`[]` ок; `required`+`[]` в ST — `FIELD_REQUIRED`). Не json.

#### 2.1 `rule` — identity

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | `entityId`. |
| `code` | string 255, required, **unique** | Семантический ключ (`DEC-060`). Trim; пустой → `RULE_INVALID`. Иммутабелен после create. |

Нет `space_id`. Нет имени/типа/spec/признаков/механики.

#### 2.2 `rule_version` — экземпляр

Конверт Versioning (`entity_id`, `created_at` default now, `active` default true) плюс:

| Поле | Тип | Смысл |
|---|---|---|
| `type` | string 64, required | Код `RuleType` как строка. Неизвестный тип в этом заходе допустим (`OPEN` enum). |
| `name` | string 255, required | Trim; пустой → `RULE_INVALID`. |
| `description` | text, required | `''` допустим. |
| `spec` | json, required | PHP-array; `[]` допустим. Без hydrator объектов. |
| `mechanic_id` | reference → `MechanicTable`, **не** required | Нет механики — null. Hop N:1 в ST допустим; **IRules его не зовёт**. |
| `keywords` | linkset → карта модуля Keyword, **не** required | Множество связей; пусто — `[]`. **Не** hop. ST 20. |
| `mechanic_payload` | json, required | PHP-array; `[]` если механики нет. Не разбирать хендлером. |
| `content_status` | string 32, required, default `needs_work` | Редакционный (`DEC-053`); не runtime. |

Нет json-массива id. Нет таблицы `rule_version_keyword`. Нет второго `code`. Нет `catalogSection` / `catalogSortOrder`.

Инвариант фасада: `mechanic_id === null` ⇒ payload `[]`; задан id ⇒ payload любой массив (форма — `OPEN`).

#### 2.2a Признаки снимка — поле `keywords`

Список id на строке `rule_version`, тип **`linkset`** ([`smarttable-plan-20-linkset.md`](smarttable-plan-20-linkset.md)). Не json. Не join-таблица. Не `reference` и не `reference`+`multiple`.

Дубль id в `RuleCommitEntry` — `RULE_INVALID` до insert. Пустой набор — `[]` (нет рядов mfv).

Признаки кладутся в карту версии `CommitEntry` (`keywords`), не отдельным SQL. TX часов достаточна: mfv `add`/`addMany` **join** уже открытую TX ([`smarttable-plan-19-addmany-mfv.md`](smarttable-plan-19-addmany-mfv.md)). **Не** оборачивать `IRules::commit` в ещё один `gateway.transaction()`: пока внешняя TX открыта, кэш среза часов не пишется.

Create/change **часов** из `put`: полный набор колонок тела. Сырой omit ключа в `CommitEntry` не экспортируем. Тело снимка — `RuleVersionBody`.

Чтение 500 правил: срез версий (часы, в т.ч. `keywords` как list int). Карточки Keyword — один `getList` `id IN` при необходимости. Не путь `keywords.code`.

#### 2.3–2.5 часы

Как Versioning 1: у `{t}_space` подпись часов (колонка конверта, не поле правила). Unique `(space_id, revision)` на ревизии; состав `(revision_id, version_id)`.

### 3. Фасад `IRules`

Не раздувать (лимит 10 public). Черновик не в БД.

| Метод | Смысл |
|---|---|
| `addSpace(string $label): int` | Прокси часов; пустая после trim → `RULE_INVALID`. |
| `commit(int $spaceId, array $entries): RuleRevisionRecord` | list `RuleCommitEntry` 1..10000. |
| `getRevision(int $spaceId, int $revision): RuleRevisionSlice` | Номер меньше 1 → `RULE_INVALID`. Нет ревизии → `RULE_NOT_FOUND`. |
| `findInRevision(int $spaceId, int $revision, string $code): RuleVersionRecord` | Trim code; нет в **составе** → `RULE_NOT_FOUND`. |

Нет `addKeyword` / `addMechanic`.

`RuleCommitEntry` в `Dto/` (`DEC-079`), не сырой `CommitEntry` в `Interface/`. Наружу не `create`/`change` часов: вызывающий не передаёт `entityId`.

`commit` — **полный новый состав**, не патч прошлой ревизии: в новой ревизии только то, что в list (`keep` и/или `put`). Не переданное правило из ревизии N в N+1 не входит (это не tombstone: tombstone — `put` с `active: false` в составе).

Фабрики:

- `keep($versionId)` — этот экземпляр остаётся в новом составе;
- `put($code, RuleVersionBody $body, bool $active = true)` — полный снимок тела (не патч). Trim `code`; пустой → `RULE_INVALID`.

Резолв **до** часов, по таблице identity (не по составу текущего space):

- `code` нет ни у одной identity → `CommitEntry::create(['code' => …], versionFields)`;
- `code` уже есть → `CommitEntry::change($entityId, versionFields)`: новая строка `rule_version` в ревизии **этого** space. Это не «второе правило» и не ошибка unique.

Unique на `rule.code` — гонка двух параллельных create одного нового кода, не пользовательский «код занят». Два `put` с одним `code`, или `keep` и `put` одной identity, в **одном** commit — часы режут «identity дважды» → `RULE_INVALID`.

`RuleVersionBody`: type, name, description, spec, keywordIds, mechanicId, mechanicPayload, contentStatus. Сборка versionFields **всегда все колонки**. Геттеры Record — `getKeywordIds()`. Дубль id в `keywordIds` — `RULE_INVALID` до insert. Чужой mechanic/keyword id → `RULE_INVALID` с previous ST. Tombstone: `put(..., active: false)` на существующий code (или на новый — сразу неактивный экземпляр).

`IRules::addSpace` — прокси часов (колонка `title`).

Ошибки — **два листа**, не дерево: `RuleInvalidException` (`RULE_INVALID`) и `RuleNotFoundException` (`RULE_NOT_FOUND`). Не плодить `RuleDuplicate` / `RuleReference` в этом заходе: после резолва по `code` «дубль» — штатный change; пустое имя и чужой FK для HTTP пока оба 400. Смотреть `previous` (ST под часами), `SPACE_*` наружу не отдаём. Третий лист — только когда появится отдельный HTTP-статус.

DTO:

- `RuleRevisionRecord` — как часы: id, spaceId, revision, publishedAt.
- `RuleVersionRecord`: version/entity/code/active + type, name, description, spec, keywordIds, mechanicId, mechanicPayload, contentStatus, createdAt.
- `RuleRevisionSlice`: ревизия + `getItems(): list<RuleVersionRecord>` в порядке состава.

`code` на пункте среза: после `getRevision` часов один `getList` identity `id IN entityIds` (ttl null, не `IN []`). Склеить по `entity_id`. Нет identity в карте → `RULE_INVALID`.

Дубль `code` в одном составе невозможен: одна identity дважды режет часы до insert.

### 4. Резолв и два мира

Один `code` = одна identity на всю систему. В space B `put('human', …)` при уже существующем `human` **не** создаёт вторую identity: новая версия в ревизии B. `findInRevision(A, n, 'human')` не видит состав B (нет в **составе** A — `RULE_NOT_FOUND`, даже если identity есть).

Смена `code` identity — не в этом заходе. `put` всегда несёт `code` для резолва identity; колонки версии `code` не имеют.

Inherit — RuleSpace (копия состава, не вторая identity).

### 5. JSON-кэш часов

Не класть **семантический `code` правила** в поля версии часов: он на identity, после среза склеивается `id IN`. Кэш часов — тело экземпляра: `keywords` (**id** признаков), `mechanic_id` (**id** поставки), `spec`/`mechanic_payload` как JSON. Коды keyword/mechanic в persist снимка нет; импорт/экспорт «через коды» — перевод на границе (HTTP/файл), не содержимое slice-кэша.

### 6. Тесты

Mysql `RuleMysqlTest` (один класс). Фикстуры признаков/механик — через `IKeywords` / `IMechanics`, не `new` чужого Table в Service Rule.

- commit со ссылками; срез отдаёт те же keyword id и mechanic id;
- `mechanic_id` без строки Mechanic → ошибка;
- два space; `put('human')` в A, затем `put('human', другое имя)` в B — один `entityId`, разные `version_id`, срез 1 в A со старым name;
- `put` нового code — новая identity; повторный `put` того же code — не ошибка unique;
- два `put` одного code, или `keep`+`put` той же identity, в одном commit → `RULE_INVALID`;
- `findInRevision` нет в составе → `RULE_NOT_FOUND` (identity при этом может существовать);
- пустой code/name / пустая подпись space / пустой commit;
- `put(..., active: false)` tombstone во второй ревизии.

Unit: trim; ключ часов `vs:rule:…`.

Не phpunit User/Chat. Не полный набор RuleType. Не suite `keyword`/`mechanic` внутри этого класса.

### 7. Quality

cs/quality `Roleplay/Rule`. ctor ≤6. `ListQuery` для identity-батча — **Repository/** Rule, не Service/. Join TX и linkset — в ST 19–20. Rule не открывает вторую TX вокруг commit часов.

Кэш среза: `vs:rule:{spaceId}:{revision}` (`RevisionSliceKey`). Не класть `code` в versionFields часов.

## Todo

- [x] **clocks-body** — `CommitEntry` картами колонок; suite `versioning` зелёный.
- [x] **openCluster** — второй метод каталога; Rule не `new VersionedCatalog`.
- [x] **st-19-20** — [`smarttable-plan-19-addmany-mfv.md`](smarttable-plan-19-addmany-mfv.md) и [`smarttable-plan-20-linkset.md`](smarttable-plan-20-linkset.md).
- [x] **module** — lazy Roleplay/Rule, кластер; `keywords` linkset; `mechanic_id` Reference.
- [x] **facade** — `IRules`, DTO, wrap `RULE_*`.
- [x] **gates** — phpunit `rule` + `versioning`; cs/quality.

## Слои

| Тип | Задача | Не делает |
|---|---|---|
| `Roleplay/Keyword` | Справочник признаков | Часы, spec |
| `Roleplay/Mechanic` | Справочник механик | Engine в этом заходе |
| Table `rule*` | Карты правила | Секции каталога |
| `IVersionedCatalog` | commit / срез | Rule spec |
| `IRules` | code + тело + резолв | Миры / inherit / CRUD справочников |
| RuleSpace | [`rulespace-plan-01.md`](rulespace-plan-01.md): code мира, inherit | spec / HTTP в плане 1 |

## Документы захода

этот файл; [`rule-roadmap.md`](rule-roadmap.md); [`keyword-plan-01.md`](keyword-plan-01.md); [`mechanic-plan-01.md`](mechanic-plan-01.md); [`versioning-plan-01.md`](versioning-plan-01.md); [`php-coding-standards.md`](php-coding-standards.md).
