import { describe, expect, it } from 'vitest';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { defenseCounterService } from '@/modules/Roleplay/Game/Service/Instance/defenseCounterService';
import { lastStrikeService } from '@/modules/Roleplay/Game/Service/Instance/lastStrikeService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';

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

describe('Выпад и противодействие защите', () => {
  it('выпад даёт полшага атакующего, не пол-ипари', () => {
    const rule = ability([
      {
        type: 'current_action_attack_reach',
        step_fraction: 0.5,
        scope: { components: ['strike'], hit_count: 1 },
      },
    ]);
    expect(actionEffectService.currentAttackReach(rule, 'strike')).toBe(0.5);
    expect(actionEffectService.currentAttackReach(rule, 'strike', 1, { base: 1, size: -1 })).toBe(0.25);
    expect(actionEffectService.currentAttackReach(rule, 'strike', 1, { base: 1, size: 1 })).toBe(1);
    expect(actionEffectService.currentAttackReach(rule, 'throw')).toBe(0);
  });

  it('подготовка даёт преимущество на ту же защиту и помеху на другую', () => {
    const pending: PendingActionEffect[] = [
      {
        sourceRuleCode: 'counter',
        effect: { type: 'prepared_defense_counter', targetKey: 'npc:1', reaction: 'dodge' },
      },
    ];
    expect(defenseCounterService.hitModifier(pending, 'npc:1', 'dodge')?.delta).toBe(1);
    expect(defenseCounterService.hitModifier(pending, 'npc:1', 'block')?.delta).toBe(-1);
    expect(defenseCounterService.hitModifier(pending, 'npc:2', 'dodge')).toBeNull();
    expect(
      actionEffectService.afterDeclaredAction(pending, 1, { isAttack: false, component: 'strike', baseCost: 1 }),
    ).toEqual(pending);
  });

  it('повторная подготовка заменяет старую, гейт только сразу после атаки', () => {
    const rule = ability([{ type: 'require_previous_attack', same_target: true }]);
    const snapshot = {
      kind: 'other' as const,
      hits: [{ targetKey: 'character:1', attackSr: 0, reaction: 'block' }],
    };
    expect(lastStrikeService.canFollowUp(rule, snapshot, 'character:1')).toBe(true);
    expect(lastStrikeService.canFollowUp(rule, snapshot, 'character:2')).toBe(false);
    expect(lastStrikeService.canFollowUp(rule, null, 'character:1')).toBe(false);
    const first = defenseCounterService.replaceOnPending([], { targetKey: 'a', reaction: 'dodge' }, 'counter');
    const second = defenseCounterService.replaceOnPending(first, { targetKey: 'b', reaction: 'block' }, 'counter');
    expect(defenseCounterService.of(second)?.targetKey).toBe('b');
    expect(second).toHaveLength(1);
  });
});
