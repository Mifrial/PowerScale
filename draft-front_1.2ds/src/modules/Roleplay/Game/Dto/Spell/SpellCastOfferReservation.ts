import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Reservation ресурсов spell-offer до принятия или отмены предложения. */
export interface SpellCastOfferReservation {
  reservationId: string;
  offerId: number;
  casterKey: CombatEntityKey;
  reservedActionCost: number;
  trainingDifficultyDelta: number;
  reservedPendingSignature: string | null;
  status: 'reserved' | 'committed' | 'released';
}
