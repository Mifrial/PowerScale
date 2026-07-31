import type { Rule, CreateRuleData, UpdateRuleData, RuleVersion } from '../Interface/types'
import { slugify } from '@/modules/Core/Utils/slugify'

let nextVersionId = 10

const rules: Rule[] = [
  {
    id: 'rule-1',
    code: 'rule-6-and-1',
    type: 'simple',
    name: 'Правило 6 и 1',
    description: 'При броске кубика: 1 даёт дополнительный успех, 6 убирает один успех.',
    spaceId: 1,
    tagIds: [1, 2],
    mechanicId: 1,
    createdAt: '2026-01-15T10:00:00Z',
  },
  {
    id: 'rule-2',
    code: 'advantages',
    type: 'simple',
    name: 'Помехи и преимущества',
    description: 'Преимущество добавляет кубик и убирает худший результат. Помеха добавляет кубик и убирает лучший результат.',
    spaceId: 1,
    tagIds: [1, 3],
    mechanicId: 2,
    createdAt: '2026-01-16T10:00:00Z',
  },
  {
    id: 'rule-3',
    code: 'strength',
    type: 'characteristic',
    name: 'Сила',
    description: 'Физическая сила персонажа.',
    spaceId: 1,
    spec: { type: 'characteristic' },
    tagIds: [4],
    mechanicId: null,
    createdAt: '2026-01-17T10:00:00Z',
  },
  {
    id: 'rule-7',
    code: 'dexterity',
    type: 'characteristic',
    name: 'Ловкость',
    description: 'Физическая ловкость персонажа.',
    spaceId: 1,
    spec: { type: 'characteristic' },
    tagIds: [4],
    mechanicId: null,
    createdAt: '2026-01-17T10:00:00Z',
  },
  {
    id: 'rule-8',
    code: 'memory',
    type: 'characteristic',
    name: 'Память',
    description: 'Способность запоминать информацию.',
    spaceId: 1,
    spec: { type: 'characteristic' },
    tagIds: [4],
    mechanicId: null,
    createdAt: '2026-01-17T10:00:00Z',
  },
  {
    id: 'rule-9',
    code: 'reasoning',
    type: 'characteristic',
    name: 'Мышление',
    description: 'Способность анализировать и принимать решения.',
    spaceId: 1,
    spec: { type: 'characteristic' },
    tagIds: [4],
    mechanicId: null,
    createdAt: '2026-01-17T10:00:00Z',
  },
  {
    id: 'rule-10',
    code: 'intellect',
    type: 'characteristic',
    name: 'Интеллект',
    description: 'Общая умственная способность.',
    spaceId: 1,
    spec: { type: 'characteristic', formula: 'min(memory, reasoning)' },
    tagIds: [4],
    mechanicId: null,
    createdAt: '2026-01-17T10:00:00Z',
  },
  {
    id: 'rule-4',
    code: 'double-strike',
    type: 'ability',
    name: 'Двойной удар',
    description: 'Атака, наносящая два удара подряд.',
    spaceId: 1,
    spec: {
      zones: { or: { kind: 'array', levels_cost: [2, 3, 4] } },
      requirements: [{ level: 1, requirements: [{ type: 'has_ability', ability_code: 'melee-fighting', min_level: 1 }] }],
      grants: [],
      action_costs: [{ resource_code: 'action-points', amount: 1 }],
      parent_ability_code: null,
    },
    tagIds: [5, 6],
    mechanicId: 3,
    createdAt: '2026-01-18T10:00:00Z',
  },
  {
    id: 'rule-5',
    code: 'sword',
    type: 'item',
    name: 'Меч',
    description: 'Простой одноручный меч.',
    spaceId: 1,
    spec: {
      category: 'equipment',
      cost_gm: 500,
      weight: { base: 1, size: 5 },
      special_rule_codes: ['rule-6-and-1'],
      weapon: {
        min_strength: { base: 3, size: 0 },
        block_profile: null,
        weapon_profiles: [
          {
            type: 'strike',
            distance: { type: 'fixed', value: 1 },
            range: null,
            damage: {
              formula: { type: 'characteristic', characteristic_code: 'strength', modifier: 0 },
              damage_type_code: 'slashing',
            },
            penetration: { type: 'characteristic', characteristic_code: 'strength', modifier: 0 },
            accuracy: { base: 4, size: 0 },
          },
        ],
      },
    },
    tagIds: [1],
    mechanicId: null,
    createdAt: '2026-01-19T10:00:00Z',
  },
  {
    id: 'rule-6',
    code: 'human',
    type: 'race',
    name: 'Человек',
    description: 'Человеческая раса.',
    spaceId: 1,
    spec: {
      parent_race_code: null,
      cost_os: 0,
      characteristics: [
        {
          characteristic_code: 'strength',
          mode: 'purchased',
          base: { base: 3, size: 0 },
          purchase: [
            { cost: 1, value: { base: 4, size: 0 } },
            { cost: 3, value: { base: 5, size: 0 } },
          ],
        },
        {
          characteristic_code: 'dexterity',
          mode: 'purchased',
          base: { base: 3, size: 0 },
          purchase: [],
        },
      ],
      abilities: [],
    },
    tagIds: [8],
    mechanicId: null,
    createdAt: '2026-01-20T10:00:00Z',
  },
  {
    id: 'rule-11',
    code: 'slashing',
    type: 'damage_type',
    name: 'Рубящий',
    description: 'Рубящие повреждения (топоры, мечи).',
    spaceId: 1,
    tagIds: [],
    mechanicId: null,
    createdAt: '2026-01-21T10:00:00Z',
  },
  {
    id: 'rule-12',
    code: 'piercing',
    type: 'damage_type',
    name: 'Колющий',
    description: 'Колющие повреждения (копья, стрелы).',
    spaceId: 1,
    tagIds: [],
    mechanicId: null,
    createdAt: '2026-01-22T10:00:00Z',
  },
  {
    id: 'rule-13',
    code: 'blunt',
    type: 'damage_type',
    name: 'Дробящий',
    description: 'Дробящие повреждения (булавы, молоты).',
    spaceId: 1,
    tagIds: [],
    mechanicId: null,
    createdAt: '2026-01-23T10:00:00Z',
  },
  {
    id: 'rule-14',
    code: 'cutting',
    type: 'damage_type',
    name: 'Режущий',
    description: 'Режущие повреждения (кинжалы, серпы).',
    spaceId: 1,
    tagIds: [],
    mechanicId: null,
    createdAt: '2026-01-24T10:00:00Z',
  },
  {
    id: 'rule-15',
    code: 'fire',
    type: 'damage_type',
    name: 'Огонь',
    description: 'Повреждения огнём и высокой температурой.',
    spaceId: 1,
    tagIds: [],
    mechanicId: null,
    createdAt: '2026-01-25T10:00:00Z',
  },
  {
    id: 'rule-16',
    code: 'electricity',
    type: 'damage_type',
    name: 'Электричество',
    description: 'Повреждения электричеством.',
    spaceId: 1,
    tagIds: [],
    mechanicId: null,
    createdAt: '2026-01-26T10:00:00Z',
  },
  {
    id: 'rule-17',
    code: 'light',
    type: 'damage_type',
    name: 'Свет',
    description: 'Повреждения светом и божественной энергией.',
    spaceId: 1,
    tagIds: [],
    mechanicId: null,
    createdAt: '2026-01-27T10:00:00Z',
  },
  {
    id: 'rule-18',
    code: 'action-points',
    type: 'resource',
    name: 'Очки Действий',
    description: 'ОД — ресурс действий в бою.',
    spaceId: 1,
    spec: { is_dimensional: false, initial_value: 3 },
    tagIds: [1],
    mechanicId: null,
    createdAt: '2026-01-28T10:00:00Z',
  },
  {
    id: 'rule-19',
    code: 'spirit-energy',
    type: 'resource',
    name: 'Ци Духа',
    description: 'Энергия духа для техник.',
    spaceId: 1,
    spec: { is_dimensional: true, initial_value: { base: 3, size: 0 } },
    tagIds: [3],
    mechanicId: null,
    createdAt: '2026-01-28T10:00:00Z',
  },
  {
    id: 'rule-20',
    code: 'mana',
    type: 'resource',
    name: 'Мана',
    description: 'Магическая энергия для заклинаний.',
    spaceId: 1,
    spec: { is_dimensional: false, initial_value: 10 },
    tagIds: [3],
    mechanicId: null,
    createdAt: '2026-01-28T10:00:00Z',
  },
  {
    id: 'rule-21',
    code: 'melee-fighting',
    type: 'ability',
    name: 'Ближний бой',
    description: 'Владение оружием ближнего боя.',
    spaceId: 1,
    spec: {
      zones: {
        os: { kind: 'array', levels_cost: [1, 2, 3] },
        or: { kind: 'array', levels_cost: [2, 3, 4] },
      },
      requirements: [
        {
          level: 2,
          requirements: [{ type: 'resource_limit', resource_code: 'action-points', min: 2 }],
        },
      ],
      grants: [
        {
          level: 1,
          grants: [
            { type: 'tag', tag_code: 'combat', remove: false },
            { type: 'characteristic_modify', characteristic_code: 'strength', amount: { type: 'ability_level', ability_code: 'melee-fighting', multiplier: 1, offset: 0 }, source_id: 5 },
          ],
        },
      ],
      action_costs: [],
      parent_ability_code: null,
    },
    tagIds: [5],
    mechanicId: null,
    createdAt: '2026-01-28T10:00:00Z',
  },
  {
    id: 'rule-22',
    code: 'os',
    type: 'points',
    name: 'Очки Создания',
    description: 'Очки, используемые на этапе «Создание» (врождённые черты, расы).',
    spaceId: 1,
    tagIds: [],
    mechanicId: null,
    createdAt: '2026-01-28T10:00:00Z',
  },
  {
    id: 'rule-23',
    code: 'ol',
    type: 'points',
    name: 'Очки Личности',
    description: 'Очки, используемые на этапе «Личность» (особенности личности).',
    spaceId: 1,
    tagIds: [],
    mechanicId: null,
    createdAt: '2026-01-28T10:00:00Z',
  },
  {
    id: 'rule-24',
    code: 'or',
    type: 'points',
    name: 'Очки Развития',
    description: 'Очки, используемые на этапе «Развитие» (навыки и черты).',
    spaceId: 1,
    tagIds: [],
    mechanicId: null,
    createdAt: '2026-01-28T10:00:00Z',
  },
  {
    id: 'rule-25',
    code: 'keen-hearing',
    type: 'ability',
    name: 'Острый слух',
    description: 'Тонкий слух, позволяющий улавливать звуки на большом расстоянии.',
    spaceId: 1,
    spec: {
      type: 'trait',
      zones: { os: { kind: 'automatic' } },
      requirements: [],
      grants: [],
      action_costs: [],
      parent_ability_code: null,
    },
    tagIds: [8, 11],
    mechanicId: null,
    createdAt: '2026-01-28T10:00:00Z',
  },
  {
    id: 'rule-26',
    code: 'night-vision',
    type: 'ability',
    name: 'Ночное зрение',
    description: 'Способность видеть в полной темноте.',
    spaceId: 1,
    spec: {
      type: 'trait',
      zones: { os: { kind: 'automatic' } },
      requirements: [],
      grants: [],
      action_costs: [],
      parent_ability_code: null,
    },
    tagIds: [8, 11],
    mechanicId: null,
    createdAt: '2026-01-28T10:00:00Z',
  },
  {
    id: 'rule-27',
    code: 'elves',
    type: 'species',
    name: 'Эльфы',
    description: 'Вид древних существ, известных изяществом и связью с природой.',
    spaceId: 1,
    spec: {
      parent_race_code: null,
      abilities: [{ ability_code: 'keen-hearing', automatic: true }],
    },
    tagIds: [8],
    mechanicId: null,
    createdAt: '2026-01-29T10:00:00Z',
  },
  {
    id: 'rule-28',
    code: 'wood-elves',
    type: 'species',
    name: 'Лесные эльфы',
    description: 'Подвид эльфов, живущий в лесах.',
    spaceId: 1,
    spec: {
      parent_race_code: 'elves',
      abilities: [],
    },
    tagIds: [8],
    mechanicId: null,
    createdAt: '2026-01-29T10:00:00Z',
  },
  {
    id: 'rule-29',
    code: 'elf',
    type: 'race',
    name: 'Эльф',
    description: 'Эльфийская раса. Наследует способности вида «Эльфы».',
    spaceId: 1,
    spec: {
      parent_race_code: 'elves',
      cost_os: 8,
      characteristics: [
        { characteristic_code: 'dexterity', mode: 'fixed', base: { base: 4, size: 0 } },
        { characteristic_code: 'memory', mode: 'fixed', base: { base: 3, size: 0 } },
      ],
      abilities: [{ ability_code: 'night-vision', automatic: true }],
    },
    tagIds: [8, 11],
    mechanicId: null,
    createdAt: '2026-01-30T10:00:00Z',
  },
  {
    id: 'rule-30',
    code: 'wood-elf',
    type: 'race',
    name: 'Лесной эльф',
    description: 'Лесной эльф. Наследует способности вида «Лесные эльфы» и «Эльфы».',
    spaceId: 1,
    spec: {
      parent_race_code: 'wood-elves',
      cost_os: 10,
      characteristics: [
        { characteristic_code: 'dexterity', mode: 'fixed', base: { base: 4, size: 0 } },
        { characteristic_code: 'memory', mode: 'fixed', base: { base: 3, size: 0 } },
      ],
      abilities: [
        { ability_code: 'night-vision', automatic: true },
        { ability_code: 'keen-hearing', automatic: true },
      ],
    },
    tagIds: [8, 11],
    mechanicId: null,
    createdAt: '2026-01-30T10:00:00Z',
  },
]

const ruleVersions: RuleVersion[] = rules.map((r, idx) => ({
  id: idx + 1,
  ruleId: r.id,
  code: r.code,
  spaceId: 1,
  versionA: 1,
  versionB: 0,
  versionC: 0,
  name: r.name,
  description: r.description,
  spec: r.spec,
  tagIds: r.tagIds,
  mechanicId: r.mechanicId,
  createdAt: r.createdAt,
}))

const delay = (ms = 300) => new Promise(r => setTimeout(r, ms))

export async function fetchRules(spaceId: number, _signal?: AbortSignal): Promise<Rule[]> {
  await delay()
  return rules.filter(r => true).map(r => ({ ...r }))
}

export async function fetchRule(ruleId: string, _signal?: AbortSignal): Promise<Rule> {
  await delay()
  const rule = rules.find(r => r.id === ruleId)
  if (!rule) throw new Error(`Rule ${ruleId} not found`)
  return { ...rule }
}

export async function fetchRuleVersions(ruleId: string, _signal?: AbortSignal): Promise<RuleVersion[]> {
  await delay()
  return ruleVersions.filter(v => v.ruleId === ruleId).map(v => ({ ...v }))
}

export async function createRule(spaceId: number, data: CreateRuleData, _signal?: AbortSignal): Promise<Rule> {
  await delay()
  const rule: Rule = {
    id: `rule-${rules.length + 1}`,
    code: data.code ?? slugify(data.name),
    type: data.type,
    name: data.name,
    description: data.description,
    spaceId,
    spec: data.spec,
    tagIds: data.tagIds,
    mechanicId: data.mechanicId,
    createdAt: new Date().toISOString(),
  }
  rules.push(rule)
  
  ruleVersions.push({
    id: nextVersionId++,
    ruleId: rule.id,
    code: rule.code,
    spaceId,
    versionA: 1,
    versionB: 0,
    versionC: 0,
    name: rule.name,
    description: rule.description,
    spec: rule.spec,
    tagIds: rule.tagIds,
    mechanicId: rule.mechanicId,
    createdAt: rule.createdAt,
  })
  
  return { ...rule }
}

export async function updateRule(ruleId: string, data: UpdateRuleData, _signal?: AbortSignal): Promise<Rule> {
  await delay()
  const rule = rules.find(r => r.id === ruleId)
  if (!rule) throw new Error(`Rule ${ruleId} not found`)
  if (data.code !== undefined) rule.code = data.code
  if (data.name !== undefined) rule.name = data.name
  if (data.description !== undefined) rule.description = data.description
  if (data.spec !== undefined) rule.spec = data.spec
  if (data.tagIds !== undefined) rule.tagIds = data.tagIds
  if (data.mechanicId !== undefined) rule.mechanicId = data.mechanicId
  return { ...rule }
}

export async function deleteRule(ruleId: string, _signal?: AbortSignal): Promise<void> {
  await delay()
  const idx = rules.findIndex(r => r.id === ruleId)
  if (idx !== -1) rules.splice(idx, 1)
}
