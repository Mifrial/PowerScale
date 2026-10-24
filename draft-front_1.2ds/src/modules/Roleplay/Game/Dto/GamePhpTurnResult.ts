import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';

/** Ответ конца хода / continue / abort / woundAction. */
export interface GamePhpTurnResult {
  pendingActionEffects?: Record<CombatEntityKey, PendingActionEffect[]>;
  version: number;
  battleId?: number;
  sheetVersion?: number;
  activeIndex?: number;
  round?: number;
  /** `continueAction` открыл отложенный удар. */
  opened?: boolean;
  /** Почему отложенный удар не открылся. */
  refused?: string;
  /** Остаток ОД в долге, если удар ещё не открыт. */
  remaining?: number;
}
