import { describe, it, expect } from 'vitest';
import { DateTime } from '@/modules/Core/Engine/Value/DateTime';

describe('DateTime.fromUnix', () => {
  it('собирает тот же instant, что ISO', () => {
    const unix = 1_704_067_200;
    const fromUnix = DateTime.fromUnix(unix);
    const fromIso = new DateTime(new Date(unix * 1000).toISOString());
    expect(fromUnix.formatTime()).toBe(fromIso.formatTime());
  });
});
