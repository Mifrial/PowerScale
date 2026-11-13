import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SceneNavigationService } from '@/modules/Roleplay/Scene/Service/SceneNavigationService';

const board: SceneBoard = {
  id: 1,
  name: 'Поле',
  sortOrder: 0,
  baseFile: null,
  boundWidth: 1000,
  boundHeight: 800,
  occupancyCell: 16,
  zQuantum: 16,
  gridStep: 32,
};

const element = (
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
  sortOrder = id,
): SceneElement => ({
  id,
  boardId: 1,
  kind,
  shape,
  props,
  sortOrder,
  targetBoardId: null,
  asset: null,
});

const grass = (): SceneElement =>
  element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 1000, h: 800 }, { kind: 'grass', speed: 100 }, 0);

const rect = (
  id: number,
  kind: SceneElementKind,
  x: number,
  y: number,
  w: number,
  h: number,
  props: SceneElementProps,
  sortOrder = id,
): SceneElement => element(id, kind, { type: 'rect', x, y, w, h }, props, sortOrder);

const near = (point: ScenePoint, x: number, y: number, radius: number): boolean =>
  Math.hypot(point[0] - x, point[1] - y) <= radius;

const crossesRect = (
  points: ScenePoint[],
  x: number,
  y: number,
  width: number,
  height: number,
): boolean => {
  for (let index = 1; index < points.length; index += 1) {
    const from = points[index - 1];
    const to = points[index];
    const length = Math.hypot(to[0] - from[0], to[1] - from[1]);
    const steps = Math.max(1, Math.ceil(length / 5));
    for (let sample = 0; sample <= steps; sample += 1) {
      const t = sample / steps;
      const px = from[0] + (to[0] - from[0]) * t;
      const py = from[1] + (to[1] - from[1]) * t;
      if (px >= x && px <= x + width && py >= y && py <= y + height) {
        return true;
      }
    }
  }

  return false;
};

describe('SceneNavigationService', () => {
  const service = new SceneNavigationService();
  const options = { agentRadius: 10 };

  it('строит маршрут по открытому лугу', () => {
    const route = service.route(board, [grass()], 64, [100, 100], [900, 700], options);

    expect(route).not.toBeNull();
    expect(route?.points[0]).toEqual([100, 100]);
    expect(route?.points[route.points.length - 1]).toEqual([900, 700]);
    expect(route?.distance).toBeGreaterThanOrEqual(1000);
    expect(route?.cost).toBeGreaterThan(0);
  });

  it('ведёт маршрут через проём в стене', () => {
    const elements = [
      grass(),
      rect(2, 'obstacle', 500, 0, 20, 300, { blocksMovement: true, blocksVision: true }),
      rect(3, 'obstacle', 500, 500, 20, 300, { blocksMovement: true, blocksVision: true }),
    ];
    const route = service.route(board, elements, 64, [200, 400], [800, 400], options);

    expect(route).not.toBeNull();
    expect(route).not.toBeNull();
    expect(crossesRect(route?.points ?? [], 500, 300, 20, 200)).toBe(true);
  });

  it('предпочитает мост реке и считает его дешевле', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    const bridge = rect(3, 'support', 450, 300, 100, 200, { kind: 'bridge', speed: 150 }, 20);
    const withoutBridge = service.route(board, [grass(), water], 64, [200, 400], [800, 400], options);
    const withBridge = service.route(board, [grass(), water, bridge], 64, [200, 400], [800, 400], options);

    expect(withoutBridge).not.toBeNull();
    expect(withBridge).not.toBeNull();
    expect(withBridge).not.toBeNull();
    expect(crossesRect(withBridge?.points ?? [], 450, 300, 100, 200)).toBe(true);
    expect(withBridge?.cost).toBeLessThan(withoutBridge?.cost ?? Number.POSITIVE_INFINITY);
  });

  it('не срезает через воду, если мост дешевле', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    const bridge = rect(3, 'support', 450, 200, 100, 120, { kind: 'bridge', speed: 150 }, 20);
    const options = { agentRadius: 10 };
    const withBridge = service.route(board, [grass(), water, bridge], 64, [200, 450], [800, 450], options);
    const withoutBridge = service.route(board, [grass(), water], 64, [200, 450], [800, 450], options);

    expect(withBridge).not.toBeNull();
    expect(withoutBridge).not.toBeNull();
    expect(withBridge?.cost).toBeLessThan(withoutBridge?.cost ?? Number.POSITIVE_INFINITY);
    expect(crossesRect(withBridge?.points ?? [], 450, 200, 100, 120)).toBe(true);

    const inWaterOutsideBridge = (withBridge?.points ?? []).some((point, index, points) => {
      if (index === 0) return false;
      const from = points[index - 1];
      const length = Math.hypot(point[0] - from[0], point[1] - from[1]);
      const steps = Math.max(1, Math.ceil(length / 5));
      for (let sample = 0; sample <= steps; sample += 1) {
        const t = sample / steps;
        const x = from[0] + (point[0] - from[0]) * t;
        const y = from[1] + (point[1] - from[1]) * t;
        if (x >= 450 && x <= 550 && (y < 200 || y > 320)) return true;
      }

      return false;
    });
    expect(inWaterOutsideBridge).toBe(false);
  });

  it('не пропускает агента в щель уже его диаметра', () => {
    const elements = [
      grass(),
      rect(2, 'obstacle', 500, 0, 20, 390, { blocksMovement: true, blocksVision: true }),
      rect(3, 'obstacle', 500, 410, 20, 390, { blocksMovement: true, blocksVision: true }),
    ];
    const route = service.route(board, elements, 64, [200, 400], [800, 400], options);

    expect(route).toBeNull();
  });

  it('обходит основание декора', () => {
    const tree = element(
      2,
      'decor',
      { type: 'point', x: 500, y: 400 },
      { scale: 420, label: 'Валун' },
    );
    const route = service.route(board, [grass(), tree], 64, [400, 400], [600, 400], options);

    expect(route).not.toBeNull();
    expect(route?.points.some((point) => near(point, 500, 400, 40))).toBe(false);
    expect(route?.distance).toBeGreaterThan(200);
  });
});
