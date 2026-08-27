import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';

const pending = new Map<number, Map<CombatEntityKey, PendingActionEffect[]>>();

export async function fetchPendingActionEffects(
  gameId: number,
  _signal?: AbortSignal,
): Promise<Record<CombatEntityKey, PendingActionEffect[]>> {
  const gamePending = pending.get(gameId);
  if (!gamePending) return {};

  return Object.fromEntries(
    [...gamePending.entries()].map(([key, effects]) => [
      key,
      effects.map((effect) => ({ sourceRuleId: effect.sourceRuleId, effect: { ...effect.effect } })),
    ]),
  );
}

export async function setPendingActionEffects(
  gameId: number,
  entityKey: CombatEntityKey,
  effects: PendingActionEffect[],
  _signal?: AbortSignal,
): Promise<PendingActionEffect[]> {
  const gamePending = pending.get(gameId) ?? new Map<CombatEntityKey, PendingActionEffect[]>();
  gamePending.set(
    entityKey,
    effects.map((effect) => ({ sourceRuleId: effect.sourceRuleId, effect: { ...effect.effect } })),
  );
  pending.set(gameId, gamePending);

  return effects.map((effect) => ({ sourceRuleId: effect.sourceRuleId, effect: { ...effect.effect } }));
}
