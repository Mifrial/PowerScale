# План 03 — Messages/Notes: Vue

**Статус:** ~СДЕЛАНО~ — 2026-11-08. Шаг 2 нарезки
[`notes-roadmap.md`](notes-roadmap.md).
**Реализация:** ~VUE~, ~МОК~
**Опираться на:** ~PHP~ (контракт `note.*`), ~МОК~ (свой mock)

Цель — общий интерфейс заметок: слайдер на странице хоста и страница `/notes`.
Notes не импортирует хосты и не знает типов инлайнов; всё доменное приходит
через реестр хостов и `Messages/Inline`.

## 1. Скелет модуля

```text
src/modules/Messages/Notes
├── Component/NotesWorkspace.vue, NotesSlider.vue, NoteHostSidebar.vue, NoteList.vue, NoteEditor.vue, NoteCreateDialog.vue
├── Constant/Navigation/NOTES_MENU_ITEM.ts
├── Dto/Note.ts, NotePage.ts, NoteFilter.ts, NewNote.ts, NotePatch.ts, NoteHost.ts, NoteHostContext.ts, NoteHostFilter.ts, NoteHostGroup.ts
├── Interface/INoteApi.ts
├── Mock/mockNoteApi.ts, mockNotes.ts
├── Page/NotesPage.vue
├── Service/NoteApi.ts, NoteViewService.ts, Instance/noteHostRegistry.ts
├── init.ts, routes.ts
└── __tests__/
```

## 2. API

`INoteApi`:

```ts
findPage(filter: NoteFilter, signal?: AbortSignal): Promise<NotePage>
findHosts(filter: NoteHostFilter, signal?: AbortSignal): Promise<NoteHostGroup[]>
get(id: number, signal?: AbortSignal): Promise<Note>
create(data: NewNote, signal?: AbortSignal): Promise<Note>
update(id: number, patch: NotePatch, signal?: AbortSignal): Promise<Note>
delete(id: number, signal?: AbortSignal): Promise<void>
reorder(hostType: string, hostId: number, noteIds: number[], signal?: AbortSignal): Promise<void>
pin(id: number, pinned: boolean, signal?: AbortSignal): Promise<Note>
```

`Note`: `id`, `hostType`, `hostId`, `authorId`, `authorName`, `authorLogin`,
`title`, `bodyHtml`, `bodyText`, `pinned`, `sortOrder`, `active`, `canEdit`,
`canDelete`, `createdAt`, `updatedAt`; моменты — unix int, как в Chat.
`canEdit` — свой автор и активная заметка; `canDelete` — свой автор.

`NoteApi` — `Engine.runAction('note.*')`, регистрация
`Messages.Notes.Service.NoteApi`. `mockNoteApi` — тот же контракт, данные в
памяти; `mockNotes` держит несколько заметок для `character:1` и `game:1`.
Mock соблюдает авторство и bypass так же, как сервер.

`NoteFilter`: `q`, `hostType`, `hostId`, `authorId`, `pinned`, `state`,
`limit`, `offset`; `state` — `active` (по умолчанию), `archived`, `all`.

`NoteHostFilter`: `q`, `hostType`, `authorId`, `state`, `limit`.
`NoteHostGroup`: `hostType`, `hostId`, `count`, `lastUpdatedAt` (unix int).

## 3. Реестр хостов

`Dto/NoteHost.ts`:

```ts
interface NoteHost {
  type: string;
  label: string;
  icon: string;
  route?: (hostId: number) => string | null;
  resolveEditorContext?: (hostId: number) => Promise<NoteHostContext>;
}
```

`NoteHostContext`: `inlineContext: Record<string, unknown>` и
`tokenSources: ITokenSource[]`. Один резолвер вместо двух: и чипы, и «Вставить
ссылку» нужны вместе.

`registerNoteHost(host)` / `getNoteHost(type)` / `getNoteHosts()` в `init.ts`;
экземпляр реестра — в `Service/Instance/noteHostRegistry.ts`, без дублей по
`type`.

`resolveEditorContext` необязателен. В слайдере контекст уже собран страницей
хоста и передаётся пропсами; он нужен только `/notes`, где страницы хоста нет.
Character регистрирует резолвер через `ICharacterApi::getCharacter(id)` и
`useSpaceRevision().fetchRevision(spaceId, rulesRevision)`, чтобы чипы правил
работали и в общем списке. Ошибка резолвера не блокирует заметку: редактор
открывается с глобальными `getTokenSources()` и пустым контекстом.

## 4. NotesWorkspace

Пропсы:

```ts
{
  hostType?: string;   // host-режим
  hostId?: number;
  global?: boolean;    // /notes
  inlineContext?: Record<string, unknown>;
  tokenSources?: ITokenSource[];
}
```

Поведение host-режима:

- только свои активные заметки пары `hostType + hostId`: запрос всегда с
  `authorId = currentUser.id` и `state = 'active'`, даже у bypass;
- поиск по `q` с debounce 300 мс, серверный;
- «Новая заметка» создаёт строку с заголовком из диалога; тело может быть
  пустым;
- список: заголовок, превью `bodyText`, дата, кнопка pin;
- drag-and-drop только внутри списка, только среди незакреплённых; после
  drop — `note.reorder` полным списком; ошибка возвращает прежний порядок;
- выбранная заметка открывается в `NoteEditor`;
- `NoteEditor`: заголовок + `RichTextEditor` из `Messages/Inline`, явное
  сохранение (кнопка и `Ctrl+Enter`), индикатор несохранённых правок; перед
  `note.update` HTML прогоняется через
  `descriptionHtmlSanitizerService`; у заметки без `canEdit` (чужая у bypass,
  архивная) — `RichTextHtml` read-only, поля заголовка и тела недоступны;
- уход с заметки или закрытие слайдера при `dirty` — подтверждение;
- удаление — подтверждение, затем `note.delete`;
- пустой список — подсказка «Заметок пока нет».

Поведение `/notes`:

- фильтр-бар сверху: `q`, тип сущности, `state` (`Активные` / `Архив` /
  `Все`), у bypass — автор (пикер из `User` API);
- по умолчанию `state = active`; архив открывается только явным фильтром,
  неактивная заметка read-only, кнопка удаления остаётся;
- **режим «обзор»**: слева узкая колонка сущностей (`NoteHostSidebar`,
  280 px) — только те, у которых есть заметки по текущему фильтру; иконка и
  подпись из `getNoteHost(type)`, число заметок, последнее обновление;
  неизвестный тип — `type #id` без ссылки. Справа — `NoteList` выбранной
  сущности; при открытии выбирается первая сущность;
- **режим «заметка»**: по клику заметка открывается в `NoteEditor`; список
  заметок сдвигается на место колонки сущностей и становится узким, редактор
  занимает правую часть. Закрытие заметки возвращает колонку сущностей и
  список;
- смена сущности или фильтра закрывает открытую заметку;
- создание заметки — только в слайдере на странице хоста: у `/notes` нет
  каталога сущностей, список строится из `note.findHosts`;
- у bypass — баннер «Вы читаете чужие заметки», `canEdit = false` у чужой
  заметки;
- перетаскивание в глобальном списке отключено: порядок `pinned +
  updated_at`.

## 5. Слайдер

`NotesSlider.vue`:

- `v-model:open`, пропсы `hostType`, `hostId`, заголовок, `inlineContext`,
  `tokenSources`;
- `v-navigation-drawer temporary location="right" width="720"`;
- шапка с иконкой/названием хоста и кнопкой закрытия;
- внутри — `NotesWorkspace` в host-режиме;
- при закрытии с несохранёнными правками — подтверждение;
- компонент ничего не знает о Character: страница передаёт `hostType/hostId`.

Один экземпляр слайдера на страницу-хост.

## 6. Страница и меню

`routes.ts`: `{ path: 'notes', name: 'Notes', component: NotesPage, meta: {
title: 'Заметки' } }`. Гость не допускается (как у персонажей).

`NOTES_MENU_ITEM`: `id: 'notes'`, title «Заметки», `to: '/notes'`,
icon `mdi-note-multiple-outline`, order 25 — между Мессенджером (20) и
Уведомлениями (30).

`init.ts` регистрирует API, пункт меню и реестр хостов; `main.ts` вызывает
`registerNotesApi` в mock/real и `registerNotesModule` в `bootstrap()`;
`moduleRoutes.ts` подключает `routes`.

## 7. Инлайн

- `NoteEditor` передаёт `tokenSources` в `RichTextEditor`; вставка даёт
  `span[data-inline-type]`.
- Контекст открытой заметки: пропсы слайдера, иначе
  `getNoteHost(type)?.resolveEditorContext(hostId)`; ошибка резолвера —
  глобальные `getTokenSources()` и пустой контекст.
- Просмотр открытой заметки без `canEdit` — `RichTextHtml` с тем же контекстом.
- Список и превью — `bodyText`, без TipTap и без резолва рендереров.
- Notes не импортирует `Messages/Chat`; всё из `Messages/Inline`.
- Нет рендерера для типа — подпись из HTML остаётся текстом.

## 8. Регистрация

- `registerNotesApi(api)` в `init.ts`;
- mock-импорт в `registerApiLayer()` при `VITE_API_MODE !== 'real'`, реальный
  `NoteApi` — в ветке real;
- `registerNotesModule()` в `bootstrap()`;
- `moduleRoutes.ts` — импорт `routes` из Notes;
- порядок регистрации — после Chat/Inline и до Character, чтобы Character мог
  вызвать `registerNoteHost` при своём `init`.

## 9. Тесты

- `mockNoteApi`: CRUD, авторство, bypass-readonly, `state`, `findHosts`,
  `reorder`, `pin`, поиск, `canDelete` — `__tests__/Mock/mockNotes.test.ts`;
- `noteHostRegistry`: регистрация, дубль `type`, неизвестный тип,
  `resolveEditorContext` — `__tests__/Service/noteHostRegistry.test.ts`.

UI-mount-тесты компонентов не пишутся: по правилам фронтенда
(`frontend-rules.md`, F8) Vuetify-компоненты тестами не покрываются.
`NotesWorkspace`, `NotesPage`, `NotesSlider`, `NoteEditor` проверяются
typecheck, lint и ручным сценарием: создание, выбор, поиск, сохранение,
dirty-guard, pin, переключение «обзор ↔ заметка».

## 10. Гейты

- `npx vitest run` по `Messages/Notes` и затронутым тестам;
- `npx eslint` по новым файлам;
- после ручной проверки — полный прогон.
