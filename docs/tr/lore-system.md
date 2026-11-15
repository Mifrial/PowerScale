# Кодекс мира (`Roleplay/Lore`)

**Статус:** текущий канон, 2026-11-15. Реализация — `www/mifrial/modules/Roleplay/Lore` и
`draft-front_1.2ds/src/modules/Roleplay/Lore`. План захода — [`lore-plan-01.md`](lore-plan-01.md).

Кодекс — сеттинговый справочник: миры, дерево разделов и статьи с HTML-телом и
inline-ссылками. Это **не** «знания персонажа» (`znanie`, [`rule-content-plan-04-knowledge.md`](rule-content-plan-04-knowledge.md)).
В UI модуль называется **«Кодекс»**, сущности — «мир», «раздел», «статья».

## 1. Границы

- Lore владеет своими мирами, как `Roleplay/Map` — картами. Мир кодекса **не**
  `rule_space`.
- Связь с пространством правил — необязательный `space_code` (строка, не FK).
  Бэкенд Lore не импортирует Rule/RuleSpace; фронт использует публичные
  `getRuleSpaceApi`/`getRuleApi`, чтобы вставлять ссылки на правила.
- Ссылка на пространство не копирует состав правил и не наследует ревизию.
- В v1 нет картинок, внешних ссылок (`href`), якорей (`id`), истории версий,
  ревизий, совместного редактирования и гостевого доступа.

## 2. Модель

| Сущность | Таблица | Ключевое |
|---|---|---|
| Мир | `lore_world` | `owner_id`, `code` (глобально уникален), `visibility public\|private`, `space_code`, `version`, архив |
| Раздел | `lore_section` | `world_id`, `code` (уникален в мире), `parent_code`, `sort_order`, `icon` |
| Статья | `lore_article` | `world_id`, `code` (уникален в мире), `title`, `summary`, `body_html`, `body_text`, `section_code`, `keywords`, `status`, `visibility`, `readers`, `version`, архив |
| Ссылка | `lore_link` | `source_article_id`, `target_type`, `target_code`; пересобирается при сохранении статьи |

- `code` — стабильный ключ URL и ссылок: `^[a-z0-9][a-z0-9_-]{0,63}$`.
  После создания не меняется; переименование — отдельная задача.
- Статья живёт ровно в одном разделе или без раздела (`section_code = null`).
- Дерево разделов: глубина ≤ 5, ≤ 500 узлов; циклы и неизвестные родители
  отвергаются. `saveTree` заменяет дерево целиком и требует, чтобы все
  `section_code` статей оставались в новом дереве.
- Права: [`lore.create`, `lore.view_all`, `lore.edit_all`](#6-права). Создатель
  мира — владелец; владелец правит свой мир и видит все его статьи.

## 3. Видимость

Две независимые оси статьи: `status draft|published` и
`visibility public|secret`; у `secret` — список `readers` (`UserLinkSetField`).

| Кто | draft | published + public | published + secret |
|---|---|---|---|
| Владелец мира | да | да | да |
| `lore.view_all` / `lore.edit_all` | да | да | да |
| Пользователь из `readers` | нет | да | да |
| Остальные (мир `public`) | нет | да | нет |

- `private`-мир закрыт для всех, кроме владельца и админов кодекса: снаружи
  он отвечает `LORE_NOT_FOUND`, а не `LORE_DENIED`.
- Недоступная статья тоже отвечает `LORE_NOT_FOUND`, чтобы не раскрывать
  существование секрета.
- `readers` не участвуют в доступе при `visibility = public`, но данные не
  стираются: при возврате в `secret` список сохраняется.
- Фильтрация — на сервере: дерево, поиск и backlinks не показывают
  недоступные статьи. Подпись ссылки в публичном теле остаётся
  ответственностью автора.

## 4. HTTP

Все действия — `csrf: true`; вход — JSON-объект, лишние ключи отвергаются.

| Action | Вход | Выход |
|---|---|---|
| `lore.findWorldPage` | `limit`, `offset`, `q?`, `state? active\|archived\|all`, `visibility?` | `{ items, total }` |
| `lore.getWorld` | `code` | мир + `sections`, `articles` (срезы), `canEdit`, счётчики |
| `lore.saveWorld` | `world{code,name,description,visibility,spaceCode}`, `version?` | мир; без `version` — создание |
| `lore.archiveWorld` | `code`, `archived`, `expectedVersion` | мир |
| `lore.saveTree` | `code`, `sections[]`, `expectedWorldVersion` | мир + новое дерево |
| `lore.findArticlePage` | `worldCode`, `limit`, `offset`, `q?`, `sectionCode?`, `keywordIds?`, `status?`, `visibility?`, `sort? order\|recent`, `includeArchived?` | `{ items, total }` |
| `lore.getArticle` | `worldCode`, `articleCode` | статья + `canEdit`, `links`, `backlinks`, `readers` |
| `lore.saveArticle` | `worldCode`, `article{...}`, `expectedVersion?` | статья; без `expectedVersion` — создание |
| `lore.archiveArticle` | `worldCode`, `articleCode`, `archived`, `expectedVersion` | статья |
| `lore.findReaderCandidates` | `worldCode`, `q?`, `limit?` (1..50) | `{ items: [{ id, login, name }] }` |
| `lore.exportWorld` | `worldCode` | документ `powerscale.lore` |
| `lore.importWorld` | `payload`, `mode? new\|merge`, `targetWorldCode?`, `conflictPolicy? skip\|overwrite`, `dryRun?` | отчёт |

Коды ошибок: `AUTH_REQUIRED`, `AUTH_DENIED`, `LORE_INVALID`, `LORE_NOT_FOUND`,
`LORE_DENIED`, `LORE_VERSION_CONFLICT` (в `details` — `currentVersion`,
`updatedAt`, `updatedBy`).

`findReaderCandidates` — узкий поиск для редактора мира: `id`, `login`, `name`,
без email и профиля. Он не требует `user.view`.

## 5. HTML, ссылки и поиск

- `body_html` хранится как есть (`HtmlField`, LONGTEXT) до 60 000 байт;
  `body_text` считается на сервере (`LoreBodyText`) и клиентом не принимается.
- Санитизация — на рендере через общий `DESCRIPTION_HTML_CONFIG`; в allowlist
  добавлен `data-lore-code`. `href`, `img`, `id` не разрешены.
- Разрешённые ссылки: `data-lore-code` (статья), `data-rule-code` (правило),
  `data-inline-type`/`data-inline-value` (сущности заметок и чата).
- При сохранении статьи `lore_link` пересобирается целиком. Ссылки нужны для
  «Ссылки сюда» и для отчёта импорта; битые `data-lore-code` не блокируют
  сохранение и помечаются в UI как ненаписанные статьи.
- Извлечение — `DOMDocument` (`LIBXML_NONET`), при недоступности расширения —
  regex-fallback. Внешняя зависимость `ext-dom` не добавляется в `composer.json`.
- Поиск — `LIKE` по `title`, `summary`, `body_text`, `code`; фильтры:
  раздел, признаки (`@keywords`), статус, видимость. Полнотекстового движка нет.
- Признаки — глобальный справочник `Roleplay/Keyword` (`LinkSetField`).
  Импорт досоздаёт использованные признаки из снимков файла.

## 6. Права

| Ключ | Смысл |
|---|---|
| `lore.create` | Создавать мир; создатель становится владельцем |
| `lore.view_all` | Читать любые миры и статьи, включая draft/secret |
| `lore.edit_all` | Править любые миры и статьи, экспортировать их |

Ключи — константы `LorePermissionKeys`; сидов в setup нет. Категория
`lore` регистрируется на фронте и назначается группам через админку прав.

## 7. Файл `powerscale.lore` v1

Серверный и атомарный: файл собирает и разбирает сервер, импорт — одна
транзакция. Это осознанное отличие от клиентского файла ревизии правил
([`revision-file-roadmap.md`](revision-file-roadmap.md)).

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
  "source": { "ownerLogin": "gm", "articleCount": 2, "sectionCount": 3 },
  "sections": [
    { "code": "history", "name": "История", "description": "", "parentCode": null, "sortOrder": 0, "icon": "mdi-history" }
  ],
  "keywords": [{ "code": "north", "name": "Север", "description": "", "active": true }],
  "articles": [
    {
      "code": "fall-of-the-empire",
      "title": "Падение империи",
      "summary": "",
      "bodyHtml": "<p>См. <a data-lore-code=\"old-capital\">столицу</a>.</p>",
      "sectionCode": "history",
      "sortOrder": 0,
      "keywordCodes": ["north"],
      "status": "published",
      "visibility": "secret",
      "readers": ["bob"],
      "isArchived": false
    }
  ]
}
```

Правила:

- Только коды, без внутренних id. `readers` — логины, `keywordCodes` — коды.
- Экспорт доступен владельцу и `lore.edit_all`; в файл попадают все статьи,
  включая draft, secret и архивные.
- Импорт: `new` создаёт мир (занятый код получает суффикс `-2`, `-3`, …),
  владелец — текущий актор; `merge` пишет в `targetWorldCode` и не меняет мета
  мира. `dryRun` возвращает отчёт без записи.
- Конфликты по `code` разделов и статей: `skip` не трогает существующее,
  `overwrite` заменяет. `rename` не поддерживается.
- Признаки: существующий код не перезаписывается, отсутствующий создаётся из
  снимка. Битые `data-lore-code` — предупреждение, не ошибка.
- Readers: неизвестный логин — предупреждение `reader-unknown`, логин
  отбрасывается.
- HTML: `script`/`style`/`iframe`/`object`/`embed`/`form` и обработчики `on*`
  удаляются, `img` и `href` вырезаются; каждое действие даёт предупреждение
  `html-*`. Размер тела проверяется после чистки.
- Блокирующая ошибка — `LORE_INVALID`, транзакция откатывается.

## 8. Версии и конфликты

- `lore_world.version` растёт на `saveWorld`, `saveTree`, `archiveWorld`.
- `lore_article.version` растёт на `saveArticle`, `archiveArticle`.
- Правка требует `expectedVersion` / `expectedWorldVersion`; несовпадение —
  `LORE_VERSION_CONFLICT`. Истории версий и отката нет; локальный черновик
  редактора живёт в localStorage.

## 9. UI

- «Кодекс» — пункт меню, `/lore`; список миров, страница мира, статья,
  редактор статьи, настройки мира.
- Формат страниц — как у «Игр»: действия (создать, настройки, импорт/экспорт,
  сохранить) живут в топбаре через `Teleport to="#editor-actions"`; страницы не
  дублируют название в заголовке и не показывают кнопку «назад».
- Хлебные крошки показывают названия мира и статьи, а не коды: страницы кладут
  их в `Store/crumbs` после загрузки, крошки читают стор реактивно.
- Страница мира — без бокового дерева: описание, сводка, разделы со статьями и
  недавние статьи на всю ширину.
- Ссылки `data-lore-code` внутри статьи открывают статью в слайдере
  (`LoreArticleSlider`, общий `SlidePanel`), не уводя со страницы; в слайдере
  есть переход на страницу статьи и вложенные ссылки. Ссылки на правила
  открывают `RuleSlider` поверх.
- Настройки мира — вкладки «Основное» (мета, видимость, пространство правил,
  архив) и «Разделы» (дерево). Пространство правил выбирается из каталога
  `RuleSpace` и сохраняется мягкой ссылкой `spaceCode`; ручной ввод кода не
  используется.
- Дерево разделов — общий компонент `Core/UI/Component/Tree/TreeEditor.vue`
  (переиспользуется деревом секций `RuleSpace`): вложенность, создание,
  переименование, перетаскивание; удаление листа с переносом соседей.
- Выбор раздела — общий `Core/UI/Component/Input/TreeSelect.vue` (дерево с
  поиском и путём; его же использует фильтр секций в персонаже и пространстве
  правил). Числовые лимиты — общий `ClampedNumberField`.
- Поля формы — одна плотность и вариант (`outlined`/`comfortable`) у полей и
  селектов; в редакторе статьи порядок полей — заголовок, краткое описание,
  признаки, раздел на всю ширину, затем строки «Видимость + Порядок» и
  «Код + Статус».
- Подсказка о публичной видимости не выводится: для `public` блок readers просто
  скрыт.
- Карточки — плоские подложки из темы (surface/`primaryLight`), без чёрных
  `outlined`-рамок.
- Обратная связь о сохранении — `LoreNotice`: цветной тост сверху с иконкой и
  закрытием, а не мелкий снизу. Несохранённые правки статьи — свой диалог
  «Остаться / Уйти без сохранения» (`window.confirm` не используется).
- Редактор — TipTap с таблицами и ссылками на статьи/правила; «создать статью
  из выделения» сохраняет черновик и вставляет ссылку.
- Импорт/экспорт — диалоги, кнопки в топбаре страницы мира и настроек; импорт
  сначала показывает отчёт проверки.
- Mock-режим повторяет контракт: два мира, разделы, secret/draft статьи,
  конфликт версий, round-trip импорта/экспорта.

## 10. Границы реализации

- Бэкенд: `IUserAccess`, `IUserAccounts`, `IKeywords`, `ISmartTableGateway`;
  без Rule/RuleSpace.
- Фронт: `Core/Engine` (локатор, `runAction`), `Core/UI` (`DescriptionHtml`,
  редакторские расширения), `User` (права), `Keyword` (признаки), `Rule` и
  `RuleSpace` (выбор правил), `Messages/Inline` не импортируется.
- Стабильные контракты SmartTable/User/Keyword не менялись; добавлены только
  новые карты `lore_*` и действия `lore.*`.
