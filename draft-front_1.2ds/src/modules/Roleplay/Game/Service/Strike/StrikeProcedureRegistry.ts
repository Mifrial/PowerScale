import type { StrikeProcedure } from '@/modules/Roleplay/Game/Dto/StrikeProcedure';

/** Реестр процедур удара: code@version → константы/алгоритм версии. */
export class StrikeProcedureRegistry {
  private readonly byKey = new Map<string, StrikeProcedure>();

  register(procedure: StrikeProcedure): void {
    this.byKey.set(`${procedure.code}@${procedure.version}`, procedure);
  }

  resolve(code: string, version: string): StrikeProcedure | undefined {
    return this.byKey.get(`${code}@${version}`);
  }
}
