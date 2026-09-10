import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';
import type { GridQuery } from '@/modules/Core/UI/Dto/Grid/GridQuery';

export class FindPageQueryFromGridService {
  toQuery(query: GridQuery): FindPageQuery {
    const q = this.filterText(query.filters.q)?.trim();
    const active = this.filterBoolean(query.filters.active);

    return {
      limit: query.perPage,
      offset: (query.page - 1) * query.perPage,
      q: q || undefined,
      active,
    };
  }

  private filterText(value: FilterValue | undefined): string | undefined {
    if (typeof value === 'string') return value;
    if (value && typeof value === 'object' && 'value' in value && typeof value.value === 'string') {
      return value.value;
    }

    return undefined;
  }

  private filterBoolean(value: FilterValue | undefined): boolean | undefined {
    return typeof value === 'boolean' ? value : undefined;
  }
}
