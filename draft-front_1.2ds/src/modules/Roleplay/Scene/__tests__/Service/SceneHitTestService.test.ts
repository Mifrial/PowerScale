import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import { SceneGeometryService } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import { SceneHitTestService } from '@/modules/Roleplay/Scene/Service/SceneHitTestService';
import { SceneViewportService } from '@/modules/Roleplay/Scene/Service/SceneViewportService';

const viewportService = new SceneViewportService();
const service = new SceneHitTestService(viewportService, new SceneGeometryService());
const viewport: SceneViewport = { x: 50, y: 50, zoom: 1, width: 1000, height: 1000 };

function element(overrides: Partial<SceneElement>): SceneElement {
  return {
    id: 1,
    boardId: 10,
    kind: 'obstacle',
    shape: { type: 'rect', x: 0, y: 0, w: 100, h: 100 },
    props: {},
    sortOrder: 0,
    targetBoardId: null,
    asset: null,
    ...overrides,
  };
}

function model(elements: SceneElement[], activeBoardId = 10): SceneRenderModel {
  return {
    activeBoardId,
    resolution: 64,
    boards: [],
    elements,
    enclosures: [],
    selection: [],
    mode: 'edit',
  };
}

describe('SceneHitTestService', () => {
  it('finds the topmost element by sort order and id', () => {
    const elements = [
      element({ id: 1, sortOrder: 0 }),
      element({ id: 2, sortOrder: 5, shape: { type: 'rect', x: 0, y: 0, w: 100, h: 100 } }),
    ];

    const hit = service.hit(model(elements), viewport, { x: 500, y: 500 });

    expect(hit?.id).toBe(2);
  });

  it('ignores elements of other boards', () => {
    const elements = [element({ id: 1, boardId: 20 })];

    expect(service.hit(model(elements), viewport, { x: 500, y: 500 })).toBeNull();
  });

  it('converts pixel tolerance into scene units', () => {
    const zoomed: SceneViewport = { x: 100, y: 50, zoom: 10, width: 1000, height: 1000 };
    const elements = [element({ shape: { type: 'rect', x: 0, y: 0, w: 100, h: 100 } })];
    // Экранная точка соответствует сцене (100.5, 50) — 0.5 единицы за правым краем.
    const nearEdge = { x: 505, y: 500 };

    expect(service.hit(model(elements), zoomed, nearEdge, 0)).toBeNull();
    expect(service.hit(model(elements), zoomed, nearEdge, 8)?.id).toBe(1);
  });

  it('returns all hits from top to bottom', () => {
    const elements = [
      element({ id: 1, sortOrder: 0 }),
      element({ id: 3, sortOrder: 2 }),
      element({ id: 2, sortOrder: 1 }),
    ];

    expect(service.hits(model(elements), viewport, { x: 500, y: 500 }).map((item) => item.id)).toEqual([3, 2, 1]);
  });

  it('крыша берётся за свес, а середина накрытия принадлежит полу', () => {
    const grass = element({
      id: 3,
      kind: 'terrain',
      sortOrder: -1,
      shape: { type: 'rect', x: -200, y: -200, w: 2000, h: 2000 },
      props: { kind: 'grass' },
    });
    const floor = element({
      id: 1,
      kind: 'terrain',
      sortOrder: 0,
      shape: { type: 'rect', x: 0, y: 0, w: 100, h: 100 },
      props: { kind: 'dirt' },
    });
    const roof = element({
      id: 2,
      kind: 'support',
      sortOrder: 9,
      shape: { type: 'rect', x: -20, y: -20, w: 140, h: 140 },
      props: { kind: 'roof', elevation: 128 },
    });
    const elements = [grass, floor, roof];

    // Центр накрытия: пол, крыша клик не перехватывает.
    expect(service.hits(model(elements), viewport, { x: 500, y: 500 }).map((item) => item.id)).toEqual([1, 3]);
    // Свес за стеной: экранная точка (440,440) — это сцена (-10,-10), уже вне
    // пола. Крыша достаётся кликом, хотя под свесом луг.
    expect(service.hits(model(elements), viewport, { x: 440, y: 440 }).map((item) => item.id)).toEqual([2, 3]);
  });

  it('selects decor by its footprint and silhouette, not only by the anchor', () => {
    const tree = element({
      id: 7,
      kind: 'decor',
      shape: { type: 'point', x: 500, y: 500 },
      props: { scale: 320, baseRadius: 10, sightRadius: 45, sightBottom: 85 },
    });

    // Крона: точка далеко от якоря, но внутри силуэта (сцена 490, 430).
    expect(service.hit(model([tree]), viewport, { x: 940, y: 880 })?.id).toBe(7);
    // Вне кроны и ствола.
    expect(service.hit(model([tree]), viewport, { x: 1150, y: 950 })).toBeNull();
  });

  it('selects decor by the visible sprite box when the aspect is known', () => {
    const tree = element({
      id: 9,
      kind: 'decor',
      shape: { type: 'point', x: 500, y: 500 },
      props: { scale: 320, baseRadius: 10, sightRadius: 45, sightBottom: 85 },
    });
    const aspectOf = (): number => 340 / 256;

    // Сцена (500, 300): высоко над якорем, вне силуэта, но в спрайте.
    expect(service.hit(model([tree]), viewport, { x: 950, y: 750 })?.id).toBeUndefined();
    expect(service.hit(model([tree]), viewport, { x: 950, y: 750 }, 8, aspectOf)?.id).toBe(9);
  });

  it('selects decor by an explicit footprint with an offset', () => {
    const boulder = element({
      id: 8,
      kind: 'decor',
      shape: { type: 'point', x: 500, y: 500 },
      props: { footprint: { shape: 'circle', radius: 20, offset: [40, 0] } },
    });

    // Смещённый круг: точка справа от якоря (сцена 540, 500).
    expect(service.hit(model([boulder]), viewport, { x: 990, y: 950 })?.id).toBe(8);
  });
});
