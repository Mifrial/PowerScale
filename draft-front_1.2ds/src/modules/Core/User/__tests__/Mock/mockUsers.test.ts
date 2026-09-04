import { describe, it, expect } from 'vitest';
import { mockDeactivateUser, mockFindPage, mockGetUser } from '@/modules/Core/User/Mock/mockUsers';

describe('mockDeactivateUser', () => {
  it('сохраняет причину и дату на пользователе', async () => {
    await mockDeactivateUser(8, 'Нарушение правил', '2026-09-01');
    const user = await mockGetUser(8);
    expect(user.active).toBe(false);
    expect(user.deactivateReason).toBe('Нарушение правил');
    expect(user.deactivatedUntil).toBe(Math.floor(Date.parse('2026-09-01T00:00:00Z') / 1000));
  });

  it('деактивация без причины и даты оставляет поля пустыми', async () => {
    await mockDeactivateUser(1);
    const user = await mockGetUser(1);
    expect(user.active).toBe(false);
    expect(user.deactivateReason).toBeNull();
    expect(user.deactivatedUntil).toBeNull();
  });

  it('findPage и get не отдают lastLogin', async () => {
    const page = await mockFindPage({ limit: 50, offset: 0 });
    expect(page.items.every((item) => !('lastLogin' in item))).toBe(true);
    const user = await mockGetUser(2);
    expect(user).not.toHaveProperty('lastLogin');
    expect(user.bypass).toBe(true);
    expect(user.email).not.toBe('');
  });
});
