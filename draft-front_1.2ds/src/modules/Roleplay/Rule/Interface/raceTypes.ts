import type { DimensionalNumber } from './abilityTypes'
import type { Rule } from './types'

export type RaceCharacteristicMode = 'fixed' | 'purchased'

/** Уровень закупки характеристики: «за N ОС → значение» (пропуски просто не перечисляются). */
export interface RacePurchaseLevel {
  cost: number
  value: DimensionalNumber
}

export interface RaceCharacteristic {
  characteristic_code: string
  mode: RaceCharacteristicMode
  /** fixed — фикс. база; purchased — минимум (значение за 0 ОС). */
  base: DimensionalNumber
  /** Только при mode='purchased': лестница закупки. */
  purchase?: RacePurchaseLevel[]
}

export interface RaceAbilityRef {
  ability_code: string
  /** true = бесплатная/авто, false = доступная (расовая/видовая). */
  automatic: boolean
}

/** Раса (type='race') — играбельная, терминальная точка цепочки Вид → … → Раса. */
export interface RaceSpec {
  parent_race_code: string | null
  cost_os: number
  characteristics: RaceCharacteristic[]
  abilities: RaceAbilityRef[]
}

/** Вид/Подвид (type='species') — узел дерева рас; контента не несёт, кроме наследуемых способностей. */
export interface SpeciesSpec {
  parent_race_code: string | null
  abilities: RaceAbilityRef[]
}

export function createEmptyRaceSpec(): RaceSpec {
  return {
    parent_race_code: null,
    cost_os: 0,
    characteristics: [],
    abilities: [],
  }
}

export function createEmptySpeciesSpec(): SpeciesSpec {
  return {
    parent_race_code: null,
    abilities: [],
  }
}

export interface InheritedAbilityRef extends RaceAbilityRef {
  /** Название вида/подвида, из которого способность наследуется. */
  fromName: string
}

/**
 * Собирает наследуемые способности из цепочки предков-видов (обход parent_race_code вверх).
 * Порядок: ближний предок → дальний. При цикле обход останавливается (visited-set).
 */
export function collectInheritedAbilities(
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
  return [...own, ...collectInheritedAbilities(parent, rulesByCode, seen)]
}
