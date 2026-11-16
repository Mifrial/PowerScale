import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Состояние цепи после попадания: остаток урона и правила прыжка. */
export interface GamePhpSpellChain {
  castId: string;
  ruleCode: string;
  spellCode: string;
  damageTypeCode: string;
  amount: DimensionalNumberValue;
  min: DimensionalNumberValue;
  damageSizePerHop: number;
  sameTarget: string;
  retarget: string;
  spellSr: number;
  lastKey: string;
  hit: string[];
}

/** Попадание прыжка цепи. */
export interface GamePhpSpellChainHop {
  target: { type: 'character' | 'npc'; id: number };
  damage: DimensionalNumberValue | null;
  injury: DimensionalNumberValue | null;
  sheetVersion: number;
}

/** Ответ сервера на прыжок или остановку цепи. */
export interface GamePhpSpellChainResult {
  phase: 'chain' | 'chainStop';
  castId: string;
  battleId: number;
  version: number;
  broke?: boolean;
  hop?: GamePhpSpellChainHop | null;
  chain?: GamePhpSpellChain | null;
}
