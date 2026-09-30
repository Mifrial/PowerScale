import type { GameCleanupSummary } from '@/modules/Roleplay/Game/Dto/GameCleanupSummary';
import type { GameStateSnapshot } from '@/modules/Roleplay/Game/Dto/GameStateSnapshot';

interface GameLifecycleTransition {
  type:
    | 'session_created'
    | 'session_already_active'
    | 'battle_created'
    | 'battle_already_active'
    | 'battle_ended'
    | 'session_stopped';
  sessionId: string | null;
  battleId: string | null;
  endedBattleId: string | null;
  cleanup: GameCleanupSummary | null;
}

interface GameLifecycleConflict {
  code:
    | 'game_not_playing'
    | 'no_active_session'
    | 'no_active_battle'
    | 'participant_not_eligible'
    | 'closed_session'
    | 'closed_battle'
    | 'invalid_command_type'
    | 'stale_version'
    | 'command_fingerprint_conflict';
  currentSessionStateVersion: number | null;
  currentBattleStateVersion: number | null;
  retriable: boolean;
}

interface GameLifecycleTransitionResult {
  kind: 'transition';
  commandId: string;
  status: 'created' | 'already_active' | 'ended' | 'stopped';
  snapshot: GameStateSnapshot;
  transition: GameLifecycleTransition;
}

interface GameLifecycleConflictResult {
  kind: 'conflict';
  commandId: string;
  status: 'rejected';
  snapshot: GameStateSnapshot;
  conflict: GameLifecycleConflict;
}

export type GameLifecycleResult = GameLifecycleTransitionResult | GameLifecycleConflictResult;
