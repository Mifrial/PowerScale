import type { DimensionalNumberValue } from '@/modules/Core/Engine/Components/DimensionalNumberInput.vue'
import type { Formula } from '@/modules/Roleplay/Rule/Components/FormulaInput.vue'

export interface WeaponProfile {
  type: 'strike' | 'throw' | 'shoot'
  distance: Formula
  range: Formula | null
  damage: { formula: Formula; damage_type_code: string | null }
  penetration: Formula
  accuracy: DimensionalNumberValue
}

export interface BlockProfile {
  efficiency: DimensionalNumberValue
  defense: number
  resistances: ResistanceSlot[]
}

export interface DefenseSlot {
  defense: number
  durability: number
  source_id: number | null
}

export interface ResistanceSlot {
  damage_type_code: string | null
  value: number
  durability: number
  source_id: number | null
}

export interface CharacteristicLimit {
  characteristic_code: string
  limit: Formula
}

export interface WeaponBlock {
  min_strength: DimensionalNumberValue | null
  block_profile: BlockProfile | null
  weapon_profiles: WeaponProfile[]
}

export interface ArmorBlock {
  defense_slots: DefenseSlot[]
  resistance_slots: ResistanceSlot[]
  characteristic_limits: CharacteristicLimit[]
}

export interface ShieldBlock {
  min_strength: DimensionalNumberValue | null
  block: BlockProfile
}

/** Общие поля предмета (не подтип-специфичные). */
export interface ItemSpecBase {
  category: 'money' | 'equipment' | 'other'
  cost_gm: number | null
  weight: DimensionalNumberValue | null
  special_rule_codes: string[]
  innate?: boolean
}

/** Черновой слой редактора: все блоки подтипов опциональны. */
export interface ItemSpecDraft extends ItemSpecBase {
  weapon?: WeaponBlock
  armor?: ArmorBlock
  shield?: ShieldBlock
}

/** Чистый слой: блоки подтипов опциональны, но типизированы. */
export interface ItemSpec extends ItemSpecBase {
  weapon?: WeaponBlock
  armor?: ArmorBlock
  shield?: ShieldBlock
}

export const ITEM_SUBTYPES: { label: string; value: string; field: keyof ItemSpecDraft }[] = [
  { label: 'Оружие', value: 'weapon', field: 'weapon' },
  { label: 'Броня', value: 'armor', field: 'armor' },
  { label: 'Щит', value: 'shield', field: 'shield' },
]

/** Манифест: какой подтип управляет каким блоком предмета. */
export const ITEM_SUBTYPE_FIELDS: Record<string, (keyof ItemSpecDraft)[]> = {
  weapon: ['weapon'],
  armor: ['armor'],
  shield: ['shield'],
}

const ITEM_BLOCK_FIELDS: (keyof ItemSpecDraft)[] = ['weapon', 'armor', 'shield']

/**
 * Оставляет в спеке только блоки активных подтипов (по манифесту).
 * Применяется на границе эмита (specToEmit): при смене подтипа черновые поля
 * редактора НЕ чистятся, но в сохранённый результат мусор не попадает.
 */
export function pruneItemSpecBySubtypes(spec: ItemSpecDraft, subtypes: string[]): ItemSpec {
  const active = new Set<keyof ItemSpecDraft>()
  for (const subtype of subtypes) {
    for (const field of ITEM_SUBTYPE_FIELDS[subtype] ?? []) {
      active.add(field)
    }
  }
  const out: ItemSpecDraft = { ...spec }
  for (const field of ITEM_BLOCK_FIELDS) {
    if (!active.has(field)) {
      delete out[field]
    }
  }
  return out as ItemSpec
}
