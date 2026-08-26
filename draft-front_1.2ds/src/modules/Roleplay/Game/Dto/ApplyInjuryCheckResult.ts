import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
export interface ApplyInjuryCheckResult {
  roll: DiceRollResult;
  overlay: GameCombatOverlay | null;
  skipped: boolean;
}
