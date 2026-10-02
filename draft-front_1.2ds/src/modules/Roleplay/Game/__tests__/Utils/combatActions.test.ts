import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  defenseOdCost,
  actionOdCost,
  actionUsesChosenCost,
  asActionAbilitySpec,
  asProcessAbilitySpec,
  attackActionById,
  defaultTouchAction,
  listAttackActions,
  reactionOdCost,
  resourceCosts,
  SIMPLE_MELEE_ATTACK_CODE,
  SIMPLE_RANGED_ATTACK_CODE,
} from '@/modules/Roleplay/Game/Utils/combatActions';
import type { CombatActionRole } from '@/modules/Roleplay/Game/Utils/combatActions';

function ability(id: number, code: string, name: string, keywordIds: number[], od: number, automatic: boolean): Rule {
  return {
    id,
    code,
    type: 'ability',
    name,
    description: '',
    spaceId: 1,
    spec: {
      type: 'action',
      zones: automatic ? { os: { kind: 'automatic' as const } } : { os: { kind: 'array' as const, levels_cost: [1] } },
      requirements: [],
      grants: [],
      action_components: [{ type: 'resource', resource_code: 'action-points', amount: od }],
      parent_ability_code: null,
    },
    keywordIds,
    mechanics: [],
    createdAt: 1767225600,
  };
}

function withRole(rule: Rule, role: CombatActionRole): Rule {
  const spec = rule.spec;
  if (!spec || spec.type !== 'action') return rule;

  return { ...rule, spec: { ...spec, combat_action: role } };
}

describe('combatActions', () => {
  const melee = ability(1, SIMPLE_MELEE_ATTACK_CODE, 'Простая атака (ближний бой)', [14, 71, 1, 20], 3, true);
  const ranged = ability(2, SIMPLE_RANGED_ATTACK_CODE, 'Простая атака (дальний бой)', [14, 71, 2, 20], 3, true);
  const extra = ability(3, 'power-strike', 'Мощный удар', [14, 71, 1], 4, false);
  const dodge = withRole(ability(4, 'dodge', 'Уклонение', [14, 53, 20], 1, true), 'dodge');
  const block = withRole(ability(5, 'block', 'Блок', [14, 53, 20], 2, true), 'block');
  const turn = withRole(ability(6, 'turn', 'Поворот', [14, 53], 1, true), 'turn');
  const pool: Rule = {
    id: 18,
    code: 'action-points',
    type: 'resource',
    name: 'Очки действий',
    description: '',
    spaceId: 1,
    spec: { is_dimensional: false, auto_add: true },
    mechanics: [],
    createdAt: 1767225600,
  };
  const rules = [pool, melee, ranged, extra, dodge, block, turn];

  it('автоматические атаки доступны без записи на листе', () => {
    const strike = listAttackActions(rules, null, 'strike');
    expect(strike.map((item) => item.code)).toEqual([SIMPLE_MELEE_ATTACK_CODE]);
    expect(strike[0]?.odCost).toBe(3);
    const shoot = listAttackActions(rules, null, 'shoot');
    expect(shoot.map((item) => item.code)).toEqual([SIMPLE_RANGED_ATTACK_CODE]);
  });

  it('взятая атака с листа добавляется к автоматическим', () => {
    const options = listAttackActions(rules, { abilities: [{ ruleCode: 'power-strike' }] } as never, 'strike');
    expect(options.map((item) => item.code)).toEqual([SIMPLE_MELEE_ATTACK_CODE, 'power-strike']);
    expect(options.find((item) => item.code === 'power-strike')?.odCost).toBe(4);
    expect(options.find((item) => item.code === 'power-strike')?.ruleCode).toBe('power-strike');
  });

  it('attackActionById находит по code', () => {
    expect(attackActionById(rules, SIMPLE_MELEE_ATTACK_CODE)?.name).toBe('Простая атака (ближний бой)');
    expect(attackActionById(rules, '1')).toBeNull();
    expect(attackActionById(rules, SIMPLE_MELEE_ATTACK_CODE)?.ruleCode).toBe(SIMPLE_MELEE_ATTACK_CODE);
  });

  it('asActionAbilitySpec / asProcessAbilitySpec не падают на пустом правиле', () => {
    expect(asActionAbilitySpec(null)).toBeNull();
    expect(asProcessAbilitySpec(undefined)).toBeNull();
  });

  it('стартовое касание только у роли simple-touch', () => {
    const touch = withRole(ability(7, 'poke', 'Касание', [14, 71, 1], 3, true), 'simple-touch');
    const plain = ability(8, 'simple-touch', 'Простое касание', [14, 71, 1], 3, true);

    expect(defaultTouchAction([pool, melee, touch], null)?.code).toBe('poke');
    expect(defaultTouchAction([pool, melee, touch], null)?.odCost).toBe(3);
    expect(defaultTouchAction([pool, melee, plain], null)).toBeNull();
  });

  it('ОД реакций из спеки действия', () => {
    expect(reactionOdCost('ignore', rules)).toBe(0);
    expect(reactionOdCost('dodge', rules)).toBe(1);
    expect(reactionOdCost('block', rules)).toBe(2);
    expect(defenseOdCost('dodge', true, rules)).toBe(2);
    expect(defenseOdCost('ignore', true, rules)).toBe(0);
  });

  it('распознаёт выбираемую стоимость действия', () => {
    const components = [
      {
        type: 'resource' as const,
        resource_code: 'action-points',
        amount: { type: 'chosen' as const, max: 'available' as const },
      },
    ];

    expect(actionUsesChosenCost(components)).toBe(true);
    expect(actionOdCost(components)).toBe(0);
  });

  it('чужой ресурс не входит в ОД и входит в группы', () => {
    const components = [
      { type: 'resource' as const, resource_code: 'action-points', amount: 2 },
      { type: 'resource' as const, resource_code: 'concentration', amount: 1 },
      {
        type: 'resource' as const,
        resource_code: 'concentration',
        amount: { type: 'chosen' as const, max: 'available' as const },
      },
    ];

    expect(actionOdCost(components, 4, 'action-points')).toBe(2);
    expect(resourceCosts(components, 4)).toEqual([
      { resourceCode: 'action-points', amount: 2 },
      { resourceCode: 'concentration', amount: 1 },
    ]);
  });
});
