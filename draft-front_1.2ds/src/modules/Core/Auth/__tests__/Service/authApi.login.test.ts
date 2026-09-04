import { describe, it, expect, vi } from 'vitest';
import { AuthApi } from '@/modules/Core/Auth/Service/AuthApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';

function authApiWithRun(runAction: ReturnType<typeof vi.fn>): AuthApi {
  return new AuthApi({ runAction } as unknown as Engine);
}

describe('AuthApi.login remember', () => {
  it('кладёт remember в payload auth.login', async () => {
    const runAction = vi.fn().mockResolvedValue({
      success: true,
      data: { user: { id: 1 } },
    });
    await authApiWithRun(runAction).login('admin', 'secret', true);
    expect(runAction).toHaveBeenCalledWith('auth.login', {
      loginOrEmail: 'admin',
      password: 'secret',
      remember: true,
    });
  });

  it('без третьего аргумента шлёт remember false', async () => {
    const runAction = vi.fn().mockResolvedValue({
      success: true,
      data: { user: { id: 1 } },
    });
    await authApiWithRun(runAction).login('admin', 'secret');
    expect(runAction.mock.calls[0][1]).toMatchObject({ remember: false });
  });
});
