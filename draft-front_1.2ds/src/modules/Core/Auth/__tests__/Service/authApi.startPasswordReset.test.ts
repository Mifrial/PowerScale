import { describe, it, expect, vi } from 'vitest';
import { AuthApi } from '@/modules/Core/Auth/Service/AuthApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';

function authApiWithRun(runAction: ReturnType<typeof vi.fn>): AuthApi {
  return new AuthApi({ runAction } as unknown as Engine);
}

describe('AuthApi.startPasswordReset', () => {
  it('вызывает auth.startPasswordReset', async () => {
    const runAction = vi.fn().mockResolvedValue({
      success: true,
      data: { status: 'sent', login: 'alice' },
    });
    const result = await authApiWithRun(runAction).startPasswordReset('alice@x.test');
    expect(runAction).toHaveBeenCalledWith('auth.startPasswordReset', { loginOrEmail: 'alice@x.test' });
    expect(result.status).toBe('sent');
  });
});
