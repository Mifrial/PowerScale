import { computed } from 'vue';
import { useKeywordStore } from '@/modules/Roleplay/Keyword/Store/keywords';

export function useKeywords() {
  const store = useKeywordStore();

  return {
    keywords: computed(() => store.keywords),
    error: computed(() => store.error),
    fetchTags: (signal?: AbortSignal) => store.fetchTags(signal),
  };
}
