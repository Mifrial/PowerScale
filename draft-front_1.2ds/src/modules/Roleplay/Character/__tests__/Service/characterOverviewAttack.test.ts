import { describe, it, expect } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CharacterOverviewService } from '@/modules/Roleplay/Character/Service/Overview/CharacterOverviewService';

const dim = (base: number, size = 0) => ({ base, size });

const base = (id: string, code: string, type: Rule['type'], name: string, spec?: Rule['spec']): Rule => ({
  id,
  code,
  type,
  name,
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  createdAt: '2026-01-01T00:00:00Z',
  spec,
});

const rules: Rule[] = [
  base('rule-strength', 'strength', 'characteristic', 'Сила', { type: 'characteristic', group: 'primary' }),
  base('rule-halberd', 'alebarda', 'item', 'Алебарда', {
    category: 'equipment',
    cost_gm: 1500,
    weight: { base: 3.5, size: 0 },
    special_rule_codes: [],
    weapon: {
      min_strength: dim(3, 2),
      durability: dim(5, 3),
      block_profile: { efficiency: dim(4), defense: { base: 5, size: 0 }, resistances: [] },
      weapon_profiles: [
        {
          type: 'strike',
          distance: { type: 'dimensional', base: 1, size: 0 },
          range: null,
          damage: {
            formula: {
              type: 'actionCharacteristic',
              action: 'strike',
              characteristic: 'strength',
              modifier: [{ delta: -1, source_code: null, source_label: null }],
            },
            damage_type_code: 'slashing',
          },
          penetration: {
            type: 'actionCharacteristic',
            action: 'strike',
            characteristic: 'strength',
            modifier: [{ delta: -3, source_code: null, source_label: null }],
          },
          accuracy: dim(3),
        },
      ],
    },
  }),
];

function versionWith(overrides: Partial<CharacterVersion> = {}): CharacterVersion {
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
    inventory: [],
    states: [],
    senses: [],
    ...overrides,
  };
}

const service = new CharacterOverviewService();

describe('CharacterOverviewService: формулы атак', () => {
  it('считает урон/пробитие от ИТОГОВОГО значения Силы (база + модификаторы)', () => {
    // Сила: база {3|0} +2 (тренировка) → итог {5|0} (5). «Сила − 1» → 4, «Сила − 3» → 5↓.
    const version = versionWith({
      characteristics: [
        {
          ruleId: 'rule-strength',
          base: dim(3),
          modifiers: [{ sourceRuleId: null, sourceLabel: 'Тренировка', delta: 2, target: 'strength', scope: null }],
        },
      ],
      inventory: [{ id: 1, ruleId: 'rule-halberd', quantity: 1, equipped: true }],
    });

    const overview = service.build(version, rules);

    expect(overview.attacks).toHaveLength(1);
    expect(overview.attacks[0].damageLabel).toBe('4 рубящего урона');
    expect(overview.attacks[0].damageFormula).toBe('Сила − 1 (для strike)');
    expect(overview.attacks[0].penetrationLabel).toBe('5↓ пробития');
    expect(overview.attacks[0].reach).toBe(1);
    expect(overview.attacks[0].minDistance).toBe(1);
    expect(overview.attacks[0].falloff).toEqual({ base: 5, size: 0 });
  });

  it('без модификаторов база равна итогу (регрессия: не падает)', () => {
    const version = versionWith({
      characteristics: [{ ruleId: 'rule-strength', base: dim(5), modifiers: [] }],
      inventory: [{ id: 1, ruleId: 'rule-halberd', quantity: 1, equipped: true }],
    });

    const overview = service.build(version, rules);

    expect(overview.attacks[0].damageLabel).toBe('4 рубящего урона');
  });
});

describe('CharacterOverviewService: потолки экипировки', () => {
  const limitRules: Rule[] = [
    base('rule-attention', 'attention', 'characteristic', 'Внимательность', {
      type: 'characteristic',
      group: 'primary',
    }),
    base('rule-reaction', 'reaction', 'characteristic', 'Реакция', { type: 'characteristic', group: 'primary' }),
    base('rule-perception', 'perception', 'characteristic', 'Восприятие', {
      type: 'characteristic',
      formula: 'min(attention, reaction)',
      group: 'primary',
    }),
    base('rule-shield', 'klassicheskiy-shchit', 'item', 'Классический щит'),
  ];

  it('limit {3|-1} пишется как 3↓, а не 3; снятие щита убирает потолок и пересчитывает производную', () => {
    const version = versionWith({
      characteristics: [
        { ruleId: 'rule-attention', base: dim(3), modifiers: [] },
        {
          ruleId: 'rule-reaction',
          base: dim(3),
          modifiers: [
            {
              sourceRuleId: 'rule-shield',
              sourceLabel: null,
              delta: 0,
              target: 'reaction',
              scope: null,
              limit: dim(3, -1),
              limitFormula: 'Сила − 3',
            },
          ],
        },
        { ruleId: 'rule-perception', base: dim(3, -1), modifiers: [] },
      ],
      inventory: [{ id: 1, ruleId: 'rule-shield', quantity: 1, equipped: true }],
    });
    const equipped = service.build(version, limitRules);
    expect(equipped.characteristics.find((item) => item.ruleId === 'rule-reaction')?.valueLabel).toBe('3↓');
    expect(equipped.characteristics.find((item) => item.ruleId === 'rule-perception')?.valueLabel).toBe('3↓');

    const unequipped = service.build(
      { ...version, inventory: [{ id: 1, ruleId: 'rule-shield', quantity: 1, equipped: false }] },
      limitRules,
    );
    expect(unequipped.characteristics.find((item) => item.ruleId === 'rule-reaction')?.valueLabel).toBe('3');
    expect(unequipped.characteristics.find((item) => item.ruleId === 'rule-perception')?.valueLabel).toBe('3');
    expect(
      unequipped.characteristics.find((item) => item.ruleId === 'rule-reaction')?.modifiers.some((item) => item.limit),
    ).toBe(false);
  });
});
