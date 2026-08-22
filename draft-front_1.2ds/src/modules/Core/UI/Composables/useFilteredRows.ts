import { computed, ref } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import { fieldTypeRegistry } from '@/modules/Core/UI/Service/Instance/fieldTypeRegistry';
import { baseFieldTypeInterpreter } from '@/modules/Core/UI/Service/Instance/baseFieldTypeInterpreter';

export function useFilteredRows<T extends Record<string, unknown>>(options: {
  getItems: () => T[];
  fields: FilterField[];
  searchFields?: string[];
}) {
  const { getItems, fields, searchFields = [] } = options;
  const appliedFilters = ref<Record<string, FilterValue>>({});

  const filteredRows = computed<T[]>(() => {
    const filters = appliedFilters.value;
    const q = typeof filters.q === 'string' ? filters.q.trim().toLowerCase() : '';
    let result = getItems();

    if (q && searchFields.length > 0) {
      result = result.filter((row) =>
        searchFields.some((key) =>
          String(row[key] ?? '')
            .toLowerCase()
            .includes(q),
        ),
      );
    }

    for (const field of fields) {
      const value = filters[field.key];
      if (value === undefined || value === null || value === '') continue;
      const interpreter = fieldTypeRegistry.get(field.type)?.interpreter ?? baseFieldTypeInterpreter;
      if (!interpreter.isActive(field, value)) continue;
      const predicate = interpreter.predicate(field, value);
      result = result.filter((row) => predicate(row[field.key]));
    }

    return result;
  });

  function onFilterChange(filters: Record<string, FilterValue>) {
    appliedFilters.value = filters;
  }

  return {
    appliedFilters,
    filteredRows,
    onFilterChange,
  };
}
