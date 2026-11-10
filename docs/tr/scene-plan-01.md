# Локации сцен: authoring-срез

**Статус:** ~В РАБОТЕ~, 2026-11-10 — backend M1, просмотр M2 и редактор M3 готовы; M4 (вложения, инстансинг, undo) в плане.
**Реализация:** ~PHP~ — M1 готов, ~VUE~ — M2/M3 готовы (просмотр и редактор), ~МОК~ — да.
**Опираться на:** ~PHP~ (`Core/File`, `Core/User`, `Core/SmartTable`), ~МОК~.

Первый срез авторской библиотеки локаций: список, создание, подложка, поля,
геометрия, декор, сохранение и перезагрузка. Боевой runtime и Game не трогаем.
Домен — [`scene-system.md`](scene-system.md), renderer — [`scene-renderer.md`](scene-renderer.md).

## Цель

Ведущий может:

1. Создать локацию и открыть её в редакторе.
2. Загрузить подложку и задать логические границы поля.
3. Добавить второе поле (двор, этаж) и переключаться между ними.
4. Нарисовать препятствия, опоры, террейн, проёмы и декор.
5. Сохранить полный снимок, перезагрузить страницу и продолжить.
6. Архивировать локацию.

PC/NPC, occupancy, LOS, туман, движение и копирование в игру не входят.

## M0 — контракт

Сделано в этом срезе:

- [`scene-system.md`](scene-system.md) — домен, таблицы, snapshot, права,
  контракт копирования в Game.
- [`scene-renderer.md`](scene-renderer.md) — PixiJS/WebGL, слои, камера,
  hit-test, инстансинг, fog hooks.
- [`scene-plan-01.md`](scene-plan-01.md) — этот план.
- Пометки в [`spatial-roadmap.md`](spatial-roadmap.md), [`TR.md`](TR.md),
  [`battleground-system.md`](battleground-system.md),
  [`contract-status.md`](contract-status.md).
- `DEC-087`, `DEC-088` в [`decisions.md`](decisions.md).

## M1 — backend

Модуль `Roleplay/Scene`.

- Каркас: `module.config.php`, `Container/SceneContainer`,
  `Interface/Container/ISceneContainer`, `Setup/SceneModuleSetup`,
  `Schema/SceneSchema`.
- Таблицы: `scene_location`, `scene_board`, `scene_element`, `scene_enclosure`.
- Репозитории и DTO: `SceneRecord`, `SceneBoardRecord`, `SceneElementRecord`,
  `SceneEnclosureRecord`, `SceneDetail`, `ScenePage`, `SceneSummary`.
- HTTP-порты и действия:
  - `scene.findPage` — список своих и доступных администратору;
  - `scene.get` — полный snapshot;
  - `scene.save` — создание и обновление полным снимком;
  - `scene.archive` — архивирование.
- Сервисы: доступ, валидатор snapshot, писатель с отрицательными id,
  сборка ответа.
- Права: `scene.edit`, `scene.view_all`, `scene.edit_all`; владелец.
- Ошибки: `SCENE_DENIED`, `SCENE_NOT_FOUND`, `SCENE_INVALID`,
  `SCENE_VERSION_CONFLICT` с `currentVersion`.
- Регистрация ленивого контейнера в `www/mifrial/config/modules.php`.

Критерии:

- Создание, чтение, сохранение, конфликт версий и архив покрыты HTTP-тестами.
- Snapshot сохраняет и восстанавливает все поля, элементы и вложения без потерь.
- Отрицательные id и ссылки на временные поля ремапятся в одной транзакции.
- Валидатор не пропускает чужое поле, цикл enclosure, элемент за границами и
  неизвестный `kind`.
- Архив не удаляет строки; список по умолчанию его скрывает.

## M2 — frontend + renderer core

Модуль `Roleplay/Scene` на фронте.

- `pixi.js` v8 в `package.json`.
- Каркас: `init.ts`, `routes.ts`, `Interface/ISceneApi.ts`,
  `Service/SceneApi.ts`, `Mock/mockSceneApi.ts`, DTO.
- `/scenes` — список; `/scenes/:id` — detail.
- `ISceneRenderer` + `PixiSceneRenderer`:
  - Application с `preference: 'webgl'`;
  - камера, pan/zoom, fit;
  - слои `backdrop`, `grid`, `terrain`, `supports`, `obstacles`, `openings`,
    `decor`, `selection`, `gizmos`;
  - DPR, resize, context loss, destroy.
- Read-only viewer локации: загрузка snapshot, переключение полей, отрисовка.
- `SceneHitTestService` на CPU.
- Регистрация модуля и маршрутов.

Критерии:

- Локация из mock и из real API открывается и рисуется.
- Pan/zoom и hit-test работают без per-object DOM.
- 40k декоративных спрайтов проходят smoke-порог без постоянного тикера.
- Нет импортов внутренних файлов `Roleplay/Game` и `Roleplay/Map`.

## M3 — редактор

- Локальный draft, dirty, `beforeunload` и router-guard.
- Toolbar: select/move, rect, circle, wall, support, terrain, decor, opening.
- Панель свойств по `kind`; удаление, копирование, порядок.
- Вкладки полей; создание, переименование, удаление, порядок.
- Загрузка подложки через `Core/File`; предупреждение о пропорциях.
- Сохранение, конфликт версий, архив.
- Preview-режим без гизмо.

Критерии:

- Полный цикл: создать → нарисовать → сохранить → перезагрузить → править.
- Конфликт версий не теряет draft и даёт явный выбор.
- Ошибка загрузки не стирает уже открытую локацию.
- Все мутации идут через сервисы/draft, не через прямые Pixi-объекты.

## M3 — итог

Реализовано: локальный черновик с dirty-флагом и guard-ами, toolbar
(select/rect/wall/подтипы), протяжка фигур, перемещение выделения, панель
свойств по `kind`, вкладки полей (создание, переименование, удаление,
порядок), подложка через `Core/File` с предупреждением о пропорциях,
сохранение снимком, конфликт версий с выбором «перезагрузить/перезаписать».
Просмотр без гизмо — страница локации.

Не входит и осталось на M4: enclosure-редактор, `targetBoardId` через UI
проёма уже есть, инстансинг декора, миниатюры, undo/redo, горячие клавиши
сверх Delete/Escape.

## M4 — polish

- Enclosure: footprint на родителе, дочернее поле, запрет циклов.
- Проёмы между полями и `targetBoardId`.
- Инстансинг декора, culling, LOD при необходимости.
- Миниатюры списка.
- Undo/redo.
- Пустое состояние, горячие клавиши, справка.

## Тесты и гейт

- PHP: unit-тесты валидатора и писателя; HTTP-тесты модуля.
- Vue/Vitest: камера, hit-test, render model, draft, API, конфликт версий.
- WebGL: ручной smoke; при необходимости puppeteer с software rendering.
- Итерация: `composer stan`, `composer test:unit`, затронутые PHP-тесты;
  `npx vitest run <файл>` на фронте.
- Полный `bash tools/test-full.sh` — только после ручной проверки и отдельного
  подтверждения.

## Риски

- **Occupancy BLOB.** SmartTable не умеет `BLOB`; в этом срезе occupancy не
  хранится. Решение — в GameScene: `BinaryField`, `Core/File` или кэш.
- **Геометрия ещё OPEN.** Фиксируем форму хранения и `schemaVersion`, но не
  алгоритмы LOS/движения.
- **PixiJS в bundle.** Деревошейкинг и отсутствие полного импорта обязательны.
- **Общая тестовая БД.** Два PHPUnit одновременно нельзя; Scene-поток и Game
  сериализуют DB-тесты.
- **Shared registration.** `config/modules.php`, `main.ts`,
  `modules/Roleplay/routes.ts` — маленькие конфликты при merge.

## Не входит

- PC/NPC, actor tokens, инициатива, ОД, бой.
- occupancy, navmesh, LOS, туман, динамическая видимость.
- движение, полёт, рёбра опор, runtime-порталы.
- `GameScene`, копирование в игру, realtime/SSE.
- шаринг локаций и связь с картой мира.

## Связанные документы

- [`scene-system.md`](scene-system.md)
- [`scene-renderer.md`](scene-renderer.md)
- [`spatial-roadmap.md`](spatial-roadmap.md)
- [`battleground-system.md`](battleground-system.md)
