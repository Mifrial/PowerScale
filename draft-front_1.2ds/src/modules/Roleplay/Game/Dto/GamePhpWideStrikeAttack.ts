import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';

/** Тело атаки game.declareWideStrike. */
export interface GamePhpWideStrikeAttack {
  attacker: GamePhpParticipant;
  targets: GamePhpParticipant[];
  actionRuleCode: string;
  itemInventoryId: number;
  itemRuleCode: string;
  profileType: string;
  profileIndex: number;
  ammunitionInventoryId?: number;
  chosenAmounts?: Record<string, unknown>;
}
