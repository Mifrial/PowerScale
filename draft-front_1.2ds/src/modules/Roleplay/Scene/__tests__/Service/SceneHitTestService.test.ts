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
});
