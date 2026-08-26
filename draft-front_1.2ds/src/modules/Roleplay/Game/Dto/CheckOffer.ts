import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';

/**
 * Оферта pairwise-проверки (согласование сторон). Кубы падают только после accept.
 * Живой транспорт — SSE; сейчас мок.
 */
export interface CheckOffer {
  id: number;
  gameId: number;
  checkCode: string;
  initiator: CombatEntityKey;
  opponent: CombatEntityKey;
  proposal: CheckOfferProposal;
  waitingOn: 'opponent' | 'initiator';
  status: 'pending' | 'accepted' | 'cancelled';
  updatedAt: string;
}
