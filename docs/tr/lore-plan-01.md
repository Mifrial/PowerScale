# План Roleplay/Lore — кодекс мира

**Статус:** ~ЧАСТИЧНО~, 2026-11-15. M0–M6 реализованы; ждут ручной проверки и полного прогона.
**Реализация:** ~PHP~, ~VUE~, ~МОК~.
**Опираться на:** ~PHP~ (`Core/SmartTable`, `Core/User`, `Messages/Notes` как образец); Vue-образцы (`RuleDescriptionEditor`, `RichTextEditor`, `DescriptionHtml`) — вспомогательные, не контракт.
**Канон после реализации:** `docs/tr/lore-system.md` (создать), этот план — `docs/tr/lore-plan-01.md`.

## 1. Цель и границы

`Roleplay/Lore` — сеттинговый кодекс мира: миры, дерево разделов, статьи с
HTML-телом и inline-ссылками, поиск, видимость и перенос мира файлом.

Это **не** «знания персонажа» (`znanie`). В UI модуль называется **«Кодекс»**,
сущности — «мир», «раздел», «статья». Термин «Знания» занят навыками правил.

Первый срез:

1. Создать мир, описать его, связать с пространством правил (мягко, по `spaceCode`).
2. Вести дерево разделов.
3. Создавать статьи с заголовком, кратким описанием, HTML-телом, признаками,
   статусом `draft|published` и видимостью `public|secret`.
4. Для `secret` — список читателей на статью.
5. Искать по заголовку, описанию, телу и признакам.
6. Видеть «ссылки сюда»; исходящие/битые ссылки — в панели статьи и отчёте
   импорта; статьи без раздела — фильтром в редакторе дерева.
7. Экспортировать и импортировать кодекс одним JSON `powerscale.lore` v1.

Не входит в первый срез: картинки, внешние ссылки, полная история версий,
ревизии как у правил, realtime-совместное редактирование, переводы, PDF,
полный bundle «правила + кодекс + локации», связка с `znanie` и проверками
знаний, алиасы/переименование `code`, шаблоны статей.

## 2. Термины

| Термин | Смысл |
|---|---|
| Мир | Сущность `lore_world`. Не `rule_space`; связь с пространством — необязательный `spaceCode`. |
| Раздел | Узел дерева `lore_section`: `code`, имя, `parentCode`, `sortOrder`, иконка. |
| Статья | `lore_article`: стабильный `code`, заголовок, описание, `bodyHtml`, `bodyText`, раздел, признаки, статус, видимость. |
| `code` | Стабильный ключ URL и ссылок. Не меняется после создания. |
| `draft` | Статья видна только владельцу мира и админам кодекса. |
| `published` | Статья опубликована; доступ определяется видимостью. |
| `public` | Опубликованная статья видна всем, кому доступен мир. |
| `secret` | Опубликованная статья видна владельцу, админам и пользователям из `readers`. |
| `readers` | Список пользователей статьи, которым доступна `secret`-статья. |
| Ссылка | `data-lore-code` (статья), `data-rule-code` (правило), `data-inline-*` (прочие сущности). |
| `spaceCode` | Мягкая ссылка на пространство правил для навигации и выбора правил в редакторе. Не FK. |

## 3. Решения

### 3.1. Мир принадлежит Lore

Lore владеет мирами, как `Map` владеет картами. Это сохраняет формулировку
«создавать миры», делает экспорт кодекса самодостаточным и не создаёт цикл
`RuleSpace ↔ Lore`.

Связь с пространством правил — `lore_world.space_code` (строка, не FK).
Бэкенд Lore не импортирует Rule/RuleSpace. Фронтенд может по `spaceCode`
открыть `/space/{code}` и загрузить правила для вставки `data-rule-code`
через публичные `getRuleSpaceApi`/`getRuleApi`; это ребро фронта
Lore → Rule/RuleSpace, не бэкенда. Если пространства нет — предупреждение,
не блокировка.

Наследование пространств (`RuleSpace`) кодекс не копирует. Копирование мира
кодекса — отдельная операция импорта, не inherit правил.

### 3.2. Видимость

Две оси у статьи:

- `status`: `draft | published`;
- `visibility`: `public | secret`;
- `readers`: `UserLinkSetField` (id пользователей), только для `secret`.

Эффективное чтение:

| Кто | draft | published + public | published + secret |
|---|---|---|---|
| Владелец мира | да | да | да |
| `lore.edit_all` / `lore.view_all` | да | да | да |
| Пользователь из `readers` | нет | да | да |
| Остальные (мир public) | нет | да | нет |

Матрица — для мира, доступного читателю (`public`). `private`-мир закрыт для
всех, кроме владельца и админов кодекса: для игроков используется
`public`-мир; отдельный список читателей мира — позже. `readers` при
`visibility = public` не участвуют в доступе; UI скрывает список, данные не
стираются (при возврате в `secret` список сохраняется).

Секреты не должны утекать через публичные статьи: сервер фильтрует дерево,
поиск и backlinks; ссылка на недоступную статью рендерится как обычный текст.
Автор предупреждается, что подпись ссылки на secret-статью видна в публичном
теле.

### 3.3. Версии и конфликты

- `lore_world.version` — версия мета и дерева разделов. Инкремент при
  `saveWorld`, `saveTree`, `archiveWorld`.
- `lore_article.version` — версия статьи. Инкремент при `saveArticle`,
  `archiveArticle`.
- `saveTree` принимает `expectedWorldVersion`; `saveWorld`/`archiveWorld` —
  тоже. `saveArticle`/`archiveArticle` принимают `expectedVersion`.
- Несовпадение — `LORE_VERSION_CONFLICT`; в `details` — `currentVersion`,
  `updatedAt`, `updatedBy` (как у Scene). `expectedVersion` обязателен при
  правке; отсутствие — `LORE_INVALID`.
- Полной истории версий и отката в v1 нет. Локальный черновик редактора
  хранится в localStorage.

### 3.4. Коды и разделы

- `code` мира — глобально уникальный; `code` раздела и статьи — уникальны
  внутри мира.
- Формат: `^[a-z0-9][a-z0-9_-]{0,63}$`. Транслит делает клиент; бэкенд
  проверяет формат.
- `code` не меняется после создания. Заголовок/имя меняются свободно.
- Статья живёт ровно в одном разделе. Сквозные связи — признаки и ссылки.
- Дерево разделов: `parentCode`, `sortOrder`, глубина ≤ 5, ≤ 500 узлов.
- Удаление раздела с дочерними разделами или статьями запрещено
  (`LORE_INVALID`); сначала перенос.
- Дерево живое, без ревизий. `saveTree` заменяет дерево целиком в одной
  транзакции и проверяет, что все `sectionCode` статей есть в новом дереве.
- `saveTree` сравнивает только коды и порядок: переименование узла — это
  тот же `code` с новым `name`, статьи остаются на месте.
- Статья без раздела (`section_code = null`) допустима; дерево её не теряет.

### 3.5. HTML и ссылки

- `bodyHtml` — сырой HTML в `HtmlField` (LONGTEXT), как `body_html` у Notes.
- `bodyText` считается на сервере и клиентом не принимается.
- Санитизация — на рендере через DOMPurify, как у `DescriptionHtml`. В
  общий `DESCRIPTION_HTML_CONFIG` добавляется `data-lore-code`; отдельный
  конфиг Lore не нужен. Бэкенд HTML не санитизирует, но ограничивает размер и
  извлекает ссылки.
- В v1 разрешены inline-ссылки на статьи (`data-lore-code`) и правила
  (`data-rule-code`). `href`, `img`, `id` в allowlist не добавляются.
- Внешние ссылки и картинки в v1 не поддерживаются: импорт предупреждает,
  рендер вырезает.
- `lore_link` пересобирается при сохранении статьи: `data-lore-code` →
  `lore`, `data-rule-code` → `rule`, `data-inline-type/value` → тип из
  атрибута. Ссылки нужны для backlinks, битых ссылок и отчёта импорта.
- Извлечение ссылок — `DOMDocument` (`LIBXML_NONET`), с fallback-regex при
  ошибке разбора. `ext-dom` уже есть в окружении; `composer.json` **не**
  меняем, чтобы не трогать `composer.lock` ради платформенного пакета.
  Отсутствие расширения — не падение сохранения, а regex-fallback.

### 3.6. Признаки

Признаки — глобальный модуль `Roleplay/Keyword`, `LinkSetField` на статье.
Импорт досоздаёт использованные признаки по снимкам из файла, как импорт
файла ревизии. Глобальный справочник не чистится. Если понадобятся теги,
локальные для мира, — отдельная таблица позже, не Keyword.

### 3.7. Импорт/экспорт — серверный и атомарный

В отличие от клиентского файла ревизии, кодекс принадлежит серверу, а импорт
должен быть одной транзакцией. Поэтому `lore.exportWorld` и
`lore.importWorld` — HTTP-действия с JSON-документом, без multipart.

Формат — `powerscale.lore` v1, только коды, без внутренних id. `readers`
экспортируются **логинами**, не id. `importWorld` поддерживает `dryRun`,
режимы `new|merge` и политику `skip|overwrite`. `rename` отложен: он требует
переписывания ссылок в импортируемых телах.

### 3.8. Права

Ключи: `lore.create`, `lore.view_all`, `lore.edit_all`.

- `lore.create` — создавать мир; создатель становится владельцем.
- Владелец правит свой мир и видит все его статьи.
- `lore.view_all` — читать любые миры и статьи, включая draft/secret.
- `lore.edit_all` — править любые миры и статьи.
- `readers` даёт чтение конкретной secret-статьи, не редактирование.
- Публичный мир читается любым авторизованным пользователем; гостевой доступ
  — позже.
- Ключи — константы `LorePermissionKeys`; сидов в setup нет. Ключ попадает в
  группы через админку прав после регистрации категории на фронте
  (`LORE_PERMISSION_CATEGORY`), как у Scene/Map.

### 3.9. UI-названия

Модуль — `Roleplay/Lore`, таблицы `lore_*`, действия `lore.*`.
UI — «Кодекс», «Миры», «Разделы», «Статьи». «Знания» не использовать.

## 4. Модель данных

### 4.1. `lore_world`

| Колонка | Тип | Смысл |
|---|---|---|
| `id` | `IdField` | PK. |
| `owner_id` | `UserReferenceField` | Владелец, required, indexed, restrict. |
| `code` | `StringField(64)` | URL-код, required, unique. |
| `name` | `StringField(255)` | Название, required. |
| `description` | `TextField` | Описание, required, `''`. |
| `visibility` | `StringField(16)` | `public|private`, required, default `private`. |
| `space_code` | `StringField(64)` | Мягкая ссылка на пространство, optional, default `''`. |
| `version` | `IntField(1..null)` | Версия мета/дерева, required, default 1. |
| `is_archived` | `BoolField` | Архив, required, default false, indexed. |
| `created_at` | `DateTimeField` | required, now. |
| `updated_at` | `DateTimeField` | required, now. |
| `updated_by` | `UserReferenceField` | optional, setNull. |

### 4.2. `lore_section`

| Колонка | Тип | Смысл |
|---|---|---|
| `id` | `IdField` | PK. |
| `world_id` | `ReferenceField -> LoreWorldTable` | required, indexed, cascade. |
| `code` | `StringField(64)` | Код узла, required. |
| `name` | `StringField(255)` | Имя, required. |
| `description` | `TextField` | Описание, required, `''`. |
| `parent_code` | `StringField(64)` | `code` родителя или null. |
| `sort_order` | `IntField(0..null)` | Порядок, required, default 0. |
| `icon` | `StringField(64)` | mdi-иконка, optional, default `''`. |
| `created_at` | `DateTimeField` | required, now. |
| `updated_at` | `DateTimeField` | required, now. |

Unique `(world_id, code)`. Index `(world_id, parent_code, sort_order)`.

### 4.3. `lore_article`

| Колонка | Тип | Смысл |
|---|---|---|
| `id` | `IdField` | PK. |
| `world_id` | `ReferenceField -> LoreWorldTable` | required, indexed, cascade. |
| `code` | `StringField(64)` | Стабильный код, required. |
| `title` | `StringField(255)` | Заголовок, required. |
| `summary` | `TextField` | Краткое описание, required, `''`. |
| `body_html` | `HtmlField` | HTML, required, `''`, LONGTEXT. |
| `body_text` | `TextField` | Производный текст, required, `''`. |
| `section_code` | `StringField(64)` | Код существующего раздела мира или null. |
| `sort_order` | `IntField(0..null)` | Порядок в разделе, required, default 0. |
| `keywords` | `LinkSetField -> KeywordTable` | Признаки, default `[]`. |
| `status` | `StringField(16)` | `draft|published`, required, default `draft`. |
| `visibility` | `StringField(16)` | `public|secret`, required, default `public`. |
| `readers` | `UserLinkSetField` | Читатели secret, default `[]`, restrict. |
| `version` | `IntField(1..null)` | Версия статьи, required, default 1. |
| `is_archived` | `BoolField` | Архив, required, default false, indexed. |
| `created_at` | `DateTimeField` | required, now. |
| `updated_at` | `DateTimeField` | required, now. |
| `created_by` | `UserReferenceField` | required, restrict. |
| `updated_by` | `UserReferenceField` | optional, setNull. |

Unique `(world_id, code)`. Index `(world_id, section_code, sort_order)`,
`(world_id, status, visibility)`, `(world_id, updated_at)`.

### 4.4. `lore_link`

| Колонка | Тип | Смысл |
|---|---|---|
| `id` | `IdField` | PK. |
| `world_id` | `ReferenceField -> LoreWorldTable` | required, indexed, restrict. |
| `source_article_id` | `ReferenceField -> LoreArticleTable` | required, indexed, restrict. |
| `target_type` | `StringField(32)` | `lore|rule|character|scene|map|other`, required. |
| `target_code` | `StringField(255)` | Код/значение цели, required. |

Unique `(source_article_id, target_type, target_code)`.
Index `(world_id, target_type, target_code)` — для backlinks.
Таблица пересобирается на `saveArticle`: delete по `source_article_id` +
`addMany`. Потолок — 2000 ссылок на статью. `restrict` вместо `cascade`
осознанно: в v1 строки не удаляются (только архив), а `cascade` на карте с
`multiple` (у статьи есть `readers`/`keywords`) запрещён каноном SmartTable.

### 4.5. Ограничения

- `body_html` ≤ 60 000 байт; `body_text` ≤ 65 535 (TEXT).
- `summary` ≤ 2 000; `description` мира ≤ 20 000; `description` раздела ≤ 2 000.
- `code` ≤ 64; `title`/`name` ≤ 255; `space_code` ≤ 64; иконка ≤ 64.
- Статья: ≤ 50 признаков, ≤ 500 readers, ≤ 2 000 ссылок.
- Мир: ≤ 500 разделов, глубина ≤ 5.
- `summary` хранится как есть; в списках отдаётся целиком, обрезка — на UI.
- Импорт: ≤ 5 000 статей, ≤ 500 разделов, ≤ 2 000 признаков.
- Поиск: `q` ≤ 100; `limit` 1..100; дерево — ≤ 5 000 стабов.

## 5. Backend

### 5.1. Каркас модуля

```text
www/mifrial/modules/Roleplay/Lore
├── Action/                       lore.*
├── Container/LoreContainer.php
├── Dto/Action/                   входы действий
├── Dto/                          Record/Page/Summary/Detail/ImportReport
├── Exception/                    LoreException и листья
├── Interface/Container/ILoreContainer.php
├── Interface/Service/ILoreWorlds.php
├── Interface/Service/ILoreArticles.php
├── Repository/                   LoreWorldRepository, LoreSectionRepository,
│                                 LoreArticleRepository, LoreLinkRepository
├── Schema/LoreSchema.php
├── Service/                      LoreWorlds, LoreArticles, LoreHttp*, LorePortFactory,
│                                 LoreAccessService, LoreBodyText, LoreLinkExtractor,
│                                 LoreImportService, LoreExportService, LoreViewAssembler
├── Setup/LoreModuleSetup.php
├── Table/                        LoreWorldTable, LoreSectionTable,
│                                 LoreArticleTable, LoreLinkTable
├── module.config.php
└── tests/
```

Регистрация:

- `config/modules.php` — lazy `Roleplay/Lore`, `ILoreContainer`.
- `composer.json` — PSR-4 `Mifrial\Roleplay\Lore\` и тестовый namespace;
  `ext-dom` не добавляем (платформа), код проверяет `class_exists`.
- `phpunit.xml.dist` — suite `lore`.
- `LoreSchema::getTableClasses()` — четыре карты; `LoreModuleSetup` — schema,
  data-steps пустые.
- DDL order: `lore_world` → `lore_section` → `lore_article` → `lore_link`;
  `lore_article` ссылается на `keyword` и `user` через linkset.

### 5.2. Фасады

`ILoreWorlds` (≤ 10 public):

| Метод | Смысл |
|---|---|
| `findWorldPage(RequestActor $actor, LoreWorldFilter $filter): LoreWorldPage` | Список миров с учётом доступа. |
| `getWorld(RequestActor $actor, string $code): LoreWorldDetail` | Мета + дерево + стабы статей. |
| `saveWorld(RequestActor $actor, SaveLoreWorld $input): LoreWorldRecord` | Создать/обновить мета. |
| `archiveWorld(RequestActor $actor, string $code, bool $archived, int $expectedVersion): LoreWorldRecord` | Архив/восстановление. |
| `saveTree(RequestActor $actor, string $code, array $sections, int $expectedVersion): LoreWorldDetail` | Полная замена дерева. |
| `exportWorld(RequestActor $actor, string $code): LoreExportFile` | Собрать `powerscale.lore`. |
| `importWorld(RequestActor $actor, LoreImportRequest $request): LoreImportReport` | Импорт/dry-run. |

`ILoreArticles` (≤ 10 public):

| Метод | Смысл |
|---|---|
| `findArticlePage(RequestActor $actor, LoreArticleFilter $filter): LoreArticlePage` | Поиск/список с доступом. |
| `getArticle(RequestActor $actor, string $worldCode, string $articleCode): LoreArticleDetail` | Полная статья + backlinks + links. |
| `saveArticle(RequestActor $actor, string $worldCode, SaveLoreArticle $input): LoreArticleRecord` | Создать/обновить полным снимком. |
| `archiveArticle(RequestActor $actor, string $worldCode, string $articleCode, bool $archived, int $expectedVersion): LoreArticleRecord` | Архив/восстановление. |
| `findReaderCandidates(RequestActor $actor, string $worldCode, string $q, int $limit): array` | Поиск пользователей для `readers`; только редактору мира, id/login/name. |

HTTP-сервисы дробим, чтобы не превышать маркер публичных методов:

- `LoreWorldHttpService` — `findWorldPage`, `getWorld`, `saveWorld`,
  `archiveWorld`, `saveTree`;
- `LoreArticleHttpService` — `findArticlePage`, `getArticle`, `saveArticle`,
  `archiveArticle`, `findReaderCandidates`;
- `LoreTransferHttpService` — `exportWorld`, `importWorld`.

### 5.3. Действия и маршруты

| Action | Вход | Выход |
|---|---|---|
| `lore.findWorldPage` | `q`, `state`, `visibility`, `limit`, `offset` | `{ items, total }` |
| `lore.getWorld` | `code` | мир + дерево + стабы |
| `lore.saveWorld` | `code` (required), `name`, `description`, `visibility`, `spaceCode`, `version` (поле отсутствует = create; explicit `null` — `INVALID_PARAMS`) | мир |
| `lore.archiveWorld` | `code`, `archived`, `expectedVersion` | мир |
| `lore.saveTree` | `code`, `sections[]`, `expectedWorldVersion` | мир + дерево |
| `lore.findArticlePage` | `worldCode`, `q`, `sectionCode?`, `keywordIds?`, `status?`, `visibility?`, `limit`, `offset` | `{ items, total }` |
| `lore.getArticle` | `worldCode`, `articleCode` | статья + backlinks + links |
| `lore.saveArticle` | `worldCode`, `article` (полный снимок) | статья |
| `lore.archiveArticle` | `worldCode`, `articleCode`, `archived`, `expectedVersion` | статья |
| `lore.findReaderCandidates` | `worldCode`, `q`, `limit` | `{ items: [{ id, login, name }] }` |
| `lore.exportWorld` | `worldCode` | `powerscale.lore` |
| `lore.importWorld` | `payload`, `mode`, `targetWorldCode?`, `conflictPolicy`, `dryRun` | отчёт |

Все маршруты — `csrf: true`. Коды ошибок: `AUTH_REQUIRED`, `LORE_INVALID`,
`LORE_NOT_FOUND`, `LORE_DENIED`, `LORE_VERSION_CONFLICT` (с
`details.currentVersion`).

### 5.4. Доступ

`LoreAccessService`:

- `requireActor()`; `requireCreate()` — `lore.create`.
- `canViewWorld(actor, world)` — владелец, `view_all`/`edit_all`, либо
  `world.visibility = public`.
- `canEditWorld(actor, world)` — владелец или `edit_all`.
- `canViewArticle(actor, world, article)` — owner/admin; иначе:
  - `draft` → нет;
  - `published + public` → да;
  - `published + secret` → `readers` содержит `actor.id`.
- `canEditArticle` — как `canEditWorld`.
- `requireWorldForView/Edit`, `requireArticleForView/Edit` бросают
  `LoreNotFoundException`/`LoreDeniedException` (не раскрывать существование
  чужого private-мира).
- `findReaderCandidates` — только `canEditWorld`; поиск через
  `IUserAccounts::findPage` (`q`, limit 1..50), результат — id/login/name,
  без email и профиля. Это не общий справочник пользователей.

### 5.5. Поиск

`LoreArticleRepository::findPage` через `ListQuery`:

- фильтр мира, `is_archived = 0`;
- для не-редактора: `status = published` и
  `(visibility = public OR readers @ actorId)`;
- `q` — OR по `title`, `summary`, `body_text` (`%q%`), как в Notes;
- `sectionCode`, `keywordIds` (OR `@` по одному id), `status`, `visibility`;
- сортировка по умолчанию: `section_code`, `sort_order`, `title`; для
  «недавних» — `updated_at DESC`, `id DESC`;
- `countTotal` для страницы.
- `findWorldPage` — владелец/`view_all`/public; `q` по имени/описанию;
  `state=active|archived|all`; счётчики статей/разделов — агрегатом, не N+1.

### 5.6. `body_text` и ссылки

`LoreBodyText::derive`:

1. удалить блоки `<script>…</script>` и `<style>…</style>`;
2. заменить `<br>` и закрывающие блочные теги пробелом;
3. `strip_tags`;
4. `html_entity_decode(ENT_QUOTES | ENT_HTML5, UTF-8)`;
5. `\u{00A0}` → пробел; схлопнуть `\s+`; trim.

Алгоритм совпадает с `NoteBodyText`; вынос в общий Core-сервис — отдельная
задача, в v1 допускается локальная копия с комментарием.

`LoreLinkExtractor::extract(string $html): array`:

- `DOMDocument` + `LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING`;
- `a[data-lore-code]` → `lore`;
- `a[data-rule-code]` → `rule`;
- `*[data-inline-type][data-inline-value]` → тип из `data-inline-type`;
- дедупликация, потолок 2 000;
- при ошибке разбора — regex-fallback и warning в лог, без падения сохранения.

## 6. Импорт/экспорт `powerscale.lore` v1

### 6.1. Envelope

```json
{
  "format": "powerscale.lore",
  "formatVersion": 1,
  "exportedAt": 1763200000,
  "world": {
    "code": "austerland",
    "name": "Аустерленд",
    "description": "",
    "visibility": "public",
    "spaceCode": ""
  },
  "source": {
    "ownerLogin": "gm",
    "articleCount": 42,
    "sectionCount": 6
  },
  "sections": [
    {
      "code": "history",
      "name": "История",
      "description": "",
      "parentCode": null,
      "sortOrder": 0,
      "icon": "mdi-history"
    }
  ],
  "keywords": [
    { "code": "north", "name": "Север", "description": "", "active": true }
  ],
  "articles": [
    {
      "code": "fall-of-the-empire",
      "title": "Падение империи",
      "summary": "Кратко.",
      "bodyHtml": "<p>См. <a data-lore-code=\"old-capital\">старую столицу</a>.</p>",
      "sectionCode": "history",
      "sortOrder": 0,
      "keywordCodes": ["north"],
      "status": "published",
      "visibility": "public",
      "readers": [],
      "isArchived": false
    }
  ]
}
```

Запрещены на статье: `id`, `worldId`, `sectionId`, `ownerId`, `createdAt`,
`updatedAt`, `keywordIds`, `readerIds`. `readers` — логины.

### 6.2. Экспорт

- Только владелец/`edit_all`; экспортируются все статьи, включая draft и
  secret, иначе перенос мира неполный.
- `exportedAt` — unix UTC.
- Детерминизм: секции по `sortOrder`, затем `code`; статьи по `sectionCode`,
  `sortOrder`, `code`; признаки по `code`; readers по логину.
- `keywords` — только использованные в статьях снимки (code/name/description/
  active), как в файле ревизии.
- `world` — мета мира для режима `new`; в `merge` не применяется.
- `source` — провенанс; `ownerLogin` не является адресом или владельцем импорта.
- `bodyHtml` отдаётся как есть; ссылки на правила/другие сущности не
  резолвятся.

### 6.3. Импорт

Вход: `payload`, `mode` (`new|merge`), `targetWorldCode?`, `conflictPolicy`
(`skip|overwrite`), `dryRun`.

Проверки:

- `format`/`formatVersion`; лишние ключи корня — ошибка формата.
- Секции: уникальные `code`, существующий `parentCode`, без циклов, глубина
  ≤ 5, `sortOrder` int.
- Статьи: уникальные `code`, `sectionCode` существует или null, валидные
  `status`/`visibility`, `bodyHtml` ≤ 60 000, ≤ 500 readers, ≤ 50 признаков.
- Признаки: неизвестный `keywordCode` — создать из снимка; `active=false` не
  реактивировать.
- Readers: `findByLogin`; неизвестный логин — warning и удалить из списка;
  owner/admin мира всегда видит secret-статьи.
- HTML: `<img>` и `href` — вырезать тег/атрибут и warning «не поддерживается
  в v1»; `<script>`, `<iframe>`, `<object>`, `<embed>` — удалить элемент
  целиком и warning. Статья импортируется без опасной разметки; блокировки
  нет, кроме превышения размера.
- Ссылки: после вставки всех статей пересобрать `lore_link` для каждой;
  битые `data-lore-code` — warning, не блокировка.

Режимы:

- `new` — создать мир; `source.worldCode` занят → суффикс `-2`, `-3`, …;
  владелец — текущий актор; `spaceCode` сохранить как мягкую ссылку.
- `merge` — целевой мир по `targetWorldCode`; секции/статьи мержатся по
  `code`; `skip` не трогает существующее, `overwrite` заменяет.
- `dryRun` — ничего не пишет, возвращает отчёт.

Отчёт:

```text
{
  worldCode, dryRun,
  created: { sections, articles },
  updated: { sections, articles },
  skipped: { sections, articles },
  warnings: [ { code, path, message } ]
}
```

Блокирующая ошибка → `LORE_INVALID`, транзакция откатывается. Успешный импорт
— одна транзакция: мир (если new), секции, статьи, ссылки, признаки.

`rename` не входит в v1: он требует переписывания `data-lore-code` в
импортируемых телах. Добавляется отдельным шагом.

## 7. Frontend

### 7.1. Каркас

```text
draft-front_1.2ds/src/modules/Roleplay/Lore
├── init.ts
├── routes.ts
├── Interface/ILoreApi.ts
├── Service/LoreApi.ts
├── Service/Description/LoreLinkExtension.ts
├── Service/Instance/loreTocService.ts
├── Service/Instance/loreLinkService.ts
├── Mock/mockLoreApi.ts
├── Dto/                          WorldSummary, WorldDetail, Section,
│                                 ArticleSummary, Article, ImportReport, ...
├── Enum/                         LoreWorldVisibility, LoreArticleStatus,
│                                 LoreArticleVisibility, LoreImportMode,
│                                 LoreImportConflictPolicy, LoreLinkType
├── Constant/Navigation/LORE_MENU_ITEM.ts
├── Constant/Permission/LORE_PERMISSION_CATEGORY.ts
├── Constant/LORE_ERROR_MESSAGES.ts
├── Page/LoreWorldsPage.vue
├── Page/LoreWorldPage.vue
├── Page/LoreArticlePage.vue
├── Page/LoreArticleEditPage.vue
├── Page/LoreWorldSettingsPage.vue
├── Component/                    дерево, карточки, диалоги импорта/экспорта,
│                                 редактор, TOC, readers picker
├── Composables/                  useLoreDraft, useLoreTree, useLoreArticle,
│                                 useLoreSearch, useLoreImport
└── __tests__/
```

- `init.ts` — `registerLoreApi`/`getLoreApi`, `registerLoreModule` (меню, права),
  реэкспорт синглтонов при необходимости.
- `routes.ts` — `/lore`, `/lore/:worldCode`,
  `/lore/:worldCode/article/:articleCode`,
  `/lore/:worldCode/article/:articleCode/edit`,
  `/lore/:worldCode/article/new/edit`,
  `/lore/:worldCode/settings`. Lazy imports. Публичные страницы — `guestAllowed`
  только после появления гостевого доступа; в v1 — auth.
- Меню: «Кодекс», `mdi-book-open-page-variant`, order 45.
- Права: `lore.create`, `lore.view_all`, `lore.edit_all`.

### 7.2. API и mock

`ILoreApi`:

```text
findWorldPage(query, signal)
getWorld(code, signal)
saveWorld(data, signal)
archiveWorld(code, archived, expectedVersion, signal)
saveTree(code, sections, expectedWorldVersion, signal)
findArticlePage(query, signal)
getArticle(worldCode, articleCode, signal)
saveArticle(worldCode, data, signal)
archiveArticle(worldCode, articleCode, archived, expectedVersion, signal)
findReaderCandidates(worldCode, q, limit, signal)
exportWorld(worldCode, signal)
importWorld(payload, options, signal)
```

`mockLoreApi`:

- два мира: публичный «Аустерленд» и приватный «Личный дневник»;
- разделы «История», «Народы», «Места», «Тайны»;
- статьи с `data-lore-code`, `data-rule-code`, таблицами, `details`,
  признаками, draft/secret/readers;
- version-конфликт и отчёт импорта;
- экспорт/импорт round-trip на фикстуре.
- `findReaderCandidates` в моке — по фикстурам `mockUserApi`; контракт тот же,
  что у реального действия.

### 7.3. Читалка

`LoreWorldPage.vue` — страница мира на всю ширину, без бокового дерева:

- действия — в топбаре: «Экспорт», «Импорт», «Настройки», «Новая статья»;
- карточки: «О мире», сводка (видимость, разделы, статьи, обновлён),
  «Разделы» (плоский обход дерева с отступами и чипами статей) и «Недавние
  статьи»;
- статьи с `sectionCode = null` — в псевдораздел «Без раздела»;
- стабы статей отсортированы по `sortOrder`, затем `title`;
- для не-редактора сервер уже скрывает пустые разделы и недоступные статьи.

`LoreArticlePage.vue`:

- хлебные крошки «Кодекс → <название мира> → <название статьи>»: названия
  кладутся страницами в `Store/crumbs`, коды остаются только fallback до
  загрузки; отдельный заголовок не дублируется;
- заголовок статьи, признаки, бейджи `Draft`/`Secret` для редактора;
- `DescriptionHtml` в обёртке `LoreArticleBody.vue`;
- клик по `[data-lore-code]` открывает статью в слайдере
  (`Component/LoreArticleSlider.vue` на общем `SlidePanel`): заголовок, бейджи,
  тело, переход на страницу статьи, вложенные ссылки; отсутствующий код →
  «красная» ссылка, для редактора — диалог создания;
- клик по `[data-rule-code]` → `open-rule`; `RuleSlider` подключается
  динамическим `import()` (статический импорт чужого `Component/` запрещён);
- TOC из `h2/h3` после рендера (ids назначаются в DOM, allowlist не меняем);
- backlinks «Ссылки сюда» (сервер уже отфильтровал), «Ещё в разделе»,
  ненаписанные статьи;
- предыдущая/следующая статья в разделе загружают страницу;
- кнопка «Редактировать» для владельца/админа — в топбаре.

`LoreArticleBody.vue`:

- `DescriptionHtml` с общим санитайзером; в `DESCRIPTION_HTML_CONFIG`
  добавлен `data-lore-code`;
- перехват `data-lore-code` на обёртке, `DescriptionHtml` оставляем для
  `data-rule-code` и стилей;
- `loreTocService` строит TOC и назначает `id` заголовкам после `v-html`.

### 7.4. Редактор

`RichTextEditor`-оболочка:

- вынести в `Core/UI` тонкую оболочку TipTap (EditorContent, lifecycle,
  `v-model`, disabled, min-height, слот `toolbar`), не трогая старые
  `RuleDescriptionEditor`/`Messages/Inline` в этом же срезе;
- Lore и Rule используют оболочку; тулбары остаются модульными;
- расширения: StarterKit, TableKit, `ruleLinkExtension`,
  `loreLinkExtension`, `descriptionMarkExtension`,
  `descriptionExpandedBlockExtension` (по необходимости).

`LoreArticleEditPage.vue`:

- действия — в топбаре: «Отмена», «Предпросмотр/Редактор», «Сохранить»;
  отдельного заголовка «Редактирование статьи» нет (мир и статья — в крошках);
- раскладка в одну колонку на всю ширину, без боковой панели: заголовок;
  краткое описание; признаки (`v-autocomplete`, multiple/chips); раздел на всю
  ширину; строка «Видимость + Порядок»; строка «Код + Статус»; readers; тело;
- для `public` блок readers скрыт и подсказка не выводится;
- раздел выбирается общим `Core/UI/Component/Input/TreeSelect.vue` (дерево с
  поиском и путём, тот же компонент у фильтра секций), а не плоским `v-select`;
- порядок — общим `ClampedNumberField` (`min=0`), а не `type=number`;
- все поля и селекты в одной плотности/варианте (`outlined`, `comfortable`,
  `hide-details="auto"`), чтобы высоты не разъезжались;
- `readers` picker — поиск через `lore.findReaderCandidates` (редактору мира),
  выбранные id; общий `user.findPage` не требуется и не должен требовать
  `user.view`;
- режим «Предпросмотр» — тот же `LoreArticleBody`;
- «Вставить статью» — поиск по текущему миру, вставка
  `<a data-lore-code="code">Название</a>`;
- «Вставить правило» — `spaceCode` → `getRuleSpaceApi().getSpaceByCode(code)`,
  затем `getRuleApi().getRules(space.id)`; иначе кнопка скрыта/disabled;
- «Создать статью из выделенного»: диалог с заголовком/кодом/разделом,
  `saveArticle` (draft), затем вставка ссылки; новый код генерируется
  `slugify` (вынести в `Core/Engine`, Rule реэкспортирует);
- локальный черновик в localStorage по `worldCode/articleCode`, dirty-guard,
  `beforeunload` и router-guard; уход при несохранённых правках — свой диалог
  «Остаться / Уйти без сохранения» (`window.confirm` не используется);
  после создания статьи переход на её страницу правки не спрашивает
  подтверждения, а флаг обхода сбрасывается;
- восстановление локального черновика не помечает статью изменённой
  (`hydrating` снимается после `nextTick`, то есть после флаша вотчера);
- конфликт версий — диалог «Загрузить текущую / Перезаписать» с
  `currentVersion`;
- сохранение полным снимком, после успеха — обновить версию и черновик;
  результат подтверждается общим `LoreNotice` (цветной тост сверху).

`LoreWorldSettingsPage.vue`:

- действия — в топбаре: «Экспорт», «Импорт», «Раздел» (на вкладке
  разделов) и контекстный «Сохранить»; архив мира — в карточке «Основное»;
  заголовок страницы не дублируется;
- вкладки «Основное» и «Разделы»;
- мета мира: имя, описание, видимость; пространство правил выбирается
  `v-select` из каталога `RuleSpace` (`useSpaceCatalog`) и сохраняется мягкой
  ссылкой `spaceCode`; ручного ввода кода нет;
- дерево разделов: общий `Core/UI/Component/Tree/TreeEditor.vue`
  (переиспользуется `SectionTreeEditor` в `RuleSpace`), редактирование в
  диалоге: добавить/переименовать/удалить/перетащить, parent, порядок, иконка;
  удаление блокируется при дочерних разделах и статьях; сохранение `saveTree`
  с `expectedWorldVersion`;
- плоские карточки на светлой подложке темы вместо `outlined`-рамок;
- сохранение (мета, дерево, архив) подтверждается общим `LoreNotice`
  (цветной тост сверху), а не мелким снизу.

### 7.5. Импорт/экспорт UI

`LoreExportDialog.vue`:

- показывает количество разделов/статей, включая draft/secret;
- `lore.exportWorld`, `JSON.stringify(file, null, 2)`, Blob, скачивание
  `<worldCode>.lore.json`.

`LoreImportDialog.vue`:

- file input, чтение текста, `JSON.parse`, проверка `format`/`formatVersion`;
- выбор `mode` (`new|merge`), `targetWorldCode` для merge, политики
  `skip|overwrite`;
- `dryRun` → отчёт: создано/обновлено/пропущено, warnings;
- «Применить» → `importWorld` без dryRun, обновить список миров/дерево;
- ошибки `LORE_INVALID` — показать путь/сообщение, не терять выбранный файл.

### 7.6. Ошибки и состояния

- `LORE_ERROR_MESSAGES` — русские сообщения по кодам.
- Загрузка/ошибка/повтор для каждого async-экрана (F17), без тихих провалов.
- Пустые состояния: нет миров, нет статей, нет результатов поиска, нет
  читателей.
- Ограничения: `limit` пагинации, `q` trim, debounce поиска.

## 8. Срезы

### M0 — контракт

- [x] `docs/tr/lore-system.md`: домен, таблицы, доступ, HTTP, формат
  `powerscale.lore`, безопасность.
- [x] Решения: мир принадлежит Lore; `draft/published` + `public/secret` +
  readers на статье; без картинок/внешних ссылок; серверный импорт/экспорт;
  `code` не меняется.
- [x] `docs/tr/TR.md` — ссылка; `contract-status.md` — owner `lore-system.md`.
- Гейт: ревью документов, кода нет.

### M1 — backend-каркас

- [x] PSR-4, suite `lore`, `config/modules.php`, `composer dump-autoload`.
- [x] `LoreContainer`, `ILoreContainer`, `LoreModuleSetup`, `LoreSchema`,
  четыре таблицы, `module.config.php`.
- [x] DTO/исключения/репозитории-скелеты.
- [x] `LorePermissionKeys`.
- [x] `LorePortBootTest`, `LoreModuleSetupTest`.
- Гейт: `composer stan`, `composer test:unit`, `phpunit --testsuite lore`.

### M2 — backend-сценарии

- [x] `LoreWorlds`: список/чтение/сохранение/архив/дерево.
- [x] `LoreArticles`: поиск/чтение/сохранение/архив/поиск читателей.
- [x] `LoreAccessService`, `LoreBodyText`, `LoreLinkExtractor`,
  `LoreViewAssembler`.
- [x] `LoreWorldHttpService`, `LoreArticleHttpService`, actions, routes.
- [x] Версии/конфликты, readers, backlinks.
- [x] Unit/MySQL/HTTP тесты.
- Гейт: `composer stan`, `phpunit --testsuite lore`.

### M3 — импорт/экспорт

- [x] `powerscale.lore` v1 DTO, parse/serialize.
- [x] `LoreExportService`.
- [x] `LoreImportValidator`, `LoreImportService` (dryRun, new/merge,
  skip/overwrite, readers по логинам, признаки, ссылки, транзакция).
- [x] `LoreTransferHttpService`, `lore.exportWorld`/`lore.importWorld`.
- [x] Round-trip, конфликты, неизвестные логины, циклы, dryRun.
- Гейт: `phpunit --testsuite lore`.

### M4 — frontend-читалка

- [x] Каркас модуля, API/mock, DTO, enum, init/routes/menu/права;
  регистрация в `main.ts` и `Roleplay/routes.ts`.
- [x] `LoreWorldsPage`, `LoreWorldPage`, `LoreArticlePage` (без бокового дерева).
- [x] `LoreArticleBody`, `loreTocService`, backlinks, красные ссылки.
- [x] Санитайзер: `data-lore-code`.
- [x] Тесты mock/service/TOC.
- Гейт: `npm run format`, `npm run lint`, `npx vue-tsc --noEmit`,
  `npx vitest run` по Lore.

### M5 — frontend-редактор

- [x] Оболочка rich-text в `Core/UI`; Lore использует её.
- [x] `LoreWorldSettingsPage`: вкладки «Основное»/«Разделы»; пространство
  правил выбирается из каталога `RuleSpace` (мягкая ссылка `spaceCode`);
  дерево разделов — общий `TreeEditor` в `Core/UI`; архив в топбаре.
- [x] `LoreArticleEditPage`: поля, readers, тело, предпросмотр.
- [x] Панель ссылок статьи: исходящие/входящие, битые цели; фильтр «без
  раздела» в дереве редактора.
- [x] `loreLinkExtension`, вставка статьи/правила, «создать из выделенного».
- [x] Локальный черновик, dirty-guard, конфликт версий.
- [x] `slugify` в `Core/Engine` (Rule реэкспортирует).
- [x] Тесты composables/service.
- Гейт: format/lint/vue-tsc/vitest.

### M6 — импорт/экспорт UI и документация

- [x] `LoreExportDialog`, `LoreImportDialog`, отчёт.
- [x] Пустые состояния, ошибки, полировка.
- [x] `docs/tr/lore-system.md`, `lore-plan-01.md`, `TR.md`, `decisions.md`,
  `architecture.md` (бэкенд Lore → Core/Keyword; фронт Lore → Rule/RuleSpace
  для выбора правил), `contract-status.md`.
- [x] `composer maps`, `composer maps-check`, `composer docs-check`.
- [ ] Ручная проверка пользователем, затем полный `bash tools/test-full.sh`
  после подтверждения.
- Гейт: полный прогон.

## 9. Тесты и гейт

### PHP unit

- `LoreBodyTextTest` — script/style, теги, entities, `&nbsp;`, пробелы.
- `LoreLinkExtractorTest` — lore/rule/inline, дубли, битый HTML, потолок.
- `LoreAccessTest` — матрица владелец/admin/public/reader/draft/secret.
- `LoreCodeTest` — формат кодов, trim, границы.
- `LoreImportValidatorTest` — формат, дубли, циклы, readers, HTML.
- `LoreExportServiceTest` — нет id, детерминизм, used keywords, readers.

### PHP MySQL

- `LoreMysqlTest` — CRUD, версии, архив, дерево, поиск, признаки, readers,
  ссылки/backlinks.
- `LoreHttpMysqlTest` — все действия, права, ошибки, конфликты,
  `findReaderCandidates` без `user.view` и только редактору мира.
- `LoreImportMysqlTest` — dryRun без записи, new/merge, skip/overwrite,
  круг export → import → export, неизвестные логины, транзакция.
- `LoreModuleSetupTest`, `LorePortBootTest`.

### Vue/Vitest

- `loreToc.test.ts` — заголовки, ids, вложенность.
- `loreLink.test.ts` — красные ссылки, доступные коды.
- `mockLoreApi.test.ts` — доступ, версии, поиск, импорт/экспорт.
- `useLoreDraft.test.ts` — localStorage, dirty, конфликт.
- `loreImport.test.ts` — парсинг файла, отчёт, ошибки.

### Гейт

- Итерация PHP: `composer stan`, `composer test:unit`,
  `php www/mifrial/vendor/bin/phpunit --testsuite lore`.
- Итерация Vue: `npm run format`, `npm run lint`, `npx vue-tsc --noEmit`,
  `npx vitest run` по затронутым файлам.
- Карты: `composer maps`, `composer maps-check`, `composer docs-check`.
- Полный `bash tools/test-full.sh` — только после ручной проверки и
  подтверждения; два PHPUnit одновременно не запускать.

## 10. Риски и открытые вопросы

- **Утечка secret через ссылку.** Сервер фильтрует дерево/поиск/backlinks,
  но подпись ссылки в публичном теле остаётся. Автор предупреждается; если
  заголовок сам секрет — не ссылаться из публичной статьи.
- **Readers в экспорте.** Только логины; на новом стенде неизвестные
  отбрасываются с warning. Одноимённый логин может быть другим человеком —
  это ответственность переноса.
- **`ext-dom`.** Нужен для извлечения ссылок; платформенный пакет, в
  `composer.json` не добавляем. Без него — regex-fallback.
  Без него — regex-fallback, менее надёжный.
- **Дубли `body_text`/`slugify`.** Локальные `LoreBodyText`/`LoreSlug` в v1;
  вынос в общий Core — отдельная задача при третьем потребителе.
- **Третий rich-text.** Оболочка выносится в `Core/UI`; старые редакторы не
  ломаем. Если вынос окажется рискованным — Lore-редактор остаётся
  модульным, но без копии оболочки.
- **Глобальные признаки.** Импорт досоздаёт used-признаки; справочник не
  чистится. Локальные теги мира — позже.
- **Private-мир и игроки.** В v1 private = только владелец/админы; для
  игроков — public-мир + secret-статьи. Список читателей мира — позже.
- **Код мира.** Глобально уникальный; при импорте `new` конфликт решается
  суффиксом. Переименование кода — позже, с редиректами.
- **HTML без серверной санитизации.** Как у Notes: храним сырой HTML,
  санитизируем на рендере. Импорт вырезает опасные теги; API-клиенты обязаны
  санитизировать.
- **Размер тела.** 60 КБ на статью; большие статьи — позже, с LONGTEXT для
  `body_text`/поисковым индексом.
- **`spaceCode`.** Мягкая ссылка без проверки на бэкенде; UI предупреждает,
  если пространство не найдено. Наследование пространства кодекс не копирует.
- **Поиск читателей.** `lore.findReaderCandidates` — узкий поиск по
  id/login/name для редактора мира, без email и профиля. Не использовать
  общий `user.findPage` с правом `user.view`: у ведущего его может не быть.
- **Кэш резолва readers.** При импорте логины резолвить пачкой с кэшем, не
  по одному запросу на статью; при экспорте id→login — пачками по ≤500.

## 11. Не входит

- Картинки, вложения, библиотека ассетов, перенос файлов.
- Внешние ссылки (`href`) и якоря (`id`).
- Полная история версий, откат, draft/publish-снимки как у правил.
- Real-time collaboration, SSE, уведомления.
- Full-text search engine, морфология, релевантность.
- Переводы/локали, несколько языков статьи.
- PDF/печать как отдельный контракт.
- Типы статей, шаблоны, infobox, алиасы, редиректы.
- Список читателей на уровне мира.
- Связка с `znanie`, проверками знаний и раскрытием статей в игре.
- Полный bundle «правила + кодекс + локации».
- Кросс-мирные ссылки.
- Гостевой доступ к публичным мирам.

## 12. Документы захода

Этот файл; после реализации — `docs/tr/lore-system.md`,
`docs/tr/lore-plan-01.md`, `docs/tr/TR.md`, `docs/tr/architecture.md`,
`docs/tr/decisions.md`, `docs/tr/contract-status.md`, карточка
`docs/tr/module-map/Roleplay/Lore.md` (генерируется), `doc-index` и
`module-map` (генерируются).

## 13. Журнал

| Дата | Изменение |
|---|---|
| 2026-11-15 | План создан: Lore-миры, draft/public/secret + readers на статье, без картинок, version+конфликт, экспорт только кодекса, серверный импорт/экспорт. |
| 2026-11-15 | M0–M6 реализованы: канон `lore-system.md`, модуль `Roleplay/Lore` (4 таблицы, 12 действий), файл `powerscale.lore`, Vue-модуль с читалкой, редактором и диалогами импорта/экспорта, mock, PHP/Vitest. Осталось: ручная проверка и полный прогон. |
