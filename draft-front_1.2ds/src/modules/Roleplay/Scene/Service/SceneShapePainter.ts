import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneFillStyle } from '@/modules/Roleplay/Scene/Dto/SceneFillStyle';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneStrokeStyle } from '@/modules/Roleplay/Scene/Dto/SceneStrokeStyle';
import { Graphics } from 'pixi.js';

/**
 * Рисование фигур и элементов поля в Pixi `Graphics`.
 *
 * Класс не хранит состояние: стиль и масштаб приходят аргументами.
 */
export class SceneShapePainter {
  /** Рисует фигуру с необязательной заливкой и обводкой. */
  paint(
    graphics: Graphics,
    shape: SceneShape,
    fill: SceneFillStyle | undefined,
    stroke: SceneStrokeStyle | undefined,
    zoom: number,
  ): void {
    if (shape.type === 'rect') {
      graphics.rect(shape.x, shape.y, shape.w, shape.h);
      this.applyFill(graphics, fill);
      this.applyStroke(graphics, stroke);

      return;
    }

    if (shape.type === 'circle') {
      graphics.circle(shape.cx, shape.cy, shape.r);
      this.applyFill(graphics, fill);
      this.applyStroke(graphics, stroke);

      return;
    }

    if (shape.type === 'point') {
      graphics.circle(shape.x, shape.y, 9 / zoom);
      this.applyFill(graphics, fill);
      this.applyStroke(graphics, stroke);

      return;
    }

    if (shape.type === 'polygon') {
      graphics.poly(shape.points.flat());
      this.applyFill(graphics, fill);
      this.applyStroke(graphics, stroke);

      return;
    }

    this.drawPolyline(graphics, shape.points, shape.width, stroke ?? { color: 0x111827, width: shape.width, alpha: 1 });
  }

  /** Рисует элемент поля по его типу и свойствам. */
  paintElement(element: SceneElement, zoom: number): Graphics {
    const graphics = new Graphics();
    if (element.kind === 'opening') {
      this.paintOpening(graphics, element, zoom);

      return graphics;
    }

    const style = this.styleFor(element, zoom);
    this.paint(graphics, element.shape, style.fill, style.stroke, zoom);

    return graphics;
  }

  /** Проём: открыт — зелёный, закрыт — красный. */
  private paintOpening(graphics: Graphics, element: SceneElement, zoom: number): void {
    const isOpen = element.props.state === 'open';
    const color = isOpen ? 0x16a34a : 0xdc2626;
    this.paint(
      graphics,
      element.shape,
      undefined,
      {
        color,
        width: element.shape.type === 'wall' ? Math.max(4, element.shape.width) : 8,
        alpha: 0.9,
      },
      zoom,
    );
  }

  /** Стиль элемента по типу и террейну. */
  private styleFor(element: SceneElement, zoom: number): { fill: SceneFillStyle; stroke: SceneStrokeStyle } {
    if (element.kind === 'terrain') {
      return {
        fill: { color: this.terrainColor(element.props), alpha: 0.32 },
        stroke: { color: 0xf1f5f9, width: 1.5 / zoom, alpha: 0.6 },
      };
    }

    if (element.kind === 'support') {
      return {
        fill: { color: 0xb45309, alpha: 0.5 },
        stroke: { color: 0xfbbf24, width: 2 / zoom, alpha: 0.9 },
      };
    }

    if (element.kind === 'obstacle') {
      return {
        fill: { color: 0x475569, alpha: 0.9 },
        stroke: { color: 0xf1f5f9, width: 2 / zoom, alpha: 0.9 },
      };
    }

    if (element.kind === 'marker') {
      return {
        fill: { color: 0xf59e0b, alpha: 0.9 },
        stroke: { color: 0xfff7ed, width: 2 / zoom, alpha: 1 },
      };
    }

    return {
      fill: { color: 0xa78bfa, alpha: 0.85 },
      stroke: { color: 0xe9d5ff, width: 1 / zoom, alpha: 1 },
    };
  }

  /** Цвет шаблона террейна. */
  private terrainColor(props: SceneElementProps): number {
    switch (props.kind) {
      case 'dirt':
        return 0x92400e;
      case 'stone':
        return 0x64748b;
      case 'sand':
        return 0xd4a373;
      case 'mud':
        return 0x6b4f2a;
      case 'water':
        return 0x2563eb;
      case 'ice':
        return 0x93c5fd;
      default:
        return 0x4d7c0f;
    }
  }

  /** Ломаная с толщиной. */
  private drawPolyline(graphics: Graphics, points: ScenePoint[], width: number, stroke: SceneStrokeStyle): void {
    if (points.length === 0) {
      return;
    }

    graphics.moveTo(points[0][0], points[0][1]);
    for (let index = 1; index < points.length; index += 1) {
      graphics.lineTo(points[index][0], points[index][1]);
    }

    graphics.stroke({ color: stroke.color, width: Math.max(width, stroke.width), alpha: stroke.alpha });
  }

  /** Заливка, если задана. */
  private applyFill(graphics: Graphics, fill?: SceneFillStyle): void {
    if (fill !== undefined) {
      graphics.fill({ color: fill.color, alpha: fill.alpha });
    }
  }

  /** Обводка, если задана. */
  private applyStroke(graphics: Graphics, stroke?: SceneStrokeStyle): void {
    if (stroke !== undefined) {
      graphics.stroke({ color: stroke.color, width: stroke.width, alpha: stroke.alpha });
    }
  }
}
