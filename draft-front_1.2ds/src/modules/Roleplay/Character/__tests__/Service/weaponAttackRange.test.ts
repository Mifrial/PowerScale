import { describe, expect, it } from 'vitest';
import { DEFAULT_FALLOFF } from '@/modules/Roleplay/Character/Constant/Weapon/DEFAULT_FALLOFF';
import { weaponAttackRangeService } from '@/modules/Roleplay/Character/Service/Instance/weaponAttackRangeService';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';

describe('weaponAttackRange', () => {
  it('полосы сложности: 1 на пороге, 2 на 10, 3 на 20…', () => {
    expect(weaponAttackRangeService.difficultySizeFromRange(5, DEFAULT_FALLOFF)).toBe(1);
    expect(weaponAttackRangeService.difficultySizeFromRange(6, DEFAULT_FALLOFF)).toBe(2);
    expect(weaponAttackRangeService.difficultySizeFromRange(10, DEFAULT_FALLOFF)).toBe(2);
    expect(weaponAttackRangeService.difficultySizeFromRange(11, DEFAULT_FALLOFF)).toBe(3);
    expect(weaponAttackRangeService.difficultySizeFromRange(20, DEFAULT_FALLOFF)).toBe(3);
    expect(weaponAttackRangeService.difficultySizeFromRange(21, DEFAULT_FALLOFF)).toBe(4);
  });

  it('сложность ДБ: успехи + ½ + укрытие, сдвиг на полосы', () => {
    expect(weaponAttackRangeService.rangedHitDifficulty(2, 0, 1, DEFAULT_FALLOFF)).toEqual({ base: 5, size: -1 });
    expect(weaponAttackRangeService.rangedHitDifficulty(2, 2, 1, DEFAULT_FALLOFF)).toEqual({ base: 9, size: -1 });
    expect(weaponAttackRangeService.rangedHitDifficulty(0, 0, 1, DEFAULT_FALLOFF)).toEqual({ base: 1, size: -1 });
    expect(weaponAttackRangeService.rangedHitDifficulty(0, 0, 6, DEFAULT_FALLOFF)).toEqual({ base: 1, size: 0 });
  });

  it('DistanceValue: ипари сохраняет ½, шаг умножается размерно', () => {
    const formula = new FormulaEvaluationService();
    const context = { characteristicValues: new Map(), abilityLevels: new Map() };

    expect(
      weaponAttackRangeService.resolveDistanceValue(
        { type: 'ipari', value: { type: 'dimensional', base: 1, size: -1 } },
        context,
        formula,
      ),
    ).toEqual({ base: 1, size: -1 });
    expect(
      weaponAttackRangeService.resolveDistanceValue(
        { type: 'step', value: { type: 'fixed', value: 1 } },
        context,
        formula,
        { base: 1, size: -1 },
      ),
    ).toEqual({ base: 1, size: -1 });
  });

  it('сила действия: полные falloff сверх reach', () => {
    expect(weaponAttackRangeService.actionStrengthSizePenalty(10, 10, DEFAULT_FALLOFF)).toBe(0);
    expect(weaponAttackRangeService.actionStrengthSizePenalty(14, 10, DEFAULT_FALLOFF)).toBe(0);
    expect(weaponAttackRangeService.actionStrengthSizePenalty(15, 10, DEFAULT_FALLOFF)).toBe(1);
    expect(weaponAttackRangeService.actionStrengthSizePenalty(20, 10, DEFAULT_FALLOFF)).toBe(2);
    expect(weaponAttackRangeService.actionStrengthSizePenalty(5.5, 0.5, DEFAULT_FALLOFF)).toBe(1);
    expect(weaponAttackRangeService.actionStrengthSizePenalty(1.5, 0.5, { base: 1, size: -1 })).toBe(2);
  });
});
