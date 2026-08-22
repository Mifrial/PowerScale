import { computed, ref } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';
import type { Sort } from '@/modules/Core/UI/Dto/Grid/Sort';
import type { Pagination } from '@/modules/Core/UI/Dto/Grid/Pagination';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import { fieldTypeRegistry } from '@/modules/Core/UI/Service/Instance/fieldTypeRegistry';
import { baseFieldTypeInterpreter } from '@/modules/Core/UI/Service/Instance/baseFieldTypeInterpreter';

export function useGridPage<T extends Record<string, unknown>>(options: {
  getItems: () => T[];
  fields: FilterField[];
  columns: ColumnDefinition[];
  searchFields?: string[];
}) {
  const { getItems, fields, columns, searchFields } = options;
  const sort = ref<Sort | null>(null);
  const pagination = ref<Pagination>({ page: 1, perPage: 10 });
  const { appliedFilters, filteredRows, onFilterChange } = useFilteredRows<T>({ getItems, fields, searchFields });

  const sortedRows = computed<T[]>(() => {
    const s = sort.value;
    if (!s) return filteredRows.value;
    const column = columns.find((c) => c.key === s.key);
    const interpreter = column ? (fieldTypeRegistry.get(column.type)?.interpreter ?? baseFieldTypeInterpreter) : null;
    const arr = [...filteredRows.value];

    if (interpreter && column) {
      arr.sort((a, b) => {
        const cmp = interpreter.compare(column, a[s.key], b[s.key]);

        return s.order === 'asc' ? cmp : -cmp;
      });
    } else {
      arr.sort((a, b) => {
        const cmp = String(a[s.key] ?? '').localeCompare(String(b[s.key] ?? ''));

        return s.order === 'asc' ? cmp : -cmp;
      });
    }

    return arr;
  });

  const pageRows = computed<T[]>(() => {
    const p = pagination.value;
    const start = (p.page - 1) * p.perPage;

    return sortedRows.value.slice(start, start + p.perPage);
  });

  const total = computed(() => filteredRows.value.length);

  function onSortChange(s: Sort | null) {
    sort.value = s;
  }

  function onPaginationChange(p: Pagination) {
    pagination.value = p;
  }

  function handleFilterChange(filters: Record<string, FilterValue>) {
    onFilterChange(filters);
    pagination.value.page = 1;
  }

  return {
    sort,
    pagination,
    appliedFilters,
    filteredRows,
    pageRows,
    total,
    onSortChange,
    onPaginationChange,
    onFilterChange: handleFilterChange,
  };
}
