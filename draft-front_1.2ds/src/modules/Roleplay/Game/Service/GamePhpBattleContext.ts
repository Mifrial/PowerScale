import type { GameInitiative } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { GameInitiativeOrderEntry } from '@/modules/Roleplay/Game/Dto/GameInitiativeOrderEntry';

/** Числовой бой PHP для команд P4. */
export class GamePhpBattleContext {
  battleId: number | null = null;
  battleVersion = 1;
  sheetVersionByKey: Record<string, number> = {};
  lastDebt = false;
  lastOrder: GameInitiativeOrderEntry[] | null = null;
  lastScale: GameInitiative | null = null;

  rememberBattle(battleId: number, version: number): void {
    this.battleId = battleId;
    this.battleVersion = version;
  }

  rememberOrder(order: GameInitiativeOrderEntry[]): void {
    this.lastOrder = order;
  }

  rememberScale(scale: GameInitiative): void {
    this.lastScale = scale;
  }

  getScale(): GameInitiative | null {
    return this.lastScale;
  }

  takeOrder(): GameInitiativeOrderEntry[] | null {
    const order = this.lastOrder;
    this.lastOrder = null;

    return order;
  }

  rememberSheet(entityKey: string, actualVersion: number): void {
    this.sheetVersionByKey[entityKey] = actualVersion;
  }

  rememberDebt(debt: boolean, version?: number): void {
    this.lastDebt = debt;
    if (typeof version === 'number') this.battleVersion = version;
  }

  clear(): void {
    this.battleId = null;
    this.battleVersion = 1;
    this.sheetVersionByKey = {};
    this.lastDebt = false;
    this.lastOrder = null;
    this.lastScale = null;
  }
}
