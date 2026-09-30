import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Изменение authoritative Character/NPC для Game projection invalidation. */
export interface GameRuntimeEntityChanged {
  gameId: number;
  entityKey: CombatEntityKey;
  actualVersion: number;
  changedSections: string[];
  eventKind: 'character.changed' | 'npc.changed';
  sessionId: string | null;
  battleId: string | null;
}
