import { describe, expect, it } from 'vitest';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
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

  /** Точка накрыта хотя бы одним контуром. */
  const covers = (polygons: ScenePoint[][][], point: ScenePoint): boolean =>
    polygons.some((polygon) => {
      const ring = polygon[0];
      let result = false;
      for (let index = 0, previous = ring.length - 1; index < ring.length; previous = index, index += 1) {
        const [x1, y1] = ring[index];
        const [x2, y2] = ring[previous];
        if (y1 > point[1] !== y2 > point[1] && point[0] < ((x2 - x1) * (point[1] - y1)) / (y2 - y1) + x1) {
          result = !result;
        }
      }

      return result;
    });

  it('сводит стены в углу в один силуэт', () => {
    const horizontal = wall([
      [0, 0],
      [200, 0],
    ], 8);
    const vertical = element(2, 'obstacle', {
      type: 'wall',
      points: [
        [200, 0],
        [200, 200],
      ],
      width: 8,
    });

    const layout = service.layout([horizontal, vertical], []);

    // Полос две, а силуэт один: в углу одна заливка и одно кольцо.
    expect(layout.bands).toHaveLength(2);
    expect(layout.outlines).toHaveLength(1);
    expect(layout.outlines[0].polygons).toHaveLength(1);
    expect(layout.outlines[0].polygons[0]).toHaveLength(1);
  });

  it('угол доходит до митры: полоса продлевается за осевую соседа', () => {
    const horizontal = wall(
      [
        [0, 0],
        [200, 0],
      ],
      8,
    );
    const vertical = element(2, 'obstacle', {
      type: 'wall',
      points: [
        [200, 0],
        [200, 200],
      ],
      width: 8,
    });

    const layout = service.layout([horizontal, vertical], []);

    // Митра прямого угла — (204, -4); с тупыми торцами здесь была бы ступенька.
    expect(covers(layout.area, [203, -3])).toBe(true);
  });

  it('сводит Т-стык и нахлёст в один силуэт', () => {
    const host = wall([
      [0, 0],
      [400, 0],
    ], 8);
    const branch = element(2, 'obstacle', {
      type: 'wall',
      points: [
        [200, -100],
        [200, 100],
      ],
      width: 8,
    });

    const layout = service.layout([host, branch], []);

    expect(layout.outlines).toHaveLength(1);
    expect(layout.outlines[0].polygons).toHaveLength(1);
  });

  it('три стены в общем узле дают один силуэт', () => {
    const left = wall([
      [0, 100],
      [100, 100],
    ], 8);
    const right = element(2, 'obstacle', {
      type: 'wall',
      points: [
        [100, 100],
        [200, 100],
      ],
      width: 8,
    });
    const down = element(3, 'obstacle', {
      type: 'wall',
      points: [
        [100, 100],
        [100, 200],
      ],
      width: 8,
    });

    expect(service.layout([left, right, down], []).outlines[0].polygons).toHaveLength(1);
  });

  it('кольцо стен даёт кольцо с дыркой комнаты', () => {
    const ring = wall([
      [0, 0],
      [200, 0],
      [200, 200],
      [0, 200],
      [0, 0],
    ], 20);

    const layout = service.layout([ring], []);

    expect(layout.bands).toHaveLength(2);
    expect(layout.outlines).toHaveLength(1);
    expect(layout.outlines[0].polygons).toHaveLength(1);
    // Внешний контур и дырка комнаты: сплошная плита закрыла бы пол.
    expect(layout.outlines[0].polygons[0]).toHaveLength(2);
  });

  it('стены разного материала дают два контура', () => {
    const plaster = element(
      1,
      'obstacle',
      {
        type: 'wall',
        points: [
          [0, 0],
          [200, 0],
        ],
        width: 8,
      },
      { material: 'plaster' },
    );
    const wood = element(
      2,
      'obstacle',
      {
        type: 'wall',
        points: [
          [200, 0],
          [200, 200],
        ],
        width: 8,
      },
      { material: 'wood' },
    );

    expect(service.layout([plaster, wood], []).outlines).toHaveLength(2);
  });

  it('режет стену проёмом на две полосы', () => {
    const host = wall([
      [0, 100],
      [400, 100],
    ], 8);
    const door = opening(
      [
        [150, 100],
        [250, 100],
      ],
      { kind: 'door', state: 'closed' },
      8,
    );

    const layout = service.layout([host], [door]);

    expect(layout.bands).toHaveLength(2);
    expect(layout.outlines).toHaveLength(1);
    expect(layout.outlines[0].polygons).toHaveLength(2);
  });

  it('тень стены не заходит в комнату и не имеет дырок', () => {
    const ring = wall([
      [0, 0],
      [200, 0],
      [200, 200],
      [0, 200],
      [0, 0],
    ], 20);

    const layout = service.layout([ring], []);
    const shadow = service.shadow(layout.area, [20, 0]);

    expect(shadow.length).toBeGreaterThan(0);
    for (const polygon of shadow) {
      expect(polygon).toHaveLength(1);
    }

    // Комната остаётся светлой, тень уходит за стену в сторону сдвига.
    expect(covers(shadow, [100, 100])).toBe(false);
    expect(covers(shadow, [220, 100])).toBe(true);
  });
});
