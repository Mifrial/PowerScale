import { ROLL_EVENTS } from '@/modules/Roleplay/Mechanic/init';
import type { MechanicHandler } from '@/modules/Roleplay/Mechanic/Interface/MechanicHandler';
import type { MechanicPayload } from '@/modules/Roleplay/Mechanic/Dto/MechanicPayload';
import type { RollMechanicContext } from '@/modules/Roleplay/Game/Dto/RollMechanicContext';
import { aggregateSourceDeltasService, advantageDropService } from '@/modules/Roleplay/Rule/init';

/**
 * Помехи и преимущества: до броска добавляет |net| кубов в пул, после броска убирает
 * худшие (преимущество) или лучшие (помеха).
 */
export const rollAdvantageHandler: MechanicHandler<RollMechanicContext> = {
  code: 'advantage_disadvantage',
  version: '2.1.0',
  subscriptions: { [ROLL_EVENTS.pool]: 10, [ROLL_EVENTS.drop]: 10 },
  run(input: { payload: MechanicPayload | null; context: RollMechanicContext; event: string }): void {
    const adv = aggregateSourceDeltasService.netSourceDelta(input.context.advantages);
    const count = Math.abs(adv);
    if (count === 0) return;
    const context = input.context;
    if (input.event === ROLL_EVENTS.pool) {
      context.poolSize += count;

      return;
    }
    if (input.event !== ROLL_EVENTS.drop) return;
    const dropped = advantageDropService.applyAdvantageDrop(context.rolls, adv);
    context.droppedRolls = dropped.dropped;
    context.adjustedRolls = dropped.kept;
    context.applied.push('advantage_disadvantage');
  },
};
