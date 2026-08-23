import { describe, expect, it } from 'vitest';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import {
  ROLL_MECHANIC_NAME_ADVANTAGE,
  ROLL_MECHANIC_NAME_SIX_ONE,
  resolveAppliedMechanicNames,
} from '@/modules/Roleplay/Game/Utils/appliedRollMechanics';

function result(
  partial: Partial<DiceRollResult> & Pick<DiceRollResult, 'adjustedRolls' | 'successes'>,
): DiceRollResult {
  return {
    spec: { diceCount: partial.adjustedRolls.length, dieFaces: 6, efficiency: 3, advantages: [], dieSize: 0 },
    rolls: partial.adjustedRolls,
    droppedRolls: [],
    totalSuccesses: partial.successes.reduce((sum, n) => sum + n, 0),
    ...partial,
  };
}

describe('resolveAppliedMechanicNames', () => {
  it('видит «6 и 1» по граням, даже без appliedMechanics', () => {
    expect(
      resolveAppliedMechanicNames(
        result({
          adjustedRolls: [3, 4, 6, 2, 6],
          successes: [1, 0, -1, 1, -1],
        }),
      ),
    ).toEqual([ROLL_MECHANIC_NAME_SIX_ONE]);
  });

  it('видит помехи/преимущества по снятым кубам', () => {
    expect(
      resolveAppliedMechanicNames(
        result({
          adjustedRolls: [5, 4, 3, 2, 2],
          successes: [0, 0, 1, 1, 1],
          droppedRolls: [6, 5],
        }),
      ),
    ).toEqual([ROLL_MECHANIC_NAME_ADVANTAGE]);
  });

  it('не считает эффективность грани за «6 и 1»', () => {
    expect(
      resolveAppliedMechanicNames(
        result({
          adjustedRolls: [3, 4, 5],
          successes: [1, 0, 0],
        }),
      ),
    ).toEqual([]);
  });
});
