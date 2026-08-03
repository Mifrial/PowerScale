import { describe, it, expect } from 'vitest';
import { mockDeactivateUser, mockGetUser } from '@/modules/Core/User/Mock/mockUsers';

describe('mockDeactivateUser', () => {
  it('сохраняет причину и дату на пользователе', async () => {
    await mockDeactivateUser(8, 'Нарушение правил', '2026-09-01');
    const user = await mockGetUser(8);
    expect(user.active).toBe(false);
    expect(user.deactivate_reason).toBe('Нарушение правил');
    expect(user.deactivated_until).toBe('2026-09-01');
  });

  it('деактивация без причины и даты оставляет поля пустыми', async () => {
    await mockDeactivateUser(1);
    const user = await mockGetUser(1);
    expect(user.active).toBe(false);
    expect(user.deactivate_reason).toBeUndefined();
    expect(user.deactivated_until).toBeUndefined();
  });
});
