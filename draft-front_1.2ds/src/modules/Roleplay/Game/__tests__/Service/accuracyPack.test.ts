import { describe, expect, it } from 'vitest';
import { durabilityShaveService } from '@/modules/Roleplay/Game/Service/Instance/durabilityShaveService';
import { lastStrikeService } from '@/modules/Roleplay/Game/Service/Instance/lastStrikeService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import {
  DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR,
  DAMAGE_TYPE_HOOK_VERSION_1,
} from '@/modules/Roleplay/Rule/Constant/Damage/DAMAGE_TYPE_HOOKS';

function ability(effects: object[]): Rule {
  return {
    id: null,
    code: 'pack',
    type: 'ability',
    name: 'Пачка',
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    createdAt: 1,
    spec: {
      type: 'action',
      zones: {},
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [],
      action_effects: effects,
    },
  } as Rule;
}

describe('Точность §2.3', () => {
  it('срез надёжности: единицы и короткое только при попадании', () => {
    expect(durabilityShaveService.shave(2, true, true)).toBe(3);
    expect(durabilityShaveService.shave(2, false, true)).toBe(2);
    expect(durabilityShaveService.shave(2, true, false)).toBe(0);
    expect(durabilityShaveService.oneCount([1, 4, 1, 6])).toBe(2);
  });

  it('срез поднимает порог игнора слоя без хука РУ', () => {
    const result = attackDamageService.applyAttackDamage({
      weaponDamage: { base: 4, size: 0 },
      sr: 1,
      damageTypeCode: 'piercing',
      defense: {
        armor: [
          {
            itemRuleCode: 'a',
            itemName: 'A',
            href: '',
            lines: [
              {
                kind: 'defense',
                value: 3,
                valueLabel: '3',
                durability: 6,
                sourceCode: 'armor',
                sourceLabel: 'доспех',
                damageTypeLabel: null,
                damageTypeDative: null,
                damageTypeCode: null,
              },
            ],
            tiers: [],
          },
        ],
        constantDefense: 0,
        tiers: [],
        shield: null,
      },
      endurance: 10,
      hooks: [
        {
          ruleCode: 'x',
          mechanicCode: DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR,
          version: DAMAGE_TYPE_HOOK_VERSION_1,
          phase: 'attack',
        },
      ],
      durabilityShave: 5,
    });
    expect(result.layers[0]?.ignored).toBe(true);
    expect(result.durabilityShave).toBe(5);
  });

  it('смертельный: гейт и потолок удвоения', () => {
    const rule = ability([
      { type: 'require_previous_strike', min_sr: 4, not_kind: 'lethal', same_target: true },
      { type: 'attack_sr_from_previous', floor_div: 2, cap: 'double_this' },
    ]);
    const snapshot = { kind: 'other' as const, hits: [{ targetKey: 'character:1', attackSr: 4 }] };
    expect(lastStrikeService.canFollowUp(rule, snapshot, 'character:1')).toBe(true);
    expect(lastStrikeService.canFollowUp(rule, snapshot, 'character:2')).toBe(false);
    expect(lastStrikeService.canFollowUp(rule, snapshot, null)).toBe(false);
    expect(lastStrikeService.followUpTargetKeys(rule, snapshot)).toEqual(['character:1']);
    expect(
      lastStrikeService.excludeForSelect(rule, snapshot, 'character:0', ['character:0', 'character:1', 'character:2']),
    ).toEqual(['character:0', 'character:2']);
    expect(lastStrikeService.canFollowUp(rule, { ...snapshot, kind: 'lethal' }, 'character:1')).toBe(false);
    expect(lastStrikeService.boostedSr(2, 6, rule)).toBe(4);
    expect(lastStrikeService.boostedSr(5, 6, rule)).toBe(8);
    expect(lastStrikeService.boostedSr(0, 6, rule)).toBe(0);
    expect(lastStrikeService.describe(snapshot, (key) => (key === 'character:1' ? 'Цель' : key))).toBe(
      'прошлый удар (Цель: попал, РУ 4)',
    );
    expect(
      lastStrikeService.describe({ kind: 'other', hits: [{ targetKey: 'npc:2', attackSr: 0 }] }, () => 'Гоблин'),
    ).toBe('прошлый удар (Гоблин: промах)');
  });

  it('срез только на режущем, рубящем и колющем', () => {
    const rule = ability([
      {
        type: 'current_action_durability_shave',
        short_extra_on_first_one: true,
        scope: { components: ['strike'], hit_count: 1 },
        damage_type_codes: ['cutting', 'slashing', 'piercing'],
      },
    ]);
    expect(actionEffectService.requiredDamageTypeCodes(rule)).toEqual(['cutting', 'slashing', 'piercing']);
    expect(actionEffectService.currentDurabilityShave(rule, 'strike', 1, 'piercing').enabled).toBe(true);
    expect(actionEffectService.currentDurabilityShave(rule, 'strike', 1, 'blunt').enabled).toBe(false);
  });

  it('рискованный pending не зеркалится в защиту', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode: 'risk',
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
    expect(actionEffectService.checkAdvantageModifiers(pending, 'check-hit', 'attacker')[0]?.delta).toBe(-2);
    expect(actionEffectService.checkAdvantageModifiers(pending, 'check-hit', 'defender')).toEqual([]);
    expect(
      actionEffectService.resolveForNextAction(pending, { isAttack: true, component: 'strike', baseCost: 3 })
        .remainingEffects,
    ).toHaveLength(1);
  });

  it('снимок прошлого удара сгорает на не-атаке и держится до следующей атаки', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode: 'strike',
        effect: {
          type: 'last_strike_snapshot',
          kind: 'other',
          hits: [{ targetKey: 'character:1', attackSr: 5 }],
        },
      },
    ];
    expect(
      actionEffectService.resolveForNextAction(pending, { isAttack: true, component: 'strike', baseCost: 3 })
        .remainingEffects,
    ).toHaveLength(1);
    expect(
      actionEffectService.afterDeclaredAction(pending, 1, { isAttack: false, component: 'strike', baseCost: 1 }),
    ).toEqual([]);
    expect(
      lastStrikeService.describe({ kind: 'other', hits: [{ targetKey: 'character:1', attackSr: 5 }] }, () => 'Цель'),
    ).toBe('прошлый удар (Цель: попал, РУ 5)');
  });
});
