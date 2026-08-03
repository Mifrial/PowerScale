import type { DefenseSlot } from '@/modules/Roleplay/Rule/Dto/Item/DefenseSlot';
import type { ResistanceSlot } from '@/modules/Roleplay/Rule/Dto/Item/ResistanceSlot';
import type { CharacteristicLimit } from '@/modules/Roleplay/Rule/Dto/Item/CharacteristicLimit';

export interface ArmorBlock {
  defense_slots: DefenseSlot[];
  resistance_slots: ResistanceSlot[];
  characteristic_limits: CharacteristicLimit[];
}
