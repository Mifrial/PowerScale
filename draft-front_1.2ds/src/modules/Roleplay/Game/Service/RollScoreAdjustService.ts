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
}
