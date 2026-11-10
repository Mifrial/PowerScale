import { describe, expect, it } from 'vitest';
import { SceneViewportService } from '@/modules/Roleplay/Scene/Service/SceneViewportService';

const service = new SceneViewportService(0.02, 8);

describe('SceneViewportService', () => {
  it('converts scene coordinates to screen and back', () => {
    const viewport = { x: 100, y: 50, zoom: 2, width: 800, height: 600 };

    const screen = service.sceneToScreen(viewport, [150, 100]);

    expect(screen).toEqual({ x: 500, y: 400 });
    expect(service.screenToScene(viewport, screen)).toEqual([150, 100]);
  });

  it('keeps the scene point under the cursor during zoom', () => {
    const viewport = { x: 0, y: 0, zoom: 1, width: 800, height: 600 };
    const cursor = { x: 300, y: 200 };
    const before = service.screenToScene(viewport, cursor);
    const zoomed = service.zoomAt(viewport, cursor, 2);
    const after = service.screenToScene(zoomed, cursor);

    expect(after[0]).toBeCloseTo(before[0], 6);
    expect(after[1]).toBeCloseTo(before[1], 6);
    expect(zoomed.zoom).toBe(2);
  });

  it('clamps zoom to configured limits', () => {
    const viewport = { x: 0, y: 0, zoom: 7, width: 800, height: 600 };

    expect(service.zoomAt(viewport, { x: 0, y: 0 }, 10).zoom).toBe(8);
    expect(service.zoomAt(viewport, { x: 0, y: 0 }, 0.0001).zoom).toBe(0.02);
  });

  it('fits a board into the view with padding and centers it', () => {
    const viewport = service.fit({ x: 0, y: 0, width: 1000, height: 800 }, { width: 2000, height: 500 }, 20);

    expect(viewport.zoom).toBeCloseTo(0.575, 6);
    expect(viewport.x).toBeCloseTo(500, 6);
    expect(viewport.y).toBeCloseTo(400, 6);
    expect(viewport.width).toBe(2000);
    expect(viewport.height).toBe(500);
  });

  it('pans by screen pixels in scene units', () => {
    const viewport = { x: 0, y: 0, zoom: 2, width: 800, height: 600 };

    expect(service.pan(viewport, 100, -50)).toEqual({ x: -50, y: 25, zoom: 2, width: 800, height: 600 });
  });

  it('does not let the board leave the viewport entirely', () => {
    const viewport = service.constrain(
      { x: 100000, y: 100000, zoom: 1, width: 800, height: 600 },
      { x: 0, y: 0, width: 1000, height: 800 },
      64,
    );

    expect(viewport.x).toBeLessThanOrEqual(1000);
    expect(viewport.y).toBeLessThanOrEqual(800);
  });
});
