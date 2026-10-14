/** Числовой бой PHP для команд P4. */
export class GamePhpBattleContext {
  battleId: number | null = null;
  battleVersion = 1;
  sheetVersionByKey: Record<string, number> = {};
  lastDebt = false;

  rememberBattle(battleId: number, version: number): void {
    this.battleId = battleId;
    this.battleVersion = version;
  }

  rememberSheet(entityKey: string, actualVersion: number): void {
    this.sheetVersionByKey[entityKey] = actualVersion;
  }

  rememberDebt(debt: boolean, version?: number): void {
    this.lastDebt = debt;
    if (typeof version === 'number') this.battleVersion = version;
  }
}
