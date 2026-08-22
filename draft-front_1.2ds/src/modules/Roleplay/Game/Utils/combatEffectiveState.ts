import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';

/** Лимит ресурса в базовых пунктах его собственной размерной шкалы (база + Σ дельт бонусов). */
export function resourceLimitBase(resource: ResourceValue): number {
  const bonuses = resource.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0);

  return resource.base.base + bonuses;
}

/** Ресурсы листа с применёнными переопределениями current из оверлея. */
export function effectiveResources(version: CharacterVersion, overlay: GameCombatOverlay | null): ResourceValue[] {
  if (!overlay || overlay.resources.length === 0) return version.resources;

  return version.resources.map((resource) => {
    const override = overlay.resources.find((item) => item.ruleId === resource.ruleId);
    if (!override) return resource;

    return { ...resource, current: { ...override.current } };
  });
}

/**
 * Эффективные состояния в бою: оверлей (авторитетный список, засеян из версии при первой мутации)
 * или версия. `updatedAt === ''` — реального оверлея нет (пустая запись), берём версию.
 */
export function effectiveStates(version: CharacterVersion, overlay: GameCombatOverlay | null): CharacterStateValue[] {
  if (!overlay || overlay.updatedAt === '') return version.states.map((state) => ({ ...state }));

  return overlay.states.map((state) => ({ ...state }));
}

/** Сравнение списков состояний по содержимому (для «есть ли изменения»). */
export function statesEqual(a: CharacterStateValue[], b: CharacterStateValue[]): boolean {
  return JSON.stringify(a) === JSON.stringify(b);
}
