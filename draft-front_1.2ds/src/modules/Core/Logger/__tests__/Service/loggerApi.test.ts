import { describe, it, expect } from 'vitest';
import { mockLoggerApi } from '@/modules/Core/Logger/Mock/mockLoggerApi';

describe('mock logger findPage', () => {
  it('режет страницу и фильтрует source contains', async () => {
    const page = await mockLoggerApi.findPage({ limit: 10, offset: 0, source: 'flush', sourceMode: 'contains' });
    expect(page.total).toBe(1);
    expect(page.items[0]?.source).toBe('mail.flush');
  });

  it('contains не раскрывает % как wildcard', async () => {
    const page = await mockLoggerApi.findPage({ limit: 10, offset: 0, source: 'a%b', sourceMode: 'contains' });
    expect(page.items.map((item) => item.id)).toEqual([1]);
  });

  it('get отдаёт ту же запись', async () => {
    const entry = await mockLoggerApi.get(3);
    expect(entry.errorCode).toBe('INTERNAL');
    expect(entry.context).toEqual({ file: 'Application.php', line: 123 });
  });
});
