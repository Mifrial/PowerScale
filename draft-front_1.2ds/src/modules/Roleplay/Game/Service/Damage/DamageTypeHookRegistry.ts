import type { DamageTypeHookPhase } from '@/modules/Roleplay/Game/Dto/DamageTypeHook';

export interface DamageTypeHookHandler {
  mechanicCode: string;
  version: string;
  phase: DamageTypeHookPhase;
  extraDiceFromSrDivisor?: number;
  efficiencyDelta?: number;
  defaultWoundMultiplier?: number;
}

export class DamageTypeHookRegistry {
  private readonly byKey = new Map<string, DamageTypeHookHandler>();

  register(handler: DamageTypeHookHandler): void {
    this.byKey.set(`${handler.mechanicCode}@${handler.version}`, handler);
  }

  resolve(mechanicCode: string, version: string): DamageTypeHookHandler | undefined {
    return this.byKey.get(`${mechanicCode}@${version}`);
  }
}
