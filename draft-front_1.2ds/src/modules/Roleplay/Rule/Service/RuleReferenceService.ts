import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec'
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec'
import type { CharacteristicRef } from '@/modules/Roleplay/Rule/Dto/Ability/CharacteristicRef'
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef'
import type { AbilityRef } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityRef'
import type { SourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/SourceRef'

export interface NamedOption {
  code: string
  name: string
}

export class RuleReferenceService {
  speciesOptions(rules: Rule[], excludeRuleId?: string): NamedOption[] {
    return rules
      .filter(r => r.type === 'species' && r.id !== excludeRuleId)
      .map(r => ({ code: r.code, name: r.name }))
  }

  characteristicOptions(rules: Rule[], spaceId: number): CharacteristicRef[] {
    return rules
      .filter(r => r.type === 'characteristic' && r.spaceId === spaceId && !(r.spec as CharacteristicSpec | undefined)?.formula)
      .map(r => ({ code: r.code, name: r.name }))
  }

  resourceOptions(rules: Rule[]): ResourceRef[] {
    return rules
      .filter(r => r.type === 'resource')
      .map(r => ({ code: r.code, name: r.name, isDimensional: !!(r.spec as ResourceSpec | undefined)?.is_dimensional }))
  }

  abilityOptions(rules: Rule[]): AbilityRef[] {
    return rules
      .filter(r => r.type === 'ability')
      .map(r => ({ code: r.code, name: r.name }))
  }

  sourceOptions(rules: Rule[]): SourceRef[] {
    return rules
      .filter(r => r.type === 'source')
      .map(r => ({ code: r.code, name: r.name }))
  }

  zoneOptions(rules: Rule[]): { label: string; value: string }[] {
    return rules
      .filter(r => r.type === 'points')
      .map(r => ({ label: r.name, value: r.code }))
  }

  abilityNameMap(rules: Rule[]): Map<string, string> {
    const map = new Map<string, string>()
    for (const r of rules) {
      if (r.type === 'ability') map.set(r.code, r.name)
    }
    return map
  }
}

export const ruleReferenceService = new RuleReferenceService()
