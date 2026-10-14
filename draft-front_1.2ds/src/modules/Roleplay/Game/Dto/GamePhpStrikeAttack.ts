import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';

/** Тело атаки game.declareStrike. */
export interface GamePhpStrikeAttack {
  attacker: GamePhpParticipant;
  defender: GamePhpParticipant;
  actionRuleCode: string;
  itemInventoryId: number;
  itemRuleCode: string;
  profileType: string;
  profileIndex: number;
  ammunitionInventoryId?: number;
  chosenAmounts: Record<string, unknown>;
}
