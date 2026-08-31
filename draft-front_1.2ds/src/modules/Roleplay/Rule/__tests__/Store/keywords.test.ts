import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { registerKeywordApi } from '@/modules/Roleplay/Rule/init';
import { mockKeywordApi } from '@/modules/Roleplay/Rule/Mock/mockKeywordApi';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';
import type { IKeywordApi } from '@/modules/Roleplay/Rule/Interface/IKeywordApi';

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
});

function failingApi(getTags: () => Promise<never>): IKeywordApi {
  return {
    ...mockKeywordApi,
    getTags,
  };
}

describe('keywords store', () => {
  it('fetchTags loads keywords and leaves error empty', async () => {
    registerKeywordApi(mockKeywordApi);
    const store = useKeywordStore();

    await store.fetchTags();

    expect(store.keywords.length).toBeGreaterThan(0);
    expect(store.error).toBeNull();
  });

  it('fetchTags sets error on failure', async () => {
    registerKeywordApi(
      failingApi(() => {
        throw new Error('boom');
      }),
    );
    const store = useKeywordStore();

    await store.fetchTags();

    expect(store.keywords.length).toBe(0);
    expect(store.error).toBe('Не удалось загрузить признаки');
  });

  it('fetchTags clears error on successful retry', async () => {
    registerKeywordApi(
      failingApi(() => {
        throw new Error('boom');
      }),
    );
    const store = useKeywordStore();
    await store.fetchTags();
    expect(store.error).toBe('Не удалось загрузить признаки');

    registerKeywordApi(mockKeywordApi);
    await store.fetchTags();

    expect(store.keywords.length).toBeGreaterThan(0);
    expect(store.error).toBeNull();
  });

  it('fetchTags ignores AbortError', async () => {
    registerKeywordApi(
      failingApi(() => {
        throw new DOMException('aborted', 'AbortError');
      }),
    );
    const store = useKeywordStore();

    await store.fetchTags();

    expect(store.keywords.length).toBe(0);
    expect(store.error).toBeNull();
  });
});
