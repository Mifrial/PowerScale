import { describe, expect, it } from 'vitest';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PushSpec } from '@/modules/Roleplay/Rule/Dto/Ability/PushSpec';
import { pushProfileService } from '@/modules/Roleplay/Game/Service/Instance/pushProfileService';

const profile = (overrides: Partial<AttackOverview> = {}): AttackOverview => ({
  itemRuleCode: 'sword',
  itemName: 'Меч',
  itemHref: '',
  profileType: 'strike',
  profileTypeLabel: 'удар',
  distanceLabel: '0',
  reach: 1,
  minDistance: 0,
  falloff: { base: 0, size: 0 },
  accuracyLabel: '1',
  accuracy: { base: 1, size: 0 },
  damageLabel: '1',
  penetrationLabel: '0',
  damageFormula: '1',
  penetrationFormula: '0',
  isResolved: true,
  damageTypeCode: 'slashing',
  damage: { base: 4, size: 0 },
  penetration: { base: 0, size: 0 },
  ...overrides,
});

const pushRule = (push: PushSpec): Rule => ({
  id: null,
  code: 'push-action',
  type: 'ability',
  name: 'Толчок',
  description: '',
  spaceId: 1,
  createdAt: 0,
  spec: {
    type: 'action',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [],
    push,
  },
});

describe('PushProfileService', () => {
  it('руки и щит — только strike рук или щита', () => {
    const rules: Rule[] = [
      {
        id: 1,
        code: 'ruka',
        type: 'item',
        name: 'Рука',
        description: '',
        spaceId: 1,
        createdAt: 0,
        spec: {},
      },
      {
        id: 2,
        code: 'round-shield',
        type: 'item',
        name: 'Щит',
        description: '',
        spaceId: 1,
        createdAt: 0,
        spec: { shield: {} },
      },
    ];
    const attacks = [
      profile({ itemRuleCode: 'ruka', damageTypeCode: 'blunt' }),
      profile({ itemRuleCode: 'round-shield', damageTypeCode: 'blunt' }),
      profile({ itemRuleCode: 'sword', damageTypeCode: 'slashing' }),
    ];
    const compatible = pushProfileService.compatibleProfiles(
      pushRule({ pool: 'strength', damage: 'crush_from_strength', profiles: 'hands_or_shield' }),
      attacks,
      rules,
    );

    expect(compatible.map((item) => item.itemRuleCode)).toEqual(['ruka', 'round-shield']);
  });

  it('оружейный толчок — только рубящий или дробящий strike', () => {
    const compatible = pushProfileService.compatibleProfiles(
      pushRule({
        pool: 'weapon_damage',
        damage: 'weapon_times_sr',
        profiles: 'slashing_or_blunt_strike',
      }),
      [
        profile({ damageTypeCode: 'slashing' }),
        profile({ damageTypeCode: 'blunt' }),
        profile({ damageTypeCode: 'piercing' }),
        profile({ profileType: 'throw', damageTypeCode: 'slashing' }),
      ],
      [],
    );

    expect(compatible.map((item) => item.damageTypeCode)).toEqual(['slashing', 'blunt']);
  });
});
