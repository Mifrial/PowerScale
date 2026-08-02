import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType'
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType'
import type { AbilitySpecService } from '@/modules/Roleplay/Rule/Service/Spec/AbilitySpecService'
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Spec/AbilitySpecService'
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec'
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula'
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement'
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant'
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec'
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec'
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Dto/Race/SpeciesSpec'
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec'

export type ReferenceTargetType = RuleType | 'keyword'

export interface ReferenceError {
  ruleName: string
  ruleCode: string
  refCode: string
  expectedType: ReferenceTargetType
}

export interface AbilityStructureError {
  ruleName: string
  ruleCode: string
  message: string
}

export interface RaceStructureError {
  ruleName: string
  ruleCode: string
  message: string
}

interface RefExpectation {
  code: string
  type: ReferenceTargetType
}

export class RuleValidationService {
  constructor(private abilitySpec: AbilitySpecService) {}

  expectedTypeLabel(type: ReferenceTargetType): string {
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
      source: 'источник',
      keyword: 'признак',
    }
    return labels[type]
  }

  /**
   * Проверяет, что все строковые ссылки (*_code) в правилах указывают на существующие
   * правила нужного типа. Возвращает массив ошибок (пустой = валидно).
   */
  validateRuleReferences(
    rules: Rule[],
    keywords: { code: string; name: string }[]
  ): ReferenceError[] {
    const byCode = new Map<string, Rule>()
    for (const rule of rules) byCode.set(rule.code, rule)
    const keywordCodes = new Set(keywords.map(t => t.code))

    const errors: ReferenceError[] = []
    const refs: { rule: Rule; ref: RefExpectation }[] = []

    for (const rule of rules) {
      this.collectSpecRefs(rule, (ref) => {
        refs.push({ rule, ref })
      })
    }

    for (const { rule, ref } of refs) {
      if (ref.type === 'keyword') {
        if (!keywordCodes.has(ref.code)) {
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

  formatReferenceError(err: ReferenceError): string {
    return `${err.ruleName} → ссылка на "${err.refCode}" (нужен тип «${this.expectedTypeLabel(err.expectedType)}»)`
  }

  /**
   * Структурная валидация способностей по типу: обязательная ОД-стоимость,
   * шаги/переходы процесса, сложность и компоненты заклинания.
   */
  validateAbilityStructure(
    rules: Rule[],
    keywords: { id: number; code: string; name: string }[]
  ): AbilityStructureError[] {
    const errors: AbilityStructureError[] = []
    const keywordCodes = new Set(keywords.map(t => t.code))

    for (const rule of rules) {
      if (rule.type !== 'ability') continue
      const spec = rule.spec as AbilitySpec | undefined
      if (!spec) continue
      const type = this.abilityTypeFromRule(rule, keywords)
      if (!type) continue

      if (type === 'action' || type === 'spell') {
        if (!hasActionPointCost('action_costs' in spec ? spec.action_costs : [])) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: 'действие требует минимум 1 ОД (стоимость в «Очки Действий» ≥ 1)',
          })
        }
      }

      if (type === 'process') {
        const steps = 'process' in spec ? spec.process?.steps ?? [] : []
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
        const startStep = 'process' in spec ? spec.process?.start_step_code : undefined
        if (startStep && !stepCodes.has(startStep)) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: `начальный шаг «${startStep}» не существует`,
          })
        }
        const transition = 'process' in spec ? spec.process?.transition : undefined
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
        const spell = 'spell' in spec ? spec.spell : undefined
        if (!spell?.difficulty) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: 'заклинание требует сложность сотворения',
          })
        }
        for (const component of spell?.components ?? []) {
          if (component.type === 'material' && component.item_code && !keywordCodes.has(component.item_code)) {
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

  /** Структурная валидация рас: стоимость, пустые/дубли коды, уровни закупки. */
  validateRaceStructure(rules: Rule[]): RaceStructureError[] {
    const errors: RaceStructureError[] = []

    for (const rule of rules) {
      if (rule.type !== 'race') continue
      const spec = rule.spec as RaceSpec | undefined
      if (!spec) continue

      if (typeof spec.cost_os !== 'number' || !Number.isInteger(spec.cost_os)) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: 'стоимость расы (cost_os) должна быть целым числом',
        })
      }

      const characteristics = spec.characteristics ?? []
      for (const code of duplicateCodes(characteristics.map(c => c.characteristic_code))) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: `характеристика «${code}» указана несколько раз`,
        })
      }
      for (const c of characteristics) {
        const label = c.characteristic_code || 'без кода'
        if (!c.characteristic_code) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: 'у характеристики не указан код',
          })
        }
        if (c.mode === 'purchased') {
          const costs = (c.purchase ?? []).map(l => l.cost)
          for (const cost of costs) {
            if (cost < 1) {
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

      const abilities = spec.abilities ?? []
      for (const code of duplicateCodes(abilities.map(a => a.ability_code))) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: `способность «${code}» указана несколько раз`,
        })
      }
      for (const a of abilities) {
        if (!a.ability_code) {
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
  validateSpeciesStructure(rules: Rule[]): RaceStructureError[] {
    const errors: RaceStructureError[] = []

    for (const rule of rules) {
      if (rule.type !== 'species') continue
      const spec = rule.spec as SpeciesSpec | undefined
      if (!spec) continue

      const abilities = spec.abilities ?? []
      for (const code of duplicateCodes(abilities.map(a => a.ability_code))) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: `способность «${code}» указана несколько раз`,
        })
      }
      for (const a of abilities) {
        if (!a.ability_code) {
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
  findSpeciesCycle(rules: Rule[]): string | null {
    const byCode = new Map<string, Rule>()
    for (const r of rules) {
      if (r.type === 'species') byCode.set(r.code, r)
    }

    const color = new Map<string, number>() // 0 white, 1 gray, 2 black

    const visit = (code: string, stack: string[]): string | null => {
      const state = color.get(code) ?? 0
      if (state === 1) {
        const start = stack.indexOf(code)
        return [...stack.slice(start), code].join(' → ')
      }
      if (state === 2) return null

      color.set(code, 1)
      stack.push(code)

      const rule = byCode.get(code)
      const parent = (rule?.spec as SpeciesSpec | undefined)?.parent_race_code
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

  formatSpeciesCycle(cycle: string): string {
    return `Цикл в цепочке видов: ${cycle}`
  }

  private abilityTypeFromRule(rule: Rule, keywords: { id: number; code: string }[]): AbilityType | null {
    const spec = rule.spec
    if (spec && 'type' in spec && spec.type) return spec.type as AbilityType
    const codes = (rule.keywordIds ?? [])
      .map(id => keywords.find(t => t.id === id)?.code)
      .filter((c): c is string => !!c)
    return this.abilitySpec.resolveTypeFromKeywords(codes)
  }

  private collectSpecRefs(
    rule: Rule,
    collect: (ref: RefExpectation) => void
  ): void {
    const spec = rule.spec
    if (!spec || typeof spec !== 'object') return

    switch (rule.type) {
      case 'item': {
        const item = spec as ItemSpec
        for (const code of item.special_rule_codes ?? []) {
          collect({ code, type: 'simple' })
        }
        for (const slot of item.weapon?.block_profile?.resistances ?? []) {
          if (slot.source_code) {
            collect({ code: slot.source_code, type: 'source' })
          }
        }
        for (const profile of item.weapon?.weapon_profiles ?? []) {
          this.walkFormula(profile.damage?.formula, 'characteristic', collect)
          if (profile.damage?.damage_type_code) {
            collect({ code: profile.damage.damage_type_code, type: 'damage_type' })
          }
          this.walkFormula(profile.penetration, 'characteristic', collect)
          this.walkFormula(profile.distance, 'characteristic', collect)
          this.walkFormula(profile.range, 'characteristic', collect)
        }
        for (const slot of item.armor?.defense_slots ?? []) {
          if (slot.source_code) {
            collect({ code: slot.source_code, type: 'source' })
          }
        }
        for (const slot of item.armor?.resistance_slots ?? []) {
          if (slot.damage_type_code) {
            collect({ code: slot.damage_type_code, type: 'damage_type' })
          }
          if (slot.source_code) {
            collect({ code: slot.source_code, type: 'source' })
          }
        }
        for (const limit of item.armor?.characteristic_limits ?? []) {
          if (limit.characteristic_code) {
            collect({ code: limit.characteristic_code, type: 'characteristic' })
          }
          this.walkFormula(limit.limit, 'characteristic', collect)
        }
        break
      }

      case 'ability': {
        const ability = spec as AbilitySpec
        if (ability.parent_ability_code) {
          collect({ code: ability.parent_ability_code, type: 'ability' })
        }
        for (const zone of Object.keys(ability.zones ?? {})) {
          if (zone) collect({ code: zone, type: 'points' })
        }
        for (const entry of ability.requirements ?? []) {
          for (const req of entry.requirements ?? []) {
            this.walkRequirements(req, collect)
          }
        }
        for (const entry of ability.grants ?? []) {
          for (const grant of entry.grants ?? []) {
            this.walkGrant(grant, collect)
          }
        }
        if ('action_costs' in ability) {
          for (const cost of ability.action_costs) {
            if (cost.resource_code) {
              collect({ code: cost.resource_code, type: 'resource' })
            }
          }
        }
        if ('process' in ability) {
          for (const step of ability.process?.steps ?? []) {
            for (const cost of step.costs ?? []) {
              if (cost.resource_code) {
                collect({ code: cost.resource_code, type: 'resource' })
              }
            }
          }
        }
        if ('spell' in ability) {
          for (const component of ability.spell?.components ?? []) {
            if (component.type === 'material' && component.item_code) {
              collect({ code: component.item_code, type: 'item' })
            }
          }
        }
        break
      }

      case 'characteristic': {
        const charSpec = spec as CharacteristicSpec
        // formula в виде строки "min(memory, reasoning)" — проверяем упомянутые коды
        if (typeof charSpec.formula === 'string') {
          const refs = charSpec.formula.match(/[a-zа-яё][a-zа-яё0-9-]*/gi) ?? []
          for (const ref of refs) {
            if (ref === 'min' || ref === 'max') continue
            collect({ code: ref, type: 'characteristic' })
          }
        }
        break
      }

      case 'race': {
        const race = spec as RaceSpec
        if (race.parent_race_code) {
          collect({ code: race.parent_race_code, type: 'species' })
        }
        for (const c of race.characteristics ?? []) {
          if (c?.characteristic_code) {
            collect({ code: c.characteristic_code, type: 'characteristic' })
          }
        }
        for (const ref of race.abilities ?? []) {
          if (ref?.ability_code) {
            collect({ code: ref.ability_code, type: 'ability' })
          }
        }
        break
      }

      case 'species': {
        const species = spec as SpeciesSpec
        if (species.parent_race_code) {
          collect({ code: species.parent_race_code, type: 'species' })
        }
        for (const ref of species.abilities ?? []) {
          if (ref?.ability_code) {
            collect({ code: ref.ability_code, type: 'ability' })
          }
        }
        break
      }

      default:
        break
    }
  }

  private walkFormula(
    node: Formula | null | undefined,
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

  private walkRequirements(
    node: Requirement | undefined,
    collect: (ref: RefExpectation) => void
  ): void {
    if (!node || typeof node !== 'object') return
    if (node.type === 'and' || node.type === 'or') {
      if (Array.isArray(node.children)) {
        for (const child of node.children) this.walkRequirements(child, collect)
      }
      return
    }
    if (node.type === 'has_ability' && node.ability_code) {
      collect({ code: node.ability_code, type: 'ability' })
    }
    if (node.type === 'has_ability_keyword' && node.keyword_code) {
      collect({ code: node.keyword_code, type: 'keyword' })
    }
    if (node.type === 'has_keyword' && node.keyword_code) {
      collect({ code: node.keyword_code, type: 'keyword' })
    }
    if (node.type === 'characteristic_value' && node.characteristic_code) {
      collect({ code: node.characteristic_code, type: 'characteristic' })
    }
    if (node.type === 'resource_limit' && node.resource_code) {
      collect({ code: node.resource_code, type: 'resource' })
    }
  }

  private walkGrant(
    grant: Grant | undefined,
    collect: (ref: RefExpectation) => void
  ): void {
    if (!grant || typeof grant !== 'object') return
    if (grant.type === 'characteristic' && grant.characteristic_code) {
      collect({ code: grant.characteristic_code, type: 'characteristic' })
    }
    if (grant.type === 'characteristic_modify' && grant.characteristic_code) {
      collect({ code: grant.characteristic_code, type: 'characteristic' })
      this.walkFormula(grant.amount, 'characteristic', collect)
      if (grant.source_code) {
        collect({ code: grant.source_code, type: 'source' })
      }
    }
    if (grant.type === 'resource' && grant.resource_code) {
      collect({ code: grant.resource_code, type: 'resource' })
    }
    if (grant.type === 'resource_limit_change' && grant.resource_code) {
      collect({ code: grant.resource_code, type: 'resource' })
      this.walkFormula(grant.amount, 'resource', collect)
      if (grant.source_code) {
        collect({ code: grant.source_code, type: 'source' })
      }
    }
    if (grant.type === 'ability' && grant.ability_code) {
      collect({ code: grant.ability_code, type: 'ability' })
    }
    if (grant.type === 'keyword' && grant.keyword_code) {
      collect({ code: grant.keyword_code, type: 'keyword' })
    }
    if (grant.type === 'item' && grant.item_code) {
      collect({ code: grant.item_code, type: 'item' })
    }
  }
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

export const ruleValidationService = new RuleValidationService(abilitySpecService)
