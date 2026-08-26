import { ROLL_EVENTS } from '@/modules/Roleplay/Rule/Constant/Mechanic/ROLL_EVENTS';
import type { MechanicHandler } from '@/modules/Roleplay/Rule/Interface/MechanicHandler';
import type { MechanicPayload } from '@/modules/Roleplay/Rule/Dto/MechanicPayload';
import type { RollMechanicContext } from '@/modules/Roleplay/Rule/Dto/RollMechanicContext';
import { applyRollScoreAdjust } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/rollScoreAdjust';

/**
 * Правило «6 и 1»: 1 начисляет дополнительный успех, грань куба снимает один успех
 * (движковое поведение: грань — провал только если не является успехом по сложности).
 */
export const rollSixOneHandler: MechanicHandler<RollMechanicContext> = {
  code: 'six_one_rule',
  version: '4.5.0',
  subscriptions: { [ROLL_EVENTS.score]: 10 },
  run(input: { payload: MechanicPayload | null; context: RollMechanicContext }): void {
    const changed = applyRollScoreAdjust(input.context, 1, -1, true);
    if (changed) input.context.applied.push('six_one_rule');
  },
};
