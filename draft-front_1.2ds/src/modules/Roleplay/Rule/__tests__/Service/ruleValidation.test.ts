import { describe, it, expect } from 'vitest';
import { ruleValidationService } from '@/modules/Roleplay/Rule/Service/Instance/ruleValidationService';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';
import { itemSpecService } from '@/modules/Roleplay/Rule/Service/Instance/itemSpecService';
import { raceSpecService } from '@/modules/Roleplay/Rule/Service/Instance/raceSpecService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const baseRule = (id: string, code: string, type: Rule['type'], spec?: any): Rule => ({
  id,
  code,
  type,
  name: code,
  description: '',
  spaceId: 1,
  spec,
  createdAt: '2026-01-01T00:00:00Z',
});

describe('validateRuleReferences', () => {
  it('passes for a fully consistent pool', () => {
    const rules: Rule[] = [
      baseRule('s', 'strength', 'characteristic'),
      baseRule('dt', 'slashing', 'damage_type'),
      baseRule('sr', 'rule-6-and-1', 'simple'),
      baseRule('i', 'sword', 'item', {
        special_rule_codes: ['rule-6-and-1'],
        weapon: {
          weapon_profiles: [
            {
              damage: {
                formula: { type: 'characteristic', characteristic_code: 'strength', modifier: 0 },
                damage_type_code: 'slashing',
              },
              penetration: { type: 'characteristic', characteristic_code: 'strength', modifier: 0 },
            },
          ],
        },
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);
  });

  it('reports missing characteristic code in item formula', () => {
    const rules: Rule[] = [
      baseRule('dt', 'slashing', 'damage_type'),
      baseRule('i', 'sword', 'item', {
        weapon: {
          weapon_profiles: [
            {
              damage: {
                formula: { type: 'characteristic', characteristic_code: 'strength', modifier: 0 },
                damage_type_code: 'slashing',
              },
              penetration: { type: 'fixed', value: 1 },
            },
          ],
        },
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ ruleCode: 'sword', refCode: 'strength', expectedType: 'characteristic' });
  });

  it('reports type mismatch when item references a characteristic as damage_type', () => {
    const rules: Rule[] = [
      baseRule('s', 'strength', 'characteristic'),
      baseRule('i', 'sword', 'item', {
        weapon: {
          weapon_profiles: [
            {
              damage: { formula: { type: 'fixed', value: 2 }, damage_type_code: 'strength' },
              penetration: { type: 'fixed', value: 1 },
            },
          ],
        },
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'strength', expectedType: 'damage_type' });
  });

  it('validates ability references: requirements, grants, action costs', () => {
    const rules: Rule[] = [
      baseRule('a', 'melee-fighting', 'ability'),
      baseRule('ap', 'action-points', 'resource', { is_dimensional: true, initial_value: 3 }),
      baseRule('s', 'strength', 'characteristic'),
      baseRule('ds', 'double-strike', 'ability', {
        requirements: [
          { level: 1, requirements: [{ type: 'has_ability', ability_code: 'melee-fighting', min_level: 1 }] },
        ],
        grants: [
          {
            level: 1,
            grants: [
              {
                type: 'characteristic_modify',
                characteristic_code: 'strength',
                amount: { type: 'fixed', value: 1 },
                permanent: true,
              },
            ],
          },
        ],
        action_components: [{ type: 'resource', resource_code: 'action-points', amount: 1 }],
        parent_ability_code: null,
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);
  });

  it('reports missing resource in ability action cost', () => {
    const rules: Rule[] = [
      baseRule('ds', 'double-strike', 'ability', {
        requirements: [],
        grants: [],
        action_components: [{ type: 'resource', resource_code: 'action-points', amount: 1 }],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'action-points', expectedType: 'resource' });
  });

  it('checks ability_level formula inside a level grant', () => {
    const rules: Rule[] = [
      baseRule('a', 'melee-fighting', 'ability'),
      baseRule('s', 'strength', 'characteristic'),
      baseRule('mf', 'melee-mastery', 'ability', {
        requirements: [],
        grants: [
          {
            level: 1,
            grants: [
              {
                type: 'characteristic_modify',
                characteristic_code: 'strength',
                amount: { type: 'ability_level', ability_code: 'melee-fighting', multiplier: 1, offset: 0 },
              },
            ],
          },
        ],
        action_components: [],
        parent_ability_code: null,
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);
  });

  it('validates resistance grant: damage_type and source refs', () => {
    const rules: Rule[] = [
      baseRule('dt', 'magic', 'damage_type'),
      baseRule('sr', 'innate', 'source'),
      baseRule('mr', 'magic-resistance', 'ability', {
        requirements: [],
        grants: [
          {
            level: 1,
            grants: [
              {
                type: 'resistance',
                damage_type_code: 'magic',
                value: { base: 2, size: 0 },
                source_code: 'innate',
              },
            ],
          },
        ],
        action_components: [],
        parent_ability_code: null,
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);

    const bad = [
      baseRule('mr', 'magic-resistance', 'ability', {
        requirements: [],
        grants: [
          {
            level: 1,
            grants: [
              {
                type: 'resistance',
                damage_type_code: 'missing-dt',
                value: { base: 2, size: 0 },
                source_code: 'missing-source',
              },
            ],
          },
        ],
        action_components: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(bad, []);
    expect(errors.some((e) => e.refCode === 'missing-dt' && e.expectedType === 'damage_type')).toBe(true);
    expect(errors.some((e) => e.refCode === 'missing-source' && e.expectedType === 'source')).toBe(true);
  });

  it('validates keyword references against provided keywords', () => {
    const rules: Rule[] = [
      baseRule('a', 'melee-fighting', 'ability', {
        requirements: [
          { level: 1, requirements: [{ type: 'has_ability_keyword', keyword_code: 'combat', min_count: 1 }] },
        ],
        grants: [],
        action_components: [],
        parent_ability_code: null,
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [{ code: 'combat', name: 'Боевое' }])).toEqual([]);
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'combat', expectedType: 'keyword' });
  });

  it('validates level-based requirements references', () => {
    const rules: Rule[] = [
      baseRule('ap', 'action-points', 'resource', { is_dimensional: true, initial_value: { base: 3, size: 0 } }),
      baseRule('mf', 'melee-fighting', 'ability', {
        requirements: [
          {
            level: 2,
            requirements: [{ type: 'resource_limit', resource_code: 'action-points', min: { base: 2, size: 0 } }],
          },
        ],
        grants: [],
        action_components: [],
        parent_ability_code: null,
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);

    const bad = [
      baseRule('mf', 'melee-fighting', 'ability', {
        requirements: [
          {
            level: 2,
            requirements: [{ type: 'resource_limit', resource_code: 'missing-resource', min: { base: 2, size: 0 } }],
          },
        ],
        grants: [],
        action_components: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(bad, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'missing-resource', expectedType: 'resource' });
  });

  it('validates characteristic formula string references', () => {
    const rules: Rule[] = [
      baseRule('m', 'memory', 'characteristic'),
      baseRule('r', 'reasoning', 'characteristic'),
      baseRule('i', 'intellect', 'characteristic', { formula: 'min(memory, reasoning)' }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);

    const bad = [
      baseRule('m', 'memory', 'characteristic'),
      baseRule('i', 'intellect', 'characteristic', { formula: 'min(memory, dexterity)' }),
    ];
    const errors = ruleValidationService.validateRuleReferences(bad, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'dexterity', expectedType: 'characteristic' });
  });

  it('validates race references: parent species, characteristics, abilities', () => {
    const rules: Rule[] = [
      baseRule('sp', 'elves', 'species', { parent_race_code: null, abilities: [] }),
      baseRule('s', 'strength', 'characteristic'),
      baseRule('a', 'keen-hearing', 'ability'),
      baseRule('r', 'elf', 'race', {
        parent_race_code: 'elves',
        cost_os: 8,
        characteristics: [{ characteristic_code: 'strength', mode: 'fixed', base: { base: 3, size: 0 } }],
        abilities: [{ ability_code: 'keen-hearing', automatic: true }],
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);
  });

  it('reports race parent referencing a race instead of a species', () => {
    const rules: Rule[] = [
      baseRule('r2', 'human', 'race'),
      baseRule('r', 'elf', 'race', {
        parent_race_code: 'human',
        cost_os: 8,
        characteristics: [],
        abilities: [],
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'human', expectedType: 'species' });
  });

  it('reports missing characteristic in race characteristics', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8,
        characteristics: [{ characteristic_code: 'missing-stat', mode: 'fixed', base: { base: 3, size: 0 } }],
        abilities: [],
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'missing-stat', expectedType: 'characteristic' });
  });

  it('validates species references: parent species and abilities', () => {
    const rules: Rule[] = [
      baseRule('sp', 'elves', 'species', { parent_race_code: null, abilities: [] }),
      baseRule('a', 'keen-hearing', 'ability'),
      baseRule('sp2', 'wood-elves', 'species', {
        parent_race_code: 'elves',
        abilities: [{ ability_code: 'keen-hearing', automatic: true }],
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);
  });

  it('validates ability zone keys reference point rules', () => {
    const rules: Rule[] = [
      baseRule('p', 'os', 'points'),
      baseRule('a', 'keen-hearing', 'ability', {
        zones: { os: { kind: 'automatic' } },
        requirements: [],
        grants: [],
        action_components: [],
        parent_ability_code: null,
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);
  });

  it('reports a missing point rule for a zone key', () => {
    const rules: Rule[] = [
      baseRule('a', 'keen-hearing', 'ability', {
        zones: { missing: { kind: 'automatic' } },
        requirements: [],
        grants: [],
        action_components: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'missing', expectedType: 'points' });
  });

  it('passes for a state with valid characteristic and decay refs', () => {
    const rules: Rule[] = [
      baseRule('c', 'strength', 'characteristic'),
      baseRule('s', 'weakness', 'state', {
        value_type: 'flag',
        aggregation: 'max',
        effects: [
          { type: 'characteristic_modify', characteristic_code: 'strength', amount: -3 },
          {
            type: 'damage_over_time',
            damage: { kind: 'value' },
            decay: { kind: 'characteristic', characteristic_code: 'strength' },
          },
        ],
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);
  });

  it('reports missing characteristic ref in a check-based decay', () => {
    const rules: Rule[] = [
      baseRule('s', 'poison', 'state', {
        value_type: 'flag',
        aggregation: 'independent',
        effects: [
          {
            type: 'damage_over_time',
            damage: { kind: 'fixed', amount: 3 },
            decay: { kind: 'check', characteristic_code: 'missing-check' },
          },
        ],
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'missing-check', expectedType: 'characteristic' });
  });

  it('passes for a consistent poison rule', () => {
    const rules: Rule[] = [
      baseRule('dt', 'poison-1', 'damage_type'),
      baseRule('p', 'poison-x', 'poison', {
        damage_type_code: 'poison-1',
        default_strength: { base: 3, size: 1 },
        default_periodicity: { kind: 'literal', value: 2, step: 'turn' },
        default_decay: { kind: 'fixed', value: 1 },
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);
  });

  it('reports missing damage_type ref in a poison rule', () => {
    const rules: Rule[] = [
      baseRule('p', 'poison-x', 'poison', {
        damage_type_code: 'missing-dt',
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'missing-dt', expectedType: 'damage_type' });
  });

  it('reports missing characteristic ref in poison decay', () => {
    const rules: Rule[] = [
      baseRule('dt', 'poison-1', 'damage_type'),
      baseRule('p', 'poison-x', 'poison', {
        damage_type_code: 'poison-1',
        default_decay: { kind: 'check', characteristic_code: 'missing-char' },
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors.some((e) => e.refCode === 'missing-char')).toBe(true);
  });

  it('reports missing characteristic ref in state effects', () => {
    const rules: Rule[] = [
      baseRule('s', 'stunned', 'state', {
        value_type: 'number',
        aggregation: 'sum',
        effects: [{ type: 'characteristic_modify', characteristic_code: 'missing-char', amount: -3 }],
      }),
    ];
    const errors = ruleValidationService.validateRuleReferences(rules, []);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ refCode: 'missing-char', expectedType: 'characteristic' });
  });
});

describe('resolveAbilityTypeFromTags', () => {
  it('maps keyword combinations to ability types by precedence', () => {
    expect(abilitySpecService.resolveTypeFromKeywords(['trait'])).toBe('trait');
    expect(abilitySpecService.resolveTypeFromKeywords(['feature'])).toBe('feature');
    expect(abilitySpecService.resolveTypeFromKeywords(['skill'])).toBe('skill');
    expect(abilitySpecService.resolveTypeFromKeywords(['skill', 'action'])).toBe('action');
    expect(abilitySpecService.resolveTypeFromKeywords(['skill', 'action', 'process'])).toBe('process');
    expect(abilitySpecService.resolveTypeFromKeywords(['skill', 'magic', 'action', 'spell'])).toBe('spell');
  });

  it('prefers most specific type when multiple distinctive keywords present', () => {
    expect(abilitySpecService.resolveTypeFromKeywords(['action', 'spell'])).toBe('spell');
    expect(abilitySpecService.resolveTypeFromKeywords(['process', 'action'])).toBe('process');
    expect(abilitySpecService.resolveTypeFromKeywords(['action', 'trait'])).toBe('action');
  });

  it('returns null for unrelated keywords or empty set', () => {
    expect(abilitySpecService.resolveTypeFromKeywords([])).toBeNull();
    expect(abilitySpecService.resolveTypeFromKeywords(['combat', 'utility'])).toBeNull();
  });
});

describe('validateAbilityStructure', () => {
  const keywords = [
    { id: 1, code: 'skill', name: 'Навык' },
    { id: 2, code: 'action', name: 'Действие' },
    { id: 3, code: 'process', name: 'Процесс' },
    { id: 4, code: 'spell', name: 'Заклинание' },
  ];

  it('requires an action point cost for action type', () => {
    const rules: Rule[] = [
      baseRule('a', 'strike', 'ability', {
        type: 'action',
        action_components: [],
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateAbilityStructure(rules, keywords);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatchObject({ ruleCode: 'strike' });
    expect(errors[0].message).toContain('1 ОД');
  });

  it('passes action type with an action point cost', () => {
    const rules: Rule[] = [
      baseRule('a', 'strike', 'ability', {
        type: 'action',
        action_components: [{ type: 'resource', resource_code: 'action-points', amount: 1 }],
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    expect(ruleValidationService.validateAbilityStructure(rules, keywords)).toEqual([]);
  });

  it('requires at least two steps for a process', () => {
    const rules: Rule[] = [
      baseRule('p', 'movement', 'ability', {
        type: 'process',
        action_components: [],
        process: { steps: [], transition: { mode: 'chain', max_shift: 1 } },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateAbilityStructure(rules, keywords);
    expect(errors.some((e) => e.message.includes('минимум 2 шага'))).toBe(true);
  });

  it('validates process steps have action point cost and existing start step', () => {
    const rules: Rule[] = [
      baseRule('p', 'movement', 'ability', {
        type: 'process',
        action_components: [],
        process: {
          steps: [
            { code: 'walk', name: 'Ходьба', description: '', costs: [{ resource_code: 'action-points', amount: 1 }] },
            { code: 'run', name: 'Бег', description: '', costs: [{ resource_code: 'action-points', amount: 1 }] },
          ],
          start_step_code: 'missing',
          transition: { mode: 'chain', max_shift: 1 },
        },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateAbilityStructure(rules, keywords);
    expect(errors.some((e) => e.message.includes('«missing» не существует'))).toBe(true);
    expect(errors.some((e) => e.message.includes('1 ОД'))).toBe(false);
  });

  it('flags a process step without an action point cost', () => {
    const rules: Rule[] = [
      baseRule('p', 'movement', 'ability', {
        type: 'process',
        action_components: [],
        process: {
          steps: [
            { code: 'walk', name: 'Ходьба', description: '', costs: [] },
            { code: 'run', name: 'Бег', description: '', costs: [{ resource_code: 'action-points', amount: 1 }] },
          ],
          transition: { mode: 'chain', max_shift: 1 },
        },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateAbilityStructure(rules, keywords);
    expect(errors.some((e) => e.message.includes('требует минимум 1 ОД'))).toBe(true);
  });

  it('validates custom transition edges reference existing steps', () => {
    const rules: Rule[] = [
      baseRule('p', 'movement', 'ability', {
        type: 'process',
        action_components: [],
        process: {
          steps: [
            { code: 'walk', name: 'Ходьба', description: '', costs: [{ resource_code: 'action-points', amount: 1 }] },
            { code: 'run', name: 'Бег', description: '', costs: [{ resource_code: 'action-points', amount: 1 }] },
          ],
          transition: { mode: 'custom', edges: [{ from: 'walk', to: 'sprint' }] },
        },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateAbilityStructure(rules, keywords);
    expect(errors.some((e) => e.message.includes('шаг «sprint» не существует'))).toBe(true);
  });

  it('requires difficulty and validates material item for a spell', () => {
    const rules: Rule[] = [
      baseRule('i', 'ash', 'item'),
      baseRule('sp', 'fire-bolt', 'ability', {
        type: 'spell',
        action_components: [
          { type: 'resource', resource_code: 'action-points', amount: 1, label: 'Сотворение' },
          { type: 'material', mode: 'consume', item_code: 'ash' },
          { type: 'material', mode: 'consume', item_code: 'missing-item' },
        ],
        spell: { duration: { type: 'instant' } },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateAbilityStructure(rules, keywords);
    expect(errors.some((e) => e.message.includes('сложность сотворения'))).toBe(true);
    expect(errors.some((e) => e.message.includes('отсутствующий предмет «missing-item»'))).toBe(true);
    expect(errors.some((e) => e.message.includes('отсутствующий предмет «ash»'))).toBe(false);
  });

  it('flags a material item whose code matches a keyword but has no item rule', () => {
    const rules: Rule[] = [
      baseRule('sp', 'fire-bolt', 'ability', {
        type: 'spell',
        action_components: [
          { type: 'resource', resource_code: 'action-points', amount: 1, label: 'Сотворение' },
          { type: 'material', mode: 'consume', item_code: 'skill' },
        ],
        spell: { duration: { type: 'instant' } },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateAbilityStructure(rules, keywords);
    expect(errors.some((e) => e.message.includes('отсутствующий предмет «skill»'))).toBe(true);
  });

  it('validates material by tags against existing keywords', () => {
    const rules: Rule[] = [
      baseRule('sp', 'fire-bolt', 'ability', {
        type: 'spell',
        action_components: [
          { type: 'resource', resource_code: 'action-points', amount: 1, label: 'Сотворение' },
          { type: 'material', mode: 'use', keyword_codes: ['skill'] },
          { type: 'material', mode: 'use', keyword_codes: ['missing-tag'] },
        ],
        spell: { difficulty: { base: 3, size: 0 }, duration: { type: 'instant' } },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    const structure = ruleValidationService.validateAbilityStructure(rules, keywords);
    expect(structure.some((e) => e.message.includes('предмет или набор тегов'))).toBe(false);

    const refErrors = ruleValidationService.validateRuleReferences(rules, keywords);
    expect(refErrors.some((e) => e.refCode === 'missing-tag' && e.expectedType === 'keyword')).toBe(true);
    expect(refErrors.some((e) => e.refCode === 'skill')).toBe(false);
  });

  it('flags a material component with no target or both targets', () => {
    const rules: Rule[] = [
      baseRule('i', 'ash', 'item'),
      baseRule('sp', 'fire-bolt', 'ability', {
        type: 'spell',
        action_components: [
          { type: 'resource', resource_code: 'action-points', amount: 1, label: 'Сотворение' },
          { type: 'material', mode: 'consume' },
          { type: 'material', mode: 'consume', item_code: 'ash', keyword_codes: ['skill'] },
        ],
        spell: { difficulty: { base: 3, size: 0 }, duration: { type: 'instant' } },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    const errors = ruleValidationService.validateAbilityStructure(rules, keywords);
    expect(errors.filter((e) => e.message.includes('предмет или набор тегов'))).toHaveLength(2);
  });

  it('accepts both consume and use modes for a material component', () => {
    const rules: Rule[] = [
      baseRule('i', 'ash', 'item'),
      baseRule('sp', 'fire-bolt', 'ability', {
        type: 'spell',
        action_components: [
          { type: 'resource', resource_code: 'action-points', amount: 1, label: 'Сотворение' },
          { type: 'material', mode: 'consume', item_code: 'ash' },
          { type: 'material', mode: 'use', keyword_codes: ['skill'] },
        ],
        spell: { difficulty: { base: 3, size: 0 }, duration: { type: 'instant' } },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ];
    expect(ruleValidationService.validateAbilityStructure(rules, keywords)).toEqual([]);
  });

  it('derives type from keywords when spec.type is absent', () => {
    const rules: Rule[] = [
      {
        ...baseRule('a', 'strike', 'ability', {
          action_components: [],
          grants: [],
          requirements: [],
          parent_ability_code: null,
        }),
        keywordIds: [1, 2],
      },
    ];
    const errors = ruleValidationService.validateAbilityStructure(rules, keywords);
    expect(errors.some((e) => e.message.includes('1 ОД'))).toBe(true);
  });
});

describe('pruneAbilitySpecForType', () => {
  const draft = {
    type: undefined,
    zones: {},
    requirements: [{ level: 1, requirements: [] }],
    grants: [],
    action_components: [{ type: 'resource' as const, resource_code: 'action-points', amount: 1 }],
    process: { steps: [], transition: { mode: 'chain' as const, max_shift: 1 } },
    spell: { difficulty: { base: 3, size: 0 }, duration: { type: 'instant' as const } },
    parent_ability_code: null,
  };

  it('keeps action_components only for action', () => {
    const out = abilitySpecService.prune({ ...draft }, 'action') as any;
    expect(out.type).toBe('action');
    expect(out.action_components).toHaveLength(1);
    expect(out.process).toBeUndefined();
    expect(out.spell).toBeUndefined();
  });

  it('keeps process only for process and clears action_components', () => {
    const out = abilitySpecService.prune({ ...draft }, 'process') as any;
    expect(out.type).toBe('process');
    expect(out.process).toBeDefined();
    expect(out.action_components).toBeUndefined();
    expect(out.spell).toBeUndefined();
  });

  it('keeps spell and action_components for spell', () => {
    const out = abilitySpecService.prune({ ...draft }, 'spell') as any;
    expect(out.type).toBe('spell');
    expect(out.spell).toBeDefined();
    expect(out.action_components).toHaveLength(1);
    expect(out.process).toBeUndefined();
  });

  it('drops all type-specific fields for skill', () => {
    const out = abilitySpecService.prune({ ...draft }, 'skill') as any;
    expect(out.type).toBe('skill');
    expect(out.action_components).toBeUndefined();
    expect(out.process).toBeUndefined();
    expect(out.spell).toBeUndefined();
  });

  it('keeps shared fields (zones, requirements, grants) always', () => {
    const out = abilitySpecService.prune({ ...draft }, 'skill') as any;
    expect(out.zones).toEqual({});
    expect(out.requirements).toHaveLength(1);
    expect(out.grants).toEqual([]);
    expect(out.parent_ability_code).toBeNull();
  });
});

describe('pruneItemSpecBySubtypes', () => {
  const draft = {
    category: 'equipment' as const,
    cost_gm: 100,
    weight: { base: 1, size: 0 },
    special_rule_codes: [],
    innate: false,
    weapon: { min_strength: null, block_profile: null, weapon_profiles: [] },
    armor: { defense_slots: [], resistance_slots: [], characteristic_limits: [] },
    shield: {
      min_strength: null,
      block: { efficiency: { base: 1, size: 0 }, defense: { base: 1, size: 0 }, resistances: [] },
    },
  };

  it('keeps only blocks of active subtypes', () => {
    const out = itemSpecService.prune({ ...draft }, ['weapon', 'shield']) as any;
    expect(out.weapon).toBeDefined();
    expect(out.shield).toBeDefined();
    expect(out.armor).toBeUndefined();
  });

  it('keeps shared fields always', () => {
    const out = itemSpecService.prune({ ...draft }, []) as any;
    expect(out.category).toBe('equipment');
    expect(out.cost_gm).toBe(100);
    expect(out.weapon).toBeUndefined();
    expect(out.armor).toBeUndefined();
    expect(out.shield).toBeUndefined();
  });
});

describe('validateRaceStructure', () => {
  it('passes for a well-formed race', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8,
        characteristics: [
          { characteristic_code: 'dexterity', mode: 'fixed', base: { base: 4, size: 0 } },
          {
            characteristic_code: 'strength',
            mode: 'purchased',
            base: { base: 3, size: 0 },
            purchase: [
              { cost: 1, value: { base: 4, size: 0 } },
              { cost: 3, value: { base: 5, size: 0 } },
            ],
          },
        ],
        abilities: [{ ability_code: 'keen-hearing', automatic: true }],
      }),
    ];
    expect(ruleValidationService.validateRaceStructure(rules)).toEqual([]);
  });

  it('flags non-integer cost_os', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8.5,
        characteristics: [],
        abilities: [],
      }),
    ];
    const errors = ruleValidationService.validateRaceStructure(rules);
    expect(errors.some((e) => e.message.includes('cost_os'))).toBe(true);
  });

  it('flags duplicate characteristic codes', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8,
        characteristics: [
          { characteristic_code: 'dexterity', mode: 'fixed', base: { base: 4, size: 0 } },
          { characteristic_code: 'dexterity', mode: 'fixed', base: { base: 5, size: 0 } },
        ],
        abilities: [],
      }),
    ];
    const errors = ruleValidationService.validateRaceStructure(rules);
    expect(errors.some((e) => e.message.includes('«dexterity» указана несколько раз'))).toBe(true);
  });

  it('flags empty characteristic code', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8,
        characteristics: [{ characteristic_code: '', mode: 'fixed', base: { base: 3, size: 0 } }],
        abilities: [],
      }),
    ];
    const errors = ruleValidationService.validateRaceStructure(rules);
    expect(errors.some((e) => e.message.includes('не указан код'))).toBe(true);
  });

  it('flags purchase level with cost < 1 and duplicate costs', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8,
        characteristics: [
          {
            characteristic_code: 'strength',
            mode: 'purchased',
            base: { base: 3, size: 0 },
            purchase: [
              { cost: 0, value: { base: 4, size: 0 } },
              { cost: 2, value: { base: 5, size: 0 } },
              { cost: 2, value: { base: 5, size: 0 } },
            ],
          },
        ],
        abilities: [],
      }),
    ];
    const errors = ruleValidationService.validateRaceStructure(rules);
    expect(errors.some((e) => e.message.includes('стоимость должна быть ≥ 1'))).toBe(true);
    expect(errors.some((e) => e.message.includes('стоимость 2 указана несколько раз'))).toBe(true);
  });

  it('не флагает дубль способности «бесплатно + доступна покупка» (automatic true/false)', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8,
        characteristics: [],
        abilities: [
          { ability_code: 'keen-hearing', automatic: true },
          { ability_code: 'keen-hearing', automatic: false },
        ],
      }),
    ];
    const errors = ruleValidationService.validateRaceStructure(rules);
    expect(errors.some((e) => e.message.includes('«keen-hearing» указана несколько раз'))).toBe(false);
  });

  it('флагает настоящий дубль способности (одинаковый automatic)', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8,
        characteristics: [],
        abilities: [
          { ability_code: 'keen-hearing', automatic: false },
          { ability_code: 'keen-hearing', automatic: false },
        ],
      }),
    ];
    const errors = ruleValidationService.validateRaceStructure(rules);
    expect(errors.some((e) => e.message.includes('«keen-hearing» указана несколько раз'))).toBe(true);
  });

  it('flags characteristic base and purchase level value outside 3–5', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8,
        characteristics: [
          { characteristic_code: 'dexterity', mode: 'fixed', base: { base: 6, size: 0 } },
          {
            characteristic_code: 'strength',
            mode: 'purchased',
            base: { base: 2, size: 0 },
            purchase: [
              { cost: 1, value: { base: 4, size: 0 } },
              { cost: 3, value: { base: 7, size: 0 } },
            ],
          },
        ],
        abilities: [],
      }),
    ];
    const errors = ruleValidationService.validateRaceStructure(rules);
    expect(errors.some((e) => e.message.includes('«dexterity»: база вне диапазона'))).toBe(true);
    expect(errors.some((e) => e.message.includes('«strength»: база вне диапазона'))).toBe(true);
    expect(errors.some((e) => e.message.includes('«strength»: база значения вне диапазона'))).toBe(true);
  });
});

describe('validateSpeciesStructure', () => {
  it('passes for a well-formed species', () => {
    const rules: Rule[] = [
      baseRule('sp', 'elves', 'species', {
        parent_race_code: null,
        abilities: [{ ability_code: 'keen-hearing', automatic: true }],
      }),
    ];
    expect(ruleValidationService.validateSpeciesStructure(rules)).toEqual([]);
  });

  it('не флагает дубль способности вида «бесплатно + доступна покупка» (automatic true/false)', () => {
    const rules: Rule[] = [
      baseRule('sp', 'elves', 'species', {
        parent_race_code: null,
        abilities: [
          { ability_code: 'keen-hearing', automatic: true },
          { ability_code: 'keen-hearing', automatic: false },
        ],
      }),
    ];
    const errors = ruleValidationService.validateSpeciesStructure(rules);
    expect(errors.some((e) => e.message.includes('«keen-hearing» указана несколько раз'))).toBe(false);
  });

  it('флагает настоящий дубль способности вида (одинаковый automatic)', () => {
    const rules: Rule[] = [
      baseRule('sp', 'elves', 'species', {
        parent_race_code: null,
        abilities: [
          { ability_code: 'keen-hearing', automatic: false },
          { ability_code: 'keen-hearing', automatic: false },
        ],
      }),
    ];
    const errors = ruleValidationService.validateSpeciesStructure(rules);
    expect(errors.some((e) => e.message.includes('«keen-hearing» указана несколько раз'))).toBe(true);
  });

  it('flags empty ability code', () => {
    const rules: Rule[] = [
      baseRule('sp', 'elves', 'species', {
        parent_race_code: null,
        abilities: [{ ability_code: '', automatic: false }],
      }),
    ];
    const errors = ruleValidationService.validateSpeciesStructure(rules);
    expect(errors.some((e) => e.message.includes('не указан код'))).toBe(true);
  });
});

describe('findSpeciesCycle', () => {
  it('returns null for an acyclic chain', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'a', 'species', { parent_race_code: null, abilities: [] }),
      baseRule('sp2', 'b', 'species', { parent_race_code: 'a', abilities: [] }),
      baseRule('sp3', 'c', 'species', { parent_race_code: 'b', abilities: [] }),
    ];
    expect(ruleValidationService.findSpeciesCycle(rules)).toBeNull();
  });

  it('detects a two-node cycle', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'a', 'species', { parent_race_code: 'b', abilities: [] }),
      baseRule('sp2', 'b', 'species', { parent_race_code: 'a', abilities: [] }),
    ];
    expect(ruleValidationService.findSpeciesCycle(rules)).toContain('a');
    expect(ruleValidationService.findSpeciesCycle(rules)).toContain('b');
  });

  it('detects a longer cycle', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'a', 'species', { parent_race_code: 'c', abilities: [] }),
      baseRule('sp2', 'b', 'species', { parent_race_code: 'a', abilities: [] }),
      baseRule('sp3', 'c', 'species', { parent_race_code: 'b', abilities: [] }),
    ];
    expect(ruleValidationService.findSpeciesCycle(rules)).toBeTruthy();
  });

  it('ignores races in the chain', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'a', 'species', { parent_race_code: null, abilities: [] }),
      baseRule('sp2', 'b', 'species', { parent_race_code: 'a', abilities: [] }),
      baseRule('r', 'elf', 'race', { parent_race_code: 'b', cost_os: 8, characteristics: [], abilities: [] }),
    ];
    expect(ruleValidationService.findSpeciesCycle(rules)).toBeNull();
  });
});

describe('collectInheritedAbilities', () => {
  it('collects abilities from the whole ancestor chain', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'elves', 'species', {
        parent_race_code: null,
        abilities: [{ ability_code: 'keen-hearing', automatic: true }],
      }),
      baseRule('sp2', 'wood-elves', 'species', {
        parent_race_code: 'elves',
        abilities: [{ ability_code: 'night-vision', automatic: false }],
      }),
      baseRule('r', 'elf', 'race', { parent_race_code: 'wood-elves', cost_os: 8, characteristics: [], abilities: [] }),
    ];
    const byCode = new Map(rules.map((r) => [r.code, r]));
    const inherited = raceSpecService.collectInheritedAbilities('wood-elves', byCode);
    expect(inherited).toHaveLength(2);
    expect(inherited[0]).toMatchObject({ ability_code: 'night-vision', fromName: 'wood-elves' });
    expect(inherited[1]).toMatchObject({ ability_code: 'keen-hearing', fromName: 'elves' });
  });

  it('returns empty when no parent is set', () => {
    const rules: Rule[] = [
      baseRule('r', 'human', 'race', { parent_race_code: null, cost_os: 0, characteristics: [], abilities: [] }),
    ];
    const byCode = new Map(rules.map((r) => [r.code, r]));
    expect(raceSpecService.collectInheritedAbilities(null, byCode)).toEqual([]);
  });

  it('stops at a cycle instead of looping forever', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'a', 'species', { parent_race_code: 'b', abilities: [{ ability_code: 'x', automatic: true }] }),
      baseRule('sp2', 'b', 'species', { parent_race_code: 'a', abilities: [] }),
    ];
    const byCode = new Map(rules.map((r) => [r.code, r]));
    const inherited = raceSpecService.collectInheritedAbilities('a', byCode);
    expect(inherited.length).toBeLessThanOrEqual(1);
  });
});

describe('validateRuleCodeFormat', () => {
  it('пропускает латинские коды с дефисом, подчёркиванием и цифрами', () => {
    const rules: Rule[] = [
      baseRule('1', 'lavash', 'item'),
      baseRule('2', 'rule-6-and-1', 'simple'),
      baseRule('3', 'vtoraya_faza', 'item'),
    ];
    expect(ruleValidationService.validateRuleCodeFormat(rules)).toEqual([]);
  });

  it('режет кириллицу, пробелы и недопустимые символы', () => {
    const rules: Rule[] = [
      baseRule('1', 'лаваш', 'item'),
      baseRule('2', 'double strike', 'simple'),
      baseRule('3', 'kristall-4↑', 'item'),
    ];
    const errors = ruleValidationService.validateRuleCodeFormat(rules);
    expect(errors.map((e) => e.ruleCode)).toEqual(['лаваш', 'double strike', 'kristall-4↑']);
  });
});

describe('validateCheckStructure', () => {
  it('ломается на цикле parent_check_code', () => {
    const rules: Rule[] = [
      baseRule('a', 'check-a', 'check', {
        type: 'check',
        parent_check_code: 'check-b',
        difficulty_input: { kind: 'ask' },
        allowed_modes: 'both',
      }),
      baseRule('b', 'check-b', 'check', {
        type: 'check',
        parent_check_code: 'check-a',
        difficulty_input: { kind: 'ask' },
        allowed_modes: 'both',
      }),
    ];
    const errors = ruleValidationService.validateCheckStructure(rules);
    expect(errors.length).toBeGreaterThan(0);
    expect(errors.some((error) => error.message.includes('цикл'))).toBe(true);
  });

  it('ломается на неизвестном правиле броска', () => {
    const rules: Rule[] = [
      baseRule('p', 'check-simple', 'check', {
        type: 'check',
        difficulty_input: { kind: 'ask' },
        allowed_modes: 'both',
        attached_rule_codes: ['no-such-rule'],
      }),
    ];
    const errors = ruleValidationService.validateCheckStructure(rules);
    expect(errors.some((error) => error.message.includes('не найдено'))).toBe(true);
  });
});

describe('validateRuleReferences check', () => {
  it('резолвит parent, характеристику и состояние', () => {
    const rules: Rule[] = [
      baseRule('c', 'willpower', 'characteristic'),
      baseRule('s', 'exhaustion', 'state', { value_type: 'dimensional', aggregation: 'max' }),
      baseRule('p', 'check-simple', 'check', {
        type: 'check',
        difficulty_input: { kind: 'ask' },
        allowed_modes: 'both',
      }),
      baseRule('x', 'check-exhaustion', 'check', {
        type: 'check',
        parent_check_code: 'check-simple',
        characteristic_code: 'willpower',
        difficulty_input: { kind: 'from_state', state_code: 'exhaustion' },
        allowed_modes: 'solo',
      }),
    ];
    expect(ruleValidationService.validateRuleReferences(rules, [])).toEqual([]);
  });
});

describe('validateDamageTypeStructure', () => {
  it('требует спеку type damage_type', () => {
    const errors = ruleValidationService.validateDamageTypeStructure([baseRule('dt', 'cold', 'damage_type')]);
    expect(errors[0]?.message).toContain('type damage_type');
  });

  it('требует родительный и дательный', () => {
    const errors = ruleValidationService.validateDamageTypeStructure([
      baseRule('dt', 'cold', 'damage_type', {
        type: 'damage_type',
        forms: { genitive: '  ', dative: '' },
        attached_rule_codes: [],
      }),
    ]);
    expect(errors[0]?.message).toContain('родительный и дательный');
  });
});
