import { describe, expect, it } from 'vitest';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';

const sourceRuleCode = 'rule-fast-strike';

describe('ActionEffectService', () => {
  it('resolves a dimensional current attack characteristic modifier by attack component and hit', () => {
    const rule = {
      id: null,
      code: 'razmashistyy-udar',
      type: 'ability',
      name: 'Размашистый удар',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: 1767225600,
      spec: {
        type: 'action',
        zones: {},
        requirements: [],
        grants: [],
        parent_ability_code: null,
        action_components: [],
        action_effects: [
          {
            type: 'current_action_attack_characteristic_modifier',
            delta: 2,
            scope: { components: ['strike'], hit_count: 1 },
          },
        ],
      },
    } as Rule;

    expect(actionEffectService.currentAttackActionCharacteristicModifier(rule, 'strike')).toBe(2);
    expect(actionEffectService.currentAttackActionCharacteristicModifier(rule, 'shoot')).toBe(0);
    expect(
      actionEffectService.applyCurrentAttackActionCharacteristicModifier(rule, 'strike', { base: 5, size: 0 }),
    ).toEqual({ base: 4, size: 1 });
  });

  it('добавляет силу ребёнка при руках и типе урона', () => {
    const parent = {
      id: null,
      code: 'parent-sweep',
      type: 'ability',
      name: 'Размах',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: 1767225600,
      spec: {
        type: 'action',
        zones: {},
        requirements: [],
        grants: [],
        parent_ability_code: null,
        action_components: [],
        action_effects: [
          {
            type: 'current_action_attack_characteristic_modifier',
            delta: 2,
            scope: { components: ['strike'], hit_count: 1 },
          },
        ],
      },
    } as Rule;
    const child = {
      id: null,
      code: 'child-power',
      type: 'ability',
      name: 'Мощь',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: 1767225600,
      spec: {
        type: 'skill',
        zones: {},
        requirements: [],
        grants: [],
        parent_ability_code: 'parent-sweep',
        action_effects: [
          {
            type: 'current_action_attack_characteristic_modifier',
            delta: 1,
            scope: { components: ['strike'], hit_count: 1 },
            min_occupy_hands: 2,
            damage_type_codes: ['slashing', 'blunt'],
          },
        ],
      },
    } as Rule;
    const extra = actionEffectService.childActionEffects('parent-sweep', ['child-power'], [parent, child]);

    expect(
      actionEffectService.currentAttackActionCharacteristicModifier(parent, 'strike', 1, {
        occupyHands: 1,
        damageTypeCode: 'slashing',
        extraEffects: extra,
      }),
    ).toBe(2);
    expect(
      actionEffectService.currentAttackActionCharacteristicModifier(parent, 'strike', 1, {
        occupyHands: 2,
        damageTypeCode: 'piercing',
        extraEffects: extra,
      }),
    ).toBe(2);
    expect(
      actionEffectService.currentAttackActionCharacteristicModifier(parent, 'strike', 1, {
        occupyHands: 2,
        damageTypeCode: 'slashing',
        extraEffects: extra,
      }),
    ).toBe(3);
    expect(actionEffectService.childActionEffects('parent-sweep', [], [parent, child])).toEqual([]);

    const actor = {
      abilities: [{ ruleCode: 'child-power', level: 1 }],
      inventory: [{ id: 1, ruleCode: 'staff', quantity: 1, equipped: true, occupyHands: 2 }],
    };
    const staff = {
      id: null,
      code: 'staff',
      type: 'item',
      name: 'staff',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: 1767225600,
      spec: {
        category: 'equipment',
        cost_gm: 1,
        weight: null,
        special_rule_codes: [],
        occupy_hands: { min: 1, max: 2 },
        weapon: { min_strength: null, block_profile: null, weapon_profiles: [] },
      },
    } as Rule;
    expect(
      actionEffectService.describeForLaunch(
        parent,
        actor,
        { itemRuleCode: 'staff', profileType: 'strike', damageTypeCode: 'slashing' },
        [parent, child, staff],
      ),
    ).toEqual([
      '+2 к силе текущего удара (действие)',
      '+1 к силе текущего удара, если оружие в 2+ руках (рубящего урона, дробящего урона) (Мощь)',
      '+2 к силе удара (удержание)',
    ]);
    expect(
      actionEffectService.effectsChatSuffix(
        parent,
        actor,
        { itemRuleCode: 'staff', profileType: 'strike', damageTypeCode: 'slashing' },
        [parent, child, staff],
      ),
    ).toBe(
      '\nЭффекты: +2 к силе текущего удара (действие); +1 к силе текущего удара, если оружие в 2+ руках (рубящего урона, дробящего урона) (Мощь); +2 к силе удара (удержание)',
    );
  });

  it('applies next-action cost and consumes the effect on an attack', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode,
        effect: { type: 'next_action_attack_cost', resource_code: 'action-points', delta: 1 },
      },
    ];

    expect(
      actionEffectService.resolveForNextAction(pending, { isAttack: true, component: 'strike', baseCost: 2 }),
    ).toMatchObject({ actionCostDelta: 1, remainingEffects: [] });
  });

  it('loses a next-action effect when the next action is not an attack', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode,
        effect: {
          type: 'next_action_attack_target_characteristic_modifier',
          check_code: 'melee-combat',
          characteristic_code: 'dexterity',
          delta: -3,
          min: 0,
          max_total_action_cost: 2,
          scope: { components: ['strike'], hit_count: 1 },
        },
      },
    ];

    expect(
      actionEffectService.resolveForNextAction(pending, { isAttack: false, component: 'strike', baseCost: 1 })
        .remainingEffects,
    ).toEqual([]);
  });

  it('does not apply target modifier when final attack cost exceeds the limit', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode,
        effect: {
          type: 'next_action_attack_cost',
          resource_code: 'action-points',
          delta: 1,
        },
      },
      {
        sourceRuleCode,
        effect: {
          type: 'next_action_attack_target_characteristic_modifier',
          check_code: 'melee-combat',
          characteristic_code: 'dexterity',
          delta: -3,
          min: 0,
          max_total_action_cost: 2,
          scope: { components: ['strike'], hit_count: 1 },
        },
      },
    ];

    expect(
      actionEffectService.resolveForNextAction(pending, { isAttack: true, component: 'strike', baseCost: 2 })
        .targetDexterityMasteryDelta,
    ).toBe(0);
  });

  it('clamps target modifier and keeps the pending effect source', () => {
    const result = actionEffectService.resolveForNextAction(
      [
        {
          sourceRuleCode: 'rule-swift-strike',
          effect: {
            type: 'next_action_attack_target_characteristic_modifier',
            check_code: 'melee-combat',
            characteristic_code: 'dexterity',
            delta: -3,
            min: 0,
            max_total_action_cost: 2,
            scope: { components: ['strike'], hit_count: 1 },
          },
        },
      ],
      { isAttack: true, component: 'strike', baseCost: 1, targetDexterityMastery: 1 },
    );

    expect(result.targetDexterityMasteryDelta).toBe(-1);
    expect(result.targetDexterityMasteryAdjustments).toEqual([{ sourceRuleCode: 'rule-swift-strike', delta: -1 }]);
  });

  it('Стремительный: soak от Реакции только при атаке ≤2 ОД и первом ударе', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode: 'stremitelnyy-udar',
        effect: {
          type: 'next_action_attack_dodge_soak_from_reaction',
          max_total_action_cost: 2,
          scope: { components: ['strike'], hit_count: 1 },
        },
      },
    ];
    expect(
      actionEffectService.resolveForNextAction(pending, { isAttack: true, component: 'strike', baseCost: 2 }).dodgeSoakFromReaction,
    ).toBe(true);
    expect(
      actionEffectService.resolveForNextAction(pending, { isAttack: true, component: 'strike', baseCost: 3 }).dodgeSoakFromReaction,
    ).toBe(false);
    expect(
      actionEffectService.resolveForNextAction(pending, {
        isAttack: true,
        component: 'strike',
        baseCost: 2,
        hitNumber: 2,
      }).dodgeSoakFromReaction,
    ).toBe(false);
  });

  it('describes target effects in player-facing language', () => {
    expect(
      actionEffectService.describe({
        type: 'next_action_attack_target_characteristic_modifier',
        check_code: 'melee-combat',
        characteristic_code: 'dexterity',
        delta: -3,
        min: 0,
        max_total_action_cost: 2,
        scope: { components: ['strike'], hit_count: 1 },
      }),
    ).toBe(
      '-3 к Ближнему бою от Ловкости(вплоть до 0 от Ловкости) у цели для первого удара следующей атаки, если итоговая стоимость атаки не более 2 ОД (действие)',
    );
  });

  it('resolves current and pending hit disadvantages with the rule source', () => {
    const rule = {
      id: null,
      code: 'sweeping-strike',
      name: 'Размашистый удар',
    } as Rule;
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode: rule.code,
        effect: {
          type: 'after_action_until_resource_spent_check_modifier',
          resource_code: 'action-points',
          amount: 2,
          check_codes: ['check-hit'],
          delta: -2,
        },
      },
    ];

    expect(
      actionEffectService.currentActionCheckModifier(
        {
          ...rule,
          spec: {
            type: 'action',
            zones: {},
            requirements: [],
            grants: [],
            parent_ability_code: null,
            action_components: [],
            action_effects: [{ type: 'current_action_check_modifier', check_codes: ['check-hit'], delta: -2 }],
          },
        },
        'check-hit',
      ),
    ).toBe(-2);
    expect(actionEffectService.checkAdvantageModifiers(pending, 'check-hit')).toEqual([
      { source_code: 'circumstances', source_label: 'Обстоятельства', delta: -2 },
    ]);
    expect(actionEffectService.checkAdvantageModifiers(pending, 'hit')).toEqual([]);
  });

  it('считает бонус Силы урона от РУ с потолком', () => {
    const rule = {
      id: null,
      code: 'power-strike',
      type: 'ability',
      name: 'Силовой',
      description: '',
      spaceId: 1,
      keywordIds: [],
      mechanicId: null,
      createdAt: 1767225600,
      spec: {
        type: 'action',
        zones: {},
        requirements: [],
        grants: [],
        parent_ability_code: null,
        action_components: [],
        action_effects: [
          {
            type: 'current_action_attack_characteristic_from_success_rating',
            floor_div: 2,
            cap: 3,
            scope: { components: ['strike'], hit_count: 1 },
          },
        ],
      },
    } as Rule;

    expect(actionEffectService.successRatingAttackCharacteristicModifier(rule, 5, 'strike')).toBe(2);
    expect(actionEffectService.successRatingAttackCharacteristicModifier(rule, 8, 'strike')).toBe(3);
    expect(actionEffectService.successRatingAttackCharacteristicModifier(rule, 1, 'strike')).toBe(0);
  });

  it('consumes a duration effect by spending its resource', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode,
        effect: {
          type: 'after_action_until_resource_spent_check_modifier',
          resource_code: 'action-points',
          amount: 2,
          check_codes: ['check-hit'],
          delta: -1,
        },
      },
    ];

    const remaining = actionEffectService.consumeResource(pending, 'action-points', 1);
    expect(remaining[0]?.effect).toMatchObject({ amount: 1 });
    expect(actionEffectService.consumeResource(remaining, 'action-points', 1)).toEqual([]);
  });

  it('не-атака снимает снимок прошлого удара и оставляет налог 6 и 1', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode: 'tochnyy-udar',
        effect: {
          type: 'last_strike_snapshot',
          kind: 'other',
          hits: [{ targetKey: 'character:1', attackSr: 5 }],
        },
      },
      {
        sourceRuleCode: 'riskovannyy-udar',
        effect: {
          type: 'after_action_until_resource_spent_check_modifier',
          resource_code: 'action-points',
          amount: 2,
          check_codes: ['check-hit'],
          delta: -2,
          source_code: 'action',
          applies_to: 'attacker_hit',
        },
      },
    ];

    expect(
      actionEffectService.afterDeclaredAction(pending, 1, { isAttack: false, component: 'strike', baseCost: 1 }),
    ).toEqual([
      {
        sourceRuleCode: 'riskovannyy-udar',
        effect: expect.objectContaining({ type: 'after_action_until_resource_spent_check_modifier', amount: 1 }),
      },
    ]);
    expect(
      actionEffectService.resolveForNextAction(pending, { isAttack: true, component: 'strike', baseCost: 3 })
        .remainingEffects,
    ).toHaveLength(2);
  });

  it('трата ОД не-атакой снимает надбавку следующего удара', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode,
        effect: { type: 'next_action_attack_cost', resource_code: 'action-points', delta: 1 },
      },
    ];

    expect(
      actionEffectService.afterDeclaredAction(pending, 2, { isAttack: false, component: 'strike', baseCost: 2 }),
    ).toEqual([]);
  });

  it('точность текущего удара суммируется по strike', () => {
    const rule = {
      spec: {
        type: 'action',
        action_effects: [
          {
            type: 'current_action_attack_accuracy',
            delta: 1,
            scope: { components: ['strike'], hit_count: 1 },
          },
        ],
      },
    } as Rule;

    expect(actionEffectService.currentAttackAccuracy(rule, 'strike')).toBe(1);
    expect(actionEffectService.currentAttackAccuracy(rule, 'throw')).toBe(0);
  });

  it('режет бонус мастерства цели от Восприятия текущего удара до 0', () => {
    const rule = {
      id: null,
      code: 'directed-strike',
      spec: {
        type: 'action',
        action_effects: [
          {
            type: 'current_action_attack_target_characteristic_modifier',
            check_code: 'melee-combat',
            characteristic_code: 'perception',
            delta: -3,
            min: 0,
            scope: { components: ['strike'], hit_count: 1 },
          },
        ],
      },
    } as Rule;

    expect(actionEffectService.currentAttackTargetCharacteristicModifier(rule, 'strike', 'perception', 1)).toEqual({
      delta: -1,
      adjustments: [{ sourceRuleCode: 'directed-strike', delta: -1 }],
    });
    expect(actionEffectService.currentAttackTargetCharacteristicModifier(rule, 'strike', 'perception', 0).delta).toBe(0);
    expect(actionEffectService.currentAttackTargetCharacteristicModifier(rule, 'throw', 'perception', 4).delta).toBe(0);
  });
});
