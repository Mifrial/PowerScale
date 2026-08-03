import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec'
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft'
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType'
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement'
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant'
import type { SpellComponent } from '@/modules/Roleplay/Rule/Dto/Ability/SpellComponent'
import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration'
import { ABILITY_SPEC_FIELDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_SPEC_FIELDS'
import { ABILITY_TYPE_PRECEDENCE } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_PRECEDENCE'
import { ABILITY_TYPE_DISTINCTIVE_KEYWORD } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_DISTINCTIVE_KEYWORD'
import { ABILITY_TYPE_SPECIFIC_FIELDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_SPECIFIC_FIELDS'
import { ABILITY_TYPE_KEYWORDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_KEYWORDS'

export class AbilitySpecService {
  constructor(
    private manifest: Record<AbilityType, readonly (keyof AbilitySpecDraft)[]>,
    private typePrecedence: AbilityType[],
    private distinctiveTag: Record<AbilityType, string>,
    private specificFields: (keyof AbilitySpecDraft)[],
    private typeKeywords: Record<AbilityType, string[]>,
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

  updateReqLevel(spec: AbilitySpecDraft, levelIndex: number, value: number): AbilitySpecDraft {
    return {
      ...spec,
      requirements: spec.requirements.map((entry, i) =>
        i === levelIndex ? { ...entry, level: Number(value) || 1 } : entry,
      ),
    }
  }

  updateRequirementLevelRequirements(
    spec: AbilitySpecDraft,
    levelIndex: number,
    reqs: Requirement[],
  ): AbilitySpecDraft {
    return {
      ...spec,
      requirements: spec.requirements.map((entry, i) =>
        i === levelIndex ? { ...entry, requirements: reqs } : entry,
      ),
    }
  }

  removeRequirementLevel(spec: AbilitySpecDraft, levelIndex: number): AbilitySpecDraft {
    return { ...spec, requirements: spec.requirements.filter((_, i) => i !== levelIndex) }
  }

  addRequirementLevel(spec: AbilitySpecDraft): AbilitySpecDraft {
    return { ...spec, requirements: [...spec.requirements, { level: 1, requirements: [] }] }
  }

  updateGrantLevel(spec: AbilitySpecDraft, levelIndex: number, value: number): AbilitySpecDraft {
    return {
      ...spec,
      grants: spec.grants.map((entry, i) =>
        i === levelIndex ? { ...entry, level: Number(value) || 1 } : entry,
      ),
    }
  }

  updateGrant(
    spec: AbilitySpecDraft,
    levelIndex: number,
    grantIndex: number,
    grant: Grant,
  ): AbilitySpecDraft {
    const grants = spec.grants.map((entry, i) => {
      if (i !== levelIndex) return entry
      const levelGrants = entry.grants.map((g, j) => (j === grantIndex ? grant : g))
      return { ...entry, grants: levelGrants }
    })
    return { ...spec, grants }
  }

  removeGrant(spec: AbilitySpecDraft, levelIndex: number, grantIndex: number): AbilitySpecDraft {
    const grants = spec.grants.map((entry, i) => {
      if (i !== levelIndex) return entry
      const levelGrants = entry.grants.filter((_, j) => j !== grantIndex)
      return { ...entry, grants: levelGrants }
    })
    return { ...spec, grants }
  }

  addGrant(spec: AbilitySpecDraft, levelIndex: number): AbilitySpecDraft {
    const grants = spec.grants.map((entry, i) => {
      if (i !== levelIndex) return entry
      const levelGrants = [...entry.grants, this.createEmptyGrant('keyword')]
      return { ...entry, grants: levelGrants }
    })
    return { ...spec, grants }
  }

  removeGrantLevel(spec: AbilitySpecDraft, levelIndex: number): AbilitySpecDraft {
    return { ...spec, grants: spec.grants.filter((_, i) => i !== levelIndex) }
  }

  addGrantLevel(spec: AbilitySpecDraft): AbilitySpecDraft {
    return { ...spec, grants: [...spec.grants, { level: 1, grants: [] }] }
  }

  ensureActionCost(spec: AbilitySpecDraft, isSpell: boolean): AbilitySpecDraft {
    const hasOd = spec.action_costs.some(c => c.resource_code === 'action-points')
    if (hasOd) return spec
    return {
      ...spec,
      action_costs: [
        ...spec.action_costs,
        {
          resource_code: 'action-points',
          amount: 1,
          label: isSpell ? 'Сотворение' : undefined,
        },
      ],
    }
  }

  syncTypeTags(
    type: AbilityType,
    keywordIds: number[],
    keywords: Array<{ id: number; code: string }>,
  ): number[] {
    const allTypeTagCodes = new Set(Object.values(this.typeKeywords).flat())
    const result = keywordIds.filter(id => {
      const keyword = keywords.find(t => t.id === id)
      return keyword && !allTypeTagCodes.has(keyword.code)
    })
    for (const code of this.typeKeywords[type]) {
      const keyword = keywords.find(t => t.code === code)
      if (keyword && !result.includes(keyword.id)) result.push(keyword.id)
    }
    return result
  }

  createEmptyGrant(type: Grant['type'], defaultSourceCode = ''): Grant {
    switch (type) {
      case 'characteristic':
        return { type: 'characteristic', characteristic_code: '', value: { base: 3, size: 0 } }
      case 'characteristic_modify':
        return {
          type: 'characteristic_modify',
          characteristic_code: '',
          amount: { type: 'fixed', value: 1 },
          source_code: defaultSourceCode,
        }
      case 'resource':
        return { type: 'resource', resource_code: '', limit: 0 }
      case 'resource_limit_change':
        return {
          type: 'resource_limit_change',
          resource_code: '',
          amount: { type: 'fixed', value: 1 },
          source_code: defaultSourceCode,
        }
      case 'ability':
        return { type: 'ability', ability_code: '' }
      case 'keyword':
        return { type: 'keyword', keyword_code: '', remove: false }
      case 'item':
        return { type: 'item', item_code: '' }
    }
  }

  createEmptyRequirement(type: Requirement['type']): Requirement {
    switch (type) {
      case 'has_ability':
        return { type: 'has_ability', ability_code: '' }
      case 'has_ability_keyword':
        return { type: 'has_ability_keyword', keyword_code: '', min_count: 1 }
      case 'has_keyword':
        return { type: 'has_keyword', keyword_code: '' }
      case 'characteristic_value':
        return {
          type: 'characteristic_value',
          characteristic_code: '',
          min: { base: 3, size: 0 },
        }
      case 'resource_limit':
        return { type: 'resource_limit', resource_code: '' }
      case 'and':
        return { type: 'and', children: [this.createEmptyRequirement('has_keyword')] }
      case 'or':
        return { type: 'or', children: [this.createEmptyRequirement('has_keyword')] }
    }
  }

  createEmptySpellComponent(type: SpellComponent['type']): SpellComponent {
    if (type === 'material') {
      return { type: 'material', item_code: undefined, description: undefined }
    }
    return { type, note: undefined }
  }

  createEmptySpellDuration(type: SpellDuration['type']): SpellDuration {
    if (type === 'instant') return { type: 'instant' }
    return { type, difficulty: { base: 3, size: 0 }, action_cost: 0 }
  }
}

export const abilitySpecService = new AbilitySpecService(
  ABILITY_SPEC_FIELDS,
  ABILITY_TYPE_PRECEDENCE,
  ABILITY_TYPE_DISTINCTIVE_KEYWORD,
  ABILITY_TYPE_SPECIFIC_FIELDS,
  ABILITY_TYPE_KEYWORDS,
)
