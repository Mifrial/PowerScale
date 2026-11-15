import { describe, expect, it } from 'vitest';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { electrochargeService } from '@/modules/Roleplay/Game/Service/Instance/electrochargeService';

function spell(overrides: Partial<ActiveSpell> = {}): ActiveSpell {
  return {
    id: '7',
    gameId: 1,
    casterKey: 'character:1',
    spellCode: 'lightning-generator',
    sourceKey: 'characteristic:magic-power',
    pathCode: 'arcanist',
    durationType: 'sustained',
    usedPower: { base: 4, size: 0 },
    sustainPower: { base: 4, size: 0 },
    parameterValues: {},
    appliedUpgradeCodes: [],
    startedRound: 1,
    startedParticipantId: 'character:1',
    version: 1,
    ...overrides,
  };
}

function state(overrides: Partial<CharacterStateValue> = {}): CharacterStateValue {
  return { stateRuleCode: 'electrocharge', value: 1, ...overrides };
}

function rules(): Rule[] {
  return [
    {
      id: 1,
      code: 'lightning-generator',
      type: 'ability',
      name: 'Генератор молний',
      keywordIds: [],
      spec: {
        type: 'spell',
        spell: {
          duration: { type: 'sustained' },
          charge: {
            state_code: 'electrocharge',
            grant: 1,
            default_cap: 1,
            spend: {
              amount: 1,
              action_points: 2,
              keyword_code: 'electromancy',
              creation_max: 'turn_ap',
              exclude_duration_types: ['sustained'],
            },
          },
        },
      },
    } as unknown as Rule,
  ];
}

describe('ElectrochargeService bindings', () => {
  it('находит поддержание по id в моке', () => {
    const row = state({ boundSustainId: '7' });

    expect(electrochargeService.spellOfState(row, [spell()], rules())?.id).toBe('7');
  });

  it('находит поддержание по источнику на сервере', () => {
    const row = state({ boundSourceKey: 'characteristic:magic-power' });

    expect(electrochargeService.spellOfState(row, [spell()], rules())?.id).toBe('7');
    expect(electrochargeService.boundState([row], spell(), 'electrocharge')).toBe(row);
  });

  it('не связывает чужой источник и чужое состояние', () => {
    expect(electrochargeService.spellOfState(state({ boundSourceKey: 'inventory:9' }), [spell()], rules())).toBeNull();
    expect(
      electrochargeService.spellOfState(
        state({ stateRuleCode: 'core-magic-deviation', boundSourceKey: 'characteristic:magic-power' }),
        [spell()],
        rules(),
      ),
    ).toBeNull();
    expect(
      electrochargeService.boundState([state({ boundSourceKey: 'inventory:9' })], spell(), 'electrocharge'),
    ).toBeUndefined();
  });
});
