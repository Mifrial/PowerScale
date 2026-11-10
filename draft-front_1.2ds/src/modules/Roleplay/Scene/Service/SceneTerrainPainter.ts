import type { Container } from 'pixi.js';
import { Graphics } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneTerrainPatternService } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';

/**
 * Материалы террейна: поверхность и кромка.
 *
 * Поверхность заливается процедурным паттерном в глобальном пространстве,
 * кромка рисуется под ней расширенным контуром или лентой.
 */
export class SceneTerrainPainter {
  constructor(private readonly patterns: SceneTerrainPatternService) {}

  /** Поверхность террейна без воды. */
  paintSurface(container: Container, element: SceneElement, resolution: number, zoom: number): Graphics {
    const graphics = new Graphics();
    this.trace(graphics, element.shape, zoom);
    graphics.fill({ fill: this.patterns.pattern(element.props.kind, element.id, resolution), alpha: 1 });
    container.addChild(graphics);

    return graphics;
  }

  /** Кромка террейна: расширенная лента или наружная полоса. */
  paintShore(container: Container, element: SceneElement, resolution: number, zoom: number): Graphics | null {
    const shoreKind = element.props.shoreKind ?? 'none';
    const width = element.props.shoreWidth ?? 0;
    if (shoreKind === 'none' || width <= 0) {
      return null;
    }

    const pattern = this.patterns.pattern(shoreKind, element.id + 17, resolution);
    const graphics = new Graphics();
    if (element.shape.type === 'path') {
      const tolerance = sceneCurveService.toleranceFor(zoom);
      const outline = sceneRibbonService.outline(
        element.shape.points,
        element.shape.handles ?? [],
        element.shape.widths,
        tolerance,
        width,
      );
      graphics.poly(outline.flat()).fill({ fill: pattern, alpha: 1 });
    } else {
      this.trace(graphics, element.shape, zoom);
      graphics.stroke({ fill: pattern, width: width * 2, alpha: 1, join: 'round', cap: 'round' });
    }

    container.addChild(graphics);

    return graphics;
  }

  /** Тонкая линия уреза поверх воды. */
  paintWaterEdge(container: Container, element: SceneElement, zoom: number): void {
    const graphics = new Graphics();
    if (element.shape.type === 'path') {
      const tolerance = sceneCurveService.toleranceFor(zoom);
      const outline = sceneRibbonService.outline(
        element.shape.points,
        element.shape.handles ?? [],
        element.shape.widths,
        tolerance,
      );
      graphics.poly(outline.flat()).stroke({ color: 0x0b2842, width: Math.max(1, 1.6 / zoom), alpha: 0.55 });
    } else {
      this.trace(graphics, element.shape, zoom);
      graphics.stroke({ color: 0x0b2842, width: Math.max(1, 1.6 / zoom), alpha: 0.55 });
    }

    container.addChild(graphics);
  }

  /** Путь фигуры в текущем контексте Graphics. */
  private trace(graphics: Graphics, shape: SceneShape, zoom: number): void {
    switch (shape.type) {
      case 'rect':
        graphics.rect(shape.x, shape.y, shape.w, shape.h);
        break;
      case 'circle':
        graphics.circle(shape.cx, shape.cy, shape.r);
        break;
      case 'polygon': {
        const tolerance = sceneCurveService.toleranceFor(zoom);
        const outline = sceneCurveService.flattenContour(shape.points, shape.handles ?? [], tolerance);
        graphics.poly(outline.flat()).closePath();
        break;
      }
      case 'path': {
        const tolerance = sceneCurveService.toleranceFor(zoom);
        const outline = sceneRibbonService.outline(shape.points, shape.handles ?? [], shape.widths, tolerance);
        graphics.poly(outline.flat()).closePath();
        break;
      }
      case 'point':
        graphics.circle(shape.x, shape.y, 9 / zoom);
        break;
      case 'wall':
        graphics.poly(shape.points.flat()).closePath();
        break;
    }
  }
}
