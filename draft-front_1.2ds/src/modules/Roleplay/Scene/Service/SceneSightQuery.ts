import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneSightRaster } from '@/modules/Roleplay/Scene/Dto/SceneSightRaster';
import { SCENE_SIGHT_SKY } from '@/modules/Roleplay/Scene/Dto/SceneSightRaster';
import type {
  SceneFloorSightPoint,
  SceneFloorSightScene,
} from '@/modules/Roleplay/Scene/Service/SceneFloorSightService';
import type {
  SceneFloorPlanService,
  SceneFloorTransform,
} from '@/modules/Roleplay/Scene/Service/SceneFloorPlanService';

/** Плоскость этажа, которую луч может пересечь: растр плиты и её место в мире. */
interface SceneSightPlane {
  raster: SceneSightRaster;
  transform: SceneFloorTransform;
  elevation: number;
}

/** Целевое поле, подготовленное к пробам. */
export interface SceneSightTarget {
  transform: SceneFloorTransform;
  elevation: number;
  /** Плоскости между глазом и телом цели: их 0–2, а не все этажи. */
  planes: SceneSightPlane[];
}

/** Стена этажа для луча: растр в мире корня и её вертикальные границы. */
export interface SceneSightWall {
  raster: SceneSightRaster;
  /** Низ стены: пол этажа в мире корня. */
  bottom: number;
  /** Верх самой высокой стены этажа: для отсечения растра целиком. */
  top: number;
}

/**
 * Видимость клеток чужих этажей за один тик.
 *
 * Тяжёлой части нет: стены читаются из запечённого корневого растра обходом
 * клеток на пути луча, плиты — аффинной пробой точки пересечения плоскости.
 * Поэтому цена тика зависит от клеток в радиусе и стен на пути луча, но не от
 * числа домов в селе.
 *
 * Стены проверяются у своего и верхних этажей, а не только у этажа
 * наблюдателя: с марша луч идёт выше своего этажа, и стены верхнего этажа
 * должны его ограничивать.
 *
 * Плоскости для поля готовятся один раз (`prepare`), а не перебираются на
 * каждую клетку: у цели между глазом и телом обычно ноль или одна плита.
 */
export class SceneSightQuery {
  constructor(
    private readonly scene: SceneFloorSightScene,
    private readonly floors: SceneFloorPlanService,
    private readonly from: SceneFloorSightPoint,
    private readonly fromRoot: ScenePoint,
    private readonly eye: number,
    private readonly walls: readonly SceneSightWall[],
    private readonly slabs: boolean,
  ) {}

  /**
   * Готовит целевое поле к пробам.
   *
   * @param boardId Целевое поле.
   * @param surfaceMin Низшая отметка поверхности поля, единицы сцены.
   * @param surfaceMax Высшая отметка поверхности поля.
   * @param body Рост тела цели, единицы сцены.
   *
   * @returns Цель для `sees`; null — поле не участвует в луче.
   */
  prepare(boardId: number, surfaceMin: number, surfaceMax: number, body: number): SceneSightTarget | null {
    const level = this.scene.levels.get(boardId);
    if (level === undefined) {
      return null;
    }

    const planes: SceneSightPlane[] = [];
    if (this.slabs) {
      const low = level.elevation + surfaceMin + Math.max(1, body) * 0.9;
      const high = level.elevation + surfaceMax + Math.max(1, body) * 0.9;
      const minHeight = Math.min(this.eye, low, high);
      const maxHeight = Math.max(this.eye, low, high);
      for (const plane of this.scene.levels.values()) {
        if (plane.elevation > minHeight && plane.elevation < maxHeight) {
          planes.push({ raster: plane.slab, transform: plane.transform, elevation: plane.elevation });
        }
      }
    }

    return { transform: level.transform, elevation: level.elevation, planes };
  }

  /**
   * Видна ли клетка другого поля.
   *
   * @param target Подготовленное поле из `prepare`.
   * @param x Точка клетки по X в координатах поля.
   * @param y Точка клетки по Y.
   * @param surface Отметка поверхности клетки, единицы сцены.
   * @param height Рост тела цели, единицы сцены.
   *
   * @returns true — клетка видна.
   */
  sees(target: SceneSightTarget, x: number, y: number, surface: number, height: number): boolean {
    const transform = target.transform;
    const rootX = transform.x + x * transform.scaleX;
    const rootY = transform.y + y * transform.scaleY;
    const targetHeight = target.elevation + surface + Math.max(1, height) * 0.9;
    if (!this.clearWalls(rootX, rootY, targetHeight)) {
      return false;
    }

    if (!this.slabs) {
      return true;
    }

    const rise = this.eye - targetHeight;
    if (Math.abs(rise) < 1e-6) {
      return true;
    }

    const fromX = this.fromRoot[0];
    const fromY = this.fromRoot[1];
    for (const plane of target.planes) {
      const elevation = plane.elevation;
      if ((this.eye - elevation) * (targetHeight - elevation) >= 0) {
        continue;
      }

      const t = (this.eye - elevation) / rise;
      if (t <= 0 || t >= 1) {
        continue;
      }

      const raster = plane.raster;
      const localX = (fromX + (rootX - fromX) * t - plane.transform.x) / (plane.transform.scaleX || 1);
      const localY = (fromY + (rootY - fromY) * t - plane.transform.y) / (plane.transform.scaleY || 1);
      const column = Math.floor((localX - raster.x) / raster.cell);
      const row = Math.floor((localY - raster.y) / raster.cell);
      if (column < 0 || row < 0 || column >= raster.columns || row >= raster.rows) {
        continue;
      }
      if (raster.cells[row * raster.columns + column] === 1) {
        return false;
      }
    }

    return true;
  }

  /**
   * Отрезок в мире корня не задет стенами этажей.
   *
   * Идём по клеткам растра, которые реально пересекает отрезок (обход
   * Аманатидеса — Ву): в отличие от теневого литья по сетке, узкий проём не
   * размывается в широкий конус на расстоянии. Стены проверяются у каждого
   * этажа, чья полоса высот пересекает луч: с марша это и свой этаж, и
   * верхний, стены которого иначе не ограничивали бы взгляд.
   *
   * Высота луча на входе в клетку — `eye + (target − eye) · t`, где `t` —
   * параметр обхода. Низкий забор пропускает взгляд поверх себя: стена
   * перекрывает, только если луч прошёл ниже её верха и не ниже её низа.
   *
   * @param rootX Точка цели по X в мире корня.
   * @param rootY Точка цели по Y.
   * @param targetHeight Высота точки тела цели над нулём корня.
   */
  private clearWalls(rootX: number, rootY: number, targetHeight: number): boolean {
    const low = Math.min(this.eye, targetHeight);
    const high = Math.max(this.eye, targetHeight);
    for (const wall of this.walls) {
      // Луч целиком ниже или выше полосы стен этажа: растру его не проверить.
      if (high < wall.bottom || low > wall.top) {
        continue;
      }

      if (!this.clearWall(wall, rootX, rootY, targetHeight)) {
        return false;
      }
    }

    return true;
  }

  /** Один растр стен: отрезок не задет его стенами. */
  private clearWall(
    wall: SceneSightWall,
    rootX: number,
    rootY: number,
    targetHeight: number,
  ): boolean {
    const raster = wall.raster;
    const cell = raster.cell;
    const fromX = this.fromRoot[0];
    const fromY = this.fromRoot[1];
    let column = Math.floor((fromX - raster.x) / cell);
    let row = Math.floor((fromY - raster.y) / cell);
    const endColumn = Math.floor((rootX - raster.x) / cell);
    const endRow = Math.floor((rootY - raster.y) / cell);
    if (column === endColumn && row === endRow) {
      return true;
    }

    const dx = rootX - fromX;
    const dy = rootY - fromY;
    const stepX = dx < 0 ? -1 : 1;
    const stepY = dy < 0 ? -1 : 1;
    const deltaX = dx === 0 ? Number.POSITIVE_INFINITY : Math.abs(cell / dx);
    const deltaY = dy === 0 ? Number.POSITIVE_INFINITY : Math.abs(cell / dy);
    const borderX = raster.x + (column + (stepX > 0 ? 1 : 0)) * cell;
    const borderY = raster.y + (row + (stepY > 0 ? 1 : 0)) * cell;
    let nextX = dx === 0 ? Number.POSITIVE_INFINITY : (borderX - fromX) / dx;
    let nextY = dy === 0 ? Number.POSITIVE_INFINITY : (borderY - fromY) / dy;
    let guard = raster.columns + raster.rows;

    while (guard > 0 && (column !== endColumn || row !== endRow)) {
      let at: number;
      if (nextX < nextY) {
        at = nextX;
        column += stepX;
        nextX += deltaX;
      } else if (nextY < nextX) {
        at = nextY;
        row += stepY;
        nextY += deltaY;
      } else {
        // Луч прошёл ровно через общий угол клеток. Проверяем обе боковые
        // клетки, иначе диагональ проскальзывает между двумя стенами.
        at = nextX;
        if (this.wallBlocks(wall, column + stepX, row, at, targetHeight)) {
          return false;
        }
        if (this.wallBlocks(wall, column, row + stepY, at, targetHeight)) {
          return false;
        }
        column += stepX;
        row += stepY;
        nextX += deltaX;
        nextY += deltaY;
      }
      guard -= 1;
      if (column < 0 || row < 0 || column >= raster.columns || row >= raster.rows) {
        return true;
      }
      if (column === endColumn && row === endRow) {
        break;
      }
      if (this.wallBlocks(wall, column, row, at, targetHeight)) {
        return false;
      }
    }

    return true;
  }

  /** Клетка растра перекрывает луч на его высоте в параметре `at`. */
  private wallBlocks(
    wall: SceneSightWall,
    column: number,
    row: number,
    at: number,
    targetHeight: number,
  ): boolean {
    const raster = wall.raster;
    if (column < 0 || row < 0 || column >= raster.columns || row >= raster.rows) {
      return false;
    }

    const index = row * raster.columns + column;
    if (raster.cells[index] === 0) {
      return false;
    }

    const height = this.eye + (targetHeight - this.eye) * at;
    // Стена начинается от пола своего этажа: луч ниже него проходит под ней.
    if (height < wall.bottom) {
      return false;
    }

    const top = raster.tops[index];
    if (top === SCENE_SIGHT_SKY) {
      return true;
    }

    return height < top;
  }
}
