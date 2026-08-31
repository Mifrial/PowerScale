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
      createdAt: '2026-01-01T00:00:00Z',
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
      '-3 к Ближнему бою от Ловкости(вплоть до 0 от Ловкости) у цели для первого удара следующей атаки, если итоговая стоимость атаки не более 2 ОД',
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
});
