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

    if (startX === endX && startY === endY) {
      return null;
    }

    return {
      type: 'wall',
      points: [
        [startX, startY],
        [endX, endY],
      ],
      width: DEFAULT_WALL_WIDTH,
    };
  }

  /** Сжимает значение в диапазон. */
  private clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, Math.round(value)));
  }
}
