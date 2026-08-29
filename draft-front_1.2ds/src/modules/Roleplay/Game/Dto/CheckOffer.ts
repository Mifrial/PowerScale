import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

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
  /** Для групповой атаки: оферта остаётся pending, пока не ответят все цели. */
  waitingOnTargets?: CombatEntityKey[];
  status: 'pending' | 'accepted' | 'cancelled';
  updatedAt: string;
}
