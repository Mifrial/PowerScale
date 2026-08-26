import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface RangedHitDifficultyParts {
  cover: number;
  checkResult: number;
  floorResult: number;
  rangeSize: number;
  distanceIpari: number;
  difficulty: DimensionalNumberValue;
}
