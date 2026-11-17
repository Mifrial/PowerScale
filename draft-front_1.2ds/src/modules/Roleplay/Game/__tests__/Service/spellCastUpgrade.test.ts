import { describe, expect, it } from 'vitest';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { spellCastUpgradeService } from '@/modules/Roleplay/Game/Service/Instance/spellCastUpgradeService';

const careful: Rule = {
  mechanics: [],
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
  mechanics: [],
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

const piercing: Rule = {
  mechanics: [],
  id: null,
  code: 'piercing-magic',
  type: 'ability',
  name: 'Пронзающее волшебство',
  description: '',
  spaceId: 1,
  spec: {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [2] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    multiple: true,
    domain_ref: 'magic-path',
    spell_upgrade: { action_point_delta: 0, any_path: true, resistance_penetration_per_step: 2 },
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

  it('Пронзающее волшебство доступно арканисту для любого выбранного пути', () => {
    const abilities = [{ ruleCode: 'piercing-magic', level: 1, domainCode: 'arcanist' }];
    const selected = spellCastUpgradeService.selectedOf(
      spellCastUpgradeService.listApplicable(abilities, 'shaman', 'discharge', [piercing]),
      ['piercing-magic'],
    );

    expect(selected).toHaveLength(1);
    expect(spellCastUpgradeService.requiredPowerDelta(selected, { 'piercing-magic': 2 })).toBe(2);
    expect(spellCastUpgradeService.resistancePenetration(selected, { 'piercing-magic': 2 })).toBe(4);
    expect(spellCastUpgradeService.chipLabel(selected[0]!, 2)).toContain('х = 2');
  });

  it('Пронзающее скрыто, если заклинание не взаимодействует с сопротивлением магии', () => {
    const damageType = (code: string, magic: boolean): Rule => ({
      mechanics: [],
      id: null,
      code,
      type: 'damage_type',
      name: code,
      description: '',
      spaceId: 1,
      spec: { type: 'damage_type', modifies_spell_difficulty: magic, defense_ignored: true } as unknown as Rule['spec'],
      createdAt: 1,
    });
    const spellRule = (code: string, targeting: string, damageCode: string | null, hit: string): Rule => ({
      mechanics: [],
      id: null,
      code,
      type: 'ability',
      name: code,
      description: '',
      spaceId: 1,
      spec: {
        type: 'spell',
        hit_resolution: { type: hit },
        spell: {
          targeting,
          ...(damageCode
            ? {
                damage: {
                  damage_type_code: damageCode,
                  experience_keyword_code: 'electromancy',
                  power_modify_steps: [],
                },
              }
            : {}),
        },
      } as unknown as Rule['spec'],
      createdAt: 1,
    });
    const electricity = damageType('electricity', false);
    const arcane = damageType('arcane', true);
    const discharge = spellRule('discharge', 'none', 'electricity', 'attack');
    const arcaneTouch = spellRule('arcane-touch', 'none', 'arcane', 'attack');
    const targeted = spellRule('arcane-bolt', 'entity', null, 'auto');
    const abilities = [{ ruleCode: 'piercing-magic', level: 1, domainCode: 'arcanist' }];

    expect(
      spellCastUpgradeService.listApplicable(abilities, 'arcanist', 'discharge', [piercing, discharge, electricity]),
    ).toEqual([]);
    expect(
      spellCastUpgradeService
        .listApplicable(abilities, 'arcanist', 'arcane-touch', [piercing, arcaneTouch, arcane])
        .map((item) => item.ruleCode),
    ).toEqual(['piercing-magic']);
    expect(
      spellCastUpgradeService
        .listApplicable(abilities, 'arcanist', 'arcane-bolt', [piercing, targeted])
        .map((item) => item.ruleCode),
    ).toEqual(['piercing-magic']);
  });

  it('записанные улучшения работают без изученных экземпляров', () => {
    const spellUpgrade = {
      mechanics: [],
      id: null,
      code: 'careful-lightning',
      type: 'ability',
      name: 'Аккуратная молния',
      description: '',
      spaceId: 1,
      spec: {
        type: 'skill',
        zones: { or: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: 'lightning-strike',
        spell_upgrade: { action_point_delta: 1 },
      },
      createdAt: 1,
    } as unknown as Rule;

    expect(
      spellCastUpgradeService
        .listRecorded(['careful-lightning', 'chain-lightning', 'unknown'], 'lightning-strike', [
          spellUpgrade,
          chain,
        ])
        .map((item) => item.ruleCode),
    ).toEqual(['careful-lightning', 'chain-lightning']);
    expect(spellCastUpgradeService.listRecorded(['careful-lightning'], 'other-spell', [spellUpgrade])).toEqual([]);
    expect(
      spellCastUpgradeService
        .listRuntimeRecorded(['chain-lightning', 'careful-lightning'], 'lightning-strike', [spellUpgrade, chain])
        .map((item) => item.ruleCode),
    ).toEqual(['chain-lightning', 'careful-lightning']);
  });

  it('real-фильтр убирает заряды по форме, а цепь пропускает', () => {
    const abilities = [
      { ruleCode: 'careful-magic', level: 1, domainCode: 'arcanist' },
      { ruleCode: 'chain-lightning', level: 1, domainCode: 'arcanist' },
      { ruleCode: 'piercing-magic', level: 1, domainCode: 'arcanist' },
    ];

    expect(
      spellCastUpgradeService
        .listRuntimeApplicable(abilities, 'arcanist', 'lightning-strike', [careful, chain, piercing])
        .map((item) => item.ruleCode),
    ).toEqual(['careful-magic', 'chain-lightning', 'piercing-magic']);
  });

  it('запрос отправляет value только для улучшения с шагами пробития', () => {
    const abilities = [
      { ruleCode: 'careful-magic', level: 1, domainCode: 'arcanist' },
      { ruleCode: 'piercing-magic', level: 1, domainCode: 'arcanist' },
    ];
    const applicable = spellCastUpgradeService.listApplicable(abilities, 'arcanist', 'discharge', [careful, piercing]);
    const both = spellCastUpgradeService.selectedOf(applicable, ['careful-magic', 'piercing-magic']);

    expect(spellCastUpgradeService.requestOf(both, { 'piercing-magic': 3 })).toEqual([
      { code: 'careful-magic' },
      { code: 'piercing-magic', value: 3 },
    ]);
  });

  it('код карточки и domain_ref не решают применимость', () => {
    const synthetic: Rule = {
      ...careful,
      code: 'synthetic-upgrade',
      spec: {
        type: 'skill',
        zones: { or: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        multiple: false,
        domain_ref: 'weapon-family',
        spell_upgrade: { action_point_delta: 2 },
      },
      createdAt: 1,
    };
    const withoutForm: Rule = {
      ...careful,
      code: 'careful-magic',
      spec: {
        type: 'skill',
        zones: { or: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        multiple: false,
        domain_ref: 'magic-path',
      },
    };
    const abilities = [{ ruleCode: 'synthetic-upgrade', level: 1, domainCode: 'arcanist' }];

    expect(
      spellCastUpgradeService
        .listApplicable(abilities, 'arcanist', 'discharge', [synthetic])
        .map((item) => item.ruleCode),
    ).toEqual(['synthetic-upgrade']);
    expect(
      spellCastUpgradeService.listApplicable(
        [{ ruleCode: 'careful-magic', level: 1, domainCode: 'arcanist' }],
        'arcanist',
        'discharge',
        [withoutForm],
      ),
    ).toEqual([]);
  });

  it('Дальность заменяет касание: мощь растёт размерами, контроль — на 2', () => {
    const range: Rule = {
      ...careful,
      code: 'gaze-curse',
      name: 'Проклятие взглядом',
      spec: {
        type: 'skill',
        zones: { or: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        multiple: true,
        domain_ref: 'magic-path',
        spell_upgrade: {
          action_point_delta: 0,
          keyword_codes: ['nolomancy'],
          range: { power_size_per_step: 1, control_base: 2 },
        },
      },
    };
    const spell: Rule = {
      ...careful,
      code: 'living-blindness',
      spec: {
        type: 'spell',
        spell: {
          power: { base: 4, size: -1 },
          control: { base: 4, size: -1 },
          duration: { type: 'instant' },
        },
        action_components: [],
        zones: { or: { kind: 'array', levels_cost: [2] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        hit_resolution: { type: 'attack' },
      },
      keywordIds: [7],
    };
    const keywords: Keyword[] = [
      { id: 7, code: 'nolomancy', name: 'Ноломансия', description: '', active: true },
    ];
    const abilities = [{ ruleCode: 'gaze-curse', level: 1, domainCode: 'arcanist' }];
    const selected = spellCastUpgradeService.selectedOf(
      spellCastUpgradeService.listApplicable(abilities, 'arcanist', 'living-blindness', [range, spell], keywords),
      ['gaze-curse'],
    );

    expect(selected).toHaveLength(1);
    expect(spellCastUpgradeService.hasRange(selected)).toBe(true);
    expect(spellCastUpgradeService.rangeLimit(selected, { 'gaze-curse': 3 })).toBe(3);
    expect(spellCastUpgradeService.requiredPowerDelta(selected, { 'gaze-curse': 3 })).toBe(9);
    expect(spellCastUpgradeService.requiredControlDelta(selected)).toBe(2);
    expect(spellCastUpgradeService.requestOf(selected, { 'gaze-curse': 3 })).toEqual([
      { code: 'gaze-curse', value: 3 },
    ]);
    expect(spellCastUpgradeService.chipLabel(selected[0]!, 3)).toContain('дальность 3 ипари');
    const nonTouchSpell: Rule = {
      ...spell,
      code: 'discharge',
      spec: {
        type: 'spell',
        spell: {
          power: { base: 4, size: 0 },
          control: { base: 3, size: -1 },
          duration: { type: 'instant' },
        },
        action_components: [],
        zones: { or: { kind: 'array', levels_cost: [2] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        hit_resolution: { type: 'auto', rating: 3 },
      },
      keywordIds: [7],
    };
    expect(
      spellCastUpgradeService.listApplicable(
        abilities,
        'arcanist',
        'living-blindness',
        [range, spell],
        keywords,
      ),
    ).toHaveLength(1);
    expect(
      spellCastUpgradeService.listApplicable(
        abilities,
        'arcanist',
        'discharge',
        [range, nonTouchSpell],
        keywords,
      ),
    ).toEqual([]);
  });

  it('Одинаковый источник преимущества объединяет улучшения', () => {
    const first: Rule = {
      ...careful,
      code: 'experienced-nolomant',
      name: 'Опытный ноломант',
      spec: {
        type: 'skill',
        zones: { or: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        multiple: true,
        domain_ref: 'magic-path',
        spell_upgrade: { action_point_delta: 0, check_advantage: 1, check_advantage_source: 'mastery' },
      },
    };
    const second: Rule = {
      ...first,
      code: 'nerve-control',
      name: 'Контроль нервов',
      spec: {
        ...first.spec,
        spell_upgrade: { action_point_delta: 0, check_advantage: 2, check_advantage_source: 'mastery' },
      } as Rule['spec'],
    };
    const selected = spellCastUpgradeService.selectedOf(
      spellCastUpgradeService.listApplicable(
        [
          { ruleCode: 'experienced-nolomant', level: 1, domainCode: 'arcanist' },
          { ruleCode: 'nerve-control', level: 1, domainCode: 'arcanist' },
        ],
        'arcanist',
        'discharge',
        [first, second],
      ),
      ['experienced-nolomant', 'nerve-control'],
    );

    expect(spellCastUpgradeService.advantageModifiers(selected)).toEqual([
      { source_code: 'mastery', source_label: 'Опытный ноломант', delta: 1 },
      { source_code: 'mastery', source_label: 'Контроль нервов', delta: 2 },
    ]);
  });
});
