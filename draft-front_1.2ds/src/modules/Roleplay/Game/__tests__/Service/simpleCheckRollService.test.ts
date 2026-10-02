import { describe, expect, it } from 'vitest';
import { simpleCheckRollService } from '@/modules/Roleplay/Game/Service/Instance/simpleCheckRollService';

import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import { CHECK_SIMPLE_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';

function rollWithoutCheck(): DiceRollResult {
  return {
    spec: { diceCount: 1, dieFaces: 6, efficiency: 3, advantages: [], dieSize: 0 },
    rolls: [2],
    successes: [1],
    adjustedRolls: [2],
    droppedRolls: [],
    totalSuccesses: 1,
  };
}

const RULES: Rule[] = [
  {
    id: null,
    code: 'roll',
    type: 'simple',
    name: 'Бросок',
    description: '',
    spaceId: 1,
    mechanics: [{ mechanicId: 5, mechanicPayload: { type: 'roll', data: { efficiency: 3, sub_mechanics: ['advantage_disadvantage'] } } }],
    createdAt: 1787400000,
  },
  {
    id: null,
    code: 'rule-6-and-1',
    type: 'simple',
    name: 'Правило 6 и 1',
    description: '',
    spaceId: 1,
    mechanics: [{ mechanicId: 1, mechanicPayload: null }],
    createdAt: 1787400000,
  },
  {
    mechanics: [],
    id: null,
    code: CHECK_SIMPLE_CODE,
    type: 'check',
    name: 'Простая проверка',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'both',
      ordinary_root: true,
      attached_rule_codes: ['rule-6-and-1', 'advantages'],
    },
    createdAt: 1787400000,
  },
];

const MECHANICS: Mechanic[] = [
  { id: 1, code: 'six_one_rule', name: 'Правило 6 и 1', description: '', version: '4.5.0' },
  { id: 5, code: 'roll', name: 'Бросок', description: '', version: '1.0.0' },
];

describe('simpleCheckRoll', () => {
  it('чат-бросок — простая проверка vs 0, с 6 и 1', () => {
    const result = simpleCheckRollService.rollSimpleCheckZero(
      { diceCount: 1, dieFaces: 6, efficiency: 3, advantages: [], dieSize: 0 },
      () => 0,
      RULES,
      MECHANICS,
    );
    expect(result.rolls).toEqual([1]);
    expect(result.successes).toEqual([2]);
    expect(result.check?.check_code).toBe(CHECK_SIMPLE_CODE);
    expect(result.check?.difficulty).toEqual({ base: 0, size: 0 });
    expect(result.check?.passed).toBe(true);
    expect(result.check?.rating).toBe(2);
    expect(result.appliedMechanics).toEqual(['Правило 6 и 1']);
  });

  it('withSimpleCheckZero не перезаписывает уже посчитанную проверку', () => {
    const result: DiceRollResult = {
      spec: { diceCount: 1, dieFaces: 6, efficiency: 3, advantages: [], dieSize: 0 },
      rolls: [2],
      successes: [1],
      adjustedRolls: [2],
      droppedRolls: [],
      totalSuccesses: 1,
      check: {
        check_code: 'check-strength',
        difficulty: { base: 2, size: 1 },
        passed: false,
        rating: -1,
      },
    };
    expect(simpleCheckRollService.withSimpleCheckZero(result, RULES).check?.check_code).toBe('check-strength');
  });

  it('withSimpleCheckZero берёт код с ordinary_root и вешает {0|0}', () => {
    const result = rollWithoutCheck();

    const attached = simpleCheckRollService.withSimpleCheckZero(result, RULES);

    expect(attached.check?.check_code).toBe(CHECK_SIMPLE_CODE);
    expect(attached.check?.difficulty).toEqual({ base: 0, size: 0 });
  });

  it('withSimpleCheckZero без ordinary_root не подставляет код проверки', () => {
    const result = rollWithoutCheck();
    const withoutRoot = RULES.map((rule) => {
      if (rule.code !== CHECK_SIMPLE_CODE || rule.spec?.type !== 'check') return rule;
      const spec: CheckSpec = { ...rule.spec, ordinary_root: false };

      return { ...rule, spec };
    });

    expect(simpleCheckRollService.withSimpleCheckZero(result, withoutRoot).check).toBeUndefined();
  });
});
