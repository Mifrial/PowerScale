import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';

/** Конверт действия game.runAction. */
export interface GamePhpRunActionRequest {
  gameId: number;
  battleId: number;
  idempotencyKey: string;
  expectedVersion: number;
  action: {
    phase: 'declare' | 'respond';
    kind: 'action' | 'strike' | 'wound';
    code?: string;
    actor?: GamePhpParticipant;
    context: Record<string, unknown>;
  };
}
