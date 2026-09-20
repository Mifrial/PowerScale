import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';

export interface CheckOfferStrikeProposal {
  strikeIndex: number;
  targetKey: CombatEntityKey;
  hit: NonNullable<CheckOfferProposal['hit']>;
}
