import { describe, it, expect, vi } from 'vitest';
import { AuthApi } from '@/modules/Core/Auth/Service/AuthApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';

function authApiWithRun(runAction: ReturnType<typeof vi.fn>): AuthApi {
  return new AuthApi({ runAction } as unknown as Engine);
}

describe('AuthApi guest and getCurrentUser', () => {
  it('guest зовёт auth.guest', async () => {
    const runAction = vi.fn().mockResolvedValue({ success: true, data: { kind: 'guest' } });
    await authApiWithRun(runAction).guest();
    expect(runAction).toHaveBeenCalledWith('auth.guest');
  });

  it('getCurrentUser читает envelope guest', async () => {
    const runAction = vi.fn().mockResolvedValue({ success: true, data: { kind: 'guest' } });
    await expect(authApiWithRun(runAction).getCurrentUser()).resolves.toEqual({ kind: 'guest' });
  });

  it('getCurrentUser читает envelope user', async () => {
    const user = { id: 1, login: 'alice' };
    const runAction = vi.fn().mockResolvedValue({ success: true, data: { kind: 'user', user } });
    await expect(authApiWithRun(runAction).getCurrentUser()).resolves.toEqual({ kind: 'user', user });
  });

  it('getCurrentUser null без data', async () => {
    const runAction = vi.fn().mockResolvedValue({ success: true, data: null });
    await expect(authApiWithRun(runAction).getCurrentUser()).resolves.toBeNull();
  });
});
