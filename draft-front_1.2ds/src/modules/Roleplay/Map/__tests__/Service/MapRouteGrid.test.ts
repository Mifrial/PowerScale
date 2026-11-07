import { describe, expect, it } from 'vitest';
import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';
import { MapRouteGrid } from '@/modules/Roleplay/Map/Service/MapRouteGrid';

const variant: MapVariant = {
  id: 1,
  label: 'Основная',
  fromMinutes: null,
  sortOrder: 0,
  baseFile: { publicId: 'test', width: 200, height: 100 },
  millimetersPerPixel: 1000,
  isDefault: true,
};

function detail(areas: MapArea[] = [], lines: MapLine[] = []): MapDetail {
  return {
    id: 1,
    ownerId: 1,
    name: 'Тест',
    description: '',
    visibility: 'private',
    fillOpacity: 55,
    isArchived: false,
    version: 1,
    updatedBy: 1,
    createdAt: 0,
    updatedAt: 0,
    canEdit: true,
    variants: [variant],
    nodes: [],
    areas,
    lines,
  };
}

const waterPath = [
  [80, 20],
  [120, 20],
  [120, 80],
  [80, 80],
] as MapArea['shapes'][number]['path'];

function waterArea(id: number, speed = 0): MapArea {
  return {
    id,
    layer: 'surface',
    color: '#88aacc',
    decor: {},
    speed,
    label: '',
    nodeId: null,
    sortOrder: 0,
    shapes: [{ variantId: 1, path: waterPath, handles: [], isPresent: true }],
  };
}

function roadLine(id: number, speed = 10000): MapLine {
  return {
    id,
    variantId: 1,
    color: '#cccccc',
    speed,
    points: [
      [0, 50],
      [200, 50],
    ],
    widths: [10, 10],
    handles: [],
    segments: [],
    colorBlend: 16,
    decor: {},
    label: '',
    isPresent: true,
    sortOrder: 1,
  };
}

const grid = new MapRouteGrid(new MapGeometryService());

describe('MapRouteGrid', () => {
  it('routes over open ground', () => {
    const field = grid.build(detail(), variant);
    const route = grid.findRoute(field, [10, 10], [190, 10], 1000);

    expect(route.distancePixels).toBeGreaterThan(170);
    expect(route.distanceMeters).toBeGreaterThan(0);
  });

  it('detours around water', () => {
    const field = grid.build(detail([waterArea(1)]), variant);
    const route = grid.findRoute(field, [10, 50], [190, 50], 1000);

    expect(route.distancePixels).toBeGreaterThan(190);
  });

  it('uses a road ribbon over water', () => {
    const field = grid.build(detail([waterArea(1)], [roadLine(1)]), variant);
    const route = grid.findRoute(field, [10, 50], [190, 50], 1000);

    expect(route.distancePixels).toBeLessThan(190);
  });

  it('opens a blocking surface with a bridge object', () => {
    const bridge: MapArea = {
      id: 2,
      layer: 'object',
      color: '#9a8b73',
      decor: {},
      speed: 10000,
      label: 'Мост',
      nodeId: null,
      sortOrder: 1,
      shapes: [{ variantId: 1, path: waterPath, handles: [], isPresent: true }],
    };
    const field = grid.build(detail([waterArea(1), bridge]), variant);
    const route = grid.findRoute(field, [10, 50], [190, 50], 1000);

    expect(route.distancePixels).toBeLessThan(190);
  });

  it('charges more for a slow segment than for a road', () => {
    const slowRoad = roadLine(1);
    slowRoad.segments = [{ speed: 1000, color: null }];
    const fast = grid.findRoute(grid.build(detail([], [roadLine(1)]), variant), [10, 50], [190, 50], 1000);
    const slow = grid.findRoute(grid.build(detail([], [slowRoad]), variant), [10, 50], [190, 50], 1000);

    expect(slow.costPixels).toBeGreaterThan(fast.costPixels);
  });

  it('has no route through a full-map blocker', () => {
    const wall: MapArea = {
      id: 1,
      layer: 'object',
      color: '#000000',
      decor: {},
      speed: 0,
      label: 'Стена',
      nodeId: null,
      sortOrder: 0,
      shapes: [
        {
          variantId: 1,
          path: [
            [0, 0],
            [200, 0],
            [200, 100],
            [0, 100],
          ],
          handles: [],
          isPresent: true,
        },
      ],
    };
    const field = grid.build(detail([wall]), variant);

    expect(() => grid.findRoute(field, [10, 50], [190, 50], 1000)).toThrow('Между точками нет проходимого маршрута');
  });
});
