import { describe, it, expect } from 'vitest';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import { advantageEntries } from '@/modules/Roleplay/Rule/Utils/aggregateSourceDeltas';

function spec(partial: Partial<DiceRollSpec> & { adv?: number } = {}): DiceRollSpec {
  const { adv, advantages, ...rest } = partial;

  return {
    diceCount: 4,
    dieFaces: 6,
    efficiency: 3,
    dieSize: 0,
    ...rest,
    advantages: advantages ?? advantageEntries(adv ?? 0),
  };
}

function rngFromDice(values: number[], faces = 6): DiceRng {
  let i = 0;

  return () => (values[i++] - 1) / faces;
}

describe('computeRollResult', () => {
  it('считает успехи без преимуществ', () => {
    const result = rollService.computeRollResult(spec({ diceCount: 4 }), rngFromDice([2, 4, 1, 5]));
    expect(result.rolls).toEqual([2, 4, 1, 5]);
    expect(result.adjustedRolls).toEqual([2, 4, 1, 5]);
    expect(result.droppedRolls).toEqual([]);
    expect(result.successes).toEqual([1, 0, 2, 0]);
    expect(result.totalSuccesses).toBe(3);
  });

  it('преимущество убирает худшие кубики (6-ки)', () => {
    const result = rollService.computeRollResult(spec({ diceCount: 3, adv: 1 }), rngFromDice([1, 6, 5, 6]));
    expect(result.droppedRolls).toEqual([6]);
    expect(result.adjustedRolls).toEqual([6, 5, 1]);
    expect(result.totalSuccesses).toBe(1);
  });

  it('помеха убирает лучшие кубики (1-ки)', () => {
    const result = rollService.computeRollResult(spec({ diceCount: 3, adv: -1 }), rngFromDice([1, 2, 6, 1]));
    expect(result.droppedRolls).toEqual([1]);
    expect(result.adjustedRolls).toEqual([1, 2, 6]);
    expect(result.totalSuccesses).toBe(2);
  });

  it('шестёрка — провал при эффективности ниже грани', () => {
    const result = rollService.computeRollResult(spec({ diceCount: 1, efficiency: 3 }), rngFromDice([6]));
    expect(result.successes).toEqual([-1]);
    expect(result.totalSuccesses).toBe(-1);
  });

  it('шестёрка — успех при эффективности равной грани', () => {
    const result = rollService.computeRollResult(spec({ diceCount: 1, efficiency: 6 }), rngFromDice([6]));
    expect(result.successes).toEqual([1]);
    expect(result.totalSuccesses).toBe(1);
  });
});
