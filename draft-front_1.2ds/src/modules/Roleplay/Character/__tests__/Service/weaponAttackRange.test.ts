import { describe, expect, it } from 'vitest';
import { DEFAULT_FALLOFF } from '@/modules/Roleplay/Character/Constant/Weapon/DEFAULT_FALLOFF';
import { weaponAttackRangeService } from '@/modules/Roleplay/Character/Service/Instance/weaponAttackRangeService';

describe('weaponAttackRange', () => {
  it('полосы сложности: 5, 10, 20…', () => {
    expect(weaponAttackRangeService.difficultySizeFromRange(5, DEFAULT_FALLOFF)).toBe(0);
    expect(weaponAttackRangeService.difficultySizeFromRange(6, DEFAULT_FALLOFF)).toBe(1);
    expect(weaponAttackRangeService.difficultySizeFromRange(10, DEFAULT_FALLOFF)).toBe(1);
    expect(weaponAttackRangeService.difficultySizeFromRange(11, DEFAULT_FALLOFF)).toBe(2);
    expect(weaponAttackRangeService.difficultySizeFromRange(20, DEFAULT_FALLOFF)).toBe(2);
    expect(weaponAttackRangeService.difficultySizeFromRange(21, DEFAULT_FALLOFF)).toBe(3);
  });

  it('сложность ДБ: max(1, результат) + укрытие, полосы на размер', () => {
    expect(weaponAttackRangeService.rangedHitDifficulty(2, 0, 1, DEFAULT_FALLOFF)).toEqual({ base: 3, size: -1 });
    expect(weaponAttackRangeService.rangedHitDifficulty(2, 2, 1, DEFAULT_FALLOFF)).toEqual({ base: 4, size: -1 });
    expect(weaponAttackRangeService.rangedHitDifficulty(0, 0, 1, DEFAULT_FALLOFF)).toEqual({ base: 1, size: -1 });
    expect(weaponAttackRangeService.rangedHitDifficulty(0, 0, 6, DEFAULT_FALLOFF)).toEqual({ base: 1, size: 0 });
  });

  it('сила действия: полные falloff сверх reach', () => {
    expect(weaponAttackRangeService.actionStrengthSizePenalty(10, 10, DEFAULT_FALLOFF)).toBe(0);
    expect(weaponAttackRangeService.actionStrengthSizePenalty(14, 10, DEFAULT_FALLOFF)).toBe(0);
    expect(weaponAttackRangeService.actionStrengthSizePenalty(15, 10, DEFAULT_FALLOFF)).toBe(1);
    expect(weaponAttackRangeService.actionStrengthSizePenalty(20, 10, DEFAULT_FALLOFF)).toBe(2);
  });
});
