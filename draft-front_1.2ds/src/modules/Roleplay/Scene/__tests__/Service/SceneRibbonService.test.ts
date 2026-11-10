import { describe, expect, it } from 'vitest';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import { SceneCurveService } from '@/modules/Roleplay/Scene/Service/SceneCurveService';
import { SceneRibbonService } from '@/modules/Roleplay/Scene/Service/SceneRibbonService';

const curves = new SceneCurveService();
const service = new SceneRibbonService(curves);

describe('SceneRibbonService', () => {
  it('builds both edges around a straight ribbon', () => {
    const geometry = service.geometry(
      [
        [0, 100],
        [300, 100],
      ],
      curves.normalizeHandles([]),
      [40, 40],
    );

    expect(geometry.left[0][1]).toBeCloseTo(120, 6);
    expect(geometry.right[0][1]).toBeCloseTo(80, 6);
    expect(geometry.left[geometry.left.length - 1][0]).toBeCloseTo(300, 6);
  });

  it('closes the outline with both edges', () => {
    const outline = service.outline(
      [
        [0, 100],
        [300, 100],
      ],
      curves.normalizeHandles([]),
      [40, 40],
    );

    expect(outline.length).toBeGreaterThanOrEqual(4);
  });

  it('measures distance to the ribbon edge', () => {
    const handles = curves.normalizeHandles([]);
    const points: ScenePoint[] = [
      [0, 100],
      [300, 100],
    ];
    const widths = [40, 40];

    expect(service.distance([150, 100], points, handles, widths)).toBe(0);
    expect(service.distance([150, 120], points, handles, widths)).toBe(0);
    expect(service.distance([150, 140], points, handles, widths)).toBe(20);
  });

  it('places the width handle outside the edge by the offset', () => {
    const point = service.widthHandlePoint(
      [
        [0, 100],
        [300, 100],
      ],
      curves.normalizeHandles([]),
      [40, 40],
      0,
      10,
    );

    expect(point).toEqual([0, 130]);
  });

  it('widens the ribbon by the shore padding', () => {
    const geometry = service.geometry(
      [
        [0, 100],
        [300, 100],
      ],
      curves.normalizeHandles([]),
      [40, 40],
      1,
      16,
    );

    expect(geometry.left[0][1]).toBeCloseTo(136, 6);
    expect(geometry.right[0][1]).toBeCloseTo(64, 6);
    expect(geometry.lengths[geometry.lengths.length - 1]).toBeCloseTo(300, 6);
  });
});
