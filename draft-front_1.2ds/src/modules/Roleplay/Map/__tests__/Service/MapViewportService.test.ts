import { describe, expect, it } from 'vitest';
import { MapViewportService } from '@/modules/Roleplay/Map/Service/MapViewportService';

const service = new MapViewportService(0.1, 8);

describe('MapViewportService', () => {
  it('converts world to screen and back', () => {
    const viewport = { scale: 2, offsetX: 40, offsetY: -10 };
    const screen = service.worldToScreen(viewport, [100, 50]);

    expect(screen).toEqual({ x: 240, y: 90 });
    expect(service.screenToWorld(viewport, screen)).toEqual([100, 50]);
  });

  it('keeps the world point under the cursor during zoom', () => {
    const viewport = { scale: 1, offsetX: 0, offsetY: 0 };
    const cursor = { x: 300, y: 200 };
    const worldBefore = service.screenToWorld(viewport, cursor);
    const zoomed = service.zoomAt(viewport, cursor, 2);
    const worldAfter = service.screenToWorld(zoomed, cursor);

    expect(worldAfter[0]).toBeCloseTo(worldBefore[0], 6);
    expect(worldAfter[1]).toBeCloseTo(worldBefore[1], 6);
    expect(zoomed.scale).toBe(2);
  });

  it('clamps zoom to configured limits', () => {
    const viewport = { scale: 7, offsetX: 0, offsetY: 0 };

    expect(service.zoomAt(viewport, { x: 0, y: 0 }, 10).scale).toBe(8);
    expect(service.zoomAt(viewport, { x: 0, y: 0 }, 0.0001).scale).toBe(0.1);
  });

  it('fits the world into the view and centers it', () => {
    const viewport = service.fit({ width: 1000, height: 800 }, { width: 2000, height: 500 }, 20);

    expect(viewport.scale).toBeCloseTo(0.48, 6);
    expect(viewport.offsetX).toBeCloseTo(20, 6);
    expect(viewport.offsetY).toBeCloseTo(280, 6);
  });

  it('centers content smaller than the viewport when constraining', () => {
    const viewport = service.constrain(
      { scale: 0.2, offsetX: -500, offsetY: -500 },
      { width: 1000, height: 800 },
      { width: 200, height: 100 },
    );

    expect(viewport.offsetX).toBeCloseTo(480, 6);
    expect(viewport.offsetY).toBeCloseTo(390, 6);
  });

  it('does not let a large world leave the viewport', () => {
    const viewport = service.constrain(
      { scale: 2, offsetX: -5000, offsetY: -5000 },
      { width: 500, height: 400 },
      { width: 1000, height: 800 },
    );

    expect(viewport.offsetX).toBeGreaterThanOrEqual(500 - 2000 - 48);
    expect(viewport.offsetY).toBeGreaterThanOrEqual(400 - 1600 - 48);
  });
});
