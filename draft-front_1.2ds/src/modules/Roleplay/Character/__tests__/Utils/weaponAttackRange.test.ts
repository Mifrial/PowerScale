import { describe, expect, it } from 'vitest';
import {
  actionStrengthSizePenalty,
  DEFAULT_FALLOFF,
  difficultySizeFromRange,
  rangedHitDifficulty,
} from '@/modules/Roleplay/Character/Utils/weaponAttackRange';

describe('weaponAttackRange', () => {
  it('полосы сложности: 5, 10, 20…', () => {
    expect(difficultySizeFromRange(5, DEFAULT_FALLOFF)).toBe(0);
    expect(difficultySizeFromRange(6, DEFAULT_FALLOFF)).toBe(1);
    expect(difficultySizeFromRange(10, DEFAULT_FALLOFF)).toBe(1);
    expect(difficultySizeFromRange(11, DEFAULT_FALLOFF)).toBe(2);
    expect(difficultySizeFromRange(20, DEFAULT_FALLOFF)).toBe(2);
    expect(difficultySizeFromRange(21, DEFAULT_FALLOFF)).toBe(3);
  });

  it('сложность ДБ: max(1, результат) + укрытие, полосы на размер', () => {
    expect(rangedHitDifficulty(2, 0, 1, DEFAULT_FALLOFF)).toEqual({ base: 3, size: -1 });
    expect(rangedHitDifficulty(2, 2, 1, DEFAULT_FALLOFF)).toEqual({ base: 4, size: -1 });
    expect(rangedHitDifficulty(0, 0, 1, DEFAULT_FALLOFF)).toEqual({ base: 1, size: -1 });
    expect(rangedHitDifficulty(0, 0, 6, DEFAULT_FALLOFF)).toEqual({ base: 1, size: 0 });
  });

  it('сила действия: полные falloff сверх reach', () => {
    expect(actionStrengthSizePenalty(10, 10, DEFAULT_FALLOFF)).toBe(0);
    expect(actionStrengthSizePenalty(14, 10, DEFAULT_FALLOFF)).toBe(0);
    expect(actionStrengthSizePenalty(15, 10, DEFAULT_FALLOFF)).toBe(1);
    expect(actionStrengthSizePenalty(20, 10, DEFAULT_FALLOFF)).toBe(2);
  });
});
