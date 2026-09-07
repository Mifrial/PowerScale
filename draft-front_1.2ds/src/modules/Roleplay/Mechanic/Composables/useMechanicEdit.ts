import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMechanicStore } from '@/modules/Roleplay/Mechanic/Store/mechanics';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';

function isAbort(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}

/**
 * Форма создания/редактирования механики: загрузка и сохранение (F17).
 */
export function useMechanicEdit() {
  const route = useRoute();
  const router = useRouter();
  const store = useMechanicStore();
  const { signal } = useAbortable();

  const isEdit = computed(() => !!route.params.id);
  const mechanicId = computed(() => Number(route.params.id));

  const code = ref('');
  const version = ref('');
  const name = ref('');
  const description = ref('');
  const loading = ref(false);
  const loadError = ref<string | null>(null);
  const saving = ref(false);
  const saveError = ref<string | null>(null);

  async function load(): Promise<void> {
    if (!isEdit.value) return;
    loading.value = true;
    loadError.value = null;
    try {
      const mechanic = await store.fetchMechanic(mechanicId.value, signal.value);
      code.value = mechanic.code;
      version.value = mechanic.version;
      name.value = mechanic.name;
      description.value = mechanic.description;
    } catch (error) {
      if (isAbort(error)) return;
      loadError.value = 'Не удалось загрузить механику';
    } finally {
      loading.value = false;
    }
  }

  async function save(): Promise<void> {
    if (!code.value.trim() || !name.value.trim() || !version.value.trim()) return;
    saving.value = true;
    saveError.value = null;
    try {
      if (isEdit.value) {
        await store.updateMechanic(
          mechanicId.value,
          { name: name.value, description: description.value },
          signal.value,
        );
      } else {
        await store.createMechanic(
          {
            code: code.value,
            name: name.value,
            version: version.value,
            description: description.value,
          },
          signal.value,
        );
      }
      await router.push('/admin/mechanics');
    } catch (error) {
      if (isAbort(error)) return;
      saveError.value = 'Не удалось сохранить механику';
    } finally {
      saving.value = false;
    }
  }

  return {
    isEdit,
    code,
    version,
    name,
    description,
    loading,
    loadError,
    saving,
    saveError,
    load,
    save,
  };
}
