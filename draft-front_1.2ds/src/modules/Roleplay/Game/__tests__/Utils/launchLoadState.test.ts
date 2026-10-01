import { describe, expect, it } from 'vitest';
import { resolveLaunchLoad } from '@/modules/Roleplay/Game/Utils/launchLoadState';

describe('resolveLaunchLoad', () => {
  it('returns error when any slice rejects and does not substitute an empty collection', () => {
    const outcome = resolveLaunchLoad({
      overlays: { ok: false, cause: new Error('overlay down') },
      pending: { ok: true, value: [] as string[] },
    });

    expect(outcome.status).toBe('error');
    expect(outcome).not.toHaveProperty('values');
    if (outcome.status === 'error') expect(outcome.message).toBe('overlay down');
  });

  it('keeps a successful empty collection', () => {
    const outcome = resolveLaunchLoad({
      overlays: { ok: true, value: [] as string[] },
      sessions: { ok: true, value: {} as Record<string, never> },
    });

    expect(outcome).toEqual({
      status: 'ready',
      values: { overlays: [], sessions: {} },
    });
  });

  it('returns ready data on a later call after a rejected call', () => {
    const failed = resolveLaunchLoad({
      overlays: { ok: false, cause: new Error('down') },
    });
    const retried = resolveLaunchLoad({
      overlays: { ok: true, value: [{ entityKey: 'character:1' }] },
    });

    expect(failed.status).toBe('error');
    expect(retried).toEqual({
      status: 'ready',
      values: { overlays: [{ entityKey: 'character:1' }] },
    });
  });

  it('treats a missing speed slice as success and a rejected speed slice as error', () => {
    const withoutSpeed = resolveLaunchLoad({
      overlays: { ok: true, value: [] as string[] },
    });
    const rejectedSpeed = resolveLaunchLoad({
      overlays: { ok: true, value: [] as string[] },
      speed: { ok: false, cause: new Error('speed down') },
    });

    expect(withoutSpeed.status).toBe('ready');
    expect(rejectedSpeed.status).toBe('error');
    if (rejectedSpeed.status === 'error') expect(rejectedSpeed.message).toBe('speed down');
  });
});
