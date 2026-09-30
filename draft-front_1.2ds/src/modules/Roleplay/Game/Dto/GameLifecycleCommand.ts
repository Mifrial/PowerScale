import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

interface GameLifecycleCommandBase {
  commandId: string;
  gameId: number;
  payload: Record<string, string | number | boolean | null>;
}

interface StartSessionCommand extends GameLifecycleCommandBase {
  commandType: 'startSession';
  sessionId: null;
  battleId: null;
  participantEntityKeys?: CombatEntityKey[];
  participantAdmission?: 'provided' | 'currentEligible';
}

interface StartBattleCommand extends GameLifecycleCommandBase {
  commandType: 'startBattle';
  sessionId: string;
  battleId: null;
  expectedSessionStateVersion: number;
}

interface EndBattleCommand extends GameLifecycleCommandBase {
  commandType: 'endBattle';
  sessionId: string;
  battleId: string;
  expectedSessionStateVersion: number;
  expectedBattleStateVersion: number;
}

interface StopSessionCommand extends GameLifecycleCommandBase {
  commandType: 'stopSession';
  sessionId: string;
  battleId: null;
  expectedSessionStateVersion: number;
}

export type GameLifecycleCommand = StartSessionCommand | StartBattleCommand | EndBattleCommand | StopSessionCommand;
