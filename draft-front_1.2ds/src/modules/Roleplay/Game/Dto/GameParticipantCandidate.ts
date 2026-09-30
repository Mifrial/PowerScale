import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Лёгкий кандидат для выбора участника Game без загрузки полного листа. */
export interface GameParticipantCandidate {
  entityKey: CombatEntityKey;
  kind: 'character' | 'npc';
  id: number;
  name: string;
  status: 'active';
  availability: 'admissible' | 'session-participant';
}
