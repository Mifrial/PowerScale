import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneViewSize } from '@/modules/Roleplay/Scene/Dto/SceneViewSize';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';

/**
 * Преобразование координат сцены в экран и обратно.
 *
 * Класс не хранит состояние: вид передаётся и возвращается значением.
 */
export class SceneViewportService {
  constructor(
    private readonly minZoom = 0.02,
    private readonly maxZoom = 8,
  ) {}

  /** Координата сцены → экран. */
  sceneToScreen(viewport: SceneViewport, point: ScenePoint): { x: number; y: number } {
    return {
      x: (point[0] - viewport.x) * viewport.zoom + viewport.width / 2,
      y: (point[1] - viewport.y) * viewport.zoom + viewport.height / 2,
    };
  }

  /** Экран → координата сцены. */
  screenToScene(viewport: SceneViewport, point: { x: number; y: number }): ScenePoint {
    return [
      (point.x - viewport.width / 2) / viewport.zoom + viewport.x,
      (point.y - viewport.height / 2) / viewport.zoom + viewport.y,
    ];
  }

  /** Панорама на экранные пиксели. */
  pan(viewport: SceneViewport, deltaX: number, deltaY: number): SceneViewport {
    return {
      ...viewport,
      x: viewport.x - deltaX / viewport.zoom,
      y: viewport.y - deltaY / viewport.zoom,
    };
  }

  /** Масштаб вокруг экранной точки. */
  zoomAt(viewport: SceneViewport, screenPoint: { x: number; y: number }, factor: number): SceneViewport {
    const zoom = this.clampZoom(viewport.zoom * factor);
    if (zoom === viewport.zoom) {
      return viewport;
    }

    const scenePoint = this.screenToScene(viewport, screenPoint);

    return {
      ...viewport,
      zoom,
      x: scenePoint[0] - (screenPoint.x - viewport.width / 2) / zoom,
      y: scenePoint[1] - (screenPoint.y - viewport.height / 2) / zoom,
    };
  }

  /** Вписывает прямоугольник сцены в область просмотра. */
  fit(bounds: SceneBounds, viewSize: SceneViewSize, padding = 24): SceneViewport {
    const availableWidth = Math.max(viewSize.width - padding * 2, 1);
    const availableHeight = Math.max(viewSize.height - padding * 2, 1);
    const zoom = this.clampZoom(
      Math.min(availableWidth / Math.max(bounds.width, 1), availableHeight / Math.max(bounds.height, 1)),
    );

    return {
      x: bounds.x + bounds.width / 2,
      y: bounds.y + bounds.height / 2,
      zoom,
      width: viewSize.width,
      height: viewSize.height,
    };
  }

  /** Обновляет размеры вида. */
  resize(viewport: SceneViewport, viewSize: SceneViewSize): SceneViewport {
    return { ...viewport, width: viewSize.width, height: viewSize.height };
  }

  /**
   * Держит сцену в кадре, не давая утащить её целиком за край.
   *
   * @param viewport Текущий вид.
   * @param bounds Границы поля.
   * @param margin Экранный запас, пиксели.
   *
   * @returns Исправленный вид.
   */
  constrain(viewport: SceneViewport, bounds: SceneBounds, margin = 64): SceneViewport {
    const halfWidth = viewport.width / (2 * viewport.zoom);
    const halfHeight = viewport.height / (2 * viewport.zoom);
    const marginScene = margin / viewport.zoom;

    return {
      ...viewport,
      x: this.clampAxis(viewport.x, bounds.x, bounds.x + bounds.width, halfWidth, marginScene),
      y: this.clampAxis(viewport.y, bounds.y, bounds.y + bounds.height, halfHeight, marginScene),
    };
  }

  /** Сжимает значение в диапазон. */
  private clampAxis(value: number, min: number, max: number, half: number, margin: number): number {
    const lower = min + half - margin;
    const upper = max - half + margin;
    if (lower > upper) {
      return (min + max) / 2;
    }

    return Math.min(upper, Math.max(lower, value));
  }

  /** Сжимает масштаб. */
  private clampZoom(zoom: number): number {
    return Math.min(this.maxZoom, Math.max(this.minZoom, zoom));
  }
}
