/** Событие кадра /api/game/sync. */
export interface GameDeliveryFrameEvent {
  eventId: string;
  cursor: number;
  source: string;
  sourceId: number;
  keys: unknown[];
  battleId?: number;
}
