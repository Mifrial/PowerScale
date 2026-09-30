import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Ordered, visibility-safe Game update; delivery never executes a mutation. */
export interface GameRealtimeEvent {
  gameId: number;
  eventId: string;
  cursor: number;
  entityKey: CombatEntityKey;
  actualVersion: number;
  changedSections: string[];
  eventKind: 'character.changed' | 'npc.changed';
  sessionId: string | null;
  battleId: string | null;
}
