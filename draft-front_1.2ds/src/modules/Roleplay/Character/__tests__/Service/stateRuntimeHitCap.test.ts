import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/Service/Instance/stateRuntimeEffectsService';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

describe('StateRuntimeEffectsService hindrance cap', () => {
  it('режет помехи попадания по max_abs', () => {
    const rules = [
      {
        id: 1,
        code: 'wobble',
        type: 'state',
        name: 'Качка',
        description: '',
        spaceId: 1,
        keywordIds: [],
        mechanicId: null,
        createdAt: 0,
        spec: {
          value_type: 'number',
          aggregation: 'sum',
          effects: [{ type: 'check_advantage', amount: -1, per_unit: true, includes_hit: true, max_abs: 3 }],
        },
      },
    ] as Rule[];
    const version = { states: [{ stateRuleCode: 'wobble', value: 8 }] } as CharacterVersion;

    expect(stateRuntimeEffectsService.checkAdvantageFromStates(version, rules, { kind: 'hit' })).toBe(-3);
  });
});
