import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { CheckOffer } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CurrentSpeed } from '@/modules/Roleplay/Game/Dto/CurrentSpeed';
import type { GameInitiative } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { GameCombatProcessState } from '@/modules/Roleplay/Game/Dto/GameCombatProcessState';

export interface GameBattleState {
  gameId: number;
  sessionId: string;
  battleId: string;
  status: 'active';
  battleStateVersion: number;
  initiative: GameInitiative | null;
  processSessions: Record<CombatEntityKey, ProcessSession>;
  combatProcesses: Record<string, GameCombatProcessState>;
  checkOffers: CheckOffer[];
  pendingActionEffects: Record<CombatEntityKey, PendingActionEffect[]>;
  committedActionSessions: Record<CombatEntityKey, CommittedActionSession>;
  activeSpells: ActiveSpell[];
  currentSpeed: Record<CombatEntityKey, CurrentSpeed>;
  quickRolls: Record<CombatEntityKey, string[]>;
  battleMarkers: Record<string, string | number | boolean | null>;
  startedAt: string;
  updatedAt: string;
}
