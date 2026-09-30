import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCombatCommand } from '@/modules/Roleplay/Game/Dto/GameCombatCommand';

export interface GameCombatProcessState {
  processId: string;
  actorKey: CombatEntityKey;
  targetKey: CombatEntityKey;
  action: Extract<GameCombatCommand, { commandType: 'attackDecision' }>['action'];
  offerId: number;
  status: 'awaitingDefense';
  processStateVersion: number;
  createdAt: string;
  updatedAt: string;
}
