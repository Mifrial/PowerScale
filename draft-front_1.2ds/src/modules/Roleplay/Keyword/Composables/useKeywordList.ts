import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import { useKeywordStore } from '@/modules/Roleplay/Keyword/Store/keywords';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useGridPage } from '@/modules/Core/UI/Composables/useGridPage';
import { columns } from '@/modules/Roleplay/Keyword/Constant/Grid/keywords/columns';
import { filterFields } from '@/modules/Roleplay/Keyword/Constant/Grid/keywords/filterFields';

/**
 * Список признаков: загрузка каталога, грид, переход в карточку.
 */
export function useKeywordList() {
  const router = useRouter();
  const store = useKeywordStore();
  const { loading, error } = storeToRefs(store);
  const { signal } = useAbortable();

  const grid = useGridPage({
    getItems: () => store.keywords,
    fields: filterFields,
    columns,
  });

  function load(): void {
    void store.fetchTags(signal.value);
  }

  function goCreate(): void {
    void router.push('/admin/keywords/new');
  }

  function onRowAction(payload: { action: string; row: Record<string, unknown> }): void {
    if (payload.action !== 'open' && payload.action !== 'view-profile') return;
    const id = payload.row.id;
    if (typeof id !== 'number') return;
    void router.push(`/admin/keywords/${id}/edit`);
  }

  return {
    loading,
    error,
    load,
    goCreate,
    onRowAction,
    sort: grid.sort,
    pagination: grid.pagination,
    appliedFilters: grid.appliedFilters,
    pageRows: grid.pageRows,
    total: grid.total,
    onSortChange: grid.onSortChange,
    onPaginationChange: grid.onPaginationChange,
    onFilterChange: grid.onFilterChange,
  };
}
