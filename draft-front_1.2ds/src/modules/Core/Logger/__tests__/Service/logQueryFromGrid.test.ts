import { describe, it, expect } from 'vitest';
import { logQueryFromGridService } from '@/modules/Core/Logger/Service/Instance/logQueryFromGridService';
import type { GridQuery } from '@/modules/Core/UI/Dto/Grid/GridQuery';

describe('LogQueryFromGridService', () => {
  it('кладёт limit/offset и не кладёт sort', () => {
    const query: GridQuery = {
      page: 2,
      perPage: 25,
      sort: { key: 'createdAt', order: 'asc' },
      filters: {},
    };

    expect(logQueryFromGridService.toQuery(query)).toEqual({
      limit: 25,
      offset: 25,
    });
  });

  it('мапит level, sourceMode и errorCodeMode', () => {
    const query: GridQuery = {
      page: 1,
      perPage: 10,
      sort: null,
      filters: {
        level: 'error',
        source: { mode: 'contains', value: '  mail  ' },
        errorCode: { mode: 'equals', value: 'INTERNAL' },
      },
    };

    expect(logQueryFromGridService.toQuery(query)).toEqual({
      limit: 10,
      offset: 0,
      level: 'error',
      source: 'mail',
      sourceMode: 'contains',
      errorCode: 'INTERNAL',
      errorCodeMode: 'equals',
    });
  });

  it('equals datetime — минута [start, start+59]', () => {
    const query: GridQuery = {
      page: 1,
      perPage: 10,
      sort: null,
      filters: {
        createdAt: { mode: 'equals', value: '2026-01-15T12:30:00' },
      },
    };
    const mapped = logQueryFromGridService.toQuery(query);
    const start = Math.floor(new Date(2026, 0, 15, 12, 30, 0).getTime() / 1000);
    expect(mapped.from).toBe(start);
    expect(mapped.to).toBe(start + 59);
  });

  it('mode from — только from', () => {
    const query: GridQuery = {
      page: 1,
      perPage: 10,
      sort: null,
      filters: {
        createdAt: { mode: 'from', from: '2026-01-15T00:00:00' },
      },
    };
    const mapped = logQueryFromGridService.toQuery(query);
    expect(mapped.from).toBe(Math.floor(new Date(2026, 0, 15, 0, 0, 0).getTime() / 1000));
    expect(mapped.to).toBeUndefined();
  });

  it('mode to — только to', () => {
    const query: GridQuery = {
      page: 1,
      perPage: 10,
      sort: null,
      filters: {
        createdAt: { mode: 'to', to: '2026-01-15T23:59:00' },
      },
    };
    const mapped = logQueryFromGridService.toQuery(query);
    expect(mapped.to).toBe(Math.floor(new Date(2026, 0, 15, 23, 59, 0).getTime() / 1000));
    expect(mapped.from).toBeUndefined();
  });

  it('interval с обеими границами', () => {
    const query: GridQuery = {
      page: 1,
      perPage: 10,
      sort: null,
      filters: {
        createdAt: { mode: 'interval', from: '2026-01-15T00:00:00', to: '2026-01-15T23:59:00' },
      },
    };
    const mapped = logQueryFromGridService.toQuery(query);
    expect(mapped.from).toBe(Math.floor(new Date(2026, 0, 15, 0, 0, 0).getTime() / 1000));
    expect(mapped.to).toBe(Math.floor(new Date(2026, 0, 15, 23, 59, 0).getTime() / 1000));
  });

  it('interval datetime без верхней границы — только from', () => {
    const query: GridQuery = {
      page: 1,
      perPage: 10,
      sort: null,
      filters: {
        createdAt: { mode: 'interval', from: '2026-01-15T00:00:00' },
      },
    };
    const mapped = logQueryFromGridService.toQuery(query);
    expect(mapped.from).toBe(Math.floor(new Date(2026, 0, 15, 0, 0, 0).getTime() / 1000));
    expect(mapped.to).toBeUndefined();
  });
});
