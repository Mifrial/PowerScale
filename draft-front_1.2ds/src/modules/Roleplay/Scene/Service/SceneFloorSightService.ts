import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import {
  SceneFloorPlanService,
  type SceneFloorTransform,
} from '@/modules/Roleplay/Scene/Service/SceneFloorPlanService';
import { SceneGeometryService, type SceneShapeProbe } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import { sceneBodyHeight, sceneSightEyeIpari } from '@/modules/Roleplay/Scene/Service/SceneSight';

/** Точка взгляда на этаже: поле, локальная точка и рост тела. */
export interface SceneFloorSightPoint {
  boardId: number;
  point: ScenePoint;
  /** Рост тела в единицах сцены; без него — стандартный. */
  height?: number;
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
}

/** Проём, вырезающий стену: дверь, окно, ворота. */
interface SceneFloorSightOpening {
  probe: SceneShapeProbe;
  /** Допуск попадания: проём может быть уже стены. */
  tolerance: number;
}

/** Этаж в мире корня: плита с колодцами, стены и проёмы. */
interface SceneFloorSightLevel {
  boardId: number;
  transform: SceneFloorTransform;
  elevation: number;
  /** Плита: террейн этажа, кроме воды. */
  slabs: SceneShapeProbe[];
  /** Колодцы в плите: проёмы-лестницы без целевого поля. */
  holes: SceneShapeProbe[];
  /** Стены этажа. */
  walls: SceneShapeProbe[];
  /** Проёмы стен: сквозь них взгляд проходит. */
  openings: SceneFloorSightOpening[];
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
 * колодца. В разрезе (`slabs: false`) плита не мешает: нижний этаж показан
 * планом, и его акторы видны сквозь полупрозрачный пол.
 *
 * Стена этажа наблюдателя перекрывает луч; проём в ней — дверь, окно, ворота
 * — считается прозрачным целиком. Подоконник и перемычка важны для обзора
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

    for (const board of model.boards) {
      const level: SceneFloorSightLevel = {
        boardId: board.id,
        transform: this.floors.transform(model, board.id),
        elevation: elevations.get(board.id) ?? 0,
        slabs: [],
        holes: [],
        walls: [],
        openings: [],
      };

      for (const element of model.elements) {
        if (element.boardId !== board.id) {
          continue;
        }

        if (element.kind === 'terrain' && element.props.kind !== 'water' && element.shape.type !== 'point') {
          level.slabs.push(this.geometry.probe(element.shape));

          continue;
        }

        if (element.kind === 'opening') {
          if (element.shape.type === 'rect') {
            // Колодец — проём-лестница без целевого поля. Площадка с целевым
            // полем — настил, а не дыра в плите.
            if (element.props.kind === 'stairs' && element.targetBoardId === null) {
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
          level.walls.push(this.geometry.probe(element.shape));
        }
      }

      levels.set(board.id, level);
    }

    return {
      resolution,
      levels,
    };
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
    const eyeBody = Math.max(1, from.height ?? sceneBodyHeight(scene.resolution));
    const eye = fromLevel.elevation + sceneSightEyeIpari(eyeBody, scene.resolution) * scene.resolution;
    const body = Math.max(1, to.height ?? sceneBodyHeight(scene.resolution));
    const slabs = options.slabs !== false;

    // Цель видна, если видна любая точка её тела: голова может подниматься над
    // подоконником или краем колодца, когда ноги ещё закрыты.
    for (const share of BODY_SHARES) {
      const target = toLevel.elevation + body * share;
      if (this.clear(scene, from.boardId, rootFrom, rootTo, eye, target, slabs, options.cut)) {
        return true;
      }
    }

    return false;
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
  ): boolean {
    if (slabs && !this.clearSlabs(scene, from, to, eye, target)) {
      return false;
    }

    return this.clearWalls(scene, fromBoardId, from, to, cut);
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
   * Стены: стена этажа наблюдателя должна быть прорезана проёмом.
   *
   * Стены ниже этажа наблюдателя не мешают: он смотрит поверх них, а перекрытие
   * проверяет плита. Поэтому на пути проверяется только этаж, на котором стоит
   * наблюдатель: из комнаты двор виден через окно, а не через глухую стену.
   *
   * Луч идёт шагами, потому что стена — полоса, а не точка.
   */
  private clearWalls(
    scene: SceneFloorSightScene,
    fromBoardId: number,
    from: ScenePoint,
    to: ScenePoint,
    cut: SceneFloorSightOptions['cut'],
  ): boolean {
    const level = scene.levels.get(fromBoardId);
    if (level === undefined || level.walls.length === 0) {
      return true;
    }

    const distance = Math.hypot(to[0] - from[0], to[1] - from[1]);
    const step = Math.max(1, scene.resolution / 16);
    const steps = Math.max(2, Math.ceil(distance / step));

    for (let index = 1; index < steps; index += 1) {
      const t = index / steps;
      const point: ScenePoint = [from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t];

      // Стены разрезанного этажа внутри пятна плиты срезаны и в рисунке:
      // взгляд сквозь них проходит.
      if (cut !== undefined && cut.boardId === level.boardId && this.insideBounds(cut.bounds, point)) {
        continue;
      }

      const local = this.floors.toLocal(level.transform, point);
      if (!level.walls.some((probe) => probe.contains(local, 0))) {
        continue;
      }

      // Стена найдена: луч проходит, только если здесь проём.
      if (!level.openings.some((opening) => opening.probe.contains(local, opening.tolerance))) {
        return false;
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
