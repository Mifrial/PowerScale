import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { NotificationTemplate } from '@/modules/Messages/Notifications/Dto/NotificationTemplate';
import type { CreateTemplateData } from '@/modules/Messages/Notifications/Dto/CreateTemplateData';
import type { UpdateTemplateData } from '@/modules/Messages/Notifications/Dto/UpdateTemplateData';
import { getTemplateApi } from '@/modules/Messages/Notifications/init';

export const useTemplateStore = defineStore('templates', () => {
  const templates = ref<NotificationTemplate[]>([]);
  const currentTemplate = ref<NotificationTemplate | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function fetchTemplates(signal?: AbortSignal) {
    loading.value = true;
    error.value = null;
    try {
      templates.value = await getTemplateApi().getTemplates(signal);
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      error.value = 'Не удалось загрузить шаблоны';
    } finally {
      loading.value = false;
    }
  }

  async function fetchTemplate(id: number, signal?: AbortSignal): Promise<NotificationTemplate> {
    const template = await getTemplateApi().getTemplate(id, signal);
    currentTemplate.value = template;

    return template;
  }

  async function createTemplate(data: CreateTemplateData, signal?: AbortSignal): Promise<NotificationTemplate> {
    const template = await getTemplateApi().createTemplate(data, signal);
    templates.value.push(template);

    return template;
  }

  async function updateTemplate(
    id: number,
    data: UpdateTemplateData,
    signal?: AbortSignal,
  ): Promise<NotificationTemplate> {
    const template = await getTemplateApi().updateTemplate(id, data, signal);
    const idx = templates.value.findIndex((t) => t.id === id);
    if (idx !== -1) templates.value[idx] = template;
    if (currentTemplate.value?.id === id) currentTemplate.value = template;

    return template;
  }

  async function deactivateTemplate(id: number, signal?: AbortSignal): Promise<void> {
    await getTemplateApi().deactivateTemplate(id, signal);
    const t = templates.value.find((t) => t.id === id);
    if (t) t.active = false;
    if (currentTemplate.value?.id === id) currentTemplate.value.active = false;
  }

  function clearCurrent() {
    currentTemplate.value = null;
  }

  return {
    templates,
    currentTemplate,
    loading,
    error,
    fetchTemplates,
    fetchTemplate,
    createTemplate,
    updateTemplate,
    deactivateTemplate,
    clearCurrent,
  };
});
