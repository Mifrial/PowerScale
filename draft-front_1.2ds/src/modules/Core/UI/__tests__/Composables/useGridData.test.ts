import { effectScope, nextTick, reactive } from 'vue';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { useGridData } from '@/modules/Core/UI/Composables/useGridData';
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { GridDataOptions } from '@/modules/Core/UI/Dto/Grid/GridDataOptions';
import type { GridPage } from '@/modules/Core/UI/Dto/Grid/GridPage';
import type { GridQuery } from '@/modules/Core/UI/Dto/Grid/GridQuery';
import { fieldTypeRegistry } from '@/modules/Core/UI/Service/Instance/fieldTypeRegistry';
import { baseFieldTypeInterpreter } from '@/modules/Core/UI/Service/Instance/baseFieldTypeInterpreter';

interface Row extends Record<string, unknown> {
  id: number;
  name: string;
}

const columns: ColumnDefinition[] = [{ key: 'name', label: 'Имя', type: 'string' }];
const fields: FilterField[] = [{ key: 'name', label: 'Имя', type: 'string' }];

function runSetup<T>(factory: () => T): { api: T; stop: () => void } {
  const scope = effectScope(true);
  const api = scope.run(factory);
  if (api === undefined) {
    throw new Error('effectScope returned undefined');
  }

  return { api, stop: () => scope.stop() };
}

describe('useGridData client', () => {
  beforeAll(() => {
    fieldTypeRegistry.register('string', { interpreter: baseFieldTypeInterpreter });
  });

  it('фильтрует, сортирует и режет страницу локально', () => {
    const items: Row[] = [
      { id: 1, name: 'Anna' },
      { id: 2, name: 'Ann' },
      { id: 3, name: 'Bob' },
      { id: 4, name: 'Zed' },
    ];
    const { api, stop } = runSetup(() =>
      useGridData({
        mode: 'client',
        getItems: () => items,
        fields,
        columns,
      }),
    );

    api.onFilterChange({ name: { mode: 'contains', value: 'nn' } });
    api.onSortChange({ key: 'name', order: 'asc' });
    api.pagination.value = { page: 1, perPage: 1 };

    expect(api.total.value).toBe(2);
    expect(api.rows.value.map((row) => row.name)).toEqual(['Ann']);

    api.onPaginationChange({ page: 2, perPage: 1 });
    expect(api.rows.value.map((row) => row.name)).toEqual(['Anna']);
    stop();
  });

  it('подхватывает смену массива getItems', () => {
    const source = reactive({ items: [{ id: 1, name: 'Alice' }] });
    const { api, stop } = runSetup(() =>
      useGridData({
        mode: 'client',
        getItems: () => source.items,
        fields,
        columns,
      }),
    );

    expect(api.total.value).toBe(1);
    source.items = [...source.items, { id: 2, name: 'Bob' }];
    expect(api.total.value).toBe(2);
    stop();
  });
});

describe('useGridData server', () => {
  async function flush(): Promise<void> {
    await Promise.resolve();
    await nextTick();
    await Promise.resolve();
  }

  it('грузит первую страницу и сбрасывает page при фильтре', async () => {
    const loadPage = vi.fn(async (query: GridQuery): Promise<GridPage<Row>> => ({
      rows: query.page === 1 ? [{ id: 1, name: 'a' }] : [{ id: 2, name: 'b' }],
      total: 2,
    }));
    const options: GridDataOptions<Row> = { mode: 'server', loadPage };
    const { api, stop } = runSetup(() => useGridData(options));
    await flush();

    expect(loadPage).toHaveBeenCalledTimes(1);
    expect(loadPage.mock.calls[0]?.[0]).toMatchObject({ page: 1, perPage: 10, sort: null, filters: {} });
    expect(api.rows.value).toEqual([{ id: 1, name: 'a' }]);
    expect(api.total.value).toBe(2);

    api.onPaginationChange({ page: 2, perPage: 10 });
    await flush();
    expect(loadPage).toHaveBeenCalledTimes(2);

    api.onFilterChange({ name: { mode: 'equals', value: 'a' } });
    await flush();
    expect(api.pagination.value.page).toBe(1);
    expect(loadPage).toHaveBeenCalledTimes(3);
    expect(loadPage.mock.calls[2]?.[0]).toMatchObject({ page: 1 });
    stop();
  });

  it('склеивает sort и pagination page 1 в один запрос', async () => {
    const loadPage = vi.fn(async (): Promise<GridPage<Row>> => ({ rows: [], total: 0 }));
    const { api, stop } = runSetup(() => useGridData({ mode: 'server', loadPage }));
    await flush();
    loadPage.mockClear();

    api.onSortChange({ key: 'name', order: 'asc' });
    api.onPaginationChange({ page: 1, perPage: 10 });
    await flush();

    expect(loadPage).toHaveBeenCalledTimes(1);
    expect(loadPage).toHaveBeenLastCalledWith(
      expect.objectContaining({ sort: { key: 'name', order: 'asc' } }),
      expect.any(AbortSignal),
    );
    stop();
  });

  it('не refetch при новой стрелке loadPage без смены query', async () => {
    const first = vi.fn(async (): Promise<GridPage<Row>> => ({ rows: [], total: 0 }));
    const options: GridDataOptions<Row> = { mode: 'server', loadPage: first };
    const { api, stop } = runSetup(() => useGridData(options));
    await flush();
    expect(first).toHaveBeenCalledTimes(1);

    const second = vi.fn(async (): Promise<GridPage<Row>> => ({ rows: [{ id: 9, name: 'x' }], total: 1 }));
    if (options.mode === 'server') {
      options.loadPage = second;
    }
    await flush();
    expect(second).not.toHaveBeenCalled();
    expect(api.rows.value).toEqual([]);
    stop();
  });

  it('reload повторяет текущий query', async () => {
    const loadPage = vi.fn(async (): Promise<GridPage<Row>> => ({ rows: [], total: 0 }));
    const { api, stop } = runSetup(() => useGridData({ mode: 'server', loadPage }));
    await flush();
    api.reload();
    await flush();
    expect(loadPage).toHaveBeenCalledTimes(2);
    stop();
  });

  it('не пишет устаревший ответ и не ставит error на abort', async () => {
    let resolveFirst: (page: GridPage<Row>) => void = () => undefined;
    const loadPage = vi.fn((query: GridQuery) => {
      if (query.page === 1 && loadPage.mock.calls.length === 1) {
        return new Promise<GridPage<Row>>((resolve) => {
          resolveFirst = resolve;
        });
      }

      return Promise.resolve({ rows: [{ id: 2, name: 'second' }], total: 2 });
    });
    const { api, stop } = runSetup(() => useGridData({ mode: 'server', loadPage }));
    api.onPaginationChange({ page: 2, perPage: 10 });
    await flush();
    resolveFirst({ rows: [{ id: 1, name: 'first' }], total: 9 });
    await flush();

    expect(api.rows.value).toEqual([{ id: 2, name: 'second' }]);
    expect(api.total.value).toBe(2);
    expect(api.error.value).toBeNull();
    stop();
  });

  it('пишет error при reject и не сортирует rows', async () => {
    const loadPage = vi.fn(async (): Promise<GridPage<Row>> => {
      throw new Error('boom');
    });
    const { api, stop } = runSetup(() => useGridData({ mode: 'server', loadPage }));
    await flush();
    expect(api.error.value).toBe('boom');

    loadPage.mockImplementation(async () => ({
      rows: [
        { id: 2, name: 'b' },
        { id: 1, name: 'a' },
      ],
      total: 2,
    }));
    api.reload();
    await flush();
    expect(api.rows.value.map((row) => row.name)).toEqual(['b', 'a']);
    expect(api.error.value).toBeNull();
    stop();
  });

  it('pagination не меняет filters', async () => {
    const loadPage = vi.fn(async (): Promise<GridPage<Row>> => ({ rows: [], total: 0 }));
    const { api, stop } = runSetup(() => useGridData({ mode: 'server', loadPage }));
    await flush();
    api.onFilterChange({ q: 'x' });
    await flush();
    api.onPaginationChange({ page: 2, perPage: 10 });
    await flush();
    expect(api.filters.value).toEqual({ q: 'x' });
    expect(loadPage).toHaveBeenLastCalledWith(
      expect.objectContaining({ page: 2, filters: { q: 'x' } }),
      expect.any(AbortSignal),
    );
    stop();
  });
});
