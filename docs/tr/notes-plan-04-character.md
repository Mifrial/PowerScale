# План 04 — Character: удаление owner_notes и слайдер заметок

**Статус:** ~СДЕЛАНО~ — 2026-11-08. Шаг 3 нарезки
[`notes-roadmap.md`](notes-roadmap.md). Character и Game переведены на
`Messages/Notes`, колонка `owner_notes` снята DDL-шагом.
**Реализация:** ~PHP~, ~VUE~
**Опираться на:** ~PHP~ (текущий Character), ~НЕ ОСНОВА~ (мок `ownerNotes`)

Цель — снести одиночное поле заметок владельца во всех слоях и открыть на
странице персонажа слайдер `Messages/Notes` с `hostType = 'character'`.

## 1. Backend: удалить

- `Table/CharacterTable.php` — поле `owner_notes`; doc-блок `payloadFields()`
  больше не говорит «заметки».
- `Dto/CharacterRecord.php` — `owner_notes` из списка полей, параметр
  конструктора, `getOwnerNotes()`, нормализация.
- `Dto/NewCharacter.php` — параметр, `getOwnerNotes()`, `toArray()`.
- `Service/Characters.php` — `owner_notes` в строке создания.
- `Service/CharacterSave.php` — `ownerNotes => ''` в сборке `NewCharacter`.
- `Service/Read/CharacterViewAssembler.php` — `ownerNotes` из JSON.
- `Service/CharacterSheetAccess.php` — `replaceOwnerNotes()`.
- `Repository/CharacterVisibilityRepository.php` — `replaceOwnerNotes()`.
- `Service/CharacterRead.php` — `updateOwnerNotes()`.
- `Action/UpdateCharacterOwnerNotesAction.php`,
  `Dto/Action/UpdateCharacterOwnerNotesInput.php` — удалить файлы.
- `module.config.php` — импорт, порт и маршрут `character.updateOwnerNotes`.
- Тесты Character: `CharacterMigrationTest`, `CharacterSectionMaskTest`,
  `CharacterSaveTest`, `CharacterInputNormalizerTest`, `CharacterMysqlTest`,
  `CharacterFormulaContextMysqlTest`, `CharacterActualMutationMysqlTest`,
  `CharacterSheetAccessMysqlTest` — убрать `ownerNotes`/`owner_notes` из
  фикстур и ожиданий; тест маски не должен опираться на это поле.
- Game-фикстуры с `ownerNotes`/`owner_notes` заменены на `NewCharacter` без
  поля (выполнено после завершения сессии Game).

`DateTime` в `CharacterSheetAccess` остаётся: он нужен другим операциям.

## 2. DDL на существующей БД

Удаление поля из карты не снимает колонку: `updateTable()` только добавляет.
Нужен data-step по образцу `Roleplay/Game/Setup/AlignGameActionColumnsStep`:

`Roleplay/Character/Setup/DropCharacterOwnerNotesStep`:

- `getId()` — `Roleplay/Character:drop-owner-notes`;
- `run()` — `ISmartTableGateway::open(CharacterTable::class)->schema()`;
- если таблицы нет — выход;
- `forceUpdateTable()` в `try/catch` и обёртка в `KernelException('SETUP_FAILED',
  'Character owner notes drop failed', $exception)`.

Шаг добавить в `CharacterModuleSetup::getDataSteps()` **после** правки карты.
Порядок в списке не важен, пока карта уже без поля.

## 3. Frontend: удалить

- `Dto/CharacterDetail.ts` — `ownerNotes`.
- `Service/CharacterHttpViewService.ts` — маппинг `ownerNotes`.
- `Interface/ICharacterApi.ts`, `Service/CharacterApi.ts` — `updateOwnerNotes`.
- `Mock/mockCharacterApi.ts`, `Mock/mockCharacters.ts` — хранилище
  `ownerNotesByCharacterId`, чтение, запись, экспорт.
- `Page/CharacterDetailPage.vue` — `notesOpen`, `notesSaving`, `notesError`,
  `saveOwnerNotes`, кнопка «Заметки», `<OwnerNotesDialog>`.
- `Component/OwnerNotesDialog.vue` — удалить файл.
- `init.ts` — экспорт `OwnerNotesDialog`.
- `__tests__/Mock/mockCharacterApi.test.ts` — сценарии заметок владельца.

## 4. Frontend: слайдер

- В `Teleport to="#editor-actions"` кнопка «Заметки» показывается при
  `canView`, а не только при `canEdit`: любой зритель ведёт свои заметки.
- Клик открывает `NotesSlider` из `Messages/Notes/init`:

```vue
<NotesSlider
  v-model:open="notesOpen"
  host-type="character"
  :host-id="detail.character.id"
  :title="detail.character.name"
  :inline-context="notesInlineContext"
  :token-sources="notesTokenSources"
/>
```

- `notesInlineContext`/`notesTokenSources` берутся из уже собранного
  контекста правил персонажа (тот же срез, что у чата обсуждения), чтобы чипы
  правил и персонажей отображались и вставлялись.
- `registerCharacterModule()` вызывает `registerNoteHost`:

```ts
registerNoteHost({
  type: 'character',
  label: 'Персонаж',
  icon: 'mdi-account',
  route: (id) => `/characters/${id}`,
  resolveEditorContext: async (id) => {
    const detail = await getCharacterApi().getCharacter(id);
    const revision = await useSpaceRevision().fetchRevision(
      detail.character.spaceId,
      detail.character.rulesRevision,
    );
    const context = characterChatRulesContextService.build(
      revision.rules,
      detail.character.spaceId,
      detail.character.rulesRevision,
    );

    return { inlineContext: chatInlineRendererContext(context) ?? {}, tokenSources: context.tokenSources };
  },
});
```

Резолвер нужен для `/notes`: страница общего списка не знает, откуда взять
правила. Он живёт в Character и не тянет Notes в доменные сервисы. Ошибка
резолвера не блокирует заметку — Notes откроет редактор с глобальными
источниками.

## 5. Game и Map

- Backend Game колонку не заводит.
- Frontend Game сделан вместе с шагом 3: `GameDetail.personalNotes`,
  `IGameApi.updatePersonalNotes` (PHP-заглушка) и мок личных заметок удалены;
  карточка игры открывает `NotesSlider` с `hostType = 'game'`, а
  `registerGameModule()` регистрирует хост с контекстом правил игры для
  `/notes`.
- Map: слайдер и `INotes::deactivateForHost('map', $mapId)` из архивации —
  после Map-сессии.

## 6. Документы

- `data-model.md` — убрать `owner_notes` из `characters`, добавить `note`;
  `game_personal_notes` пометить как заменяемое таблицей `note` после
  интеграции Game.
- `character-system.md` — заметки больше не поле листа: личный блокнот
  `Messages/Notes`, авторство, слайдер.
- `character-roadmap.md` — C6: `ownerNotes` заменён на `Messages/Notes`.
- `architecture.md` — ребро `Character → Notes`, `Messages/Notes` в дереве.
- `game-roadmap.md` — `personalNotes` уходит в Notes.

## 7. Порядок

1. Backend: убрать поле из карты и DTO; удалить action/route; добавить
   DDL-step; поправить тесты Character и фикстуры Game.
2. Frontend: убрать `ownerNotes` из DTO/API/мока; удалить диалог; добавить
   слайдер и `registerNoteHost`; перевести Game на `NotesSlider`.
3. Прогнать Character-тесты, MySQL и фронтовые тесты.

## 8. Тесты и гейты

- `phpunit --testsuite character` — 145 тестов OK; схема без колонки после
  setup-шага проверяется `CharacterMysqlTest`;
- `npx vitest run` по Character, Game, Notes, Inline, Chat — OK;
- `composer stan`, `composer test:unit` — OK;
- ручная проверка: страница персонажа, кнопка «Заметки» у владельца и у
  зрителя, создание/правка/поиск/pin/reorder, `/notes` с фильтром хоста;
- после ручной проверки — полный прогон.
