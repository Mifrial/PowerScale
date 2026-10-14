import { describe, expect, it } from 'vitest';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import { InnateCharacteristicKindService } from '@/modules/Roleplay/Character/Service/InnateCharacteristicKindService';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const service = new InnateCharacteristicKindService();

function ability(ruleCode: string, keywordIds: number[]): EditorAbility {
  return {
    ruleCode,
    code: ruleCode,
    name: ruleCode,
    type: 'trait',
    description: '',
    processSteps: [],
    keywordIds,
    zones: [],
    level: 0,
    levels: [],
    automatic: false,
    gifted: false,
    giftedLevel: 0,
    derived: false,
    racial: false,
    visible: true,
    characteristic: true,
    characteristicCode: 'strength',
    groupCode: null,
    parentCode: null,
    parameters: [],
    multiple: false,
    domainRef: null,
    instances: [],
    domain: null,
    domainCode: null,
    domainOptions: [],
  };
}

function keyword(id: number, code: string): Keyword {
  return { id, code, name: code, description: '', active: true };
}

function abilityRule(code: string, grants: Grant[]): Rule {
  const spec: AbilitySpec = {
    type: 'trait',
    zones: {},
    requirements: [],
    grants: [{ level: 1, grants }],
    parent_ability_code: null,
  };

  return {
    id: 1,
    code,
    type: 'ability',
    name: code,
    description: '',
    spaceId: 1,
    spec,
    keywordIds: [],
    mechanics: [],
    createdAt: 0,
  };
}

describe('InnateCharacteristicKindService', () => {
  it('делит по кодам признаков, а не по зашитым id мока', () => {
    const split = service.split(
      [ability('innate-strength', [100]), ability('magic-core-capacity', [101])],
      [keyword(100, 'modifier'), keyword(101, 'gift')],
      [],
    );

    expect(split.modifiers.map((row) => row.ruleCode)).toEqual(['innate-strength']);
    expect(split.gifts.map((row) => row.ruleCode)).toEqual(['magic-core-capacity']);
  });

  it('если id признаков не совпали — смотрит гранты спеки', () => {
    const split = service.split(
      [ability('innate-strength', [46]), ability('magic-core-capacity', [47])],
      [keyword(900, 'modifier'), keyword(901, 'gift')],
      [
        abilityRule('innate-strength', [
          {
            type: 'characteristic_modify',
            characteristic_code: 'strength',
            amount: { type: 'parameter', parameter_code: 'x', per_unit: 1 },
            source_code: 'innate',
          },
        ]),
        abilityRule('magic-core-capacity', [
          {
            type: 'characteristic_parameter',
            characteristic_code: 'magic-power',
            parameter_code: 'x',
            per_unit: 1,
          },
        ]),
      ],
    );

    expect(split.modifiers.map((row) => row.ruleCode)).toEqual(['innate-strength']);
    expect(split.gifts.map((row) => row.ruleCode)).toEqual(['magic-core-capacity']);
  });
});
