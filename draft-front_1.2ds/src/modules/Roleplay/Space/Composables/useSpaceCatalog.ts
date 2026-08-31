import { computed } from 'vue';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';

export function useSpaceCatalog() {
  const store = useSpaceStore();

  return {
    spaces: computed(() => store.spaces),
    error: computed(() => store.error),
    fetchSpaces: (signal?: AbortSignal) => store.fetchSpaces(signal),
    fetchSpace: (id: number, signal?: AbortSignal) => store.fetchSpace(id, signal),
    fetchSpaceByCode: (code: string, signal?: AbortSignal) => store.fetchSpaceByCode(code, signal),
  };
}
