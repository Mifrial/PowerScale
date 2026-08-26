import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { DeclineOutcome } from '@/modules/Roleplay/Game/Utils/exhaustionCheckMessage';

export interface ApplyExhaustionCheckResult {
  roll: DiceRollResult | null;
  overlay: GameCombatOverlay | null;
  outcome: DeclineOutcome;
  skipped: boolean;
}
