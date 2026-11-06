import type { CanvasPoint } from '@/modules/Roleplay/Map/Dto/CanvasPoint';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapSize } from '@/modules/Roleplay/Map/Dto/MapSize';
import type { MapViewport } from '@/modules/Roleplay/Map/Dto/MapViewport';
import { MAP_CANVAS_CONFIG } from '@/modules/Roleplay/Map/Constant/Canvas/MAP_CANVAS_CONFIG';

/**
 * Преобразование мира карты в экран и обратно: зум, панорама, вписывание.
 *
 * Класс не хранит состояние: вид передаётся и возвращается значением.
 */
export class MapViewportService {
  constructor(
    private readonly minScale: number = MAP_CANVAS_CONFIG.minScale,
    private readonly maxScale: number = MAP_CANVAS_CONFIG.maxScale,
  ) {}

  worldToScreen(viewport: MapViewport, point: MapPoint): CanvasPoint {
    return {
      x: point[0] * viewport.scale + viewport.offsetX,
      y: point[1] * viewport.scale + viewport.offsetY,
    };
  }

  screenToWorld(viewport: MapViewport, point: CanvasPoint): MapPoint {
    return [(point.x - viewport.offsetX) / viewport.scale, (point.y - viewport.offsetY) / viewport.scale];
  }

  pan(viewport: MapViewport, deltaX: number, deltaY: number): MapViewport {
    return { ...viewport, offsetX: viewport.offsetX + deltaX, offsetY: viewport.offsetY + deltaY };
  }

  /** Масштабирует вокруг точки экрана, сохраняя её мировую координату на месте. */
  zoomAt(viewport: MapViewport, screenPoint: CanvasPoint, factor: number): MapViewport {
    const scale = this.clampScale(viewport.scale * factor);
    if (scale === viewport.scale) {
      return viewport;
    }

    const worldPoint = this.screenToWorld(viewport, screenPoint);

    return {
      scale,
      offsetX: screenPoint.x - worldPoint[0] * scale,
      offsetY: screenPoint.y - worldPoint[1] * scale,
    };
  }

  /** Вписывает мир в видимую область с отступами. */
  fit(viewSize: MapSize, worldSize: MapSize, padding = 16): MapViewport {
    const availableWidth = Math.max(viewSize.width - padding * 2, 1);
    const availableHeight = Math.max(viewSize.height - padding * 2, 1);
    const scale = this.clampScale(
      Math.min(availableWidth / Math.max(worldSize.width, 1), availableHeight / Math.max(worldSize.height, 1)),
    );

    return {
      scale,
      offsetX: (viewSize.width - worldSize.width * scale) / 2,
      offsetY: (viewSize.height - worldSize.height * scale) / 2,
    };
  }

  /** Держит мир в кадре, не давая утащить его целиком за край. */
  constrain(viewport: MapViewport, viewSize: MapSize, worldSize: MapSize): MapViewport {
    const worldWidth = worldSize.width * viewport.scale;
    const worldHeight = worldSize.height * viewport.scale;
    const margin = 48;

    return {
      scale: viewport.scale,
      offsetX: this.clampOffset(viewport.offsetX, worldWidth, viewSize.width, margin),
      offsetY: this.clampOffset(viewport.offsetY, worldHeight, viewSize.height, margin),
    };
  }

  private clampScale(scale: number): number {
    return Math.min(this.maxScale, Math.max(this.minScale, scale));
  }

  private clampOffset(offset: number, contentSize: number, viewSize: number, margin: number): number {
    if (contentSize <= viewSize - margin * 2) {
      return (viewSize - contentSize) / 2;
    }

    return Math.min(margin, Math.max(viewSize - contentSize - margin, offset));
  }
}
