import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
export interface JointCheckRoll {
  left: DiceRollResult;
  right: DiceRollResult;
}
