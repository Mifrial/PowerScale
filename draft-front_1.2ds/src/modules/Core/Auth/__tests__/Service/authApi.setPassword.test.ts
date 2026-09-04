import { describe, it, expect, vi } from 'vitest';
import { AuthApi } from '@/modules/Core/Auth/Service/AuthApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';

function authApiWithRun(runAction: ReturnType<typeof vi.fn>): AuthApi {
  return new AuthApi({ runAction } as unknown as Engine);
}

describe('AuthApi.setPassword', () => {
  it('кладёт userId, newPassword и currentPassword', async () => {
    const runAction = vi.fn().mockResolvedValue({ success: true, data: true });
    await authApiWithRun(runAction).setPassword(3, 'newpass', 'old');
    expect(runAction).toHaveBeenCalledWith('auth.setPassword', {
      userId: 3,
      newPassword: 'newpass',
      currentPassword: 'old',
    });
  });
});
