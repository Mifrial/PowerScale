import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';

/** Лимит ресурса в базовых пунктах его собственной размерной шкалы (база + Σ дельт бонусов). */
export function resourceLimitBase(resource: ResourceValue): number {
  const bonuses = resource.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0);

  return resource.base.base + bonuses;
}

/** Ресурсы actual-листа; overlay больше не содержит sheet overrides. */
export function effectiveResources(version: CharacterVersion, _overlay: GameCombatOverlay | null): ResourceValue[] {
  return version.resources.map((resource) => ({ ...resource, current: { ...resource.current } }));
}

/** Состояния actual-листа. */
export function effectiveStates(version: CharacterVersion, _overlay: GameCombatOverlay | null): CharacterStateValue[] {
  return version.states.map((state) => ({ ...state }));
}

/** Сравнение списков состояний по содержимому (для «есть ли изменения»). */
export function statesEqual(a: CharacterStateValue[], b: CharacterStateValue[]): boolean {
  return JSON.stringify(a) === JSON.stringify(b);
}
