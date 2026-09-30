import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface GameCombatConflict {
  code:
    | 'game_not_playing'
    | 'no_active_session'
    | 'no_active_battle'
    | 'closed_session'
    | 'closed_battle'
    | 'closed_process'
    | 'invalid_actor'
    | 'invalid_target'
    | 'invalid_offer'
    | 'participant_not_eligible'
    | 'stale_version'
    | 'command_fingerprint_conflict'
    | 'invalid_command';
  currentSessionStateVersion: number | null;
  currentBattleStateVersion: number | null;
  currentProcessStateVersion: number | null;
  currentEntityVersions: Record<CombatEntityKey, number>;
  retriable: boolean;
}
