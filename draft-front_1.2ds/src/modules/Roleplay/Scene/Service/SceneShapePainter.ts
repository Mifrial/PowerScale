import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneFillStyle } from '@/modules/Roleplay/Scene/Dto/SceneFillStyle';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneStrokeStyle } from '@/modules/Roleplay/Scene/Dto/SceneStrokeStyle';
import type { SceneWallBand, SceneWallOutline } from '@/modules/Roleplay/Scene/Service/SceneWallService';
import { Graphics } from 'pixi.js';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';
import { SCENE_ROOF } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ROOF';
import { SCENE_STAIRS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_STAIRS';
import { SCENE_WALL_MATERIAL } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_WALL_MATERIAL';

/** Цвета стены и проёмов. */
const SHADOW_COLOR = 0x111827;
const OPENING_JAMB = 0x7a5c3e;
const OPENING_EDGE = 0x8b6a45;
const DOOR_LEAF = 0x7c2d12;
const DOOR_LINE = 0xd6a06a;
const GATE_LEAF = 0x44403c;
const WINDOW_GLASS = 0x7dd3fc;
const WINDOW_SILL = 0xdfe7ef;

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

    if (element.kind === 'support' && element.props.kind === 'roof') {
      this.paintRoof(graphics, element, zoom);

      return graphics;
    }

    const style = this.styleFor(element, zoom);
    this.paint(graphics, element.shape, style.fill, style.stroke, zoom, sceneRotation(element));

    return graphics;
  }

  /**
   * Крыша: накрытие, край свеса и конёк вдоль длинной стороны.
   *
   * Плоская заливка на виде сверху читается как плита, поэтому объём
   * возвращает конёк — одна линия по центру вдоль длинной стороны.
   * Проходимая крыша — настил: конька у неё нет, вместо него швы досок.
   */
  private paintRoof(graphics: Graphics, element: SceneElement, zoom: number): void {
    const walkable = element.props.walkable === true;
    this.paint(
      graphics,
      element.shape,
      { color: walkable ? SCENE_ROOF.deckColor : SCENE_ROOF.color, alpha: walkable ? 1 : 0.94 },
      { color: walkable ? SCENE_ROOF.deckEdgeColor : SCENE_ROOF.edgeColor, width: 2 / zoom, alpha: 1 },
      zoom,
      sceneRotation(element),
    );

    const shape = element.shape;
    if (shape.type !== 'rect') {
      return;
    }

    const horizontal = shape.w >= shape.h;
    if (walkable) {
      // Доски идут поперёк длинной стороны: шаг — четверть короткой стороны,
      // чтобы швы читались на любом размере крыши, но не превращались в
      // штриховку.
      const step = Math.max(8, Math.min(shape.w, shape.h) / 4);
      if (horizontal) {
        for (let x = shape.x + step; x < shape.x + shape.w; x += step) {
          this.paintLine(
            graphics,
            [x, shape.y],
            [x, shape.y + shape.h],
            SCENE_ROOF.deckSeamColor,
            1 / zoom,
            SCENE_ROOF.deckSeamAlpha,
          );
        }
      } else {
        for (let y = shape.y + step; y < shape.y + shape.h; y += step) {
          this.paintLine(
            graphics,
            [shape.x, y],
            [shape.x + shape.w, y],
            SCENE_ROOF.deckSeamColor,
            1 / zoom,
            SCENE_ROOF.deckSeamAlpha,
          );
        }
      }

      return;
    }

    const from: ScenePoint = horizontal ? [shape.x, shape.y + shape.h / 2] : [shape.x + shape.w / 2, shape.y];
    const to: ScenePoint = horizontal
      ? [shape.x + shape.w, shape.y + shape.h / 2]
      : [shape.x + shape.w / 2, shape.y + shape.h];
    this.paintLine(graphics, from, to, SCENE_ROOF.ridgeColor, 1.5 / zoom, SCENE_ROOF.ridgeAlpha);
  }

  /**
   * Верхняя грань стен: плоская заливка и швы материала.
   *
   * Заливка идёт по отдельным полосам, но цвет у всех полос материала один,
   * поэтому нахлёст соседних стен не виден: в углу получается одна заливка,
   * а не два прямоугольника. Силуэт и обводку рисует `paintWallOutline`.
   */
  paintWallTop(bands: readonly SceneWallBand[], resolution: number, zoom: number): Graphics {
    const graphics = new Graphics();
    for (const band of bands) {
      if (band.polygon.length < 3) {
        continue;
      }

      const palette = this.wallPalette(this.wallMaterialKey(band.material));
      graphics.poly(band.polygon.flat()).fill({ color: palette.base, alpha: 1 });
    }

    // Швы — вторым проходом: заливка соседней полосы не должна их стирать.
    for (const band of bands) {
      const palette = this.wallPalette(this.wallMaterialKey(band.material));
      if (palette.seamStepIpari <= 0) {
        continue;
      }

      this.paintWallSeams(
        graphics,
        band.elementId,
        band.center,
        band.thickness,
        palette.seam,
        palette.seamStepIpari,
        resolution,
        zoom,
      );
    }

    return graphics;
  }

  /**
   * Обводка силуэта стен.
   *
   * Кольца уже сведены булевой геометрией (`SceneWallService.layout`), поэтому
   * линия одна на всю сеть: внешний край и край со стороны комнаты. Обводки
   * каждой ленты и торцов на стыках нет — именно они и превращали угол в два
   * наезжающих прямоугольника.
   */
  paintWallOutline(outlines: readonly SceneWallOutline[], zoom: number): Graphics {
    const graphics = new Graphics();
    for (const outline of outlines) {
      const palette = this.wallPalette(this.wallMaterialKey(outline.material));
      const width = Math.max(1, outline.thickness * SCENE_WALL_MATERIAL.edgeShare, 1 / zoom);
      for (const polygon of outline.polygons) {
        for (const ring of polygon) {
          this.strokeRing(graphics, ring, palette.edge, width);
        }
      }
    }

    return graphics;
  }

  /**
   * Тень стены.
   *
   * Кольца приходят уже как сдвинутая копия силуэта без него самого, поэтому
   * тень не ложится под стену и не двоится на углах. Дырок в них не бывает:
   * разность тонкого кольца с самим собой даёт серпы, а не замкнутые контуры.
   */
  paintWallShadow(polygons: readonly ScenePoint[][][], alpha: number): Graphics {
    const graphics = new Graphics();
    for (const polygon of polygons) {
      const outer = polygon[0];
      if (outer === undefined || outer.length < 3) {
        continue;
      }

      graphics.poly(outer.flat()).fill({ color: SHADOW_COLOR, alpha });
    }

    return graphics;
  }

  /** Палитра материала стены; неизвестный материал получает умолчание. */
  private wallPalette(material: string) {
    const materials: Record<string, { base: number; edge: number; seam: number; seamStepIpari: number }> =
      SCENE_WALL_MATERIAL.materials;

    return materials[material] ?? materials[SCENE_WALL_MATERIAL.default];
  }

  /** Ключ материала: неизвестное значение в старых сценах заменяется умолчанием. */
  private wallMaterialKey(material: string | undefined): string {
    return material !== undefined && material in SCENE_WALL_MATERIAL.materials ? material : SCENE_WALL_MATERIAL.default;
  }

  /** Обводка замкнутого кольца силуэта. */
  private strokeRing(graphics: Graphics, ring: readonly ScenePoint[], color: number, width: number): void {
    if (ring.length < 2) {
      return;
    }

    graphics.moveTo(ring[0][0], ring[0][1]);
    for (let index = 1; index < ring.length; index += 1) {
      graphics.lineTo(ring[index][0], ring[index][1]);
    }

    graphics.closePath();
    graphics.stroke({ color, width, alpha: 0.9, join: 'miter', cap: 'butt' });
  }

  /**
   * Швы материала: поперечные линии через полосу.
   *
   * Шаг — в ипари от материала: у досок редкие торцы, у кирпича частая
   * перевязка. Сдвиг детерминирован от id стены, поэтому швы не выстраиваются
   * в линейку у соседних стен.
   */
  private paintWallSeams(
    graphics: Graphics,
    elementId: number,
    points: readonly ScenePoint[],
    thickness: number,
    color: number,
    stepIpari: number,
    resolution: number,
    zoom: number,
  ): void {
    const step = Math.max(1, resolution * stepIpari);
    const seed = Math.abs(Math.trunc(elementId)) * 2654435761;
    const jitter = ((seed % 1024) / 1024) * step * 0.5;
    const line = Math.max(1, 1 / zoom);
    const half = Math.max(1, thickness) / 2;
    let distance = jitter;
    for (let index = 1; index < points.length; index += 1) {
      const from = points[index - 1];
      const to = points[index];
      const dx = to[0] - from[0];
      const dy = to[1] - from[1];
      const length = Math.hypot(dx, dy);
      if (length < 1e-6) {
        continue;
      }

      const dirX = dx / length;
      const dirY = dy / length;
      const normalX = -dirY;
      const normalY = dirX;
      while (distance <= length) {
        const x = from[0] + dirX * distance;
        const y = from[1] + dirY * distance;
        graphics.moveTo(x - normalX * half, y - normalY * half);
        graphics.lineTo(x + normalX * half, y + normalY * half);
        distance += step;
      }

      distance -= length;
      if (distance <= 0) {
        distance = step;
      }
    }

    graphics.stroke({ color, width: line, alpha: 0.4, cap: 'butt' });
  }

  /**
   * Проём: рама и полотно двери или окно с подоконником.
   *
   * Проём стоит на вырезанном участке стены. Дверь закрыта — полотно лежит
   * вдоль стены; открыта — полотно повёрнуто внутрь и видна дуга хода. Окно —
   * стеклянная полоса с рамой и подоконником, сквозь него видно, но не пройти.
   */
  private paintOpening(graphics: Graphics, element: SceneElement, zoom: number): void {
    if (element.shape.type === 'rect') {
      this.paintFloorOpening(graphics, element, zoom);

      return;
    }

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
    const frame = Math.min(Math.max(2, thickness * 0.3), Math.max(2, length / 3));
    const kind = element.props.kind ?? 'door';
    const open = element.props.state === 'open';

    // Откосы: срезанные торцы стены. Они темнее верхней грани — так проём
    // читается разрывом стены, а не накладкой поверх неё.
    this.paintBand(graphics, start, dir, normal, 0, frame, thickness / 2, OPENING_JAMB, 1);
    this.paintBand(graphics, start, dir, normal, length - frame, length, thickness / 2, OPENING_JAMB, 1);

    if (kind === 'window') {
      this.paintWindow(graphics, start, dir, normal, length, thickness, frame, zoom);

      return;
    }

    const leafColor = kind === 'gate' ? GATE_LEAF : DOOR_LEAF;
    if (!open) {
      this.paintBand(graphics, start, dir, normal, frame, length - frame, thickness * 0.34, leafColor, 0.95);
      this.paintLine(
        graphics,
        this.pointOn(start, dir, normal, frame, 0),
        this.pointOn(start, dir, normal, length - frame, 0),
        DOOR_LINE,
        Math.max(1, 1 / zoom),
        0.7,
      );

      return;
    }

    // Открытая дверь: полотно у первого откоса и тонкая дуга хода. Полотно
    // узкое, как настоящая створка: широкий клин читался бы подметённым следом.
    const leaf = Math.max(1, length - frame * 2);
    const hinge = this.pointOn(start, dir, normal, frame, 0);
    const leafHalf = Math.max(1, thickness * 0.13);
    this.paintBand(graphics, hinge, normal, dir, 0, leaf, leafHalf, leafColor, 1);
    this.paintLine(
      graphics,
      this.pointOn(hinge, normal, dir, 0, 0),
      this.pointOn(hinge, normal, dir, leaf, 0),
      DOOR_LINE,
      Math.max(1, 1 / zoom),
      0.5,
    );

    const from = Math.atan2(dir[1], dir[0]);
    graphics.moveTo(hinge[0] + Math.cos(from) * leaf, hinge[1] + Math.sin(from) * leaf);
    graphics.arc(hinge[0], hinge[1], leaf, from, from + Math.PI / 2);
    graphics.stroke({ color: OPENING_EDGE, width: Math.max(1, 1.2 / zoom), alpha: 0.55 });
  }

  /**
   * Окно: стеклянная щель в стене, а не белая плита поперёк неё.
   *
   * Сверху окно — проём с тонкими рамами по краям выреза: светлая полоса
   * стекла между двумя узкими импостами. Толстые подоконники по сторонам
   * делали окно шире стены и читались второй стеной.
   */
  private paintWindow(
    graphics: Graphics,
    start: ScenePoint,
    dir: ScenePoint,
    normal: ScenePoint,
    length: number,
    thickness: number,
    frame: number,
    zoom: number,
  ): void {
    const glassHalf = Math.max(1, thickness * 0.2);
    const rail = Math.max(1, 1.1 / zoom);

    this.paintBand(graphics, start, dir, normal, frame, length - frame, glassHalf, WINDOW_GLASS, 0.5);
    this.paintLine(
      graphics,
      this.pointOn(start, dir, normal, frame, glassHalf),
      this.pointOn(start, dir, normal, length - frame, glassHalf),
      WINDOW_SILL,
      rail,
      0.9,
    );
    this.paintLine(
      graphics,
      this.pointOn(start, dir, normal, frame, -glassHalf),
      this.pointOn(start, dir, normal, length - frame, -glassHalf),
      WINDOW_SILL,
      rail,
      0.9,
    );

    // Импост посередине: два стекла вместо одной длинной полосы. У короткого
    // окна он не нужен — занял бы весь проём.
    if (length - frame * 2 > thickness * 3) {
      this.paintLine(
        graphics,
        this.pointOn(start, dir, normal, length / 2, glassHalf),
        this.pointOn(start, dir, normal, length / 2, -glassHalf),
        OPENING_JAMB,
        rail,
        0.95,
      );
    }
  }

  /**
   * Проём в полу: площадка перехода или лестничный проём.
   *
   * Площадка — верхняя ступень марша и одновременно дверь на другой этаж.
   * Заливки и рамки у неё нет: плита этажа под площадкой прорезана, сквозь
   * вырез видны настоящие ступени, а постоянная рамка только спорила с ними.
   * В предпросмотре площадку подсвечивает наведение мыши, в редакторе остаётся
   * маленькая метка-ромб — иначе невыделенный портал не найти на поле.
   *
   * Проём без цели — дырка в полу: тёмный колодец с убегающими вниз ступенями.
   */
  private paintFloorOpening(graphics: Graphics, element: SceneElement, zoom: number): void {
    if (element.shape.type !== 'rect' || element.props.kind !== 'stairs') {
      this.paintFreeOpening(graphics, element, zoom);

      return;
    }

    const { x, y, w, h } = element.shape;
    const horizontal = w >= h;
    const inset = Math.max(1, Math.min(w, h) * 0.06);
    const line = Math.max(1, 2 / zoom);

    if (element.targetBoardId !== null) {
      // Метка площадки: только ромб, без рамки.
      const cx = x + w / 2;
      const cy = y + h / 2;
      const radius = Math.max(2, Math.min(w, h) * 0.12);
      graphics
        .poly([cx, cy - radius, cx + radius, cy, cx, cy + radius, cx - radius, cy])
        .fill({ color: SCENE_STAIRS.landingMark, alpha: 0.8 });

      return;
    }

    graphics.rect(x, y, w, h).fill({ color: SCENE_STAIRS.shaftFill });
    graphics
      .rect(x + inset, y + inset, Math.max(1, w - inset * 2), Math.max(1, h - inset * 2))
      .fill({ color: SCENE_STAIRS.shaftShadow });

    // Ступени уходят вниз от ближней кромки: проступь темнеет в глубину,
    // носок светится только у входа. Направление условное — у проёма без цели
    // нет данных о том, с какой стороны марш; главное, что это ступени, а не
    // чёрная дыра с линиями.
    const steps = 7;
    const alongX = horizontal;
    const runInner = (alongX ? w : h) - inset * 2;
    const tread = runInner / steps;
    const start = (alongX ? x : y) + inset;
    const crossStart = (alongX ? y : x) + inset;
    const crossSize = (alongX ? h : w) - inset * 2;
    const tones = SCENE_STAIRS.shaftTreadTones;
    for (let index = 0; index < steps; index += 1) {
      const from = start + tread * index;
      const length = Math.max(0.5, tread);
      const tone = tones[Math.min(tones.length - 1, Math.floor((index / steps) * tones.length))] ?? SCENE_STAIRS.treadColor;
      if (alongX) {
        graphics.rect(from, crossStart, length, crossSize).fill({ color: tone });
        graphics
          .rect(from, crossStart, line, crossSize)
          .fill({ color: SCENE_STAIRS.nosingColor, alpha: 0.4 * (1 - index / steps) });
      } else {
        graphics.rect(crossStart, from, crossSize, length).fill({ color: tone });
        graphics
          .rect(crossStart, from, crossSize, line)
          .fill({ color: SCENE_STAIRS.nosingColor, alpha: 0.4 * (1 - index / steps) });
      }
    }

    graphics.rect(x, y, w, h).stroke({ color: SCENE_STAIRS.shaftEdge, width: Math.max(1, 3 / zoom), alpha: 1 });
  }

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
      case 'wood':
        return 0x9a6b45;
      case 'tile':
        return 0xbab4a8;
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
