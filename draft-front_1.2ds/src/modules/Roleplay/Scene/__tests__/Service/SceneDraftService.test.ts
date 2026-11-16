import { describe, expect, it } from 'vitest';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import { SceneDraftMapper } from '@/modules/Roleplay/Scene/Service/SceneDraftMapper';
import { SceneDraftService } from '@/modules/Roleplay/Scene/Service/SceneDraftService';

const mapper = new SceneDraftMapper();

function draft() {
  const detail: SceneDetail = {
    id: 1,
    ownerId: 1,
    name: 'Локация',
    description: '',
    resolution: 64,
    schemaVersion: 1,
    isArchived: false,
    version: 1,
    updatedBy: null,
    createdAt: 0,
    updatedAt: 0,
    canEdit: true,
    boards: [
      {
        id: 10,
        name: 'Двор',
        sortOrder: 0,
        baseFile: null,
        boundWidth: 1000,
        boundHeight: 800,
        occupancyCell: 16,
        zQuantum: 16,
        gridStep: 0,
      },
    ],
    elements: [],
    enclosures: [],
  };

  return mapper.fromDetail(detail);
}

describe('SceneDraftService', () => {
  it('allocates negative temp ids', () => {
    const service = new SceneDraftService();

    expect(service.allocateId()).toBe(-1);
    expect(service.allocateId()).toBe(-2);
  });

  it('adds a board with defaults and a free name', () => {
    const service = new SceneDraftService();
    const state = draft();

    const board = service.addBoard(state, service.freeBoardName(state));

    expect(board.id).toBe(-1);
    expect(board.sortOrder).toBe(1);
    expect(board.boundWidth).toBe(2560);
    expect(service.freeBoardName(state)).toBe('Поле 2');
  });

  it('removes a board with its elements but keeps the last board', () => {
    const service = new SceneDraftService();
    const state = draft();
    const second = service.addBoard(state, '1 этаж');
    service.addElement(state, second.id, 'obstacle', { type: 'rect', x: 0, y: 0, w: 10, h: 10 }, {});

    expect(service.removeBoard(state, second.id)).toBe(true);
    expect(state.boards).toHaveLength(1);
    expect(state.elements).toHaveLength(0);
    expect(service.removeBoard(state, 10)).toBe(false);
  });

  it('moves an element and clamps it to the board', () => {
    const service = new SceneDraftService();
    const state = draft();
    const element = service.addElement(state, 10, 'obstacle', { type: 'rect', x: 10, y: 10, w: 100, h: 100 }, {});

    service.moveElement(state, element.id, 10_000, -10_000);

    expect(element.shape).toEqual({ type: 'rect', x: 900, y: 0, w: 100, h: 100 });
  });

  it('clamps a circle inside the board', () => {
    const service = new SceneDraftService();
    const state = draft();
    const element = service.addElement(state, 10, 'terrain', { type: 'circle', cx: 100, cy: 100, r: 50 }, {});

    service.moveElement(state, element.id, -1000, -1000);

    expect(element.shape).toEqual({ type: 'circle', cx: 50, cy: 50, r: 50 });
  });

  it('clamps a polyline by all points', () => {
    const service = new SceneDraftService();
    const state = draft();
    const element = service.addElement(
      state,
      10,
      'obstacle',
      {
        type: 'wall',
        points: [
          [100, 100],
          [500, 100],
        ],
        width: 16,
      },
      {},
    );

    service.moveElement(state, element.id, 1000, 0);

    expect(element.shape).toEqual({
      type: 'wall',
      points: [
        [600, 100],
        [1000, 100],
      ],
      width: 16,
    });
  });

  it('detects a duplicate board name', () => {
    const service = new SceneDraftService();
    const state = draft();

    expect(service.isBoardNameTaken(state, 'Двор')).toBe(true);
    expect(service.isBoardNameTaken(state, 'Двор', 10)).toBe(false);
  });

  it('rescales geometry and sizes when resolution halves', () => {
    const service = new SceneDraftService();
    const state = draft();
    state.boards[0]!.gridStep = 32;
    const rect = service.addElement(
      state,
      10,
      'obstacle',
      { type: 'rect', x: 100, y: 200, w: 300, h: 400 },
      { height: 100 },
    );
    const wall = service.addElement(
      state,
      10,
      'obstacle',
      {
        type: 'wall',
        points: [
          [100, 100],
          [500, 100],
        ],
        width: 16,
      },
      {},
    );
    const circle = service.addElement(state, 10, 'obstacle', { type: 'circle', cx: 400, cy: 400, r: 160 }, {});
    const point = service.addElement(state, 10, 'decor', { type: 'point', x: 100, y: 200 }, {});
    const support = service.addElement(
      state,
      10,
      'support',
      { type: 'rect', x: 0, y: 0, w: 64, h: 64 },
      { height: 64, elevation: 32 },
    );

    service.rescale(state, 32);

    expect(state.resolution).toBe(32);
    expect(state.boards[0]).toMatchObject({
      boundWidth: 500,
      boundHeight: 400,
      occupancyCell: 8,
      zQuantum: 8,
      gridStep: 16,
    });
    expect(rect.shape).toEqual({ type: 'rect', x: 50, y: 100, w: 150, h: 200 });
    expect(rect.props.height).toBe(50);
    expect(wall.shape).toEqual({
      type: 'wall',
      points: [
        [50, 50],
        [250, 50],
      ],
      width: 8,
    });
    expect(circle.shape).toEqual({ type: 'circle', cx: 200, cy: 200, r: 80 });
    expect(point.shape).toEqual({ type: 'point', x: 50, y: 100 });
    expect(support.props.height).toBe(32);
    expect(support.props.elevation).toBe(16);
  });

  it('rescales decor footprint and silhouette with resolution', () => {
    const service = new SceneDraftService();
    const state = draft();
    const decor = service.addElement(
      state,
      10,
      'decor',
      { type: 'point', x: 100, y: 200 },
      {
        footprint: {
          shape: 'polygon',
          points: [
            [-40, -20],
            [40, -20],
            [40, 20],
            [-40, 20],
          ],
          handles: [
            { in: [0, 0], out: [8, 0] },
            { in: [-8, 0], out: [0, 0] },
            { in: [0, 0], out: [0, 0] },
            { in: [0, 0], out: [0, 0] },
          ],
          offset: [8, -4],
        },
        silhouette: { shape: 'circle', radius: 60, bottom: 20 },
      },
    );

    service.rescale(state, 32);

    expect(decor.props.footprint).toEqual({
      shape: 'polygon',
      points: [
        [-20, -10],
        [20, -10],
        [20, 10],
        [-20, 10],
      ],
      handles: [
        { in: [0, 0], out: [4, 0] },
        { in: [-4, 0], out: [0, 0] },
        { in: [0, 0], out: [0, 0] },
        { in: [0, 0], out: [0, 0] },
      ],
      offset: [4, -2],
    });
    expect(decor.props.silhouette).toEqual({ shape: 'circle', radius: 30, bottom: 10 });
  });

  it('редактирует вершины, ручки и кривизну основания декора', () => {
    const service = new SceneDraftService();
    const state = draft();
    const decor = service.addElement(
      state,
      10,
      'decor',
      { type: 'point', x: 100, y: 200 },
      {
        footprint: {
          shape: 'polygon',
          points: [
            [0, 0],
            [40, 0],
            [40, 40],
            [0, 40],
          ],
        },
      },
    );

    const points = () => {
      const footprint = decor.props.footprint;
      if (footprint?.shape !== 'polygon') {
        throw new Error('ожидался полигон основания');
      }

      return footprint.points;
    };

    service.setZoneVertex(state, decor.id, 'footprint', 1, [50, 5]);
    expect(points()[1]).toEqual([50, 5]);

    expect(service.insertZoneVertex(state, decor.id, 'footprint', [25, 2.5], 3)).toBe(true);
    expect(points().length).toBe(5);

    service.setZoneHandle(state, decor.id, 'footprint', 1, 'out', [10, 0]);
    const withHandle = decor.props.footprint;
    if (withHandle?.shape !== 'polygon') {
      throw new Error('ожидался полигон основания');
    }
    expect(withHandle.handles?.[1].out).toEqual([10, 0]);

    service.removeZoneVertex(state, decor.id, 'footprint', 1);
    expect(points().length).toBe(4);

    service.smoothZone(state, decor.id, 'footprint');
    const smoothed = decor.props.footprint;
    if (smoothed?.shape !== 'polygon') {
      throw new Error('ожидался полигон основания');
    }
    expect(smoothed.handles?.length).toBe(points().length);

    service.cornerZone(state, decor.id, 'footprint');
    const cornered = decor.props.footprint;
    if (cornered?.shape !== 'polygon') {
      throw new Error('ожидался полигон основания');
    }
    expect(cornered.handles?.every((handle) => handle.in[0] === 0 && handle.out[0] === 0)).toBe(true);
  });

  it('снимает силуэт с основания при первой правке зоны', () => {
    const service = new SceneDraftService();
    const state = draft();
    const decor = service.addElement(
      state,
      10,
      'decor',
      { type: 'point', x: 100, y: 200 },
      {
        footprint: {
          shape: 'polygon',
          points: [
            [0, 0],
            [40, 0],
            [40, 40],
            [0, 40],
          ],
        },
      },
    );

    service.setZoneVertex(state, decor.id, 'silhouette', 2, [60, 60]);

    const silhouette = decor.props.silhouette;
    if (silhouette?.shape !== 'polygon') {
      throw new Error('ожидался полигон силуэта');
    }
    expect(silhouette.points[2]).toEqual([60, 60]);
    expect(silhouette.bottom).toBe(0);
    // Основание осталось прежним: правка одной зоны не меняет другую.
    const footprint = decor.props.footprint;
    if (footprint?.shape !== 'polygon') {
      throw new Error('ожидался полигон основания');
    }
    expect(footprint.points[2]).toEqual([40, 40]);
  });

  it('rescales enclosure footprints with the parent board', () => {
    const service = new SceneDraftService();
    const state = draft();
    const child = service.addBoard(state, 'Верх');
    state.enclosures.push({
      id: -5,
      parentBoardId: 10,
      childBoardId: child.id,
      footprint: { type: 'rect', x: 100, y: 100, w: 200, h: 200 },
      sortOrder: 0,
    });

    service.rescale(state, 128);

    expect(state.enclosures[0]?.footprint).toEqual({ type: 'rect', x: 200, y: 200, w: 400, h: 400 });
  });

  it('fills the board with terrain and reuses the full-board element', () => {
    const service = new SceneDraftService();
    const state = draft();

    const first = service.fillBoardTerrain(state, 10, 'grass');
    expect(first?.shape).toEqual({ type: 'rect', x: 0, y: 0, w: 1000, h: 800 });
    expect(first?.props.kind).toBe('grass');

    const second = service.fillBoardTerrain(state, 10, 'water');
    expect(second?.id).toBe(first?.id);
    expect(state.elements).toHaveLength(1);
    expect(second?.props.kind).toBe('water');
  });

  it('stretches only the full-board terrain when bounds change', () => {
    const service = new SceneDraftService();
    const state = draft();
    const full = service.fillBoardTerrain(state, 10, 'grass');
    const partial = service.addElement(
      state,
      10,
      'terrain',
      { type: 'rect', x: 100, y: 100, w: 100, h: 100 },
      { kind: 'water' },
    );
    state.boards[0]!.boundWidth = 1200;
    state.boards[0]!.boundHeight = 900;

    service.stretchBoardTerrain(state, 10, 1000, 800);

    expect(full?.shape).toEqual({ type: 'rect', x: 0, y: 0, w: 1200, h: 900 });
    expect(partial.shape).toEqual({ type: 'rect', x: 100, y: 100, w: 100, h: 100 });
  });

  it('rescales polygon points', () => {
    const service = new SceneDraftService();
    const state = draft();
    const element = service.addElement(
      state,
      10,
      'terrain',
      {
        type: 'polygon',
        points: [
          [100, 100],
          [500, 100],
          [500, 500],
        ],
      },
      { kind: 'water' },
    );

    service.rescale(state, 32);

    expect(element.shape).toEqual({
      type: 'polygon',
      points: [
        [50, 50],
        [250, 50],
        [250, 250],
      ],
    });
  });

  it('creates a path with a constant width and smooth handles', () => {
    const service = new SceneDraftService();
    const shape = service.pathShape(
      [
        [100, 300],
        [500, 300],
        [900, 500],
      ],
      40,
    );

    expect(shape.type).toBe('path');
    if (shape.type !== 'path') return;
    expect(shape.widths).toEqual([40, 40, 40]);
    expect(shape.handles?.[1].out[0]).toBeGreaterThan(0);
  });

  it('inserts a vertex into a path and scales its widths', () => {
    const service = new SceneDraftService();
    const state = draft();
    const element = service.addElement(
      state,
      10,
      'terrain',
      {
        type: 'path',
        points: [
          [0, 100],
          [500, 100],
        ],
        widths: [20, 20],
      },
      { kind: 'water' },
    );

    service.insertVertex(state, element.id, 0, [250, 100], { in: [0, 0], out: [0, 0] }, 30);
    if (element.shape.type !== 'path') return;
    expect(element.shape.points).toEqual([
      [0, 100],
      [250, 100],
      [500, 100],
    ]);
    expect(element.shape.widths).toEqual([20, 30, 20]);

    service.setWidth(state, element.id, 1, 60);
    if (element.shape.type !== 'path') return;
    expect(element.shape.widths).toEqual([20, 60, 20]);
  });

  it('rescales path widths and bezier handles', () => {
    const service = new SceneDraftService();
    const state = draft();
    const element = service.addElement(
      state,
      10,
      'terrain',
      {
        type: 'path',
        points: [
          [100, 100],
          [500, 100],
        ],
        widths: [40, 80],
        handles: [
          { in: [0, 0], out: [100, 0] },
          { in: [-100, 0], out: [0, 0] },
        ],
      },
      { kind: 'water' },
    );

    service.rescale(state, 32);

    if (element.shape.type !== 'path') return;
    expect(element.shape.points).toEqual([
      [50, 50],
      [250, 50],
    ]);
    expect(element.shape.widths).toEqual([20, 40]);
    expect(element.shape.handles).toEqual([
      { in: [0, 0], out: [50, 0] },
      { in: [-50, 0], out: [0, 0] },
    ]);
  });

  it('прижимает сдвиг группы по самому тесному элементу', () => {
    const service = new SceneDraftService();
    const state = draft();
    service.addElement(state, 10, 'obstacle', { type: 'rect', x: 800, y: 0, w: 100, h: 100 }, {});
    service.addElement(state, 10, 'obstacle', { type: 'rect', x: 100, y: 0, w: 100, h: 100 }, {});

    const ids = state.elements.map((element) => element.id);

    expect(service.groupDelta(state, ids, 150, 0)).toEqual([100, 0]);
    expect(service.groupDelta(state, ids, -150, 0)).toEqual([-100, 0]);
  });

  it('двигает общий узел стены вместе с соседней стеной', () => {
    const service = new SceneDraftService();
    const state = draft();
    const first = service.addElement(
      state,
      10,
      'obstacle',
      {
        type: 'wall',
        points: [
          [100, 100],
          [300, 100],
        ],
        width: 8,
      },
      {},
    );
    const second = service.addElement(
      state,
      10,
      'obstacle',
      {
        type: 'wall',
        points: [
          [300, 100],
          [300, 300],
        ],
        width: 8,
      },
      {},
    );

    service.setVertexShared(state, first.id, 1, [350, 150]);

    expect(second.shape.type === 'wall' ? second.shape.points[0] : null).toEqual([350, 150]);
  });

  it('двигает шов замкнутого кольца как одну вершину', () => {
    const service = new SceneDraftService();
    const state = draft();
    const ring = service.addElement(
      state,
      10,
      'obstacle',
      {
        type: 'wall',
        points: [
          [100, 100],
          [300, 100],
          [300, 300],
          [100, 300],
          [100, 100],
        ],
        width: 8,
      },
      {},
    );

    service.setVertexShared(state, ring.id, 0, [50, 50]);

    if (ring.shape.type !== 'wall') return;
    expect(ring.shape.points[0]).toEqual([50, 50]);
    expect(ring.shape.points[ring.shape.points.length - 1]).toEqual([50, 50]);
  });

  it('меняет размер прямоугольника от ручки', () => {
    const service = new SceneDraftService();
    const state = draft();
    const bridge = service.addElement(
      state,
      10,
      'support',
      { type: 'rect', x: 100, y: 100, w: 100, h: 50 },
      { kind: 'bridge', rotation: 0 },
    );

    service.resizeRect(state, bridge.id, 'se', [300, 300]);

    expect(bridge.shape).toEqual({ type: 'rect', x: 100, y: 100, w: 200, h: 200 });
  });

  it('поворачивает прямоугольную опору и нормализует угол', () => {
    const service = new SceneDraftService();
    const state = draft();
    const bridge = service.addElement(
      state,
      10,
      'support',
      { type: 'rect', x: 100, y: 100, w: 100, h: 50 },
      { kind: 'bridge', rotation: 0 },
    );

    service.setRotation(state, bridge.id, 370);

    expect(bridge.props.rotation).toBe(10);
  });
});
