import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'
import type { ResistanceSlot } from './ResistanceSlot'

export interface BlockProfile {
  efficiency: DimensionalNumberValue
  defense: number
  resistances: ResistanceSlot[]
}
