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

  it('не отдаёт оверлеев, когда они выключены', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const paint = service.build(1, grid, { costs: false, visibility: false, observer: null, radius: null }, 64, null);

    expect(paint.costs).toEqual([]);
    expect(paint.fog).toBeNull();
  });
});
