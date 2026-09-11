import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';

const byGame = new Map<number, Map<string, ActiveSpell>>();

export async function fetchActiveSpells(gameId: number, _signal?: AbortSignal): Promise<ActiveSpell[]> {
  return [...(byGame.get(gameId)?.values() ?? [])].map((spell) => ({ ...spell }));
}

export async function upsertActiveSpell(
  gameId: number,
  spell: ActiveSpell,
  _signal?: AbortSignal,
): Promise<ActiveSpell> {
  const bucket = byGame.get(gameId) ?? new Map<string, ActiveSpell>();
  const stored = { ...spell, gameId };
  bucket.set(stored.id, stored);
  byGame.set(gameId, bucket);

  return { ...stored };
}

export async function dropActiveSpell(gameId: number, id: string, _signal?: AbortSignal): Promise<void> {
  byGame.get(gameId)?.delete(id);
}
