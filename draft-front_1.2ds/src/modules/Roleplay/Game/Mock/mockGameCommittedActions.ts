import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';

const sessions = new Map<number, Map<CombatEntityKey, CommittedActionSession>>();

export async function fetchCommittedActionSessions(
  gameId: number,
  _signal?: AbortSignal,
): Promise<Record<CombatEntityKey, CommittedActionSession>> {
  return Object.fromEntries(sessions.get(gameId) ?? []);
}

export async function setCommittedActionSession(
  gameId: number,
  entityKey: CombatEntityKey,
  session: CommittedActionSession | null,
  _signal?: AbortSignal,
): Promise<CommittedActionSession | null> {
  const gameSessions = sessions.get(gameId) ?? new Map<CombatEntityKey, CommittedActionSession>();
  if (session) gameSessions.set(entityKey, { ...session });
  else gameSessions.delete(entityKey);
  sessions.set(gameId, gameSessions);

  return session ? { ...session } : null;
}
