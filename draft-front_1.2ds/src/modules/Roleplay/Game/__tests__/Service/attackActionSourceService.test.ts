import { describe, expect, it } from 'vitest';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { attackActionSourceService } from '@/modules/Roleplay/Game/Service/Instance/attackActionSourceService';

const profile = (profileType: AttackOverview['profileType']): AttackOverview => ({
  itemRuleId: `${profileType}-item`,
  itemName: profileType,
  itemHref: '',
  profileType,
  profileTypeLabel: profileType,
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
  damageTypeCode: null,
  damage: { base: 1, size: 0 },
  penetration: { base: 0, size: 0 },
});

const rule = (keywordIds: number[]): Rule => ({
  id: 'attack',
  code: 'attack',
  type: 'ability',
  name: 'Атака',
  description: '',
  spaceId: 1,
  keywordIds,
  createdAt: '',
  spec: {
    type: 'action',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [],
  },
});

describe('AttackActionSourceService', () => {
  it('does not offer throw profiles for a melee attack', () => {
    expect(
      attackActionSourceService.compatibleProfiles(rule([64, 71]), [
        profile('strike'),
        profile('throw'),
        profile('shoot'),
      ]),
    ).toHaveLength(1);
  });

  it('keeps an arbitrary number of compatible simultaneous profiles', () => {
    expect(
      attackActionSourceService.compatibleProfiles(rule([66, 71]), [
        profile('throw'),
        profile('shoot'),
        profile('throw'),
      ]),
    ).toHaveLength(3);
  });

  it('checks favorite profile availability by full profile identity', () => {
    const favorite = { ...profile('strike'), profileIndex: 1 };

    expect(attackActionSourceService.isProfileAvailable(favorite, [{ ...profile('strike'), profileIndex: 0 }])).toBe(
      false,
    );
    expect(attackActionSourceService.isProfileAvailable(favorite, [favorite])).toBe(true);
  });
});
