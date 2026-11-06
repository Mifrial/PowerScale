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
    edges: [],
    ...overrides,
  };
}

let savedPayload: SaveMapData | null = null;
let saveResponse: MapSaveResult = { ...detail({ version: 2 }), warnings: [] };
let saveError: Error | null = null;

const api: IMapApi = {
  findPage: () => Promise.reject(new Error('unused')),
  get: () => Promise.reject(new Error('unused')),
  getTerrains: () => Promise.reject(new Error('unused')),
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
    state.updateMeta({ name: 'Аустан 2', description: '', visibility: 'private' });
    expect(state.dirty.value).toBe(true);
    state.discard();
    expect(state.dirty.value).toBe(false);
    expect(state.draft.value?.name).toBe('Аустан');
  });

  it('sends the version token and adopts the published snapshot', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    state.updateMeta({ name: 'Аустан 2', description: '', visibility: 'private' });

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
    state.updateMeta({ name: 'Аустан 2', description: '', visibility: 'private' });
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
    const lineId = state.addLine({ terrainCode: 'water', width: 10, label: 'Река' }, 1, [
      [0, 0],
      [10, 0],
    ]);

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
    state.addLine({ terrainCode: 'water', width: 10, label: 'Река' }, 1, [
      [0, 0],
      [10, 10],
      [20, 0],
    ]);

    const line = state.draft.value?.lines[0];
    expect(line?.handles[0].out).toEqual([0, 0]);
    expect(line?.handles[2].in).toEqual([0, 0]);
    expect(line?.handles[1].out[0]).toBeGreaterThan(0);
    expect(line?.handles[1].in[0]).toBeLessThan(0);
  });

  it('rounds fractional geometry before map.save', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    const lineId = state.addLine({ terrainCode: 'water', width: 10, label: 'Река' }, 1, [
      [0, 0],
      [10, 0],
    ]);
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
        isPoi: true,
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
        terrainCode: null,
        kind: 'other',
        label: 'Край',
        nodeId,
        blocksMovement: false,
      },
      1,
      [
        [-10, -10],
        [250, 50],
        [250, -20],
      ],
    );
    state.addLine({ terrainCode: 'water', width: 10, label: 'Река' }, 1, [
      [-20, 50],
      [250, 140],
    ]);
    state.setLineVertex(state.draft.value?.lines[0].id ?? 0, 0, [Number.NaN, 50]);

    await state.save();

    expect(savedPayload?.map.nodes[0].placements[0]).toMatchObject({ x: 0, y: 100 });
    expect(savedPayload?.map.areas[0].shapes[0].path).toEqual([
      [0, 0],
      [200, 50],
      [200, 0],
    ]);
    expect(savedPayload?.map.lines[0].points).toEqual([
      [0, 50],
      [200, 100],
    ]);
  });

  it('serializes line widths and handles into map.save', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    const lineId = state.addLine({ terrainCode: 'water', width: 10, label: 'Река' }, 1, [
      [0, 0],
      [10, 0],
    ]);
    state.setLineWidth(lineId, 1, 20);
    state.setLineHandle(lineId, 0, 'out', [4, 2]);

    await state.save();

    expect(savedPayload?.map.lines[0].widths).toEqual([10, 20]);
    expect(savedPayload?.map.lines[0].handles[0].out).toEqual([4, 2]);
  });

  it('saves a copy without an id and adopts the new map', async () => {
    const state = useMapDraft();
    state.setBaseline(detail());
    state.updateMeta({ name: 'Аустан 2', description: '', visibility: 'public' });
    saveResponse = { ...detail({ id: 7, version: 1, name: 'Аустан 2 (копия)', visibility: 'private' }), warnings: [] };

    const result = await state.saveAsCopy();

    expect(savedPayload).toMatchObject({ map: { name: 'Аустан 2 (копия)', visibility: 'private' } });
    expect(savedPayload?.id).toBeUndefined();
    expect(result?.id).toBe(7);
    expect(state.draft.value?.id).toBe(7);
    expect(state.dirty.value).toBe(false);
  });
});
