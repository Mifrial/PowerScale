import type { InjuryProcedure } from '@/modules/Roleplay/Game/Dto/InjuryProcedure';

/** Реестр процедур увечья: code@version → алгоритм версии. */
export class InjuryProcedureRegistry {
  private readonly byKey = new Map<string, InjuryProcedure>();

  register(procedure: InjuryProcedure): void {
    this.byKey.set(`${procedure.code}@${procedure.version}`, procedure);
  }

  resolve(code: string, version: string): InjuryProcedure | undefined {
    return this.byKey.get(`${code}@${version}`);
  }
}
