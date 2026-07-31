import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Group } from '../Interface/types'
import type { CreateGroupData, UpdateGroupData } from '../Interface/IGroupApi'
import { getGroupApi } from '../init'

export const useGroupStore = defineStore('groups', () => {
  const groups = ref<Group[]>([])
  const currentGroup = ref<Group | null>(null)
  const loading = ref(false)
  const filterName = ref('')
  const filterActive = ref('')

  const filteredGroups = computed(() => {
    let result = groups.value
    if (filterName.value) {
      const q = filterName.value.toLowerCase()
      result = result.filter(g => g.name.toLowerCase().includes(q))
    }
    if (filterActive.value === 'true') {
      result = result.filter(g => g.active)
    } else if (filterActive.value === 'false') {
      result = result.filter(g => !g.active)
    }
    return result
  })

  async function fetchGroups(signal?: AbortSignal) {
    loading.value = true
    try {
      groups.value = await getGroupApi().getGroups(signal)
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return
      console.error('fetchGroups failed', e)
    } finally {
      loading.value = false
    }
  }

  async function fetchGroup(id: number, signal?: AbortSignal): Promise<Group> {
    const group = await getGroupApi().getGroup(id, signal)
    currentGroup.value = group
    return group
  }

  async function createGroup(data: CreateGroupData, signal?: AbortSignal): Promise<Group> {
    const group = await getGroupApi().createGroup(data, signal)
    groups.value.push(group)
    return group
  }

  async function updateGroup(id: number, data: UpdateGroupData, signal?: AbortSignal): Promise<Group> {
    const group = await getGroupApi().updateGroup(id, data, signal)
    const idx = groups.value.findIndex(g => g.id === id)
    if (idx !== -1) groups.value[idx] = group
    if (currentGroup.value?.id === id) currentGroup.value = group
    return group
  }

  async function deactivateGroup(id: number, signal?: AbortSignal): Promise<void> {
    await getGroupApi().deactivateGroup(id, signal)
    const group = groups.value.find(g => g.id === id)
    if (group) group.active = false
    if (currentGroup.value?.id === id) currentGroup.value.active = false
  }

  function clearCurrent() {
    currentGroup.value = null
  }

  return {
    groups, currentGroup, loading,
    filterName, filterActive,
    filteredGroups,
    fetchGroups, fetchGroup, createGroup, updateGroup, deactivateGroup, clearCurrent,
  }
})
