import { describe, expect, it } from 'vitest';
import { SceneUnits } from '@/modules/Roleplay/Scene/Service/SceneUnits';

const units = new SceneUnits();

describe('SceneUnits', () => {
  it('converts whole ipari', () => {
    expect(units.toIpari(2560, 64)).toEqual({ base: 40, size: 0 });
    expect(units.formatUnits(2560, 64)).toBe('40');
  });

  it('keeps fractions exact for powers of two', () => {
    expect(units.toIpari(32, 64)).toEqual({ base: 1, size: -1 });
    expect(units.toIpari(16, 64)).toEqual({ base: 1, size: -2 });
    expect(units.toIpari(24, 64)).toEqual({ base: 3, size: -3 });
    expect(units.toIpari(100, 64)).toEqual({ base: 25, size: -4 });
  });

  it('formats and parses dimensional values', () => {
    expect(units.format({ base: 1, size: -1 })).toBe('{1|-1}');
    expect(units.parse('{3|-3}')).toEqual({ base: 3, size: -3 });
    expect(units.parse(' 40 ')).toEqual({ base: 40, size: 0 });
    expect(units.parse('{ 2 | 1 }')).toEqual({ base: 2, size: 1 });
    expect(units.parse('0.5')).toBeNull();
  });

  it('round-trips units through ipari', () => {
    for (const value of [0, 1, 16, 24, 32, 100, 2560]) {
      const ipari = units.toIpari(value, 64);

      expect(units.fromIpari(ipari, 64)).toBe(value);
    }
  });

  it('accepts only powers of two', () => {
    expect(units.isPowerOfTwo(64)).toBe(true);
    expect(units.isPowerOfTwo(1)).toBe(true);
    expect(units.isPowerOfTwo(100)).toBe(false);
    expect(units.isPowerOfTwo(0)).toBe(false);
  });
});
