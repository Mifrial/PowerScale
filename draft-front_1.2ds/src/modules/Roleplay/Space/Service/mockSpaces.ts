import type { Space, SpaceCreateData, SpaceUpdateData, SpaceRevisionMeta, SpaceRevision } from '../Interface/types'
import type { Rule } from '@/modules/Roleplay/Rule/Interface/types'
import { slugify } from '@/modules/Core/Utils/slugify'

let nextId = 3
let nextRevisionId = 6

// Реальные мок-правила для ревизий (чтобы ID совпадали с mockRules.ts)
const revisionRulePool: Rule[] = [
  { id: 'rule-1', code: 'rule-6-and-1', type: 'simple', name: 'Правило 6 и 1', description: 'При броске кубика: 1 даёт дополнительный успех, 6 убирает один успех.', spaceId: 1, tagIds: [1, 2], mechanicId: 1, createdAt: '2026-01-15T10:00:00Z' },
  { id: 'rule-2', code: 'advantages', type: 'simple', name: 'Помехи и преимущества', description: 'Преимущество добавляет кубик и убирает худший результат.', spaceId: 1, tagIds: [1, 3], mechanicId: 2, createdAt: '2026-01-16T10:00:00Z' },
  { id: 'rule-3', code: 'strength', type: 'characteristic', name: 'Сила', description: 'Физическая сила персонажа.', spaceId: 1, spec: { type: 'characteristic' }, tagIds: [4], mechanicId: null, createdAt: '2026-01-17T10:00:00Z' },
  { id: 'rule-7', code: 'dexterity', type: 'characteristic', name: 'Ловкость', description: 'Физическая ловкость персонажа.', spaceId: 1, spec: { type: 'characteristic' }, tagIds: [4], mechanicId: null, createdAt: '2026-01-17T10:00:00Z' },
  { id: 'rule-8', code: 'memory', type: 'characteristic', name: 'Память', description: 'Способность запоминать информацию.', spaceId: 1, spec: { type: 'characteristic' }, tagIds: [4], mechanicId: null, createdAt: '2026-01-17T10:00:00Z' },
  { id: 'rule-9', code: 'sword', type: 'item', name: 'Меч', description: 'Одноручный меч.', spaceId: 1, spec: {
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
          damage: { formula: { type: 'characteristic', characteristic_code: 'strength', modifier: 0 }, damage_type_code: 'slashing' },
          penetration: { type: 'characteristic', characteristic_code: 'strength', modifier: 0 },
          accuracy: { base: 4, size: 0 },
        },
      ],
    },
  }, tagIds: [5], mechanicId: null, createdAt: '2026-01-18T10:00:00Z' },
  { id: 'rule-10', code: 'longbow', type: 'item', name: 'Длинный лук', description: 'Большой лук для стрельбы на дальние дистанции.', spaceId: 1, spec: {
    category: 'equipment',
    cost_gm: 800,
    weight: { base: 1, size: 5 },
    special_rule_codes: ['advantages'],
    weapon: {
      min_strength: { base: 3, size: 0 },
      block_profile: null,
      weapon_profiles: [
        {
          type: 'shoot',
          distance: { type: 'fixed', value: 20 },
          range: { type: 'fixed', value: 60 },
          damage: { formula: { type: 'characteristic', characteristic_code: 'dexterity', modifier: 0 }, damage_type_code: 'piercing' },
          penetration: { type: 'fixed', value: 1 },
          accuracy: { base: 3, size: 0 },
        },
      ],
    },
  }, tagIds: [2], mechanicId: null, createdAt: '2026-01-18T10:00:00Z' },
  { id: 'rule-11', code: 'plate-armor', type: 'item', name: 'Пластинчатый доспех', description: 'Тяжёлый доспех из металлических пластин.', spaceId: 1, spec: {
    category: 'equipment',
    cost_gm: 1500,
    weight: { base: 5, size: 5 },
    special_rule_codes: [],
    armor: {
      defense_slots: [
        { defense: 5, durability: 3, source_id: 1 },
        { defense: 3, durability: 2, source_id: 1 },
      ],
      resistance_slots: [
        { damage_type_code: 'slashing', value: 2, durability: 3, source_id: 1 },
        { damage_type_code: 'piercing', value: 1, durability: 2, source_id: 1 },
      ],
      characteristic_limits: [
        { characteristic_code: 'strength', limit: { type: 'fixed', value: 5 } },
      ],
    },
  }, tagIds: [5], mechanicId: null, createdAt: '2026-01-18T10:00:00Z' },
  { id: 'rule-12', code: 'slashing', type: 'damage_type', name: 'Рубящий', description: 'Тип урона от рубящего оружия.', spaceId: 1, tagIds: [], mechanicId: null, createdAt: '2026-01-19T10:00:00Z' },
  { id: 'rule-13', code: 'piercing', type: 'damage_type', name: 'Колющий', description: 'Тип урона от колющего оружия.', spaceId: 1, tagIds: [], mechanicId: null, createdAt: '2026-01-19T10:00:00Z' },
  { id: 'rule-14', code: 'action-points', type: 'resource', name: 'Очки Действий', description: 'ОД — ресурс действий в бою.', spaceId: 1, spec: { is_dimensional: false, initial_value: 3 }, tagIds: [1], mechanicId: null, createdAt: '2026-01-20T10:00:00Z' },
  { id: 'rule-15', code: 'spirit-energy', type: 'resource', name: 'Ци Духа', description: 'Энергия духа для техник.', spaceId: 1, spec: { is_dimensional: true, initial_value: { base: 3, size: 0 } }, tagIds: [3], mechanicId: null, createdAt: '2026-01-20T10:00:00Z' },
  { id: 'rule-16', code: 'mana', type: 'resource', name: 'Мана', description: 'Магическая энергия для заклинаний.', spaceId: 1, spec: { is_dimensional: false, initial_value: 10 }, tagIds: [3], mechanicId: null, createdAt: '2026-01-20T10:00:00Z' },
  { id: 'rule-4', code: 'double-strike', type: 'ability', name: 'Двойной удар', description: 'Атака, наносящая два удара подряд.', spaceId: 1, spec: {
    type: 'action',
    zones: { or: { kind: 'array', levels_cost: [2, 3, 4] } },
    requirements: [{ level: 1, requirements: [{ type: 'has_ability', ability_code: 'melee-fighting', min_level: 1 }] }],
    grants: [],
    action_costs: [{ resource_code: 'action-points', amount: 1 }],
    parent_ability_code: null,
  }, tagIds: [5, 6, 13, 14], mechanicId: 3, createdAt: '2026-01-18T10:00:00Z' },
  { id: 'rule-17', code: 'melee-fighting', type: 'ability', name: 'Ближний бой', description: 'Владение оружием ближнего боя.', spaceId: 1, spec: {
    type: 'skill',
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
  }, tagIds: [5, 13], mechanicId: null, createdAt: '2026-01-18T10:00:00Z' },
  { id: 'rule-18', code: 'movement', type: 'ability', name: 'Движение', description: 'Процесс перемещения: Ходьба, Бег, Спринт. Каждый шаг стоит 1 ОД; переключение — на соседний шаг.', spaceId: 1, spec: {
    type: 'process',
    zones: { or: { kind: 'array', levels_cost: [1] } },
    requirements: [],
    grants: [],
    action_costs: [],
    process: {
      start_step_code: 'walk',
      transition: { mode: 'chain', max_shift: 1, direction: 'both' },
      failure: null,
      steps: [
        { code: 'walk', name: 'Ходьба', description: 'Спокойное перемещение. Повторим.', costs: [{ resource_code: 'action-points', amount: 1 }] },
        { code: 'run', name: 'Бег', description: 'Быстрое перемещение. Можно перейти на Ходьбу или Спринт.', costs: [{ resource_code: 'action-points', amount: 1 }] },
        { code: 'sprint', name: 'Спринт', description: 'Максимальная скорость. Повторим.', costs: [{ resource_code: 'action-points', amount: 1 }] },
      ],
    },
    parent_ability_code: null,
  }, tagIds: [13, 14, 15], mechanicId: null, createdAt: '2026-01-21T10:00:00Z' },
  { id: 'rule-19', code: 'fire-bolt', type: 'ability', name: 'Огненная стрела', description: 'Порождает сгусток пламени, бросаемый во врага.', spaceId: 1, spec: {
    type: 'spell',
    zones: { or: { kind: 'array', levels_cost: [3, 4, 5] } },
    requirements: [{ level: 1, requirements: [{ type: 'has_tag', tag_code: 'magic' }] }],
    grants: [],
    action_costs: [{ resource_code: 'action-points', amount: 1, label: 'Сотворение' }],
    spell: {
      difficulty: { base: 3, size: 0 },
      duration: { type: 'instant' },
      components: [
        { type: 'verbal', note: 'крик' },
        { type: 'somatic', note: undefined },
      ],
    },
    parent_ability_code: null,
  }, tagIds: [3, 13, 14, 16], mechanicId: null, createdAt: '2026-01-21T10:00:00Z' },
  { id: 'rule-20', code: 'keen-hearing', type: 'ability', name: 'Острый слух', description: 'Тонкий слух.', spaceId: 1, spec: {
    type: 'trait',
    zones: { os: { kind: 'automatic' } },
    requirements: [], grants: [],
    action_costs: [], parent_ability_code: null,
  }, tagIds: [8, 11], mechanicId: null, createdAt: '2026-01-22T10:00:00Z' },
  { id: 'rule-21', code: 'night-vision', type: 'ability', name: 'Ночное зрение', description: 'Видеть в темноте.', spaceId: 1, spec: {
    type: 'trait',
    zones: { os: { kind: 'automatic' } },
    requirements: [], grants: [],
    action_costs: [], parent_ability_code: null,
  }, tagIds: [8, 11], mechanicId: null, createdAt: '2026-01-22T10:00:00Z' },
  { id: 'rule-22', code: 'elves', type: 'species', name: 'Эльфы', description: 'Вид эльфов.', spaceId: 1, spec: {
    parent_race_code: null,
    abilities: [{ ability_code: 'keen-hearing', automatic: true }],
  }, tagIds: [8], mechanicId: null, createdAt: '2026-01-23T10:00:00Z' },
  { id: 'rule-23', code: 'wood-elves', type: 'species', name: 'Лесные эльфы', description: 'Подвид эльфов.', spaceId: 1, spec: {
    parent_race_code: 'elves',
    abilities: [],
  }, tagIds: [8], mechanicId: null, createdAt: '2026-01-23T10:00:00Z' },
  { id: 'rule-24', code: 'elf', type: 'race', name: 'Эльф', description: 'Эльфийская раса.', spaceId: 1, spec: {
    parent_race_code: 'elves',
    cost_os: 8,
    characteristics: [
      { characteristic_code: 'dexterity', mode: 'fixed', base: { base: 4, size: 0 } },
      { characteristic_code: 'memory', mode: 'fixed', base: { base: 3, size: 0 } },
    ],
    abilities: [{ ability_code: 'night-vision', automatic: true }],
  }, tagIds: [8, 11], mechanicId: null, createdAt: '2026-01-24T10:00:00Z' },
  { id: 'rule-25', code: 'wood-elf', type: 'race', name: 'Лесной эльф', description: 'Лесной эльф.', spaceId: 1, spec: {
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
  }, tagIds: [8, 11], mechanicId: null, createdAt: '2026-01-24T10:00:00Z' },
  { id: 'rule-26', code: 'os', type: 'points', name: 'Очки Создания', description: 'Очки этапа «Создание».', spaceId: 1, tagIds: [], mechanicId: null, createdAt: '2026-01-25T10:00:00Z' },
  { id: 'rule-27', code: 'ol', type: 'points', name: 'Очки Личности', description: 'Очки этапа «Личность».', spaceId: 1, tagIds: [], mechanicId: null, createdAt: '2026-01-25T10:00:00Z' },
  { id: 'rule-28', code: 'or', type: 'points', name: 'Очки Развития', description: 'Очки этапа «Развитие».', spaceId: 1, tagIds: [], mechanicId: null, createdAt: '2026-01-25T10:00:00Z' },
]

const spaces: Space[] = [
  {
    id: 1,
    code: 'razrabotka',
    name: 'Разработка',
    description: 'Рабочее пространство для разработки правил',
    revision: 5,
    active: true,
    createdAt: '2026-01-15T10:00:00Z',
    rulesCount: 42,
  },
  {
    id: 2,
    code: 'actual',
    name: 'Актуальные правила',
    description: 'Опубликованные правила для игроков',
    revision: 12,
    active: true,
    createdAt: '2026-02-01T09:00:00Z',
    rulesCount: 38,
  },
]

const delay = (ms = 300) => new Promise(r => setTimeout(r, ms))

export async function fetchSpaces(_signal?: AbortSignal): Promise<Space[]> {
  await delay()
  return spaces.map(s => ({ ...s }))
}

export async function fetchSpace(id: number, _signal?: AbortSignal): Promise<Space> {
  await delay()
  const space = spaces.find(s => s.id === id)
  if (!space) throw new Error(`Space ${id} not found`)
  return { ...space }
}

export async function fetchSpaceByCode(code: string, _signal?: AbortSignal): Promise<Space> {
  await delay()
  const space = spaces.find(s => s.code === code)
  if (!space) throw new Error(`Space ${code} not found`)
  return { ...space }
}

export async function createSpace(data: SpaceCreateData, _signal?: AbortSignal): Promise<Space> {
  await delay()
  
  let rulesCount = 0
  
  if (data.inheritFrom) {
    const parent = spaces.find(s => s.id === data.inheritFrom)
    if (parent) {
      rulesCount = parent.rulesCount
    }
  }
  
  const space: Space = {
    id: nextId++,
    code: slugify(data.name),
    name: data.name,
    description: data.description,
    revision: 0,
    active: true,
    createdAt: new Date().toISOString(),
    rulesCount,
  }
  spaces.push(space)
  return { ...space }
}

export async function updateSpace(id: number, data: SpaceUpdateData, _signal?: AbortSignal): Promise<Space> {
  await delay()
  const space = spaces.find(s => s.id === id)
  if (!space) throw new Error(`Space ${id} not found`)
  if (data.name !== undefined) space.name = data.name
  if (data.description !== undefined) space.description = data.description
  return { ...space }
}

export async function deactivateSpace(id: number, _signal?: AbortSignal): Promise<void> {
  await delay()
  const space = spaces.find(s => s.id === id)
  if (space) space.active = false
}

// ——— SpaceRevision mocks ———

function collectReferencedCodes(spec: any): string[] {
  const refs = new Set<string>()
  const walk = (node: any) => {
    if (!node || typeof node !== 'object') return
    if (Array.isArray(node)) {
      node.forEach(walk)
      return
    }
    for (const [key, value] of Object.entries(node)) {
      if (typeof value === 'string' && /_code$/.test(key)) {
        refs.add(value)
      } else if (typeof value === 'object') {
        walk(value)
      }
    }
  }
  walk(spec)
  return Array.from(refs)
}

function generateRevisionRules(spaceId: number, revision: number): Rule[] {
  const count = Math.min(revisionRulePool.length, 5 + Math.floor(revision * 0.5))
  const poolByCode = new Map(revisionRulePool.map(r => [r.code, r]))

  const included = new Map<string, Rule>()
  const addRule = (rule: Rule) => {
    if (included.has(rule.code)) return
    included.set(rule.code, rule)
    for (const refCode of collectReferencedCodes(rule.spec)) {
      const refRule = poolByCode.get(refCode)
      if (refRule) addRule(refRule)
    }
  }

  // Ресурсы, способности и очки попадают в срез всегда — на них ссылаются
  // из других правил (requirements, action_costs, grants, зоны способностей),
  // поэтому их отсутствие ломает «в наличии». Срез по count применяется к остальным.
  const alwaysIncluded = revisionRulePool.filter(r => r.type === 'resource' || r.type === 'ability' || r.type === 'points')
  const sliced = revisionRulePool.filter(r => r.type !== 'resource' && r.type !== 'ability' && r.type !== 'points').slice(0, count)

  for (const rule of alwaysIncluded) addRule(rule)
  for (const rule of sliced) addRule(rule)

  return Array.from(included.values()).map(r => ({
    ...r,
    spaceId,
    updatedAt: new Date(2026, 0, 15 + revision).toISOString(),
  }))
}

const revisionsCache = new Map<string, SpaceRevisionMeta[]>()

function buildRevisionsMeta(space: Space): SpaceRevisionMeta[] {
  const key = `meta:${space.id}`
  if (revisionsCache.has(key)) return revisionsCache.get(key)!
  
  const items: SpaceRevisionMeta[] = []
  for (let r = 1; r <= space.revision; r++) {
    items.push({
      revision: r,
      publishedAt: new Date(2026, 0, 10 + r * 5).toISOString(),
      ruleCount: 5 + Math.floor(r * 0.5),
      changedCount: Math.floor(Math.random() * 3) + 1,
    })
  }
  revisionsCache.set(key, items)
  return items
}

export async function fetchRevisions(spaceId: number, _signal?: AbortSignal): Promise<SpaceRevisionMeta[]> {
  await delay(200)
  const space = spaces.find(s => s.id === spaceId)
  if (!space) throw new Error(`Space ${spaceId} not found`)
  return buildRevisionsMeta(space)
}

const revisionCache = new Map<string, SpaceRevision<Rule>>()

export async function fetchRevision(spaceId: number, revision: number, _signal?: AbortSignal): Promise<SpaceRevision<Rule>> {
  await delay(300)
  const key = `${spaceId}:${revision}`
  if (revisionCache.has(key)) return revisionCache.get(key)!
  
  const space = spaces.find(s => s.id === spaceId)
  if (!space) throw new Error(`Space ${spaceId} not found`)
  if (revision > space.revision) throw new Error(`Revision ${revision} not found for space ${spaceId}`)
  
  const result: SpaceRevision<Rule> = {
    revision,
    publishedAt: new Date(2026, 0, 10 + revision * 5).toISOString(),
    rules: generateRevisionRules(spaceId, revision),
  }
  revisionCache.set(key, result)
  return result
}

export async function commitDraft(spaceId: number, rules: Rule[], _signal?: AbortSignal): Promise<SpaceRevision<Rule>> {
  await delay(500)
  const space = spaces.find(s => s.id === spaceId)
  if (!space) throw new Error(`Space ${spaceId} not found`)
  
  space.revision++
  const revision = space.revision
  const now = new Date().toISOString()
  
  const result: SpaceRevision<Rule> = {
    revision,
    publishedAt: now,
    rules: rules.map(r => ({ ...r, updatedAt: now })),
  }
  
  const key = `${spaceId}:${revision}`
  revisionCache.set(key, result)
  
  const metaKey = `meta:${spaceId}`
  revisionsCache.delete(metaKey)
  
  return result
}
