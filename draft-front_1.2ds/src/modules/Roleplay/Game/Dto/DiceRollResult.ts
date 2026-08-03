import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';

export interface DiceRollResult {
  spec: DiceRollSpec;
  rolls: number[];
  successes: number[];
  adjustedRolls: number[];
  droppedRolls: number[];
  totalSuccesses: number;
}
