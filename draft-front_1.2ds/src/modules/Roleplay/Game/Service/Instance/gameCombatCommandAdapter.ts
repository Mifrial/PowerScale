import { getGameApi } from '@/modules/Roleplay/Game/init';
import { GameCombatCommandAdapter } from '@/modules/Roleplay/Game/Service/GameCombatCommandAdapter';

export const gameCombatCommandAdapter = new GameCombatCommandAdapter({
  submitCombatCommand: (command, signal) => getGameApi().submitCombatCommand(command, signal),
});
