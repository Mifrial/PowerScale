import { ref } from 'vue';
import { getUserApi } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';

/**
 * Страница учёток для выбора собеседника / членов группы.
 */
export function useChatUserPicker() {
  const users = ref<User[]>([]);
  const loading = ref(false);
  const error = ref('');

  async function load(query?: string) {
    loading.value = true;
    error.value = '';
    try {
      const page = await getUserApi().findPage({
        limit: 50,
        offset: 0,
        q: query?.trim() || undefined,
      });
      users.value = page.items;
    } catch (caught) {
      error.value = caught instanceof Error ? caught.message : 'Не удалось загрузить пользователей';
    } finally {
      loading.value = false;
    }
  }

  return { users, loading, error, load };
}
