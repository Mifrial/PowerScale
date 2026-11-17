import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
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

function element(id: number, boardId: number, shape: SceneShape, props: SceneElementProps = {}): SceneElement {
  return {
    id,
    boardId,
    kind: 'terrain',
    shape,
    props,
    sortOrder: id,
    targetBoardId: null,
    asset: null,
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

  it('отдаёт клик в колодце этажу ниже, а площадку оставляет дверью', () => {
    const root = board(3, 5120, 5120);
    const upper = board(4, 1408, 1216);
    const scene = model([root, upper], [enclosure(3, 4, 128, { x: 1983, y: 1614, w: 1408, h: 1216 })], 4);
    scene.elements = [
      element(90, 3, { type: 'rect', x: 0, y: 0, w: 5120, h: 5120 }, { kind: 'grass' }),
      element(91, 4, { type: 'rect', x: 0, y: 0, w: 1408, h: 1216 }, { kind: 'dirt' }),
      // Площадка перехода наверху и колодец за ней: марш лежит на нижнем поле.
      { ...element(92, 4, { type: 'rect', x: 1048, y: 286, w: 96, h: 190 }), kind: 'opening', props: { kind: 'stairs', state: 'open' }, targetBoardId: 3 },
      { ...element(93, 4, { type: 'rect', x: 1056, y: 476, w: 88, h: 310 }), kind: 'opening', props: { kind: 'stairs', state: 'closed' } },
    ];

    // Колодец: видно ступени нижнего этажа, клик принадлежит им.
    expect(service.targetAt(scene, [3083, 2200], true)).toEqual({ point: [3083, 2200], boardId: 3 });
    // Площадка — дверь: она остаётся на верхнем этаже.
    expect(service.targetAt(scene, [3079, 1995], true)).toEqual({ point: [1096, 381], boardId: 4 });
    // Без разреза плита непрозрачна: вырез не отдаёт клик вниз.
    expect(service.targetAt(scene, [3083, 2200], false)).toEqual({ point: [1100, 586], boardId: 4 });
    // Вне пятна верхнего этажа — общая земля корня.
    expect(service.targetAt(scene, [500, 500], true)).toEqual({ point: [500, 500], boardId: 3 });
  });

  it('режет плиту колодцами и площадками, а клику отдаёт только колодцы', () => {
    const root = board(3, 5120, 5120);
    const upper = board(4, 1408, 1216);
    const scene = model([root, upper], [enclosure(3, 4, 128, { x: 1983, y: 1614, w: 1408, h: 1216 })], 4);
    scene.elements = [
      { ...element(92, 4, { type: 'rect', x: 1048, y: 286, w: 96, h: 190 }), kind: 'opening', props: { kind: 'stairs', state: 'open' }, targetBoardId: 3 },
      { ...element(93, 4, { type: 'rect', x: 1056, y: 476, w: 88, h: 310 }), kind: 'opening', props: { kind: 'stairs', state: 'closed' } },
    ];

    expect(service.slabHoles(scene, 4, false)).toEqual([{ x: 1056, y: 476, width: 88, height: 310 }]);
    expect(service.slabHoles(scene, 4)).toHaveLength(2);
    expect(service.slabHoles(scene, 3)).toEqual([]);
  });

  it('берёт пятно плиты по нарисованному полу, а не по всему полю', () => {
    const root = board(3, 5120, 5120);
    const floor = board(4, 1408, 1216);
    const scene = model([root, floor], [enclosure(3, 4, 128)], 4);
    scene.elements = [
      element(79, 4, { type: 'rect', x: 256, y: 256, w: 896, h: 704 }, { kind: 'dirt' }),
      // Вода — не плита: под ней пол не лежит.
      element(80, 4, { type: 'rect', x: 0, y: 0, w: 1408, h: 1216 }, { kind: 'water' }),
      element(81, 4, { type: 'rect', x: 1200, y: 1000, w: 100, h: 100 }, { kind: 'grass' }),
    ];

    // Поля обзора вокруг плана (1408×1216) в пятно не входят: иначе под рез
    // попал бы забор двора, стоящий за домом.
    expect(service.slabBounds(scene, 4)).toEqual({ x: 256, y: 256, width: 1044, height: 844 });
    expect(service.slabBounds(scene, 3)).toBeNull();
  });
});
