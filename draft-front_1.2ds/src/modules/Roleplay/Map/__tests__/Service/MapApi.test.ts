import { describe, expect, it } from 'vitest';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { ActionResponse } from '@/modules/Core/Engine/Dto/ActionResponse';
import type { MapSnapshot } from '@/modules/Roleplay/Map/Dto/MapSnapshot';
import { MapApi } from '@/modules/Roleplay/Map/Service/MapApi';
import { MapApiError } from '@/modules/Roleplay/Map/Service/MapApiError';

function fakeEngine(response: ActionResponse<unknown>): {
  engine: Engine;
  calls: { action: string; payload: unknown }[];
} {
  const calls: { action: string; payload: unknown }[] = [];
  const engine = {
    runAction: async (action: string, payload: unknown) => {
      calls.push({ action, payload });

      return response;
    },
  } as unknown as Engine;

  return { engine, calls };
}

const snapshot: MapSnapshot = {
  name: 'Аустан',
  description: '',
  visibility: 'private',
  fillOpacity: 55,
  variants: [
    {
      id: 1,
      label: 'Основная',
      fromMinutes: null,
      sortOrder: 0,
      filePublicId: null,
      millimetersPerPixel: 1000,
      isDefault: true,
    },
  ],
  nodes: [],
  areas: [],
  groups: [],
  lines: [],
};

describe('MapApi', () => {
  it('sends flat page filters and omits undefined ones', async () => {
    const { engine, calls } = fakeEngine({ success: true, data: { items: [], total: 0 } });
    const api = new MapApi(engine);

    await api.findPage({ limit: 20, offset: 0, q: 'Аустан' });

    expect(calls[0]).toEqual({
      action: 'map.findPage',
      payload: { limit: 20, offset: 0, q: 'Аустан' },
    });
  });

  it('sends the full snapshot with version token and force', async () => {
    const { engine, calls } = fakeEngine({ success: true, data: { id: 1 } });
    const api = new MapApi(engine);

    await api.save({ id: 1, expectedVersion: 3, force: true, map: snapshot });

    expect(calls[0]).toEqual({
      action: 'map.save',
      payload: { id: 1, expectedVersion: 3, force: true, map: snapshot },
    });
  });

  it('omits the id for a new map and keeps expectedVersion otherwise', async () => {
    const { engine, calls } = fakeEngine({ success: true, data: { id: 1 } });
    const api = new MapApi(engine);

    await api.save({ map: snapshot });
    await api.save({ id: 1, expectedVersion: 2, map: snapshot });

    expect(calls[0].payload).toEqual({ map: snapshot });
    expect(calls[1].payload).toEqual({ id: 1, expectedVersion: 2, map: snapshot });
  });

  it('keeps the invalid field path for MAP_INVALID', async () => {
    const { engine } = fakeEngine({
      success: false,
      data: null,
      error: { code: 'MAP_INVALID', message: 'lines[0].points has an invalid point' },
    });
    const api = new MapApi(engine);

    await expect(api.get(1)).rejects.toMatchObject({
      code: 'MAP_INVALID',
      message: 'Недопустимые данные карты: lines[0].points — недопустимая точка',
    });
  });

  it('maps action errors to MapApiError with a russian message', async () => {
    const { engine } = fakeEngine({
      success: false,
      data: null,
      error: { code: 'MAP_NO_ROUTE', message: 'no route' },
    });
    const api = new MapApi(engine);

    await expect(api.route({ mapId: 1, variantId: 1, fromX: 1, fromY: 2, toX: 3, toY: 4 })).rejects.toMatchObject({
      code: 'MAP_NO_ROUTE',
      message: 'Между точками нет проходимого маршрута',
    });
  });

  it('keeps conflict details on the version error', async () => {
    const { engine } = fakeEngine({
      success: false,
      data: null,
      error: { code: 'MAP_VERSION_CONFLICT', message: 'conflict', details: { currentVersion: 4 } },
    });
    const api = new MapApi(engine);

    await expect(api.save({ id: 1, expectedVersion: 3, map: snapshot })).rejects.toMatchObject({
      code: 'MAP_VERSION_CONFLICT',
      details: { currentVersion: 4 },
    });
  });

  it('rejects an empty successful response', async () => {
    const { engine } = fakeEngine({ success: true, data: null });
    const api = new MapApi(engine);

    await expect(api.get(1)).rejects.toBeInstanceOf(MapApiError);
  });
});
