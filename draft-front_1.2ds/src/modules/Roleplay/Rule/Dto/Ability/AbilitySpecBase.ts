import type { ZoneId } from '@/modules/Roleplay/Rule/Dto/Ability/ZoneId'
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost'
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement'
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant'

/** Общие поля способности (не типоспецифичные). */
export interface AbilitySpecBase {
  zones: Partial<Record<ZoneId, AbilityCost>>
  requirements: { level: number; requirements: Requirement[] }[]
  grants: { level: number; grants: Grant[] }[]
  parent_ability_code: string | null
}
