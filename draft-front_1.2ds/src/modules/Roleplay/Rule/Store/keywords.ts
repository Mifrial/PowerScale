import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { CreateKeywordData } from '@/modules/Roleplay/Rule/Dto/CreateKeywordData';
import type { UpdateKeywordData } from '@/modules/Roleplay/Rule/Dto/UpdateKeywordData';
import { getKeywordApi } from '@/modules/Roleplay/Rule/init';

export const useKeywordStore = defineStore('keywords', () => {
  const keywords = ref<Keyword[]>([]);
  const currentTag = ref<Keyword | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function fetchTags(signal?: AbortSignal) {
    loading.value = true;
    error.value = null;
    try {
      keywords.value = await getKeywordApi().getTags(signal);
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      error.value = 'Не удалось загрузить признаки';
    } finally {
      loading.value = false;
    }
  }

  async function fetchTag(id: number, signal?: AbortSignal): Promise<Keyword> {
    const keyword = await getKeywordApi().getTag(id, signal);
    currentTag.value = keyword;

    return keyword;
  }

  async function createTag(data: CreateKeywordData, signal?: AbortSignal): Promise<Keyword> {
    const keyword = await getKeywordApi().createTag(data, signal);
    keywords.value.push(keyword);

    return keyword;
  }

  async function updateTag(id: number, data: UpdateKeywordData, signal?: AbortSignal): Promise<Keyword> {
    const keyword = await getKeywordApi().updateTag(id, data, signal);
    const idx = keywords.value.findIndex((t) => t.id === id);
    if (idx !== -1) keywords.value[idx] = keyword;
    if (currentTag.value?.id === id) currentTag.value = keyword;

    return keyword;
  }

  async function deactivateTag(id: number, signal?: AbortSignal): Promise<void> {
    await getKeywordApi().deactivateTag(id, signal);
    const keyword = keywords.value.find((t) => t.id === id);
    if (keyword) keyword.active = false;
    if (currentTag.value?.id === id) currentTag.value.active = false;
  }

  function clearCurrent() {
    currentTag.value = null;
  }

  return {
    keywords,
    currentTag,
    loading,
    error,
    fetchTags,
    fetchTag,
    createTag,
    updateTag,
    deactivateTag,
    clearCurrent,
  };
});
