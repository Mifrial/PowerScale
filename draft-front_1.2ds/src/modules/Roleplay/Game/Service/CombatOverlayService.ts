import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';

export class CombatOverlayService {
  /** Более новый снимок оверлея по `updatedAt` (пустая метка — ещё не трогали, проигрывает любой записи). */
  newerCombatOverlay(current: GameCombatOverlay, incoming: GameCombatOverlay): GameCombatOverlay {
    if (!incoming.updatedAt) return current;
    if (!current.updatedAt) return incoming;

    return incoming.updatedAt >= current.updatedAt ? incoming : current;
  }

  /** Свести список с сервера с уже показанными оверлеями, не откатывая более новую локальную правку. */
  preferNewerCombatOverlays(current: GameCombatOverlay[], incoming: GameCombatOverlay[]): GameCombatOverlay[] {
    const shown = new Map(current.map((item) => [item.entityKey, item]));

    return incoming.map((item) => {
      const previous = shown.get(item.entityKey);

      return previous ? this.newerCombatOverlay(previous, item) : item;
    });
  }

  /** Заменить оверлей участника новым снимком (новый массив — чтобы Vue пересчитал карточку). */
  replaceCombatOverlay(current: GameCombatOverlay[], next: GameCombatOverlay): GameCombatOverlay[] {
    const index = current.findIndex((item) => item.entityKey === next.entityKey);
    if (index < 0) return [...current, next];

    return current.map((item, i) => (i === index ? next : item));
  }
}
