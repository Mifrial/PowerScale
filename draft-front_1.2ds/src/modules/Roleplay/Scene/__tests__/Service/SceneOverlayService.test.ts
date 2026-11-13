import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneVisibility } from '@/modules/Roleplay/Scene/Dto/SceneVisibility';
import { SCENE_OVERLAY } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OVERLAY';
import { SceneOverlayService } from '@/modules/Roleplay/Scene/Service/SceneOverlayService';
import { SceneSurfaceService } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';

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

  it('отличает край скрытой области от её середины', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const visible = new Uint8Array(grid.columns * grid.rows).fill(1);
    for (let row = 3; row <= 7; row += 1) {
      for (let column = 3; column <= 7; column += 1) {
        visible[row * grid.columns + column] = 0;
      }
    }
    const visibility: SceneVisibility = { columns: grid.columns, rows: grid.rows, visible };
    const bands = service.hiddenBands(grid, visibility);

    expect(bands.some((band) => band.alpha === SCENE_OVERLAY.hiddenEdge.alpha)).toBe(true);
    expect(bands.some((band) => band.alpha === SCENE_OVERLAY.hiddenMid.alpha)).toBe(true);
    expect(bands.some((band) => band.alpha === SCENE_OVERLAY.hidden.alpha)).toBe(true);
    expect(bands.every((band) => band.width > 0 && band.height > 0)).toBe(true);
  });

  it('ставит стоимость перед затемнением видимости', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const paint = service.build(
      grid,
      { costs: true, visibility: true, observer: null, radius: 48 },
      64,
      null,
    );

    expect(paint.bands.length).toBeGreaterThan(1);
    expect(paint.bands[0].color).toBe(SCENE_OVERLAY.cost.grass.color);
    expect([SCENE_OVERLAY.hidden.color, SCENE_OVERLAY.hiddenEdge.color]).toContain(
      paint.bands[paint.bands.length - 1].color,
    );
  });

  it('не отдаёт полос, когда оверлеи выключены', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const paint = service.build(grid, { costs: false, visibility: false, observer: null, radius: null }, 64, null);

    expect(paint.bands).toEqual([]);
  });
});
