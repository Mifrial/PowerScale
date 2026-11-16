import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import { SceneFloorPlanService } from '@/modules/Roleplay/Scene/Service/SceneFloorPlanService';

const service = new SceneFloorPlanService();

function board(id: number, width = 1000, height = 800): SceneBoard {
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
  elevation: number,
  footprint = { x: 100, y: 100, w: 500, h: 400 },
): SceneEnclosure {
  return {
    id: childBoardId,
    parentBoardId,
    childBoardId,
    footprint: { type: 'rect', ...footprint },
    elevation,
    label: null,
    sortOrder: 0,
  };
}

function model(boards: SceneBoard[], enclosures: SceneEnclosure[], activeBoardId: number): SceneRenderModel {
  return {
    activeBoardId,
    resolution: 64,
    boards,
    elements: [],
    enclosures,
    selection: [],
    mode: 'preview',
  };
}

describe('SceneFloorPlanService', () => {
  it('держит корень и этаж стопки в мире корня', () => {
    const root = board(3, 5120, 5120);
    const floor = board(4, 1408, 1216);
    const plan = service.plan(model([root, floor], [enclosure(3, 4, 128)], 3));

    expect(plan.rootBoardId).toBe(3);
    expect(plan.floors.map((item) => item.boardId)).toEqual([3]);
    expect(plan.slabBoardId).toBeNull();
  });

  it('на втором этаже рисует корень и активный этаж последним', () => {
    const root = board(3, 5120, 5120);
    const floor = board(4, 1408, 1216);
    const plan = service.plan(model([root, floor], [enclosure(3, 4, 128)], 4));

    expect(plan.floors.map((item) => item.boardId)).toEqual([3, 4]);
    expect(plan.slabBoardId).toBe(4);
    expect(plan.floors[1]?.active).toBe(true);
    expect(plan.visibleBoardIds).toEqual([3, 4]);
  });

  it('переводит точку этажа в мир корня и обратно', () => {
    const root = board(3, 5120, 5120);
    const floor = board(4, 1408, 1216);
    const plan = service.plan(model([root, floor], [enclosure(3, 4, 128)], 4));
    const transform = plan.floors[1]?.transform;

    expect(transform).toBeDefined();
    expect(transform?.x).toBe(100);
    expect(transform?.y).toBe(100);
    expect(transform?.scaleX).toBeCloseTo(500 / 1408);
    expect(transform?.scaleY).toBeCloseTo(400 / 1216);

    const local: [number, number] = [704, 608];
    const world = service.toRoot(transform!, local);
    expect(world[0]).toBeCloseTo(100 + 704 * (500 / 1408));
    expect(world[1]).toBeCloseTo(100 + 608 * (400 / 1216));
    expect(service.toLocal(transform!, world)[0]).toBeCloseTo(704);
    expect(service.toLocal(transform!, world)[1]).toBeCloseTo(608);
  });

  it('рисует ближайший этаж ниже активного и не рисует верхние', () => {
    const root = board(3);
    const second = board(4);
    const third = board(5);
    const basement = board(6);
    const enclosures = [enclosure(3, 4, 128), enclosure(4, 5, 128), enclosure(4, 6, -128)];
    const plan = service.plan(model([root, second, third, basement], enclosures, 5));

    // Верхнего этажа нет, ниже активного — второй; корень остаётся землёй.
    expect(plan.floors.map((item) => item.boardId)).toEqual([3, 4, 5]);
    expect(plan.floors.find((item) => item.boardId === 4)?.below).toBe(true);
    expect(plan.slabBoardId).toBe(5);
  });

  it('на подвале рисует подвал последним', () => {
    const root = board(3);
    const second = board(4);
    const basement = board(6);
    const enclosures = [enclosure(3, 4, 128), enclosure(4, 6, -128)];
    const plan = service.plan(model([root, second, basement], enclosures, 6));

    expect(plan.floors.map((item) => item.boardId)).toEqual([3, 6]);
    expect(plan.floors[1]?.active).toBe(true);
    expect(plan.floors[1]?.elevation).toBe(0);
  });

  it('на корне не поднимает верхний этаж над землёй', () => {
    const root = board(3);
    const second = board(4);
    const plan = service.plan(model([root, second], [enclosure(3, 4, 128)], 3));

    expect(plan.floors.map((item) => item.boardId)).toEqual([3]);
  });

  it('держит несколько строений независимо', () => {
    const root = board(3, 5000, 5000);
    const first = board(4);
    const firstTop = board(5);
    const second = board(6);
    const enclosures = [
      enclosure(3, 4, 128, { x: 100, y: 100, w: 500, h: 400 }),
      enclosure(4, 5, 128, { x: 20, y: 20, w: 300, h: 200 }),
      enclosure(3, 6, 128, { x: 2000, y: 2000, w: 600, h: 400 }),
    ];
    const plan = service.plan(model([root, first, firstTop, second], enclosures, 5));

    // Фокус на верхнем этаже первого строения: у второго — его этаж, а не
    // верхний первого.
    expect(plan.floors.map((item) => item.boardId)).toEqual([3, 6, 4, 5]);
    expect(plan.floors.find((item) => item.boardId === 5)?.elevation).toBe(256);
  });

  it('режет стены только у своей стопки', () => {
    const root = board(3, 5000, 5000);
    const first = board(4);
    const firstTop = board(5);
    const second = board(6);
    const enclosures = [
      enclosure(3, 4, 128, { x: 100, y: 100, w: 500, h: 400 }),
      enclosure(4, 5, 128, { x: 20, y: 20, w: 300, h: 200 }),
      enclosure(3, 6, 128, { x: 2000, y: 2000, w: 600, h: 400 }),
    ];
    const plan = service.plan(model([root, first, firstTop, second], enclosures, 5));

    // Корень под плитой своего строения — режется; второй дом на земле стоит
    // рядом, его стены не срезаны.
    expect(plan.floors.find((item) => item.boardId === 3)?.underCut).toBe(true);
    expect(plan.floors.find((item) => item.boardId === 4)?.underCut).toBe(true);
    expect(plan.floors.find((item) => item.boardId === 6)?.underCut).toBe(false);
    expect(plan.floors.find((item) => item.boardId === 5)?.underCut).toBe(false);
  });

  it('на корне ничего не режет', () => {
    const root = board(3);
    const second = board(4);
    const plan = service.plan(model([root, second], [enclosure(3, 4, 128)], 3));

    expect(plan.floors.every((item) => !item.underCut)).toBe(true);
  });
});
