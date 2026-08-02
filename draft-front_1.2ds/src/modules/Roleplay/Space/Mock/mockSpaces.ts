import type { Space } from '@/modules/Roleplay/Space/Dto/Space'
import type { SpaceCreateData } from '@/modules/Roleplay/Space/Dto/SpaceCreateData'
import type { SpaceUpdateData } from '@/modules/Roleplay/Space/Dto/SpaceUpdateData'
import type { SpaceRevisionMeta } from '@/modules/Roleplay/Space/Dto/SpaceRevisionMeta'
import type { SpaceRevision } from '@/modules/Roleplay/Space/Dto/SpaceRevision'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { RuleSpec } from '@/modules/Roleplay/Rule/Enum/RuleSpec'
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules'
import { slugify } from '@/modules/Roleplay/Rule/Utils/Text/slugify'

let nextId = 3

// Единый источник правил пространств — каталог из Rule-модуля (ID и code согласованы).
const revisionRulePool: Rule[] = ruleCatalog

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
  let inheritedRules: Rule[] | null = null

  if (data.inheritFrom) {
    const parent = spaces.find(s => s.id === data.inheritFrom)
    if (parent) {
      inheritedRules = generateRevisionRules(parent.id, parent.revision)
      rulesCount = inheritedRules.length
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

  if (inheritedRules) {
    revisionCache.set(`${space.id}:0`, {
      revision: 0,
      publishedAt: new Date().toISOString(),
      rules: inheritedRules.map(r => ({ ...r, spaceId: space.id })),
    })
  }

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

function collectReferencedCodes(spec: RuleSpec | undefined): string[] {
  const refs = new Set<string>()
  const walk = (node: unknown) => {
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
