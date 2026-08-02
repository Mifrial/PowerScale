import type { DefenseSlot } from './DefenseSlot'
import type { ResistanceSlot } from './ResistanceSlot'
import type { CharacteristicLimit } from './CharacteristicLimit'

export interface ArmorBlock {
  defense_slots: DefenseSlot[]
  resistance_slots: ResistanceSlot[]
  characteristic_limits: CharacteristicLimit[]
}
