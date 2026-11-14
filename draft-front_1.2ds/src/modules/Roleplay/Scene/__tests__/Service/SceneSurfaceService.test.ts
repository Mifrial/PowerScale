import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import {
  SceneSurfaceService,
  surfaceCenter,
  surfaceIndex,
  surfaceUnderDeck,
} from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';

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
    expect(grid.sightBottom[cellOf(grid, 8, 8)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('считает высоту настила и зазор под ним', () => {
    const high = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, 10),
        element(
          3,
          'support',
          { type: 'rect', x: 96, y: 96, w: 64, h: 64 },
          { kind: 'bridge', elevation: 100, height: 16 },
          20,
        ),
      ],
      64,
    );
    const low = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, 10),
        element(
          3,
          'support',
          { type: 'rect', x: 96, y: 96, w: 64, h: 64 },
          { kind: 'bridge', elevation: 0, height: 16 },
          20,
        ),
      ],
      64,
    );

    expect(high.elevation[cellOf(high, 8, 8)]).toBe(100);
    expect(high.clearance[cellOf(high, 8, 8)]).toBe(84);
    expect(low.elevation[cellOf(low, 8, 8)]).toBe(0);
    expect(low.clearance[cellOf(low, 8, 8)]).toBe(-16);
    // Вне настила потолка нет: зазор — открытое небо.
    expect(high.clearance[cellOf(high, 0, 0)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('считает «под настилом» только при конечном положительном зазоре', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'support',
          { type: 'rect', x: 96, y: 96, w: 64, h: 64 },
          { kind: 'bridge', elevation: 100, height: 16 },
          20,
        ),
        element(
          3,
          'support',
          { type: 'rect', x: 0, y: 0, w: 64, h: 64 },
          { kind: 'bridge', elevation: 0, height: 16 },
          30,
        ),
      ],
      64,
    );

    // Под высоким настилом зазор конечный и положительный.
    expect(surfaceUnderDeck(grid, 136, 136)).toBe(true);
    // Настил-пол: зазор отрицательный, это не потолок.
    expect(surfaceUnderDeck(grid, 40, 40)).toBe(false);
    // Открытая трава: потолка нет вовсе.
    expect(surfaceUnderDeck(grid, 200, 200)).toBe(false);
  });

  it('поворачивает настил моста вместе с опорой', () => {
    const shape: SceneShape = { type: 'rect', x: 93, y: 84, w: 70, h: 40 };
    const straight = service.build(board(), [grass(), element(2, 'support', shape, { kind: 'bridge' }, 20)], 64);
    const rotated = service.build(
      board(),
      [grass(), element(2, 'support', shape, { kind: 'bridge', rotation: 90 }, 20)],
      64,
    );

    // Клетка (8,4) попадает в повёрнутый настил, но не в осевой.
    expect(straight.kinds[cellOf(straight, 8, 4)]).toBe('grass');
    expect(rotated.kinds[cellOf(rotated, 8, 4)]).toBe('bridge');
  });

  it('съезд считается настилом, как мост', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, 10),
        element(3, 'support', { type: 'rect', x: 96, y: 96, w: 64, h: 64 }, { kind: 'ramp' }, 20),
      ],
      64,
    );

    expect(grid.kinds[cellOf(grid, 8, 8)]).toBe('bridge');
    expect(grid.speeds[cellOf(grid, 8, 8)]).toBe(150);
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
    expect(grid.sightBottom[cellOf(grid, 4, 4)]).toBe(0);
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
    expect(transparent.sightBottom[cellOf(transparent, 4, 4)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('декор перекрывает ход основанием и обзор силуэтом', () => {
    const tree = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'decor',
          { type: 'point', x: 128, y: 128 },
          { scale: 100, baseRadius: 20, sightRadius: 45, sightBottom: 85, label: 'Дерево' },
        ),
      ],
      64,
    );
    const trunk = cellOf(tree, 8, 8);
    const crown = cellOf(tree, 9, 8);
    const [trunkX, trunkY] = surfaceCenter(tree, trunk);

    expect(Math.hypot(trunkX - 128, trunkY - 128)).toBeLessThan(20);
    expect(tree.speeds[trunk]).toBe(0);
    expect(tree.sightBottom[trunk]).toBe(0);

    // Крона шире ствола, но ход не перекрывает и начинается выше глаз.
    expect(tree.speeds[crown]).toBe(100);
    expect(tree.sightBottom[crown]).toBeCloseTo(0.85);
    expect(tree.sightBottom[cellOf(tree, 10, 8)]).toBe(Number.POSITIVE_INFINITY);

    const boulder = service.build(
      board(),
      [grass(), element(2, 'decor', { type: 'point', x: 128, y: 128 }, { scale: 420, label: 'Валун' })],
      64,
    );

    expect(boulder.speeds[cellOf(boulder, 10, 8)]).toBe(0);
    expect(boulder.sightBottom[cellOf(boulder, 10, 8)]).toBe(0);
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
    const wallShape: SceneShape = {
      type: 'wall',
      points: [
        [64, 72],
        [96, 72],
      ],
      width: 32,
    };
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
    expect(closed.sightBottom[cellOf(closed, 4, 4)]).toBe(0);
    expect(open.speeds[cellOf(open, 4, 4)]).toBe(100);
    expect(open.sightBottom[cellOf(open, 4, 4)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('переводит мировые координаты в клетку', () => {
    const grid = service.build(board(), [grass()], 64);

    expect(surfaceIndex(grid, 8, 8)).toBe(cellOf(grid, 0, 0));
    expect(surfaceIndex(grid, 255, 255)).toBe(cellOf(grid, 15, 15));
    expect(surfaceIndex(grid, -1, 8)).toBe(-1);
    expect(surfaceIndex(grid, 256, 8)).toBe(-1);
  });
});
