import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Dto/CheckOffer';

/** Слагаемые сложности попадания ДБ для [i] броска. */
export interface RangedHitDifficultyBreakdown {
  cover: number;
  defense_result: number;
  reaction: HitDefenseReaction;
  range_size: number;
  distance_ipari: number;
}

/** Исход слоя проверки на броске (чат: простая проверка vs 0). */
export interface DiceRollCheckOutcome {
  check_code: string;
  difficulty: DimensionalNumberValue;
  passed: boolean;
  rating: number;
  ranged_hit?: RangedHitDifficultyBreakdown;
}
