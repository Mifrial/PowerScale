import { computed, ref } from 'vue'
import type { Sort } from '@/modules/Core/UI/Dto/Sort'
import type { Pagination } from '@/modules/Core/UI/Dto/Pagination'
import type { Row } from '@/modules/Core/UI/Dto/Row'
import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue'

export function useGridPage(getItems: () => Row[]) {
  const sort = ref<Sort | null>(null)
  const pagination = ref<Pagination>({ page: 1, perPage: 10 })
  const appliedFilters = ref<Record<string, FilterValue>>({})

  const sortedRows = computed(() => {
    const s = sort.value
    if (!s) return getItems()
    const arr = [...getItems()]
    arr.sort((a, b) => {
      const va = a[s.key as keyof Row]
      const vb = b[s.key as keyof Row]
      if (va == null) return 1
      if (vb == null) return -1
      const dir = s.order === 'asc' ? 1 : -1
      if (typeof va === 'string') return dir * va.localeCompare(String(vb))
      if (typeof va === 'number') return dir * (va - Number(vb))
      return dir * String(va).localeCompare(String(vb))
    })
    return arr
  })

  const pageRows = computed(() => {
    const p = pagination.value
    const start = (p.page - 1) * p.perPage
    return sortedRows.value.slice(start, start + p.perPage)
  })

  function onSortChange(s: Sort | null) {
    sort.value = s
  }

  function onPaginationChange(p: Pagination) {
    pagination.value = p
  }

  function onFilterChange(filters: Record<string, FilterValue>) {
    appliedFilters.value = filters
    pagination.value.page = 1
  }

  return {
    sort,
    pagination,
    appliedFilters,
    sortedRows,
    pageRows,
    onSortChange,
    onPaginationChange,
    onFilterChange,
  }
}
