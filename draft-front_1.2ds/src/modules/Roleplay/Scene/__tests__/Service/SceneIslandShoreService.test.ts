import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { sceneIslandShores } from '@/modules/Roleplay/Scene/Service/SceneIslandShoreService';

const element = (id: number, shape: SceneShape, props: SceneElement['props']): SceneElement => ({
  id,
  boardId: 1,
  kind: 'terrain',
  shape,
  props,
  sortOrder: id,
  targetBoardId: null,
  asset: null,
});

const polygon = (points: [number, number][]): SceneShape => ({ type: 'polygon', points, handles: [] });

const lake = (props: SceneElement['props'] = {}): SceneElement =>
  element(
    2,
    polygon([
      [100, 100],
      [900, 100],
      [900, 900],
      [100, 900],
    ]),
    { kind: 'water', ...props },
  );

const island = (): SceneElement =>
  element(
    3,
    polygon([
      [300, 300],
      [700, 300],
      [700, 700],
      [300, 700],
    ]),
    { kind: 'grass', overWater: true },
  );

describe('SceneIslandShoreService', () => {
  it('находит остров в воде и его воду', () => {
    const water = lake({ shoreKind: 'sand', shoreWidth: 70 });
    const shores = sceneIslandShores([water, island()], 64);

    expect(shores).toHaveLength(1);
    expect(shores[0].island.id).toBe(3);
    expect(shores[0].water.id).toBe(2);
    expect(shores[0].contour.length).toBeGreaterThanOrEqual(4);
  });

  it('отдаёт остров даже воде без кромки: дырка нужна и для мели', () => {
    const shores = sceneIslandShores([lake(), island()], 64);

    expect(shores).toHaveLength(1);
  });

  it('не считает островом пол дома внутри острова', () => {
    const floor = element(4, { type: 'rect', x: 400, y: 400, w: 100, h: 100 }, { kind: 'dirt', overWater: true });

    const shores = sceneIslandShores([lake(), island(), floor], 64);

    expect(shores.map((shore) => shore.island.id)).toEqual([3]);
  });

  it('не считает островом полуостров, упирающийся в сушу', () => {
    const peninsula = element(
      4,
      polygon([
        [400, 800],
        [600, 800],
        [600, 980],
        [400, 980],
      ]),
      { kind: 'grass', overWater: true },
    );

    const shores = sceneIslandShores([lake(), peninsula], 64);

    expect(shores).toHaveLength(0);
  });

  it('берёт верхнюю воду, если водоёмы перекрываются', () => {
    const lower = lake();
    const upper = element(
      5,
      polygon([
        [200, 200],
        [800, 200],
        [800, 800],
        [200, 800],
      ]),
      { kind: 'water' },
    );
    const shores = sceneIslandShores([lower, { ...upper, sortOrder: 10 }, island()], 64);

    // Верхняя вода нарисована позже и «владеет» берегом.
    expect(shores[0].water.id).toBe(5);
  });
});
