import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneOverlay } from '@/modules/Roleplay/Scene/Dto/SceneOverlay';
import type {
  SceneOverlayBand,
  SceneOverlayMask,
  SceneOverlayPaint,
} from '@/modules/Roleplay/Scene/Dto/SceneOverlayPaint';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import type { SceneVisibility } from '@/modules/Roleplay/Scene/Dto/SceneVisibility';
import { SCENE_OVERLAY } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OVERLAY';
import type { SceneFloorSightScene } from '@/modules/Roleplay/Scene/Service/SceneFloorSightService';
import { SceneFloorSightService } from '@/modules/Roleplay/Scene/Service/SceneFloorSightService';
import { SceneFloorPlanService } from '@/modules/Roleplay/Scene/Service/SceneFloorPlanService';
import {
  SceneSurfaceService,
  surfaceCenter,
  surfaceFloor,
  surfaceIndex,
} from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import { SceneVisibilityService } from '@/modules/Roleplay/Scene/Service/SceneVisibilityService';
import { sceneBodyHeight, sceneBodyIpari, sceneSightEyeIpari } from '@/modules/Roleplay/Scene/Service/SceneSight';

/** Цвет и прозрачность одной заливки. */
interface OverlayStyle {
  color: number;
  alpha: number;
}

/** Наблюдатель тумана: поле, точка и уровень поверхности под ногами. */
export interface SceneOverlayObserver {
  boardId: number;
  point: ScenePoint;
  /** Уровень поверхности: 0 — пол, больше — настил или крыша. */
  level?: number;
  /** Рост тела в единицах сцены; без него — стандартный. */
  height?: number;
}

/**
 * Оверлеи предпросмотра: стоимость клеток и скрытые области.
 *
 * Считает полосы стоимости и маски тумана, но не рисует. Соседние клетки
 * одного цвета сливаются в полосу, чтобы 160×160 клеток не превращались в
 * десятки тысяч инструкций Graphics; туман отдаётся маской, которую рендерер
 * растягивает с линейной фильтрацией.
 *
 * Туман считается на всех видимых полях, а не только на активном: двор и
 * другие этажи — часть одного мира. Своё поле наблюдателя считает сетка
 * поверхностей, чужие — луч в мире корня через `SceneFloorSightService`:
 * плиты, стены и проёмы перекрывают взгляд так же, как для акторов.
 */
export class SceneOverlayService {
  /** Сетки поверхностей переживают переключение оверлеев и зум. */
  private readonly grids = new Map<number, { key: string; grid: SceneSurfaceGrid }>();

  /** Готовая сцена лучей между этажами; пересобирается при смене данных. */
  private sightKey = '';

  private sight: SceneFloorSightScene | null = null;

  constructor(
    private readonly visibility: SceneVisibilityService = new SceneVisibilityService(),
    private readonly surfaces: SceneSurfaceService = new SceneSurfaceService(),
    private readonly floors: SceneFloorPlanService = new SceneFloorPlanService(),
    private readonly floorSight: SceneFloorSightService = new SceneFloorSightService(),
  ) {}

  /**
   * Картинки оверлеев для всех видимых полей.
   *
   * Стоимость — подсказка активного поля; туман — на каждом видимом этаже.
   * Наблюдатель попадает на своё поле, остальные поля проверяются лучом мира.
   *
   * @param model Модель рендера.
   * @param options Настройки оверлеев.
   * @param observer Точка обзора; без неё — центр активного поля.
   *
   * @returns Набор картинок по полям; пустой, если оверлеи выключены.
   */
  paints(
    model: SceneRenderModel,
    options: SceneOverlay,
    observer: SceneOverlayObserver | null = null,
  ): SceneOverlayPaint[] {
    if (!options.costs && !options.visibility) {
      return [];
    }

    const resolution = Math.max(1, model.resolution);
    const plan = this.floors.plan(model);
    const floors = plan.floors.length > 0 ? plan.floors : [];
    const activeBoard = model.boards.find((board) => board.id === model.activeBoardId) ?? null;
    const activeGrid = activeBoard === null ? null : this.grid(activeBoard, model.elements, resolution);
    const defaultObserver: SceneOverlayObserver | null =
      observer ??
      (activeBoard !== null && activeGrid !== null
        ? { boardId: activeBoard.id, point: this.center(activeGrid), level: 0 }
        : null);
    const radius = options.radius ?? resolution * SCENE_OVERLAY.sightIpari;
    const paints: SceneOverlayPaint[] = [];

    // Сетка наблюдателя нужна чужим полям для высоты его пола. Собираем её
    // заранее: поля идут снизу вверх, и наблюдатель может оказаться выше.
    if (defaultObserver !== null) {
      const observerBoard = model.boards.find((board) => board.id === defaultObserver.boardId);
      if (observerBoard !== undefined) {
        this.grid(observerBoard, model.elements, resolution);
      }
    }

    for (const floor of floors) {
      const board = floor.board;
      const grid = this.grid(board, model.elements, resolution);
      const costs = activeBoard?.id === board.id && options.costs ? this.costBands(grid) : [];
      let fog: SceneOverlayMask | null = null;
      if (options.visibility) {
        const visibility = this.visibilityOf(model, board, grid, defaultObserver, radius);
        const scale = grid.columns * grid.rows <= SCENE_OVERLAY.fogFineCells ? SCENE_OVERLAY.fogScale : 1;
        fog = this.hiddenMask(grid, visibility, scale);
      }

      if (costs.length > 0 || fog !== null) {
        paints.push({ boardId: board.id, costs, fog });
      }
    }

    return paints;
  }

  /**
   * Маска видимости поля.
   *
   * Своё поле наблюдателя считает `SceneVisibilityService` по клеткам
   * поверхностей. Чужие — межэтажный луч: клетка видна, если видна точка её
   * поверхности сквозь проёмы и мимо плит.
   *
   * @param model Модель рендера.
   * @param board Поле, для которого строится маска.
   * @param grid Сетка поверхностей поля.
   * @param observer Наблюдатель.
   * @param radius Радиус обзора в координатах поля наблюдателя.
   *
   * @returns Карта видимости клеток поля.
   */
  private visibilityOf(
    model: SceneRenderModel,
    board: SceneBoard,
    grid: SceneSurfaceGrid,
    observer: SceneOverlayObserver | null,
    radius: number,
  ): SceneVisibility {
    const visible = new Uint8Array(grid.columns * grid.rows);
    if (observer === null) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    const resolution = Math.max(1, model.resolution);
    const body = sceneBodyIpari(sceneBodyHeight(resolution), resolution);
    if (observer.boardId === board.id) {
      return this.visibility.map(
        grid,
        observer.point,
        radius,
        sceneSightEyeIpari(observer.height ?? sceneBodyHeight(resolution), resolution),
        body,
        observer.level ?? 0,
      );
    }

    const scene = this.sightScene(model);
    const from = scene.levels.get(observer.boardId);
    const to = scene.levels.get(board.id);
    const observerGrid = this.gridOf(observer.boardId);
    if (from === undefined || to === undefined || observerGrid === null) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    const fromSurface = surfaceFloor(observerGrid, observer.point, observer.level ?? 0);
    const fromRoot = this.floors.toRoot(from.transform, observer.point);
    const radiusWorld = radius * Math.max(Math.abs(from.transform.scaleX), Math.abs(from.transform.scaleY));
    const radiusSquared = radiusWorld * radiusWorld;
    const observerPoint = observer.point;
    const observerHeight = observer.height ?? sceneBodyHeight(resolution);
    const onRoof = this.observerOnRoof(observer);
    const stride = Math.max(1, SCENE_OVERLAY.fogStride);
    const half = Math.floor((stride - 1) / 2);

    // Чужие поля проверяются блоками: межэтажный луч дороже, а на дворе и
    // соседнем этаже шаг в две клетки не читается — край сглаживает маска.
    for (let row = 0; row < grid.rows; row += stride) {
      for (let column = 0; column < grid.columns; column += stride) {
        const sampleColumn = Math.min(grid.columns - 1, column + half);
        const sampleRow = Math.min(grid.rows - 1, row + half);
        const index = sampleRow * grid.columns + sampleColumn;
        if (grid.kinds[index] === 'none') {
          continue;
        }

        const center = surfaceCenter(grid, index);
        const root = this.floors.toRoot(to.transform, center);
        const dx = root[0] - fromRoot[0];
        const dy = root[1] - fromRoot[1];
        if (dx * dx + dy * dy > radiusSquared) {
          continue;
        }

        const seen = this.floorSight.seesSurface(
          scene,
          {
            boardId: observer.boardId,
            point: observerPoint,
            height: observerHeight,
            surface: fromSurface,
          },
          {
            boardId: board.id,
            point: center,
            height: sceneBodyHeight(resolution),
            surface: grid.elevation[index],
          },
          { slabs: !onRoof, onRoof },
        );
        if (!seen) {
          continue;
        }

        const maxRow = Math.min(grid.rows, row + stride);
        const maxColumn = Math.min(grid.columns, column + stride);
        for (let nextRow = row; nextRow < maxRow; nextRow += 1) {
          for (let nextColumn = column; nextColumn < maxColumn; nextColumn += 1) {
            visible[nextRow * grid.columns + nextColumn] = 1;
          }
        }
      }
    }

    return { columns: grid.columns, rows: grid.rows, visible };
  }

  /**
   * Собирает заливки по данным поля; сетка поверхностей кэшируется.
   *
   * @deprecated Оставлено для одного поля; мир считает `paints`.
   */
  paint(
    board: SceneBoard,
    elements: readonly SceneElement[],
    resolution: number,
    options: SceneOverlay,
    observer: ScenePoint | null,
    level = 0,
    height?: number,
  ): SceneOverlayPaint {
    return this.build(board.id, this.grid(board, elements, resolution), options, resolution, observer, level, height);
  }

  /** Собирает заливки для одного поля. */
  build(
    boardId: number,
    grid: SceneSurfaceGrid,
    options: SceneOverlay,
    resolution: number,
    observer: ScenePoint | null,
    level = 0,
    height?: number,
  ): SceneOverlayPaint {
    const costs = options.costs ? this.costBands(grid) : [];
    let fog: SceneOverlayMask | null = null;
    if (options.visibility) {
      const from = observer ?? this.center(grid);
      const radius = options.radius ?? Math.max(1, resolution) * SCENE_OVERLAY.sightIpari;
      const eye = sceneSightEyeIpari(height ?? sceneBodyHeight(resolution), resolution);
      const body = sceneBodyIpari(sceneBodyHeight(resolution), resolution);
      fog = this.hiddenMask(grid, this.visibility.map(grid, from, radius, eye, body, level));
    }

    return { boardId, costs, fog };
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
   * Маска скрытых клеток с мягким краем.
   *
   * Видимость считается по клеткам поверхности, но маска рисуется мельче
   * (`fogScale` текселей на клетку), а граница скрытой области размывается на
   * `fogBlurIpari`: видимая клетка остаётся прозрачной, туман набирает
   * плотность за несколько текселей. Так на косой тени нет ступенек в целую
   * клетку, а край обзора читается как мягкий свет, а не как три кольца.
   */
  hiddenMask(
    grid: SceneSurfaceGrid,
    visibility: SceneVisibility,
    scale: number = SCENE_OVERLAY.fogScale,
  ): SceneOverlayMask {
    const factor = Math.max(1, Math.floor(scale));
    const columns = grid.columns * factor;
    const rows = grid.rows * factor;
    const cell = grid.cell / factor;
    const size = columns * rows;
    const values = new Float32Array(size);
    const baseOf = (column: number, row: number): number =>
      Math.min(grid.rows - 1, Math.floor(row / factor)) * grid.columns +
      Math.min(grid.columns - 1, Math.floor(column / factor));

    for (let row = 0; row < rows; row += 1) {
      for (let column = 0; column < columns; column += 1) {
        const base = baseOf(column, row);
        // За пределами поверхностей прятать нечего: поля этажа окружены
        // полями обзора, и туман на них закрыл бы поляну родителя.
        if (grid.kinds[base] === 'none' || visibility.visible[base] === 1) {
          continue;
        }
        values[row * columns + column] = 1;
      }
    }

    const radius = Math.max(0, Math.round((SCENE_OVERLAY.fogBlurIpari * grid.resolution) / cell));
    const blurred = radius > 0 ? this.blur(values, columns, rows, radius) : values;
    const alpha = new Uint8Array(size);
    const full = Math.round(SCENE_OVERLAY.hidden.alpha * 255);
    for (let row = 0; row < rows; row += 1) {
      for (let column = 0; column < columns; column += 1) {
        const index = row * columns + column;
        const base = baseOf(column, row);
        if (grid.kinds[base] === 'none' || visibility.visible[base] === 1) {
          continue;
        }
        alpha[index] = Math.round(this.smoothstep(blurred[index]) * full);
      }
    }

    return {
      x: grid.x,
      y: grid.y,
      cell,
      columns,
      rows,
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
            element.props.depth ?? '',
            element.props.shoreWidth ?? '',
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
            element.props.sill ?? '',
            JSON.stringify(element.shape),
          ].join(':'),
        ),
    ].join('|');
    const cached = this.grids.get(board.id);
    if (cached === undefined || cached.key !== key) {
      const built = this.surfaces.build(board, elements, resolution);
      this.grids.set(board.id, { key, grid: built });

      return built;
    }

    return cached.grid;
  }

  /** Уже собранная сетка поля или null. */
  private gridOf(boardId: number): SceneSurfaceGrid | null {
    return this.grids.get(boardId)?.grid ?? null;
  }

  /**
   * Наблюдатель стоит на проходимой крыше.
   *
   * С крыши смотрят поверх стен своего этажа, и плита под ногами не
   * перекрывает двор: она ниже наблюдателя, а не между ним и целью.
   */
  private observerOnRoof(observer: SceneOverlayObserver): boolean {
    if ((observer.level ?? 0) <= 0) {
      return false;
    }

    const grid = this.gridOf(observer.boardId);
    if (grid === null) {
      return false;
    }

    const index = surfaceIndex(grid, observer.point[0], observer.point[1]);

    return index >= 0 && grid.kinds[index] === 'roof';
  }

  /**
   * Сцена лучей между этажами с кэшем по геометрии.
   *
   * Тяжёлая часть — обход террейна, стен и проёмов; при движении актора
   * геометрия не меняется, поэтому пересобираем только по смене данных.
   */
  private sightScene(model: SceneRenderModel): SceneFloorSightScene {
    const key = [
      model.resolution,
      model.boards.map((board) => `${board.id}:${board.boundWidth}:${board.boundHeight}`).join(','),
      model.enclosures
        .map((item) => `${item.parentBoardId}:${item.childBoardId}:${item.elevation}:${JSON.stringify(item.footprint)}`)
        .join(','),
      model.elements
        .filter((element) => element.kind === 'terrain' || element.kind === 'obstacle' || element.kind === 'opening')
        .map((element) =>
          [
            element.boardId,
            element.id,
            element.kind,
            element.targetBoardId ?? '',
            element.props.kind ?? '',
            element.props.blocksVision ?? '',
            JSON.stringify(element.shape),
          ].join(':'),
        )
        .join(','),
    ].join('|');

    if (this.sight === null || this.sightKey !== key) {
      this.sight = this.floorSight.scene(model);
      this.sightKey = key;
    }

    return this.sight;
  }

  /** Стиль клетки по поверхности и проходимости. */
  private costStyle(grid: SceneSurfaceGrid, index: number): OverlayStyle {
    if (grid.speeds[index] <= 0) {
      return SCENE_OVERLAY.impassable;
    }

    return SCENE_OVERLAY.cost[grid.kinds[index]];
  }

  /** Сглаживание края: ноль у видимой клетки, единица — в глубине тумана. */
  private smoothstep(value: number): number {
    const t = Math.max(0, Math.min(1, value));

    return t * t * (3 - 2 * t);
  }

  /**
   * Двухпроходное размытие битовой маски.
   *
   * Окно с повторением краёв: на границе поля значение не проваливается в
   * ноль, иначе туман темнел бы у кромки плиты сильнее, чем в середине.
   */
  private blur(values: Float32Array, columns: number, rows: number, radius: number): Float32Array {
    const window = radius * 2 + 1;
    const clamp = (value: number, max: number): number => Math.max(0, Math.min(max, value));
    const horizontal = new Float32Array(values.length);
    for (let row = 0; row < rows; row += 1) {
      const offset = row * columns;
      let sum = 0;
      for (let step = -radius; step <= radius; step += 1) {
        sum += values[offset + clamp(step, columns - 1)];
      }
      for (let column = 0; column < columns; column += 1) {
        horizontal[offset + column] = sum / window;
        sum +=
          values[offset + clamp(column + radius + 1, columns - 1)] -
          values[offset + clamp(column - radius, columns - 1)];
      }
    }

    const result = new Float32Array(values.length);
    for (let column = 0; column < columns; column += 1) {
      let sum = 0;
      for (let step = -radius; step <= radius; step += 1) {
        sum += horizontal[clamp(step, rows - 1) * columns + column];
      }
      for (let row = 0; row < rows; row += 1) {
        result[row * columns + column] = sum / window;
        sum +=
          horizontal[clamp(row + radius + 1, rows - 1) * columns + column] -
          horizontal[clamp(row - radius, rows - 1) * columns + column];
      }
    }

    return result;
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
