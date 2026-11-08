# Нарезка Messages/Notes

**Статус:** ~СДЕЛАНО~ — 2026-11-08. Шаги 0–3 сделаны: `Messages/Inline`,
`Messages/Notes`, Character и Game переведены, `characters.owner_notes` снят.
Личные заметки с HTML-телом вынесены из `characters.owner_notes` в общий модуль.
**Отложено:** интеграция Map (слайдер и `deactivateForHost` из архивации карты)
в текущую поставку не входит; карта подключается тем же способом, что
Character и Game, когда понадобится.
**Реализация:** ~PHP~, ~VUE~, ~МОК~
**Опираться на:** ~PHP~ (Chat, SmartTable, `IUserAccess`), ~НЕ ОСНОВА~ (моки
Character/Game как контракт заметок)

Личный блокнот с rich-текстом, а не поле на сущности. Одна заметка — один
заголовок и одно HTML-тело; заметок у пары «автор + сущность» сколько угодно.
Заметки не являются летописью: `GameChronicle` остаётся отдельной доменной
сущностью и в `note` не переезжает.

Смежные решения: [`chat-system.md`](chat-system.md) (текущий инлайн-слой),
[`data-model.md`](data-model.md) (таблица `note`), [`TR.md`](TR.md),
[`architecture.md`](architecture.md) (дерево и рёбра).

## 1. Границы

- Заметка принадлежит **автору**, а `(host_type, host_id)` — только ярлык, к
  чему она привязана.
- Читает и пишет только автор. `RequestActor::hasBypass()` даёт супер-админу
  **только чтение** чужих заметок; править и удалять их он не может.
- `Messages/Notes` **не проверяет доступ к хосту**. Показывать ли заметки на
  странице персонажа, решает Character: он же решает, кому доступна страница.
  Следствие: любой зритель сущности может вести о ней **свои** заметки.
- Заметки не знают имён хостов и типов инлайнов. Хост регистрирует метаданные
  на фронте, инлайн-типы приходят из реестра `Messages/Inline`.
- Модуль не импортирует Character, Game, Map, Chat и Rule. Обратное ребро
  `Character → Notes` допустимо: страница-хост открывает слайдер.

## 2. Модули и рёбра

Появляются два модуля:

- **`Messages/Inline`** — только фронтенд. Общий инлайн-реестр, токены,
  TipTap-редактор и HTML-рендер. PHP-модуля нет, карточки module-map нет.
- **`Messages/Notes`** — PHP + Vue. Таблица, фасад, HTTP и общий интерфейс
  заметок.

Рёбра фронта:

- `Inline → Engine, UI, User`;
- `Chat → Inline` — Chat реэкспортирует старые функции из `init.ts` и держит
  тонкие файлы-мосты на прежних путях для прямых импортов Game/Rule/Character;
- `Notes → Inline, Engine, UI, User`;
- `Character → Notes` (слайдер), `Game → Notes` (слайдер и регистрация
  хоста); `Map → Notes` — позже;
- `Notes ↛ Chat`, `Notes ↛ Character/Game/Map/Rule`.

Ребро PHP: `Notes` → SmartTable + `IUserAccess` + `IUserAccounts`. Хосты позже
вызывают `INotes::deactivateForHost()` при удалении/архивации сущности.

## 3. Хранение

Таблица `note`:

| Поле | Тип | Смысл |
|---|---|---|
| `id` | bigint PK | идентификатор |
| `host_type` | varchar(32), required | непрозрачная строка: `character`, `game`, `map` |
| `host_id` | int, required | id сущности |
| `author_id` | FK user, required | автор; удаление учётки — `cascade` |
| `title` | varchar(255), required | заголовок |
| `body_html` | text, required, default `''` | HTML редактора |
| `body_text` | text, required, default `''` | производный текст для поиска и превью |
| `pinned` | bool, required, default `false` | закрепление |
| `sort_order` | int, required, default `0` | ручной порядок в рамках автора и хоста |
| `active` | bool, required, default `true` | деактивация без удаления |
| `created_at`, `updated_at` | datetime (unix int) | моменты записи |

Индексы: одиночные `indexed` на `author_id`, `host_type`, `host_id`,
`pinned`. Составной INDEX в SmartTable пока не поддерживается — не обещать его
в плане; при росте таблицы добавлять вместе с поддержкой в SmartTable.
Fulltext нет: поиск идёт `LIKE` по `body_text`. `title` в поиск входит
отдельным `LIKE`-условием. ESCAPE SmartTable не поддерживает, поэтому `%` и `_`
во входе работают как SQL-wildcard — как в поиске User.

`body_text` считает сервер: заменить `<br>` и концы блочных тегов пробелом,
снять теги, раскодировать entities, заменить `&nbsp;`, схлопнуть пробелы.
Так в поиск попадают подписи инлайнов, а не HTML-атрибуты.

Порядок:

- в панели хоста — `pinned DESC, sort_order ASC, id ASC`;
- на `/notes` — `pinned DESC, updated_at DESC, id DESC`;
- `sort_order` при создании — в конец списка, `reorder` нормализует в `0…n-1`;
- `pinned` не перетаскивается: закреплённая заметка всегда выше.

## 4. Права и API

Фасад `INotes`:

- `add(actor, NewNote)`, `update(actor, noteId, NotePatch)`;
- `delete(actor, noteId)`, `get(actor, noteId)`;
- `findPage(actor, NoteFilter)` — `q`, `hostType`, `hostId`, `authorId`,
  `pinned`, `state`, `limit`, `offset`;
- `findHosts(actor, NoteHostFilter)` — сущности, у которых есть заметки
  (для левой колонки `/notes`); агрегат SmartTable с группировкой и
  `MAX(updated_at)`, поэтому сортировка по свежести и обрезка до `limit` —
  в PHP, максимум 500 групп;
- `reorder(actor, hostType, hostId, noteIds)`;
- `pin(actor, noteId, pinned)`;
- `deactivateForHost(hostType, hostId)` — только фасад, без HTTP; вызывает хост
  при удалении/архивации сущности.

Правила:

- обычный пользователь: `authorId` принудительно равен актору, даже если в
  запросе передан чужой;
- bypass: `authorId` — свободный фильтр; без фильтра видны все авторы;
- `update`, `delete`, `reorder`, `pin` — только автор, bypass не помогает;
- в панели хоста всегда только свои заметки, даже у админа; чужие — только на
  `/notes`;
- `active = false` скрывает заметку из панели хоста и обычного поиска; на
  `/notes` архив открывается фильтром `state = archived` (или `all`); неактивная
  заметка read-only, удалить её автор может;
- `canEdit` — свой автор и активная заметка; `canDelete` — свой автор;
- `reorder` принимает полный список id своего хоста и пишет порядок в одной
  транзакции; чужая или неактивная заметка — `NOTE_INVALID`.

HTTP: `note.findPage`, `note.findHosts`, `note.get`, `note.create`,
`note.update`, `note.delete`, `note.reorder`, `note.pin`; все с `csrf: true`.
Ошибки: `NOTE_INVALID`, `NOTE_NOT_FOUND`, `AUTH_REQUIRED`, `AUTH_DENIED`.

## 5. UI

- `NotesWorkspace` — список + редактор, общий для слайдера и `/notes`.
- `NotesSlider` — `v-navigation-drawer temporary`, открывается со страницы
  хоста, всегда `hostType + hostId` и только свои заметки.
- `NotesPage` `/notes` — как мессенджер:
  - слева узкая колонка сущностей, у которых есть заметки текущего автора
    (иконка из реестра хостов, число заметок, последнее обновление);
  - справа список заметок выбранной сущности;
  - открытая заметка сдвигает список на место сущностей (узкая левая колонка),
    редактор занимает правую часть; закрытие возвращает сущности и список;
  - сверху фильтр-бар: `q`, тип сущности, `state` (`Активные` / `Архив` /
    `Все`), у bypass — автор;
  - у bypass — баннер о чтении чужих заметок.
- Создание заметки — на странице хоста (слайдер): у `/notes` нет каталога
  сущностей, только те, где заметки уже есть.
- `registerNoteHost({ type, label, icon, route?, resolveEditorContext? })` —
  фронтовый реестр метаданных; Notes не импортирует хосты. Неизвестный тип
  показывается строкой. Резолвер контекста нужен только `/notes`; в слайдере
  контекст приходит пропсами со страницы хоста.
- Пункт меню «Заметки», order 25 — между Мессенджером (20) и Уведомлениями (30).
- Inline: редактор вставляет `span[data-inline-type]`; список показывает
  `body_text`-превью, полный rich-text с чипами — только у открытой заметки;
  архивная и чужая заметка показываются read-only через `RichTextHtml`.
- Сохранение — явное: кнопка и `Ctrl+Enter`; смена заметки при несохранённых
  правках спрашивает подтверждение. Автосохранения нет.

## 6. Этапы

0. **`Messages/Inline`** — вынос из Chat, общий редактор, Chat-реэкспорт.
   Подробно: [`notes-plan-01-inline-extract.md`](notes-plan-01-inline-extract.md).
1. **`Messages/Notes`: хранение и HTTP** — схема, фасад, действия, тесты.
   Подробно: [`notes-plan-02-storage-http.md`](notes-plan-02-storage-http.md).
2. **`Messages/Notes`: Vue** — API/mock, workspace, слайдер, `/notes`,
   меню, inline. Подробно: [`notes-plan-03-vue.md`](notes-plan-03-vue.md).
3. **Character** — удаление `owner_notes`, DDL-шаг, слайдер, регистрация хоста.
   Подробно: [`notes-plan-04-character.md`](notes-plan-04-character.md).
4. **Game** — сделано вместе с шагом 3: `personalNotes` убран из `GameDetail`
   и мока, карточка игры открывает слайдер `hostType = 'game'`, хост
   зарегистрирован для `/notes`. **Map** — отложено: слайдер и
   `deactivateForHost` из архивации карты подключаются тем же способом, когда
   карта понадобится.

## 7. Параллелить нельзя

- Inline-вынос и перевод Rule/Game/Character на новые импорты в одном заходе:
  сначала реэкспорт, потребители не меняются.
- `Messages/Notes` с импортом Chat, Character, Game, Map или Rule.
- Проверку доступа к хосту внутри Notes — её делает страница-хост.
- Один общий `owner_notes`-блок вместо нескольких заметок.
- Запись/удаление чужих заметок через bypass.
- HTTP-деактивацию хоста: только фасадный вызов из PHP-хоста.
- Удаление `owner_notes` вместе с незавершённой сессией Game: её MySQL-тесты
  создают персонажей с `ownerNotes`/`owner_notes`. Правка фикстур входила в
  шаг 3; сделана после завершения сессии Game.
- Полнотекстовый поиск и составной индекс, пока их нет в SmartTable.

## 8. Документы и гейты

Обновить при реализации:

- `architecture.md` — дерево `Messages: Chat, Inline, Notes, Notifications` и
  рёбра;
- `TR.md`, `data-model.md` — таблица `note`, удаление `characters.owner_notes`;
- `character-system.md`, `character-roadmap.md` — заметки больше не поле листа;
- `game-roadmap.md` — `personalNotes` уходит в Notes.

Регистрация PHP-модуля: `module.config.php`, `INotesContainer`, запись в
`config/modules.php`, PSR-4 в `composer.json`, suite `notes` в
`phpunit.xml.dist`. Фронт: `moduleRoutes.ts`, `main.ts`, `init.ts`.

Проверки:

- `npx vitest run` по затронутым файлам;
- `composer stan`, `composer test:unit`;
- `phpunit --testsuite notes`;
- `composer maps-check`, `composer docs-check`;
- после ручной проверки — полный прогон `bash tools/test-full.sh`.
