import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import { useMechanicStore } from '@/modules/Roleplay/Mechanic/Store/mechanics';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useGridData } from '@/modules/Core/UI/Composables/useGridData';
import { columns } from '@/modules/Roleplay/Mechanic/Constant/Grid/mechanics/columns';
import { filterFields } from '@/modules/Roleplay/Mechanic/Constant/Grid/mechanics/filterFields';

/**
 * Список механик: загрузка каталога, грид, переход в карточку.
 */
export function useMechanicList() {
  const router = useRouter();
  const store = useMechanicStore();
  const { loading, error } = storeToRefs(store);
  const { signal } = useAbortable();

  const grid = useGridData({
    mode: 'client',
    getItems: () => store.mechanics,
    fields: filterFields,
    columns,
  });

  function load(): void {
    void store.fetchMechanics(signal.value);
  }

  function goCreate(): void {
    void router.push('/admin/mechanics/new');
  }

  function onRowAction(payload: { action: string; row: Record<string, unknown> }): void {
    if (payload.action !== 'open' && payload.action !== 'view-profile') return;
    const id = payload.row.id;
    if (typeof id !== 'number') return;
    void router.push(`/admin/mechanics/${id}/edit`);
  }

  return {
    loading,
    error,
    load,
    goCreate,
    onRowAction,
    sort: grid.sort,
    pagination: grid.pagination,
    filters: grid.filters,
    rows: grid.rows,
    total: grid.total,
    onSortChange: grid.onSortChange,
    onPaginationChange: grid.onPaginationChange,
    onFilterChange: grid.onFilterChange,
  };
}
