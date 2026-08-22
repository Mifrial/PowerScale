import { describe, it, expect } from 'vitest';
import { CharacteristicNumber, CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

const cn = (base: number, size: number) => new CharacteristicNumber({ base, size });

describe('CHARACTERISTIC_BASE_RANGE', () => {
  it('границы 3–5', () => {
    expect(CHARACTERISTIC_BASE_RANGE).toEqual({ min: 3, max: 5 });
  });
});

describe('CharacteristicNumber.modifyWith', () => {
  it('{4|0} + 1 = {5|0}', () => {
    expect(cn(4, 0).modifyWith(1).value).toEqual({ base: 5, size: 0 });
  });
  it('{5|0} + 1 = {3|+1} (переполнение → размер)', () => {
    expect(cn(5, 0).modifyWith(1).value).toEqual({ base: 3, size: 1 });
  });
  it('{3|0} - 1 = {5|-1}', () => {
    expect(cn(3, 0).modifyWith(-1).value).toEqual({ base: 5, size: -1 });
  });
  it('{4|-1} + 4 = {5|0}', () => {
    expect(cn(4, -1).modifyWith(4).value).toEqual({ base: 5, size: 0 });
  });
});

describe('CharacteristicNumber.modifyDiffTo', () => {
  it('{5|0} от {4|-1} = 4 пункта (4 + 3·1 − 5)', () => {
    expect(cn(4, -1).modifyDiffTo(cn(5, 0))).toBe(-4);
    expect(cn(5, 0).modifyDiffTo(cn(4, -1))).toBe(4);
  });
  it('{3|+1} от {5|0} = 1 пункт', () => {
    expect(cn(5, 0).modifyDiffTo(cn(3, 1))).toBe(-1);
    expect(cn(3, 1).modifyDiffTo(cn(5, 0))).toBe(1);
  });
  it('обратна modifyWith (round-trip)', () => {
    const from = cn(4, 0);
    for (let delta = -7; delta <= 7; delta += 1) {
      const to = from.modifyWith(delta);
      expect(to.modifyDiffTo(from)).toBe(delta);
    }
  });
});
