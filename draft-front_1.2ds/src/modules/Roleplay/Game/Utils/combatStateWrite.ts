import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { combatActionPoints } from '@/modules/Roleplay/Game/Utils/combatCardModel';
import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Utils/applyAttackDamage';

export function stateRuleOf(rules: Rule[], code: string): Rule | undefined {
  return rules.find((rule) => rule.code === code && rule.type === 'state');
}

export function stateIndicesOf(version: CharacterVersion, ruleId: string): number[] {
  const indices: number[] = [];
  version.states.forEach((state, index) => {
    if (state.stateRuleId === ruleId) indices.push(index);
  });

  return indices;
}

export async function removeStatesByCodes(
  gameId: number,
  key: CombatEntityKey,
  version: CharacterVersion,
  rules: Rule[],
  codes: readonly string[],
): Promise<GameCombatOverlay | null> {
  const ids = new Set(codes.map((code) => stateRuleOf(rules, code)?.id).filter((id): id is string => Boolean(id)));
  const indices = version.states
    .map((state, index) => (ids.has(state.stateRuleId) ? index : -1))
    .filter((index) => index >= 0)
    .sort((a, b) => b - a);
  let overlay: GameCombatOverlay | null = null;
  for (const index of indices) {
    overlay = await getGameApi().removeCombatState(gameId, key, index);
  }

  return overlay;
}

export async function addFlagState(
  gameId: number,
  key: CombatEntityKey,
  rules: Rule[],
  code: string,
): Promise<GameCombatOverlay | null> {
  const rule = stateRuleOf(rules, code);
  if (!rule) return null;

  return getGameApi().addCombatState(gameId, key, { stateRuleId: rule.id } as CharacterStateValue);
}

export async function setNumericState(
  gameId: number,
  key: CombatEntityKey,
  version: CharacterVersion,
  rules: Rule[],
  code: string,
  value: number,
): Promise<GameCombatOverlay | null> {
  const rule = stateRuleOf(rules, code);
  if (!rule) return null;
  const spec = rule.spec as StateSpec | undefined;
  const independent = spec?.aggregation === 'independent';
  const indices = stateIndicesOf(version, rule.id);
  if (value <= 0 && !independent) {
    if (indices.length === 0) return null;

    return removeStatesByCodes(gameId, key, version, rules, [code]);
  }
  if (independent) {
    if (value <= 0) return null;

    return getGameApi().addCombatState(gameId, key, { stateRuleId: rule.id, value } as CharacterStateValue);
  }
  if (indices.length > 0) {
    return getGameApi().setCombatStateValue(gameId, key, indices[0], value);
  }

  return getGameApi().addCombatState(gameId, key, { stateRuleId: rule.id, value } as CharacterStateValue);
}

export async function clampCombatActionPoints(
  gameId: number,
  key: CombatEntityKey,
  version: CharacterVersion,
  rules: Rule[],
): Promise<GameCombatOverlay | null> {
  const ap = combatActionPoints(version, rules);
  if (!ap) return null;
  const rule = rules.find((item) => item.code === ACTION_POINTS_CODE && item.type === 'resource');
  if (!rule) return null;
  const resource = version.resources.find((item) => item.ruleId === rule.id);
  if (!resource) return null;
  if (resource.current.base <= ap.max) return null;
  const current: DimensionalNumberValue = { ...resource.current, base: ap.max };

  return getGameApi().setCombatResource(gameId, key, rule.id, current);
}
