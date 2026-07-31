import type { Rule, RuleType } from '../Interface/types'
import type { AbilityType } from '../Interface/abilityTypes'
import { resolveAbilityTypeFromTags } from '../Interface/abilityTypes'

export type ReferenceTargetType = RuleType | 'tag'

export interface ReferenceError {
  ruleName: string
  ruleCode: string
  refCode: string
  expectedType: ReferenceTargetType
}

interface RefExpectation {
  code: string
  type: ReferenceTargetType
}

function expectedTypeLabel(type: ReferenceTargetType): string {
  const labels: Record<ReferenceTargetType, string> = {
    simple: 'простое правило',
    race: 'раса',
    species: 'вид/подвид',
    characteristic: 'характеристика',
    resource: 'ресурс',
    points: 'очки',
    ability: 'способность',
    item: 'предмет',
    damage_type: 'тип урона',
    tag: 'признак (тег)',
  }
  return labels[type]
}

function walkFormula(
  node: any,
  _expected: ReferenceTargetType,
  collect: (ref: RefExpectation) => void
): void {
  if (!node || typeof node !== 'object') return
  if (node.type === 'characteristic' && node.characteristic_code) {
    collect({ code: node.characteristic_code, type: 'characteristic' })
  }
  if (node.type === 'ability_level' && node.ability_code) {
    collect({ code: node.ability_code, type: 'ability' })
  }
}

function walkRequirements(
  node: any,
  collect: (ref: RefExpectation) => void
): void {
  if (!node || typeof node !== 'object') return
  if (node.type === 'and' || node.type === 'or') {
    if (Array.isArray(node.children)) {
      for (const child of node.children) walkRequirements(child, collect)
    }
    return
  }
  if (node.type === 'has_ability' && node.ability_code) {
    collect({ code: node.ability_code, type: 'ability' })
  }
  if (node.type === 'has_ability_tag' && node.tag_code) {
    collect({ code: node.tag_code, type: 'tag' })
  }
  if (node.type === 'has_tag' && node.tag_code) {
    collect({ code: node.tag_code, type: 'tag' })
  }
  if (node.type === 'characteristic_value' && node.characteristic_code) {
    collect({ code: node.characteristic_code, type: 'characteristic' })
  }
  if (node.type === 'resource_limit' && node.resource_code) {
    collect({ code: node.resource_code, type: 'resource' })
  }
}

function walkGrant(
  grant: any,
  collect: (ref: RefExpectation) => void
): void {
  if (!grant || typeof grant !== 'object') return
  if (grant.type === 'characteristic' && grant.characteristic_code) {
    collect({ code: grant.characteristic_code, type: 'characteristic' })
  }
  if (grant.type === 'characteristic_modify' && grant.characteristic_code) {
    collect({ code: grant.characteristic_code, type: 'characteristic' })
    walkFormula(grant.amount, 'characteristic', collect)
  }
  if (grant.type === 'resource' && grant.resource_code) {
    collect({ code: grant.resource_code, type: 'resource' })
  }
  if (grant.type === 'resource_limit_change' && grant.resource_code) {
    collect({ code: grant.resource_code, type: 'resource' })
    walkFormula(grant.amount, 'resource', collect)
  }
  if (grant.type === 'ability' && grant.ability_code) {
    collect({ code: grant.ability_code, type: 'ability' })
  }
  if (grant.type === 'tag' && grant.tag_code) {
    collect({ code: grant.tag_code, type: 'tag' })
  }
  if (grant.type === 'item' && grant.item_code) {
    collect({ code: grant.item_code, type: 'item' })
  }
}

function collectSpecRefs(
  rule: Rule,
  collect: (ref: RefExpectation) => void
): void {
  const spec = rule.spec
  if (!spec || typeof spec !== 'object') return

  switch (rule.type) {
    case 'item':
      for (const code of spec.special_rule_codes ?? []) {
        collect({ code, type: 'simple' })
      }
      walkFormula(spec.weapon?.block_profile?.efficiency, 'characteristic', collect)
      for (const profile of spec.weapon?.weapon_profiles ?? []) {
        walkFormula(profile.damage?.formula, 'characteristic', collect)
        if (profile.damage?.damage_type_code) {
          collect({ code: profile.damage.damage_type_code, type: 'damage_type' })
        }
        walkFormula(profile.penetration, 'characteristic', collect)
        walkFormula(profile.distance, 'characteristic', collect)
        walkFormula(profile.range, 'characteristic', collect)
      }
      for (const slot of spec.armor?.resistance_slots ?? []) {
        if (slot.damage_type_code) {
          collect({ code: slot.damage_type_code, type: 'damage_type' })
        }
      }
      for (const limit of spec.armor?.characteristic_limits ?? []) {
        if (limit.characteristic_code) {
          collect({ code: limit.characteristic_code, type: 'characteristic' })
        }
        walkFormula(limit.limit, 'characteristic', collect)
      }
      break

    case 'ability':
      if (spec.parent_ability_code) {
        collect({ code: spec.parent_ability_code, type: 'ability' })
      }
      for (const zone of Object.keys(spec.zones ?? {})) {
        if (zone) collect({ code: zone, type: 'points' })
      }
      for (const entry of spec.requirements ?? []) {
        for (const req of entry.requirements ?? []) {
          walkRequirements(req, collect)
        }
      }
      for (const entry of spec.grants ?? []) {
        for (const grant of entry.grants ?? []) {
          walkGrant(grant, collect)
        }
      }
      for (const cost of spec.action_costs ?? []) {
        if (cost.resource_code) {
          collect({ code: cost.resource_code, type: 'resource' })
        }
      }
      for (const step of spec.process?.steps ?? []) {
        for (const cost of step.costs ?? []) {
          if (cost.resource_code) {
            collect({ code: cost.resource_code, type: 'resource' })
          }
        }
      }
      for (const component of spec.spell?.components ?? []) {
        if (component.type === 'material' && component.item_code) {
          collect({ code: component.item_code, type: 'item' })
        }
      }
      break

    case 'characteristic':
      // formula в виде строки "min(memory, reasoning)" — проверяем упомянутые коды
      if (typeof spec.formula === 'string') {
        const refs = spec.formula.match(/[a-zа-яё][a-zа-яё0-9-]*/gi) ?? []
        for (const ref of refs) {
          if (ref === 'min' || ref === 'max') continue
          collect({ code: ref, type: 'characteristic' })
        }
      }
      break

    case 'race':
      if (spec.parent_race_code) {
        collect({ code: spec.parent_race_code, type: 'species' })
      }
      for (const c of spec.characteristics ?? []) {
        if (c?.characteristic_code) {
          collect({ code: c.characteristic_code, type: 'characteristic' })
        }
      }
      for (const ref of spec.abilities ?? []) {
        if (ref?.ability_code) {
          collect({ code: ref.ability_code, type: 'ability' })
        }
      }
      break

    case 'species':
      if (spec.parent_race_code) {
        collect({ code: spec.parent_race_code, type: 'species' })
      }
      for (const ref of spec.abilities ?? []) {
        if (ref?.ability_code) {
          collect({ code: ref.ability_code, type: 'ability' })
        }
      }
      break

    default:
      break
  }
}

/**
 * Проверяет, что все строковые ссылки (*_code) в правилах указывают на существующие
 * правила нужного типа. Возвращает массив ошибок (пустой = валидно).
 */
export function validateRuleReferences(
  rules: Rule[],
  tags: { code: string; name: string }[]
): ReferenceError[] {
  const byCode = new Map<string, Rule>()
  for (const rule of rules) byCode.set(rule.code, rule)
  const tagCodes = new Set(tags.map(t => t.code))

  const errors: ReferenceError[] = []
  const refs: { rule: Rule; ref: RefExpectation }[] = []

  for (const rule of rules) {
    collectSpecRefs(rule, (ref) => {
      refs.push({ rule, ref })
    })
  }

  for (const { rule, ref } of refs) {
    if (ref.type === 'tag') {
      if (!tagCodes.has(ref.code)) {
        errors.push({ ruleName: rule.name, ruleCode: rule.code, refCode: ref.code, expectedType: ref.type })
      }
      continue
    }
    const target = byCode.get(ref.code)
    if (!target) {
      errors.push({ ruleName: rule.name, ruleCode: rule.code, refCode: ref.code, expectedType: ref.type })
      continue
    }
    if (target.type !== ref.type) {
      errors.push({ ruleName: rule.name, ruleCode: rule.code, refCode: ref.code, expectedType: ref.type })
    }
  }

  return errors
}

export function formatReferenceError(err: ReferenceError): string {
  return `${err.ruleName} → ссылка на "${err.refCode}" (нужен тип «${expectedTypeLabel(err.expectedType)}»)`
}

export interface AbilityStructureError {
  ruleName: string
  ruleCode: string
  message: string
}

function amountValue(amount: unknown): number | null {
  if (typeof amount === 'number') return amount
  if (amount && typeof amount === 'object' && 'base' in amount) {
    const a = amount as { base: unknown }
    return typeof a.base === 'number' ? a.base : null
  }
  return null
}

function hasActionPointCost(costs: { resource_code: string; amount: unknown }[]): boolean {
  return costs.some(c => c.resource_code === 'action-points' && (amountValue(c.amount) ?? 0) >= 1)
}

function abilityTypeFromRule(rule: Rule, tags: { id: number; code: string }[]): AbilityType | null {
  const spec = rule.spec
  if (spec?.type) return spec.type as AbilityType
  const codes = (rule.tagIds ?? [])
    .map(id => tags.find(t => t.id === id)?.code)
    .filter((c): c is string => !!c)
  return resolveAbilityTypeFromTags(codes)
}

/**
 * Структурная валидация способностей по типу: обязательная ОД-стоимость,
 * шаги/переходы процесса, сложность и компоненты заклинания.
 */
export function validateAbilityStructure(
  rules: Rule[],
  tags: { id: number; code: string; name: string }[]
): AbilityStructureError[] {
  const errors: AbilityStructureError[] = []
  const tagCodes = new Set(tags.map(t => t.code))

  for (const rule of rules) {
    if (rule.type !== 'ability') continue
    const spec = rule.spec
    if (!spec || typeof spec !== 'object') continue
    const type = abilityTypeFromRule(rule, tags)
    if (!type) continue

    if (type === 'action' || type === 'spell') {
      if (!hasActionPointCost(spec.action_costs ?? [])) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: 'действие требует минимум 1 ОД (стоимость в «Очки Действий» ≥ 1)',
        })
      }
    }

    if (type === 'process') {
      const steps: { code?: string; name?: string; costs?: { resource_code: string; amount: unknown }[] }[] =
        spec.process?.steps ?? []
      if (steps.length < 2) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: 'процесс должен содержать минимум 2 шага',
        })
      }
      const stepCodes = new Set(steps.filter(s => s.code).map(s => s.code))
      for (const step of steps) {
        if (!hasActionPointCost(step.costs ?? [])) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: `шаг «${step.name || step.code}» требует минимум 1 ОД`,
          })
        }
      }
      if (spec.process?.start_step_code && !stepCodes.has(spec.process.start_step_code)) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: `начальный шаг «${spec.process.start_step_code}» не существует`,
        })
      }
      const transition = spec.process?.transition
      if (transition?.mode === 'custom') {
        for (const edge of transition.edges ?? []) {
          if (edge.from && !stepCodes.has(edge.from)) {
            errors.push({
              ruleName: rule.name,
              ruleCode: rule.code,
              message: `переход: шаг «${edge.from}» не существует`,
            })
          }
          if (edge.to && !stepCodes.has(edge.to)) {
            errors.push({
              ruleName: rule.name,
              ruleCode: rule.code,
              message: `переход: шаг «${edge.to}» не существует`,
            })
          }
        }
      }
    }

    if (type === 'spell') {
      if (!spec.spell?.difficulty) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: 'заклинание требует сложность сотворения',
        })
      }
      for (const component of spec.spell?.components ?? []) {
        if (component.type === 'material' && component.item_code && !tagCodes.has(component.item_code)) {
          const exists = rules.some(r => r.code === component.item_code && r.type === 'item')
          if (!exists) {
            errors.push({
              ruleName: rule.name,
              ruleCode: rule.code,
              message: `материальный компонент ссылается на отсутствующий предмет «${component.item_code}»`,
            })
          }
        }
      }
    }
  }

  return errors
}

export function formatAbilityStructureError(err: AbilityStructureError): string {
  return `${err.ruleName} → ${err.message}`
}

export interface RaceStructureError {
  ruleName: string
  ruleCode: string
  message: string
}

function duplicateCodes(codes: (string | null | undefined)[]): string[] {
  const seen = new Set<string>()
  const dups = new Set<string>()
  for (const c of codes) {
    if (!c) continue
    if (seen.has(c)) dups.add(c)
    seen.add(c)
  }
  return Array.from(dups)
}

/** Структурная валидация рас: стоимость, пустые/дубли коды, уровни закупки. */
export function validateRaceStructure(rules: Rule[]): RaceStructureError[] {
  const errors: RaceStructureError[] = []

  for (const rule of rules) {
    if (rule.type !== 'race') continue
    const spec = rule.spec
    if (!spec || typeof spec !== 'object') continue

    if (typeof spec.cost_os !== 'number' || !Number.isInteger(spec.cost_os)) {
      errors.push({
        ruleName: rule.name,
        ruleCode: rule.code,
        message: 'стоимость расы (cost_os) должна быть целым числом',
      })
    }

    const characteristics: any[] = spec.characteristics ?? []
    for (const code of duplicateCodes(characteristics.map((c: any) => c?.characteristic_code))) {
      errors.push({
        ruleName: rule.name,
        ruleCode: rule.code,
        message: `характеристика «${code}» указана несколько раз`,
      })
    }
    for (const c of characteristics) {
      const label = c?.characteristic_code || 'без кода'
      if (!c?.characteristic_code) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: 'у характеристики не указан код',
        })
      }
      if (c?.mode === 'purchased') {
        const costs: unknown[] = (c.purchase ?? []).map((l: any) => l?.cost)
        for (const cost of costs) {
          if (typeof cost !== 'number' || cost < 1) {
            errors.push({
              ruleName: rule.name,
              ruleCode: rule.code,
              message: `уровень закупки «${label}»: стоимость должна быть ≥ 1`,
            })
          }
        }
        for (const cost of duplicateCodes(costs.map(String))) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: `уровень закупки «${label}»: стоимость ${cost} указана несколько раз`,
          })
        }
      }
    }

    const abilities: any[] = spec.abilities ?? []
    for (const code of duplicateCodes(abilities.map((a: any) => a?.ability_code))) {
      errors.push({
        ruleName: rule.name,
        ruleCode: rule.code,
        message: `способность «${code}» указана несколько раз`,
      })
    }
    for (const a of abilities) {
      if (!a?.ability_code) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: 'у способности не указан код',
        })
      }
    }
  }

  return errors
}

/** Структурная валидация видов/подвидов: пустые/дубли кодов способностей. */
export function validateSpeciesStructure(rules: Rule[]): RaceStructureError[] {
  const errors: RaceStructureError[] = []

  for (const rule of rules) {
    if (rule.type !== 'species') continue
    const spec = rule.spec
    if (!spec || typeof spec !== 'object') continue

    const abilities: any[] = spec.abilities ?? []
    for (const code of duplicateCodes(abilities.map((a: any) => a?.ability_code))) {
      errors.push({
        ruleName: rule.name,
        ruleCode: rule.code,
        message: `способность «${code}» указана несколько раз`,
      })
    }
    for (const a of abilities) {
      if (!a?.ability_code) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: 'у способности не указан код',
        })
      }
    }
  }

  return errors
}

/**
 * Ищет цикл в цепочке видов (species) через parent_race_code. Возвращает строку цикла
 * вида «a → b → a» или null. Расы не участвуют (родитель расы — всегда species).
 */
export function findSpeciesCycle(rules: Rule[]): string | null {
  const byCode = new Map<string, Rule>()
  for (const r of rules) {
    if (r.type === 'species') byCode.set(r.code, r)
  }

  const color = new Map<string, number>() // 0 white, 1 gray, 2 black

  function visit(code: string, stack: string[]): string | null {
    const state = color.get(code) ?? 0
    if (state === 1) {
      const start = stack.indexOf(code)
      return [...stack.slice(start), code].join(' → ')
    }
    if (state === 2) return null

    color.set(code, 1)
    stack.push(code)

    const rule = byCode.get(code)
    const parent = rule?.spec?.parent_race_code
    if (parent && byCode.has(parent)) {
      const cycle = visit(parent, stack)
      if (cycle) return cycle
    }

    stack.pop()
    color.set(code, 2)
    return null
  }

  for (const code of byCode.keys()) {
    if ((color.get(code) ?? 0) !== 0) continue
    const cycle = visit(code, [])
    if (cycle) return cycle
  }

  return null
}

export function formatRaceStructureError(err: RaceStructureError): string {
  return `${err.ruleName} → ${err.message}`
}

export function formatSpeciesCycle(cycle: string): string {
  return `Цикл в цепочке видов: ${cycle}`
}
