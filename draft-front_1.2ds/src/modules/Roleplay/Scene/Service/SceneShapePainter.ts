import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneFillStyle } from '@/modules/Roleplay/Scene/Dto/SceneFillStyle';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneStrokeStyle } from '@/modules/Roleplay/Scene/Dto/SceneStrokeStyle';
import { Graphics } from 'pixi.js';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';

/** Цвета стены и проёмов. */
const WALL_FILL = 0x475569;
const WALL_EDGE = 0xe2e8f0;
const OPENING_FRAME = 0x78350f;
const OPENING_EDGE = 0xd6a06a;
const DOOR_LEAF = 0x7c2d12;
const DOOR_OPEN = 0xa16207;
const GATE_LEAF = 0x44403c;
const WINDOW_GLASS = 0x7dd3fc;
const WINDOW_SILL = 0xe2e8f0;

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
    rotation = 0,
  ): void {
    if (shape.type === 'rect') {
      graphics.rect(shape.x, shape.y, shape.w, shape.h);
      this.applyFill(graphics, fill);
      this.applyStroke(graphics, stroke);
      this.applyRotation(graphics, shape, rotation);

      return;
    }

    if (shape.type === 'circle') {
      graphics.circle(shape.cx, shape.cy, shape.r);
      this.applyFill(graphics, fill);
      this.applyStroke(graphics, stroke);
      this.applyRotation(graphics, shape, rotation);

      return;
    }

    if (shape.type === 'point') {
      graphics.circle(shape.x, shape.y, 9 / zoom);
      this.applyFill(graphics, fill);
      this.applyStroke(graphics, stroke);
      this.applyRotation(graphics, shape, rotation);

      return;
    }

    if (shape.type === 'polygon') {
      const tolerance = sceneCurveService.toleranceFor(zoom);
      const outline = sceneCurveService.flattenContour(shape.points, shape.handles ?? [], tolerance);
      graphics.poly(outline.flat());
      this.applyFill(graphics, fill);
      this.applyStroke(graphics, stroke);
      this.applyRotation(graphics, shape, rotation);

      return;
    }

    if (shape.type === 'path') {
      const tolerance = sceneCurveService.toleranceFor(zoom);
      const outline = sceneRibbonService.outline(shape.points, shape.handles ?? [], shape.widths, tolerance);
      graphics.poly(outline.flat());
      this.applyFill(graphics, fill);
      this.applyStroke(graphics, stroke);
      this.applyRotation(graphics, shape, rotation);

      return;
    }

    this.drawPolyline(graphics, shape.points, shape.width, stroke ?? { color: 0x111827, width: shape.width, alpha: 1 });
    this.applyRotation(graphics, shape, rotation);
  }

  /** Поворот нарисованной фигуры вокруг центра прямоугольника. */
  private applyRotation(graphics: Graphics, shape: SceneShape, rotation: number): void {
    if (shape.type !== 'rect' || ((rotation % 360) + 360) % 360 === 0) {
      return;
    }

    const centerX = shape.x + shape.w / 2;
    const centerY = shape.y + shape.h / 2;
    graphics.pivot.set(centerX, centerY);
    graphics.position.set(centerX, centerY);
    graphics.rotation = (rotation * Math.PI) / 180;
  }

  /** Рисует элемент поля по его типу и свойствам. */
  paintElement(element: SceneElement, zoom: number): Graphics {
    const graphics = new Graphics();
    if (element.kind === 'opening') {
      this.paintOpening(graphics, element, zoom);

      return graphics;
    }

    const style = this.styleFor(element, zoom);
    this.paint(graphics, element.shape, style.fill, style.stroke, zoom, sceneRotation(element));

    return graphics;
  }

  /**
   * Стена-лента: заливка по контуру ленты, а не линия.
   *
   * Линия с толщиной рисует только обводку и не даёт стене объёма; контур
   * ленты даёт настоящую полосу с углами, как у реки.
   */
  paintWall(shape: SceneShape, zoom: number): Graphics {
    const graphics = new Graphics();
    if (shape.type !== 'wall' || shape.points.length < 2) {
      return graphics;
    }

    const widths = shape.points.map(() => Math.max(1, shape.width));
    const outline = sceneRibbonService.outline(shape.points, [], widths, sceneCurveService.toleranceFor(zoom));
    if (outline.length < 3) {
      return graphics;
    }

    graphics.poly(outline.flat());
    graphics.fill({ color: WALL_FILL, alpha: 0.95 });
    graphics.stroke({ color: WALL_EDGE, width: Math.max(1, 1.5 / zoom), alpha: 0.85 });

    return graphics;
  }

  /**
   * Уголок стыка.
   *
   * Две отдельные ленты встречаются прямоугольными торцами и оставляют
   * квадратную щель на внешнем углу. Квадрат по толщине закрывает её и
   * совпадает с рёбрами стен на прямом угле.
   */
  paintWallJoint(point: ScenePoint, width: number, _zoom: number): Graphics {
    const graphics = new Graphics();
    const size = Math.max(1, width);
    graphics.rect(point[0] - size / 2, point[1] - size / 2, size, size);
    graphics.fill({ color: WALL_FILL, alpha: 0.95 });

    return graphics;
  }

  /**
   * Проём: рама и полотно двери или окно с подоконником.
   *
   * Проём стоит на вырезанном участке стены. Дверь закрыта — полотно лежит
   * вдоль стены; открыта — полотно повёрнуто внутрь и видна дуга хода. Окно —
   * стеклянная полоса с рамой и подоконником, сквозь него видно, но не пройти.
   */
  private paintOpening(graphics: Graphics, element: SceneElement, zoom: number): void {
    if (element.shape.type !== 'wall' || element.shape.points.length < 2) {
      this.paintFreeOpening(graphics, element, zoom);

      return;
    }

    const shape = element.shape;
    const start = shape.points[0];
    const end = shape.points[shape.points.length - 1];
    const dx = end[0] - start[0];
    const dy = end[1] - start[1];
    const length = Math.hypot(dx, dy);
    if (length < 1) {
      return;
    }

    const dir: ScenePoint = [dx / length, dy / length];
    const normal: ScenePoint = [-dir[1], dir[0]];
    const thickness = Math.max(1, shape.width);
    const frame = Math.min(Math.max(2, thickness * 0.32), Math.max(2, length / 3));
    const kind = element.props.kind ?? 'door';
    const open = element.props.state === 'open';

    this.paintBand(graphics, start, dir, normal, 0, frame, thickness / 2, OPENING_FRAME, 1);
    this.paintBand(graphics, start, dir, normal, length - frame, length, thickness / 2, OPENING_FRAME, 1);

    if (kind === 'window') {
      this.paintBand(graphics, start, dir, normal, frame, length - frame, thickness * 0.42, WINDOW_GLASS, 0.55);
      // Подоконник: две линии по краям выреза.
      this.paintLine(
        graphics,
        this.pointOn(start, dir, normal, frame, thickness * 0.42),
        this.pointOn(start, dir, normal, length - frame, thickness * 0.42),
        WINDOW_SILL,
        Math.max(1, 2 / zoom),
        0.9,
      );
      this.paintLine(
        graphics,
        this.pointOn(start, dir, normal, frame, -thickness * 0.42),
        this.pointOn(start, dir, normal, length - frame, -thickness * 0.42),
        WINDOW_SILL,
        Math.max(1, 2 / zoom),
        0.9,
      );
      // Середина рамы поперёк окна.
      this.paintLine(
        graphics,
        this.pointOn(start, dir, normal, length / 2, thickness / 2),
        this.pointOn(start, dir, normal, length / 2, -thickness / 2),
        OPENING_FRAME,
        Math.max(1, 2 / zoom),
        0.95,
      );

      return;
    }

    const leafColor = kind === 'gate' ? GATE_LEAF : open ? DOOR_OPEN : DOOR_LEAF;
    if (!open) {
      this.paintBand(graphics, start, dir, normal, frame, length - frame, thickness * 0.42, leafColor, 0.95);
      this.paintLine(
        graphics,
        this.pointOn(start, dir, normal, frame, 0),
        this.pointOn(start, dir, normal, length - frame, 0),
        OPENING_EDGE,
        Math.max(1, 1.5 / zoom),
        0.8,
      );

      return;
    }

    // Открытая дверь: полотно у первого наличника и дуга хода.
    const leaf = Math.max(1, length - frame * 2);
    this.paintBand(
      graphics,
      this.pointOn(start, dir, normal, frame, 0),
      normal,
      dir,
      0,
      leaf,
      Math.max(2, thickness * 0.22),
      leafColor,
      0.95,
    );
    const hinge = this.pointOn(start, dir, normal, frame, 0);
    const from = Math.atan2(dir[1], dir[0]);
    graphics.moveTo(hinge[0] + Math.cos(from) * leaf, hinge[1] + Math.sin(from) * leaf);
    graphics.arc(hinge[0], hinge[1], leaf, from, from + Math.PI / 2);
    graphics.stroke({ color: OPENING_EDGE, width: Math.max(1, 1.5 / zoom), alpha: 0.55 });
  }

  /** Старый свободный проём без стены: цветная полоса. */
  private paintFreeOpening(graphics: Graphics, element: SceneElement, zoom: number): void {
    const isOpen = element.props.state === 'open';
    this.paint(
      graphics,
      element.shape,
      undefined,
      {
        color: isOpen ? 0x16a34a : 0xdc2626,
        width: element.shape.type === 'wall' ? Math.max(4, element.shape.width) : 8,
        alpha: 0.9,
      },
      zoom,
    );
  }

  /** Полоса вдоль осевой: `from…to` и полуширина поперёк. */
  private paintBand(
    graphics: Graphics,
    origin: ScenePoint,
    dir: ScenePoint,
    normal: ScenePoint,
    from: number,
    to: number,
    half: number,
    color: number,
    alpha: number,
  ): void {
    if (to <= from || half <= 0) {
      return;
    }

    const a = this.pointOn(origin, dir, normal, from, half);
    const b = this.pointOn(origin, dir, normal, to, half);
    const c = this.pointOn(origin, dir, normal, to, -half);
    const d = this.pointOn(origin, dir, normal, from, -half);
    graphics.poly([a, b, c, d].flat());
    graphics.fill({ color, alpha });
  }

  /** Точка в системе осевой: вдоль и поперёк. */
  private pointOn(origin: ScenePoint, dir: ScenePoint, normal: ScenePoint, along: number, across: number): ScenePoint {
    return [origin[0] + dir[0] * along + normal[0] * across, origin[1] + dir[1] * along + normal[1] * across];
  }

  /** Отрезок в мировых координатах. */
  private paintLine(
    graphics: Graphics,
    from: ScenePoint,
    to: ScenePoint,
    color: number,
    width: number,
    alpha: number,
  ): void {
    graphics.moveTo(from[0], from[1]);
    graphics.lineTo(to[0], to[1]);
    graphics.stroke({ color, width, alpha });
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
