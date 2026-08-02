import type { ColumnDefinition } from './ColumnDefinition'
import type { Row } from './Row'
import type { Sort } from './Sort'
import type { Pagination } from './Pagination'
import type { FilterValue } from './FilterValue'

export interface DataProvider {
  getColumns(): ColumnDefinition[]
  getRows(): Row[]
  getTotalCount(): number
  setSort(sort: Sort): void
  setFilter(filter: Record<string, FilterValue>): void
  setPagination(pagination: Pagination): void
}
