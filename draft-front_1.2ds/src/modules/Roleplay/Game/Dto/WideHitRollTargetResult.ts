import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface WideHitRollTargetResult {
  targetKey: CombatEntityKey;
  attacker: DiceRollResult;
  defender: DiceRollResult | null;
}
