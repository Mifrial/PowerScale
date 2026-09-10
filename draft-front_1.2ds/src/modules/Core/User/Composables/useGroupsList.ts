import { useRouter } from 'vue-router';
import { useGroupStore } from '@/modules/Core/User/Store/groups';
import { useGridData } from '@/modules/Core/UI/Composables/useGridData';
import { findPageQueryFromGridService } from '@/modules/Core/User/Service/Instance/findPageQueryFromGridService';
import type { Group } from '@/modules/Core/User/Dto/Group';

export function useGroupsList() {
  const router = useRouter();
  const store = useGroupStore();

  const grid = useGridData<Group & Record<string, unknown>>({
    mode: 'server',
    loadPage: async (query, signal) => {
      const page = await store.findPage(findPageQueryFromGridService.toQuery(query), signal);

      return { rows: page.items as Array<Group & Record<string, unknown>>, total: page.total };
    },
  });

  function goCreate(): void {
    void router.push('/admin/groups/new');
  }

  function onRowAction(payload: { action: string; row: Record<string, unknown> }): void {
    if (typeof payload.row.id !== 'number') return;
    if (payload.action === 'open' || payload.action === 'view-profile') {
      void router.push(`/admin/groups/${payload.row.id}`);
    }
  }

  return {
    ...grid,
    goCreate,
    onRowAction,
  };
}
