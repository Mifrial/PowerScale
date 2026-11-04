import { describe, it, expect } from 'vitest';
import { fileQueryFromGridService } from '@/modules/Core/File/Service/Instance/fileQueryFromGridService';
import type { GridQuery } from '@/modules/Core/UI/Dto/Grid/GridQuery';

function gridQuery(filters: GridQuery['filters']): GridQuery {
  return { page: 1, perPage: 10, sort: null, filters };
}

describe('FileQueryFromGridService', () => {
  it('кладёт limit/offset и не кладёт sort', () => {
    const query: GridQuery = {
      page: 3,
      perPage: 25,
      sort: { key: 'createdAt', order: 'desc' },
      filters: {},
    };

    expect(fileQueryFromGridService.toQuery(query)).toEqual({ limit: 25, offset: 50 });
  });

  it('мапит mime, ownerId и isArchived', () => {
    expect(
      fileQueryFromGridService.toQuery(
        gridQuery({
          mime: 'image/png',
          ownerId: { mode: 'equals', value: 7 },
          isArchived: true,
        }),
      ),
    ).toEqual({ limit: 10, offset: 0, mime: 'image/png', ownerId: 7, isArchived: true });
  });

  it('не кладёт пустые фильтры', () => {
    expect(
      fileQueryFromGridService.toQuery(
        gridQuery({
          mime: { mode: 'equals', value: '  ' },
          ownerId: { mode: 'from', from: 3 },
        }),
      ),
    ).toEqual({ limit: 10, offset: 0 });
  });

  it('equals datetime — минута [start, start+59]', () => {
    const mapped = fileQueryFromGridService.toQuery(
      gridQuery({ createdAt: { mode: 'equals', value: '2026-01-15T12:30:00' } }),
    );
    const start = Math.floor(new Date(2026, 0, 15, 12, 30, 0).getTime() / 1000);
    expect(mapped.from).toBe(start);
    expect(mapped.to).toBe(start + 59);
  });

  it('interval — обе границы', () => {
    const mapped = fileQueryFromGridService.toQuery(
      gridQuery({
        createdAt: { mode: 'interval', from: '2026-01-15T00:00:00', to: '2026-01-15T23:59:00' },
      }),
    );
    expect(mapped.from).toBe(Math.floor(new Date(2026, 0, 15, 0, 0, 0).getTime() / 1000));
    expect(mapped.to).toBe(Math.floor(new Date(2026, 0, 15, 23, 59, 0).getTime() / 1000));
  });
});
