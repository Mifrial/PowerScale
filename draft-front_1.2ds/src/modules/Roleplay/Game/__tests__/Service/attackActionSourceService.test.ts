import { describe, expect, it } from 'vitest';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { attackActionSourceService } from '@/modules/Roleplay/Game/Service/Instance/attackActionSourceService';

const profile = (profileType: AttackOverview['profileType']): AttackOverview => ({
  itemRuleCode: `${profileType}-item`,
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

const rule = (keywordIds: number[], attackMode?: 'single' | 'wide'): Rule => ({
  id: null,
  code: 'attack',
  type: 'ability',
  name: 'Атака',
  description: '',
  spaceId: 1,
  keywordIds,
  createdAt: 0,
  spec: {
    type: 'action',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [],
    attack_mode: attackMode,
    max_targets: attackMode === 'wide' ? 3 : undefined,
  },
});

describe('AttackActionSourceService', () => {
  it('limits strike profiles to required damage types', () => {
    const joint = {
      ...rule([1, 71]),
      spec: {
        ...rule([1, 71]).spec,
        action_effects: [
          {
            type: 'current_action_durability_shave' as const,
            short_extra_on_first_one: true,
            scope: { components: ['strike' as const], hit_count: 1 as const },
            damage_type_codes: ['cutting', 'slashing', 'piercing'],
          },
        ],
      },
    };

    expect(
      attackActionSourceService.compatibleProfiles(joint, [
        { ...profile('strike'), damageTypeCode: 'blunt' },
        { ...profile('strike'), damageTypeCode: 'piercing', itemRuleCode: 'pike' },
      ]),
    ).toEqual([expect.objectContaining({ itemRuleCode: 'pike', damageTypeCode: 'piercing' })]);
  });

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

  it('resolves a favorite by item code, not storage id', () => {
    const strike = { ...profile('strike'), itemRuleCode: 'ruka', profileIndex: 0 };
    const rules: Rule[] = [
      {
        id: 520,
        code: 'ruka',
        type: 'item',
        name: 'Рука',
        description: '',
        spaceId: 1,
        keywordIds: [],
        createdAt: 0,
      },
    ];

    expect(
      attackActionSourceService.favoriteAttack(
        [strike],
        { itemRuleCode: 'ruka', profileType: 'strike', profileIndex: 0 },
        rules,
      )?.itemName,
    ).toBe('strike');
    expect(
      attackActionSourceService.favoriteAttack(
        [strike],
        { itemRuleCode: 'rule-520', profileType: 'strike', profileIndex: 0 },
        rules,
      ),
    ).toBeNull();
  });

  it('limits wide attacks to three distinct targets and single attacks to one', () => {
    expect(attackActionSourceService.maxTargets(rule([64, 71], 'wide'))).toBe(3);
    expect(attackActionSourceService.validateTargetCount(rule([64, 71], 'wide'), ['a', 'b', 'c'])).toBeNull();
    expect(attackActionSourceService.validateTargetCount(rule([64, 71], 'wide'), ['a', 'b', 'c', 'd'])).toContain('3');
    expect(attackActionSourceService.validateTargetCount(rule([64, 71]), ['a', 'a'])).toBeNull();
    expect(attackActionSourceService.validateTargetCount(rule([64, 71]), ['a', 'b'])).toContain('1');
  });

  it('wide даёт помеху за каждую цель после первой', () => {
    expect(attackActionSourceService.extraTargetHitAdvantage(1)).toBe(0);
    expect(attackActionSourceService.extraTargetHitAdvantage(3)).toBe(-2);
    expect(attackActionSourceService.extraTargetHitModifiers(3)).toEqual([
      { source_code: 'circumstances', source_label: 'Обстоятельства', delta: -2 },
    ]);
  });

  it('ключ оружия различает экземпляр и код правила без id', () => {
    expect(attackActionSourceService.weaponKey({ ...profile('strike'), inventoryItemId: 7, instanceIndex: 0 })).toBe(
      'id:7:0',
    );
    expect(attackActionSourceService.weaponKey({ ...profile('strike'), itemRuleCode: 'kindzhal' })).toBe(
      'code:kindzhal:0',
    );
  });

  it('последовательные удары требуют разный экземпляр оружия', () => {
    const sequential = {
      ...rule([64, 71]),
      spec: {
        ...rule([64, 71]).spec,
        strike_count: 2,
        distinct_weapons: true,
      },
    };
    expect(attackActionSourceService.strikeCount(sequential)).toBe(2);
    expect(attackActionSourceService.isSequentialStrikes(sequential)).toBe(true);
    expect(
      attackActionSourceService.validateDistinctWeapons(sequential, [
        { ...profile('strike'), inventoryItemId: 1 },
        { ...profile('strike'), inventoryItemId: 1 },
      ]),
    ).toContain('другое оружие');
    expect(
      attackActionSourceService.validateDistinctWeapons(sequential, [
        { ...profile('strike'), inventoryItemId: 1 },
        { ...profile('strike'), inventoryItemId: 2, itemRuleCode: 'other' },
      ]),
    ).toBeNull();
    expect(
      attackActionSourceService.validateDistinctWeapons(sequential, [
        { ...profile('strike'), inventoryItemId: 1, instanceIndex: 0 },
        { ...profile('strike'), inventoryItemId: 1, instanceIndex: 1 },
      ]),
    ).toBeNull();
    expect(
      attackActionSourceService.nextDistinctWeaponProfile(
        [
          { ...profile('strike'), inventoryItemId: 1 },
          { ...profile('strike'), inventoryItemId: 2, itemName: 'second' },
        ],
        [{ ...profile('strike'), inventoryItemId: 1 }],
      )?.itemName,
    ).toBe('second');
    expect(
      attackActionSourceService
        .profilesForOtherWeapons(
          [
            { ...profile('strike'), inventoryItemId: 1 },
            { ...profile('strike'), inventoryItemId: 2, itemName: 'second' },
          ],
          [{ ...profile('strike'), inventoryItemId: 1 }],
          null,
        )
        .map((entry) => entry.itemName),
    ).toEqual(['second']);
  });

  it('сдвоенный удар требует одинаковые экземпляры и кап 2, навык снимает кап', () => {
    const paired = {
      ...rule([64, 71]),
      code: 'paired',
      spec: {
        ...rule([64, 71]).spec,
        same_weapon: true,
        min_weapons: 2,
        max_weapons: 2,
      },
    };
    const lift = {
      ...rule([]),
      code: 'lift',
      spec: {
        type: 'skill' as const,
        zones: {},
        requirements: [],
        grants: [],
        parent_ability_code: 'paired',
        lift_parent_max_weapons: true,
      },
    };
    const copies = [
      { ...profile('strike'), inventoryItemId: 1, instanceIndex: 0, itemRuleCode: 'ruka' },
      { ...profile('strike'), inventoryItemId: 1, instanceIndex: 1, itemRuleCode: 'ruka' },
      { ...profile('strike'), inventoryItemId: 2, instanceIndex: 0, itemRuleCode: 'noga' },
    ];
    expect(attackActionSourceService.isSameWeaponStrikes(paired)).toBe(true);
    expect(attackActionSourceService.isSequentialStrikes(paired)).toBe(false);
    expect(attackActionSourceService.maxWeapons(paired, { abilities: [] }, [paired, lift])).toBe(2);
    expect(
      attackActionSourceService.maxWeapons(paired, { abilities: [{ ruleCode: 'lift', level: 1 }] }, [paired, lift]),
    ).toBe(Number.POSITIVE_INFINITY);
    expect(attackActionSourceService.validateSameWeapons(paired, [copies[0], copies[2]])).toContain('одним оружием');
    expect(attackActionSourceService.validateSameWeapons(paired, [copies[0], copies[1]])).toBeNull();
    expect(attackActionSourceService.sameWeaponCheckModifiers(paired, 2)).toEqual([
      { source_code: 'multi_attack', source_label: 'множественная атака', delta: -2 },
    ]);
    expect(attackActionSourceService.preferredSameWeaponLead(copies, 2, copies[2])).toMatchObject({
      itemRuleCode: 'ruka',
    });
    expect(attackActionSourceService.hasUnusedSameWeaponCopy(copies, [copies[0]], 'ruka')).toBe(true);
    expect(attackActionSourceService.hasUnusedSameWeaponCopy(copies, [copies[2]], 'noga')).toBe(false);
  });

  it('не ставит подготовку в список атак даже при владении', () => {
    const strike = {
      ...rule([64, 71]),
      spec: {
        ...rule([64, 71]).spec,
        zones: { os: { kind: 'automatic' as const } },
      },
    };
    const preparation = {
      ...rule([226]),
      code: 'prep',
      name: 'Подготовка',
    };
    const overview = {
      abilities: [{ ruleCode: 'prep' }, { ruleCode: 'attack' }],
    } as CharacterOverview;

    expect(attackActionSourceService.list([strike, preparation], overview).map((source) => source.code)).toEqual([
      'attack',
    ]);
  });
});
