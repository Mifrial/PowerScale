import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Tag } from '../Interface/types'
import type { CreateTagData, UpdateTagData } from '../Interface/ITagApi'
import { getTagApi } from '../init'

export const useTagStore = defineStore('tags', () => {
  const tags = ref<Tag[]>([])
  const currentTag = ref<Tag | null>(null)
  const loading = ref(false)
  const filterName = ref('')
  const filterActive = ref('')

  const filteredTags = computed(() => {
    let result = tags.value
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
      tags.value = await getTagApi().getTags(signal)
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return
      console.error('fetchTags failed', e)
    } finally {
      loading.value = false
    }
  }

  async function fetchTag(id: number, signal?: AbortSignal): Promise<Tag> {
    const tag = await getTagApi().getTag(id, signal)
    currentTag.value = tag
    return tag
  }

  async function createTag(data: CreateTagData, signal?: AbortSignal): Promise<Tag> {
    const tag = await getTagApi().createTag(data, signal)
    tags.value.push(tag)
    return tag
  }

  async function updateTag(id: number, data: UpdateTagData, signal?: AbortSignal): Promise<Tag> {
    const tag = await getTagApi().updateTag(id, data, signal)
    const idx = tags.value.findIndex(t => t.id === id)
    if (idx !== -1) tags.value[idx] = tag
    if (currentTag.value?.id === id) currentTag.value = tag
    return tag
  }

  async function deactivateTag(id: number, signal?: AbortSignal): Promise<void> {
    await getTagApi().deactivateTag(id, signal)
    const tag = tags.value.find(t => t.id === id)
    if (tag) tag.active = false
    if (currentTag.value?.id === id) currentTag.value.active = false
  }

  function clearCurrent() {
    currentTag.value = null
  }

  return {
    tags, currentTag, loading,
    filterName, filterActive,
    filteredTags,
    fetchTags, fetchTag, createTag, updateTag, deactivateTag, clearCurrent,
  }
})
