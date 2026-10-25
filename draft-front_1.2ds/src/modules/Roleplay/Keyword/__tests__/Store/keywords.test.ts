import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { registerKeywordApi } from '@/modules/Roleplay/Keyword/init';
import { mockKeywordApi } from '@/modules/Roleplay/Keyword/Mock/mockKeywordApi';
import { useKeywordStore } from '@/modules/Roleplay/Keyword/Store/keywords';
import type { IKeywordApi } from '@/modules/Roleplay/Keyword/Interface/IKeywordApi';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import { keywords as mockKeywords } from '@/modules/Roleplay/Keyword/Mock/mockKeywords';

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
});

function deferred<T>() {
  let resolve: (value: T) => void = () => undefined;
  let reject: (reason?: unknown) => void = () => undefined;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });

  return { promise, resolve, reject };
}

function failingApi(getKeywords: () => Promise<never>): IKeywordApi {
  return {
    ...mockKeywordApi,
    getKeywords,
  };
}

describe('keywords store', () => {
  it('coalesces concurrent fetchTags calls into one request', async () => {
    const gate = deferred<Keyword[]>();
    let calls = 0;
    registerKeywordApi({
      ...mockKeywordApi,
      getKeywords: () => {
        calls += 1;

        return gate.promise;
      },
    });
    const store = useKeywordStore();

    const first = store.fetchTags();
    const second = store.fetchTags();

    expect(calls).toBe(1);
    expect(store.loading).toBe(true);

    gate.resolve(mockKeywords.map((keyword) => ({ ...keyword })));

    await Promise.all([first, second]);

    expect(store.keywords).toHaveLength(mockKeywords.length);
    expect(store.loading).toBe(false);
  });

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
