import { describe, expect, it } from 'vitest';
import { ruleToForm } from '@/modules/Roleplay/Rule/Utils/Rule/ruleToForm';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const rule = (mechanicPayload: Rule['mechanics'][number]['mechanicPayload']): Rule => ({
  id: 1,
  code: 'roll',
  type: 'simple',
  name: 'Бросок',
  description: '',
  spaceId: 1,
  mechanics: [{ mechanicId: 5, mechanicPayload }],
  createdAt: 1,
});

describe('ruleToForm mechanicPayload', () => {
  it('клонирует непустой payload и не делит ссылку с правилом', () => {
    const source = rule({
      type: 'roll',
      data: { sub_mechanics: ['advantage_disadvantage'] },
    });
    const form = ruleToForm(source);
    expect(form.mechanics[0]?.mechanicPayload).toEqual(source.mechanics[0]?.mechanicPayload);
    expect(form.mechanics[0]?.mechanicPayload).not.toBe(source.mechanics[0]?.mechanicPayload);
  });

  it('оставляет null', () => {
    expect(ruleToForm(rule(null)).mechanics[0]?.mechanicPayload).toBeNull();
  });
});
