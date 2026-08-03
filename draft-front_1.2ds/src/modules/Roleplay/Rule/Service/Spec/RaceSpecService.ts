import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec'
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Dto/Race/SpeciesSpec'
import type { InheritedAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/InheritedAbilityRef'
import type { RaceCharacteristic } from '@/modules/Roleplay/Rule/Dto/Race/RaceCharacteristic'
import type { RacePurchaseLevel } from '@/modules/Roleplay/Rule/Dto/Race/RacePurchaseLevel'
import type { RaceCharacteristicMode } from '@/modules/Roleplay/Rule/Enum/Race/RaceCharacteristicMode'
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber'
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

  addCharacteristic(spec: RaceSpec): RaceSpec {
    const entry: RaceCharacteristic = {
      characteristic_code: '',
      mode: 'fixed',
      base: { base: 3, size: 0 },
    }
    return { ...spec, characteristics: [...spec.characteristics, entry] }
  }

  patchCharacteristic(
    spec: RaceSpec,
    index: number,
    key: 'characteristic_code' | 'mode',
    value: string | null,
  ): RaceSpec {
    const resolved = value ?? (key === 'mode' ? 'fixed' : '')
    const list = spec.characteristics.map((c, i) => {
      if (i !== index) return c
      const next: RaceCharacteristic = key === 'characteristic_code'
        ? { ...c, characteristic_code: resolved }
        : { ...c, mode: resolved as RaceCharacteristicMode }
      if (key === 'mode' && resolved === 'fixed') {
        delete next.purchase
      }
      return next
    })
    return { ...spec, characteristics: list }
  }

  patchCharacteristicBase(
    spec: RaceSpec,
    index: number,
    value: DimensionalNumberValue | null,
  ): RaceSpec {
    const list = spec.characteristics.map((c, i) =>
      i === index ? { ...c, base: value ?? { base: 3, size: 0 } } : c,
    )
    return { ...spec, characteristics: list }
  }

  removeCharacteristic(spec: RaceSpec, index: number): RaceSpec {
    return {
      ...spec,
      characteristics: spec.characteristics.filter((_, i) => i !== index),
    }
  }

  addPurchaseLevel(spec: RaceSpec, index: number): RaceSpec {
    const list = spec.characteristics.map((c, i) => {
      if (i !== index) return c
      const purchase: RacePurchaseLevel[] = [
        ...(c.purchase ?? []),
        { cost: 1, value: { base: 3, size: 0 } },
      ]
      return { ...c, purchase }
    })
    return { ...spec, characteristics: list }
  }

  patchPurchaseLevel(
    spec: RaceSpec,
    index: number,
    levelIndex: number,
    key: 'cost' | 'value',
    value: number | DimensionalNumberValue | null,
  ): RaceSpec {
    const list = spec.characteristics.map((c, i) => {
      if (i !== index) return c
      const purchase = (c.purchase ?? []).map((level, j) =>
        j === levelIndex
          ? key === 'cost'
            ? { ...level, cost: value as number }
            : { ...level, value: (value ?? { base: 3, size: 0 }) as DimensionalNumberValue }
          : level,
      )
      return { ...c, purchase }
    })
    return { ...spec, characteristics: list }
  }

  removePurchaseLevel(spec: RaceSpec, index: number, levelIndex: number): RaceSpec {
    const list = spec.characteristics.map((c, i) =>
      i === index
        ? { ...c, purchase: (c.purchase ?? []).filter((_, j) => j !== levelIndex) }
        : c,
    )
    return { ...spec, characteristics: list }
  }

  addAbility(spec: RaceSpec): RaceSpec {
    return {
      ...spec,
      abilities: [...spec.abilities, { ability_code: '', automatic: false }],
    }
  }

  patchAbility(
    spec: RaceSpec,
    index: number,
    key: 'ability_code' | 'automatic',
    value: string | boolean,
  ): RaceSpec {
    const abilities = spec.abilities.map((a, i) =>
      i === index ? { ...a, [key]: value } : a,
    )
    return { ...spec, abilities }
  }

  removeAbility(spec: RaceSpec, index: number): RaceSpec {
    return {
      ...spec,
      abilities: spec.abilities.filter((_, i) => i !== index),
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
