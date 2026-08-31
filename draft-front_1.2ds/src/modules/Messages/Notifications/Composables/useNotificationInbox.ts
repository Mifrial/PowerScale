import { computed } from 'vue';
import { useNotificationStore } from '@/modules/Messages/Notifications/Store/notifications';

export function useNotificationInbox() {
  const store = useNotificationStore();

  return {
    items: computed(() => store.items),
    fetchData: () => store.fetchData(),
    markAsRead: (id: number) => store.markAsRead(id),
  };
}
