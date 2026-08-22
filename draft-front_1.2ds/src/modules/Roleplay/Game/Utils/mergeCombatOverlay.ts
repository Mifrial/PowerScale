import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import { resourceLimitBase } from '@/modules/Roleplay/Game/Utils/combatEffectiveState';

/**
 * Применяет поля оверлея боя на версию листа (CD-4): current ресурсов (кламп к лимиту актуальной
 * версии) и список состояний. Возвращает новую версию, исходную не мутирует. Используется при
 * «Остановить сессию» (построение pendingVersion) и при approve (мерж на актуальную версию —
 * standalone-правки остальных полей сохраняются).
 */
export function mergeCombatOverlay(version: CharacterVersion, overlay: GameCombatOverlay): CharacterVersion {
  const resources = version.resources.map((resource) => {
    const override = overlay.resources.find((item) => item.ruleId === resource.ruleId);
    if (!override) return resource;
    const clamped = Math.max(0, Math.min(resourceLimitBase(resource), override.current.base));

    return { ...resource, current: { base: clamped, size: resource.current.size } };
  });

  return {
    ...version,
    resources,
    states: overlay.states.map((state) => ({ ...state })),
  };
}
