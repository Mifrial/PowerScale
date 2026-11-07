import { describe, expect, it } from 'vitest';
import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import { MapRenderService } from '@/modules/Roleplay/Map/Service/MapRenderService';

const service = new MapRenderService();

function line(overrides: Partial<MapLine> = {}): MapLine {
  return {
    id: 1,
    variantId: 1,
    color: '#3f6f8f',
    speed: 400,
    points: [
      [0, 0],
      [100, 0],
    ],
    widths: [20, 20],
    handles: [],
    segments: [],
    colorBlend: 16,
    decor: {},
    label: '',
    isPresent: true,
    sortOrder: 0,
    ...overrides,
  };
}

function area(overrides: Partial<MapArea> = {}): MapArea {
  return {
    id: 1,
    layer: 'surface',
    color: '#4e7a4e',
    decor: {},
    speed: 2000,
    label: 'Лес',
    nodeId: null,
    sortOrder: 0,
    shapes: [],
    ...overrides,
  };
}

describe('MapRenderService', () => {
  it('inherits the map fill opacity when the object has none', () => {
    expect(service.lineFillOpacity(line(), 30)).toBe(30);
    expect(service.areaFillOpacity(area(), 30)).toBe(30);
  });

  it('lets a line or area override the map opacity, including zero', () => {
    expect(service.lineFillOpacity(line({ decor: { fillOpacity: 80 } }), 30)).toBe(80);
    expect(service.lineFillOpacity(line({ decor: { fillOpacity: 0 } }), 30)).toBe(0);
    expect(service.areaFillOpacity(area({ decor: { fillOpacity: 15 } }), 30)).toBe(15);
  });

  it('clamps opacity and depth into the contract range', () => {
    expect(service.fillOpacity(140, 55)).toBe(100);
    expect(service.fillOpacity(-5, 55)).toBe(0);
    expect(service.fillOpacity(undefined, 55)).toBe(55);
    expect(service.lineDepth(line({ decor: { depth: 45 } }))).toBe(45);
    expect(service.lineDepth(line({ decor: { depth: 120 } }))).toBe(100);
    expect(service.lineDepth(line())).toBe(0);
  });

  it('defaults the depth profile width to 85% and clamps overrides', () => {
    expect(service.lineDepthWidth(line())).toBe(85);
    expect(service.lineDepthWidth(line({ decor: { depthWidth: 60 } }))).toBe(60);
    expect(service.lineDepthWidth(line({ decor: { depthWidth: 10 } }))).toBe(20);
    expect(service.lineDepthWidth(line({ decor: { depthWidth: 150 } }))).toBe(100);
  });
});
