import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { spellCastUpgradeService } from '@/modules/Roleplay/Game/Service/Instance/spellCastUpgradeService';

const careful: Rule = {
  id: null,
  code: 'careful-magic',
  type: 'ability',
  name: 'Аккуратное волшебство',
  description: '',
  spaceId: 1,
  spec: {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [1] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    multiple: true,
    domain_ref: 'magic-path',
    spell_upgrade: { action_point_delta: 1, check_advantage: 1 },
  },
  createdAt: 1,
};

const chain: Rule = {
  id: null,
  code: 'chain-lightning',
  type: 'ability',
  name: 'Цепная молния',
  description: '',
  spaceId: 1,
  spec: {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [1] } },
    requirements: [],
    grants: [],
    parent_ability_code: 'lightning-strike',
    multiple: true,
    spell_upgrade: {
      action_point_delta: 1,
      chain: {
        damage_size_per_hop: 1,
        min: { base: 3, size: -1 },
        retarget: 'from_last_hit',
        same_target: 'via_other',
      },
    },
  },
  createdAt: 1,
};

describe('SpellCastUpgradeService', () => {
  it('Аккуратное только на выбранный путь', () => {
    const abilities = [
      { ruleCode: 'careful-magic', level: 1, domain: 'Арканист', domainCode: 'arcanist' },
      { ruleCode: 'careful-magic', level: 1, domain: 'Шаман', domainCode: 'shaman' },
    ];
    const listed = spellCastUpgradeService.listApplicable(abilities, 'arcanist', 'discharge', [careful]);
    expect(listed).toHaveLength(1);
    expect(listed[0]?.ruleCode).toBe('careful-magic');
    expect(spellCastUpgradeService.listApplicable(abilities, 'psionic', 'discharge', [careful])).toEqual([]);
  });

  it('Цепная только на Удар молнии', () => {
    const abilities = [{ ruleCode: 'chain-lightning', level: 1 }];
    expect(
      spellCastUpgradeService
        .listApplicable(abilities, 'arcanist', 'lightning-strike', [chain])
        .map((item) => item.ruleCode),
    ).toEqual(['chain-lightning']);
    expect(spellCastUpgradeService.listApplicable(abilities, 'arcanist', 'discharge', [chain])).toEqual([]);
  });

  it('неотмеченный не даёт ОД и преимущество', () => {
    const applicable = spellCastUpgradeService.listApplicable(
      [{ ruleCode: 'careful-magic', level: 1, domainCode: 'arcanist' }],
      'arcanist',
      'discharge',
      [careful],
    );
    expect(spellCastUpgradeService.actionPointDelta(spellCastUpgradeService.selectedOf(applicable, []))).toBe(0);
    expect(spellCastUpgradeService.advantageModifiers(spellCastUpgradeService.selectedOf(applicable, []))).toEqual([]);
    const selected = spellCastUpgradeService.selectedOf(applicable, ['careful-magic']);
    expect(spellCastUpgradeService.actionPointDelta(selected)).toBe(1);
    expect(spellCastUpgradeService.advantageModifiers(selected)).toEqual([
      { source_code: 'careful-magic', source_label: 'Аккуратное волшебство', delta: 1 },
    ]);
    expect(spellCastUpgradeService.chipLabel(selected[0]!)).toBe('Аккуратное волшебство · +1 ОД, преимущество');
    expect(spellCastUpgradeService.pruneSelected(applicable, ['careful-magic', 'chain-lightning'])).toEqual([
      'careful-magic',
    ]);
  });
});
