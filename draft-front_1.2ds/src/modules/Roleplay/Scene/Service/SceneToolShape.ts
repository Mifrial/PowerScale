import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';

/** Толщина стены по умолчанию, единицы сцены. */
const DEFAULT_WALL_WIDTH = 16;

/**
 * Сборка геометрии из протяжки указателя.
 *
 * Возвращает null, если фигура слишком мала для сохранения.
 */
export class SceneToolShape {
  /** Фигура по типу инструмента и двум точкам сцены. */
  build(
    shapeType: 'rect' | 'circle' | 'wall' | 'point' | 'polygon' | 'path',
    from: ScenePoint,
    to: ScenePoint,
    bounds: SceneBounds,
    wallWidth?: number,
    wallMode: 'line' | 'ring' = 'line',
  ): SceneShape | null {
    if (shapeType === 'polygon' || shapeType === 'path') {
      return null;
    }

    if (shapeType === 'point') {
      return { type: 'point', x: this.clamp(from[0], 0, bounds.width), y: this.clamp(from[1], 0, bounds.height) };
    }

    const startX = this.clamp(Math.round(from[0]), 0, bounds.width);
    const startY = this.clamp(Math.round(from[1]), 0, bounds.height);
    const endX = this.clamp(Math.round(to[0]), 0, bounds.width);
    const endY = this.clamp(Math.round(to[1]), 0, bounds.height);

    if (shapeType === 'rect') {
      const width = Math.abs(endX - startX);
      const height = Math.abs(endY - startY);
      if (width < 1 || height < 1) {
        return null;
      }

      return { type: 'rect', x: Math.min(startX, endX), y: Math.min(startY, endY), w: width, h: height };
    }

    if (shapeType === 'circle') {
      const requestedRadius = Math.round(Math.hypot(endX - startX, endY - startY));
      const radius = Math.min(requestedRadius, Math.floor(Math.min(bounds.width, bounds.height) / 2));
      if (radius < 1) {
        return null;
      }

      return {
        type: 'circle',
        cx: this.clamp(startX, radius, bounds.width - radius),
        cy: this.clamp(startY, radius, bounds.height - radius),
        r: radius,
      };
    }

    if (wallMode === 'ring') {
      const requestedRadius = Math.round(Math.hypot(endX - startX, endY - startY));
      const radius = Math.min(requestedRadius, Math.floor(Math.min(bounds.width, bounds.height) / 2));
      if (radius < 2) {
        return null;
      }

      const cx = this.clamp(startX, radius, bounds.width - radius);
      const cy = this.clamp(startY, radius, bounds.height - radius);

      return {
        type: 'wall',
        points: this.ringPoints(cx, cy, radius, bounds),
        width: Math.max(1, Math.round(wallWidth ?? DEFAULT_WALL_WIDTH)),
      };
    }

    if (startX === endX && startY === endY) {
      return null;
    }

    return {
      type: 'wall',
      points: [
        [startX, startY],
        [endX, endY],
      ],
      width: Math.max(1, Math.round(wallWidth ?? DEFAULT_WALL_WIDTH)),
    };
  }

  /**
   * Замкнутое кольцо стеной-лентой.
   *
   * Число точек выбирается по радиусу: отклонение хорды от окружности не
   * больше двух единиц сцены. При радиусе 5 ипари это 24–28 точек — на поле
   * 80×80 кольцо читается как круглое, а данные остаются целыми.
   */
  private ringPoints(cx: number, cy: number, radius: number, bounds: SceneBounds): ScenePoint[] {
    const step = Math.acos(Math.max(-1, Math.min(1, 1 - 2 / radius)));
    const count = Math.max(12, Math.min(48, Math.ceil(Math.PI / step)));
    const points: ScenePoint[] = [];
    for (let index = 0; index < count; index += 1) {
      const angle = (Math.PI * 2 * index) / count;
      points.push([
        this.clamp(cx + radius * Math.cos(angle), 0, bounds.width),
        this.clamp(cy + radius * Math.sin(angle), 0, bounds.height),
      ]);
    }

    points.push([points[0][0], points[0][1]]);

    return points;
  }

  /** Сжимает значение в диапазон. */
  private clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, Math.round(value)));
  }
}
