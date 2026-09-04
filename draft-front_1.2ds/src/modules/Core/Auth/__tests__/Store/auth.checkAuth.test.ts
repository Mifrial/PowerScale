import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { registerAuthApi } from '@/modules/Core/Auth/init';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import type { IAuthApi } from '@/modules/Core/Auth/Interface/IAuthApi';

function stubAuthApi(overrides: Partial<IAuthApi>): IAuthApi {
  return {
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    guest: vi.fn(),
    getCurrentUser: vi.fn(),
    startPasswordReset: vi.fn(),
    finalPasswordReset: vi.fn(),
    setPassword: vi.fn(),
    getPasswordPolicy: vi.fn(),
    ...overrides,
  };
}

describe('auth store checkAuth / guestLogin', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('checkAuth guest возвращает true', async () => {
    registerAuthApi(stubAuthApi({ getCurrentUser: vi.fn().mockResolvedValue({ kind: 'guest' }) }));
    const auth = useAuthStore();
    await expect(auth.checkAuth()).resolves.toBe(true);
    expect(auth.isGuest).toBe(true);
    expect(auth.isAuthenticated).toBe(true);
  });

  it('guestLogin при ошибке API не ставит guest', async () => {
    registerAuthApi(stubAuthApi({ guest: vi.fn().mockRejectedValue(new Error('AUTH_INVALID')) }));
    const auth = useAuthStore();
    await expect(auth.guestLogin()).resolves.toBe(false);
    expect(auth.isGuest).toBe(false);
    expect(auth.isAuthenticated).toBe(false);
  });
});
