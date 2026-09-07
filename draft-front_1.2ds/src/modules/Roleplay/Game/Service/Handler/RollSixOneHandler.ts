import { ROLL_EVENTS } from '@/modules/Roleplay/Mechanic/init';
import type { MechanicHandler } from '@/modules/Roleplay/Mechanic/Interface/MechanicHandler';
import type { MechanicPayload } from '@/modules/Roleplay/Mechanic/Dto/MechanicPayload';
import type { RollMechanicContext } from '@/modules/Roleplay/Game/Dto/RollMechanicContext';
import { rollScoreAdjustService } from '@/modules/Roleplay/Game/Service/Instance/rollScoreAdjustService';

/**
 * Правило «6 и 1»: 1 начисляет дополнительный успех, грань куба снимает один успех.
 */
export const rollSixOneHandler: MechanicHandler<RollMechanicContext> = {
  code: 'six_one_rule',
  version: '4.5.0',
  subscriptions: { [ROLL_EVENTS.score]: 10 },
  run(input: { payload: MechanicPayload | null; context: RollMechanicContext }): void {
    const changed = rollScoreAdjustService.apply(input.context, 1, -1, true);
    if (changed) input.context.applied.push('six_one_rule');
  },
};
