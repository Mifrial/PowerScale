import { describe, expect, it } from 'vitest';
import { keywordExperienceService } from '@/modules/Roleplay/Character/Service/Instance/keywordExperienceService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';

const keywords: Keyword[] = [{ id: 227, code: 'electromancy', name: 'Электромансия', description: '', active: true }];

const discharge: Rule = {
  id: null,
  code: 'discharge',
  type: 'ability',
  name: 'Разряд',
  description: '',
  spaceId: 1,
  spec: {
    type: 'spell',
    zones: { or: { kind: 'array', levels_cost: [1] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [],
    spell: {
      power: { base: 3, size: 0 },
      control: { base: 3, size: -1 },
      duration: { type: 'instant' },
    },
  } as AbilitySpec,
  keywordIds: [227],
  createdAt: 1,
};

describe('KeywordExperienceService', () => {
  it('суммирует лестницу зоны способностей с keyword', () => {
    const experience = keywordExperienceService.experienceOf(
      'electromancy',
      [{ ruleCode: 'discharge', level: 1, zone: 'or' }],
      [discharge],
      keywords,
      (ability, _rule, spec) => keywordExperienceService.zoneLadderCost(ability, spec),
    );
    expect(experience).toBe(1);
  });
});
