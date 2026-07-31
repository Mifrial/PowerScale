import { describe, it, expect } from 'vitest'
import { validateRuleReferences, validateAbilityStructure, validateRaceStructure, validateSpeciesStructure, findSpeciesCycle } from '../ruleValidation'
import { resolveAbilityTypeFromTags, pruneAbilitySpecForType } from '../../Interface/abilityTypes'
import { pruneItemSpecBySubtypes } from '../../Interface/itemTypes'
import { collectInheritedAbilities } from '../../Interface/raceTypes'
import type { Rule } from '../../Interface/types'

const baseRule = (id: string, code: string, type: Rule['type'], spec?: any): Rule => ({
  id,
  code,
  type,
  name: code,
  description: '',
  spaceId: 1,
  spec,
  createdAt: '2026-01-01T00:00:00Z',
})

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
              damage: { formula: { type: 'characteristic', characteristic_code: 'strength', modifier: 0 }, damage_type_code: 'slashing' },
              penetration: { type: 'characteristic', characteristic_code: 'strength', modifier: 0 },
            },
          ],
        },
      }),
    ]
    expect(validateRuleReferences(rules, [])).toEqual([])
  })

  it('reports missing characteristic code in item formula', () => {
    const rules: Rule[] = [
      baseRule('dt', 'slashing', 'damage_type'),
      baseRule('i', 'sword', 'item', {
        weapon: {
          weapon_profiles: [
            {
              damage: { formula: { type: 'characteristic', characteristic_code: 'strength', modifier: 0 }, damage_type_code: 'slashing' },
              penetration: { type: 'fixed', value: 1 },
            },
          ],
        },
      }),
    ]
    const errors = validateRuleReferences(rules, [])
    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ ruleCode: 'sword', refCode: 'strength', expectedType: 'characteristic' })
  })

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
    ]
    const errors = validateRuleReferences(rules, [])
    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ refCode: 'strength', expectedType: 'damage_type' })
  })

  it('validates ability references: requirements, grants, action costs', () => {
    const rules: Rule[] = [
      baseRule('a', 'melee-fighting', 'ability'),
      baseRule('ap', 'action-points', 'resource', { is_dimensional: true, initial_value: 3 }),
      baseRule('s', 'strength', 'characteristic'),
      baseRule('ds', 'double-strike', 'ability', {
        requirements: [{ level: 1, requirements: [{ type: 'has_ability', ability_code: 'melee-fighting', min_level: 1 }] }],
        grants: [
          { level: 1, grants: [{ type: 'characteristic_modify', characteristic_code: 'strength', amount: { type: 'fixed', value: 1 }, permanent: true }] },
        ],
        action_costs: [{ resource_code: 'action-points', amount: 1 }],
        parent_ability_code: null,
      }),
    ]
    expect(validateRuleReferences(rules, [])).toEqual([])
  })

  it('reports missing resource in ability action cost', () => {
    const rules: Rule[] = [
      baseRule('ds', 'double-strike', 'ability', {
        requirements: [],
        grants: [],
        action_costs: [{ resource_code: 'action-points', amount: 1 }],
        parent_ability_code: null,
      }),
    ]
    const errors = validateRuleReferences(rules, [])
    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ refCode: 'action-points', expectedType: 'resource' })
  })

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
              { type: 'characteristic_modify', characteristic_code: 'strength', amount: { type: 'ability_level', ability_code: 'melee-fighting', multiplier: 1, offset: 0 } },
            ],
          },
        ],
        action_costs: [],
        parent_ability_code: null,
      }),
    ]
    expect(validateRuleReferences(rules, [])).toEqual([])
  })

  it('validates tag references against provided tags', () => {
    const rules: Rule[] = [
      baseRule('a', 'melee-fighting', 'ability', {
        requirements: [{ level: 1, requirements: [{ type: 'has_ability_tag', tag_code: 'combat', min_count: 1 }] }],
        grants: [],
        action_costs: [],
        parent_ability_code: null,
      }),
    ]
    expect(validateRuleReferences(rules, [{ code: 'combat', name: 'Боевое' }])).toEqual([])
    const errors = validateRuleReferences(rules, [])
    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ refCode: 'combat', expectedType: 'tag' })
  })

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
        action_costs: [],
        parent_ability_code: null,
      }),
    ]
    expect(validateRuleReferences(rules, [])).toEqual([])

    const bad = [
      baseRule('mf', 'melee-fighting', 'ability', {
        requirements: [
          {
            level: 2,
            requirements: [{ type: 'resource_limit', resource_code: 'missing-resource', min: { base: 2, size: 0 } }],
          },
        ],
        grants: [],
        action_costs: [],
        parent_ability_code: null,
      }),
    ]
    const errors = validateRuleReferences(bad, [])
    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ refCode: 'missing-resource', expectedType: 'resource' })
  })

  it('validates characteristic formula string references', () => {
    const rules: Rule[] = [
      baseRule('m', 'memory', 'characteristic'),
      baseRule('r', 'reasoning', 'characteristic'),
      baseRule('i', 'intellect', 'characteristic', { formula: 'min(memory, reasoning)' }),
    ]
    expect(validateRuleReferences(rules, [])).toEqual([])

    const bad = [
      baseRule('m', 'memory', 'characteristic'),
      baseRule('i', 'intellect', 'characteristic', { formula: 'min(memory, dexterity)' }),
    ]
    const errors = validateRuleReferences(bad, [])
    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ refCode: 'dexterity', expectedType: 'characteristic' })
  })

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
    ]
    expect(validateRuleReferences(rules, [])).toEqual([])
  })

  it('reports race parent referencing a race instead of a species', () => {
    const rules: Rule[] = [
      baseRule('r2', 'human', 'race'),
      baseRule('r', 'elf', 'race', {
        parent_race_code: 'human',
        cost_os: 8,
        characteristics: [],
        abilities: [],
      }),
    ]
    const errors = validateRuleReferences(rules, [])
    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ refCode: 'human', expectedType: 'species' })
  })

  it('reports missing characteristic in race characteristics', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8,
        characteristics: [{ characteristic_code: 'missing-stat', mode: 'fixed', base: { base: 3, size: 0 } }],
        abilities: [],
      }),
    ]
    const errors = validateRuleReferences(rules, [])
    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ refCode: 'missing-stat', expectedType: 'characteristic' })
  })

  it('validates species references: parent species and abilities', () => {
    const rules: Rule[] = [
      baseRule('sp', 'elves', 'species', { parent_race_code: null, abilities: [] }),
      baseRule('a', 'keen-hearing', 'ability'),
      baseRule('sp2', 'wood-elves', 'species', {
        parent_race_code: 'elves',
        abilities: [{ ability_code: 'keen-hearing', automatic: true }],
      }),
    ]
    expect(validateRuleReferences(rules, [])).toEqual([])
  })

  it('validates ability zone keys reference point rules', () => {
    const rules: Rule[] = [
      baseRule('p', 'os', 'points'),
      baseRule('a', 'keen-hearing', 'ability', {
        zones: { os: { kind: 'automatic' } },
        requirements: [], grants: [], action_costs: [], parent_ability_code: null,
      }),
    ]
    expect(validateRuleReferences(rules, [])).toEqual([])
  })

  it('reports a missing point rule for a zone key', () => {
    const rules: Rule[] = [
      baseRule('a', 'keen-hearing', 'ability', {
        zones: { missing: { kind: 'automatic' } },
        requirements: [], grants: [], action_costs: [], parent_ability_code: null,
      }),
    ]
    const errors = validateRuleReferences(rules, [])
    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ refCode: 'missing', expectedType: 'points' })
  })
})

describe('resolveAbilityTypeFromTags', () => {
  it('maps tag combinations to ability types by precedence', () => {
    expect(resolveAbilityTypeFromTags(['trait'])).toBe('trait')
    expect(resolveAbilityTypeFromTags(['feature'])).toBe('feature')
    expect(resolveAbilityTypeFromTags(['skill'])).toBe('skill')
    expect(resolveAbilityTypeFromTags(['skill', 'action'])).toBe('action')
    expect(resolveAbilityTypeFromTags(['skill', 'action', 'process'])).toBe('process')
    expect(resolveAbilityTypeFromTags(['skill', 'magic', 'action', 'spell'])).toBe('spell')
  })

  it('prefers most specific type when multiple distinctive tags present', () => {
    expect(resolveAbilityTypeFromTags(['action', 'spell'])).toBe('spell')
    expect(resolveAbilityTypeFromTags(['process', 'action'])).toBe('process')
    expect(resolveAbilityTypeFromTags(['action', 'trait'])).toBe('action')
  })

  it('returns null for unrelated tags or empty set', () => {
    expect(resolveAbilityTypeFromTags([])).toBeNull()
    expect(resolveAbilityTypeFromTags(['combat', 'utility'])).toBeNull()
  })
})

describe('validateAbilityStructure', () => {
  const tags = [
    { id: 1, code: 'skill', name: 'Навык' },
    { id: 2, code: 'action', name: 'Действие' },
    { id: 3, code: 'process', name: 'Процесс' },
    { id: 4, code: 'spell', name: 'Заклинание' },
  ]

  it('requires an action point cost for action type', () => {
    const rules: Rule[] = [
      baseRule('a', 'strike', 'ability', {
        type: 'action',
        action_costs: [],
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ]
    const errors = validateAbilityStructure(rules, tags)
    expect(errors).toHaveLength(1)
    expect(errors[0]).toMatchObject({ ruleCode: 'strike' })
    expect(errors[0].message).toContain('1 ОД')
  })

  it('passes action type with an action point cost', () => {
    const rules: Rule[] = [
      baseRule('a', 'strike', 'ability', {
        type: 'action',
        action_costs: [{ resource_code: 'action-points', amount: 1 }],
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ]
    expect(validateAbilityStructure(rules, tags)).toEqual([])
  })

  it('requires at least two steps for a process', () => {
    const rules: Rule[] = [
      baseRule('p', 'movement', 'ability', {
        type: 'process',
        action_costs: [],
        process: { steps: [], transition: { mode: 'chain', max_shift: 1 } },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ]
    const errors = validateAbilityStructure(rules, tags)
    expect(errors.some(e => e.message.includes('минимум 2 шага'))).toBe(true)
  })

  it('validates process steps have action point cost and existing start step', () => {
    const rules: Rule[] = [
      baseRule('p', 'movement', 'ability', {
        type: 'process',
        action_costs: [],
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
    ]
    const errors = validateAbilityStructure(rules, tags)
    expect(errors.some(e => e.message.includes('«missing» не существует'))).toBe(true)
    expect(errors.some(e => e.message.includes('1 ОД'))).toBe(false)
  })

  it('flags a process step without an action point cost', () => {
    const rules: Rule[] = [
      baseRule('p', 'movement', 'ability', {
        type: 'process',
        action_costs: [],
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
    ]
    const errors = validateAbilityStructure(rules, tags)
    expect(errors.some(e => e.message.includes('требует минимум 1 ОД'))).toBe(true)
  })

  it('validates custom transition edges reference existing steps', () => {
    const rules: Rule[] = [
      baseRule('p', 'movement', 'ability', {
        type: 'process',
        action_costs: [],
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
    ]
    const errors = validateAbilityStructure(rules, tags)
    expect(errors.some(e => e.message.includes('шаг «sprint» не существует'))).toBe(true)
  })

  it('requires difficulty and validates material item for a spell', () => {
    const rules: Rule[] = [
      baseRule('i', 'ash', 'item'),
      baseRule('sp', 'fire-bolt', 'ability', {
        type: 'spell',
        action_costs: [{ resource_code: 'action-points', amount: 1, label: 'Сотворение' }],
        spell: {
          duration: { type: 'instant' },
          components: [
            { type: 'material', item_code: 'ash' },
            { type: 'material', item_code: 'missing-item' },
          ],
        },
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }),
    ]
    const errors = validateAbilityStructure(rules, tags)
    expect(errors.some(e => e.message.includes('сложность сотворения'))).toBe(true)
    expect(errors.some(e => e.message.includes('отсутствующий предмет «missing-item»'))).toBe(true)
    expect(errors.some(e => e.message.includes('отсутствующий предмет «ash»'))).toBe(false)
  })

  it('derives type from tags when spec.type is absent', () => {
    const rules: Rule[] = [
      { ...baseRule('a', 'strike', 'ability', {
        action_costs: [],
        grants: [],
        requirements: [],
        parent_ability_code: null,
      }), tagIds: [1, 2] },
    ]
    const errors = validateAbilityStructure(rules, tags)
    expect(errors.some(e => e.message.includes('1 ОД'))).toBe(true)
  })
})

describe('pruneAbilitySpecForType', () => {
  const draft = {
    type: undefined,
    zones: {},
    requirements: [{ level: 1, requirements: [] }],
    grants: [],
    action_costs: [{ resource_code: 'action-points', amount: 1 }],
    process: { steps: [], transition: { mode: 'chain' as const, max_shift: 1 } },
    spell: { difficulty: { base: 3, size: 0 }, duration: { type: 'instant' as const }, components: [] },
    parent_ability_code: null,
  }

  it('keeps action_costs only for action', () => {
    const out = pruneAbilitySpecForType({ ...draft }, 'action') as any
    expect(out.type).toBe('action')
    expect(out.action_costs).toHaveLength(1)
    expect(out.process).toBeUndefined()
    expect(out.spell).toBeUndefined()
  })

  it('keeps process only for process and clears action_costs', () => {
    const out = pruneAbilitySpecForType({ ...draft }, 'process') as any
    expect(out.type).toBe('process')
    expect(out.process).toBeDefined()
    expect(out.action_costs).toBeUndefined()
    expect(out.spell).toBeUndefined()
  })

  it('keeps spell and action_costs for spell', () => {
    const out = pruneAbilitySpecForType({ ...draft }, 'spell') as any
    expect(out.type).toBe('spell')
    expect(out.spell).toBeDefined()
    expect(out.action_costs).toHaveLength(1)
    expect(out.process).toBeUndefined()
  })

  it('drops all type-specific fields for skill', () => {
    const out = pruneAbilitySpecForType({ ...draft }, 'skill') as any
    expect(out.type).toBe('skill')
    expect(out.action_costs).toBeUndefined()
    expect(out.process).toBeUndefined()
    expect(out.spell).toBeUndefined()
  })

  it('keeps shared fields (zones, requirements, grants) always', () => {
    const out = pruneAbilitySpecForType({ ...draft }, 'skill') as any
    expect(out.zones).toEqual({})
    expect(out.requirements).toHaveLength(1)
    expect(out.grants).toEqual([])
    expect(out.parent_ability_code).toBeNull()
  })
})

describe('pruneItemSpecBySubtypes', () => {
  const draft = {
    category: 'equipment' as const,
    cost_gm: 100,
    weight: { base: 1, size: 0 },
    special_rule_codes: [],
    innate: false,
    weapon: { min_strength: null, block_profile: null, weapon_profiles: [] },
    armor: { defense_slots: [], resistance_slots: [], characteristic_limits: [] },
    shield: { min_strength: null, block: { efficiency: { base: 1, size: 0 }, defense: 1, resistances: [] } },
  }

  it('keeps only blocks of active subtypes', () => {
    const out = pruneItemSpecBySubtypes({ ...draft }, ['weapon', 'shield']) as any
    expect(out.weapon).toBeDefined()
    expect(out.shield).toBeDefined()
    expect(out.armor).toBeUndefined()
  })

  it('keeps shared fields always', () => {
    const out = pruneItemSpecBySubtypes({ ...draft }, []) as any
    expect(out.category).toBe('equipment')
    expect(out.cost_gm).toBe(100)
    expect(out.weapon).toBeUndefined()
    expect(out.armor).toBeUndefined()
    expect(out.shield).toBeUndefined()
  })
})

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
    ]
    expect(validateRaceStructure(rules)).toEqual([])
  })

  it('flags non-integer cost_os', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8.5,
        characteristics: [],
        abilities: [],
      }),
    ]
    const errors = validateRaceStructure(rules)
    expect(errors.some(e => e.message.includes('cost_os'))).toBe(true)
  })

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
    ]
    const errors = validateRaceStructure(rules)
    expect(errors.some(e => e.message.includes('«dexterity» указана несколько раз'))).toBe(true)
  })

  it('flags empty characteristic code', () => {
    const rules: Rule[] = [
      baseRule('r', 'elf', 'race', {
        parent_race_code: null,
        cost_os: 8,
        characteristics: [{ characteristic_code: '', mode: 'fixed', base: { base: 3, size: 0 } }],
        abilities: [],
      }),
    ]
    const errors = validateRaceStructure(rules)
    expect(errors.some(e => e.message.includes('не указан код'))).toBe(true)
  })

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
    ]
    const errors = validateRaceStructure(rules)
    expect(errors.some(e => e.message.includes('стоимость должна быть ≥ 1'))).toBe(true)
    expect(errors.some(e => e.message.includes('стоимость 2 указана несколько раз'))).toBe(true)
  })

  it('flags duplicate ability codes', () => {
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
    ]
    const errors = validateRaceStructure(rules)
    expect(errors.some(e => e.message.includes('«keen-hearing» указана несколько раз'))).toBe(true)
  })
})

describe('validateSpeciesStructure', () => {
  it('passes for a well-formed species', () => {
    const rules: Rule[] = [
      baseRule('sp', 'elves', 'species', { parent_race_code: null, abilities: [{ ability_code: 'keen-hearing', automatic: true }] }),
    ]
    expect(validateSpeciesStructure(rules)).toEqual([])
  })

  it('flags duplicate ability codes', () => {
    const rules: Rule[] = [
      baseRule('sp', 'elves', 'species', {
        parent_race_code: null,
        abilities: [
          { ability_code: 'keen-hearing', automatic: true },
          { ability_code: 'keen-hearing', automatic: false },
        ],
      }),
    ]
    const errors = validateSpeciesStructure(rules)
    expect(errors.some(e => e.message.includes('«keen-hearing» указана несколько раз'))).toBe(true)
  })

  it('flags empty ability code', () => {
    const rules: Rule[] = [
      baseRule('sp', 'elves', 'species', {
        parent_race_code: null,
        abilities: [{ ability_code: '', automatic: false }],
      }),
    ]
    const errors = validateSpeciesStructure(rules)
    expect(errors.some(e => e.message.includes('не указан код'))).toBe(true)
  })
})

describe('findSpeciesCycle', () => {
  it('returns null for an acyclic chain', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'a', 'species', { parent_race_code: null, abilities: [] }),
      baseRule('sp2', 'b', 'species', { parent_race_code: 'a', abilities: [] }),
      baseRule('sp3', 'c', 'species', { parent_race_code: 'b', abilities: [] }),
    ]
    expect(findSpeciesCycle(rules)).toBeNull()
  })

  it('detects a two-node cycle', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'a', 'species', { parent_race_code: 'b', abilities: [] }),
      baseRule('sp2', 'b', 'species', { parent_race_code: 'a', abilities: [] }),
    ]
    expect(findSpeciesCycle(rules)).toContain('a')
    expect(findSpeciesCycle(rules)).toContain('b')
  })

  it('detects a longer cycle', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'a', 'species', { parent_race_code: 'c', abilities: [] }),
      baseRule('sp2', 'b', 'species', { parent_race_code: 'a', abilities: [] }),
      baseRule('sp3', 'c', 'species', { parent_race_code: 'b', abilities: [] }),
    ]
    expect(findSpeciesCycle(rules)).toBeTruthy()
  })

  it('ignores races in the chain', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'a', 'species', { parent_race_code: null, abilities: [] }),
      baseRule('sp2', 'b', 'species', { parent_race_code: 'a', abilities: [] }),
      baseRule('r', 'elf', 'race', { parent_race_code: 'b', cost_os: 8, characteristics: [], abilities: [] }),
    ]
    expect(findSpeciesCycle(rules)).toBeNull()
  })
})

describe('collectInheritedAbilities', () => {
  it('collects abilities from the whole ancestor chain', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'elves', 'species', { parent_race_code: null, abilities: [{ ability_code: 'keen-hearing', automatic: true }] }),
      baseRule('sp2', 'wood-elves', 'species', { parent_race_code: 'elves', abilities: [{ ability_code: 'night-vision', automatic: false }] }),
      baseRule('r', 'elf', 'race', { parent_race_code: 'wood-elves', cost_os: 8, characteristics: [], abilities: [] }),
    ]
    const byCode = new Map(rules.map(r => [r.code, r]))
    const inherited = collectInheritedAbilities('wood-elves', byCode)
    expect(inherited).toHaveLength(2)
    expect(inherited[0]).toMatchObject({ ability_code: 'night-vision', fromName: 'wood-elves' })
    expect(inherited[1]).toMatchObject({ ability_code: 'keen-hearing', fromName: 'elves' })
  })

  it('returns empty when no parent is set', () => {
    const rules: Rule[] = [
      baseRule('r', 'human', 'race', { parent_race_code: null, cost_os: 0, characteristics: [], abilities: [] }),
    ]
    const byCode = new Map(rules.map(r => [r.code, r]))
    expect(collectInheritedAbilities(null, byCode)).toEqual([])
  })

  it('stops at a cycle instead of looping forever', () => {
    const rules: Rule[] = [
      baseRule('sp1', 'a', 'species', { parent_race_code: 'b', abilities: [{ ability_code: 'x', automatic: true }] }),
      baseRule('sp2', 'b', 'species', { parent_race_code: 'a', abilities: [] }),
    ]
    const byCode = new Map(rules.map(r => [r.code, r]))
    const inherited = collectInheritedAbilities('a', byCode)
    expect(inherited.length).toBeLessThanOrEqual(1)
  })
})
