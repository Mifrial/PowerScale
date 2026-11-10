import { describe, expect, it } from 'vitest';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SceneCurveService } from '@/modules/Roleplay/Scene/Service/SceneCurveService';
import { SceneRibbonService } from '@/modules/Roleplay/Scene/Service/SceneRibbonService';
import { SceneVertexService } from '@/modules/Roleplay/Scene/Service/SceneVertexService';

const curves = new SceneCurveService();
const service = new SceneVertexService(curves, new SceneRibbonService(curves));
const polygon: SceneShape = {
  type: 'polygon',
  points: [
    [0, 0],
    [200, 0],
    [200, 200],
    [0, 200],
  ],
};
const path: SceneShape = {
  type: 'path',
  points: [
    [0, 100],
    [200, 100],
    [400, 100],
  ],
  widths: [20, 40, 20],
  handles: curves.normalizeHandles([]),
};

describe('SceneVertexService', () => {
  it('finds a vertex under the point', () => {
    expect(service.vertexAt(polygon, [204, 3], 6)).toBe(1);
    expect(service.vertexAt(polygon, [100, 100], 6)).toBeNull();
  });

  it('finds the insertion point on a polygon edge', () => {
    const insertion = service.insertion(polygon, [100, 3], 8);

    expect(insertion).not.toBeNull();
    expect(insertion?.index).toBe(0);
    expect(insertion?.t).toBeGreaterThan(0.4);
    expect(insertion?.t).toBeLessThan(0.6);
  });

  it('interpolates the ribbon width at the insertion point', () => {
    const insertion = service.insertion(path, [100, 100], 8);

    expect(insertion?.index).toBe(0);
    expect(insertion?.width).toBeCloseTo(30, 0);
  });

  it('splits a segment and keeps the curve shape', () => {
    const insertion = service.insertion(path, [100, 100], 8);
    expect(insertion).not.toBeNull();

    const split = service.split(path, insertion!);

    expect(split?.index).toBe(0);
    expect(split?.point[0]).toBeGreaterThan(80);
    expect(split?.point[0]).toBeLessThan(120);
    expect(split?.handle.in[0]).toBeLessThan(0);
  });

  it('finds a bezier handle of the focused vertex', () => {
    const curved: SceneShape = {
      type: 'path',
      points: [
        [0, 0],
        [200, 0],
      ],
      widths: [10, 10],
      handles: [
        { in: [0, 0], out: [60, 80] },
        { in: [-60, 80], out: [0, 0] },
      ],
    };

    const handle = service.handleAt(curved, [60, 80], 5, 16);

    expect(handle).toEqual({ index: 0, side: 'out' });
  });
});
