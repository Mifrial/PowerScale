import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { ScenePortalService } from '@/modules/Roleplay/Scene/Service/ScenePortalService';

const service = new ScenePortalService();

function board(id: number, width = 100, height = 100): SceneBoard {
  return {
    id,
    name: `Поле ${id}`,
    sortOrder: 0,
    baseFile: null,
    boundWidth: width,
    boundHeight: height,
    occupancyCell: 16,
    zQuantum: 16,
    gridStep: 0,
  };
}

function opening(
  id: number,
  boardId: number,
  targetBoardId: number | null,
  from: [number, number] = [40, 50],
  to: [number, number] = [60, 50],
): SceneElement {
  return {
    id,
    boardId,
    kind: 'opening',
    shape: { type: 'wall', points: [from, to], width: 8 },
    props: { kind: 'door', state: 'open' },
    sortOrder: 0,
    targetBoardId,
    asset: null,
  };
}

function landing(id: number, boardId: number, targetBoardId: number | null): SceneElement {
  return {
    id,
    boardId,
    kind: 'opening',
    shape: { type: 'rect', x: 20, y: 30, w: 40, h: 20 },
    props: { kind: 'stairs', state: 'open' },
    sortOrder: 0,
    targetBoardId,
    asset: null,
  };
}

function hole(id: number, boardId: number, x: number, y: number): SceneElement {
  return {
    id,
    boardId,
    kind: 'opening',
    shape: { type: 'rect', x, y, w: 40, h: 20 },
    props: { kind: 'stairs', state: 'closed', blocksMovement: true },
    sortOrder: 0,
    targetBoardId: null,
    asset: null,
  };
}

function stairs(id: number, boardId: number): SceneElement {
  return {
    id,
    boardId,
    kind: 'support',
    shape: { type: 'rect', x: 20, y: 30, w: 40, h: 100 },
    props: { kind: 'stairs', elevation: 128, height: 16, speed: 100, rotation: 180 },
    sortOrder: 0,
    targetBoardId: null,
    asset: null,
  };
}

describe('ScenePortalService', () => {
  it('находит проём с целевым полем под точкой', () => {
    const elements = [opening(1, 10, 20), opening(2, 10, null, [10, 10], [20, 10])];

    expect(service.portalAt(elements, 10, [50, 50], 4)?.opening.id).toBe(1);
    expect(service.portalAt(elements, 10, [15, 10], 4)).toBeNull();
  });

  it('выбирает ближайший парный проём обратно', () => {
    const elements = [opening(1, 20, 10, [10, 10], [20, 10]), opening(2, 20, 10, [80, 80], [90, 80])];

    expect(service.paired(elements, 20, 10, [85, 80])?.id).toBe(2);
  });

  it('находит площадку перехода прямоугольником', () => {
    const elements = [landing(1, 10, 20)];

    expect(service.portalAt(elements, 10, [40, 40], 0)?.opening.id).toBe(1);
    expect(service.portalAt(elements, 10, [10, 10], 0)).toBeNull();
  });

  it('берёт площадку при клике по колодцу рядом с ней', () => {
    const elements = [landing(1, 10, 20), hole(2, 10, 20, 50)];

    expect(service.portalAt(elements, 10, [40, 60], 4)?.opening.id).toBe(1);
    // Далёкий колодец без площадки рядом остаётся обычным проёмом.
    expect(service.portalAt([landing(1, 10, 20), hole(2, 10, 200, 200)], 10, [220, 210], 4)).toBeNull();
  });

  it('не считает марш переходом: клик по ступеням ставит на них', () => {
    const elements = [landing(1, 10, 20), stairs(2, 10)];

    // Марш — опора, а не проём: обычный клик идёт по навигации ступеней.
    expect(service.portalAt(elements, 10, [40, 100], 4)).toBeNull();
    // Площадка на высоком торце остаётся дверью на другой этаж.
    expect(service.portalAt(elements, 10, [40, 40], 0)?.opening.id).toBe(1);
  });

  it('переносит точку между полями через пятно вложения', () => {
    const footprint = { x: 100, y: 200, w: 400, h: 200 };
    const child = board(20, 200, 100);

    const local = service.toChild([300, 300], footprint, child);
    expect(local).toEqual([100, 50]);
    expect(service.toParent(local, footprint, child)).toEqual([300, 300]);
  });

  it('берёт габарит полигона как пятно вложения', () => {
    expect(
      service.footprint({
        type: 'polygon',
        points: [
          [10, 20],
          [50, 20],
          [50, 80],
          [10, 80],
        ],
      }),
    ).toEqual({ x: 10, y: 20, w: 40, h: 60 });
  });
});
