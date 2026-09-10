import { describe, it, expect } from 'vitest';
import { mockFindPage } from '@/modules/Core/User/Mock/mockGroups';

describe('mock group findPage', () => {
  it('не сортирует страницу локально — порядок каталога (id)', async () => {
    const page = await mockFindPage({ limit: 3, offset: 0 });
    expect(page.items.map((item) => item.id)).toEqual([1, 2, 3]);
    const byName = [...page.items].sort((left, right) => left.name.localeCompare(right.name));
    expect(page.items.map((item) => item.id)).not.toEqual(byName.map((item) => item.id));
  });
});
