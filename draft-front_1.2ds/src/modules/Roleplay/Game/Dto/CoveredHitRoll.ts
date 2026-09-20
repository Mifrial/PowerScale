import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface CoveredHitRoll {
  attacker: DiceRollResult;
  primaryDefender: DiceRollResult | null;
  coveringDefenders: { key: CombatEntityKey; result: DiceRollResult | null }[];
}
