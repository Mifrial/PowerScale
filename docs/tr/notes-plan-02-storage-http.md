# План 02 — Messages/Notes: хранение и HTTP

**Статус:** ~СДЕЛАНО~ — 2026-11-08. Шаг 1 нарезки
[`notes-roadmap.md`](notes-roadmap.md).
**Реализация:** ~PHP~
**Опираться на:** ~PHP~ (SmartTable, Chat, `IUserAccess`, `IUserAccounts`)

Цель — таблица `note`, фасад `INotes` и HTTP-действия `note.*`. Никакого Vue и
никаких импортов Character/Game/Map/Chat.

## 1. Скелет модуля

```text
modules/Messages/Notes
├── Action/                 note.*
├── Container/NotesContainer.php
├── Dto/                    NoteRecord, NotePage, NewNote, NotePatch, NoteFilter, NoteHostGroup
├── Dto/Action/             входы действий
├── Exception/              NoteException, NoteInvalidException, NoteNotFoundException
├── Interface/Container/INotesContainer.php
├── Interface/Service/INotes.php
├── Repository/NoteRepository.php
├── Schema/NotesSchema.php
├── Service/NoteHttpService.php, NoteViewAssembler.php, NoteBodyText.php, NotePortFactory.php
├── Setup/NotesModuleSetup.php
├── Table/NoteTable.php
├── module.config.php
└── tests/
```

Регистрация:

- `config/modules.php` — `INotesContainer` в `lazy`, группа `Messages`, имя
  `Notes`;
- `composer.json` — PSR-4 `Mifrial\Messages\Notes\` и тестовый неймспейс;
- `phpunit.xml.dist` — suite `notes`;
- контейнер `NotesContainer` без методов, как у Chat.

## 2. Таблица `note`

`NoteTable extends SmartTableDefinition`:

| Поле | Класс | Настройки |
|---|---|---|
| `id` | `IdField::big()` | PK |
| `host_type` | `StringField` | required, `indexed`, длина 32 |
| `host_id` | `IntField` | required, `indexed` |
| `author_id` | `UserReferenceField` | required, `indexed` |
| `title` | `StringField` | required, длина 255 |
| `body_html` | `TextField` | required, default `''` |
| `body_text` | `TextField` | required, default `''` |
| `pinned` | `BoolField` | required, default `false`, `indexed` |
| `sort_order` | `IntField` | required, default `0` |
| `active` | `BoolField` | required, default `true` |
| `created_at`, `updated_at` | `DateTimeField` | required, `DateTimeNow` |

Составной индекс `(author_id, host_type, host_id, sort_order)` **не заявлять**:
SmartTable сейчас даёт только одиночные `indexed` и составные `unique`.
Fulltext-поиска нет.

`NotesSchema::getTableClasses()` возвращает `[NoteTable::class]`;
`NotesModuleSetup` отдаёт эту карту и пустой список data-step.

## 3. DTO

- `NewNote`: `hostType`, `hostId`, `title`, `bodyHtml`.
- `NotePatch`: nullable `title`, `bodyHtml`, `pinned`; `null` — «не менять».
- `NoteFilter`: `q`, `hostType`, `hostId`, `authorId`, `pinned`, `state`,
  `limit`, `offset`; все фильтры, кроме страницы, nullable. `state` —
  `active` (по умолчанию), `archived` или `all`.
- `NoteHostFilter`: `q`, `hostType`, `authorId`, `state`, `limit`; `limit`
  1…500 (по умолчанию 200). Это не страница: у агрегата SmartTable нет offset.
- `NoteHostGroup`: `hostType`, `hostId`, `count`, `lastUpdatedAt` (unix int).
- `NoteRecord`: полная строка, включая `bodyText` и `active`.
- `NotePage`: `items: NoteRecord[]`, `total`.
- JSON-вид собирает `NoteViewAssembler`: поля записи плюс `authorName`,
  `authorLogin`, `canEdit`, `canDelete`; моменты — unix int, как в Chat.
- `canEdit = authorId === actor.id && active`; `canDelete = authorId ===
  actor.id`. Архивная заметка read-only, но удалить её автор может.

## 4. Фасад `INotes`

```php
add(RequestActor $actor, NewNote $note): NoteRecord
update(RequestActor $actor, int $noteId, NotePatch $patch): NoteRecord
delete(RequestActor $actor, int $noteId): void
get(RequestActor $actor, int $noteId): NoteRecord
findPage(RequestActor $actor, NoteFilter $filter): NotePage
findHosts(RequestActor $actor, NoteHostFilter $filter): array
reorder(RequestActor $actor, string $hostType, int $hostId, array $noteIds): void
pin(RequestActor $actor, int $noteId, bool $pinned): NoteRecord
deactivateForHost(string $hostType, int $hostId): int
```

Фасад отдаёт DTO, JSON собирает `NoteHttpService` через `NoteViewAssembler` —
как `IChats`/`ChatHttpService`. `findHosts` возвращает
`array<int, NoteHostGroup>`.

`deactivateForHost` — только фасад для PHP-хостов. HTTP-маршрута у него нет:
иначе любой клиент гасил бы чужие заметки без проверки доступа к сущности.

## 5. Правила сценария

### Автор

- `add` пишет `author_id = actor.id`, `active = true`.
- `update`, `delete`, `pin`, `reorder` — только если `author_id === actor.id`.
  `hasBypass()` здесь не помогает.
- `update` и `pin` архивной заметки — `NOTE_INVALID`; `delete` архивной —
  разрешён.
- `get`: своя заметка — всегда; чужая — только bypass и только на чтение.
- `findPage`: не-bypass принудительно получает `author_id = actor.id`, даже
  если в запросе чужой id. Bypass использует `authorId` как фильтр; без него
  видит всех авторов.
- `findHosts`: те же правила авторства; `q` сужает сущности до тех, у которых
  есть совпадающие заметки; `limit` 1…500 (по умолчанию 200). В выборку
  попадают только сущности, у которых есть заметки, — каталога хостов у Notes
  нет.
- `state`: `active` → `active = 1`; `archived` → `active = 0`; `all` — без
  условия. В host-панели всегда `active`.
- host-панель: фронт передаёт `authorId` текущего актора, поэтому даже bypass
  видит там только свои заметки; чужие — только на `/notes`.

### Ошибки

- нет актора — `AUTH_REQUIRED`;
- невалидные поля/страница/список — `NOTE_INVALID`;
- нет строки, чужая для обычного пользователя — `NOTE_NOT_FOUND`;
- попытка записи в чужую заметку у bypass — `AUTH_DENIED`.

Отдельные исключения модуля: `NoteException` (база), `NoteInvalidException`
(`NOTE_INVALID`), `NoteNotFoundException` (`NOTE_NOT_FOUND`).

### Валидация

- `host_type`: `^[a-z][a-z0-9_]{0,31}$`;
- `host_id > 0`;
- `title`: trim, 1…255 символов;
- `body_html`: не больше 65 535 байт (предел `TEXT`);
- `q`: trim, не длиннее 100 символов;
- `limit`: 1…100 для `findPage`, 1…500 для `findHosts`; `offset ≥ 0`;
- `noteIds`: непустые уникальные положительные int.

## 6. `body_text`

`NoteBodyText::derive(string $html): string`:

1. заменить `<br>` и закрывающие блочные теги (`</p>`, `</div>`, `</li>`,
   `</h1>`…`</h6>`, `</blockquote>`, `</td>`, `</th>`) пробелом;
2. `strip_tags`;
3. `html_entity_decode(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')`;
4. `\u{00A0}` → пробел;
5. схлопнуть `\s+` в один пробел, trim.

Пересчитывать при `add` и при любом изменении `body_html`; клиентский
`body_text` не принимается.

## 7. Порядок и закрепление

- `add`: `sort_order = max(sort_order) + 1` среди активных заметок автора и
  хоста; первая — `0`.
- `pin`: меняет только `pinned`, порядок не трогает.
- `reorder`: принимает полный список id активных заметок автора и хоста.
  Проверить, что множества совпадают, и записать `0…n-1` в одной транзакции.
  Частичный список, чужой id или неактивная заметка — `NOTE_INVALID`.
- Выдача панели хоста: `pinned DESC, sort_order ASC, id ASC`.
- Выдача `/notes`: `pinned DESC, updated_at DESC, id DESC`.

## 8. Репозиторий

`NoteRepository` поверх `ISmartTableGateway`:

- `add`, `update`, `delete`, `getById`;
- `findPage` через `ListQuery`:
  - фильтр `author_id`, `host_type`, `host_id`, `pinned`, `active` (по `state`);
  - `%title`/`%body_text` — LIKE-группа по `q` (шаблон `%q%`); как в
    `UserPageQuery`, ESCAPE не добавляется, поэтому `%` и `_` во входе
    работают как SQL-wildcard. Это осознанно: отдельного `ESCAPE` в SmartTable
    нет;
  - сортировка и `countTotal` по сценарию;
- `findHosts` — агрегат SmartTable: GROUP BY `host_type, host_id`,
  `COUNT(*)`, `MAX(updated_at)`; фильтры авторства, `state`, `hostType`, `q`.
  Агрегат умеет сортировать только по ключам группы (`host_type, host_id`), не
  по мере. Поэтому репозиторий берёт `limit = 500`, сортирует ряды в PHP по
  `lastUpdatedAt DESC, hostId DESC` (сравнение по unix-секундам) и отдаёт
  запрошенный `limit`. При более
  чем 500 сущностях с заметками список будет неполным — это предел агрегата,
  а не ошибка;
- `maxSortOrder(authorId, hostType, hostId)`;
- `idsForHost(authorId, hostType, hostId)`;
- `reorder` в `ISmartTableGateway::transaction`;
- `deactivateForHost` — `UPDATE note SET active = 0 WHERE host_type = ? AND
  host_id = ? AND active = 1`; вернуть число погашенных.

## 9. HTTP

`NoteHttpService` получает `IUserAccess`, `INotes`, `IUserAccounts`,
`NoteViewAssembler`. Действия:

| Action | Вход | Выход |
|---|---|---|
| `note.findPage` | `NoteFilterInput` | `{ items, total }` |
| `note.findHosts` | `NoteHostFilterInput` | `{ items: NoteHostGroup[] }` |
| `note.get` | `noteId` | заметка |
| `note.create` | `hostType`, `hostId`, `title`, `bodyHtml` | заметка |
| `note.update` | `noteId`, nullable `title`/`bodyHtml` | заметка |
| `note.delete` | `noteId` | `{ deleted: true }` |
| `note.reorder` | `hostType`, `hostId`, `noteIds` | `{ reordered: n }` |
| `note.pin` | `noteId`, `pinned` | заметка |

Все маршруты — `csrf: true`. Имена авторов собираются пачкой через
`IUserAccounts::getByIds` (не по одной строке).

## 10. Тесты

Unit:

- `NoteBodyText`: теги, `<br>`, entities, `&nbsp;`, пробелы, inline-подпись;
- валидация `NewNote`/`NotePatch`/`NoteFilter`/`noteIds`;
- `NoteViewAssembler`: `canEdit`, имена авторов, отсутствие лишних полей.

MySQL (`NotesMysqlTest`):

- CRUD одной заметки, `body_text` пересчитан;
- несколько заметок на один хост;
- обычный пользователь не видит и не правит чужие;
- bypass читает чужую (`canEdit = false`), но не правит и не удаляет;
- `findPage`: `q` по заголовку и телу, `hostType/hostId`, `pinned`,
  `state`, `limit/offset`, `total`;
- `findHosts`: группировка по паре хост+id, счётчик, `MAX(updated_at)`, фильтры
  `q`/`hostType`/`state`/автор, обычный пользователь видит только свои;
  сортировка по `lastUpdatedAt` и обрезка до `limit` — в PHP;
- `reorder`: успешная нормализация, частичный список → `NOTE_INVALID`,
  чужой id → `NOTE_INVALID`, неактивная → `NOTE_INVALID`;
- `pin` не меняет `sort_order`;
- `deactivateForHost` гасит только пару хост+id и возвращает число;
- `author_id` чужой в запросе игнорируется у обычного пользователя.

## 11. Гейты

- `composer stan`;
- `composer test:unit`;
- `php www/mifrial/vendor/bin/phpunit --testsuite notes` (из `www/mifrial`);
- `composer maps-check`, `composer docs-check` — после обновления карт.
