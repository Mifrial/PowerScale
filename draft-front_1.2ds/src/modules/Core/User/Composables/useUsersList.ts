import { useRouter } from 'vue-router';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useGridData } from '@/modules/Core/UI/Composables/useGridData';
import { findPageQueryFromGridService } from '@/modules/Core/User/Service/Instance/findPageQueryFromGridService';
import type { User } from '@/modules/Core/User/Dto/User';

export function useUsersList() {
  const router = useRouter();
  const store = useUserStore();

  const grid = useGridData<User & Record<string, unknown>>({
    mode: 'server',
    loadPage: async (query, signal) => {
      const page = await store.findPage(findPageQueryFromGridService.toQuery(query), signal);

      return { rows: page.items as Array<User & Record<string, unknown>>, total: page.total };
    },
  });

  function onRowAction(payload: { action: string; row: Record<string, unknown> }): void {
    const isOpen = payload.action === 'open' || payload.action === 'view-profile';
    if (isOpen && typeof payload.row.id === 'number') {
      void router.push(`/users/${payload.row.id}`);
    }
  }

  return {
    ...grid,
    onRowAction,
  };
}
