import type { RollMechanicContext } from '@/modules/Roleplay/Game/Dto/RollMechanicContext';

/**
 * Подсчёт броска: «1» начисляет oneDelta успехов, грань куба — faceDelta.
 */
export class RollScoreAdjustService {
  apply(context: RollMechanicContext, oneDelta: number, faceDelta: number, conditionalFace: boolean): boolean {
    let changed = false;
    for (let i = 0; i < context.adjustedRolls.length; i++) {
      const value = context.adjustedRolls[i];
      let delta = 0;
      if (value === 1) delta += oneDelta;
      if (value === context.dieFaces && (!conditionalFace || value > context.efficiency)) delta += faceDelta;
      if (delta !== 0) {
        context.successes[i] += delta;
        changed = true;
      }
    }

    return changed;
  }

  /**
   * Подменяет грани, заново считает успехи по эффективности и заново накладывает правило 6 и 1.
   * Имена в appliedMechanics — с движка («Правило 6 и 1»), не коды.
   */
  remap(context: RollMechanicContext, pairs: readonly { from: number; to: number }[]): boolean {
    if (pairs.length === 0) return false;
    const mapped = new Map(pairs.map((pair) => [pair.from, pair.to]));
    let changed = false;
    for (let i = 0; i < context.adjustedRolls.length; i++) {
      const next = mapped.get(context.adjustedRolls[i]) ?? context.adjustedRolls[i];
      if (next === context.adjustedRolls[i]) continue;
      context.adjustedRolls[i] = next;
      changed = true;
    }
    if (!changed) return false;
    for (let i = 0; i < context.adjustedRolls.length; i++) {
      context.successes[i] = context.adjustedRolls[i] <= context.efficiency ? 1 : 0;
    }
    this.apply(context, 1, -1, true);

    return true;
  }
}
