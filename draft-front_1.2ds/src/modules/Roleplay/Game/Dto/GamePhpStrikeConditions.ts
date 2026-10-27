import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DistanceValue } from '@/modules/Roleplay/Rule/Dto/Item/DistanceValue';

/** Условия цели открытого PHP-удара. */
export interface GamePhpStrikeConditions {
  distance?: DistanceValue;
  cover?: DimensionalNumberValue;
  flank?: boolean;
  turn?: boolean;
}
