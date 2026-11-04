import { describe, it, expect, beforeEach } from 'vitest';
import { mockFileApi, resetMockFileApi } from '@/modules/Core/File/Mock/mockFileApi';

beforeEach(() => {
  resetMockFileApi();
});

describe('mockFileApi', () => {
  it('findPage фильтрует архив и режет страницу', async () => {
    const active = await mockFileApi.findPage({ limit: 10, offset: 0, isArchived: false });
    expect(active.total).toBe(2);
    expect(active.items.every((item) => !item.isArchived)).toBe(true);

    const archived = await mockFileApi.findPage({ limit: 10, offset: 0, isArchived: true });
    expect(archived.items.map((item) => item.id)).toEqual([1]);

    const secondPage = await mockFileApi.findPage({ limit: 1, offset: 1 });
    expect(secondPage.items).toHaveLength(1);
    expect(secondPage.total).toBe(3);
  });

  it('get отдаёт запись и бросает на отсутствии', async () => {
    const record = await mockFileApi.get(2);
    expect(record.originalName).toBe('portrait.webp');
    await expect(mockFileApi.get(999)).rejects.toThrow('File is not found');
  });

  it('upload добавляет запись в начало и отдаёт publicId', async () => {
    const file = new File(['bytes'], 'new.png', { type: 'image/png' });
    const uploaded = await mockFileApi.upload(file);
    expect(uploaded.publicId).toHaveLength(32);
    expect(uploaded.originalName).toBe('new.png');
    expect(uploaded.mime).toBe('image/png');
    const page = await mockFileApi.findPage({ limit: 10, offset: 0 });
    expect(page.total).toBe(4);
    expect(page.items[0]?.publicId).toBe(uploaded.publicId);
  });

  it('getViewUrl стабилен и непуст', () => {
    const first = mockFileApi.getViewUrl('a'.repeat(32));
    const second = mockFileApi.getViewUrl('a'.repeat(32));
    expect(first).toBe(second);
    expect(first.length).toBeGreaterThan(0);
  });
});
