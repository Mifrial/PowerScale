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
    const limit = Math.max(0, resourceLimitBase(resource));
    const sourceBase = override?.current.base ?? resource.current.base;
    const clamped = Math.max(0, Math.min(limit, sourceBase));
    if (!override && clamped === resource.current.base) return resource;

    return { ...resource, current: { base: clamped, size: resource.current.size } };
  });

  return {
    ...version,
    resources,
    states: overlay.states.map((state) => ({ ...state })),
  };
}

/** Более новый снимок оверлея по `updatedAt` (пустая метка — ещё не трогали, проигрывает любой записи). */
export function newerCombatOverlay(current: GameCombatOverlay, incoming: GameCombatOverlay): GameCombatOverlay {
  if (!incoming.updatedAt) return current;
  if (!current.updatedAt) return incoming;

  return incoming.updatedAt >= current.updatedAt ? incoming : current;
}

/** Свести список с сервера с уже показанными оверлеями, не откатывая более новую локальную правку. */
export function preferNewerCombatOverlays(
  current: GameCombatOverlay[],
  incoming: GameCombatOverlay[],
): GameCombatOverlay[] {
  const shown = new Map(current.map((item) => [item.entityKey, item]));

  return incoming.map((item) => {
    const previous = shown.get(item.entityKey);

    return previous ? newerCombatOverlay(previous, item) : item;
  });
}

/** Заменить оверлей участника новым снимком (новый массив — чтобы Vue пересчитал карточку). */
export function replaceCombatOverlay(current: GameCombatOverlay[], next: GameCombatOverlay): GameCombatOverlay[] {
  const index = current.findIndex((item) => item.entityKey === next.entityKey);
  if (index < 0) return [...current, next];

  return current.map((item, i) => (i === index ? next : item));
}
