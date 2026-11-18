import { Graphics } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SCENE_WALL_MATERIAL } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_WALL_MATERIAL';
import {
  SCENE_FURNITURE_PALETTE,
  sceneFurnitureKind,
  sceneFurnitureMaterial,
} from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_FURNITURE';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';

/** Мягкая тень под мебелью. */
const SHADOW_COLOR = 0x1d1008;
const SHADOW_ALPHA = 0.28;

/** Ткань постели и ковра: не зависит от материала корпуса. */
const LINEN = 0xe4d6bf;
const BLANKET = 0x8a4b3a;
const METAL = 0x8b8f94;
const EMBER = 0xd97724;

/** Прямоугольник мебели в координатах сцены. */
interface FurnitureBox {
  x: number;
  y: number;
  w: number;
  h: number;
  cx: number;
  cy: number;
  short: number;
  long: number;
  horizontal: boolean;
}

/**
 * Мебель сверху: процедурный рисунок по подтипу.
 *
 * Предмет рисуется в локальных координатах прямоугольника и поворачивается
 * вокруг центра, как стена и настил. Тень падает в ту же сторону, что у стен:
 * свет общий для сцены. Толщины линий — доли короткой стороны, а не экранные
 * пиксели: рисунок не зависит от зума.
 */
export class SceneFurnitureService {
  /** Рисует мебель; `null` — это не мебель или фигура не поддерживается. */
  paint(element: SceneElement): Graphics | null {
    const kind = sceneFurnitureKind(element.props.kind);
    if (kind === null || (element.shape.type !== 'rect' && element.shape.type !== 'circle')) {
      return null;
    }

    const box = this.boxOf(element);
    const graphics = new Graphics();
    const material = sceneFurnitureMaterial(kind, element.props.material);
    const palette = SCENE_FURNITURE_PALETTE[material];

    switch (kind) {
      case 'bed':
        this.paintBed(graphics, box, palette);
        break;
      case 'table':
        this.paintTable(graphics, box, palette);
        break;
      case 'chair':
        this.paintChair(graphics, box, palette);
        break;
      case 'bench':
        this.paintBench(graphics, box, palette);
        break;
      case 'chest':
        this.paintChest(graphics, box, palette);
        break;
      case 'wardrobe':
        this.paintWardrobe(graphics, box, palette);
        break;
      case 'shelf':
        this.paintShelf(graphics, box, palette);
        break;
      case 'barrel':
        this.paintBarrel(graphics, box, palette);
        break;
      case 'rug':
        this.paintRug(graphics, box, palette);
        break;
      case 'fireplace':
        this.paintFireplace(graphics, box, palette);
        break;
    }

    this.applyRotation(graphics, element, box);

    return graphics;
  }

  /** Подложка мебели и её оси; круг вписывается в квадрат. */
  private boxOf(element: SceneElement): FurnitureBox {
    if (element.shape.type === 'circle') {
      const { cx, cy, r } = element.shape;

      return { x: cx - r, y: cy - r, w: r * 2, h: r * 2, cx, cy, short: r * 2, long: r * 2, horizontal: true };
    }

    if (element.shape.type !== 'rect') {
      return { x: 0, y: 0, w: 0, h: 0, cx: 0, cy: 0, short: 0, long: 0, horizontal: true };
    }

    const { x, y, w, h } = element.shape;

    return {
      x,
      y,
      w,
      h,
      cx: x + w / 2,
      cy: y + h / 2,
      short: Math.min(w, h),
      long: Math.max(w, h),
      horizontal: w >= h,
    };
  }

  /** Поворот вокруг центра; у круга поворот не нужен. */
  private applyRotation(graphics: Graphics, element: SceneElement, box: FurnitureBox): void {
    if (element.shape.type !== 'rect') {
      return;
    }

    const rotation = sceneRotation(element);
    if (rotation % 360 === 0) {
      return;
    }

    graphics.pivot.set(box.cx, box.cy);
    graphics.position.set(box.cx, box.cy);
    graphics.rotation = (rotation * Math.PI) / 180;
  }

  /** Тень под предметом: сдвиг в сторону, противоположную свету. */
  private shadow(graphics: Graphics, box: FurnitureBox, radius: number): void {
    const [lightX, lightY] = SCENE_WALL_MATERIAL.light;
    const shift = box.short * 0.12;
    graphics
      .roundRect(box.x - lightX * shift, box.y - lightY * shift, box.w, box.h, radius)
      .fill({ color: SHADOW_COLOR, alpha: SHADOW_ALPHA });
  }

  private paintBed(
    graphics: Graphics,
    box: FurnitureBox,
    palette: (typeof SCENE_FURNITURE_PALETTE)[keyof typeof SCENE_FURNITURE_PALETTE],
  ): void {
    const r = box.short * 0.06;
    this.shadow(graphics, box, r * 1.4);
    // Рама, матрас, одеяло и подушки: изголовье — север локальной рамки.
    graphics.roundRect(box.x, box.y, box.w, box.h, r).fill({ color: palette.dark });
    graphics
      .roundRect(
        box.x + box.short * 0.06,
        box.y + box.short * 0.14,
        box.w - box.short * 0.12,
        box.h - box.short * 0.2,
        r,
      )
      .fill({ color: LINEN });
    graphics
      .roundRect(box.x + box.short * 0.06, box.y + box.h * 0.42, box.w - box.short * 0.12, box.h * 0.5, r)
      .fill({ color: BLANKET });
    graphics
      .rect(box.x + box.short * 0.06, box.y + box.h * 0.42, box.w - box.short * 0.12, box.short * 0.06)
      .fill({ color: 0x6e3228, alpha: 0.7 });
    const pillow = Math.min(box.w * 0.3, box.short * 0.48);
    for (const offset of [0.16, 0.54]) {
      graphics
        .roundRect(box.x + box.w * offset, box.y + box.short * 0.2, pillow, box.short * 0.16, box.short * 0.05)
        .fill({ color: 0xf2eadc });
    }
    this.outline(graphics, box, r, palette.edge);
  }

  private paintTable(
    graphics: Graphics,
    box: FurnitureBox,
    palette: (typeof SCENE_FURNITURE_PALETTE)[keyof typeof SCENE_FURNITURE_PALETTE],
  ): void {
    const r = box.short * 0.07;
    this.shadow(graphics, box, r * 1.2);
    // Ножки выглядывают из-под столешницы тёмными точками по углам.
    for (const [x, y] of [
      [box.x + box.short * 0.1, box.y + box.short * 0.1],
      [box.x + box.w - box.short * 0.1, box.y + box.short * 0.1],
      [box.x + box.short * 0.1, box.y + box.h - box.short * 0.1],
      [box.x + box.w - box.short * 0.1, box.y + box.h - box.short * 0.1],
    ]) {
      graphics.circle(x, y, box.short * 0.055).fill({ color: palette.edge, alpha: 0.8 });
    }
    graphics.roundRect(box.x, box.y, box.w, box.h, r).fill({ color: palette.base });
    graphics
      .roundRect(box.x + r * 0.5, box.y + r * 0.5, box.w - r, box.h - r, r * 0.7)
      .fill({ color: palette.light, alpha: 0.18 });
    // Посуда на столе: без неё столешница читается просто доской.
    graphics.circle(box.cx - box.short * 0.22, box.cy, box.short * 0.14).fill({ color: 0xe4d6bf, alpha: 0.95 });
    graphics.circle(box.cx - box.short * 0.22, box.cy, box.short * 0.08).fill({ color: 0xb98552, alpha: 0.8 });
    graphics.circle(box.cx + box.short * 0.18, box.cy - box.short * 0.12, box.short * 0.07).fill({ color: 0xe4d6bf });
    graphics.circle(box.cx + box.short * 0.18, box.cy - box.short * 0.12, box.short * 0.035).fill({ color: 0x8a5a35 });
    // Швы досок вдоль длинной стороны.
    const lines = 4;
    const axis = box.horizontal ? box.h : box.w;
    const start = box.horizontal ? box.y : box.x;
    for (let index = 1; index < lines; index += 1) {
      const at = start + (axis * index) / lines;
      if (box.horizontal) {
        this.line(graphics, box.x + r, at, box.x + box.w - r, at, palette.dark, box.short * 0.012, 0.55);
      } else {
        this.line(graphics, at, box.y + r, at, box.y + box.h - r, palette.dark, box.short * 0.012, 0.55);
      }
    }
    this.outline(graphics, box, r, palette.edge);
  }

  private paintChair(
    graphics: Graphics,
    box: FurnitureBox,
    palette: (typeof SCENE_FURNITURE_PALETTE)[keyof typeof SCENE_FURNITURE_PALETTE],
  ): void {
    const r = box.short * 0.1;
    this.shadow(graphics, box, r * 1.4);
    graphics
      .roundRect(box.x + box.w * 0.12, box.y + box.h * 0.18, box.w * 0.76, box.h * 0.7, r)
      .fill({ color: palette.base });
    // Спинка с северной стороны и два столбика: поворот разворачивает стул.
    graphics
      .roundRect(box.x + box.w * 0.08, box.y + box.h * 0.06, box.w * 0.84, box.h * 0.14, r * 0.5)
      .fill({ color: palette.dark });
    for (const x of [box.x + box.w * 0.18, box.x + box.w * 0.82]) {
      graphics.circle(x, box.y + box.h * 0.16, box.short * 0.035).fill({ color: palette.edge });
    }
    this.outline(graphics, box, r, palette.edge, 0.08);
  }

  private paintBench(
    graphics: Graphics,
    box: FurnitureBox,
    palette: (typeof SCENE_FURNITURE_PALETTE)[keyof typeof SCENE_FURNITURE_PALETTE],
  ): void {
    const r = box.short * 0.08;
    this.shadow(graphics, box, r * 1.3);
    graphics.roundRect(box.x, box.y + box.h * 0.12, box.w, box.h * 0.76, r).fill({ color: palette.base });
    // Три доски сиденья и ножки по торцам.
    for (let index = 1; index < 3; index += 1) {
      const at = box.y + box.h * (0.12 + (0.76 * index) / 3);
      this.line(graphics, box.x + r, at, box.x + box.w - r, at, palette.dark, box.short * 0.02, 0.5);
    }
    for (const x of [box.x + r, box.x + box.w - r]) {
      graphics.circle(x, box.y + box.h * 0.5, box.short * 0.07).fill({ color: palette.dark });
    }
    this.outline(graphics, box, r, palette.edge);
  }

  private paintChest(
    graphics: Graphics,
    box: FurnitureBox,
    palette: (typeof SCENE_FURNITURE_PALETTE)[keyof typeof SCENE_FURNITURE_PALETTE],
  ): void {
    const r = box.short * 0.08;
    this.shadow(graphics, box, r * 1.2);
    graphics.roundRect(box.x, box.y, box.w, box.h, r).fill({ color: palette.base });
    graphics
      .rect(box.x + box.w * 0.04, box.y + box.h * 0.16, box.w * 0.92, box.h * 0.16)
      .fill({ color: palette.light, alpha: 0.8 });
    for (const x of [box.x + box.w * 0.22, box.x + box.w * 0.78]) {
      graphics.rect(x - box.short * 0.025, box.y, box.short * 0.05, box.h).fill({ color: METAL, alpha: 0.85 });
    }
    graphics.circle(box.x + box.w * 0.5, box.y + box.h * 0.46, box.short * 0.09).fill({ color: METAL });
    graphics.circle(box.x + box.w * 0.5, box.y + box.h * 0.46, box.short * 0.03).fill({ color: palette.edge });
    this.outline(graphics, box, r, palette.edge);
  }

  private paintWardrobe(
    graphics: Graphics,
    box: FurnitureBox,
    palette: (typeof SCENE_FURNITURE_PALETTE)[keyof typeof SCENE_FURNITURE_PALETTE],
  ): void {
    const r = box.short * 0.06;
    this.shadow(graphics, box, r * 1.6);
    graphics.roundRect(box.x, box.y, box.w, box.h, r).fill({ color: palette.base });
    const inset = box.short * 0.08;
    graphics
      .rect(box.x + inset, box.y + inset, box.w / 2 - inset * 1.5, box.h - inset * 2)
      .fill({ color: palette.light, alpha: 0.28 });
    graphics
      .rect(box.x + box.w / 2 + inset / 2, box.y + inset, box.w / 2 - inset * 1.5, box.h - inset * 2)
      .fill({ color: palette.dark, alpha: 0.18 });
    this.line(graphics, box.cx, box.y + inset, box.cx, box.y + box.h - inset, palette.edge, box.short * 0.02, 0.7);
    for (const x of [box.cx - box.w * 0.08, box.cx + box.w * 0.08]) {
      graphics.circle(x, box.cy, box.short * 0.035).fill({ color: METAL });
    }
    this.outline(graphics, box, r, palette.edge);
  }

  private paintShelf(
    graphics: Graphics,
    box: FurnitureBox,
    palette: (typeof SCENE_FURNITURE_PALETTE)[keyof typeof SCENE_FURNITURE_PALETTE],
  ): void {
    const r = box.short * 0.08;
    this.shadow(graphics, box, r);
    // Полка — узкая полоса у стены. Книги и банки стоят рядами.
    graphics.roundRect(box.x, box.y, box.w, box.h, r).fill({ color: palette.dark });
    const count = Math.max(4, Math.floor(box.long / Math.max(6, box.short * 0.22)));
    const step = box.long / count;
    const horizontal = box.horizontal;
    for (let index = 0; index < count; index += 1) {
      const at = (horizontal ? box.x : box.y) + step * (index + 0.5);
      const height = box.short * (0.35 + (index % 3) * 0.12);
      const width = step * 0.48;
      if (horizontal) {
        graphics
          .rect(at - width / 2, box.y + box.h * 0.34, width, height * 0.5)
          .fill({ color: index % 2 === 0 ? 0x7d5a3a : 0x6b7f9a, alpha: 0.95 });
      } else {
        graphics
          .rect(box.x + box.w * 0.34, at - width / 2, height * 0.5, width)
          .fill({ color: index % 2 === 0 ? 0x7d5a3a : 0x6b7f9a, alpha: 0.95 });
      }
    }
    this.outline(graphics, box, r, palette.edge);
  }

  private paintBarrel(
    graphics: Graphics,
    box: FurnitureBox,
    palette: (typeof SCENE_FURNITURE_PALETTE)[keyof typeof SCENE_FURNITURE_PALETTE],
  ): void {
    const radius = box.short / 2;
    this.shadow(graphics, box, radius);
    graphics.circle(box.cx, box.cy, radius).fill({ color: palette.base });
    // Клёпки: радиальные швы и два обруча.
    for (let index = 0; index < 8; index += 1) {
      const angle = (Math.PI * index) / 4;
      this.line(
        graphics,
        box.cx + Math.cos(angle) * radius * 0.25,
        box.cy + Math.sin(angle) * radius * 0.25,
        box.cx + Math.cos(angle) * radius * 0.95,
        box.cy + Math.sin(angle) * radius * 0.95,
        palette.dark,
        radius * 0.025,
        0.55,
      );
    }
    for (const share of [0.55, 0.9]) {
      graphics.circle(box.cx, box.cy, radius * share).stroke({
        color: METAL,
        width: Math.max(1, radius * 0.055),
        alpha: 0.85,
      });
    }
    graphics.circle(box.cx, box.cy, radius * 0.2).fill({ color: palette.dark, alpha: 0.55 });
    graphics.circle(box.cx, box.cy, radius).stroke({
      color: palette.edge,
      width: Math.max(1, radius * 0.04),
      alpha: 0.8,
    });
  }

  private paintRug(
    graphics: Graphics,
    box: FurnitureBox,
    palette: (typeof SCENE_FURNITURE_PALETTE)[keyof typeof SCENE_FURNITURE_PALETTE],
  ): void {
    const r = box.short * 0.03;
    graphics.roundRect(box.x, box.y, box.w, box.h, r).fill({ color: palette.base, alpha: 0.9 });
    const inset = box.short * 0.08;
    graphics
      .rect(box.x + inset, box.y + inset, box.w - inset * 2, box.h - inset * 2)
      .stroke({ color: 0xe4d6bf, width: Math.max(1, box.short * 0.018), alpha: 0.75 });
    // Простой ромбовый узор: две диагонали и центр.
    this.line(
      graphics,
      box.cx,
      box.y + inset * 1.4,
      box.x + box.w - inset * 1.4,
      box.cy,
      0xe4d6bf,
      box.short * 0.014,
      0.55,
    );
    this.line(graphics, box.cx, box.y + inset * 1.4, box.x + inset * 1.4, box.cy, 0xe4d6bf, box.short * 0.014, 0.55);
    this.line(
      graphics,
      box.cx,
      box.y + box.h - inset * 1.4,
      box.x + box.w - inset * 1.4,
      box.cy,
      0xe4d6bf,
      box.short * 0.014,
      0.55,
    );
    this.line(
      graphics,
      box.cx,
      box.y + box.h - inset * 1.4,
      box.x + inset * 1.4,
      box.cy,
      0xe4d6bf,
      box.short * 0.014,
      0.55,
    );
    graphics.circle(box.cx, box.cy, box.short * 0.05).fill({ color: 0xe4d6bf, alpha: 0.55 });
  }

  private paintFireplace(
    graphics: Graphics,
    box: FurnitureBox,
    palette: (typeof SCENE_FURNITURE_PALETTE)[keyof typeof SCENE_FURNITURE_PALETTE],
  ): void {
    const r = box.short * 0.08;
    this.shadow(graphics, box, r * 1.4);
    // Каменная обкладка, тёмная топка с южной стороны и полка очага.
    graphics.roundRect(box.x, box.y, box.w, box.h, r).fill({ color: palette.base });
    for (let index = 1; index < 4; index += 1) {
      const at = box.y + (box.h * index) / 4;
      this.line(graphics, box.x + r, at, box.x + box.w - r, at, palette.dark, box.short * 0.02, 0.45);
    }
    const fireX = box.x + box.w * 0.2;
    const fireY = box.y + box.h * 0.38;
    const fireW = box.w * 0.6;
    const fireH = box.h * 0.48;
    graphics.roundRect(fireX, fireY, fireW, fireH, r * 0.6).fill({ color: 0x1b1410 });
    for (const [x, y, size] of [
      [0.35, 0.68, 0.08],
      [0.52, 0.78, 0.11],
      [0.68, 0.7, 0.07],
    ]) {
      graphics.circle(box.x + box.w * x, box.y + box.h * y, box.short * size).fill({ color: EMBER, alpha: 0.9 });
    }
    this.outline(graphics, box, r, palette.edge);
  }

  /** Обводка прямоугольника по краю. */
  private outline(graphics: Graphics, box: FurnitureBox, radius: number, color: number, alpha = 0.75): void {
    graphics.roundRect(box.x, box.y, box.w, box.h, radius).stroke({
      color,
      width: Math.max(1, box.short * 0.022),
      alpha,
    });
  }

  /** Отрезок с постоянной толщиной в единицах сцены. */
  private line(
    graphics: Graphics,
    x1: number,
    y1: number,
    x2: number,
    y2: number,
    color: number,
    width: number,
    alpha: number,
  ): void {
    graphics
      .moveTo(x1, y1)
      .lineTo(x2, y2)
      .stroke({ color, width: Math.max(0.5, width), alpha });
  }
}
