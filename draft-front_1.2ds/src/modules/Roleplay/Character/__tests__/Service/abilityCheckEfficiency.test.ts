import { describe, expect, it } from 'vitest';
import { AbilityCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/AbilityCheckAdvantagesService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_SPELL_CAST_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';

const service = new AbilityCheckAdvantagesService();

function skill(code: string, amount: number, checkCode = CHECK_SPELL_CAST_CODE): Rule {
  return {
    id: 1,
    code,
    type: 'ability',
    name: code,
    description: '',
    spaceId: 1,
    mechanics: [],
    createdAt: 1,
    spec: {
      type: 'skill',
      zones: {},
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [{ type: 'check_efficiency', amount, check_codes: [checkCode], source_code: 'mastery' }],
        },
      ],
      parent_ability_code: null,
    },
  };
}

describe('AbilityCheckAdvantagesService.checkEfficiencyDeltasFromAbilities', () => {
  it('грант чужой проверки и нулевой уровень не дают дельту', () => {
    const rules = [skill('element-skill', 1, 'check-intellect')];
    expect(
      service.checkEfficiencyDeltasFromAbilities(
        { abilities: [{ ruleCode: 'element-skill', level: 1 }] },
        rules,
        CHECK_SPELL_CAST_CODE,
      ),
    ).toEqual([]);
    expect(
      service.checkEfficiencyDeltasFromAbilities(
        { abilities: [{ ruleCode: 'element-skill', level: 0 }] },
        rules,
        'check-intellect',
      ),
    ).toEqual([]);
  });

  it('грант +1 и +2 одного источника дают обе дельты', () => {
    const rules = [skill('basic', 1), skill('interaction', 2)];
    expect(
      service.checkEfficiencyDeltasFromAbilities(
        {
          abilities: [
            { ruleCode: 'basic', level: 1 },
            { ruleCode: 'interaction', level: 1 },
          ],
        },
        rules,
        CHECK_SPELL_CAST_CODE,
      ),
    ).toEqual([
      { sourceCode: 'mastery', delta: 1 },
      { sourceCode: 'mastery', delta: 2 },
    ]);
  });
});
