import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Space } from '@/modules/Roleplay/Space/Dto/Space'
import type { SpaceCreateData } from '@/modules/Roleplay/Space/Dto/SpaceCreateData'
import type { SpaceUpdateData } from '@/modules/Roleplay/Space/Dto/SpaceUpdateData'
import { getSpaceApi } from '../init'

export const useSpaceStore = defineStore('spaces', () => {
  const spaces = ref<Space[]>([])
  const currentSpace = ref<Space | null>(null)
  const loading = ref(false)
  const quickFilter = ref('')
  const filterName = ref<{ mode: 'equals' | 'contains'; value: string } | null>(null)
  const filterActive = ref('')

  const filteredSpaces = computed(() => {
    let result = spaces.value
    if (quickFilter.value) {
      const q = quickFilter.value.toLowerCase()
      result = result.filter(s =>
        s.name.toLowerCase().includes(q) ||
        s.description.toLowerCase().includes(q)
      )
    }
    if (filterName.value && filterName.value.value) {
      const q = filterName.value.value.toLowerCase()
      if (filterName.value.mode === 'equals') {
        result = result.filter(s => s.name.toLowerCase() === q)
      } else {
        result = result.filter(s => s.name.toLowerCase().includes(q))
      }
    }
    if (filterActive.value === 'true') {
      result = result.filter(s => s.active)
    } else if (filterActive.value === 'false') {
      result = result.filter(s => !s.active)
    }
    return result
  })

  async function fetchSpaces(signal?: AbortSignal) {
    loading.value = true
    try {
      spaces.value = await getSpaceApi().getSpaces(signal)
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return
      console.error('fetchSpaces failed', e)
    } finally {
      loading.value = false
    }
  }

  async function fetchSpace(id: number, signal?: AbortSignal): Promise<Space> {
    const space = await getSpaceApi().getSpace(id, signal)
    currentSpace.value = space
    return space
  }

  async function fetchSpaceByCode(code: string, signal?: AbortSignal): Promise<Space> {
    const space = await getSpaceApi().getSpaceByCode(code, signal)
    currentSpace.value = space
    return space
  }

  async function createSpace(data: SpaceCreateData, signal?: AbortSignal): Promise<Space> {
    const space = await getSpaceApi().createSpace(data, signal)
    spaces.value.push(space)
    return space
  }

  async function updateSpace(id: number, data: SpaceUpdateData, signal?: AbortSignal): Promise<Space> {
    const space = await getSpaceApi().updateSpace(id, data, signal)
    const idx = spaces.value.findIndex(s => s.id === id)
    if (idx !== -1) spaces.value[idx] = space
    if (currentSpace.value?.id === id) currentSpace.value = space
    return space
  }

  async function deactivateSpace(id: number, signal?: AbortSignal): Promise<void> {
    await getSpaceApi().deactivateSpace(id, signal)
    const space = spaces.value.find(s => s.id === id)
    if (space) space.active = false
    if (currentSpace.value?.id === id) currentSpace.value.active = false
  }

  function clearCurrent() {
    currentSpace.value = null
  }

  return {
    spaces, currentSpace, loading,
    quickFilter, filterName, filterActive,
    filteredSpaces,
    fetchSpaces, fetchSpace, fetchSpaceByCode, createSpace, updateSpace, deactivateSpace, clearCurrent,
  }
})
