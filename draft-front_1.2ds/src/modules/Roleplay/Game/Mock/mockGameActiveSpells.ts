import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { GamePhpSustainSpellRequest } from '@/modules/Roleplay/Game/Dto/GamePhpSustainSpellRequest';
import type { GamePhpDropSpellRequest } from '@/modules/Roleplay/Game/Dto/GamePhpDropSpellRequest';
import type { GamePhpActiveSpellResult } from '@/modules/Roleplay/Game/Dto/GamePhpActiveSpellResult';
import type { GamePhpDropSpellResult } from '@/modules/Roleplay/Game/Dto/GamePhpDropSpellResult';

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

export async function sustainActiveSpell(
  body: GamePhpSustainSpellRequest,
  _signal?: AbortSignal,
): Promise<GamePhpActiveSpellResult> {
  const bucket = byGame.get(body.gameId);
  const spell = bucket?.get(body.spellId);
  if (!bucket || !spell) {
    throw new Error('Активное заклинание не найдено');
  }
  const next: ActiveSpell = {
    ...spell,
    sustainPower: body.sustainPower,
    version: (spell.version ?? 1) + 1,
  };
  bucket.set(next.id, next);

  return {
    spell: { ...next },
    actionCost: 0,
    battleId: body.battleId,
    version: body.expectedBattleVersion,
    sheetVersion: body.expectedSheetVersion,
  };
}

export async function dropActiveSpell(
  body: GamePhpDropSpellRequest,
  _signal?: AbortSignal,
): Promise<GamePhpDropSpellResult> {
  byGame.get(body.gameId)?.delete(body.spellId);

  return { dropped: true, battleId: body.battleId, version: body.expectedBattleVersion };
}
