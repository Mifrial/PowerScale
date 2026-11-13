import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SceneSurfaceService, surfaceCenter, surfaceIndex } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';

const board = (overrides: Partial<SceneBoard> = {}): SceneBoard => ({
  id: 1,
  name: 'Поле',
  sortOrder: 0,
  baseFile: null,
  boundWidth: 256,
  boundHeight: 256,
  occupancyCell: 16,
  zQuantum: 16,
  gridStep: 32,
  ...overrides,
});

const element = (
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
  sortOrder = id,
): SceneElement => ({
  id,
  boardId: 1,
  kind,
  shape,
  props,
  sortOrder,
  targetBoardId: null,
  asset: null,
});

const grass = (): SceneElement =>
  element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 256, h: 256 }, { kind: 'grass' }, 0);

const cellOf = (grid: ReturnType<SceneSurfaceService['build']>, column: number, row: number): number =>
  row * grid.columns + column;

describe('SceneSurfaceService', () => {
  const service = new SceneSurfaceService();

  it('ставит скорость по террейну и считает стоимость', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 0, y: 0, w: 64, h: 256 }, { kind: 'dirt' }, 10),
        element(3, 'terrain', { type: 'rect', x: 192, y: 0, w: 64, h: 256 }, { kind: 'water' }, 20),
      ],
      64,
    );

    expect(grid.kinds[cellOf(grid, 0, 0)]).toBe('dirt');
    expect(grid.speeds[cellOf(grid, 0, 0)]).toBe(150);
    expect(grid.costs[cellOf(grid, 0, 0)]).toBeCloseTo(2 / 3);
    expect(grid.kinds[cellOf(grid, 6, 0)]).toBe('grass');
    expect(grid.costs[cellOf(grid, 6, 0)]).toBe(1);
    expect(grid.kinds[cellOf(grid, 13, 0)]).toBe('water');
    expect(grid.speeds[cellOf(grid, 13, 0)]).toBe(20);
    expect(grid.costs[cellOf(grid, 13, 0)]).toBe(5);
  });

  it('вода перекрывает дорогу независимо от sortOrder', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 0, y: 0, w: 256, h: 256 }, { kind: 'dirt' }, 100),
        element(3, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, -10),
      ],
      64,
    );

    expect(grid.kinds[cellOf(grid, 8, 0)]).toBe('water');
    expect(grid.costs[cellOf(grid, 8, 0)]).toBe(5);
  });

  it('мост возвращает проходимость над водой', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, 10),
        element(3, 'support', { type: 'rect', x: 96, y: 96, w: 64, h: 64 }, { kind: 'bridge' }, 20),
      ],
      64,
    );

    expect(grid.kinds[cellOf(grid, 8, 8)]).toBe('bridge');
    expect(grid.speeds[cellOf(grid, 8, 8)]).toBe(150);
    expect(grid.opaque[cellOf(grid, 8, 8)]).toBe(false);
  });

  it('делает препятствие непроходимым и непрозрачным по флагам', () => {
    const grid = service.build(
      board(),
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

    expect(grid.speeds[cellOf(grid, 4, 4)]).toBe(0);
    expect(grid.costs[cellOf(grid, 4, 4)]).toBe(Number.POSITIVE_INFINITY);
    expect(grid.opaque[cellOf(grid, 4, 4)]).toBe(true);
    expect(grid.speeds[cellOf(grid, 0, 0)]).toBe(100);

    const transparent = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'obstacle',
          { type: 'rect', x: 64, y: 64, w: 32, h: 32 },
          { blocksMovement: true, blocksVision: false },
        ),
      ],
      64,
    );

    expect(transparent.speeds[cellOf(transparent, 4, 4)]).toBe(0);
    expect(transparent.opaque[cellOf(transparent, 4, 4)]).toBe(false);
  });

  it('декор перекрывает ход основанием и обзор силуэтом', () => {
    const tree = service.build(
      board(),
      [
        grass(),
        element(2, 'decor', { type: 'point', x: 128, y: 128 }, { scale: 100, label: 'Дерево' }),
      ],
      64,
    );
    const near = cellOf(tree, 8, 8);
    const [nearX, nearY] = surfaceCenter(tree, near);

    expect(Math.hypot(nearX - 128, nearY - 128)).toBeLessThan(20);
    expect(tree.speeds[near]).toBe(0);
    expect(tree.opaque[near]).toBe(true);
    expect(tree.opaque[cellOf(tree, 10, 8)]).toBe(false);

    const boulder = service.build(
      board(),
      [
        grass(),
        element(2, 'decor', { type: 'point', x: 128, y: 128 }, { scale: 420, label: 'Валун' }),
      ],
      64,
    );

    expect(boulder.speeds[cellOf(boulder, 10, 8)]).toBe(0);
    expect(boulder.opaque[cellOf(boulder, 10, 8)]).toBe(true);
  });

  it('читает явную скорость элемента вместо пресета', () => {
    const grid = service.build(
      board(),
      [grass(), element(2, 'terrain', { type: 'rect', x: 0, y: 0, w: 64, h: 256 }, { kind: 'dirt', speed: 60 })],
      64,
    );

    expect(grid.speeds[cellOf(grid, 0, 0)]).toBe(60);
    expect(grid.costs[cellOf(grid, 0, 0)]).toBeCloseTo(100 / 60);
    expect(grid.speeds[cellOf(grid, 6, 0)]).toBe(100);
  });

  it('оставляет непокрытую клетку непроходимой', () => {
    const grid = service.build(board(), [], 64);

    expect(grid.kinds[0]).toBe('none');
    expect(grid.speeds[0]).toBe(0);
    expect(grid.costs[0]).toBe(Number.POSITIVE_INFINITY);
  });

  it('закрытый проём держит стену, открытый прорезает', () => {
    const wallShape: SceneShape = { type: 'wall', points: [[64, 72], [96, 72]], width: 32 };
    const closed = service.build(
      board(),
      [
        grass(),
        element(2, 'obstacle', wallShape, { blocksMovement: true, blocksVision: true }),
        element(3, 'opening', wallShape, { state: 'closed' }),
      ],
      64,
    );
    const open = service.build(
      board(),
      [
        grass(),
        element(2, 'obstacle', wallShape, { blocksMovement: true, blocksVision: true }),
        element(3, 'opening', wallShape, { state: 'open' }),
      ],
      64,
    );

    expect(closed.speeds[cellOf(closed, 4, 4)]).toBe(0);
    expect(closed.opaque[cellOf(closed, 4, 4)]).toBe(true);
    expect(open.speeds[cellOf(open, 4, 4)]).toBe(100);
    expect(open.opaque[cellOf(open, 4, 4)]).toBe(false);
  });

  it('переводит мировые координаты в клетку', () => {
    const grid = service.build(board(), [grass()], 64);

    expect(surfaceIndex(grid, 8, 8)).toBe(cellOf(grid, 0, 0));
    expect(surfaceIndex(grid, 255, 255)).toBe(cellOf(grid, 15, 15));
    expect(surfaceIndex(grid, -1, 8)).toBe(-1);
    expect(surfaceIndex(grid, 256, 8)).toBe(-1);
  });
});
