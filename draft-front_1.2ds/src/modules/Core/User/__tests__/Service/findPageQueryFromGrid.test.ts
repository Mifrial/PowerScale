import { describe, it, expect } from 'vitest';
import { findPageQueryFromGridService } from '@/modules/Core/User/Service/Instance/findPageQueryFromGridService';
import type { GridQuery } from '@/modules/Core/UI/Dto/Grid/GridQuery';

describe('FindPageQueryFromGridService', () => {
  it('кладёт limit/offset из page и perPage', () => {
    const query: GridQuery = {
      page: 3,
      perPage: 25,
      sort: { key: 'name', order: 'desc' },
      filters: {},
    };

    expect(findPageQueryFromGridService.toQuery(query)).toEqual({
      limit: 25,
      offset: 50,
      q: undefined,
      active: undefined,
    });
  });

  it('берёт q и active из FilterBar, sort в HTTP не кладёт', () => {
    const query: GridQuery = {
      page: 1,
      perPage: 10,
      sort: { key: 'login', order: 'asc' },
      filters: {
        q: { mode: 'contains', value: '  anna  ' },
        active: false,
      },
    };

    expect(findPageQueryFromGridService.toQuery(query)).toEqual({
      limit: 10,
      offset: 0,
      q: 'anna',
      active: false,
    });
  });
});
