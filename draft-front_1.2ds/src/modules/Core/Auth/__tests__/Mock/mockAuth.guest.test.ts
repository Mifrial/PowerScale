import { describe, it, expect, beforeEach, vi } from 'vitest';

vi.mock('@/modules/Core/Engine/Mock/abortableDelay', () => ({
  abortableDelay: async () => undefined,
}));

describe('mockAuth guest sessionStorage', () => {
  beforeEach(() => {
    sessionStorage.clear();
    vi.resetModules();
  });

  it('переживает reload как guest', async () => {
    const first = await import('@/modules/Core/Auth/Mock/mockAuth');
    await first.mockGuest();
    expect(await first.mockGetCurrentUser()).toEqual({ kind: 'guest' });
    vi.resetModules();
    const second = await import('@/modules/Core/Auth/Mock/mockAuth');
    expect(await second.mockGetCurrentUser()).toEqual({ kind: 'guest' });
  });

  it('не сбивает user-сессию', async () => {
    const auth = await import('@/modules/Core/Auth/Mock/mockAuth');
    await auth.mockLogin('admin', 'test');
    await expect(auth.mockGuest()).rejects.toThrow('Authentication failed');
    const current = await auth.mockGetCurrentUser();
    expect(current?.kind).toBe('user');
  });
});
