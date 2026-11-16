import { describe, expect, it } from 'vitest';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import {
  SceneWallGraphService,
  type SceneWallGraphElement,
} from '@/modules/Roleplay/Scene/Service/SceneWallGraphService';

const element = (
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
): SceneWallGraphElement => ({ id, boardId: 1, kind, shape, props });

const wall = (id: number, points: [number, number][], width = 8): SceneWallGraphElement =>
  element(id, 'obstacle', { type: 'wall', points, width });

const square = (prefix: number, x: number, y: number, size: number): SceneWallGraphElement[] => [
  wall(prefix, [
    [x, y],
    [x + size, y],
  ]),
  wall(prefix + 1, [
    [x + size, y],
    [x + size, y + size],
  ]),
  wall(prefix + 2, [
    [x + size, y + size],
    [x, y + size],
  ]),
  wall(prefix + 3, [
    [x, y + size],
    [x, y],
  ]),
];

const bounds = { x: 0, y: 0, width: 1000, height: 1000 };

describe('SceneWallGraphService', () => {
  const service = new SceneWallGraphService();

  it('находит комнату в замкнутом контуре', () => {
    const elements = square(1, 0, 0, 400);

    const graph = service.build(elements, 1);
    const rooms = service.rooms(elements, 1, bounds);

    expect(graph.faces.filter((face) => face.bounded)).toHaveLength(1);
    expect(rooms).toHaveLength(1);
    expect(rooms[0].area).toBe(160000);
  });

  it('делит комнату перегородкой на две', () => {
    const elements = [
      wall(1, [
        [0, 0],
        [200, 0],
      ]),
      wall(2, [
        [200, 0],
        [400, 0],
      ]),
      wall(3, [
        [400, 0],
        [400, 400],
      ]),
      wall(4, [
        [400, 400],
        [0, 400],
      ]),
      wall(5, [
        [0, 400],
        [0, 0],
      ]),
      wall(6, [
        [200, 0],
        [200, 400],
      ]),
    ];

    const rooms = service.rooms(elements, 1, bounds);

    expect(rooms).toHaveLength(2);
    expect(rooms.map((room) => room.area).sort((left, right) => left - right)).toEqual([80000, 80000]);
  });

  it('считает кольцо комнатой и берёт самый маленький вложенный контур', () => {
    const ring = wall(1, [
      [0, 0],
      [100, 0],
      [100, 100],
      [0, 100],
      [0, 0],
    ]);
    const house = square(10, 200, 200, 100);
    const yard = square(20, 150, 150, 200);

    const graph = service.build([ring, ...house, ...yard], 1);
    const insideHouse = service.faceAt(graph, [250, 250]);
    const insideYard = service.faceAt(graph, [170, 250]);

    expect(insideHouse?.area).toBe(10000);
    expect(insideYard?.area).toBe(40000);
  });

  it('включает в комнату пол и содержимое, но не поле целиком', () => {
    const elements = [
      ...square(1, 0, 0, 400),
      element(10, 'terrain', { type: 'rect', x: 0, y: 0, w: 1000, h: 1000 }, { kind: 'grass' }),
      element(11, 'terrain', { type: 'rect', x: 50, y: 50, w: 300, h: 300 }, { kind: 'dirt' }),
      element(12, 'marker', { type: 'point', x: 200, y: 200 }, { role: 'npc_spawn' }),
      element(13, 'marker', { type: 'point', x: 800, y: 800 }, { role: 'npc_spawn' }),
    ];

    const rooms = service.rooms(elements, 1, bounds);
    const ids = rooms[0].elementIds;

    expect(ids).toContain(11);
    expect(ids).toContain(12);
    expect(ids).not.toContain(10);
    expect(ids).not.toContain(13);
    expect(rooms[0].floorIds).toEqual([11]);
  });

  it('собирает строение: стены и всё внутри внешнего контура', () => {
    const elements = [
      ...square(1, 0, 0, 400),
      element(10, 'marker', { type: 'point', x: 200, y: 200 }, { role: 'npc_spawn' }),
      element(11, 'marker', { type: 'point', x: 800, y: 800 }, { role: 'npc_spawn' }),
      element(12, 'terrain', { type: 'rect', x: 0, y: 0, w: 1000, h: 1000 }, { kind: 'grass' }),
    ];

    const ids = service.building(elements, 1, 1, bounds);

    expect(ids).toEqual(expect.arrayContaining([1, 2, 3, 4, 10]));
    expect(ids).not.toContain(11);
    expect(ids).not.toContain(12);
  });

  it('находит строение под точкой и берёт самое маленькое из вложенных', () => {
    const house = square(10, 200, 200, 100);
    const yard = square(20, 100, 100, 300);
    const elements = [...house, ...yard];

    const insideHouse = service.buildingAt(elements, 1, [250, 250], bounds);
    const insideYard = service.buildingAt(elements, 1, [120, 120], bounds);

    expect(insideHouse).toEqual(expect.arrayContaining([10, 11, 12, 13]));
    expect(insideHouse).not.toContain(20);
    expect(insideYard).toEqual(expect.arrayContaining([20, 21, 22, 23, 10]));
  });

  it('пол дома тянет всё строение, а заплатка внутри — нет', () => {
    const elements = [
      ...square(1, 0, 0, 400),
      element(10, 'terrain', { type: 'rect', x: 0, y: 0, w: 400, h: 400 }, { kind: 'dirt' }),
      element(11, 'terrain', { type: 'rect', x: 50, y: 50, w: 50, h: 50 }, { kind: 'dirt' }),
    ];

    const house = service.buildingOfTerrain(elements, 1, 10, bounds);
    const patch = service.buildingOfTerrain(elements, 1, 11, bounds);

    expect(house).toEqual(expect.arrayContaining([1, 2, 3, 4, 10]));
    expect(patch).toEqual([]);
  });

  it('пол комнаты тянет комнату, а не всё строение', () => {
    const elements = [
      wall(1, [
        [0, 0],
        [200, 0],
      ]),
      wall(2, [
        [200, 0],
        [400, 0],
      ]),
      wall(3, [
        [400, 0],
        [400, 400],
      ]),
      wall(4, [
        [400, 400],
        [0, 400],
      ]),
      wall(5, [
        [0, 400],
        [0, 0],
      ]),
      wall(6, [
        [200, 0],
        [200, 400],
      ]),
      element(10, 'terrain', { type: 'rect', x: 0, y: 0, w: 200, h: 400 }, { kind: 'dirt' }),
    ];

    const ids = service.buildingOfTerrain(elements, 1, 10, bounds);

    expect(ids).toContain(6);
    expect(ids).not.toContain(2);
    expect(ids).not.toContain(3);
  });

  it('не забирает чужой забор и его калитку, если контур дома накрыл забор', () => {
    // Двор 0..800; дом 100..700 × 300..900 наехал на южную стену двора. Середина
    // стены оказалась внутри контура дома, но сама стена выходит за него.
    const elements = [
      ...square(20, 0, 0, 800),
      ...square(10, 100, 300, 600),
      element(
        30,
        'opening',
        {
          type: 'wall',
          points: [
            [380, 800],
            [420, 800],
          ],
          width: 8,
        },
        { kind: 'gate' },
      ),
    ];

    const ids = service.building(elements, 1, 10, bounds);

    expect(ids).toEqual(expect.arrayContaining([10, 11, 12, 13]));
    expect(ids).not.toContain(22);
    expect(ids).not.toContain(30);
  });

  it('забирает проём на своей стене', () => {
    const elements = [
      ...square(1, 0, 0, 400),
      element(
        10,
        'opening',
        {
          type: 'wall',
          points: [
            [150, 400],
            [250, 400],
          ],
          width: 8,
        },
        { kind: 'door' },
      ),
    ];

    const ids = service.building(elements, 1, 1, bounds);

    expect(ids).toContain(10);
  });
});
