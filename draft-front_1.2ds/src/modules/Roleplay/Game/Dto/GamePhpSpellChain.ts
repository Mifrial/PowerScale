import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Состояние цепи после попадания: остаток урона, успеха и правила прыжка. */
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
  /** Остаток успеха каста; его тратит Сопротивление магии каждой следующей цели. */
  rating: number;
  freeIpari: number | null;
  sizePerExtraIpari: number | null;
  damageMin: DimensionalNumberValue | null;
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

/** Дистанция прыжка в размерных ипари, как у удара. */
export interface GamePhpSpellChainDistance {
  type: 'ipari';
  value: { type: 'literal'; value: DimensionalNumberValue };
}

/** Ответ сервера на прыжок или остановку цепи. */
export interface GamePhpSpellChainResult {
  phase: 'chain' | 'chainStop';
  castId: string;
  battleId: number;
  version: number;
  broke?: boolean;
  /** Цель погасила заклинание Сопротивлением магии. */
  avoided?: boolean;
  /** Сколько успеха каста потратило сопротивление цели. */
  spentRating?: number;
  rating?: number;
  hop?: GamePhpSpellChainHop | null;
  chain?: GamePhpSpellChain | null;
}
