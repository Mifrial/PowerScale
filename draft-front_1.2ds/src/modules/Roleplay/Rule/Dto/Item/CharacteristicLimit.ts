import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';

export interface CharacteristicLimit {
  characteristic_code: string;
  limit: DimensionalFormula;
}
