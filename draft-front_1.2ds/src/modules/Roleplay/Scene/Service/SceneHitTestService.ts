import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneGeometryService } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import type { SceneViewportService } from '@/modules/Roleplay/Scene/Service/SceneViewportService';

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
  ): SceneElement | null {
    return this.hits(model, viewport, screenPoint, tolerancePixels)[0] ?? null;
  }

  /** Все элементы под точкой, сверху вниз. */
  hits(
    model: SceneRenderModel,
    viewport: SceneViewport,
    screenPoint: { x: number; y: number },
    tolerancePixels = 8,
  ): SceneElement[] {
    const scenePoint = this.viewportService.screenToScene(viewport, screenPoint);
    const tolerance = tolerancePixels / viewport.zoom;
    const elements = model.elements
      .filter((element) => element.boardId === model.activeBoardId)
      .sort((left, right) => right.sortOrder - left.sortOrder || right.id - left.id);

    return elements.filter((element) => this.contains(element, scenePoint, tolerance));
  }

  /** Попадает ли точка в элемент. */
  contains(element: SceneElement, point: ScenePoint, tolerance: number): boolean {
    return this.geometryService.contains(element.shape, point, tolerance);
  }
}
