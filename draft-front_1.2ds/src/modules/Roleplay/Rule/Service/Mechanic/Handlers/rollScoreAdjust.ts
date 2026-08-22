import type { RollMechanicContext } from '@/modules/Roleplay/Rule/Dto/RollMechanicContext';

/**
 * Общая логика механик «подсчёта»: «1» начисляет `oneDelta` доп. успехов, грань куба —
 * `faceDelta` (правило «6 и 1», Критический удар и др.). `conditionalFace` — грань
 * штрафуется только если она не является успехом по сложности (движковое правило «6 и 1»:
 * шестёрка при сложности ≥ 6 — обычный успех, а не провал). Возвращает, что механика
 * реально изменила бросок (для чипов применённых механик).
 */
export function applyRollScoreAdjust(
  context: RollMechanicContext,
  oneDelta: number,
  faceDelta: number,
  conditionalFace: boolean,
): boolean {
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
