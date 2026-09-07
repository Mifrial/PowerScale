import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { MechanicBinding } from '@/modules/Roleplay/Mechanic/Dto/MechanicBinding';
import type { ResolveActiveOptions } from '@/modules/Roleplay/Mechanic/Dto/ResolveActiveOptions';
import type { ResolvedMechanic } from '@/modules/Roleplay/Mechanic/Dto/ResolvedMechanic';
import type { MechanicHandlerRegistry } from '@/modules/Roleplay/Mechanic/Service/MechanicHandlerRegistry';

/**
 * Событийный движок механик: `resolveActive` собирает механики через каталог
 * `mechanicId → code@version`; `runEvent` поднимает событие. Не знает семантику и не видит Rule.
 */
export class MechanicEngine {
  constructor(private readonly registry: MechanicHandlerRegistry) {}

  resolveActive(
    bindings: MechanicBinding[],
    mechanics: Mechanic[],
    options: ResolveActiveOptions = {},
  ): ResolvedMechanic[] {
    const byId = new Map(mechanics.map((mechanic) => [mechanic.id, mechanic]));
    const poolByCode = new Map(bindings.map((binding) => [binding.ruleCode, binding]));

    const resolved: ResolvedMechanic[] = [];
    const pushBinding = (binding: MechanicBinding, force: boolean): void => {
      if (binding.mechanicId == null) return;
      const mechanic = byId.get(binding.mechanicId);
      if (!mechanic) return;
      if (!force && options.includeCodes && !options.includeCodes.includes(mechanic.code)) return;
      const handler = this.registry.resolve(mechanic.code, mechanic.version);
      if (!handler) return;
      resolved.push({ handler, payload: binding.mechanicPayload ?? null });
    };

    for (const binding of bindings) pushBinding(binding, false);
    for (const code of options.extraRuleCodes ?? []) {
      const binding = poolByCode.get(code);
      if (binding) pushBinding(binding, true);
    }

    return resolved;
  }

  runEvent<TContext extends object>(event: string, context: TContext, active: ResolvedMechanic[]): void {
    const subscribed = active
      .filter((resolved) => resolved.handler.subscriptions[event] !== undefined)
      .sort((a, b) => (a.handler.subscriptions[event] ?? 0) - (b.handler.subscriptions[event] ?? 0));
    for (const resolved of subscribed) {
      resolved.handler.run({ payload: resolved.payload, context, event });
    }
  }
}
