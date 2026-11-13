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

  it('joins a sharp corner with one miter vertex, without a zero-length fan', () => {
    const geometry = service.geometry(
      [
        [0, 0],
        [100, 0],
        [100, 100],
      ],
      curves.normalizeHandles([]),
      [40, 40, 40],
    );

    // Точка излома не дублируется: иначе лента получает веер нулевой длины.
    expect(geometry.center).toHaveLength(3);
    expect(geometry.left).toHaveLength(3);
    // Митра уводит внутренний край внутрь угла, внешний — наружу.
    expect(geometry.left[1][0]).toBeCloseTo(80, 6);
    expect(geometry.left[1][1]).toBeCloseTo(20, 6);
    expect(geometry.right[1][0]).toBeCloseTo(120, 6);
    expect(geometry.right[1][1]).toBeCloseTo(-20, 6);
  });

  it('limits the miter on a hairpin turn instead of producing a needle', () => {
    const geometry = service.geometry(
      [
        [0, 0],
        [100, 0],
        [0, 0],
      ],
      curves.normalizeHandles([]),
      [40, 40, 40],
    );

    expect(geometry.center).toHaveLength(3);
    for (const point of [...geometry.left, ...geometry.right]) {
      expect(Number.isFinite(point[0])).toBe(true);
      expect(Number.isFinite(point[1])).toBe(true);
      expect(Math.hypot(point[0], point[1])).toBeLessThan(1000);
    }
  });

  it('extends open ends along the axis, not across', () => {
    const geometry = service.geometry(
      [
        [0, 100],
        [300, 100],
      ],
      curves.normalizeHandles([]),
      [40, 40],
      1,
      0,
      80,
      120,
    );

    // Начало уходит назад, конец — вперёд: торец уезжает за край поля.
    expect(geometry.center[0][0]).toBeCloseTo(-80, 6);
    expect(geometry.center[geometry.center.length - 1][0]).toBeCloseTo(420, 6);
    expect(geometry.lengths[0]).toBeCloseTo(-80, 6);
    expect(geometry.lengths[geometry.lengths.length - 1]).toBeCloseTo(420, 6);
    // Ширина концов та же: продление не раздувает русло.
    expect(geometry.left[0][1]).toBeCloseTo(120, 6);
    expect(geometry.right[0][1]).toBeCloseTo(80, 6);
  });
});
