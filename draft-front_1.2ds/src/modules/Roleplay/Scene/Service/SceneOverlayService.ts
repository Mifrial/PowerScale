import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneOverlay } from '@/modules/Roleplay/Scene/Dto/SceneOverlay';
import type {
  SceneOverlayBand,
  SceneOverlayMask,
  SceneOverlayPaint,
} from '@/modules/Roleplay/Scene/Dto/SceneOverlayPaint';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import type { SceneVisibility } from '@/modules/Roleplay/Scene/Dto/SceneVisibility';
import { SCENE_OVERLAY } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OVERLAY';
import { SceneSurfaceService } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import { SceneVisibilityService } from '@/modules/Roleplay/Scene/Service/SceneVisibilityService';

/** Цвет и прозрачность одной заливки. */
interface OverlayStyle {
  color: number;
  alpha: number;
}

/**
 * Оверлеи предпросмотра: стоимость клеток и скрытые области.
 *
 * Считает полосы стоимости и маску тумана, но не рисует. Соседние клетки
 * одного цвета сливаются в полосу, чтобы 160×160 клеток не превращались в
 * десятки тысяч инструкций Graphics; туман отдаётся маской, которую рендерер
 * растягивает с линейной фильтрацией.
 */
export class SceneOverlayService {
  /** Сетка поверхностей переживает переключение оверлеев и зум. */
  private surfaceKey = '';

  private surfaceGrid: SceneSurfaceGrid | null = null;

  constructor(
    private readonly visibility: SceneVisibilityService = new SceneVisibilityService(),
    private readonly surfaces: SceneSurfaceService = new SceneSurfaceService(),
  ) {}

  /** Собирает заливки по данным поля; сетка поверхностей кэшируется. */
  paint(
    board: SceneBoard,
    elements: readonly SceneElement[],
    resolution: number,
    options: SceneOverlay,
    observer: ScenePoint | null,
    level = 0,
  ): SceneOverlayPaint {
    return this.build(this.grid(board, elements, resolution), options, resolution, observer, level);
  }

  /** Собирает заливки для включённых оверлеев. */
  build(
    grid: SceneSurfaceGrid,
    options: SceneOverlay,
    resolution: number,
    observer: ScenePoint | null,
    level = 0,
  ): SceneOverlayPaint {
    const costs = options.costs ? this.costBands(grid) : [];
    let fog: SceneOverlayMask | null = null;
    if (options.visibility) {
      const from = observer ?? this.center(grid);
      const radius = options.radius ?? Math.max(1, resolution) * SCENE_OVERLAY.sightIpari;
      fog = this.hiddenMask(grid, this.visibility.map(grid, from, radius, undefined, undefined, level));
    }

    return { costs, fog };
  }

  /** Полосы стоимости: непроходимое красное, остальное — по поверхности. */
  costBands(grid: SceneSurfaceGrid): SceneOverlayBand[] {
    const bands: SceneOverlayBand[] = [];
    for (let row = 0; row < grid.rows; row += 1) {
      const offset = row * grid.columns;
      let start = 0;
      let style: OverlayStyle | null = this.costStyle(grid, offset);
      for (let column = 1; column < grid.columns; column += 1) {
        const next = this.costStyle(grid, offset + column);
        if (next !== style) {
          if (style !== null) {
            bands.push(this.band(grid, row, start, column, style));
          }
          start = column;
          style = next;
        }
      }
      if (style !== null) {
        bands.push(this.band(grid, row, start, grid.columns, style));
      }
    }

    return bands;
  }

  /**
   * Маска скрытых клеток; у границы обзора прозрачность ниже.
   *
   * Один тексель на клетку: рендерер растягивает её с линейной фильтрацией,
   * поэтому на экране нет ступенек, а кольца мягкого края остаются данными.
   */
  hiddenMask(grid: SceneSurfaceGrid, visibility: SceneVisibility): SceneOverlayMask {
    const distance = this.edgeDistance(grid, visibility);
    const size = grid.columns * grid.rows;
    const alpha = new Uint8Array(size);
    for (let index = 0; index < size; index += 1) {
      alpha[index] = this.hiddenAlpha(visibility, distance, index);
    }

    return {
      x: grid.x,
      y: grid.y,
      cell: grid.cell,
      columns: grid.columns,
      rows: grid.rows,
      alpha,
      color: SCENE_OVERLAY.hidden.color,
    };
  }

  /** Сетка поверхностей с кэшем по полю, разрешению и форме элементов. */
  private grid(board: SceneBoard, elements: readonly SceneElement[], resolution: number): SceneSurfaceGrid {
    const key = [
      board.id,
      board.boundWidth,
      board.boundHeight,
      board.occupancyCell,
      resolution,
      ...elements
        .filter((element) => element.boardId === board.id)
        .map((element) =>
          [
            element.id,
            element.kind,
            element.sortOrder,
            element.props.kind ?? '',
            element.props.speed ?? '',
            element.props.height ?? '',
            element.props.elevation ?? '',
            element.props.rotation ?? '',
            element.props.scale ?? '',
            element.props.baseRadius ?? '',
            element.props.sightRadius ?? '',
            element.props.sightBottom ?? '',
            JSON.stringify(element.props.footprint ?? null),
            JSON.stringify(element.props.silhouette ?? null),
            element.props.blocksMovement ?? '',
            element.props.blocksVision ?? '',
            element.props.state ?? '',
            JSON.stringify(element.shape),
          ].join(':'),
        ),
    ].join('|');
    if (this.surfaceGrid === null || this.surfaceKey !== key) {
      this.surfaceGrid = this.surfaces.build(board, elements, resolution);
      this.surfaceKey = key;
    }

    return this.surfaceGrid;
  }

  /** Стиль клетки по поверхности и проходимости. */
  private costStyle(grid: SceneSurfaceGrid, index: number): OverlayStyle {
    if (grid.speeds[index] <= 0) {
      return SCENE_OVERLAY.impassable;
    }

    return SCENE_OVERLAY.cost[grid.kinds[index]];
  }

  /** Альфа скрытой клетки: два кольца мягкого края, дальше — плотный туман. */
  private hiddenAlpha(visibility: SceneVisibility, distance: Int16Array, index: number): number {
    if (visibility.visible[index] === 1) {
      return 0;
    }

    const depth = distance[index];
    if (depth === 1) {
      return Math.round(SCENE_OVERLAY.hiddenEdge.alpha * 255);
    }
    if (depth === 2) {
      return Math.round(SCENE_OVERLAY.hiddenMid.alpha * 255);
    }

    return Math.round(SCENE_OVERLAY.hidden.alpha * 255);
  }

  /**
   * Расстояние от скрытой клетки до видимой области, до двух колец.
   *
   * Многоисточниковый BFS от всех видимых клеток: он даёт мягкую кромку без
   * рисования градиента и без проверки соседей на каждой клетке вручную.
   */
  private edgeDistance(grid: SceneSurfaceGrid, visibility: SceneVisibility): Int16Array {
    const size = grid.columns * grid.rows;
    const distance = new Int16Array(size).fill(-1);
    const queue: number[] = [];
    for (let index = 0; index < size; index += 1) {
      if (visibility.visible[index] === 1) {
        distance[index] = 0;
        queue.push(index);
      }
    }

    for (let head = 0; head < queue.length; head += 1) {
      const index = queue[head];
      const depth = distance[index];
      if (depth >= 2) {
        continue;
      }

      const column = index % grid.columns;
      const row = Math.floor(index / grid.columns);
      const neighbors = [
        column > 0 ? index - 1 : -1,
        column < grid.columns - 1 ? index + 1 : -1,
        row > 0 ? index - grid.columns : -1,
        row < grid.rows - 1 ? index + grid.columns : -1,
      ];
      for (const neighbor of neighbors) {
        if (neighbor < 0 || distance[neighbor] !== -1) {
          continue;
        }

        distance[neighbor] = depth + 1;
        queue.push(neighbor);
      }
    }

    return distance;
  }

  /** Полоса из соседних клеток одной строки. */
  private band(
    grid: SceneSurfaceGrid,
    row: number,
    fromColumn: number,
    toColumn: number,
    style: OverlayStyle,
  ): SceneOverlayBand {
    return {
      x: grid.x + fromColumn * grid.cell,
      y: grid.y + row * grid.cell,
      width: (toColumn - fromColumn) * grid.cell,
      height: grid.cell,
      color: style.color,
      alpha: style.alpha,
    };
  }

  /** Центр поля, если точка обзора не задана. */
  private center(grid: SceneSurfaceGrid): ScenePoint {
    return [grid.x + (grid.columns * grid.cell) / 2, grid.y + (grid.rows * grid.cell) / 2];
  }
}
