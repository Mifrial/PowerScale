import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';

export interface HitCheckRoll {
  attacker: DiceRollResult;
  defender: DiceRollResult | null;
}
