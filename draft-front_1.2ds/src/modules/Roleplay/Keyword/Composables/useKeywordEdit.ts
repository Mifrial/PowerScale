import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useKeywordStore } from '@/modules/Roleplay/Keyword/Store/keywords';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';

function isAbort(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}

/**
 * Форма создания/редактирования признака: загрузка, сохранение, выключение (F17).
 */
export function useKeywordEdit() {
  const route = useRoute();
  const router = useRouter();
  const store = useKeywordStore();
  const { signal } = useAbortable();

  const isEdit = computed(() => !!route.params.id);
  const keywordId = computed(() => Number(route.params.id));

  const code = ref('');
  const name = ref('');
  const description = ref('');
  const active = ref(true);
  const loading = ref(false);
  const loadError = ref<string | null>(null);
  const saving = ref(false);
  const saveError = ref<string | null>(null);
  const deactivating = ref(false);
  const actionError = ref<string | null>(null);

  async function load(): Promise<void> {
    if (!isEdit.value) return;
    loading.value = true;
    loadError.value = null;
    try {
      const keyword = await store.fetchTag(keywordId.value, signal.value);
      code.value = keyword.code;
      name.value = keyword.name;
      description.value = keyword.description;
      active.value = keyword.active;
    } catch (error) {
      if (isAbort(error)) return;
      loadError.value = 'Не удалось загрузить признак';
    } finally {
      loading.value = false;
    }
  }

  async function save(): Promise<void> {
    if (!code.value.trim() || !name.value.trim()) return;
    saving.value = true;
    saveError.value = null;
    try {
      if (isEdit.value) {
        await store.updateTag(keywordId.value, { name: name.value, description: description.value }, signal.value);
      } else {
        await store.createTag({ code: code.value, name: name.value, description: description.value }, signal.value);
      }
      await router.push('/admin/keywords');
    } catch (error) {
      if (isAbort(error)) return;
      saveError.value = 'Не удалось сохранить признак';
    } finally {
      saving.value = false;
    }
  }

  async function deactivate(): Promise<void> {
    deactivating.value = true;
    actionError.value = null;
    try {
      await store.deactivateTag(keywordId.value, signal.value);
      await router.push('/admin/keywords');
    } catch (error) {
      if (isAbort(error)) return;
      actionError.value = 'Не удалось выключить признак';
    } finally {
      deactivating.value = false;
    }
  }

  return {
    isEdit,
    code,
    name,
    description,
    active,
    loading,
    loadError,
    saving,
    saveError,
    deactivating,
    actionError,
    load,
    save,
    deactivate,
  };
}
