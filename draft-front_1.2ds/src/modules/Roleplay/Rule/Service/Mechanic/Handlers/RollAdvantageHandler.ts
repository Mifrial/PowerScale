import { ROLL_EVENTS } from '@/modules/Roleplay/Rule/Constant/Mechanic/ROLL_EVENTS';
import type { MechanicHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicHandler';
import type { MechanicPayload } from '@/modules/Roleplay/Rule/Dto/MechanicPayload';
import type { RollMechanicContext } from '@/modules/Roleplay/Rule/Dto/RollMechanicContext';

/**
 * Помехи и преимущества: до броска добавляет |adv| кубов в пул, после броска убирает
 * худшие (преимущество, adv > 0) или лучшие (помеха, adv < 0). В этой игре низкие
 * значения лучше (1 — доп. успех, грань — провал), поэтому худшие = большие значения.
 */
export const rollAdvantageHandler: MechanicHandler<RollMechanicContext> = {
  code: 'advantage_disadvantage',
  version: '2.1.0',
  subscriptions: { [ROLL_EVENTS.pool]: 10, [ROLL_EVENTS.drop]: 10 },
  run(input: { payload: MechanicPayload | null; context: RollMechanicContext; event: string }): void {
    const count = Math.abs(input.context.adv);
    if (count === 0) return;
    const context = input.context;
    if (input.event === ROLL_EVENTS.pool) {
      context.poolSize += count;

      return;
    }
    if (input.event !== ROLL_EVENTS.drop) return;
    const sorted = [...context.rolls].sort((a, b) => (context.adv > 0 ? b - a : a - b));
    const droppedCount = Math.min(count, sorted.length);
    context.droppedRolls = sorted.slice(0, droppedCount);
    context.adjustedRolls = sorted.slice(droppedCount);
    context.applied.push('advantage_disadvantage');
  },
};
