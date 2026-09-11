import { describe, expect, it } from 'vitest';
import { spellDeviationService } from '@/modules/Roleplay/Game/Service/Instance/spellDeviationService';
import type { SpellCastRollOutcome } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastRollOutcome';
import { SPELL_CAST_SKIP_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Spell/SPELL_CAST_SKIP_DIFFICULTY';
import { SPELL_CAST_BASE_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Spell/SPELL_CAST_BASE_DIFFICULTY';

function rngFaces(...faces: number[]): () => number {
  let index = 0;

  return () => {
    const face = faces[index] ?? 1;
    index += 1;

    return (face - 1) / 6 + 0.001;
  };
}

function failedCast(): SpellCastRollOutcome {
  return {
    difficulty: SPELL_CAST_BASE_DIFFICULTY,
    needsCheck: true,
    roll: {
      spec: { diceCount: 1, dieSize: 0, dieFaces: 6, efficiency: 4, advantages: [] },
      rolls: [2],
      successes: [0],
      adjustedRolls: [2],
      droppedRolls: [],
      totalSuccesses: 0,
      check: {
        check_code: 'check-spell-cast',
        check_name: 'Сотворение',
        difficulty: SPELL_CAST_BASE_DIFFICULTY,
        rating: -1,
        passed: false,
      },
    },
  };
}

describe('SpellDeviationService', () => {
  it('хук только при passed === false', () => {
    expect(spellDeviationService.isFailedCastCheck(failedCast())).toBe(true);
    expect(
      spellDeviationService.isFailedCastCheck({
        difficulty: SPELL_CAST_SKIP_DIFFICULTY,
        needsCheck: false,
        roll: null,
      }),
    ).toBe(false);
    expect(
      spellDeviationService.isFailedCastCheck({
        difficulty: SPELL_CAST_SKIP_DIFFICULTY,
        needsCheck: true,
        roll: null,
      }),
    ).toBe(false);
    const passed = failedCast();
    if (passed.roll?.check) {
      passed.roll.check.passed = true;
    }
    expect(spellDeviationService.isFailedCastCheck(passed)).toBe(false);
  });

  it('7 — нет эффекта и нет дубля', () => {
    const outcome = spellDeviationService.roll(rngFaces(3, 4), 'character:1');
    expect(outcome.faceSum).toBe(7);
    expect(outcome.strength).toBe(0);
    expect(outcome.hasEffect).toBe(false);
    expect(outcome.isDoubles).toBe(false);
    expect(outcome.roll.spec.scoring).toBe('face_sum');
    expect(outcome.roll.faceSum).toBe(7);
  });

  it('4+4 — взрыв с цифрой 4', () => {
    const outcome = spellDeviationService.roll(rngFaces(4, 4), 'character:1');
    expect(outcome.isDoubles).toBe(true);
    expect(outcome.dieDigit).toBe(4);
    expect(outcome.strength).toBe(1);
    expect(outcome.hasEffect).toBe(true);
    expect(spellDeviationService.explosionAmount({ base: 4, size: 0 }, 0, 4, 0)).toBe(16);
    expect(spellDeviationService.explosionAmount({ base: 4, size: 0 }, 5, 4, 0)).toBe(0);
    expect(spellDeviationService.explosionAmount({ base: 4, size: 0 }, 0, 4, 3)).toBe(13);
    expect(spellDeviationService.explosionWeaponDamage(16)).toEqual({ base: 16, size: 0 });
  });

  it('2+2 — взрыв без отклонения', () => {
    const outcome = spellDeviationService.roll(rngFaces(2, 2), 'character:1');
    expect(outcome.isDoubles).toBe(true);
    expect(outcome.hasEffect).toBe(false);
    expect(outcome.strength).toBe(-3);
    expect(spellDeviationService.formatOutcomeMessage(outcome)).toBe('Магического отклонения нет.');
    expect(spellDeviationService.formatBurstBeginMessage(outcome)).toBe('Собранная магия взрывается.');
  });

  it('состояние на одном источнике суммируется; другой источник отдельно', () => {
    const first = spellDeviationService.grant([], 'inventory:9', 2);
    expect(first).toEqual({
      stateRuleCode: 'core-magic-deviation',
      value: 2,
      boundSourceKey: 'inventory:9',
    });
    const stacked = spellDeviationService.grant([first!], 'inventory:9', 1);
    expect(stacked?.value).toBe(3);
    expect(spellDeviationService.grant([first!], 'inventory:8', 1)?.boundSourceKey).toBe('inventory:8');
    expect(spellDeviationService.grant([], '', 2)).toBeNull();
    expect(spellDeviationService.grant([], 'inventory:9', 0)).toBeNull();
  });

  it('decay −1 за ход, на 0 снимается', () => {
    const state = { stateRuleCode: 'core-magic-deviation', value: 2, boundSourceKey: 'inventory:9' };
    expect(spellDeviationService.decay(state)).toEqual({ ...state, value: 1 });
    expect(spellDeviationService.decay({ ...state, value: 1 })).toBeNull();
  });

  it('штраф к мощи ядра; ниже маленького размера — 0', () => {
    expect(spellDeviationService.penalizeUsedPower({ base: 4, size: 0 }, 1)).toEqual({ base: 3, size: 0 });
    expect(spellDeviationService.penalizeUsedPower({ base: 5, size: 0 }, 3)).toEqual({ base: 5, size: -1 });
    expect(spellDeviationService.penalizeUsedPower({ base: 3, size: -1 }, 1)).toEqual({ base: 0, size: 0 });
  });
});
