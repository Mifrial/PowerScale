import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import type { SceneSurfaceKind } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceKind';
import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { SceneGeometryService } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import {
  sceneDecorFootprint,
  sceneDecorSilhouette,
  type SceneWorldFootprint,
} from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';
import {
  isSceneDeck,
  isWalkableRoof,
  sceneDeckThickness,
  sceneSurfaceElevation,
  sceneWaterDepth,
} from '@/modules/Roleplay/Scene/Service/SceneHeight';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';
import { signedDistanceField } from '@/modules/Roleplay/Scene/Service/SceneSurfaceDistance';

/** Тип поверхности по виду террейна. */
const TERRAIN_SURFACE: Readonly<Record<string, SceneSurfaceKind>> = {
  grass: 'grass',
  dirt: 'dirt',
  stone: 'stone',
  sand: 'sand',
  mud: 'mud',
  ice: 'ice',
  water: 'water',
};

/**
 * Порядок террейна: суша, вода, поверх воды.
 *
 * Вода лежит поверх суши — река перекрывает дорогу независимо от порядка
 * элементов. Остров и насыпь, наоборот, лежат поверх воды: их контур вырезает
 * воду, а не тонет под ней. Один порядок нужен и рисунку, и сетке поверхностей,
 * иначе трава острова разошлась бы с картой проходимости.
 */
export function sceneTerrainRank(element: SceneElement): number {
  if (element.props.kind === 'water') {
    return 1;
  }

  return element.props.overWater === true ? 2 : 0;
}

/** Индекс клетки по мировым координатам; `-1` — точка вне поля. */
export function surfaceIndex(grid: SceneSurfaceGrid, x: number, y: number): number {
  const column = Math.floor((x - grid.x) / grid.cell);
  const row = Math.floor((y - grid.y) / grid.cell);
  if (column < 0 || row < 0 || column >= grid.columns || row >= grid.rows) {
    return -1;
  }

  return row * grid.columns + column;
}

/** Центр клетки в мировых координатах. */
export function surfaceCenter(grid: SceneSurfaceGrid, index: number): ScenePoint {
  const column = index % grid.columns;
  const row = Math.floor(index / grid.columns);

  return [grid.x + (column + 0.5) * grid.cell, grid.y + (row + 0.5) * grid.cell];
}

/**
 * Точка стоит под настилом: клетка несёт конечный положительный зазор.
 *
 * `Infinity` — потолка нет вовсе, `<= 0` — настил лежит на поверхности и
 * заменяет пол. Оба случая не «под настилом», иначе актёр на лугу считался бы
 * стоящим под мостом.
 */
export function surfaceUnderDeck(grid: SceneSurfaceGrid, x: number, y: number): boolean {
  const cell = surfaceIndex(grid, x, y);
  if (cell < 0) {
    return false;
  }

  const clearance = grid.clearance[cell];

  return Number.isFinite(clearance) && clearance > 0;
}

/**
 * Высота пола под точкой, единицы сцены.
 *
 * Верхний уровень стоит на настиле, базовый — на поверхности под ним: под
 * мостом это вода или берег. Обзор стартует от пола, поэтому луч с настила
 * идёт над ним, а не сквозь него.
 */
export function surfaceFloor(grid: SceneSurfaceGrid, point: ScenePoint, level = 0): number {
  const cell = surfaceIndex(grid, point[0], point[1]);
  if (cell < 0) {
    return 0;
  }

  return level > 0 ? grid.elevation[cell] : grid.ground[cell];
}

/** Обход клеток в границах фигуры. */
type CellVisitor = (index: number, center: ScenePoint) => void;

/**
 * Поверхность поля, стоимость прохода и препятствия.
 *
 * Слой читает только данные сцены — поле, элементы, свойства и геометрию — и
 * не знает о Pixi. Клетка равна `occupancyCell` поля: по ней же потом считаются
 * маршруты и видимость. Порядок наложения повторяет рендер: террейн по
 * `sortOrder`, вода последней, затем мост и съезд как настил, препятствия,
 * декор и проёмы.
 */
export class SceneSurfaceService {
  constructor(private readonly geometry: SceneGeometryService = new SceneGeometryService()) {}

  /** Собирает сетку поверхностей активного поля. */
  build(board: SceneBoard, elements: readonly SceneElement[], resolution: number): SceneSurfaceGrid {
    const cell = Math.max(1, Math.floor(board.occupancyCell) || SCENE_SURFACE.fallbackCell);
    const columns = Math.max(1, Math.ceil(board.boundWidth / cell));
    const rows = Math.max(1, Math.ceil(board.boundHeight / cell));
    const size = columns * rows;
    const grid: SceneSurfaceGrid = {
      cell,
      resolution: Math.max(1, resolution),
      x: 0,
      y: 0,
      columns,
      rows,
      kinds: new Array<SceneSurfaceKind>(size).fill('none'),
      speeds: new Array<number>(size).fill(0),
      costs: new Array<number>(size).fill(Number.POSITIVE_INFINITY),
      elevation: new Float32Array(size),
      ground: new Float32Array(size),
      clearance: new Float32Array(size).fill(Number.POSITIVE_INFINITY),
      sightBottom: new Float32Array(size).fill(Number.POSITIVE_INFINITY),
      sightTop: new Float32Array(size).fill(Number.NEGATIVE_INFINITY),
    };

    const own = elements.filter((element) => element.boardId === board.id);
    const openings = own.filter((element) => element.kind === 'opening');
    this.paintTerrain(grid, own);
    this.paintBridges(grid, own, resolution);
    this.paintObstacles(grid, own, openings);
    this.paintDecor(grid, own, resolution);
    this.paintOpenings(grid, own);

    grid.costs = grid.speeds.map((speed) => (speed > 0 ? 100 / speed : Number.POSITIVE_INFINITY));

    return grid;
  }

  /** Террейн: вода перекрывает сушу, а остров — воду. */
  private paintTerrain(grid: SceneSurfaceGrid, elements: readonly SceneElement[]): void {
    const terrain = elements
      .filter((element) => element.kind === 'terrain')
      .sort(
        (left, right) =>
          sceneTerrainRank(left) - sceneTerrainRank(right) || left.sortOrder - right.sortOrder || left.id - right.id,
      );

    for (const element of terrain) {
      const kind = TERRAIN_SURFACE[element.props.kind ?? ''] ?? null;
      if (kind === null) {
        continue;
      }

      const preset = SCENE_SURFACE.speeds[kind];
      const elevation = sceneSurfaceElevation(element);
      this.paintShape(grid, element.shape, (index) => {
        grid.kinds[index] = kind;
        grid.speeds[index] = this.speedOf(element, preset);
        grid.elevation[index] = elevation;
        grid.ground[index] = elevation;
      });

      if (kind === 'water') {
        this.paintWaterBank(grid, element);
      }
    }
  }

  /**
   * Наклонный берег: полоса `shoreWidth` снаружи уреза опускается от отметки
   * берега до поверхности воды.
   *
   * Высота линейна по расстоянию до уреза, поэтому вход в воду — наклон, а не
   * обрыв. Полоса только опускает уже нарисованную поверхность: подъём дороги
   * или настила она не трогает. Без `shoreWidth` откоса нет — вода остаётся на
   * своей отметке с обрывом у уреза, и это осознанный выбор автора.
   */
  private paintWaterBank(grid: SceneSurfaceGrid, element: SceneElement): void {
    const depth = sceneWaterDepth(element);
    const width = element.props.shoreWidth ?? 0;
    if (depth <= 0 || width <= 0) {
      return;
    }

    const probe = this.geometry.probe(element.shape, 0);
    const margin = width;
    const bounds: SceneBounds = {
      x: probe.bounds.x - margin,
      y: probe.bounds.y - margin,
      width: probe.bounds.width + margin * 2,
      height: probe.bounds.height + margin * 2,
    };
    const minColumn = this.clamp(Math.floor((bounds.x - grid.x) / grid.cell), 0, grid.columns - 1);
    const maxColumn = this.clamp(Math.floor((bounds.x + bounds.width - grid.x) / grid.cell), 0, grid.columns - 1);
    const minRow = this.clamp(Math.floor((bounds.y - grid.y) / grid.cell), 0, grid.rows - 1);
    const maxRow = this.clamp(Math.floor((bounds.y + bounds.height - grid.y) / grid.cell), 0, grid.rows - 1);
    const columns = maxColumn - minColumn + 1;
    const rows = maxRow - minRow + 1;

    const mask = new Float32Array(columns * rows);
    for (let row = 0; row < rows; row += 1) {
      for (let column = 0; column < columns; column += 1) {
        const index = (minRow + row) * grid.columns + (minColumn + column);
        mask[row * columns + column] = probe.contains(surfaceCenter(grid, index), 0) ? 1 : 0;
      }
    }

    const signed = signedDistanceField(mask, columns, rows);
    const bankCells = width / grid.cell;
    for (let row = 0; row < rows; row += 1) {
      for (let column = 0; column < columns; column += 1) {
        const local = row * columns + column;
        // Внутри воды высоту уже задала сама вода.
        if (signed[local] > 0) {
          continue;
        }

        const outward = -signed[local];
        if (outward >= bankCells) {
          continue;
        }

        const share = 1 - outward / bankCells;
        const index = (minRow + row) * grid.columns + (minColumn + column);
        grid.elevation[index] = Math.min(grid.elevation[index], -depth * share);
        grid.ground[index] = Math.min(grid.ground[index], -depth * share);
      }
    }
  }

  /**
   * Мост, съезд и проходимая крыша — настил: проходим, перекрывает только свою
   * толщину.
   *
   * Настил — потолок, а не стена: луч с настила идёт над ним, луч из-под
   * настила — под ним, а сквозь настил не видно ни тому, ни другому.
   */
  private paintBridges(grid: SceneSurfaceGrid, elements: readonly SceneElement[], resolution: number): void {
    for (const element of elements) {
      if (!isSceneDeck(element)) {
        continue;
      }

      // Крыша — не мост: клетка должна говорить обзору и карте, что под
      // ногами настил над домом, а не доски через реку.
      const roof = isWalkableRoof(element);
      const preset = SCENE_SURFACE.speeds[roof ? 'roof' : 'bridge'];
      const deckElevation = sceneSurfaceElevation(element);
      const thickness = sceneDeckThickness(element, resolution);
      const base = grid.resolution;
      // Полоса настила в тех же координатах, что и пол: мост на уровне берега
      // над врезанным руслом перекрывает свою толщину ниже нуля, и луч из-под
      // него упирается в доски.
      const bottom = (deckElevation - thickness) / base;
      const top = deckElevation / base;
      this.paintShape(
        grid,
        element.shape,
        (index) => {
          // Клетка до настила — это поверхность под ним: вода, берег, дорога
          // или нижний настил. Зазор считается до низа настила и берётся
          // самый узкий, если настилов несколько.
          const floor = grid.elevation[index];
          const gap = deckElevation - thickness - floor;
          grid.clearance[index] = Math.min(grid.clearance[index], gap);
          grid.kinds[index] = roof ? 'roof' : 'bridge';
          grid.speeds[index] = this.speedOf(element, preset);
          grid.elevation[index] = deckElevation;
          // Настил на уровне пола не перекрывает: его полоса лежит под полом.
          // Над врезанным руслом та же полоса уже над водой и закрывает луч.
          if (top > bottom && top > floor / base) {
            this.paintOccluder(grid, index, bottom, top);
          }
        },
        sceneRotation(element),
      );
    }
  }

  /** Препятствия: ход и обзор по флагам элемента; проём вырезает стену. */
  private paintObstacles(
    grid: SceneSurfaceGrid,
    elements: readonly SceneElement[],
    openings: readonly SceneElement[],
  ): void {
    // Проём вырезает стену, а не ложится на неё слоем: иначе полоса стены
    // осталась бы под окном и перекрывала обзор до неба. Вырез считается по
    // геометрии, поэтому работает и для проёмов без явной привязки.
    const cutters = openings
      .filter((opening) => opening.shape.type === 'wall')
      .map((opening) => this.geometry.probe(opening.shape, 0));

    for (const element of elements) {
      if (element.kind !== 'obstacle') {
        continue;
      }

      const cut = element.shape.type === 'wall' && cutters.length > 0;
      const blocksMovement = element.props.blocksMovement !== false;
      const blocksVision = element.props.blocksVision !== false;
      this.paintShape(grid, element.shape, (index) => {
        if (cut) {
          const center = surfaceCenter(grid, index);
          if (cutters.some((cutter) => cutter.contains(center, 0))) {
            return;
          }
        }

        if (blocksMovement) {
          grid.speeds[index] = 0;
        }
        if (blocksVision) {
          this.paintOccluder(grid, index, 0, Number.POSITIVE_INFINITY);
        }
      });
    }
  }

  /**
   * Декор: основание задаёт ход, силуэт — обзор.
   *
   * Основание и силуэт берутся из `props.footprint` и `props.silhouette` в
   * единицах сцены. У старых сцен полей нет: остаются доли ширины спрайта,
   * поэтому прозрачные поля картинки влияют только на них. Скорость декора —
   * `props.speed`, по умолчанию `0`: объект непроходим, пока не сказано иное.
   */
  private paintDecor(grid: SceneSurfaceGrid, elements: readonly SceneElement[], resolution: number): void {
    const base = Math.max(1, resolution);
    for (const element of elements) {
      if (element.kind !== 'decor') {
        continue;
      }

      if (element.shape.type !== 'point') {
        const speed = this.decorSpeed(element);
        const blocksVision = element.props.blocksVision !== false;
        this.paintShape(grid, element.shape, (index) => {
          grid.speeds[index] = speed;
          if (blocksVision) {
            this.paintOccluder(grid, index, 0, Number.POSITIVE_INFINITY);
          }
        });
        continue;
      }

      const speed = this.decorSpeed(element);
      const blocksVision = element.props.blocksVision !== false;

      // Силуэт первым: основание наследует его потолок на тех клетках, где
      // силуэт накрывает место. Так валун с верхом виден насквозь сверху, а
      // ствол дерева под кроной остаётся твёрдым до неба.
      const covered = new Map<number, number>();
      const silhouette = blocksVision ? sceneDecorSilhouette(element, resolution) : null;
      if (silhouette !== null) {
        const bottom = silhouette.bottom / base;
        const top = silhouette.top === undefined ? Number.POSITIVE_INFINITY : silhouette.top / base;
        this.paintArea(grid, silhouette.area, (index) => {
          this.paintOccluder(grid, index, bottom, top);
          covered.set(index, top);
        });
      }

      const footprint = sceneDecorFootprint(element, resolution);
      if (footprint !== null) {
        this.paintArea(grid, footprint, (index) => {
          grid.speeds[index] = speed;
          if (blocksVision) {
            this.paintOccluder(grid, index, 0, covered.get(index) ?? Number.POSITIVE_INFINITY);
          }
        });
      }
    }
  }

  /**
   * Полоса перекрытия клетки: низ и верх объединяются в оболочку.
   *
   * Клетка хранит одну полосу, поэтому пересекающиеся силуэты сливаются:
   * стена и настил над ней дают общий диапазон. Для сцены этого достаточно —
   * перекрытия ставят встык, а не друг над другом.
   */
  private paintOccluder(grid: SceneSurfaceGrid, index: number, bottom: number, top: number): void {
    grid.sightBottom[index] = Math.min(grid.sightBottom[index], bottom);
    grid.sightTop[index] = Math.max(grid.sightTop[index], top);
  }

  /** Скорость декора: явная или непроходимость по умолчанию. */
  private decorSpeed(element: SceneElement): number {
    const speed = element.props.speed;
    if (typeof speed !== 'number' || !Number.isFinite(speed)) {
      return 0;
    }

    return Math.max(0, Math.min(SCENE_NAVIGATION.maxSpeed, speed));
  }

  /** Проёмы: дверь режет стену, окно оставляет подоконник. */
  private paintOpenings(grid: SceneSurfaceGrid, elements: readonly SceneElement[]): void {
    for (const element of elements) {
      if (element.kind !== 'opening') {
        continue;
      }

      const window = element.props.kind === 'window';
      const open = element.props.state === 'open';
      // Подоконник хранится в единицах сцены, а полоса обзора — в ипари.
      const sill = window ? Math.max(0, Math.round(element.props.sill ?? 0)) / Math.max(1, grid.resolution) : 0;
      this.paintShape(grid, element.shape, (index) => {
        // Открытое окно — не дверь: створка распахнута, но пролезть в неё
        // нельзя. Ход открывает только дверь, ворота и лестница.
        if (open && !window) {
          grid.speeds[index] = Math.max(grid.speeds[index], SCENE_SURFACE.speeds.grass);
          this.clearOccluder(grid, index);

          return;
        }

        grid.speeds[index] = 0;
        if (window) {
          // Окно: видно выше подоконника, но не пройти. Перемычку держит
          // сетка батлграунда; в предпросмотре полоса одна — подоконник.
          if (sill > 0) {
            this.paintOccluder(grid, index, 0, sill);
          } else {
            this.clearOccluder(grid, index);
          }

          return;
        }

        // Стеклянная дверь: закрыта, но прозрачна. Стена под ней уже вырезана.
        if (element.props.blocksVision === false) {
          this.clearOccluder(grid, index);

          return;
        }

        this.paintOccluder(grid, index, 0, Number.POSITIVE_INFINITY);
      });
    }
  }

  /** Убирает перекрытие обзора из клетки. */
  private clearOccluder(grid: SceneSurfaceGrid, index: number): void {
    grid.sightBottom[index] = Number.POSITIVE_INFINITY;
    grid.sightTop[index] = Number.NEGATIVE_INFINITY;
  }

  /** Заливает клетки, чей центр попал в фигуру; `rotation` — поворот опоры. */
  private paintShape(grid: SceneSurfaceGrid, shape: SceneShape, apply: (index: number) => void, rotation = 0): void {
    const probe = this.geometry.probe(shape, rotation);
    this.forEachCell(grid, probe.bounds, (index, center) => {
      if (probe.contains(center, 0)) {
        apply(index);
      }
    });
  }

  /** Заливает мировую форму: круг или полигон. */
  private paintArea(grid: SceneSurfaceGrid, area: SceneWorldFootprint, apply: (index: number) => void): void {
    if (area.shape === 'circle') {
      this.paintCircle(grid, area.center, area.radius, apply);

      return;
    }

    if (area.points.length < 3) {
      return;
    }

    const bounds = this.geometry.bounds({ type: 'polygon', points: area.points });
    this.forEachCell(grid, bounds, (index, center) => {
      if (this.geometry.pointInPolygon(center, area.points)) {
        apply(index);
      }
    });
  }

  /** Заливает круг в мировых координатах. */
  private paintCircle(
    grid: SceneSurfaceGrid,
    center: ScenePoint,
    radius: number,
    apply: (index: number) => void,
  ): void {
    if (radius <= 0) {
      return;
    }

    const bounds: SceneBounds = {
      x: center[0] - radius,
      y: center[1] - radius,
      width: radius * 2,
      height: radius * 2,
    };
    this.forEachCell(grid, bounds, (index, cell) => {
      if (Math.hypot(cell[0] - center[0], cell[1] - center[1]) <= radius) {
        apply(index);
      }
    });
  }

  /** Обход клеток, пересекающих границы фигуры. */
  private forEachCell(grid: SceneSurfaceGrid, bounds: SceneBounds, visit: CellVisitor): void {
    const minColumn = this.clamp(Math.floor((bounds.x - grid.x) / grid.cell), 0, grid.columns - 1);
    const maxColumn = this.clamp(Math.floor((bounds.x + bounds.width - grid.x) / grid.cell), 0, grid.columns - 1);
    const minRow = this.clamp(Math.floor((bounds.y - grid.y) / grid.cell), 0, grid.rows - 1);
    const maxRow = this.clamp(Math.floor((bounds.y + bounds.height - grid.y) / grid.cell), 0, grid.rows - 1);

    for (let row = minRow; row <= maxRow; row += 1) {
      for (let column = minColumn; column <= maxColumn; column += 1) {
        const index = row * grid.columns + column;
        visit(index, surfaceCenter(grid, index));
      }
    }
  }

  /** Явная скорость элемента или пресет типа. */
  private speedOf(element: SceneElement, preset: number): number {
    const speed = element.props.speed;
    if (typeof speed !== 'number' || !Number.isFinite(speed)) {
      return preset;
    }

    return Math.max(0, Math.min(10000, speed));
  }

  /** Ограничение в диапазоне. */
  private clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
  }
}
