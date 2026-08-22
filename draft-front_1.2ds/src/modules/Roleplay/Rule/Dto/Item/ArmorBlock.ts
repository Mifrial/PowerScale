import type { DefenseSlot } from '@/modules/Roleplay/Rule/Dto/Item/DefenseSlot';
import type { ResistanceSlot } from '@/modules/Roleplay/Rule/Dto/Item/ResistanceSlot';
import type { CharacteristicLimit } from '@/modules/Roleplay/Rule/Dto/Item/CharacteristicLimit';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface ArmorBlock {
  defense_slots: DefenseSlot[];
  resistance_slots: ResistanceSlot[];
  characteristic_limits: CharacteristicLimit[];
  /** Максимальная ловкость при экипировании (ограничение проворности сверху). */
  max_agility?: DimensionalNumberValue | null;
  /** Штраф к силе при экипировании (отрицательное число; нет штрафа — null). */
  strength_penalty?: number | null;
}
