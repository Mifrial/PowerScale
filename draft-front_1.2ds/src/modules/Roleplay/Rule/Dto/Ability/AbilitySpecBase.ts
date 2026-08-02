import type { ZoneId } from './ZoneId'
import type { AbilityCost } from './AbilityCost'
import type { Requirement } from './Requirement'
import type { Grant } from './Grant'

/** Общие поля способности (не типоспецифичные). */
export interface AbilitySpecBase {
  zones: Partial<Record<ZoneId, AbilityCost>>
  requirements: { level: number; requirements: Requirement[] }[]
  grants: { level: number; grants: Grant[] }[]
  parent_ability_code: string | null
}
