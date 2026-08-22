import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';
import type { Notification } from '@/modules/Messages/Notifications/Dto/Notification';
import type { NotifFilter } from '@/modules/Messages/Notifications/Enum/NotifFilter';
import { getNotificationApi } from '@/modules/Messages/Notifications/init';

export const useNotificationStore = defineStore('notifications', () => {
  const items = ref<Notification[]>([]);
  const total = ref(0);
  const unreadCount = ref(0);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const actionError = ref<string | null>(null);
  const filter = ref<NotifFilter>('all');
  const searchQuery = ref('');
  const page = ref(1);
  const pageSize = ref(6);

  // Серверная загрузка данных
  async function fetchData() {
    loading.value = true;
    error.value = null;
    try {
      const offset = (page.value - 1) * pageSize.value;
      const result = await getNotificationApi().fetchPage({
        filter: filter.value,
        search: searchQuery.value || undefined,
        offset,
        limit: pageSize.value,
      });
      items.value = result.items;
      total.value = result.total;
      unreadCount.value = result.unreadCount;
    } catch {
      error.value = 'Не удалось загрузить уведомления';
    } finally {
      loading.value = false;
    }
  }

  // При смене фильтра или поиска — сбрасываем страницу на первую и перезапрашиваем
  watch([filter, searchQuery], () => {
    if (page.value === 1) {
      fetchData();
    } else {
      page.value = 1;
    }
  });

  // При смене страницы — перезапрашиваем
  watch(page, () => {
    fetchData();
  });

  const totalPages = computed(() => Math.max(1, Math.ceil(total.value / pageSize.value)));

  async function markAsRead(id: number) {
    try {
      await getNotificationApi().markAsRead(id);
      actionError.value = null;
      const n = items.value.find((x) => x.id === id);
      if (n) {
        n.read = true;
        unreadCount.value = Math.max(0, unreadCount.value - 1);
      }
    } catch {
      actionError.value = 'Не удалось отметить уведомление';
    }
  }

  async function markAllAsRead() {
    try {
      await getNotificationApi().markAllAsRead();
      actionError.value = null;
      items.value.forEach((n) => {
        n.read = true;
      });
      unreadCount.value = 0;
    } catch {
      actionError.value = 'Не удалось отметить все уведомления';
    }
  }

  function setPage(p: number) {
    page.value = p;
  }

  function setFilter(f: NotifFilter) {
    filter.value = f;
  }

  return {
    items,
    total,
    unreadCount,
    loading,
    error,
    actionError,
    filter,
    searchQuery,
    totalPages,
    page,
    pageSize,
    fetchData,
    markAsRead,
    markAllAsRead,
    setPage,
    setFilter,
  };
});
