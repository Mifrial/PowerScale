import { describe, expect, it } from 'vitest';
import { SceneToolShape } from '@/modules/Roleplay/Scene/Service/SceneToolShape';

const service = new SceneToolShape();
const bounds = { x: 0, y: 0, width: 1000, height: 800 };

describe('SceneToolShape', () => {
  it('builds a normalized rect from a drag in any direction', () => {
    expect(service.build('rect', [300, 200], [100, 50], bounds)).toEqual({
      type: 'rect',
      x: 100,
      y: 50,
      w: 200,
      h: 150,
    });
  });

  it('rejects a rect with zero area', () => {
    expect(service.build('rect', [300, 200], [300, 200], bounds)).toBeNull();
  });

  it('builds a circle from center to radius', () => {
    expect(service.build('circle', [400, 400], [500, 400], bounds)).toEqual({
      type: 'circle',
      cx: 400,
      cy: 400,
      r: 100,
    });
  });

  it('caps a circle that does not fit the board', () => {
    expect(service.build('circle', [500, 400], [2000, 400], bounds)).toEqual({
      type: 'circle',
      cx: 500,
      cy: 400,
      r: 400,
    });
  });

  it('builds a wall with default thickness', () => {
    expect(service.build('wall', [100, 100], [200, 150], bounds)).toEqual({
      type: 'wall',
      points: [
        [100, 100],
        [200, 150],
      ],
      width: 16,
    });
  });

  it('builds a point at the press position, clamped to the board', () => {
    expect(service.build('point', [-10, 900], [500, 500], bounds)).toEqual({ type: 'point', x: 0, y: 800 });
  });

  it('does not build a polygon from a drag', () => {
    expect(service.build('polygon', [100, 100], [500, 500], bounds)).toBeNull();
  });

  it('builds a closed ring wall from center to radius', () => {
    const shape = service.build('wall', [400, 400], [500, 400], bounds, 16, 'ring');

    expect(shape?.type).toBe('wall');
    if (shape?.type !== 'wall') {
      return;
    }

    expect(shape.points.length).toBeGreaterThan(12);
    expect(shape.points[0]).toEqual(shape.points[shape.points.length - 1]);
    expect(shape.width).toBe(16);
    for (const point of shape.points) {
      expect(Math.hypot(point[0] - 400, point[1] - 400)).toBeLessThanOrEqual(101);
    }
  });
});
