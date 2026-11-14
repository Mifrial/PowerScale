import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import {
  sceneDeckEndEdge,
  sceneDeckFrame,
  sceneDeckLocalPoint,
  sceneDeckRailAt,
  sceneDeckRailPolygons,
  sceneDeckRailSlope,
  sceneDeckRailWidth,
  sceneRampBaseBlocker,
} from '@/modules/Roleplay/Scene/Service/SceneDeck';

const RESOLUTION = 64;

const element = (
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
): SceneElement => ({
  id,
  boardId: 1,
  kind,
  shape,
  props,
  sortOrder: id,
  targetBoardId: null,
  asset: null,
});

const deckShape: SceneShape = { type: 'rect', x: 100, y: 100, w: 200, h: 100 };

const frameOf = (props: SceneElementProps = { kind: 'bridge' }) => {
  const frame = sceneDeckFrame(element(1, 'support', deckShape, props));
  if (frame === null) {
    throw new Error('ожидался прямоугольный настил');
  }

  return frame;
};

const contains = (ring: readonly ScenePoint[], point: ScenePoint): boolean => {
  let inside = false;
  for (let index = 0, previous = ring.length - 1; index < ring.length; previous = index, index += 1) {
    const a = ring[index];
    const b = ring[previous];
    if (a[1] > point[1] !== b[1] > point[1]) {
      const x = ((b[0] - a[0]) * (point[1] - a[1])) / (b[1] - a[1]) + a[0];
      if (point[0] < x) {
        inside = !inside;
      }
    }
  }

  return inside;
};

describe('SceneDeck', () => {
  it('строит рамку только для прямоугольника', () => {
    expect(sceneDeckFrame(element(1, 'support', deckShape, { kind: 'bridge' }))).not.toBeNull();
    expect(sceneDeckFrame(element(2, 'support', { type: 'point', x: 10, y: 10 }, { kind: 'bridge' }))).toBeNull();
  });

  it('считает ширину перил от короткой стороны', () => {
    const frame = frameOf();

    expect(sceneDeckRailWidth(frame)).toBeCloseTo(100 * SCENE_BRIDGE.railWidth);
  });

  it('переводит точку в локальную рамку с поворотом', () => {
    const frame = frameOf({ kind: 'bridge', rotation: 90 });
    // Центр (200, 150) остаётся на месте; точка справа от центра уходит вверх
    // в локальной рамке.
    expect(sceneDeckLocalPoint(frame, [200, 150])).toEqual([200, 150]);
    const local = sceneDeckLocalPoint(frame, [250, 150]);
    expect(local[0]).toBeCloseTo(200);
    expect(local[1]).toBeCloseTo(100);
  });

  it('находит полосы перил по длинным сторонам', () => {
    const frame = frameOf();

    expect(sceneDeckRailAt(frame, [200, 105], RESOLUTION)).toBe(true);
    expect(sceneDeckRailAt(frame, [200, 195], RESOLUTION)).toBe(true);
    expect(sceneDeckRailAt(frame, [200, 150], RESOLUTION)).toBe(false);
  });

  it('отличает торец от длинной стороны по направлению ребра', () => {
    const frame = frameOf();

    expect(sceneDeckEndEdge(frame, [100, 100], [100, 200])).toBe(true);
    expect(sceneDeckEndEdge(frame, [100, 100], [300, 100])).toBe(false);
  });

  it('поворачивает полосы перил вокруг центра настила', () => {
    const frame = frameOf({ kind: 'bridge', rotation: 90 });
    const rings = sceneDeckRailPolygons(frame, RESOLUTION);

    expect(rings).toHaveLength(2);
    // После поворота верхняя полоса уходит вправо от центра.
    expect(contains(rings[0], [245, 150])).toBe(true);
    expect(contains(rings[0], [155, 150])).toBe(false);
  });

  it('поднимает наклон перил на их высоту', () => {
    const slope = sceneDeckRailSlope({ from: [0, 0], to: [100, 0], low: 0, high: 100 }, RESOLUTION);

    expect(slope.low).toBeCloseTo(RESOLUTION * SCENE_BRIDGE.railHeightIpari);
    expect(slope.high).toBeCloseTo(100 + RESOLUTION * SCENE_BRIDGE.railHeightIpari);
  });

  it('закрывает базу под низкой частью съезда и оставляет высокий конец', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const ramp = element(
      2,
      'support',
      { type: 'rect', x: 100, y: 100, w: 300, h: 120 },
      {
        kind: 'ramp',
        elevation: 100,
        height: 16,
      },
    );
    const blocker = sceneRampBaseBlocker(ramp, [grass, ramp], RESOLUTION);
    if (blocker === null) {
      throw new Error('ожидался блокер низкой части съезда');
    }

    expect(contains(blocker, [110, 160])).toBe(true);
    expect(contains(blocker, [390, 160])).toBe(false);
  });
});
