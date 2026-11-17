import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SceneOverlayMask } from '@/modules/Roleplay/Scene/Dto/SceneOverlayPaint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneVisibility } from '@/modules/Roleplay/Scene/Dto/SceneVisibility';
import { SCENE_OVERLAY } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OVERLAY';
import { SceneOverlayService } from '@/modules/Roleplay/Scene/Service/SceneOverlayService';
import { SceneSurfaceService, surfaceIndex } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';

const board: SceneBoard = {
  id: 1,
  name: 'Поле',
  sortOrder: 0,
  baseFile: null,
  boundWidth: 256,
  boundHeight: 256,
  occupancyCell: 16,
  zQuantum: 16,
  gridStep: 32,
};

const element = (
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
): SceneElement => ({
  id,
  boardId: 1,
  kind,
  shape,
  props,
  sortOrder: id,
  targetBoardId: null,
  asset: null,
});

const grass = (): SceneElement =>
  element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 256, h: 256 }, { kind: 'grass' });

const fineIndex = (mask: SceneOverlayMask, point: [number, number]): number => {
  const column = Math.max(0, Math.min(mask.columns - 1, Math.floor((point[0] - mask.x) / mask.cell)));
  const row = Math.max(0, Math.min(mask.rows - 1, Math.floor((point[1] - mask.y) / mask.cell)));

  return row * mask.columns + column;
};

const onBoard = (
  boardId: number,
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
): SceneElement => ({
  ...element(id, kind, shape, props),
  boardId,
});

describe('SceneOverlayService', () => {
  const surfaces = new SceneSurfaceService();
  const service = new SceneOverlayService();

  it('сливает соседние клетки дороги в одну полосу', () => {
    const grid = surfaces.build(
      board,
      [grass(), element(2, 'terrain', { type: 'rect', x: 0, y: 0, w: 64, h: 256 }, { kind: 'dirt' })],
      64,
    );
    const bands = service.costBands(grid);

    expect(bands).toHaveLength(32);
    expect(bands[0]).toMatchObject({
      x: 0,
      y: 0,
      width: 64,
      height: 16,
      color: SCENE_OVERLAY.cost.dirt.color,
      alpha: SCENE_OVERLAY.cost.dirt.alpha,
    });
    expect(bands[1]).toMatchObject({
      x: 64,
      y: 0,
      width: 192,
      color: SCENE_OVERLAY.cost.grass.color,
    });
  });

  it('красит непроходимую клетку отдельно от поверхности', () => {
    const grid = surfaces.build(
      board,
      [
        grass(),
        element(
          2,
          'obstacle',
          { type: 'rect', x: 64, y: 64, w: 32, h: 32 },
          { blocksMovement: true, blocksVision: true },
        ),
      ],
      64,
    );
    const row = service.costBands(grid).filter((band) => band.y === 64);

    expect(row).toHaveLength(3);
    expect(row[1]).toMatchObject({
      x: 64,
      width: 32,
      color: SCENE_OVERLAY.impassable.color,
      alpha: SCENE_OVERLAY.impassable.alpha,
    });
  });

  it('сглаживает край скрытой области, а не рисует кольца', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const visible = new Uint8Array(grid.columns * grid.rows).fill(1);
    for (let row = 3; row <= 7; row += 1) {
      for (let column = 3; column <= 7; column += 1) {
        visible[row * grid.columns + column] = 0;
      }
    }
    const visibility: SceneVisibility = { columns: grid.columns, rows: grid.rows, visible };
    const mask = service.hiddenMask(grid, visibility);
    const alphas = new Set(mask.alpha);

    expect(mask.columns).toBe(grid.columns * SCENE_OVERLAY.fogScale);
    expect(mask.rows).toBe(grid.rows * SCENE_OVERLAY.fogScale);
    expect(mask.cell).toBe(grid.cell / SCENE_OVERLAY.fogScale);
    // У края туман светлее, в глубине — плотнее; видимая клетка прозрачна.
    expect(mask.alpha[fineIndex(mask, [88, 88])]).toBeGreaterThan(mask.alpha[fineIndex(mask, [56, 56])]);
    expect(mask.alpha[fineIndex(mask, [8, 8])]).toBe(0);
    expect(alphas.size).toBeGreaterThan(3);
  });

  it('делает край обзора ровным кругом, а не квадратом', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const visible = new Uint8Array(grid.columns * grid.rows);
    const center: [number, number] = [128, 128];
    const radius = 64;
    for (let row = 0; row < grid.rows; row += 1) {
      for (let column = 0; column < grid.columns; column += 1) {
        const x = grid.x + (column + 0.5) * grid.cell;
        const y = grid.y + (row + 0.5) * grid.cell;
        if (Math.hypot(x - center[0], y - center[1]) <= radius) {
          visible[row * grid.columns + column] = 1;
        }
      }
    }
    const mask = service.hiddenMask(
      grid,
      { columns: grid.columns, rows: grid.rows, visible },
      { edge: { center, radiusX: radius, radiusY: radius } },
    );
    // Контур одинаково далёк от центра по оси и по диагонали: кромка — круг.
    const at = (column: number, row: number): number =>
      mask.alpha[
        Math.min(mask.rows - 1, Math.max(0, row)) * mask.columns + Math.min(mask.columns - 1, Math.max(0, column))
      ];
    const sample = (x: number, y: number): number => {
      const fx = (x - mask.x) / mask.cell - 0.5;
      const fy = (y - mask.y) / mask.cell - 0.5;
      const x0 = Math.floor(fx);
      const y0 = Math.floor(fy);
      const tx = fx - x0;
      const ty = fy - y0;

      return (
        (at(x0, y0) * (1 - tx) + at(x0 + 1, y0) * tx) * (1 - ty) +
        (at(x0, y0 + 1) * (1 - tx) + at(x0 + 1, y0 + 1) * tx) * ty
      );
    };
    const contour = (dirX: number, dirY: number): number => {
      for (let distance = radius; distance <= radius + 64; distance += 1) {
        if (sample(center[0] + dirX * distance, center[1] + dirY * distance) >= 60) {
          return distance;
        }
      }

      return Number.POSITIVE_INFINITY;
    };
    const axis = contour(1, 0);
    const diagonal = contour(Math.SQRT1_2, Math.SQRT1_2);

    expect(axis).toBeGreaterThan(radius);
    expect(Math.abs(axis - diagonal)).toBeLessThanOrEqual(4);
  });

  it('наращивает плотность тумана по расстоянию, без полок', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const visible = new Uint8Array(grid.columns * grid.rows).fill(1);
    for (let row = 4; row <= 12; row += 1) {
      for (let column = 4; column <= 12; column += 1) {
        visible[row * grid.columns + column] = 0;
      }
    }
    const mask = service.hiddenMask(grid, { columns: grid.columns, rows: grid.rows, visible }, { scale: 1 });
    const row = 8;
    const profile: number[] = [];
    for (let column = 2; column <= 9; column += 1) {
      profile.push(mask.alpha[row * mask.columns + column]);
    }

    // Профиль по строке только растёт: у кромки светлее, в глубине плотнее.
    for (let index = 1; index < profile.length; index += 1) {
      expect(profile[index]).toBeGreaterThanOrEqual(profile[index - 1]);
    }
    expect(new Set(profile).size).toBeGreaterThanOrEqual(4);
    expect(profile[profile.length - 1]).toBe(Math.round(SCENE_OVERLAY.hidden.alpha * 255));
  });

  it('не прячет клетки без поверхности: поля этажа остаются видимыми', () => {
    const grid = surfaces.build(
      board,
      [element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 64, h: 64 }, { kind: 'grass' })],
      64,
    );
    const visible = new Uint8Array(grid.columns * grid.rows).fill(0);
    const visibility: SceneVisibility = { columns: grid.columns, rows: grid.rows, visible };
    const mask = service.hiddenMask(grid, visibility);

    expect(grid.kinds[surfaceIndex(grid, 200, 200)]).toBe('none');
    expect(mask.alpha[fineIndex(mask, [200, 200])]).toBe(0);
  });

  it('не светит остатком радиуса сквозь стены за пределами видимости', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const visible = new Uint8Array(grid.columns * grid.rows);
    // Видимая полоса доходит до окружности радиуса, но не заслоняет всё поле.
    for (let column = 4; column <= 7; column += 1) {
      visible[8 * grid.columns + column] = 1;
    }
    const mask = service.hiddenMask(
      grid,
      { columns: grid.columns, rows: grid.rows, visible },
      { scale: 1, edge: { center: [64, 128], radiusX: 64, radiusY: 64 } },
    );
    const full = Math.round(SCENE_OVERLAY.hidden.alpha * 255);
    const cell = (x: number, y: number): number => fineIndex(mask, [x, y]);

    // У кромки видимой области край мягкий.
    expect(mask.alpha[cell(136, 136)]).toBeLessThan(full);
    // А за стеной, далеко от видимых клеток, остаток радиуса не подсвечивает
    // клетку: иначе на границе круга остаются световые дуги.
    expect(mask.alpha[cell(136, 184)]).toBe(full);
  });

  it('держит стоимость и туман в разных наборах', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const paint = service.build(1, grid, { costs: true, visibility: true, observer: null, radius: 48 }, 64, null);

    expect(paint.costs.length).toBeGreaterThan(1);
    expect(paint.costs[0].color).toBe(SCENE_OVERLAY.cost.grass.color);
    expect(paint.fog).not.toBeNull();
    expect(paint.fog?.color).toBe(SCENE_OVERLAY.hidden.color);
    expect(paint.fog?.columns).toBe(grid.columns * SCENE_OVERLAY.fogScale);
    expect(paint.fog?.rows).toBe(grid.rows * SCENE_OVERLAY.fogScale);
    expect(paint.fog?.cell).toBe(grid.cell / SCENE_OVERLAY.fogScale);
  });

  it('считает туман на всех видимых полях, а не только на активном', () => {
    const root: SceneBoard = { ...board, id: 1, boundWidth: 512, boundHeight: 512 };
    const upper: SceneBoard = { ...board, id: 2, boundWidth: 256, boundHeight: 256 };
    const enclosure: SceneEnclosure = {
      id: 1,
      parentBoardId: 1,
      childBoardId: 2,
      footprint: { type: 'rect', x: 128, y: 128, w: 256, h: 256 },
      elevation: 128,
      label: null,
      sortOrder: 0,
    };
    const elements = [
      onBoard(1, 1, 'terrain', { type: 'rect', x: 0, y: 0, w: 512, h: 512 }, { kind: 'grass' }),
      onBoard(2, 2, 'terrain', { type: 'rect', x: 0, y: 0, w: 256, h: 256 }, { kind: 'grass' }),
    ];
    const model: SceneRenderModel = {
      activeBoardId: 2,
      resolution: 64,
      boards: [root, upper],
      elements,
      enclosures: [enclosure],
      selection: [],
      mode: 'preview',
    };
    const paints = service.paints(
      model,
      { costs: true, visibility: true, observer: null, radius: 800 },
      { boardId: 2, point: [16, 16], level: 0, height: 96 },
    );
    const rootPaint = paints.find((paint) => paint.boardId === 1);
    const upperPaint = paints.find((paint) => paint.boardId === 2);
    expect(rootPaint?.fog).not.toBeNull();
    expect(rootPaint?.costs).toEqual([]);
    expect(upperPaint?.costs.length).toBeGreaterThan(0);

    // Под плитой верхнего этажа двор скрыт: плита перекрывает луч.
    expect(rootPaint!.fog!.alpha[fineIndex(rootPaint!.fog!, [256, 256])]).toBeGreaterThan(0);
    // Двор в стороне от плиты виден: луч выходит из-под неё за край.
    expect(rootPaint!.fog!.alpha[fineIndex(rootPaint!.fog!, [32, 32])]).toBe(0);
  });

  it('с крыши пол закрывает дом, а двор за краем плиты остаётся видимым', () => {
    const root: SceneBoard = { ...board, id: 1, boundWidth: 512, boundHeight: 512 };
    const upper: SceneBoard = { ...board, id: 2, boundWidth: 256, boundHeight: 256 };
    const enclosure: SceneEnclosure = {
      id: 1,
      parentBoardId: 1,
      childBoardId: 2,
      footprint: { type: 'rect', x: 128, y: 128, w: 256, h: 256 },
      elevation: 128,
      label: null,
      sortOrder: 0,
    };
    const elements = [
      onBoard(1, 1, 'terrain', { type: 'rect', x: 0, y: 0, w: 512, h: 512 }, { kind: 'grass' }),
      onBoard(2, 2, 'terrain', { type: 'rect', x: 0, y: 0, w: 256, h: 256 }, { kind: 'grass' }),
      onBoard(
        2,
        3,
        'support',
        { type: 'rect', x: 32, y: 32, w: 192, h: 192 },
        { kind: 'roof', elevation: 128, height: 16, speed: 100, walkable: true },
      ),
    ];
    const model: SceneRenderModel = {
      activeBoardId: 2,
      resolution: 64,
      boards: [root, upper],
      elements,
      enclosures: [enclosure],
      selection: [],
      mode: 'preview',
    };
    const paintsFor = (onRoof?: boolean) =>
      service.paints(
        model,
        { costs: false, visibility: true, observer: null, radius: 400 },
        { boardId: 2, point: [128, 128], level: 1, onRoof, height: 96 },
      );
    const rootMask = (onRoof?: boolean) => {
      const paint = paintsFor(onRoof).find((item) => item.boardId === 1);
      expect(paint?.fog).not.toBeNull();

      return paint!.fog!;
    };

    // На крыше плита проверяется: пол этажа под крышей закрывает землю под
    // домом, иначе с крыши просвечивал бы и дом, и проёмы нижнего этажа.
    const roof = rootMask(true);
    expect(roof.alpha[fineIndex(roof, [256, 256])]).toBeGreaterThan(0);
    // Двор за краем плиты виден: луч обходит её по воздуху.
    expect(roof.alpha[fineIndex(roof, [32, 32])]).toBe(0);
    // С марша под крышей плита честно перекрывает.
    const stairs = rootMask(false);
    expect(stairs.alpha[fineIndex(stairs, [256, 256])]).toBeGreaterThan(0);
    // Без явного флага запасная проверка по сетке видит крышу так же.
    const fallback = rootMask();
    expect(fallback.alpha[fineIndex(fallback, [256, 256])]).toBeGreaterThan(0);
    expect(fallback.alpha[fineIndex(fallback, [32, 32])]).toBe(0);
  });

  it('не открывает второй этаж через люк по верхней поверхности крыши', () => {
    const root: SceneBoard = { ...board, id: 1, boundWidth: 1024, boundHeight: 1024 };
    const upper: SceneBoard = { ...board, id: 2, boundWidth: 512, boundHeight: 512 };
    const enclosure: SceneEnclosure = {
      id: 1,
      parentBoardId: 1,
      childBoardId: 2,
      footprint: { type: 'rect', x: 256, y: 256, w: 512, h: 512 },
      elevation: 128,
      label: null,
      sortOrder: 0,
    };
    const elements = [
      onBoard(1, 1, 'terrain', { type: 'rect', x: 0, y: 0, w: 1024, h: 1024 }, { kind: 'grass' }),
      onBoard(2, 2, 'terrain', { type: 'rect', x: 0, y: 0, w: 512, h: 512 }, { kind: 'dirt' }),
      onBoard(
        2,
        3,
        'support',
        { type: 'rect', x: 32, y: 32, w: 448, h: 448 },
        { kind: 'roof', elevation: 128, height: 16, speed: 100, walkable: true },
      ),
      // Колодец в плите — узкая щель над головой наблюдателя.
      onBoard(2, 4, 'opening', { type: 'rect', x: 80, y: 240, w: 32, h: 24 }, { kind: 'stairs' }),
    ];
    const model: SceneRenderModel = {
      activeBoardId: 2,
      resolution: 64,
      boards: [root, upper],
      elements,
      enclosures: [enclosure],
      selection: [],
      mode: 'preview',
    };
    const paint = service
      .paints(
        model,
        { costs: false, visibility: true, observer: null, radius: 1024 },
        // Наблюдатель на первом этаже, южнее люка.
        { boardId: 1, point: [352, 556], level: 0, height: 96 },
      )
      .find((item) => item.boardId === 2);
    expect(paint?.fog).not.toBeNull();
    const mask = paint!.fog!;
    const full = Math.round(SCENE_OVERLAY.hidden.alpha * 255);

    // Туман этажа — план пола, а не верх крыши: через люк видно пол рядом с
    // ним, но дальняя часть этажа остаётся скрытой. Иначе люк подсвечивал бы
    // половину второго этажа, и с первого этажа был бы виден стоящий там актор.
    expect(mask.alpha[fineIndex(mask, [96, 0])]).toBe(full);
    expect(mask.alpha[fineIndex(mask, [96, 190])]).toBe(0);
  });

  it('не открывает двор с марша под верхним этажом', () => {
    const root: SceneBoard = { ...board, id: 1, boundWidth: 512, boundHeight: 512 };
    const upper: SceneBoard = { ...board, id: 2, boundWidth: 256, boundHeight: 256 };
    const enclosure: SceneEnclosure = {
      id: 1,
      parentBoardId: 1,
      childBoardId: 2,
      footprint: { type: 'rect', x: 128, y: 128, w: 256, h: 256 },
      elevation: 128,
      label: null,
      sortOrder: 0,
    };
    const wall = (id: number, points: [number, number][]): SceneElement =>
      onBoard(1, id, 'obstacle', { type: 'wall', points, width: 16 }, { height: 128, blocksVision: true });
    const elements = [
      onBoard(1, 1, 'terrain', { type: 'rect', x: 0, y: 0, w: 512, h: 512 }, { kind: 'grass' }),
      onBoard(2, 2, 'terrain', { type: 'rect', x: 0, y: 0, w: 256, h: 256 }, { kind: 'grass' }),
      // Дом с комнатой вокруг марша: стена выше глаз, но ниже поднятого марша.
      wall(3, [
        [160, 150],
        [384, 150],
        [384, 400],
        [160, 400],
        [160, 150],
      ]),
      onBoard(1, 4, 'support', { type: 'rect', x: 240, y: 170, w: 64, h: 200 }, {
        kind: 'stairs',
        elevation: 128,
        height: 16,
        speed: 100,
      }),
    ];
    const model: SceneRenderModel = {
      activeBoardId: 2,
      resolution: 64,
      boards: [root, upper],
      elements,
      enclosures: [enclosure],
      selection: [],
      mode: 'preview',
    };
    const paint = service
      .paints(
        model,
        { costs: false, visibility: true, observer: null, radius: 400 },
        // Наблюдатель на середине марша: глаз выше стен своего этажа.
        { boardId: 1, point: [272, 320], level: 1, height: 96 },
      )
      .find((item) => item.boardId === 1);
    expect(paint?.fog).not.toBeNull();
    const mask = paint!.fog!;

    // Пол комнаты виден, а двор за стеной — нет: перекрытие верхнего этажа
    // не даёт глазу подняться выше своего пола и смотреть поверх стены.
    expect(mask.alpha[fineIndex(mask, [200, 320])]).toBe(0);
    expect(mask.alpha[fineIndex(mask, [64, 320])]).toBeGreaterThan(0);
  });

  it('с марша под крышей двор за стеной не открывается', () => {
    const root: SceneBoard = { ...board, id: 1, boundWidth: 512, boundHeight: 512 };
    const upper: SceneBoard = { ...board, id: 2, boundWidth: 256, boundHeight: 256 };
    const enclosure: SceneEnclosure = {
      id: 1,
      parentBoardId: 1,
      childBoardId: 2,
      footprint: { type: 'rect', x: 128, y: 128, w: 256, h: 256 },
      elevation: 128,
      label: null,
      sortOrder: 0,
    };
    const wall = (id: number, shape: SceneShape): SceneElement =>
      onBoard(2, id, 'obstacle', shape, { height: 128, blocksMovement: true, blocksVision: true });
    const elements = [
      onBoard(1, 1, 'terrain', { type: 'rect', x: 0, y: 0, w: 512, h: 512 }, { kind: 'grass' }),
      onBoard(2, 2, 'terrain', { type: 'rect', x: 0, y: 0, w: 256, h: 256 }, { kind: 'grass' }),
      onBoard(
        2,
        3,
        'support',
        { type: 'rect', x: 32, y: 32, w: 192, h: 192 },
        { kind: 'roof', elevation: 128, height: 16, speed: 100, walkable: true },
      ),
      onBoard(
        2,
        4,
        'support',
        { type: 'rect', x: 48, y: 112, w: 160, h: 32 },
        { kind: 'stairs', elevation: 128, height: 16, speed: 100 },
      ),
      wall(5, { type: 'rect', x: 32, y: 32, w: 192, h: 16 }),
      wall(6, { type: 'rect', x: 32, y: 208, w: 192, h: 16 }),
      wall(7, { type: 'rect', x: 32, y: 32, w: 16, h: 192 }),
      wall(8, { type: 'rect', x: 208, y: 32, w: 16, h: 192 }),
    ];
    const model: SceneRenderModel = {
      activeBoardId: 2,
      resolution: 64,
      boards: [root, upper],
      elements,
      enclosures: [enclosure],
      selection: [],
      mode: 'preview',
    };
    const paints = service.paints(
      model,
      { costs: false, visibility: true, observer: null, radius: 400 },
      { boardId: 2, point: [140, 128], level: 1, onRoof: false, height: 96 },
    );
    const rootPaint = paints.find((paint) => paint.boardId === 1);
    expect(rootPaint?.fog).not.toBeNull();

    // Середина марша: без ограничения глаз был бы выше стены (128+73+86),
    // а с ним остаётся под крышей и двор за стеной закрыт.
    expect(rootPaint!.fog!.alpha[fineIndex(rootPaint!.fog!, [64, 256])]).toBeGreaterThan(0);
  });

  it('не отдаёт оверлеев, когда они выключены', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const paint = service.build(1, grid, { costs: false, visibility: false, observer: null, radius: null }, 64, null);

    expect(paint.costs).toEqual([]);
    expect(paint.fog).toBeNull();
  });
});
