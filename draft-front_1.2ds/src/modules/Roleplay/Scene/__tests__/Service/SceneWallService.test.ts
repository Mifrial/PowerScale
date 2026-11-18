import { describe, expect, it } from 'vitest';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SceneWallService, type SceneWallElement } from '@/modules/Roleplay/Scene/Service/SceneWallService';

const element = (
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
): SceneWallElement => ({ id, boardId: 1, kind, shape, props });

const wall = (points: [number, number][], width = 20): SceneWallElement =>
  element(1, 'obstacle', { type: 'wall', points, width }, { blocksMovement: true, blocksVision: true });

const opening = (points: [number, number][], props: SceneElementProps, width = 20): SceneWallElement =>
  element(2, 'opening', { type: 'wall', points, width }, props);

describe('SceneWallService', () => {
  const service = new SceneWallService();

  it('режет стену на участки вокруг проёма', () => {
    const door = opening(
      [
        [150, 100],
        [250, 100],
      ],
      { kind: 'door', state: 'open' },
    );
    const segments = service.segments(
      wall([
        [0, 100],
        [400, 100],
      ]),
      [door],
    );

    expect(segments).toHaveLength(2);
    expect(segments[0].type === 'wall' ? segments[0].points : []).toEqual([
      [0, 100],
      [150, 100],
    ]);
    expect(segments[1].type === 'wall' ? segments[1].points : []).toEqual([
      [250, 100],
      [400, 100],
    ]);
  });

  it('ставит протяжку вдоль стены проёмом', () => {
    const placement = service.place(
      [
        wall([
          [0, 100],
          [400, 100],
        ]),
      ],
      1,
      [100, 104],
      [240, 104],
      64,
      16,
      4,
    );

    expect(placement).not.toBeNull();
    expect(placement?.wallId).toBe(1);
    expect(placement?.offset).toBe(100);
    expect(placement?.width).toBe(140);
    expect(placement?.shape.type === 'wall' ? placement.shape.points : []).toEqual([
      [100, 100],
      [240, 100],
    ]);
  });

  it('короткий клик ставит проём пресетной ширины по центру', () => {
    const placement = service.place(
      [
        wall([
          [0, 100],
          [400, 100],
        ]),
      ],
      1,
      [200, 104],
      [200, 104],
      64,
      16,
      4,
    );

    expect(placement?.offset).toBe(168);
    expect(placement?.width).toBe(64);
  });

  it('не ставит проём мимо стены', () => {
    const placement = service.place(
      [
        wall([
          [0, 100],
          [400, 100],
        ]),
      ],
      1,
      [200, 200],
      [240, 200],
      64,
      16,
      4,
    );

    expect(placement).toBeNull();
  });

  it('не привязывает проём к стене, если он стоит в разрыве между кусками', () => {
    // Частокол собран из двух стен, калитка стоит между ними. Привязка к
    // ближайшему куску схлопнула бы её в единицу длины и потеряла бы при
    // загрузке, поэтому проём остаётся свободным.
    const gate = opening(
      [
        [200, 100],
        [300, 100],
      ],
      { kind: 'gate', state: 'open' },
    );

    service.attachAll([
      wall([
        [0, 100],
        [200, 100],
      ]),
      wall([
        [300, 100],
        [400, 100],
      ]),
      gate,
    ]);

    expect(gate.props.wallId).toBeUndefined();
    expect(gate.shape.type === 'wall' ? gate.shape.points : []).toEqual([
      [200, 100],
      [300, 100],
    ]);
  });

  it('восстанавливает привязку по геометрии', () => {
    const door = opening(
      [
        [150, 100],
        [250, 100],
      ],
      { kind: 'door', state: 'closed' },
    );

    service.attachAll([
      wall([
        [0, 100],
        [400, 100],
      ]),
      door,
    ]);

    expect(door.props.wallId).toBe(1);
    expect(door.props.offset).toBe(150);
    expect(door.shape.type === 'wall' ? door.shape.points : []).toEqual([
      [150, 100],
      [250, 100],
    ]);
  });

  it('двигает проём вместе со стеной', () => {
    const door = opening(
      [
        [150, 100],
        [250, 100],
      ],
      { kind: 'door', state: 'closed', wallId: 1, offset: 150 },
    );
    const moved = wall([
      [50, 100],
      [450, 100],
    ]);

    service.sync([moved, door], 1);

    expect(door.props.offset).toBe(150);
    expect(door.shape.type === 'wall' ? door.shape.points : []).toEqual([
      [200, 100],
      [300, 100],
    ]);
  });

  it('сжимает проём, если стена стала короче', () => {
    const door = opening(
      [
        [150, 100],
        [250, 100],
      ],
      { kind: 'door', state: 'closed', wallId: 1, offset: 150 },
    );

    service.sync(
      [
        wall([
          [0, 100],
          [160, 100],
        ]),
        door,
      ],
      1,
    );

    expect(door.props.offset).toBe(60);
    expect(door.shape.type === 'wall' ? door.shape.points : []).toEqual([
      [60, 100],
      [160, 100],
    ]);
  });

  it('притягивает конец стены к концу уже нарисованной', () => {
    const snapped = service.snapPoint(
      [
        wall([
          [0, 100],
          [400, 100],
        ]),
      ],
      1,
      [404, 104],
      10,
      32,
    );

    expect(snapped).toEqual([400, 100]);
  });

  it('притягивает к сетке, если рядом нет конца стены', () => {
    const snapped = service.snapPoint(
      [
        wall([
          [0, 100],
          [400, 100],
        ]),
      ],
      1,
      [73, 39],
      10,
      32,
    );

    expect(snapped).toEqual([64, 32]);
  });

  it('притягивает к телу стены, а не только к её концам', () => {
    const snapped = service.snapPoint(
      [
        wall([
          [0, 100],
          [400, 100],
        ]),
      ],
      1,
      [200, 108],
      10,
      32,
    );

    expect(snapped).toEqual([200, 100]);
  });

  it('сваривает близкие вершины в общий узел', () => {
    const first = element(1, 'obstacle', {
      type: 'wall',
      points: [
        [0, 0],
        [200, 0],
      ],
      width: 8,
    });
    const second = element(2, 'obstacle', {
      type: 'wall',
      points: [
        [201, 1],
        [200, 200],
      ],
      width: 8,
    });

    service.weld([first, second], 1, 2);

    expect(second.shape.type === 'wall' ? second.shape.points[0] : null).toEqual([200, 0]);
  });

  it('вставляет T-стык в тело стены, не меняя её длину', () => {
    const host = element(1, 'obstacle', {
      type: 'wall',
      points: [
        [0, 0],
        [400, 0],
      ],
      width: 8,
    });
    const branch = element(2, 'obstacle', {
      type: 'wall',
      points: [
        [200, 0],
        [200, 200],
      ],
      width: 8,
    });

    const affected = service.weld([host, branch], 1, 1);

    expect(affected).toContain(host.id);
    expect(host.shape.type === 'wall' ? host.shape.points : []).toEqual([
      [0, 0],
      [200, 0],
      [400, 0],
    ]);
    expect(service.length(host)).toBe(400);
  });

  it('возвращает стыки углов и шов кольца', () => {
    const corner = element(1, 'obstacle', {
      type: 'wall',
      points: [
        [0, 0],
        [100, 0],
      ],
      width: 8,
    });
    const side = element(2, 'obstacle', {
      type: 'wall',
      points: [
        [100, 0],
        [100, 100],
      ],
      width: 8,
    });
    const ring = element(3, 'obstacle', {
      type: 'wall',
      points: [
        [200, 0],
        [300, 0],
        [300, 100],
        [200, 0],
      ],
      width: 8,
    });

    const joints = service.joints([corner, side, ring], 1);

    expect(joints).toContainEqual({ point: [100, 0], width: 8 });
    expect(joints).toContainEqual({ point: [200, 0], width: 8 });
    expect(joints.some((joint) => joint.point[0] === 0 && joint.point[1] === 0)).toBe(false);
  });

  it('стык несёт материал самой широкой из сходящихся стен', () => {
    const thin = element(
      1,
      'obstacle',
      {
        type: 'wall',
        points: [
          [0, 0],
          [100, 0],
        ],
        width: 8,
      },
      { material: 'plaster' },
    );
    const thick = element(
      2,
      'obstacle',
      {
        type: 'wall',
        points: [
          [100, 0],
          [100, 100],
        ],
        width: 16,
      },
      { material: 'wood' },
    );

    expect(service.joints([thin, thick], 1)).toEqual([{ point: [100, 0], width: 16, material: 'wood' }]);
  });
});
