import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';

import { CHECK_SIMPLE_CODE } from '@/modules/Roleplay/Rule/init';

const RULES: Rule[] = [
  {
    id: 'roll',
    code: 'roll',
    type: 'simple',
    name: 'Бросок',
    description: '',
    spaceId: 1,
    mechanicId: 5,
    mechanic_payload: { type: 'roll', data: { efficiency: 3, sub_mechanics: ['advantage_disadvantage'] } },
    createdAt: '2026-08-22T12:00:00Z',
  },
  {
    id: 'six',
    code: 'rule-6-and-1',
    type: 'simple',
    name: 'Правило 6 и 1',
    description: '',
    spaceId: 1,
    mechanicId: 1,
    createdAt: '2026-08-22T12:00:00Z',
  },
  {
    id: 'check-simple',
    code: CHECK_SIMPLE_CODE,
    type: 'check',
    name: 'Простая проверка',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'both',
      attached_rule_codes: ['rule-6-and-1', 'advantages'],
    },
    createdAt: '2026-08-22T12:00:00Z',
  },
  {
    id: 'check-strength',
    code: 'check-strength',
    type: 'check',
    name: 'Проверка на Силу',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      parent_check_code: CHECK_SIMPLE_CODE,
      characteristic_code: 'strength',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'both',
    },
    createdAt: '2026-08-22T12:00:00Z',
  },
  {
    id: 'check-exhaustion',
    code: 'check-exhaustion',
    type: 'check',
    name: 'Проверка на истощение',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      parent_check_code: CHECK_SIMPLE_CODE,
      characteristic_code: 'willpower',
      difficulty_input: { kind: 'from_state', state_code: 'exhaustion' },
      allowed_modes: 'solo',
    },
    createdAt: '2026-08-22T12:00:00Z',
  },
];

const MECHANICS: Mechanic[] = [
  { id: 1, code: 'six_one_rule', name: 'Правило 6 и 1', description: '', version: '4.5.0' },
  { id: 5, code: 'roll', name: 'Бросок', description: '', version: '1.0.0' },
];

function rngOf(...values: number[]): () => number {
  let index = 0;

  return () => values[index++ % values.length] ?? 0;
}

describe('checkRoll', () => {
  it('namedCheckSpec: пул = база, преимущества в spec', () => {
    const spec = checkRollService.namedCheckSpec('Сила', { base: 4, size: 1 }, 2, RULES);
    expect(spec.diceCount).toBe(4);
    expect(spec.dieSize).toBe(1);
    expect(spec.advantages).toEqual([{ source_code: 'manual', source_label: null, delta: 2 }]);
    expect(spec.actorKey).toBeUndefined();
  });

  it('namedCheckSpec: actorKey уходит в spec', () => {
    const spec = checkRollService.namedCheckSpec('Сила', { base: 4, size: 0 }, 0, RULES, 'character:3');
    expect(spec.actorKey).toBe('character:3');
  });

  it('соло ask: слой проверки против заданной сложности', () => {
    const spec = checkRollService.namedCheckSpec('Сила', { base: 1, size: 0 }, 0, RULES);
    const result = checkRollService.rollNamedCheck(
      spec,
      'check-strength',
      { base: 2, size: 0 },
      rngOf(0),
      RULES,
      MECHANICS,
    );
    expect(result.rolls).toEqual([1]);
    expect(result.successes).toEqual([2]);
    expect(result.check?.check_code).toBe('check-strength');
    expect(result.check?.difficulty).toEqual({ base: 2, size: 0 });
    expect(result.check?.passed).toBe(true);
    expect(result.check?.rating).toBe(0);
  });

  it('соло from_state: сложность — значение состояния', () => {
    const spec = checkRollService.namedCheckSpec('Воля', { base: 1, size: 0 }, 0, RULES);
    const difficulty = { base: 3, size: 0 };
    const result = checkRollService.rollNamedCheck(spec, 'check-exhaustion', difficulty, rngOf(0), RULES, MECHANICS);
    expect(result.check?.difficulty).toEqual(difficulty);
    expect(result.check?.passed).toBe(false);
    expect(result.check?.rating).toBe(-1);
  });

  it('pairwise: чужой размерный итог = сложность стороны', () => {
    const left = checkRollService.namedCheckSpec('А', { base: 1, size: 0 }, 0, RULES);
    const right = checkRollService.namedCheckSpec('Б', { base: 1, size: 1 }, 0, RULES);
    const rolled = checkRollService.rollJointCheck(left, right, CHECK_SIMPLE_CODE, rngOf(0, 0.5), RULES, MECHANICS);
    expect(rolled.left.check?.difficulty).toEqual(checkRollService.successesOf(rolled.right));
    expect(rolled.right.check?.difficulty).toEqual(checkRollService.successesOf(rolled.left));
    expect(rolled.left.check?.check_code).toBe(CHECK_SIMPLE_CODE);
    expect(rolled.right.check?.check_code).toBe(CHECK_SIMPLE_CODE);
  });
});
