import { describe, it, expect, beforeEach } from 'vitest';
import { computed, createApp, nextTick, ref } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { registerRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/init';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { mockRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/Mock/mockRuleSpaceApi';
import { useSpaceContextResolve } from '@/modules/Roleplay/RuleSpace/Composables/useSpaceContextResolve';
import { useSpaceRevisionStore } from '@/modules/Roleplay/RuleSpace/Store/spaceRevision';
import { useSpaceStore } from '@/modules/Roleplay/RuleSpace/Store/spaces';
import type { IRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/Interface/IRuleSpaceApi';
import type { Space } from '@/modules/Roleplay/RuleSpace/Dto/Space';
import type { SpaceRevision } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevision';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

interface Deferred<T> {
  promise: Promise<T>;
  resolve: (value: T) => void;
  reject: (reason: unknown) => void;
}

function deferred<T>(): Deferred<T> {
  let resolve: (value: T) => void = () => undefined;
  let reject: (reason: unknown) => void = () => undefined;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });

  return { promise, resolve, reject };
}

function spaceOf(id: number, code: string, revision: number): Space {
  return {
    id,
    code,
    name: code,
    description: '',
    ownerId: 1,
    revision,
    active: true,
    createdAt: 1,
    rulesCount: 0,
  };
}

function revisionOf(revision: number, code: string): SpaceRevision<Rule> {
  return {
    revision,
    publishedAt: 1,
    spaceCode: code,
    spaceName: code,
    rules: [],
    sections: [],
  };
}

const spaceA = spaceOf(1, 'razrabotka', 5);
const spaceB = spaceOf(2, 'actual', 12);

beforeEach(() => {
  localStorage.clear();
  setActivePinia(createPinia());
  resetRegisteredApis();
});

function installApi(overrides: Partial<IRuleSpaceApi>): void {
  registerRuleSpaceApi({ ...mockRuleSpaceApi, getRevisions: async () => [], ...overrides });
}

async function flush(): Promise<void> {
  await Promise.resolve();
  await nextTick();
  await Promise.resolve();
}

async function until(ready: () => boolean): Promise<void> {
  for (let attempt = 0; attempt < 20 && !ready(); attempt += 1) {
    await flush();
  }
}

function mountResolve(code: string, ctx: string | undefined) {
  const codeRef = ref(code);
  const ctxRef = ref<string | undefined>(ctx);
  const pinia = createPinia();
  setActivePinia(pinia);
  let api: ReturnType<typeof useSpaceContextResolve> | undefined;
  const app = createApp({
    setup() {
      api = useSpaceContextResolve(
        computed(() => codeRef.value),
        computed(() => ctxRef.value),
        (path) => {
          const match = /^\/space\/([^/]+)(?:\/([^/]+))?$/.exec(path);
          codeRef.value = match?.[1] ?? '';
          ctxRef.value = match?.[2];
        },
      );

      return () => null;
    },
  });
  app.use(pinia);
  const root = document.createElement('div');
  app.mount(root);
  if (!api) throw new Error('composable не смонтирован');

  return { api, app, codeRef, ctxRef };
}

describe('useSpaceContextResolve', () => {
  it('поздний ответ предыдущего route не затирает currentSpace и activeContext', async () => {
    const spaces = new Map<string, Deferred<Space>>();
    const revisions = new Map<string, Deferred<SpaceRevision<Rule>>>();
    installApi({
      getSpaceByCode: (code) => {
        const pending = deferred<Space>();
        spaces.set(code, pending);

        return pending.promise;
      },
      getRevision: (spaceId, revision) => {
        const pending = deferred<SpaceRevision<Rule>>();
        revisions.set(`${spaceId}:${revision}`, pending);

        return pending.promise;
      },
    });

    const { api, codeRef, ctxRef } = mountResolve('razrabotka', '5');
    await flush();
    codeRef.value = 'actual';
    ctxRef.value = '7';
    await flush();

    spaces.get('actual')?.resolve(spaceB);
    await until(() => revisions.has('2:7'));
    revisions.get('2:7')?.resolve(revisionOf(7, 'actual'));
    await until(() => api.loading.value === false && useSpaceRevisionStore().activeContext.spaceId === 2);

    const spaceStore = useSpaceStore();
    expect(spaceStore.currentSpace?.code).toBe('actual');
    expect(useSpaceRevisionStore().activeContext).toEqual({ spaceId: 2, revision: 7, kind: 'rev' });
    expect(api.loadedCode.value).toBe('actual');
    expect(api.loading.value).toBe(false);
    expect(api.error.value).toBeNull();

    spaces.get('razrabotka')?.resolve(spaceA);
    await flush();
    revisions.get('1:5')?.resolve(revisionOf(5, 'razrabotka'));
    await flush();

    expect(spaceStore.currentSpace?.code).toBe('actual');
    expect(useSpaceRevisionStore().activeContext).toEqual({ spaceId: 2, revision: 7, kind: 'rev' });
    expect(api.loadedCode.value).toBe('actual');
    expect(api.loading.value).toBe(false);
    expect(api.error.value).toBeNull();
  });

  it('завершившийся старый запрос не снимает loading и не пишет error нового', async () => {
    const spaces: Deferred<Space>[] = [];
    installApi({
      getSpaceByCode: () => {
        const pending = deferred<Space>();
        spaces.push(pending);

        return pending.promise;
      },
    });

    const { api, codeRef, ctxRef } = mountResolve('razrabotka', '5');
    await flush();
    codeRef.value = 'actual';
    ctxRef.value = '7';
    await flush();

    spaces[0]?.reject(new Error('устаревшая ошибка'));
    await flush();

    expect(api.loading.value).toBe(true);
    expect(api.error.value).toBeNull();
    expect(useSpaceStore().currentSpace).toBeNull();
  });

  it('поздний draft не откатывает уже загруженную revision', async () => {
    const revisions = new Map<string, Deferred<SpaceRevision<Rule>>>();
    installApi({
      getSpaceByCode: async () => spaceA,
      getRevision: (spaceId, revision) => {
        const pending = deferred<SpaceRevision<Rule>>();
        revisions.set(`${spaceId}:${revision}`, pending);

        return pending.promise;
      },
    });

    const { ctxRef } = mountResolve('razrabotka', 'draft');
    await until(() => revisions.has('1:5'));
    expect(revisions.has('1:5')).toBe(true);

    ctxRef.value = '7';
    await until(() => revisions.has('1:7'));
    revisions.get('1:7')?.resolve(revisionOf(7, 'razrabotka'));
    await until(() => useSpaceRevisionStore().activeContext.revision === 7);
    revisions.get('1:5')?.resolve(revisionOf(5, 'razrabotka'));
    await flush();

    expect(useSpaceRevisionStore().activeContext).toEqual({ spaceId: 1, revision: 7, kind: 'rev' });
    expect(useSpaceStore().currentSpace?.code).toBe('razrabotka');
  });

  it('retry после ошибки загружает пространство и игнорирует поздний reject первой попытки', async () => {
    const spaces: Deferred<Space>[] = [];
    const revisions: Deferred<SpaceRevision<Rule>>[] = [];
    installApi({
      getSpaceByCode: () => {
        const pending = deferred<Space>();
        spaces.push(pending);

        return pending.promise;
      },
      getRevision: () => {
        const pending = deferred<SpaceRevision<Rule>>();
        revisions.push(pending);

        return pending.promise;
      },
    });

    const { api } = mountResolve('razrabotka', '5');
    await flush();
    spaces[0]?.reject(new Error('сеть'));
    await until(() => api.error.value === 'сеть');
    expect(api.error.value).toBe('сеть');
    expect(api.loadedCode.value).toBe('');

    api.retry();
    await until(() => spaces.length === 2);
    spaces[1]?.resolve(spaceA);
    await until(() => revisions.length === 1);
    revisions[0]?.resolve(revisionOf(5, 'razrabotka'));
    await until(() => api.loading.value === false);

    expect(api.error.value).toBeNull();
    expect(api.loading.value).toBe(false);
    expect(useSpaceStore().currentSpace?.code).toBe('razrabotka');
    expect(useSpaceRevisionStore().activeContext).toEqual({ spaceId: 1, revision: 5, kind: 'rev' });
  });

  it('повтор пока первый запрос ещё в полёте не принимает его поздний reject', async () => {
    const spaces: Deferred<Space>[] = [];
    installApi({
      getSpaceByCode: async (code) => {
        const pending = deferred<Space>();
        spaces.push(pending);
        const space = await pending.promise;
        if (space.code !== code) throw new Error('чужой space');

        return space;
      },
      getRevision: async (_spaceId, revision) => revisionOf(revision, 'razrabotka'),
    });

    const { api } = mountResolve('razrabotka', '5');
    await flush();
    api.retry();
    await until(() => spaces.length === 2);
    spaces[1]?.resolve(spaceA);
    await until(() => api.loading.value === false);
    spaces[0]?.reject(new Error('первая попытка'));
    await flush();

    expect(api.error.value).toBeNull();
    expect(useSpaceStore().currentSpace?.code).toBe('razrabotka');
    expect(api.loading.value).toBe(false);
  });

  it('unmount не даёт позднему ответу записать store', async () => {
    const pending = deferred<Space>();
    installApi({
      getSpaceByCode: () => pending.promise,
    });

    const { app } = mountResolve('razrabotka', '5');
    await flush();
    app.unmount();
    pending.resolve(spaceA);
    await flush();

    expect(useSpaceStore().currentSpace).toBeNull();
    expect(useSpaceRevisionStore().activeContext).toEqual({ spaceId: null, revision: null, kind: 'rev' });
  });
});
