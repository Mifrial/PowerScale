import { beforeEach, describe, expect, it } from 'vitest';
import type { IMapApi } from '@/modules/Roleplay/Map/Interface/IMapApi';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapSaveResult } from '@/modules/Roleplay/Map/Dto/MapSaveResult';
import type { SaveMapData } from '@/modules/Roleplay/Map/Dto/SaveMapData';
import { useMapDraft } from '@/modules/Roleplay/Map/Composables/useMapDraft';
import { registerMapApi } from '@/modules/Roleplay/Map/init';
import { MapApiError } from '@/modules/Roleplay/Map/Service/MapApiError';

function detail(overrides: Partial<MapDetail> = {}): MapDetail {
  return {
    id: 1,
    ownerId: 1,
    name: 'Аустан',
    description: '',
    visibility: 'private',
    fillOpacity: 55,
    isArchived: false,
    version: 1,
    updatedBy: 1,
    createdAt: 0,
    updatedAt: 0,
    canEdit: true,
    variants: [
      {
        id: 1,
        label: 'Основная',
        fromMinutes: null,
        sortOrder: 0,
        baseFile: null,
        millimetersPerPixel: 1000,
        isDefault: true,
      },
    ],
    nodes: [],
    areas: [],
    lines: [],
    ...overrides,
  };
}

let savedPayload: SaveMapData | null = null;
let saveResponse: MapSaveResult = { ...detail({ version: 2 }), warnings: [] };
let saveError: Error | null = null;

const api: IMapApi = {
  findPage: () => Promise.reject(new Error('unused')),
  get: () => Promise.reject(new Error('unused')),
  save: async (data) => {
    savedPayload = data;
    if (saveError) throw saveError;

    return saveResponse;
  },
  archive: () => Promise.resolve(),
  route: () => Promise.reject(new Error('unused')),
};

describe('useMapDraft', () => {
  beforeEach(() => {
    registerMapApi(api);
    savedPayload = null;
    saveError = null;
    saveResponse = { ...detail({ version: 2 }), warnings: [] };
  });

  it('tracks dirty state and discards to the baseline', () => {
    const state = useMapDraft();
    state.setBaseline(detail());

    expect(state.dirty.value).toBe(false);
    state.updateMeta({ name: 'Аустан 2', description: '', visibility: 'private', fillOpacity: 55 });
    expect(state.dirty.value).toBe(true);
    state.discard();
    expect(state.dirty.value).toBe(false);
    expect(state.draft.value?.name).toBe('Аустан');
  });

  it('sends the version token and adopts the published snapshot', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    state.updateMeta({ name: 'Аустан 2', description: '', visibility: 'private', fillOpacity: 55 });

    const result = await state.save();

    expect(savedPayload).toMatchObject({ id: 1, expectedVersion: 1 });
    expect(savedPayload?.map.name).toBe('Аустан 2');
    expect(result).not.toBeNull();
    expect(state.dirty.value).toBe(false);
    expect(state.draft.value?.version).toBe(2);
  });

  it('keeps the draft and exposes conflict details', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    state.updateMeta({ name: 'Аустан 2', description: '', visibility: 'private', fillOpacity: 55 });
    saveError = new MapApiError('MAP_VERSION_CONFLICT', 'conflict', {
      currentVersion: 3,
      updatedAt: 10,
      updatedBy: 2,
    });

    const result = await state.save();

    expect(result).toBeNull();
    expect(state.dirty.value).toBe(true);
    expect(state.conflict.value).toEqual({ currentVersion: 3, updatedAt: 10, updatedBy: 2 });
  });

  it('keeps per-point widths and handles, and supports undo', () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    const lineId = state.addLine(
      {
        color: '#3f6f8f',
        speed: 400,
        width: 10,
        widthAll: false,
        label: 'Река',
        segments: [],
        colorBlend: 16,
        decor: {},
      },
      1,
      [
        [0, 0],
        [10, 0],
      ],
    );

    expect(state.draft.value?.lines[0].widths).toEqual([10, 10]);
    state.setLineWidth(lineId, 1, 24);
    state.setLineHandle(lineId, 0, 'out', [6, 3]);

    expect(state.draft.value?.lines[0].widths).toEqual([10, 24]);
    expect(state.draft.value?.lines[0].handles[0].out).toEqual([6, 3]);
    expect(state.canUndo.value).toBe(true);

    state.undo();
    expect(state.draft.value?.lines[0].widths).toEqual([10, 24]);
    expect(state.draft.value?.lines[0].handles[0].out).toEqual([0, 0]);
    state.undo();
    expect(state.draft.value?.lines[0].widths).toEqual([10, 10]);
    state.undo();
    expect(state.draft.value?.lines).toEqual([]);
    state.redo();
    expect(state.draft.value?.lines[0].widths).toEqual([10, 10]);
  });

  it('creates ribbons with smooth interior handles', () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    state.addLine(
      {
        color: '#3f6f8f',
        speed: 400,
        width: 10,
        widthAll: false,
        label: 'Река',
        segments: [],
        colorBlend: 16,
        decor: {},
      },
      1,
      [
        [0, 0],
        [10, 10],
        [20, 0],
      ],
    );

    const line = state.draft.value?.lines[0];
    expect(line?.handles[0].out).toEqual([0, 0]);
    expect(line?.handles[2].in).toEqual([0, 0]);
    expect(line?.handles[1].out[0]).toBeGreaterThan(0);
    expect(line?.handles[1].in[0]).toBeLessThan(0);
  });

  it('rounds fractional geometry before map.save', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    const lineId = state.addLine(
      {
        color: '#3f6f8f',
        speed: 400,
        width: 10,
        widthAll: false,
        label: 'Река',
        segments: [],
        colorBlend: 16,
        decor: {},
      },
      1,
      [
        [0, 0],
        [10, 0],
      ],
    );
    state.setLineVertex(lineId, 0, [1.4, 2.6]);
    state.setLineWidth(lineId, 0, 10.6);
    state.setLineHandle(lineId, 0, 'out', [3.2, -4.7]);

    await state.save();

    expect(savedPayload?.map.lines[0].points[0]).toEqual([1, 3]);
    expect(savedPayload?.map.lines[0].widths[0]).toBe(11);
    expect(savedPayload?.map.lines[0].handles[0].out).toEqual([3, -5]);
  });

  it('clamps points to the backdrop bounds before map.save', async () => {
    const state = useMapDraft();
    state.setBaseline(
      detail({
        variants: [
          {
            id: 1,
            label: 'Основная',
            fromMinutes: null,
            sortOrder: 0,
            baseFile: { publicId: 'backdrop', width: 200, height: 100 },
            millimetersPerPixel: 1000,
            isDefault: true,
          },
        ],
      }),
    );
    const nodeId = state.addNode(
      {
        name: 'Край',
        shortInfo: '',
        publicInfo: '',
        gmInfo: '',
        linkedMapId: null,
        icon: '',
      },
      1,
      [-5, 150],
    );
    state.addArea(
      {
        layer: 'object',
        color: '#8d8d8d',
        decor: {},
        speed: 0,
        label: 'Край',
        nodeId,
        sortOrder: 0,
        place: null,
      },
      1,
      [
        [-10, -10],
        [250, 50],
        [250, -20],
      ],
    );
    state.addLine(
      {
        color: '#3f6f8f',
        speed: 400,
        width: 10,
        widthAll: false,
        label: 'Река',
        segments: [],
        colorBlend: 16,
        decor: {},
      },
      1,
      [
        [-20, 50],
        [250, 140],
      ],
    );
    state.setLineVertex(state.draft.value?.lines[0].id ?? 0, 0, [Number.NaN, 50]);

    await state.save();

    expect(savedPayload?.map.nodes[0].placements[0]).toMatchObject({ x: 0, y: 100 });
    expect(savedPayload?.map.areas[0].shapes[0].path).toEqual([
      [0, 0],
      [200, 50],
      [200, 0],
    ]);
    expect(savedPayload?.map.areas[0].shapes[0].handles).toEqual([
      { in: [0, 0], out: [0, 0] },
      { in: [0, 0], out: [0, 0] },
      { in: [0, 0], out: [0, 0] },
    ]);
    expect(savedPayload?.map.lines[0].points).toEqual([
      [0, 50],
      [200, 100],
    ]);
  });

  it('keeps area bezier handles and splits a curved contour', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    const areaId = state.addArea(
      {
        layer: 'surface',
        color: '#4e7a4e',
        decor: {},
        speed: 2000,
        label: 'Лес',
        nodeId: null,
        sortOrder: 0,
        place: null,
      },
      1,
      [
        [0, 0],
        [100, 0],
        [100, 100],
        [0, 100],
      ],
    );
    state.setAreaHandle(areaId, 1, 0, 'out', [0, 40]);
    state.setAreaHandle(areaId, 1, 1, 'in', [0, 40]);
    state.insertAreaVertex(areaId, 1, 0, [50, 30], { in: [-10, -5], out: [10, 5] });

    const shape = state.draft.value?.areas[0].shapes[0];
    expect(shape?.path).toHaveLength(5);
    expect(shape?.handles).toHaveLength(5);
    expect(shape?.handles[1]).toEqual({ in: [-10, -5], out: [10, 5] });

    await state.save();
    expect(savedPayload?.map.areas[0].shapes[0].handles[1]).toEqual({ in: [-10, -5], out: [10, 5] });
    expect(savedPayload?.map.areas[0].shapes[0].handles[0].out).toEqual([0, 40]);
  });

  it('moves areas inside their layer and lines inside their variant', () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    const firstArea = state.addArea(
      {
        layer: 'object',
        color: '#111111',
        decor: {},
        speed: 0,
        label: 'Первый',
        nodeId: null,
        sortOrder: 0,
        place: null,
      },
      1,
      [
        [0, 0],
        [10, 0],
        [10, 10],
      ],
    );
    const secondArea = state.addArea(
      {
        layer: 'object',
        color: '#222222',
        decor: {},
        speed: 0,
        label: 'Второй',
        nodeId: null,
        sortOrder: 0,
        place: null,
      },
      1,
      [
        [20, 20],
        [30, 20],
        [30, 30],
      ],
    );
    state.moveArea(secondArea, -1);
    expect(state.draft.value?.areas.find((area) => area.id === secondArea)?.sortOrder).toBe(0);
    expect(state.draft.value?.areas.find((area) => area.id === firstArea)?.sortOrder).toBe(1);

    const firstLine = state.addLine(
      {
        color: '#3f6f8f',
        speed: 400,
        width: 10,
        widthAll: false,
        label: 'Первая',
        segments: [],
        colorBlend: 16,
        decor: {},
      },
      1,
      [
        [0, 0],
        [10, 0],
      ],
    );
    const secondLine = state.addLine(
      {
        color: '#c5c6c6',
        speed: 10000,
        width: 10,
        widthAll: false,
        label: 'Вторая',
        segments: [],
        colorBlend: 16,
        decor: {},
      },
      1,
      [
        [0, 20],
        [10, 20],
      ],
    );
    state.moveLine(secondLine, -1);
    expect(state.draft.value?.lines.find((line) => line.id === secondLine)?.sortOrder).toBe(0);
    expect(state.draft.value?.lines.find((line) => line.id === firstLine)?.sortOrder).toBe(1);
  });

  it('keeps a style override for a single line segment', () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    const lineId = state.addLine(
      {
        color: '#3f6f8f',
        speed: 400,
        width: 10,
        widthAll: false,
        label: 'Река',
        segments: [],
        colorBlend: 16,
        decor: {},
      },
      1,
      [
        [0, 0],
        [10, 0],
        [20, 0],
      ],
    );
    state.setLineSegmentStyle(lineId, 1, { speed: 1000, color: '#b7a98a' });

    expect(state.draft.value?.lines[0].segments[1]).toEqual({ speed: 1000, color: '#b7a98a' });

    state.resetLineSegments(lineId);
    expect(state.draft.value?.lines[0].segments).toEqual([]);
  });

  it('serializes line widths and handles into map.save', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    const lineId = state.addLine(
      {
        color: '#3f6f8f',
        speed: 400,
        width: 10,
        widthAll: false,
        label: 'Река',
        segments: [],
        colorBlend: 16,
        decor: {},
      },
      1,
      [
        [0, 0],
        [10, 0],
      ],
    );
    state.setLineWidth(lineId, 1, 20);
    state.setLineHandle(lineId, 0, 'out', [4, 2]);

    await state.save();

    expect(savedPayload?.map.lines[0].widths).toEqual([10, 20]);
    expect(savedPayload?.map.lines[0].handles[0].out).toEqual([4, 2]);
  });

  it('applies one width to all points and scales widths proportionally', () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    const lineId = state.addLine(
      {
        color: '#3f6f8f',
        speed: 400,
        width: 10,
        widthAll: true,
        label: 'Река',
        segments: [],
        colorBlend: 16,
        decor: {},
      },
      1,
      [
        [0, 0],
        [10, 0],
        [20, 0],
      ],
    );
    state.setLineWidth(lineId, 1, 20);
    expect(state.draft.value?.lines[0].widths).toEqual([10, 20, 10]);

    state.scaleLineWidths(lineId, 1, 40);
    expect(state.draft.value?.lines[0].widths).toEqual([20, 40, 20]);

    state.updateLine(lineId, {
      color: '#3f6f8f',
      speed: 400,
      width: 7,
      widthAll: true,
      label: 'Река',
      segments: [],
      colorBlend: 16,
      decor: {},
    });
    expect(state.draft.value?.lines[0].widths).toEqual([7, 7, 7]);
  });

  it('serializes segment styles and speed into map.save', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    const lineId = state.addLine(
      {
        color: '#3f6f8f',
        speed: 400,
        width: 10,
        widthAll: true,
        label: 'Река',
        segments: [],
        colorBlend: 16,
        decor: {},
      },
      1,
      [
        [0, 0],
        [10, 0],
        [20, 0],
      ],
    );
    state.setLineSegmentStyle(lineId, 0, { speed: 1000.4, color: '#b7a98a' });
    state.setLineSegmentStyle(lineId, 1, { speed: -5, color: null });

    await state.save();

    expect(savedPayload?.map.lines[0].speed).toBe(400);
    expect(savedPayload?.map.lines[0].segments).toEqual([
      { speed: 1000, color: '#b7a98a' },
      { speed: 0, color: '#3f6f8f' },
    ]);
  });

  it('keeps map fill opacity and per-object decor overrides, including zero', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    state.updateMeta({ name: 'Аустан', description: '', visibility: 'private', fillOpacity: 30 });
    state.addArea(
      {
        layer: 'surface',
        color: '#4e7a4e',
        decor: { fillOpacity: 0 },
        speed: 2000,
        label: 'Лес',
        nodeId: null,
        sortOrder: 0,
        place: null,
      },
      1,
      [
        [0, 0],
        [10, 0],
        [10, 10],
      ],
    );
    state.addLine(
      {
        color: '#3f6f8f',
        speed: 400,
        width: 10,
        widthAll: true,
        label: 'Река',
        segments: [],
        colorBlend: 16,
        decor: { fillOpacity: 0, depth: 40, depthWidth: 70 },
      },
      1,
      [
        [0, 0],
        [100, 0],
      ],
    );

    await state.save();

    expect(savedPayload?.map.fillOpacity).toBe(30);
    expect(savedPayload?.map.areas[0].decor).toEqual({ fillOpacity: 0 });
    expect(savedPayload?.map.lines[0].decor).toEqual({ fillOpacity: 0, depth: 40, depthWidth: 70 });
  });

  it('saves a copy without an id and adopts the new map', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    state.updateMeta({ name: 'Аустан 2', description: '', visibility: 'public', fillOpacity: 55 });
    saveResponse = { ...detail({ id: 7, version: 1, name: 'Аустан 2 (копия)', visibility: 'private' }), warnings: [] };

    const result = await state.saveAsCopy();

    expect(savedPayload).toMatchObject({ map: { name: 'Аустан 2 (копия)', visibility: 'private' } });
    expect(savedPayload?.id).toBeUndefined();
    expect(result?.id).toBe(7);
    expect(state.draft.value?.id).toBe(7);
    expect(state.dirty.value).toBe(false);
  });
});
