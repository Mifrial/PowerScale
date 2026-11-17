import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import { sceneFloorPlanService } from '@/modules/Roleplay/Scene/Service/Instance/sceneFloorPlanService';
import { SceneRouteService } from '@/modules/Roleplay/Scene/Service/SceneRouteService';

const service = new SceneRouteService();

function board(id: number, width: number, height: number): SceneBoard {
  return {
    id,
    name: `Поле ${id}`,
    sortOrder: id,
    baseFile: null,
    boundWidth: width,
    boundHeight: height,
    occupancyCell: 16,
    zQuantum: 16,
    gridStep: 0,
  };
}

function enclosure(
  parentBoardId: number,
  childBoardId: number,
  footprint: { x: number; y: number; w: number; h: number },
): SceneEnclosure {
  return {
    id: childBoardId,
    parentBoardId,
    childBoardId,
    footprint: { type: 'rect', ...footprint },
    elevation: 128,
    label: null,
    sortOrder: 0,
  };
}

function grass(id: number, boardId: number, width: number, height: number): SceneElement {
  return {
    id,
    boardId,
    kind: 'terrain',
    shape: { type: 'rect', x: 0, y: 0, w: width, h: height },
    props: { kind: 'grass', speed: 100 },
    sortOrder: 0,
    targetBoardId: null,
    asset: null,
  };
}

function landing(
  id: number,
  boardId: number,
  targetBoardId: number,
  x: number,
  y: number,
  w = 60,
  h = 60,
): SceneElement {
  return {
    id,
    boardId,
    kind: 'opening',
    shape: { type: 'rect', x, y, w, h },
    props: { kind: 'stairs', state: 'open' },
    sortOrder: id,
    targetBoardId,
    asset: null,
  };
}

function hole(id: number, boardId: number, x: number, y: number, w = 60, h = 60): SceneElement {
  return {
    id,
    boardId,
    kind: 'opening',
    shape: { type: 'rect', x, y, w, h },
    props: { kind: 'stairs', state: 'closed', blocksMovement: true },
    sortOrder: id,
    targetBoardId: null,
    asset: null,
  };
}

/** Проходимая крыша над полем: настил на высоте этажа. */
function roof(id: number, boardId: number, width: number, height: number): SceneElement {
  return {
    id,
    boardId,
    kind: 'support',
    shape: { type: 'rect', x: 0, y: 0, w: width, h: height },
    props: { kind: 'roof', elevation: 128, height: 16, speed: 100, walkable: true },
    sortOrder: id,
    targetBoardId: null,
    asset: null,
  };
}

const boards = [board(1, 1000, 800), board(2, 400, 400), board(3, 300, 300)];
const enclosures = [
  enclosure(1, 2, { x: 200, y: 200, w: 400, h: 400 }),
  enclosure(2, 3, { x: 50, y: 50, w: 300, h: 300 }),
];
const elements = [
  grass(1, 1, 1000, 800),
  grass(2, 2, 400, 400),
  grass(3, 3, 300, 300),
  landing(10, 1, 2, 300, 300),
  landing(11, 2, 1, 100, 100),
  landing(12, 1, 3, 350, 350, 40, 40),
  landing(13, 3, 1, 100, 100, 40, 40),
];

const model: SceneRenderModel = {
  activeBoardId: 1,
  resolution: 64,
  boards,
  elements,
  enclosures,
  selection: [],
  mode: 'preview',
};

describe('SceneRouteService', () => {
  it('ведёт с земли на этаж через площадку и не рвёт мир', () => {
    const route = service.route(
      model,
      { boardId: 1, point: [50, 50] },
      { boardId: 2, point: [300, 300] },
      { agentRadius: 16 },
    );

    expect(route).not.toBeNull();
    const ids = route!.segments.map((segment) => segment.boardId);
    expect(ids[0]).toBe(1);
    expect(ids[ids.length - 1]).toBe(2);
    expect(new Set(ids)).toEqual(new Set([1, 2]));

    // Стык этажей — одна точка мира: актор не прыгает на другую доску.
    const lower = route!.segments.filter((segment) => segment.boardId === 1).at(-1)!;
    const upper = route!.segments.find((segment) => segment.boardId === 2)!;
    const lowerPoint = lower.points[lower.points.length - 1];
    const upperPoint = upper.points[0];
    const lowerWorld = sceneFloorPlanService.toRoot(sceneFloorPlanService.transform(model, 1), lowerPoint);
    const upperWorld = sceneFloorPlanService.toRoot(sceneFloorPlanService.transform(model, 2), upperPoint);
    expect(lowerWorld[0]).toBeCloseTo(upperWorld[0], 5);
    expect(lowerWorld[1]).toBeCloseTo(upperWorld[1], 5);
  });

  it('соединяет несоседние этажи одним ребром', () => {
    const route = service.route(
      model,
      { boardId: 1, point: [50, 50] },
      { boardId: 3, point: [250, 250] },
      { agentRadius: 16 },
    );

    expect(route).not.toBeNull();
    // Второй этаж не участвует: площадки 12 и 13 связывают землю и третий
    // этаж напрямую.
    expect(new Set(route!.segments.map((segment) => segment.boardId))).toEqual(new Set([1, 3]));
  });

  it('в пределах поля не ищет этажи', () => {
    const route = service.route(
      model,
      { boardId: 1, point: [50, 50] },
      { boardId: 1, point: [500, 500] },
      { agentRadius: 16 },
    );

    expect(route).not.toBeNull();
    expect(new Set(route!.segments.map((segment) => segment.boardId))).toEqual(new Set([1]));
  });

  it('с площадки под крышей идёт по полу, а не по крыше', () => {
    // Крыша накрывает площадку: с неё маршрут не должен стартовать наверху.
    const withRoof: SceneRenderModel = {
      ...model,
      elements: [...elements, roof(30, 2, 400, 400)],
    };
    const route = service.route(
      withRoof,
      { boardId: 1, point: [50, 50], level: 0 },
      { boardId: 2, point: [300, 300], level: 0 },
      { agentRadius: 16 },
    );

    expect(route).not.toBeNull();
    const upper = route!.segments.find((segment) => segment.boardId === 2)!;
    expect(upper.level).toBe(0);
  });

  it('переходит на кромке колодца, а не в центре площадки', () => {
    // Колодец вплотную под верхней площадкой: актор выходит из него на пол,
    // а не идёт под настилом до середины площадки.
    const withHole: SceneRenderModel = {
      ...model,
      elements: [...elements, hole(20, 2, 100, 160)],
    };
    const route = service.route(
      withHole,
      { boardId: 1, point: [50, 50] },
      { boardId: 2, point: [300, 300] },
      { agentRadius: 16 },
    );

    expect(route).not.toBeNull();
    const lower = route!.segments.filter((segment) => segment.boardId === 1).at(-1)!;
    const upper = route!.segments.find((segment) => segment.boardId === 2)!;
    const lowerWorld = sceneFloorPlanService.toRoot(
      sceneFloorPlanService.transform(withHole, 1),
      lower.points[lower.points.length - 1],
    );
    const upperWorld = sceneFloorPlanService.toRoot(sceneFloorPlanService.transform(withHole, 2), upper.points[0]);

    // Общая кромка площадки 11 и колодца — y = 160 на поле 2, то есть
    // (330, 360) в мире корня.
    expect(lowerWorld[0]).toBeCloseTo(330, 3);
    expect(lowerWorld[1]).toBeCloseTo(360, 3);
    expect(upperWorld[0]).toBeCloseTo(330, 3);
    expect(upperWorld[1]).toBeCloseTo(360, 3);
  });
});
