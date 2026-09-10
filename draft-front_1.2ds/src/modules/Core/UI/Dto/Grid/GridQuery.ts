import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import type { Sort } from '@/modules/Core/UI/Dto/Grid/Sort';

export interface GridQuery {
  page: number;
  perPage: number;
  sort: Sort | null;
  filters: Record<string, FilterValue>;
}
