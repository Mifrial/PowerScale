import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec'
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Dto/Race/SpeciesSpec'
import type { InheritedAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/InheritedAbilityRef'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'

export class RaceSpecService {
  createEmptyRace(): RaceSpec {
    return {
      parent_race_code: null,
      cost_os: 0,
      characteristics: [],
      abilities: [],
    }
  }

  createEmptySpecies(): SpeciesSpec {
    return {
      parent_race_code: null,
      abilities: [],
    }
  }

  /**
   * Собирает наследуемые способности из цепочки предков-видов (обход parent_race_code вверх).
   * Порядок: ближний предок → дальний. При цикле обход останавливается (visited-set).
   */
  collectInheritedAbilities(
    startSpeciesCode: string | null,
    rulesByCode: Map<string, Rule>,
    visited?: Set<string>
  ): InheritedAbilityRef[] {
    if (!startSpeciesCode) return []
    const seen = visited ?? new Set<string>()
    if (seen.has(startSpeciesCode)) return []
    seen.add(startSpeciesCode)

    const rule = rulesByCode.get(startSpeciesCode)
    if (!rule || rule.type !== 'species') return []

    const spec = rule.spec as SpeciesSpec | null | undefined
    const own = (spec?.abilities ?? []).map(a => ({ ...a, fromName: rule.name }))
    const parent = spec?.parent_race_code ?? null
    return [...own, ...this.collectInheritedAbilities(parent, rulesByCode, seen)]
  }
}

export const raceSpecService = new RaceSpecService()
