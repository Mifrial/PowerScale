import { ROLL_EVENTS } from '@/modules/Roleplay/Rule/Constant/Mechanic/ROLL_EVENTS';
import type { MechanicHandler } from '@/modules/Roleplay/Rule/Interface/MechanicHandler';
import type { MechanicPayload } from '@/modules/Roleplay/Rule/Dto/MechanicPayload';
import type { RollMechanicContext } from '@/modules/Roleplay/Rule/Dto/RollMechanicContext';
import { applyRollScoreAdjust } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/rollScoreAdjust';

/**
 * Активируемая механика (навык, напр. «Критический удар»): поверх прочих механик
 * начисляет ещё один доп. успех за «1» и списывает ещё один за грань (payload-дельты,
 * по умолчанию +1/−1). Входит в активный набор только при применении к конкретному броску.
 */
export const rollCriticalStrikeHandler: MechanicHandler<RollMechanicContext> = {
  code: 'critical_strike',
  version: '1.0.0',
  subscriptions: { [ROLL_EVENTS.score]: 20 },
  run(input: { payload: MechanicPayload | null; context: RollMechanicContext }): void {
    const data = input.payload?.type === 'roll_score_adjust' ? input.payload.data : undefined;
    const changed = applyRollScoreAdjust(input.context, data?.oneDelta ?? 1, data?.faceDelta ?? -1, false);
    if (changed) input.context.applied.push('critical_strike');
  },
};
