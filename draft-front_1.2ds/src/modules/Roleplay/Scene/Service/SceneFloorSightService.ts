import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneSightRaster } from '@/modules/Roleplay/Scene/Dto/SceneSightRaster';
import { SCENE_SIGHT_SKY } from '@/modules/Roleplay/Scene/Dto/SceneSightRaster';
import {
  SceneFloorPlanService,
  type SceneFloorTransform,
} from '@/modules/Roleplay/Scene/Service/SceneFloorPlanService';
import { SceneGeometryService, type SceneShapeProbe } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import { SceneSightQuery, type SceneSightWall } from '@/modules/Roleplay/Scene/Service/SceneSightQuery';
import { sceneBodyHeight, sceneSightEyeIpari } from '@/modules/Roleplay/Scene/Service/SceneSight';

/** Точка взгляда на этаже: поле, локальная точка и рост тела. */
export interface SceneFloorSightPoint {
  boardId: number;
  point: ScenePoint;
  /** Рост тела в единицах сцены; без него — стандартный. */
  height?: number;
  /**
   * Отметка поверхности под ногами внутри поля, единицы сцены.
   *
   * У актора на настиле или крыше глаза выше плиты поля на высоту настила.
   * Ноль — обычный пол.
   */
  surface?: number;
}

/** Настройки межэтажного луча. */
export interface SceneFloorSightOptions {
  /**
   * Плиты перекрывают взгляд.
   *
   * В разрезе плита полупрозрачна и показывает нижний этаж планом, поэтому
   * взгляд сквозь неё не блокируется. Без разреза плита — перекрытие: видно
   * только через колодец.
   */
  slabs?: boolean;
  /**
   * Разрез этажа под плитой фокуса.
   *
   * Стены этого этажа внутри пятна плиты срезаны в рисунке, поэтому и взгляд
   * они не перекрывают. Снаружи пятна стены остаются: забор во дворе не
   * исчезает вместе с планом дома.
   */
  cut?: { boardId: number; bounds: SceneBounds };
  /**
   * Наблюдатель стоит на проходимой крыше.
   *
   * Крыша выше стен своего этажа: с неё двор виден поверх них, и стены этажа
   * луч не перекрывают.
   */
  onRoof?: boolean;
}

/** Проём, вырезающий стену: дверь, окно, ворота. */
export interface SceneFloorSightOpening {
  probe: SceneShapeProbe;
  /** Допуск попадания: проём может быть уже стены. */
  tolerance: number;
}

/** Стена этажа: фигура и её верх от нуля корня. */
export interface SceneFloorSightWall {
  probe: SceneShapeProbe;
  /** Верх стены в единицах сцены; `SCENE_SIGHT_SKY` — без высоты, до неба. */
  top: number;
}

/** Этаж в мире корня: плита с колодцами, стены и проёмы. */
export interface SceneFloorSightLevel {
  boardId: number;
  transform: SceneFloorTransform;
  elevation: number;
  /** Плита: террейн этажа, кроме воды. */
  slabs: SceneShapeProbe[];
  /** Вода этажа: поверхность ниже пола, поэтому пола над ней нет. */
  waters: SceneShapeProbe[];
  /** Колодцы и площадки в плите: проёмы-лестницы. */
  holes: SceneShapeProbe[];
  /** Стены этажа. */
  walls: SceneFloorSightWall[];
  /** Проёмы стен: сквозь них взгляд проходит. */
  openings: SceneFloorSightOpening[];
  /** Границы поля в локальных координатах. */
  bounds: SceneBounds;
  /** Плита без колодцев в локальных координатах: источник перекрытия этажа. */
  slab: SceneSightRaster;
  /** Стены без проёмов в мире корня: источник тени стен; null — стен нет. */
  wall: SceneSightRaster | null;
}

/** Готовые препятствия этажей для межэтажного луча. */
export interface SceneFloorSightScene {
  resolution: number;
  levels: Map<number, SceneFloorSightLevel>;
}

/** Доли роста цели, которые пробует луч: видна любая точка тела. */
const BODY_SHARES = [0.5, 0.75, 0.9] as const;

/**
 * Видимость акторов между этажами.
 *
 * В пределах одного этажа обзор считает `SceneVisibilityService` по сетке
 * поля. Между этажами луч идёт в мире корня: на пути проверяются плиты и
 * стены, а проём считается прозрачным. Так с этажа видно ступени и актора
 * снизу через колодец, двор — через окно, а глухой пол и глухая стена
 * скрывают то, что за ними.
 *
 * Плита — террейн этажа (кроме воды): она лежит на отметке этажа и
 * перекрывает луч, если тот пересекает её плоскость внутри пятна и вне
 * колодца или площадки (проём-лестница). В разрезе (`slabs: false`) плита не
 * мешает: нижний этаж показан планом, и его акторы видны сквозь
 * полупрозрачный пол.
 *
 * Глаз не поднимается выше пола верхнего этажа, в пятне которого стоит
 * наблюдатель: иначе марш у верха или колодец открывали бы взгляд поверх стен
 * своего этажа.
 *
 * Стены своего и верхних этажей перекрывают луч; проём в них — дверь, окно,
 * ворота — считается прозрачным целиком. Подоконник и перемычка важны для обзора
 * внутри этажа, а не для межэтажной визуализации: сверху окно читается как
 * проём. Стены нижних этажей не мешают — наблюдатель смотрит поверх них, а
 * перекрытие проверяет плита.
 */
export class SceneFloorSightService {
  constructor(
    private readonly floors: SceneFloorPlanService = new SceneFloorPlanService(),
    private readonly geometry: SceneGeometryService = new SceneGeometryService(),
  ) {}

  /**
   * Готовит препятствия этажей.
   *
   * @param model Модель рендера: поля, вложения, элементы.
   *
   * @returns Сцена для лучей; пересобирается при смене данных.
   */
  scene(model: SceneRenderModel): SceneFloorSightScene {
    const resolution = Math.max(1, model.resolution);
    const elevations = this.floors.elevations(model);
    const levels = new Map<number, SceneFloorSightLevel>();
    const rootBoardId = this.floors.rootBoardId(model);
    const rootBoard = model.boards.find((item) => item.id === rootBoardId) ?? model.boards[0] ?? null;
    // Тень стен нужна в мире корня: она ложится и за пределами своего поля,
    // во дворе. Поэтому растр стен — корневой, а плиты остаются локальными.
    const rootBounds: SceneBounds = {
      x: 0,
      y: 0,
      width: Math.max(1, rootBoard?.boundWidth ?? 1),
      height: Math.max(1, rootBoard?.boundHeight ?? 1),
    };
    const rootCell = Math.max(1, rootBoard?.occupancyCell ?? 1);

    for (const board of model.boards) {
      const bounds: SceneBounds = {
        x: 0,
        y: 0,
        width: Math.max(1, board.boundWidth),
        height: Math.max(1, board.boundHeight),
      };
      const cell = Math.max(1, board.occupancyCell);
      const level: SceneFloorSightLevel = {
        boardId: board.id,
        transform: this.floors.transform(model, board.id),
        elevation: elevations.get(board.id) ?? 0,
        slabs: [],
        holes: [],
        waters: [],
        walls: [],
        openings: [],
        bounds,
        slab: this.emptyRaster(bounds, cell),
        wall: null,
      };

      for (const element of model.elements) {
        if (element.boardId !== board.id) {
          continue;
        }

        if (element.kind === 'terrain' && element.shape.type !== 'point') {
          if (element.props.kind === 'water') {
            // Вода — не плита: её поверхность лежит ниже пола, и луч к ней
            // идёт вниз. Если оставить пол над водой, он перекроет луч, и
            // вода с другого этажа всегда останется в тумане.
            level.waters.push(this.geometry.probe(element.shape));

            continue;
          }

          level.slabs.push(this.geometry.probe(element.shape));

          continue;
        }

        if (element.kind === 'opening') {
          if (element.shape.type === 'rect') {
            // Колодец и площадка — вырезы в плите этажа: площадка перехода
            // это верхняя ступень марша, а не доска над ним.
            if (element.props.kind === 'stairs') {
              level.holes.push(this.geometry.probe(element.shape));
            }

            continue;
          }

          if (element.shape.type === 'wall') {
            level.openings.push({
              probe: this.geometry.probe(element.shape),
              tolerance: element.shape.width / 2,
            });
          }

          continue;
        }

        if (element.kind === 'obstacle' && element.shape.type === 'wall' && element.props.blocksVision !== false) {
          const height = element.props.height;
          const top =
            typeof height === 'number' && Number.isFinite(height) ? level.elevation + Math.max(0, height) : SCENE_SIGHT_SKY;
          level.walls.push({ probe: this.geometry.probe(element.shape), top: Math.min(SCENE_SIGHT_SKY, top) });
        }
      }

      // Запекаем препятствия в байты один раз: на тике тумана стены и плиты
      // читаются как маска, а не как фигуры. Допуск в полклетки не даёт
      // тонкой стене проскочить между центрами клеток.
      level.slab = this.bakeRaster(
        bounds,
        cell,
        level.slabs,
        [
          ...level.holes.map((probe) => ({ probe, tolerance: cell / 2 })),
          // Вода — дыра в плите: её поверхность ниже пола. Без выреза пол над
          // водой перекрывал бы луч к ней, и с другого этажа вода не виднелась
          // бы никогда.
          ...level.waters.map((probe) => ({ probe, tolerance: 0 })),
        ],
        cell / 2,
      );
      if (level.walls.length > 0) {
        level.wall = this.bakeRootRaster(
          rootBounds,
          rootCell,
          level.transform,
          level.walls,
          level.openings.map((opening) => ({ probe: opening.probe, tolerance: opening.tolerance })),
          rootCell / 2,
        );
      }
      levels.set(board.id, level);
    }

    return {
      resolution,
      levels,
    };
  }

  /**
   * Потолок над наблюдателем: пол ближайшего этажа выше.
   *
   * На марше, площадке и в колодце голова может оказаться выше стен своего
   * этажа. Верхний этаж при этом остаётся перекрытием: его пол не должен
   * пропускать взгляд наружу. Поэтому глаз ограничивается полом ближайшего
   * этажа, в пятне которого стоит наблюдатель. Когда пол под ногами уже выше
   * этого этажа, ограничения нет — там он уже сверху.
   *
   * @param scene Препятствия этажей.
   * @param from Наблюдатель: поле, точка и отметка пола под ногами.
   *
   * @returns Абсолютная отметка потолка в мире корня или null.
   */
  ceiling(scene: SceneFloorSightScene, from: SceneFloorSightPoint): number | null {
    const level = scene.levels.get(from.boardId);
    if (level === undefined) {
      return null;
    }

    const floor = level.elevation + (from.surface ?? 0);
    const root = this.floors.toRoot(level.transform, from.point);
    let ceiling = Number.POSITIVE_INFINITY;
    for (const other of scene.levels.values()) {
      if (other.elevation <= level.elevation) {
        continue;
      }

      // Наблюдатель уже выше пола этого этажа: это не потолок над ним, а
      // настил под ногами (башня, второй марш).
      if (floor > other.elevation) {
        continue;
      }

      const local = this.floors.toLocal(other.transform, root);
      if (!other.slabs.some((probe) => probe.contains(local, 0))) {
        continue;
      }

      ceiling = Math.min(ceiling, other.elevation);
    }

    return Number.isFinite(ceiling) ? ceiling : null;
  }

  /**
   * Видимость другого этажа по запечённым маскам.
   *
   * Стены читаются из корневого растра обходом клеток на пути луча, плиты —
   * аффинной пробой точки пересечения плоскости. Тяжёлой части нет: луч идёт
   * по клеткам растра, а не по фигурам, и не зависит от числа домов в селе.
   *
   * @param scene Препятствия этажей.
   * @param from Наблюдатель: поле, точка, рост.
   * @param options Настройки лучей.
   *
   * @returns Готовый запрос видимости целевых клеток.
   */
  query(
    scene: SceneFloorSightScene,
    from: SceneFloorSightPoint,
    options: SceneFloorSightOptions = {},
  ): SceneSightQuery {
    const level = scene.levels.get(from.boardId);
    const fromRoot = level === undefined ? from.point : this.floors.toRoot(level.transform, from.point);
    const eye = this.eyeOf(scene, from);
    const walls: SceneSightWall[] = [];
    for (const other of scene.levels.values()) {
      if (other.wall === null) {
        continue;
      }

      // Стены ниже наблюдателя не мешают: он смотрит поверх них, а перекрытие
      // проверяет плита. Свои стены нужны, пока он не на крыше; стены верхних
      // этажей — всегда, с марша луч идёт выше своего этажа.
      if (level !== undefined && other.elevation < level.elevation) {
        continue;
      }

      if (options.onRoof === true && other.boardId === from.boardId) {
        continue;
      }

      walls.push({
        raster: other.wall,
        bottom: other.elevation,
        top: other.walls.reduce((max, wall) => Math.max(max, wall.top), other.elevation),
      });
    }

    return new SceneSightQuery(scene, this.floors, from, fromRoot, eye, walls, options.slabs !== false);
  }

  /** Глаз наблюдателя в мире корня, не выше потолка над ним. */
  private eyeOf(scene: SceneFloorSightScene, from: SceneFloorSightPoint): number {
    const level = scene.levels.get(from.boardId);
    const eyeBody = Math.max(1, from.height ?? sceneBodyHeight(scene.resolution));
    const eye =
      (level?.elevation ?? 0) +
      (from.surface ?? 0) +
      sceneSightEyeIpari(eyeBody, scene.resolution) * scene.resolution;
    const ceiling = this.ceiling(scene, from);

    return ceiling === null ? eye : Math.min(eye, Math.max(0, ceiling - 0.5));
  }

  /**
   * Запекает стены этажа в растр мира корня.
   *
   * Проба задана в координатах поля, поэтому клетка корня переводится в поле и
   * проверяется там. Так стена ложится на общую карту без преобразования фигур
   * и её тень продолжается во двор за границей поля.
   *
   * @param bounds Границы корня.
   * @param cell Размер клетки корня.
   * @param transform Преобразование этажа в мир корня.
   * @param add Стены.
   * @param erase Проёмы.
   * @param toleranceRoot Допуск в единицах корня.
   *
   * @returns Растр стен в мире корня.
   */
  private bakeRootRaster(
    bounds: SceneBounds,
    cell: number,
    transform: SceneFloorTransform,
    add: readonly SceneFloorSightWall[],
    erase: readonly { probe: SceneShapeProbe; tolerance: number }[],
    toleranceRoot: number,
  ): SceneSightRaster {
    const raster = this.emptyRaster(bounds, cell);
    const scaleX = transform.scaleX === 0 ? 1 : Math.abs(transform.scaleX);
    const scaleY = transform.scaleY === 0 ? 1 : Math.abs(transform.scaleY);
    // Допуск попадания один на растр: он не даёт тонкой стене проскочить меж
    // центрами клеток. Габарит обхода у проёма шире — проём должен стереть
    // стену целиком, но не расширить её форму.
    const toleranceLocal = toleranceRoot / Math.max(1e-6, Math.min(scaleX, scaleY));
    const paint = (probe: SceneShapeProbe, boxTolerance: number, value: number, top = 0): void => {
      const box = probe.bounds;
      const left = transform.x + (box.x - boxTolerance) * transform.scaleX;
      const right = transform.x + (box.x + box.width + boxTolerance) * transform.scaleX;
      const topEdge = transform.y + (box.y - boxTolerance) * transform.scaleY;
      const bottom = transform.y + (box.y + box.height + boxTolerance) * transform.scaleY;
      const minColumn = Math.max(0, Math.floor((Math.min(left, right) - raster.x) / cell));
      const maxColumn = Math.min(raster.columns - 1, Math.floor((Math.max(left, right) - raster.x) / cell));
      const minRow = Math.max(0, Math.floor((Math.min(topEdge, bottom) - raster.y) / cell));
      const maxRow = Math.min(raster.rows - 1, Math.floor((Math.max(topEdge, bottom) - raster.y) / cell));
      for (let row = minRow; row <= maxRow; row += 1) {
        const centerY = raster.y + (row + 0.5) * cell;
        const localY = (centerY - transform.y) / (transform.scaleY === 0 ? 1 : transform.scaleY);
        for (let column = minColumn; column <= maxColumn; column += 1) {
          const centerX = raster.x + (column + 0.5) * cell;
          const localX = (centerX - transform.x) / (transform.scaleX === 0 ? 1 : transform.scaleX);
          if (probe.contains([localX, localY], toleranceLocal)) {
            const index = row * raster.columns + column;
            raster.cells[index] = value;
            if (value === 1) {
              raster.tops[index] = Math.max(raster.tops[index], top);
            }
          }
        }
      }
    };

    for (const wall of add) {
      paint(wall.probe, toleranceRoot, 1, this.wallTop(wall.top));
    }
    for (const opening of erase) {
      paint(opening.probe, opening.tolerance + toleranceRoot, 0);
    }

    return raster;
  }

  /** Верх стены в единицах `Uint16Array`; без высоты — сентинел «до неба». */
  private wallTop(top: number): number {
    if (!Number.isFinite(top)) {
      return SCENE_SIGHT_SKY;
    }

    return Math.min(SCENE_SIGHT_SKY, Math.max(0, Math.ceil(top)));
  }

  /**
   * Пустой растр этажа.
   *
   * @param bounds Границы поля.
   * @param cell Размер клетки.
   *
   * @returns Растр без перекрытий.
   */
  private emptyRaster(bounds: SceneBounds, cell: number): SceneSightRaster {
    const columns = Math.max(1, Math.ceil(bounds.width / cell));
    const rows = Math.max(1, Math.ceil(bounds.height / cell));

    return {
      x: bounds.x,
      y: bounds.y,
      cell,
      columns,
      rows,
      cells: new Uint8Array(columns * rows),
      tops: new Uint16Array(columns * rows),
    };
  }

  /**
   * Запекает пробы в растр.
   *
   * Обход идёт по габаритам проб, а не по всему полю: большой корень с одной
   * травой стоит столько же, сколько её площадь, а не поле целиком.
   *
   * @param bounds Границы поля.
   * @param cell Размер клетки.
   * @param add Пробы, которые перекрывают.
   * @param erase Пробы-проёмы, которые вырезают перекрытие.
   * @param tolerance Допуск попадания для `add`, единицы сцены.
   *
   * @returns Запечённый растр.
   */
  private bakeRaster(
    bounds: SceneBounds,
    cell: number,
    add: readonly SceneShapeProbe[],
    erase: readonly { probe: SceneShapeProbe; tolerance: number }[],
    tolerance: number,
  ): SceneSightRaster {
    const raster = this.emptyRaster(bounds, cell);
    const paint = (probe: SceneShapeProbe, toleranceOf: number, value: number): void => {
      const box = probe.bounds;
      const minColumn = Math.max(0, Math.floor((box.x - toleranceOf - raster.x) / cell));
      const maxColumn = Math.min(raster.columns - 1, Math.floor((box.x + box.width + toleranceOf - raster.x) / cell));
      const minRow = Math.max(0, Math.floor((box.y - toleranceOf - raster.y) / cell));
      const maxRow = Math.min(raster.rows - 1, Math.floor((box.y + box.height + toleranceOf - raster.y) / cell));
      for (let row = minRow; row <= maxRow; row += 1) {
        const centerY = raster.y + (row + 0.5) * cell;
        for (let column = minColumn; column <= maxColumn; column += 1) {
          const centerX = raster.x + (column + 0.5) * cell;
          if (probe.contains([centerX, centerY], toleranceOf)) {
            raster.cells[row * raster.columns + column] = value;
          }
        }
      }
    };

    for (const probe of add) {
      paint(probe, tolerance, 1);
    }
    for (const opening of erase) {
      paint(opening.probe, opening.tolerance, 0);
    }

    return raster;
  }

  /**
   * Виден ли актор другого этажа.
   *
   * @param scene Препятствия из `scene()`.
   * @param from Наблюдатель: поле, точка и рост.
   * @param to Цель: поле, точка и рост.
   * @param options Настройки луча.
   *
   * @returns true — виден; на одном поле всегда true: его считает сетка.
   */
  sees(
    scene: SceneFloorSightScene,
    from: SceneFloorSightPoint,
    to: SceneFloorSightPoint,
    options: SceneFloorSightOptions = {},
  ): boolean {
    if (from.boardId === to.boardId) {
      return true;
    }

    const fromLevel = scene.levels.get(from.boardId);
    const toLevel = scene.levels.get(to.boardId);
    if (fromLevel === undefined || toLevel === undefined) {
      return true;
    }

    const rootFrom = this.floors.toRoot(fromLevel.transform, from.point);
    const rootTo = this.floors.toRoot(toLevel.transform, to.point);
    const eye = this.eyeOf(scene, from);
    const body = Math.max(1, to.height ?? sceneBodyHeight(scene.resolution));
    const targetSurface = to.surface ?? 0;
    const slabs = options.slabs !== false;

    // Цель видна, если видна любая точка её тела: голова может подниматься над
    // подоконником или краем колодца, когда ноги ещё закрыты.
    for (const share of BODY_SHARES) {
      const target = toLevel.elevation + targetSurface + body * share;
      if (this.clear(scene, from.boardId, rootFrom, rootTo, eye, target, slabs, options.cut, options.onRoof === true)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Видна ли клетка другого поля.
   *
   * Для тумана достаточно одной точки тела над полом: луч идёт к голове
   * стоящего в клетке, а не перебирает низ, середину и верх. Так видно
   * комнату второго этажа через окно, но не видно клетку за плитой: луч к
   * точке чуть выше пола упирался бы в саму плиту цели.
   *
   * @param scene Препятствия этажей.
   * @param from Точка глаз наблюдателя.
   * @param to Клетка цели: поверхность и рост тела.
   * @param options Настройки луча.
   *
   * @returns true — клетка видна; на одном поле всегда true.
   */
  seesSurface(
    scene: SceneFloorSightScene,
    from: SceneFloorSightPoint,
    to: SceneFloorSightPoint,
    options: SceneFloorSightOptions = {},
  ): boolean {
    if (from.boardId === to.boardId) {
      return true;
    }

    const fromLevel = scene.levels.get(from.boardId);
    const toLevel = scene.levels.get(to.boardId);
    if (fromLevel === undefined || toLevel === undefined) {
      return true;
    }

    const rootFrom = this.floors.toRoot(fromLevel.transform, from.point);
    const rootTo = this.floors.toRoot(toLevel.transform, to.point);
    const eye = this.eyeOf(scene, from);
    const target =
      toLevel.elevation + (to.surface ?? 0) + Math.max(1, to.height ?? sceneBodyHeight(scene.resolution)) * 0.9;

    return this.clear(
      scene,
      from.boardId,
      rootFrom,
      rootTo,
      eye,
      target,
      options.slabs !== false,
      options.cut,
      options.onRoof === true,
    );
  }

  /**
   * Свободен ли луч между точками мира корня.
   *
   * @param scene Препятствия этажей.
   * @param fromBoardId Поле наблюдателя: его стены и перекрывают взгляд.
   * @param from Точка глаз в мире корня.
   * @param to Точка тела цели в мире корня.
   * @param eye Высота глаз над нулём корня.
   * @param target Высота точки тела над нулём корня.
   * @param slabs Проверять плиты.
   * @param cut Разрез этажа под плитой фокуса.
   * @param onRoof Наблюдатель на крыше: стены его этажа ниже.
   *
   * @returns true — ни плита, ни стена не перекрыли луч.
   */
  private clear(
    scene: SceneFloorSightScene,
    fromBoardId: number,
    from: ScenePoint,
    to: ScenePoint,
    eye: number,
    target: number,
    slabs: boolean,
    cut: SceneFloorSightOptions['cut'],
    onRoof: boolean,
  ): boolean {
    if (slabs && !this.clearSlabs(scene, from, to, eye, target)) {
      return false;
    }

    return this.clearWalls(scene, fromBoardId, from, to, eye, target, cut, onRoof);
  }

  /**
   * Плиты: луч пересекает плоскость этажа внутри пятна и вне колодца.
   *
   * Пересечение считается по высоте, а не по всей проекции: луч из комнаты в
   * двор проходит над полом у окна и опускается уже снаружи, поэтому пятно
   * плиты проверяется только в точке пересечения плоскости.
   */
  private clearSlabs(
    scene: SceneFloorSightScene,
    from: ScenePoint,
    to: ScenePoint,
    eye: number,
    target: number,
  ): boolean {
    const rise = eye - target;
    if (Math.abs(rise) < 1e-6) {
      return true;
    }

    for (const level of scene.levels.values()) {
      const elevation = level.elevation;
      if ((eye - elevation) * (target - elevation) >= 0) {
        continue;
      }

      const t = (eye - elevation) / rise;
      if (t <= 0 || t >= 1) {
        continue;
      }

      const point: ScenePoint = [from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t];
      const local = this.floors.toLocal(level.transform, point);
      if (!level.slabs.some((probe) => probe.contains(local, 0))) {
        continue;
      }

      if (level.holes.some((probe) => probe.contains(local, 0))) {
        continue;
      }

      return false;
    }

    return true;
  }

  /**
   * Стены: луч не должен проходить сквозь стены своего и верхних этажей.
   *
   * Стены ниже наблюдателя не мешают: он смотрит поверх них, а перекрытие
   * проверяет плита. С марша луч идёт выше своего этажа, поэтому стены
   * верхнего этажа тоже проверяются. На крыше стены своего этажа
   * пропускаются: наблюдатель выше них.
   *
   * Луч идёт шагами, потому что стена — полоса, а не точка.
   */
  private clearWalls(
    scene: SceneFloorSightScene,
    fromBoardId: number,
    from: ScenePoint,
    to: ScenePoint,
    eye: number,
    target: number,
    cut: SceneFloorSightOptions['cut'],
    onRoof: boolean,
  ): boolean {
    const distance = Math.hypot(to[0] - from[0], to[1] - from[1]);
    const step = Math.max(1, scene.resolution / 16);
    const steps = Math.max(2, Math.ceil(distance / step));
    const low = Math.min(eye, target);
    const high = Math.max(eye, target);
    const fromLevel = scene.levels.get(fromBoardId);

    for (const level of scene.levels.values()) {
      if (level.walls.length === 0) {
        continue;
      }

      // Стены ниже наблюдателя не мешают: он смотрит поверх них, а перекрытие
      // проверяет плита.
      if (fromLevel !== undefined && level.elevation < fromLevel.elevation) {
        continue;
      }

      // С крыши смотрят поверх стен своего этажа: они ниже настила.
      if (onRoof && level.boardId === fromBoardId) {
        continue;
      }

      const top = level.walls.reduce((max, wall) => Math.max(max, wall.top), level.elevation);
      if (high < level.elevation || low > top) {
        continue;
      }

      for (let index = 1; index < steps; index += 1) {
        const t = index / steps;
        const height = eye + (target - eye) * t;
        if (height < level.elevation) {
          continue;
        }

        const point: ScenePoint = [from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t];
        // Стены разрезанного этажа внутри пятна плиты срезаны и в рисунке:
        // взгляд сквозь них проходит.
        if (cut !== undefined && cut.boardId === level.boardId && this.insideBounds(cut.bounds, point)) {
          continue;
        }

        const local = this.floors.toLocal(level.transform, point);
        if (!level.walls.some((wall) => wall.probe.contains(local, 0) && height < wall.top)) {
          continue;
        }

        // Стена найдена: луч проходит, только если здесь проём.
        if (!level.openings.some((opening) => opening.probe.contains(local, opening.tolerance))) {
          return false;
        }
      }
    }

    return true;
  }

  /** Точка внутри прямоугольника разреза. */
  private insideBounds(bounds: SceneBounds, point: ScenePoint): boolean {
    return (
      point[0] >= bounds.x &&
      point[0] <= bounds.x + bounds.width &&
      point[1] >= bounds.y &&
      point[1] <= bounds.y + bounds.height
    );
  }
}
