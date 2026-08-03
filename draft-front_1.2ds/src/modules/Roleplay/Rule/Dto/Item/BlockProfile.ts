import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber'
import type { ResistanceSlot } from '@/modules/Roleplay/Rule/Dto/Item/ResistanceSlot'

export interface BlockProfile {
  efficiency: DimensionalNumberValue
  defense: number
  resistances: ResistanceSlot[]
}
