import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { registerMechanicApi } from '@/modules/Roleplay/Rule/init';
import { mockMechanicApi } from '@/modules/Roleplay/Rule/Mock/mockMechanicApi';
import { useMechanicStore } from '@/modules/Roleplay/Rule/Store/mechanics';
import type { IMechanicApi } from '@/modules/Roleplay/Rule/Interface/IMechanicApi';

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
});

function failingApi(getMechanics: () => Promise<never>): IMechanicApi {
  return {
    ...mockMechanicApi,
    getMechanics,
  };
}

describe('mechanics store', () => {
  it('fetchMechanics loads mechanics and leaves error empty', async () => {
    registerMechanicApi(mockMechanicApi);
    const store = useMechanicStore();

    await store.fetchMechanics();

    expect(store.mechanics.length).toBeGreaterThan(0);
    expect(store.error).toBeNull();
  });

  it('fetchMechanics sets error on failure', async () => {
    registerMechanicApi(
      failingApi(() => {
        throw new Error('boom');
      }),
    );
    const store = useMechanicStore();

    await store.fetchMechanics();

    expect(store.mechanics.length).toBe(0);
    expect(store.error).toBe('Не удалось загрузить механики');
  });

  it('fetchMechanics clears error on successful retry', async () => {
    registerMechanicApi(
      failingApi(() => {
        throw new Error('boom');
      }),
    );
    const store = useMechanicStore();
    await store.fetchMechanics();
    expect(store.error).toBe('Не удалось загрузить механики');

    registerMechanicApi(mockMechanicApi);
    await store.fetchMechanics();

    expect(store.mechanics.length).toBeGreaterThan(0);
    expect(store.error).toBeNull();
  });

  it('fetchMechanics ignores AbortError', async () => {
    registerMechanicApi(
      failingApi(() => {
        throw new DOMException('aborted', 'AbortError');
      }),
    );
    const store = useMechanicStore();

    await store.fetchMechanics();

    expect(store.mechanics.length).toBe(0);
    expect(store.error).toBeNull();
  });
});
