---
name: Battleground Implementation Plan
overview: >
  Реализовать библиотеку шаблонов сцен, независимые игровые сцены и
  полноэкранный battlemap с редактором ведущего, движением, порталами и
  динамической видимостью.
todos:
  - id: scene-domain
    content: Ввести DTO SceneTemplate, GameScene, SceneSpace, SupportSurface и копирования
    status: pending
  - id: scene-api
    content: Добавить IBattlegroundApi, mock persistence и фасад GameApi
    status: pending
  - id: scene-editor
    content: Реализовать библиотеку шаблонов и editor игровой сцены
    status: pending
  - id: battlemap-runtime
    content: Реализовать полноэкранный BattlemapView, пространства, опоры, tokens и controls
    status: pending
  - id: spatial-domain
    content: Реализовать координаты, опоры, рёбра walk/climb/drop, obstacles, portals
    status: pending
  - id: visibility
    content: Сетка occupancy/vis, manual override, explored и lastSeen
    status: pending
  - id: movement-integration
    content: Связать movement actions с авторитетным состоянием GameScene
    status: pending
  - id: backend-contract
    content: Зафиксировать backend persistence, projections, assets и realtime API
    status: pending
  - id: verification
    content: Добавить unit-тесты и пройти полный frontend verification gate
    status: pending
isProject: false
---

# План реализации battleground

Канон решений: [battlegroundLockedDecisions.md](battlegroundLockedDecisions.md).
При расхождении верить канону.

## 1. Границы и порядок поставки

Первый полноценный релиз включает:

- личную библиотеку шаблонов сцен;
- независимые копии сцен внутри игры;
- редактирование игровой сцены ведущим;
- несколько пространств с отдельными подложками (поляна, этажи дома);
- опоры на пространстве (земля, настил, крыша) и сплошные плиты;
- река как полилиния русла; дом как вложенный enclosure;
- персонажей, NPC и декоративные/служебные токены;
- прямоугольные препятствия, круги и стены-полосы;
- связанные порталы/проёмы между пространствами;
- граф смены опор (walk на съезде моста, climb на стойку, без провала сквозь плиту);
- полёт: 1 ОД = 1 взмах, simultaneous H+V, высота опор default+override;
- свободные целочисленные координаты и необязательную сетку;
- перемещение через существующий action/movement контур;
- ручную и динамическую видимость;
- realtime-синхронизацию через backend boundary.

Не включать в первую поставку:

- произвольные polygon;
- realtime-курсор и совместное редактирование;
- технически защищённые тайлы подложки;
- UI факелов и расчёт cover (слоты в сетке уже есть).

Порядок вертикальных срезов:

```text
SceneTemplate
  → GameScene snapshot
  → BattlemapView
  → tokens/spaces/supports/obstacles
  → support-graph + portals
  → visibility projection
  → backend persistence/realtime
```

## 2. Доменная модель и DTO

Разместить типы в `draft-front_1.2ds/src/modules/Roleplay/Game/Dto/`,
по одному именованному типу на файл:

- `SceneTemplate`;
- `GameScene`;
- `SceneSpace` (не глобальный «этаж локации»);
- `Enclosure`, `SupportSurface`, `SupportEdge`;
- `Watercourse` / `WaterBody`;
- `SceneCoordinate` и `SceneResolution`;
- `BackgroundAsset`;
- `Token`, `TokenType`, `ActorTokenReference`;
- `Obstacle` и `ObstacleShape`;
- `Portal` и связанные области входа/выхода;
- `ScenePermission`;
- `VisibilityProjection`;
- `PlayerSceneKnowledge`;
- команды и результаты scene operations.

Семантика:

- `SceneTemplate` принадлежит пользователю и содержит только неигровые токены;
- `GameScene` принадлежит игре и является независимым snapshot;
- копирование шаблона и дублирование игровой сцены создают новые ID;
- при дублировании ссылки на персонажей/NPC сохраняются, но ID token instance
  создаются заново;
- у игры несколько `enabled/disabled` сцен и одна `current` сцена;
- состояние `explored` и `lastSeen` хранится отдельно для каждого игрока.

Не хранить позиции в пикселях. Координаты сцены — целые значения в собственной
`SceneResolution`; визуальная сетка только помогает вводу. Подложка каждого
пространства хранит логические границы и масштаб. Этаж дома — отдельное
`SceneSpace`; крыша и мост — `SupportSurface` на родителе. Позиция токена:
`spaceId`, xy, `supportId`, `altitude`.

## 3. API и persistence boundary

Добавить `IBattlegroundApi` в
`draft-front_1.2ds/src/modules/Roleplay/Game/Interface/`. Реализацию и фасад
разместить согласно текущему разделению:

- `Roleplay/Game/Service/BattlegroundApi.ts`;
- `Roleplay/Game/Service/Instance/battlegroundApi.ts`;
- регистрация и `getBattlegroundApi()` в `Roleplay/Game/init.ts`;
- типизированная mock-реализация в
  `Roleplay/Game/Mock/mockBattlegroundApi.ts`;
- при необходимости расширить `Roleplay/Game/Service/GameApi.ts`, не обходя
  ServiceLocator.

Минимальные операции API:

- получить список шаблонов пользователя;
- создать/изменить/удалить шаблон;
- скопировать шаблон в игру;
- дублировать, включить/выключить и выбрать `current` GameScene;
- получить полную сцену для ведущего;
- получить `PlayerSceneProjection` для игрока;
- добавить actor token из game roster;
- изменить сцену, token, obstacle, portal и visibility override;
- отправить команду движения или действия с версией сцены.

Проект пока frontend-only. Mock API используется первым, но DTO и интерфейсы
сразу должны быть пригодны для backend. Полная `GameScene` не должна попадать
в клиентскую проекцию игрока как скрываемый frontend-объект.

## 4. Редактор шаблонов и игровой сцены

Добавить компоненты в `Roleplay/Game/Component/Battleground/`, разделяя
renderer, editor controls, token palette, floor selector и dialogs. Vue-файлы
содержат только UI и вызовы фасадов; координатные расчёты, мутации и валидация
остаются в сервисах.

Раздел библиотеки шаблонов:

- список личных шаблонов;
- создание и переименование;
- загрузка подложки;
- создание пространств и вложенных enclosure;
- размещение прямоугольников, кругов, стен-полос и служебных токенов;
- настройка логических границ и масштаба.

Игровой редактор ведущего:

- добавить шаблон snapshot’ом;
- дублировать GameScene;
- включать/выключать сцены;
- выбирать одну `current` сцену;
- добавлять персонажей/NPC из roster игры;
- редактировать токены, obstacles, portals и visibility override.

Использовать свободные логические координаты и optional grid snap. Не делать
drag-and-drop единственным способом доменного изменения: жест карты должен
создавать typed command.

## 5. Battlemap runtime

Добавить маршрут вида `games/:id/battleground` в
`Roleplay/Game/routes.ts` и подключить его из
`Roleplay/Game/Page/GameDetailPage.vue`.

Создать полноэкранный режим:

- без обычных topbar, sidebar и chatbar;
- узкий чат слева;
- узкая панель действий сцены справа;
- центральный renderer карты;
- tabs пространств (не каждый настил), масштаб, выбор персонажа и режим ведущего.

Переиспользовать существующие публичные API Game/Chat и не импортировать
внутренние файлы чужих прикладных модулей. Проверять ограничения
`frontend-rules.md`: тяжёлая логика вне SFC, async-состояния с ошибкой и
повтором, отсутствие тысяч DOM-узлов без необходимости.

## 6. Геометрия, collision и порталы

Создать сервисы в `Roleplay/Game/Service/`:

- coordinate conversion и arithmetic;
- obstacle intersection;
- movement collision;
- support-graph walk/climb/drop (без провала сквозь плиту);
- portal/opening transition между пространствами;
- distance/cover/flank extension points.

`ObstacleShape` первого релиза:

- axis-aligned rectangle;
- circle;
- wall как rectangle с толщиной.

Не использовать `DimensionalNumber.toNumber()` для авторитетных пространственных
расчётов. Перевод размерной дистанции в целые единицы сцены должен быть явным.

Расширить
`draft-front_1.2ds/src/modules/Roleplay/Game/Interface/ISpatialResolver.ts`:

- принимать scene version, floor, actor token, position, spatial profile и
  movement request;
- возвращать component-wise traversed distance;
- возвращать новую позицию, space и support;
- возвращать `completed` или `collision`;
- возвращать смену опоры или portal/opening, если применены.

Для движения порядок должен быть атомарным:

```text
permission + scene version
  → resolve geometry and portal
  → spend action points
  → save token position and scene version
  → update currentSpeed
  → publish event
```

При collision положительная частичная дистанция сохраняется, ОД не возвращаются.

## 7. Dynamic visibility

Создать отдельные сервисы visibility, не встраивая алгоритм в Vue:

- получить выбранного игроком active character;
- проверить его token на `current` сцене;
- получить отдельную дальность LOS-чувства с лучшим радиусом;
- построить LOS по occupancy-столбикам (vis-порталы между пространствами);
- получить видимые token instances и области;
- применить manual GM override;
- обновить player-specific `exploredAreas` и `lastSeenTokens`.

Fog/explored — LOS-чувство с лучшим радиусом, не имя «Зрение». Модификатор
Внимательности не превращать напрямую в дальность.

Состояния проекции:

```text
visible  → текущая область и актуальный token
explored → ранее видимая область и lastSeen token
unknown  → данные не выдаются игроку
```

Если выбранный персонаж не размещён на текущей сцене, игрок получает
затемнённую сцену без динамической области видимости. Вручную скрытый ведущим
token полностью удаляется из `lastSeen`.

Backend должен выдавать игроку только `PlayerSceneProjection`. Frontend рисует
fog-маску по **тому же** occupancy-blob, но не является механизмом безопасности.
Подложка может быть у клиента. PHP на ход: фильтр токенов и LOS атаки, не
полный fog и не 40k кругов. Illumination в сетке сразу; в срезе везде светло.
Cover с сервера, пока 0.

## 8. Backend и realtime

Отдельно зафиксировать backend boundary для:

- хранения blob occupancy/height/illumination и navmesh (штамп при save);
- серверной авторизации ведущего и владельца token;
- проверки ОД и movement command;
- серверного spatial resolver;
- фильтрации projection по игроку;
- хранения player-specific knowledge;
- upload metadata и замены подложки;
- realtime-событий scene/token/visibility.

Использовать optimistic versioning: каждая команда содержит `sceneVersion`.
Конфликт отклоняется, клиент перечитывает projection и повторяет команду с
явным решением UI. Движение игрока и редактирование ведущего не блокируются
заранее.

Существующую заготовку `Core/Engine/Service/HttpClient.ts` использовать как
транспортный слой после появления backend. `ChatSyncService` не считать готовым
battleground transport; realtime-контракт сцены должен быть отдельным.

## 9. Тесты и критерии готовности

Размещать unit-тесты в `Roleplay/Game/__tests__/Service` и
`Roleplay/Game/__tests__/Mock`:

- snapshot и deep-copy сцены;
- enabled/current lifecycle;
- права ведущего и actor ownership;
- целочисленные координаты и obstacle intersection;
- collision с частичной дистанцией и списанием ОД;
- рёбра опор и portal/opening между пространствами;
- независимое horizontal/vertical movement;
- manual visibility override;
- occupancy LOS для rectangle/circle/wall и vis-портала;
- explored и lastSeen per player;
- удаление hidden token из lastSeen;
- optimistic version conflict;
- mock persistence и восстановление после reload;
- realtime projection update.

Первый вертикальный срез считается готовым, когда ведущий может создать сцену,
скопировать её в игру, разместить токены и obstacles, выбрать `current` сцену,
а игрок может открыть projection, выбрать своего персонажа, переместить его,
перейти через portal, увидеть доступные токены и восстановить состояние после
перезагрузки. Все проверки проходят через сервисы и API, а не через прямые
мутации renderer.

Перед завершением каждого этапа:

```text
npm run format
npm run lint
npx vue-tsc --noEmit
npm run test
```

Сверять реализацию с
[docs/specs/battlegroundIdeaContext.md](docs/specs/battlegroundIdeaContext.md),
[docs/specs/movementImplementationPlan.md](docs/specs/movementImplementationPlan.md)
и [draft-front_1.2ds/frontend-rules.md](draft-front_1.2ds/frontend-rules.md).
