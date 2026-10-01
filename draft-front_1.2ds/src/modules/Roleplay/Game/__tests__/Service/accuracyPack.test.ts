import { describe, expect, it } from 'vitest';
import { durabilityShaveService } from '@/modules/Roleplay/Game/Service/Instance/durabilityShaveService';
import { lastStrikeService } from '@/modules/Roleplay/Game/Service/Instance/lastStrikeService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { rollScoreAdjustService } from '@/modules/Roleplay/Game/Service/Instance/rollScoreAdjustService';
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
    mechanics: [],
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

  it('замена снимка прошлого удара сохраняет pending-эффекты заклинания', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode: 'interstructure-energy-transfer',
        effect: {
          type: 'next_spell_cast_difficulty',
          delta: -1,
          source_code: 'training',
          max_total_action_cost: 5,
        },
      },
    ];

    const next = lastStrikeService.replaceOnPending(
      pending,
      { kind: 'other', hits: [{ targetKey: 'character:1', attackSr: 2 }] },
      'simple-touch',
    );

    expect(next).toHaveLength(2);
    expect(next.some((item) => item.effect.type === 'next_spell_cast_difficulty')).toBe(true);
    expect(next.some((item) => item.effect.type === 'last_strike_snapshot')).toBe(true);
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

  it('критический: гейт по повреждениям, цепочка открыта, один удар', () => {
    const rule = ability([
      { type: 'require_previous_attack', same_target: true, all_damaged: true, single_strike: true },
    ]);
    const damaged = {
      kind: 'other' as const,
      hits: [{ targetKey: 'character:1', attackSr: 2, damaged: true }],
    };
    expect(lastStrikeService.canFollowUp(rule, damaged, 'character:1')).toBe(true);
    expect(lastStrikeService.canFollowUp(rule, { ...damaged, kind: 'lethal' }, 'character:1')).toBe(true);
    expect(
      lastStrikeService.canFollowUp(
        rule,
        { kind: 'other', hits: [{ targetKey: 'character:1', attackSr: 3, damaged: false }] },
        'character:1',
      ),
    ).toBe(false);
    expect(
      lastStrikeService.canFollowUp(
        rule,
        {
          kind: 'other',
          hits: [
            { targetKey: 'character:1', attackSr: 2, damaged: true },
            { targetKey: 'character:2', attackSr: 1, damaged: false },
          ],
        },
        'character:1',
      ),
    ).toBe(false);
    expect(lastStrikeService.requiresSingleStrike(rule)).toBe(true);
  });

  it('критический: хвост −1 за 1 той же цели и сгорает иначе', () => {
    const hanging: PendingActionEffect[] = [
      {
        sourceRuleCode: 'crit',
        effect: {
          type: 'next_action_attack_score_adjust',
          oneDelta: -1,
          faceDelta: 0,
          same_target: true,
          targetKey: 'character:1',
        },
      },
    ];
    const same = actionEffectService.resolveForNextAction(hanging, {
      isAttack: true,
      component: 'strike',
      baseCost: 4,
      targetKeys: ['character:1'],
    });
    expect(same.remainingEffects).toEqual([]);
    expect(actionEffectService.pendingHitScoreAdjust(same.hitScoreAdjusts, 'character:1')).toEqual({
      oneDelta: -1,
      faceDelta: 0,
    });
    const other = actionEffectService.resolveForNextAction(hanging, {
      isAttack: true,
      component: 'strike',
      baseCost: 4,
      targetKeys: ['character:2'],
    });
    expect(other.remainingEffects).toEqual([]);
    expect(actionEffectService.pendingHitScoreAdjust(other.hitScoreAdjusts, 'character:2')).toEqual({
      oneDelta: 0,
      faceDelta: 0,
    });
    expect(
      actionEffectService.resolveForNextAction(hanging, { isAttack: false, component: 'strike', baseCost: 1 })
        .remainingEffects,
    ).toEqual([]);
  });

  it('критический: ремап 5→6 и 2→1 до правила 6 и 1', () => {
    const context = {
      diceCount: 3,
      dieFaces: 6,
      efficiency: 3,
      advantages: [],
      poolSize: 3,
      rolls: [5, 2, 4],
      adjustedRolls: [5, 2, 4],
      droppedRolls: [],
      successes: [0, 1, 0],
      totalSuccesses: 1,
      applied: ['six_one_rule'],
    };
    expect(
      rollScoreAdjustService.remap(context, [
        { from: 5, to: 6 },
        { from: 2, to: 1 },
      ]),
    ).toBe(true);
    expect(context.adjustedRolls).toEqual([6, 1, 4]);
    expect(context.successes).toEqual([-1, 2, 0]);
  });

  it('критический: ремап накладывает 6 и 1 даже если оно не стреляло на исходных гранях', () => {
    const context = {
      diceCount: 2,
      dieFaces: 6,
      efficiency: 4,
      advantages: [],
      poolSize: 2,
      rolls: [5, 2],
      adjustedRolls: [5, 2],
      droppedRolls: [],
      successes: [0, 1],
      totalSuccesses: 1,
      applied: [],
    };
    expect(
      rollScoreAdjustService.remap(context, [
        { from: 5, to: 6 },
        { from: 2, to: 1 },
      ]),
    ).toBe(true);
    expect(context.adjustedRolls).toEqual([6, 1]);
    expect(context.successes).toEqual([-1, 2]);
  });
});
