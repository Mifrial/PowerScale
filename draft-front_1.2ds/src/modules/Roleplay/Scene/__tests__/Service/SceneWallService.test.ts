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
});
