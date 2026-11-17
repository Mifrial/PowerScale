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
import type { SceneFloor, SceneFloorPlan } from '@/modules/Roleplay/Scene/Service/SceneFloorPlanService';
import { SceneFloorPlanService } from '@/modules/Roleplay/Scene/Service/SceneFloorPlanService';
import type { SceneSightQuery } from '@/modules/Roleplay/Scene/Service/SceneSightQuery';
import { SceneSurfaceService, surfaceFloor, surfaceIndex } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import { SceneVisibilityService } from '@/modules/Roleplay/Scene/Service/SceneVisibilityService';
import { sceneBodyHeight, sceneBodyIpari, sceneSightEyeIpari } from '@/modules/Roleplay/Scene/Service/SceneSight';

/** Цвет и прозрачность одной заливки. */
interface OverlayStyle {
  color: number;
  alpha: number;
}

/** Граница обзора в координатах поля: круг, а при масштабе — эллипс. */
export interface SceneOverlayEdge {
  /** Центр обзора в координатах поля. */
  center: ScenePoint;
  /** Полуось радиуса по X в координатах поля. */
  radiusX: number;
  /** Полуось радиуса по Y в координатах поля. */
  radiusY: number;
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

/** Готовая маска для рендерера: альфа и её размеры. */
interface OverlayMaskScratch {
  blocked: Uint8Array;
  distance: Float32Array;
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
 * поверхностей, чужие — запечённые растры через `SceneSightQuery`: стены
 * читаются обходом клеток на пути луча, плиты — аффинной пробой точки
 * пересечения. Поэтому цена тика не зависит от числа домов в селе: считаются
 * только поля, чей габарит попал в радиус, и только клетки этого радиуса.
 */
export class SceneOverlayService {
  /** Сетки поверхностей переживают переключение оверлеев и зум. */
  private readonly grids = new Map<number, { key: string; grid: SceneSurfaceGrid }>();

  /** Подписи элементов по полям; пересобираются только при новых данных. */
  private elementsRef: readonly SceneElement[] | null = null;

  private readonly elementKeys = new Map<number, string>();

  /** План этажей переживает движение актора: он зависит только от данных. */
  private planKey: readonly unknown[] = [];

  private planCache: SceneFloorPlan | null = null;

  /** Готовая сцена лучей между этажами; пересобирается при смене данных. */
  private sightKey = '';

  /** Ссылки данных, по которым собрана сцена: движение актора её не трогает. */
  private sightRefs: readonly unknown[] = [];

  private sight: SceneFloorSightScene | null = null;

  /** Полосы стоимости: меняются только с данными поля, не с туманом. */
  private readonly costsCache = new Map<number, { grid: SceneSurfaceGrid; bands: SceneOverlayBand[] }>();

  /** Буферы размытия по размеру маски: не аллоцируем их каждый тик. */
  private readonly scratch = new Map<string, OverlayMaskScratch>();

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
   * Наблюдатель попадает на своё поле, остальные поля проверяются общим
   * запросом видимости.
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
    const plan = this.plan(model);
    const activeBoard = model.boards.find((board) => board.id === model.activeBoardId) ?? null;
    const activeGrid = activeBoard === null ? null : this.grid(activeBoard, model.elements, resolution);
    const defaultObserver: SceneOverlayObserver | null =
      observer ??
      (activeBoard !== null && activeGrid !== null
        ? { boardId: activeBoard.id, point: this.center(activeGrid), level: 0 }
        : null);
    const radius = options.radius ?? resolution * SCENE_OVERLAY.sightIpari;
    const paints: SceneOverlayPaint[] = [];
    const scene = options.visibility && defaultObserver !== null ? this.sightScene(model) : null;
    let query: SceneSightQuery | null = null;

    for (const floor of plan.floors) {
      const board = floor.board;
      const grid = this.grid(board, model.elements, resolution);
      const costs = activeBoard?.id === board.id ? this.costsFor(board.id, grid, options.costs) : [];
      let fog: SceneOverlayMask | null = null;
      if (options.visibility) {
        if (defaultObserver !== null && defaultObserver.boardId !== board.id && query === null && scene !== null) {
          query = this.queryOf(scene, defaultObserver);
        }

        const visibility = this.visibilityOf(model, board, grid, defaultObserver, radius, query);
        const scale = grid.columns * grid.rows <= SCENE_OVERLAY.fogFineCells ? SCENE_OVERLAY.fogScale : 1;
        fog = this.hiddenMask(grid, visibility, {
          scale,
          edge: this.edgeOf(floor, plan, defaultObserver, radius),
        });
      }

      if (costs.length > 0 || fog !== null) {
        paints.push({ boardId: board.id, costs, fog });
      }
    }

    return paints;
  }

  /** Общий запрос видимости чужих полей на текущий тик. */
  private queryOf(scene: SceneFloorSightScene, observer: SceneOverlayObserver): SceneSightQuery {
    const level = this.gridOf(observer.boardId);
    const surface = level === null ? 0 : surfaceFloor(level, observer.point, observer.level ?? 0);
    const onRoof = this.observerOnRoof(observer);

    return this.floorSight.query(
      scene,
      {
        boardId: observer.boardId,
        point: observer.point,
        height: observer.height,
        surface,
      },
      { slabs: !onRoof, onRoof },
    );
  }

  /**
   * Граница обзора в координатах поля.
   *
   * Своё поле — круг радиуса, чужие — эллипс: поле вложено с масштабом, и
   * радиус мира в его координатах сжимается по осям.
   */
  private edgeOf(
    floor: SceneFloor,
    plan: SceneFloorPlan,
    observer: SceneOverlayObserver | null,
    radius: number,
  ): SceneOverlayEdge | null {
    if (observer === null) {
      return null;
    }

    if (observer.boardId === floor.boardId) {
      return { center: observer.point, radiusX: radius, radiusY: radius };
    }

    const source = plan.floors.find((item) => item.boardId === observer.boardId);
    if (source === undefined) {
      return null;
    }

    const root = this.floors.toRoot(source.transform, observer.point);
    const local = this.floors.toLocal(floor.transform, root);
    const radiusWorld = radius * Math.max(Math.abs(source.transform.scaleX), Math.abs(source.transform.scaleY));

    return {
      center: local,
      radiusX: radiusWorld / Math.max(1e-6, Math.abs(floor.transform.scaleX)),
      radiusY: radiusWorld / Math.max(1e-6, Math.abs(floor.transform.scaleY)),
    };
  }

  /**
   * Маска видимости поля.
   *
   * Своё поле наблюдателя считает `SceneVisibilityService` по клеткам
   * поверхностей. Чужие — общий запрос: тень стен этажа наблюдателя и плиты.
   *
   * @param model Модель рендера.
   * @param board Поле, для которого строится маска.
   * @param grid Сетка поверхностей поля.
   * @param observer Наблюдатель.
   * @param radius Радиус обзора в координатах поля наблюдателя.
   * @param query Запрос видимости чужих полей.
   *
   * @returns Карта видимости клеток поля.
   */
  private visibilityOf(
    model: SceneRenderModel,
    board: SceneBoard,
    grid: SceneSurfaceGrid,
    observer: SceneOverlayObserver | null,
    radius: number,
    query: SceneSightQuery | null,
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

    if (query === null) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    const scene = this.sightScene(model);
    const from = scene.levels.get(observer.boardId);
    const to = scene.levels.get(board.id);
    if (from === undefined || to === undefined) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    const bodyHeight = sceneBodyHeight(resolution);
    const range = this.elevationRange(grid);
    const target = query.prepare(board.id, range.min, range.max, bodyHeight);
    if (target === null) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    const fromRoot = this.floors.toRoot(from.transform, observer.point);
    const radiusWorld = radius * Math.max(Math.abs(from.transform.scaleX), Math.abs(from.transform.scaleY));
    const radiusSquared = radiusWorld * radiusWorld;
    const localCenter = this.floors.toLocal(to.transform, fromRoot);
    const cellWorldX = grid.cell * Math.abs(to.transform.scaleX);
    const cellWorldY = grid.cell * Math.abs(to.transform.scaleY);
    if (cellWorldX <= 0 || cellWorldY <= 0) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    // Считаем только габарит круга в координатах поля: остальное заведомо
    // скрыто, и перебирать его незачем.
    const halfColumns = Math.ceil(radiusWorld / cellWorldX);
    const halfRows = Math.ceil(radiusWorld / cellWorldY);
    const centerColumn = (localCenter[0] - grid.x) / grid.cell;
    const centerRow = (localCenter[1] - grid.y) / grid.cell;
    const minColumn = Math.max(0, Math.floor(centerColumn - halfColumns));
    const maxColumn = Math.min(grid.columns - 1, Math.ceil(centerColumn + halfColumns));
    const minRow = Math.max(0, Math.floor(centerRow - halfRows));
    const maxRow = Math.min(grid.rows - 1, Math.ceil(centerRow + halfRows));

    for (let row = minRow; row <= maxRow; row += 1) {
      const localY = grid.y + (row + 0.5) * grid.cell;
      const rootY = to.transform.y + localY * to.transform.scaleY;
      const dy = rootY - fromRoot[1];
      for (let column = minColumn; column <= maxColumn; column += 1) {
        const index = row * grid.columns + column;
        if (grid.kinds[index] === 'none') {
          continue;
        }

        const localX = grid.x + (column + 0.5) * grid.cell;
        const rootX = to.transform.x + localX * to.transform.scaleX;
        const dx = rootX - fromRoot[0];
        if (dx * dx + dy * dy > radiusSquared) {
          continue;
        }

        if (query.sees(target, localX, localY, grid.elevation[index], bodyHeight)) {
          visible[index] = 1;
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
      fog = this.hiddenMask(grid, this.visibility.map(grid, from, radius, eye, body, level), {
        edge: { center: from, radiusX: radius, radiusY: radius },
      });
    }

    return { boardId, costs, fog };
  }

  /** Полосы стоимости активного поля; пересчёт только при смене данных. */
  private costsFor(boardId: number, grid: SceneSurfaceGrid, enabled: boolean): SceneOverlayBand[] {
    if (!enabled) {
      return [];
    }

    const cached = this.costsCache.get(boardId);
    if (cached !== undefined && cached.grid === grid) {
      return cached.bands;
    }

    const bands = this.costBands(grid);
    this.costsCache.set(boardId, { grid, bands });

    return bands;
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
   * Видимость считается по клеткам поверхности, а маска может рисоваться
   * мельче (`scale`). Вместо размытия битовой маски считается расстояние до
   * видимой области: альфа растёт от нуля у кромки до полной в глубине тумана.
   * Поэтому контур сглажен по расстоянию, а не «террасами» усреднения, и у
   * косой тени нет ступенек в целую клетку.
   *
   * Аналитический остаток радиуса (`edge`) делает границу обзора ровным
   * кругом: без него кромка всё равно повторяла бы клетки.
   *
   * @param grid Сетка поверхностей поля.
   * @param visibility Карта видимости.
   * @param options Масштаб маски и граница обзора.
   *
   * @returns Маска тумана.
   */
  hiddenMask(
    grid: SceneSurfaceGrid,
    visibility: SceneVisibility,
    options: { scale?: number; edge?: SceneOverlayEdge | null } = {},
  ): SceneOverlayMask {
    const factor = Math.max(1, Math.floor(options.scale ?? SCENE_OVERLAY.fogScale));
    const columns = grid.columns * factor;
    const rows = grid.rows * factor;
    const cell = grid.cell / factor;
    const size = columns * rows;
    const alpha = new Uint8Array(size);
    const full = Math.round(SCENE_OVERLAY.hidden.alpha * 255);
    const blur = Math.max(1e-3, SCENE_OVERLAY.fogBlurIpari * grid.resolution);
    const scratch = this.scratchFor(columns, rows);
    const blocked = scratch.blocked;
    blocked.fill(0);

    // Кромка ищется только там, где есть видимые клетки: остальное — полный
    // туман. Для корня 5120² это в разы меньше работы, чем разметка всего поля.
    let minColumn = columns;
    let maxColumn = -1;
    let minRow = rows;
    let maxRow = -1;
    const baseColumn = (column: number): number => Math.min(grid.columns - 1, Math.floor(column / factor));
    const baseRow = (row: number): number => Math.min(grid.rows - 1, Math.floor(row / factor));

    for (let row = 0; row < rows; row += 1) {
      const baseLine = baseRow(row) * grid.columns;
      for (let column = 0; column < columns; column += 1) {
        const base = baseLine + baseColumn(column);
        if (grid.kinds[base] === 'none') {
          continue;
        }

        const index = row * columns + column;
        if (visibility.visible[base] === 1) {
          if (column < minColumn) minColumn = column;
          if (column > maxColumn) maxColumn = column;
          if (row < minRow) minRow = row;
          if (row > maxRow) maxRow = row;
          continue;
        }

        blocked[index] = 1;
        alpha[index] = full;
      }
    }

    const mask: SceneOverlayMask = {
      x: grid.x,
      y: grid.y,
      cell,
      columns,
      rows,
      alpha,
      color: SCENE_OVERLAY.hidden.color,
    };
    if (maxColumn < 0) {
      return mask;
    }

    const margin = Math.ceil(blur / cell) + 1;
    const left = Math.max(0, minColumn - margin);
    const right = Math.min(columns - 1, maxColumn + margin);
    const top = Math.max(0, minRow - margin);
    const bottom = Math.min(rows - 1, maxRow + margin);
    const distance = this.distanceField(blocked, columns, cell, scratch.distance, left, top, right, bottom);
    const edge = options.edge ?? null;

    for (let row = top; row <= bottom; row += 1) {
      const centerY = grid.y + (row + 0.5) * cell;
      for (let column = left; column <= right; column += 1) {
        const index = row * columns + column;
        if (blocked[index] === 0) {
          continue;
        }

        let clearance = distance[index];
        if (edge !== null) {
          const centerX = grid.x + (column + 0.5) * cell;
          const dx = (centerX - edge.center[0]) / Math.max(1e-6, edge.radiusX);
          const dy = (centerY - edge.center[1]) / Math.max(1e-6, edge.radiusY);
          // За радиусом кромку задаёт сам круг: расстояние до него точное, а не
          // клеточное. Внутри радиуса кромка — только граница тени.
          const outside = (Math.sqrt(dx * dx + dy * dy) - 1) * Math.min(edge.radiusX, edge.radiusY);
          if (outside > 0) {
            clearance = Math.min(clearance, outside);
          }
        }

        alpha[index] = Math.round(this.smoothstep(clearance / blur) * full);
      }
    }

    return mask;
  }

  /** Буферы масок по размеру: их размеры повторяются от тика к тику. */
  /** Буферы масок по размеру: их размеры повторяются от тика к тику. */
  private scratchFor(columns: number, rows: number): OverlayMaskScratch {
    const key = `${columns}:${rows}`;
    const cached = this.scratch.get(key);
    if (cached !== undefined) {
      return cached;
    }

    const size = columns * rows;
    const created: OverlayMaskScratch = {
      blocked: new Uint8Array(size),
      distance: new Float32Array(size),
    };
    this.scratch.set(key, created);

    return created;
  }

  /** Сглаживание края: ноль у видимой клетки, единица — в глубине тумана. */
  private smoothstep(value: number): number {
    const t = Math.max(0, Math.min(1, value));

    return t * t * (3 - 2 * t);
  }

  /**
   * Расстояние до видимой области, единицы сцены.
   *
   * Два прохода чамфера: вперёд и назад по строке и столбцу, с диагоналями
   * √2. Точность ~2% длины — на глаз неотличима от евклидовой, а стоит O(N).
   */
  private distanceField(
    blocked: Uint8Array,
    columns: number,
    cell: number,
    target: Float32Array,
    left: number,
    top: number,
    right: number,
    bottom: number,
  ): Float32Array {
    const far = Number.MAX_VALUE;
    for (let row = top; row <= bottom; row += 1) {
      const line = row * columns;
      for (let column = left; column <= right; column += 1) {
        target[line + column] = blocked[line + column] === 1 ? far : 0;
      }
    }

    const diagonal = Math.SQRT2;
    for (let row = top; row <= bottom; row += 1) {
      const line = row * columns;
      for (let column = left; column <= right; column += 1) {
        const index = line + column;
        if (target[index] === 0) {
          continue;
        }
        let value = target[index];
        if (row > top) {
          value = Math.min(value, target[index - columns] + 1);
          if (column > left) {
            value = Math.min(value, target[index - columns - 1] + diagonal);
          }
          if (column < right) {
            value = Math.min(value, target[index - columns + 1] + diagonal);
          }
        }
        if (column > left) {
          value = Math.min(value, target[index - 1] + 1);
        }
        target[index] = value;
      }
    }

    for (let row = bottom; row >= top; row -= 1) {
      const line = row * columns;
      for (let column = right; column >= left; column -= 1) {
        const index = line + column;
        if (target[index] === 0) {
          continue;
        }
        let value = target[index];
        if (row < bottom) {
          value = Math.min(value, target[index + columns] + 1);
          if (column < right) {
            value = Math.min(value, target[index + columns + 1] + diagonal);
          }
          if (column > left) {
            value = Math.min(value, target[index + columns - 1] + diagonal);
          }
        }
        if (column < right) {
          value = Math.min(value, target[index + 1] + 1);
        }
        target[index] = value;
      }
    }

    for (let index = top * columns + left; index <= bottom * columns + right; index += 1) {
      target[index] *= cell;
    }

    return target;
  }

  /** Диапазон отметок поверхности поля: сколько плоскостей пересекает луч. */
  private elevationRange(grid: SceneSurfaceGrid): { min: number; max: number } {
    let min = Number.POSITIVE_INFINITY;
    let max = Number.NEGATIVE_INFINITY;
    for (let index = 0; index < grid.elevation.length; index += 1) {
      const value = grid.elevation[index];
      if (value < min) {
        min = value;
      }
      if (value > max) {
        max = value;
      }
    }

    return Number.isFinite(min) ? { min, max } : { min: 0, max: 0 };
  }

  /** Сетка поверхностей с кэшем по полю, разрешению и форме элементов. */
  /** Сетка поверхностей с кэшем по полю, разрешению и форме элементов. */
  private grid(board: SceneBoard, elements: readonly SceneElement[], resolution: number): SceneSurfaceGrid {
    const key = [
      board.id,
      board.boundWidth,
      board.boundHeight,
      board.occupancyCell,
      resolution,
      this.elementsKey(elements, board.id),
    ].join('|');
    const cached = this.grids.get(board.id);
    if (cached === undefined || cached.key !== key) {
      const built = this.surfaces.build(board, elements, resolution);
      this.grids.set(board.id, { key, grid: built });

      return built;
    }

    return cached.grid;
  }

  /** Подпись элементов поля; строки пересобираются только на новых данных. */
  private elementsKey(elements: readonly SceneElement[], boardId: number): string {
    if (this.elementsRef !== elements) {
      this.elementsRef = elements;
      this.elementKeys.clear();
    }

    const cached = this.elementKeys.get(boardId);
    if (cached !== undefined) {
      return cached;
    }

    const key = elements
      .filter((element) => element.boardId === boardId)
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
      )
      .join(',');

    this.elementKeys.set(boardId, key);

    return key;
  }

  /** Уже собранная сетка поля или null. */
  private gridOf(boardId: number): SceneSurfaceGrid | null {
    return this.grids.get(boardId)?.grid ?? null;
  }

  /** План этажей по кэшу: зависит от данных модели, а не от актора. */
  private plan(model: SceneRenderModel): SceneFloorPlan {
    const key = [model.activeBoardId, model.boards, model.elements, model.enclosures];
    const changed = this.planCache === null || key.some((value, index) => this.planKey[index] !== value);
    if (changed) {
      this.planCache = this.floors.plan(model);
      this.planKey = key;
    }

    return this.planCache as SceneFloorPlan;
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
   * Тяжёлая часть — обход террейна, стен и проёмов и запекание растров; при
   * движении актора геометрия не меняется, поэтому пересобираем только по
   * смене данных.
   */
  private sightScene(model: SceneRenderModel): SceneFloorSightScene {
    const refs: readonly unknown[] = [model.resolution, model.boards, model.elements, model.enclosures];
    if (this.sight !== null && refs.every((value, index) => this.sightRefs[index] === value)) {
      return this.sight;
    }

    // Ссылки сменились — сверяем подпись: массив могли пересобрать с теми же
    // данными, и тогда запекать растры заново незачем.
    const key = [
      model.resolution,
      model.boards
        .map((board) => `${board.id}:${board.boundWidth}:${board.boundHeight}:${board.occupancyCell}`)
        .join(','),
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
    this.sightRefs = refs;

    return this.sight;
  }

  /** Стиль клетки по поверхности и проходимости. */
  private costStyle(grid: SceneSurfaceGrid, index: number): OverlayStyle | null {
    if (grid.speeds[index] <= 0) {
      return SCENE_OVERLAY.impassable;
    }

    return SCENE_OVERLAY.cost[grid.kinds[index]];
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
