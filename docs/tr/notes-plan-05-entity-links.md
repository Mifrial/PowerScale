# План 05 — inline-ссылки персонажа/НПС в Messages/Notes

**Статус:** ~В РАБОТЕ~ — 2026-11-15
**Реализация:** ~VUE~; PHP, схема `note` и HTTP-контракты не меняются
**Опираться на:** ~PHP~ (`note.*`, `character.get`, `game.get`, `game.getSheets`, `game.getNpc`), ~МОК~ (`mockCharacterApi`, `mockGameApi`), ~НЕ ОСНОВА~ (реализация Notes как контракт бэкенда)

Цель: в заметке можно вставить inline-ссылку на персонажа или НПС, а по клику
открывается полная деталка сущности в слайдере. Заметка остаётся личным
блокнотом: сервер по-прежнему хранит только `body_html`/`body_text`, ссылки —
это `span[data-inline-type]` в HTML.

## 1. Границы и принятые решения

- `Messages/Notes` **не импортирует** Game, Character, Rule и не знает типов
  сущностей. Notes передаёт только opaque `inlineContext` и `tokenSources`;
  чипы и слайдеры регистрируют доменные модули.
- Вставка — существующим пикером «Вставить ссылку» (`InlineNodePicker`) в
  `RichTextEditor`. Формат токена не меняется:
  `[[character:<id>,<label>]]`, `[[npc:<id>,<label>]]`.
- Клик по чипу в заметке открывает **полную деталку в слайдере**: шапка,
  все вкладки/секции листа, «Обсуждение» (где доступно), действия-ссылки.
  Правка листа и черновик прямо в слайдере **не входят**: «Редактировать»,
  «Перевести» ведут на существующие страницы; «Заметки» открывает вложенный
  `NotesSlider`.
- Контекст ссылки определяется заметкой:
  - заметка об игре (`hostType = 'game'`) — деталка в контексте игры
    (видимость членства, игровой лист);
  - заметка о персонаже (`hostType = 'character'`) — standalone-деталка
    `/characters/:id`;
  - `/notes` восстанавливает тот же контекст через `resolveEditorContext`.
- Пикер ищет сущности по контенту хоста:
  - хост-игра: персонажи этой игры + активные НПС этой игры;
  - хост-персонаж: видимые пользователю персонажи;
  - НПС в заметке о персонаже не предлагаются: Character не импортирует Game.
- Существующий `GameEntityChip` унифицируется: при наличии host-колбэка
  `openEntity` (игровой чат) поведение прежнее — боевая карточка; иначе чип
  открывает слайдер деталки. Летопись тоже переводится на те же слайдеры.
- Скрытая/удалённая сущность: чип остаётся, слайдер показывает
  «Объект скрыт» (канон inline-чипов), без падения заметки.

## 2. Контекст и источники

### 2.1. Расширение `inlineContext` (без изменения Notes)

В `inlineContext` заметок добавляются два необязательных ключа:

| Ключ | Значение |
|---|---|
| `gameId` | id игры, в контексте которой открывать персонажа/НПС; `null` — нет |
| `entityContext` | `'game'` — игровая деталка; `'standalone'` — деталка `/characters/:id` |

Notes передаёт этот объект как есть в `RichTextEditor`/`RichTextHtml`; чип
читает ключи из `context`. `NotesPage` и `NotesSlider` не меняются.

### 2.2. `CharacterNoteContextService`

Новый сервис в Character (`Service/CharacterNoteContextService.ts` +
`Service/Instance/characterNoteContextService.ts`):

- `build(rules, spaceId, rulesRevision, gameId)` возвращает
  `{ inlineContext, tokenSources }`;
- `inlineContext`: `tokenLabels`, `spaceId`, `rulesRevision`, `gameId`,
  `entityContext: 'standalone'`;
- `tokenSources`: правило из `characterChatRulesContextService` + источник
  `character` (ленивый `getCharacterApi().getCharacters()`, поиск по имени,
  кэш списка на время жизни источника) + глобальные источники без правила;
- ошибка `getCharacters()` не ломает пикер: источник отдаёт `[]`.

Сервис используется в `CharacterDetailPage`, в резолвере
`registerNoteHost({ type: 'character' })` и в `CharacterDetailSlider`.

### 2.3. `GameNoteContextService`

Новый сервис в Game (`Service/GameNoteContextService.ts` +
`Service/Instance/gameNoteContextService.ts`):

- `build(rules, mechanics, spaceId, rulesRevision, gameId)` возвращает
  `{ inlineContext, tokenSources }`;
- `inlineContext`: `tokenLabels`, `spaceId`, `rulesRevision`, `gameId`,
  `entityContext: 'game'`;
- `tokenSources`: правило из `gameChatRulesContextService` + источник
  `character` + источник `npc` + глобальные источники без правила;
- `character`: `getGameApi().getCharacterList({ gameId })`, исключить
  `membershipStatus === 'left'`, поиск по `characterName`;
- `npc`: `getGameApi().getNpcSummaries({ gameId, status: 'active', query,
  limit: 50 })`, поиск серверный/моковый, значение — `String(npc.id)`;
- списки кэшируются на время жизни источника; ошибка источника даёт `[]`.

Сервис используется в `GameDetailPage`, в резолвере
`registerNoteHost({ type: 'game' })`, в `GameCharacterDetailSlider` (только
для источников) и в летописи, если понадобится.

## 3. Слайдеры деталки

### 3.1. Standalone-деталка персонажа

Новые компоненты Character:

- `Component/Detail/CharacterDetailHeader.vue` — шапка карточки: раса, имя,
  статус, ссылка на ревизию, владелец, игра. Выносится из
  `CharacterDetailPage`, чтобы страница и слайдер не расходились.
- `Component/Detail/CharacterDetailSlider.vue`:
  - пропсы `open` (v-model), `characterId`;
  - грузит `getCharacterApi().getCharacter(id)` (не через глобальный store,
    чтобы не перетирать `currentCharacter` страницы), затем ревизию через
    `useSpaceRevision().fetchRevision(spaceId, version.rulesRevision)`;
  - `visibleSections`: `detail.visibleSections`, иначе
    `sheetAccessService.visibleSheetSections` (как на странице);
  - шапка `CharacterDetailHeader` + действия-ссылки: «Заметки»,
    «Редактировать», «Перевести», «Открыть страницу»;
  - `CharacterSheetBody` в read-only режиме: `can-edit = false`,
    `show-favorites = true`, `character-id`, `show-discussion = hasFullView`,
    `supplied-rules` из загруженной ревизии;
  - «Заметки» открывает вложенный `NotesSlider` с
    `CharacterNoteContextService.build(...)`;
  - состояния: загрузка, «Объект скрыт» (нет доступа/404), ошибка + повтор;
  - `SlidePanel width="calc(100vw - 120px)" max-width="1200px"`, тело
    прокручивается; без изменений в `SlidePanel`.
- `CharacterDetailPage` использует `CharacterDetailHeader` и
  `CharacterNoteContextService`; поведение страницы (черновик, Teleport
  действий, NotesSlider) не меняется.
- `Character/init.ts` экспортирует `CharacterDetailSlider` асинхронно
  (`defineAsyncComponent`) — это публичная точка для Game-чипа.

### 3.2. Деталка персонажа в контексте игры

Новый компонент Game `Component/Detail/GameCharacterDetailSlider.vue`:

- пропсы `open`, `gameId`, `characterId`;
- грузит `getGameApi().getGame(gameId)`, `getCharacterList({ gameId })`,
  лист персонажа в игровом контексте:
  - PHP-режим — `getSheets({ gameId, keys: [{ type: 'character', id }] })`,
    версия через `characterHttpViewService.versionOf`;
  - мок-режим — `getRuntimeEntity(gameId, 'character:<id>', 'full')`;
- проверяет доступ игры (`gameAccessService.canViewGame`) и видимость листа
  (`sheetAccessService.visibleSheetSections` с `ownerId`, `characterId`,
  `gameId`);
- шапка: имя, владелец, игра, статус членства; действия-ссылки:
  «Заметки», «Редактировать в игре» / «Редактировать», «Открыть страницу»;
- `CharacterSheetBody` с игровым листом и видимыми секциями, `can-edit =
  false`, `show-favorites = true`, `show-discussion` — только если удалось
  получить `discussionChatId` (best-effort `getCharacterApi().getCharacter`;
  ошибка не блокирует);
- «Заметки» — `NotesSlider` с `hostType = 'character'`, контекст
  `CharacterNoteContextService.build(...)` по ревизии листа; это те же
  заметки персонажа, что на `/characters/:id`;
- «Объект скрыт» при отсутствии членства/доступа, ошибка + повтор.
- Логика загрузки листа дублирует `CharactersTab.openCard`; на этом шаге
  допускается локальный загрузчик в слайдере. Вынос общего сервиса —
  отдельная задача, если появится третье место.

### 3.3. Деталка НПС

Новый компонент Game `Component/Detail/NpcDetailSlider.vue`:

- пропсы `open`, `gameId: number | null`, `npcId`;
- грузит `getGameApi().getGame(gameId)` и `getNpc(gameId, npcId)`; проверяет
  `npcDetailAccessService.canOpen`;
- `visibleSections` — `sheetAccessService.visibleSheetSections` с контекстом
  `ownerId: null`, `characterId: npc.id`, `gameId`;
- шапка: имя, статус предложения, «Редактировать», «Открыть страницу»;
- `CharacterSheetBody` с `version`, `spaceId`, видимыми секциями,
  `show-discussion = false`;
- `gameId === null` → «Объект скрыт» (НПС без игры открыть нельзя);
- ошибка + повтор.

### 3.4. Выбор слайдера в чипе

`Component/Chat/GameEntityChip.vue`:

- расширить локальный тип контекста:
  `openEntity?`, `gameId?: number | null`,
  `entityContext?: 'game' | 'standalone'`;
- клик:
  1. `context.openEntity` есть → вызвать, как сейчас (игровой чат/боевая
     карточка);
  2. `character` + `entityContext === 'game'` + `gameId` →
     `GameCharacterDetailSlider`;
  3. `character` иначе → `CharacterDetailSlider` (standalone);
  4. `npc` + `gameId` (контекст или `route.params.id`) → `NpcDetailSlider`;
  5. `npc` без `gameId` → `NpcDetailSlider` с `gameId = null` (покажет
     «Объект скрыт»);
- слайдеры подключаются `defineAsyncComponent` и рендерятся по
  `v-if="sliderOpen"`, чтобы не тянуть CharacterSheetBody в чат-чанк;
- старый переход `router.push` для персонажа/НПС убирается; в игровом чате
  остаётся `openEntity`, в остальных местах — слайдер.

## 4. Летопись

`Component/Detail/ChronicleTab.vue`:

- убрать `v-dialog` с `SheetCard` и вычисление `refCard`;
- по `openRef` открывать `GameCharacterDetailSlider` (`kind: 'character'`) или
  `NpcDetailSlider` (`kind: 'npc'`) с `gameId` игры;
- чипы летописи (`ChronicleEntryContent`) не меняются: у них своя подпись из
  членства/НПС. Меняется только назначение клика — полная деталка в слайдере
  вместо компактного `SheetCard` в диалоге.
- Переход летописи на общий `GameEntityChip`/Inline-рендерер — отдельная
  задача: у летописи plain-text контент и свои подписи.

## 5. Файлы

### Character

- создать `Service/CharacterNoteContextService.ts`;
- создать `Service/Instance/characterNoteContextService.ts`;
- создать `Component/Detail/CharacterDetailHeader.vue`;
- создать `Component/Detail/CharacterDetailSlider.vue`;
- изменить `Page/CharacterDetailPage.vue` — шапка и контекст заметок через
  новые сервис/компонент;
- изменить `init.ts` — экспорт `CharacterDetailSlider`, резолвер
  `registerNoteHost({ type: 'character' })` через
  `characterNoteContextService`;
- тест `__tests__/Service/characterNoteContextService.test.ts`.

### Game

- создать `Service/GameNoteContextService.ts`;
- создать `Service/Instance/gameNoteContextService.ts`;
- создать `Component/Detail/GameCharacterDetailSlider.vue`;
- создать `Component/Detail/NpcDetailSlider.vue`;
- изменить `Component/Chat/GameEntityChip.vue` — выбор слайдера;
- изменить `Page/GameDetailPage.vue` — `notesInline`/`notesSources` через
  `gameNoteContextService`;
- изменить `Component/Detail/ChronicleTab.vue` — слайдеры вместо диалога;
- изменить `init.ts` — резолвер `registerNoteHost({ type: 'game' })` через
  `gameNoteContextService`;
- тест `__tests__/Service/gameNoteContextService.test.ts`.

### Messages

- код Notes/Inline не меняется; `NoteHostContext` уже допускает
  `inlineContext` и `tokenSources`;
- при необходимости добавить комментарий в `NoteHostContext`/`NoteHost`, что
  контекст opaque и доменные ключи не толкуются Notes.

### Документы

- этот план после реализации переносится в
  `docs/tr/notes-plan-05-entity-links.md` (статус `~СДЕЛАНО~`);
- `docs/tr/notes-roadmap.md` — шаг 5: inline-ссылки и слайдеры деталки;
- `docs/tr/chat-system.md` — чипы персонажа/НПС открывают деталку в слайдере,
  если хост не передал `openEntity`;
- `docs/tr/character-system.md` — заметки персонажа могут ссылаться на
  персонажей (и на НПС, если у персонажа есть игра);
- `docs/tr/TR.md` — ссылка на план 05;
- `docs/tr/architecture.md` — рёбра не меняются (`Character → Notes`,
  `Game → Notes`, `Game → Character` уже есть); отметить новые UI-точки;
- `docs/tr/doc-index.md`/`doc-index.json` — `composer docs-check`.

## 6. Ограничения и отложенное

- В заметке о персонаже пикер не предлагает НПС: Character не импортирует
  Game. НПС вставляются в заметках об игре; в заметке о персонаже НПС-ссылка
  откроется, только если в контексте есть `gameId` (например, у персонажа
  есть игра) и токен уже есть.
- Глобального поиска НПС по всем играм нет: для него нужен новый backend-
  поиск, иначе это N запросов по играм.
- Формат токена не меняется; `gameId` для НПС берётся из контекста заметки,
  а не из токена. Это сохраняет совместимость со старыми `[[npc:id]]`.
- В слайдере нет правки листа/черновика: действия ведут на страницы.
  Live-обновление листа в открытом слайдере не подписывается на
  `characterChangePort`; данные перечитываются при открытии.
- В игровом контексте вкладка «Обсуждение» показывается best-effort: если
  standalone-деталка недоступна и `discussionChatId` не получен, вкладки нет.
- Карточные расширения Character (например «Видимость листа» от Game) в
  слайдере не рендерятся: это интерактивная правка, оставленная странице.

## 7. Тесты и гейты

- `characterNoteContextService`: источники содержат `character`, контекст —
  `entityContext: 'standalone'`, `gameId`; поиск по имени, пустой результат
  при ошибке API.
- `gameNoteContextService`: источники `character` и `npc` ограничены игрой;
  НПС только `active`; контекст — `entityContext: 'game'`, `gameId`.
- Чип/слайдеры: по правилам фронта Vuetify-компоненты mount-тестами не
  покрываются (`frontend-rules.md`, F8); проверка — `vue-tsc`, lint и ручной
  сценарий.
- `npm run format`, `npm run lint`, `npx vue-tsc --noEmit`;
- точечно `npx vitest run` по новым тестам и затронутым сервисам;
- ручная проверка:
  - заметка об игре: вставить персонажа и НПС, клик → полная деталка в
    игровом контексте, вкладки, «Заметки» (вложенный слайдер), скрытый объект;
  - заметка о персонаже: вставить персонажа, клик → standalone-деталка,
    вкладка «Обсуждение», «Заметки»;
  - `/notes`: контекст восстанавливается после перезагрузки и смены хоста;
  - игровой чат: чип по-прежнему открывает боевую карточку; мессенджер —
    деталку; летопись — деталку;
  - удалённый/скрытый персонаж или НПС → «Объект скрыт», заметка не ломается;
- после ручной проверки — полный прогон `bash tools/test-full.sh` (только с
  подтверждения пользователя).

## 8. Порядок

1. Character: сервис контекста, шапка, standalone-слайдер; подключить в
   странице и резолвере хоста.
2. Game: сервис контекста, игровой слайдер персонажа, слайдер НПС; подключить
   в `GameDetailPage` и резолвере.
3. `GameEntityChip`: выбор слайдера; проверить игровой чат (openEntity),
   мессенджер, заметки.
4. Летопись: заменить диалог `SheetCard` на слайдеры.
5. Тесты сервисов, typecheck/lint, ручная проверка.
6. Документы, `composer docs-check`; после подтверждения — полный прогон.

## 9. Как сделано (2026-11-15)

- Каркас слайдера вынесен в `Character/Component/Detail/CharacterDetailPanel.vue`:
  шапка, действия-ссылки, `CharacterSheetBody` и вложенный `NotesSlider`.
  Его используют все три слайдера; Game получает панель через публичный
  экспорт `CharacterDetailPanel` из `Character/init.ts`.
- Standalone-слайдер грузит `character.get` и ревизию через `fetchRevision`,
  не трогая `useCharacterStore`; видимые секции — `visibleSections` из ответа,
  иначе `sheetAccessService`. Игровой слайдер повторяет загрузку
  `CharactersTab.openCard` (`getSheets` в PHP, `getRuntimeEntity` в моке).
- `CharacterNoteContextService`/`GameNoteContextService` — классы с
  инъекцией загрузчиков; списки кэшируются на время жизни источника, ошибки
  дают пустой результат. Тесты — `__tests__/Service/*NoteContextService.test.ts`.
- `discussionChatId` в игровой деталке — best-effort через `character.get`;
  плоский PHP-ответ может не нести чат, тогда вкладки «Обсуждение» нет.
- Летопись больше не грузит `SheetCard` в диалоге: `openRef` выставляет id и
  открывает соответствующий слайдер; чипы летописи не менялись.
- Мелкие правки `Messages/Inline`: чипы источников в пикере переносятся на
  новую строку (4 источника больше не обрезаются), реестр источников
  заменяет источник того же типа вместо дублирования (HMR/повторная
  инициализация).
