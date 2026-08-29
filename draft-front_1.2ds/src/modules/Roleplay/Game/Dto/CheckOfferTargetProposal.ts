import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';

export interface CheckOfferTargetProposal {
  targetKey: CombatEntityKey;
  hit: NonNullable<CheckOfferProposal['hit']>;
}
