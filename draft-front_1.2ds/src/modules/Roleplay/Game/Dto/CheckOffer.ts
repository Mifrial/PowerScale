import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { SpellCastOfferReservation } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastOfferReservation';

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
  spellCastReservation?: SpellCastOfferReservation;
  waitingOn: 'opponent' | 'initiator' | 'covering';
  /** Для групповой атаки: оферта остаётся pending, пока не ответят все цели. */
  waitingOnTargets?: CombatEntityKey[];
  /** Кандидаты Прикрытия, пока waitingOn === 'covering'. */
  waitingOnCoverers?: CombatEntityKey[];
  status: 'pending' | 'accepted' | 'cancelled';
  updatedAt: string;
}
