export interface GameCombatProcessTransition {
  processId: string | null;
  offerId: number | null;
  status: 'created' | 'awaitingDefense' | 'applied' | 'rejected' | 'closed';
  processStateVersion: number;
}
