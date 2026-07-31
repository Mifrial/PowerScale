import type { ColumnDefinition } from './ColumnDefinition'
import type { Row } from './Row'
import type { Sort } from './Sort'
import type { Pagination } from './Pagination'

export interface DataProvider {
  getColumns(): ColumnDefinition[]
  getRows(): Row[]
  getTotalCount(): number
  setSort(sort: Sort): void
  setFilter(filter: Record<string, any>): void
  setPagination(pagination: Pagination): void
}
