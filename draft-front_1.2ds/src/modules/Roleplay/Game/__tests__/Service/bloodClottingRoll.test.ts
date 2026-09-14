import { describe, expect, it } from 'vitest';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
import { CHECK_BLOOD_CLOTTING_CODE, CHECK_SIMPLE_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { SIMPLE_CHECK_ZERO_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Check/SIMPLE_CHECK_ZERO_DIFFICULTY';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import { WOUND_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';

const RULES: Rule[] = [
  {
    id: null,
    code: 'roll',
    type: 'simple',
    name: 'Бросок',
    description: '',
    spaceId: 1,
    mechanicId: 5,
    mechanicPayload: { type: 'roll', data: { dieFaces: 6, efficiency: 3, diceCount: 3, sub_mechanics: [] } },
    createdAt: 0,
  },
  {
    id: null,
    code: 'rule-6-and-1',
    type: 'simple',
    name: 'Правило 6 и 1',
    description: '',
    spaceId: 1,
    mechanicId: 1,
    createdAt: 0,
  },
  {
    id: null,
    code: CHECK_SIMPLE_CODE,
    type: 'check',
    name: 'Простая',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'both',
      attached_rule_codes: ['rule-6-and-1', 'advantages'],
    },
    createdAt: 0,
  },
  {
    id: null,
    code: CHECK_BLOOD_CLOTTING_CODE,
    type: 'check',
    name: 'Свёртывание крови',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      parent_check_code: CHECK_SIMPLE_CODE,
      difficulty_input: { kind: 'none' },
      allowed_modes: 'solo',
    },
    createdAt: 0,
  },
];

const MECHANICS: Mechanic[] = [
  { id: 1, code: 'six_one_rule', name: 'Правило 6 и 1', description: '', version: '4.5.0' },
  { id: 5, code: 'roll', name: 'Бросок', description: '', version: '1.0.0' },
];

describe('blood clotting roll', () => {
  it('один куб vs {0|0}, 6 и 1 с предка', () => {
    const result = checkRollService.rollNamedCheck(
      { diceCount: 1, dieFaces: 6, efficiency: 3, advantages: [], dieSize: 0 },
      CHECK_BLOOD_CLOTTING_CODE,
      SIMPLE_CHECK_ZERO_DIFFICULTY,
      () => 0,
      RULES,
      MECHANICS,
    );
    expect(result.rolls).toEqual([1]);
    expect(result.check?.check_code).toBe(CHECK_BLOOD_CLOTTING_CODE);
    expect(result.check?.passed).toBe(true);
    expect(result.check?.rating).toBe(2);
    const next = woundInstanceService.applyClotting(
      { stateRuleCode: WOUND_STATE_CODE, value: 4 },
      result.check?.rating ?? 0,
    );
    expect(woundInstanceService.payload(next).clotting).toBe(2);
  });
});
