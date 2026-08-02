import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Keyword } from '../Dto/Keyword'
import type { CreateKeywordData, UpdateKeywordData } from '../Interface/IKeywordApi'
import { getKeywordApi } from '../init'

export const useKeywordStore = defineStore('keywords', () => {
  const keywords = ref<Keyword[]>([])
  const currentTag = ref<Keyword | null>(null)
  const loading = ref(false)
  const filterName = ref('')
  const filterActive = ref('')

  const filteredTags = computed(() => {
    let result = keywords.value
    if (filterName.value) {
      const q = filterName.value.toLowerCase()
      result = result.filter(t =>
        t.name.toLowerCase().includes(q) ||
        t.code.toLowerCase().includes(q) ||
        (t.description ?? '').toLowerCase().includes(q)
      )
    }
    if (filterActive.value === 'true') {
      result = result.filter(t => t.active)
    } else if (filterActive.value === 'false') {
      result = result.filter(t => !t.active)
    }
    return result
  })

  async function fetchTags(signal?: AbortSignal) {
    loading.value = true
    try {
      keywords.value = await getKeywordApi().getTags(signal)
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return
      console.error('fetchTags failed', e)
    } finally {
      loading.value = false
    }
  }

  async function fetchTag(id: number, signal?: AbortSignal): Promise<Keyword> {
    const keyword = await getKeywordApi().getTag(id, signal)
    currentTag.value = keyword
    return keyword
  }

  async function createTag(data: CreateKeywordData, signal?: AbortSignal): Promise<Keyword> {
    const keyword = await getKeywordApi().createTag(data, signal)
    keywords.value.push(keyword)
    return keyword
  }

  async function updateTag(id: number, data: UpdateKeywordData, signal?: AbortSignal): Promise<Keyword> {
    const keyword = await getKeywordApi().updateTag(id, data, signal)
    const idx = keywords.value.findIndex(t => t.id === id)
    if (idx !== -1) keywords.value[idx] = keyword
    if (currentTag.value?.id === id) currentTag.value = keyword
    return keyword
  }

  async function deactivateTag(id: number, signal?: AbortSignal): Promise<void> {
    await getKeywordApi().deactivateTag(id, signal)
    const keyword = keywords.value.find(t => t.id === id)
    if (keyword) keyword.active = false
    if (currentTag.value?.id === id) currentTag.value.active = false
  }

  function clearCurrent() {
    currentTag.value = null
  }

  return {
    keywords, currentTag, loading,
    filterName, filterActive,
    filteredTags,
    fetchTags, fetchTag, createTag, updateTag, deactivateTag, clearCurrent,
  }
})
