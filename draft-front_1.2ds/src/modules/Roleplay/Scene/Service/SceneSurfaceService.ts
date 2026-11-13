import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import type { SceneSurfaceKind } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceKind';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { SceneGeometryService } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import { isSceneDeck, sceneDeckThickness, sceneSurfaceElevation } from '@/modules/Roleplay/Scene/Service/SceneHeight';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';

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
      x: 0,
      y: 0,
      columns,
      rows,
      kinds: new Array<SceneSurfaceKind>(size).fill('none'),
      speeds: new Array<number>(size).fill(0),
      costs: new Array<number>(size).fill(Number.POSITIVE_INFINITY),
      elevation: new Float32Array(size),
      clearance: new Float32Array(size).fill(Number.POSITIVE_INFINITY),
      sightBottom: new Float32Array(size).fill(Number.POSITIVE_INFINITY),
    };

    const own = elements.filter((element) => element.boardId === board.id);
    this.paintTerrain(grid, own, resolution);
    this.paintBridges(grid, own, resolution);
    this.paintObstacles(grid, own);
    this.paintDecor(grid, own, resolution);
    this.paintOpenings(grid, own);

    grid.costs = grid.speeds.map((speed) => (speed > 0 ? 100 / speed : Number.POSITIVE_INFINITY));

    return grid;
  }

  /** Террейн: вода рисуется последней и перекрывает дорогу. */
  private paintTerrain(grid: SceneSurfaceGrid, elements: readonly SceneElement[], resolution: number): void {
    const terrain = elements
      .filter((element) => element.kind === 'terrain')
      .sort(
        (left, right) =>
          this.terrainRank(left) - this.terrainRank(right) || left.sortOrder - right.sortOrder || left.id - right.id,
      );

    for (const element of terrain) {
      const kind = TERRAIN_SURFACE[element.props.kind ?? ''] ?? null;
      if (kind === null) {
        continue;
      }

      const preset = SCENE_SURFACE.speeds[kind];
      const elevation = sceneSurfaceElevation(element, resolution);
      this.paintShape(grid, element.shape, (index) => {
        grid.kinds[index] = kind;
        grid.speeds[index] = this.speedOf(element, preset);
        grid.elevation[index] = elevation;
      });
    }
  }

  /** Мост и съезд — настил: проходимы и не перекрывают обзор. */
  private paintBridges(grid: SceneSurfaceGrid, elements: readonly SceneElement[], resolution: number): void {
    for (const element of elements) {
      if (!isSceneDeck(element)) {
        continue;
      }

      const preset = SCENE_SURFACE.speeds.bridge;
      const deckElevation = sceneSurfaceElevation(element, resolution);
      const thickness = sceneDeckThickness(element, resolution);
      this.paintShape(
        grid,
        element.shape,
        (index) => {
          // Клетка до настила — это поверхность под ним: вода, берег, дорога
          // или нижний настил. Зазор считается до низа настила и берётся
          // самый узкий, если настилов несколько.
          const gap = deckElevation - thickness - grid.elevation[index];
          grid.clearance[index] = Math.min(grid.clearance[index], gap);
          grid.kinds[index] = 'bridge';
          grid.speeds[index] = this.speedOf(element, preset);
          grid.elevation[index] = deckElevation;
        },
        sceneRotation(element),
      );
    }
  }

  /** Препятствия: ход и обзор по флагам элемента. */
  private paintObstacles(grid: SceneSurfaceGrid, elements: readonly SceneElement[]): void {
    for (const element of elements) {
      if (element.kind !== 'obstacle') {
        continue;
      }

      const blocksMovement = element.props.blocksMovement !== false;
      const blocksVision = element.props.blocksVision !== false;
      this.paintShape(grid, element.shape, (index) => {
        if (blocksMovement) {
          grid.speeds[index] = 0;
        }
        if (blocksVision) {
          grid.sightBottom[index] = 0;
        }
      });
    }
  }

  /**
   * Декор: основание перекрывает ход, силуэт — обзор.
   *
   * У точкового декора размер берётся из `scale` (процент от разрешения):
   * ствол дерева уже кроны, поэтому основание и силуэт считаются разными
   * долями ширины спрайта. `sightBottom` задаёт низ силуэта: у кроны он выше
   * глаз, и луч под ней её игнорирует. Прямоугольный и круглый декор
   * перекрывает свою фигуру целиком.
   */
  private paintDecor(grid: SceneSurfaceGrid, elements: readonly SceneElement[], resolution: number): void {
    const base = Math.max(1, resolution);
    for (const element of elements) {
      if (element.kind !== 'decor') {
        continue;
      }

      if (element.shape.type !== 'point') {
        this.paintShape(grid, element.shape, (index) => {
          grid.speeds[index] = 0;
          grid.sightBottom[index] = 0;
        });
        continue;
      }

      const size = base * (Math.max(1, element.props.scale ?? 100) / 100);
      const center: ScenePoint = [element.shape.x, element.shape.y];
      const baseRadius = size * ((element.props.baseRadius ?? SCENE_SURFACE.decorBaseRadius) / 100);
      const sightRadius = size * ((element.props.sightRadius ?? SCENE_SURFACE.decorSightRadius) / 100);
      // Проценты ширины переводятся в ипари: ширина спрайта — это `scale`
      // процентов от разрешения, а разрешение и есть ипари.
      const sightBottom = (size / base) * ((element.props.sightBottom ?? SCENE_SURFACE.decorSightBottom) / 100);

      this.paintCircle(grid, center, baseRadius, (index) => {
        grid.speeds[index] = 0;
        grid.sightBottom[index] = 0;
      });
      this.paintCircle(grid, center, sightRadius, (index) => {
        grid.sightBottom[index] = Math.min(grid.sightBottom[index], sightBottom);
      });
    }
  }

  /** Проёмы: закрытая дверь непроходима и непрозрачна, открытая прорезает стену. */
  private paintOpenings(grid: SceneSurfaceGrid, elements: readonly SceneElement[]): void {
    for (const element of elements) {
      if (element.kind !== 'opening') {
        continue;
      }

      const open = element.props.state === 'open';
      this.paintShape(grid, element.shape, (index) => {
        if (open) {
          grid.speeds[index] = Math.max(grid.speeds[index], SCENE_SURFACE.speeds.grass);
          grid.sightBottom[index] = Number.POSITIVE_INFINITY;

          return;
        }

        grid.speeds[index] = 0;
        grid.sightBottom[index] = 0;
      });
    }
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

  /** Ранг террейна: вода поверх остальных поверхностей. */
  private terrainRank(element: SceneElement): number {
    return element.props.kind === 'water' ? 1 : 0;
  }

  /** Ограничение в диапазоне. */
  private clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
  }
}
