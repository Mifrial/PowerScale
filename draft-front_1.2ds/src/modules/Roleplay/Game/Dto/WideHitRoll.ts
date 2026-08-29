import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { WideHitRollTargetResult } from '@/modules/Roleplay/Game/Dto/WideHitRollTargetResult';

export interface WideHitRoll {
  attacker: DiceRollResult;
  targetResults: WideHitRollTargetResult[];
}
