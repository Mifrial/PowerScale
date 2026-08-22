import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { MechanicPayload } from '@/modules/Roleplay/Rule/Dto/MechanicPayload';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { MechanicHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicHandler';
import type { MechanicHandlerRegistry } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicHandlerRegistry';

/** Механика активного правила, разрешённая до хендлера: code@version + payload. */
export interface ResolvedMechanic {
  handler: MechanicHandler;
  payload: MechanicPayload | null;
}

/** Опции набора активных механик. */
export interface ResolveActiveOptions {
  /** Коды механик «всегда в силе» (срез ревизии фильтруется по ним; undefined — все). */
  includeCodes?: string[];
  /** Коды правил пер-ролл активации (добавляются независимо от includeCodes). */
  extraRuleCodes?: string[];
}

/**
 * Событийный движок механик (обобщение прежнего диспетчера): `resolveActive` собирает
 * механики активных правил (срез ревизии + пер-ролл по кодам правил) через каталог
 * `mechanicId → code@version`; `runEvent` поднимает событие — подписанные хендлеры
 * выполняются в порядке приоритета, мутируя контекст потока. Движок не знает семантику
 * механик: новые механики = правило + хендлер, правок движка не требуют.
 */
export class MechanicEngine {
  constructor(private readonly registry: MechanicHandlerRegistry) {}

  resolveActive(rules: Rule[], mechanics: Mechanic[], options: ResolveActiveOptions = {}): ResolvedMechanic[] {
    const byId = new Map(mechanics.map((mechanic) => [mechanic.id, mechanic]));
    const poolByCode = new Map(rules.map((rule) => [rule.code, rule]));

    const resolved: ResolvedMechanic[] = [];
    const pushRule = (rule: Rule, force: boolean): void => {
      if (rule.mechanicId == null) return;
      const mechanic = byId.get(rule.mechanicId);
      if (!mechanic) return;
      if (!force && options.includeCodes && !options.includeCodes.includes(mechanic.code)) return;
      const handler = this.registry.resolve(mechanic.code, mechanic.version);
      if (!handler) return;
      resolved.push({ handler, payload: rule.mechanic_payload ?? null });
    };

    for (const rule of rules) pushRule(rule, false);
    for (const code of options.extraRuleCodes ?? []) {
      const rule = poolByCode.get(code);
      if (rule) pushRule(rule, true);
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
