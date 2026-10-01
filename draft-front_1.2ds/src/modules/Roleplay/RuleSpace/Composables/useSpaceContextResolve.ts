import { onBeforeUnmount, ref, watch, type ComputedRef } from 'vue';
import type { Space } from '@/modules/Roleplay/RuleSpace/Dto/Space';
import { useSpaceRevisionStore } from '@/modules/Roleplay/RuleSpace/Store/spaceRevision';
import { useSpaceStore } from '@/modules/Roleplay/RuleSpace/Store/spaces';

function isAbortError(caught: unknown): boolean {
  return caught instanceof DOMException && caught.name === 'AbortError';
}

/**
 * Загрузка пространства и ревизии для текущего route.
 * Новая навигация отменяет предыдущий запрос и не даёт ему записать состояние.
 */
export function useSpaceContextResolve(
  code: ComputedRef<string | undefined>,
  ctx: ComputedRef<string | undefined>,
  replaceRoute: (path: string) => void,
) {
  const spaceStore = useSpaceStore();
  const revisionStore = useSpaceRevisionStore();
  const loading = ref(true);
  const error = ref<string | null>(null);
  const loadedCode = ref('');
  let generation = 0;
  let controller: AbortController | null = null;

  function beginRequest(): { generation: number; signal: AbortSignal } {
    controller?.abort();
    generation += 1;
    controller = new AbortController();

    return { generation, signal: controller.signal };
  }

  function isCurrent(requestGeneration: number): boolean {
    return requestGeneration === generation;
  }

  async function syncContext(space: Space, routeCtx: string, signal: AbortSignal): Promise<void> {
    if (routeCtx === 'draft') {
      await revisionStore.syncFromContext(space.id, 'draft', space.revision, signal);

      return;
    }
    await revisionStore.syncFromContext(space.id, 'rev', Number(routeCtx), signal);
  }

  async function resolve(): Promise<void> {
    const request = beginRequest();
    const routeCode = code.value;
    const routeCtx = ctx.value;
    if (!routeCode) {
      if (isCurrent(request.generation)) loading.value = false;

      return;
    }

    loading.value = true;
    error.value = null;
    try {
      let space = spaceStore.currentSpace;
      if (routeCode !== loadedCode.value || space?.code !== routeCode) {
        space = await spaceStore.fetchSpaceByCode(routeCode, request.signal);
        if (!isCurrent(request.generation)) return;
        loadedCode.value = routeCode;
        await revisionStore.fetchRevisionsMeta(space.id, request.signal);
        if (!isCurrent(request.generation)) return;
      }
      if (!isCurrent(request.generation)) return;
      if (!space || space.code !== routeCode) return;

      if (routeCtx === undefined) {
        revisionStore.clearContext();

        return;
      }
      if (routeCtx !== 'draft' && !/^\d+$/.test(routeCtx)) {
        replaceRoute(`/space/${routeCode}`);

        return;
      }
      if (routeCtx === '0') {
        replaceRoute(`/space/${routeCode}/draft`);

        return;
      }
      await syncContext(space, routeCtx, request.signal);
    } catch (caught: unknown) {
      if (!isCurrent(request.generation)) return;
      if (isAbortError(caught)) return;
      error.value = caught instanceof Error ? caught.message : 'Ошибка загрузки пространства';
    } finally {
      if (isCurrent(request.generation)) loading.value = false;
    }
  }

  function retry(): void {
    loadedCode.value = '';
    void resolve();
  }

  watch(
    () => [code.value, ctx.value],
    () => {
      void resolve();
    },
    { immediate: true },
  );

  onBeforeUnmount(() => {
    generation += 1;
    controller?.abort();
  });

  return { loading, error, retry, loadedCode };
}
