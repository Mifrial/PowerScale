import { describe, it, expect } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import { CharacterOverviewService } from '@/modules/Roleplay/Character/Service/Overview/CharacterOverviewService';

function armorRule(
  id: string,
  code: string,
  name: string,
  defenseSlots: { defense: { base: number; size: number }; durability: number; source_code: string | null }[],
): Rule {
  return {
    id,
    code,
    type: 'item',
    name,
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
    spec: {
      category: 'equipment',
      cost_gm: null,
      weight: null,
      special_rule_codes: [],
      armor: { defense_slots: defenseSlots, resistance_slots: [], characteristic_limits: [] },
    },
  };
}

function version(inventory: InventoryItem[]): CharacterVersion {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleId: null,
    characteristics: [],
    resources: [],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 0 },
    money: 0,
    ageYears: null,
    inventory,
    states: [],
    senses: [],
  };
}

describe('CharacterOverviewService: ступени защиты по надёжности', () => {
  it('строит ступени: доспех 5/надёжн. 3 + поддоспешник 3/надёжн. 2', () => {
    const rules: Rule[] = [
      armorRule('rule-armor', 'steel-armor', 'Стальной доспех', [
        { defense: { base: 5, size: 0 }, durability: 3, source_code: 'armor-source' },
      ]),
      armorRule('rule-gambeson', 'gambeson', 'Поддоспешник', [
        { defense: { base: 3, size: 0 }, durability: 2, source_code: null },
      ]),
    ];
    const service = new CharacterOverviewService();
    const defense = service.build(
      version([
        { id: 1, ruleId: 'rule-armor', quantity: 1, equipped: true },
        { id: 2, ruleId: 'rule-gambeson', quantity: 1, equipped: true },
      ]),
      rules,
    ).defense;

    expect(defense?.constantDefense).toBe(8);
    expect(defense?.tiers).toEqual([
      { threshold: 2, defense: 8 },
      { threshold: 3, defense: 5 },
    ]);
    expect(defense?.armor.map((armor) => ({ name: armor.itemName, tiers: armor.tiers }))).toEqual([
      { name: 'Стальной доспех', tiers: [{ threshold: 3, defense: 5 }] },
      { name: 'Поддоспешник', tiers: [{ threshold: 2, defense: 3 }] },
    ]);
  });

  it('защиты одного источника не суммируются: берётся максимум', () => {
    const rules: Rule[] = [
      armorRule('rule-armor', 'steel-armor', 'Стальной доспех', [
        { defense: { base: 5, size: 0 }, durability: 3, source_code: 'armor-source' },
        { defense: { base: 2, size: 0 }, durability: 3, source_code: 'armor-source' },
      ]),
    ];
    const service = new CharacterOverviewService();
    const defense = service.build(
      version([{ id: 1, ruleId: 'rule-armor', quantity: 1, equipped: true }]),
      rules,
    ).defense;

    expect(defense?.constantDefense).toBe(5);
    expect(defense?.tiers).toEqual([{ threshold: 3, defense: 5 }]);
    expect(defense?.armor[0].tiers).toEqual([{ threshold: 3, defense: 5 }]);
  });

  it('не экипированные предметы не учитываются в защите', () => {
    const rules: Rule[] = [
      armorRule('rule-armor', 'steel-armor', 'Стальной доспех', [
        { defense: { base: 5, size: 0 }, durability: 3, source_code: 'armor-source' },
      ]),
    ];
    const service = new CharacterOverviewService();
    const defense = service.build(
      version([{ id: 1, ruleId: 'rule-armor', quantity: 1, equipped: false }]),
      rules,
    ).defense;

    expect(defense).toBeNull();
  });
});
