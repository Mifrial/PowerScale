import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';

const sessions = new Map<number, Map<CombatEntityKey, ProcessSession>>();

export async function fetchProcessSessions(
  gameId: number,
  _signal?: AbortSignal,
): Promise<Record<CombatEntityKey, ProcessSession>> {
  return Object.fromEntries(sessions.get(gameId) ?? []);
}

export async function setProcessSession(
  gameId: number,
  entityKey: CombatEntityKey,
  session: ProcessSession | null,
  _signal?: AbortSignal,
): Promise<ProcessSession | null> {
  const gameSessions = sessions.get(gameId) ?? new Map<CombatEntityKey, ProcessSession>();
  if (session) gameSessions.set(entityKey, { ...session });
  else gameSessions.delete(entityKey);
  sessions.set(gameId, gameSessions);

  return session ? { ...session } : null;
}
