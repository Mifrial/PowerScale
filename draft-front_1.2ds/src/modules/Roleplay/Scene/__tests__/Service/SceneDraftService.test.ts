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
});
