import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec'
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft'
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType'
import { ABILITY_SPEC_FIELDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_SPEC_FIELDS'
import { ABILITY_TYPE_PRECEDENCE } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_PRECEDENCE'
import { ABILITY_TYPE_DISTINCTIVE_KEYWORD } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_DISTINCTIVE_KEYWORD'
import { ABILITY_TYPE_SPECIFIC_FIELDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_SPECIFIC_FIELDS'

export class AbilitySpecService {
  constructor(
    private manifest: Record<AbilityType, readonly (keyof AbilitySpecDraft)[]>,
    private typePrecedence: AbilityType[],
    private distinctiveTag: Record<AbilityType, string>,
    private specificFields: (keyof AbilitySpecDraft)[],
  ) {}

  resolveTypeFromKeywords(keywords: string[]): AbilityType | null {
    const set = new Set(keywords)
    for (const type of this.typePrecedence) {
      if (set.has(this.distinctiveTag[type])) return type
    }
    return null
  }

  /**
   * Оставляет в спеке только поля, релевантные типу (по манифесту), и проставляет type.
   * Применяется на границе эмита (specToEmit): при смене типа черновые поля редактора
   * НЕ чистятся, но в сохранённый результат мусор не попадает.
   */
  prune(spec: AbilitySpecDraft, type: AbilityType): AbilitySpec {
    const allowed = new Set<keyof AbilitySpecDraft>(this.manifest[type])
    const out: AbilitySpecDraft = { ...spec, type }
    for (const key of this.specificFields) {
      if (!allowed.has(key)) {
        delete out[key]
      }
    }
    return out as AbilitySpec
  }
}

export const abilitySpecService = new AbilitySpecService(
  ABILITY_SPEC_FIELDS,
  ABILITY_TYPE_PRECEDENCE,
  ABILITY_TYPE_DISTINCTIVE_KEYWORD,
  ABILITY_TYPE_SPECIFIC_FIELDS,
)
