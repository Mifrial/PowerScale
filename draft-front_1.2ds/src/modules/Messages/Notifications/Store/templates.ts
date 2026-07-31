import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { NotificationTemplate } from '../Interface/types'
import type { CreateTemplateData, UpdateTemplateData } from '../Interface/INotificationTemplateApi'
import { getTemplateApi } from '../init'

export const useTemplateStore = defineStore('templates', () => {
  const templates = ref<NotificationTemplate[]>([])
  const currentTemplate = ref<NotificationTemplate | null>(null)
  const loading = ref(false)
  const filterKey = ref('')

  const filteredTemplates = computed(() => {
    let result = templates.value
    if (filterKey.value) {
      const q = filterKey.value.toLowerCase()
      result = result.filter(t =>
        t.key.toLowerCase().includes(q) ||
        t.titleTemplate.toLowerCase().includes(q)
      )
    }
    return result
  })

  async function fetchTemplates(signal?: AbortSignal) {
    loading.value = true
    try {
      templates.value = await getTemplateApi().getTemplates(signal)
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return
      console.error('fetchTemplates failed', e)
    } finally {
      loading.value = false
    }
  }

  async function fetchTemplate(id: number, signal?: AbortSignal): Promise<NotificationTemplate> {
    const template = await getTemplateApi().getTemplate(id, signal)
    currentTemplate.value = template
    return template
  }

  async function createTemplate(data: CreateTemplateData, signal?: AbortSignal): Promise<NotificationTemplate> {
    const template = await getTemplateApi().createTemplate(data, signal)
    templates.value.push(template)
    return template
  }

  async function updateTemplate(id: number, data: UpdateTemplateData, signal?: AbortSignal): Promise<NotificationTemplate> {
    const template = await getTemplateApi().updateTemplate(id, data, signal)
    const idx = templates.value.findIndex(t => t.id === id)
    if (idx !== -1) templates.value[idx] = template
    if (currentTemplate.value?.id === id) currentTemplate.value = template
    return template
  }

  async function deleteTemplate(id: number, signal?: AbortSignal): Promise<void> {
    await getTemplateApi().deleteTemplate(id, signal)
    templates.value = templates.value.filter(t => t.id !== id)
    if (currentTemplate.value?.id === id) currentTemplate.value = null
  }

  function clearCurrent() {
    currentTemplate.value = null
  }

  return {
    templates, currentTemplate, loading,
    filterKey,
    filteredTemplates,
    fetchTemplates, fetchTemplate, createTemplate, updateTemplate, deleteTemplate, clearCurrent,
  }
})
