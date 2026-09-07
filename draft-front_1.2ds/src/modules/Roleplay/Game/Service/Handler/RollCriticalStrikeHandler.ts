import { ROLL_EVENTS } from '@/modules/Roleplay/Mechanic/init';
import type { MechanicHandler } from '@/modules/Roleplay/Mechanic/Interface/MechanicHandler';
import type { MechanicPayload } from '@/modules/Roleplay/Mechanic/Dto/MechanicPayload';
import type { RollMechanicContext } from '@/modules/Roleplay/Game/Dto/RollMechanicContext';
import { rollScoreAdjustService } from '@/modules/Roleplay/Game/Service/Instance/rollScoreAdjustService';

/**
 * Критический удар: доп. успех за «1» и списание за грань (payload-дельты).
 */
export const rollCriticalStrikeHandler: MechanicHandler<RollMechanicContext> = {
  code: 'critical_strike',
  version: '1.0.0',
  subscriptions: { [ROLL_EVENTS.score]: 20 },
  run(input: { payload: MechanicPayload | null; context: RollMechanicContext }): void {
    const data = input.payload?.type === 'roll_score_adjust' ? input.payload.data : undefined;
    const changed = rollScoreAdjustService.apply(input.context, data?.oneDelta ?? 1, data?.faceDelta ?? -1, false);
    if (changed) input.context.applied.push('critical_strike');
  },
};
