import type { ColumnDefinition } from '@/modules/Core/UI/Dto/ColumnDefinition'
import type { Row } from '@/modules/Core/UI/Dto/Row'
import type { Sort } from '@/modules/Core/UI/Dto/Sort'
import type { Pagination } from '@/modules/Core/UI/Dto/Pagination'
import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue'

export interface DataProvider {
  getColumns(): ColumnDefinition[]
  getRows(): Row[]
  getTotalCount(): number
  setSort(sort: Sort): void
  setFilter(filter: Record<string, FilterValue>): void
  setPagination(pagination: Pagination): void
}
