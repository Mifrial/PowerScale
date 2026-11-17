import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import {
  SceneFloorSightService,
  type SceneFloorSightPoint,
} from '@/modules/Roleplay/Scene/Service/SceneFloorSightService';

const RESOLUTION = 64;

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

function element(
  id: number,
  boardId: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
  targetBoardId: number | null = null,
): SceneElement {
  return {
    id,
    boardId,
    kind,
    shape,
    props,
    sortOrder: id,
    targetBoardId,
    asset: null,
  };
}

const enclosure = (parentBoardId: number, childBoardId: number): SceneEnclosure => ({
  id: childBoardId,
  parentBoardId,
  childBoardId,
  footprint: { type: 'rect', x: 700, y: 700, w: 400, h: 400 },
  elevation: 128,
  label: null,
  sortOrder: 0,
});

const root = board(1, 2000, 2000);
const upper = board(2, 400, 400);

const elements: SceneElement[] = [
  // Двор: общая земля корня.
  element(10, 1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2000, h: 2000 }, { kind: 'grass' }),
  // Плита верхнего этажа с колодцем.
  element(20, 2, 'terrain', { type: 'rect', x: 0, y: 0, w: 400, h: 400 }, { kind: 'dirt' }),
  element(21, 2, 'opening', { type: 'rect', x: 160, y: 160, w: 80, h: 80 }, { kind: 'stairs' }),
  // Наружная стена верхнего этажа с окном на западной половине.
  element(22, 2, 'obstacle', {
    type: 'wall',
    width: 16,
    points: [
      [0, 100],
      [400, 100],
    ],
  }),
  element(
    23,
    2,
    'opening',
    {
      type: 'wall',
      width: 16,
      points: [
        [100, 100],
        [180, 100],
      ],
    },
    { kind: 'window' },
  ),
];

const model: SceneRenderModel = {
  activeBoardId: 2,
  resolution: RESOLUTION,
  boards: [root, upper],
  elements,
  enclosures: [enclosure(1, 2)],
  selection: [],
  mode: 'preview',
};

describe('SceneFloorSightService', () => {
  const service = new SceneFloorSightService();
  const scene = service.scene(model);

  it('видит актора нижнего этажа через колодец', () => {
    // Наблюдатель стоит у колодца, цель — под ним на первом этаже.
    expect(service.sees(scene, { boardId: 2, point: [200, 140] }, { boardId: 1, point: [900, 900] })).toBe(true);
  });

  it('не видит актора под плитой вне колодца', () => {
    // Луч пересекает плоскость плиты южнее колодца: пол закрывает вид.
    expect(service.sees(scene, { boardId: 2, point: [200, 140] }, { boardId: 1, point: [900, 1090] })).toBe(false);
  });

  it('в разрезе показывает нижний этаж планом', () => {
    expect(
      service.sees(scene, { boardId: 2, point: [200, 140] }, { boardId: 1, point: [900, 1090] }, { slabs: false }),
    ).toBe(true);
  });

  it('видит двор через окно и не видит через глухую стену', () => {
    // Окно западнее: луч из комнаты во двор проходит через проём.
    expect(service.sees(scene, { boardId: 2, point: [120, 200] }, { boardId: 1, point: [840, 100] })).toBe(true);

    // Та же геометрия, но луч упирается в глухую часть стены.
    expect(service.sees(scene, { boardId: 2, point: [300, 200] }, { boardId: 1, point: [1000, 100] })).toBe(false);
  });

  it('видит клетку двора через окно, но не под плитой', () => {
    expect(
      service.seesSurface(scene, { boardId: 2, point: [120, 200] }, { boardId: 1, point: [840, 100], height: 96 }),
    ).toBe(true);
    expect(
      service.seesSurface(scene, { boardId: 2, point: [200, 140] }, { boardId: 1, point: [900, 1090], height: 96 }),
    ).toBe(false);
  });

  it('видит актора сверху только через колодец', () => {
    expect(service.sees(scene, { boardId: 1, point: [900, 900] }, { boardId: 2, point: [200, 200] })).toBe(true);
    expect(service.sees(scene, { boardId: 1, point: [1000, 1000] }, { boardId: 2, point: [300, 300] })).toBe(false);
  });

  it('на одном поле не считает: там обзор по сетке', () => {
    expect(service.sees(scene, { boardId: 2, point: [100, 100] }, { boardId: 2, point: [300, 300] })).toBe(true);
  });

  it('с крыши видно двор поверх стен своего этажа', () => {
    // Наблюдатель стоит на крыше над вторым этажом: стены этажа ниже него.
    const from: SceneFloorSightPoint = { boardId: 2, point: [300, 200], surface: 128 };
    const to: SceneFloorSightPoint = { boardId: 1, point: [1000, 100] };

    // Без признака крыши луч упирается в глухую стену второго этажа.
    expect(service.sees(scene, from, to)).toBe(false);
    expect(service.sees(scene, from, to, { onRoof: true })).toBe(true);
  });

  it('с крыши видно актора нижнего этажа', () => {
    const from: SceneFloorSightPoint = { boardId: 2, point: [200, 140], surface: 128 };

    expect(service.sees(scene, from, { boardId: 1, point: [900, 900] }, { onRoof: true })).toBe(true);
  });

  it('срезает стены нижнего этажа только под пятном плиты', () => {
    const walled: SceneRenderModel = {
      ...model,
      elements: [
        ...elements,
        element(30, 1, 'obstacle', {
          type: 'wall',
          width: 16,
          points: [
            [0, 950],
            [2000, 950],
          ],
        }),
      ],
    };
    const walledScene = service.scene(walled);
    // Наблюдатель — на нижнем этаже под разрезом, цель — выше стены.
    const from: SceneFloorSightPoint = { boardId: 1, point: [900, 1000] };
    const to: SceneFloorSightPoint = { boardId: 2, point: [200, 200] };

    // Без разреза луч упирается в стену этажа наблюдателя.
    expect(service.sees(walledScene, from, to, { slabs: false })).toBe(false);
    // Под пятном плиты стена срезана и в рисунке, и для взгляда.
    expect(
      service.sees(walledScene, from, to, {
        slabs: false,
        cut: { boardId: 1, bounds: { x: 700, y: 700, width: 400, height: 400 } },
      }),
    ).toBe(true);
  });
});
