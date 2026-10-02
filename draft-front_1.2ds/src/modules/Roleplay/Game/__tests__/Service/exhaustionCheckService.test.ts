import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';

function stateRule(code: string, flag?: 'decline_weakness' | 'decline_disabled' | 'decline_unconscious'): Rule {
  return {
    code,
    type: 'state',
    spec: {
      value_type: 'flag',
      aggregation: 'max',
      effects: [],
      ...(flag ? { [flag]: true } : {}),
    },
  } as Rule;
}

describe('ExhaustionCheckService.declineRule', () => {
  it('ищет исход упадка сил по флагу, не по коду каталога', () => {
    expect(exhaustionCheckService.declineRule([stateRule('weakness')], 'weakness')).toBeNull();
    expect(exhaustionCheckService.declineRule([stateRule('fatigue', 'decline_weakness')], 'weakness')?.code).toBe('fatigue');
    expect(exhaustionCheckService.declineRule([stateRule('disabled')], 'disabled')).toBeNull();
    expect(exhaustionCheckService.declineRule([stateRule('spent', 'decline_disabled')], 'disabled')?.code).toBe('spent');
    expect(exhaustionCheckService.declineRule([stateRule('unconscious')], 'unconscious')).toBeNull();
    expect(exhaustionCheckService.declineRule([stateRule('asleep', 'decline_unconscious')], 'unconscious')?.code).toBe('asleep');
    expect(exhaustionCheckService.declineRule([stateRule('fatigue', 'decline_weakness')], 'clear')).toBeNull();
  });
});
