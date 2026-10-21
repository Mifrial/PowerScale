import type { GamePhpOpenWideStrikeTarget } from '@/modules/Roleplay/Game/Dto/GamePhpOpenWideStrikeTarget';
import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';

/** Открытый широкий удар PHP из карточки боя. */
export interface GamePhpOpenWideStrike {
  id: number;
  attacker: GamePhpParticipant;
  actionRuleCode: string;
  itemInventoryId: number;
  itemRuleCode: string;
  profileType: string;
  profileIndex: number;
  targets: GamePhpOpenWideStrikeTarget[];
}
