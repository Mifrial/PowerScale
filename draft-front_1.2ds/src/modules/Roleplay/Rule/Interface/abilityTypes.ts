export interface DimensionalNumber {
  base: number
  size: number
}

/** Ключ зоны = код очков-правила (type='points'), напр. 'os' | 'ol' | 'or'. */
export type ZoneId = string

export type AbilityCost =
  | { kind: 'array'; levels_cost: number[] }
  | { kind: 'progression'; max_level: number; base_cost: number; step: number }
  | { kind: 'automatic' }

export type Requirement =
  | { type: 'has_ability'; ability_code: string; min_level?: number }
  | { type: 'has_ability_tag'; tag_code: string; min_count: number }
  | { type: 'has_tag'; tag_code: string }
  | { type: 'characteristic_value'; characteristic_code: string; min: DimensionalNumber }
  | { type: 'resource_limit'; resource_code: string; min?: DimensionalNumber | number }
  | { type: 'and'; children: Requirement[] }
  | { type: 'or'; children: Requirement[] }

export type Formula =
  | { type: 'fixed'; value: number }
  | { type: 'characteristic'; characteristic_code: string; modifier: number }
  | { type: 'ability_level'; ability_code: string; multiplier?: number; offset?: number }
  | { type: 'dimensional'; base: number; size: number }

export type AbilityType = 'trait' | 'feature' | 'skill' | 'action' | 'process' | 'spell'

export const ABILITY_TYPE_LABELS: Record<AbilityType, string> = {
  trait: 'Черта',
  feature: 'Особенность',
  skill: 'Навык',
  action: 'Действие',
  process: 'Процесс',
  spell: 'Заклинание',
}

export const ABILITY_TYPE_TAGS: Record<AbilityType, string[]> = {
  trait: ['trait'],
  feature: ['feature'],
  skill: ['skill'],
  action: ['skill', 'action'],
  process: ['skill', 'action', 'process'],
  spell: ['skill', 'magic', 'action', 'spell'],
}

const ABILITY_TYPE_PRECEDENCE: AbilityType[] = ['spell', 'process', 'action', 'skill', 'feature', 'trait']

const ABILITY_TYPE_DISTINCTIVE_TAG: Record<AbilityType, string> = {
  trait: 'trait',
  feature: 'feature',
  skill: 'skill',
  action: 'action',
  process: 'process',
  spell: 'spell',
}

export function resolveAbilityTypeFromTags(tags: string[]): AbilityType | null {
  const set = new Set(tags)
  for (const type of ABILITY_TYPE_PRECEDENCE) {
    if (set.has(ABILITY_TYPE_DISTINCTIVE_TAG[type])) return type
  }
  return null
}

export function isActionLikeType(type: AbilityType): boolean {
  return type === 'action' || type === 'process' || type === 'spell'
}

export interface ProcessStep {
  code: string
  name: string
  description: string
  costs: { resource_code: string; amount: DimensionalNumber | number }[]
}

export type ProcessTransition =
  | { mode: 'chain'; max_shift: number; direction?: 'forward' | 'both' }
  | { mode: 'free' }
  | { mode: 'custom'; edges: { from: string; to: string }[] }

export interface ProcessSpec {
  steps: ProcessStep[]
  start_step_code?: string
  transition: ProcessTransition
  failure?: 'restart_from_first' | 'end_action' | null
}

export type SpellDuration =
  | { type: 'instant' }
  | {
      type: 'refreshable' | 'sustained'
      difficulty: DimensionalNumber
      action_cost: DimensionalNumber | number
      limit?: { value: DimensionalNumber | number; unit: 'turn' | 'minute' | 'hour' }
    }

export type SpellComponent =
  | { type: 'verbal' | 'somatic'; note?: string }
  | { type: 'material'; item_code?: string; description?: string }

export interface SpellSpec {
  difficulty: DimensionalNumber
  duration: SpellDuration
  components: SpellComponent[]
}

export type Grant =
  | { type: 'characteristic'; characteristic_code: string; value: DimensionalNumber; permanent?: boolean }
  | { type: 'characteristic_modify'; characteristic_code: string; amount: Formula; source_id: number; permanent?: boolean }
  | { type: 'resource'; resource_code: string; limit: DimensionalNumber | number; permanent?: boolean }
  | { type: 'resource_limit_change'; resource_code: string; amount: Formula; source_id: number; permanent?: boolean }
  | { type: 'ability'; ability_code: string; permanent?: boolean }
  | { type: 'tag'; tag_code: string; remove?: boolean; permanent?: boolean }
  | { type: 'item'; item_code: string; permanent?: boolean }

export type ActionCost = { resource_code: string; amount: DimensionalNumber | number; label?: string }

/** Общие поля способности (не типоспецифичные). */
export interface AbilitySpecBase {
  zones: Partial<Record<ZoneId, AbilityCost>>
  requirements: { level: number; requirements: Requirement[] }[]
  grants: { level: number; grants: Grant[] }[]
  parent_ability_code: string | null
}

/** Черновой слой редактора: type опционален, типоспецифичные поля могут «висеть» при смене типа. */
export interface AbilitySpecDraft extends AbilitySpecBase {
  type?: AbilityType
  action_costs: ActionCost[]
  process?: ProcessSpec
  spell?: SpellSpec
}

/** Чистый слой — дискриминированный юнион, выдаётся на границе (эмит). */
export type AbilitySpec =
  | (AbilitySpecBase & { type: 'trait' | 'feature' | 'skill' })
  | (AbilitySpecBase & { type: 'action'; action_costs: ActionCost[] })
  | (AbilitySpecBase & { type: 'process'; process: ProcessSpec })
  | (AbilitySpecBase & { type: 'spell'; action_costs: ActionCost[]; spell: SpellSpec })

/** Манифест: какие типоспецифичные поля валидны для каждого типа. */
export const ABILITY_SPEC_FIELDS: Record<AbilityType, readonly (keyof AbilitySpecDraft)[]> = {
  trait: [],
  feature: [],
  skill: [],
  action: ['action_costs'],
  process: ['process'],
  spell: ['action_costs', 'spell'],
}

const ABILITY_TYPE_SPECIFIC_FIELDS: (keyof AbilitySpecDraft)[] = ['action_costs', 'process', 'spell']

/**
 * Оставляет в спеке только поля, релевантные типу (по манифесту), и проставляет type.
 * Применяется на границе эмита (specToEmit): при смене типа черновые поля редактора
 * НЕ чистятся, но в сохранённый результат мусор не попадает.
 */
export function pruneAbilitySpecForType(spec: AbilitySpecDraft, type: AbilityType): AbilitySpec {
  const allowed = new Set<keyof AbilitySpecDraft>(ABILITY_SPEC_FIELDS[type])
  const out: AbilitySpecDraft = { ...spec, type }
  for (const key of ABILITY_TYPE_SPECIFIC_FIELDS) {
    if (!allowed.has(key)) {
      delete out[key]
    }
  }
  return out as AbilitySpec
}

export interface CharacteristicRef {
  code: string
  name: string
}

export interface ResourceRef extends CharacteristicRef {
  isDimensional?: boolean
}

export interface AbilityRef extends CharacteristicRef {}

export interface TagRef extends CharacteristicRef {}

export interface SourceRef {
  id: number
  name: string
}
