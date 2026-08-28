import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CurrentSpeed } from '@/modules/Roleplay/Game/Dto/CurrentSpeed';
import { MovementStateService } from '@/modules/Roleplay/Game/Service/MovementStateService';

const movementStateService = new MovementStateService();
const states = new Map<number, Map<CombatEntityKey, CurrentSpeed>>();

export async function getCurrentSpeed(gameId: number, entityKey: CombatEntityKey): Promise<CurrentSpeed> {
  return structuredClone(states.get(gameId)?.get(entityKey) ?? movementStateService.zero());
}

export async function setCurrentSpeed(
  gameId: number,
  entityKey: CombatEntityKey,
  currentSpeed: CurrentSpeed,
): Promise<CurrentSpeed> {
  const gameStates = states.get(gameId) ?? new Map<CombatEntityKey, CurrentSpeed>();
  gameStates.set(entityKey, structuredClone(currentSpeed));
  states.set(gameId, gameStates);

  return structuredClone(currentSpeed);
}
