import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';

/** Уже объявленная реакция в открытом ударе. */
export interface GamePhpOpenStrikeSupporter {
  actor: GamePhpParticipant;
  ruleCode: string;
  side: string;
  advantage: number;
  source: string;
  weapon?: {
    itemInventoryId: number;
    itemRuleCode: string;
    profileType: string;
  };
}
