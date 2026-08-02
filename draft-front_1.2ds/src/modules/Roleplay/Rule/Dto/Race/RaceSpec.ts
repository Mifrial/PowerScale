import type { RaceCharacteristic } from './RaceCharacteristic'
import type { RaceAbilityRef } from './RaceAbilityRef'

/** Раса (type='race') — играбельная, терминальная точка цепочки Вид → … → Раса. */
export interface RaceSpec {
  parent_race_code: string | null
  cost_os: number
  characteristics: RaceCharacteristic[]
  abilities: RaceAbilityRef[]
}
