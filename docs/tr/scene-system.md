# Система локаций сцен

**Статус:** ~В РАБОТЕ~, 2026-11-10 — authoring-слой локаций; `GameScene`, occupancy, LOS и движение остаются `DEFERRED` в [`battleground-system.md`](battleground-system.md).
**Реализация:** ~PHP~ — M1 готов, ~VUE~ — M2 (просмотр) готов, ~МОК~ — да.
**Опираться на:** ~PHP~ (`Core/File`, `Core/User`, `Core/SmartTable`), ~МОК~.

Документ фиксирует авторскую библиотеку локаций: как ведущий проектирует
тактическую сцену без персонажей и как Game позже получает её независимую
копию. Боевой runtime, видимость, движение и `GameScene` сюда не входят.

## Назначение

`SceneLocation` — авторский шаблон детальной сцены. Он содержит одно или
несколько полей (`SceneBoard`), геометрию, подложки, декор и служебные маркеры.
Локация не знает про игру, карту мира, персонажей и боевые процедуры.

Game позже читает локацию как snapshot и создаёт свою `GameScene`: независимую
копию с NPC/PC, occupancy, туманом и движением. Изменение локации после
копирования не меняет уже созданную сцену.

```text
SceneLocation «Таверна»
├── SceneBoard «Двор»
├── SceneBoard «1 этаж»
└── SceneBoard «2 этаж»

Map (карта мира) → позже может ссылаться на SceneLocation
GameScene        → независимая копия SceneLocation внутри игры
```

## Термины

| Термин | Код | Что это | Владелец |
|---|---|---|---|
| Локация | `SceneLocation` | Авторский шаблон детальной сцены. | `Roleplay/Scene` |
| Поле локации | `SceneBoard` | Одно 2D-полотно: подложка, границы, свои координаты. | `Roleplay/Scene` |
| Элемент поля | `SceneElement` | Опора, препятствие, проём, террейн, декор или маркер на поле. | `Roleplay/Scene` |
| Вложение | `SceneEnclosure` | Связь «поле внутри поля»: дом на поляне, этаж дома. | `Roleplay/Scene` |
| Сцена игры | `GameScene` | Независимая копия локации в игре с актёрами и боевым состоянием. | `Roleplay/Game` (позже) |

В старых deferred-спеках battleground использовался `SceneSpace`. В этом каноне
термин заменён на `SceneBoard`, чтобы не путать с `Versioning/Space`,
`RuleSpace` и «пространством игры». `SceneSpace` считается историческим.

## Границы

- `Roleplay/Scene` не импортирует `Roleplay/Game`, `Roleplay/Map` и `Roleplay/Character`.
- `Map` позже может ссылаться на локацию: `Map → SceneLocation`. Обратной
  зависимости быть не должно. В `Scene` нет `mapNodeId`.
- `Game` позже читает локацию через публичный порт и создаёт независимый
  snapshot. `Scene` не знает, кто именно её прочитал.
- Локация не содержит PC/NPC, инициативы, ОД, тумана и результата боя.
- Локация не является картой мира и не участвует в маршруте по мировой карте.

## Единицы и масштаб

- Ипари — единственная единица расстояний в интерфейсе. Метры не используются.
- `resolution` — целых единиц сцены на 1 ипари; по умолчанию 64. Допустимы
  только степени двойки (16…1024), чтобы любое расстояние переводилось в
  размерное число `{base|size}` без дробей.
- Внутри хранятся целые единицы сцены. Интерфейс показывает ипари: при
  `resolution = 64` 1 ед. = `{1|-6}` ипари, 0,5 ипари = 32 ед. = `{1|-1}`,
  0,25 = 16 ед. = `{1|-2}`, 0,375 = 24 ед. = `{3|-3}`.
- Размерность: `{3|1}` = 6 ипари, `{4|-1}` = 2 ипари. `1 ипари = 1,5 м`
  остаётся справочным фактом вне сцены; в UI метры не показываются.
- `gridStep` — визуальная сетка: `0` — выключена; по умолчанию 0,5 ипари
  (32 ед.), жирная линия каждые два шага (1 ипари).
- `occupancyCell` — клетка сэмплинга света/укрытий, по умолчанию 0,25 ипари
  (16 ед.). На движение и отрисовку карты не влияет; это ручка производительности
  будущих расчётов.
- Клетка движения 1×1 ипари не является полем карты: её применяет Game.
- Смена `resolution` — пересчёт всех координат, размеров и высот в черновике
  (`new/old`) с округлением; сохраняется обычным `scene.save` и поднимает
  `version`. Карта сохраняет физический размер, меняется только точность.

## Модель

### `scene_location`

```
id             IdField
owner_id       UserReferenceField, indexed
name           StringField 255, required
description    TextField, required, default ''
resolution     IntField, required, default 64, min 1, max 1024
schema_version IntField, required, default 1, min 1
is_archived    BoolField, required, default false, indexed
version        IntField, required, default 1, min 1
updated_by     UserReferenceField, nullable, onDelete setNull
created_at     DateTimeField, required, default now
updated_at     DateTimeField, required, default now
```

- `resolution` — целых единиц сцены на один ипари. По умолчанию `64`;
  допустимы степени двойки от `16` до `1024`.
- `schema_version` — версия формы snapshot, не версия строки.
- `version` увеличивается только успешным `scene.save`.
- `updated_at` и `updated_by` пишет только `scene.save`.
- Удаление — `is_archived = true`, не hard-delete.

### `scene_board`

```
id             IdField
location_id    ReferenceField scene_location, indexed, onDelete cascade
name           StringField 255, required
sort_order     IntField, required, default 0, min 0
base_file_id   FileReferenceField, nullable, onDelete setNull
bound_width    IntField, required, min 1
bound_height   IntField, required, min 1
occupancy_cell IntField, required, default 16, min 1
z_quantum      IntField, required, default 16, min 1
grid_step      IntField, required, default 0, min 0
created_at     DateTimeField, required, default now
```

- Поле — не «этаж всей локации» и не слой. У него своя подложка и свои
  координаты.
- `bound_width`/`bound_height` — логические границы в единицах сцены.
  Ориентир: типичная карта ~40×40 ипари, запас — 200×200 ипари.
- `occupancy_cell` и `z_quantum` — параметры будущей occupancy-сетки. В
  authoring-слое они только хранятся; сетка не строится. Значение по умолчанию
  `16` соответствует `resolution = 64`; при другом разрешении редактор
  предлагает `resolution / 4`. Это сэмплинг света/укрытий, не клетка движения.
- `grid_step = 0` — визуальная сетка выключена. Редактор рисует тонкие линии
  с шагом `grid_step` и жирные — каждые два шага (1 ипари при значении по
  умолчанию).
- Подложка хранится ссылкой на `Core/File`; пиксели изображения не являются
  единицами сцены. Масштаб выводится из `bound_width/height` и размеров файла;
  отдельного float-поля нет.

### `scene_element`

```
id              IdField
board_id        ReferenceField scene_board, indexed, onDelete cascade
kind            StringField 32, required, indexed
shape           JsonField, required
props           JsonField, required, default {}
sort_order      IntField, required, default 0, min 0
target_board_id IntField, nullable, indexed
created_at      DateTimeField, required, default now
```

Один writer и один валидатор на все примитивы. Новый тип не требует миграции:
добавляется `kind` и правила его `props`. Типизация остаётся на границе DTO и
серверного валидатора.

Первый набор `kind`:

| `kind` | Назначение | `props` первого среза |
|---|---|---|
| `support` | Земля, настил, крыша, стойка, мост. | `kind`, `height`, `elevation` |
| `obstacle` | Стена, ствол, мебель, непроходимый объём. | `blocksMovement`, `blocksVision`, `height` |
| `opening` | Дверь, окно, ворота, лестница, телепорт. | `kind`, `state`, `blocksVision`; цель — колонка `target_board_id` |
| `terrain` | Грязь, лёд, вода, высокая трава. | `speed`, `label` |
| `decor` | Декоративный токен или ассет. | `assetFileId`, `rotation`, `scale`, `label` |
| `marker` | Служебная точка: вход, спавн, добыча. | `role`, `label` |

`enclosure` не является элементом: это связь двух полей, см. ниже.

`target_board_id` используется `opening`; для остальных `kind` он `null`.
Валидатор проверяет, что целевое поле принадлежит той же локации, а не самому
элементу.

Будущие `kind` без изменения таблицы: `water`, `instance_layer` (лес), другие
объекты. Добавление `kind` сопровождается правилами валидации и рендера.

### `shape`

Все координаты — целые единицы сцены, начало координат — левый верхний угол
поля, `x` вправо, `y` вниз.

```json
{ "type": "rect",    "x": 0, "y": 0, "w": 640, "h": 320 }
{ "type": "circle",  "cx": 320, "cy": 320, "r": 160 }
{ "type": "wall",    "points": [[0, 0], [640, 0]], "width": 16 }
{ "type": "polygon", "points": [[0, 0], [640, 0], [640, 480]] }
{ "type": "point",   "x": 320, "y": 320 }
```

- Первый срез геометрии: `rect`, `circle`, `wall`; `point` для маркеров и
  декора; `polygon` — для террейна и будущих областей.
- Произвольные polygon-стены — `BACKLOG`; стена первого среза — полоса с
  толщиной.
- Поворот первого среза — только у `decor`; препятствия и опоры
  axis-aligned.

### `scene_enclosure`

```
id              IdField
parent_board_id ReferenceField scene_board, indexed, onDelete cascade
child_board_id  ReferenceField scene_board, indexed, unique, onDelete cascade
footprint       JsonField, required
sort_order      IntField, required, default 0, min 0
created_at      DateTimeField, required, default now
```

- `footprint` — контур в координатах родительского поля.
- `child_board_id` уникален: у поля не больше одного родителя.
- Циклы запрещены: поле не может быть предком самого себя.
- Крыша и мост — не enclosure, а `support` на родительском поле.
- Двор, кольцо частокола и атриум — стены на том же поле, без вложения.

## Сохранение и конкурентность

`scene.save` принимает полный snapshot локации. Промежуточных запросов на
каждое действие нет.

- Новые сущности клиент нумерует отрицательными `id` (`-1`, `-2`, …);
  `id = 0` запрещён.
- Ссылки внутри snapshot (`boardId`, `targetBoardId`, `parentBoardId`,
  `childBoardId`) могут указывать на временные id; писатель заменяет их на
  реальные в той же транзакции.
- Запись — одна транзакция: проверка доступа и `expectedVersion`, валидация
  всего snapshot, реконсиляция строк, обновление `version`.
- `expectedVersion` не совпал — типизированный конфликт с `currentVersion`.
  Автоматического разрешения нет: перечитать, перезаписать или сохранить копией.
- Успешный ответ возвращает сохранённый snapshot; клиент принимает его как
  новый эталон.
- Версии подсущностей не нужны.

Snapshot верхнего уровня:

```json
{
  "schemaVersion": 1,
  "id": 12,
  "name": "Таверна",
  "description": "",
  "resolution": 64,
  "version": 3,
  "boards": [],
  "elements": [],
  "enclosures": []
}
```

## Права

- `scene.edit` — создавать локации.
- `scene.view_all` и `scene.edit_all` — административный доступ.
- Владелец видит и правит свою локацию.
- Шаринг локаций и публичная видимость — `OPEN`; в первом срезе нет.
- Архивная локация не появляется в обычном списке; доступ к ней остаётся у
  владельца и администратора.

## Контракт копирования в Game

`SceneLocationSnapshot` — единственный вход для будущей `GameScene`.

- Game сохраняет `sourceLocationId` и `sourceVersion`.
- Все id полей, элементов, вложений и `targetBoardId` пересоздаются.
- Ссылки на файлы (`baseFileId`, `assetFileId`) сохраняются.
- PC/NPC в локации нет. Копируются только декор и служебные маркеры.
- `GameScene` — независимый snapshot. Правка локации после копирования не
  меняет игру.
- Occupancy, navmesh, LOS и туман не входят в snapshot локации. Их строит Game
  при копировании или общий компилятор позже — `OPEN`.
- Зависимость только `Game → Scene` на чтении; `Scene` не вызывает Game.

## Что не входит

- `GameScene`, актёры, инициатива, ОД, бой.
- occupancy, navmesh, LOS, туман, динамическая видимость.
- движение, полёт, рёбра опор, порталы как runtime-переходы.
- realtime/SSE и совместное редактирование.
- публичный шаринг локаций.
- связь с картой мира (появится позже в `Roleplay/Map`).

## Открытые вопросы

- Хранение occupancy/navmesh: `BLOB` в SmartTable, `Core/File` или кэш.
- Точная форма связи `Map → SceneLocation`: nullable `scene_location_id` или
  отдельная таблица.
- Шаринг локаций пользователям и группам.
- Импорт геометрии из SVG/растра.
- Правила LOD и инстансинга для больших лесных массивов.

## Связанные документы

- [`scene-renderer.md`](scene-renderer.md) — PixiJS/WebGL renderer.
- [`scene-plan-01.md`](scene-plan-01.md) — план authoring-среза.
- [`spatial-roadmap.md`](spatial-roadmap.md) — место локаций среди карт и сцен.
- [`battleground-system.md`](battleground-system.md) — `GameScene`, occupancy,
  LOS и движение; остаётся отложенным.
- [`cross-domain.md`](cross-domain.md) — границы `GameScene → spatial combat`.
