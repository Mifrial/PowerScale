import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';

/** Ответ game.declareCheck: итог и детали броска для чата и окна. */
export interface GamePhpCheckResult {
  processId: number;
  status: string;
  difficulty: DimensionalNumberValue | null;
  roll: DimensionalNumberValue | null;
  success: boolean | null;
  rating: number | null;
  sheetVersion: number | null;
  battleVersion?: number;
  label?: string;
  actorKey?: string;
  faces?: number[];
  adjustedRolls?: number[];
  successes?: number[];
  droppedRolls?: number[];
  dieFaces?: number;
  advantages?: AdvantageModifier[];
  min_success_size?: number | null;
  masteryAdjustments?: AdvantageModifier[];
  efficiencyAdjustments?: AdvantageModifier[];
  efficiency?: DimensionalNumberValue;
  poolSize?: number;
  process_bonus?: { successes: number; rating: number };
}
