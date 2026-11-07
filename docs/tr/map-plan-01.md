# Карты: серверный срез

**Статус:** ~В РАБОТЕ~, 2026-11-07 — плотность заливки, глубина русла и декоративное течение; ждёт ручной проверки.
**Реализация:** ~PHP~ — готово, ~VUE~ — [`map-plan-02.md`](map-plan-02.md), ~МОК~ — да.
**Опираться на:** ~PHP~ (`Core/File`, `Core/User`, `Core/SmartTable`).

Первый срез пространственного слоя: авторские карты, эпохи-варианты, места,
поверхности, объекты, ленты и маршрут по проходимой зоне. Игровое раскрытие и
`GameWorld` — позже. Видение и границы — [`spatial-roadmap.md`](spatial-roadmap.md).

## Решения

- **Черновик → публикация.** Редактор собирает полный снимок карты локально и
  отправляет его одним `map.save`. Промежуточных запросов на каждое действие нет.
- **Одна карта — один черновик.** Границы черновика: имя, описание, видимость,
  все варианты, все места, поверхности, объекты и ленты.
- **Один токен конкурентности** — `map.version`. Версии подсущностей не нужны.
- **Конфликт не разрешается автоматически.** Версия не совпала — отказ, решение
  за пользователем: перезаписать, продолжить редактирование или сохранить копией.
- **Два геометрических слоя и ленты.** Поверхности (полигоны), ленты (реки,
  дороги, мосты) и объекты (то, что стоит на земле). Порядок внутри слоя —
  `sort_order`. Объекты всегда выше лент, ленты выше поверхностей.
- **Маршрут — по проходимой зоне, а не по авторским рёбрам.** Сетка A* строится
  из геометрии варианта. Отдельной таблицы рёбер и waypoint-ов нет.
- **Скорость живёт на элементе, а не в справочнике.** У области и ленты есть
  `color` и `speed`; у сегмента ленты — свои `speed` и `color`. Пресеты — только
  начальные значения во фронтенде, отдельного типа или справочника террейнов нет.
- **Дорога — лента.** Дорога поверх леса или реки — верхняя лента со своей
  скоростью; брод — сегмент реки со скоростью 10 и песочным цветом.
- **Точка интереса — свойство места.** Узел всегда место; площадное место —
  область с `node_id`. Отдельного `is_poi` нет.
- **Оформление ленты не меняет проходимость.** `colorBlend` и `decor.flow` —
  визуальные поля. `speed`, `segments`, растр маршрута и стоимость шага от них
  не зависят; игра читает те же цвет и скорость, что и раньше.
- **Координаты прижимаются к миру.** Клиент и сохранение не выпускают точку за
  `width × height`; ручки Безье и ширина — смещения, их не прижимаем.

## Слои данных

| слой | форма | что задаёт | примеры |
|---|---|---|---|
| Поверхность | полигон | цвет и скорость | лес, поле, гора, озеро, площадь |
| Лента | полилиния с шириной | цвет и скорость по сегментам | река, ручей, дорога, мост |
| Объект | полигон | цвет и скорость (0 — блок) | дом, стена, ворота, прилавок |
| Место | узел или `area.node_id` | информацию для клика | город, таверна, лес с легендой |

Правило клетки маршрута:

1. Верхний элемент пишет свою скорость: поверхность → лента → объект.
2. `speed = 0` закрывает клетку; `speed > 0` задаёт стоимость шага.
3. Ничего не нарисовано — открытая земля, скорость 5000.
4. Мост или ворота со скоростью 10000 открывают воду под собой; дом со
   скоростью 0 блокирует. Порядок решает всё.

Шкала хранится в сотых: обычная земля — 5000, грунтовая дорога — 7000,
каменная — 10000, лес — 2000, брод — 1000, река — 400. В карточках показывается
0–100. Дробная шкала нужна, чтобы последующие штрафы не упирались в единицу.

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
fill_opacity  IntField, required, default 55, min 0, max 100  // плотность заливки объектов, %
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
редактор предупреждает. Без подложки мир — `1200 × 900`.

### `map_node`

```
id            IdField
map_id        ReferenceField map, indexed, onDelete cascade
name          StringField 255, required
short_info    TextField, required, default ''
public_info   TextField, required, default ''
gm_info       TextField, required, default ''
linked_map_id IntField, nullable        // осознанно без FK: карта может быть удалена
icon          StringField 64, required, default ''
```

Узел — всегда место с информацией; `name` обязателен. Точечное место — узел без
области; площадное — узел, на который ссылается `map_area.node_id`. Невидимых
waypoint-ов нет. Отдельного `updated_at` нет: узлы правятся только публикацией,
а её время есть у карты.

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

Unique `(variant_id, node_id)`. Размещение площадного места — невидимый якорь
для выбора места и старых ссылок; маркер по нему не рисуется.

### `map_area`

Полигон поверхности или объекта. Слой задаётся полем `layer`; обязательные поля
различаются валидатором.

```
id         IdField
map_id     ReferenceField map, indexed, onDelete cascade
layer      StringField 16, required, default 'object'   // surface|object
color      StringField 16, required, default '#8d8d8d'
decor      JsonField, required, default {}                 // {fillOpacity?, set?, density?, scale?, jitter?, seed?}, пусто = без декора
speed      IntField, required, default 5000, min 0, max 10000
label      StringField 255, required, default ''
node_id    ReferenceField map_node, nullable, onDelete setNull
sort_order IntField, required, default 0, min 0
```

- `layer = 'surface'`: поверхность; `color` — явный `#rrggbb`.
- `layer = 'object'`: объект; `speed = 0` — непроходим, `speed > 0` — проходим.
- `node_id` разрешён обоим слоям: лес, озеро, дом и площадь могут быть местом.
- `sort_order` — порядок внутри слоя; больше — выше.
- `decor.fillOpacity` — своя плотность заливки области, 0–100. Если ключа нет,
  используется `map.fill_opacity`.
- `decor.set` — набор символов декора: `grass`, `tree`, `pine`, `field`, `meadow`,
  `mixed`. Нет ключа — область без символов.
- `decor.density` — 0–200, символов на 10 000 px²; по умолчанию значение набора.
- `decor.scale` — 40–250, размер символа в процентах; по умолчанию 100.
- `decor.jitter` — 0–100, разброс позиции внутри ячейки; по умолчанию значение
  набора.
- `decor.seed` — 0–1000000, зерно раскладки; `0` — взять id области.

Настройки декора без `set` недопустимы: `density`, `scale`, `jitter` и `seed`
имеют смысл только вместе с набором. Декор визуальный и не влияет на маршрут,
проходимость или стоимость.

### `map_variant_area`

```
id          IdField
variant_id  ReferenceField map_variant, indexed, onDelete cascade
area_id     ReferenceField map_area, indexed, onDelete cascade
path        JsonField, required      // [[x,y], ...], минимум 3 точки
handles     JsonField, required      // [{in:[dx,dy], out:[dx,dy]}, ...], пусто = прямые
is_present  BoolField, required, default true
```

Unique `(variant_id, area_id)`.

`handles` — ручки Безье замкнутого контура: сегмент `i → i+1` использует `out`
вершины `i` и `in` вершины `i+1`, последний сегмент замыкается на первую
вершину. Смещения относительны точки; пустой массив равен всем нулям. Перед
отрисовкой, hit-тестом, поиском центроида и растрированием маршрута контур
разворачивается в полилинию (`MapGeometry::flattenContour`, 12 сэмплов на
сегмент).

### `map_variant_line`

Лента: русло реки, ручей, дорога, мост.

```
id         IdField
variant_id ReferenceField map_variant, indexed, onDelete cascade
color      StringField 16, required, default '#4a7fa5'
speed      IntField, required, default 5000, min 0, max 10000
points     JsonField, required      // [[x,y], ...], минимум 2 точки
widths     JsonField, required      // [int, ...], по числу точек, каждая >= 1
handles    JsonField, required      // [{in:[dx,dy], out:[dx,dy]}, ...], пусто = прямые
segments   JsonField, required      // [{speed, color}, ...], пусто = цвет и скорость ленты
color_blend IntField, required, default 16, min 0, max 1000  // px, 0 = резкая граница
decor      JsonField, required, default {}                   // {fillOpacity?, depth?, flow?}, пусто = наследовать карту
label      StringField 255, required, default ''
is_present BoolField, required, default true
sort_order IntField, required, default 0, min 0
```

Ширина — в пикселях подложки. `handles` задают кубический Безье: `out` точки `i`
и `in` точки `i+1` — контрольные точки сегмента. Смещения относительны точки,
в пикселях; нули означают прямой сегмент. Пустой массив допустим и трактуется
как все нули.

`segments` задаёт стиль каждого сегмента и либо пуст, либо имеет ровно
`points.length - 1` элементов. Так делается брод: река со скоростью 400 и
песочный сегмент со скоростью 1000. Цвет `null` означает цвет ленты.
Ветвление реки — отдельные ленты, начинающиеся в общей точке; древовидной
структуры нет.

`color_blend` — длина перехода между разными цветами соседних сегментов в
пикселях подложки. `0` — резкая граница; по умолчанию 16. Фронтенд ограничивает
переход половиной длины соседних участков, поэтому близкие границы не
накладываются.

`decor` — визуальный декор ленты:

```json
{
  "fillOpacity": 80,
  "depth": 45,
  "flow": {
    "enabled": true,
    "speed": 40,
    "density": 50,
    "opacity": 18,
    "color": "#ffffff",
    "direction": 1,
    "seed": 7
  }
}
```

- `fillOpacity` — 0–100, плотность заливки ленты. Если ключа нет, используется
  `map.fill_opacity`; значение `0` допустимо и означает «только контур».
- `depth` — 0–100, профиль русла: центр темнее, у берегов мелководье светлее.
  `0` — плоская лента, по умолчанию `0`.
- `depthWidth` — 20–100, ширина профиля глубины в процентах полной ширины
  ленты; по умолчанию 85. Остаток у кромок отводится мелководью.
- `flow.speed` — 1–600 px подложки в секунду по осевой, по умолчанию 40.
- `flow.density` — 0–100, по умолчанию 50; сколько бликов ряби на единицу длины:
  малое значение — редкие крупные, большое — частая мелкая рябь.
- `flow.opacity` — 0–100, непрозрачность бликов, по умолчанию 18.
- `flow.color` — `#rrggbb`, по умолчанию `#ffffff`.
- `flow.direction` — `1` по порядку точек, `-1` против; по умолчанию `1`.
- `flow.seed` — 0–1000000, детерминированная раскладка бликов; по умолчанию 0.

Пустой объект `decor` означает «наследовать настройки карты, без течения и
глубины». Декор рисуется только на клиенте и не участвует в маршруте. Пресет
«Спокойная река» заполняет `colorBlend`, `decor.depth` и `decor.flow` стартовыми
значениями, но не запрещает их потом менять.

## Маршрут

`map.route` строит маршрут между двумя точками варианта.

- Сетка адаптивная: около 150 тысяч клеток. Для `1280×1258` клетка 4 px,
  для `20000×20000` — 52 px.
- Поверхности и объекты перед растрированием разворачиваются из `path` и
  `handles` в полилинию; клетка считается задетой, если её центр, угол или
  вершина контура попали внутрь. Ленты растрируются по
  расстоянию до осевой линии с полушириной, но не тоньше `0.6` клетки — иначе
  тонкая река исчезла бы на грубой сетке.
- Старт и финиш прижимаются к ближайшей проходимой клетке в радиусе 8 клеток.
  Если такой нет — `MAP_NO_ROUTE`.
- A* по 8 соседям, без срезания углов; стоимость шага — `длина × 10000 / скорость`.
- Результат: `points`, `distancePixels`, `costPixels`, `distanceMeters`,
  `costMeters`. Метры и стоимость — `null`, если у варианта нет масштаба.
- Кэша в этой итерации нет: сетка строится на запрос. Это осознанно, пока карты
  небольшие; при росте — кэш по `map.version` и варианту.

## Публикация

`map.save` принимает полный снимок карты. Новые сущности клиент нумерует
отрицательными `id` (`-1`, `-2`, …); `id = 0` запрещён. Ссылки внутри снимка
(`nodeId`, `variantId`) могут указывать на такие временные id — писатель заменит
их на реальные в той же транзакции.

Алгоритм записи — в одной транзакции:

1. Проверить доступ и `expectedVersion`.
2. Провалидировать снимок целиком.
3. **Сущности** (`map_variant`, `map_node`, `map_area`, `map_variant_line`)
   реконсилировать: `id > 0` → `UPDATE`, `id < 0` → `INSERT` и запомнить
   `temp → real`, отсутствует в снимке → `DELETE`.
4. **Связки** (`map_variant_node`, `map_variant_area`) удалить и вставить заново
   для вариантов из снимка: на их `id` никто не ссылается.
5. Увеличить `map.version`, записать `updated_at` и `updated_by`.
6. Вернуть полный снимок (как `map.get`) и `warnings: []`.

Идентичность мест, областей, лент и вариантов сохраняется между публикациями:
на неё будут ссылаться `GameWorld` и сцены.

### Создание

Отдельного `map.create` нет: создание — это `map.save` без `id` с пустым
черновиком и вариантом по умолчанию. Список карт пользуется тем же действием.

## Проверки

`MapSaveValidator` проверяет снимок целиком и возвращает ошибки с путями к полям
(`nodes[3].name`, `lines[7].segments[2].speed`), а не первую попавшуюся.

Инварианты:

- минимум один вариант; ровно один `isDefault`;
- `label` варианта не пустой и не повторяется в карте;
- место: `name` не пустой; `linkedMapId` существует и доступен;
- размещение: `variantId` из снимка; координаты в границах `width × height`
  варианта (если размеры заданы);
- карта: `fillOpacity` 0–100, по умолчанию 55;
- область: `layer` из `surface|object`; `color` — `#rrggbb`; `decor` пуст или
  содержит `fillOpacity` 0–100, `set` из списка наборов, `density` 0–200,
  `scale` 40–250, `jitter` 0–100 и `seed` 0–1000000; настройки без `set`
  недопустимы; `speed` 0–10000; `nodeId` из снимка;
  полигон ≥ 3 точек; `handles` пуст или ровно по числу вершин; координаты в
  границах; `sortOrder ≥ 0`;
- лента: ≥ 2 точек, `widths` и `handles` той же длины, ширина ≥ 1, смещения
  ручек в пределах мира; пустой `handles` допустим; `color` — `#rrggbb`;
  `speed` 0–10000; `segments` пуст или ровно по числу сегментов, у каждого
  `speed` 0–10000 и `color` `null` или `#rrggbb`; `colorBlend` 0–1000;
  `decor` пуст или содержит `fillOpacity` 0–100, `depth` 0–100,
  `depthWidth` 20–100 и `flow` с полями в допустимых диапазонах;
  `sortOrder ≥ 0`.

Предупреждений о пересечениях больше нет: маршрут сам обходит блокеры, а мост и
ворота — это проходимые элементы сверху.

## Доступ

Ключи: `map.view`, `map.edit`, `map.view_all`, `map.edit_all`.

- `map.findPage`: актор обязателен; видны `visibility = public`, свои и все при
  `map.view_all`. Архивные — только при `isArchived = true`.
- `map.get`: владелец, `public`, `map.view_all`, `map.edit_all`.
- `map.save`: владелец или `map.edit_all`; создание — `map.edit`.
- `map.archive`: владелец или `map.edit_all`.
- `map.route`: доступ на просмотр.
- Файл подложки: `IFiles::requireOwned`, не архивный; `width/height` из `FileRecord`.

## HTTP

Плоские входы, вложенные DTO биндер не гидрирует. Пять действий.

```
map.findPage    { limit, offset, q?, ownerId?, isArchived? }
map.get         { id }
map.save        { id?, expectedVersion?, force?, map: {...} }
map.archive     { id }
map.route       { mapId, variantId, fromX, fromY, toX, toY }
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
    "fillOpacity": 55,
    "variants": [
      { "id": 1, "label": "Основная", "fromMinutes": null, "sortOrder": 0,
        "filePublicId": null, "millimetersPerPixel": 1900, "isDefault": true }
    ],
    "nodes": [
      { "id": 1, "name": "Базар", "shortInfo": "...",
        "publicInfo": "...", "gmInfo": "...", "linkedMapId": null, "icon": "",
        "placements": [{ "variantId": 1, "x": 100, "y": 200,
                         "isPresent": true, "labelOverride": "" }] }
    ],
    "areas": [
      { "id": 1, "layer": "surface", "color": "#4E7A4E",
        "decor": { "set": "mixed", "density": 30, "scale": 100, "jitter": 90, "seed": 0 },
        "speed": 2000, "label": "Лес", "nodeId": null, "sortOrder": 2,
        "shapes": [{ "variantId": 1,
                     "path": [[10,10],[20,10],[20,20]],
                     "handles": [{ "in": [0,0], "out": [0,0] },
                                 { "in": [0,0], "out": [0,0] },
                                 { "in": [0,0], "out": [0,0] }],
                     "isPresent": true }] }
    ],
    "lines": [
      { "id": 1, "variantId": 1, "color": "#3F6F8F", "speed": 400,
        "points": [[0,50],[200,60],[400,90]], "widths": [24, 30, 26],
        "handles": [{ "in": [0,0], "out": [60,0] }, { "in": [-60,0], "out": [60,0] },
                    { "in": [-60,0], "out": [0,0] }],
        "segments": [{ "speed": 400, "color": null }, { "speed": 1000, "color": "#B7A98A" }],
        "colorBlend": 16,
        "decor": { "fillOpacity": 80, "depth": 45, "depthWidth": 88,
                   "flow": { "enabled": true, "speed": 40, "density": 50,
                             "opacity": 18, "color": "#ffffff",
                             "direction": 1, "seed": 7 } },
        "label": "Река", "isPresent": true, "sortOrder": 1 }
    ]
  }
}
```

Ответ `map.save` — полный снимок плюс `warnings`. Ответ `map.route`:

```json
{
  "points": [[10, 10], [30, 10], [110, 10]],
  "distancePixels": 100.4,
  "costPixels": 100.4,
  "distanceMeters": 190,
  "costMeters": 190
}
```

## Ошибки

- `MAP_NOT_FOUND` — карты, варианта, места, области или ленты нет.
- `MAP_DENIED` — нет доступа.
- `MAP_INVALID` — снимок или вход недопустимы; текст содержит путь к полю.
- `MAP_VERSION_CONFLICT` — не совпала версия; `details = { currentVersion, updatedAt, updatedBy }`.
- `MAP_NO_ROUTE` — между точками нет проходимого пути или нет проходимой клетки
  рядом со стартом/финишем.

## Слои кода

- `Action/*` — пять HTTP-действий.
- `Service/MapSave*` — разбор, проверка, запись снимка.
- `Service/MapRouteGrid` — растровая сетка и A*; `Service/MapGeometry` — чистая
  геометрия (полигоны, Безье, расстояния).
- `Service/MapRouteService` — сборка геометрии варианта и вызов сетки.
- `Service/MapViewAssembler` — JSON снимка, места, области, ленты, маршрут.
- `Repository/*` и `Dto/*` — строки SmartTable и записи.
- `Setup/*` — таблицы карт.

`MapEdge*`, `MapRouteGraph`, `MapBlockingCheck` удалены.

## Тесты

- `MapRouteGridTest` — открытая земля, обход воды, дорога поверх воды,
  объект со скоростью над блокером, стоимость медленного сегмента, полностью
  закрытая карта.
- `MapHttpMysqlTest` — сохранение снимка, цвет/скорость и порядок области,
  стили сегментов, `colorBlend` и `decor.flow`, маршрут вокруг воды, мост и
  дорога поверх воды, закрытая карта, конфликт, доступ, стабильные id.
- `MapGeometryTest` — полигоны, Безье и расстояния.

## Вне среза

- `GameWorld`, раскрытие информации по играм, туман войны.
- SVG-парсер, импорт чужих карт.
- Кэш сетки маршрута.
- Пользовательские шаблоны объектов (пока встроенные).
- `localStorage`-черновик.
