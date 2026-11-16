import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneGeometryService } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import type { SceneViewportService } from '@/modules/Roleplay/Scene/Service/SceneViewportService';
import {
  sceneDecorFootprint,
  sceneDecorSilhouette,
  spriteSize,
  type SceneWorldFootprint,
} from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';
import { isSceneRoof } from '@/modules/Roleplay/Scene/Service/SceneHeight';
import { SCENE_ROOF } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ROOF';

/**
 * CPU-hit-test по геометрии активного поля.
 */
export class SceneHitTestService {
  constructor(
    private readonly viewportService: SceneViewportService,
    private readonly geometryService: SceneGeometryService,
  ) {}

  /** Верхний элемент под экранной точкой. */
  hit(
    model: SceneRenderModel,
    viewport: SceneViewport,
    screenPoint: { x: number; y: number },
    tolerancePixels = 8,
    aspectOf?: (element: SceneElement) => number | null,
  ): SceneElement | null {
    return this.hits(model, viewport, screenPoint, tolerancePixels, aspectOf)[0] ?? null;
  }

  /** Все элементы под точкой, сверху вниз. */
  hits(
    model: SceneRenderModel,
    viewport: SceneViewport,
    screenPoint: { x: number; y: number },
    tolerancePixels = 8,
    aspectOf?: (element: SceneElement) => number | null,
  ): SceneElement[] {
    const scenePoint = this.viewportService.screenToScene(viewport, screenPoint);
    const tolerance = tolerancePixels / viewport.zoom;
    const elements = model.elements
      .filter((element) => element.boardId === model.activeBoardId)
      .sort((left, right) => right.sortOrder - left.sortOrder || right.id - left.id);
    // Крыша накрывает дом целиком, поэтому в очереди она идёт после всего,
    // что под ней, но до террейна: свес лежит над землёй, и без этого клик по
    // свесу доставал бы луг, а не накрытие. Середина крыши кликом не берётся —
    // её видно, но выделяется она за край.
    const ordered = [
      ...elements.filter((element) => !isSceneRoof(element) && element.kind !== 'terrain'),
      ...elements.filter((element) => isSceneRoof(element)),
      ...elements.filter((element) => element.kind === 'terrain' && !isSceneRoof(element)),
    ];

    return ordered.filter((element) =>
      this.contains(element, scenePoint, tolerance, model.resolution, aspectOf?.(element) ?? null),
    );
  }

  /** Попадает ли точка в элемент. */
  contains(
    element: SceneElement,
    point: ScenePoint,
    tolerance: number,
    resolution = 64,
    aspect: number | null = null,
  ): boolean {
    // Крыша выделяется за свес: середина накрытия принадлежит тому, что под
    // ней. Иначе после первой крыши дом перестал бы выделяться изнутри.
    if (isSceneRoof(element)) {
      return this.roofEdgeContains(element, point, tolerance, resolution);
    }

    // Декор выделяется по своей видимой зоне, а не по точке якоря: иначе
    // дерево приходится искать под стволом. Спрайт рисуется выше якоря,
    // поэтому его видимая коробка — тоже цель; основание и силуэт — та же
    // геометрия, что видит расчёт хода и обзора.
    if (element.kind === 'decor' && element.shape.type === 'point') {
      if (aspect !== null && this.spriteContains(element, point, tolerance, resolution, aspect)) {
        return true;
      }

      const footprint = sceneDecorFootprint(element, resolution);
      if (footprint !== null && this.areaContains(footprint, point, tolerance)) {
        return true;
      }

      const silhouette = sceneDecorSilhouette(element, resolution);
      if (silhouette !== null && this.areaContains(silhouette.area, point, tolerance)) {
        return true;
      }

      return Math.hypot(point[0] - element.shape.x, point[1] - element.shape.y) <= tolerance;
    }

    return this.geometryService.contains(element.shape, point, tolerance, sceneRotation(element));
  }

  /**
   * Крыша под точкой: только её край.
   *
   * Свес — единственная часть накрытия, которая никому не принадлежит: под ним
   * луг или двор. Поэтому крыша выделяется полосой у края, а середина остаётся
   * полу, стенам и декору под ней.
   */
  private roofEdgeContains(element: SceneElement, point: ScenePoint, tolerance: number, resolution: number): boolean {
    const band = Math.max(tolerance, resolution * SCENE_ROOF.grabBandIpari);
    const shape = element.shape;
    if (shape.type === 'circle') {
      const distance = Math.hypot(point[0] - shape.cx, point[1] - shape.cy);

      return Math.abs(distance - shape.r) <= band;
    }

    const outline = this.geometryService.points(shape, sceneRotation(element));
    if (outline.length < 2) {
      return false;
    }

    return this.geometryService.distanceToPolyline(point, outline, shape.type !== 'wall') <= band;
  }

  /** Точка в видимой коробке спрайта: ширина — `scale`, высота — по аспекту. */
  private spriteContains(
    element: SceneElement,
    point: ScenePoint,
    tolerance: number,
    resolution: number,
    aspect: number,
  ): boolean {
    if (element.shape.type !== 'point') {
      return false;
    }

    const width = spriteSize(element, resolution);
    const height = width * Math.max(0.05, aspect);
    const left = element.shape.x - width / 2 - tolerance;
    const right = element.shape.x + width / 2 + tolerance;
    const top = element.shape.y - height - tolerance;
    const bottom = element.shape.y + tolerance;

    return point[0] >= left && point[0] <= right && point[1] >= top && point[1] <= bottom;
  }

  /** Точка внутри мировой формы основания или рядом с её краем. */
  private areaContains(area: SceneWorldFootprint, point: ScenePoint, tolerance: number): boolean {
    if (area.shape === 'circle') {
      return Math.hypot(point[0] - area.center[0], point[1] - area.center[1]) <= area.radius + tolerance;
    }

    return (
      this.geometryService.pointInPolygon(point, area.points) ||
      this.geometryService.distanceToPolyline(point, area.points, true) <= tolerance
    );
  }
}
