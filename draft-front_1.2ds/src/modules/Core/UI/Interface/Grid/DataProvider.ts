import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';
import type { Sort } from '@/modules/Core/UI/Dto/Grid/Sort';
import type { Pagination } from '@/modules/Core/UI/Dto/Grid/Pagination';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';

export interface DataProvider {
  getColumns(): ColumnDefinition[];
  getRows(): Record<string, unknown>[];
  getTotalCount(): number;
  setSort(sort: Sort): void;
  setFilter(filter: Record<string, FilterValue>): void;
  setPagination(pagination: Pagination): void;
}
