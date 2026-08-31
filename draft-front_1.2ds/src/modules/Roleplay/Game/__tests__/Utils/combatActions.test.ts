import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  defenseOdCost,
  actionOdCost,
  actionUsesChosenCost,
  attackActionById,
  listAttackActions,
  reactionOdCost,
  SIMPLE_MELEE_ATTACK_CODE,
  SIMPLE_RANGED_ATTACK_CODE,
} from '@/modules/Roleplay/Game/Utils/combatActions';

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
    mechanicId: null,
    createdAt: '2026-01-01T00:00:00Z',
  };
}

describe('combatActions', () => {
  const melee = ability(1, SIMPLE_MELEE_ATTACK_CODE, 'Простая атака (ближний бой)', [14, 71, 1, 20], 3, true);
  const ranged = ability(2, SIMPLE_RANGED_ATTACK_CODE, 'Простая атака (дальний бой)', [14, 71, 2, 20], 3, true);
  const extra = ability(3, 'power-strike', 'Мощный удар', [14, 71, 1], 4, false);
  const dodge = ability(4, 'dodge', 'Уклонение', [14, 53, 20], 1, true);
  const block = ability(5, 'block', 'Блок', [14, 53, 20], 2, true);
  const rules = [melee, ranged, extra, dodge, block];

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
});
