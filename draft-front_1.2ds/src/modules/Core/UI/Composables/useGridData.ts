import { computed, onUnmounted, ref, watch } from 'vue';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';
import type { GridDataOptions } from '@/modules/Core/UI/Dto/Grid/GridDataOptions';
import type { GridPage } from '@/modules/Core/UI/Dto/Grid/GridPage';
import type { GridQuery } from '@/modules/Core/UI/Dto/Grid/GridQuery';
import type { Pagination } from '@/modules/Core/UI/Dto/Grid/Pagination';
import type { Sort } from '@/modules/Core/UI/Dto/Grid/Sort';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import { fieldTypeRegistry } from '@/modules/Core/UI/Service/Instance/fieldTypeRegistry';
import { baseFieldTypeInterpreter } from '@/modules/Core/UI/Service/Instance/baseFieldTypeInterpreter';

const LOAD_FAILED = 'Не удалось загрузить страницу';

function isAbortError(caught: unknown): boolean {
  return caught instanceof DOMException && caught.name === 'AbortError';
}

function errorMessage(caught: unknown): string {
  return caught instanceof Error && caught.message ? caught.message : LOAD_FAILED;
}

function queryKey(query: GridQuery): string {
  return JSON.stringify(query);
}

function sortRows<T extends Record<string, unknown>>(rows: T[], sort: Sort | null, columns: ColumnDefinition[]): T[] {
  if (!sort) return rows;
  const column = columns.find((entry) => entry.key === sort.key);
  const interpreter = column ? (fieldTypeRegistry.get(column.type)?.interpreter ?? baseFieldTypeInterpreter) : null;
  const copy = [...rows];
  copy.sort((left, right) => {
    const cmp =
      interpreter && column
        ? interpreter.compare(column, left[sort.key], right[sort.key])
        : String(left[sort.key] ?? '').localeCompare(String(right[sort.key] ?? ''));

    return sort.order === 'asc' ? cmp : -cmp;
  });

  return copy;
}

function useClientGrid<T extends Record<string, unknown>>(options: Extract<GridDataOptions<T>, { mode: 'client' }>) {
  const sort = ref<Sort | null>(null);
  const pagination = ref<Pagination>({ page: 1, perPage: 10 });
  const { appliedFilters, filteredRows, onFilterChange } = useFilteredRows<T>({
    getItems: options.getItems,
    fields: options.fields,
    searchFields: options.searchFields,
  });

  const rows = computed<T[]>(() => {
    const sorted = sortRows(filteredRows.value, sort.value, options.columns);
    const start = (pagination.value.page - 1) * pagination.value.perPage;

    return sorted.slice(start, start + pagination.value.perPage);
  });

  const total = computed(() => filteredRows.value.length);
  const loading = computed(() => false);
  const error = computed(() => null);

  function onSortChange(next: Sort | null): void {
    sort.value = next;
    pagination.value = { ...pagination.value, page: 1 };
  }

  function onPaginationChange(next: Pagination): void {
    pagination.value = next;
  }

  function handleFilterChange(next: Record<string, FilterValue>): void {
    onFilterChange(next);
    pagination.value = { ...pagination.value, page: 1 };
  }

  return {
    rows,
    total,
    pagination,
    sort,
    filters: appliedFilters,
    loading,
    error,
    reload: (): void => undefined,
    onPaginationChange,
    onSortChange,
    onFilterChange: handleFilterChange,
  };
}

function useServerGrid<T extends Record<string, unknown>>(options: Extract<GridDataOptions<T>, { mode: 'server' }>) {
  const sort = ref<Sort | null>(null);
  const pagination = ref<Pagination>({ page: 1, perPage: 10 });
  const filters = ref<Record<string, FilterValue>>({});
  const rows = ref<T[]>([]);
  const total = ref(0);
  const loading = ref(false);
  const error = ref<string | null>(null);
  let generation = 0;
  let controller: AbortController | null = null;

  const query = computed<GridQuery>(() => ({
    page: pagination.value.page,
    perPage: pagination.value.perPage,
    sort: sort.value,
    filters: filters.value,
  }));

  async function fetchPage(): Promise<void> {
    controller?.abort();
    generation += 1;
    const currentGeneration = generation;
    const currentController = new AbortController();
    controller = currentController;
    loading.value = true;
    error.value = null;
    try {
      const page: GridPage<T> = await options.loadPage(query.value, currentController.signal);
      if (currentGeneration !== generation) return;
      rows.value = page.rows;
      total.value = page.total;
    } catch (caught: unknown) {
      if (currentGeneration !== generation) return;
      if (isAbortError(caught) || currentController.signal.aborted) return;
      error.value = errorMessage(caught);
    } finally {
      if (currentGeneration === generation) {
        loading.value = false;
      }
    }
  }

  watch(
    () => queryKey(query.value),
    () => {
      void fetchPage();
    },
    { immediate: true },
  );

  onUnmounted(() => {
    generation += 1;
    controller?.abort();
  });

  function onSortChange(next: Sort | null): void {
    sort.value = next;
    pagination.value = { ...pagination.value, page: 1 };
  }

  function onPaginationChange(next: Pagination): void {
    pagination.value = next;
  }

  function onFilterChange(next: Record<string, FilterValue>): void {
    filters.value = next;
    pagination.value = { ...pagination.value, page: 1 };
  }

  return {
    rows,
    total,
    pagination,
    sort,
    filters,
    loading,
    error,
    reload: (): void => {
      void fetchPage();
    },
    onPaginationChange,
    onSortChange,
    onFilterChange,
  };
}

export function useGridData<T extends Record<string, unknown>>(options: GridDataOptions<T>) {
  if (options.mode === 'client') {
    return useClientGrid(options);
  }

  return useServerGrid(options);
}
