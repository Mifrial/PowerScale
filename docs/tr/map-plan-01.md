# Карты: серверный срез

**Статус:** ~В РАБОТЕ~, 2026-11-06 — серверный срез переработан под «черновик → публикация»; ждёт ручной проверки.
**Реализация:** ~PHP~ — готово, ~VUE~ — [`map-plan-02.md`](map-plan-02.md), ~МОК~ — да.
**Опираться на:** ~PHP~ (`Core/File`, `Core/User`, `Core/SmartTable`).

Первый срез пространственного слоя: авторские карты, эпохи-варианты, узлы,
ландшафт, объекты, рёбра и маршрут. Игровое раскрытие и `GameWorld` — позже.
Видение и границы — [`spatial-roadmap.md`](spatial-roadmap.md).

## Решения

- **Черновик → публикация.** Редактор собирает полный снимок карты локально и
  отправляет его одним `map.save`. Промежуточных запросов на каждое действие нет.
- **Одна карта — один черновик.** Границы черновика: имя, описание, видимость,
  все варианты, весь граф.
- **Один токен конкурентности** — `map.version`. Версии подсущностей не нужны и
  удаляются из схемы.
- **Конфликт не разрешается автоматически.** Версия не совпала — отказ, решение
  за пользователем: перезаписать, продолжить редактирование или сохранить копией.
- **Два геометрических слоя.** Ландшафт (поверхность) и объекты (то, что стоит на
  поверхности). Разделение — как в [`battleground-system.md`](battleground-system.md).
- **Проходимость в маршруте — только у ребра.** Геометрия даёт предупреждение при
  публикации, а не запрет: мост через реку и арка в доме остаются возможными.
- **Река — лента, а не полигон.** Полилиния русла с шириной в точках; озёра,
  леса и дома — полигоны.

## Слои данных

| слой | форма | что задаёт | примеры |
|---|---|---|---|
| Ландшафт | полигон или лента | тип поверхности и скорость | река (лента), озеро, лес, болото, поле |
| Объекты | полигон | непроходимость и/или идентичность | дом, стена, ворота, прилавок |
| Граф | ребро | стоимость и проходимость маршрута | улица, мост, тропа |

Ландшафт и объекты — визуал и проверка при публикации. Стоимость пути считает
только террейн ребра. Ландшафт с `speedFactor = 0` и объект с
`blocksMovement = true` считаются блокерами при проверке пересечений.

## Таблицы

Все координаты — пиксели подложки варианта, целые. Масштаб — целые
`millimetersPerPixel`: `FloatField` в SmartTable нет.

### `map`

```
id            IdField
owner_id      UserReferenceField, indexed
name          StringField 255, required
description   TextField, required, default ''
visibility    StringField 16, required, default 'private'   // public|private
is_archived   BoolField, required, default false, indexed
version       IntField, required, default 1, min 1
updated_by    UserReferenceField, nullable, onDelete setNull
created_at    DateTimeField, required, default now
updated_at    DateTimeField, required, default now
```

`version` увеличивается только публикацией. `updated_at` и `updated_by` пишет
только `map.save` — они нужны для сообщения о конфликте.

### `map_variant`

```
id                    IdField
map_id                ReferenceField map, indexed, onDelete cascade
label                 StringField 255, required
from_minutes          IntField, nullable, min 0
sort_order            IntField, required, default 0
base_file_id          FileReferenceField, nullable, onDelete setNull
width                 IntField, nullable, min 1
height                IntField, nullable, min 1
millimeters_per_pixel IntField, nullable, min 1
is_default            BoolField, required, default false
created_at            DateTimeField, required, default now
```

Unique `(map_id, label)`. `width`/`height` берутся из `FileRecord` при публикации,
не с клиента. Замена подложки на другую размерность координаты не масштабирует —
редактор предупреждает.

### `map_terrain`

Глобальный справочник, без `map_id`.

```
id            IdField
code          StringField 64, required, unique
label         StringField 255, required
speed_factor  IntField, required, min 0, max 100
color         StringField 16, required
sort_order    IntField, required, default 0
```

Seed: `road 100`, `path 70`, `grass 50`, `forest 30`, `swamp 15`, `water 0`,
`wall 0`. `speedFactor = 0` означает «поверхность непроходима»; объекты
непроходимость объявляют флагом, а не террейном.

### `map_node`

```
id            IdField
map_id        ReferenceField map, indexed, onDelete cascade
is_poi        BoolField, required, default false
name          StringField 255, required, default ''
short_info    TextField, required, default ''
public_info   TextField, required, default ''
gm_info       TextField, required, default ''
linked_map_id IntField, nullable        // осознанно без FK: карта может быть удалена
icon          StringField 64, required, default ''
```

`is_poi = false` — невидимый waypoint. Информация у waypoint пустая.
Отдельного `updated_at` нет: узлы правятся только публикацией, а её время есть
у карты.

### `map_variant_node`

```
id             IdField
variant_id     ReferenceField map_variant, indexed, onDelete cascade
node_id        ReferenceField map_node, indexed, onDelete cascade
x              IntField, required, min 0
y              IntField, required, min 0
is_present     BoolField, required, default true
label_override StringField 255, required, default ''
```

Unique `(variant_id, node_id)`.

### `map_area`

Полигон ландшафта или объект. Слой задаётся полем `layer`; обязательные поля
различаются валидатором.

```
id              IdField
map_id          ReferenceField map, indexed, onDelete cascade
layer           StringField 16, required, default 'object'   // terrain|object
terrain_id      ReferenceField map_terrain, nullable, onDelete setNull
kind            StringField 32, required, default 'other'    // building|wall|gate|stall|other
label           StringField 255, required, default ''
node_id         ReferenceField map_node, nullable, onDelete setNull
blocks_movement BoolField, required, default false
```

- `layer = 'terrain'`: обязателен `terrain_id`, запрещены `node_id` и
  `blocks_movement = true`; `kind` не используется.
- `layer = 'object'`: обязателен `kind`; `terrain_id` не используется;
  `blocks_movement` — непроходимость; `node_id` — повышение до POI.

Повышение дома до точки интереса = создать узел и проставить `node_id`; отдельной
операции в API нет, всё едет снимком.

### `map_variant_area`

```
id          IdField
variant_id  ReferenceField map_variant, indexed, onDelete cascade
area_id     ReferenceField map_area, indexed, onDelete cascade
path        JsonField, required      // [[x,y], ...], минимум 3 точки
is_present  BoolField, required, default true
```

Unique `(variant_id, area_id)`.

### `map_variant_line`

Лента: русло реки, ручей, декоративная дорога вне графа.

```
id          IdField
variant_id  ReferenceField map_variant, indexed, onDelete cascade
terrain_id  ReferenceField map_terrain, indexed, onDelete restrict
points      JsonField, required      // [[x,y], ...], минимум 2 точки
widths      JsonField, required      // [int, ...], по числу точек, каждая >= 1
handles     JsonField, required      // [{in:[dx,dy], out:[dx,dy]}, ...], пусто = прямые
label       StringField 255, required, default ''
is_present  BoolField, required, default true
```

Ширина — в пикселях подложки. `handles` задают кубический Безье: `out`
точки `i` и `in` точки `i+1` — контрольные точки сегмента. Смещения
относительны точки, в пикселях; нули означают прямой сегмент. Пустой
массив допустим и трактуется как все нули.

Ленты не участвуют в маршруте: непроходимость выражается террейном ребра.
Ветвление реки — отдельные ленты, начинающиеся в общей точке; отдельной
древовидной структуры нет.

### `map_variant_edge`

```
id              IdField
variant_id      ReferenceField map_variant, indexed, onDelete cascade
from_node_id    ReferenceField map_node, indexed, onDelete cascade
to_node_id      ReferenceField map_node, indexed, onDelete cascade
terrain_id      ReferenceField map_terrain, indexed, onDelete restrict
path            JsonField, required   // [[x,y], ...]; [] — прямая между узлами
distance_meters IntField, nullable, min 0
is_directed     BoolField, required, default false
label           StringField 255, required, default ''
```

Параллельные рёбра между одной парой узлов разрешены: это разные дороги/тропы.
Пустой `path` — не «нет геометрии», а «прямая по размещениям узлов».

## Публикация

`map.save` принимает полный снимок карты. Новые сущности клиент нумерует
отрицательными `id` (`-1`, `-2`, …); `id = 0` запрещён. Ссылки внутри снимка
(`nodeId`, `fromNodeId`/`toNodeId`, `variantId`) могут указывать на такие
временные id — писатель заменит их на реальные в той же транзакции.

Алгоритм записи — в одной транзакции:

1. Проверить доступ и `expectedVersion`.
2. Провалидировать снимок целиком (см. ниже).
3. **Сущности** (`map_variant`, `map_node`, `map_area`, `map_variant_edge`,
   `map_variant_line`) реконсилировать: `id > 0` → `UPDATE`, `id < 0` → `INSERT`
   и запомнить `temp → real`, отсутствует в снимке → `DELETE`.
4. **Связки** (`map_variant_node`, `map_variant_area`) удалить и вставить заново
   для вариантов из снимка: на их `id` никто не ссылается.
5. Увеличить `map.version`, записать `updated_at` и `updated_by`.
6. Вернуть полный снимок (как `map.get`) и список предупреждений.

Идентичность узлов, областей, рёбер и вариантов сохраняется между публикациями:
на неё будут ссылаться `GameWorld` и сцены.

### Конфликт версий

- `expectedVersion` обязателен, если `id` передан.
- Не совпало и `force = false` → `MAP_VERSION_CONFLICT`,
  `details = { currentVersion, updatedAt, updatedBy }`, запись не выполняется.
- `force = true` — запись без сверки; применяется после подтверждения в UI.
- `id` не передан → создаётся новая карта из черновика (в том числе копия при
  конфликте). Версия новой карты — `1`.

### Создание

Отдельного `map.create` нет: создание — это `map.save` без `id` с пустым
черновиком и вариантом по умолчанию. Список карт пользуется тем же действием.

## Проверки

`MapSaveValidator` проверяет снимок целиком и возвращает ошибки с путями к полям
(`nodes[3].name`, `edges[7].toNodeId`), а не первую попавшуюся.

Инварианты:

- минимум один вариант; ровно один `isDefault`;
- `label` варианта не пустой и не повторяется в карте;
- узел: `isPoi = true` требует непустой `name`; `linkedMapId` существует и доступен;
- размещение: `variantId` из снимка; координаты в границах `width × height`
  варианта (если размеры заданы);
- область: слой и обязательные поля по слою; полигон ≥ 3 точек; координаты в
  границах;
- лента: ≥ 2 точек, `widths` и `handles` той же длины, ширина ≥ 1, смещения
  ручек в пределах мира; пустой `handles` допустим;
- ребро: `fromNodeId ≠ toNodeId`, оба узла из снимка и размещены в варианте ребра;
  `distanceMeters ≥ 0`; если `null` — у варианта задан масштаб.

`MapBlockingCheck` считает **предупреждения** (публикация не отменяется):

- `MAP_EDGE_CROSSES_BLOCKER` — полилиния ребра пересекает полигон/ленту
  ландшафта с `speedFactor = 0` или объект с `blocksMovement = true`.
  Изогнутая лента перед проверкой разворачивается в полилинию по Безье.
  `details = { edgeIndex, blockerIndex }`.

Предупреждение означает «это точно мост/арка?». Решение принимает автор, а не
геометрия; ребро с проходимым террейном остаётся проходимым.

## Доступ

Ключи: `map.view`, `map.edit`, `map.view_all`, `map.edit_all`.

- `map.findPage`: актор обязателен; видны `visibility = public`, свои и все при
  `map.view_all`. Архивные — только при `isArchived = true`.
- `map.get`: владелец, `public`, `map.view_all`, `map.edit_all`.
- `map.save`: владелец или `map.edit_all`; создание — `map.edit`.
- `map.archive`: владелец или `map.edit_all`.
- `map.route`: доступ на просмотр.
- `map.getTerrains`: актор обязателен, ключ не нужен.
- Файл подложки: `IFiles::requireOwned`, не архивный; `width/height` из `FileRecord`.

## HTTP

Плоские входы, вложенные DTO биндер не гидрирует. Шесть действий вместо
семнадцати.

```
map.findPage    { limit, offset, q?, ownerId?, isArchived? }
map.get         { id }
map.getTerrains {}
map.save        { id?, expectedVersion?, force?, map: {...} }
map.archive     { id }
map.route       { mapId, variantId, fromNodeId, toNodeId }
```

Тело `map.save`:

```json
{
  "id": 1,
  "expectedVersion": 5,
  "force": false,
  "map": {
    "name": "Аустан",
    "description": "",
    "visibility": "private",
    "variants": [
      { "id": 1, "label": "Основная", "fromMinutes": null, "sortOrder": 0,
        "filePublicId": null, "millimetersPerPixel": 1900, "isDefault": true }
    ],
    "nodes": [
      { "id": 1, "isPoi": true, "name": "Базар", "shortInfo": "...",
        "publicInfo": "...", "gmInfo": "...", "linkedMapId": null, "icon": "",
        "placements": [{ "variantId": 1, "x": 100, "y": 200,
                         "isPresent": true, "labelOverride": "" }] }
    ],
    "areas": [
      { "id": 1, "layer": "object", "terrainCode": null, "kind": "building",
        "label": "Ратуша", "nodeId": null, "blocksMovement": true,
        "shapes": [{ "variantId": 1,
                     "path": [[10,10],[20,10],[20,20]], "isPresent": true }] }
    ],
    "lines": [
      { "id": 1, "variantId": 1, "terrainCode": "water", "points": [[0,50],[200,60],[400,90]],
        "widths": [24, 30, 26],
        "handles": [
          { "in": [0,0], "out": [80,0] },
          { "in": [-80,10], "out": [80,-10] },
          { "in": [-80,0], "out": [0,0] }
        ],
        "label": "Река", "isPresent": true }
    ],
    "edges": [
      { "id": 1, "variantId": 1, "fromNodeId": 1, "toNodeId": 2,
        "terrainCode": "road", "path": [[100,200],[150,220]],
        "distanceMeters": null, "isDirected": false, "label": "" }
    ]
  }
}
```

Ответ — снимок `map.get` плюс `warnings: []`.

`map.get` отдаёт:

```json
{
  "id": 1,
  "ownerId": 1,
  "name": "Аустан",
  "description": "",
  "visibility": "private",
  "isArchived": false,
  "version": 5,
  "updatedAt": 1762300000,
  "updatedBy": { "id": 1, "name": "Мастер" },
  "canEdit": true,
  "variants": [
    { "id": 1, "label": "Основная", "fromMinutes": null, "sortOrder": 0,
      "baseFile": { "publicId": "...", "width": 1280, "height": 1220 },
      "millimetersPerPixel": 1900, "isDefault": true }
  ],
  "nodes": [
    { "id": 1, "isPoi": true, "name": "Базар", "shortInfo": "...",
      "publicInfo": "...", "gmInfo": "...", "linkedMapId": null, "icon": "",
      "placements": [{ "variantId": 1, "x": 100, "y": 200,
                       "isPresent": true, "labelOverride": "" }] }
  ],
  "areas": [
    { "id": 1, "layer": "object", "terrainCode": null, "kind": "building",
      "label": "Ратуша", "nodeId": null, "blocksMovement": true,
      "shapes": [{ "variantId": 1,
                   "path": [[10,10],[20,10],[20,20]], "isPresent": true }] }
  ],
  "lines": [
    { "id": 1, "variantId": 1, "terrainCode": "water", "points": [[0,50],[200,60],[400,90]],
      "widths": [24, 30, 26],
      "handles": [
        { "in": [0,0], "out": [80,0] },
        { "in": [-80,10], "out": [80,-10] },
        { "in": [-80,0], "out": [0,0] }
      ],
      "label": "Река", "isPresent": true }
  ],
  "edges": [
    { "id": 1, "variantId": 1, "fromNodeId": 1, "toNodeId": 2,
      "terrainCode": "road", "path": [[100,200],[150,220]],
      "distanceMeters": null, "isDirected": false, "label": "" }
  ]
}
```

`canEdit = false` скрывает `publicInfo` и `gmInfo` у узлов. `shortInfo` остаётся.

`map.route`:

```json
{
  "nodeIds": [1, 5, 2],
  "points": [[100,200],[140,220],[300,400]],
  "edgeIds": [10, 11],
  "distanceMeters": 2400,
  "costMeters": 3200
}
```

Нет пути — `MAP_NO_ROUTE`. Нет масштаба и нет явных расстояний — `MAP_INVALID`.
Маршрут считается Dijkstra по рёбрам варианта; `speedFactor = 0` пропускается.
Стоимость = `distance * 100 / speedFactor`. Ландшафт и объекты в расчёте не
участвуют.

## Ошибки

- `MAP_INVALID` — недопустимые поля/границы/путь/масштаб.
- `MAP_NOT_FOUND` — карты, варианта, узла, области, ленты или ребра нет.
- `MAP_DENIED` — доступ на просмотр/редактирование.
- `MAP_NO_ROUTE` — пути нет.
- `MAP_VERSION_CONFLICT` — `expectedVersion` не совпал с текущим.

`AUTH_REQUIRED` / `AUTH_DENIED` — из `Core/User`.

## Слои кода

- `MapAccessService` — актор, владелец, `*_all`, public.
- `MapCatalogService` — каталог, архив, чтение карты.
- `MapSaveService` — публикация: транзакция, реконсиляция, `temp → real`.
- `MapSaveValidator` — инварианты снимка и ошибки по полям.
- `MapBlockingCheck` — предупреждения о пересечениях; геометрия — `MapGeometry`.
- `MapVariantService` — чтение вариантов, выбор default.
- `MapRouteService` — загрузка графа варианта; расчёт — `MapRouteGraph`.
- `MapTerrainService` — глобальный справочник.
- `MapDetailService` — снимок карты для JSON.
- `MapViewAssembler` — JSON и фильтрация секретов по `canEdit`.
- `MapCatalogHttp` / `MapSaveHttp` / `MapRouteHttp` / `MapTerrainHttp` — сценарии.
- `MapPortFactory` — сборка.
- Сторы `MapNodeStore` / `MapAreaStore` / `MapEdgeStore` / `MapLineStore` —
  чтение и запись таблиц без бизнес-правил.

Лимиты качества: не больше 6 зависимостей конструктора и 10 публичных методов
класса; поэтому публикация, валидация и геометрия разделены.

## Тесты

- Unit: `MapRouteGraph` (параллельные рёбра, направленное, блокер, нет пути,
  масштаб), `MapSaveValidator` (инварианты по слоям), `MapGeometry` и
  `MapBlockingCheck` (пересечения ребра с полигоном и лентой).
- MySQL: схема и unique-ключи; публикация (создание, правка, удаление узла/ребра,
  `temp → real`, стабильность ID при повторной публикации); конфликт версий и
  `force`; копия через `map.save` без `id`; `findPage`; `route`;
  `canEdit`-фильтрация `gmInfo`.
- `composer test:module map`.

## Вне среза

- SVG-парсер.
- `GameWorld` и раскрытие.
- Автоподбор варианта по мировому времени.
- Шаринг карт между пользователями сверх `visibility` / `*_all`.
- Экспорт и печать.
