import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';

export interface CreateCheckOfferData {
  checkCode: string;
  initiator: CombatEntityKey;
  opponent: CombatEntityKey;
  proposal: CheckOfferProposal;
}
