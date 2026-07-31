import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Source } from '../Interface/types'
import { getSourceApi } from '../init'

export const useSourceStore = defineStore('sources', () => {
  const sources = ref<Source[]>([])
  const loading = ref(false)

  async function fetchSources(signal?: AbortSignal) {
    loading.value = true
    try {
      sources.value = await getSourceApi().getSources(signal)
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return
      console.error('fetchSources failed', e)
    } finally {
      loading.value = false
    }
  }

  return { sources, loading, fetchSources }
})
