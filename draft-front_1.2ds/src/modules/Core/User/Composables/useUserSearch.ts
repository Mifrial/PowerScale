import { onScopeDispose, ref } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { displayName } from '@/modules/Core/User/Utils/displayName';

const SEARCH_LIMIT = 20;
const EMPTY_LIMIT = 10;
const SEARCH_DEBOUNCE_MS = 250;

/**
 * Серверный поиск пользователей для автокомплита: debounce, отмена устаревших запросов.
 */
export function useUserSearch() {
  const store = useUserStore();
  const options = ref<User[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);

  let sequence = 0;
  let controller: AbortController | undefined;
  let timer: ReturnType<typeof setTimeout> | undefined;

  function labelOf(user: User): string {
    return displayName(user.name, user.surname, user.login) || user.login;
  }

  function search(text: string): void {
    if (timer !== undefined) clearTimeout(timer);
    const q = text.trim();
    if (q === '') {
      void runSearch('');

      return;
    }

    timer = setTimeout(() => void runSearch(q), SEARCH_DEBOUNCE_MS);
  }

  async function runSearch(q: string): Promise<void> {
    const request = ++sequence;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;

    try {
      const page = await store.findPage(
        {
          limit: q === '' ? EMPTY_LIMIT : SEARCH_LIMIT,
          offset: 0,
          q: q === '' ? undefined : q,
          active: true,
        },
        controller.signal,
      );
      if (request !== sequence) return;
      options.value = page.items;
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      if (request !== sequence) return;
      error.value = 'Не удалось загрузить пользователей';
    } finally {
      if (request === sequence) loading.value = false;
    }
  }

  function loadInitial(): Promise<void> {
    return runSearch('');
  }

  async function loadById(id: number): Promise<void> {
    const known = store.users.find((user) => user.id === id);
    if (known) {
      options.value = [known, ...options.value.filter((candidate) => candidate.id !== id)];

      return;
    }
    if (options.value.some((user) => user.id === id)) return;

    try {
      const user = await store.getUser(id);
      store.rememberUsers([user]);
      options.value = [user, ...options.value.filter((candidate) => candidate.id !== id)];
    } catch {
      // Подпись останется в виде #id, пока каталог не догрузится другим запросом.
    }
  }

  onScopeDispose(() => {
    if (timer !== undefined) clearTimeout(timer);
    controller?.abort();
  });

  return { options, loading, error, labelOf, search, loadInitial, loadById };
}
