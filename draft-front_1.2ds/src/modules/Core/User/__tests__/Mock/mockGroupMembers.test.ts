import { describe, it, expect } from 'vitest';
import { getGroupMembers } from '@/modules/Core/User/Mock/mockGroups';

describe('mock getGroupMembers', () => {
  it('режет массив и отдаёт total без фильтра по группе', async () => {
    const first = await getGroupMembers(99, { limit: 1, offset: 0 });
    expect(first.total).toBe(2);
    expect(first.items).toHaveLength(1);
    expect(first.items[0].login).toBe('admin');
    const second = await getGroupMembers(99, { limit: 1, offset: 1 });
    expect(second.items[0].login).toBe('ivan');
  });
});
